<?php
declare(strict_types=1);

require_once __DIR__ . '/capabilities.php';
require_once dirname(__DIR__) . '/classops_modules/audience/auth_store_source.php';
require_once dirname(__DIR__) . '/classops_modules/delivery/delivery_planner.php';
require_once dirname(__DIR__) . '/classops_modules/scheduler/classops_reminder_policy.php';

function classops_stage2_default_audience_spec(): array
{
    return [
        'version'=>'classops-audience-v1',
        'resolutionMode'=>'snapshot',
        'expression'=>['op'=>'whole_cohort'],
        'includeStudentNumbers'=>[],
        'excludeStudentNumbers'=>[],
    ];
}

function classops_stage2_resolve_audience(array $owner, string $cohortKey, $spec, ?string $expectedHash = null): array
{
    if (!is_array($spec) || array_is_list($spec)) classops_domain_error('CLASSOPS_AUDIENCE_INVALID_SPEC', 'تعریف مخاطب باید object باشد.');
    if ($cohortKey === '' || !dent_cohort_exists($cohortKey)) classops_domain_error('CLASSOPS_INVALID_COHORT','ورودی canonical موردنظر وجود ندارد.');
    $source = new DentClassOpsAuthStoreAudienceSource();
    try {
        return classops_audience_resolve_from_source(
            $source,
            $spec,
            $cohortKey,
            classops_stage2_owner_scope($owner, $cohortKey),
            null,
            $expectedHash
        );
    } catch (DentClassOpsAudienceException $exception) {
        $status = property_exists($exception, 'httpStatus') ? (int) $exception->httpStatus : 422;
        classops_domain_error($exception->reasonCode, $exception->getMessage(), $status);
    }
}

function classops_stage2_foundation_audience(array $resolution): array
{
    $spec = $resolution['normalizedSpec'] ?? [];
    $expr = $spec['expression'] ?? [];
    $recipients = is_array($resolution['recipientStudentNumbers'] ?? null) ? $resolution['recipientStudentNumbers'] : [];
    if (($expr['op'] ?? '') === 'whole_cohort'
        && ($spec['includeStudentNumbers'] ?? []) === []
        && ($spec['excludeStudentNumbers'] ?? []) === []) {
        return ['version'=>'classops-audience-placeholder-v1','mode'=>'entire_cohort','refs'=>[]];
    }
    if (($expr['op'] ?? '') === 'students' && count($recipients) >= 1 && count($recipients) <= 500) {
        return [
            'version'=>'classops-audience-placeholder-v1',
            'mode'=>count($recipients) === 1 ? 'single_student' : 'explicit_students',
            'refs'=>array_values($recipients),
        ];
    }
    return [
        'version'=>'classops-audience-placeholder-v1',
        'mode'=>'snapshot',
        'refs'=>['aud_' . substr((string) ($resolution['deterministicHash'] ?? ''), 0, 24)],
    ];
}

function classops_stage2_normalize_destinations($value): array
{
    if ($value === null || $value === []) return ['private_users'];
    if (!is_array($value) || !array_is_list($value) || count($value) > CLASSOPS_STAGE2_MAX_DESTINATIONS) {
        classops_domain_error('CLASSOPS_DESTINATIONS_INVALID', 'فهرست مقصدهای ارسال معتبر نیست.');
    }
    $allowed = ['private_users','class_group','information_channel'];
    $out = [];
    foreach ($value as $entry) {
        $name = trim((string) $entry);
        if (!in_array($name, $allowed, true)) classops_domain_error('CLASSOPS_DESTINATION_UNKNOWN', 'مقصد ارسال شناخته‌شده نیست.');
        $out[$name] = true;
    }
    if ($out === []) classops_domain_error('CLASSOPS_DESTINATION_REQUIRED', 'حداقل یک مقصد ارسال الزامی است.');
    return array_keys($out);
}

