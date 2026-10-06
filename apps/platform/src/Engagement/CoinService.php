<?php

declare(strict_types=1);

namespace Fanoos\Platform\Engagement;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Commerce\DiscountService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

/**
 * سکه (docs/product/08_ENGAGEMENT.md): spending the coins PointsService pays
 * for each goal day.
 *
 * The owner sets the "boxes" (engagement_coin_offers): so many coins buy so
 * much off, optionally on one product, as a personal single-use code valid
 * for a few days. Redeeming one writes the code and a negative ledger row in
 * one transaction, under a lock on the student's ledger, so two taps cannot
 * spend the same coins twice.
 */
final class CoinService
{
    private readonly DiscountService $discounts;

    public function __construct(
        private readonly PDO $database,
        private readonly ?AccessGate $access = null,
        private readonly ?AuditLogger $audit = null,
    ) {
        $this->discounts = new DiscountService($database);
    }

    /**
     * The student's coins: balance, the active boxes, and the codes they bought.
     *
     * @return array{coins:int,offers:list<array<string,mixed>>,codes:list<array<string,mixed>>}
     */
    public function wallet(string $userId, string $workspaceId): array
    {
        $this->access?->requireWorkspace($userId, $workspaceId, 'exam.take');
        $coins = (new PointsService($this->database))->coins($workspaceId, $userId);
        $codes = $this->database->prepare(<<<'SQL'
SELECT code.code, code.label, code.kind, code.amount_minor, code.percent, code.valid_until, product.name AS product_name,
       code.valid_until IS NOT NULL AND code.valid_until <= UTC_TIMESTAMP(6) AS expired,
       EXISTS (SELECT 1 FROM commerce_orders orders WHERE orders.discount_code_id = code.id AND orders.status = 'paid') AS used
FROM commerce_discount_codes code
LEFT JOIN commerce_products product ON product.id = code.product_id
WHERE code.workspace_id = :workspace AND code.owner_user_id = :user AND code.source = 'coins'
ORDER BY code.created_at DESC
LIMIT 20
SQL);
        $codes->execute(['workspace' => $workspaceId, 'user' => $userId]);

        return [
            'coins' => $coins,
            'offers' => array_map(fn (array $offer): array => $offer + ['affordable' => $coins >= $offer['coins']], $this->offerRows($workspaceId, true)),
            'codes' => array_map(static fn (array $row): array => [
                'code' => (string) $row['code'], 'label' => (string) $row['label'], 'kind' => (string) $row['kind'],
                'amount_minor' => $row['amount_minor'] === null ? null : (int) $row['amount_minor'],
                'percent' => $row['percent'] === null ? null : (int) $row['percent'],
                'product_name' => $row['product_name'],
                'valid_until' => $row['valid_until'] === null ? null : gmdate(DATE_ATOM, (int) strtotime($row['valid_until'] . ' UTC')),
                'state' => (bool) $row['used'] ? 'used' : ((bool) $row['expired'] ? 'expired' : 'ready'),
            ], $codes->fetchAll()),
        ];
    }

    /**
     * Spends coins on a box: a personal code, and the coins taken off.
     *
     * @return array{code:string,valid_until:?string,coins:int}
     */
    public function redeem(string $userId, string $workspaceId, string $offerId): array
    {
        $this->access?->requireWorkspace($userId, $workspaceId, 'exam.take');
        $result = Transaction::run($this->database, function () use ($userId, $workspaceId, $offerId): array {
            $offer = $this->database->prepare("SELECT * FROM engagement_coin_offers WHERE id = :id AND workspace_id = :workspace AND status = 'active'");
            $offer->execute(['id' => $offerId, 'workspace' => $workspaceId]);
            $box = $offer->fetch();
            if ($box === false) {
                throw new PlatformException('coin_offer_not_found', 'This box is not available.', 404);
            }
            // Locks the student's ledger: a second redemption waits here and then sees the new balance.
            $ledger = $this->database->prepare('SELECT delta FROM engagement_coin_ledger WHERE workspace_id = :workspace AND user_id = :user FOR UPDATE');
            $ledger->execute(['workspace' => $workspaceId, 'user' => $userId]);
            $balance = array_sum(array_map('intval', $ledger->fetchAll(PDO::FETCH_COLUMN)));
            if ($balance < (int) $box['coins']) {
                throw new PlatformException('coins_insufficient', 'Not enough coins for this box.', 409);
            }

            $codeId = Uuid::v7();
            $code = null;
            for ($try = 0; $try < 5 && $code === null; $try++) {
                $candidate = DiscountService::randomCode();
                $taken = $this->database->prepare('SELECT 1 FROM commerce_discount_codes WHERE workspace_id = :workspace AND code = :code');
                $taken->execute(['workspace' => $workspaceId, 'code' => $candidate]);
                if ($taken->fetchColumn() === false) {
                    $code = $candidate;
                }
            }
            if ($code === null) {
                throw new PlatformException('coin_code_unavailable', 'A code could not be made; try again.', 503);
            }
            $this->discounts->insert(
                $codeId, $workspaceId, $code, (string) $box['title'], (string) $box['kind'],
                $box['amount_minor'] === null ? null : (int) $box['amount_minor'],
                $box['percent'] === null ? null : (int) $box['percent'],
                $box['product_id'] === null ? null : (string) $box['product_id'],
                $userId, 1, 1, (int) $box['code_valid_days'], 'coins', $userId,
            );
            $this->database->prepare(<<<'SQL'
INSERT INTO engagement_coin_ledger (id, workspace_id, user_id, delta, reason, reference, created_at)
VALUES (:id, :workspace, :user, :delta, 'redeem', :code, UTC_TIMESTAMP(6))
SQL)->execute(['id' => Uuid::v7(), 'workspace' => $workspaceId, 'user' => $userId, 'delta' => -(int) $box['coins'], 'code' => $codeId]);

            $until = $this->database->prepare('SELECT valid_until FROM commerce_discount_codes WHERE id = :id');
            $until->execute(['id' => $codeId]);
            $validUntil = $until->fetchColumn();

            return [
                'code_id' => $codeId, 'code' => $code,
                'valid_until' => is_string($validUntil) ? gmdate(DATE_ATOM, (int) strtotime($validUntil . ' UTC')) : null,
                'coins' => $balance - (int) $box['coins'],
            ];
        });
        $this->audit?->record($workspaceId, $userId, 'engagement.coins.redeem', 'commerce_discount_code', $result['code_id'], 'success', ['offer_id' => $offerId]);
        unset($result['code_id']);

        return $result;
    }

