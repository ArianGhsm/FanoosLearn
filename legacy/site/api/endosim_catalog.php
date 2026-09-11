<?php

declare(strict_types=1);

/**
 * Shared catalog definition for the Endosim endodontic training-tooth shop.
 *
 * Prices are stored in rials, like the rest of the payments store. The source
 * price list is in toman; the agreed retail formula is:
 *     final_toman = round(base_toman * 0.85) + 15000
 *     price_rial  = final_toman * 10
 *
 * Distinct price tiers from the price list:
 *     325,000 toman -> 291,250 toman -> 2,912,500 rial
 *     350,000 toman -> 312,500 toman -> 3,125,000 rial
 *     390,000 toman -> 346,500 toman -> 3,465,000 rial
 *     455,000 toman -> 401,750 toman -> 4,017,500 rial
 *
 * Images live as static assets under /assets/images/buy/endosim/<lowercase-code>.png
 * which satisfies payments_api_validate_uploaded_image_url().
 */

const ENDOSIM_CATEGORY = 'endodontic_models';
const ENDOSIM_SLUG_PREFIX = 'endosim-';

function endosim_catalog(): array
{
    $tierA = 2912500; // 291,250 toman
    $tierB = 3125000; // 312,500 toman
    $tierC = 3465000; // 346,500 toman
    $tierD = 4017500; // 401,750 toman

    // [code, title, jaw, tooth, detail, priceRial]
    $rows = [
        ['CT-1UL-G100', 'دندان سنترال ماگزیلا — استریت', 'ماگزیلا (فک بالا)', 'سنترال', 'استریت', $tierA],
        ['CT-1UL-G110', 'دندان سنترال ماگزیلا — کرودار', 'ماگزیلا (فک بالا)', 'سنترال', 'کرودار', $tierA],
        ['CT-1UL-G101', 'دندان سنترال ماگزیلا — اپن اپکس', 'ماگزیلا (فک بالا)', 'سنترال', 'اپن اپکس', $tierA],
        ['CT-1LL-G100', 'دندان سنترال مندیبل — تک‌کاناله', 'مندیبل (فک پایین)', 'سنترال', '۱ کانال', $tierA],
        ['CT-1LL-G400', 'دندان سنترال مندیبل — دو‌کاناله (تایپ ۴ ورتوچی)', 'مندیبل (فک پایین)', 'سنترال', '۲ کانال، تایپ ۴ ورتوچی', $tierA],
        ['CT-2LL-G300', 'دندان لترال مندیبل — تایپ ۳ ورتوچی', 'مندیبل (فک پایین)', 'لترال', 'تایپ ۳ ورتوچی', $tierA],
        ['CT-3LL-G100', 'دندان کانین مندیبل — طول ۲۶ میلی‌متر', 'مندیبل (فک پایین)', 'کانین', 'طول ۲۶mm', $tierA],
        ['CT-4LL-G500', 'دندان پرمولر اول مندیبل — تایپ ۵ ورتوچی', 'مندیبل (فک پایین)', 'پرمولر اول', 'تایپ ۵ ورتوچی', $tierB],
        ['CT-4LL-G610', 'دندان پرمولر اول مندیبل — تایپ ۶ ورتوچی', 'مندیبل (فک پایین)', 'پرمولر اول', 'تایپ ۶ ورتوچی', $tierB],
        ['CT-4UL-G400', 'دندان پرمولر اول ماگزیلا — دو ریشه (تایپ ۴ ورتوچی)', 'ماگزیلا (فک بالا)', 'پرمولر اول', '۲ ریشه، تایپ ۴ ورتوچی', $tierB],
        ['CT-5LL-G100', 'دندان پرمولر دوم مندیبل — تک‌کاناله', 'مندیبل (فک پایین)', 'پرمولر دوم', '۱ کانال', $tierB],
        ['CT-4LL-G800', 'دندان پرمولر اول مندیبل — سه‌کاناله', 'مندیبل (فک پایین)', 'پرمولر اول', '۳ کانال', $tierC],
        ['CT-4UL-G800', 'دندان پرمولر اول ماگزیلا — مینی‌مولر (سه‌کاناله)', 'ماگزیلا (فک بالا)', 'پرمولر اول (مینی‌مولر)', '۳ کانال', $tierC],
        ['CT-5UL-G110', 'دندان پرمولر دوم ماگزیلا — دبل کرو (S curve)', 'ماگزیلا (فک بالا)', 'پرمولر دوم', 'دبل کرو (S curve)', $tierB],
        ['CT-5UL-G120', 'دندان پرمولر دوم ماگزیلا — کرو شدید (بالای ۴۰ درجه)', 'ماگزیلا (فک بالا)', 'پرمولر دوم', 'کرو شدید، بالای ۴۰°', $tierB],
        ['CT-6LL-G100', 'دندان مولر اول مندیبل — استریت (سه‌کاناله)', 'مندیبل (فک پایین)', 'مولر اول', 'استریت، ۳ کانال', $tierC],
        ['CT-6LL-G110', 'دندان مولر اول مندیبل — کرو مزیالی (سه‌کاناله)', 'مندیبل (فک پایین)', 'مولر اول', 'کرو مزیالی، ۳ کانال', $tierC],
        ['CT-6LL-G400', 'دندان مولر اول مندیبل — استریت (چهارکاناله)', 'مندیبل (فک پایین)', 'مولر اول', 'استریت، ۴ کانال', $tierC],
        ['CT-6LL-G120', 'دندان مولر اول مندیبل — کرو hook (چهارکاناله)', 'مندیبل (فک پایین)', 'مولر اول', 'کرو hook، ۴ کانال', $tierD],
        ['CT-6LL-G910', 'دندان مولر اول مندیبل — Radix (چهارکاناله)', 'مندیبل (فک پایین)', 'مولر اول', 'Radix، ۴ کانال', $tierD],
        ['CT-6UL-G100', 'دندان مولر اول ماگزیلا — سه‌کاناله', 'ماگزیلا (فک بالا)', 'مولر اول', '۳ کانال', $tierC],
        ['CT-6UL-G400', 'دندان مولر اول ماگزیلا — چهارکاناله (دارای MB2)', 'ماگزیلا (فک بالا)', 'مولر اول', '۴ کانال، دارای MB2', $tierC],
        ['CT-DUL-G100', 'مولر اول شیری فک بالا (D ماگزیلا)', 'ماگزیلا (فک بالا)', 'مولر اول شیری (D)', 'دندان شیری', $tierA],
        ['CT-EUL-G100', 'مولر دوم شیری فک بالا (E ماگزیلا)', 'ماگزیلا (فک بالا)', 'مولر دوم شیری (E)', 'دندان شیری', $tierA],
        ['CT-DLL-G100', 'مولر اول شیری فک پایین (D مندیبل)', 'مندیبل (فک پایین)', 'مولر اول شیری (D)', 'دندان شیری', $tierA],
        ['CT-ELL-G100', 'مولر دوم شیری فک پایین (E مندیبل)', 'مندیبل (فک پایین)', 'مولر دوم شیری (E)', 'دندان شیری', $tierA],
    ];

    $catalog = [];
    foreach ($rows as $row) {
        [$code, $title, $jaw, $tooth, $detail, $priceRial] = $row;
        $slug = ENDOSIM_SLUG_PREFIX . strtolower($code);
        $imageName = strtolower($code) . '.png';
        $catalog[] = [
            'code' => $code,
            'slug' => $slug,
            'title' => $title,
            'jaw' => $jaw,
            'tooth' => $tooth,
            'detail' => $detail,
            'price' => $priceRial,
            'heroImage' => '/assets/images/buy/endosim/' . $imageName,
            'shortDescription' => $tooth . ' • ' . $jaw . ' • ' . $detail,
            'specifications' => [
                ['label' => 'کد محصول', 'value' => $code],
                ['label' => 'فک', 'value' => $jaw],
                ['label' => 'نوع دندان', 'value' => $tooth],
                ['label' => 'مشخصه', 'value' => $detail],
                ['label' => 'برند', 'value' => 'اندوسیم (Endosim)'],
            ],
        ];
    }

    return $catalog;
}

