<?php
declare(strict_types=1);

require_once __DIR__ . '/student_workflow.php';
require_once dirname(__DIR__) . '/academic_term7.php';
require_once dirname(__DIR__) . '/classops_modules/digests/digest_engine.php';

function classops_stage2_digest_task_state(?array $state,?string $dueAt,string $nowUtc): ?string
{
    if ($state===null) return null;
    $value=(string)($state['state']??'pending');
    if (in_array($value,['completed','waived'],true)) return 'completed';
    if ($value==='pending'&&is_string($dueAt)&&$dueAt!==''&&(strtotime($dueAt)?:PHP_INT_MAX)<(strtotime($nowUtc)?:0)) return 'overdue';
    return 'pending';
}

function classops_stage2_digest_record_from_item(array $item,array $viewer,?array $taskState,string $nowUtc): array
{
    $type=(string)$item['type'];
    $source=match($type){'task'=>'task','requirement'=>'requirement','exam'=>'exam','critical_notice'=>'ack','service_reminder'=>'service',default=>'classops'};
    $status=match((string)$item['status']){'cancelled'=>'cancelled','completed','archived'=>'completed','draft','scheduled'=>'pending',default=>'active'};
    $timing=is_array($item['timing']??null)?$item['timing']:[];
    $student=$viewer['scope']==='student'?($viewer['studentNumber']??null):null;
    $ackState='not_required';
    if ($type==='critical_notice'&&!empty($item['requireAck'])) {
        $ackState=$student!==null&&classops_ack_is_satisfied(classops_stage2_ack_state(),$item,(string)$student)?'acked':'pending';
    }
    return [
        'recordVersion'=>'classops-digest-record-v1','entityRef'=>(string)$item['id'],'revision'=>(int)$item['revision'],'cohortKey'=>(string)$item['cohortKey'],
        'source'=>$source,'itemType'=>$type,'title'=>(string)$item['title'],'description'=>(string)$item['description'],'course'=>$item['course'],'location'=>(string)$item['location'],
        'importance'=>(string)$item['importance'],'status'=>$status,'scheduleRef'=>null,
        'timing'=>[
            'allDay'=>false,'localDate'=>null,'startsAtUtc'=>$timing['startsAt']??null,'endsAtUtc'=>$timing['endsAt']??null,'dueAtUtc'=>$timing['dueAt']??null,'timezone'=>'Asia/Tehran',
        ],
        'visibility'=>['studentAllowed'=>true,'ownerAllowed'=>true,'subject'=>null],
        'state'=>[
            'taskState'=>classops_stage2_digest_task_state($taskState,is_string($timing['dueAt']??null)?$timing['dueAt']:null,$nowUtc),
            'ackState'=>$ackState,
        ],
        'change'=>['kind'=>$status==='cancelled'?'cancelled':'none','changedAtUtc'=>$status==='cancelled'?(string)$item['updatedAt']:null],
        'supersedesRef'=>null,
    ];
}

function classops_stage2_term7_record_for_date(array $user,DateTimeImmutable $localDate): array
{
    $student=classops_stage2_student_number($user);
    [$jy,$jm,$jd]=notifications_gregorian_to_jalali((int)$localDate->format('Y'),(int)$localDate->format('n'),(int)$localDate->format('j'));
    $jalali=sprintf('%04d/%02d/%02d',$jy,$jm,$jd);
    $assignment=dent_term7_assignment_for_student($student,dent_term7_state_read());
    $resolved=dent_term7_resolve_jalali($jalali,(int)$localDate->format('N'),$assignment);
    $summary=dent_term7_summary_body($resolved);
    return [
        'recordVersion'=>'classops-digest-record-v1',
        'entityRef'=>'term7:'.$localDate->format('Y-m-d').':'.substr(hash('sha256',$student),0,20),
        'revision'=>1,'cohortKey'=>DENT_TERM7_COHORT,'source'=>'schedule','itemType'=>'schedule_ref','title'=>'برنامه رسمی ترم ۷','description'=>$summary,
        'course'=>null,'location'=>'','importance'=>'normal','status'=>'active','scheduleRef'=>'term7:'.DENT_TERM7_SCHEDULE_VERSION,
        'timing'=>['allDay'=>true,'localDate'=>$localDate->format('Y-m-d'),'startsAtUtc'=>null,'endsAtUtc'=>null,'dueAtUtc'=>null,'timezone'=>'Asia/Tehran'],
        'visibility'=>['studentAllowed'=>true,'ownerAllowed'=>true,'subject'=>['kind'=>'studentNumber','value'=>$student]],
        'state'=>['taskState'=>null,'ackState'=>'not_required'],'change'=>['kind'=>'none','changedAtUtc'=>null],'supersedesRef'=>null,
    ];
}

