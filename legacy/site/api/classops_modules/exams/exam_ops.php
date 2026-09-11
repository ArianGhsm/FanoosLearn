<?php
declare(strict_types=1);

require_once __DIR__ . '/exam_common.php';

function classops_exam_normalize_extension(array $value): array
{
    classops_exam_assert_known_keys(
        $value,
        [
            'contractVersion', 'reference', 'scope', 'notes', 'reminderPolicy',
            'assessmentRef', 'resourceRefs', 'paymentAccessRef', 'lastScheduleChange',
        ],
        'examExtension'
    );
    $version = classops_exam_text(
        $value['contractVersion'] ?? CLASSOPS_EXAM_ACK_CANDIDATE_VERSION,
        64,
        'examExtension.contractVersion',
        true
    );
    if ($version !== CLASSOPS_EXAM_ACK_CANDIDATE_VERSION) {
        classops_exam_fail('CLASSOPS_EXAM_EXTENSION_VERSION_UNSUPPORTED', 'Unsupported exam extension contract version.');
    }

    $lastScheduleChange = null;
    if (($value['lastScheduleChange'] ?? null) !== null) {
        if (!is_array($value['lastScheduleChange'])) {
            classops_exam_fail('CLASSOPS_EXAM_INVALID_SUPERSESSION', 'lastScheduleChange must be an object.');
        }
        classops_exam_assert_known_keys($value['lastScheduleChange'], ['kind', 'supersedesRevision'], 'lastScheduleChange');
        $kind = classops_exam_text($value['lastScheduleChange']['kind'] ?? '', 24, 'lastScheduleChange.kind', true);
        $supersedesRevision = filter_var($value['lastScheduleChange']['supersedesRevision'] ?? null, FILTER_VALIDATE_INT);
        if ($kind !== 'rescheduled' || $supersedesRevision === false || $supersedesRevision < 1) {
            classops_exam_fail('CLASSOPS_EXAM_INVALID_SUPERSESSION', 'lastScheduleChange must identify the superseded exam revision.');
        }
        $lastScheduleChange = [
            'kind' => 'rescheduled',
            'supersedesRevision' => (int) $supersedesRevision,
        ];
    }

    return [
        'contractVersion' => CLASSOPS_EXAM_ACK_CANDIDATE_VERSION,
        'reference' => classops_exam_normalize_external_reference($value['reference'] ?? null, 'reference'),
        'scope' => classops_exam_text($value['scope'] ?? '', 3000, 'scope'),
        'notes' => classops_exam_text($value['notes'] ?? '', 6000, 'notes'),
        'reminderPolicy' => classops_exam_normalize_reminder_policy($value['reminderPolicy'] ?? null),
        'assessmentRef' => classops_exam_normalize_assessment_ref($value['assessmentRef'] ?? null),
        'resourceRefs' => classops_exam_normalize_resource_refs($value['resourceRefs'] ?? []),
        'paymentAccessRef' => classops_exam_normalize_payment_access_ref($value['paymentAccessRef'] ?? null),
        'lastScheduleChange' => $lastScheduleChange,
    ];
}

function classops_exam_assert_no_parallel_assessment_state(array $input, string $field = 'exam'): void
{
    $forbidden = [
        'attempt', 'attempts', 'attemptState', 'attemptsByUser', 'answer', 'answers', 'answersByUser',
        'timer', 'timerState', 'access', 'hasAccess', 'payment', 'payments', 'paymentStatus',
        'paid', 'verifiedPayment', 'verifiedSuccess', 'transaction', 'transactions',
    ];
    $present = array_values(array_intersect(array_keys($input), $forbidden));
    if ($present !== []) {
        classops_exam_fail(
            'CLASSOPS_EXAM_PARALLEL_ASSESSMENT_STATE_FORBIDDEN',
            $field . ' cannot contain assessment/payment state: ' . implode(', ', $present)
        );
    }
}

function classops_exam_normalize_course($value): ?array
{
    if ($value === null || $value === []) {
        return null;
    }
    if (!is_array($value)) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_COURSE', 'course must be an object.');
    }
    classops_exam_assert_known_keys($value, ['ref', 'title'], 'course');
    $ref = ($value['ref'] ?? '') === '' ? '' : classops_exam_foundation_ref($value['ref'], 'course.ref');
    $title = classops_exam_text($value['title'] ?? '', 180, 'course.title');
    if ($ref === '' && $title === '') {
        return null;
    }
    return ['ref' => $ref, 'title' => $title];
}

