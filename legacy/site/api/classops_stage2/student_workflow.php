<?php
declare(strict_types=1);

require_once __DIR__ . '/owner_workflow.php';
require_once __DIR__ . '/service_state.php';
require_once dirname(__DIR__) . '/classops_modules/exams/exam_ops.php';

function classops_stage2_item_audience_for_student(array $item,string $studentNumber): ?array
{
    $record=classops_stage2_get_audience((string)$item['id'],(int)$item['revision']);
    if (!is_array($record)) return null;
    $recipients=$record['snapshot']['recipientStudentNumbers']??[];
    return is_array($recipients)&&in_array($studentNumber,$recipients,true)?$record:null;
}

function classops_stage2_student_item_projection(array $item,array $user): array
{
    $student=classops_stage2_student_number($user);
    if (dent_user_cohort_key($user)!==(string)($item['cohortKey']??'')) classops_domain_error('CLASSOPS_ITEM_NOT_VISIBLE','این آیتم برای حساب شما قابل مشاهده نیست.',403);
    $audience=classops_stage2_item_audience_for_student($item,$student);
    if ($audience===null) classops_domain_error('CLASSOPS_ITEM_NOT_VISIBLE','این آیتم برای حساب شما قابل مشاهده نیست.',403);
    $projection=[
        'id'=>$item['id'],'revision'=>$item['revision'],'type'=>$item['type'],'title'=>$item['title'],'description'=>$item['description'],
        'course'=>$item['course'],'timing'=>$item['timing'],'location'=>$item['location'],'importance'=>$item['importance'],
        'status'=>$item['status'],'requireAck'=>$item['requireAck'],
    ];
    if (in_array((string)$item['type'],['task','requirement'],true)) {
        // Reads must never initialize state. Task state is created on owner
        // confirmation, or lazily only inside an explicit mutation transaction.
        $state=classops_stage2_get_task_state($item,$student,false);
        $due=is_array($item['timing']??null)?($item['timing']['dueAt']??null):null;
        $projection['task']=$state===null?null:classops_task_student_projection($state,$student,is_string($due)?$due:null,gmdate('Y-m-d\TH:i:s\Z'));
    }
    if (($item['type']??'')==='exam') {
        try { $projection['exam']=classops_exam_projection($item); }
        catch (Throwable $e) { $projection['exam']=null; }
    }
    if (($item['type']??'')==='critical_notice'&&!empty($item['requireAck'])) {
        $projection['ack']=['acked'=>classops_ack_is_satisfied(classops_stage2_ack_state(),$item,$student),'revision'=>(int)$item['revision']];
    }
    if (($item['type']??'')==='service_reminder') {
        $serviceState=classops_stage2_get_service_state($item,$student);
        $projection['service']=[
            'serviceRef'=>$item['extensions'][CLASSOPS_STAGE2_EXTENSION_KEY]['serviceRef']??null,
            'externallyVerified'=>false,
            'claim'=>'local-reminder-only',
            'state'=>$serviceState,
        ];
    }
    return $projection;
}

function classops_stage2_student_list(array $user,array $filters=[]): array
{
    $student=classops_stage2_student_number($user);
    $cohort=dent_user_cohort_key($user);
    if ($cohort==='') classops_domain_error('CLASSOPS_CANONICAL_COHORT_REQUIRED','ورودی canonical کاربر مشخص نیست.',403);
    $allowedFilters=['type','status'];
    foreach (array_keys($filters) as $key) if (!in_array($key,$allowedFilters,true)) classops_domain_error('CLASSOPS_UNKNOWN_FIELD','فیلتر شناخته‌شده نیست.');
    $store=classops_read_store();
    $items=[];
    foreach (($store['items']??[]) as $item) {
        if (!is_array($item)||($item['cohortKey']??'')!==$cohort||in_array((string)($item['status']??''),['archived','cancelled'],true)) continue;
        if (($filters['type']??'')!==''&&($item['type']??'')!==$filters['type']) continue;
        if (($filters['status']??'')!==''&&($item['status']??'')!==$filters['status']) continue;
        if (classops_stage2_item_audience_for_student($item,$student)===null) continue;
        $items[]=classops_stage2_student_item_projection($item,$user);
    }
    usort($items,static function(array $a,array $b):int {
        $left=(string)(($a['timing']['dueAt']??'')?:($a['timing']['startsAt']??''));
        $right=(string)(($b['timing']['dueAt']??'')?:($b['timing']['startsAt']??''));
        $cmp=strcmp($left,$right);
        return $cmp!==0?$cmp:strcmp((string)$a['id'],(string)$b['id']);
    });
    return ['count'=>count($items),'items'=>array_slice($items,0,100)];
}

