<?php

declare(strict_types=1);

namespace Fanoos\Platform\Engagement;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Content\ProgressService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Transaction;
use Fanoos\Platform\Support\Uuid;
use PDO;

/**
 * اتاق مطالعه گروهی (docs/product/08_ENGAGEMENT.md).
 *
 * A private room in a workspace, joined only through its invite code. Its
 * members see each other's names and today's study -- minutes, questions
 * answered and right, points -- side by side. That is what they joined
 * for, and the only thing a room shows; nobody outside the room sees it.
 *
 * At most MAX_MEMBERS present in a room and MAX_ROOMS rooms for one person.
 * Anyone may leave; the creator may remove a member. A room whose last
 * member leaves is archived.
 */
final class StudyRoomService
{
    public const MAX_MEMBERS = 10;
    public const MAX_ROOMS = 10;
    private const INVITE_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public function __construct(
        private readonly PDO $database,
        private readonly AccessGate $access,
        private readonly ?AuditLogger $audit = null,
    ) {
    }

    /**
     * The caller's rooms, each with its members' study today, most minutes first.
     *
     * @return list<array<string, mixed>>
     */
    public function rooms(string $userId, string $workspaceId, ?int $now = null): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $query = $this->database->prepare(<<<'SQL'
SELECT room.id, room.name, room.invite_code, room.created_by_user_id
FROM engagement_study_rooms room
JOIN engagement_study_room_members mine ON mine.room_id = room.id AND mine.user_id = :user AND mine.left_at IS NULL
WHERE room.workspace_id = :workspace AND room.archived_at IS NULL
ORDER BY mine.joined_at
SQL);
        $query->execute(['user' => $userId, 'workspace' => $workspaceId]);
        $progress = new ProgressService($this->database, $this->access);
        $points = new PointsService($this->database);
        $today = [];

