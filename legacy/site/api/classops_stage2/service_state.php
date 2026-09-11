<?php
declare(strict_types=1);

require_once __DIR__ . '/capabilities.php';

function classops_stage2_service_state_key(string $itemId,int $revision,string $studentNumber): string
{
    return 'svc_' . hash('sha256','classops-service-state-v1|'.$itemId.'|'.$revision.'|'.$studentNumber);
}

function classops_stage2_service_state_default(array $item,string $studentNumber): array
{
    return [
        'kind'=>'service_state','contractVersion'=>'classops-service-state-v1','itemId'=>(string)$item['id'],'itemRevision'=>(int)$item['revision'],
        'studentNumber'=>$studentNumber,'state'=>'pending','stateRevision'=>1,'history'=>[],'commands'=>[],'updatedAt'=>gmdate('Y-m-d\TH:i:s\Z'),
        'externallyVerified'=>false,
    ];
}

function classops_stage2_ensure_service_states(array $item,array $studentNumbers): int
{
    if (($item['type']??'')!=='service_reminder') return 0;
    $tx=classops_stage2_transaction(static function(array &$state) use($item,$studentNumbers): int {
        $created=0;
        foreach ($studentNumbers as $raw) {
            $student=dent_normalize_student_number((string)$raw);
            if ($student==='') continue;
            $key=classops_stage2_service_state_key((string)$item['id'],(int)$item['revision'],$student);
            if (isset($state['schedulerOccurrences'][$key])) continue;
            $state['schedulerOccurrences'][$key]=classops_stage2_service_state_default($item,$student);
            $created++;
        }
        $state['updatedAt']=dent_iso_now();
        return $created;
    },'service-state-init');
    return (int)$tx['result'];
}

function classops_stage2_get_service_state(array $item,string $studentNumber): ?array
{
    $student=dent_normalize_student_number($studentNumber);
    if ($student==='') return null;
    $state=classops_stage2_read_state();
    $entry=$state['schedulerOccurrences'][classops_stage2_service_state_key((string)$item['id'],(int)$item['revision'],$student)]??null;
    return is_array($entry)&&($entry['kind']??'')==='service_state'?$entry:null;
}

function classops_stage2_transition_service_state(array $item,string $studentNumber,int $expectedRevision,string $target,string $commandId): array
{
    $student=dent_normalize_student_number($studentNumber);
    if ($student===''||!in_array($target,['completed','waived'],true)) classops_domain_error('CLASSOPS_SERVICE_STATE_INVALID','وضعیت محلی یادآوری معتبر نیست.');
    if ($expectedRevision<1||preg_match('/^[A-Za-z0-9._:-]{8,128}$/D',$commandId)!==1) classops_domain_error('CLASSOPS_SERVICE_COMMAND_INVALID','درخواست تغییر وضعیت معتبر نیست.');
    $key=classops_stage2_service_state_key((string)$item['id'],(int)$item['revision'],$student);
    $tx=classops_stage2_transaction(static function(array &$state) use($key,$item,$student,$expectedRevision,$target,$commandId): array {
        $current=$state['schedulerOccurrences'][$key]??classops_stage2_service_state_default($item,$student);
        if (isset($current['commands'][$commandId])) return $current;
        if ((int)($current['stateRevision']??0)!==$expectedRevision) classops_domain_error('CLASSOPS_SERVICE_STATE_STALE','وضعیت یادآوری تغییر کرده است.',409);
        $from=(string)($current['state']??'pending');
        if (!in_array($from,['pending','completed','waived'],true)) classops_domain_error('CLASSOPS_SERVICE_STATE_INVALID','وضعیت ذخیره‌شده معتبر نیست.',503);
        $current['state']=$target;
        $current['stateRevision']=$expectedRevision+1;
        $event=['from'=>$from,'to'=>$target,'at'=>gmdate('Y-m-d\TH:i:s\Z'),'commandHash'=>hash('sha256',$commandId)];
        $history=is_array($current['history']??null)?$current['history']:[];
        $history[]=$event;
        $current['history']=array_slice($history,-64);
        $commands=is_array($current['commands']??null)?$current['commands']:[];
        $commands[$commandId]=hash('sha256',classops_canonical_json([$from,$target]));
        if (count($commands)>64) array_shift($commands);
        $current['commands']=$commands;
        $current['updatedAt']=$event['at'];
        $current['externallyVerified']=false;
        $state['schedulerOccurrences'][$key]=$current;
        $state['updatedAt']=dent_iso_now();
        return $current;
    },'service-state-transition');
    return $tx['result'];
}
