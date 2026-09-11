<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/payments_store.php';
require_once __DIR__ . '/payments_gateway.php';
require_once __DIR__ . '/endosim_catalog.php';
require_once __DIR__ . '/bot_store.php';

const PAYMENTS_PUBLIC_LIST_PATH = '/buy/';
const PAYMENTS_PUBLIC_ITEM_PATH = '/buy/item/';
const PAYMENTS_PUBLIC_RESULT_PATH = '/buy/result/';

final class PaymentsApiException extends RuntimeException
{
    private int $statusCode;

    public function __construct(string $message, int $statusCode = 422)
    {
        parent::__construct($message);
        $this->statusCode = max(400, min(599, $statusCode));
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}

function payments_api_require_method(array $methods): void
{
    $method = dent_request_method();
    if (!in_array($method, $methods, true)) {
        dent_error('متد درخواست نامعتبر است.', 405);
    }
}

function payments_api_require_public_user(): array
{
    return dent_require_main_site_user();
}

function payments_api_current_public_user(): ?array
{
    $user = dent_current_user();
    if ($user !== null && dent_user_is_prosthesis($user)) {
        return null;
    }

    return $user;
}

function payments_api_user_can_view_order(array $order, array $user): bool
{
    if ((string) ($user['role'] ?? 'student') === 'owner') {
        return true;
    }

    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        return false;
    }

    return $studentNumber === dent_normalize_student_number((string) ($order['user_id'] ?? ''))
        || $studentNumber === dent_normalize_student_number((string) ($order['payer_student_number'] ?? ''));
}

function payments_api_absolute_url(string $path): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return $path;
    }

    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $isSecure = $forwardedProto === 'https'
        || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');

    return ($isSecure ? 'https://' : 'http://') . $host . $path;
}

function payments_api_decode_json_assoc(string $value): ?array
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return null;
    }

    $decoded = json_decode($trimmed, true);
    if (!is_array($decoded)) {
        return null;
    }

    return $decoded;
}

function payments_api_clean_return_path($raw, string $fallback = ''): string
{
    $path = trim((string) $raw);
    if ($path === '') {
        return $fallback;
    }

    if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
        return $fallback;
    }

    if (preg_match('/[\r\n]/', $path) === 1) {
        return $fallback;
    }

    return dent_clean_text($path, 420);
}

function payments_api_parse_required_fields_input($raw): array
{
    if (is_array($raw)) {
        return payments_normalize_required_fields_loose($raw);
    }

    $text = trim((string) $raw);
    if ($text === '') {
        return [];
    }

    $decoded = payments_api_decode_json_assoc($text);
    if (!is_array($decoded)) {
        dent_error('فرمت فیلدهای موردنیاز معتبر نیست. JSON معتبر وارد کنید.', 422);
    }

    $normalized = payments_normalize_required_fields_loose($decoded);
    if ($decoded !== [] && $normalized === []) {
        dent_error('حداقل یک فیلد معتبر برای فرم پرداخت تعریف کنید.', 422);
    }

    return $normalized;
}

function payments_api_parse_specifications_input($raw): array
{
    if (is_array($raw)) {
        return payments_normalize_specifications($raw);
    }

    $text = trim((string) $raw);
    if ($text === '') {
        return [];
    }

    $decoded = payments_api_decode_json_assoc($text);
    if (is_array($decoded)) {
        return payments_normalize_specifications($decoded);
    }

    return payments_normalize_specifications($text);
}

function payments_api_parse_gallery_input($raw): array
{
    if (is_array($raw)) {
        return payments_normalize_string_list($raw, 24, 420);
    }

    $text = trim((string) $raw);
    if ($text === '') {
        return [];
    }

    $decoded = payments_api_decode_json_assoc($text);
    if (is_array($decoded)) {
        return payments_normalize_string_list($decoded, 24, 420);
    }

    return payments_normalize_string_list($text, 24, 420);
}

function payments_api_parse_discount_codes_input($raw): array
{
    if (is_array($raw)) {
        return payments_normalize_discount_codes($raw);
    }

    $text = trim((string) $raw);
    if ($text === '') {
        return [];
    }

    $decoded = payments_api_decode_json_assoc($text);
    if (!is_array($decoded)) {
        dent_error('فرمت کدهای تخفیف معتبر نیست. JSON معتبر وارد کنید.', 422);
    }

    return payments_normalize_discount_codes($decoded);
}

function payments_api_parse_reviews_input($raw): array
{
    if (is_array($raw)) {
        return payments_normalize_reviews($raw);
    }

    $text = trim((string) $raw);
    if ($text === '') {
        return [];
    }

    $decoded = payments_api_decode_json_assoc($text);
    if (!is_array($decoded)) {
        dent_error('فرمت دیدگاه‌ها معتبر نیست. JSON معتبر وارد کنید.', 422);
    }

    return payments_normalize_reviews($decoded);
}

function payments_api_parse_extra_form_data($raw): array
{
    if (is_array($raw)) {
        return payments_normalize_extra_form_data($raw);
    }

    $text = trim((string) $raw);
    if ($text === '') {
        return [];
    }

    $decoded = payments_api_decode_json_assoc($text);
    if (!is_array($decoded)) {
        return [];
    }

    return payments_normalize_extra_form_data($decoded);
}

function payments_api_status_label(string $status): string
{
    if ($status === PAYMENTS_ORDER_STATUS_SUCCESS) {
        return 'موفق';
    }
    if ($status === PAYMENTS_ORDER_STATUS_FAILED) {
        return 'ناموفق';
    }
    if ($status === PAYMENTS_ORDER_STATUS_CANCELED) {
        return 'لغو شده';
    }
    if ($status === PAYMENTS_ORDER_STATUS_EXPIRED) {
        return 'منقضی شده';
    }

    return 'در انتظار';
}

function payments_api_item_status_message(array $state): string
{
    $key = (string) ($state['key'] ?? '');
    if ($key === 'inactive') {
        return 'این آیتم سفارش غیرفعال است.';
    }
    if ($key === 'upcoming') {
        return 'پرداخت این آیتم هنوز شروع نشده است.';
    }
    if ($key === 'expired') {
        return 'مهلت پرداخت این آیتم به پایان رسیده است.';
    }
    if ($key === 'full') {
        return 'ظرفیت این آیتم تکمیل شده است.';
    }

    return 'این آیتم قابل پرداخت است.';
}

function payments_api_notifications_unread_count(array $notifications): int
{
    $count = 0;
    foreach ($notifications as $notification) {
        if (!is_array($notification)) {
            continue;
        }
        if (trim((string) ($notification['read_at'] ?? '')) === '') {
            $count++;
        }
    }

    return $count;
}

function payments_api_order_extra(array $order): array
{
    return is_array($order['extra_form_data'] ?? null) ? $order['extra_form_data'] : [];
}

function payments_api_order_source_key(array $order): string
{
    $extra = payments_api_order_extra($order);
    $source = trim((string) ($extra['_source'] ?? ''));
    if ($source === 'form_receipt_payment') {
        return 'form_receipt';
    }
    if ($source === 'form_payment') {
        return 'form_online';
    }
    if ($source === 'collection') {
        return 'collection';
    }
    if ($source === 'cart') {
        return 'cart';
    }
    if (trim((string) ($extra['form_id'] ?? '')) !== '') {
        return (string) ($order['gateway'] ?? '') === 'receipt' ? 'form_receipt' : 'form_online';
    }
    if ((int) ($extra['collection_id'] ?? 0) > 0) {
        return 'collection';
    }
    if ((int) ($order['item_id'] ?? 0) > 0 || payments_normalize_cart_order_items($order['cart_items'] ?? []) !== []) {
        return 'catalog';
    }
    return 'other';
}

function payments_api_order_source_label(string $source): string
{
    if ($source === 'form_receipt') {
        return 'فرم - رسید';
    }
    if ($source === 'form_online') {
        return 'فرم - درگاه';
    }
    if ($source === 'collection') {
        return 'جمع‌آوری هزینه';
    }
    if ($source === 'cart') {
        return 'سبد خرید';
    }
    if ($source === 'catalog') {
        return 'آیتم خرید';
    }
    return 'سایر';
}

function payments_api_order_method_key(array $order): string
{
    if ((string) ($order['gateway'] ?? '') === 'receipt' || payments_api_order_source_key($order) === 'form_receipt') {
        return 'receipt';
    }
    return 'online';
}

function payments_api_order_method_label(string $method): string
{
    return $method === 'receipt' ? 'آپلود رسید' : 'درگاه آنلاین';
}

function payments_api_summary_bucket(array &$target, string $key, string $label, array $order, ?int $amountOverride = null): void
{
    if ($key === '') {
        return;
    }
    if (!isset($target[$key])) {
        $target[$key] = [
            'key' => $key,
            'label' => $label,
            'totalOrders' => 0,
            'successOrders' => 0,
            'totalAmount' => 0,
            'receivedAmount' => 0,
        ];
    }

    $amount = $amountOverride === null ? max(0, (int) ($order['amount'] ?? 0)) : max(0, $amountOverride);
    $target[$key]['totalOrders']++;
    $target[$key]['totalAmount'] += $amount;
    if ((string) ($order['status'] ?? '') === PAYMENTS_ORDER_STATUS_SUCCESS) {
        $target[$key]['successOrders']++;
        $target[$key]['receivedAmount'] += $amount;
    }
}

function payments_api_order_form_key(array $order): string
{
    $extra = payments_api_order_extra($order);
    return trim((string) ($extra['form_id'] ?? ''));
}

function payments_api_order_form_label(array $order): string
{
    $extra = payments_api_order_extra($order);
    $title = trim((string) ($extra['form_title'] ?? ''));
    return $title !== '' ? $title : 'فرم بدون عنوان';
}

function payments_api_order_collection_key(array $order): string
{
    $extra = payments_api_order_extra($order);
    $collectionId = (int) ($extra['collection_id'] ?? 0);
    return $collectionId > 0 ? 'collection-' . $collectionId : '';
}

function payments_api_order_collection_label(array $order): string
{
    $extra = payments_api_order_extra($order);
    $title = trim((string) ($extra['collection_title'] ?? ''));
    return $title !== '' ? $title : 'جمع‌آوری هزینه';
}

function payments_api_orders_summary(array $orders): array
{
    $summary = [
        'totalOrders' => 0,
        'totalSuccess' => 0,
        'totalPending' => 0,
        'totalFailed' => 0,
        'totalCanceled' => 0,
        'totalExpired' => 0,
        'totalAmount' => 0,
        'totalReceived' => 0,
        'totalPendingAmount' => 0,
        'totalFailedAmount' => 0,
        'totalCanceledAmount' => 0,
        'totalExpiredAmount' => 0,
        'totalUnfinishedAmount' => 0,
        'breakdowns' => [
            'sources' => [],
            'methods' => [],
            'items' => [],
            'forms' => [],
            'collections' => [],
            'gateways' => [],
        ],
    ];

    foreach ($orders as $order) {
        if (!is_array($order)) {
            continue;
        }

        $summary['totalOrders']++;
        $status = (string) ($order['status'] ?? PAYMENTS_ORDER_STATUS_PENDING);
        $amount = max(0, (int) ($order['amount'] ?? 0));
        $summary['totalAmount'] += $amount;
        if ($status === PAYMENTS_ORDER_STATUS_SUCCESS) {
            $summary['totalSuccess']++;
            $summary['totalReceived'] += $amount;
        } elseif ($status === PAYMENTS_ORDER_STATUS_FAILED) {
            $summary['totalFailed']++;
            $summary['totalFailedAmount'] += $amount;
            $summary['totalUnfinishedAmount'] += $amount;
        } elseif ($status === PAYMENTS_ORDER_STATUS_CANCELED) {
            $summary['totalCanceled']++;
            $summary['totalCanceledAmount'] += $amount;
            $summary['totalUnfinishedAmount'] += $amount;
        } elseif ($status === PAYMENTS_ORDER_STATUS_EXPIRED) {
            $summary['totalExpired']++;
            $summary['totalExpiredAmount'] += $amount;
            $summary['totalUnfinishedAmount'] += $amount;
        } else {
            $summary['totalPending']++;
            $summary['totalPendingAmount'] += $amount;
            $summary['totalUnfinishedAmount'] += $amount;
        }

        $source = payments_api_order_source_key($order);
        payments_api_summary_bucket($summary['breakdowns']['sources'], $source, payments_api_order_source_label($source), $order);
        $method = payments_api_order_method_key($order);
        payments_api_summary_bucket($summary['breakdowns']['methods'], $method, payments_api_order_method_label($method), $order);
        $gateway = trim((string) ($order['gateway'] ?? ''));
        payments_api_summary_bucket($summary['breakdowns']['gateways'], $gateway !== '' ? $gateway : 'unknown', $gateway !== '' ? $gateway : 'نامشخص', $order);

        $formKey = payments_api_order_form_key($order);
        if ($formKey !== '') {
            payments_api_summary_bucket($summary['breakdowns']['forms'], $formKey, payments_api_order_form_label($order), $order);
        }
        $collectionKey = payments_api_order_collection_key($order);
        if ($collectionKey !== '') {
            payments_api_summary_bucket($summary['breakdowns']['collections'], $collectionKey, payments_api_order_collection_label($order), $order);
        }
        $cartItems = payments_normalize_cart_order_items($order['cart_items'] ?? []);
        if ($cartItems === [] && (int) ($order['item_id'] ?? 0) > 0) {
            $itemId = (int) ($order['item_id'] ?? 0);
            payments_api_summary_bucket(
                $summary['breakdowns']['items'],
                'item-' . $itemId,
                'آیتم #' . (string) $itemId,
                $order
            );
        }
        foreach ($cartItems as $cartItem) {
            $itemId = (int) ($cartItem['item_id'] ?? 0);
            $itemKey = $itemId > 0 ? 'item-' . $itemId : trim((string) ($cartItem['title'] ?? ''));
            $itemLabel = trim((string) ($cartItem['title'] ?? ''));
            payments_api_summary_bucket(
                $summary['breakdowns']['items'],
                $itemKey,
                $itemLabel !== '' ? $itemLabel : 'آیتم نامشخص',
                $order,
                max(0, (int) ($cartItem['amount'] ?? 0))
            );
        }
    }

    foreach ($summary['breakdowns'] as $group => $rows) {
        $rows = array_values($rows);
        usort($rows, static function (array $left, array $right): int {
            return (int) ($right['receivedAmount'] ?? 0) <=> (int) ($left['receivedAmount'] ?? 0);
        });
        $summary['breakdowns'][$group] = $rows;
    }

    return $summary;
}

function payments_api_owner_order_filters_from(array $source): array
{
    return [
        'itemId' => (int) ($source['itemId'] ?? 0),
        'status' => dent_clean_text((string) ($source['status'] ?? 'all'), 24),
        'source' => dent_clean_text((string) ($source['source'] ?? 'all'), 32),
        'method' => dent_clean_text((string) ($source['method'] ?? 'all'), 24),
        'formId' => dent_clean_text((string) ($source['formId'] ?? ''), 90),
        'dateFrom' => dent_clean_text((string) ($source['dateFrom'] ?? ''), 40),
        'dateTo' => dent_clean_text((string) ($source['dateTo'] ?? ''), 40),
        'query' => dent_clean_text((string) ($source['query'] ?? ''), 120),
    ];
}

function payments_api_order_has_item_id(array $order, int $itemId): bool
{
    if ($itemId <= 0) {
        return true;
    }

    if ((int) ($order['item_id'] ?? 0) === $itemId) {
        return true;
    }

    foreach (payments_normalize_cart_order_items($order['cart_items'] ?? []) as $cartItem) {
        if ((int) ($cartItem['item_id'] ?? 0) === $itemId) {
            return true;
        }
    }

    return false;
}

function payments_api_status_filter_matches(string $orderStatus, string $filterStatus): bool
{
    $filter = trim($filterStatus);
    if ($filter === '' || $filter === 'all') {
        return true;
    }
    if ($filter === 'done') {
        return $orderStatus === PAYMENTS_ORDER_STATUS_SUCCESS;
    }
    if ($filter === 'unfinished' || $filter === 'not_done') {
        return $orderStatus !== PAYMENTS_ORDER_STATUS_SUCCESS;
    }
    if ($filter === 'problem') {
        return in_array($orderStatus, [
            PAYMENTS_ORDER_STATUS_FAILED,
            PAYMENTS_ORDER_STATUS_CANCELED,
            PAYMENTS_ORDER_STATUS_EXPIRED,
        ], true);
    }

    return $orderStatus === $filter;
}

