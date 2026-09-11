<?php
declare(strict_types=1);

require_once __DIR__ . '/classops_stage2_core.php';
require_once __DIR__ . '/classops_modules/scheduler/classops_reminder_planner.php';

function classops_stage2_scheduler_slot_key(string $purpose,string $bucket): string
{
    return 'slot_' . hash('sha256','classops-stage2-scheduler-v1|'.$purpose.'|'.$bucket);
}

function classops_stage2_scheduler_claim_slot(string $purpose,string $bucket,int $leaseSeconds=120): bool
{
    $key=classops_stage2_scheduler_slot_key($purpose,$bucket);
    $now=time();
    $tx=classops_stage2_transaction(static function(array &$state) use($key,$purpose,$bucket,$leaseSeconds,$now): bool {
        $existing=$state['schedulerOccurrences'][$key]??null;
        if (is_array($existing)) {
            if (($existing['status']??'')==='completed') return false;
            if (($existing['status']??'')==='leased'&&(int)($existing['leaseUntil']??0)>$now) return false;
        }
        $state['schedulerOccurrences'][$key]=[
            'kind'=>'coordinator_slot','purpose'=>$purpose,'bucket'=>$bucket,'status'=>'leased',
            'leaseUntil'=>$now+$leaseSeconds,'attempts'=>max(0,(int)($existing['attempts']??0))+1,
            'startedAt'=>gmdate('Y-m-d\TH:i:s\Z',$now),'completedAt'=>null,
        ];
        $state['updatedAt']=dent_iso_now();
        return true;
    },'scheduler-claim');
    return (bool)$tx['result'];
}

function classops_stage2_scheduler_complete_slot(string $purpose,string $bucket): void
{
    $key=classops_stage2_scheduler_slot_key($purpose,$bucket);
    classops_stage2_transaction(static function(array &$state) use($key): void {
        if (!is_array($state['schedulerOccurrences'][$key]??null)) return;
        $state['schedulerOccurrences'][$key]['status']='completed';
        $state['schedulerOccurrences'][$key]['leaseUntil']=0;
        $state['schedulerOccurrences'][$key]['completedAt']=gmdate('Y-m-d\TH:i:s\Z');
        $state['updatedAt']=dent_iso_now();
    },'scheduler-complete');
}

function classops_stage2_scheduler_release_slot(string $purpose,string $bucket,string $reason='failed'): void
{
    $key=classops_stage2_scheduler_slot_key($purpose,$bucket);
    classops_stage2_transaction(static function(array &$state) use($key,$reason): void {
        if (!is_array($state['schedulerOccurrences'][$key]??null)) return;
        $state['schedulerOccurrences'][$key]['status']='retry';
        $state['schedulerOccurrences'][$key]['leaseUntil']=0;
        $state['schedulerOccurrences'][$key]['lastReasonCode']=substr($reason,0,80);
        $state['updatedAt']=dent_iso_now();
    },'scheduler-release');
}

function classops_stage2_known_reminders(): array
{
    $state=classops_stage2_read_state();
    $known=[];
    foreach (($state['schedulerOccurrences']??[]) as $entry) {
        if (!is_array($entry)||($entry['kind']??'')!=='reminder_occurrence') continue;
        if (!in_array((string)($entry['state']??''),['planned','leased','delivered','failed','superseded'],true)) continue;
        $known[]=[
            'occurrenceKey'=>$entry['occurrenceKey'],'idempotencyKey'=>$entry['idempotencyKey'],'itemId'=>$entry['itemId'],
            'revision'=>$entry['revision'],'ruleId'=>$entry['ruleId'],'dueAt'=>$entry['dueAt'],'state'=>$entry['state'],
        ];
    }
    return $known;
}