        return array_map(function (array $room) use ($userId, $workspaceId, $now, $progress, $points, &$today): array {
            $members = $this->database->prepare(<<<'SQL'
SELECT member.user_id, account.display_name, member.joined_at
FROM engagement_study_room_members member
JOIN iam_users account ON account.id = member.user_id
WHERE member.room_id = :room AND member.left_at IS NULL
ORDER BY member.joined_at
SQL);
            $members->execute(['room' => $room['id']]);
            $rows = [];
            foreach ($members->fetchAll() as $member) {
                $id = (string) $member['user_id'];
                $today[$id] ??= $progress->today($id, $workspaceId, $now) + ['points' => $points->today($workspaceId, $id, $now)];
                $rows[] = [
                    'user_id' => $id, 'name' => (string) $member['display_name'], 'me' => $id === $userId,
                    'creator' => $id === (string) $room['created_by_user_id'],
                ] + $today[$id];
            }
            usort($rows, static fn (array $a, array $b): int => [$b['minutes'], $b['points']] <=> [$a['minutes'], $a['points']]);

            return [
                'id' => (string) $room['id'], 'name' => (string) $room['name'], 'invite_code' => (string) $room['invite_code'],
                'mine' => (string) $room['created_by_user_id'] === $userId,
                'members' => $rows,
                'total_minutes' => array_sum(array_column($rows, 'minutes')),
            ];
        }, $query->fetchAll());
    }

    /** @return array{id:string,invite_code:string} */
    public function create(string $userId, string $workspaceId, string $name): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '' || mb_strlen($name) > 60) {
            throw new PlatformException('study_room_name_invalid', 'A room needs a name of up to 60 characters.', 422);
        }

        return Transaction::run($this->database, function () use ($userId, $workspaceId, $name): array {
            $this->requireRoomSlot($userId, $workspaceId);
            $id = Uuid::v7();
            $code = $this->inviteCode();
            $this->database->prepare(<<<'SQL'
INSERT INTO engagement_study_rooms (id, workspace_id, name, invite_code, created_by_user_id, created_at)
VALUES (:id, :workspace, :name, :code, :user, UTC_TIMESTAMP(6))
SQL)->execute(['id' => $id, 'workspace' => $workspaceId, 'name' => $name, 'code' => $code, 'user' => $userId]);
            $this->enter($id, $workspaceId, $userId);
            $this->audit?->record($workspaceId, $userId, 'engagement.study_room.create', 'engagement_study_room', $id);

            return ['id' => $id, 'invite_code' => $code];
        });
    }

    /** @return array{id:string,name:string} */
    public function join(string $userId, string $workspaceId, string $inviteCode): array
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        $code = strtolower(trim($inviteCode));

        return Transaction::run($this->database, function () use ($userId, $workspaceId, $code): array {
            $room = $this->database->prepare('SELECT id, name FROM engagement_study_rooms WHERE invite_code = :code AND workspace_id = :workspace AND archived_at IS NULL FOR UPDATE');
            $room->execute(['code' => $code, 'workspace' => $workspaceId]);
            $found = $room->fetch();
            if ($found === false) {
                throw new PlatformException('study_room_not_found', 'This invite link is not valid.', 404);
            }
            $present = $this->database->prepare('SELECT left_at IS NULL FROM engagement_study_room_members WHERE room_id = :room AND user_id = :user');
            $present->execute(['room' => $found['id'], 'user' => $userId]);
            $here = $present->fetchColumn();
            if ($here !== false && (int) $here === 1) {
                return ['id' => (string) $found['id'], 'name' => (string) $found['name']];
            }
            $count = $this->database->prepare('SELECT COUNT(*) FROM engagement_study_room_members WHERE room_id = :room AND left_at IS NULL');
            $count->execute(['room' => $found['id']]);
            if ((int) $count->fetchColumn() >= self::MAX_MEMBERS) {
                throw new PlatformException('study_room_full', 'This room is full.', 409);
            }
            $this->requireRoomSlot($userId, $workspaceId);
            $this->enter((string) $found['id'], $workspaceId, $userId);
            $this->audit?->record($workspaceId, $userId, 'engagement.study_room.join', 'engagement_study_room', (string) $found['id']);

            return ['id' => (string) $found['id'], 'name' => (string) $found['name']];
        });
    }

    public function leave(string $userId, string $workspaceId, string $roomId): void
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        Transaction::run($this->database, function () use ($userId, $workspaceId, $roomId): void {
            $this->depart($roomId, $workspaceId, $userId, 'study_room_not_found');
            $this->audit?->record($workspaceId, $userId, 'engagement.study_room.leave', 'engagement_study_room', $roomId);
        });
    }

    public function remove(string $userId, string $workspaceId, string $roomId, string $memberId): void
    {
        $this->access->requireWorkspace($userId, $workspaceId, 'exam.take');
        Transaction::run($this->database, function () use ($userId, $workspaceId, $roomId, $memberId): void {
            $room = $this->database->prepare('SELECT created_by_user_id FROM engagement_study_rooms WHERE id = :room AND workspace_id = :workspace AND archived_at IS NULL FOR UPDATE');
            $room->execute(['room' => $roomId, 'workspace' => $workspaceId]);
            $creator = $room->fetchColumn();
            if ($creator === false) {
                throw new PlatformException('study_room_not_found', 'This room was not found.', 404);
            }
            if (!hash_equals((string) $creator, $userId)) {
                throw new PlatformException('study_room_not_creator', 'Only the room\'s creator can remove a member.', 403);
            }
            if ($memberId === $userId) {
                throw new PlatformException('study_room_remove_self', 'Leave the room instead.', 422);
            }
            $this->depart($roomId, $workspaceId, $memberId, 'study_room_member_not_found');
            $this->audit?->record($workspaceId, $userId, 'engagement.study_room.remove', 'engagement_study_room', $roomId, 'success', ['member' => $memberId]);
        });
    }

    private function enter(string $roomId, string $workspaceId, string $userId): void
    {
        $this->database->prepare(<<<'SQL'
INSERT INTO engagement_study_room_members (room_id, workspace_id, user_id, joined_at, left_at)
VALUES (:room, :workspace, :user, UTC_TIMESTAMP(6), NULL)
ON DUPLICATE KEY UPDATE joined_at = UTC_TIMESTAMP(6), left_at = NULL
SQL)->execute(['room' => $roomId, 'workspace' => $workspaceId, 'user' => $userId]);
    }

    /** Marks a member gone; archives the room when nobody is left. */
    private function depart(string $roomId, string $workspaceId, string $userId, string $missing): void
    {
        $leave = $this->database->prepare(<<<'SQL'
UPDATE engagement_study_room_members member
JOIN engagement_study_rooms room ON room.id = member.room_id AND room.workspace_id = :workspace AND room.archived_at IS NULL
SET member.left_at = UTC_TIMESTAMP(6)
WHERE member.room_id = :room AND member.user_id = :user AND member.left_at IS NULL
SQL);
        $leave->execute(['workspace' => $workspaceId, 'room' => $roomId, 'user' => $userId]);
        if ($leave->rowCount() === 0) {
            throw new PlatformException($missing, 'Not a member of this room.', 404);
        }
        $left = $this->database->prepare('SELECT COUNT(*) FROM engagement_study_room_members WHERE room_id = :room AND left_at IS NULL');
        $left->execute(['room' => $roomId]);
        if ((int) $left->fetchColumn() === 0) {
            $this->database->prepare('UPDATE engagement_study_rooms SET archived_at = UTC_TIMESTAMP(6) WHERE id = :room')->execute(['room' => $roomId]);
        }
    }

    private function requireRoomSlot(string $userId, string $workspaceId): void
    {
        $rooms = $this->database->prepare(<<<'SQL'
SELECT COUNT(*) FROM engagement_study_room_members member
JOIN engagement_study_rooms room ON room.id = member.room_id AND room.archived_at IS NULL
WHERE member.workspace_id = :workspace AND member.user_id = :user AND member.left_at IS NULL
SQL);
        $rooms->execute(['workspace' => $workspaceId, 'user' => $userId]);
        if ((int) $rooms->fetchColumn() >= self::MAX_ROOMS) {
            throw new PlatformException('study_room_limit', 'You are already in the most rooms allowed.', 409);
        }
    }

    private function inviteCode(): string
    {
        for ($try = 0; $try < 5; $try++) {
            $code = '';
            for ($i = 0; $i < 12; $i++) {
                $code .= self::INVITE_ALPHABET[random_int(0, strlen(self::INVITE_ALPHABET) - 1)];
            }
            $taken = $this->database->prepare('SELECT 1 FROM engagement_study_rooms WHERE invite_code = :code');
            $taken->execute(['code' => $code]);
            if ($taken->fetchColumn() === false) {
                return $code;
            }
        }
        throw new PlatformException('study_room_code_unavailable', 'A room could not be made; try again.', 503);
    }
}
