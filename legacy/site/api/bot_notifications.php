<?php
declare(strict_types=1);

require_once __DIR__ . '/notifications_store.php';
require_once __DIR__ . '/academic_term7.php';

function dent_bot_notifications_require_owner(array $user): void
{
    $role = dent_normalize_role((string) ($user['role'] ?? 'student'), (string) ($user['studentNumber'] ?? ''));
    if ($role !== 'owner') {
        dent_error('این عملیات فقط برای مالک مجاز است.', 403, ['code' => 'OWNER_REQUIRED']);
    }
}

function dent_bot_create_deploy_notification(array $user, array $payload): array
{
    dent_bot_notifications_require_owner($user);
    $event = $payload['event'] ?? null;
    if (!is_array($event)) {
        dent_error('رویداد استقرار نامعتبر است.', 422, ['code' => 'INVALID_DEPLOY_EVENT']);
    }
    $record = notifications_create_owner_deploy_event_notice($user, $event);
    return [
        'success' => true,
        'notificationId' => (string) ($record['id'] ?? ''),
        'eventId' => (string) ($event['event_id'] ?? ''),
    ];
}

function dent_bot_notification_feed(array $user, array $payload): array
{
    notifications_process_due_queue($user);
    $store = notifications_read_store();
    $limit = max(1, min(40, (int) ($payload['limit'] ?? 20)));
    return ['success' => true, 'data' => notifications_list_payload_for_user($store, $user, $limit)];
}

function dent_bot_term7_status(array $user): array
{
    dent_bot_notifications_require_owner($user);
    $schedule = dent_term7_schedule();
    $state = dent_term7_state_read();
    $sample = dent_term7_resolve_jalali('1405/07/04', 6, ['group10' => 6, 'group8' => 15]);
    $thursday = dent_term7_resolve_jalali('1405/07/02', 4, []);
    $endo = is_array($thursday['theory'][0] ?? null) ? $thursday['theory'][0] : [];
    return [
        'success' => true,
        'contractVersion' => DENT_TERM7_CONTRACT,
        'scheduleVersion' => (string) ($schedule['scheduleVersion'] ?? ''),
        'cohortKey' => (string) ($schedule['cohortKey'] ?? ''),
        'timezone' => (string) ($schedule['timezone'] ?? ''),
        'assignmentCount' => count(is_array($state['assignments'] ?? null) ? $state['assignments'] : []),
        'foodConfirmationCount' => count(is_array($state['foodConfirmations'] ?? null) ? $state['foodConfirmations'] : []),
        'schedulerSlotCount' => count(is_array($state['schedulerSlots'] ?? null) ? $state['schedulerSlots'] : []),
        'sample' => [
            'rotation' => (string) ($sample['rotation'] ?? ''),
            'morningTitles' => array_values(array_map(static fn(array $event): string => (string) ($event['title'] ?? ''), $sample['practicalMorning'] ?? [])),
            'afternoonTitles' => array_values(array_map(static fn(array $event): string => (string) ($event['title'] ?? ''), $sample['practicalAfternoon'] ?? [])),
        ],
        'thursdayEndo' => [
            'title' => (string) ($endo['title'] ?? ''),
            'start' => (string) ($endo['start'] ?? ''),
            'end' => (string) ($endo['end'] ?? ''),
        ],
        'foodUrl' => (string) ($schedule['foodUrl'] ?? ''),
    ];
}

function dent_bot_mark_notification_read(array $user, array $payload): array
{
    $notificationId = trim((string) ($payload['notificationId'] ?? ''));
    if (preg_match('/^nt-[A-Za-z0-9._-]{6,80}$/', $notificationId) !== 1) {
        dent_error('اعلان پیدا نشد.', 404, ['code' => 'NOTIFICATION_NOT_FOUND']);
    }
    $summary = notifications_mark_read($user, [$notificationId]);
    return ['success' => true, 'notificationId' => $notificationId, 'summary' => $summary];
}