function classops_stage2_scheduler_items(): array
{
    $store=classops_read_store();
    $items=[];
    foreach (($store['items']??[]) as $item) {
        if (!is_array($item)) continue;
        $binding=$item['extensions'][CLASSOPS_STAGE2_EXTENSION_KEY]??null;
        if (!is_array($binding)||($binding['contractVersion']??'')!==CLASSOPS_STAGE2_BINDING_VERSION) continue;
        $policy=$binding['reminderPolicy']??null;
        if (!is_array($policy)||($policy['rules']??[])===[]) continue;
        $aud=classops_stage2_get_audience((string)$item['id'],(int)$item['revision']);
        if (!is_array($aud)) continue;
        $timing=is_array($item['timing']??null)?$item['timing']:[];
        $items[]=[
            'itemId'=>(string)$item['id'],'revision'=>(int)$item['revision'],'itemType'=>(string)$item['type'],'status'=>(string)$item['status'],
            'timing'=>['startsAt'=>$timing['startsAt']??null,'dueAt'=>$timing['dueAt']??null],
            'audience'=>['ref'=>'audience_'.substr((string)$aud['resolutionHash'],0,24),'hash'=>(string)$aud['resolutionHash']],
            'deliveryPolicyRef'=>'classops_delivery_policy_v1','reminderPolicy'=>$policy,'serviceRef'=>$binding['serviceRef']??null,
        ];
    }
    return $items;
}

function classops_stage2_scheduler_mark_occurrence(array $intent,string $state='planned'): void
{
    $key=(string)$intent['occurrenceKey'];
    classops_stage2_transaction(static function(array &$store) use($key,$intent,$state): void {
        $store['schedulerOccurrences'][$key]=[
            'kind'=>'reminder_occurrence','occurrenceKey'=>$key,'idempotencyKey'=>(string)$intent['idempotencyKey'],
            'itemId'=>(string)$intent['itemId'],'revision'=>(int)$intent['revision'],'ruleId'=>(string)$intent['ruleId'],
            'dueAt'=>(string)$intent['dueAt'],'plannedDueAt'=>(string)($intent['plannedDueAt']??$intent['dueAt']),
            'state'=>$state,'catchUp'=>(bool)($intent['catchUp']??false),'updatedAt'=>gmdate('Y-m-d\TH:i:s\Z'),
        ];
        if (count($store['schedulerOccurrences'])>CLASSOPS_STAGE2_MAX_SCHEDULER_OCCURRENCES) {
            foreach ($store['schedulerOccurrences'] as $oldKey=>$old) {
                if (($old['kind']??'')==='reminder_occurrence'&&in_array(($old['state']??''),['delivered','failed','superseded'],true)) {
                    unset($store['schedulerOccurrences'][$oldKey]);
                    if (count($store['schedulerOccurrences'])<=CLASSOPS_STAGE2_MAX_SCHEDULER_OCCURRENCES) break;
                }
            }
        }
        $store['updatedAt']=dent_iso_now();
    },'scheduler-occurrence');
}

function classops_stage2_scheduler_supersede_occurrence(array $supersession): void
{
    $key=(string)($supersession['occurrenceKey']??'');
    classops_stage2_transaction(static function(array &$state) use($key,$supersession): void {
        $entry=$state['schedulerOccurrences'][$key]??null;
        if (!is_array($entry)||($entry['kind']??'')!=='reminder_occurrence') return;
        if (($entry['state']??'')==='delivered') return;
        $entry['state']='superseded';
        $entry['lastReasonCode']=substr((string)($supersession['reason']??'superseded'),0,80);
        $entry['updatedAt']=gmdate('Y-m-d\TH:i:s\Z');
        $state['schedulerOccurrences'][$key]=$entry;
        $state['updatedAt']=dent_iso_now();
    },'scheduler-supersede');
}

function classops_stage2_scheduler_remove_occurrence_notification(string $itemId,int $revision,string $occurrenceKey): void
{
    $prefix='item:'.$itemId.':r'.$revision.':'.$occurrenceKey;
    notifications_with_store_lock(static function(array &$store) use($prefix): void {
        foreach (($store['notifications']??[]) as $id=>$record) {
            if (is_array($record)&&($record['source']??'')==='classops'&&str_starts_with((string)($record['sourceKey']??''),$prefix)) unset($store['notifications'][$id]);
        }
    });
}