function payments_api_order_matches_filters(array $order, array $itemById, array $filters): bool
{
    $filterItemId = max(0, (int) ($filters['itemId'] ?? 0));
    if (!payments_api_order_has_item_id($order, $filterItemId)) {
        return false;
    }

    $status = trim((string) ($filters['status'] ?? ''));
    if (!payments_api_status_filter_matches((string) ($order['status'] ?? PAYMENTS_ORDER_STATUS_PENDING), $status)) {
        return false;
    }

    $source = trim((string) ($filters['source'] ?? 'all'));
    if ($source !== '' && $source !== 'all') {
        $actualSource = payments_api_order_source_key($order);
        if ($source === 'form') {
            if (!in_array($actualSource, ['form_online', 'form_receipt'], true)) {
                return false;
            }
        } elseif ($actualSource !== $source) {
            return false;
        }
    }

    $method = trim((string) ($filters['method'] ?? 'all'));
    if ($method !== '' && $method !== 'all' && payments_api_order_method_key($order) !== $method) {
        return false;
    }

    $formId = trim((string) ($filters['formId'] ?? ''));
    if ($formId !== '' && payments_api_order_form_key($order) !== $formId) {
        return false;
    }

    $fromTs = payments_timestamp_or_null((string) ($filters['dateFrom'] ?? ''));
    if ($fromTs !== null) {
        $orderTs = payments_timestamp_or_null((string) ($order['created_at'] ?? ''));
        if ($orderTs !== null && $orderTs < $fromTs) {
            return false;
        }
    }

    $toTs = payments_timestamp_or_null((string) ($filters['dateTo'] ?? ''));
    if ($toTs !== null) {
        $orderTs = payments_timestamp_or_null((string) ($order['created_at'] ?? ''));
        if ($orderTs !== null && $orderTs > ($toTs + 86399)) {
            return false;
        }
    }

    $query = dent_utf8_strtolower(dent_clean_text((string) ($filters['query'] ?? ''), 120));
    if ($query !== '') {
        $itemId = (int) ($order['item_id'] ?? 0);
        $item = $itemById[$itemId] ?? null;
        $haystacks = [
            (string) ($order['payer_name'] ?? ''),
            (string) ($order['payer_phone'] ?? ''),
            (string) ($order['payer_student_number'] ?? ''),
            (string) ($order['authority'] ?? ''),
            (string) ($order['ref_id'] ?? ''),
            is_array($item) ? (string) ($item['title'] ?? '') : '',
        ];
        foreach (payments_normalize_cart_order_items($order['cart_items'] ?? []) as $cartItem) {
            $haystacks[] = (string) ($cartItem['title'] ?? '');
            $haystacks[] = (string) ($cartItem['slug'] ?? '');
        }
        $found = false;
        foreach ($haystacks as $haystack) {
            if (str_contains(dent_utf8_strtolower((string) $haystack), $query)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            return false;
        }
    }

    return true;
}

function payments_api_redirect_to_result(string $orderToken): void
{
    $target = PAYMENTS_PUBLIC_RESULT_PATH . '?orderToken=' . rawurlencode($orderToken);
    $store = payments_read_store();
    $orderIndex = payments_find_order_index_by_token($store, $orderToken);
    if ($orderIndex >= 0 && is_array($store['orders'][$orderIndex] ?? null)) {
        $extra = is_array($store['orders'][$orderIndex]['extra_form_data'] ?? null) ? $store['orders'][$orderIndex]['extra_form_data'] : [];
        if ((string) ($extra['source'] ?? '') === 'bot-offer') {
            $platform = (string) ($extra['bot_origin_platform'] ?? '');
            $envKey = $platform === 'telegram' ? 'DENT_TELEGRAM_BOT_USERNAME' : ($platform === 'bale' ? 'DENT_BALE_BOT_USERNAME' : '');
            $username = $envKey !== '' ? trim((string) dent_env_value($envKey)) : '';
            if ($username === '') {
                $username = $platform === 'telegram' ? 'Dent1402Bot' : ($platform === 'bale' ? 'dent1402bot' : '');
            }
            if (preg_match('/^[A-Za-z0-9_]{4,64}$/', $username) === 1) {
                $host = $platform === 'bale' ? 'https://ble.ir/' : 'https://t.me/';
                $target = $host . $username . '?start=receipt_' . rawurlencode($orderToken);
                header('Location: ' . $target, true, 302);
                exit;
            }
        }
        $returnPath = dent_clean_text((string) ($extra['_return_path'] ?? ''), 420);
        if ($returnPath !== '' && str_starts_with($returnPath, '/') && !str_starts_with($returnPath, '//')) {
            $separator = str_contains($returnPath, '?') ? '&' : '?';
            $target = $returnPath . $separator . 'paymentOrderToken=' . rawurlencode($orderToken);
        }
    }

    header('Location: ' . $target, true, 302);
    exit;
}

function payments_api_append_order_notification(
    array &$store,
    array $order,
    ?array $item,
    bool $success
): array {
    if ($success && dent_bot_payment_is_offer_order($order)) {
        try {
            dent_bot_queue_payment_success_deliveries($order);
        } catch (Throwable $error) {
            // Payment verification is authoritative and must never be rolled
            // back because an auxiliary bot-delivery store is temporarily
            // unavailable. The claim action idempotently backfills this intent.
            error_log('Bot payment success delivery deferred: ' . get_class($error));
        }
    }
    $orderId = (int) ($order['id'] ?? 0);
    $type = $success ? PAYMENTS_NOTIFICATION_TYPE_ORDER_SUCCESS : PAYMENTS_NOTIFICATION_TYPE_ORDER_FAILED;
    $title = $success ? 'پرداخت موفق جدید' : 'پرداخت ناموفق یا لغوشده';
    $cartItems = payments_normalize_cart_order_items($order['cart_items'] ?? []);
    $itemTitle = $item ? (string) ($item['title'] ?? '') : '';
    if ($itemTitle === '' && $cartItems !== []) {
        $itemTitle = 'سبد خرید (' . count($cartItems) . ' آیتم)';
    }
    if ($itemTitle === '') {
        $itemTitle = 'آیتم نامشخص';
    }
    $payerName = (string) ($order['payer_name'] ?? 'کاربر');
    $amount = number_format(max(0, (int) ($order['amount'] ?? 0)));
    $statusLabel = payments_api_status_label((string) ($order['status'] ?? ''));

    $body = sprintf(
        '%s | %s | %s ریال | وضعیت: %s',
        $itemTitle,
        $payerName,
        $amount,
        $statusLabel
    );

    return payments_append_notification($store, $type, $title, $body, $orderId);
}

function payments_api_trigger_notification_hook(array $notification, array $order, ?array $item): void
{
    $webhook = trim((string) getenv('DENT_PAYMENT_NOTIFICATION_WEBHOOK'));
    if ($webhook === '') {
        return;
    }

    $payload = [
        'event' => 'payment-notification',
        'notification' => $notification,
        'order' => payments_owner_order_payload($order, $item),
        'item' => $item ? payments_owner_item_payload($item) : null,
    ];

    payments_gateway_http_post_json($webhook, $payload, 8);
}

function payments_api_order_reconcile_due(array $order, int $now, int $minAgeSeconds, int $cooldownSeconds): bool
{
    if ((string) ($order['status'] ?? '') !== PAYMENTS_ORDER_STATUS_PENDING) {
        return false;
    }
    if (payments_api_order_method_key($order) === 'receipt') {
        return false;
    }
    if (max(0, (int) ($order['amount'] ?? 0)) <= 0) {
        return false;
    }
    if (trim((string) ($order['authority'] ?? '')) === '') {
        return false;
    }

    $createdAt = payments_timestamp_or_null((string) ($order['created_at'] ?? ''));
    if ($createdAt !== null && $minAgeSeconds > 0 && ($now - $createdAt) < $minAgeSeconds) {
        return false;
    }

    $snapshot = is_array($order['gateway_response_snapshot'] ?? null) ? $order['gateway_response_snapshot'] : [];
    $auto = is_array($snapshot['auto_reconcile'] ?? null) ? $snapshot['auto_reconcile'] : [];
    $lastAt = payments_timestamp_or_null((string) ($auto['at'] ?? ''));
    if ($lastAt !== null && $cooldownSeconds > 0 && ($now - $lastAt) < $cooldownSeconds) {
        return false;
    }

    return true;
}

function payments_api_reconcile_pending_orders(array $options = []): array
{
    $limit = max(1, min(60, (int) ($options['limit'] ?? 12)));
    $minAgeSeconds = max(0, (int) ($options['minAgeSeconds'] ?? 45));
    $cooldownSeconds = max(0, (int) ($options['cooldownSeconds'] ?? 240));
    $now = time();
    $store = payments_read_store();
    $candidates = [];

    foreach (($store['orders'] ?? []) as $order) {
        if (!is_array($order) || !payments_api_order_reconcile_due($order, $now, $minAgeSeconds, $cooldownSeconds)) {
            continue;
        }
        $candidates[] = [
            'id' => (int) ($order['id'] ?? 0),
            'gateway' => (string) ($order['gateway'] ?? ''),
            'authority' => dent_clean_text((string) ($order['authority'] ?? ''), 120),
            'order' => $order,
        ];
        if (count($candidates) >= $limit) {
            break;
        }
    }

    $result = [
        'checked' => 0,
        'verified' => 0,
        'changed' => 0,
        'stillPending' => 0,
        'errors' => 0,
    ];

    foreach ($candidates as $candidate) {
        $orderId = (int) ($candidate['id'] ?? 0);
        $authority = (string) ($candidate['authority'] ?? '');
        $gateway = (string) ($candidate['gateway'] ?? '');
        $order = is_array($candidate['order'] ?? null) ? $candidate['order'] : [];
        if ($orderId <= 0 || $authority === '' || $order === []) {
            continue;
        }

        $result['checked']++;
        try {
            $verifyResult = payments_gateway_verify_payment($gateway, $order, [
                'authority' => $authority,
                'trackId' => $authority,
                'status' => 'ok',
                'autoReconcile' => true,
            ]);
            payments_log_gateway_event('auto-reconcile-request', [
                'orderId' => $orderId,
                'gateway' => $gateway,
                'result' => $verifyResult,
            ]);
        } catch (Throwable $error) {
            $result['errors']++;
            payments_log_gateway_event('auto-reconcile-error', [
                'orderId' => $orderId,
                'gateway' => $gateway,
                'error' => $error->getMessage(),
            ]);
            continue;
        }

        $payload = payments_with_store_lock(static function (array &$store) use ($orderId, $authority, $verifyResult): array {
            $orderIndex = payments_find_order_index_by_id($store, $orderId);
            if ($orderIndex < 0 || !is_array($store['orders'][$orderIndex] ?? null)) {
                return ['applied' => false];
            }

            $current = $store['orders'][$orderIndex];
            if ((string) ($current['status'] ?? '') !== PAYMENTS_ORDER_STATUS_PENDING) {
                return ['applied' => false, 'status' => (string) ($current['status'] ?? '')];
            }

            $currentAuthority = dent_clean_text((string) ($current['authority'] ?? ''), 120);
            if ($currentAuthority !== '' && $currentAuthority !== $authority) {
                return ['applied' => false, 'status' => (string) ($current['status'] ?? '')];
            }
            if ($currentAuthority === '') {
                $current['authority'] = $authority;
            }

            $previousStatus = (string) ($current['status'] ?? PAYMENTS_ORDER_STATUS_PENDING);
            $snapshot = is_array($current['gateway_response_snapshot'] ?? null) ? $current['gateway_response_snapshot'] : [];
            $snapshot['auto_reconcile'] = [
                'authority' => $authority,
                'at' => dent_iso_now(),
                'result' => $verifyResult,
            ];

            if ((bool) ($verifyResult['verified'] ?? false)) {
                $current['status'] = PAYMENTS_ORDER_STATUS_SUCCESS;
                $current['ref_id'] = dent_clean_text((string) ($verifyResult['refId'] ?? ''), 120);
                if ((string) ($current['paid_at'] ?? '') === '') {
                    $current['paid_at'] = dent_iso_now();
                }
                $current['verified_at'] = dent_iso_now();
            }

            $current['gateway_response_snapshot'] = $snapshot;
            $store['orders'][$orderIndex] = $current;

            $item = null;
            $itemIndex = payments_find_item_index_by_id($store, (int) ($current['item_id'] ?? 0));
            if ($itemIndex >= 0) {
                $item = $store['items'][$itemIndex];
            }

            $notification = null;
            if ($previousStatus !== (string) ($current['status'] ?? '') && (string) ($current['status'] ?? '') === PAYMENTS_ORDER_STATUS_SUCCESS) {
                $notification = payments_api_append_order_notification($store, $current, is_array($item) ? $item : null, true);
            }

            return [
                'applied' => true,
                'verified' => (string) ($current['status'] ?? '') === PAYMENTS_ORDER_STATUS_SUCCESS,
                'changed' => $previousStatus !== (string) ($current['status'] ?? ''),
                'status' => (string) ($current['status'] ?? ''),
                'order' => $current,
                'item' => $item,
                'notification' => $notification,
            ];
        });

        if ((bool) ($payload['verified'] ?? false)) {
            $result['verified']++;
        }
        if ((bool) ($payload['changed'] ?? false)) {
            $result['changed']++;
        }
        if ((string) ($payload['status'] ?? '') === PAYMENTS_ORDER_STATUS_PENDING) {
            $result['stillPending']++;
        }
        if (is_array($payload['notification'] ?? null) && is_array($payload['order'] ?? null)) {
            payments_api_trigger_notification_hook(
                $payload['notification'],
                $payload['order'],
                is_array($payload['item'] ?? null) ? $payload['item'] : null
            );
        }
    }

    return $result;
}

function payments_api_checkout_gateways_payload(): array
{
    $catalog = payments_gateway_checkout_catalog(false);
    $defaultKey = payments_gateway_default_enabled_checkout(false);

    $gateways = [];
    foreach ($catalog as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $key = payments_gateway_clean((string) ($entry['key'] ?? ''));
        if ($key === '') {
            continue;
        }

        $gateways[] = [
            'key' => $key,
            'label' => dent_clean_text((string) ($entry['label'] ?? ''), 80),
            'provider' => dent_clean_text((string) ($entry['provider'] ?? ''), 120),
            'icon' => dent_clean_text((string) ($entry['icon'] ?? ''), 8),
            'isEnabled' => (bool) ($entry['isEnabled'] ?? false),
            'isDefault' => (bool) ($entry['isEnabled'] ?? false) && $key === $defaultKey,
        ];
    }

    return [
        'defaultKey' => $defaultKey,
        'gateways' => $gateways,
    ];
}

function payments_api_owner_gateway_payload(array $gateway): array
{
    $provider = payments_gateway_provider_clean((string) ($gateway['provider'] ?? ''));
    return [
        'id' => (int) ($gateway['id'] ?? 0),
        'key' => (string) ($gateway['key'] ?? ''),
        'provider' => $provider,
        'providerLabel' => (string) ($gateway['provider_label'] ?? payments_gateway_provider_label_from_type($provider)),
        'label' => (string) ($gateway['label'] ?? payments_gateway_public_label($provider)),
        'icon' => (string) ($gateway['icon'] ?? ''),
        'merchantId' => (string) ($gateway['merchant_id'] ?? ''),
        'apiKey' => (string) ($gateway['api_key'] ?? ''),
        'requestUrl' => (string) ($gateway['request_url'] ?? ''),
        'verifyUrl' => (string) ($gateway['verify_url'] ?? ''),
        'startUrl' => (string) ($gateway['start_url'] ?? ''),
        'isEnabled' => (bool) ($gateway['is_enabled'] ?? false),
        'isDefault' => (bool) ($gateway['is_default'] ?? false),
        'isConfigured' => payments_gateway_is_record_configured($gateway),
        'createdAt' => (string) ($gateway['created_at'] ?? ''),
        'updatedAt' => (string) ($gateway['updated_at'] ?? ''),
    ];
}

function payments_api_owner_gateways_payload(array $store): array
{
    $gateways = [];
    foreach ($store['gateways'] as $gateway) {
        if (!is_array($gateway)) {
            continue;
        }
        $gateways[] = payments_api_owner_gateway_payload($gateway);
    }

    return [
        'managed' => (bool) (($store['gatewaySettings']['managed'] ?? false)),
        'gateways' => $gateways,
        'checkout' => payments_api_checkout_gateways_payload(),
    ];
}

function payments_api_collection_order_matches(array $order, int $collectionId): bool
{
    $extra = is_array($order['extra_form_data'] ?? null) ? $order['extra_form_data'] : [];
    return (string) ($extra['_source'] ?? '') === 'collection'
        && (int) ($extra['collection_id'] ?? 0) === $collectionId;
}

function payments_api_collection_orders(array $store, int $collectionId): array
{
    $orders = [];
    foreach (($store['orders'] ?? []) as $order) {
        if (!is_array($order) || !payments_api_collection_order_matches($order, $collectionId)) {
            continue;
        }
        $orders[] = $order;
    }

    usort($orders, static function (array $left, array $right): int {
        return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    });
    return $orders;
}

function payments_api_public_collection_order_for_user(array $store, int $collectionId, array $user): ?array
{
    $studentNumber = dent_normalize_student_number((string) ($user['studentNumber'] ?? ''));
    if ($studentNumber === '') {
        return null;
    }

    foreach (payments_api_collection_orders($store, $collectionId) as $order) {
        if ((string) ($order['status'] ?? '') !== PAYMENTS_ORDER_STATUS_SUCCESS) {
            continue;
        }
        if (
            $studentNumber === dent_normalize_student_number((string) ($order['user_id'] ?? ''))
            || $studentNumber === dent_normalize_student_number((string) ($order['payer_student_number'] ?? ''))
        ) {
            return $order;
        }
    }

    return null;
}

function payments_api_collection_allows_guest(array $collection): bool
{
    return (bool) ($collection['allow_guest_payments'] ?? false);
}

function payments_api_collection_collects(array $collection, string $field): bool
{
    if ($field === 'name') {
        return (bool) ($collection['collect_payer_name'] ?? true);
    }
    if ($field === 'phone') {
        return (bool) ($collection['collect_payer_phone'] ?? true);
    }
    if ($field === 'student_number') {
        return (bool) ($collection['collect_payer_student_number'] ?? false);
    }
    return false;
}

function payments_api_collection_payer_defaults(?array $user): array
{
    if ($user === null) {
        return [
            'name' => '',
            'phone' => '',
            'studentNumber' => '',
        ];
    }

    return [
        'name' => (string) (dent_public_user($user)['name'] ?? ''),
        'phone' => payments_normalize_phone((string) ($user['phoneNumber'] ?? '')),
        'studentNumber' => dent_normalize_student_number((string) ($user['studentNumber'] ?? '')),
    ];
}

function payments_api_collection_from_order(array $store, array $order): ?array
{
    $extra = is_array($order['extra_form_data'] ?? null) ? $order['extra_form_data'] : [];
    $collectionId = (int) ($extra['collection_id'] ?? 0);
    if ($collectionId <= 0) {
        return null;
    }

    $index = payments_find_collection_index_by_id($store, $collectionId);
    if ($index < 0 || !is_array($store['collections'][$index] ?? null)) {
        return null;
    }

    return $store['collections'][$index];
}

function payments_api_owner_collection_payload(array $collection, array $orders = []): array
{
    $successOrders = array_values(array_filter($orders, static function (array $order): bool {
        return (string) ($order['status'] ?? '') === PAYMENTS_ORDER_STATUS_SUCCESS;
    }));
    $received = 0;
    foreach ($successOrders as $order) {
        $received += max(0, (int) ($order['amount'] ?? 0));
    }

    return [
        'id' => (int) ($collection['id'] ?? 0),
        'token' => (string) ($collection['token'] ?? ''),
        'title' => (string) ($collection['title'] ?? ''),
        'description' => (string) ($collection['description'] ?? ''),
        'imageUrl' => (string) ($collection['image_url'] ?? ''),
        'amount' => max(0, (int) ($collection['amount'] ?? 0)),
        'discountCodes' => payments_normalize_collection_discount_codes($collection['discount_codes'] ?? []),
        'hasDiscountCodes' => payments_normalize_collection_discount_codes($collection['discount_codes'] ?? []) !== [],
        'status' => (string) ($collection['status'] ?? PAYMENTS_COLLECTION_STATUS_INACTIVE),
        'gateway' => (string) ($collection['gateway'] ?? ''),
        'allowGuestPayments' => payments_api_collection_allows_guest($collection),
        'collectPayerName' => payments_api_collection_collects($collection, 'name'),
        'collectPayerPhone' => payments_api_collection_collects($collection, 'phone'),
        'collectPayerStudentNumber' => payments_api_collection_collects($collection, 'student_number'),
        'successMessage' => (string) ($collection['success_message'] ?? ''),
        'failureMessage' => (string) ($collection['failure_message'] ?? ''),
        'createdAt' => (string) ($collection['created_at'] ?? ''),
        'updatedAt' => (string) ($collection['updated_at'] ?? ''),
        'publicPath' => '/payments/pay/?token=' . rawurlencode((string) ($collection['token'] ?? '')),
        'publicUrl' => payments_api_absolute_url('/payments/pay/?token=' . rawurlencode((string) ($collection['token'] ?? ''))),
        'ordersCount' => count($orders),
        'successCount' => count($successOrders),
        'receivedAmount' => $received,
    ];
}

function payments_api_public_collection_payload(array $collection, ?array $paidOrder = null, ?array $user = null): array
{
    return [
        'id' => (int) ($collection['id'] ?? 0),
        'token' => (string) ($collection['token'] ?? ''),
        'title' => (string) ($collection['title'] ?? ''),
        'description' => (string) ($collection['description'] ?? ''),
        'imageUrl' => (string) ($collection['image_url'] ?? ''),
        'amount' => max(0, (int) ($collection['amount'] ?? 0)),
        'hasDiscountCodes' => payments_normalize_collection_discount_codes($collection['discount_codes'] ?? []) !== [],
        'status' => (string) ($collection['status'] ?? PAYMENTS_COLLECTION_STATUS_INACTIVE),
        'allowGuestPayments' => payments_api_collection_allows_guest($collection),
        'collectPayerName' => payments_api_collection_collects($collection, 'name'),
        'collectPayerPhone' => payments_api_collection_collects($collection, 'phone'),
        'collectPayerStudentNumber' => payments_api_collection_collects($collection, 'student_number'),
        'payerDefaults' => payments_api_collection_payer_defaults($user),
        'paymentGateways' => payments_api_checkout_gateways_payload(),
        'paid' => $paidOrder !== null,
        'paidOrder' => $paidOrder ? payments_order_public_result_payload($paidOrder, null) : null,
        'successMessage' => (string) ($collection['success_message'] ?? ''),
        'failureMessage' => (string) ($collection['failure_message'] ?? ''),
    ];
}

function payments_api_collection_discount_usage_count(array $orders, string $discountCode): int
{
    $normalizedCode = payments_normalize_discount_code_text($discountCode);
    if ($normalizedCode === '') {
        return 0;
    }

    $count = 0;
    foreach ($orders as $order) {
        if (!is_array($order) || (string) ($order['status'] ?? '') !== PAYMENTS_ORDER_STATUS_SUCCESS) {
            continue;
        }
        if (payments_normalize_discount_code_text((string) ($order['discount_code'] ?? '')) !== $normalizedCode) {
            continue;
        }
        $count++;
    }

    return $count;
}

function payments_api_collection_quote(
    array $collection,
    array $orders = [],
    string $discountCode = '',
    string $studentNumber = ''
): array {
    $subtotal = max(0, (int) ($collection['amount'] ?? 0));
    if ($subtotal <= 0) {
        throw new PaymentsApiException('مبلغ این لینک پرداخت معتبر نیست.', 422);
    }

    $normalizedCode = payments_normalize_discount_code_text($discountCode);
    $normalizedStudentNumber = dent_normalize_student_number($studentNumber);
    $discountAmount = 0;
    $discountLabel = '';
    $discountApplied = false;
    $discountValid = $normalizedCode === '';
    $maxUses = null;
    $usedCount = 0;

    if ($normalizedCode !== '') {
        $matchedCode = null;
        foreach (payments_normalize_collection_discount_codes($collection['discount_codes'] ?? []) as $code) {
            if ((string) ($code['code'] ?? '') === $normalizedCode) {
                $matchedCode = $code;
                break;
            }
        }

        if (!is_array($matchedCode)) {
            throw new PaymentsApiException('کد تخفیف معتبر یا فعالی برای این آزمون پیدا نشد.', 422);
        }
        if (!(bool) ($matchedCode['is_enabled'] ?? true)) {
            throw new PaymentsApiException('این کد تخفیف غیرفعال شده است.', 422);
        }

        $expiresAt = payments_timestamp_or_null((string) ($matchedCode['expires_at'] ?? ''));
        if ($expiresAt !== null && $expiresAt <= time()) {
            throw new PaymentsApiException('مهلت استفاده از این کد تخفیف به پایان رسیده است.', 422);
        }

        $restrictedStudentNumber = dent_normalize_student_number((string) ($matchedCode['student_number'] ?? ''));
        if ($restrictedStudentNumber !== '') {
            if ($normalizedStudentNumber === '') {
                throw new PaymentsApiException('این کد فقط برای یک شماره دانشجویی مشخص فعال است.', 422);
            }
            if ($restrictedStudentNumber !== $normalizedStudentNumber) {
                throw new PaymentsApiException('این کد برای شماره دانشجویی شما فعال نیست.', 422);
            }
        }

        $maxUses = payments_normalize_positive_int_nullable($matchedCode['max_uses'] ?? null, 1000000);
        $usedCount = $maxUses !== null
            ? payments_api_collection_discount_usage_count($orders, $normalizedCode)
            : 0;
        if ($maxUses !== null && $usedCount >= $maxUses) {
            throw new PaymentsApiException('ظرفیت استفاده از این کد تخفیف تکمیل شده است.', 409);
        }

        $discountValid = true;
        $discountApplied = true;
        $discountLabel = (string) ($matchedCode['label'] ?? '');
        if ((string) ($matchedCode['type'] ?? 'fixed') === 'percent') {
            $discountAmount = (int) floor($subtotal * ((int) ($matchedCode['amount'] ?? 0)) / 100);
        } else {
            $discountAmount = (int) ($matchedCode['amount'] ?? 0);
        }
    }

    $discountAmount = max(0, min($subtotal, $discountAmount));
    $amount = max(0, $subtotal - $discountAmount);

    return [
        'subtotal' => $subtotal,
        'discountCode' => $discountApplied ? $normalizedCode : '',
        'discountLabel' => $discountLabel,
        'discountAmount' => $discountAmount,
        'discountApplied' => $discountApplied,
        'discountValid' => $discountValid,
        'amount' => $amount,
        'maxUses' => $maxUses,
        'usedCount' => $discountApplied ? $usedCount : 0,
        'remainingUses' => $discountApplied && $maxUses !== null ? max(0, $maxUses - $usedCount) : null,
        'restrictedStudentNumber' => $discountApplied ? dent_normalize_student_number((string) $normalizedStudentNumber) : '',
    ];
}

function payments_api_parse_cart_items_input($raw): array
{
    $rows = [];
    if (is_array($raw)) {
        $rows = $raw;
    } else {
        $text = trim((string) $raw);
        if ($text !== '') {
            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                $rows = $decoded;
            }
        }
    }

    $items = [];
    $seen = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $slug = payments_clean_slug((string) ($row['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }
        $quantity = max(1, min(99, (int) dent_normalize_digits((string) ($row['quantity'] ?? 1))));
        if (isset($seen[$slug])) {
            $items[$seen[$slug]]['quantity'] = min(99, $items[$seen[$slug]]['quantity'] + $quantity);
            continue;
        }
        $seen[$slug] = count($items);
        $items[] = [
            'slug' => $slug,
            'quantity' => $quantity,
        ];
        if (count($items) >= 40) {
            break;
        }
    }

    return $items;
}

function payments_api_cart_quote(array $store, array $cartItems, string $discountCode = '', array $extraBySlug = []): array
{
    if ($cartItems === []) {
        throw new PaymentsApiException('سبد خرید خالی است.', 422);
    }

    $lines = [];
    $subtotal = 0;
    $discountAmount = 0;
    $amount = 0;
    $discountValid = $discountCode === '';
    $discountApplied = false;

    foreach ($cartItems as $cartItem) {
        $slug = payments_clean_slug((string) ($cartItem['slug'] ?? ''));
        $quantity = max(1, min(99, (int) ($cartItem['quantity'] ?? 1)));
        $itemIndex = payments_find_item_index_by_slug($store, $slug);
        if ($itemIndex < 0) {
            throw new PaymentsApiException('یکی از آیتم‌های سبد پیدا نشد.', 404);
        }

        $item = $store['items'][$itemIndex];
        $publicItem = payments_public_item_payload($item);
        $state = is_array($publicItem['state'] ?? null) ? $publicItem['state'] : ['key' => 'inactive'];
        if (!(bool) ($state['isPayable'] ?? false)) {
            throw new PaymentsApiException('آیتم «' . (string) ($item['title'] ?? $slug) . '» قابل پرداخت نیست.', 422);
        }

        $remaining = payments_item_remaining_capacity($item);
        if ($remaining !== null && $quantity > $remaining) {
            throw new PaymentsApiException('تعداد انتخاب‌شده برای «' . (string) ($item['title'] ?? $slug) . '» بیشتر از ظرفیت باقی‌مانده است.', 422);
        }

        $quote = payments_calculate_item_quote($item, $quantity, $discountCode);
        if ($discountCode !== '' && (bool) ($quote['discountValid'] ?? false)) {
            $discountValid = true;
        }
        if ((bool) ($quote['discountApplied'] ?? false)) {
            $discountApplied = true;
        }

        $lineSubtotal = max(0, (int) ($quote['subtotal'] ?? 0));
        $lineDiscount = max(0, (int) ($quote['discountAmount'] ?? 0));
        $lineAmount = max(0, (int) ($quote['amount'] ?? 0));
        $lineExtra = is_array($extraBySlug[$slug] ?? null) ? payments_normalize_extra_form_data($extraBySlug[$slug]) : [];

        $lines[] = [
            'itemId' => (int) ($item['id'] ?? 0),
            'slug' => $slug,
            'title' => (string) ($item['title'] ?? ''),
            'quantity' => (int) ($quote['quantity'] ?? $quantity),
            'unitPrice' => max(0, (int) ($quote['unitPrice'] ?? 0)),
            'subtotal' => $lineSubtotal,
            'discountCode' => (string) ($quote['discountCode'] ?? ''),
            'discountAmount' => $lineDiscount,
            'amount' => $lineAmount,
            'requiredFields' => is_array($publicItem['requiredFields'] ?? null) ? $publicItem['requiredFields'] : [],
            'extraFormData' => $lineExtra,
            'item' => $publicItem,
        ];
        $subtotal += $lineSubtotal;
        $discountAmount += $lineDiscount;
        $amount += $lineAmount;
    }

    if ($discountCode !== '' && !$discountValid) {
        throw new PaymentsApiException('کد تخفیف برای آیتم‌های این سبد معتبر یا فعال نیست.', 422);
    }
    if ($amount <= 0) {
        throw new PaymentsApiException('مبلغ سبد خرید معتبر نیست.', 422);
    }

    return [
        'lines' => $lines,
        'quantity' => array_sum(array_map(static function (array $line): int {
            return max(1, (int) ($line['quantity'] ?? 1));
        }, $lines)),
        'subtotal' => $subtotal,
        'discountCode' => $discountApplied ? strtoupper(preg_replace('/\s+/u', '', $discountCode) ?? '') : '',
        'discountAmount' => $discountAmount,
        'discountApplied' => $discountApplied,
        'discountValid' => $discountValid,
        'amount' => $amount,
    ];
}

function payments_api_cart_order_title(array $lines): string
{
    $count = count($lines);
    if ($count <= 0) {
        return 'سبد خرید';
    }
    if ($count === 1) {
        return (string) ($lines[0]['title'] ?? 'سبد خرید');
    }

    return 'سبد خرید (' . $count . ' آیتم)';
}

function payments_api_uploads_dir(): string
{
    return dent_storage_path('payments/uploads');
}

function payments_api_clean_upload_name(string $value): string
{
    $name = basename(str_replace('\\', '/', $value));
    if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{5,180}$/', $name) !== 1) {
        return '';
    }

    return $name;
}

function payments_api_image_mime_from_extension(string $name): string
{
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($extension === 'jpg' || $extension === 'jpeg') {
        return 'image/jpeg';
    }
    if ($extension === 'png') {
        return 'image/png';
    }
    if ($extension === 'webp') {
        return 'image/webp';
    }

    return 'application/octet-stream';
}

function payments_api_uploaded_image_url(string $name): string
{
    return '/api/payments_api.php?action=paymentImage&name=' . rawurlencode($name);
}

function payments_api_validate_uploaded_image_url(string $value): bool
{
    $clean = trim($value);
    if ($clean === '') {
        return false;
    }

    if (str_starts_with($clean, '/api/payments_api.php?action=paymentImage&name=')) {
        return true;
    }

    if (str_starts_with($clean, '/assets/images/buy/')) {
        return true;
    }

    return false;
}

function payments_api_store_uploaded_image(array $file): array
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        dent_error('آپلود تصویر انجام نشد. دوباره تصویر را انتخاب کن.', 422);
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    $size = max(0, (int) ($file['size'] ?? 0));
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        dent_error('فایل تصویر معتبر نیست.', 422);
    }

    $maxBytes = 5 * 1024 * 1024;
    if ($size <= 0 || $size > $maxBytes) {
        dent_error('حجم تصویر باید کمتر از ۵ مگابایت باشد.', 422);
    }

    $imageInfo = @getimagesize($tmpName);
    if (!is_array($imageInfo)) {
        dent_error('فایل انتخاب‌شده تصویر معتبر نیست.', 422);
    }

    $mime = strtolower((string) ($imageInfo['mime'] ?? ''));
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extensions[$mime])) {
        dent_error('فرمت تصویر باید jpg، png یا webp باشد.', 422);
    }

    $width = max(0, (int) ($imageInfo[0] ?? 0));
    $height = max(0, (int) ($imageInfo[1] ?? 0));
    if ($width < 120 || $height < 120) {
        dent_error('ابعاد تصویر برای نمایش کالا خیلی کوچک است.', 422);
    }

    dent_ensure_directory(payments_api_uploads_dir());
    try {
        $token = bin2hex(random_bytes(8));
    } catch (Throwable $error) {
        $token = substr(sha1((string) mt_rand() . '|' . microtime(true)), 0, 16);
    }

    $name = 'buy-' . date('Ymd-His') . '-' . $token . '.' . $extensions[$mime];
    $target = payments_api_uploads_dir() . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($tmpName, $target)) {
        dent_error('ذخیره تصویر در فضای پایدار پرداخت انجام نشد.', 500);
    }
    @chmod($target, 0644);

    return [
        'name' => $name,
        'url' => payments_api_uploaded_image_url($name),
        'mime' => $mime,
        'size' => $size,
        'width' => $width,
        'height' => $height,
    ];
}

