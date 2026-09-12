<?php

declare(strict_types=1);

namespace Fanoos\Platform\Messaging;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

final class MessagingLinkService
{
    private const PLATFORMS = ['telegram', 'bale'];

    public function __construct(
        private readonly PDO $database,
        private readonly AuditLogger $audit,
        private readonly ChannelSubjectProtector $subjects,
        private readonly int $challengeTtlSeconds = 300,
        private readonly int $cooldownSeconds = 30,
        private readonly int $maximumChallengesPerTenMinutes = 5,
    ) {
    }

    /** @return array{challenge_id:string,challenge_token:string,platform:string,expires_at:string} */
    public function createChallenge(string $userId, string $platform, ?int $now = null): array
    {
        $platform = $this->platform($platform);
        $now ??= time();
        $active = $this->database->prepare("SELECT 1 FROM iam_users WHERE id = :user AND status = 'active' AND deleted_at IS NULL");
        $active->execute(['user' => $userId]);
        if ($active->fetchColumn() === false) {
            throw new PlatformException('account_unavailable', 'Account is unavailable.', 403);
        }

        return Transaction::run($this->database, function () use ($userId, $platform, $now): array {
            $rate = $this->database->prepare(<<<'SQL'
SELECT COUNT(*) AS recent_count, MAX(created_at) AS last_created
FROM messaging_link_challenges
WHERE user_id = :user AND platform = :platform AND created_at >= FROM_UNIXTIME(:window_start)
FOR UPDATE
SQL);
            $rate->execute(['user' => $userId, 'platform' => $platform, 'window_start' => $now - 600]);
            $row = $rate->fetch();
            $last = $row !== false && $row['last_created'] !== null ? strtotime((string) $row['last_created'] . ' UTC') : false;
            if ($last !== false && $now - $last < $this->cooldownSeconds) {
                throw new PlatformException('link_challenge_cooldown', 'Create another link challenge later.', 429);
            }
            if ($row !== false && (int) $row['recent_count'] >= $this->maximumChallengesPerTenMinutes) {
                throw new PlatformException('link_challenge_rate_limited', 'Too many link challenges were created.', 429);
            }

            $token = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
            $id = Uuid::v7();
            $expires = $now + $this->challengeTtlSeconds;
            $insert = $this->database->prepare(<<<'SQL'
INSERT INTO messaging_link_challenges (
    id, user_id, platform, token_digest, expires_at, consumed_at, consumed_link_id, created_at
) VALUES (:id, :user, :platform, :digest, FROM_UNIXTIME(:expires), NULL, NULL, FROM_UNIXTIME(:created))
SQL);
            $insert->bindValue(':id', $id);
            $insert->bindValue(':user', $userId);
            $insert->bindValue(':platform', $platform);
            $insert->bindValue(':digest', hash('sha256', $token, true), PDO::PARAM_LOB);
            $insert->bindValue(':expires', $expires, PDO::PARAM_INT);
            $insert->bindValue(':created', $now, PDO::PARAM_INT);
            $insert->execute();
            $this->audit->record(null, $userId, 'messaging.link_challenge.create', 'messaging_link_challenge', $id, 'success', ['platform' => $platform]);

            return [
                'challenge_id' => $id,
                'challenge_token' => $token,
                'platform' => $platform,
                'expires_at' => gmdate(DATE_ATOM, $expires),
            ];
        });
    }

