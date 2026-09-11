<?php
declare(strict_types=1);

require_once __DIR__ . '/errors.php';

const CLASSOPS_AI_DRAFT_CONTRACT_VERSION = 'classops-structured-draft-v1';
const CLASSOPS_AI_PARSER_VERSION = 'classops-ai-parser-v1';
const CLASSOPS_AI_PROMPT_VERSION = 'classops-ai-prompt-v1';
const CLASSOPS_AI_MAX_OWNER_TEXT_BYTES = 24000;
const CLASSOPS_AI_MAX_FORWARDED_TEXT_BYTES = 48000;
const CLASSOPS_AI_MAX_EDIT_TEXT_BYTES = 12000;
const CLASSOPS_AI_MAX_UNRESOLVED = 32;

function classops_ai_field_names(): array
{
    return [
        'type', 'title', 'description', 'course', 'timing', 'location',
        'importance', 'requireAck', 'audience', 'delivery', 'reminderHint',
    ];
}

function classops_ai_allowed_types(): array
{
    return [
        'announcement', 'event', 'class_change', 'deadline', 'task',
        'requirement', 'exam', 'critical_notice', 'service_reminder',
    ];
}

function classops_ai_allowed_importance(): array
{
    return ['normal', 'important', 'critical'];
}

function classops_ai_allowed_destinations(): array
{
    return ['private_users', 'class_group', 'information_channel'];
}

function classops_ai_is_assoc(array $value): bool
{
    return !array_is_list($value);
}

function classops_ai_assert_object($value, string $field): array
{
    if (!is_array($value) || !classops_ai_is_assoc($value)) {
        classops_ai_error('CLASSOPS_AI_INVALID_OBJECT', "{$field} must be an object");
    }
    return $value;
}

function classops_ai_assert_known_keys(array $value, array $allowed, string $field): void
{
    $unknown = array_values(array_diff(array_keys($value), $allowed));
    if ($unknown !== []) {
        classops_ai_error('CLASSOPS_AI_EXTRA_FIELD', "Unexpected field in {$field}");
    }
}

function classops_ai_text($value, int $maxLength, string $field, bool $nullable = true): ?string
{
    if ($value === null && $nullable) {
        return null;
    }
    if (!is_string($value)) {
        classops_ai_error('CLASSOPS_AI_INVALID_TEXT', "{$field} must be text or null");
    }
    $text = trim(str_replace(["\r\n", "\r"], "\n", $value));
    if ($text === '' && $nullable) {
        return null;
    }
    if (function_exists('mb_strlen')) {
        $length = mb_strlen($text, 'UTF-8');
    } else {
        $length = strlen($text);
    }
    if ($length > $maxLength) {
        classops_ai_error('CLASSOPS_AI_FIELD_TOO_LONG', "{$field} is too long");
    }
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $text) === 1) {
        classops_ai_error('CLASSOPS_AI_INVALID_TEXT', "{$field} contains control characters");
    }
    return $text;
}

function classops_ai_validate_destinations($value, string $field): array
{
    if (!is_array($value) || !array_is_list($value) || count($value) > 3) {
        classops_ai_error('CLASSOPS_AI_INVALID_DESTINATIONS', "{$field} must be a bounded list");
    }
    $result = [];
    foreach ($value as $entry) {
        if (!is_string($entry) || !in_array($entry, classops_ai_allowed_destinations(), true)) {
            classops_ai_error('CLASSOPS_AI_INVALID_DESTINATION', "{$field} has an invalid symbolic destination");
        }
        $result[$entry] = true;
    }
    return array_keys($result);
}

function classops_ai_validate_course($value): ?array
{
    if ($value === null) {
        return null;
    }
    $value = classops_ai_assert_object($value, 'fields.course');
    classops_ai_assert_known_keys($value, ['ref', 'title', 'rawText'], 'fields.course');
    if (($value['ref'] ?? null) !== null) {
        classops_ai_error('CLASSOPS_AI_RESOLUTION_FORBIDDEN', 'AI may not resolve course.ref');
    }
    $title = classops_ai_text($value['title'] ?? null, 180, 'fields.course.title');
    $rawText = classops_ai_text($value['rawText'] ?? null, 300, 'fields.course.rawText');
    if ($title === null && $rawText === null) {
        return null;
    }
    return ['ref' => null, 'title' => $title, 'rawText' => $rawText];
}

