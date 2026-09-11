<?php
declare(strict_types=1);

require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/state_migrations.php';

function classops_stage2_reject_client_binding(array $itemOrPatch): void
{
    $extensions=$itemOrPatch['extensions']??null;
    if (is_array($extensions)&&array_key_exists(CLASSOPS_STAGE2_EXTENSION_KEY,$extensions)) {
        classops_domain_error('CLASSOPS_TRUSTED_EXTENSION_FORBIDDEN','classops_stage2_v1 فقط توسط سرور ساخته می‌شود.',403);
    }
}

function classops_stage2_derived_idem(string $root,string $lane): string
{
    if (strlen($root)<8||strlen($root)>128||preg_match('/^[A-Za-z0-9._:-]{8,128}$/D',$root)!==1) {
        classops_domain_error('CLASSOPS_INVALID_IDEMPOTENCY_KEY','کلید idempotency معتبر نیست.');
    }
    return 'stage2.'.substr(hash('sha256',$root.'|'.$lane),0,48).'.'.$lane;
}

function classops_stage2_preview(array $owner,array $input): array
{
    if (!classops_stage2_is_owner($owner)) classops_domain_error('CLASSOPS_OWNER_REQUIRED','این عملیات فقط برای مالک سامانه مجاز است.',403);
    classops_assert_known_keys($input,['item','audienceSpec','destinations','reminderPolicy','serviceRef','expectedAudienceHash']);
    $itemInput=$input['item']??null;
    if (!is_array($itemInput)||array_is_list($itemInput)) classops_domain_error('CLASSOPS_INVALID_OBJECT','item باید object باشد.');
    classops_stage2_reject_client_binding($itemInput);
    $normalized=classops_domain_store_normalize_create($itemInput);
    $cohort=(string)$normalized['cohortKey'];
    if ($cohort===''||!dent_cohort_exists($cohort)) classops_domain_error('CLASSOPS_INVALID_COHORT','ورودی canonical موردنظر وجود ندارد.');
    $spec=is_array($input['audienceSpec']??null)?$input['audienceSpec']:classops_stage2_default_audience_spec();
    $expected=trim((string)($input['expectedAudienceHash']??''));
    $resolution=classops_stage2_resolve_audience($owner,$cohort,$spec,$expected!==''?$expected:null);
    $destinations=classops_stage2_normalize_destinations($input['destinations']??null);
    $reminder=classops_stage2_normalize_reminder_policy($input['reminderPolicy']??null,(string)$normalized['type']);
    $serviceRef=array_key_exists('serviceRef',$input)&&$input['serviceRef']!==null?strtolower(trim((string)$input['serviceRef'])):null;
    if (($normalized['type']??'')==='service_reminder'&&$serviceRef===null) $serviceRef='saba';
    $binding=classops_stage2_binding_extension($resolution,$destinations,$reminder,$serviceRef);
    $draftItem=[
        'id'=>'cop_'.str_repeat('b',24),'revision'=>1,'cohortKey'=>$cohort,'type'=>$normalized['type'],'title'=>$normalized['title'],
        'description'=>$normalized['description'],'course'=>$normalized['course'],'timing'=>$normalized['timing'],'location'=>$normalized['location'],
        'importance'=>$normalized['importance'],'requireAck'=>$normalized['requireAck'],'status'=>'scheduled',
        'extensions'=>array_replace($normalized['extensions']??[],[CLASSOPS_STAGE2_EXTENSION_KEY=>$binding]),
    ];
    $delivery=classops_stage2_delivery_plan_for_item($draftItem,$resolution,$destinations);
    return [
        'success'=>true,'item'=>$normalized,
        'audience'=>classops_audience_preview($resolution,null,true),
        'audienceResolution'=>$resolution,
        'destinations'=>$delivery,'reminderPolicy'=>$reminder,'serviceRef'=>$serviceRef,
        'confirmation'=>['required'=>true,'audienceHash'=>(string)$resolution['deterministicHash'],'mutationAuthority'=>'owner-confirm-only','aiDirectSend'=>false],
    ];
}