function classops_stage2_term7_records(array $user,string $nowUtc): array
{
    if (dent_user_cohort_key($user)!==DENT_TERM7_COHORT) return [];
    try {
        $now=(new DateTimeImmutable($nowUtc,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Tehran'))->setTime(0,0,0);
        $records=[];
        // Covers tomorrow plus the next Saturday-Friday window regardless of current weekday.
        for ($i=1;$i<=14;$i++) $records[]=classops_stage2_term7_record_for_date($user,$now->modify('+'.$i.' days'));
        return $records;
    } catch (Throwable $exception) {
        $student=classops_stage2_student_number($user);
        $tomorrow=(new DateTimeImmutable($nowUtc,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Tehran'))->modify('+1 day');
        return [[
            'recordVersion'=>'classops-digest-record-v1','entityRef'=>'term7:unavailable:'.substr(hash('sha256',$student),0,20),'revision'=>1,
            'cohortKey'=>DENT_TERM7_COHORT,'source'=>'schedule','itemType'=>'schedule_ref','title'=>'برنامه رسمی ترم ۷ در دسترس نیست',
            'description'=>'منبع canonical برنامه در این لحظه قابل resolve نبود؛ برنامه حدس زده نشده است.','course'=>null,'location'=>'','importance'=>'important','status'=>'active',
            'scheduleRef'=>'term7:'.DENT_TERM7_SCHEDULE_VERSION,
            'timing'=>['allDay'=>true,'localDate'=>$tomorrow->format('Y-m-d'),'startsAtUtc'=>null,'endsAtUtc'=>null,'dueAtUtc'=>null,'timezone'=>'Asia/Tehran'],
            'visibility'=>['studentAllowed'=>true,'ownerAllowed'=>true,'subject'=>['kind'=>'studentNumber','value'=>$student]],
            'state'=>['taskState'=>null,'ackState'=>'not_required'],'change'=>['kind'=>'none','changedAtUtc'=>null],'supersedesRef'=>null,
        ]];
    }
}

function classops_stage2_digest(array $user,string $kind,?string $nowUtc=null): array
{
    if (!in_array($kind,['tomorrow','weekly'],true)) classops_domain_error('CLASSOPS_DIGEST_KIND_INVALID','نوع خلاصه معتبر نیست.');
    $nowUtc=$nowUtc??gmdate('Y-m-d\TH:i:s\Z');
    $cohort=dent_user_cohort_key($user);
    if ($cohort==='') classops_domain_error('CLASSOPS_CANONICAL_COHORT_REQUIRED','ورودی canonical کاربر مشخص نیست.',403);
    $owner=classops_stage2_is_owner($user);
    $student=$owner?null:classops_stage2_student_number($user);
    $viewer=['scope'=>$owner?'owner':'student','cohortKey'=>$cohort,'studentNumber'=>$student,'canonicalUserId'=>null];
    $records=[];
    $store=classops_read_store();
    foreach (($store['items']??[]) as $item) {
        if (!is_array($item)||($item['cohortKey']??'')!==$cohort||($item['status']??'')==='archived') continue;
        $taskState=null;
        if (!$owner) {
            if (classops_stage2_item_audience_for_student($item,(string)$student)===null) continue;
            if (in_array((string)$item['type'],['task','requirement'],true)) {
                // Digest generation is a read projection and must never initialize
                // per-student state as a side effect.
                $taskState=classops_stage2_get_task_state($item,(string)$student,false);
            }
        }
        $records[]=classops_stage2_digest_record_from_item($item,$viewer,$taskState,$nowUtc);
    }
    if (!$owner) $records=array_merge($records,classops_stage2_term7_records($user,$nowUtc));
    try {
        return classops_digest_build([
            'contractVersion'=>'classops-digest-v1','digestKind'=>$kind,'viewer'=>$viewer,
            'window'=>['nowUtc'=>$nowUtc,'timezone'=>'Asia/Tehran'],'budget'=>['maxItems'=>60,'maxEstimatedChars'=>7000],'records'=>$records,
        ]);
    } catch (DentClassOpsDigestException $exception) {
        classops_domain_error($exception->reasonCode,'خلاصه ClassOps قابل تولید نیست.',422);
    }
}