function classops_ai_validate_timing($value): ?array
{
    if ($value === null) {
        return null;
    }
    $value = classops_ai_assert_object($value, 'fields.timing');
    classops_ai_assert_known_keys($value, ['startsAt', 'endsAt', 'dueAt', 'timezone', 'rawText'], 'fields.timing');
    foreach (['startsAt', 'endsAt', 'dueAt', 'timezone'] as $forbiddenResolvedField) {
        if (($value[$forbiddenResolvedField] ?? null) !== null) {
            classops_ai_error('CLASSOPS_AI_RESOLUTION_FORBIDDEN', "AI may not resolve timing.{$forbiddenResolvedField}");
        }
    }
    $rawText = classops_ai_text($value['rawText'] ?? null, 500, 'fields.timing.rawText');
    if ($rawText === null) {
        return null;
    }
    return [
        'startsAt' => null,
        'endsAt' => null,
        'dueAt' => null,
        'timezone' => null,
        'rawText' => $rawText,
    ];
}

function classops_ai_validate_audience($value): ?array
{
    if ($value === null) {
        return null;
    }
    $value = classops_ai_assert_object($value, 'fields.audience');
    classops_ai_assert_known_keys($value, ['mode', 'refs', 'rawText'], 'fields.audience');
    if (($value['mode'] ?? null) !== null) {
        classops_ai_error('CLASSOPS_AI_RESOLUTION_FORBIDDEN', 'AI may not resolve audience.mode');
    }
    $refs = $value['refs'] ?? [];
    if (!is_array($refs) || $refs !== []) {
        classops_ai_error('CLASSOPS_AI_IDENTITY_FORBIDDEN', 'AI may not emit audience refs or student identities');
    }
    $rawText = classops_ai_text($value['rawText'] ?? null, 500, 'fields.audience.rawText');
    if ($rawText === null) {
        return null;
    }
    return ['mode' => null, 'refs' => [], 'rawText' => $rawText];
}

function classops_ai_validate_delivery($value): ?array
{
    if ($value === null) {
        return null;
    }
    $value = classops_ai_assert_object($value, 'fields.delivery');
    classops_ai_assert_known_keys($value, ['initialDestinations', 'reminderDestinations'], 'fields.delivery');
    $initial = classops_ai_validate_destinations($value['initialDestinations'] ?? [], 'fields.delivery.initialDestinations');
    $reminder = classops_ai_validate_destinations($value['reminderDestinations'] ?? [], 'fields.delivery.reminderDestinations');
    if ($initial === [] && $reminder === []) {
        return null;
    }
    return ['initialDestinations' => $initial, 'reminderDestinations' => $reminder];
}

function classops_ai_validate_fields($value): array
{
    $value = classops_ai_assert_object($value, 'fields');
    classops_ai_assert_known_keys($value, classops_ai_field_names(), 'fields');
    foreach (classops_ai_field_names() as $field) {
        if (!array_key_exists($field, $value)) {
            classops_ai_error('CLASSOPS_AI_MISSING_FIELD', "Model candidate is missing {$field}");
        }
    }

    $type = $value['type'];
    if ($type !== null && (!is_string($type) || !in_array($type, classops_ai_allowed_types(), true))) {
        classops_ai_error('CLASSOPS_AI_INVALID_ENUM', 'fields.type is invalid');
    }
    $importance = $value['importance'];
    if ($importance !== null && (!is_string($importance) || !in_array($importance, classops_ai_allowed_importance(), true))) {
        classops_ai_error('CLASSOPS_AI_INVALID_ENUM', 'fields.importance is invalid');
    }
    $requireAck = $value['requireAck'];
    if ($requireAck !== null && !is_bool($requireAck)) {
        classops_ai_error('CLASSOPS_AI_INVALID_BOOLEAN', 'fields.requireAck must be boolean or null');
    }

    return [
        'type' => $type,
        'title' => classops_ai_text($value['title'], 160, 'fields.title'),
        'description' => classops_ai_text($value['description'], 4000, 'fields.description'),
        'course' => classops_ai_validate_course($value['course']),
        'timing' => classops_ai_validate_timing($value['timing']),
        'location' => classops_ai_text($value['location'], 300, 'fields.location'),
        'importance' => $importance,
        'requireAck' => $requireAck,
        'audience' => classops_ai_validate_audience($value['audience']),
        'delivery' => classops_ai_validate_delivery($value['delivery']),
        'reminderHint' => classops_ai_text($value['reminderHint'], 500, 'fields.reminderHint'),
    ];
}