function classops_exam_normalize_audience_spec($value): array
{
    if (!is_array($value)) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_AUDIENCE', 'audienceSpec must be an object.');
    }
    classops_exam_assert_known_keys($value, ['version', 'mode', 'refs'], 'audienceSpec');
    $version = classops_exam_text($value['version'] ?? 'classops-audience-placeholder-v1', 64, 'audienceSpec.version', true);
    if ($version !== 'classops-audience-placeholder-v1') {
        classops_exam_fail('CLASSOPS_EXAM_AUDIENCE_VERSION_UNSUPPORTED', 'Unsupported frozen audience placeholder version.');
    }
    $mode = classops_exam_text($value['mode'] ?? '', 40, 'audienceSpec.mode', true);
    $allowedModes = ['entire_cohort', 'single_student', 'explicit_students', 'academic_group', 'snapshot', 'dynamic'];
    if (!in_array($mode, $allowedModes, true)) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_AUDIENCE', 'Unsupported audience mode.');
    }
    $refs = $value['refs'] ?? [];
    if (!is_array($refs) || !array_is_list($refs) || count($refs) > 500) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_AUDIENCE', 'audienceSpec.refs must be a list with at most 500 refs.');
    }
    $normalizedRefs = [];
    foreach ($refs as $index => $ref) {
        $normalizedRefs[] = classops_exam_foundation_ref($ref, "audienceSpec.refs[{$index}]");
    }
    $normalizedRefs = array_values(array_unique($normalizedRefs));
    $expected = match ($mode) {
        'entire_cohort' => [0, 0],
        'single_student', 'academic_group', 'snapshot', 'dynamic' => [1, 1],
        'explicit_students' => [1, 500],
    };
    if (count($normalizedRefs) < $expected[0] || count($normalizedRefs) > $expected[1]) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_AUDIENCE', 'audienceSpec.refs count is incompatible with audience mode.');
    }
    if (in_array($mode, ['single_student', 'explicit_students'], true)) {
        foreach ($normalizedRefs as $ref) {
            if (preg_match('/^[0-9]{5,20}$/D', $ref) !== 1) {
                classops_exam_fail('CLASSOPS_EXAM_INVALID_AUDIENCE', 'Student audiences require canonical ASCII student numbers.');
            }
        }
    }
    return ['version' => $version, 'mode' => $mode, 'refs' => $normalizedRefs];
}

function classops_exam_build_create_input(array $input): array
{
    classops_exam_assert_no_parallel_assessment_state($input, 'examCreate');
    classops_exam_assert_known_keys(
        $input,
        [
            'cohortKey', 'title', 'description', 'course', 'startsAt', 'endsAt', 'location',
            'importance', 'audienceSpec', 'reference', 'scope', 'notes', 'reminderPolicy',
            'assessmentRef', 'resourceRefs', 'paymentAccessRef', 'status',
        ],
        'examCreate'
    );

    $cohortKey = classops_exam_cohort_key($input['cohortKey'] ?? null);
    $title = classops_exam_text($input['title'] ?? '', 160, 'title', true);
    $startsAt = classops_exam_utc_timestamp($input['startsAt'] ?? null, 'startsAt', true);
    $endsAt = classops_exam_utc_timestamp($input['endsAt'] ?? null, 'endsAt');
    if ($endsAt !== null && strcmp($endsAt, $startsAt) < 0) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_TIME_RANGE', 'endsAt cannot be before startsAt.');
    }
    $importance = classops_exam_text($input['importance'] ?? 'normal', 24, 'importance', true);
    if (!in_array($importance, ['normal', 'important', 'critical'], true)) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_IMPORTANCE', 'importance must be normal, important, or critical.');
    }
    $status = classops_exam_text($input['status'] ?? 'draft', 24, 'status', true);
    if (!in_array($status, ['draft', 'scheduled'], true)) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_INITIAL_STATUS', 'Exam creation supports only draft or scheduled initial status.');
    }

    $extension = classops_exam_normalize_extension([
        'contractVersion' => CLASSOPS_EXAM_ACK_CANDIDATE_VERSION,
        'reference' => $input['reference'] ?? null,
        'scope' => $input['scope'] ?? '',
        'notes' => $input['notes'] ?? '',
        'reminderPolicy' => $input['reminderPolicy'] ?? null,
        'assessmentRef' => $input['assessmentRef'] ?? null,
        'resourceRefs' => $input['resourceRefs'] ?? [],
        'paymentAccessRef' => $input['paymentAccessRef'] ?? null,
        'lastScheduleChange' => null,
    ]);

    return [
        'cohortKey' => $cohortKey,
        'type' => 'exam',
        'title' => $title,
        'description' => classops_exam_text($input['description'] ?? '', 4000, 'description'),
        'course' => classops_exam_normalize_course($input['course'] ?? null),
        'timing' => [
            'startsAt' => $startsAt,
            'endsAt' => $endsAt,
            'dueAt' => null,
            // Frozen classops-v1 requires this marker while timestamps themselves remain canonical UTC.
            'timezone' => 'Asia/Tehran',
        ],
        'location' => classops_exam_text($input['location'] ?? '', 300, 'location'),
        'importance' => $importance,
        'requireAck' => false,
        'audienceSpec' => classops_exam_normalize_audience_spec($input['audienceSpec'] ?? [
            'version' => 'classops-audience-placeholder-v1',
            'mode' => 'entire_cohort',
            'refs' => [],
        ]),
        // Scheduler integration consumes exam_ops_v1.reminderPolicy after candidate promotion.
        'reminderPolicy' => [
            'version' => 'classops-reminder-placeholder-v1',
            'rules' => [],
        ],
        'status' => $status,
        'extensions' => [
            CLASSOPS_EXAM_EXTENSION_KEY => $extension,
        ],
    ];
}

