<?php

declare(strict_types=1);

namespace Fanoos\Tests\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Engagement\StudyRoomService;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

/**
 * اتاق مطالعه گروهی: rooms are joined only by invite, show their members'
 * study today, cap at ten members and ten rooms, and close when empty.
 */
final class StudyRoomTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $workspace = $this->workspace();
        $students = [];
        for ($i = 0; $i < 12; $i++) {
            $students[] = $this->member($workspace, 'student');
        }
        [$a, $b, $c] = $students;
        $rooms = new StudyRoomService($this->database, $this->access(), new AuditLogger($this->database));

        $this->expectCode('study_room_name_invalid', fn () => $rooms->create($a, $workspace, '   '));
        $made = $rooms->create($a, $workspace, 'کشیک‌های بی‌خواب');
        $this->assert(preg_match('/^[a-z0-9]{12}$/', $made['invite_code']) === 1, 'The invite code is not twelve letters and digits.');

        // b joins by the code (in any case); c, without it, sees nothing.
        $joined = $rooms->join($b, $workspace, strtoupper($made['invite_code']));
        $this->assert($joined['id'] === $made['id'], 'Joining by the code did not reach the room.');
        $this->assert($rooms->join($b, $workspace, $made['invite_code'])['id'] === $made['id'], 'Joining twice was not harmless.');
        $this->assert($rooms->rooms($c, $workspace) === [], 'Someone outside the room saw it.');
        $this->expectCode('study_room_not_found', fn () => $rooms->join($c, $workspace, 'aaaaaaaaaaaa'));

        // Members see each other's study today; a timed block counts.
        $this->database->prepare("INSERT INTO study_sessions (id, workspace_id, user_id, minutes, label, ended_at) VALUES (:id, :workspace, :user, 50, NULL, UTC_TIMESTAMP(6))")
            ->execute(['id' => Uuid::v7(), 'workspace' => $workspace, 'user' => $b]);
        $view = $rooms->rooms($a, $workspace)[0];
        $this->assert(count($view['members']) === 2 && $view['members'][0]['user_id'] === $b && $view['members'][0]['minutes'] === 50, 'Members are not ranked by today\'s study: ' . json_encode($view['members']));
        $this->assert($view['mine'] === true && $view['total_minutes'] === 50 && $view['members'][1]['me'] === true, 'The room does not say whose it is or its total.');

        // Only the creator removes; nobody removes themselves.
        $this->expectCode('study_room_not_creator', fn () => $rooms->remove($b, $workspace, $made['id'], $a));
        $this->expectCode('study_room_remove_self', fn () => $rooms->remove($a, $workspace, $made['id'], $a));
        $rooms->remove($a, $workspace, $made['id'], $b);
        $this->assert($rooms->rooms($b, $workspace) === [], 'A removed member still sees the room.');

        // Ten members at most.
        foreach (array_slice($students, 1, 9) as $student) {
            $rooms->join($student, $workspace, $made['invite_code']);
        }
        $this->expectCode('study_room_full', fn () => $rooms->join($students[10], $workspace, $made['invite_code']));

        // Ten rooms at most for one person.
        for ($i = 0; $i < 9; $i++) {
            $rooms->create($students[11], $workspace, "اتاق {$i}");
        }
        $tenth = $rooms->create($students[11], $workspace, 'دهمی');
        $this->expectCode('study_room_limit', fn () => $rooms->create($students[11], $workspace, 'یازدهمی'));

        // The last one out closes the room; its link stops working.
        $rooms->leave($students[11], $workspace, $tenth['id']);
        $this->expectCode('study_room_not_found', fn () => $rooms->join($c, $workspace, $tenth['invite_code']));
        $this->expectCode('study_room_not_found', fn () => $rooms->leave($students[11], $workspace, $tenth['id']));

        // A room belongs to its workspace.
        $elsewhere = $this->workspace();
        $outsider = $this->member($elsewhere, 'student');
        $this->expectCode('study_room_not_found', fn () => $rooms->join($outsider, $elsewhere, $made['invite_code']));

        return $this->assertions;
    }

    private function workspace(): string
    {
        $suffix = substr(str_replace('-', '', Uuid::v7()), -10);
        $owner = $this->user('Room Owner');
        $this->database->prepare("INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at) SELECT :id, :user, id, '00000000-0000-7000-8000-000000000001', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6) FROM rbac_role_templates WHERE role_key = 'platform-super-admin'")
            ->execute(['id' => Uuid::v7(), 'user' => $owner]);

        return (new ClassProvisioningService($this->database, $this->access(), new AuditLogger($this->database)))->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Room Province ' . $suffix],
            'city' => ['name' => 'Room City ' . $suffix],
            'institution' => ['name' => 'Room University ' . $suffix],
            'faculty' => ['name' => 'Room Faculty ' . $suffix],
            'program' => ['name' => 'Room Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 5904, 'label' => 'Room Cohort ' . $suffix],
            'workspace' => ['name' => 'Room Library ' . $suffix],
        ])['workspace_id'];
    }

    private function expectCode(string $code, callable $call): void
    {
        try {
            $call();
        } catch (PlatformException $error) {
            $this->assert($error->errorCode === $code, "Expected {$code}, got {$error->errorCode}.");
            return;
        }
        throw new RuntimeException("Expected {$code}, but the call succeeded.");
    }

    private function member(string $workspace, string $roleKey): string
    {
        $user = $this->user('Room ' . $roleKey);
        $this->database->prepare("INSERT INTO tenant_workspace_memberships (id, workspace_id, user_id, status, joined_at, created_at, updated_at) VALUES (:id, :workspace, :user, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => Uuid::v7(), 'workspace' => $workspace, 'user' => $user]);
        $this->database->prepare(<<<'SQL'
INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at)
SELECT :id, :user, role.id, scope.id, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM rbac_role_templates role
JOIN rbac_scopes scope ON scope.scope_type = 'workspace' AND scope.entity_id = :workspace AND scope.workspace_id = :workspace_check
WHERE role.role_key = :role
SQL)->execute(['id' => Uuid::v7(), 'user' => $user, 'workspace' => $workspace, 'workspace_check' => $workspace, 'role' => $roleKey]);

        return $user;
    }

    private function user(string $name): string
    {
        $id = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $id, 'name' => $name]);

        return $id;
    }

    private function access(): AccessGate
    {
        return new AccessGate($this->database, new ScopeAuthorizer($this->database));
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
