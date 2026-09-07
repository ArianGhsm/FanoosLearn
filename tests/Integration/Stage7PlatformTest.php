<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Commerce\BotCommerceService;
use Fanoos\Platform\Commerce\CommerceService;
use Fanoos\Platform\Commerce\FakePaymentGateway;
use Fanoos\Platform\Content\DeliveryReceiptService;
use Fanoos\Platform\Content\ProtectedMediaEnqueueService;
use Fanoos\Platform\Content\ProtectedMediaJobService;
use Fanoos\Platform\Content\ProtectedResourceAuthorizer;
use Fanoos\Platform\Content\SecureDeliveryService;
use Fanoos\Platform\Core\WorkspacePlatformService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Messaging\ChannelSubjectProtector;
use Fanoos\Platform\Messaging\MessagingLinkService;
use Fanoos\Platform\Notifications\NotificationDeliveryService;
use Fanoos\Platform\Storage\SignedDownloadToken;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

final class Stage7PlatformTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $fixture = $this->fixture();
        $audit = new AuditLogger($this->database);
        $authorizer = new ScopeAuthorizer($this->database);
        $access = new AccessGate($this->database, $authorizer);
        $entitlements = new EntitlementService($this->database, $access, $audit);
        $protected = new ProtectedResourceAuthorizer($this->database, $authorizer, $entitlements);
        $links = new MessagingLinkService($this->database, $audit, new ChannelSubjectProtector(str_repeat('s', 32)), 300, 1, 10);
        $now = time();
        $challenge = $links->createChallenge($fixture['student'], 'bale', $now);
        $link = $links->consumeChallenge('bale', $challenge['challenge_token'], 'bale-stage7-student', $now + 1);

        $this->assertCommerce($fixture, $audit, $access, $entitlements);
        $this->assertNotifications($fixture, $audit, $access, $links);
        $this->assertDeliveryAndMedia($fixture, $audit, $protected, $links, $link['link_id']);

        $links->revoke($fixture['student'], 'bale');
        return $this->assertions;
    }

    /** @param array<string,string> $fixture */
    private function assertCommerce(array $fixture, AuditLogger $audit, AccessGate $access, EntitlementService $entitlements): void
    {
        $targetScope = Uuid::v7();
        $targetEntity = Uuid::v7();
        $this->database->prepare("INSERT INTO rbac_scopes (id, scope_type, entity_id, workspace_id, parent_scope_id, created_at) VALUES (:id, 'resource', :entity, :workspace, :parent, UTC_TIMESTAMP(6))")
            ->execute(['id' => $targetScope, 'entity' => $targetEntity, 'workspace' => $fixture['workspace_a'], 'parent' => $fixture['scope_a']]);
        $product = Uuid::v7();
        $price = Uuid::v7();
        $suffix = substr(str_replace('-', '', $product), -10);
        $this->database->prepare("INSERT INTO commerce_products (id, workspace_id, target_scope_id, product_key, name, status, created_at, updated_at) VALUES (:id, :workspace, :scope, :key, 'Stage 7 product', 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $product, 'workspace' => $fixture['workspace_a'], 'scope' => $targetScope, 'key' => 'stage7-' . $suffix]);
        $this->database->prepare("INSERT INTO commerce_price_versions (id, workspace_id, product_id, amount_minor, currency, valid_from, created_at) VALUES (:id, :workspace, :product, 321000, 'IRR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $price, 'workspace' => $fixture['workspace_a'], 'product' => $product]);

        $commerce = new CommerceService($this->database, $access, $entitlements, $audit, new FakePaymentGateway(), 'stage7-payment-callback-test-key-000000000000');
        $bot = new BotCommerceService($this->database, $access, $commerce, $entitlements);
        $key = 'stage7-order-' . $suffix;
        $order = $bot->createOrder($fixture['student'], $fixture['workspace_a'], $product, $key);
        self::assert($order['amount_minor'] === 321000 && $order['title'] === 'Stage 7 product', 'Bot order did not use canonical server price/title.');
        self::assert(!array_key_exists('callback_token', $order) && !array_key_exists('authority', $order), 'Bot order projection leaked payment proof material.');
        self::assert(($order['entitlement']['granted'] ?? true) === false, 'Unverified client-visible payment state granted an entitlement.');
        $duplicate = $bot->createOrder($fixture['student'], $fixture['workspace_a'], $product, $key);
        self::assert($duplicate['order_id'] === $order['order_id'], 'Duplicate bot order did not honor idempotency.');

        $server = $commerce->createOrder($fixture['student'], $fixture['workspace_a'], $product, $key);
        self::assert(isset($server['callback_token'], $server['authority']), 'Server payment edge could not retrieve canonical provider verification material.');
        $verified = $commerce->handleCallback((string) $server['callback_token'], (string) $server['authority'], ['status' => 'success']);
        self::assert($verified['status'] === 'paid', 'Provider-verified callback did not settle the canonical order.');
        $again = $commerce->handleCallback((string) $server['callback_token'], (string) $server['authority'], ['status' => 'success']);
        self::assert(($again['duplicate'] ?? false) === true, 'Duplicate provider callback was not idempotent.');
        $paid = $bot->status($fixture['student'], $fixture['workspace_a'], $order['order_id']);
        self::assert(($paid['entitlement']['granted'] ?? false) === true, 'Verified payment did not project the canonical entitlement.');
    }

    /** @param array<string,string> $fixture */
    private function assertNotifications(array $fixture, AuditLogger $audit, AccessGate $access, MessagingLinkService $links): void
    {
        $core = new WorkspacePlatformService($this->database, $access, $audit);
        $notificationId = $core->publishAnnouncement($fixture['representative'], $fixture['workspace_a'], 'Stage 7 notice', 'Canonical notification delivery test.');
        $delivery = new NotificationDeliveryService($this->database, $links, 60, 3);
        for ($i = 0; $i < 20; ++$i) {
            if ($delivery->projectNext() === null) {
                break;
            }
        }
        $claim = $delivery->claim('bale');
        self::assert(is_array($claim) && $claim['subject'] === 'bale-stage7-student', 'Bale notification adapter did not claim the canonical linked recipient.');
        self::assert(!isset($claim['payload']['user_id'], $claim['payload']['phone']), 'Notification payload leaked unnecessary identity data.');
        $retry = $delivery->receipt($claim['delivery_id'], $claim['lease_token'], 'retry-1', 'retry', null, 'transient_transport');
        self::assert($retry['state'] === 'retry' && $retry['idempotent'] === false, 'Notification retry receipt was not recorded.');
        $retryAgain = $delivery->receipt($claim['delivery_id'], $claim['lease_token'], 'retry-1', 'retry', null, 'transient_transport');
        self::assert($retryAgain['idempotent'] === true, 'Notification receipt replay was not idempotent.');
        $second = $delivery->claim('bale');
        self::assert(is_array($second) && $second['delivery_id'] === $claim['delivery_id'], 'Retryable notification was not reclaimed as the same delivery.');
        $done = $delivery->receipt($second['delivery_id'], $second['lease_token'], 'delivered-1', 'delivered', 'bale-message-opaque');
        self::assert($done['state'] === 'delivered', 'Notification success receipt did not reach terminal delivered state.');

        $count = $this->database->prepare('SELECT COUNT(*) FROM notification_channel_deliveries delivery JOIN notification_recipients recipient ON recipient.id = delivery.recipient_id WHERE recipient.notification_id = :notification AND recipient.user_id = :user AND recipient.channel = \'bale\'');
        $count->execute(['notification' => $notificationId, 'user' => $fixture['student']]);
        self::assert((int) $count->fetchColumn() === 1, 'Notification projection duplicated channel fan-out.');
    }

    /** @param array<string,string> $fixture */
    private function assertDeliveryAndMedia(array $fixture, AuditLogger $audit, ProtectedResourceAuthorizer $protected, MessagingLinkService $links, string $linkId): void
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT policy.resource_id, policy.target_scope_id, grant_record.id AS grant_id
FROM content_access_policies policy
JOIN entitlement_grants grant_record ON grant_record.workspace_id = policy.workspace_id
 AND grant_record.subject_user_id = :user AND grant_record.target_scope_id = policy.target_scope_id
 AND grant_record.revoked_at IS NULL
JOIN content_resources resource ON resource.id = policy.resource_id AND resource.workspace_id = policy.workspace_id
WHERE policy.workspace_id = :workspace AND policy.requires_entitlement = TRUE
  AND resource.lifecycle_status = 'published'
ORDER BY resource.updated_at DESC
LIMIT 1
SQL);
        $query->execute(['user' => $fixture['student'], 'workspace' => $fixture['workspace_a']]);
        $protectedFixture = $query->fetch();
        if ($protectedFixture === false) {
            throw new RuntimeException('Content-engine protected fixture is unavailable for Stage 7 delivery tests.');
        }

        $downloadKey = str_repeat('d', 32);
        $deliveryKey = str_repeat('i', 32);
        $tokens = new SignedDownloadToken($downloadKey);
        $delivery = new SecureDeliveryService($this->database, $protected, $tokens, $audit, $deliveryKey);
        $issued = $delivery->issue($fixture['student'], $fixture['workspace_a'], (string) $protectedFixture['resource_id'], 'bale');
        $served = $delivery->consume($issued['delivery_token'], $fixture['student'], $fixture['workspace_a']);
        self::assert(($served['forward_protection_required'] ?? false) === true && $served['download_token'] !== null, 'Protected delivery did not require channel protection and scoped object capability.');
        $this->expectCode('delivery_subject_mismatch', fn () => $delivery->consume($issued['delivery_token'], $fixture['student'], $fixture['workspace_b']));

        $receipts = new DeliveryReceiptService($this->database);
        $receipt = $receipts->record($fixture['workspace_a'], $issued['issuance_id'], 'bale', 'delivery-ok-1', 'delivered', 'cached-platform-file-id');
        self::assert($receipt['idempotent'] === false, 'Protected delivery receipt was not recorded.');
        $receiptReplay = $receipts->record($fixture['workspace_a'], $issued['issuance_id'], 'bale', 'delivery-ok-1', 'delivered', 'cached-platform-file-id');
        self::assert($receiptReplay['idempotent'] === true, 'Protected delivery receipt replay was not idempotent.');

        $this->database->prepare("UPDATE entitlement_grants SET revoked_at = UTC_TIMESTAMP(6), revoke_reason = 'stage7_test' WHERE id = :id")
            ->execute(['id' => $protectedFixture['grant_id']]);
        $this->expectCode('resource_access_denied', fn () => $delivery->consume($issued['delivery_token'], $fixture['student'], $fixture['workspace_a']));
        $this->database->prepare('UPDATE entitlement_grants SET revoked_at = NULL, revoke_reason = NULL WHERE id = :id')->execute(['id' => $protectedFixture['grant_id']]);

        $expired = $delivery->issue($fixture['student'], $fixture['workspace_a'], (string) $protectedFixture['resource_id'], 'bale', time() - 1000);
        $this->expectCode('delivery_token_expired', fn () => $delivery->consume($expired['delivery_token'], $fixture['student'], $fixture['workspace_a'], time()));

        $mediaIssuance = $delivery->issue($fixture['student'], $fixture['workspace_a'], (string) $protectedFixture['resource_id'], 'bale');
        $jobs = new ProtectedMediaJobService($this->database, $protected, $tokens, 60, 2);
        $enqueuer = new ProtectedMediaEnqueueService($this->database, $jobs);
        $job = $enqueuer->enqueue($fixture['workspace_a'], $mediaIssuance['issuance_id'], 'pdf-watermark-v1', ['max_input_bytes' => 50 * 1024 * 1024, 'max_pages' => 500, 'max_seconds' => 120]);
        $claim = $jobs->claim();
        self::assert(is_array($claim) && $claim['job_id'] === $job['job_id'], 'Protected media job could not be leased.');
        self::assert(isset($claim['object_capability']) && !isset($claim['storage_key'], $claim['path'], $claim['secret']), 'Protected media worker contract exposed an unscoped path or secret.');
        self::assert($claim['forensic_id'] !== '' && $claim['watermark_label'] !== '', 'Protected media worker contract omitted forensic metadata.');
        $completed = $jobs->complete($claim['job_id'], $claim['lease_token'], $claim['completion_key'], [
            'checksum_sha256' => hash('sha256', 'rendered-pdf'), 'size' => 2048,
            'mime' => 'application/pdf', 'artifact_ref' => 'media:' . substr(str_replace('-', '', Uuid::v7()), -16),
        ]);
        self::assert($completed['state'] === 'completed' && $completed['idempotent'] === false, 'Protected media completion was not persisted.');
        $completedAgain = $jobs->complete($claim['job_id'], $claim['lease_token'], $claim['completion_key'], [
            'checksum_sha256' => hash('sha256', 'rendered-pdf'), 'size' => 2048,
            'mime' => 'application/pdf', 'artifact_ref' => 'media:ignored-on-idempotent-replay',
        ]);
        self::assert($completedAgain['idempotent'] === true, 'Protected media completion replay was not idempotent.');
        self::assert($links->selectedWorkspace($linkId) === null || is_string($links->selectedWorkspace($linkId)), 'Messaging link context became invalid after protected delivery.');
    }

    /** @return array<string,string> */
    private function fixture(): array
    {
        $result = [];
        foreach (['Multi member' => 'student', 'Representative' => 'representative'] as $name => $key) {
            $query = $this->database->prepare('SELECT id FROM iam_users WHERE display_name = :name ORDER BY created_at DESC LIMIT 1');
            $query->execute(['name' => $name]);
            $result[$key] = (string) $query->fetchColumn();
        }
        foreach (['Fixture Workspace A' => 'workspace_a', 'Fixture Workspace B' => 'workspace_b'] as $name => $key) {
            $query = $this->database->prepare('SELECT id FROM tenant_workspaces WHERE name = :name ORDER BY created_at DESC LIMIT 1');
            $query->execute(['name' => $name]);
            $result[$key] = (string) $query->fetchColumn();
        }
        foreach (['a', 'b'] as $side) {
            $scope = $this->database->prepare("SELECT id FROM rbac_scopes WHERE scope_type = 'workspace' AND workspace_id = :workspace AND entity_id = :workspace LIMIT 1");
            $scope->execute(['workspace' => $result['workspace_' . $side]]);
            $result['scope_' . $side] = (string) $scope->fetchColumn();
        }
        foreach ($result as $key => $value) {
            if ($value === '') {
                throw new RuntimeException("Missing Stage 7 fixture value: {$key}");
            }
        }
        return $result;
    }

    private function expectCode(string $code, callable $operation): void
    {
        ++$this->assertions;
        try {
            $operation();
        } catch (PlatformException $error) {
            if ($error->errorCode === $code) {
                return;
            }
            throw new RuntimeException("Expected {$code}, got {$error->errorCode}.");
        }
        throw new RuntimeException("Expected PlatformException {$code}.");
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