function classops_ai_validate_field_path_list($value, string $field, array $allowed): array
{
    if (!is_array($value) || !array_is_list($value) || count($value) > CLASSOPS_AI_MAX_UNRESOLVED) {
        classops_ai_error('CLASSOPS_AI_INVALID_FIELD_LIST', "{$field} must be a bounded list");
    }
    $result = [];
    foreach ($value as $entry) {
        if (!is_string($entry) || !in_array($entry, $allowed, true)) {
            classops_ai_error('CLASSOPS_AI_INVALID_FIELD_PATH', "{$field} contains an invalid field path");
        }
        $result[$entry] = true;
    }
    return array_keys($result);
}

function classops_ai_unresolved_paths(): array
{
    return [
        'type', 'title', 'description', 'course', 'course.ref', 'timing', 'timing.startsAt',
        'timing.endsAt', 'timing.dueAt', 'location', 'importance', 'requireAck', 'audience',
        'audience.mode', 'audience.refs', 'delivery', 'reminderHint',
    ];
}

function classops_ai_validate_model_candidate(array $candidate, ?array $priorDraft = null): array
{
    classops_ai_assert_known_keys($candidate, ['fields', 'changedFields', 'unresolved'], 'modelCandidate');
    foreach (['fields', 'changedFields', 'unresolved'] as $required) {
        if (!array_key_exists($required, $candidate)) {
            classops_ai_error('CLASSOPS_AI_MISSING_FIELD', "Model candidate is missing {$required}");
        }
    }
    $fields = classops_ai_validate_fields($candidate['fields']);
    $changedFields = classops_ai_validate_field_path_list($candidate['changedFields'], 'changedFields', classops_ai_field_names());
    $unresolved = classops_ai_validate_field_path_list($candidate['unresolved'], 'unresolved', classops_ai_unresolved_paths());

    if ($priorDraft !== null) {
        $priorFields = classops_ai_validate_fields($priorDraft['fields'] ?? null);
        foreach (classops_ai_field_names() as $field) {
            if (!in_array($field, $changedFields, true) && $fields[$field] !== $priorFields[$field]) {
                classops_ai_error('CLASSOPS_AI_EDIT_PRESERVATION_FAILED', "Edit changed {$field} without declaring it");
            }
        }
    }

    return ['fields' => $fields, 'changedFields' => $changedFields, 'unresolved' => $unresolved];
}

function classops_ai_validate_context($value): array
{
    if ($value === null || $value === []) {
        return ['cohortKey' => null];
    }
    $value = classops_ai_assert_object($value, 'trustedContext');
    classops_ai_assert_known_keys($value, ['cohortKey'], 'trustedContext');
    $cohort = classops_ai_text($value['cohortKey'] ?? null, 80, 'trustedContext.cohortKey');
    if ($cohort !== null && preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/', $cohort) !== 1) {
        classops_ai_error('CLASSOPS_AI_INVALID_COHORT', 'trustedContext.cohortKey must be canonical');
    }
    return ['cohortKey' => $cohort];
}

