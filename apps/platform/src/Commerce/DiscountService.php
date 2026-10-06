<?php

declare(strict_types=1);

namespace Fanoos\Platform\Commerce;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;

/**
 * کد تخفیف (docs/product/08_ENGAGEMENT.md).
 *
 * A code takes an amount or a percentage off one product or any product. The
 * owner creates codes here; a student mints a personal one with coins
 * (Engagement\CoinService). A code is checked when an order is created, inside
 * that order's transaction, and its uses are counted from **paid** orders, so
 * an abandoned checkout never uses a code up.
 *
 * What is charged never falls below MINIMUM_CHARGE_MINOR: the gateway cannot
 * take a zero payment, and free access is granted another way.
 */
final class DiscountService
{
    /** 1,000 toman, in rials: the least a discounted order may charge. */
    public const MINIMUM_CHARGE_MINOR = 10000;
    private const CODE = '/^[A-Z0-9]{4,32}$/';

    public function __construct(
        private readonly PDO $database,
        private readonly ?AccessGate $access = null,
        private readonly ?AuditLogger $audit = null,
    ) {
    }

    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[\s\-]+/', '', $code) ?? '');
    }

    /**
     * What a code takes off a price for this buyer, or why it cannot.
     * Read-only; callers that create an order call it inside their transaction.
     *
     * @return array{code_id:string,code:string,label:string,discount_minor:int,total_minor:int}
     */
    public function quote(string $userId, string $workspaceId, string $productId, int $priceMinor, string $code): array
    {
        $code = self::normalize($code);
        if (preg_match(self::CODE, $code) !== 1) {
            throw new PlatformException('discount_code_invalid', 'This discount code is not valid.', 422);
        }
        $query = $this->database->prepare(<<<'SQL'
SELECT id, code, label, kind, amount_minor, percent, product_id, owner_user_id, max_uses, per_user_limit, status,
       valid_from <= UTC_TIMESTAMP(6) AS started, (valid_until IS NULL OR valid_until > UTC_TIMESTAMP(6)) AS open
FROM commerce_discount_codes WHERE workspace_id = :workspace AND code = :code
SQL);
        $query->execute(['workspace' => $workspaceId, 'code' => $code]);
        $row = $query->fetch();
        // Someone else's personal code reads exactly like a code that does not exist.
        if ($row === false || ($row['owner_user_id'] !== null && !hash_equals((string) $row['owner_user_id'], $userId))) {
            throw new PlatformException('discount_code_invalid', 'This discount code is not valid.', 422);
        }
        if ($row['status'] !== 'active' || !(bool) $row['started'] || !(bool) $row['open']) {
            throw new PlatformException('discount_code_expired', 'This discount code is no longer valid.', 422);
        }
        if ($row['product_id'] !== null && !hash_equals((string) $row['product_id'], $productId)) {
            throw new PlatformException('discount_code_wrong_product', 'This discount code is for another product.', 422);
        }
        $uses = $this->database->prepare("SELECT COUNT(*) AS total, SUM(buyer_user_id = :buyer) AS mine FROM commerce_orders WHERE workspace_id = :workspace AND discount_code_id = :code AND status = 'paid'");
        $uses->execute(['buyer' => $userId, 'workspace' => $workspaceId, 'code' => $row['id']]);
        $used = $uses->fetch() ?: [];
        if (($row['max_uses'] !== null && (int) $used['total'] >= (int) $row['max_uses']) || (int) ($used['mine'] ?? 0) >= (int) $row['per_user_limit']) {
            throw new PlatformException('discount_code_used', 'This discount code has already been used.', 422);
        }

        $discount = $row['kind'] === 'percent'
            ? intdiv($priceMinor * (int) $row['percent'], 100)
            : (int) $row['amount_minor'];
        $discount = max(0, min($discount, $priceMinor - self::MINIMUM_CHARGE_MINOR));
        if ($discount === 0) {
            throw new PlatformException('discount_code_not_applicable', 'This discount code does not lower this price.', 422);
        }

        return ['code_id' => (string) $row['id'], 'code' => (string) $row['code'], 'label' => (string) $row['label'], 'discount_minor' => $discount, 'total_minor' => $priceMinor - $discount];
    }

    /** A student's check before paying: the product's current price with the code applied. */
    public function check(string $userId, string $workspaceId, string $productId, string $code): array
    {
        $this->access?->requireWorkspace($userId, $workspaceId, 'commerce.purchase');
        $price = $this->database->prepare(<<<'SQL'
SELECT price.amount_minor, price.currency FROM commerce_products product
JOIN commerce_price_versions price ON price.product_id = product.id AND price.workspace_id = product.workspace_id
WHERE product.id = :product AND product.workspace_id = :workspace AND product.status = 'active' AND product.archived_at IS NULL
  AND price.valid_from <= UTC_TIMESTAMP(6) AND (price.valid_until IS NULL OR price.valid_until > UTC_TIMESTAMP(6))
ORDER BY price.valid_from DESC LIMIT 1
SQL);
        $price->execute(['product' => $productId, 'workspace' => $workspaceId]);
        $row = $price->fetch();
        if ($row === false) {
            throw new PlatformException('product_unavailable', 'Product or active price is unavailable.', 404);
        }
        $quote = $this->quote($userId, $workspaceId, $productId, (int) $row['amount_minor'], $code);
        unset($quote['code_id']);

        return $quote + ['price_minor' => (int) $row['amount_minor'], 'currency' => (string) $row['currency']];
    }

    /** @return list<array<string, mixed>> the owner's codes, newest first, with paid uses */
    public function codes(string $actorUserId, string $workspaceId): array
    {
        $this->requireManager($actorUserId, $workspaceId);
        $query = $this->database->prepare(<<<'SQL'
SELECT code.id, code.code, code.label, code.kind, code.amount_minor, code.percent, code.product_id, product.name AS product_name,
       code.max_uses, code.per_user_limit, code.valid_from, code.valid_until, code.status, code.source,
       (SELECT COUNT(*) FROM commerce_orders orders WHERE orders.discount_code_id = code.id AND orders.status = 'paid') AS uses
FROM commerce_discount_codes code
LEFT JOIN commerce_products product ON product.id = code.product_id
WHERE code.workspace_id = :workspace AND code.source = 'admin'
ORDER BY code.created_at DESC
SQL);
        $query->execute(['workspace' => $workspaceId]);

        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'], 'code' => (string) $row['code'], 'label' => (string) $row['label'],
            'kind' => (string) $row['kind'], 'amount_minor' => $row['amount_minor'] === null ? null : (int) $row['amount_minor'],
            'percent' => $row['percent'] === null ? null : (int) $row['percent'],
            'product_id' => $row['product_id'], 'product_name' => $row['product_name'],
            'max_uses' => $row['max_uses'] === null ? null : (int) $row['max_uses'], 'per_user_limit' => (int) $row['per_user_limit'],
            'valid_from' => self::atom((string) $row['valid_from']), 'valid_until' => $row['valid_until'] === null ? null : self::atom((string) $row['valid_until']),
            'status' => (string) $row['status'], 'uses' => (int) $row['uses'],
        ], $query->fetchAll());
    }

    /**
     * Creates an owner's code.
     *
     * @param array<string, mixed> $input code, label, kind (amount|percent), amount_minor | percent,
     *                                    product_id?, max_uses?, per_user_limit?, valid_days?
     * @return array{id:string,code:string}
     */
    public function create(string $actorUserId, string $workspaceId, array $input): array
    {
        $this->requireManager($actorUserId, $workspaceId);
        $code = self::normalize((string) ($input['code'] ?? ''));
        if (preg_match(self::CODE, $code) !== 1) {
            throw new PlatformException('discount_code_format', 'A code is 4 to 32 Latin letters or digits.', 422);
        }
        $label = trim((string) ($input['label'] ?? ''));
        if ($label === '' || mb_strlen($label) > 120) {
            throw new PlatformException('discount_label_invalid', 'A code needs a short label.', 422);
        }
        [$kind, $amount, $percent] = self::value($input);
        $productId = $this->product($workspaceId, $input['product_id'] ?? null);
        $maxUses = self::optionalPositive($input['max_uses'] ?? null, 'discount_max_uses_invalid');
        $perUser = self::optionalPositive($input['per_user_limit'] ?? null, 'discount_per_user_invalid') ?? 1;
        $days = self::optionalPositive($input['valid_days'] ?? null, 'discount_days_invalid');

        $id = Uuid::v7();
        try {
            $this->insert($id, $workspaceId, $code, $label, $kind, $amount, $percent, $productId, null, $maxUses, $perUser, $days, 'admin', $actorUserId);
        } catch (\PDOException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) {
                throw new PlatformException('discount_code_taken', 'This code already exists.', 409);
            }
            throw $error;
        }
        $this->audit?->record($workspaceId, $actorUserId, 'commerce.discount_code.create', 'commerce_discount_code', $id, 'success', ['code' => $code]);

        return ['id' => $id, 'code' => $code];
    }

    public function setStatus(string $actorUserId, string $workspaceId, string $codeId, bool $active): void
    {
        $this->requireManager($actorUserId, $workspaceId);
        $update = $this->database->prepare("UPDATE commerce_discount_codes SET status = :status, updated_at = UTC_TIMESTAMP(6) WHERE id = :id AND workspace_id = :workspace AND source = 'admin'");
        $update->execute(['status' => $active ? 'active' : 'disabled', 'id' => $codeId, 'workspace' => $workspaceId]);
        if ($update->rowCount() === 0) {
            $exists = $this->database->prepare("SELECT 1 FROM commerce_discount_codes WHERE id = :id AND workspace_id = :workspace AND source = 'admin'");
            $exists->execute(['id' => $codeId, 'workspace' => $workspaceId]);
            if ($exists->fetchColumn() === false) {
                throw new PlatformException('discount_code_not_found', 'Discount code was not found.', 404);
            }
        }
        $this->audit?->record($workspaceId, $actorUserId, 'commerce.discount_code.' . ($active ? 'enable' : 'disable'), 'commerce_discount_code', $codeId);
    }

    /**
     * Writes a code row. Shared with CoinService, which mints personal codes
     * inside its own transaction.
     */
    public function insert(
        string $id, string $workspaceId, string $code, string $label, string $kind, ?int $amount, ?int $percent,
        ?string $productId, ?string $ownerUserId, ?int $maxUses, int $perUser, ?int $validDays, string $source, ?string $createdBy,
    ): void {
        $this->database->prepare(<<<'SQL'
INSERT INTO commerce_discount_codes (
    id, workspace_id, code, label, kind, amount_minor, percent, product_id, owner_user_id, max_uses, per_user_limit,
    valid_from, valid_until, status, source, created_by_user_id, created_at, updated_at
) VALUES (
    :id, :workspace, :code, :label, :kind, :amount, :percent, :product, :owner, :max_uses, :per_user,
    UTC_TIMESTAMP(6), IF(:days IS NULL, NULL, DATE_ADD(UTC_TIMESTAMP(6), INTERVAL :days_again DAY)), 'active', :source, :creator,
    UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
)
SQL)->execute([
            'id' => $id, 'workspace' => $workspaceId, 'code' => $code, 'label' => mb_substr($label, 0, 120), 'kind' => $kind,
            'amount' => $amount, 'percent' => $percent, 'product' => $productId, 'owner' => $ownerUserId,
            'max_uses' => $maxUses, 'per_user' => $perUser, 'days' => $validDays, 'days_again' => $validDays ?? 0,
            'source' => $source, 'creator' => $createdBy,
        ]);
    }

    /** A random personal code: eight letters and digits without look-alikes. */
    public static function randomCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0:string,1:?int,2:?int} kind, amount_minor, percent
     */
    public static function value(array $input): array
    {
        $kind = (string) ($input['kind'] ?? '');
        if ($kind === 'amount') {
            $amount = filter_var($input['amount_minor'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($amount === false) {
                throw new PlatformException('discount_amount_invalid', 'The amount off must be a positive number of rials.', 422);
            }
            return ['amount', $amount, null];
        }
        if ($kind === 'percent') {
            $percent = filter_var($input['percent'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
            if ($percent === false) {
                throw new PlatformException('discount_percent_invalid', 'The percentage off must be between 1 and 100.', 422);
            }
            return ['percent', null, $percent];
        }
        throw new PlatformException('discount_kind_invalid', 'A discount is an amount or a percentage.', 422);
    }

    public function product(string $workspaceId, mixed $productId): ?string
    {
        if ($productId === null || $productId === '') {
            return null;
        }
        $query = $this->database->prepare('SELECT id FROM commerce_products WHERE id = :id AND workspace_id = :workspace AND archived_at IS NULL');
        $query->execute(['id' => (string) $productId, 'workspace' => $workspaceId]);
        $found = $query->fetchColumn();
        if ($found === false) {
            throw new PlatformException('product_unavailable', 'Product is unavailable.', 404);
        }

        return (string) $found;
    }

    private static function optionalPositive(mixed $value, string $error): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
        if ($number === false) {
            throw new PlatformException($error, 'Expected a positive whole number.', 422);
        }

        return $number;
    }

    private function requireManager(string $actorUserId, string $workspaceId): void
    {
        if ($this->access === null) {
            throw new PlatformException('forbidden', 'The scoped permission was not granted.', 403);
        }
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'commerce.manage_catalog');
    }

    private static function atom(string $utc): string
    {
        return gmdate(DATE_ATOM, (int) strtotime($utc . ' UTC'));
    }
}