function dent_bot_perform_notification_action(array $user, string $platform, array $payload): array
{
    $notificationId = trim((string) ($payload['notificationId'] ?? ''));
    $actionRef = trim((string) ($payload['actionRef'] ?? ''));
    if (preg_match('/^nt-[A-Za-z0-9._-]{6,80}$/', $notificationId) !== 1
        || preg_match('/^[A-Za-z0-9_-]{1,20}$/', $actionRef) !== 1) {
        dent_error('عملیات اعلان معتبر نیست.', 422, ['code' => 'INVALID_NOTIFICATION_ACTION']);
    }
    $store = notifications_read_store();
    $record = is_array($store['notifications'][$notificationId] ?? null) ? $store['notifications'][$notificationId] : null;
    $userState = notifications_user_state($store, $user);
    if (!is_array($record) || !notifications_record_visible_to_user($record, $user, $userState)) {
        dent_error('اعلان پیدا نشد.', 404, ['code' => 'NOTIFICATION_NOT_FOUND']);
    }
    return dent_term7_perform_notification_action($user, $platform, $record, $actionRef);
}

function dent_bot_notification_audience(array $user, array $payload): array
{
    dent_bot_notifications_require_owner($user);
    return [
        'success' => true,
        'data' => notifications_audience_payload($user, trim((string) ($payload['notificationId'] ?? ''))),
    ];
}

function dent_bot_notification_configured_since(): ?int
{
    $raw = trim((string) (getenv('DENT_BOT_NOTIFICATIONS_SINCE') ?: ''));
    if ($raw === '') {
        return null;
    }
    $timestamp = strtotime($raw);
    return $timestamp === false ? null : $timestamp;
}

function dent_bot_notification_delivery_key(string $platform, string $identityHash, string $notificationId): string
{
    return hash_hmac('sha256', $platform . ':' . $identityHash . ':' . $notificationId, dent_auth_secret_key());
}

function dent_bot_notification_delivery_id(string $deliveryKey): string
{
    return 'nd-' . substr($deliveryKey, 0, 32);
}

function dent_bot_notification_absolute_cta(string $href): string
{
    $clean = notifications_clean_cta_href($href);
    return $clean === '' ? '' : dent_bot_site_origin() . $clean;
}

