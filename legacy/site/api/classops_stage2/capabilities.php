<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/auth_store.php';
require_once dirname(__DIR__) . '/classops_stage2_store.php';
require_once dirname(__DIR__) . '/classops_modules/domain_facade.php';
require_once dirname(__DIR__) . '/classops_modules/ai/copilot.php';

const CLASSOPS_STAGE2_BINDING_VERSION = 'classops-stage2-binding-v1';
const CLASSOPS_STAGE2_EXTENSION_KEY = 'classops_stage2_v1';
const CLASSOPS_STAGE2_SURFACE_VERSION = 'classops-surface-v1';
const CLASSOPS_STAGE2_MAX_DESTINATIONS = 8;

function classops_stage2_bool_env(string $name): ?bool
{
    $raw = strtolower(trim((string) getenv($name)));
    if ($raw === '') return null;
    if (in_array($raw, ['1','true','yes','on'], true)) return true;
    if (in_array($raw, ['0','false','no','off'], true)) return false;
    return null;
}

function classops_stage2_ai_configured(): bool
{
    if (trim((string) getenv('DENT_CLASSOPS_AI_AVALAI_API_KEY')) === ''
        || trim((string) getenv('DENT_CLASSOPS_AI_MODEL')) === '') {
        return false;
    }
    $provider = strtolower(trim((string) getenv('DENT_CLASSOPS_AI_PROVIDER')));
    if ($provider !== '' && $provider !== DentClassOpsAiAvalAiClient::DEFAULT_PROVIDER) return false;
    $base = trim((string) getenv('DENT_CLASSOPS_AI_BASE_URL'));
    if ($base !== '') {
        try {
            DentClassOpsAiAvalAiClient::endpointFromBase($base);
        } catch (DentClassOpsAiException $exception) {
            return false;
        }
    }
    $retry = trim((string) getenv('DENT_CLASSOPS_AI_MAX_RETRIES'));
    if ($retry !== '') {
        $value = filter_var($retry, FILTER_VALIDATE_INT);
        if ($value === false || $value < 0 || $value > 2) return false;
    }
    return true;
}

function classops_stage2_platform_state(string $platform): string
{
    $enabled = classops_stage2_bool_env($platform === 'telegram' ? 'DENT_CLASSOPS_TELEGRAM_ENABLED' : 'DENT_CLASSOPS_BALE_ENABLED');
    return $enabled === true ? 'available' : ($enabled === false ? 'unavailable' : 'unknown');
}

function classops_stage2_capabilities(): array
{
    $base = trim((string) getenv('DENT_CLASSOPS_AI_BASE_URL'));
    return [
        'success' => true,
        'surfaceVersion' => CLASSOPS_STAGE2_SURFACE_VERSION,
        'foundation' => ['state'=>'available','contract'=>'classops-v1'],
        'audience' => ['state'=>'available','contract'=>'classops-audience-v1'],
        'deliveryPlanning' => ['state'=>'available','contract'=>'classops-delivery-v1'],
        'ai' => [
            'state' => classops_stage2_ai_configured() ? 'configured' : 'unconfigured',
            'provider' => DentClassOpsAiAvalAiClient::DEFAULT_PROVIDER,
            'baseEndpointConfigured' => $base !== '',
            'manualFallback' => true,
            'directMutation' => false,
            'directSend' => false,
        ],
        'tasksRequirements' => ['state'=>'available','contract'=>'classops-tasks-v1'],
        'examAck' => ['state'=>'available','contract'=>'classops-exam-ack-v1'],
        'scheduler' => ['state'=>'available','contract'=>'classops-reminder-v1','coordinator'=>'server-canonical'],
        'digest' => ['state'=>'available','contract'=>'classops-digest-v1'],
        'telegram' => ['state'=>classops_stage2_platform_state('telegram')],
        'bale' => ['state'=>classops_stage2_platform_state('bale')],
        'website' => ['state'=>'available','notifications'=>'canonical-existing-subsystem'],
        'saba' => ['state'=>'reminder-only','credentialsAccepted'=>false,'loginAutomation'=>false],
    ];
}

function classops_stage2_require_item_id($value): string
{
    $id = trim((string) $value);
    if (preg_match('/^cop_[a-f0-9]{16,64}$/D', $id) !== 1) {
        classops_domain_error('CLASSOPS_ITEM_ID_INVALID', 'شناسه آیتم ClassOps معتبر نیست.');
    }
    return $id;
}

function classops_stage2_student_number(array $user): string
{
    $student = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($student === '') {
        classops_domain_error('CLASSOPS_CANONICAL_IDENTITY_REQUIRED', 'هویت canonical کاربر در دسترس نیست.', 403);
    }
    return $student;
}

function classops_stage2_is_owner(array $user): bool
{
    return (string) ($user['role'] ?? 'student') === 'owner';
}

function classops_stage2_owner_scope(array $owner, string $cohortKey): array
{
    if (!classops_stage2_is_owner($owner)) {
        classops_domain_error('CLASSOPS_OWNER_REQUIRED', 'این عملیات فقط برای مالک سامانه مجاز است.', 403);
    }
    // The audience domain owns the scope schema. Keep this adapter canonical
    // rather than inventing a second Stage2 scope representation.
    return ['role'=>'owner','cohortKeys'=>[$cohortKey]];
}

function classops_stage2_ai_create(array $owner, string $ownerText, ?string $forwardedText, string $cohortKey): array
{
    if (!classops_stage2_is_owner($owner)) classops_domain_error('CLASSOPS_OWNER_REQUIRED','این عملیات فقط برای مالک سامانه مجاز است.',403);
    if (!classops_stage2_ai_configured()) {
        classops_domain_error('CLASSOPS_AI_NOT_CONFIGURED', 'هوش مصنوعی ClassOps تنظیم نشده؛ ورود دستی همچنان فعال است.', 503);
    }
    if ($cohortKey === '' || !dent_cohort_exists($cohortKey)) classops_domain_error('CLASSOPS_INVALID_COHORT','ورودی canonical موردنظر وجود ندارد.');
    $copilot = new DentClassOpsAiCopilot(DentClassOpsAiAvalAiClient::fromEnvironment());
    try {
        return ['success'=>true] + $copilot->createDraft($ownerText, $forwardedText, ['cohortKey'=>$cohortKey]);
    } catch (DentClassOpsAiException $exception) {
        classops_domain_error($exception->reasonCode, 'سرویس AI ClassOps در دسترس نیست یا خروجی معتبر نبود.', $exception->httpStatus);
    }
}

function classops_stage2_ai_edit(array $owner, array $priorDraft, string $editText): array
{
    if (!classops_stage2_is_owner($owner)) classops_domain_error('CLASSOPS_OWNER_REQUIRED','این عملیات فقط برای مالک سامانه مجاز است.',403);
    if (!classops_stage2_ai_configured()) {
        classops_domain_error('CLASSOPS_AI_NOT_CONFIGURED', 'هوش مصنوعی ClassOps تنظیم نشده؛ ویرایش دستی همچنان فعال است.', 503);
    }
    $copilot = new DentClassOpsAiCopilot(DentClassOpsAiAvalAiClient::fromEnvironment());
    try {
        return ['success'=>true] + $copilot->editDraft($priorDraft, $editText);
    } catch (DentClassOpsAiException $exception) {
        classops_domain_error($exception->reasonCode, 'سرویس AI ClassOps در دسترس نیست یا خروجی معتبر نبود.', $exception->httpStatus);
    }
}