    /** @return array{link_id:string,user_id:string,platform:string} */
    public function consumeChallenge(string $platform, string $challengeToken, string $platformSubject, ?int $now = null): array
    {
        $platform = $this->platform($platform);
        $now ??= time();
        if ($challengeToken === '' || strlen($challengeToken) > 128) {
            throw new PlatformException('link_challenge_invalid', 'Link challenge is invalid.', 400);
        }
        $tokenDigest = hash('sha256', $challengeToken, true);

        return Transaction::run($this->database, function () use ($platform, $platformSubject, $tokenDigest, $now): array {
            $challenge = $this->database->prepare(<<<'SQL'
SELECT id, user_id, platform, expires_at, consumed_at
FROM messaging_link_challenges
WHERE token_digest = :digest
LIMIT 1
FOR UPDATE
SQL);
            $challenge->bindValue(':digest', $tokenDigest, PDO::PARAM_LOB);
            $challenge->execute();
            $row = $challenge->fetch();
            if ($row === false || !hash_equals((string) $row['platform'], $platform)) {
                throw new PlatformException('link_challenge_invalid', 'Link challenge is invalid.', 404);
            }
            if ($row['consumed_at'] !== null) {
                throw new PlatformException('link_challenge_used', 'Link challenge was already used.', 409);
            }
            $expires = strtotime((string) $row['expires_at'] . ' UTC');
            if ($expires === false || $expires < $now) {
                throw new PlatformException('link_challenge_expired', 'Link challenge expired.', 410);
            }

            $userId = (string) $row['user_id'];
            $linkId = $this->linkSubjectToUser($platform, $platformSubject, $userId);

            $consume = $this->database->prepare('UPDATE messaging_link_challenges SET consumed_at = UTC_TIMESTAMP(6), consumed_link_id = :link WHERE id = :id AND consumed_at IS NULL');
            $consume->execute(['link' => $linkId, 'id' => $row['id']]);
            if ($consume->rowCount() !== 1) {
                throw new PlatformException('link_challenge_used', 'Link challenge was already used.', 409);
            }
            $this->audit->record(null, $userId, 'messaging.link', 'messaging_link', $linkId, 'success', ['platform' => $platform]);
            return ['link_id' => $linkId, 'user_id' => $userId, 'platform' => $platform];
        });
    }

    /**
     * Links a platform subject to an already-identified canonical user, without
     * a challenge token. Used where some other flow already proved the caller
     * owns the platform subject well enough to act as that identity (e.g. the
     * onboarding join wizard, where phone OTP verification is the proof) --
     * the challenge/token dance exists to prove that binding when nothing else
     * already has, not as an end in itself. Same conflict rules as
     * consumeChallenge: a platform subject already linked to a different user
     * is refused, and a user already actively linked to a different subject on
     * this platform must unlink first.
     */
    public function establishLink(string $userId, string $platform, string $platformSubject): string
    {
        $platform = $this->platform($platform);
        return Transaction::run($this->database, function () use ($userId, $platform, $platformSubject): string {
            $active = $this->database->prepare("SELECT 1 FROM iam_users WHERE id = :user AND status = 'active' AND deleted_at IS NULL");
            $active->execute(['user' => $userId]);
            if ($active->fetchColumn() === false) {
                throw new PlatformException('account_unavailable', 'Account is unavailable.', 403);
            }
            return $this->linkSubjectToUser($platform, $platformSubject, $userId);
        });
    }

