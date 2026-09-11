<?php
declare(strict_types=1);

require_once __DIR__ . '/navid_service.php';

function dent_bot_navid_require_owner(array $user): void
{
    $role = dent_normalize_role((string) ($user['role'] ?? 'student'), (string) ($user['studentNumber'] ?? ''));
    if ($role !== 'owner') {
        dent_error('این عملیات فقط برای مالک مجاز است.', 403, ['code' => 'OWNER_REQUIRED']);
    }
}

function dent_bot_navid_daily_start(array $user, string $platform, array $payload): array
{
    dent_bot_navid_require_owner($user);
    if ($platform !== 'telegram') {
        dent_error('حل کپچای روزانه نوید فقط از ربات تلگرام مالک انجام می‌شود.', 409, [
            'code' => 'NAVID_CAPTCHA_TELEGRAM_ONLY',
        ]);
    }
    return navid_daily_start(
        (string) ($payload['date'] ?? ''),
        filter_var($payload['refresh'] ?? false, FILTER_VALIDATE_BOOLEAN)
    );
}

function dent_bot_navid_status(array $user): array
{
    dent_bot_navid_require_owner($user);
    $store = navid_load_store();
    $status = navid_build_owner_status($store);
    $snapshot = is_array($store['snapshot'] ?? null) ? $store['snapshot'] : [];
    $assignments = is_array($snapshot['assignments'] ?? null) ? array_values($snapshot['assignments']) : [];
    $assignments = navid_assignments_sorted(array_values(array_filter($assignments, 'is_array')));
    $recent = [];
    foreach (array_slice($assignments, 0, 8) as $assignment) {
        $recent[] = [
            'key' => dent_clean_text((string) ($assignment['assignmentKey'] ?? ''), 80),
            'title' => dent_clean_text((string) ($assignment['title'] ?? ''), 180),
            'courseTitle' => dent_clean_text((string) ($assignment['courseTitle'] ?? ''), 160),
            'description' => dent_clean_text((string) ($assignment['descriptionText'] ?? ''), 360),
            'endDateIso' => dent_clean_text((string) ($assignment['endDateIso'] ?? ''), 80),
            'endDateShamsi' => dent_clean_text((string) ($assignment['endDateShamsi'] ?? ''), 40),
            'replyStatusName' => dent_clean_text((string) ($assignment['replyStatusName'] ?? ''), 80),
            'isActive' => !empty($assignment['isActive']),
        ];
    }
    $automation = is_array($store['automation'] ?? null) ? $store['automation'] : [];
    return [
        'success' => true,
        'status' => $status,
        'automation' => [
            'dailyDate' => dent_clean_text((string) ($automation['dailyDate'] ?? ''), 20),
            'challengeIssuedAt' => dent_clean_text((string) ($automation['challengeIssuedAt'] ?? ''), 80),
            'challengeExpiresAt' => dent_clean_text((string) ($automation['challengeExpiresAt'] ?? ''), 80),
            'completedAt' => dent_clean_text((string) ($automation['completedAt'] ?? ''), 80),
            'lastResult' => dent_clean_text((string) ($automation['lastResult'] ?? ''), 80),
        ],
        'assignments' => $recent,
    ];
}

function dent_bot_navid_daily_complete(array $user, string $platform, array $payload): array
{
    dent_bot_navid_require_owner($user);
    if ($platform !== 'telegram') {
        dent_error('حل کپچای روزانه نوید فقط از ربات تلگرام مالک انجام می‌شود.', 409, [
            'code' => 'NAVID_CAPTCHA_TELEGRAM_ONLY',
        ]);
    }
    return navid_daily_complete(
        (string) ($payload['date'] ?? ''),
        (string) ($payload['captchaCode'] ?? '')
    );
}
