<?php
declare(strict_types=1);

require_once __DIR__ . '/service_state.php';

/**
 * Carry canonical per-student task/requirement state forward to a new item
 * revision for students who remain in the confirmed audience. The old revision
 * remains untouched so historical projections and audits stay reconstructable.
 */
function classops_stage2_carry_task_states(array $previousItem, array $nextItem, array $recipientStudentNumbers): int
{
    if (!in_array((string)($previousItem['type'] ?? ''), ['task','requirement'], true)
        || !in_array((string)($nextItem['type'] ?? ''), ['task','requirement'], true)
        || (string)($previousItem['id'] ?? '') !== (string)($nextItem['id'] ?? '')
        || (int)($nextItem['revision'] ?? 0) <= (int)($previousItem['revision'] ?? 0)) {
        return 0;
    }
    $oldRevision=(int)$previousItem['revision'];
    $newRevision=(int)$nextItem['revision'];
    $itemId=(string)$nextItem['id'];
    $cohort=(string)$nextItem['cohortKey'];
    $students=[];
    foreach ($recipientStudentNumbers as $raw) {
        try { $student=classops_task_digits($raw); } catch (Throwable $e) { continue; }
        // Numeric-looking PHP array keys are coerced to int. Keep the canonical
        // identifier as the value behind a non-numeric key so strict string
        // contracts remain intact for every student number.
        $students['student:'.$student]=$student;
    }
    if ($students===[]) return 0;

    $tx=classops_stage2_transaction(static function(array &$state) use($students,$oldRevision,$newRevision,$itemId,$cohort): int {
        $carried=0;
        foreach ($students as $student) {
            $oldKey=classops_stage2_task_key($itemId,$oldRevision,$student);
            $newKey=classops_stage2_task_key($itemId,$newRevision,$student);
            if (isset($state['taskStates'][$newKey])) continue;
            $old=$state['taskStates'][$oldKey]??null;
            if (!is_array($old)) continue;
            $next=$old;
            $next['itemRevision']=$newRevision;
            $next['cohortKey']=$cohort;
            $next['updatedAt']=gmdate('Y-m-d\TH:i:s\Z');
            try { $next=classops_task_validate_state($next); }
            catch (DentClassOpsTaskException $exception) {
                classops_domain_error('CLASSOPS_TASK_STATE_CARRY_INVALID','وضعیت task قبلی قابل انتقال به revision جدید نیست.',500);
            }
            $state['taskStates'][$newKey]=$next;
            $carried++;
        }
        $state['updatedAt']=dent_iso_now();
        return $carried;
    },'task-state-carry');
    return (int)$tx['result'];
}

/**
 * Saba/service completion is local ClassOps state. Preserve it across a benign
 * item revision for students who remain eligible, without ever setting or
 * implying external verification.
 */
function classops_stage2_carry_service_states(array $previousItem, array $nextItem, array $recipientStudentNumbers): int
{
    if (($previousItem['type'] ?? '')!=='service_reminder' || ($nextItem['type'] ?? '')!=='service_reminder'
        || (string)($previousItem['id'] ?? '') !== (string)($nextItem['id'] ?? '')
        || (int)($nextItem['revision'] ?? 0) <= (int)($previousItem['revision'] ?? 0)) {
        return 0;
    }
    $oldRevision=(int)$previousItem['revision'];
    $newRevision=(int)$nextItem['revision'];
    $itemId=(string)$nextItem['id'];
    $students=[];
    foreach ($recipientStudentNumbers as $raw) {
        $student=dent_normalize_student_number((string)$raw);
        if ($student!=='') $students['student:'.$student]=$student;
    }
    if ($students===[]) return 0;

    $tx=classops_stage2_transaction(static function(array &$state) use($students,$oldRevision,$newRevision,$itemId): int {
        $carried=0;
        foreach ($students as $student) {
            $oldKey=classops_stage2_service_state_key($itemId,$oldRevision,$student);
            $newKey=classops_stage2_service_state_key($itemId,$newRevision,$student);
            if (isset($state['schedulerOccurrences'][$newKey])) continue;
            $old=$state['schedulerOccurrences'][$oldKey]??null;
            if (!is_array($old)||($old['kind']??'')!=='service_state') continue;
            $next=$old;
            $next['itemRevision']=$newRevision;
            $next['updatedAt']=gmdate('Y-m-d\TH:i:s\Z');
            $next['externallyVerified']=false;
            $state['schedulerOccurrences'][$newKey]=$next;
            $carried++;
        }
        $state['updatedAt']=dent_iso_now();
        return $carried;
    },'service-state-carry');
    return (int)$tx['result'];
}
