<?php
declare(strict_types=1);

require_once __DIR__ . '/classops_stage2/digests.php';
require_once __DIR__ . '/classops_stage2_scheduler.php';

const CLASSOPS_BOT_SERVICE_MAX_LIST = 50;
const CLASSOPS_BOT_SERVICE_MAX_CALLBACK_PAYLOAD_BYTES = 24000;

function classops_bot_service_action(string $action): bool
{
    return str_starts_with($action, 'classops');
}

function classops_bot_service_platform(array $payload): string
{
    $platform = strtolower(trim((string) ($payload['platform'] ?? '')));
    if (!in_array($platform, ['telegram', 'bale'], true)) {
        classops_domain_error('CLASSOPS_BOT_PLATFORM_INVALID', 'Bot platform is invalid.', 422);
    }
    return $platform;
}

function classops_bot_service_platform_user_id(array $payload): string
{
    $value = trim((string) ($payload['platformUserId'] ?? ''));
    if ($value === '' || strlen($value) > 40 || preg_match('/^-?[0-9]{1,32}$/D', $value) !== 1) {
        classops_domain_error('CLASSOPS_BOT_ACTOR_INVALID', 'Bot actor identity is invalid.', 403);
    }
    return $value;
}

function classops_bot_service_linked_user(array $payload): array
{
    $platform = classops_bot_service_platform($payload);
    $platformUserId = classops_bot_service_platform_user_id($payload);
    $user = dent_bot_linked_user($platform, $platformUserId);
    if (!is_array($user)) {
        classops_domain_error('CLASSOPS_BOT_LINK_REQUIRED', 'Canonical website account link is required.', 403);
    }
    return $user;
}

function classops_bot_service_owner(array $payload): array
{
    $user = classops_bot_service_linked_user($payload);
    if (!classops_stage2_is_owner($user)) {
        classops_domain_error('CLASSOPS_OWNER_REQUIRED', 'ClassOps management is owner-only.', 403);
    }
    return $user;
}

function classops_bot_service_object(array $payload, string $key, bool $required = true): array
{
    if (!array_key_exists($key, $payload)) {
        if (!$required) return [];
        classops_domain_error('CLASSOPS_BOT_OBJECT_REQUIRED', 'Required bot object is missing.');
    }
    $value = $payload[$key];
    if (!is_array($value) || ($value !== [] && array_is_list($value))) {
        classops_domain_error('CLASSOPS_BOT_OBJECT_INVALID', 'Bot object payload is invalid.');
    }
    $encoded = classops_canonical_json($value);
    if (strlen($encoded) > CLASSOPS_BOT_SERVICE_MAX_CALLBACK_PAYLOAD_BYTES) {
        classops_domain_error('CLASSOPS_BOT_PAYLOAD_TOO_LARGE', 'Bot ClassOps payload is too large.', 413);
    }
    return $value;
}

function classops_bot_service_random_ref(string $prefix): string
{
    return $prefix . bin2hex(random_bytes(12));
}

function classops_bot_service_issue_action(array $payload, string $action, array $actionPayload, int $ttl = 900): string
{
    $encoded = classops_canonical_json($actionPayload);
    if (strlen($encoded) > CLASSOPS_BOT_SERVICE_MAX_CALLBACK_PAYLOAD_BYTES) {
        classops_domain_error('CLASSOPS_BOT_PAYLOAD_TOO_LARGE', 'Bot callback payload is too large.', 413);
    }
    return classops_stage2_issue_callback(
        classops_bot_service_platform($payload),
        classops_bot_service_platform_user_id($payload),
        $action,
        $actionPayload,
        $ttl
    );
}

function classops_bot_service_owner_item(array $request, array $owner): array
{
    $id = classops_stage2_require_item_id($request['id'] ?? '');
    $item = classops_get_item($id);
    $audience = classops_stage2_get_audience((string) $item['id'], (int) $item['revision']);
    return [
        'item' => $item,
        'audience' => $audience === null ? null : [
            'resolutionHash' => (string) $audience['resolutionHash'],
            'recipientCount' => count($audience['snapshot']['recipientStudentNumbers'] ?? []),
        ],
    ];
}