function dent_bot_claim_notification_deliveries(string $platform, array $payload): array
{
    [$platform] = dent_bot_identity($platform, (string) ($payload['platformUserId'] ?? ''));
    // Central, retry-safe generation: both workers may tick it, while canonical
    // source keys and locks guarantee that only one notification is created.
    dent_term7_scheduler_tick();
    $limit = max(1, min(20, (int) ($payload['limit'] ?? 10)));
    $leaseSeconds = max(30, min(600, (int) (getenv('DENT_BOT_NOTIFICATION_LEASE_SECONDS') ?: 120)));
    $maxAttempts = max(1, min(12, (int) (getenv('DENT_BOT_NOTIFICATION_MAX_ATTEMPTS') ?: 5)));
    $configuredSince = dent_bot_notification_configured_since();
    $notificationStore = notifications_read_store();
    $retiredNotificationIds = array_fill_keys(
        is_array($notificationStore['retiredNotificationIds'] ?? null)
            ? $notificationStore['retiredNotificationIds']
            : [],
        true
    );
    $now = time();
    dent_bot_delivery_stores_ensure_migrated();
    $identityStore = dent_bot_identity_snapshot_optional() ?? dent_bot_store_default();

    $claimed = dent_bot_notification_delivery_store_with_lock(static function (array &$store) use (
        $identityStore,
        $platform,
        $limit,
        $leaseSeconds,
        $maxAttempts,
        $configuredSince,
        $notificationStore,
        $retiredNotificationIds,
        $now
    ): array {
        dent_bot_cleanup_notification_delivery_store($store, $now);
        foreach (($store['deliveries'] ?? []) as $deliveryKey => $delivery) {
            if (!is_array($delivery)) {
                continue;
            }
            $notificationId = (string) ($delivery['notificationId'] ?? '');
            $status = (string) ($delivery['status'] ?? 'pending');
            if ($notificationId === '' || !isset($retiredNotificationIds[$notificationId]) || in_array($status, ['delivered', 'failed'], true)) {
                continue;
            }
            $delivery['status'] = 'failed';
            $delivery['leaseUntil'] = 0;
            $delivery['reasonCode'] = 'EXAM_REMINDER_RETIRED';
            $store['deliveries'][$deliveryKey] = $delivery;
        }
        $storedSince = strtotime((string) ($store['dispatchSince'] ?? ''));
        $since = $configuredSince ?? ($storedSince !== false ? $storedSince : $now);
        if ($storedSince === false) {
            $store['dispatchSince'] = gmdate('c', $since);
        }
        $deliveries = [];
        foreach ($store['deliveries'] ?? [] as $deliveryKey => $delivery) {
            if (count($deliveries) >= $limit
                || !is_array($delivery)
                || (string) ($delivery['platform'] ?? '') !== $platform
                || !is_array($delivery['notificationPayload'] ?? null)) {
                continue;
            }
            $status = (string) ($delivery['status'] ?? 'pending');
            $attempts = max(0, (int) ($delivery['attempts'] ?? 0));
            if ($status === 'delivered' || $status === 'failed' || ($status === 'leased' && (int) ($delivery['leaseUntil'] ?? 0) > $now)) {
                continue;
            }
            if ($attempts >= $maxAttempts) {
                $delivery['status'] = 'failed';
                $delivery['leaseUntil'] = 0;
                $delivery['reasonCode'] = (string) (($delivery['reasonCode'] ?? '') ?: 'MAX_ATTEMPTS');
                $store['deliveries'][$deliveryKey] = $delivery;
                continue;
            }
            $chatId = dent_decrypt_secret_text($delivery['platformUserIdEncrypted'] ?? null);
            if (preg_match('/^[0-9]{1,24}$/', $chatId) !== 1) {
                continue;
            }
            $delivery['status'] = 'leased';
            $delivery['attempts'] = $attempts + 1;
            $delivery['leaseUntil'] = $now + $leaseSeconds;
            $delivery['lastAttemptAt'] = dent_iso_now();
            $store['deliveries'][$deliveryKey] = $delivery;
            $deliveries[] = [
                'deliveryId' => (string) ($delivery['deliveryId'] ?? ''),
                'chatId' => $chatId,
                'notification' => $delivery['notificationPayload'],
            ];
        }
        foreach ($identityStore['links'] ?? [] as $identityHash => $link) {
            if (count($deliveries) >= $limit || !is_array($link) || (string) ($link['platform'] ?? '') !== $platform) {
                continue;
            }
            $studentNumber = dent_normalize_student_number((string) ($link['studentNumber'] ?? ''));
            $user = $studentNumber !== '' ? dent_get_user_record($studentNumber) : null;
            $platformUserId = dent_decrypt_secret_text($link['platformUserIdEncrypted'] ?? null);
            if (!is_array($user) || preg_match('/^[0-9]{1,24}$/', $platformUserId) !== 1) {
                continue;
            }
            foreach (notifications_visible_records_for_user($notificationStore, $user) as $record) {
                if (notifications_record_is_retired_exam_reminder($record)) {
                    continue;
                }
                if (dent_term7_notification_is_suppressed_for_user($record, $user)) {
                    continue;
                }
                if (count($deliveries) >= $limit) {
                    break 2;
                }
                $notificationId = (string) ($record['id'] ?? '');
                $recordMeta = is_array($record['meta'] ?? null) ? $record['meta'] : [];
                if (!empty($recordMeta['disablePush'])) {
                    continue;
                }
                $effectiveAt = notifications_timestamp(notifications_record_effective_at($record));
                if ($notificationId === '' || $effectiveAt < $since) {
                    continue;
                }
                $deliveryKey = dent_bot_notification_delivery_key($platform, (string) $identityHash, $notificationId);
                $existing = is_array($store['deliveries'][$deliveryKey] ?? null)
                    ? $store['deliveries'][$deliveryKey]
                    : [];
                $status = (string) ($existing['status'] ?? 'pending');
                $leaseUntil = (int) ($existing['leaseUntil'] ?? 0);
                $attempts = max(0, (int) ($existing['attempts'] ?? 0));
                if ($status === 'delivered' || $status === 'failed' || ($status === 'leased' && $leaseUntil > $now)) {
                    continue;
                }
                if ($attempts >= $maxAttempts) {
                    $existing['status'] = 'failed';
                    $existing['leaseUntil'] = 0;
                    $existing['reasonCode'] = (string) (($existing['reasonCode'] ?? '') ?: 'MAX_ATTEMPTS');
                    $store['deliveries'][$deliveryKey] = $existing;
                    continue;
                }
                $attempts++;
                $deliveryId = dent_bot_notification_delivery_id($deliveryKey);
                $notificationPayload = [
                    'id' => $notificationId,
                    'source' => (string) ($record['source'] ?? ''),
                    'sourceKey' => (string) ($record['sourceKey'] ?? ''),
                    'title' => (string) ($record['title'] ?? ''),
                    'body' => (string) ($record['body'] ?? ''),
                    'tone' => (string) ($record['tone'] ?? 'accent'),
                    'important' => notifications_record_is_important($record),
                    'effectiveAt' => notifications_record_effective_at($record),
                    'ctaLabel' => (string) (($recordMeta['externalUrl'] ?? '') !== '' ? '🍽 رزرو غذا' : ($record['ctaLabel'] ?? '')),
                    'ctaUrl' => (string) (($recordMeta['externalUrl'] ?? '') !== ''
                        ? $recordMeta['externalUrl']
                        : dent_bot_notification_absolute_cta((string) ($record['ctaHref'] ?? ''))),
                    'actions' => dent_term7_public_actions_for_notification($record, $user),
                ];
                $store['deliveries'][$deliveryKey] = [
                    'deliveryId' => $deliveryId,
                    'notificationId' => $notificationId,
                    'identityHash' => (string) $identityHash,
                    'platform' => $platform,
                    'platformUserIdEncrypted' => $link['platformUserIdEncrypted'] ?? dent_encrypt_secret_text($platformUserId),
                    'notificationPayload' => $notificationPayload,
                    'status' => 'leased',
                    'attempts' => $attempts,
                    'leaseUntil' => $now + $leaseSeconds,
                    'lastAttemptAt' => dent_iso_now(),
                    'deliveredAt' => '',
                    'reasonCode' => '',
                ];
                $deliveries[] = [
                    'deliveryId' => $deliveryId,
                    'chatId' => $platformUserId,
                    'notification' => $notificationPayload,
                ];
            }
        }
        return ['deliveries' => $deliveries];
    }, 'claim-notification-deliveries');

    return ['success' => true, 'deliveries' => array_values($claimed['deliveries'] ?? [])];
}

