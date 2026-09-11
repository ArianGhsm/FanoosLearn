<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (!defined('PAYMENTS_SCHEMA_VERSION')) {
    define('PAYMENTS_SCHEMA_VERSION', 3);
}
if (!defined('PAYMENTS_ITEM_STATUS_ACTIVE')) {
    define('PAYMENTS_ITEM_STATUS_ACTIVE', 'active');
}
if (!defined('PAYMENTS_ITEM_STATUS_INACTIVE')) {
    define('PAYMENTS_ITEM_STATUS_INACTIVE', 'inactive');
}
if (!defined('PAYMENTS_ITEM_STATUS_DELETED')) {
    define('PAYMENTS_ITEM_STATUS_DELETED', 'deleted');
}
if (!defined('PAYMENTS_COLLECTION_STATUS_ACTIVE')) {
    define('PAYMENTS_COLLECTION_STATUS_ACTIVE', 'active');
}
if (!defined('PAYMENTS_COLLECTION_STATUS_INACTIVE')) {
    define('PAYMENTS_COLLECTION_STATUS_INACTIVE', 'inactive');
}
if (!defined('PAYMENTS_COLLECTION_STATUS_DELETED')) {
    define('PAYMENTS_COLLECTION_STATUS_DELETED', 'deleted');
}
if (!defined('PAYMENTS_GATEWAY_ZARINPAL')) {
    define('PAYMENTS_GATEWAY_ZARINPAL', 'zarinpal');
}
if (!defined('PAYMENTS_GATEWAY_ZIBAL')) {
    define('PAYMENTS_GATEWAY_ZIBAL', 'zibal');
}
if (!defined('PAYMENTS_GATEWAY_MOCK')) {
    define('PAYMENTS_GATEWAY_MOCK', 'mock');
}
if (!defined('PAYMENTS_ORDER_STATUS_PENDING')) {
    define('PAYMENTS_ORDER_STATUS_PENDING', 'pending');
}
if (!defined('PAYMENTS_ORDER_STATUS_SUCCESS')) {
    define('PAYMENTS_ORDER_STATUS_SUCCESS', 'success');
}
if (!defined('PAYMENTS_ORDER_STATUS_FAILED')) {
    define('PAYMENTS_ORDER_STATUS_FAILED', 'failed');
}
if (!defined('PAYMENTS_ORDER_STATUS_CANCELED')) {
    define('PAYMENTS_ORDER_STATUS_CANCELED', 'canceled');
}
if (!defined('PAYMENTS_ORDER_STATUS_EXPIRED')) {
    define('PAYMENTS_ORDER_STATUS_EXPIRED', 'expired');
}
if (!defined('PAYMENTS_NOTIFICATION_TYPE_ORDER_SUCCESS')) {
    define('PAYMENTS_NOTIFICATION_TYPE_ORDER_SUCCESS', 'order-success');
}
if (!defined('PAYMENTS_NOTIFICATION_TYPE_ORDER_FAILED')) {
    define('PAYMENTS_NOTIFICATION_TYPE_ORDER_FAILED', 'order-failed');
}

function payments_store_path(): string
{
    return dent_storage_path('payments/store.json');
}

function payments_lock_path(): string
{
    return dent_storage_path('payments/store.lock');
}

function payments_log_path(): string
{
    return dent_storage_path('payments/gateway.log');
}

function payments_default_store(): array
{
    return [
        'schemaVersion' => PAYMENTS_SCHEMA_VERSION,
        'nextItemId' => 1,
        'nextOrderId' => 1,
        'nextNotificationId' => 1,
        'nextGatewayId' => 1,
        'nextCollectionId' => 1,
        'gatewaySettings' => [
            'managed' => false,
        ],
        'gateways' => [],
        'collections' => [],
        'items' => [],
        'orders' => [],
        'notifications' => [],
    ];
}

function payments_item_categories(): array
{
    return [
        'educational_supplies' => 'ملزومات آموزشی',
        'consumables' => 'اقلام مصرفی',
        'event_registration' => 'ثبت‌نام رویداد',
        'educational_package' => 'بسته آموزشی',
        'endodontic_models' => 'دندان آموزشی اندو (اندوسیم)',
        'group_order' => 'سفارش گروهی',
    ];
}

function payments_normalize_item_category(string $value): string
{
    $key = trim(strtolower($value));
    if (isset(payments_item_categories()[$key])) {
        return $key;
    }

    $legacy = [
        'class' => 'educational_package',
        'exam' => 'educational_package',
        'event' => 'event_registration',
        'order' => 'group_order',
        'product' => 'group_order',
    ];

    return $legacy[$key] ?? 'group_order';
}

function payments_item_category_label(string $value): string
{
    $key = payments_normalize_item_category($value);
    $labels = payments_item_categories();
    return $labels[$key] ?? 'سفارش گروهی';
}

function payments_ensure_storage(): void
{
    dent_ensure_directory(dirname(payments_store_path()));

    if (!is_file(payments_store_path())) {
        dent_write_json_file(payments_store_path(), payments_default_store());
    }
}

/**
 * Holds the in-memory copy of the payments store for the lifetime of the
 * current request, so repeated reads (e.g. catalog rendering loops) don't
 * re-read and re-decode the multi-hundred-KB store file each time.
 *
 * @return array|null
 */
function &dent_payments_store_cache_slot()
{
    static $cache = null;
    return $cache;
}

function payments_read_store(): array
{
    $cache =& dent_payments_store_cache_slot();
    if (is_array($cache)) {
        return $cache;
    }

    payments_ensure_storage();

    $lock = fopen(payments_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل فضای ذخیره‌سازی پرداخت.', 500);
    }

    $store = payments_default_store();
    try {
        if (!flock($lock, LOCK_SH)) {
            throw new RuntimeException('Unable to acquire shared lock.');
        }

        $store = payments_load_store_unlocked();
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }

    $cache = $store;

    return $store;
}

/**
 * @template T
 * @param callable(array):T $callback
 * @return T
 */