/**
 * Idempotently upsert the Endosim catalog into a payments store array (in place).
 * Existing items are matched by slug; their id, created_at and sold_count are
 * preserved so historical orders stay intact. Returns a {created, updated, total}
 * summary. Requires payments_store.php to be loaded for the helper functions.
 */
function endosim_import_into_store(array &$store): array
{
    $catalog = endosim_catalog();
    $deliveryNote = 'تحویل حضوری در محدوده دانشکده دندانپزشکی دانشگاه علوم پزشکی تهران هماهنگ می‌شود.';
    if (!is_array($store['items'] ?? null)) {
        $store['items'] = [];
    }

    $created = 0;
    $updated = 0;
    $now = dent_iso_now();

    foreach ($catalog as $product) {
        $slug = payments_clean_slug((string) ($product['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }
        $price = max(0, (int) ($product['price'] ?? 0));
        if ($price <= 0) {
            continue;
        }

        $payload = [
            'slug' => $slug,
            'category' => ENDOSIM_CATEGORY,
            'title' => (string) ($product['title'] ?? ''),
            'short_description' => (string) ($product['shortDescription'] ?? ''),
            'full_description' => '',
            'hero_image' => (string) ($product['heroImage'] ?? ''),
            'gallery' => [],
            'specifications' => payments_normalize_specifications($product['specifications'] ?? []),
            'price' => $price,
            'status' => PAYMENTS_ITEM_STATUS_ACTIVE,
            'starts_at' => '',
            'expires_at' => '',
            'capacity' => null,
            'max_quantity_per_order' => 20,
            'required_fields' => [],
            'audience_note' => '',
            'delivery_note' => $deliveryNote,
            'support_note' => '',
            'allow_cancellation' => false,
            'discount_codes' => [],
            'rating_average' => 0,
            'rating_count' => 0,
            'reviews' => [],
            'success_message' => 'سفارش شما با موفقیت ثبت شد. برای هماهنگی تحویل با شما تماس می‌گیریم.',
            'failure_message' => 'پرداخت شما ناموفق بود. در صورت کسر وجه، مبلغ طی ۷۲ ساعت بازمی‌گردد.',
            'updated_at' => $now,
        ];

        $index = payments_find_item_index_by_slug($store, $slug);
        if ($index >= 0 && is_array($store['items'][$index] ?? null)) {
            $existing = $store['items'][$index];
            $payload['id'] = (int) ($existing['id'] ?? payments_next_item_id($store));
            $payload['created_at'] = (string) ($existing['created_at'] ?? $now);
            $payload['sold_count'] = max(0, (int) ($existing['sold_count'] ?? 0));
            $store['items'][$index] = array_merge($existing, $payload);
            $updated++;
            continue;
        }

        $payload['id'] = payments_next_item_id($store);
        $payload['created_at'] = $now;
        $payload['sold_count'] = 0;
        $store['items'][] = $payload;
        $created++;
    }

    return ['created' => $created, 'updated' => $updated, 'total' => count($catalog)];
}
