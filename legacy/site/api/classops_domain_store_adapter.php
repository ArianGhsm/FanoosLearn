<?php
declare(strict_types=1);

require_once __DIR__ . '/classops_store.php';
require_once __DIR__ . '/classops_modules/tasks/task_domain.php';
require_once __DIR__ . '/classops_modules/exams/exam_ops.php';

/**
 * Stage 1 compatibility bridge between frozen classops-v1 persistence and
 * promoted domain extensions. The Foundation item contract and its historical
 * audience/delivery/reminder placeholders remain unchanged. Only known domain
 * extension namespaces are interpreted here; unknown historical namespaces
 * remain opaque and readable.
 */
function classops_domain_store_normalize_extensions(array $extensions, string $itemType): array
{
    $extensions = classops_normalize_extension_map($extensions, 'extensions');

    if (array_key_exists(CLASSOPS_TASKS_EXTENSION_KEY, $extensions)) {
        if (!in_array($itemType, ['task', 'requirement'], true)) {
            classops_domain_error(
                'CLASSOPS_TASK_TYPE_REQUIRED',
                'Task/requirement extension is only valid on task or requirement items.'
            );
        }
        if (!is_array($extensions[CLASSOPS_TASKS_EXTENSION_KEY]) || array_is_list($extensions[CLASSOPS_TASKS_EXTENSION_KEY])) {
            classops_domain_error('CLASSOPS_TASK_INVALID_OBJECT', 'Task/requirement extension must be an object.');
        }
        try {
            $extensions[CLASSOPS_TASKS_EXTENSION_KEY] = classops_task_normalize_extension(
                $extensions[CLASSOPS_TASKS_EXTENSION_KEY],
                $itemType
            );
        } catch (DentClassOpsTaskException $exception) {
            classops_domain_error($exception->reasonCode, $exception->getMessage(), $exception->httpStatus);
        }
    }

    if (array_key_exists(CLASSOPS_EXAM_EXTENSION_KEY, $extensions)) {
        if ($itemType !== 'exam') {
            classops_domain_error(
                'CLASSOPS_EXAM_TYPE_REQUIRED',
                'Exam operations extension is only valid on exam items.'
            );
        }
        if (!is_array($extensions[CLASSOPS_EXAM_EXTENSION_KEY]) || array_is_list($extensions[CLASSOPS_EXAM_EXTENSION_KEY])) {
            classops_domain_error('CLASSOPS_EXAM_INVALID_OBJECT', 'Exam operations extension must be an object.');
        }
        try {
            $extensions[CLASSOPS_EXAM_EXTENSION_KEY] = classops_exam_normalize_extension(
                $extensions[CLASSOPS_EXAM_EXTENSION_KEY]
            );
        } catch (DentClassOpsExamOpsException $exception) {
            classops_domain_error($exception->reasonCode, $exception->getMessage(), $exception->statusCode);
        }
    }

    return $extensions;
}

function classops_domain_store_normalize_create(array $input): array
{
    $normalized = classops_default_item_fields($input);
    $normalized['extensions'] = classops_domain_store_normalize_extensions(
        $normalized['extensions'],
        (string) $normalized['type']
    );
    return $normalized;
}

function classops_domain_store_normalize_patch(array $currentItem, array $patch): array
{
    $normalizedPatch = classops_normalize_item_fields($patch, true);
    $nextType = (string) ($normalizedPatch['type'] ?? $currentItem['type'] ?? '');
    $nextExtensions = array_key_exists('extensions', $normalizedPatch)
        ? $normalizedPatch['extensions']
        : ($currentItem['extensions'] ?? []);

    // Validate the complete prospective known-extension set even when a patch
    // changes only the item type. This prevents a type change from stranding a
    // known extension under an incompatible item kind.
    $canonicalExtensions = classops_domain_store_normalize_extensions($nextExtensions, $nextType);
    if (array_key_exists('extensions', $normalizedPatch)) {
        $normalizedPatch['extensions'] = $canonicalExtensions;
    }

    return $normalizedPatch;
}

function classops_domain_store_create_item(
    array $input,
    array $viewer,
    string $idempotencyKey,
    string $reason = ''
): array {
    return classops_create_item(
        classops_domain_store_normalize_create($input),
        $viewer,
        $idempotencyKey,
        $reason
    );
}

function classops_domain_store_update_item(
    string $id,
    int $expectedRevision,
    array $patch,
    array $viewer,
    string $idempotencyKey,
    string $reason
): array {
    $current = classops_get_item($id);
    $normalizedPatch = classops_domain_store_normalize_patch($current, $patch);
    return classops_update_item(
        $id,
        $expectedRevision,
        $normalizedPatch,
        $viewer,
        $idempotencyKey,
        $reason
    );
}

function classops_domain_store_validate_known_extensions(array $item): array
{
    $type = (string) ($item['type'] ?? '');
    $extensions = $item['extensions'] ?? [];
    if (!is_array($extensions) || ($extensions !== [] && array_is_list($extensions))) {
        classops_domain_error('CLASSOPS_INVALID_EXTENSION_MAP', 'Item extensions must be an object.');
    }
    return classops_domain_store_normalize_extensions($extensions, $type);
}
