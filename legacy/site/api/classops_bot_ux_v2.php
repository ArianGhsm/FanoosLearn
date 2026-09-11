<?php
declare(strict_types=1);

require_once __DIR__ . '/classops_bot_service.php';

function classops_bot_ux_v2_action(string $action): bool
{
    return $action === 'classopsAckStatusV2';
}

function classops_bot_ux_v2_dispatch(array $request): array
{
    $action = trim((string) ($request['action'] ?? ''));
    if ($action !== 'classopsAckStatusV2') {
        classops_domain_error('CLASSOPS_BOT_UX_V2_ACTION_UNKNOWN', 'ClassOps bot UX v2 action is not recognized.', 404);
    }

    $owner = classops_bot_service_owner($request);
    $listed = classops_list_items([
        'cohortKey' => dent_user_cohort_key($owner),
        'type' => 'critical_notice',
        'status' => '',
        'limit' => CLASSOPS_BOT_SERVICE_MAX_LIST,
        'cursor' => '',
    ]);

    $eligible = 0;
    $acked = 0;
    $pending = 0;
    $notices = [];
    foreach (($listed['items'] ?? []) as $item) {
        if (!is_array($item) || empty($item['requireAck']) || ($item['status'] ?? '') === 'archived') {
            continue;
        }
        $stats = classops_stage2_owner_ack_stats($owner, $item);
        $eligible += (int) ($stats['eligible'] ?? 0);
        $acked += (int) ($stats['acked'] ?? 0);
        $pending += (int) ($stats['pending'] ?? 0);
        $notices[] = [
            'title' => (string) ($item['title'] ?? 'اطلاعیه مهم'),
            'status' => (string) ($item['status'] ?? 'unknown'),
            'revision' => (int) ($item['revision'] ?? 0),
            'eligible' => (int) ($stats['eligible'] ?? 0),
            'acked' => (int) ($stats['acked'] ?? 0),
            'pending' => (int) ($stats['pending'] ?? 0),
        ];
    }

    return [
        'success' => true,
        'ack' => [
            'eligible' => $eligible,
            'acked' => $acked,
            'pending' => $pending,
            'noticeCount' => count($notices),
        ],
        'notices' => array_slice($notices, 0, 12),
    ];
}
