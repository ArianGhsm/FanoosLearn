<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/classops_stage2/digests.php';
require_once __DIR__ . '/classops_stage2_scheduler.php';

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow, noarchive');

const CLASSOPS_API_MAX_BODY_BYTES = 65536;
const CLASSOPS_API_MAX_DEPTH = 12;
const CLASSOPS_API_MAX_NODES = 2500;
const CLASSOPS_API_AI_CALLS_PER_MINUTE = 12;

function classops_api_assert_bounded_value($value, int $depth = 0, int &$nodes = 0): void
{
    $nodes++;
    if ($nodes > CLASSOPS_API_MAX_NODES || $depth > CLASSOPS_API_MAX_DEPTH) {
        classops_domain_error('CLASSOPS_REQUEST_COMPLEXITY_EXCEEDED', 'ساختار درخواست بیش از حد پیچیده است.', 413);
    }
    if (!is_array($value)) return;
    foreach ($value as $child) classops_api_assert_bounded_value($child, $depth + 1, $nodes);
}

function classops_api_payload(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $declaredLength = max(0, (int) ($_SERVER['CONTENT_LENGTH'] ?? 0));
        if ($declaredLength > CLASSOPS_API_MAX_BODY_BYTES) {
            classops_domain_error('CLASSOPS_REQUEST_TOO_LARGE', 'حجم درخواست بیش از حد مجاز است.', 413);
        }
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || strlen($raw) > CLASSOPS_API_MAX_BODY_BYTES) {
            classops_domain_error('CLASSOPS_REQUEST_TOO_LARGE', 'حجم درخواست بیش از حد مجاز است.', 413);
        }
        try {
            $decoded = json_decode($raw, true, CLASSOPS_API_MAX_DEPTH + 4, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            classops_domain_error('CLASSOPS_INVALID_JSON', 'بدنه JSON معتبر نیست.');
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            classops_domain_error('CLASSOPS_INVALID_JSON', 'بدنه JSON باید object باشد.');
        }
        $nodes = 0;
        classops_api_assert_bounded_value($decoded, 0, $nodes);
        return $decoded;
    }
    $decoded = is_array($_POST) ? $_POST : [];
    $nodes = 0;
    classops_api_assert_bounded_value($decoded, 0, $nodes);
    return $decoded;
}

