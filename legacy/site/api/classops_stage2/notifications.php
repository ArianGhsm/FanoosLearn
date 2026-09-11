<?php
declare(strict_types=1);

require_once __DIR__ . '/audience_delivery.php';
require_once dirname(__DIR__) . '/notifications_store.php';

function classops_stage2_message_for_item(array $item, string $prefix = ''): array
{
    $typeLabels = [
        'announcement'=>'اطلاعیه','event'=>'رویداد','class_change'=>'تغییر برنامه','deadline'=>'ددلاین',
        'task'=>'تسک','requirement'=>'الزام','exam'=>'آزمون','critical_notice'=>'اطلاعیه مهم','service_reminder'=>'یادآوری خدمت',
    ];
    $title=trim(($prefix!==''?$prefix.' — ':'').($typeLabels[$item['type']]??'ClassOps').': '.(string)$item['title']);
    $lines=[];
    $description=trim((string)($item['description']??''));
    if ($description!=='') $lines[]=$description;
    $timing=is_array($item['timing']??null)?$item['timing']:[];
    foreach ([['startsAt','شروع'],['endsAt','پایان'],['dueAt','مهلت']] as [$key,$label]) {
        if (!empty($timing[$key])) $lines[]=$label.': '.(string)$timing[$key];
    }
    if (trim((string)($item['location']??''))!=='') $lines[]='مکان: '.(string)$item['location'];
    if (($item['type']??'')==='service_reminder') {
        $stage2=$item['extensions'][CLASSOPS_STAGE2_EXTENSION_KEY]??[];
        if (($stage2['serviceRef']??null)==='saba') $lines[]='این فقط یادآوری صباست؛ وضعیت ورود یا انجام کار در صبا تأیید نمی‌شود.';
    }
    return [
        'title'=>dent_clean_text($title,180),
        'body'=>dent_clean_text(implode("\n",$lines),4000),
        'ctaHref'=>'/classops/','ctaLabel'=>'مشاهده در ClassOps',
    ];
}

function classops_stage2_notification_source_key(array $item, string $studentNumber = '', string $occurrence = 'initial'): string
{
    $parts=['item',(string)$item['id'],'r'.(int)$item['revision'],$occurrence];
    if ($studentNumber!=='') $parts[]='student-'.substr(hash('sha256',$studentNumber),0,24);
    return implode(':',$parts);
}

function classops_stage2_remove_prior_notifications(string $itemId, int $revision): void
{
    notifications_with_store_lock(static function(array &$store) use($itemId,$revision): void {
        foreach (($store['notifications']??[]) as $id=>$record) {
            if (!is_array($record)||($record['source']??'')!=='classops') continue;
            $sourceKey=(string)($record['sourceKey']??'');
            if (!str_starts_with($sourceKey,'item:'.$itemId.':r')) continue;
            if (preg_match('/^item:'.preg_quote($itemId,'/').':r(\d+):/',$sourceKey,$match)!==1) continue;
            if ((int)$match[1]<$revision) unset($store['notifications'][$id]);
        }
    });
}

function classops_stage2_cohort_members(string $cohortKey): array
{
    $members=[];
    $store=dent_load_user_store();
    foreach (($store['users']??[]) as $key=>$user) {
        if (!is_array($user)||dent_user_cohort_key($user)!==$cohortKey) continue;
        $student=dent_normalize_student_number((string)($user['studentNumber']??$key));
        if ($student!=='') $members[$student]=true;
    }
    $out=array_keys($members);
    sort($out,SORT_STRING);
    return $out;
}

