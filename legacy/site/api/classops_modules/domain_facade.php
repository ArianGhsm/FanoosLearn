<?php
declare(strict_types=1);

require_once __DIR__ . '/audience/resolver.php';
require_once __DIR__ . '/delivery/delivery_planner.php';
require_once __DIR__ . '/ai/contract.php';
require_once __DIR__ . '/tasks/task_domain.php';
require_once __DIR__ . '/exams/exam_ops.php';
require_once __DIR__ . '/ack/critical_ack.php';
require_once __DIR__ . '/scheduler/classops_reminder_planner.php';
require_once __DIR__ . '/digests/digest_engine.php';

const CLASSOPS_DOMAIN_FACADE_VERSION = 'classops-domain-facade-v1';

function classops_domain_capabilities(): array
{
    return [
        'contractVersion'=>CLASSOPS_DOMAIN_FACADE_VERSION,
        'foundation'=>'classops-v1',
        'audience'=>'classops-audience-v1',
        'delivery'=>'classops-delivery-v1',
        'aiDraft'=>'classops-ai-draft-v1',
        'tasksRequirements'=>'classops-tasks-v1',
        'examAck'=>'classops-exam-ack-v1',
        'reminder'=>'classops-reminder-v1',
        'digest'=>'classops-digest-v1',
        'directSend'=>false,
        'directAiMutation'=>false,
        'surfaceWiring'=>'stage2',
    ];
}

function classops_domain_normalize_audience_spec(array $spec): array
{
    $warnings = [];
    return [
        'spec'=>classops_audience_normalize_spec($spec, $warnings),
        'warnings'=>$warnings,
    ];
}

function classops_domain_resolve_audience(array $spec, string $cohortKey, array $context, ?array $snapshot = null, ?string $expectedHash = null): array
{
    return classops_audience_resolve($spec, $cohortKey, $context, $snapshot, $expectedHash);
}

function classops_domain_preview_audience(array $current, ?array $previous = null, bool $includeIdentifiers = false): array
{
    return classops_audience_preview($current, $previous, $includeIdentifiers);
}

function classops_domain_validate_delivery_registry(array $registry): array
{
    return classops_delivery_validate_registry($registry);
}

function classops_domain_plan_delivery(array $registry, string $requestedAlias, array $itemRef, array $audienceRef, array $occurrence, array $policy, array $availability, array $previousIntents = [], array $capabilityOverrides = []): array
{
    return classops_delivery_plan($registry, $requestedAlias, $itemRef, $audienceRef, $occurrence, $policy, $availability, $previousIntents, $capabilityOverrides);
}

function classops_domain_validate_ai_draft(array $draft): array
{
    return classops_ai_validate_final_draft($draft);
}

function classops_domain_validate_task_extension(array $extension, string $itemType): array
{
    return classops_task_normalize_extension($extension, $itemType);
}

function classops_domain_task_projection(array $state, $viewerStudentNumber, ?string $dueAtUtc, string $nowUtc): array
{
    return classops_task_student_projection($state, $viewerStudentNumber, $dueAtUtc, $nowUtc);
}

function classops_domain_exam_projection(array $item): array
{
    return classops_exam_projection($item);
}

function classops_domain_ack_satisfied(array $state, array $notice, string $studentNumber): bool
{
    return classops_ack_is_satisfied($state, $notice, $studentNumber);
}

function classops_domain_ack_owner_stats(array $state, array $notice, array $eligibleStudentNumbers): array
{
    return classops_ack_owner_stats($state, $notice, $eligibleStudentNumbers);
}

function classops_domain_plan_reminders(array $snapshot, ?callable $clock = null): array
{
    return classops_reminder_plan($snapshot, $clock);
}

function classops_domain_build_digest(array $request): array
{
    return classops_digest_build($request);
}