function classops_ai_validate_final_draft(array $draft): array
{
    $allowed = ['contractVersion', 'draftVersion', 'operation', 'context', 'fields', 'changedFields', 'unresolved', 'provenance', 'preview'];
    classops_ai_assert_known_keys($draft, $allowed, 'draft');
    foreach ($allowed as $required) {
        if (!array_key_exists($required, $draft)) {
            classops_ai_error('CLASSOPS_AI_MISSING_FIELD', "Draft is missing {$required}");
        }
    }
    if ($draft['contractVersion'] !== CLASSOPS_AI_DRAFT_CONTRACT_VERSION) {
        classops_ai_error('CLASSOPS_AI_CONTRACT_VERSION', 'Unsupported structured draft version');
    }
    if (!is_int($draft['draftVersion']) || $draft['draftVersion'] < 1) {
        classops_ai_error('CLASSOPS_AI_DRAFT_VERSION', 'draftVersion must be positive');
    }
    if (!in_array($draft['operation'], ['create', 'edit'], true)) {
        classops_ai_error('CLASSOPS_AI_OPERATION', 'operation is invalid');
    }
    $context = classops_ai_validate_context($draft['context']);
    $candidate = classops_ai_validate_model_candidate([
        'fields' => $draft['fields'],
        'changedFields' => $draft['changedFields'],
        'unresolved' => $draft['unresolved'],
    ]);

    $provenance = classops_ai_assert_object($draft['provenance'], 'provenance');
    classops_ai_assert_known_keys($provenance, ['provider', 'model', 'parserVersion', 'promptVersion', 'generatedAt', 'parentDraftVersion', 'fieldOrigins'], 'provenance');
    if (($provenance['provider'] ?? null) !== 'avalai') {
        classops_ai_error('CLASSOPS_AI_PROVENANCE', 'provider provenance must be avalai');
    }
    $model = classops_ai_text($provenance['model'] ?? null, 120, 'provenance.model', false);
    if (($provenance['parserVersion'] ?? null) !== CLASSOPS_AI_PARSER_VERSION || ($provenance['promptVersion'] ?? null) !== CLASSOPS_AI_PROMPT_VERSION) {
        classops_ai_error('CLASSOPS_AI_PROVENANCE', 'parser/prompt provenance is invalid');
    }
    $generatedAt = $provenance['generatedAt'] ?? null;
    if (!is_string($generatedAt) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $generatedAt) !== 1) {
        classops_ai_error('CLASSOPS_AI_PROVENANCE', 'generatedAt must be UTC ISO-8601');
    }
    $parent = $provenance['parentDraftVersion'] ?? null;
    if ($parent !== null && (!is_int($parent) || $parent < 1 || $parent >= $draft['draftVersion'])) {
        classops_ai_error('CLASSOPS_AI_PROVENANCE', 'parentDraftVersion is invalid');
    }
    $origins = classops_ai_assert_object($provenance['fieldOrigins'] ?? null, 'provenance.fieldOrigins');
    classops_ai_assert_known_keys($origins, classops_ai_field_names(), 'provenance.fieldOrigins');
    foreach (classops_ai_field_names() as $field) {
        if (!array_key_exists($field, $origins) || !is_int($origins[$field]) || $origins[$field] < 1 || $origins[$field] > $draft['draftVersion']) {
            classops_ai_error('CLASSOPS_AI_PROVENANCE', 'field origin version is invalid');
        }
    }

    $preview = classops_ai_assert_object($draft['preview'], 'preview');
    classops_ai_assert_known_keys($preview, ['required', 'confirmed', 'mutationAuthority', 'directSend'], 'preview');
    if (($preview['required'] ?? null) !== true || ($preview['confirmed'] ?? null) !== false || ($preview['mutationAuthority'] ?? null) !== 'none' || ($preview['directSend'] ?? null) !== false) {
        classops_ai_error('CLASSOPS_AI_PREVIEW_REQUIRED', 'AI draft must remain preview-only and unconfirmed');
    }

    return [
        'contractVersion' => CLASSOPS_AI_DRAFT_CONTRACT_VERSION,
        'draftVersion' => $draft['draftVersion'],
        'operation' => $draft['operation'],
        'context' => $context,
        'fields' => $candidate['fields'],
        'changedFields' => $candidate['changedFields'],
        'unresolved' => $candidate['unresolved'],
        'provenance' => [
            'provider' => 'avalai',
            'model' => $model,
            'parserVersion' => CLASSOPS_AI_PARSER_VERSION,
            'promptVersion' => CLASSOPS_AI_PROMPT_VERSION,
            'generatedAt' => $generatedAt,
            'parentDraftVersion' => $parent,
            'fieldOrigins' => $origins,
        ],
        'preview' => ['required' => true, 'confirmed' => false, 'mutationAuthority' => 'none', 'directSend' => false],
    ];
}