function classops_stage2_destination_registry(string $cohortKey): array
{
    $physical = static function (string $kind, string $platform, string $binding) use ($cohortKey): array {
        return ['kind'=>$kind,'cohortId'=>$cohortKey,'platform'=>$platform,'bindingRef'=>$binding];
    };
    return [
        'version'=>CLASSOPS_DESTINATION_REGISTRY_VERSION,
        'id'=>'registry.classops.' . str_replace('-', '_', $cohortKey),
        'cohortId'=>$cohortKey,
        'destinations'=>[
            'private.telegram'=>$physical('private_recipient','telegram','canonical.notification.private'),
            'private.bale'=>$physical('private_recipient','bale','canonical.notification.private'),
            'private_users'=>['kind'=>'logical','cohortId'=>$cohortKey,'routes'=>[['alias'=>'private.telegram'],['alias'=>'private.bale']]],
            'group.telegram'=>$physical('class_group','telegram','class_group'),
            'group.bale'=>$physical('class_group','bale','class_group'),
            'class_group'=>['kind'=>'logical','cohortId'=>$cohortKey,'routes'=>[['alias'=>'group.telegram'],['alias'=>'group.bale']]],
            'channel.telegram'=>$physical('channel','telegram','information_channel'),
            'channel.bale'=>$physical('channel','bale','information_channel'),
            'information_channel'=>['kind'=>'logical','cohortId'=>$cohortKey,'routes'=>[['alias'=>'channel.telegram'],['alias'=>'channel.bale']]],
        ],
    ];
}

function classops_stage2_capability_overrides(): array
{
    $overrides = [];
    $baleChannel = classops_stage2_bool_env('DENT_CLASSOPS_BALE_CHANNEL_SUPPORTED');
    if ($baleChannel !== null) {
        $overrides['bale']['channel_delivery'] = [
            'state'=>$baleChannel ? 'supported' : 'unsupported',
            'evidence'=>'runtime-explicit-config',
        ];
    }
    return $overrides;
}

function classops_stage2_delivery_policy(string $destination): array
{
    $capability = match ($destination) {
        'private_users' => 'private_recipient',
        'class_group' => 'group_delivery',
        'information_channel' => 'channel_delivery',
        default => 'text_message',
    };
    return ['requiredCapabilities'=>['text_message',$capability],'platformMode'=>'independent','allowFallback'=>false];
}

function classops_stage2_item_snapshot_hash(array $item): string
{
    return hash('sha256', classops_canonical_json($item));
}

function classops_stage2_delivery_plan_for_item(
    array $item,
    array $resolution,
    array $destinations,
    string $purpose = 'initial',
    ?string $scheduledAt = null,
    array $previousIntents = []
): array {
    $status=(string)($item['status']??'');
    if (in_array($status,['completed','cancelled','archived'],true)) {
        // The generic delivery domain deliberately rejects terminal items. The
        // integrated Stage2 caller, however, still needs a deterministic empty
        // plan after a completion revision so post-commit reconciliation cannot
        // turn a successful lifecycle mutation into an API error.
        $plans=[];
        foreach ($destinations as $destination) {
            $plans[(string)$destination]=[
                'contractVersion'=>CLASSOPS_DELIVERY_CONTRACT_VERSION,
                'requestedAlias'=>(string)$destination,
                'intents'=>[],
                'outcomes'=>[],
                'blockedReason'=>'item_status_'.$status,
            ];
        }
        return $plans;
    }

    $scheduledAt = $scheduledAt ?? gmdate('Y-m-d\TH:i:s\Z');
    $audHash = (string) ($resolution['deterministicHash'] ?? '');
    $itemRef = [
        'id'=>(string)$item['id'],'revision'=>(int)$item['revision'],'snapshotHash'=>classops_stage2_item_snapshot_hash($item),
        'cohortId'=>(string)$item['cohortKey'],'status'=>$status,
    ];
    $audienceRef = [
        'contractVersion'=>'classops-audience-v1','ref'=>'audience.' . substr($audHash,0,24),
        'version'=>'classops-audience-snapshot-v1','cohortId'=>(string)$item['cohortKey'],'hash'=>$audHash,
    ];
    $availability=['telegram'=>classops_stage2_platform_state('telegram'),'bale'=>classops_stage2_platform_state('bale')];
    $registry=classops_stage2_destination_registry((string)$item['cohortKey']);
    $plans=[];
    foreach ($destinations as $destination) {
        $occurrence=[
            'key'=>'occ_' . hash('sha256', implode('|',[(string)$item['id'],(string)$item['revision'],$destination,$purpose,$scheduledAt])),
            'purpose'=>$purpose,'scheduledAt'=>$scheduledAt,
        ];
        try {
            $plans[$destination]=classops_delivery_plan(
                $registry,$destination,$itemRef,$audienceRef,$occurrence,classops_stage2_delivery_policy($destination),
                $availability,$previousIntents,classops_stage2_capability_overrides()
            );
        } catch (DentClassOpsDeliveryException $exception) {
            classops_domain_error('CLASSOPS_DELIVERY_PLAN_FAILED','برنامه ارسال قابل محاسبه نیست: ' . $exception->getMessage(),422);
        }
    }
    return $plans;
}