function classops_bot_service_item_actions(array $request, array $user, array $projection): array
{
    $item = is_array($projection['item'] ?? null) ? $projection['item'] : $projection;
    $id = (string) ($item['id'] ?? '');
    $revision = (int) ($item['revision'] ?? 0);
    if ($id === '' || $revision < 1) return [];
    $actions = [];
    if (classops_stage2_is_owner($user)) {
        foreach (['cancel', 'archive'] as $verb) {
            $actions[$verb] = classops_bot_service_issue_action($request, 'owner_' . $verb, [
                'id' => $id,
                'expectedRevision' => $revision,
                'idempotencyKey' => classops_bot_service_random_ref('bot.' . $verb . '.'),
                'reason' => 'explicit-bot-owner-' . $verb,
            ], 600);
        }
        return $actions;
    }

    $task = is_array($item['task'] ?? null) ? $item['task'] : null;
    if ($task !== null) {
        $state = (string) ($task['state'] ?? '');
        $stateRevision = (int) ($task['stateRevision'] ?? 0);
        if ($stateRevision > 0 && in_array($state, ['pending', 'needs_revision'], true)) {
            $actions['task_submit'] = classops_bot_service_issue_action($request, 'student_task_transition', [
                'id'=>$id,'expectedStateRevision'=>$stateRevision,'target'=>'submitted',
                'commandId'=>classops_bot_service_random_ref('bot.task.'),'reason'=>'explicit-bot-submit',
            ], 900);
        }
        if ($stateRevision > 0 && !in_array($state, ['completed','waived'], true)) {
            $actions['task_complete'] = classops_bot_service_issue_action($request, 'student_task_transition', [
                'id'=>$id,'expectedStateRevision'=>$stateRevision,'target'=>'completed',
                'commandId'=>classops_bot_service_random_ref('bot.task.'),'reason'=>'explicit-bot-complete',
            ], 900);
        }
    }
    $ack = is_array($item['ack'] ?? null) ? $item['ack'] : null;
    if ($ack !== null && empty($ack['acked'])) {
        $actions['ack'] = classops_bot_service_issue_action($request, 'student_ack', [
            'id'=>$id,'expectedRevision'=>$revision,'idempotencyKey'=>classops_bot_service_random_ref('bot.ack.'),
        ], 900);
    }
    $service = is_array($item['service'] ?? null) ? $item['service'] : null;
    $serviceState = is_array($service['state'] ?? null) ? $service['state'] : null;
    if ($service !== null && $serviceState !== null) {
        $stateRevision=(int)($serviceState['stateRevision']??0);
        $state=(string)($serviceState['state']??'pending');
        if ($stateRevision>0&&$state==='pending') {
            foreach (['completed','waived'] as $target) {
                $actions['service_'.$target]=classops_bot_service_issue_action($request,'student_service_transition',[
                    'id'=>$id,'expectedStateRevision'=>$stateRevision,'target'=>$target,
                    'commandId'=>classops_bot_service_random_ref('bot.service.'),
                ],900);
            }
        }
    }
    return $actions;
}

function classops_bot_service_preview_item_from_existing(array $current, array $patch): array
{
    $normalizedPatch = classops_domain_store_normalize_patch($current, $patch);
    $candidate = array_replace($current, $normalizedPatch);
    return [
        'cohortKey' => (string) ($candidate['cohortKey'] ?? ''),
        'type' => (string) ($candidate['type'] ?? ''),
        'title' => (string) ($candidate['title'] ?? ''),
        'description' => (string) ($candidate['description'] ?? ''),
        'course' => $candidate['course'] ?? null,
        'timing' => is_array($candidate['timing'] ?? null) ? $candidate['timing'] : [],
        'location' => (string) ($candidate['location'] ?? ''),
        'importance' => (string) ($candidate['importance'] ?? 'normal'),
        'requireAck' => !empty($candidate['requireAck']),
        'status' => (string) ($candidate['status'] ?? 'draft'),
    ];
}

