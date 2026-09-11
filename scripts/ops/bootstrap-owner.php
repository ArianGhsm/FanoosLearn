<?php

declare(strict_types=1);

/*
 * Bootstrap the first FANOOS owner identity.
 *
 * A freshly provisioned FANOOS database has no `iam_users` row holding the
 * platform-scoped `deployment.manage` permission, so no one can trigger the
 * owner-facing "Update Server" bot action. This is a one-time, server-side
 * bootstrap path executed directly by the operator to create that first
 * owner (a user, a platform role assignment and a protected messaging
 * link) without a web account or an authenticated web session.
 *
 * It intentionally bypasses the normal challenge/confirm proof-of-possession
 * flow in Fanoos\Platform\Messaging\MessagingLinkService, which requires an
 * authenticated session to prove the operator controls both the FANOOS
 * account and the messaging account. That proof is unavailable before any
 * owner exists to grant one, and the trust level here matches
 * register-service.php: a privileged tool run by the operator on the
 * server. Routine linking (every owner/admin after this one) must continue
 * to go through MessagingLinkService's challenge/confirm flow, not this
 * script.
 *
 * Usage: php scripts/ops/bootstrap-owner.php <display-name> <platform> <subject> [role-key]
 */

use Fanoos\Platform\Messaging\ChannelSubjectProtector;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;
use Fanoos\Platform\Support\Uuid;

$root = dirname(__DIR__, 2);
require $root . '/apps/platform/bootstrap.php';

try {
    if ($argc !== 4 && $argc !== 5) {
        throw new RuntimeException('Usage: php bootstrap-owner.php <display-name> <platform> <subject> [role-key]');
    }
    [, $displayName, $platform, $subject] = $argv;
    $roleKey = $argv[4] ?? 'platform-super-admin';

    if ($displayName === '' || mb_strlen($displayName, 'UTF-8') > 160 || preg_match('/[\x00-\x1F\x7F]/', $displayName)) {
        throw new RuntimeException('Owner display name is invalid.');
    }
    if (!in_array($platform, ['telegram', 'bale'], true)) {
        throw new RuntimeException('Owner messaging platform must be telegram or bale.');
    }
    if (!preg_match('/^[0-9]{1,32}$/', $subject)) {
        throw new RuntimeException('Owner messaging platform subject is invalid.');
    }
    if (!in_array($roleKey, ['platform-super-admin', 'platform-deployment-operator'], true)) {
        throw new RuntimeException('Owner role key must be platform-super-admin or platform-deployment-operator.');
    }

    $config = RuntimeConfig::load();
    $protector = new ChannelSubjectProtector($config->requireString('FANOOS_MESSAGING_SUBJECT_KEY'));
    $db = DatabaseConnection::fromEnvironment();

    $db->beginTransaction();
    try {
        $digest = $protector->digest($platform, $subject);

        $linkLookup = $db->prepare('SELECT id, user_id FROM messaging_links WHERE platform = :platform AND subject_digest = :digest LIMIT 1 FOR UPDATE');
        $linkLookup->bindValue(':platform', $platform);
        $linkLookup->bindValue(':digest', $digest, PDO::PARAM_LOB);
        $linkLookup->execute();
        $existingLink = $linkLookup->fetch();

        if ($existingLink !== false) {
            $userId = (string) $existingLink['user_id'];
            $userCreated = false;
        } else {
            $userId = Uuid::v7();
            $db->prepare("INSERT INTO iam_users (id, display_name, status, locale, version, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
                ->execute(['id' => $userId, 'name' => $displayName]);
            $userCreated = true;
        }

        $roleLookup = $db->prepare("SELECT id FROM rbac_role_templates WHERE role_key = :key AND status = 'active'");
        $roleLookup->execute(['key' => $roleKey]);
        $roleTemplateId = $roleLookup->fetchColumn();
        if ($roleTemplateId === false) {
            throw new RuntimeException("Role template is unavailable: {$roleKey}");
        }

        $assignmentInsert = $db->prepare(<<<'SQL'
INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at)
VALUES (:id, :user, :role, '00000000-0000-7000-8000-000000000001', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE id = id
SQL);
        $assignmentInsert->execute(['id' => Uuid::v7(), 'user' => $userId, 'role' => $roleTemplateId]);
        $roleCreated = $assignmentInsert->rowCount() === 1;

        if ($existingLink === false) {
            $ciphertext = $protector->encrypt($platform, $subject);
            $linkInsert = $db->prepare(<<<'SQL'
INSERT INTO messaging_links (id, user_id, platform, subject_digest, subject_ciphertext, status, linked_at, updated_at)
VALUES (:id, :user, :platform, :digest, :ciphertext, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL);
            $linkInsert->bindValue(':id', Uuid::v7());
            $linkInsert->bindValue(':user', $userId);
            $linkInsert->bindValue(':platform', $platform);
            $linkInsert->bindValue(':digest', $digest, PDO::PARAM_LOB);
            $linkInsert->bindValue(':ciphertext', $ciphertext, PDO::PARAM_LOB);
            $linkInsert->execute();
            $linkCreated = true;
        } else {
            $linkCreated = false;
        }

        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }

    echo 'Owner user: ' . ($userCreated ? 'created' : 'already existed') . PHP_EOL;
    echo "Role assignment ({$roleKey}): " . ($roleCreated ? 'created' : 'already existed') . PHP_EOL;
    echo "Messaging link ({$platform}): " . ($linkCreated ? 'created' : 'already existed') . PHP_EOL;
    echo "Owner user id: {$userId}" . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Owner bootstrap failed: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