$action = dent_request_action();

// Only auth_store writes the PHP session; every action below is a pure session
// reader. Release the session lock now so concurrent same-session requests are
// not serialized behind this (often heavy) request. $_SESSION stays readable.
dent_release_session_lock();

if ($action === 'paymentImage') {
    payments_api_require_method(['GET']);
    $name = payments_api_clean_upload_name((string) ($_GET['name'] ?? ''));
    if ($name === '') {
        dent_error('نام تصویر معتبر نیست.', 422);
    }

    $path = payments_api_uploads_dir() . DIRECTORY_SEPARATOR . $name;
    if (!is_file($path) || !is_readable($path)) {
        dent_error('تصویر پیدا نشد.', 404);
    }

    header('Content-Type: ' . payments_api_image_mime_from_extension($name));
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: public, max-age=31536000, immutable');
    readfile($path);
    exit;
}

if ($action === 'ownerUploadImage') {
    payments_api_require_method(['POST']);
    dent_require_owner();

    $file = $_FILES['image'] ?? null;
    if (!is_array($file)) {
        dent_error('تصویر کالا انتخاب نشده است.', 422);
    }

    $stored = payments_api_store_uploaded_image($file);
    dent_json_response([
        'success' => true,
        'image' => $stored,
        'message' => 'تصویر کالا با موفقیت آپلود شد.',
    ]);
}

if ($action === 'listPublicItems') {
    payments_api_require_method(['GET']);
    payments_api_require_public_user();
    $store = payments_read_store();

    $items = [];
    foreach ($store['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }

        if ((string) ($item['status'] ?? '') !== PAYMENTS_ITEM_STATUS_ACTIVE) {
            continue;
        }

        $items[] = payments_public_item_payload($item);
    }

    usort($items, static function (array $left, array $right): int {
        return strcmp((string) ($right['updatedAt'] ?? ''), (string) ($left['updatedAt'] ?? ''));
    });

    dent_json_response([
        'success' => true,
        'items' => $items,
    ]);
}

if ($action === 'publicItem') {
    payments_api_require_method(['GET']);
    payments_api_require_public_user();
    $slug = payments_clean_slug((string) ($_GET['slug'] ?? ''));
    if ($slug === '') {
        dent_error('شناسه آیتم سفارش معتبر نیست.', 422);
    }

    $store = payments_read_store();
    $itemIndex = payments_find_item_index_by_slug($store, $slug);
    if ($itemIndex < 0) {
        dent_error('آیتم سفارش موردنظر پیدا نشد.', 404);
    }

    $item = $store['items'][$itemIndex];
    if ((string) ($item['status'] ?? '') === PAYMENTS_ITEM_STATUS_DELETED) {
        dent_error('آیتم سفارش موردنظر پیدا نشد.', 404);
    }
    $payload = payments_public_item_payload($item);
    $payload['statusMessage'] = payments_api_item_status_message($payload['state']);
    $payload['paymentGateways'] = payments_api_checkout_gateways_payload();

    dent_json_response([
        'success' => true,
        'item' => $payload,
    ]);
}

if ($action === 'quoteOrder') {
    payments_api_require_method(['POST']);
    payments_api_require_public_user();

    $slug = payments_clean_slug((string) ($_POST['slug'] ?? ''));
    if ($slug === '') {
        dent_error('شناسه آیتم معتبر نیست.', 422);
    }

    $quantity = max(1, min(99, (int) dent_normalize_digits((string) ($_POST['quantity'] ?? '1'))));
    $discountCode = dent_clean_text((string) ($_POST['discountCode'] ?? ($_POST['discount_code'] ?? '')), 40);

    $store = payments_read_store();
    $itemIndex = payments_find_item_index_by_slug($store, $slug);
    if ($itemIndex < 0) {
        dent_error('آیتم موردنظر پیدا نشد.', 404);
    }

    $item = $store['items'][$itemIndex];
    $publicItem = payments_public_item_payload($item);
    $state = is_array($publicItem['state'] ?? null) ? $publicItem['state'] : ['key' => 'inactive'];
    if (!(bool) ($state['isPayable'] ?? false)) {
        dent_error(payments_api_item_status_message($state), 422);
    }

    $remaining = payments_item_remaining_capacity($item);
    if ($remaining !== null && $quantity > $remaining) {
        dent_error('تعداد انتخاب‌شده بیشتر از ظرفیت باقی‌مانده است.', 422);
    }

    $quote = payments_calculate_item_quote($item, $quantity, $discountCode);
    if ($discountCode !== '' && !(bool) ($quote['discountValid'] ?? false)) {
        dent_error('کد تخفیف معتبر یا فعال نیست.', 422);
    }

    dent_json_response([
        'success' => true,
        'quote' => $quote,
    ]);
}

if ($action === 'quoteCart') {
    payments_api_require_method(['POST']);
    payments_api_require_public_user();

    $cartItems = payments_api_parse_cart_items_input($_POST['items'] ?? ($_POST['cartItems'] ?? []));
    $discountCode = dent_clean_text((string) ($_POST['discountCode'] ?? ($_POST['discount_code'] ?? '')), 40);
    $store = payments_read_store();

    try {
        $quote = payments_api_cart_quote($store, $cartItems, $discountCode);
    } catch (PaymentsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode());
    }

    dent_json_response([
        'success' => true,
        'quote' => $quote,
        'paymentGateways' => payments_api_checkout_gateways_payload(),
    ]);
}

