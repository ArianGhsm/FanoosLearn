<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_store.php';
require_once __DIR__ . '/msg_store.php';

$action = dent_request_action();

if ($action === 'get') {
    msg_handle_get();
    exit;
}

if ($action === 'view') {
    msg_handle_view();
    exit;
}

dent_require_owner();

switch ($action) {
    case 'create':
        msg_handle_create();
        break;
    case 'list':
        msg_handle_list();
        break;
    case 'delete':
        msg_handle_delete();
        break;
    default:
        dent_error('عملیات نامعتبر است.', 400);
}

function msg_json_input(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function msg_handle_get(): void
{
    $token = msg_clean_token((string) ($_GET['token'] ?? ''));
    if ($token === '') {
        dent_json_response(['success' => false, 'error' => 'توکن نامعتبر.'], 404);
    }

    $store = msg_read_store();
    $card = msg_find_by_token($store, $token);
    if (!is_array($card)) {
        dent_json_response(['success' => false, 'error' => 'کارت یافت نشد.'], 404);
    }

    msg_with_write_lock(function (array $store) use ($token): array {
        foreach ($store['cards'] as &$record) {
            if (is_array($record) && ($record['token'] ?? '') === $token) {
                $record['viewCount'] = (int) ($record['viewCount'] ?? 0) + 1;
                $record['lastViewedAt'] = dent_iso_now();
                break;
            }
        }
        unset($record);
        return $store;
    });

    dent_json_response(['success' => true, 'card' => $card]);
}

function msg_handle_view(): void
{
    $token = msg_clean_token((string) ($_POST['token'] ?? $_GET['token'] ?? ''));
    if ($token === '') {
        dent_json_response(['success' => false], 400);
    }
    dent_json_response(['success' => true]);
}

function msg_handle_create(): void
{
    if (dent_request_method() !== 'POST') {
        dent_error('متد درخواست نامعتبر.', 405);
    }

    $body = msg_json_input();

    $povLine       = trim((string) ($body['povLine'] ?? ''));
    $recipientName = trim((string) ($body['recipientName'] ?? ''));
    $theme         = trim((string) ($body['theme'] ?? 'rose'));
    $rawLines      = $body['lines'] ?? [];

    if ($povLine === '') {
        dent_error('متن اصلی POV الزامی است.', 422);
    }

    $allowedThemes = ['rose', 'blue', 'gold', 'mint'];
    if (!in_array($theme, $allowedThemes, true)) {
        $theme = 'rose';
    }

    $lines = [];
    if (is_array($rawLines)) {
        foreach ($rawLines as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }
    }

    $id    = msg_next_id();
    $token = msg_next_token();

    $record = [
        'id'            => $id,
        'token'         => $token,
        'povLine'       => $povLine,
        'recipientName' => $recipientName,
        'lines'         => $lines,
        'theme'         => $theme,
        'createdAt'     => dent_iso_now(),
        'viewCount'     => 0,
        'lastViewedAt'  => null,
        'deleted'       => false,
    ];

    msg_with_write_lock(function (array $store) use ($id, $record): array {
        $store['cards'][$id] = $record;
        return $store;
    });

    dent_json_response([
        'success'   => true,
        'card'      => $record,
        'publicUrl' => msg_public_url($token),
    ]);
}

function msg_handle_list(): void
{
    $store = msg_read_store();
    $cards = [];
    foreach (($store['cards'] ?? []) as $record) {
        if (!is_array($record) || ($record['deleted'] ?? false) !== false) {
            continue;
        }
        $cards[] = array_merge($record, [
            'publicUrl' => msg_public_url((string) ($record['token'] ?? '')),
        ]);
    }
    usort($cards, static fn($a, $b) => strcmp(
        (string) ($b['createdAt'] ?? ''),
        (string) ($a['createdAt'] ?? '')
    ));
    dent_json_response(['success' => true, 'cards' => $cards]);
}

function msg_handle_delete(): void
{
    if (dent_request_method() !== 'POST') {
        dent_error('متد درخواست نامعتبر.', 405);
    }

    $body = msg_json_input();
    $id   = msg_clean_id(trim((string) ($body['id'] ?? '')));
    if ($id === '') {
        dent_error('شناسه کارت نامعتبر است.', 422);
    }

    $found = false;
    msg_with_write_lock(function (array $store) use ($id, &$found): array {
        if (isset($store['cards'][$id]) && is_array($store['cards'][$id])) {
            $store['cards'][$id]['deleted'] = true;
            $found = true;
        }
        return $store;
    });

    if (!$found) {
        dent_error('کارت یافت نشد.', 404);
    }

    dent_json_response(['success' => true]);
}