function classops_bot_service_owner_preview(array $request, array $owner): array
{
    $previewRequest = classops_bot_service_object($request, 'request');
    $mode = strtolower(trim((string) ($request['mode'] ?? 'create')));
    if (!in_array($mode, ['create', 'update'], true)) {
        classops_domain_error('CLASSOPS_CONFIRM_MODE_INVALID', 'Bot preview mode is invalid.');
    }

    $confirmItem = $previewRequest['item'] ?? [];
    $confirmPayload = [];
    if ($mode === 'update') {
        $id = classops_stage2_require_item_id($request['id'] ?? '');
        $expectedRevision = (int) ($request['expectedRevision'] ?? 0);
        $current = classops_get_item($id);
        if ($expectedRevision < 1 || $expectedRevision !== (int) ($current['revision'] ?? 0)) {
            classops_domain_error('CLASSOPS_REVISION_CONFLICT', 'Item revision changed; refresh before preview.', 409);
        }
        $binding = classops_stage2_existing_binding($current);
        $previewRequest['item'] = classops_bot_service_preview_item_from_existing($current, is_array($confirmItem) ? $confirmItem : []);
        if (!array_key_exists('audienceSpec', $previewRequest) && is_array($binding['audienceSpec'] ?? null)) {
            $previewRequest['audienceSpec'] = $binding['audienceSpec'];
        }
        if (!array_key_exists('destinations', $previewRequest) && is_array($binding['destinations'] ?? null)) {
            $previewRequest['destinations'] = $binding['destinations'];
        }
        if (!array_key_exists('reminderPolicy', $previewRequest) && array_key_exists('reminderPolicy', $binding)) {
            $previewRequest['reminderPolicy'] = $binding['reminderPolicy'];
        }
        if (!array_key_exists('serviceRef', $previewRequest) && array_key_exists('serviceRef', $binding)) {
            $previewRequest['serviceRef'] = $binding['serviceRef'];
        }
        $confirmPayload['id'] = $id;
        $confirmPayload['expectedRevision'] = $expectedRevision;
    }

    $preview = classops_stage2_preview($owner, $previewRequest);
    $confirmPayload += [
        'mode' => $mode,
        'item' => $mode === 'update' ? $confirmItem : $previewRequest['item'],
        'audienceSpec' => $previewRequest['audienceSpec'] ?? classops_stage2_default_audience_spec(),
        'expectedAudienceHash' => (string) ($preview['confirmation']['audienceHash'] ?? ''),
        'destinations' => $previewRequest['destinations'] ?? ['private_users'],
        'reminderPolicy' => $previewRequest['reminderPolicy'] ?? null,
        'serviceRef' => $previewRequest['serviceRef'] ?? null,
        'idempotencyKey' => classops_bot_service_random_ref('bot.confirm.'),
        'reason' => $mode === 'update' ? 'explicit-bot-owner-update-confirm' : 'explicit-bot-owner-confirm',
    ];
    $token = classops_bot_service_issue_action($request, 'owner_confirm', $confirmPayload, 900);
    return ['success'=>true,'preview'=>$preview,'confirmToken'=>$token,'mode'=>$mode];
}

function classops_bot_service_record_sort_at(array $item): ?int
{
    $timing = is_array($item['timing'] ?? null) ? $item['timing'] : [];
    foreach (['startsAt','dueAt','endsAt'] as $field) {
        $raw = trim((string) ($timing[$field] ?? ''));
        if ($raw === '') continue;
        $timestamp = strtotime($raw);
        if ($timestamp !== false) return $timestamp;
    }
    return null;
}