if ($action === 'publicCollection') {
    payments_api_require_method(['GET']);
    $user = payments_api_current_public_user();
    $token = payments_clean_collection_token((string) ($_GET['token'] ?? ''));
    if ($token === '') {
        dent_error('شناسه لینک پرداخت معتبر نیست.', 422);
    }

    $store = payments_read_store();
    $collectionIndex = payments_find_collection_index_by_token($store, $token);
    if ($collectionIndex < 0) {
        dent_error('لینک پرداخت پیدا نشد.', 404);
    }
    $collection = $store['collections'][$collectionIndex];
    if ((string) ($collection['status'] ?? '') === PAYMENTS_COLLECTION_STATUS_DELETED) {
        dent_error('لینک پرداخت پیدا نشد.', 404);
    }
    if ($user === null && !payments_api_collection_allows_guest($collection)) {
        dent_error('نیاز به ورود به حساب کاربری است.', 401, ['loggedOut' => true]);
    }

    $paidOrder = $user !== null
        ? payments_api_public_collection_order_for_user($store, (int) ($collection['id'] ?? 0), $user)
        : null;
    dent_json_response([
        'success' => true,
        'collection' => payments_api_public_collection_payload($collection, $paidOrder, $user),
    ]);
}

if ($action === 'quoteCollection') {
    payments_api_require_method(['POST']);
    $user = payments_api_current_public_user();

    $token = payments_clean_collection_token((string) ($_POST['token'] ?? ''));
    if ($token === '') {
        dent_error('شناسه لینک پرداخت معتبر نیست.', 422);
    }

    $store = payments_read_store();
    $collectionIndex = payments_find_collection_index_by_token($store, $token);
    if ($collectionIndex < 0 || !is_array($store['collections'][$collectionIndex] ?? null)) {
        dent_error('لینک پرداخت پیدا نشد.', 404);
    }

    $collection = $store['collections'][$collectionIndex];
    if ((string) ($collection['status'] ?? '') !== PAYMENTS_COLLECTION_STATUS_ACTIVE) {
        dent_error('این لینک پرداخت در حال حاضر فعال نیست.', 422);
    }
    if ($user === null && !payments_api_collection_allows_guest($collection)) {
        dent_error('نیاز به ورود به حساب کاربری است.', 401, ['loggedOut' => true]);
    }

    $payerDefaults = payments_api_collection_payer_defaults($user);
    $payerStudentNumber = dent_normalize_student_number((string) ($_POST['payerStudentNumber'] ?? ''));
    if ($payerStudentNumber === '') {
        $payerStudentNumber = dent_normalize_student_number((string) ($payerDefaults['studentNumber'] ?? ''));
    }

    try {
        $quote = payments_api_collection_quote(
            $collection,
            payments_api_collection_orders($store, (int) ($collection['id'] ?? 0)),
            (string) ($_POST['discountCode'] ?? ($_POST['discount_code'] ?? '')),
            $payerStudentNumber
        );
    } catch (PaymentsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode());
    }

    dent_json_response([
        'success' => true,
        'quote' => $quote,
    ]);
}

if ($action === 'createCollectionOrder') {
    payments_api_require_method(['POST']);
    $user = payments_api_current_public_user();

    $token = payments_clean_collection_token((string) ($_POST['token'] ?? ''));
    if ($token === '') {
        dent_error('شناسه لینک پرداخت معتبر نیست.', 422);
    }

    $previewStore = payments_read_store();
    $previewCollectionIndex = payments_find_collection_index_by_token($previewStore, $token);
    if ($previewCollectionIndex < 0 || !is_array($previewStore['collections'][$previewCollectionIndex] ?? null)) {
        dent_error('لینک پرداخت پیدا نشد.', 404);
    }
    $previewCollection = $previewStore['collections'][$previewCollectionIndex];
    if ((string) ($previewCollection['status'] ?? '') === PAYMENTS_COLLECTION_STATUS_DELETED) {
        dent_error('لینک پرداخت پیدا نشد.', 404);
    }
    if ($user === null && !payments_api_collection_allows_guest($previewCollection)) {
        dent_error('نیاز به ورود به حساب کاربری است.', 401, ['loggedOut' => true]);
    }

    $payerDefaults = payments_api_collection_payer_defaults($user);
    $collectPayerName = payments_api_collection_collects($previewCollection, 'name');
    $collectPayerPhone = payments_api_collection_collects($previewCollection, 'phone');
    $collectPayerStudentNumber = payments_api_collection_collects($previewCollection, 'student_number');

    $payerName = dent_clean_text((string) ($_POST['payerName'] ?? ''), 120);
    if ($payerName === '' && (!$collectPayerName || $user !== null)) {
        $payerName = dent_clean_text((string) ($payerDefaults['name'] ?? ''), 120);
    }
    if ($collectPayerName && $payerName === '') {
        dent_error('نام پرداخت‌کننده الزامی است.', 422);
    }

    $payerPhone = payments_normalize_phone((string) ($_POST['payerPhone'] ?? ''));
    if ($payerPhone === '' && (!$collectPayerPhone || $user !== null)) {
        $payerPhone = payments_normalize_phone((string) ($payerDefaults['phone'] ?? ''));
    }
    if ($collectPayerPhone && ($payerPhone === '' || strlen($payerPhone) < 10 || strlen($payerPhone) > 14)) {
        dent_error('شماره موبایل پرداخت‌کننده معتبر نیست.', 422);
    }
    if (!$collectPayerPhone && $payerPhone !== '' && (strlen($payerPhone) < 10 || strlen($payerPhone) > 14)) {
        dent_error('شماره موبایل پرداخت‌کننده معتبر نیست.', 422);
    }

    $payerStudentNumber = dent_normalize_student_number((string) ($_POST['payerStudentNumber'] ?? ''));
    if ($payerStudentNumber === '' && (!$collectPayerStudentNumber || $user !== null)) {
        $payerStudentNumber = dent_normalize_student_number((string) ($payerDefaults['studentNumber'] ?? ''));
    }
    if ($collectPayerStudentNumber && $payerStudentNumber === '') {
        dent_error('شماره دانشجویی پرداخت‌کننده الزامی است.', 422);
    }

    $discountCode = payments_normalize_discount_code_text((string) ($_POST['discountCode'] ?? ($_POST['discount_code'] ?? '')));
    $enabledGateways = payments_gateway_enabled_checkout_keys(false);
    if ($enabledGateways === [] && $discountCode === '') {
        dent_error('هیچ درگاه پرداخت فعالی برای این پرداخت وجود ندارد.', 503);
    }

    $requestedGateway = payments_gateway_clean((string) ($_POST['gateway'] ?? ''));
    $studentNumber = dent_normalize_student_number((string) ($payerDefaults['studentNumber'] ?? ''));
    $defaultGateway = payments_gateway_default_enabled_checkout(false);
    $returnPath = payments_api_clean_return_path(
        $_POST['returnPath'] ?? ($_POST['return_path'] ?? ''),
        '/payments/pay/?token=' . rawurlencode((string) ($previewCollection['token'] ?? ''))
    );

    try {
        $created = payments_with_store_lock(static function (array &$store) use (
            $token,
            $payerName,
            $payerPhone,
            $requestedGateway,
            $defaultGateway,
            $enabledGateways,
            $studentNumber,
            $payerStudentNumber,
            $discountCode,
            $user,
            $returnPath
        ): array {
            $collectionIndex = payments_find_collection_index_by_token($store, $token);
            if ($collectionIndex < 0) {
                throw new PaymentsApiException('لینک پرداخت پیدا نشد.', 404);
            }
            $collection = $store['collections'][$collectionIndex];
            if ((string) ($collection['status'] ?? '') !== PAYMENTS_COLLECTION_STATUS_ACTIVE) {
                throw new PaymentsApiException('این لینک پرداخت در حال حاضر فعال نیست.', 422);
            }
            if ($user === null && !payments_api_collection_allows_guest($collection)) {
                throw new PaymentsApiException('نیاز به ورود به حساب کاربری است.', 401);
            }

            $dedupeStudentNumber = $studentNumber !== '' ? $studentNumber : $payerStudentNumber;
            if ($dedupeStudentNumber !== '') {
                $alreadyPaid = payments_api_public_collection_order_for_user($store, (int) ($collection['id'] ?? 0), [
                    'studentNumber' => $dedupeStudentNumber,
                ]);
                if ($alreadyPaid !== null) {
                    throw new PaymentsApiException('پرداخت شما برای این هزینه قبلا تایید شده است.', 409);
                }
            }

            $quote = payments_api_collection_quote(
                $collection,
                payments_api_collection_orders($store, (int) ($collection['id'] ?? 0)),
                $discountCode,
                $dedupeStudentNumber
            );
            $amount = max(0, (int) ($quote['amount'] ?? 0));
            $gateway = $requestedGateway !== '' ? $requestedGateway : payments_gateway_clean((string) ($collection['gateway'] ?? ''));
            if ($gateway === '') {
                $gateway = $defaultGateway;
            }
            if ($amount > 0 && ($gateway === '' || !in_array($gateway, $enabledGateways, true))) {
                throw new PaymentsApiException('درگاه پرداخت انتخاب‌شده فعال نیست. لطفا گزینه دیگری را انتخاب کنید.', 422);
            }

            $now = dent_iso_now();
            $title = (string) ($collection['title'] ?? 'پرداخت هزینه');
            if ($amount <= 0) {
                $gateway = 'discount';
            }
            $order = [
                'id' => payments_next_order_id($store),
                'item_id' => 0,
                'user_id' => $studentNumber,
                'payer_name' => $payerName,
                'payer_phone' => $payerPhone,
                'payer_student_number' => $payerStudentNumber,
                'extra_form_data' => [
                    '_source' => 'collection',
                    '_payer_source' => $user === null ? 'guest' : 'account',
                    'collection_id' => (int) ($collection['id'] ?? 0),
                    'collection_token' => (string) ($collection['token'] ?? ''),
                    'collection_title' => $title,
                    'discount_label' => (string) ($quote['discountLabel'] ?? ''),
                    '_return_path' => $returnPath,
                ],
                'cart_items' => [[
                    'item_id' => 0,
                    'slug' => '',
                    'title' => $title,
                    'quantity' => 1,
                    'unit_price' => (int) ($quote['subtotal'] ?? 0),
                    'subtotal' => (int) ($quote['subtotal'] ?? 0),
                    'discount_code' => (string) ($quote['discountCode'] ?? ''),
                    'discount_amount' => (int) ($quote['discountAmount'] ?? 0),
                    'amount' => $amount,
                    'extra_form_data' => [],
                ]],
                'quantity' => 1,
                'unit_price' => (int) ($quote['subtotal'] ?? 0),
                'subtotal' => (int) ($quote['subtotal'] ?? 0),
                'discount_code' => (string) ($quote['discountCode'] ?? ''),
                'discount_amount' => (int) ($quote['discountAmount'] ?? 0),
                'amount' => $amount,
                'gateway' => $gateway,
                'authority' => '',
                'ref_id' => '',
                'status' => $amount > 0 ? PAYMENTS_ORDER_STATUS_PENDING : PAYMENTS_ORDER_STATUS_SUCCESS,
                'gateway_response_snapshot' => [
                    'created' => ['at' => $now, 'type' => $amount > 0 ? 'collection' : 'collection-discount'],
                ],
                'created_at' => $now,
                'paid_at' => $amount > 0 ? '' : $now,
                'verified_at' => $amount > 0 ? '' : $now,
                'public_token' => payments_random_token(),
            ];
            $store['orders'][] = $order;
            $notification = null;
            if ((string) ($order['status'] ?? '') === PAYMENTS_ORDER_STATUS_SUCCESS) {
                $notification = payments_api_append_order_notification($store, $order, null, true);
            }

            return [
                'order' => $order,
                'collection' => $collection,
                'notification' => $notification,
            ];
        });
    } catch (PaymentsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode());
    }

    $order = $created['order'];
    $collection = $created['collection'];
    $orderToken = (string) ($order['public_token'] ?? '');
    $resultUrl = $returnPath . (str_contains($returnPath, '?') ? '&' : '?') . 'paymentOrderToken=' . rawurlencode($orderToken);
    if (is_array($created['notification'] ?? null)) {
        payments_api_trigger_notification_hook(
            $created['notification'],
            $order,
            null
        );
    }
    if ((string) ($order['status'] ?? '') === PAYMENTS_ORDER_STATUS_SUCCESS) {
        dent_json_response([
            'success' => true,
            'orderToken' => $orderToken,
            'redirectUrl' => $resultUrl,
            'resultUrl' => $resultUrl,
            'order' => payments_order_public_result_payload($order, null),
        ]);
    }
    $callbackUrl = payments_api_absolute_url(
        '/api/payments_api.php?action=callback&orderToken=' . rawurlencode($orderToken)
    );
    $syntheticItem = [
        'id' => 0,
        'title' => (string) ($collection['title'] ?? 'پرداخت هزینه'),
    ];

    $startResult = payments_gateway_start_payment((string) ($order['gateway'] ?? ''), $syntheticItem, $order, [
        'callbackUrl' => $callbackUrl,
        'description' => 'پرداخت ' . (string) ($collection['title'] ?? 'هزینه'),
        'mobile' => $payerPhone,
        'orderId' => $orderToken,
    ]);

    payments_log_gateway_event('start-request', [
        'orderId' => (int) ($order['id'] ?? 0),
        'gateway' => (string) ($order['gateway'] ?? ''),
        'result' => $startResult,
    ]);

    if (!(bool) ($startResult['success'] ?? false)) {
        payments_with_store_lock(static function (array &$store) use ($order, $startResult): void {
            $orderIndex = payments_find_order_index_by_id($store, (int) ($order['id'] ?? 0));
            if ($orderIndex < 0) {
                return;
            }
            $current = $store['orders'][$orderIndex];
            if ((string) ($current['status'] ?? '') === PAYMENTS_ORDER_STATUS_PENDING) {
                $current['status'] = PAYMENTS_ORDER_STATUS_FAILED;
            }
            $snapshot = is_array($current['gateway_response_snapshot'] ?? null) ? $current['gateway_response_snapshot'] : [];
            $snapshot['start'] = $startResult;
            $current['gateway_response_snapshot'] = $snapshot;
            $store['orders'][$orderIndex] = $current;
            payments_api_append_order_notification($store, $current, null, false);
        });

        dent_error((string) ($startResult['error'] ?? 'ایجاد درخواست درگاه پرداخت انجام نشد.'), 503, [
            'orderToken' => $orderToken,
        ]);
    }

    $authority = dent_clean_text((string) ($startResult['authority'] ?? ''), 120);
    $redirectUrl = dent_clean_text((string) ($startResult['redirectUrl'] ?? ''), 900);
    if ($redirectUrl === '') {
        dent_error('لینک انتقال به درگاه پرداخت دریافت نشد.', 500);
    }

    payments_with_store_lock(static function (array &$store) use ($order, $authority, $startResult): void {
        $orderIndex = payments_find_order_index_by_id($store, (int) ($order['id'] ?? 0));
        if ($orderIndex < 0) {
            return;
        }
        $current = $store['orders'][$orderIndex];
        $current['authority'] = $authority;
        $snapshot = is_array($current['gateway_response_snapshot'] ?? null) ? $current['gateway_response_snapshot'] : [];
        $snapshot['start'] = $startResult;
        $current['gateway_response_snapshot'] = $snapshot;
        $store['orders'][$orderIndex] = $current;
    });

    dent_json_response([
        'success' => true,
        'orderToken' => $orderToken,
        'redirectUrl' => $redirectUrl,
        'resultUrl' => $resultUrl,
    ]);
}