function classops_stage2_commit_direct_intents(array $item,array $resolution,array $destinations,string $purpose='initial',?string $scheduledAt=null): array
{
    $plans=classops_stage2_delivery_plan_for_item($item,$resolution,$destinations,$purpose,$scheduledAt);
    $direct=[];
    foreach ($plans as $destination=>$plan) {
        if ($destination==='private_users') continue;
        foreach (($plan['intents']??[]) as $intent) if (is_array($intent)) $direct[]=$intent;
    }
    classops_stage2_store_delivery_intents($direct,classops_stage2_message_for_item($item));
    return ['plans'=>$plans,'direct'=>$direct];
}

function classops_stage2_existing_binding(array $item): array
{
    $extensions=is_array($item['extensions']??null)?$item['extensions']:[];
    $binding=$extensions[CLASSOPS_STAGE2_EXTENSION_KEY]??[];
    if (!is_array($binding)||array_is_list($binding)) return [];
    try { return classops_stage2_validate_binding_extension($binding,(string)($item['type']??'')); }
    catch (DentClassOpsDomainException $exception) { throw $exception; }
    catch (Throwable $exception) { classops_domain_error('CLASSOPS_STAGE2_BINDING_INVALID','Stage2 binding ذخیره‌شده معتبر نیست.',500); }
}