function classops_stage2_scheduler_plan_reminders(?DateTimeImmutable $clock=null): array
{
    $clock=$clock??new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $snapshot=[
        'contractVersion'=>'classops-reminder-v1','planningHorizonSeconds'=>2678400,'maxOccurrences'=>128,
        'items'=>classops_stage2_scheduler_items(),'knownOccurrences'=>classops_stage2_known_reminders(),
    ];
    $plan=classops_reminder_plan($snapshot,static fn():DateTimeImmutable=>$clock);
    if (($plan['ok']??false)!==true) return $plan;
    foreach (($plan['supersessions']??[]) as $supersession) {
        if (!is_array($supersession)) continue;
        classops_stage2_scheduler_supersede_occurrence($supersession);
        classops_stage2_scheduler_remove_occurrence_notification((string)$supersession['itemId'],(int)$supersession['revision'],(string)$supersession['occurrenceKey']);
    }
    $created=0;
    foreach (($plan['intents']??[]) as $intent) {
        if (!is_array($intent)) continue;
        try {
            $item=classops_get_item((string)$intent['itemId']);
            if ((int)$item['revision']!==(int)$intent['revision']) continue;
            $aud=classops_stage2_get_audience((string)$item['id'],(int)$item['revision']);
            if (!is_array($aud)) continue;
            $binding=$item['extensions'][CLASSOPS_STAGE2_EXTENSION_KEY]??[];
            $destinations=classops_stage2_normalize_destinations($binding['destinations']??null);
            $publishAt=(string)($intent['plannedDueAt']??$intent['dueAt']);
            classops_stage2_ensure_notification_record(
                $item,$aud['snapshot']['recipientStudentNumbers']??[],in_array('private_users',$destinations,true),(string)$intent['occurrenceKey'],$publishAt
            );
            classops_stage2_commit_direct_intents($item,[
                'deterministicHash'=>$aud['resolutionHash'],'recipientStudentNumbers'=>$aud['snapshot']['recipientStudentNumbers']??[],'normalizedSpec'=>$aud['spec'],'snapshot'=>$aud['snapshot'],
            ],$destinations,'reminder',$publishAt);
            classops_stage2_scheduler_mark_occurrence($intent,'planned');
            $created++;
        } catch (Throwable $exception) {
            // Fail one occurrence independently; unmarked occurrences are retried on the next coordinator tick.
            continue;
        }
    }
    $plan['persistedIntentCount']=$created;
    return $plan;
}

function classops_stage2_local_time_env(string $name,string $default): string
{
    $value=trim((string)getenv($name));
    return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$value)===1?$value:$default;
}