if ($action === 'createCartOrder') {
    payments_api_require_method(['POST']);
    $requiredUser = payments_api_require_public_user();

    $cartItems = payments_api_parse_cart_items_input($_POST['items'] ?? ($_POST['cartItems'] ?? []));
    if ($cartItems === []) {
        dent_error('سبد خرید خالی است.', 422);
    }

    // Identity is taken from the logged-in account (login is required above).
    // Posted values are accepted only as an override and never as the source of truth.
    $payerDefaults = payments_api_collection_payer_defaults($requiredUser);

    $payerName = dent_clean_text((string) ($_POST['payerName'] ?? ''), 120);
    if ($payerName === '') {
        $payerName = dent_clean_text((string) ($payerDefaults['name'] ?? ''), 120);
    }
    if ($payerName === '') {
        dent_error('نام شما در حساب کاربری ثبت نشده است. لطفا ابتدا پروفایل خود را کامل کنید.', 422);
    }

    $payerPhone = payments_normalize_phone((string) ($_POST['payerPhone'] ?? ''));
    if ($payerPhone === '') {
        $payerPhone = payments_normalize_phone((string) ($payerDefaults['phone'] ?? ''));
    }
    if ($payerPhone !== '' && (strlen($payerPhone) < 10 || strlen($payerPhone) > 14)) {
        dent_error('شماره موبایل ثبت‌شده در حساب شما معتبر نیست.', 422);
    }

    $payerStudentNumber = dent_normalize_student_number((string) ($_POST['payerStudentNumber'] ?? ''));
    if ($payerStudentNumber === '') {
        $payerStudentNumber = dent_normalize_student_number((string) ($payerDefaults['studentNumber'] ?? ''));
    }
    $extraFormData = payments_api_parse_extra_form_data($_POST['extraFormData'] ?? ($_POST['extra_form_data'] ?? ''));
    $extraBySlug = [];
    $rawLineExtras = $_POST['lineExtraFormData'] ?? ($_POST['line_extra_form_data'] ?? '');
    $decodedLineExtras = is_array($rawLineExtras) ? $rawLineExtras : json_decode(trim((string) $rawLineExtras), true);
    if (is_array($decodedLineExtras)) {
        foreach ($decodedLineExtras as $slug => $fields) {
            $cleanSlug = payments_clean_slug((string) $slug);
            if ($cleanSlug === '' || !is_array($fields)) {
                continue;
            }
            $extraBySlug[$cleanSlug] = payments_normalize_extra_form_data($fields);
        }
    }

    $discountCode = dent_clean_text((string) ($_POST['discountCode'] ?? ($_POST['discount_code'] ?? '')), 40);
    $enabledGateways = payments_gateway_enabled_checkout_keys(false);
    if ($enabledGateways === []) {
        dent_error('هیچ درگاه پرداخت فعالی برای ثبت سفارش وجود ندارد.', 503);
    }

    $requestedGateway = payments_gateway_clean((string) ($_POST['gateway'] ?? ''));
    $gateway = $requestedGateway !== '' ? $requestedGateway : payments_gateway_default_enabled_checkout(false);
    if ($gateway === '' || !in_array($gateway, $enabledGateways, true)) {
        dent_error('درگاه پرداخت انتخاب‌شده فعال نیست. لطفا گزینه دیگری را انتخاب کنید.', 422);
    }

    $user = $requiredUser;
    $userId = $user ? dent_normalize_student_number((string) ($user['studentNumber'] ?? '')) : '';
    if ($payerStudentNumber === '' && $userId !== '') {
        $payerStudentNumber = $userId;
    }

    try {
        $created = payments_with_store_lock(static function (array &$store) use (
            $cartItems,
            $discountCode,
            $extraBySlug,
            $extraFormData,
            $payerName,
            $payerPhone,
            $payerStudentNumber,
            $gateway,
            $userId
        ): array {
            $quote = payments_api_cart_quote($store, $cartItems, $discountCode, $extraBySlug);

            foreach ($quote['lines'] as $line) {
                $slug = payments_clean_slug((string) ($line['slug'] ?? ''));
                $lineExtra = is_array($extraBySlug[$slug] ?? null) ? $extraBySlug[$slug] : [];
                $requiredFields = is_array($line['requiredFields'] ?? null) ? $line['requiredFields'] : [];
                foreach ($requiredFields as $field) {
                    if (!is_array($field)) {
                        continue;
                    }
                    $name = (string) ($field['name'] ?? '');
                    if ($name === '') {
                        continue;
                    }
                    $required = (bool) ($field['required'] ?? true);
                    $value = dent_clean_text((string) ($lineExtra[$name] ?? ''), (int) ($field['maxLength'] ?? 140));
                    if ($required && $value === '') {
                        $label = dent_clean_text((string) ($field['label'] ?? $name), 80);
                        throw new PaymentsApiException('فیلد «' . $label . '» برای «' . (string) ($line['title'] ?? 'آیتم') . '» الزامی است.', 422);
                    }
                    $extraBySlug[$slug][$name] = $value;
                }
            }

            $cartOrderItems = [];
            foreach ($quote['lines'] as $line) {
                $slug = payments_clean_slug((string) ($line['slug'] ?? ''));
                $cartOrderItems[] = [
                    'item_id' => (int) ($line['itemId'] ?? 0),
                    'slug' => $slug,
                    'title' => (string) ($line['title'] ?? ''),
                    'quantity' => (int) ($line['quantity'] ?? 1),
                    'unit_price' => (int) ($line['unitPrice'] ?? 0),
                    'subtotal' => (int) ($line['subtotal'] ?? 0),
                    'discount_code' => (string) ($line['discountCode'] ?? ''),
                    'discount_amount' => (int) ($line['discountAmount'] ?? 0),
                    'amount' => (int) ($line['amount'] ?? 0),
                    'extra_form_data' => is_array($extraBySlug[$slug] ?? null) ? $extraBySlug[$slug] : [],
                ];
            }

            $now = dent_iso_now();
            $order = [
                'id' => payments_next_order_id($store),
                'item_id' => count($cartOrderItems) === 1 ? (int) ($cartOrderItems[0]['item_id'] ?? 0) : 0,
                'user_id' => $userId,
                'payer_name' => $payerName,
                'payer_phone' => $payerPhone,
                'payer_student_number' => $payerStudentNumber,
                'extra_form_data' => $extraFormData,
                'cart_items' => $cartOrderItems,
                'quantity' => max(1, (int) ($quote['quantity'] ?? 1)),
                'unit_price' => count($cartOrderItems) === 1 ? (int) ($cartOrderItems[0]['unit_price'] ?? 0) : 0,
                'subtotal' => (int) ($quote['subtotal'] ?? 0),
                'discount_code' => (string) ($quote['discountCode'] ?? ''),
                'discount_amount' => (int) ($quote['discountAmount'] ?? 0),
                'amount' => (int) ($quote['amount'] ?? 0),
                'gateway' => $gateway,
                'authority' => '',
                'ref_id' => '',
                'status' => PAYMENTS_ORDER_STATUS_PENDING,
                'gateway_response_snapshot' => [
                    'created' => ['at' => $now, 'type' => 'cart'],
                ],
                'created_at' => $now,
                'paid_at' => '',
                'verified_at' => '',
                'public_token' => payments_random_token(),
            ];

            $store['orders'][] = $order;
            return [
                'order' => $order,
                'quote' => $quote,
            ];
        });
    } catch (PaymentsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode());
    }

    if (!is_array($created['order'] ?? null)) {
        dent_error('ایجاد سفارش سبد خرید انجام نشد.', 500);
    }

    $order = $created['order'];
    $quote = is_array($created['quote'] ?? null) ? $created['quote'] : ['lines' => []];
    $orderToken = (string) ($order['public_token'] ?? '');
    $callbackUrl = payments_api_absolute_url(
        '/api/payments_api.php?action=callback&orderToken=' . rawurlencode($orderToken)
    );
    $syntheticItem = [
        'id' => 0,
        'title' => payments_api_cart_order_title(is_array($quote['lines'] ?? null) ? $quote['lines'] : []),
    ];

    $startResult = payments_gateway_start_payment((string) ($order['gateway'] ?? ''), $syntheticItem, $order, [
        'callbackUrl' => $callbackUrl,
        'description' => 'پرداخت ' . (string) ($syntheticItem['title'] ?? 'سبد خرید'),
        'mobile' => $payerPhone,
        'orderId' => (string) ($order['public_token'] ?? ''),
    ]);

    payments_log_gateway_event('start-request', [
        'orderId' => (int) ($order['id'] ?? 0),
        'gateway' => (string) ($order['gateway'] ?? ''),
        'result' => $startResult,
    ]);

    if (!(bool) ($startResult['success'] ?? false)) {
        payments_with_store_lock(static function (array &$store) use ($order, $startResult): void {
            $orderIndex = payments_find_order_index_by_id($store, (int) ($order['id'] ?? 0));
            if ($orderIndex < 0) {
                return;
            }
            $current = $store['orders'][$orderIndex];
            if ((string) ($current['status'] ?? '') === PAYMENTS_ORDER_STATUS_PENDING) {
                $current['status'] = PAYMENTS_ORDER_STATUS_FAILED;
            }
            $snapshot = is_array($current['gateway_response_snapshot'] ?? null) ? $current['gateway_response_snapshot'] : [];
            $snapshot['start'] = $startResult;
            $current['gateway_response_snapshot'] = $snapshot;
            $store['orders'][$orderIndex] = $current;
            payments_api_append_order_notification($store, $current, null, false);
        });

        dent_error((string) ($startResult['error'] ?? 'ایجاد درخواست درگاه پرداخت انجام نشد.'), 503, [
            'orderToken' => $orderToken,
        ]);
    }

    $authority = dent_clean_text((string) ($startResult['authority'] ?? ''), 120);
    $redirectUrl = dent_clean_text((string) ($startResult['redirectUrl'] ?? ''), 900);
    if ($redirectUrl === '') {
        dent_error('لینک انتقال به درگاه پرداخت دریافت نشد.', 500);
    }

    payments_with_store_lock(static function (array &$store) use ($order, $authority, $startResult): void {
        $orderIndex = payments_find_order_index_by_id($store, (int) ($order['id'] ?? 0));
        if ($orderIndex < 0) {
            return;
        }
        $current = $store['orders'][$orderIndex];
        $current['authority'] = $authority;
        $snapshot = is_array($current['gateway_response_snapshot'] ?? null) ? $current['gateway_response_snapshot'] : [];
        $snapshot['start'] = $startResult;
        $current['gateway_response_snapshot'] = $snapshot;
        $store['orders'][$orderIndex] = $current;
    });

    dent_json_response([
        'success' => true,
        'orderToken' => $orderToken,
        'redirectUrl' => $redirectUrl,
        'resultUrl' => PAYMENTS_PUBLIC_RESULT_PATH . '?orderToken=' . rawurlencode($orderToken),
    ]);
}

if ($action === 'createOrder') {
    payments_api_require_method(['POST']);
    $requiredUser = payments_api_require_public_user();

    $slug = payments_clean_slug((string) ($_POST['slug'] ?? ''));
    if ($slug === '') {
        dent_error('شناسه آیتم سفارش معتبر نیست.', 422);
    }

    $payerName = dent_clean_text((string) ($_POST['payerName'] ?? ''), 120);
    if ($payerName === '') {
        dent_error('نام پرداخت‌کننده الزامی است.', 422);
    }

    $payerPhone = payments_normalize_phone((string) ($_POST['payerPhone'] ?? ''));
    if ($payerPhone === '' || strlen($payerPhone) < 10 || strlen($payerPhone) > 14) {
        dent_error('شماره موبایل پرداخت‌کننده معتبر نیست.', 422);
    }

    $payerStudentNumber = dent_normalize_student_number((string) ($_POST['payerStudentNumber'] ?? ''));
    $extraFormData = payments_api_parse_extra_form_data($_POST['extraFormData'] ?? ($_POST['extra_form_data'] ?? ''));
    $quantity = max(1, min(99, (int) dent_normalize_digits((string) ($_POST['quantity'] ?? '1'))));
    $discountCode = dent_clean_text((string) ($_POST['discountCode'] ?? ($_POST['discount_code'] ?? '')), 40);

    $enabledGateways = payments_gateway_enabled_checkout_keys(false);
    if ($enabledGateways === []) {
        dent_error('هیچ درگاه پرداخت فعالی برای ثبت سفارش وجود ندارد.', 503);
    }

    $requestedGateway = payments_gateway_clean((string) ($_POST['gateway'] ?? ''));
    $gateway = $requestedGateway !== '' ? $requestedGateway : payments_gateway_default_enabled_checkout(false);
    if ($gateway === '' || !in_array($gateway, $enabledGateways, true)) {
        dent_error('درگاه پرداخت انتخاب‌شده فعال نیست. لطفا گزینه دیگری را انتخاب کنید.', 422);
    }
    $user = $requiredUser;
    $userId = $user ? dent_normalize_student_number((string) ($user['studentNumber'] ?? '')) : '';
    if ($payerStudentNumber === '' && $userId !== '') {
        $payerStudentNumber = $userId;
    }

    try {
        $created = payments_with_store_lock(static function (array &$store) use (
            $slug,
            $payerName,
            $payerPhone,
            $payerStudentNumber,
            $extraFormData,
            $gateway,
            $quantity,
            $discountCode,
            $userId
        ): array {
            $itemIndex = payments_find_item_index_by_slug($store, $slug);
            if ($itemIndex < 0) {
                throw new PaymentsApiException('آیتم سفارش موردنظر پیدا نشد.', 404);
            }

            $item = $store['items'][$itemIndex];
            $publicItem = payments_public_item_payload($item);
            $state = is_array($publicItem['state'] ?? null) ? $publicItem['state'] : ['key' => 'inactive'];
            if (!(bool) ($state['isPayable'] ?? false)) {
                throw new PaymentsApiException(payments_api_item_status_message($state), 422);
            }

            $remaining = payments_item_remaining_capacity($item);
            if ($remaining !== null && $quantity > $remaining) {
                throw new PaymentsApiException('تعداد انتخاب‌شده بیشتر از ظرفیت باقی‌مانده است.', 422);
            }

            $requiredFields = is_array($item['required_fields'] ?? null) ? $item['required_fields'] : [];
            foreach ($requiredFields as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $name = (string) ($field['name'] ?? '');
                if ($name === '') {
                    continue;
                }

                $required = (bool) ($field['required'] ?? true);
                $value = dent_clean_text((string) ($extraFormData[$name] ?? ''), (int) ($field['maxLength'] ?? 140));
                if ($required && $value === '') {
                    $label = dent_clean_text((string) ($field['label'] ?? $name), 80);
                    throw new PaymentsApiException('فیلد «' . $label . '» الزامی است.', 422);
                }
                $extraFormData[$name] = $value;
            }

            $quote = payments_calculate_item_quote($item, $quantity, $discountCode);
            if ($discountCode !== '' && !(bool) ($quote['discountValid'] ?? false)) {
                throw new PaymentsApiException('کد تخفیف معتبر یا فعال نیست.', 422);
            }
            $amount = max(0, (int) ($quote['amount'] ?? 0));
            if ($amount <= 0) {
                throw new PaymentsApiException('مبلغ آیتم سفارش معتبر نیست.', 422);
            }

            $now = dent_iso_now();
            $order = [
                'id' => payments_next_order_id($store),
                'item_id' => (int) ($item['id'] ?? 0),
                'user_id' => $userId,
                'payer_name' => $payerName,
                'payer_phone' => $payerPhone,
                'payer_student_number' => $payerStudentNumber,
                'extra_form_data' => $extraFormData,
                'quantity' => (int) ($quote['quantity'] ?? 1),
                'unit_price' => (int) ($quote['unitPrice'] ?? 0),
                'subtotal' => (int) ($quote['subtotal'] ?? 0),
                'discount_code' => (string) ($quote['discountCode'] ?? ''),
                'discount_amount' => (int) ($quote['discountAmount'] ?? 0),
                'amount' => $amount,
                'gateway' => $gateway,
                'authority' => '',
                'ref_id' => '',
                'status' => PAYMENTS_ORDER_STATUS_PENDING,
                'gateway_response_snapshot' => [
                    'created' => ['at' => $now],
                ],
                'created_at' => $now,
                'paid_at' => '',
                'verified_at' => '',
                'public_token' => payments_random_token(),
            ];

            $store['orders'][] = $order;
            return [
                'order' => $order,
                'item' => $item,
            ];
        });
    } catch (PaymentsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode());
    }

    if (!is_array($created['order'] ?? null) || !is_array($created['item'] ?? null)) {
        dent_error('ایجاد سفارش پرداخت انجام نشد.', 500);
    }

    $order = $created['order'];
    $item = $created['item'];
    $orderToken = (string) ($order['public_token'] ?? '');
    $callbackUrl = payments_api_absolute_url(
        '/api/payments_api.php?action=callback&orderToken=' . rawurlencode($orderToken)
    );

    $startResult = payments_gateway_start_payment((string) ($order['gateway'] ?? ''), $item, $order, [
        'callbackUrl' => $callbackUrl,
        'description' => 'ثبت سفارش ' . (string) ($item['title'] ?? 'آیتم مشخص'),
        'mobile' => $payerPhone,
        'orderId' => (string) ($order['public_token'] ?? ''),
    ]);

    payments_log_gateway_event('start-request', [
        'orderId' => (int) ($order['id'] ?? 0),
        'gateway' => (string) ($order['gateway'] ?? ''),
        'result' => $startResult,
    ]);

    if (!(bool) ($startResult['success'] ?? false)) {
        $updated = payments_with_store_lock(static function (array &$store) use ($order, $startResult): array {
            $orderIndex = payments_find_order_index_by_id($store, (int) ($order['id'] ?? 0));
            if ($orderIndex < 0) {
                return [];
            }

            $current = $store['orders'][$orderIndex];
            if ((string) ($current['status'] ?? '') === PAYMENTS_ORDER_STATUS_PENDING) {
                $current['status'] = PAYMENTS_ORDER_STATUS_FAILED;
            }
            $snapshot = is_array($current['gateway_response_snapshot'] ?? null) ? $current['gateway_response_snapshot'] : [];
            $snapshot['start'] = $startResult;
            $current['gateway_response_snapshot'] = $snapshot;
            $store['orders'][$orderIndex] = $current;

            $item = null;
            $itemIndex = payments_find_item_index_by_id($store, (int) ($current['item_id'] ?? 0));
            if ($itemIndex >= 0) {
                $item = $store['items'][$itemIndex];
            }

            $notification = payments_api_append_order_notification($store, $current, $item, false);
            return [
                'order' => $current,
                'item' => $item,
                'notification' => $notification,
            ];
        });

        if (is_array($updated['notification'] ?? null) && is_array($updated['order'] ?? null)) {
            payments_api_trigger_notification_hook(
                $updated['notification'],
                $updated['order'],
                is_array($updated['item'] ?? null) ? $updated['item'] : null
            );
        }

        dent_error((string) ($startResult['error'] ?? 'ایجاد درخواست درگاه پرداخت انجام نشد.'), 503, [
            'orderToken' => $orderToken,
        ]);
    }

    $authority = dent_clean_text((string) ($startResult['authority'] ?? ''), 120);
    $redirectUrl = dent_clean_text((string) ($startResult['redirectUrl'] ?? ''), 900);
    if ($redirectUrl === '') {
        dent_error('لینک انتقال به درگاه پرداخت دریافت نشد.', 500);
    }

    payments_with_store_lock(static function (array &$store) use ($order, $authority, $startResult): void {
        $orderIndex = payments_find_order_index_by_id($store, (int) ($order['id'] ?? 0));
        if ($orderIndex < 0) {
            return;
        }

        $current = $store['orders'][$orderIndex];
        $current['authority'] = $authority;
        $snapshot = is_array($current['gateway_response_snapshot'] ?? null) ? $current['gateway_response_snapshot'] : [];
        $snapshot['start'] = $startResult;
        $current['gateway_response_snapshot'] = $snapshot;
        $store['orders'][$orderIndex] = $current;
    });

    dent_json_response([
        'success' => true,
        'orderToken' => $orderToken,
        'redirectUrl' => $redirectUrl,
        'resultUrl' => PAYMENTS_PUBLIC_RESULT_PATH . '?orderToken=' . rawurlencode($orderToken),
    ]);
}