    private function linkSubjectToUser(string $platform, string $platformSubject, string $userId): string
    {
        $subjectDigest = $this->subjects->digest($platform, $platformSubject);
        $subjectCiphertext = $this->subjects->encrypt($platform, $platformSubject);

        $subjectLookup = $this->database->prepare('SELECT id, user_id, status FROM messaging_links WHERE platform = :platform AND subject_digest = :digest LIMIT 1 FOR UPDATE');
        $subjectLookup->bindValue(':platform', $platform);
        $subjectLookup->bindValue(':digest', $subjectDigest, PDO::PARAM_LOB);
        $subjectLookup->execute();
        $subjectLink = $subjectLookup->fetch();
        if ($subjectLink !== false && !hash_equals((string) $subjectLink['user_id'], $userId)) {
            throw new PlatformException('platform_subject_conflict', 'Messaging account is linked to another account.', 409);
        }

        $userLookup = $this->database->prepare('SELECT id, subject_digest, status FROM messaging_links WHERE user_id = :user AND platform = :platform LIMIT 1 FOR UPDATE');
        $userLookup->execute(['user' => $userId, 'platform' => $platform]);
        $userLink = $userLookup->fetch();
        if ($userLink !== false && $userLink['status'] === 'active' && !hash_equals((string) $userLink['subject_digest'], $subjectDigest)) {
            throw new PlatformException('platform_already_linked', 'Unlink the current messaging account before linking another.', 409);
        }

        if ($userLink === false) {
            $linkId = Uuid::v7();
            $insert = $this->database->prepare(<<<'SQL'
INSERT INTO messaging_links (
    id, user_id, platform, subject_digest, subject_ciphertext, status,
    linked_at, revoked_at, revoke_reason, updated_at
) VALUES (:id, :user, :platform, :digest, :ciphertext, 'active', UTC_TIMESTAMP(6), NULL, NULL, UTC_TIMESTAMP(6))
SQL);
            $insert->bindValue(':id', $linkId);
            $insert->bindValue(':user', $userId);
            $insert->bindValue(':platform', $platform);
            $insert->bindValue(':digest', $subjectDigest, PDO::PARAM_LOB);
            $insert->bindValue(':ciphertext', $subjectCiphertext, PDO::PARAM_LOB);
            $insert->execute();
        } else {
            $linkId = (string) $userLink['id'];
            $update = $this->database->prepare(<<<'SQL'
UPDATE messaging_links
SET subject_digest = :digest, subject_ciphertext = :ciphertext, status = 'active',
    linked_at = UTC_TIMESTAMP(6), revoked_at = NULL, revoke_reason = NULL, updated_at = UTC_TIMESTAMP(6)
WHERE id = :id AND user_id = :user AND platform = :platform
SQL);
            $update->bindValue(':digest', $subjectDigest, PDO::PARAM_LOB);
            $update->bindValue(':ciphertext', $subjectCiphertext, PDO::PARAM_LOB);
            $update->bindValue(':id', $linkId);
            $update->bindValue(':user', $userId);
            $update->bindValue(':platform', $platform);
            $update->execute();
        }

        return $linkId;
    }

    public function revoke(string $userId, string $platform, string $reason = 'user_unlink'): void
    {
        $platform = $this->platform($platform);
        $reason = trim($reason);
        if ($reason === '') {
            $reason = 'user_unlink';
        }
        Transaction::run($this->database, function () use ($userId, $platform, $reason): void {
            $find = $this->database->prepare("SELECT id FROM messaging_links WHERE user_id = :user AND platform = :platform AND status = 'active' FOR UPDATE");
            $find->execute(['user' => $userId, 'platform' => $platform]);
            $linkId = $find->fetchColumn();
            if ($linkId === false) {
                throw new PlatformException('messaging_link_not_found', 'Active messaging link was not found.', 404);
            }
            $this->database->prepare("UPDATE messaging_links SET status = 'revoked', revoked_at = UTC_TIMESTAMP(6), revoke_reason = :reason, updated_at = UTC_TIMESTAMP(6) WHERE id = :id")
                ->execute(['reason' => $reason, 'id' => $linkId]);
            $this->database->prepare('DELETE FROM messaging_channel_contexts WHERE link_id = :link')->execute(['link' => $linkId]);
            $this->audit->record(null, $userId, 'messaging.unlink', 'messaging_link', (string) $linkId, 'success', ['platform' => $platform]);
        });
    }

    /** @return array{link_id:string,user_id:string}|null */
    public function resolve(string $platform, string $platformSubject): ?array
    {
        $platform = $this->platform($platform);
        $query = $this->database->prepare(<<<'SQL'
SELECT link.id, link.user_id
FROM messaging_links link
JOIN iam_users user ON user.id = link.user_id
WHERE link.platform = :platform AND link.subject_digest = :digest AND link.status = 'active'
  AND link.revoked_at IS NULL AND user.status = 'active' AND user.deleted_at IS NULL
LIMIT 1
SQL);
        $query->bindValue(':platform', $platform);
        $query->bindValue(':digest', $this->subjects->digest($platform, $platformSubject), PDO::PARAM_LOB);
        $query->execute();
        $row = $query->fetch();
        return $row === false ? null : ['link_id' => (string) $row['id'], 'user_id' => (string) $row['user_id']];
    }