function classops_stage2_student_get(array $user,string $id): array
{
    return classops_stage2_student_item_projection(classops_get_item(classops_stage2_require_item_id($id)),$user);
}

function classops_stage2_student_task_transition(array $user,array $payload): array
{
    classops_assert_known_keys($payload,['id','expectedStateRevision','target','commandId','reason']);
    $item=classops_get_item(classops_stage2_require_item_id($payload['id']??''));
    if (!in_array((string)$item['type'],['task','requirement'],true)) classops_domain_error('CLASSOPS_TASK_TYPE_REQUIRED','این آیتم task/requirement نیست.');
    $student=classops_stage2_student_number($user);
    if (classops_stage2_item_audience_for_student($item,$student)===null) classops_domain_error('CLASSOPS_ITEM_NOT_VISIBLE','این آیتم برای حساب شما قابل مشاهده نیست.',403);
    $target=trim((string)($payload['target']??''));
    if (!in_array($target,['submitted','completed'],true)) classops_domain_error('CLASSOPS_TASK_STUDENT_TRANSITION_FORBIDDEN','این تغییر وضعیت برای دانشجو مجاز نیست.',403);
    return classops_stage2_transition_task(
        $item,$student,(int)($payload['expectedStateRevision']??0),$target,trim((string)($payload['commandId']??'')),
        'student:'.substr(hash('sha256',$student),0,32),trim((string)($payload['reason']??''))
    );
}

function classops_stage2_student_ack(array $user,array $payload): array
{
    classops_assert_known_keys($payload,['id','expectedRevision','idempotencyKey']);
    $item=classops_get_item(classops_stage2_require_item_id($payload['id']??''));
    $student=classops_stage2_student_number($user);
    $aud=classops_stage2_item_audience_for_student($item,$student);
    if ($aud===null) classops_domain_error('CLASSOPS_ACK_NOT_ELIGIBLE','این اطلاعیه برای حساب شما نیست.',403);
    if ((int)($payload['expectedRevision']??0)!==(int)$item['revision']) classops_domain_error('CLASSOPS_ACK_STALE_REVISION','نسخه اطلاعیه تغییر کرده است.',409);
    return classops_stage2_record_ack($item,$student,(string)$aud['resolutionHash'],trim((string)($payload['idempotencyKey']??'')));
}

function classops_stage2_student_service_transition(array $user,array $payload): array
{
    classops_assert_known_keys($payload,['id','expectedStateRevision','target','commandId']);
    $item=classops_get_item(classops_stage2_require_item_id($payload['id']??''));
    if (($item['type']??'')!=='service_reminder') classops_domain_error('CLASSOPS_SERVICE_REMINDER_REQUIRED','این آیتم service reminder نیست.');
    $student=classops_stage2_student_number($user);
    if (classops_stage2_item_audience_for_student($item,$student)===null) classops_domain_error('CLASSOPS_ITEM_NOT_VISIBLE','این آیتم برای حساب شما قابل مشاهده نیست.',403);
    return classops_stage2_transition_service_state(
        $item,$student,(int)($payload['expectedStateRevision']??0),trim((string)($payload['target']??'')),trim((string)($payload['commandId']??''))
    );
}