function classops_bot_service_upcoming_month(array $request, array $user): array
{
    $timezone = new DateTimeZone('Asia/Tehran');
    $today = (new DateTimeImmutable('now', $timezone))->setTime(0, 0, 0);
    $end = $today->modify('+30 days');
    $nowTs = time();
    $endTs = $end->getTimestamp();
    $owner = classops_stage2_is_owner($user);
    $rawItems = [];
    if ($owner) {
        $listed = classops_list_items(['cohortKey'=>dent_user_cohort_key($user),'type'=>'','status'=>'','limit'=>CLASSOPS_BOT_SERVICE_MAX_LIST,'cursor'=>'']);
        $rawItems = is_array($listed['items'] ?? null) ? $listed['items'] : [];
    } else {
        $listed = classops_stage2_student_list($user, []);
        $rawItems = is_array($listed['items'] ?? null) ? $listed['items'] : [];
    }
    $records = [];
    foreach ($rawItems as $item) {
        if (!is_array($item) || ($item['status'] ?? '') === 'archived') continue;
        $sortAt = classops_bot_service_record_sort_at($item);
        $status = (string) ($item['status'] ?? '');
        $overdue = $sortAt !== null && $sortAt < $nowTs && !in_array($status, ['completed','cancelled','archived'], true);
        if ($sortAt !== null && $sortAt > $endTs) continue;
        if ($sortAt !== null && $sortAt < $today->getTimestamp() && !$overdue) continue;
        $course = is_array($item['course'] ?? null) ? (string) ($item['course']['title'] ?? '') : '';
        $records[] = [
            'source'=>'classops','id'=>(string)($item['id']??''),'type'=>(string)($item['type']??''),'status'=>$status,
            'title'=>(string)($item['title']??''),'description'=>(string)($item['description']??''),'courseTitle'=>$course,
            'location'=>(string)($item['location']??''),'timing'=>is_array($item['timing']??null)?$item['timing']:[],
            'sortAt'=>$sortAt===null?null:gmdate('c',$sortAt),'overdue'=>$overdue,
        ];
    }
    if (dent_user_cohort_key($user) === DENT_TERM7_COHORT) {
        try {
            for ($index = 0; $index < 30; $index++) {
                $localDate = $today->modify('+' . $index . ' days');
                $record = classops_stage2_term7_record_for_date($user, $localDate);
                $description = trim((string) ($record['description'] ?? ''));
                if ($description === '') continue;
                $records[] = [
                    'source'=>'term7','id'=>'','type'=>'schedule_ref','status'=>'active','title'=>'برنامه ترم ۷',
                    'description'=>$description,'courseTitle'=>'','location'=>'',
                    'timing'=>['localDate'=>$localDate->format('Y-m-d'),'allDay'=>true],
                    'sortAt'=>$localDate->setTime(0,0,0)->setTimezone(new DateTimeZone('UTC'))->format('c'),'overdue'=>false,
                ];
            }
        } catch (Throwable $exception) {
            $records[] = [
                'source'=>'term7','id'=>'','type'=>'schedule_ref','status'=>'unknown','title'=>'برنامه ترم ۷',
                'description'=>'برنامه رسمی در این لحظه قابل resolve نبود؛ داده‌ای حدس زده نشد.','courseTitle'=>'','location'=>'',
                'timing'=>['localDate'=>$today->format('Y-m-d'),'allDay'=>true],
                'sortAt'=>$today->setTimezone(new DateTimeZone('UTC'))->format('c'),'overdue'=>false,
            ];
        }
    }
    usort($records, static function(array $a, array $b): int {
        $left = strtotime((string)($a['sortAt']??'')) ?: PHP_INT_MAX;
        $right = strtotime((string)($b['sortAt']??'')) ?: PHP_INT_MAX;
        return $left <=> $right ?: strcmp((string)($a['title']??''),(string)($b['title']??''));
    });
    return ['success'=>true,'role'=>$owner?'owner':'student','timezone'=>'Asia/Tehran','days'=>30,'records'=>$records];
}

function classops_bot_service_notification_status(array $request, array $owner): array
{
    if (!classops_stage2_is_owner($owner)) classops_domain_error('CLASSOPS_OWNER_REQUIRED','Owner status required.',403);
    $notificationStore = notifications_read_store();
    $stage2State = classops_stage2_read_state();
    $notificationCounts = [];
    $notifications = [];
    foreach (($notificationStore['notifications'] ?? []) as $record) {
        if (!is_array($record) || (string)($record['source']??'') !== 'classops') continue;
        $status = (string)($record['status']??'unknown');
        $notificationCounts[$status] = ($notificationCounts[$status] ?? 0) + 1;
        $notifications[] = [
            'title'=>(string)($record['title']??'اعلان امور کلاس'),'status'=>$status,
            'publishAt'=>(string)($record['publishAt']??''),'releasedAt'=>(string)($record['releasedAt']??''),
        ];
    }
    usort($notifications, static fn(array $a,array $b): int => strcmp((string)($b['publishAt']??''),(string)($a['publishAt']??'')));
    $deliveryCounts = [];
    $platformCounts = ['telegram'=>[],'bale'=>[]];
    foreach (($stage2State['deliveryIntents'] ?? []) as $entry) {
        if (!is_array($entry)) continue;
        $status = (string)($entry['status']??'unknown');
        $platform = (string)($entry['intent']['platform']??'');
        $deliveryCounts[$status] = ($deliveryCounts[$status] ?? 0) + 1;
        if (isset($platformCounts[$platform])) {
            $platformCounts[$platform][$status] = ($platformCounts[$platform][$status] ?? 0) + 1;
        }
    }
    return [
        'success'=>true,'notifications'=>array_slice($notifications,0,20),
        'notificationCounts'=>$notificationCounts,'deliveryCounts'=>$deliveryCounts,'platformCounts'=>$platformCounts,
        'stateUpdatedAt'=>(string)($stage2State['updatedAt']??''),
    ];
}

