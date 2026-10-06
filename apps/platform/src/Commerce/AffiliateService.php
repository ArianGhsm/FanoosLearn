<?php

declare(strict_types=1);

namespace Fanoos\Platform\Commerce;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;

/**
 * برنامه همکاری در فروش (docs/product/08_ENGAGEMENT.md).
 *
 * A student shares a referral link (/r/<code>). An account created through it
 * is attributed to them (StudentRegistrationService calls attribute() inside
 * its own transaction). A paid order by that buyer within the attribution
 * window earns the referrer a commission on what was paid
 * (CommerceService calls commission() inside the paying transaction).
 * The owner turns the programme on, sets the percentage and the window, and
 * marks commissions paid out. Off until the owner turns it on.
 *
 * A referrer sees how many signed up and bought and what they earned --
 * never who the buyers are.
 */
final class AffiliateService
{
    /** Shown to the owner before they set their own; the programme is off until they do. */
    public const DEFAULT_PERCENT = 10;
    public const DEFAULT_DAYS = 90;
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public function __construct(
        private readonly PDO $database,
        private readonly ?AccessGate $access = null,
        private readonly ?AuditLogger $audit = null,
    ) {
    }

    /** @return array{enabled:bool,commission_percent:int,attribution_days:int} */
    public function program(string $workspaceId): array
    {
        $query = $this->database->prepare('SELECT enabled, commission_percent, attribution_days FROM affiliate_programs WHERE workspace_id = :workspace');
        $query->execute(['workspace' => $workspaceId]);
        $row = $query->fetch();

        return $row === false
            ? ['enabled' => false, 'commission_percent' => self::DEFAULT_PERCENT, 'attribution_days' => self::DEFAULT_DAYS]
            : ['enabled' => (bool) $row['enabled'], 'commission_percent' => (int) $row['commission_percent'], 'attribution_days' => (int) $row['attribution_days']];
    }

    /**
     * The student's affiliate page: the programme's terms, their link (if
     * they made one), and what it brought -- counts and money only.
     *
     * @return array<string, mixed>
     */
    public function overview(string $userId, string $workspaceId): array
    {
        $this->access?->requireWorkspace($userId, $workspaceId, 'exam.take');
        $program = $this->program($workspaceId);
        $link = $this->database->prepare('SELECT code FROM affiliate_links WHERE workspace_id = :workspace AND user_id = :user');
        $link->execute(['workspace' => $workspaceId, 'user' => $userId]);
        $code = $link->fetchColumn();
        $commissions = $this->database->prepare(<<<'SQL'
SELECT commission_minor, status, created_at FROM affiliate_commissions
WHERE workspace_id = :workspace AND affiliate_user_id = :user AND status <> 'void'
ORDER BY created_at DESC LIMIT 20
SQL);
        $commissions->execute(['workspace' => $workspaceId, 'user' => $userId]);

        return $program + [
            'code' => $code === false ? null : (string) $code,
            'currency' => 'IRR',
        ] + $this->totals($workspaceId, $userId) + [
            'commissions' => array_map(static fn (array $row): array => [
                'commission_minor' => (int) $row['commission_minor'], 'status' => (string) $row['status'],
                'created_at' => gmdate(DATE_ATOM, (int) strtotime($row['created_at'] . ' UTC')),
            ], $commissions->fetchAll()),
        ];
    }