function classops_exam_assert_item(array $item, ?int $expectedRevision = null): void
{
    if (($item['type'] ?? null) !== 'exam') {
        classops_exam_fail('CLASSOPS_EXAM_TYPE_REQUIRED', 'Operation requires a ClassOps exam item.');
    }
    $id = $item['id'] ?? null;
    if (!is_string($id) || preg_match('/^cop_[a-f0-9]{16,64}$/D', $id) !== 1) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_ITEM_ID', 'Exam item id is invalid.');
    }
    $revision = filter_var($item['revision'] ?? null, FILTER_VALIDATE_INT);
    if ($revision === false || $revision < 1) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_REVISION', 'Exam revision is invalid.');
    }
    if ($expectedRevision !== null && $revision !== $expectedRevision) {
        classops_exam_fail('CLASSOPS_EXAM_STALE_REVISION', 'Exam revision is stale.', 409);
    }
    $extensions = $item['extensions'] ?? [];
    if (!is_array($extensions) || !is_array($extensions[CLASSOPS_EXAM_EXTENSION_KEY] ?? null)) {
        classops_exam_fail('CLASSOPS_EXAM_EXTENSION_REQUIRED', 'Exam operations extension is missing.');
    }
    classops_exam_normalize_extension($extensions[CLASSOPS_EXAM_EXTENSION_KEY]);
}

function classops_exam_build_reschedule_command(array $currentItem, int $expectedRevision, array $change): array
{
    classops_exam_assert_item($currentItem, $expectedRevision);
    classops_exam_assert_known_keys($change, ['startsAt', 'endsAt', 'reason'], 'reschedule');
    $startsAt = classops_exam_utc_timestamp($change['startsAt'] ?? null, 'reschedule.startsAt', true);
    $endsAt = classops_exam_utc_timestamp($change['endsAt'] ?? null, 'reschedule.endsAt');
    if ($endsAt !== null && strcmp($endsAt, $startsAt) < 0) {
        classops_exam_fail('CLASSOPS_EXAM_INVALID_TIME_RANGE', 'Rescheduled endsAt cannot be before startsAt.');
    }
    $reason = classops_exam_text($change['reason'] ?? '', 500, 'reschedule.reason', true);

    $extensions = $currentItem['extensions'];
    $examExtension = classops_exam_normalize_extension($extensions[CLASSOPS_EXAM_EXTENSION_KEY]);
    $examExtension['lastScheduleChange'] = [
        'kind' => 'rescheduled',
        'supersedesRevision' => $expectedRevision,
    ];
    $extensions[CLASSOPS_EXAM_EXTENSION_KEY] = classops_exam_normalize_extension($examExtension);

    return [
        'operation' => 'update',
        'itemId' => $currentItem['id'],
        'expectedRevision' => $expectedRevision,
        'reason' => $reason,
        'patch' => [
            'timing' => [
                'startsAt' => $startsAt,
                'endsAt' => $endsAt,
                'dueAt' => null,
                'timezone' => 'Asia/Tehran',
            ],
            // Preserve every foreign namespace: Foundation updates replace the extension map.
            'extensions' => $extensions,
        ],
    ];
}

function classops_exam_build_cancel_command(array $currentItem, int $expectedRevision, string $reason): array
{
    classops_exam_assert_item($currentItem, $expectedRevision);
    $reason = classops_exam_text($reason, 500, 'cancel.reason', true);
    if (($currentItem['status'] ?? '') === 'cancelled') {
        classops_exam_fail('CLASSOPS_EXAM_ALREADY_CANCELLED', 'Exam is already cancelled.', 409);
    }
    return [
        'operation' => 'cancel',
        'itemId' => $currentItem['id'],
        'expectedRevision' => $expectedRevision,
        'reason' => $reason,
    ];
}

function classops_exam_projection(array $item): array
{
    classops_exam_assert_item($item);
    $extension = classops_exam_normalize_extension($item['extensions'][CLASSOPS_EXAM_EXTENSION_KEY]);
    return [
        'contractVersion' => CLASSOPS_EXAM_PROJECTION_VERSION,
        'itemId' => $item['id'],
        'revision' => (int) $item['revision'],
        'status' => (string) ($item['status'] ?? ''),
        'title' => (string) ($item['title'] ?? ''),
        'description' => (string) ($item['description'] ?? ''),
        'course' => $item['course'] ?? null,
        'timing' => $item['timing'] ?? [],
        'location' => (string) ($item['location'] ?? ''),
        'importance' => (string) ($item['importance'] ?? 'normal'),
        'audienceSpec' => $item['audienceSpec'] ?? [],
        'scope' => $extension['scope'],
        'notes' => $extension['notes'],
        'reference' => $extension['reference'],
        'reminderPolicy' => $extension['reminderPolicy'],
        'assessmentRef' => $extension['assessmentRef'],
        'resourceRefs' => $extension['resourceRefs'],
        'paymentAccessRef' => $extension['paymentAccessRef'],
        'lastScheduleChange' => $extension['lastScheduleChange'],
    ];
}