    /** @return list<array<string, mixed>> every box, for the owner */
    public function offers(string $actorUserId, string $workspaceId): array
    {
        $this->access?->requireWorkspace($actorUserId, $workspaceId, 'commerce.manage_catalog');

        return $this->offerRows($workspaceId, false);
    }

    /**
     * Creates a box, or changes one.
     *
     * @param array<string, mixed> $input title, coins, kind, amount_minor | percent, product_id?, code_valid_days?, active?, sort_order?
     * @return array{id:string}
     */
    public function saveOffer(string $actorUserId, string $workspaceId, ?string $offerId, array $input): array
    {
        $this->access?->requireWorkspace($actorUserId, $workspaceId, 'commerce.manage_catalog');
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 120) {
            throw new PlatformException('coin_offer_title_invalid', 'A box needs a short title.', 422);
        }
        $coins = filter_var($input['coins'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
        if ($coins === false) {
            throw new PlatformException('coin_offer_coins_invalid', 'A box costs a positive number of coins.', 422);
        }
        [$kind, $amount, $percent] = DiscountService::value($input);
        $productId = $this->discounts->product($workspaceId, $input['product_id'] ?? null);
        $days = filter_var($input['code_valid_days'] ?? 3, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 60]]);
        if ($days === false) {
            throw new PlatformException('coin_offer_days_invalid', 'A code is valid for 1 to 60 days.', 422);
        }
        $values = [
            'title' => $title, 'coins' => $coins, 'kind' => $kind, 'amount' => $amount, 'percent' => $percent,
            'product' => $productId, 'days' => $days, 'status' => ($input['active'] ?? true) === false ? 'disabled' : 'active',
            'sort' => (int) ($input['sort_order'] ?? 0), 'workspace' => $workspaceId,
        ];
        if ($offerId === null) {
            $offerId = Uuid::v7();
            $this->database->prepare(<<<'SQL'
INSERT INTO engagement_coin_offers (id, workspace_id, title, coins, kind, amount_minor, percent, product_id, code_valid_days, status, sort_order, created_at, updated_at)
VALUES (:id, :workspace, :title, :coins, :kind, :amount, :percent, :product, :days, :status, :sort, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute($values + ['id' => $offerId]);
        } else {
            $update = $this->database->prepare(<<<'SQL'
UPDATE engagement_coin_offers SET title = :title, coins = :coins, kind = :kind, amount_minor = :amount, percent = :percent,
       product_id = :product, code_valid_days = :days, status = :status, sort_order = :sort, updated_at = UTC_TIMESTAMP(6)
WHERE id = :id AND workspace_id = :workspace
SQL);
            $update->execute($values + ['id' => $offerId]);
            $exists = $this->database->prepare('SELECT 1 FROM engagement_coin_offers WHERE id = :id AND workspace_id = :workspace');
            $exists->execute(['id' => $offerId, 'workspace' => $workspaceId]);
            if ($exists->fetchColumn() === false) {
                throw new PlatformException('coin_offer_not_found', 'This box was not found.', 404);
            }
        }
        $this->audit?->record($workspaceId, $actorUserId, 'engagement.coin_offer.save', 'engagement_coin_offer', $offerId);

        return ['id' => $offerId];
    }

    /** @return list<array<string, mixed>> */
    private function offerRows(string $workspaceId, bool $activeOnly): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT offer.id, offer.title, offer.coins, offer.kind, offer.amount_minor, offer.percent, offer.product_id,
       product.name AS product_name, offer.code_valid_days, offer.status, offer.sort_order
FROM engagement_coin_offers offer
LEFT JOIN commerce_products product ON product.id = offer.product_id
WHERE offer.workspace_id = :workspace AND (:all = 1 OR offer.status = 'active')
ORDER BY offer.sort_order, offer.coins
SQL);
        $query->execute(['workspace' => $workspaceId, 'all' => $activeOnly ? 0 : 1]);

        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'], 'title' => (string) $row['title'], 'coins' => (int) $row['coins'],
            'kind' => (string) $row['kind'], 'amount_minor' => $row['amount_minor'] === null ? null : (int) $row['amount_minor'],
            'percent' => $row['percent'] === null ? null : (int) $row['percent'],
            'product_id' => $row['product_id'], 'product_name' => $row['product_name'],
            'code_valid_days' => (int) $row['code_valid_days'], 'active' => $row['status'] === 'active', 'sort_order' => (int) $row['sort_order'],
        ], $query->fetchAll());
    }
}