if ($action === 'callback') {
    payments_api_require_method(['GET', 'POST']);

    $orderToken = dent_clean_text((string) ($_GET['orderToken'] ?? ($_POST['orderToken'] ?? '')), 120);
    if ($orderToken === '') {
        dent_error('شناسه سفارش callback معتبر نیست.', 422);
    }

    $authority = dent_clean_text((string) ($_GET['Authority'] ?? ($_GET['authority'] ?? ($_POST['Authority'] ?? ($_POST['authority'] ?? '')))), 120);
    $gatewayStatus = dent_clean_text((string) ($_GET['Status'] ?? ($_GET['status'] ?? ($_POST['Status'] ?? ($_POST['status'] ?? '')))), 40);
    $zibalTrackId = dent_clean_text((string) ($_GET['trackId'] ?? ($_POST['trackId'] ?? '')), 120);
    $zibalSuccess = dent_clean_text((string) ($_GET['success'] ?? ($_POST['success'] ?? '')), 20);

    $store = payments_read_store();
    $orderIndex = payments_find_order_index_by_token($store, $orderToken);
    if ($orderIndex < 0) {
        dent_error('سفارش callback پیدا نشد.', 404);
    }
    $order = $store['orders'][$orderIndex];
    $orderGateway = (string) ($order['gateway'] ?? '');
    $orderGatewayRecord = payments_gateway_resolve_record($orderGateway);
    $orderGatewayProvider = is_array($orderGatewayRecord)
        ? payments_gateway_provider_clean((string) ($orderGatewayRecord['provider'] ?? ''))
        : payments_gateway_provider_clean($orderGateway);

    if ($orderGatewayProvider === PAYMENTS_GATEWAY_ZIBAL) {
        if ($zibalTrackId !== '') {
            $authority = $zibalTrackId;
        }

        $zibalSuccessLower = strtolower($zibalSuccess);
        $zibalStatusLower = strtolower($gatewayStatus);
        $isZibalApproved = false;

        if (in_array($zibalSuccessLower, ['1', 'true', 'yes'], true)) {
            $isZibalApproved = true;
        }
        if (in_array($zibalStatusLower, ['2', 'success', 'ok', 'paid'], true)) {
            $isZibalApproved = true;
        }

        if ($gatewayStatus === '') {
            $gatewayStatus = $isZibalApproved ? 'ok' : ($zibalSuccess !== '' ? 'cancel' : 'failed');
        } elseif (!$isZibalApproved && in_array($zibalStatusLower, ['0', '-1', '-2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'], true)) {
            $gatewayStatus = 'cancel';
        }
    }

    $gatewayStatusLower = strtolower($gatewayStatus);

    if ((string) ($order['status'] ?? '') === PAYMENTS_ORDER_STATUS_SUCCESS) {
        payments_api_redirect_to_result($orderToken);
    }

    if ($authority === '') {
        $authority = (string) ($order['authority'] ?? '');
    }

    $canceledStatuses = ['nok', 'cancel', 'canceled', 'cancelled', 'failed'];
    if (in_array($gatewayStatusLower, $canceledStatuses, true) && $authority === '') {
        payments_with_store_lock(static function (array &$store) use ($orderToken, $gatewayStatus, $authority): void {
            $orderIndex = payments_find_order_index_by_token($store, $orderToken);
            if ($orderIndex < 0) {
                return;
            }

            $current = $store['orders'][$orderIndex];
            if ((string) ($current['status'] ?? '') === PAYMENTS_ORDER_STATUS_PENDING) {
                $current['status'] = PAYMENTS_ORDER_STATUS_CANCELED;
            }

            if ($authority !== '' && (string) ($current['authority'] ?? '') === '') {
                $current['authority'] = $authority;
            }

            $snapshot = is_array($current['gateway_response_snapshot'] ?? null) ? $current['gateway_response_snapshot'] : [];
            $snapshot['callback'] = [
                'authority' => $authority,
                'status' => $gatewayStatus,
                'at' => dent_iso_now(),
            ];
            $current['gateway_response_snapshot'] = $snapshot;
            $store['orders'][$orderIndex] = $current;

            $item = null;
            $itemIndex = payments_find_item_index_by_id($store, (int) ($current['item_id'] ?? 0));
            if ($itemIndex >= 0) {
                $item = $store['items'][$itemIndex];
            }
            payments_api_append_order_notification($store, $current, $item, false);
        });

        payments_api_redirect_to_result($orderToken);
    }

    $verifyResult = payments_gateway_verify_payment($orderGateway, $order, [
        'authority' => $authority,
        'trackId' => $zibalTrackId !== '' ? $zibalTrackId : $authority,
        'status' => $gatewayStatusLower !== '' ? $gatewayStatusLower : 'ok',
    ]);

    payments_log_gateway_event('verify-request', [
        'orderId' => (int) ($order['id'] ?? 0),
        'gateway' => (string) ($order['gateway'] ?? ''),
        'result' => $verifyResult,
    ]);

    $hookPayload = payments_with_store_lock(static function (array &$store) use ($orderToken, $authority, $gatewayStatus, $verifyResult): array {
        $orderIndex = payments_find_order_index_by_token($store, $orderToken);
        if ($orderIndex < 0) {
            return [];
        }

        $current = $store['orders'][$orderIndex];
        $previousStatus = (string) ($current['status'] ?? PAYMENTS_ORDER_STATUS_PENDING);
        if ($previousStatus === PAYMENTS_ORDER_STATUS_SUCCESS) {
            return [];
        }

        if ($authority !== '') {
            if ((string) ($current['authority'] ?? '') === '') {
                $current['authority'] = $authority;
            } elseif ((string) ($current['authority'] ?? '') !== $authority) {
                $current['status'] = PAYMENTS_ORDER_STATUS_FAILED;
            }
        }

        if ((bool) ($verifyResult['verified'] ?? false)) {
            $current['status'] = PAYMENTS_ORDER_STATUS_SUCCESS;
            $current['ref_id'] = dent_clean_text((string) ($verifyResult['refId'] ?? ''), 120);
            if ((string) ($current['paid_at'] ?? '') === '') {
                $current['paid_at'] = dent_iso_now();
            }
            $current['verified_at'] = dent_iso_now();
        } else {
            $nextStatus = (string) ($verifyResult['status'] ?? PAYMENTS_ORDER_STATUS_FAILED);
            if (!in_array(
                $nextStatus,
                [PAYMENTS_ORDER_STATUS_FAILED, PAYMENTS_ORDER_STATUS_CANCELED, PAYMENTS_ORDER_STATUS_EXPIRED, PAYMENTS_ORDER_STATUS_PENDING],
                true
            )) {
                $nextStatus = PAYMENTS_ORDER_STATUS_FAILED;
            }
            $current['status'] = $nextStatus;
        }

        $snapshot = is_array($current['gateway_response_snapshot'] ?? null) ? $current['gateway_response_snapshot'] : [];
        $snapshot['callback'] = [
            'authority' => $authority,
            'status' => $gatewayStatus,
            'at' => dent_iso_now(),
        ];
        $snapshot['verify'] = $verifyResult;
        $current['gateway_response_snapshot'] = $snapshot;
        $store['orders'][$orderIndex] = $current;

        if ($previousStatus !== (string) ($current['status'] ?? '')) {
            $item = null;
            $itemIndex = payments_find_item_index_by_id($store, (int) ($current['item_id'] ?? 0));
            if ($itemIndex >= 0) {
                $item = $store['items'][$itemIndex];
            }
            $notification = payments_api_append_order_notification(
                $store,
                $current,
                $item,
                (string) ($current['status'] ?? '') === PAYMENTS_ORDER_STATUS_SUCCESS
            );

            return [
                'order' => $current,
                'item' => $item,
                'notification' => $notification,
            ];
        }

        return [];
    });

    if (is_array($hookPayload['notification'] ?? null) && is_array($hookPayload['order'] ?? null)) {
        payments_api_trigger_notification_hook(
            $hookPayload['notification'],
            $hookPayload['order'],
            is_array($hookPayload['item'] ?? null) ? $hookPayload['item'] : null
        );
    }

    payments_api_redirect_to_result($orderToken);
}

if ($action === 'publicOrderResult') {
    payments_api_require_method(['GET']);
    $user = payments_api_current_public_user();
    $orderToken = dent_clean_text((string) ($_GET['orderToken'] ?? ''), 120);
    if ($orderToken === '') {
        dent_error('شناسه سفارش معتبر نیست.', 422);
    }

    $store = payments_read_store();
    $orderIndex = payments_find_order_index_by_token($store, $orderToken);
    if ($orderIndex < 0) {
        dent_error('سفارش موردنظر پیدا نشد.', 404);
    }

    $order = $store['orders'][$orderIndex];
    $collectionForOrder = payments_api_collection_from_order($store, $order);
    $guestResultAllowed = $collectionForOrder !== null && payments_api_collection_allows_guest($collectionForOrder);
    if ($user === null && !$guestResultAllowed) {
        dent_error('نیاز به ورود به حساب کاربری است.', 401, ['loggedOut' => true]);
    }
    if ($user !== null && !payments_api_user_can_view_order($order, $user) && !$guestResultAllowed) {
        dent_error('دسترسی به نتیجه این پرداخت مجاز نیست.', 403);
    }

    $authority = dent_clean_text((string) ($order['authority'] ?? ''), 120);
    if ((string) ($order['status'] ?? '') !== PAYMENTS_ORDER_STATUS_SUCCESS && $authority !== '') {
        $verifyResult = payments_gateway_verify_payment((string) ($order['gateway'] ?? ''), $order, [
            'authority' => $authority,
            'trackId' => $authority,
            'status' => 'ok',
            'publicResult' => true,
        ]);
        payments_log_gateway_event('public-result-verify', [
            'orderId' => (int) ($order['id'] ?? 0),
            'gateway' => (string) ($order['gateway'] ?? ''),
            'result' => $verifyResult,
        ]);

        $payload = payments_with_store_lock(static function (array &$store) use ($orderToken, $authority, $verifyResult): array {
            $orderIndex = payments_find_order_index_by_token($store, $orderToken);
            if ($orderIndex < 0 || !is_array($store['orders'][$orderIndex] ?? null)) {
                return [];
            }
            $current = $store['orders'][$orderIndex];
            $previousStatus = (string) ($current['status'] ?? PAYMENTS_ORDER_STATUS_PENDING);

            $snapshot = is_array($current['gateway_response_snapshot'] ?? null) ? $current['gateway_response_snapshot'] : [];
            $snapshot['public_result_verify'] = [
                'authority' => $authority,
                'at' => dent_iso_now(),
                'result' => $verifyResult,
            ];
            $current['gateway_response_snapshot'] = $snapshot;

            if ((bool) ($verifyResult['verified'] ?? false)) {
                $current['status'] = PAYMENTS_ORDER_STATUS_SUCCESS;
                $current['ref_id'] = dent_clean_text((string) ($verifyResult['refId'] ?? ''), 120);
                if ((string) ($current['paid_at'] ?? '') === '') {
                    $current['paid_at'] = dent_iso_now();
                }
                $current['verified_at'] = dent_iso_now();
            }

            $store['orders'][$orderIndex] = $current;
            $item = null;
            $itemIndex = payments_find_item_index_by_id($store, (int) ($current['item_id'] ?? 0));
            if ($itemIndex >= 0) {
                $item = $store['items'][$itemIndex];
            }
            $notification = null;
            if ($previousStatus !== (string) ($current['status'] ?? '') && (string) ($current['status'] ?? '') === PAYMENTS_ORDER_STATUS_SUCCESS) {
                $notification = payments_api_append_order_notification($store, $current, is_array($item) ? $item : null, true);
            }

            return [
                'order' => $current,
                'item' => $item,
                'notification' => $notification,
            ];
        });

        if (is_array($payload['notification'] ?? null) && is_array($payload['order'] ?? null)) {
            payments_api_trigger_notification_hook(
                $payload['notification'],
                $payload['order'],
                is_array($payload['item'] ?? null) ? $payload['item'] : null
            );
        }
        if (is_array($payload['order'] ?? null)) {
            $order = $payload['order'];
        }
    }

    $item = null;
    $itemIndex = payments_find_item_index_by_id($store, (int) ($order['item_id'] ?? 0));
    if ($itemIndex >= 0) {
        $item = $store['items'][$itemIndex];
    }

    dent_json_response([
        'success' => true,
        'order' => payments_order_public_result_payload($order, $item),
    ]);
}

if ($action === 'mockGateway') {
    payments_api_require_method(['GET']);

    $orderToken = dent_clean_text((string) ($_GET['orderToken'] ?? ''), 120);
    $result = strtolower(dent_clean_text((string) ($_GET['result'] ?? 'success'), 20));
    $authority = dent_clean_text((string) ($_GET['authority'] ?? ''), 120);
    if ($orderToken === '') {
        dent_error('شناسه سفارش درگاه آزمایشی معتبر نیست.', 422);
    }

    $status = 'OK';
    if (!in_array($result, ['success', 'ok'], true)) {
        $status = 'NOK';
    }

    $callbackUrl = '/api/payments_api.php?action=callback'
        . '&orderToken=' . rawurlencode($orderToken)
        . '&Status=' . rawurlencode($status)
        . '&Authority=' . rawurlencode($authority);

    header('Location: ' . $callbackUrl, true, 302);
    exit;
}