function classops_stage2_default_exam_reminder_policy(): array
{
    return [
        'version'=>'classops-reminder-v1','timezone'=>'Asia/Tehran','catchUp'=>['mode'=>'skip','maxAgeSeconds'=>0],
        'rules'=>[
            ['ruleId'=>'exam-t3','type'=>'relative','anchor'=>'startsAt','offsetSeconds'=>-259200,'reason'=>'exam-t3'],
            ['ruleId'=>'exam-t1','type'=>'relative','anchor'=>'startsAt','offsetSeconds'=>-86400,'reason'=>'exam-t1'],
            ['ruleId'=>'exam-night','type'=>'daypart','anchor'=>'startsAt','dayOffset'=>-1,'daypart'=>'night','reason'=>'exam-night-before'],
            ['ruleId'=>'exam-morning','type'=>'daypart','anchor'=>'startsAt','dayOffset'=>0,'daypart'=>'morning','reason'=>'exam-morning-of'],
        ],
    ];
}

function classops_stage2_normalize_reminder_policy($policy, string $itemType): ?array
{
    if ($policy === null || $policy === []) {
        return $itemType === 'exam' ? classops_stage2_default_exam_reminder_policy() : null;
    }
    if (!is_array($policy) || array_is_list($policy)) classops_domain_error('CLASSOPS_REMINDER_POLICY_INVALID','سیاست یادآوری معتبر نیست.');
    try {
        return classops_reminder_normalize_policy($policy,$itemType);
    } catch (DentClassOpsReminderException $exception) {
        classops_domain_error($exception->reasonCode,'سیاست یادآوری معتبر نیست.',422);
    }
}

function classops_stage2_binding_extension(array $resolution, array $destinations, ?array $reminderPolicy, ?string $serviceRef): array
{
    if ($serviceRef !== null) {
        $serviceRef=strtolower(trim($serviceRef));
        if ($serviceRef!=='saba') classops_domain_error('CLASSOPS_SERVICE_REF_INVALID','خدمت یادآوری شناخته‌شده نیست.');
    }
    return [
        'contractVersion'=>CLASSOPS_STAGE2_BINDING_VERSION,'audienceSpec'=>$resolution['normalizedSpec'],
        'audienceResolutionHash'=>(string)$resolution['deterministicHash'],'destinations'=>$destinations,
        'deliveryPolicy'=>['platformMode'=>'independent','allowFallback'=>false],
        'reminderPolicy'=>$reminderPolicy,'serviceRef'=>$serviceRef,'confirmedAt'=>gmdate('Y-m-d\TH:i:s\Z'),
    ];
}

function classops_stage2_validate_binding_extension(array $value, string $itemType): array
{
    classops_assert_known_keys($value,['contractVersion','audienceSpec','audienceResolutionHash','destinations','deliveryPolicy','reminderPolicy','serviceRef','confirmedAt']);
    if (($value['contractVersion']??'')!==CLASSOPS_STAGE2_BINDING_VERSION) classops_domain_error('CLASSOPS_STAGE2_BINDING_VERSION_INVALID','Stage2 binding version is invalid.');
    if (!is_array($value['audienceSpec']??null)||($value['audienceSpec']['version']??'')!=='classops-audience-v1') classops_domain_error('CLASSOPS_STAGE2_AUDIENCE_INVALID','Stage2 audience binding is invalid.');
    if (preg_match('/^[a-f0-9]{64}$/D',(string)($value['audienceResolutionHash']??''))!==1) classops_domain_error('CLASSOPS_STAGE2_AUDIENCE_HASH_INVALID','Stage2 audience hash is invalid.');
    $value['destinations']=classops_stage2_normalize_destinations($value['destinations']??null);
    if ($value['reminderPolicy']!==null) $value['reminderPolicy']=classops_stage2_normalize_reminder_policy($value['reminderPolicy'],$itemType);
    $serviceRef=$value['serviceRef']??null;
    if ($itemType==='service_reminder'&&$serviceRef!=='saba') classops_domain_error('CLASSOPS_REMINDER_SERVICE_REF_REQUIRED','service_reminder Stage2 requires supported serviceRef.');
    if ($itemType!=='service_reminder'&&$serviceRef!==null) classops_domain_error('CLASSOPS_SERVICE_REF_FORBIDDEN','serviceRef is only valid for service_reminder.');
    return $value;
}