function classops_stage2_ensure_notification_record(
    array $item,
    array $recipientNumbers,
    bool $allowPrivateBotPush,
    string $occurrence = 'initial',
    ?string $publishAt = null
): array {
    // Terminal revisions must never generate a fresh notification merely because
    // the owner recorded completion. Existing/future ClassOps notifications are
    // reconciled separately by the lifecycle/update path.
    if (in_array((string)($item['status']??''), ['completed','cancelled','archived'], true)) return [];

    $recipients=[];
    foreach ($recipientNumbers as $raw) {
        $student=dent_normalize_student_number((string)$raw);
        if ($student!=='') $recipients[$student]=true;
    }
    $recipientNumbers=array_keys($recipients);
    sort($recipientNumbers,SORT_STRING);
    if ($recipientNumbers===[]) return [];
    $message=classops_stage2_message_for_item($item);
    $cohort=(string)$item['cohortKey'];
    $wholeCohort=$recipientNumbers===classops_stage2_cohort_members($cohort);
    $publishAt=$publishAt===null?dent_iso_now():notifications_normalize_iso_datetime($publishAt);
    if ($publishAt==='') classops_domain_error('CLASSOPS_NOTIFICATION_TIME_INVALID','زمان اعلان canonical معتبر نیست.');
    $scheduled=notifications_timestamp($publishAt)>time();

    return notifications_with_store_lock(static function(array &$store) use($item,$recipientNumbers,$allowPrivateBotPush,$occurrence,$message,$cohort,$wholeCohort,$publishAt,$scheduled): array {
        $results=[];
        $targets=$wholeCohort
            ? [['kind'=>'cohort','student'=>'']]
            : array_map(static fn(string $s):array=>['kind'=>'user','student'=>$s],$recipientNumbers);
        foreach ($targets as $targetSpec) {
            $student=(string)$targetSpec['student'];
            $sourceKey=classops_stage2_notification_source_key($item,$student,$occurrence);
            foreach (($store['notifications']??[]) as $candidate) {
                if (is_array($candidate)&&($candidate['source']??'')==='classops'&&($candidate['sourceKey']??'')===$sourceKey) {
                    $results[]=$candidate;
                    continue 2;
                }
            }
            $snapshot=$targetSpec['kind']==='cohort'
                ? notifications_snapshot_recipients_for_target(DENT_NOTIFICATION_TARGET_COHORT,$cohort)
                : notifications_snapshot_recipients_for_target(DENT_NOTIFICATION_TARGET_USER,'',$student);
            if ($snapshot===[]) continue;
            $id=notifications_generate_id();
            $record=notifications_normalize_record($id,[
                'id'=>$id,'kind'=>DENT_NOTIFICATION_KIND_ANNOUNCEMENT,'title'=>$message['title'],'body'=>$message['body'],
                'tone'=>in_array((string)($item['importance']??''),['important','critical'],true)?'warn':'accent',
                'target'=>$targetSpec['kind']==='cohort'?DENT_NOTIFICATION_TARGET_COHORT:DENT_NOTIFICATION_TARGET_USER,
                'cohortKey'=>$targetSpec['kind']==='cohort'?$cohort:'','targetStudentNumber'=>$student,
                'source'=>'classops','sourceKey'=>$sourceKey,'ctaHref'=>$message['ctaHref'],'ctaLabel'=>$message['ctaLabel'],
                'createdAt'=>dent_iso_now(),'publishAt'=>$publishAt,'releasedAt'=>$scheduled?'':$publishAt,
                'status'=>$scheduled?DENT_NOTIFICATION_STATUS_SCHEDULED:DENT_NOTIFICATION_STATUS_ACTIVE,
                'createdByStudentNumber'=>'','createdByName'=>'','createdByRole'=>'سیستم',
                'meta'=>[
                    'disablePush'=>!$allowPrivateBotPush,
                    'eventId'=>'classops-'.substr(hash('sha256',$sourceKey),0,32),
                    'important'=>in_array((string)($item['importance']??''),['important','critical'],true),
                ],
                'recipients'=>$snapshot,'sendSms'=>false,'smsStatus'=>DENT_NOTIFICATION_SMS_STATUS_NONE,
            ]);
            if ($record!==null) {
                $store['notifications'][$record['id']]=$record;
                $results[]=$record;
            }
        }
        return $results;
    });
}

function classops_stage2_notification_remove_item(string $itemId): int
{
    return notifications_with_store_lock(static function(array &$store) use($itemId): int {
        $removed=0;
        foreach (($store['notifications']??[]) as $id=>$record) {
            if (!is_array($record)||($record['source']??'')!=='classops') continue;
            if (!str_starts_with((string)($record['sourceKey']??''),'item:'.$itemId.':')) continue;
            unset($store['notifications'][$id]);
            $removed++;
        }
        return $removed;
    });
}