function payments_with_store_lock(callable $callback)
{
    payments_ensure_storage();

    $lock = fopen(payments_lock_path(), 'c+');
    if ($lock === false) {
        dent_error('خطا در دسترسی به قفل فضای ذخیره‌سازی پرداخت.', 500);
    }

    $store = payments_default_store();
    try {
        if (!flock($lock, LOCK_EX)) {
            throw new RuntimeException('Unable to acquire exclusive lock.');
        }

        $store = payments_load_store_unlocked();
        $result = $callback($store);
        $store = payments_normalize_store($store);
        payments_save_store_unlocked($store);

        $cache =& dent_payments_store_cache_slot();
        $cache = $store;

        return $result;
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function payments_load_store_unlocked(): array
{
    $raw = dent_read_json_file(payments_store_path(), payments_default_store());
    if (
        !is_array($raw)
        || !isset($raw['items'])
        || !is_array($raw['items'])
        || !isset($raw['orders'])
        || !is_array($raw['orders'])
        || (isset($raw['schemaVersion']) && (!is_int($raw['schemaVersion']) || $raw['schemaVersion'] < 1))
    ) {
        throw new DentJsonPersistenceException(
            'PAYMENTS_STORE_SCHEMA_INVALID',
            'Existing payment store has an invalid schema'
        );
    }

    return payments_normalize_store($raw);
}

function payments_save_store_unlocked(array $store): void
{
    dent_write_json_file(payments_store_path(), payments_normalize_store($store));
}

function payments_normalize_store(array $store): array
{
    $itemsRaw = $store['items'] ?? [];
    if (!is_array($itemsRaw)) {
        $itemsRaw = [];
    }

    $ordersRaw = $store['orders'] ?? [];
    if (!is_array($ordersRaw)) {
        $ordersRaw = [];
    }

    $notificationsRaw = $store['notifications'] ?? [];
    if (!is_array($notificationsRaw)) {
        $notificationsRaw = [];
    }

    $gatewaySettings = payments_normalize_gateway_settings($store['gatewaySettings'] ?? []);
    $gatewaysRaw = $store['gateways'] ?? ($store['paymentGateways'] ?? []);
    if (!is_array($gatewaysRaw)) {
        $gatewaysRaw = [];
    }

    $collectionsRaw = $store['collections'] ?? [];
    if (!is_array($collectionsRaw)) {
        $collectionsRaw = [];
    }

    $normalizedItems = [];
    $maxItemId = 0;
    $seenSlugs = [];
    foreach ($itemsRaw as $seed) {
        if (!is_array($seed)) {
            continue;
        }

        $item = payments_normalize_item_record($seed);
        if ($item === null) {
            continue;
        }

        $itemId = (int) $item['id'];
        $maxItemId = max($maxItemId, $itemId);

        $slug = (string) $item['slug'];
        if ($slug === '' || isset($seenSlugs[$slug])) {
            $slug = 'item-' . $itemId;
            while (isset($seenSlugs[$slug])) {
                $slug .= '-x';
            }
            $item['slug'] = $slug;
        }
        $seenSlugs[$slug] = true;

        $normalizedItems[] = $item;
    }

    usort($normalizedItems, static function (array $left, array $right): int {
        return (int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0);
    });

    $normalizedOrders = [];
    $maxOrderId = 0;
    $seenOrderTokens = [];
    foreach ($ordersRaw as $seed) {
        if (!is_array($seed)) {
            continue;
        }

        $order = payments_normalize_order_record($seed);
        if ($order === null) {
            continue;
        }

        $orderId = (int) $order['id'];
        $maxOrderId = max($maxOrderId, $orderId);
        $token = (string) $order['public_token'];
        if ($token === '' || isset($seenOrderTokens[$token])) {
            $token = payments_random_token();
            $order['public_token'] = $token;
        }
        $seenOrderTokens[$token] = true;

        $normalizedOrders[] = $order;
    }

    usort($normalizedOrders, static function (array $left, array $right): int {
        return (int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0);
    });

    $normalizedNotifications = [];
    $maxNotificationId = 0;
    foreach ($notificationsRaw as $seed) {
        if (!is_array($seed)) {
            continue;
        }

        $notification = payments_normalize_notification_record($seed);
        if ($notification === null) {
            continue;
        }

        $notificationId = (int) $notification['id'];
        $maxNotificationId = max($maxNotificationId, $notificationId);
        $normalizedNotifications[] = $notification;
    }

    usort($normalizedNotifications, static function (array $left, array $right): int {
        return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
    });

    $normalizedGateways = [];
    $maxGatewayId = 0;
    $seenGatewayKeys = [];
    $hasDefaultGateway = false;
    foreach ($gatewaysRaw as $seed) {
        if (!is_array($seed)) {
            continue;
        }

        $gateway = payments_normalize_gateway_record($seed);
        if ($gateway === null) {
            continue;
        }

        $gatewayId = (int) $gateway['id'];
        $maxGatewayId = max($maxGatewayId, $gatewayId);

        $key = (string) $gateway['key'];
        if ($key === '' || isset($seenGatewayKeys[$key])) {
            $key = 'gateway-' . $gatewayId;
            while (isset($seenGatewayKeys[$key])) {
                $key .= '-x';
            }
            $gateway['key'] = $key;
        }
        $seenGatewayKeys[$key] = true;

        if ((bool) ($gateway['is_default'] ?? false)) {
            if ($hasDefaultGateway) {
                $gateway['is_default'] = false;
            } else {
                $hasDefaultGateway = true;
            }
        }

        $normalizedGateways[] = $gateway;
    }

    usort($normalizedGateways, static function (array $left, array $right): int {
        if ((bool) ($left['is_default'] ?? false) !== (bool) ($right['is_default'] ?? false)) {
            return (bool) ($left['is_default'] ?? false) ? -1 : 1;
        }
        return (int) ($left['id'] ?? 0) <=> (int) ($right['id'] ?? 0);
    });

    $normalizedCollections = [];
    $maxCollectionId = 0;
    $seenCollectionTokens = [];
    foreach ($collectionsRaw as $seed) {
        if (!is_array($seed)) {
            continue;
        }

        $collection = payments_normalize_collection_record($seed);
        if ($collection === null) {
            continue;
        }

        $collectionId = (int) $collection['id'];
        $maxCollectionId = max($maxCollectionId, $collectionId);

        $token = (string) ($collection['token'] ?? '');
        if ($token === '' || isset($seenCollectionTokens[$token])) {
            $token = payments_random_token(12);
            while (isset($seenCollectionTokens[$token])) {
                $token = payments_random_token(12);
            }
            $collection['token'] = $token;
        }
        $seenCollectionTokens[$token] = true;

        $normalizedCollections[] = $collection;
    }

    usort($normalizedCollections, static function (array $left, array $right): int {
        return strcmp((string) ($right['updated_at'] ?? ''), (string) ($left['updated_at'] ?? ''));
    });

    $normalized = [
        'schemaVersion' => PAYMENTS_SCHEMA_VERSION,
        'nextItemId' => max($maxItemId + 1, (int) ($store['nextItemId'] ?? 1), 1),
        'nextOrderId' => max($maxOrderId + 1, (int) ($store['nextOrderId'] ?? 1), 1),
        'nextNotificationId' => max($maxNotificationId + 1, (int) ($store['nextNotificationId'] ?? 1), 1),
        'nextGatewayId' => max($maxGatewayId + 1, (int) ($store['nextGatewayId'] ?? 1), 1),
        'nextCollectionId' => max($maxCollectionId + 1, (int) ($store['nextCollectionId'] ?? 1), 1),
        'gatewaySettings' => $gatewaySettings,
        'gateways' => $normalizedGateways,
        'collections' => $normalizedCollections,
        'items' => $normalizedItems,
        'orders' => $normalizedOrders,
        'notifications' => $normalizedNotifications,
    ];

    payments_recalculate_sold_counts($normalized);
    return $normalized;
}

function payments_normalize_item_record(array $seed): ?array
{
    $id = (int) ($seed['id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $slug = payments_clean_slug((string) ($seed['slug'] ?? ''));
    if ($slug === '') {
        $slug = 'item-' . $id;
    }

    $status = trim((string) ($seed['status'] ?? PAYMENTS_ITEM_STATUS_ACTIVE));
    if (!in_array($status, [PAYMENTS_ITEM_STATUS_ACTIVE, PAYMENTS_ITEM_STATUS_INACTIVE, PAYMENTS_ITEM_STATUS_DELETED], true)) {
        $status = PAYMENTS_ITEM_STATUS_INACTIVE;
    }

    $gallery = payments_normalize_string_list($seed['gallery'] ?? [], 24, 420);
    $specifications = payments_normalize_specifications($seed['specifications'] ?? []);
    $requiredFields = payments_normalize_required_fields_loose($seed['required_fields'] ?? []);
    $discountCodes = payments_normalize_discount_codes($seed['discount_codes'] ?? []);
    $reviews = payments_normalize_reviews($seed['reviews'] ?? []);

    $startsAt = payments_normalize_datetime_string((string) ($seed['starts_at'] ?? ''));
    $expiresAt = payments_normalize_datetime_string((string) ($seed['expires_at'] ?? ''));
    $capacity = payments_normalize_positive_int_nullable($seed['capacity'] ?? null, 1000000);
    $ratingAverage = (float) dent_normalize_digits((string) ($seed['rating_average'] ?? 0));
    $ratingAverage = max(0, min(5, $ratingAverage));
    $ratingCount = max(0, (int) dent_normalize_digits((string) ($seed['rating_count'] ?? 0)));

    return [
        'id' => $id,
        'slug' => $slug,
        'category' => payments_normalize_item_category((string) ($seed['category'] ?? '')),
        'title' => dent_clean_text((string) ($seed['title'] ?? ''), 140),
        'short_description' => dent_clean_text((string) ($seed['short_description'] ?? ''), 460),
        'full_description' => dent_clean_text((string) ($seed['full_description'] ?? ''), 6000),
        'hero_image' => dent_clean_text((string) ($seed['hero_image'] ?? ''), 420),
        'gallery' => $gallery,
        'specifications' => $specifications,
        'price' => max(0, (int) ($seed['price'] ?? 0)),
        'status' => $status,
        'starts_at' => $startsAt,
        'expires_at' => $expiresAt,
        'capacity' => $capacity,
        'max_quantity_per_order' => max(1, min(99, (int) dent_normalize_digits((string) ($seed['max_quantity_per_order'] ?? 1)))),
        'sold_count' => max(0, (int) ($seed['sold_count'] ?? 0)),
        'required_fields' => $requiredFields,
        'audience_note' => dent_clean_text((string) ($seed['audience_note'] ?? 'دانشجویان دندانپزشکی ورودی ۱۴۰۲'), 220),
        'delivery_note' => dent_clean_text((string) ($seed['delivery_note'] ?? 'تحویل یا استفاده در محدوده دانشگاه علوم پزشکی تهران هماهنگ می‌شود.'), 360),
        'support_note' => dent_clean_text((string) ($seed['support_note'] ?? 'برای پیگیری سفارش با نماینده یا مالک سایت تماس بگیرید.'), 360),
        'allow_cancellation' => filter_var($seed['allow_cancellation'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === true,
        'discount_codes' => $discountCodes,
        'rating_average' => $ratingAverage,
        'rating_count' => $ratingCount,
        'reviews' => $reviews,
        'success_message' => dent_clean_text((string) ($seed['success_message'] ?? 'پرداخت شما با موفقیت ثبت شد.'), 600),
        'failure_message' => dent_clean_text((string) ($seed['failure_message'] ?? 'پرداخت شما تایید نشد.'), 600),
        'created_at' => payments_normalize_datetime_string((string) ($seed['created_at'] ?? dent_iso_now()), dent_iso_now()),
        'updated_at' => payments_normalize_datetime_string((string) ($seed['updated_at'] ?? dent_iso_now()), dent_iso_now()),
    ];
}

function payments_normalize_gateway_settings($value): array
{
    $settings = is_array($value) ? $value : [];
    $managed = filter_var($settings['managed'] ?? false, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

    return [
        'managed' => $managed === true,
    ];
}

function payments_gateway_key_clean(string $value): string
{
    $value = trim(strtolower($value));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
    $value = trim($value, '-_');
    if ($value === '') {
        return '';
    }

    if (strlen($value) > 60) {
        $value = substr($value, 0, 60);
        $value = trim($value, '-_');
    }

    return $value;
}

function payments_gateway_provider_clean(string $value): string
{
    $value = trim(strtolower($value));
    if ($value === PAYMENTS_GATEWAY_ZIBAL) {
        return PAYMENTS_GATEWAY_ZIBAL;
    }
    if ($value === PAYMENTS_GATEWAY_ZARINPAL) {
        return PAYMENTS_GATEWAY_ZARINPAL;
    }
    if ($value === PAYMENTS_GATEWAY_MOCK) {
        return PAYMENTS_GATEWAY_MOCK;
    }

    return '';
}

function payments_gateway_public_label(string $provider): string
{
    $provider = payments_gateway_provider_clean($provider);
    if ($provider === PAYMENTS_GATEWAY_MOCK) {
        return 'پرداخت آزمایشی';
    }

    return 'پرداخت آنلاین';
}

function payments_gateway_provider_label_from_type(string $provider): string
{
    $provider = payments_gateway_provider_clean($provider);
    if ($provider === PAYMENTS_GATEWAY_ZIBAL) {
        return 'درگاه زیبال';
    }
    if ($provider === PAYMENTS_GATEWAY_ZARINPAL) {
        return 'درگاه زرین‌پال';
    }
    if ($provider === PAYMENTS_GATEWAY_MOCK) {
        return 'درگاه آزمایشی';
    }

    return 'درگاه پرداخت';
}

function payments_gateway_record_credential(array $gateway): string
{
    $merchantId = trim((string) ($gateway['merchant_id'] ?? ''));
    if ($merchantId !== '') {
        return $merchantId;
    }

    return trim((string) ($gateway['api_key'] ?? ''));
}

function payments_gateway_is_record_configured(array $gateway): bool
{
    $provider = payments_gateway_provider_clean((string) ($gateway['provider'] ?? ''));
    if ($provider === PAYMENTS_GATEWAY_MOCK) {
        return true;
    }

    return payments_gateway_record_credential($gateway) !== '';
}

function payments_normalize_gateway_record(array $seed): ?array
{
    $id = (int) ($seed['id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $provider = payments_gateway_provider_clean((string) ($seed['provider'] ?? ($seed['provider_type'] ?? ($seed['type'] ?? ''))));
    if ($provider === '') {
        $provider = payments_gateway_provider_clean((string) ($seed['key'] ?? ''));
    }
    if ($provider === '') {
        return null;
    }

    $key = payments_gateway_key_clean((string) ($seed['key'] ?? ''));
    if ($key === '') {
        $key = payments_gateway_key_clean($provider . '-' . $id);
    }
    if ($key === '') {
        return null;
    }

    $enabled = filter_var($seed['is_enabled'] ?? ($seed['isEnabled'] ?? true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $isDefault = filter_var($seed['is_default'] ?? ($seed['isDefault'] ?? false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

    $label = dent_clean_text((string) ($seed['label'] ?? ''), 80);
    if ($label === '') {
        $label = payments_gateway_public_label($provider);
    }

    $providerLabel = dent_clean_text((string) ($seed['provider_label'] ?? ($seed['providerLabel'] ?? '')), 120);
    if ($providerLabel === '') {
        $providerLabel = payments_gateway_provider_label_from_type($provider);
    }

    $now = dent_iso_now();

    return [
        'id' => $id,
        'key' => $key,
        'provider' => $provider,
        'label' => $label,
        'provider_label' => $providerLabel,
        'icon' => dent_clean_text((string) ($seed['icon'] ?? ''), 8),
        'merchant_id' => dent_clean_text((string) ($seed['merchant_id'] ?? ($seed['merchantId'] ?? ($seed['credential'] ?? ''))), 260),
        'api_key' => dent_clean_text((string) ($seed['api_key'] ?? ($seed['apiKey'] ?? '')), 320),
        'request_url' => dent_clean_text((string) ($seed['request_url'] ?? ($seed['requestUrl'] ?? '')), 420),
        'verify_url' => dent_clean_text((string) ($seed['verify_url'] ?? ($seed['verifyUrl'] ?? '')), 420),
        'start_url' => dent_clean_text((string) ($seed['start_url'] ?? ($seed['startUrl'] ?? '')), 420),
        'is_enabled' => $enabled !== false,
        'is_default' => $isDefault === true,
        'created_at' => payments_normalize_datetime_string((string) ($seed['created_at'] ?? ($seed['createdAt'] ?? $now)), $now),
        'updated_at' => payments_normalize_datetime_string((string) ($seed['updated_at'] ?? ($seed['updatedAt'] ?? $now)), $now),
    ];
}

function payments_clean_collection_token(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^A-Za-z0-9_-]+/', '', $value) ?? '';
    return substr($value, 0, 80);
}

function payments_normalize_collection_record(array $seed): ?array
{
    $id = (int) ($seed['id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $title = dent_clean_text((string) ($seed['title'] ?? ''), 160);
    if ($title === '') {
        return null;
    }

    $amount = max(0, (int) dent_normalize_digits((string) ($seed['amount'] ?? '0')));
    if ($amount <= 0) {
        return null;
    }

    $status = trim((string) ($seed['status'] ?? PAYMENTS_COLLECTION_STATUS_ACTIVE));
    if (!in_array($status, [PAYMENTS_COLLECTION_STATUS_ACTIVE, PAYMENTS_COLLECTION_STATUS_INACTIVE, PAYMENTS_COLLECTION_STATUS_DELETED], true)) {
        $status = PAYMENTS_COLLECTION_STATUS_INACTIVE;
    }

    $token = payments_clean_collection_token((string) ($seed['token'] ?? ''));
    if ($token === '') {
        $token = payments_random_token(12);
    }

    $allowGuestPayments = filter_var($seed['allow_guest_payments'] ?? ($seed['allowGuestPayments'] ?? false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $collectPayerName = filter_var($seed['collect_payer_name'] ?? ($seed['collectPayerName'] ?? true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $collectPayerPhone = filter_var($seed['collect_payer_phone'] ?? ($seed['collectPayerPhone'] ?? true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    $collectPayerStudentNumber = filter_var($seed['collect_payer_student_number'] ?? ($seed['collectPayerStudentNumber'] ?? false), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

    $now = dent_iso_now();
    return [
        'id' => $id,
        'token' => $token,
        'title' => $title,
        'description' => dent_clean_text((string) ($seed['description'] ?? ''), 1200),
        'image_url' => dent_clean_text((string) ($seed['image_url'] ?? ($seed['imageUrl'] ?? '')), 420),
        'amount' => $amount,
        'discount_codes' => payments_normalize_collection_discount_codes($seed['discount_codes'] ?? ($seed['discountCodes'] ?? [])),
        'status' => $status,
        'gateway' => payments_gateway_key_clean((string) ($seed['gateway'] ?? '')),
        'allow_guest_payments' => $allowGuestPayments === true,
        'collect_payer_name' => $collectPayerName !== false,
        'collect_payer_phone' => $collectPayerPhone !== false,
        'collect_payer_student_number' => $collectPayerStudentNumber === true,
        'success_message' => dent_clean_text((string) ($seed['success_message'] ?? ($seed['successMessage'] ?? '')), 600),
        'failure_message' => dent_clean_text((string) ($seed['failure_message'] ?? ($seed['failureMessage'] ?? '')), 600),
        'created_at' => payments_normalize_datetime_string((string) ($seed['created_at'] ?? ($seed['createdAt'] ?? $now)), $now),
        'updated_at' => payments_normalize_datetime_string((string) ($seed['updated_at'] ?? ($seed['updatedAt'] ?? $now)), $now),
    ];
}

function payments_normalize_order_record(array $seed): ?array
{
    $id = (int) ($seed['id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $status = trim((string) ($seed['status'] ?? PAYMENTS_ORDER_STATUS_PENDING));
    if (!in_array(
        $status,
        [
            PAYMENTS_ORDER_STATUS_PENDING,
            PAYMENTS_ORDER_STATUS_SUCCESS,
            PAYMENTS_ORDER_STATUS_FAILED,
            PAYMENTS_ORDER_STATUS_CANCELED,
            PAYMENTS_ORDER_STATUS_EXPIRED,
        ],
        true
    )) {
        $status = PAYMENTS_ORDER_STATUS_PENDING;
    }

    $snapshot = $seed['gateway_response_snapshot'] ?? [];
    if (!is_array($snapshot)) {
        $snapshot = [];
    }

    $extraFormData = $seed['extra_form_data'] ?? [];
    if (!is_array($extraFormData)) {
        $extraFormData = [];
    }

    $publicToken = dent_clean_text((string) ($seed['public_token'] ?? ''), 120);
    if ($publicToken === '') {
        $publicToken = payments_random_token();
    }

    $cartItems = payments_normalize_cart_order_items($seed['cart_items'] ?? ($seed['cartItems'] ?? []));
    $fallbackItemId = max(0, (int) ($seed['item_id'] ?? 0));
    $fallbackQuantity = max(1, min(99, (int) dent_normalize_digits((string) ($seed['quantity'] ?? 1))));
    $fallbackUnitPrice = max(0, (int) ($seed['unit_price'] ?? ($seed['amount'] ?? 0)));
    $fallbackSubtotal = max(0, (int) ($seed['subtotal'] ?? ($seed['amount'] ?? 0)));
    $fallbackDiscountAmount = max(0, (int) ($seed['discount_amount'] ?? 0));
    $fallbackAmount = max(0, (int) ($seed['amount'] ?? 0));
    if ($cartItems === [] && $fallbackItemId > 0) {
        $cartItems[] = [
            'item_id' => $fallbackItemId,
            'slug' => '',
            'title' => '',
            'quantity' => $fallbackQuantity,
            'unit_price' => $fallbackUnitPrice,
            'subtotal' => $fallbackSubtotal,
            'discount_code' => dent_clean_text((string) ($seed['discount_code'] ?? ''), 40),
            'discount_amount' => $fallbackDiscountAmount,
            'amount' => $fallbackAmount,
            'extra_form_data' => payments_normalize_extra_form_data($extraFormData),
        ];
    }

    $createdAt = payments_normalize_datetime_string((string) ($seed['created_at'] ?? dent_iso_now()), dent_iso_now());
    $paymentStartedAt = payments_normalize_datetime_string((string) ($seed['payment_started_at'] ?? ($seed['paymentStartedAt'] ?? '')), '');
    $paidAt = payments_normalize_datetime_string((string) ($seed['paid_at'] ?? ''), '');
    $verifiedAt = payments_normalize_datetime_string((string) ($seed['verified_at'] ?? ''), '');
    $updatedAt = payments_normalize_datetime_string((string) ($seed['updated_at'] ?? ($seed['updatedAt'] ?? '')), '');
    foreach ([$createdAt, $paymentStartedAt, $paidAt, $verifiedAt] as $candidateAt) {
        if ($candidateAt !== '' && ($updatedAt === '' || strcmp($candidateAt, $updatedAt) > 0)) {
            $updatedAt = $candidateAt;
        }
    }

    return [
        'id' => $id,
        'item_id' => $fallbackItemId,
        'user_id' => dent_normalize_student_number((string) ($seed['user_id'] ?? '')),
        'payer_name' => dent_clean_text((string) ($seed['payer_name'] ?? ''), 120),
        'payer_phone' => payments_normalize_phone((string) ($seed['payer_phone'] ?? '')),
        'payer_student_number' => dent_normalize_student_number((string) ($seed['payer_student_number'] ?? '')),
        'extra_form_data' => payments_normalize_extra_form_data($extraFormData),
        'cart_items' => $cartItems,
        'quantity' => $fallbackQuantity,
        'unit_price' => $fallbackUnitPrice,
        'subtotal' => $fallbackSubtotal,
        'discount_code' => dent_clean_text((string) ($seed['discount_code'] ?? ''), 40),
        'discount_amount' => $fallbackDiscountAmount,
        'amount' => $fallbackAmount,
        'gateway' => dent_clean_text((string) ($seed['gateway'] ?? ''), 32),
        'authority' => dent_clean_text((string) ($seed['authority'] ?? ''), 120),
        'ref_id' => dent_clean_text((string) ($seed['ref_id'] ?? ''), 120),
        'status' => $status,
        'gateway_response_snapshot' => $snapshot,
        'created_at' => $createdAt,
        'payment_started_at' => $paymentStartedAt,
        'paid_at' => $paidAt,
        'verified_at' => $verifiedAt,
        'updated_at' => $updatedAt,
        'expires_at' => payments_normalize_datetime_string((string) ($seed['expires_at'] ?? ($seed['expiresAt'] ?? '')), ''),
        'public_token' => $publicToken,
    ];
}

function payments_normalize_cart_order_items($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $normalized = [];
    foreach ($value as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $itemId = max(0, (int) ($entry['item_id'] ?? ($entry['itemId'] ?? 0)));
        $quantity = max(1, min(99, (int) dent_normalize_digits((string) ($entry['quantity'] ?? 1))));
        $unitPrice = max(0, (int) ($entry['unit_price'] ?? ($entry['unitPrice'] ?? 0)));
        $subtotal = max(0, (int) ($entry['subtotal'] ?? ($unitPrice * $quantity)));
        $discountAmount = max(0, (int) ($entry['discount_amount'] ?? ($entry['discountAmount'] ?? 0)));
        $amount = max(0, (int) ($entry['amount'] ?? max(0, $subtotal - $discountAmount)));

        if ($itemId <= 0 && $amount <= 0) {
            continue;
        }

        $extraFormData = $entry['extra_form_data'] ?? ($entry['extraFormData'] ?? []);
        if (!is_array($extraFormData)) {
            $extraFormData = [];
        }

        $normalized[] = [
            'item_id' => $itemId,
            'slug' => payments_clean_slug((string) ($entry['slug'] ?? '')),
            'title' => dent_clean_text((string) ($entry['title'] ?? ''), 160),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'discount_code' => dent_clean_text((string) ($entry['discount_code'] ?? ($entry['discountCode'] ?? '')), 40),
            'discount_amount' => min($subtotal, $discountAmount),
            'amount' => min($subtotal, $amount),
            'extra_form_data' => payments_normalize_extra_form_data($extraFormData),
        ];

        if (count($normalized) >= 40) {
            break;
        }
    }

    return $normalized;
}

function payments_normalize_notification_record(array $seed): ?array
{
    $id = (int) ($seed['id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    return [
        'id' => $id,
        'type' => dent_clean_text((string) ($seed['type'] ?? ''), 40),
        'title' => dent_clean_text((string) ($seed['title'] ?? ''), 180),
        'body' => dent_clean_text((string) ($seed['body'] ?? ''), 900),
        'related_order_id' => max(0, (int) ($seed['related_order_id'] ?? 0)),
        'read_at' => payments_normalize_datetime_string((string) ($seed['read_at'] ?? ''), ''),
        'created_at' => payments_normalize_datetime_string((string) ($seed['created_at'] ?? dent_iso_now()), dent_iso_now()),
    ];
}

function payments_random_token(int $bytes = 18): string
{
    try {
        return rtrim(strtr(base64_encode(random_bytes(max(8, $bytes))), '+/', '-_'), '=');
    } catch (Throwable $error) {
        return 'tok-' . sha1((string) mt_rand() . '|' . microtime(true));
    }
}

function payments_clean_slug(string $value): string
{
    $value = dent_force_utf8($value);
    $value = trim(strtolower($value));
    if ($value === '') {
        return '';
    }

    $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
    $value = trim($value, '-');
    if ($value === '') {
        return '';
    }

    if (strlen($value) > 80) {
        $value = substr($value, 0, 80);
        $value = trim($value, '-');
    }

    return $value;
}

function payments_normalize_datetime_string(string $value, string $fallback = ''): string
{
    $value = trim($value);
    if ($value === '') {
        return $fallback;
    }

    $parsed = strtotime($value);
    if ($parsed === false) {
        return $fallback;
    }

    return date('c', $parsed);
}

function payments_normalize_positive_int_nullable($value, int $max): ?int
{
    if ($value === null || $value === '') {
        return null;
    }

    $normalized = (int) dent_normalize_digits((string) $value);
    if ($normalized <= 0) {
        return null;
    }

    return min($max, $normalized);
}

function payments_normalize_phone(string $value): string
{
    $digits = preg_replace('/\D+/u', '', dent_normalize_digits($value)) ?? '';
    if ($digits === '') {
        return '';
    }

    if (str_starts_with($digits, '0098')) {
        $digits = substr($digits, 4);
    } elseif (str_starts_with($digits, '98')) {
        $digits = substr($digits, 2);
    }

    if (str_starts_with($digits, '9') && strlen($digits) === 10) {
        $digits = '0' . $digits;
    }

    return $digits;
}

function payments_normalize_extra_form_data(array $value): array
{
    $output = [];
    foreach ($value as $key => $fieldValue) {
        $fieldName = dent_clean_text((string) $key, 60);
        if ($fieldName === '') {
            continue;
        }
        $output[$fieldName] = dent_clean_text((string) $fieldValue, 1000);
    }

    return $output;
}

function payments_normalize_string_list($value, int $maxItems, int $maxLength): array
{
    $items = [];
    if (is_array($value)) {
        $items = $value;
    } elseif (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $items = $decoded;
            } else {
                $items = preg_split('/\r\n|\r|\n|,/u', $trimmed) ?: [];
            }
        }
    }

    $normalized = [];
    $seen = [];
    foreach ($items as $item) {
        $clean = dent_clean_text((string) $item, $maxLength);
        if ($clean === '' || isset($seen[$clean])) {
            continue;
        }
        $seen[$clean] = true;
        $normalized[] = $clean;
        if (count($normalized) >= $maxItems) {
            break;
        }
    }

    return $normalized;
}

function payments_normalize_specifications($value): array
{
    $specs = [];
    if (is_array($value)) {
        $specs = $value;
    } elseif (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $specs = $decoded;
            } else {
                $lines = preg_split('/\r\n|\r|\n/u', $trimmed) ?: [];
                foreach ($lines as $line) {
                    $parts = preg_split('/[:|]/u', (string) $line, 2);
                    $label = dent_clean_text((string) ($parts[0] ?? ''), 120);
                    $textValue = dent_clean_text((string) ($parts[1] ?? ''), 220);
                    if ($label !== '' && $textValue !== '') {
                        $specs[] = ['label' => $label, 'value' => $textValue];
                    }
                }
            }
        }
    }

    $normalized = [];
    foreach ($specs as $spec) {
        if (!is_array($spec)) {
            continue;
        }

        $label = dent_clean_text((string) ($spec['label'] ?? ''), 120);
        $textValue = dent_clean_text((string) ($spec['value'] ?? ''), 220);
        if ($label === '' || $textValue === '') {
            continue;
        }

        $normalized[] = [
            'label' => $label,
            'value' => $textValue,
        ];

        if (count($normalized) >= 30) {
            break;
        }
    }

    return $normalized;
}

function payments_normalize_discount_codes($value): array
{
    $codes = [];
    if (is_array($value)) {
        $codes = $value;
    } elseif (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $codes = $decoded;
            }
        }
    }

    $normalized = [];
    $seen = [];
    foreach ($codes as $code) {
        if (!is_array($code)) {
            continue;
        }

        $rawCode = payments_normalize_discount_code_text((string) ($code['code'] ?? ''));
        if ($rawCode === '' || isset($seen[$rawCode])) {
            continue;
        }

        $type = trim(strtolower((string) ($code['type'] ?? 'fixed')));
        if (!in_array($type, ['fixed', 'percent'], true)) {
            $type = 'fixed';
        }

        $amount = max(0, (int) dent_normalize_digits((string) ($code['amount'] ?? 0)));
        if ($amount <= 0) {
            continue;
        }
        if ($type === 'percent') {
            $amount = min(95, $amount);
        }

        $normalized[] = [
            'code' => $rawCode,
            'type' => $type,
            'amount' => $amount,
            'label' => dent_clean_text((string) ($code['label'] ?? ''), 120),
            'expires_at' => payments_normalize_datetime_string((string) ($code['expiresAt'] ?? ($code['expires_at'] ?? '')), ''),
            'is_enabled' => !array_key_exists('isEnabled', $code) || (bool) $code['isEnabled'],
        ];
        $seen[$rawCode] = true;
        if (count($normalized) >= 30) {
            break;
        }
    }

    return $normalized;
}

function payments_normalize_discount_code_text(string $value): string
{
    $value = dent_clean_text($value, 40);
    if ($value === '') {
        return '';
    }

    return strtoupper(preg_replace('/\s+/u', '', $value) ?? '');
}

function payments_normalize_collection_discount_codes($value): array
{
    $codes = [];
    if (is_array($value)) {
        $codes = $value;
    } elseif (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $codes = $decoded;
            }
        }
    }

    $normalized = [];
    $seen = [];
    foreach ($codes as $code) {
        if (!is_array($code)) {
            continue;
        }

        $rawCode = payments_normalize_discount_code_text((string) ($code['code'] ?? ''));
        if ($rawCode === '' || isset($seen[$rawCode])) {
            continue;
        }

        $type = trim(strtolower((string) ($code['type'] ?? 'fixed')));
        if (!in_array($type, ['fixed', 'percent'], true)) {
            $type = 'fixed';
        }

        $amount = max(0, (int) dent_normalize_digits((string) ($code['amount'] ?? 0)));
        if ($amount <= 0) {
            continue;
        }
        if ($type === 'percent') {
            $amount = min(95, $amount);
        }

        $maxUses = payments_normalize_positive_int_nullable($code['max_uses'] ?? ($code['maxUses'] ?? null), 1000000);
        $isEnabledRaw = $code['is_enabled'] ?? ($code['isEnabled'] ?? true);
        $isEnabled = filter_var($isEnabledRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        $normalized[] = [
            'code' => $rawCode,
            'type' => $type,
            'amount' => $amount,
            'label' => dent_clean_text((string) ($code['label'] ?? ''), 120),
            'expires_at' => payments_normalize_datetime_string((string) ($code['expires_at'] ?? ($code['expiresAt'] ?? '')), ''),
            'is_enabled' => $isEnabled !== false,
            'max_uses' => $maxUses,
            'student_number' => dent_normalize_student_number((string) ($code['student_number'] ?? ($code['studentNumber'] ?? ''))),
        ];
        $seen[$rawCode] = true;

        if (count($normalized) >= 30) {
            break;
        }
    }

    return $normalized;
}

function payments_normalize_reviews($value): array
{
    $reviews = [];
    if (is_array($value)) {
        $reviews = $value;
    } elseif (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $reviews = $decoded;
            }
        }
    }

    $normalized = [];
    foreach ($reviews as $review) {
        if (!is_array($review)) {
            continue;
        }

        $name = dent_clean_text((string) ($review['name'] ?? ($review['reviewer'] ?? 'کاربر')), 80);
        $body = dent_clean_text((string) ($review['body'] ?? ($review['text'] ?? '')), 700);
        $rating = (float) dent_normalize_digits((string) ($review['rating'] ?? 0));
        $rating = max(1, min(5, $rating));
        if ($body === '') {
            continue;
        }

        $normalized[] = [
            'name' => $name !== '' ? $name : 'کاربر',
            'rating' => round($rating, 1),
            'body' => $body,
            'created_at' => payments_normalize_datetime_string((string) ($review['createdAt'] ?? ($review['created_at'] ?? '')), ''),
        ];
        if (count($normalized) >= 12) {
            break;
        }
    }

    return $normalized;
}

function payments_normalize_required_fields_loose($value): array
{
    $fields = [];
    if (is_array($value)) {
        $fields = $value;
    } elseif (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                $fields = $decoded;
            }
        }
    }

    $normalized = [];
    $seenNames = [];
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }

        $name = dent_clean_text((string) ($field['name'] ?? ''), 60);
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9_]/', '', $name) ?? '';
        if ($name === '' || isset($seenNames[$name])) {
            continue;
        }

        $type = trim(strtolower((string) ($field['type'] ?? 'text')));
        if (!in_array($type, ['text', 'tel', 'number', 'textarea', 'select'], true)) {
            $type = 'text';
        }

        $label = dent_clean_text((string) ($field['label'] ?? ''), 80);
        if ($label === '') {
            continue;
        }

        $options = payments_normalize_string_list($field['options'] ?? [], 20, 80);
        if ($type === 'select' && $options === []) {
            continue;
        }

        $maxLength = (int) ($field['maxLength'] ?? 120);
        $maxLength = max(10, min(1000, $maxLength));

        $normalized[] = [
            'name' => $name,
            'label' => $label,
            'type' => $type,
            'required' => (bool) ($field['required'] ?? true),
            'placeholder' => dent_clean_text((string) ($field['placeholder'] ?? ''), 120),
            'maxLength' => $maxLength,
            'options' => $options,
        ];

        $seenNames[$name] = true;
        if (count($normalized) >= 12) {
            break;
        }
    }

    return $normalized;
}

function payments_find_item_index_by_id(array $store, int $itemId): int
{
    foreach ($store['items'] as $index => $item) {
        if ((int) ($item['id'] ?? 0) === $itemId) {
            return (int) $index;
        }
    }
    return -1;
}

function payments_find_item_index_by_slug(array $store, string $slug): int
{
    foreach ($store['items'] as $index => $item) {
        if ((string) ($item['slug'] ?? '') === $slug) {
            return (int) $index;
        }
    }
    return -1;
}

function payments_find_order_index_by_id(array $store, int $orderId): int
{
    foreach ($store['orders'] as $index => $order) {
        if ((int) ($order['id'] ?? 0) === $orderId) {
            return (int) $index;
        }
    }
    return -1;
}

function payments_find_order_index_by_token(array $store, string $token): int
{
    foreach ($store['orders'] as $index => $order) {
        if ((string) ($order['public_token'] ?? '') === $token) {
            return (int) $index;
        }
    }
    return -1;
}

function payments_find_collection_index_by_id(array $store, int $collectionId): int
{
    foreach ($store['collections'] as $index => $collection) {
        if ((int) ($collection['id'] ?? 0) === $collectionId) {
            return (int) $index;
        }
    }
    return -1;
}

function payments_find_collection_index_by_token(array $store, string $token): int
{
    $token = payments_clean_collection_token($token);
    if ($token === '') {
        return -1;
    }

    foreach ($store['collections'] as $index => $collection) {
        if ((string) ($collection['token'] ?? '') === $token) {
            return (int) $index;
        }
    }
    return -1;
}

function payments_next_item_id(array &$store): int
{
    $next = max(1, (int) ($store['nextItemId'] ?? 1));
    $store['nextItemId'] = $next + 1;
    return $next;
}

function payments_next_order_id(array &$store): int
{
    $next = max(1, (int) ($store['nextOrderId'] ?? 1));
    $store['nextOrderId'] = $next + 1;
    return $next;
}

function payments_next_notification_id(array &$store): int
{
    $next = max(1, (int) ($store['nextNotificationId'] ?? 1));
    $store['nextNotificationId'] = $next + 1;
    return $next;
}

function payments_next_gateway_id(array &$store): int
{
    $next = max(1, (int) ($store['nextGatewayId'] ?? 1));
    $store['nextGatewayId'] = $next + 1;
    return $next;
}

function payments_next_collection_id(array &$store): int
{
    $next = max(1, (int) ($store['nextCollectionId'] ?? 1));
    $store['nextCollectionId'] = $next + 1;
    return $next;
}

function payments_recalculate_sold_counts(array &$store): void
{
    $successByItem = [];
    foreach ($store['orders'] as $order) {
        if ((string) ($order['status'] ?? '') !== PAYMENTS_ORDER_STATUS_SUCCESS) {
            continue;
        }

        $cartItems = payments_normalize_cart_order_items($order['cart_items'] ?? []);
        if ($cartItems !== []) {
            foreach ($cartItems as $cartItem) {
                $itemId = max(0, (int) ($cartItem['item_id'] ?? 0));
                if ($itemId <= 0) {
                    continue;
                }
                $quantity = max(1, (int) ($cartItem['quantity'] ?? 1));
                $successByItem[$itemId] = ($successByItem[$itemId] ?? 0) + $quantity;
            }
            continue;
        }

        $itemId = max(0, (int) ($order['item_id'] ?? 0));
        if ($itemId <= 0) {
            continue;
        }
        $quantity = max(1, (int) ($order['quantity'] ?? 1));
        $successByItem[$itemId] = ($successByItem[$itemId] ?? 0) + $quantity;
    }

    foreach ($store['items'] as $index => $item) {
        $itemId = max(0, (int) ($item['id'] ?? 0));
        $store['items'][$index]['sold_count'] = $successByItem[$itemId] ?? 0;
    }
}

function payments_item_public_state(array $item): array
{
    $status = (string) ($item['status'] ?? PAYMENTS_ITEM_STATUS_INACTIVE);
    if ($status === PAYMENTS_ITEM_STATUS_DELETED) {
        return [
            'key' => 'deleted',
            'label' => 'حذف‌شده',
            'isPayable' => false,
        ];
    }
    if ($status !== PAYMENTS_ITEM_STATUS_ACTIVE) {
        return [
            'key' => 'inactive',
            'label' => 'غیرفعال',
            'isPayable' => false,
        ];
    }

    $now = time();
    $startsAt = payments_timestamp_or_null((string) ($item['starts_at'] ?? ''));
    if ($startsAt !== null && $startsAt > $now) {
        return [
            'key' => 'upcoming',
            'label' => 'شروع‌نشده',
            'isPayable' => false,
        ];
    }

    $expiresAt = payments_timestamp_or_null((string) ($item['expires_at'] ?? ''));
    if ($expiresAt !== null && $expiresAt <= $now) {
        return [
            'key' => 'expired',
            'label' => 'منقضی‌شده',
            'isPayable' => false,
        ];
    }

    $capacity = payments_normalize_positive_int_nullable($item['capacity'] ?? null, 1000000);
    $soldCount = max(0, (int) ($item['sold_count'] ?? 0));
    if ($capacity !== null && $soldCount >= $capacity) {
        return [
            'key' => 'full',
            'label' => 'تکمیل ظرفیت',
            'isPayable' => false,
        ];
    }

    return [
        'key' => 'active',
        'label' => 'فعال',
        'isPayable' => true,
    ];
}

function payments_timestamp_or_null(string $value): ?int
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $parsed = strtotime($value);
    if ($parsed === false) {
        return null;
    }

    return (int) $parsed;
}

function payments_public_item_payload(array $item): array
{
    $state = payments_item_public_state($item);
    $ratingAverage = (float) ($item['rating_average'] ?? 0);
    $ratingCount = max(0, (int) ($item['rating_count'] ?? 0));
    $reviews = is_array($item['reviews'] ?? null) ? array_values($item['reviews']) : [];
    if ($ratingAverage <= 0 && $reviews !== []) {
        $sum = 0.0;
        foreach ($reviews as $review) {
            $sum += (float) ($review['rating'] ?? 0);
        }
        $ratingAverage = count($reviews) > 0 ? $sum / count($reviews) : 0;
        $ratingCount = max($ratingCount, count($reviews));
    }

    return [
        'id' => (int) ($item['id'] ?? 0),
        'slug' => (string) ($item['slug'] ?? ''),
        'category' => payments_normalize_item_category((string) ($item['category'] ?? '')),
        'categoryLabel' => payments_item_category_label((string) ($item['category'] ?? '')),
        'title' => (string) ($item['title'] ?? ''),
        'shortDescription' => (string) ($item['short_description'] ?? ''),
        'fullDescription' => (string) ($item['full_description'] ?? ''),
        'heroImage' => (string) ($item['hero_image'] ?? ''),
        'gallery' => is_array($item['gallery'] ?? null) ? array_values($item['gallery']) : [],
        'specifications' => is_array($item['specifications'] ?? null) ? array_values($item['specifications']) : [],
        'price' => max(0, (int) ($item['price'] ?? 0)),
        'status' => (string) ($item['status'] ?? PAYMENTS_ITEM_STATUS_INACTIVE),
        'state' => $state,
        'startsAt' => (string) ($item['starts_at'] ?? ''),
        'expiresAt' => (string) ($item['expires_at'] ?? ''),
        'capacity' => payments_normalize_positive_int_nullable($item['capacity'] ?? null, 1000000),
        'maxQuantityPerOrder' => max(1, min(99, (int) ($item['max_quantity_per_order'] ?? 1))),
        'soldCount' => max(0, (int) ($item['sold_count'] ?? 0)),
        'remainingCapacity' => payments_item_remaining_capacity($item),
        'requiredFields' => is_array($item['required_fields'] ?? null) ? array_values($item['required_fields']) : [],
        'audienceNote' => (string) ($item['audience_note'] ?? ''),
        'deliveryNote' => (string) ($item['delivery_note'] ?? ''),
        'supportNote' => (string) ($item['support_note'] ?? ''),
        'allowCancellation' => (bool) ($item['allow_cancellation'] ?? false),
        'hasDiscountCodes' => payments_normalize_discount_codes($item['discount_codes'] ?? []) !== [],
        'ratingAverage' => round(max(0, min(5, $ratingAverage)), 1),
        'ratingCount' => $ratingCount,
        'reviews' => $reviews,
        'successMessage' => (string) ($item['success_message'] ?? ''),
        'failureMessage' => (string) ($item['failure_message'] ?? ''),
        'updatedAt' => (string) ($item['updated_at'] ?? ''),
    ];
}

function payments_item_remaining_capacity(array $item): ?int
{
    $capacity = payments_normalize_positive_int_nullable($item['capacity'] ?? null, 1000000);
    if ($capacity === null) {
        return null;
    }

    $soldCount = max(0, (int) ($item['sold_count'] ?? 0));
    return max(0, $capacity - $soldCount);
}

function payments_calculate_item_quote(array $item, int $quantity, string $discountCode = ''): array
{
    $maxQuantity = max(1, min(99, (int) ($item['max_quantity_per_order'] ?? 1)));
    $quantity = max(1, min($maxQuantity, $quantity));
    $unitPrice = max(0, (int) ($item['price'] ?? 0));
    $subtotal = $unitPrice * $quantity;
    $normalizedCode = payments_normalize_discount_code_text($discountCode);
    $discountAmount = 0;
    $discountLabel = '';
    $discountApplied = false;
    $discountValid = $normalizedCode === '';

    if ($normalizedCode !== '') {
        foreach (payments_normalize_discount_codes($item['discount_codes'] ?? []) as $code) {
            if ((string) ($code['code'] ?? '') !== $normalizedCode) {
                continue;
            }
            if (!(bool) ($code['is_enabled'] ?? true)) {
                continue;
            }
            $expiresAt = payments_timestamp_or_null((string) ($code['expires_at'] ?? ''));
            if ($expiresAt !== null && $expiresAt <= time()) {
                continue;
            }

            $discountValid = true;
            $discountApplied = true;
            $discountLabel = (string) ($code['label'] ?? '');
            if ((string) ($code['type'] ?? 'fixed') === 'percent') {
                $discountAmount = (int) floor($subtotal * ((int) ($code['amount'] ?? 0)) / 100);
            } else {
                $discountAmount = (int) ($code['amount'] ?? 0);
            }
            break;
        }
    }

    $discountAmount = max(0, min($subtotal, $discountAmount));
    $amount = max(0, $subtotal - $discountAmount);

    return [
        'quantity' => $quantity,
        'unitPrice' => $unitPrice,
        'subtotal' => $subtotal,
        'discountCode' => $discountApplied ? $normalizedCode : '',
        'discountAmount' => $discountAmount,
        'discountLabel' => $discountLabel,
        'discountApplied' => $discountApplied,
        'discountValid' => $discountValid,
        'amount' => $amount,
    ];
}

function payments_owner_item_payload(array $item): array
{
    $public = payments_public_item_payload($item);
    $public['createdAt'] = (string) ($item['created_at'] ?? '');
    $public['publicUrl'] = '/buy/item/?slug=' . rawurlencode((string) ($item['slug'] ?? ''));
    $public['discountCodes'] = payments_normalize_discount_codes($item['discount_codes'] ?? []);
    return $public;
}

function payments_owner_order_payload(array $order, ?array $item = null): array
{
    $cartItems = payments_normalize_cart_order_items($order['cart_items'] ?? []);
    $cartPayload = [];
    foreach ($cartItems as $cartItem) {
        $cartPayload[] = [
            'itemId' => (int) ($cartItem['item_id'] ?? 0),
            'slug' => (string) ($cartItem['slug'] ?? ''),
            'title' => (string) ($cartItem['title'] ?? ''),
            'quantity' => max(1, (int) ($cartItem['quantity'] ?? 1)),
            'unitPrice' => max(0, (int) ($cartItem['unit_price'] ?? 0)),
            'subtotal' => max(0, (int) ($cartItem['subtotal'] ?? 0)),
            'discountCode' => (string) ($cartItem['discount_code'] ?? ''),
            'discountAmount' => max(0, (int) ($cartItem['discount_amount'] ?? 0)),
            'amount' => max(0, (int) ($cartItem['amount'] ?? 0)),
            'extraFormData' => is_array($cartItem['extra_form_data'] ?? null) ? $cartItem['extra_form_data'] : [],
        ];
    }

    return [
        'id' => (int) ($order['id'] ?? 0),
        'itemId' => (int) ($order['item_id'] ?? 0),
        'itemTitle' => $cartPayload !== []
            ? 'سبد خرید (' . count($cartPayload) . ' آیتم)'
            : (string) ($item['title'] ?? ''),
        'itemSlug' => (string) ($item['slug'] ?? ''),
        'userId' => (string) ($order['user_id'] ?? ''),
        'payerName' => (string) ($order['payer_name'] ?? ''),
        'payerPhone' => (string) ($order['payer_phone'] ?? ''),
        'payerStudentNumber' => (string) ($order['payer_student_number'] ?? ''),
        'extraFormData' => is_array($order['extra_form_data'] ?? null) ? $order['extra_form_data'] : [],
        'cartItems' => $cartPayload,
        'quantity' => max(1, (int) ($order['quantity'] ?? 1)),
        'unitPrice' => max(0, (int) ($order['unit_price'] ?? 0)),
        'subtotal' => max(0, (int) ($order['subtotal'] ?? 0)),
        'discountCode' => (string) ($order['discount_code'] ?? ''),
        'discountAmount' => max(0, (int) ($order['discount_amount'] ?? 0)),
        'amount' => max(0, (int) ($order['amount'] ?? 0)),
        'gateway' => (string) ($order['gateway'] ?? ''),
        'authority' => (string) ($order['authority'] ?? ''),
        'refId' => (string) ($order['ref_id'] ?? ''),
        'status' => (string) ($order['status'] ?? PAYMENTS_ORDER_STATUS_PENDING),
        'createdAt' => (string) ($order['created_at'] ?? ''),
        'paymentStartedAt' => (string) ($order['payment_started_at'] ?? ''),
        'paidAt' => (string) ($order['paid_at'] ?? ''),
        'verifiedAt' => (string) ($order['verified_at'] ?? ''),
        'updatedAt' => (string) ($order['updated_at'] ?? ''),
        'expiredAt' => (string) ($order['expires_at'] ?? ''),
        'publicToken' => (string) ($order['public_token'] ?? ''),
        'source' => function_exists('payments_api_order_source_key') ? payments_api_order_source_key($order) : '',
        'sourceLabel' => function_exists('payments_api_order_source_key') && function_exists('payments_api_order_source_label')
            ? payments_api_order_source_label(payments_api_order_source_key($order))
            : '',
        'paymentMethod' => function_exists('payments_api_order_method_key') ? payments_api_order_method_key($order) : '',
        'paymentMethodLabel' => function_exists('payments_api_order_method_key') && function_exists('payments_api_order_method_label')
            ? payments_api_order_method_label(payments_api_order_method_key($order))
            : '',
    ];
}

function payments_order_public_result_payload(array $order, ?array $item = null): array
{
    $status = (string) ($order['status'] ?? PAYMENTS_ORDER_STATUS_PENDING);
    $statusLabel = 'در انتظار';
    if ($status === PAYMENTS_ORDER_STATUS_SUCCESS) {
        $statusLabel = 'موفق';
    } elseif ($status === PAYMENTS_ORDER_STATUS_FAILED) {
        $statusLabel = 'ناموفق';
    } elseif ($status === PAYMENTS_ORDER_STATUS_CANCELED) {
        $statusLabel = 'لغو شده';
    } elseif ($status === PAYMENTS_ORDER_STATUS_EXPIRED) {
        $statusLabel = 'منقضی شده';
    }

    $message = '';
    if ($status === PAYMENTS_ORDER_STATUS_SUCCESS) {
        $message = (string) ($item['success_message'] ?? 'پرداخت شما با موفقیت تایید شد.');
    } elseif (in_array($status, [PAYMENTS_ORDER_STATUS_FAILED, PAYMENTS_ORDER_STATUS_CANCELED, PAYMENTS_ORDER_STATUS_EXPIRED], true)) {
        $message = (string) ($item['failure_message'] ?? 'پرداخت شما ناموفق بود.');
    } else {
        $message = 'پرداخت هنوز در حال بررسی است.';
    }

    $cartItems = payments_normalize_cart_order_items($order['cart_items'] ?? []);
    $cartPayload = [];
    foreach ($cartItems as $cartItem) {
        $cartPayload[] = [
            'itemId' => (int) ($cartItem['item_id'] ?? 0),
            'slug' => (string) ($cartItem['slug'] ?? ''),
            'title' => (string) ($cartItem['title'] ?? ''),
            'quantity' => max(1, (int) ($cartItem['quantity'] ?? 1)),
            'unitPrice' => max(0, (int) ($cartItem['unit_price'] ?? 0)),
            'subtotal' => max(0, (int) ($cartItem['subtotal'] ?? 0)),
            'discountCode' => (string) ($cartItem['discount_code'] ?? ''),
            'discountAmount' => max(0, (int) ($cartItem['discount_amount'] ?? 0)),
            'amount' => max(0, (int) ($cartItem['amount'] ?? 0)),
        ];
    }

    return [
        'id' => (int) ($order['id'] ?? 0),
        'status' => $status,
        'statusLabel' => $statusLabel,
        'message' => $message,
        'quantity' => max(1, (int) ($order['quantity'] ?? 1)),
        'unitPrice' => max(0, (int) ($order['unit_price'] ?? 0)),
        'subtotal' => max(0, (int) ($order['subtotal'] ?? 0)),
        'discountCode' => (string) ($order['discount_code'] ?? ''),
        'discountAmount' => max(0, (int) ($order['discount_amount'] ?? 0)),
        'amount' => max(0, (int) ($order['amount'] ?? 0)),
        'authority' => (string) ($order['authority'] ?? ''),
        'refId' => (string) ($order['ref_id'] ?? ''),
        'createdAt' => (string) ($order['created_at'] ?? ''),
        'paidAt' => (string) ($order['paid_at'] ?? ''),
        'verifiedAt' => (string) ($order['verified_at'] ?? ''),
        'item' => $cartPayload === [] && $item ? payments_public_item_payload($item) : null,
        'cartItems' => $cartPayload,
        'payerName' => (string) ($order['payer_name'] ?? ''),
        'payerPhone' => (string) ($order['payer_phone'] ?? ''),
        'payerStudentNumber' => (string) ($order['payer_student_number'] ?? ''),
    ];
}

function payments_append_notification(
    array &$store,
    string $type,
    string $title,
    string $body,
    int $relatedOrderId
): array {
    $notification = [
        'id' => payments_next_notification_id($store),
        'type' => dent_clean_text($type, 40),
        'title' => dent_clean_text($title, 180),
        'body' => dent_clean_text($body, 900),
        'related_order_id' => max(0, $relatedOrderId),
        'read_at' => '',
        'created_at' => dent_iso_now(),
    ];

    $store['notifications'][] = $notification;
    return $notification;
}

function payments_log_gateway_event(string $event, array $payload): void
{
    $entry = [
        'event' => dent_clean_text($event, 60),
        'at' => dent_iso_now(),
        'payload' => $payload,
    ];

    $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || $json === '') {
        return;
    }

    dent_ensure_directory(dirname(payments_log_path()));
    @file_put_contents(payments_log_path(), $json . PHP_EOL, FILE_APPEND | LOCK_EX);
}