function classops_stage2_confirm(array $owner,array $payload): array
{
    if (!classops_stage2_is_owner($owner)) classops_domain_error('CLASSOPS_OWNER_REQUIRED','این عملیات فقط برای مالک سامانه مجاز است.',403);
    classops_assert_known_keys($payload,['mode','item','id','expectedRevision','audienceSpec','expectedAudienceHash','destinations','reminderPolicy','serviceRef','idempotencyKey','reason']);
    $mode=trim((string)($payload['mode']??'create'));
    if (!in_array($mode,['create','update'],true)) classops_domain_error('CLASSOPS_CONFIRM_MODE_INVALID','حالت تأیید معتبر نیست.');
    $itemInput=$payload['item']??null;
    if (!is_array($itemInput)||array_is_list($itemInput)) classops_domain_error('CLASSOPS_INVALID_OBJECT','item باید object باشد.');
    classops_stage2_reject_client_binding($itemInput);
    $rootIdem=trim((string)($payload['idempotencyKey']??''));
    classops_stage2_derived_idem($rootIdem,'validate');
    $reason=trim((string)($payload['reason']??'owner-confirmed'));

    $previousItem=null;
    $existingBinding=[];
    if ($mode==='create') {
        $normalized=classops_domain_store_normalize_create($itemInput);
        $cohort=(string)$normalized['cohortKey'];
    } else {
        $id=classops_stage2_require_item_id($payload['id']??'');
        $previousItem=classops_get_item($id);
        $existingBinding=classops_stage2_existing_binding($previousItem);
        $patch=classops_domain_store_normalize_patch($previousItem,$itemInput);
        $cohort=(string)($patch['cohortKey']??$previousItem['cohortKey']);
        $normalized=array_replace($previousItem,$patch);
    }
    if ($cohort===''||!dent_cohort_exists($cohort)) classops_domain_error('CLASSOPS_INVALID_COHORT','ورودی canonical موردنظر وجود ندارد.');

    if (array_key_exists('audienceSpec',$payload)) {
        if (!is_array($payload['audienceSpec'])||array_is_list($payload['audienceSpec'])) classops_domain_error('CLASSOPS_AUDIENCE_INVALID_SPEC','تعریف مخاطب باید object باشد.');
        $spec=$payload['audienceSpec'];
    } elseif ($mode==='update'&&is_array($existingBinding['audienceSpec']??null)) {
        $spec=$existingBinding['audienceSpec'];
    } else {
        $spec=classops_stage2_default_audience_spec();
    }

    $expectedHash=trim((string)($payload['expectedAudienceHash']??''));
    if (preg_match('/^[a-f0-9]{64}$/D',$expectedHash)!==1) classops_domain_error('CLASSOPS_AUDIENCE_CONFIRMATION_HASH_REQUIRED','برای commit باید hash همان preview ارسال شود.',409);
    $resolution=classops_stage2_resolve_audience($owner,$cohort,$spec,$expectedHash);

    if (array_key_exists('destinations',$payload)) {
        $destinations=classops_stage2_normalize_destinations($payload['destinations']);
    } elseif ($mode==='update'&&is_array($existingBinding['destinations']??null)) {
        $destinations=classops_stage2_normalize_destinations($existingBinding['destinations']);
    } else {
        $destinations=classops_stage2_normalize_destinations(null);
    }

    if (array_key_exists('reminderPolicy',$payload)) {
        $reminder=classops_stage2_normalize_reminder_policy($payload['reminderPolicy'],(string)$normalized['type']);
    } elseif ($mode==='update'&&array_key_exists('reminderPolicy',$existingBinding)) {
        $reminder=classops_stage2_normalize_reminder_policy($existingBinding['reminderPolicy'],(string)$normalized['type']);
    } else {
        $reminder=classops_stage2_normalize_reminder_policy(null,(string)$normalized['type']);
    }

    if (array_key_exists('serviceRef',$payload)) {
        $serviceRef=$payload['serviceRef']===null?null:strtolower(trim((string)$payload['serviceRef']));
    } elseif ($mode==='update'&&array_key_exists('serviceRef',$existingBinding)) {
        $serviceRef=$existingBinding['serviceRef'];
    } else {
        $serviceRef=null;
    }
    if (($normalized['type']??'')==='service_reminder'&&$serviceRef===null) $serviceRef='saba';
    if (($normalized['type']??'')!=='service_reminder') $serviceRef=null;

    $binding=classops_stage2_validate_binding_extension(classops_stage2_binding_extension($resolution,$destinations,$reminder,$serviceRef),(string)$normalized['type']);
    $foundationAudience=classops_stage2_foundation_audience($resolution);

    if ($mode==='create') {
        $createInput=$normalized;
        $createInput['status']='draft';
        $createInput['audienceSpec']=$foundationAudience;
        $createInput['extensions']=array_replace($createInput['extensions']??[],[CLASSOPS_STAGE2_EXTENSION_KEY=>$binding]);
        $created=classops_domain_store_create_item($createInput,$owner,classops_stage2_derived_idem($rootIdem,'draft'),$reason);
        $draft=$created['item'];
        if (($draft['status']??'')==='draft') {
            $committed=classops_domain_store_update_item((string)$draft['id'],(int)$draft['revision'],['status'=>'scheduled'],$owner,classops_stage2_derived_idem($rootIdem,'confirm'),$reason);
            $item=$committed['item'];
        } else {
            $item=$draft;
        }
    } else {
        $current=$previousItem;
        $expectedRevision=(int)($payload['expectedRevision']??0);
        if ($expectedRevision<1) classops_domain_error('CLASSOPS_EXPECTED_REVISION_REQUIRED','expectedRevision معتبر الزامی است.');
        $patch=classops_domain_store_normalize_patch($current,$itemInput);
        $patch['extensions']=array_replace($current['extensions']??[],$patch['extensions']??[],[CLASSOPS_STAGE2_EXTENSION_KEY=>$binding]);
        $patch['audienceSpec']=$foundationAudience;
        if (!array_key_exists('status',$patch)&&($current['status']??'')==='draft') $patch['status']='scheduled';
        $committed=classops_domain_store_update_item((string)$current['id'],$expectedRevision,$patch,$owner,$rootIdem,$reason);
        $item=$committed['item'];
        classops_stage2_supersede_item_deliveries((string)$item['id'],(int)$item['revision'],'newer_revision');
    }

    classops_stage2_save_audience($item,$resolution['normalizedSpec'],$resolution);
    if (is_array($previousItem)) {
        classops_stage2_carry_task_states($previousItem,$item,$resolution['recipientStudentNumbers']);
        classops_stage2_carry_service_states($previousItem,$item,$resolution['recipientStudentNumbers']);
    }
    classops_stage2_ensure_task_states($item,$resolution['recipientStudentNumbers']);
    classops_stage2_ensure_service_states($item,$resolution['recipientStudentNumbers']);
    classops_stage2_remove_prior_notifications((string)$item['id'],(int)$item['revision']);
    $allowPrivate=in_array('private_users',$destinations,true);
    $notificationRecords=classops_stage2_ensure_notification_record($item,$resolution['recipientStudentNumbers'],$allowPrivate);
    $delivery=classops_stage2_commit_direct_intents($item,$resolution,$destinations);
    return [
        'success'=>true,'item'=>$item,'audience'=>classops_audience_preview($resolution,null,false),
        'notificationCount'=>count($notificationRecords),'directDeliveryIntentCount'=>count($delivery['direct']),'deliveryPreview'=>$delivery['plans'],
    ];
}

