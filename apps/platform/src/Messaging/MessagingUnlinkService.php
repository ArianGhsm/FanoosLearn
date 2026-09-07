<?php

declare(strict_types=1);

namespace Fanoos\Platform\Messaging;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use PDO;

final class MessagingUnlinkService
{
    private const PLATFORMS = ['telegram', 'bale'];

    public function __construct(
        private readonly PDO $database,
        private readonly AuditLogger $audit,
        private readonly ChannelSubjectProtector $subjects,
    ) {
    }

    /** @return array{revoked:bool,idempotent:bool} */
    public function revokeSubject(string $platform, string $subject): array
    {
        $platform = strtolower(trim($platform));
        if (!in_array($platform, self::PLATFORMS, true) || $subject === '' || strlen($subject) > 160) {
            throw new PlatformException('messaging_unlink_invalid', 'Messaging unlink request is invalid.', 422);
        }
        $digest = $this->subjects->digest($platform, $subject);

        return Transaction::run($this->database, function () use ($platform, $digest): array {
            $query = $this->database->prepare(<<<'SQL'
SELECT id, user_id, status, revoked_at
FROM messaging_links
WHERE platform = :platform AND subject_digest = :digest
LIMIT 1
FOR UPDATE
SQL);
            $query->bindValue(':platform', $platform);
            $query->bindValue(':digest', $digest, PDO::PARAM_LOB);
            $query->execute();
            $row = $query->fetch();
            if ($row === false) {
                return ['revoked' => false, 'idempotent' => true];
            }

            $linkId = (string) $row['id'];
            $userId = (string) $row['user_id'];
            $this->database->prepare('DELETE FROM messaging_channel_contexts WHERE link_id = :link')->execute(['link' => $linkId]);
            if ($row['status'] !== 'active' || $row['revoked_at'] !== null) {
                return ['revoked' => false, 'idempotent' => true];
            }

            $this->database->prepare(<<<'SQL'
UPDATE messaging_links
SET status = 'revoked', revoked_at = UTC_TIMESTAMP(6), revoke_reason = 'channel_unlink', updated_at = UTC_TIMESTAMP(6)
WHERE id = :id AND status = 'active' AND revoked_at IS NULL
SQL)->execute(['id' => $linkId]);
            $this->audit->record(null, $userId, 'messaging.unlink', 'messaging_link', $linkId, 'success', ['platform' => $platform, 'source' => 'signed_adapter']);
            return ['revoked' => true, 'idempotent' => false];
        });
    }
}