    /** @return list<array<string,mixed>> */
    public function workspaces(string $linkId): array
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT workspace.id, workspace.slug, workspace.name
FROM messaging_links link
JOIN tenant_workspace_memberships membership ON membership.user_id = link.user_id
JOIN tenant_workspaces workspace ON workspace.id = membership.workspace_id
WHERE link.id = :link AND link.status = 'active' AND link.revoked_at IS NULL
  AND membership.status = 'active' AND (membership.ended_at IS NULL OR membership.ended_at > UTC_TIMESTAMP(6))
  AND workspace.status = 'active' AND workspace.archived_at IS NULL
ORDER BY workspace.name, workspace.id
SQL);
        $query->execute(['link' => $linkId]);
        return $query->fetchAll();
    }

    public function selectWorkspace(string $linkId, string $workspaceId): void
    {
        $membership = $this->database->prepare(<<<'SQL'
SELECT 1
FROM messaging_links link
JOIN tenant_workspace_memberships membership ON membership.user_id = link.user_id
JOIN tenant_workspaces workspace ON workspace.id = membership.workspace_id
WHERE link.id = :link AND link.status = 'active' AND link.revoked_at IS NULL
  AND membership.workspace_id = :workspace AND membership.status = 'active'
  AND (membership.ended_at IS NULL OR membership.ended_at > UTC_TIMESTAMP(6))
  AND workspace.status = 'active' AND workspace.archived_at IS NULL
LIMIT 1
SQL);
        $membership->execute(['link' => $linkId, 'workspace' => $workspaceId]);
        if ($membership->fetchColumn() === false) {
            throw new PlatformException('workspace_forbidden', 'Linked account is not an active member of this workspace.', 403);
        }
        $this->database->prepare(<<<'SQL'
INSERT INTO messaging_channel_contexts (link_id, workspace_id, updated_at)
VALUES (:link, :workspace, UTC_TIMESTAMP(6))
ON DUPLICATE KEY UPDATE workspace_id = VALUES(workspace_id), updated_at = UTC_TIMESTAMP(6)
SQL)->execute(['link' => $linkId, 'workspace' => $workspaceId]);
    }

    public function selectedWorkspace(string $linkId): ?string
    {
        $query = $this->database->prepare(<<<'SQL'
SELECT context.workspace_id
FROM messaging_channel_contexts context
JOIN messaging_links link ON link.id = context.link_id AND link.status = 'active' AND link.revoked_at IS NULL
JOIN tenant_workspace_memberships membership ON membership.user_id = link.user_id AND membership.workspace_id = context.workspace_id
JOIN tenant_workspaces workspace ON workspace.id = context.workspace_id
WHERE context.link_id = :link AND membership.status = 'active'
  AND (membership.ended_at IS NULL OR membership.ended_at > UTC_TIMESTAMP(6))
  AND workspace.status = 'active' AND workspace.archived_at IS NULL
LIMIT 1
SQL);
        $query->execute(['link' => $linkId]);
        $workspace = $query->fetchColumn();
        if ($workspace === false) {
            $this->database->prepare('DELETE FROM messaging_channel_contexts WHERE link_id = :link')->execute(['link' => $linkId]);
            return null;
        }
        return (string) $workspace;
    }

    public function outboundSubject(string $linkId, string $expectedPlatform): string
    {
        $expectedPlatform = $this->platform($expectedPlatform);
        $query = $this->database->prepare("SELECT platform, subject_ciphertext FROM messaging_links WHERE id = :id AND status = 'active' AND revoked_at IS NULL");
        $query->execute(['id' => $linkId]);
        $row = $query->fetch();
        if ($row === false || !hash_equals((string) $row['platform'], $expectedPlatform)) {
            throw new PlatformException('messaging_link_not_found', 'Active messaging link was not found.', 404);
        }
        return $this->subjects->decrypt($expectedPlatform, (string) $row['subject_ciphertext']);
    }

    private function platform(string $platform): string
    {
        $platform = strtolower(trim($platform));
        if (!in_array($platform, self::PLATFORMS, true)) {
            throw new PlatformException('messaging_platform_invalid', 'Messaging platform is not supported.', 422);
        }
        return $platform;
    }
}