if ($action === 'ownerDashboard') {
    payments_api_require_method(['GET']);
    dent_require_owner();

    $reconciliation = payments_api_reconcile_pending_orders([
        'limit' => 8,
        'minAgeSeconds' => 45,
        'cooldownSeconds' => 240,
    ]);
    $store = payments_read_store();
    $itemById = [];
    foreach ($store['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $itemById[(int) ($item['id'] ?? 0)] = $item;
    }

    $orders = is_array($store['orders'] ?? null) ? $store['orders'] : [];
    $summary = payments_api_orders_summary($orders);

    $recentOrders = [];
    foreach (array_slice($orders, 0, 10) as $order) {
        if (!is_array($order)) {
            continue;
        }
        $item = $itemById[(int) ($order['item_id'] ?? 0)] ?? null;
        $recentOrders[] = payments_owner_order_payload($order, is_array($item) ? $item : null);
    }

    $items = [];
    foreach ($store['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string) ($item['status'] ?? '') === PAYMENTS_ITEM_STATUS_DELETED) {
            continue;
        }
        $items[] = payments_owner_item_payload($item);
    }

    $notifications = [];
    foreach (array_slice($store['notifications'], 0, 24) as $notification) {
        if (!is_array($notification)) {
            continue;
        }
        $notifications[] = $notification;
    }

    $collections = [];
    foreach (($store['collections'] ?? []) as $collection) {
        if (!is_array($collection) || (string) ($collection['status'] ?? '') === PAYMENTS_COLLECTION_STATUS_DELETED) {
            continue;
        }
        $collections[] = payments_api_owner_collection_payload(
            $collection,
            payments_api_collection_orders($store, (int) ($collection['id'] ?? 0))
        );
    }

    dent_json_response([
        'success' => true,
        'summary' => array_merge($summary, [
            'totalItems' => count($items),
            'activeItems' => count(array_filter($items, static function (array $item): bool {
                return (string) ($item['status'] ?? '') === PAYMENTS_ITEM_STATUS_ACTIVE;
            })),
            'totalCollections' => count($collections),
            'unreadNotifications' => payments_api_notifications_unread_count($notifications),
        ]),
        'items' => $items,
        'collections' => $collections,
        'recentOrders' => $recentOrders,
        'notifications' => $notifications,
        'gateways' => payments_api_owner_gateways_payload($store),
        'reconciliation' => $reconciliation,
    ]);
}

if ($action === 'ownerGateways') {
    payments_api_require_method(['GET']);
    dent_require_owner();

    $store = payments_read_store();
    dent_json_response(array_merge(['success' => true], payments_api_owner_gateways_payload($store)));
}

if ($action === 'ownerCollections') {
    payments_api_require_method(['GET']);
    dent_require_owner();

    $store = payments_read_store();
    $collections = [];
    foreach (($store['collections'] ?? []) as $collection) {
        if (!is_array($collection) || (string) ($collection['status'] ?? '') === PAYMENTS_COLLECTION_STATUS_DELETED) {
            continue;
        }
        $collections[] = payments_api_owner_collection_payload(
            $collection,
            payments_api_collection_orders($store, (int) ($collection['id'] ?? 0))
        );
    }

    dent_json_response([
        'success' => true,
        'collections' => $collections,
        'paymentGateways' => payments_api_checkout_gateways_payload(),
    ]);
}

if ($action === 'ownerSaveCollection') {
    payments_api_require_method(['POST']);
    dent_require_owner();

    $collectionId = max(0, (int) ($_POST['id'] ?? 0));
    $title = dent_clean_text((string) ($_POST['title'] ?? ''), 160);
    if ($title === '') {
        dent_error('عنوان هزینه الزامی است.', 422);
    }

    $amount = max(0, (int) dent_normalize_digits((string) ($_POST['amount'] ?? '0')));
    if ($amount <= 0) {
        dent_error('مبلغ باید بیشتر از صفر باشد.', 422);
    }

    $status = dent_clean_text((string) ($_POST['status'] ?? PAYMENTS_COLLECTION_STATUS_ACTIVE), 24);
    if (!in_array($status, [PAYMENTS_COLLECTION_STATUS_ACTIVE, PAYMENTS_COLLECTION_STATUS_INACTIVE], true)) {
        $status = PAYMENTS_COLLECTION_STATUS_INACTIVE;
    }

    $gateway = payments_gateway_clean((string) ($_POST['gateway'] ?? ''));
    $description = dent_clean_text((string) ($_POST['description'] ?? ''), 1200);
    $imageUrl = dent_clean_text((string) ($_POST['imageUrl'] ?? ($_POST['image_url'] ?? '')), 420);
    if ($imageUrl !== '' && !payments_api_validate_uploaded_image_url($imageUrl)) {
        dent_error('تصویر جمع‌آوری هزینه باید از آپلود داخلی سایت انتخاب شود.', 422);
    }
    $successMessage = dent_clean_text((string) ($_POST['successMessage'] ?? ''), 600);
    $failureMessage = dent_clean_text((string) ($_POST['failureMessage'] ?? ''), 600);
    $allowGuestPayments = filter_var($_POST['allowGuestPayments'] ?? ($_POST['allow_guest_payments'] ?? false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    $collectPayerName = filter_var($_POST['collectPayerName'] ?? ($_POST['collect_payer_name'] ?? true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false;
    $collectPayerPhone = filter_var($_POST['collectPayerPhone'] ?? ($_POST['collect_payer_phone'] ?? true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false;
    $collectPayerStudentNumber = filter_var($_POST['collectPayerStudentNumber'] ?? ($_POST['collect_payer_student_number'] ?? false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;

    try {
        $saved = payments_with_store_lock(static function (array &$store) use (
            $collectionId,
            $title,
            $amount,
            $status,
            $gateway,
            $description,
            $imageUrl,
            $successMessage,
            $failureMessage,
            $allowGuestPayments,
            $collectPayerName,
            $collectPayerPhone,
            $collectPayerStudentNumber
        ): array {
            $now = dent_iso_now();
            $payload = [
                'title' => $title,
                'description' => $description,
                'amount' => $amount,
                'status' => $status,
                'gateway' => $gateway,
                'image_url' => $imageUrl,
                'allow_guest_payments' => $allowGuestPayments,
                'collect_payer_name' => $collectPayerName,
                'collect_payer_phone' => $collectPayerPhone,
                'collect_payer_student_number' => $collectPayerStudentNumber,
                'success_message' => $successMessage,
                'failure_message' => $failureMessage,
                'updated_at' => $now,
            ];

            if ($collectionId > 0) {
                $index = payments_find_collection_index_by_id($store, $collectionId);
                if ($index < 0) {
                    throw new PaymentsApiException('لینک جمع‌آوری هزینه پیدا نشد.', 404);
                }
                $existing = is_array($store['collections'][$index] ?? null) ? $store['collections'][$index] : [];
                $store['collections'][$index] = array_merge($existing, $payload, [
                    'id' => (int) ($existing['id'] ?? $collectionId),
                    'token' => (string) ($existing['token'] ?? payments_random_token(12)),
                    'created_at' => (string) ($existing['created_at'] ?? $now),
                ]);
                return $store['collections'][$index];
            }

            $payload = array_merge($payload, [
                'id' => payments_next_collection_id($store),
                'token' => payments_random_token(12),
                'created_at' => $now,
            ]);
            $store['collections'][] = $payload;
            return $payload;
        });
    } catch (PaymentsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode());
    }

    dent_json_response([
        'success' => true,
        'collection' => payments_api_owner_collection_payload($saved, []),
        'message' => $collectionId > 0 ? 'لینک جمع‌آوری هزینه ویرایش شد.' : 'لینک جمع‌آوری هزینه ساخته شد.',
    ]);
}

if ($action === 'ownerDeleteCollection') {
    payments_api_require_method(['POST']);
    dent_require_owner();

    $collectionId = max(0, (int) ($_POST['id'] ?? 0));
    if ($collectionId <= 0) {
        dent_error('شناسه لینک جمع‌آوری هزینه معتبر نیست.', 422);
    }

    try {
        $deleted = payments_with_store_lock(static function (array &$store) use ($collectionId): array {
            $index = payments_find_collection_index_by_id($store, $collectionId);
            if ($index < 0) {
                throw new PaymentsApiException('لینک جمع‌آوری هزینه پیدا نشد.', 404);
            }
            $collection = $store['collections'][$index];
            $collection['status'] = PAYMENTS_COLLECTION_STATUS_DELETED;
            $collection['updated_at'] = dent_iso_now();
            $store['collections'][$index] = $collection;
            return $collection;
        });
    } catch (PaymentsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode());
    }

    dent_json_response([
        'success' => true,
        'collection' => payments_api_owner_collection_payload($deleted, []),
        'message' => 'لینک جمع‌آوری هزینه حذف شد و سوابق پرداخت آن حفظ شد.',
    ]);
}

if ($action === 'ownerCollectionExport') {
    payments_api_require_method(['GET']);
    dent_require_owner();

    $collectionId = max(0, (int) ($_GET['id'] ?? 0));
    $format = strtolower(dent_clean_text((string) ($_GET['format'] ?? 'csv'), 12));
    if (!in_array($format, ['csv', 'txt', 'json'], true)) {
        $format = 'csv';
    }
    if ($collectionId <= 0) {
        dent_error('شناسه لینک جمع‌آوری هزینه معتبر نیست.', 422);
    }

    $store = payments_read_store();
    $index = payments_find_collection_index_by_id($store, $collectionId);
    if ($index < 0 || !is_array($store['collections'][$index] ?? null)) {
        dent_error('لینک جمع‌آوری هزینه پیدا نشد.', 404);
    }
    $collection = $store['collections'][$index];
    $orders = array_values(array_filter(
        payments_api_collection_orders($store, $collectionId),
        static fn(array $order): bool => (string) ($order['status'] ?? '') === PAYMENTS_ORDER_STATUS_SUCCESS
    ));

    $rows = [];
    $rowNumber = 1;
    foreach ($orders as $order) {
        $rows[] = [
            'row' => $rowNumber,
            'name' => (string) ($order['payer_name'] ?? ''),
            'student_number' => (string) ($order['payer_student_number'] ?? ''),
            'phone' => (string) ($order['payer_phone'] ?? ''),
            'amount' => max(0, (int) ($order['amount'] ?? 0)),
            'ref_id' => (string) ($order['ref_id'] ?? ''),
            'authority' => (string) ($order['authority'] ?? ''),
            'paid_at' => (string) ($order['paid_at'] ?? ''),
            'created_at' => (string) ($order['created_at'] ?? ''),
        ];
        $rowNumber++;
    }

    $fileBase = 'dent1402-collection-' . (int) ($collection['id'] ?? 0) . '-' . date('Y-m-d');
    if ($format === 'json') {
        header('Content-Disposition: attachment; filename="' . $fileBase . '.json"');
        dent_json_response([
            'success' => true,
            'collection' => payments_api_owner_collection_payload($collection, $orders),
            'rows' => $rows,
        ]);
    }

    if ($format === 'txt') {
        $lines = [(string) ($collection['title'] ?? ''), str_repeat('=', 32)];
        foreach ($rows as $row) {
            $lines[] = implode(' | ', [
                (string) $row['row'],
                (string) $row['name'],
                (string) $row['student_number'],
                (string) $row['phone'],
                (string) $row['amount'],
                (string) $row['ref_id'],
                (string) $row['paid_at'],
            ]);
        }
        $content = implode("\r\n", $lines);
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileBase . '.txt"');
        header('Content-Length: ' . (string) strlen($content));
        echo $content;
        exit;
    }

    $headers = ['row', 'name', 'student_number', 'phone', 'amount', 'ref_id', 'authority', 'paid_at', 'created_at'];
    $csv = [implode(',', array_map(static fn(string $cell): string => '"' . str_replace('"', '""', $cell) . '"', $headers))];
    foreach ($rows as $row) {
        $csv[] = implode(',', array_map(static function ($cell): string {
            return '"' . str_replace('"', '""', (string) $cell) . '"';
        }, array_values($row)));
    }
    $content = "\xEF\xBB\xBF" . implode("\r\n", $csv);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileBase . '.csv"');
    header('Content-Length: ' . (string) strlen($content));
    echo $content;
    exit;
}

if ($action === 'ownerSaveGateway') {
    payments_api_require_method(['POST']);
    dent_require_owner();

    $gatewayId = max(0, (int) ($_POST['id'] ?? 0));
    $provider = payments_gateway_provider_clean((string) ($_POST['provider'] ?? ''));
    if ($provider === '') {
        dent_error('نوع درگاه پرداخت معتبر نیست.', 422);
    }

    $key = payments_gateway_clean((string) ($_POST['key'] ?? ''));
    if ($key === '') {
        $key = $provider . '-' . ($gatewayId > 0 ? (string) $gatewayId : substr(sha1((string) microtime(true)), 0, 6));
    }

    $label = dent_clean_text((string) ($_POST['label'] ?? ''), 80);
    if ($label === '') {
        $label = payments_gateway_public_label($provider);
    }

    $providerLabel = dent_clean_text((string) ($_POST['providerLabel'] ?? ($_POST['provider_label'] ?? '')), 120);
    if ($providerLabel === '') {
        $providerLabel = payments_gateway_provider_label_from_type($provider);
    }

    $merchantId = dent_clean_text((string) ($_POST['merchantId'] ?? ($_POST['merchant_id'] ?? '')), 260);
    $apiKey = dent_clean_text((string) ($_POST['apiKey'] ?? ($_POST['api_key'] ?? '')), 320);
    $isEnabled = filter_var($_POST['isEnabled'] ?? ($_POST['is_enabled'] ?? true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false;
    if ($isEnabled && $provider !== PAYMENTS_GATEWAY_MOCK && $merchantId === '' && $apiKey === '') {
        dent_error('برای فعال‌سازی این درگاه باید merchant یا API Key وارد شود.', 422);
    }

    $isDefault = filter_var($_POST['isDefault'] ?? ($_POST['is_default'] ?? false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    $payload = [
        'key' => $key,
        'provider' => $provider,
        'label' => $label,
        'provider_label' => $providerLabel,
        'icon' => dent_clean_text((string) ($_POST['icon'] ?? ''), 8),
        'merchant_id' => $merchantId,
        'api_key' => $apiKey,
        'request_url' => dent_clean_text((string) ($_POST['requestUrl'] ?? ($_POST['request_url'] ?? '')), 420),
        'verify_url' => dent_clean_text((string) ($_POST['verifyUrl'] ?? ($_POST['verify_url'] ?? '')), 420),
        'start_url' => dent_clean_text((string) ($_POST['startUrl'] ?? ($_POST['start_url'] ?? '')), 420),
        'is_enabled' => $isEnabled,
        'is_default' => $isDefault,
    ];

    try {
        $result = payments_with_store_lock(static function (array &$store) use ($gatewayId, $payload): array {
            $store['gatewaySettings'] = ['managed' => true];
            $now = dent_iso_now();
            $targetIndex = -1;
            foreach ($store['gateways'] as $index => $gateway) {
                if (!is_array($gateway)) {
                    continue;
                }
                if ($gatewayId > 0 && (int) ($gateway['id'] ?? 0) === $gatewayId) {
                    $targetIndex = (int) $index;
                    continue;
                }
                if ((string) ($gateway['key'] ?? '') === (string) ($payload['key'] ?? '')) {
                    throw new PaymentsApiException('کلید این درگاه قبلا استفاده شده است.', 422);
                }
            }

            if ((bool) ($payload['is_default'] ?? false)) {
                foreach ($store['gateways'] as $index => $gateway) {
                    if (!is_array($gateway)) {
                        continue;
                    }
                    $gateway['is_default'] = false;
                    $store['gateways'][$index] = $gateway;
                }
            }

            if ($targetIndex >= 0) {
                $existing = is_array($store['gateways'][$targetIndex]) ? $store['gateways'][$targetIndex] : [];
                $next = array_merge($existing, $payload, [
                    'id' => (int) ($existing['id'] ?? $gatewayId),
                    'created_at' => (string) ($existing['created_at'] ?? $now),
                    'updated_at' => $now,
                ]);
                $store['gateways'][$targetIndex] = $next;
            } else {
                $next = array_merge($payload, [
                    'id' => payments_next_gateway_id($store),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $store['gateways'][] = $next;
            }

            $hasDefault = false;
            foreach ($store['gateways'] as $gateway) {
                if (is_array($gateway) && (bool) ($gateway['is_default'] ?? false) && (bool) ($gateway['is_enabled'] ?? false)) {
                    $hasDefault = true;
                    break;
                }
            }
            if (!$hasDefault) {
                foreach ($store['gateways'] as $index => $gateway) {
                    if (!is_array($gateway) || !(bool) ($gateway['is_enabled'] ?? false)) {
                        continue;
                    }
                    $gateway['is_default'] = true;
                    $store['gateways'][$index] = $gateway;
                    break;
                }
            }

            return [
                'gateway' => $next,
                'store' => $store,
            ];
        });
    } catch (PaymentsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode());
    }

    dent_json_response([
        'success' => true,
        'gateway' => payments_api_owner_gateway_payload($result['gateway']),
        'gateways' => payments_api_owner_gateways_payload($result['store']),
        'message' => $gatewayId > 0 ? 'درگاه پرداخت ویرایش شد.' : 'درگاه پرداخت جدید اضافه شد.',
    ]);
}

if ($action === 'ownerDeleteGateway') {
    payments_api_require_method(['POST']);
    dent_require_owner();

    $gatewayId = max(0, (int) ($_POST['id'] ?? 0));
    if ($gatewayId <= 0) {
        dent_error('شناسه درگاه معتبر نیست.', 422);
    }

    try {
        $storePayload = payments_with_store_lock(static function (array &$store) use ($gatewayId): array {
            $targetIndex = -1;
            $targetKey = '';
            foreach ($store['gateways'] as $index => $gateway) {
                if (!is_array($gateway) || (int) ($gateway['id'] ?? 0) !== $gatewayId) {
                    continue;
                }
                $targetIndex = (int) $index;
                $targetKey = (string) ($gateway['key'] ?? '');
                break;
            }
            if ($targetIndex < 0) {
                throw new PaymentsApiException('درگاه پرداخت پیدا نشد.', 404);
            }

            foreach ($store['orders'] as $order) {
                if (!is_array($order)) {
                    continue;
                }
                if ((string) ($order['gateway'] ?? '') === $targetKey && (string) ($order['status'] ?? '') === PAYMENTS_ORDER_STATUS_PENDING) {
                    throw new PaymentsApiException('این درگاه سفارش در انتظار دارد و فعلا قابل حذف نیست.', 422);
                }
            }

            array_splice($store['gateways'], $targetIndex, 1);
            $hasDefault = false;
            foreach ($store['gateways'] as $gateway) {
                if (is_array($gateway) && (bool) ($gateway['is_default'] ?? false) && (bool) ($gateway['is_enabled'] ?? false)) {
                    $hasDefault = true;
                    break;
                }
            }
            if (!$hasDefault) {
                foreach ($store['gateways'] as $index => $gateway) {
                    if (!is_array($gateway) || !(bool) ($gateway['is_enabled'] ?? false)) {
                        continue;
                    }
                    $gateway['is_default'] = true;
                    $store['gateways'][$index] = $gateway;
                    break;
                }
            }

            return $store;
        });
    } catch (PaymentsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode());
    }

    dent_json_response(array_merge([
        'success' => true,
        'message' => 'درگاه پرداخت حذف شد.',
    ], payments_api_owner_gateways_payload($storePayload)));
}

if ($action === 'ownerItems') {
    payments_api_require_method(['GET']);
    dent_require_owner();

    $store = payments_read_store();
    $items = [];
    foreach ($store['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string) ($item['status'] ?? '') === PAYMENTS_ITEM_STATUS_DELETED) {
            continue;
        }
        $items[] = payments_owner_item_payload($item);
    }

    dent_json_response([
        'success' => true,
        'items' => $items,
    ]);
}

if ($action === 'ownerImportEndosim') {
    payments_api_require_method(['POST']);
    dent_require_owner();

    $summary = payments_with_store_lock(static function (array &$store): array {
        return endosim_import_into_store($store);
    });

    dent_json_response([
        'success' => true,
        'summary' => $summary,
        'message' => 'کاتالوگ اندوسیم به‌روزرسانی شد: '
            . (int) ($summary['created'] ?? 0) . ' محصول جدید، '
            . (int) ($summary['updated'] ?? 0) . ' محصول به‌روزرسانی‌شده.',
    ]);
}

if ($action === 'ownerSaveItem') {
    payments_api_require_method(['POST']);
    dent_require_owner();

    $itemId = max(0, (int) ($_POST['id'] ?? 0));
    $title = dent_clean_text((string) ($_POST['title'] ?? ''), 140);
    if ($title === '') {
        dent_error('عنوان آیتم الزامی است.', 422);
    }

    $slug = payments_clean_slug((string) ($_POST['slug'] ?? ''));
    if ($slug === '') {
        if ($itemId > 0) {
            $previewStore = payments_read_store();
            $previewIndex = payments_find_item_index_by_id($previewStore, $itemId);
            if ($previewIndex >= 0 && is_array($previewStore['items'][$previewIndex] ?? null)) {
                $slug = payments_clean_slug((string) ($previewStore['items'][$previewIndex]['slug'] ?? ''));
            }
        }
        if ($slug === '') {
            $slug = payments_clean_slug($title);
        }
        if ($slug === '') {
            $slug = 'item-' . date('YmdHis') . '-' . substr(sha1($title . '|' . microtime(true)), 0, 6);
        }
    }

    $price = max(0, (int) dent_normalize_digits((string) ($_POST['price'] ?? '0')));
    if ($price <= 0) {
        dent_error('قیمت آیتم باید بیشتر از صفر باشد.', 422);
    }

    $status = (string) ($_POST['status'] ?? PAYMENTS_ITEM_STATUS_ACTIVE);
    if (!in_array($status, [PAYMENTS_ITEM_STATUS_ACTIVE, PAYMENTS_ITEM_STATUS_INACTIVE], true)) {
        $status = PAYMENTS_ITEM_STATUS_INACTIVE;
    }

    $category = payments_normalize_item_category((string) ($_POST['category'] ?? ''));
    $shortDescription = dent_clean_text((string) ($_POST['shortDescription'] ?? ($_POST['short_description'] ?? '')), 460);
    $fullDescription = dent_clean_text((string) ($_POST['fullDescription'] ?? ($_POST['full_description'] ?? '')), 6000);
    $heroImage = dent_clean_text((string) ($_POST['heroImage'] ?? ($_POST['hero_image'] ?? '')), 420);
    $gallery = payments_api_parse_gallery_input($_POST['gallery'] ?? []);
    if ($heroImage === '') {
        dent_error('تصویر اصلی کالا الزامی است. تصویر را از دستگاه آپلود کن.', 422);
    }
    if (!payments_api_validate_uploaded_image_url($heroImage)) {
        dent_error('برای تصویر کالا لینک مستقیم اینترنتی مجاز نیست. تصویر را از پنل مالک آپلود کن.', 422);
    }
    foreach ($gallery as $galleryImage) {
        if (!payments_api_validate_uploaded_image_url((string) $galleryImage)) {
            dent_error('برای گالری کالا لینک مستقیم اینترنتی مجاز نیست. تصاویر را از پنل مالک آپلود کن.', 422);
        }
    }
    $specifications = payments_api_parse_specifications_input($_POST['specifications'] ?? []);
    $requiredFields = payments_api_parse_required_fields_input($_POST['requiredFields'] ?? ($_POST['required_fields'] ?? []));
    $audienceNote = dent_clean_text((string) ($_POST['audienceNote'] ?? ($_POST['audience_note'] ?? '')), 220);
    $deliveryNote = dent_clean_text((string) ($_POST['deliveryNote'] ?? ($_POST['delivery_note'] ?? '')), 360);
    $supportNote = dent_clean_text((string) ($_POST['supportNote'] ?? ($_POST['support_note'] ?? '')), 360);
    $allowCancellation = filter_var($_POST['allowCancellation'] ?? ($_POST['allow_cancellation'] ?? false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    $maxQuantityPerOrder = max(1, min(99, (int) dent_normalize_digits((string) ($_POST['maxQuantityPerOrder'] ?? ($_POST['max_quantity_per_order'] ?? '1')))));
    $discountCodes = payments_api_parse_discount_codes_input($_POST['discountCodes'] ?? ($_POST['discount_codes'] ?? []));
    $ratingAverage = (float) dent_normalize_digits((string) ($_POST['ratingAverage'] ?? ($_POST['rating_average'] ?? '0')));
    $ratingAverage = max(0, min(5, $ratingAverage));
    $ratingCount = max(0, (int) dent_normalize_digits((string) ($_POST['ratingCount'] ?? ($_POST['rating_count'] ?? '0'))));
    $reviews = payments_api_parse_reviews_input($_POST['reviews'] ?? []);
    $successMessage = dent_clean_text((string) ($_POST['successMessage'] ?? ($_POST['success_message'] ?? 'پرداخت شما با موفقیت ثبت شد.')), 600);
    $failureMessage = dent_clean_text((string) ($_POST['failureMessage'] ?? ($_POST['failure_message'] ?? 'پرداخت شما ناموفق بود.')), 600);
    $startsAt = payments_normalize_datetime_string((string) ($_POST['startsAt'] ?? ($_POST['starts_at'] ?? '')), '');
    $expiresAt = payments_normalize_datetime_string((string) ($_POST['expiresAt'] ?? ($_POST['expires_at'] ?? '')), '');
    $capacity = payments_normalize_positive_int_nullable($_POST['capacity'] ?? null, 1000000);

    if ($startsAt !== '' && $expiresAt !== '' && strtotime($startsAt) !== false && strtotime($expiresAt) !== false) {
        if ((int) strtotime($expiresAt) <= (int) strtotime($startsAt)) {
            dent_error('تاریخ پایان باید بعد از تاریخ شروع باشد.', 422);
        }
    }

    try {
        $saved = payments_with_store_lock(static function (array &$store) use (
            $itemId,
            $title,
            $slug,
            $price,
            $status,
            $category,
            $shortDescription,
            $fullDescription,
            $heroImage,
            $gallery,
            $specifications,
            $requiredFields,
            $audienceNote,
            $deliveryNote,
            $supportNote,
            $allowCancellation,
            $maxQuantityPerOrder,
            $discountCodes,
            $ratingAverage,
            $ratingCount,
            $reviews,
            $successMessage,
            $failureMessage,
            $startsAt,
            $expiresAt,
            $capacity
        ): array {
            foreach ($store['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                if ((string) ($item['slug'] ?? '') !== $slug) {
                    continue;
                }
                if ($itemId > 0 && (int) ($item['id'] ?? 0) === $itemId) {
                    continue;
                }
                throw new PaymentsApiException('این اسلاگ قبلاً استفاده شده است.', 422);
            }

            $now = dent_iso_now();
            $payload = [
                'id' => $itemId > 0 ? $itemId : payments_next_item_id($store),
                'slug' => $slug,
                'category' => $category,
                'title' => $title,
                'short_description' => $shortDescription,
                'full_description' => $fullDescription,
                'hero_image' => $heroImage,
                'gallery' => $gallery,
                'specifications' => $specifications,
                'price' => $price,
                'status' => $status,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'capacity' => $capacity,
                'max_quantity_per_order' => $maxQuantityPerOrder,
                'required_fields' => $requiredFields,
                'audience_note' => $audienceNote,
                'delivery_note' => $deliveryNote,
                'support_note' => $supportNote,
                'allow_cancellation' => $allowCancellation,
                'discount_codes' => $discountCodes,
                'rating_average' => $ratingAverage,
                'rating_count' => $ratingCount,
                'reviews' => $reviews,
                'success_message' => $successMessage,
                'failure_message' => $failureMessage,
                'updated_at' => $now,
            ];

            if ($itemId > 0) {
                $index = payments_find_item_index_by_id($store, $itemId);
                if ($index < 0) {
                    throw new PaymentsApiException('آیتم برای ویرایش پیدا نشد.', 404);
                }
                $existing = is_array($store['items'][$index]) ? $store['items'][$index] : [];
                $payload['created_at'] = (string) ($existing['created_at'] ?? $now);
                $payload['sold_count'] = max(0, (int) ($existing['sold_count'] ?? 0));
                $store['items'][$index] = array_merge($existing, $payload);
                return $store['items'][$index];
            }

            $payload['created_at'] = $now;
            $payload['sold_count'] = 0;
            $store['items'][] = $payload;
            return $payload;
        });
    } catch (PaymentsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode());
    }

    dent_json_response([
        'success' => true,
        'item' => payments_owner_item_payload($saved),
        'message' => $itemId > 0 ? 'آیتم کاتالوگ ویرایش شد.' : 'آیتم کاتالوگ جدید ساخته شد.',
    ]);
}

if ($action === 'ownerToggleItem') {
    payments_api_require_method(['POST']);
    dent_require_owner();

    $itemId = max(0, (int) ($_POST['id'] ?? 0));
    if ($itemId <= 0) {
        dent_error('شناسه آیتم معتبر نیست.', 422);
    }

    $enabled = filter_var($_POST['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true;
    $status = $enabled ? PAYMENTS_ITEM_STATUS_ACTIVE : PAYMENTS_ITEM_STATUS_INACTIVE;

    try {
        $item = payments_with_store_lock(static function (array &$store) use ($itemId, $status): array {
            $index = payments_find_item_index_by_id($store, $itemId);
            if ($index < 0) {
                throw new PaymentsApiException('آیتم سفارش پیدا نشد.', 404);
            }

            $current = $store['items'][$index];
            $current['status'] = $status;
            $current['updated_at'] = dent_iso_now();
            $store['items'][$index] = $current;
            return $current;
        });
    } catch (PaymentsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode());
    }

    dent_json_response([
        'success' => true,
        'item' => payments_owner_item_payload($item),
        'message' => $enabled ? 'آیتم فعال شد.' : 'آیتم غیرفعال شد.',
    ]);
}

if ($action === 'ownerDeleteItem') {
    payments_api_require_method(['POST']);
    dent_require_owner();

    $itemId = max(0, (int) ($_POST['id'] ?? 0));
    if ($itemId <= 0) {
        dent_error('شناسه آیتم معتبر نیست.', 422);
    }

    try {
        $deleted = payments_with_store_lock(static function (array &$store) use ($itemId): array {
            $index = payments_find_item_index_by_id($store, $itemId);
            if ($index < 0) {
                throw new PaymentsApiException('آیتم سفارش پیدا نشد.', 404);
            }

            $current = $store['items'][$index];
            $current['status'] = PAYMENTS_ITEM_STATUS_DELETED;
            $current['updated_at'] = dent_iso_now();
            $store['items'][$index] = $current;
            return $current;
        });
    } catch (PaymentsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode());
    }

    dent_json_response([
        'success' => true,
        'item' => payments_owner_item_payload($deleted),
        'message' => 'آیتم از کاتالوگ حذف شد و سوابق سفارش‌های قبلی حفظ شد.',
    ]);
}

if ($action === 'ownerReconcileOrders') {
    payments_api_require_method(['POST']);
    dent_require_owner();

    $limit = max(1, min(60, (int) ($_POST['limit'] ?? 50)));
    $reconciliation = payments_api_reconcile_pending_orders([
        'limit' => $limit,
        'minAgeSeconds' => 0,
        'cooldownSeconds' => 0,
    ]);

    dent_json_response([
        'success' => true,
        'reconciliation' => $reconciliation,
        'message' => $reconciliation['verified'] > 0
            ? 'استعلام گروهی انجام شد و پرداخت‌های تاییدشده به سوابق اضافه شدند.'
            : 'استعلام گروهی انجام شد؛ پرداخت تاییدشده جدیدی پیدا نشد.',
    ]);
}

if ($action === 'ownerOrders') {
    payments_api_require_method(['GET']);
    dent_require_owner();

    $reconciliation = payments_api_reconcile_pending_orders([
        'limit' => 16,
        'minAgeSeconds' => 45,
        'cooldownSeconds' => 240,
    ]);
    $store = payments_read_store();
    $itemById = [];
    foreach ($store['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $itemById[(int) ($item['id'] ?? 0)] = $item;
    }

    $filters = payments_api_owner_order_filters_from($_GET);

    $filteredOrders = [];
    $filteredRawOrders = [];
    foreach ($store['orders'] as $order) {
        if (!is_array($order)) {
            continue;
        }
        if (!payments_api_order_matches_filters($order, $itemById, $filters)) {
            continue;
        }
        $item = $itemById[(int) ($order['item_id'] ?? 0)] ?? null;
        $filteredOrders[] = payments_owner_order_payload($order, is_array($item) ? $item : null);
        $filteredRawOrders[] = $order;
    }

    usort($filteredOrders, static function (array $left, array $right): int {
        return strcmp((string) ($right['createdAt'] ?? ''), (string) ($left['createdAt'] ?? ''));
    });

    dent_json_response([
        'success' => true,
        'filters' => $filters,
        'summary' => payments_api_orders_summary($store['orders']),
        'filteredSummary' => payments_api_orders_summary($filteredRawOrders),
        'orders' => $filteredOrders,
        'reconciliation' => $reconciliation,
    ]);
}

if ($action === 'ownerOrderDetails') {
    payments_api_require_method(['GET']);
    dent_require_owner();

    $orderId = max(0, (int) ($_GET['id'] ?? 0));
    if ($orderId <= 0) {
        dent_error('شناسه سفارش معتبر نیست.', 422);
    }

    $store = payments_read_store();
    $orderIndex = payments_find_order_index_by_id($store, $orderId);
    if ($orderIndex < 0) {
        dent_error('سفارش موردنظر پیدا نشد.', 404);
    }

    $order = $store['orders'][$orderIndex];
    $item = null;
    $itemIndex = payments_find_item_index_by_id($store, (int) ($order['item_id'] ?? 0));
    if ($itemIndex >= 0) {
        $item = $store['items'][$itemIndex];
    }

    dent_json_response([
        'success' => true,
        'order' => payments_owner_order_payload($order, $item),
        'gatewaySnapshot' => is_array($order['gateway_response_snapshot'] ?? null) ? $order['gateway_response_snapshot'] : [],
    ]);
}

if ($action === 'ownerVerifyOrder') {
    payments_api_require_method(['POST']);
    dent_require_owner();

    $orderId = max(0, (int) ($_POST['id'] ?? 0));
    if ($orderId <= 0) {
        dent_error('شناسه سفارش معتبر نیست.', 422);
    }

    $store = payments_read_store();
    $orderIndex = payments_find_order_index_by_id($store, $orderId);
    if ($orderIndex < 0 || !is_array($store['orders'][$orderIndex] ?? null)) {
        dent_error('سفارش موردنظر پیدا نشد.', 404);
    }
    $order = $store['orders'][$orderIndex];
    if ((string) ($order['status'] ?? '') === PAYMENTS_ORDER_STATUS_SUCCESS) {
        dent_json_response([
            'success' => true,
            'order' => payments_owner_order_payload($order, null),
            'message' => 'این سفارش قبلا تایید شده است.',
        ]);
    }
    $authority = dent_clean_text((string) ($order['authority'] ?? ''), 120);
    if ($authority === '') {
        dent_error('برای بازبینی درگاه، authority یا trackId در سفارش ثبت نشده است.', 422);
    }

    $verifyResult = payments_gateway_verify_payment((string) ($order['gateway'] ?? ''), $order, [
        'authority' => $authority,
        'trackId' => $authority,
        'status' => 'ok',
        'manual' => true,
    ]);
    payments_log_gateway_event('manual-verify-request', [
        'orderId' => $orderId,
        'gateway' => (string) ($order['gateway'] ?? ''),
        'result' => $verifyResult,
    ]);

    $payload = payments_with_store_lock(static function (array &$store) use ($orderId, $authority, $verifyResult): array {
        $orderIndex = payments_find_order_index_by_id($store, $orderId);
        if ($orderIndex < 0) {
            return [];
        }
        $current = $store['orders'][$orderIndex];
        $previousStatus = (string) ($current['status'] ?? PAYMENTS_ORDER_STATUS_PENDING);

        if ((bool) ($verifyResult['verified'] ?? false)) {
            $current['status'] = PAYMENTS_ORDER_STATUS_SUCCESS;
            $current['ref_id'] = dent_clean_text((string) ($verifyResult['refId'] ?? ''), 120);
            if ((string) ($current['paid_at'] ?? '') === '') {
                $current['paid_at'] = dent_iso_now();
            }
            $current['verified_at'] = dent_iso_now();
        } else {
            $nextStatus = (string) ($verifyResult['status'] ?? PAYMENTS_ORDER_STATUS_FAILED);
            if (!in_array($nextStatus, [PAYMENTS_ORDER_STATUS_PENDING, PAYMENTS_ORDER_STATUS_FAILED, PAYMENTS_ORDER_STATUS_CANCELED, PAYMENTS_ORDER_STATUS_EXPIRED], true)) {
                $nextStatus = PAYMENTS_ORDER_STATUS_FAILED;
            }
            $current['status'] = $nextStatus;
        }

        $snapshot = is_array($current['gateway_response_snapshot'] ?? null) ? $current['gateway_response_snapshot'] : [];
        $snapshot['manual_verify'] = [
            'authority' => $authority,
            'at' => dent_iso_now(),
            'result' => $verifyResult,
        ];
        $current['gateway_response_snapshot'] = $snapshot;
        $store['orders'][$orderIndex] = $current;

        $item = null;
        $itemIndex = payments_find_item_index_by_id($store, (int) ($current['item_id'] ?? 0));
        if ($itemIndex >= 0) {
            $item = $store['items'][$itemIndex];
        }
        $notification = null;
        if ($previousStatus !== (string) ($current['status'] ?? '')) {
            $notification = payments_api_append_order_notification(
                $store,
                $current,
                is_array($item) ? $item : null,
                (string) ($current['status'] ?? '') === PAYMENTS_ORDER_STATUS_SUCCESS
            );
        }

        return [
            'order' => $current,
            'item' => $item,
            'notification' => $notification,
        ];
    });

    if (is_array($payload['notification'] ?? null) && is_array($payload['order'] ?? null)) {
        payments_api_trigger_notification_hook(
            $payload['notification'],
            $payload['order'],
            is_array($payload['item'] ?? null) ? $payload['item'] : null
        );
    }

    $updatedOrder = is_array($payload['order'] ?? null) ? $payload['order'] : $order;
    dent_json_response([
        'success' => true,
        'order' => payments_owner_order_payload($updatedOrder, is_array($payload['item'] ?? null) ? $payload['item'] : null),
        'verify' => $verifyResult,
        'message' => (string) ($updatedOrder['status'] ?? '') === PAYMENTS_ORDER_STATUS_SUCCESS
            ? 'پرداخت با بازبینی درگاه تایید شد.'
            : 'بازبینی درگاه انجام شد؛ پرداخت هنوز تایید نشده است.',
    ]);
}

if ($action === 'ownerUpdateOrderStatus') {
    payments_api_require_method(['POST']);
    dent_require_owner();

    $orderId = max(0, (int) ($_POST['id'] ?? 0));
    $status = dent_clean_text((string) ($_POST['status'] ?? ''), 24);
    if ($orderId <= 0) {
        dent_error('شناسه سفارش معتبر نیست.', 422);
    }
    if (!in_array($status, [PAYMENTS_ORDER_STATUS_CANCELED, PAYMENTS_ORDER_STATUS_FAILED, PAYMENTS_ORDER_STATUS_EXPIRED, PAYMENTS_ORDER_STATUS_PENDING], true)) {
        dent_error('وضعیت سفارش برای تغییر دستی معتبر نیست.', 422);
    }

    try {
        $payload = payments_with_store_lock(static function (array &$store) use ($orderId, $status): array {
            $orderIndex = payments_find_order_index_by_id($store, $orderId);
            if ($orderIndex < 0) {
                throw new PaymentsApiException('سفارش موردنظر پیدا نشد.', 404);
            }

            $order = $store['orders'][$orderIndex];
            if ((string) ($order['status'] ?? '') === PAYMENTS_ORDER_STATUS_SUCCESS) {
                throw new PaymentsApiException('سفارش تاییدشده را نمی‌توان دستی لغو یا تغییر وضعیت داد.', 422);
            }

            $order['status'] = $status;
            $snapshot = is_array($order['gateway_response_snapshot'] ?? null) ? $order['gateway_response_snapshot'] : [];
            $snapshot['owner_status_update'] = [
                'status' => $status,
                'at' => dent_iso_now(),
            ];
            $order['gateway_response_snapshot'] = $snapshot;
            $store['orders'][$orderIndex] = $order;

            $item = null;
            $itemIndex = payments_find_item_index_by_id($store, (int) ($order['item_id'] ?? 0));
            if ($itemIndex >= 0) {
                $item = $store['items'][$itemIndex];
            }

            return [
                'order' => $order,
                'item' => $item,
            ];
        });
    } catch (PaymentsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode());
    }

    dent_json_response([
        'success' => true,
        'order' => payments_owner_order_payload($payload['order'], is_array($payload['item'] ?? null) ? $payload['item'] : null),
        'message' => 'وضعیت سفارش به‌روزرسانی شد.',
    ]);
}

if ($action === 'ownerDeleteOrder') {
    payments_api_require_method(['POST']);
    dent_require_owner();

    $orderId = max(0, (int) ($_POST['id'] ?? 0));
    if ($orderId <= 0) {
        dent_error('شناسه سفارش معتبر نیست.', 422);
    }

    try {
        $deleted = payments_with_store_lock(static function (array &$store) use ($orderId): array {
            $orderIndex = payments_find_order_index_by_id($store, $orderId);
            if ($orderIndex < 0) {
                throw new PaymentsApiException('سفارش موردنظر پیدا نشد.', 404);
            }

            $order = is_array($store['orders'][$orderIndex] ?? null) ? $store['orders'][$orderIndex] : [];
            array_splice($store['orders'], $orderIndex, 1);
            $store['notifications'] = array_values(array_filter($store['notifications'] ?? [], static function ($notification) use ($orderId): bool {
                if (!is_array($notification)) {
                    return false;
                }
                return (int) ($notification['related_order_id'] ?? 0) !== $orderId;
            }));

            return $order;
        });
    } catch (PaymentsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode());
    }

    dent_json_response([
        'success' => true,
        'deletedOrderId' => $orderId,
        'deletedOrder' => payments_owner_order_payload($deleted, null),
        'message' => 'سفارش از سوابق حذف شد.',
    ]);
}

if ($action === 'ownerResetOrderHistory') {
    payments_api_require_method(['POST']);
    dent_require_owner();

    $confirmation = trim((string) ($_POST['confirmation'] ?? ''));
    if ($confirmation !== 'DELETE_FILTERED_HISTORY') {
        dent_error('تایید حذف سوابق معتبر نیست.', 422);
    }

    $filters = payments_api_owner_order_filters_from($_POST);

    try {
        $result = payments_with_store_lock(static function (array &$store) use ($filters): array {
            $itemById = [];
            foreach ($store['items'] as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $itemById[(int) ($item['id'] ?? 0)] = $item;
            }

            $deletedIds = [];
            $keptOrders = [];
            foreach ($store['orders'] as $order) {
                if (!is_array($order)) {
                    continue;
                }
                if (payments_api_order_matches_filters($order, $itemById, $filters)) {
                    $deletedIds[] = (int) ($order['id'] ?? 0);
                    continue;
                }
                $keptOrders[] = $order;
            }

            $deletedLookup = array_fill_keys($deletedIds, true);
            $store['orders'] = array_values($keptOrders);
            $store['notifications'] = array_values(array_filter($store['notifications'] ?? [], static function ($notification) use ($deletedLookup): bool {
                if (!is_array($notification)) {
                    return false;
                }
                $relatedOrderId = (int) ($notification['related_order_id'] ?? 0);
                return $relatedOrderId <= 0 || !isset($deletedLookup[$relatedOrderId]);
            }));

            return [
                'deletedIds' => $deletedIds,
                'deletedCount' => count($deletedIds),
                'remainingCount' => count($store['orders']),
            ];
        });
    } catch (PaymentsApiException $error) {
        dent_error($error->getMessage(), $error->statusCode());
    }

    dent_json_response([
        'success' => true,
        'filters' => $filters,
        'deletedIds' => $result['deletedIds'],
        'deletedCount' => $result['deletedCount'],
        'remainingCount' => $result['remainingCount'],
        'message' => $result['deletedCount'] > 0 ? 'سوابق فیلترشده حذف شد.' : 'سفارشی برای حذف با این فیلتر وجود نداشت.',
    ]);
}

if ($action === 'ownerNotifications') {
    payments_api_require_method(['GET']);
    dent_require_owner();

    $store = payments_read_store();
    $limit = max(1, min(200, (int) ($_GET['limit'] ?? 60)));
    $notifications = [];
    foreach (array_slice($store['notifications'], 0, $limit) as $notification) {
        if (!is_array($notification)) {
            continue;
        }
        $notifications[] = $notification;
    }

    dent_json_response([
        'success' => true,
        'unreadCount' => payments_api_notifications_unread_count($notifications),
        'notifications' => $notifications,
    ]);
}

if ($action === 'ownerMarkNotificationRead') {
    payments_api_require_method(['POST']);
    dent_require_owner();

    $notificationId = max(0, (int) ($_POST['id'] ?? 0));
    $markAll = (string) ($_POST['all'] ?? '0') === '1';

    $updatedCount = payments_with_store_lock(static function (array &$store) use ($notificationId, $markAll): int {
        $count = 0;
        foreach ($store['notifications'] as $index => $notification) {
            if (!is_array($notification)) {
                continue;
            }
            if (!$markAll && (int) ($notification['id'] ?? 0) !== $notificationId) {
                continue;
            }
            if (trim((string) ($notification['read_at'] ?? '')) !== '') {
                continue;
            }
            $notification['read_at'] = dent_iso_now();
            $store['notifications'][$index] = $notification;
            $count++;
            if (!$markAll) {
                break;
            }
        }
        return $count;
    });

    dent_json_response([
        'success' => true,
        'updatedCount' => $updatedCount,
    ]);
}

dent_error('درخواست نامعتبر است.', 404);