function classops_stage2_digest_slot_due(string $kind,DateTimeImmutable $nowUtc): ?array
{
    $local=$nowUtc->setTimezone(new DateTimeZone('Asia/Tehran'));
    $time=$kind==='tomorrow'
        ? classops_stage2_local_time_env('DENT_CLASSOPS_TOMORROW_SUMMARY_LOCAL_TIME','21:00')
        : classops_stage2_local_time_env('DENT_CLASSOPS_WEEKLY_DIGEST_LOCAL_TIME','20:00');
    if ($kind==='weekly'&&(int)$local->format('N')!==5) return null; // Friday -> next academic week.
    [$h,$m]=array_map('intval',explode(':',$time));
    $scheduled=$local->setTime($h,$m,0);
    $age=$local->getTimestamp()-$scheduled->getTimestamp();
    if ($age<0||$age>7200) return null;
    return ['bucket'=>$local->format('Y-m-d'),'scheduledAt'=>$scheduled->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z')];
}

function classops_stage2_digest_source_key(string $kind,string $bucket,string $student): string
{
    return 'digest:'.$kind.':'.$bucket.':'.substr(hash('sha256',$student),0,24);
}

function classops_stage2_ensure_digest_notification(array $user,string $kind,string $bucket,array $digest): bool
{
    $student=classops_stage2_student_number($user);
    $sourceKey=classops_stage2_digest_source_key($kind,$bucket,$student);
    $title=$kind==='tomorrow'?'خلاصه فردا':'خلاصه هفتگی';
    return notifications_with_store_lock(static function(array &$store) use($student,$sourceKey,$title,$digest): bool {
        foreach (($store['notifications']??[]) as $record) {
            if (is_array($record)&&($record['source']??'')==='classops_digest'&&($record['sourceKey']??'')===$sourceKey) return false;
        }
        $snapshot=notifications_snapshot_recipients_for_target(DENT_NOTIFICATION_TARGET_USER,'',$student);
        if ($snapshot===[]) return false;
        $id=notifications_generate_id();
        $record=notifications_normalize_record($id,[
            'id'=>$id,'kind'=>DENT_NOTIFICATION_KIND_ANNOUNCEMENT,'title'=>$title,
            'body'=>dent_clean_text((string)($digest['plainText']??''),4000),'tone'=>'accent',
            'target'=>DENT_NOTIFICATION_TARGET_USER,'targetStudentNumber'=>$student,'source'=>'classops_digest','sourceKey'=>$sourceKey,
            'ctaHref'=>'/classops/','ctaLabel'=>'مشاهده در ClassOps','createdAt'=>dent_iso_now(),'publishAt'=>dent_iso_now(),'releasedAt'=>dent_iso_now(),
            'status'=>DENT_NOTIFICATION_STATUS_ACTIVE,'createdByStudentNumber'=>'','createdByName'=>'','createdByRole'=>'سیستم','meta'=>[],
            'recipients'=>$snapshot,'sendSms'=>false,'smsStatus'=>DENT_NOTIFICATION_SMS_STATUS_NONE,
        ]);
        if ($record===null) return false;
        $store['notifications'][$record['id']]=$record;
        return true;
    });
}

function classops_stage2_scheduler_generate_digest(string $kind,DateTimeImmutable $nowUtc,array $slot): array
{
    $created=0;$failed=0;
    $userStore=dent_load_user_store();
    foreach (($userStore['users']??[]) as $user) {
        if (!is_array($user)||($user['role']??'student')==='owner') continue;
        $student=dent_normalize_student_number((string)($user['studentNumber']??''));
        if ($student===''||dent_user_cohort_key($user)==='') continue;
        try {
            $digest=classops_stage2_digest($user,$kind,$nowUtc->format('Y-m-d\TH:i:s\Z'));
            if (classops_stage2_ensure_digest_notification($user,$kind,(string)$slot['bucket'],$digest)) $created++;
        } catch (Throwable $exception) { $failed++; }
    }
    return ['created'=>$created,'failed'=>$failed];
}

function classops_stage2_scheduler_tick(?DateTimeImmutable $clock=null): array
{
    $clock=$clock??new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $bucket=$clock->format('Y-m-d\TH:i');
    // One server-side coordinator slot means Telegram/Bale callers cannot each produce side effects.
    if (!classops_stage2_scheduler_claim_slot('tick',$bucket,90)) return ['success'=>true,'claimed'=>false,'reason'=>'coordinator-slot-busy-or-complete'];
    try {
        $reminders=classops_stage2_scheduler_plan_reminders($clock);
        $digests=[];
        foreach (['tomorrow','weekly'] as $kind) {
            $slot=classops_stage2_digest_slot_due($kind,$clock);
            if ($slot===null) continue;
            if (!classops_stage2_scheduler_claim_slot('digest-'.$kind,(string)$slot['bucket'],300)) continue;
            try {
                $digests[$kind]=classops_stage2_scheduler_generate_digest($kind,$clock,$slot);
                classops_stage2_scheduler_complete_slot('digest-'.$kind,(string)$slot['bucket']);
            } catch (Throwable $exception) {
                classops_stage2_scheduler_release_slot('digest-'.$kind,(string)$slot['bucket'],'digest-failed');
                $digests[$kind]=['created'=>0,'failed'=>1];
            }
        }
        classops_stage2_scheduler_complete_slot('tick',$bucket);
        return ['success'=>true,'claimed'=>true,'reminders'=>$reminders,'digests'=>$digests];
    } catch (Throwable $exception) {
        classops_stage2_scheduler_release_slot('tick',$bucket,'tick-failed');
        return ['success'=>false,'claimed'=>true,'reason'=>'scheduler-failed'];
    }
}