function classops_bot_service_runtime_status(array $request, array $owner): array
{
    if (!classops_stage2_is_owner($owner)) classops_domain_error('CLASSOPS_OWNER_REQUIRED','Owner status required.',403);
    $currentPlatform = classops_bot_service_platform($request);
    $otherPlatform = $currentPlatform === 'telegram' ? 'bale' : 'telegram';
    $notificationStore = notifications_read_store();
    $stage2State = classops_stage2_read_state();
    return [
        'success'=>true,
        'services'=>[
            ['key'=>'site_api','label'=>'اتصال API سایت','state'=>'ready'],
            ['key'=>'classops','label'=>'امور کلاس','state'=>'ready','checkedAt'=>(string)($stage2State['updatedAt']??'')],
            ['key'=>'notifications','label'=>'اعلان‌ها','state'=>'ready','checkedAt'=>(string)($notificationStore['updatedAt']??'')],
            ['key'=>$currentPlatform,'label'=>$currentPlatform==='telegram'?'تلگرام':'بله','state'=>'ready'],
            ['key'=>$otherPlatform,'label'=>$otherPlatform==='telegram'?'تلگرام':'بله','state'=>'unknown'],
        ],
    ];
}

function classops_bot_service_resolve_action(array $request, array $user): array
{
    $token = trim((string) ($request['token'] ?? ''));
    $resolved = classops_stage2_resolve_callback(
        classops_bot_service_platform($request),
        classops_bot_service_platform_user_id($request),
        $token
    );
    $action = (string) ($resolved['action'] ?? '');
    $payload = is_array($resolved['payload'] ?? null) ? $resolved['payload'] : [];

    if ($action === 'owner_confirm') {
        if (!classops_stage2_is_owner($user)) classops_domain_error('CLASSOPS_OWNER_REQUIRED','Owner confirmation required.',403);
        return ['success'=>true,'action'=>$action,'result'=>classops_stage2_confirm($user,$payload)];
    }
    if ($action === 'owner_cancel' || $action === 'owner_archive') {
        if (!classops_stage2_is_owner($user)) classops_domain_error('CLASSOPS_OWNER_REQUIRED','Owner lifecycle action required.',403);
        $verb = substr($action, 6);
        return ['success'=>true,'action'=>$action,'result'=>classops_stage2_cancel_or_archive(
            $user,$verb,classops_stage2_require_item_id($payload['id']??''),(int)($payload['expectedRevision']??0),
            (string)($payload['idempotencyKey']??''),(string)($payload['reason']??'')
        )];
    }
    if (classops_stage2_is_owner($user)) classops_domain_error('CLASSOPS_CALLBACK_ROLE_MISMATCH','Student callback cannot be used by owner.',403);
    if ($action === 'student_task_transition') return ['success'=>true,'action'=>$action,'result'=>classops_stage2_student_task_transition($user,$payload)];
    if ($action === 'student_ack') return ['success'=>true,'action'=>$action,'result'=>classops_stage2_student_ack($user,$payload)];
    if ($action === 'student_service_transition') return ['success'=>true,'action'=>$action,'result'=>classops_stage2_student_service_transition($user,$payload),'externallyVerified'=>false];
    classops_domain_error('CLASSOPS_CALLBACK_ACTION_INVALID','Callback action is not recognized.',422);
}