function classops_stage2_owner_task_transition(array $owner,array $payload): array
{
    if (!classops_stage2_is_owner($owner)) classops_domain_error('CLASSOPS_OWNER_REQUIRED','این عملیات فقط برای مالک سامانه مجاز است.',403);
    classops_assert_known_keys($payload,['id','studentNumber','expectedStateRevision','target','commandId','reason']);
    $item=classops_get_item(classops_stage2_require_item_id($payload['id']??''));
    if (!in_array((string)$item['type'],['task','requirement'],true)) classops_domain_error('CLASSOPS_TASK_TYPE_REQUIRED','این آیتم task/requirement نیست.');
    $student=dent_normalize_student_number((string)($payload['studentNumber']??''));
    if ($student===''||classops_stage2_item_audience_for_student($item,$student)===null) classops_domain_error('CLASSOPS_TASK_STUDENT_NOT_ELIGIBLE','دانشجو عضو audience این revision نیست.',403);
    $actor=classops_actor($owner);
    return classops_stage2_transition_task($item,$student,(int)($payload['expectedStateRevision']??0),trim((string)($payload['target']??'')),trim((string)($payload['commandId']??'')),(string)$actor['ref'],trim((string)($payload['reason']??'')));
}

function classops_stage2_owner_ack_stats(array $owner,array $item): array
{
    if (!classops_stage2_is_owner($owner)) classops_domain_error('CLASSOPS_OWNER_REQUIRED','این عملیات فقط برای مالک سامانه مجاز است.',403);
    if (($item['type']??'')!=='critical_notice'||empty($item['requireAck'])) classops_domain_error('CLASSOPS_ACK_NOT_REQUIRED','این آیتم ACK ندارد.');
    $aud=classops_stage2_get_audience((string)$item['id'],(int)$item['revision']);
    if (!is_array($aud)) classops_domain_error('CLASSOPS_AUDIENCE_SNAPSHOT_MISSING','snapshot مخاطبان این revision در دسترس نیست.',503);
    return classops_ack_owner_stats(classops_stage2_ack_state(),$item,$aud['snapshot']['recipientStudentNumbers']??[]);
}

function classops_stage2_cancel_or_archive(array $owner,string $action,string $id,int $expectedRevision,string $idempotencyKey,string $reason): array
{
    if (!classops_stage2_is_owner($owner)) classops_domain_error('CLASSOPS_OWNER_REQUIRED','این عملیات فقط برای مالک سامانه مجاز است.',403);
    $result=classops_transition_item($action,$id,$expectedRevision,$owner,$idempotencyKey,$reason);
    $item=$result['item'];
    classops_stage2_supersede_item_deliveries((string)$item['id'],PHP_INT_MAX,'item_'.$action);
    classops_stage2_notification_remove_item((string)$item['id']);
    return $result;
}