function classops_api_object_field(array $payload, string $field, bool $required = true): array
{
    if (!array_key_exists($field, $payload) && !$required) return [];
    $value = $payload[$field] ?? [];
    if (is_string($value)) {
        try {
            $value = json_decode($value, true, CLASSOPS_API_MAX_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            classops_domain_error('CLASSOPS_INVALID_OBJECT', "فیلد {$field} باید object معتبر باشد.");
        }
    }
    if (!is_array($value) || array_is_list($value)) {
        classops_domain_error('CLASSOPS_INVALID_OBJECT', "فیلد {$field} باید object معتبر باشد.");
    }
    return $value;
}

function classops_api_validate_cohort(string $cohortKey): void
{
    if ($cohortKey === '' || !dent_cohort_exists($cohortKey)) {
        classops_domain_error('CLASSOPS_INVALID_COHORT', 'ورودی canonical موردنظر وجود ندارد.');
    }
}

function classops_api_require_method(string $method): void
{
    if (dent_request_method() !== $method) {
        classops_domain_error('CLASSOPS_METHOD_NOT_ALLOWED', 'متد این عملیات معتبر نیست.', 405);
    }
}

function classops_api_owner_for_read(): array
{
    $viewer = dent_require_owner();
    dent_release_session_lock();
    return $viewer;
}

function classops_api_owner_for_post(bool $aiRateGuard = false): array
{
    classops_api_require_method('POST');
    dent_auth_session_require_csrf();
    $viewer = dent_require_owner();
    if ($aiRateGuard) classops_api_ai_rate_guard();
    dent_release_session_lock();
    return $viewer;
}

function classops_api_student_for_read(): array
{
    $viewer = function_exists('dent_require_main_site_user') ? dent_require_main_site_user() : dent_require_user();
    if (classops_stage2_is_owner($viewer)) {
        classops_domain_error('CLASSOPS_STUDENT_SCOPE_REQUIRED', 'این endpoint مخصوص projection شخصی دانشجو است.', 403);
    }
    classops_stage2_student_number($viewer);
    dent_release_session_lock();
    return $viewer;
}

function classops_api_student_for_post(): array
{
    classops_api_require_method('POST');
    dent_auth_session_require_csrf();
    $viewer = function_exists('dent_require_main_site_user') ? dent_require_main_site_user() : dent_require_user();
    if (classops_stage2_is_owner($viewer)) {
        classops_domain_error('CLASSOPS_STUDENT_SCOPE_REQUIRED', 'این endpoint مخصوص projection شخصی دانشجو است.', 403);
    }
    classops_stage2_student_number($viewer);
    dent_release_session_lock();
    return $viewer;
}

function classops_api_user_for_digest(): array
{
    $viewer = function_exists('dent_require_main_site_user') ? dent_require_main_site_user() : dent_require_user();
    if (!classops_stage2_is_owner($viewer)) classops_stage2_student_number($viewer);
    dent_release_session_lock();
    return $viewer;
}

function classops_api_ai_rate_guard(): void
{
    $now = time();
    $window = [];
    foreach (is_array($_SESSION['classopsAiRate'] ?? null) ? $_SESSION['classopsAiRate'] : [] as $value) {
        $at = (int) $value;
        if ($at > $now - 60 && $at <= $now + 5) $window[] = $at;
    }
    if (count($window) >= CLASSOPS_API_AI_CALLS_PER_MINUTE) {
        classops_domain_error('CLASSOPS_AI_RATE_LIMITED', 'تعداد درخواست‌های AI در بازه کوتاه بیش از حد مجاز است.', 429);
    }
    $window[] = $now;
    $_SESSION['classopsAiRate'] = array_slice($window, -CLASSOPS_API_AI_CALLS_PER_MINUTE);
}

function classops_api_stage2_status(): array
{
    $state = classops_stage2_read_state();
    $foundation = classops_status();
    // Foundation fields remain at the top level for existing clients. Stage2
    // telemetry is strictly additive and does not rewrite classops-v1 meaning.
    $foundation['stage2'] = [
        'contractVersion' => CLASSOPS_STAGE2_STATE_CONTRACT_VERSION,
        'schemaVersion' => CLASSOPS_STAGE2_STATE_SCHEMA_VERSION,
        'updatedAt' => (string) ($state['updatedAt'] ?? ''),
        'audienceSnapshots' => count($state['audienceSnapshots'] ?? []),
        'taskStates' => count($state['taskStates'] ?? []),
        'deliveryIntents' => count($state['deliveryIntents'] ?? []),
        'schedulerOccurrences' => count($state['schedulerOccurrences'] ?? []),
        'callbackRefs' => count($state['callbackRefs'] ?? []),
    ];
    $foundation['capabilities'] = classops_stage2_capabilities();
    return $foundation;
}

function classops_api_draft_validate(array $payload): array
{
    classops_assert_known_keys($payload, ['action','item']);
    $item = classops_api_object_field($payload, 'item');
    classops_stage2_reject_client_binding($item);
    $normalized = classops_domain_store_normalize_create($item);
    classops_api_validate_cohort((string) $normalized['cohortKey']);
    return ['success'=>true,'valid'=>true,'item'=>$normalized,'mutationPerformed'=>false];
}

function classops_api_audience_preview(array $owner, array $payload): array
{
    classops_assert_known_keys($payload, ['action','cohortKey','audienceSpec','expectedAudienceHash']);
    $cohort = trim((string) ($payload['cohortKey'] ?? ''));
    classops_api_validate_cohort($cohort);
    $spec = classops_api_object_field($payload, 'audienceSpec');
    $expected = trim((string) ($payload['expectedAudienceHash'] ?? ''));
    $resolution = classops_stage2_resolve_audience($owner, $cohort, $spec, $expected === '' ? null : $expected);
    return [
        'success'=>true,
        'preview'=>classops_audience_preview($resolution, null, true),
        'resolution'=>$resolution,
        'mutationPerformed'=>false,
    ];
}

function classops_api_reminder_preview(array $owner, array $payload): array
{
    classops_assert_known_keys($payload, ['action','item','audienceSpec','destinations','reminderPolicy','serviceRef','expectedAudienceHash']);
    $copy = $payload;
    unset($copy['action']);
    $preview = classops_stage2_preview($owner, $copy);
    $policy = $preview['reminderPolicy'] ?? null;
    if (!is_array($policy)) {
        return ['success'=>true,'policy'=>null,'plan'=>['ok'=>true,'intents'=>[],'supersessions'=>[]],'mutationPerformed'=>false];
    }
    $item = $preview['item'];
    $resolution = $preview['audienceResolution'];
    $plan = classops_reminder_plan([
        'contractVersion'=>'classops-reminder-v1',
        'planningHorizonSeconds'=>CLASSOPS_REMINDER_MAX_HORIZON_SECONDS,
        'maxOccurrences'=>64,
        'items'=>[[
            'itemId'=>'cop_'.str_repeat('c',24),
            'revision'=>1,
            'itemType'=>(string)$item['type'],
            'status'=>'active',
            'timing'=>['startsAt'=>$item['timing']['startsAt']??null,'dueAt'=>$item['timing']['dueAt']??null],
            'audience'=>['ref'=>'audience_preview','hash'=>(string)$resolution['deterministicHash'],],
            'deliveryPolicyRef'=>'delivery_policy_preview',
            'reminderPolicy'=>$policy,
            'serviceRef'=>$preview['serviceRef']??null,
        ]],
        'knownOccurrences'=>[],
    ]);
    return ['success'=>true,'policy'=>$policy,'plan'=>$plan,'mutationPerformed'=>false];
}

function classops_api_owner_task_state(): array
{
    $id = classops_stage2_require_item_id($_GET['id'] ?? '');
    $student = dent_normalize_student_number((string) ($_GET['studentNumber'] ?? ''));
    if ($student === '') classops_domain_error('CLASSOPS_TASK_INVALID_STUDENT', 'شماره دانشجویی canonical معتبر نیست.');
    $item = classops_get_item($id);
    if (!in_array((string) $item['type'], ['task','requirement'], true)) {
        classops_domain_error('CLASSOPS_TASK_TYPE_REQUIRED', 'این آیتم task/requirement نیست.');
    }
    if (classops_stage2_item_audience_for_student($item, $student) === null) {
        classops_domain_error('CLASSOPS_TASK_STUDENT_NOT_ELIGIBLE', 'دانشجو عضو audience این revision نیست.', 403);
    }
    return ['success'=>true,'state'=>classops_stage2_get_task_state($item,$student,false)];
}

function classops_api_legacy_create(array $owner, array $payload): array
{
    classops_assert_known_keys($payload, ['action','idempotencyKey','reason','item']);
    $item = classops_api_object_field($payload, 'item');
    classops_stage2_reject_client_binding($item);
    classops_api_validate_cohort(trim((string) ($item['cohortKey'] ?? '')));
    return ['success'=>true] + classops_domain_store_create_item(
        $item,
        $owner,
        (string) ($payload['idempotencyKey'] ?? ''),
        (string) ($payload['reason'] ?? '')
    );
}

function classops_api_legacy_update(array $owner, array $payload): array
{
    classops_assert_known_keys($payload, ['action','idempotencyKey','reason','id','expectedRevision','patch']);
    $patch = classops_api_object_field($payload, 'patch');
    classops_stage2_reject_client_binding($patch);
    if (array_key_exists('cohortKey', $patch)) classops_api_validate_cohort(trim((string) $patch['cohortKey']));
    return ['success'=>true] + classops_domain_store_update_item(
        classops_stage2_require_item_id($payload['id'] ?? ''),
        (int) ($payload['expectedRevision'] ?? 0),
        $patch,
        $owner,
        (string) ($payload['idempotencyKey'] ?? ''),
        (string) ($payload['reason'] ?? '')
    );
}

try {
    $method = dent_request_method();
    $payload = $method === 'POST' ? classops_api_payload() : [];
    $action = dent_request_action();
    if ($action === '' && isset($payload['action'])) $action = trim((string) $payload['action']);
    if ($action === '') $action = 'capabilities';

    // Public machine-readable capabilities keep the original classops-v1 shape
    // and add Stage2 information. They expose no identities or destination IDs.
    if ($action === 'capabilities') {
        classops_api_require_method('GET');
        $foundation = classops_capabilities();
        $foundation['surface'] = classops_stage2_capabilities();
        $foundation['domain'] = classops_domain_capabilities();
        dent_json_response($foundation);
    }
    if ($action === 'domain-capabilities') {
        classops_api_require_method('GET');
        dent_json_response(['success'=>true,'domain'=>classops_domain_capabilities(),'surface'=>classops_stage2_capabilities()]);
    }
    if ($action === 'status') {
        classops_api_require_method('GET');
        classops_api_owner_for_read();
        dent_json_response(['success'=>true,'status'=>classops_api_stage2_status()]);
    }

    // Foundation CRUD compatibility remains owner-only and side-effect-free
    // beyond the canonical classops-v1 store. Product publication uses the
    // explicit Stage2 preview -> confirm path below.
    if ($action === 'list') {
        classops_api_require_method('GET');
        classops_api_owner_for_read();
        classops_assert_known_keys($_GET, ['action','cohortKey','type','status','limit','cursor']);
        $cohort = trim((string) ($_GET['cohortKey'] ?? ''));
        if ($cohort !== '') classops_api_validate_cohort($cohort);
        dent_json_response(['success'=>true,'data'=>classops_list_items([
            'cohortKey'=>$cohort,
            'type'=>(string)($_GET['type']??''),
            'status'=>(string)($_GET['status']??''),
            'limit'=>$_GET['limit']??25,
            'cursor'=>(string)($_GET['cursor']??''),
        ])]);
    }
    if ($action === 'get') {
        classops_api_require_method('GET');
        classops_api_owner_for_read();
        classops_assert_known_keys($_GET, ['action','id']);
        $item = classops_get_item(classops_stage2_require_item_id($_GET['id'] ?? ''));
        $aud = classops_stage2_get_audience((string)$item['id'],(int)$item['revision']);
        dent_json_response([
            'success'=>true,
            'item'=>$item,
            'audience'=>$aud===null?null:[
                'resolutionHash'=>$aud['resolutionHash'],
                'recipientCount'=>count($aud['snapshot']['recipientStudentNumbers']??[]),
            ],
        ]);
    }
    if ($action === 'revisions') {
        classops_api_require_method('GET');
        classops_api_owner_for_read();
        classops_assert_known_keys($_GET, ['action','id','limit','cursor']);
        dent_json_response(['success'=>true,'data'=>classops_revision_history(
            classops_stage2_require_item_id($_GET['id'] ?? ''),
            (int)($_GET['limit']??50),
            (string)($_GET['cursor']??'')
        )]);
    }
    if ($action === 'create') {
        $owner = classops_api_owner_for_post();
        dent_json_response(classops_api_legacy_create($owner, $payload));
    }
    if ($action === 'update') {
        $owner = classops_api_owner_for_post();
        dent_json_response(classops_api_legacy_update($owner, $payload));
    }

    // Owner projections.
    if ($action === 'task-state') {
        classops_api_require_method('GET');
        classops_api_owner_for_read();
        classops_assert_known_keys($_GET, ['action','id','studentNumber']);
        dent_json_response(classops_api_owner_task_state());
    }
    if ($action === 'ack-stats') {
        classops_api_require_method('GET');
        $owner = classops_api_owner_for_read();
        classops_assert_known_keys($_GET, ['action','id']);
        $item = classops_get_item(classops_stage2_require_item_id($_GET['id']??''));
        dent_json_response(['success'=>true,'stats'=>classops_stage2_owner_ack_stats($owner,$item)]);
    }

    // Personalized student reads. Client-provided actor/cohort are never used.
    if ($action === 'student-list') {
        classops_api_require_method('GET');
        $student = classops_api_student_for_read();
        classops_assert_known_keys($_GET,['action','type','status']);
        dent_json_response(['success'=>true,'data'=>classops_stage2_student_list($student,[
            'type'=>(string)($_GET['type']??''),
            'status'=>(string)($_GET['status']??''),
        ])]);
    }
    if ($action === 'student-get') {
        classops_api_require_method('GET');
        $student = classops_api_student_for_read();
        classops_assert_known_keys($_GET,['action','id']);
        dent_json_response(['success'=>true,'item'=>classops_stage2_student_get($student,(string)($_GET['id']??''))]);
    }
    if ($action === 'tomorrow-summary' || $action === 'weekly-digest') {
        classops_api_require_method('GET');
        $viewer = classops_api_user_for_digest();
        classops_assert_known_keys($_GET,['action']);
        dent_json_response(['success'=>true,'digest'=>classops_stage2_digest(
            $viewer,
            $action==='tomorrow-summary'?'tomorrow':'weekly'
        )]);
    }

    // Preview-only management actions.
    if ($action === 'draft-validate') {
        classops_api_owner_for_post();
        dent_json_response(classops_api_draft_validate($payload));
    }
    if ($action === 'ai-draft-create') {
        $owner = classops_api_owner_for_post(true);
        classops_assert_known_keys($payload,['action','ownerText','forwardedText','cohortKey']);
        $forwarded = array_key_exists('forwardedText',$payload)&&$payload['forwardedText']!==null
            ? (string)$payload['forwardedText'] : null;
        dent_json_response(classops_stage2_ai_create(
            $owner,
            (string)($payload['ownerText']??''),
            $forwarded,
            trim((string)($payload['cohortKey']??''))
        ));
    }
    if ($action === 'ai-draft-edit') {
        $owner = classops_api_owner_for_post(true);
        classops_assert_known_keys($payload,['action','priorDraft','ownerEditText']);
        dent_json_response(classops_stage2_ai_edit(
            $owner,
            classops_api_object_field($payload,'priorDraft'),
            (string)($payload['ownerEditText']??'')
        ));
    }
    if ($action === 'audience-preview') {
        $owner = classops_api_owner_for_post();
        dent_json_response(classops_api_audience_preview($owner,$payload));
    }
    if ($action === 'preview' || $action === 'delivery-preview') {
        $owner = classops_api_owner_for_post();
        $copy = $payload;
        unset($copy['action']);
        $preview = classops_stage2_preview($owner,$copy);
        dent_json_response($action === 'delivery-preview'
            ? [
                'success'=>true,
                'destinations'=>$preview['destinations'],
                'audience'=>$preview['audience'],
                'confirmation'=>$preview['confirmation'],
                'mutationPerformed'=>false,
            ]
            : $preview + ['mutationPerformed'=>false]
        );
    }
    if ($action === 'reminder-preview') {
        $owner = classops_api_owner_for_post();
        dent_json_response(classops_api_reminder_preview($owner,$payload));
    }

    // The only Stage2 publication/update path. It re-resolves audience and
    // requires the exact preview hash before a canonical revision is committed.
    if ($action === 'confirm') {
        $owner = classops_api_owner_for_post();
        $copy = $payload;
        unset($copy['action']);
        dent_json_response(classops_stage2_confirm($owner,$copy));
    }
    if ($action === 'cancel' || $action === 'archive') {
        $owner = classops_api_owner_for_post();
        classops_assert_known_keys($payload,['action','id','expectedRevision','idempotencyKey','reason']);
        dent_json_response(['success'=>true] + classops_stage2_cancel_or_archive(
            $owner,
            $action,
            classops_stage2_require_item_id($payload['id']??''),
            (int)($payload['expectedRevision']??0),
            trim((string)($payload['idempotencyKey']??'')),
            trim((string)($payload['reason']??''))
        ));
    }
    if ($action === 'owner-task-transition') {
        $owner = classops_api_owner_for_post();
        $copy = $payload;
        unset($copy['action']);
        dent_json_response(['success'=>true,'state'=>classops_stage2_owner_task_transition($owner,$copy)]);
    }
    if ($action === 'student-task-transition') {
        $student = classops_api_student_for_post();
        $copy = $payload;
        unset($copy['action']);
        dent_json_response(['success'=>true,'state'=>classops_stage2_student_task_transition($student,$copy)]);
    }
    if ($action === 'student-ack') {
        $student = classops_api_student_for_post();
        $copy = $payload;
        unset($copy['action']);
        dent_json_response(['success'=>true,'ack'=>classops_stage2_student_ack($student,$copy)]);
    }
    if ($action === 'student-service-transition') {
        $student = classops_api_student_for_post();
        $copy = $payload;
        unset($copy['action']);
        dent_json_response([
            'success'=>true,
            'state'=>classops_stage2_student_service_transition($student,$copy),
            'externallyVerified'=>false,
        ]);
    }

    classops_domain_error('CLASSOPS_UNKNOWN_ACTION', 'عملیات ClassOps شناخته‌شده نیست.', 404);
} catch (DentClassOpsDomainException $exception) {
    dent_error($exception->getMessage(), $exception->httpStatus, ['code'=>$exception->reasonCode]);
} catch (DentClassOpsTaskException $exception) {
    dent_error('عملیات task/requirement انجام نشد.', $exception->httpStatus, ['code'=>$exception->reasonCode]);
} catch (DentClassOpsCriticalAckException $exception) {
    dent_error('عملیات ACK انجام نشد.', $exception->statusCode, ['code'=>$exception->reasonCode]);
} catch (DentClassOpsAudienceException $exception) {
    dent_error('مخاطبان ClassOps قابل resolve نیستند.', $exception->httpStatus, ['code'=>$exception->reasonCode]);
} catch (DentClassOpsPersistenceException $exception) {
    dent_error(
        'ذخیره‌سازی ClassOps موقتاً در دسترس نیست؛ داده موجود دست‌نخورده باقی ماند.',
        503,
        ['code'=>$exception->reasonCode]
    );
} catch (Throwable $exception) {
    classops_persistence_log('error',[
        'action'=>'api',
        'decodeStatus'=>'not-applicable',
        'commitResult'=>'unhandled-error',
        'reasonCode'=>'CLASSOPS_INTERNAL_ERROR',
    ]);
    dent_error('خطای داخلی ClassOps رخ داد.',500,['code'=>'CLASSOPS_INTERNAL_ERROR']);
}