function classops_bot_service_dispatch(array $request): array
{
    $action = trim((string) ($request['action'] ?? ''));
    try {
        if ($action === 'classopsCapabilities') {
            $user = classops_bot_service_linked_user($request);
            return ['success'=>true,'role'=>classops_stage2_is_owner($user)?'owner':'student','capabilities'=>classops_stage2_capabilities()];
        }
        if ($action === 'classopsList') {
            $user = classops_bot_service_linked_user($request);
            if (classops_stage2_is_owner($user)) {
                $limit=max(1,min(CLASSOPS_BOT_SERVICE_MAX_LIST,(int)($request['limit']??20)));
                return ['success'=>true,'role'=>'owner','data'=>classops_list_items([
                    'cohortKey'=>trim((string)($request['cohortKey']??'')),
                    'type'=>trim((string)($request['type']??'')),
                    'status'=>trim((string)($request['status']??'')),
                    'limit'=>$limit,'cursor'=>'',
                ])];
            }
            return ['success'=>true,'role'=>'student','data'=>classops_stage2_student_list($user,[
                'type'=>trim((string)($request['type']??'')),'status'=>trim((string)($request['status']??'')),
            ])];
        }
        if ($action === 'classopsGet') {
            $user = classops_bot_service_linked_user($request);
            $projection = classops_stage2_is_owner($user)
                ? classops_bot_service_owner_item($request,$user)
                : ['item'=>classops_stage2_student_get($user,(string)($request['id']??''))];
            $projection['actions']=classops_bot_service_item_actions($request,$user,$projection);
            return ['success'=>true,'role'=>classops_stage2_is_owner($user)?'owner':'student']+$projection;
        }
        if ($action === 'classopsTomorrowSummary' || $action === 'classopsWeeklyDigest') {
            $user=classops_bot_service_linked_user($request);
            return ['success'=>true,'digest'=>classops_stage2_digest($user,$action==='classopsTomorrowSummary'?'tomorrow':'weekly')];
        }
        if ($action === 'classopsUpcomingMonth') {
            $user=classops_bot_service_linked_user($request);
            return classops_bot_service_upcoming_month($request,$user);
        }
        if ($action === 'classopsOwnerAiDraft') {
            $owner=classops_bot_service_owner($request);
            $forwarded=array_key_exists('forwardedText',$request)&&$request['forwardedText']!==null?(string)$request['forwardedText']:null;
            return classops_stage2_ai_create($owner,(string)($request['ownerText']??''),$forwarded,trim((string)($request['cohortKey']??'')));
        }
        if ($action === 'classopsOwnerPreview') {
            return classops_bot_service_owner_preview($request,classops_bot_service_owner($request));
        }
        if ($action === 'classopsNotificationStatus') {
            return classops_bot_service_notification_status($request,classops_bot_service_owner($request));
        }
        if ($action === 'classopsRuntimeStatus') {
            return classops_bot_service_runtime_status($request,classops_bot_service_owner($request));
        }
        if ($action === 'classopsResolveAction') {
            $user=classops_bot_service_linked_user($request);
            return classops_bot_service_resolve_action($request,$user);
        }
        if ($action === 'classopsDeliveryClaim') {
            classops_bot_service_owner($request);
            return ['success'=>true,'deliveries'=>classops_stage2_claim_deliveries(classops_bot_service_platform($request),(int)($request['limit']??10))];
        }
        if ($action === 'classopsDeliveryAck') {
            classops_bot_service_owner($request);
            $intentId=trim((string)($request['intentId']??''));
            if (preg_match('/^cdi_[a-f0-9]{32}$/D',$intentId)!==1) classops_domain_error('CLASSOPS_DELIVERY_INTENT_INVALID','Delivery intent is invalid.');
            $success=filter_var($request['delivered']??false,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);
            if ($success===null) classops_domain_error('CLASSOPS_DELIVERY_RESULT_INVALID','Delivery result is invalid.');
            return ['success'=>true,'delivery'=>classops_stage2_ack_delivery(
                classops_bot_service_platform($request),$intentId,$success,trim((string)($request['reasonCode']??''))
            )];
        }
        if ($action === 'classopsSchedulerTick') {
            classops_bot_service_owner($request);
            return classops_stage2_scheduler_tick();
        }
        classops_domain_error('CLASSOPS_BOT_ACTION_UNKNOWN','ClassOps bot action is not recognized.',404);
    } catch (DentClassOpsDomainException $exception) {
        dent_error('عملیات ClassOps ربات انجام نشد.', $exception->httpStatus, ['code'=>$exception->reasonCode]);
    } catch (DentClassOpsTaskException $exception) {
        dent_error('عملیات task/requirement ربات انجام نشد.', $exception->httpStatus, ['code'=>$exception->reasonCode]);
    } catch (DentClassOpsCriticalAckException $exception) {
        dent_error('عملیات ACK ربات انجام نشد.', $exception->statusCode, ['code'=>$exception->reasonCode]);
    } catch (DentClassOpsPersistenceException $exception) {
        dent_error('ذخیره‌سازی ClassOps موقتاً در دسترس نیست.',503,['code'=>$exception->reasonCode]);
    } catch (Throwable $exception) {
        classops_persistence_log('error',[
            'action'=>'bot-service','decodeStatus'=>'not-applicable','commitResult'=>'unhandled-error','reasonCode'=>'CLASSOPS_BOT_INTERNAL_ERROR',
        ]);
        dent_error('خطای داخلی ClassOps ربات رخ داد.',500,['code'=>'CLASSOPS_BOT_INTERNAL_ERROR']);
    }
}