function dent_bot_ack_notification_delivery(string $platform, array $payload): array
{
    [$platform] = dent_bot_identity($platform, (string) ($payload['platformUserId'] ?? ''));
    $deliveryId = trim((string) ($payload['deliveryId'] ?? ''));
    $delivered = filter_var($payload['delivered'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $reasonCode = dent_clean_text((string) ($payload['reasonCode'] ?? ''), 60);
    if (preg_match('/^nd-[a-f0-9]{32}$/', $deliveryId) !== 1) {
        dent_error('شناسه تحویل نامعتبر است.', 422, ['code' => 'INVALID_DELIVERY_ID']);
    }

    dent_bot_delivery_stores_ensure_migrated();
    $updated = dent_bot_notification_delivery_store_with_lock(static function (array &$store) use ($deliveryId, $platform, $delivered, $reasonCode): array {
        foreach ($store['deliveries'] ?? [] as $key => $delivery) {
            if (!is_array($delivery) || (string) ($delivery['deliveryId'] ?? '') !== $deliveryId || (string) ($delivery['platform'] ?? '') !== $platform) {
                continue;
            }
            $retired = !$delivered && $reasonCode === 'EXAM_REMINDER_RETIRED';
            $delivery['status'] = $delivered ? 'delivered' : ($retired ? 'failed' : 'pending');
            $delivery['leaseUntil'] = 0;
            $delivery['deliveredAt'] = $delivered ? dent_iso_now() : '';
            $delivery['reasonCode'] = $delivered ? '' : $reasonCode;
            $store['deliveries'][$key] = $delivery;
            return ['found' => true];
        }
        return ['found' => false];
    }, 'ack-notification-delivery');
    if (empty($updated['found'])) {
        dent_error('تحویل اعلان پیدا نشد.', 404, ['code' => 'DELIVERY_NOT_FOUND']);
    }
    return ['success' => true, 'deliveryId' => $deliveryId, 'delivered' => $delivered];
}