    /** The student's referral code, made on first ask. Only while the programme is on. */
    public function link(string $userId, string $workspaceId): array
    {
        $this->access?->requireWorkspace($userId, $workspaceId, 'exam.take');
        if (!$this->program($workspaceId)['enabled']) {
            throw new PlatformException('affiliate_program_off', 'The affiliate programme is not running.', 409);
        }
        $existing = $this->database->prepare('SELECT code FROM affiliate_links WHERE workspace_id = :workspace AND user_id = :user');
        $existing->execute(['workspace' => $workspaceId, 'user' => $userId]);
        $code = $existing->fetchColumn();
        if ($code !== false) {
            return ['code' => (string) $code];
        }
        for ($try = 0; $try < 5; $try++) {
            $candidate = '';
            for ($i = 0; $i < 10; $i++) {
                $candidate .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            try {
                $this->database->prepare('INSERT INTO affiliate_links (workspace_id, user_id, code, created_at) VALUES (:workspace, :user, :code, UTC_TIMESTAMP(6))')
                    ->execute(['workspace' => $workspaceId, 'user' => $userId, 'code' => $candidate]);
                $this->audit?->record($workspaceId, $userId, 'affiliate.link.create', 'iam_user', $userId);

                return ['code' => $candidate];
            } catch (\PDOException $error) {
                if (($error->errorInfo[1] ?? null) !== 1062) {
                    throw $error;
                }
                // Either the code was taken (try another) or a second tap made the link first.
                $existing->execute(['workspace' => $workspaceId, 'user' => $userId]);
                $code = $existing->fetchColumn();
                if ($code !== false) {
                    return ['code' => (string) $code];
                }
            }
        }
        throw new PlatformException('affiliate_code_unavailable', 'A link could not be made; try again.', 503);
    }

    /**
     * Attributes a new account to the link it signed up through. Quietly does
     * nothing for an unknown code, a programme that is off, or one's own link.
     * Runs inside the registration transaction.
     */
    public function attribute(string $referredUserId, ?string $code): void
    {
        $code = strtolower(trim((string) $code));
        if (preg_match('/^[a-z0-9]{10}$/', $code) !== 1) {
            return;
        }
        $link = $this->database->prepare(<<<'SQL'
SELECT link.workspace_id, link.user_id FROM affiliate_links link
JOIN affiliate_programs program ON program.workspace_id = link.workspace_id AND program.enabled = TRUE
WHERE link.code = :code
SQL);
        $link->execute(['code' => $code]);
        $row = $link->fetch();
        if ($row === false || $row['user_id'] === $referredUserId) {
            return;
        }
        $this->database->prepare(<<<'SQL'
INSERT IGNORE INTO affiliate_referrals (workspace_id, referred_user_id, affiliate_user_id, created_at)
VALUES (:workspace, :referred, :affiliate, UTC_TIMESTAMP(6))
SQL)->execute(['workspace' => $row['workspace_id'], 'referred' => $referredUserId, 'affiliate' => $row['user_id']]);
    }

    /**
     * Records the commission a paid order earns, if its buyer was referred
     * within the window and the programme is on. Runs inside the paying
     * transaction; one commission per order.
     */
    public function commission(string $workspaceId, string $buyerUserId, string $orderId, int $paidMinor): void
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT referral.affiliate_user_id, program.commission_percent
FROM affiliate_referrals referral
JOIN affiliate_programs program ON program.workspace_id = referral.workspace_id AND program.enabled = TRUE
WHERE referral.workspace_id = :workspace AND referral.referred_user_id = :buyer
  AND referral.created_at > DATE_SUB(UTC_TIMESTAMP(6), INTERVAL program.attribution_days DAY)
SQL);
        $query->execute(['workspace' => $workspaceId, 'buyer' => $buyerUserId]);
        $row = $query->fetch();
        if ($row === false) {
            return;
        }
        $commission = intdiv($paidMinor * (int) $row['commission_percent'], 100);
        if ($commission <= 0) {
            return;
        }
        $this->database->prepare(<<<'SQL'
INSERT IGNORE INTO affiliate_commissions (id, workspace_id, affiliate_user_id, referred_user_id, order_id, order_total_minor, commission_minor, status, created_at)
VALUES (:id, :workspace, :affiliate, :buyer, :order, :total, :commission, 'pending', UTC_TIMESTAMP(6))
SQL)->execute([
            'id' => Uuid::v7(), 'workspace' => $workspaceId, 'affiliate' => $row['affiliate_user_id'], 'buyer' => $buyerUserId,
            'order' => $orderId, 'total' => $paidMinor, 'commission' => $commission,
        ]);
    }

    /**
     * The owner's view: the programme and every affiliate with what they are owed.
     *
     * @return array<string, mixed>
     */
    public function admin(string $actorUserId, string $workspaceId): array
    {
        $this->requireManager($actorUserId, $workspaceId);
        $query = $this->database->prepare(<<<'SQL'
SELECT link.user_id, account.display_name, link.code,
       (SELECT COUNT(*) FROM affiliate_referrals referral WHERE referral.workspace_id = link.workspace_id AND referral.affiliate_user_id = link.user_id) AS referrals
FROM affiliate_links link
JOIN iam_users account ON account.id = link.user_id
WHERE link.workspace_id = :workspace
ORDER BY link.created_at
SQL);
        $query->execute(['workspace' => $workspaceId]);
        $affiliates = [];
        foreach ($query->fetchAll() as $row) {
            $affiliates[] = ['user_id' => (string) $row['user_id'], 'name' => (string) $row['display_name'], 'code' => (string) $row['code']]
                + $this->totals($workspaceId, (string) $row['user_id']);
        }
        usort($affiliates, static fn (array $a, array $b): int => $b['pending_minor'] <=> $a['pending_minor']);

        return $this->program($workspaceId) + ['affiliates' => $affiliates];
    }

    /** @param array<string, mixed> $input enabled, commission_percent, attribution_days */
    public function save(string $actorUserId, string $workspaceId, array $input): array
    {
        $this->requireManager($actorUserId, $workspaceId);
        $percent = filter_var($input['commission_percent'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 90]]);
        if ($percent === false) {
            throw new PlatformException('affiliate_percent_invalid', 'The commission is a whole percentage from 1 to 90.', 422);
        }
        $days = filter_var($input['attribution_days'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3650]]);
        if ($days === false) {
            throw new PlatformException('affiliate_days_invalid', 'The window is 1 to 3650 days.', 422);
        }
        $enabled = ($input['enabled'] ?? false) === true;
        $this->database->prepare(<<<'SQL'
INSERT INTO affiliate_programs (workspace_id, enabled, commission_percent, attribution_days, updated_by_user_id, updated_at)
VALUES (:workspace, :enabled, :percent, :days, :actor, UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), commission_percent = VALUES(commission_percent),
    attribution_days = VALUES(attribution_days), updated_by_user_id = VALUES(updated_by_user_id), updated_at = UTC_TIMESTAMP(6)
SQL)->execute(['workspace' => $workspaceId, 'enabled' => $enabled ? 1 : 0, 'percent' => $percent, 'days' => $days, 'actor' => $actorUserId]);
        $this->audit?->record($workspaceId, $actorUserId, 'affiliate.program.save', 'tenant_workspace', $workspaceId, 'success', [
            'enabled' => $enabled, 'commission_percent' => $percent, 'attribution_days' => $days,
        ]);

        return $this->program($workspaceId);
    }

    /** Marks an affiliate's pending commissions paid out. @return array{count:int,total_minor:int} */
    public function payOut(string $actorUserId, string $workspaceId, string $affiliateUserId): array
    {
        $this->requireManager($actorUserId, $workspaceId);
        $pending = $this->database->prepare("SELECT COUNT(*), COALESCE(SUM(commission_minor), 0) FROM affiliate_commissions WHERE workspace_id = :workspace AND affiliate_user_id = :user AND status = 'pending'");
        $pending->execute(['workspace' => $workspaceId, 'user' => $affiliateUserId]);
        [$count, $total] = $pending->fetch(PDO::FETCH_NUM);
        $this->database->prepare(<<<'SQL'
UPDATE affiliate_commissions SET status = 'paid_out', paid_out_at = UTC_TIMESTAMP(6), paid_out_by_user_id = :actor
WHERE workspace_id = :workspace AND affiliate_user_id = :user AND status = 'pending'
SQL)->execute(['actor' => $actorUserId, 'workspace' => $workspaceId, 'user' => $affiliateUserId]);
        $this->audit?->record($workspaceId, $actorUserId, 'affiliate.pay_out', 'iam_user', $affiliateUserId, 'success', ['count' => (int) $count, 'total_minor' => (int) $total]);

        return ['count' => (int) $count, 'total_minor' => (int) $total];
    }

    /** @return array{referrals:int,buyers:int,pending_minor:int,paid_out_minor:int} */
    private function totals(string $workspaceId, string $userId): array
    {
        $referrals = $this->database->prepare('SELECT COUNT(*) FROM affiliate_referrals WHERE workspace_id = :workspace AND affiliate_user_id = :user');
        $referrals->execute(['workspace' => $workspaceId, 'user' => $userId]);
        $money = $this->database->prepare(<<<'SQL'
SELECT COUNT(DISTINCT referred_user_id) AS buyers,
       COALESCE(SUM(CASE WHEN status = 'pending' THEN commission_minor END), 0) AS pending,
       COALESCE(SUM(CASE WHEN status = 'paid_out' THEN commission_minor END), 0) AS paid
FROM affiliate_commissions WHERE workspace_id = :workspace AND affiliate_user_id = :user AND status <> 'void'
SQL);
        $money->execute(['workspace' => $workspaceId, 'user' => $userId]);
        $row = $money->fetch() ?: [];

        return [
            'referrals' => (int) $referrals->fetchColumn(),
            'buyers' => (int) ($row['buyers'] ?? 0),
            'pending_minor' => (int) ($row['pending'] ?? 0),
            'paid_out_minor' => (int) ($row['paid'] ?? 0),
        ];
    }

    private function requireManager(string $actorUserId, string $workspaceId): void
    {
        if ($this->access === null) {
            throw new PlatformException('forbidden', 'The scoped permission was not granted.', 403);
        }
        $this->access->requireWorkspace($actorUserId, $workspaceId, 'commerce.manage_catalog');
    }
}
