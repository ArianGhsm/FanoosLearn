<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Commerce\CommerceService;
use Fanoos\Platform\Commerce\FakePaymentGateway;
use Fanoos\Platform\Content\ProtectedResourceAuthorizer;
use Fanoos\Platform\Core\WorkspacePlatformService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Http\ApiKernel;
use Fanoos\Platform\Http\Request;
use Fanoos\Platform\Web\AssetVersioner;
use Fanoos\Platform\Web\PageRenderer;
use Fanoos\Platform\Web\WebRouter;
use Fanoos\Platform\Identity\AuthenticatedSession;
use Fanoos\Platform\Identity\AuthService;
use Fanoos\Platform\Identity\PasswordHasher;
use Fanoos\Platform\Support\PlatformException;
use Fanoos\Platform\Support\Uuid;
use PDO;
use RuntimeException;

final class CorePlatformTest
{
    private static int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        self::$assertions = 0;
        $fixture = $this->loadBaseFixture();
        $audit = new AuditLogger($this->database);
        $authorizer = new ScopeAuthorizer($this->database);
        $access = new AccessGate($this->database, $authorizer);
        $passwords = new PasswordHasher();
        $auth = new AuthService($this->database, $passwords, $audit, 3600, 3, 60);
        $entitlements = new EntitlementService($this->database, $access, $audit);
        $commerce = new CommerceService(
            $this->database,
            $access,
            $entitlements,
            $audit,
            new FakePaymentGateway(),
            'test-only-callback-key-not-for-production-0001',
        );
        $resources = new ProtectedResourceAuthorizer($this->database, $authorizer, $entitlements);
        $core = new WorkspacePlatformService($this->database, $access, $audit, $resources);

        $this->seedAuthenticator($fixture['student'], 'student-' . substr($fixture['student'], -8), 'correct horse battery staple');
        $this->assertLoginAndWorkspaceSelection($auth, $fixture);
        $this->assertPlatformOwnerReachesEveryWorkspace($auth, $fixture);
        $this->assertRenderedPageGreetsThePersonBehindTheSession($auth, $fixture);
        $domain = $this->seedDomain($fixture);

        self::assert(count($core->academicNavigation($fixture['student'], $fixture['workspace_a'])['courses']) >= 1, 'Academic navigation did not return tenant courses.');
        self::assert(count($core->schedule($fixture['student'], $fixture['workspace_a'], '2026-01-01', '2027-01-01')) === 1, 'Schedule did not isolate workspace A.');
        self::assert(count($core->schedule($fixture['student'], $fixture['workspace_b'], '2026-01-01', '2027-01-01')) === 1, 'Schedule did not support the second workspace.');
        self::assert(count($core->myGrades($fixture['student'], $fixture['workspace_a'])) === 1, 'Published self grade was not returned.');
        self::assert((string) $core->myGrades($fixture['student'], $fixture['workspace_a'])[0]['score'] === '17.500', 'Grade value changed during projection.');

        $announcementA = $core->publishAnnouncement($fixture['representative'], $fixture['workspace_a'], 'آزمون میان‌ترم', 'زمان آزمون در برنامه ثبت شد.');
        self::assert(count($core->announcements($fixture['student'], $fixture['workspace_a'])) === 1, 'Workspace announcement did not reach an active member.');
        self::assert(count($core->announcements($fixture['student'], $fixture['workspace_b'])) === 0, 'Announcement leaked into another workspace.');
        $core->markAnnouncementRead($fixture['student'], $fixture['workspace_a'], $announcementA);
        self::assert($core->announcements($fixture['student'], $fixture['workspace_a'])[0]['status'] === 'read', 'Announcement read state was not persisted.');
        $inbox = $core->notifications($fixture['student'], $fixture['workspace_a']);
        self::assert(count($inbox['items']) === 1 && $inbox['items'][0]['id'] === $announcementA, 'Personal notification projection did not preserve the recipient-scoped read item.');
        self::assert($core->notificationPreferences($fixture['student'], $fixture['workspace_a'])['in_app_enabled'] === true, 'Notification preferences did not return canonical defaults.');
        $updatedPreferences = $core->updateNotificationPreferences($fixture['student'], $fixture['workspace_a'], ['in_app_enabled' => false, 'snoozed_until' => null]);
        self::assert($updatedPreferences['in_app_enabled'] === false && $core->notificationPreferences($fixture['student'], $fixture['workspace_b'])['in_app_enabled'] === true, 'Notification preferences escaped the selected workspace.');

        $formId = $core->createForm($fixture['representative'], $fixture['workspace_a'], 'بازخورد کلاس', [
            'fields' => [['id' => 'message', 'type' => 'textarea', 'label' => 'نظر شما', 'required' => true]],
        ], true);
        self::assert(count($core->forms($fixture['student'], $fixture['workspace_a'])) === 1, 'Open form was not visible to a workspace member.');
        $submission = $core->submitForm($fixture['student'], $fixture['workspace_a'], $formId, ['message' => 'مفید بود'], 'submit-1');
        self::assert($submission === $core->submitForm($fixture['student'], $fixture['workspace_a'], $formId, ['message' => 'مفید بود'], 'submit-1'), 'Form idempotency did not return the original submission.');
        $this->expectPlatformException('duplicate_submission', fn () => $core->submitForm($fixture['student'], $fixture['workspace_a'], $formId, ['message' => 'دوباره'], 'submit-2'));

        $core->upsertSearchDocument($fixture['representative'], $fixture['workspace_a'], 'announcement', $announcementA, 'آزمون میان‌ترم', 'زمان آزمون', '/announcements/' . $announcementA);
        $core->upsertSearchDocument($fixture['representative'], $fixture['workspace_a'], 'course', $fixture['course_b'], 'آزمون فضای دیگر', 'نباید در فضای اول دیده شود', '/courses/' . $fixture['course_b']);
        $core->upsertSearchDocument($fixture['global_admin'], $fixture['workspace_b'], 'course', $fixture['course_b'], 'آزمون فضای دوم', 'نباید در فضای اول دیده شود', '/courses/' . $fixture['course_b']);
        self::assert(count($core->search($fixture['student'], $fixture['workspace_a'], 'آزمون')) === 1, 'Workspace search missed an authorized document.');
        self::assert(count($core->search($fixture['student'], $fixture['workspace_b'], 'آزمون')) === 1, 'Workspace search did not find the second tenant document.');
        self::assert($core->search($fixture['student'], $fixture['workspace_a'], 'آزمون')[0]['source_id'] !== $fixture['course_b'], 'Workspace search leaked a second-tenant document.');

        $members = $core->members($fixture['representative'], $fixture['workspace_a']);
        self::assert(count($members) >= 2, 'Representative could not read members in the assigned workspace.');
        $studentDashboard = $core->adminDashboard($fixture['student'], $fixture['workspace_a']);
        self::assert(($studentDashboard['management_available'] ?? true) === false, 'Student dashboard incorrectly advertised management capability.');
        $dashboard = $core->adminDashboard($fixture['representative'], $fixture['workspace_a']);
        self::assert(($dashboard['management_available'] ?? false) === true, 'Representative dashboard did not advertise canonical management capability.');
        self::assert(isset($dashboard['sections']['members'], $dashboard['sections']['forms']), 'Representative dashboard omitted allowed sections.');
        self::assert(!isset($dashboard['sections']['orders'], $dashboard['sections']['audit_events']), 'Dashboard exposed privileged sections to representative.');
        self::assert($core->globalAdminDashboard($fixture['global_admin'])['scope'] === 'platform', 'Global administrator dashboard was not available at platform scope.');
        $this->expectPlatformException('forbidden', fn () => $core->globalAdminDashboard($fixture['representative']));
        $this->expectPlatformException('forbidden', fn () => $core->assignRepresentative($fixture['representative'], $fixture['workspace_a'], $fixture['student']));
        $assignmentId = $core->assignRepresentative($fixture['global_admin'], $fixture['workspace_a'], $fixture['student']);
        self::assert($assignmentId !== '', 'Scoped representative assignment was not created by an administrator.');

        $this->assertCommerceAndEntitlements($commerce, $entitlements, $resources, $fixture, $domain);
        $this->assertApiContract($auth, $core, $commerce, $entitlements, $resources, $fixture);

        $auditCount = $this->database->prepare("SELECT COUNT(*) FROM audit_events WHERE workspace_id = :workspace AND action IN ('payment.verify', 'entitlement.grant', 'form.submit', 'announcement.publish', 'notification.read', 'notification.preferences.update')");
        $auditCount->execute(['workspace' => $fixture['workspace_a']]);
        self::assert((int) $auditCount->fetchColumn() >= 4, 'Sensitive operations did not create audit events.');

        return self::$assertions;
    }

    /** @return array<string, string> */
    private function loadBaseFixture(): array
    {
        $workspaceQuery = $this->database->query("SELECT id, name FROM tenant_workspaces WHERE name IN ('Fixture Workspace A', 'Fixture Workspace B') ORDER BY created_at DESC");
        $workspaces = [];
        foreach ($workspaceQuery->fetchAll() as $row) {
            $workspaces[$row['name'] === 'Fixture Workspace A' ? 'workspace_a' : 'workspace_b'] ??= (string) $row['id'];
        }
        self::assert(isset($workspaces['workspace_a'], $workspaces['workspace_b']), 'Prompt 3 tenant fixture is unavailable.');
        $users = [];
        foreach (['Multi member' => 'student', 'Representative' => 'representative', 'Global admin' => 'global_admin'] as $name => $key) {
            $query = $this->database->prepare('SELECT id FROM iam_users WHERE display_name = :name ORDER BY created_at DESC LIMIT 1');
            $query->execute(['name' => $name]);
            $id = $query->fetchColumn();
            self::assert($id !== false, "Fixture user is missing: {$name}");
            $users[$key] = (string) $id;
        }
        foreach (['a', 'b'] as $side) {
            $scope = $this->database->prepare("SELECT id FROM rbac_scopes WHERE scope_type = 'workspace' AND workspace_id = :workspace AND entity_id = :entity_workspace");
            $scope->execute(['workspace' => $workspaces['workspace_' . $side], 'entity_workspace' => $workspaces['workspace_' . $side]]);
            $workspaces['scope_' . $side] = (string) $scope->fetchColumn();
            $course = $this->database->prepare('SELECT id FROM academic_courses WHERE workspace_id = :workspace ORDER BY created_at DESC LIMIT 1');
            $course->execute(['workspace' => $workspaces['workspace_' . $side]]);
            $workspaces['course_' . $side] = (string) $course->fetchColumn();
            $term = $this->database->prepare('SELECT id FROM academic_terms WHERE workspace_id = :workspace ORDER BY created_at DESC LIMIT 1');
            $term->execute(['workspace' => $workspaces['workspace_' . $side]]);
            $workspaces['term_' . $side] = (string) $term->fetchColumn();
        }
        return array_merge($workspaces, $users);
    }

    /** @param array<string, string> $fixture */
    /**
     * An installation owner is authorized in every workspace -- the scope
     * chain makes the platform scope an ancestor of all of them -- but the
     * account listing used to be membership-only, so an owner holding every
     * permission in the system still had no workspace to select and could
     * reach none of it. These assert the two halves of that fix, and that it
     * did not quietly become "everyone sees everything".
     */

    /**
     * The website resolves its viewer from the session cookie through
     * WebRouter, not from a hand-built ViewerContext, so this is the only
     * place a mistake in that resolution shows up. It shipped one: the
     * display name was read from the top level of account(), where it does
     * not live, and every signed-in page greeted a nameless visitor.
     */
    private function assertRenderedPageGreetsThePersonBehindTheSession(AuthService $auth, array $fixture): void
    {
        $token = 'router-probe-token';
        $this->insert(
            'INSERT INTO iam_sessions (id, user_id, token_digest, csrf_token_digest, client_json, created_at, last_seen_at, expires_at) VALUES (:id, :user, :token, :csrf, JSON_OBJECT(), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 1 HOUR))',
            ['id' => 'router-probe', 'user' => $fixture['student'], 'token' => hash('sha256', $token, true), 'csrf' => hash('sha256', 'router-probe-csrf', true)],
        );

        $name = (string) $this->database->query('SELECT display_name FROM iam_users WHERE id = ' . $this->database->quote($fixture['student']))->fetchColumn();
        self::assert($name !== '', 'This fixture must give the student a display name for the test to mean anything.');

        $renderer = new PageRenderer(new AssetVersioner(dirname(__DIR__, 2) . '/apps/platform/public'));
        $router = new WebRouter($auth, $renderer);
        $response = $router->handle('/app', ['fanoos_session' => $token]);

        self::assert($response['status'] === 200, 'A live session must render the app, not redirect to sign-in.');
        self::assert(str_contains($response['body'], $name), 'The rendered page did not greet the person the session belongs to.');

        // A recovery link must work in the browser the owner actually has
        // open, which usually already holds a session -- for the wrong
        // account, or a stale one. Redirecting that browser to /app made the
        // link a no-op in production.
        $recovery = $router->handle('/recovery', ['fanoos_session' => $token], ['token' => 'whatever']);
        self::assert($recovery['status'] === 200, 'A recovery link must render even when the browser already has a session.');
        self::assert(str_contains($recovery['body'], 'recovery-checking'), 'The recovery link did not reach the page that redeems it.');

        $this->database->prepare('UPDATE iam_sessions SET revoked_at = UTC_TIMESTAMP(6) WHERE id = :id')->execute(['id' => 'router-probe']);
    }

    private function assertPlatformOwnerReachesEveryWorkspace(AuthService $auth, array $fixture): void
    {
        $ownerMemberships = $this->database->prepare("SELECT COUNT(*) FROM tenant_workspace_memberships WHERE user_id = :user AND status = 'active'");
        $ownerMemberships->execute(['user' => $fixture['global_admin']]);
        self::assert((int) $ownerMemberships->fetchColumn() === 0, 'This fixture must keep the owner a non-member for the test to mean anything.');

        $ownerSession = new AuthenticatedSession('s-owner', $fixture['global_admin'], 't-owner', 'c-owner', null, '2099-01-01 00:00:00');
        $ownerAccount = $auth->account($ownerSession);
        $ownerWorkspaceIds = array_map(static fn (array $row): string => (string) $row['id'], $ownerAccount['workspaces']);
        foreach (['workspace_a', 'workspace_b'] as $key) {
            self::assert(in_array($fixture[$key], $ownerWorkspaceIds, true), "Platform owner could not see {$key} despite being authorized in it.");
        }

        // Selection is enforced server-side, not merely offered in the list.
        $this->insert(
            'INSERT INTO iam_sessions (id, user_id, token_digest, created_at, last_seen_at, expires_at) VALUES (:id, :user, :token, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 1 HOUR))',
            ['id' => 's-owner', 'user' => $fixture['global_admin'], 'token' => hash('sha256', 'owner-token', true)],
        );
        $auth->selectWorkspace($ownerSession, $fixture['workspace_b']);
        $selected = $this->database->prepare('SELECT selected_workspace_id FROM iam_sessions WHERE id = :id');
        $selected->execute(['id' => 's-owner']);
        self::assert((string) $selected->fetchColumn() === $fixture['workspace_b'], 'Platform owner could not select a workspace they do not belong to.');

        // And a plain member still cannot reach a workspace they are not in.
        $outsiderSession = new AuthenticatedSession('s-rep', $fixture['representative'], 't-rep', 'c-rep', null, '2099-01-01 00:00:00');
        $outsiderIds = array_map(static fn (array $row): string => (string) $row['id'], $auth->account($outsiderSession)['workspaces']);
        self::assert(!in_array($fixture['workspace_b'], $outsiderIds, true), 'A non-owner must not see a workspace they do not belong to.');
        $this->expectPlatformException('workspace_forbidden', fn () => $auth->selectWorkspace($outsiderSession, $fixture['workspace_b']));
    }

    private function assertLoginAndWorkspaceSelection(AuthService $auth, array $fixture): void
    {
        $identifier = 'student-' . substr($fixture['student'], -8);
        $this->expectPlatformException('invalid_credentials', fn () => $auth->login($identifier, 'wrong', '192.0.2.10'));
        $failureCount = $this->database->query('SELECT SUM(failure_count) FROM iam_login_attempts')->fetchColumn();
        self::assert((int) $failureCount >= 1, 'Failed login was not retained for throttling.');
        $session = $auth->login($identifier, 'correct horse battery staple', '192.0.2.10', ['client' => 'test']);
        self::assert($session->token !== '' && $session->csrfToken !== '', 'Login did not issue session and CSRF tokens.');
        $account = $auth->account($session);
        self::assert(count($account['workspaces']) === 2, 'Canonical account did not expose both active memberships.');
        $auth->selectWorkspace($session, $fixture['workspace_a']);
        self::assert($auth->authenticate($session->token)->selectedWorkspaceId === $fixture['workspace_a'], 'Workspace selection was not bound to the session.');
        $this->expectPlatformException('csrf_failed', fn () => $auth->requireCsrf($session, 'wrong'));

        $digest = $this->database->prepare("SELECT secret_digest FROM iam_authenticators WHERE user_id = :user AND authenticator_type = 'password' AND revoked_at IS NULL ORDER BY created_at DESC LIMIT 1");
        $digest->execute(['user' => $fixture['student']]);
        self::assert(!str_starts_with((string) $digest->fetchColumn(), 'pbkdf2_sha256$'), 'Legacy PBKDF2 credential was not rehashed after login.');
    }

    /** @param array<string, string> $fixture @return array<string, string> */
    private function seedDomain(array $fixture): array
    {
        $ids = [];
        foreach (['offering_a', 'offering_b', 'session_a', 'session_b', 'enrollment_a', 'enrollment_b', 'gradebook_a', 'grade_item_a', 'grade_result_a', 'event_a', 'event_b', 'product', 'price', 'resource', 'resource_version', 'resource_scope'] as $name) {
            $ids[$name] = Uuid::v7();
        }
        foreach (['a', 'b'] as $side) {
            $workspace = $fixture['workspace_' . $side];
            $this->insert("INSERT INTO academic_course_offerings (id, workspace_id, course_id, term_id, section_key, status, created_at, updated_at) VALUES (:id, :workspace, :course, :term, 'main', 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))", ['id' => $ids['offering_' . $side], 'workspace' => $workspace, 'course' => $fixture['course_' . $side], 'term' => $fixture['term_' . $side]]);
            $this->insert("INSERT INTO academic_course_sessions (id, workspace_id, offering_id, sequence_no, title, starts_at, ends_at, status, created_at, updated_at) VALUES (:id, :workspace, :offering, 1, :title, '2026-05-01 08:00:00', '2026-05-01 10:00:00', 'scheduled', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))", ['id' => $ids['session_' . $side], 'workspace' => $workspace, 'offering' => $ids['offering_' . $side], 'title' => 'Session ' . strtoupper($side)]);
            $membership = $this->database->prepare("SELECT id FROM tenant_workspace_memberships WHERE workspace_id = :workspace AND user_id = :user AND status = 'active'");
            $membership->execute(['workspace' => $workspace, 'user' => $fixture['student']]);
            $membershipId = (string) $membership->fetchColumn();
            $this->insert("INSERT INTO academic_enrollments (id, workspace_id, offering_id, membership_id, status, enrolled_at, created_at, updated_at) VALUES (:id, :workspace, :offering, :membership, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))", ['id' => $ids['enrollment_' . $side], 'workspace' => $workspace, 'offering' => $ids['offering_' . $side], 'membership' => $membershipId]);
            $this->insert("INSERT INTO schedule_events (id, workspace_id, offering_id, event_type, title, starts_at, ends_at, status, created_at, updated_at) VALUES (:id, :workspace, :offering, 'exam', :title, '2026-05-15 09:00:00', '2026-05-15 10:00:00', 'scheduled', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))", ['id' => $ids['event_' . $side], 'workspace' => $workspace, 'offering' => $ids['offering_' . $side], 'title' => 'Exam ' . strtoupper($side)]);
        }
        $this->insert("INSERT INTO grade_gradebooks (id, workspace_id, offering_id, title, status, created_at, updated_at) VALUES (:id, :workspace, :offering, 'Final grades', 'published', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))", ['id' => $ids['gradebook_a'], 'workspace' => $fixture['workspace_a'], 'offering' => $ids['offering_a']]);
        $this->insert("INSERT INTO grade_items (id, workspace_id, gradebook_id, item_key, title, max_score, created_at) VALUES (:id, :workspace, :gradebook, 'final', 'Final', 20, UTC_TIMESTAMP(6))", ['id' => $ids['grade_item_a'], 'workspace' => $fixture['workspace_a'], 'gradebook' => $ids['gradebook_a']]);
        $this->insert("INSERT INTO grade_results (id, workspace_id, grade_item_id, enrollment_id, score, status, updated_by_user_id, created_at, updated_at) VALUES (:id, :workspace, :item, :enrollment, 17.500, 'published', :actor, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))", ['id' => $ids['grade_result_a'], 'workspace' => $fixture['workspace_a'], 'item' => $ids['grade_item_a'], 'enrollment' => $ids['enrollment_a'], 'actor' => $fixture['global_admin']]);

        $this->insert("INSERT INTO commerce_products (id, workspace_id, target_scope_id, product_key, name, status, created_at, updated_at) VALUES (:id, :workspace, :scope, 'core-access', 'Core Access', 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))", ['id' => $ids['product'], 'workspace' => $fixture['workspace_a'], 'scope' => $fixture['scope_a']]);
        $this->insert("INSERT INTO commerce_price_versions (id, workspace_id, product_id, amount_minor, currency, valid_from, created_at) VALUES (:id, :workspace, :product, 250000, 'IRR', UTC_TIMESTAMP(6) - INTERVAL 1 DAY, UTC_TIMESTAMP(6))", ['id' => $ids['price'], 'workspace' => $fixture['workspace_a'], 'product' => $ids['product']]);

        $resourceType = (string) $this->database->query("SELECT id FROM content_resource_types WHERE type_key = 'lecture_note'")->fetchColumn();
        $this->insert("INSERT INTO content_resources (id, workspace_id, resource_type_id, owner_user_id, title, visibility, lifecycle_status, current_version_no, created_at, updated_at) VALUES (:id, :workspace, :type, :owner, 'Protected lesson', 'workspace', 'published', 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))", ['id' => $ids['resource'], 'workspace' => $fixture['workspace_a'], 'type' => $resourceType, 'owner' => $fixture['representative']]);
        $this->insert("INSERT INTO content_resource_versions (id, workspace_id, resource_id, version_no, created_by_user_id, source_kind, content_json, checksum_sha256, status, created_at, reviewed_at) VALUES (:id, :workspace, :resource, 1, :creator, 'authored', JSON_OBJECT('body', 'lesson'), UNHEX(SHA2('lesson', 256)), 'approved', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))", ['id' => $ids['resource_version'], 'workspace' => $fixture['workspace_a'], 'resource' => $ids['resource'], 'creator' => $fixture['representative']]);
        $this->insert("INSERT INTO rbac_scopes (id, scope_type, entity_id, workspace_id, parent_scope_id, created_at) VALUES (:id, 'resource', :resource, :workspace, :parent, UTC_TIMESTAMP(6))", ['id' => $ids['resource_scope'], 'resource' => $ids['resource'], 'workspace' => $fixture['workspace_a'], 'parent' => $fixture['scope_a']]);
        $this->insert('INSERT INTO content_access_policies (workspace_id, resource_id, target_scope_id, requires_entitlement, download_ttl_seconds, updated_at) VALUES (:workspace, :resource, :scope, TRUE, 300, UTC_TIMESTAMP(6))', ['workspace' => $fixture['workspace_a'], 'resource' => $ids['resource'], 'scope' => $fixture['scope_a']]);
        return $ids;
    }

    /** @param array<string, string> $fixture @param array<string, string> $domain */
    private function assertCommerceAndEntitlements(CommerceService $commerce, EntitlementService $entitlements, ProtectedResourceAuthorizer $resources, array $fixture, array $domain): void
    {
        $order = $commerce->createOrder($fixture['student'], $fixture['workspace_a'], $domain['product'], 'order-success');
        self::assert($order['status'] === 'payment_pending' && $order['authority'] !== null, 'Server-side quote did not start a fake payment.');
        $success = $commerce->handleCallback($order['callback_token'], $order['authority'], ['status' => 'success']);
        self::assert($success['status'] === 'paid' && $success['duplicate'] === false, 'Successful payment was not verified.');
        self::assert($commerce->handleCallback($order['callback_token'], $order['authority'], ['status' => 'success'])['duplicate'] === true, 'Duplicate callback was not idempotent.');
        self::assert($entitlements->has($fixture['student'], $fixture['workspace_a'], $fixture['scope_a']), 'Verified order did not grant an entitlement.');
        self::assert($resources->decide($fixture['student'], $fixture['workspace_a'], $domain['resource'])['allowed'], 'RBAC plus entitlement did not authorize protected resource.');
        self::assert($resources->decide($fixture['representative'], $fixture['workspace_a'], $domain['resource'])['reason'] === 'entitlement_required', 'Protected resource ignored entitlement for another authorized member.');

        $grantCount = (int) $this->database->query('SELECT COUNT(*) FROM entitlement_grants')->fetchColumn();
        $failedOrder = $commerce->createOrder($fixture['student'], $fixture['workspace_a'], $domain['product'], 'order-failure');
        self::assert($commerce->handleCallback($failedOrder['callback_token'], $failedOrder['authority'], ['status' => 'failed'])['status'] === 'failed', 'Failed payment was accepted.');
        self::assert((int) $this->database->query('SELECT COUNT(*) FROM entitlement_grants')->fetchColumn() === $grantCount, 'Failed payment granted an entitlement.');
        self::assert($commerce->reconcile($fixture['global_admin'], $fixture['workspace_a'], $failedOrder['attempt_id'])['status'] === 'paid', 'Reconciliation did not use the common verification finalizer.');
        self::assert(count($commerce->history($fixture['student'], $fixture['workspace_a'])) === 2, 'Buyer payment history is incomplete.');

        $manualGrant = $entitlements->grantByAdministrator($fixture['global_admin'], $fixture['workspace_b'], $fixture['student'], $fixture['scope_b'], 'support_case');
        self::assert($entitlements->has($fixture['student'], $fixture['workspace_b'], $fixture['scope_b']), 'Administrator entitlement grant failed.');
        $entitlements->revoke($fixture['global_admin'], $fixture['workspace_b'], $manualGrant, 'case_closed');
        self::assert(!$entitlements->has($fixture['student'], $fixture['workspace_b'], $fixture['scope_b']), 'Revoked entitlement remained active.');
    }

    /** @param array<string, string> $fixture */
    private function assertApiContract(AuthService $auth, WorkspacePlatformService $core, CommerceService $commerce, EntitlementService $entitlements, ProtectedResourceAuthorizer $resources, array $fixture): void
    {
        $kernel = new ApiKernel($auth, $core, $commerce, $entitlements, $resources, true);
        $identifier = 'student-' . substr($fixture['student'], -8);
        $login = $kernel->handle(new Request('POST', '/api/v1/auth/login', ['user-agent' => 'contract-test'], [], ['identifier' => $identifier, 'password' => 'correct horse battery staple'], '192.0.2.20'));
        self::assert($login->status === 200 && $login->payload['ok'] === true, 'Login API envelope failed.');
        $token = (string) $login->payload['data']['token'];
        $account = $kernel->handle(new Request('GET', '/api/v1/account', ['authorization' => 'Bearer ' . $token]));
        self::assert($account->status === 200 && count($account->payload['data']['workspaces']) === 2, 'Canonical account API contract failed.');
        $unknown = $kernel->handle(new Request('GET', '/api/v1/workspaces/' . $fixture['workspace_a'] . '/unknown', ['authorization' => 'Bearer ' . $token]));
        self::assert($unknown->status === 404 && $unknown->payload['error']['code'] === 'route_not_found', 'API error envelope failed.');
    }

    private function seedAuthenticator(string $userId, string $identifier, string $password): void
    {
        $salt = 'legacy-test-salt';
        $derived = hash_pbkdf2('sha256', $password, $salt, 210000, 32, true);
        $digest = 'pbkdf2_sha256$210000$' . $salt . '$' . rawurlencode(base64_encode($derived));
        $this->insert("INSERT INTO iam_user_identifiers (id, user_id, identifier_type, normalized_value, is_verified, verified_at, created_at) VALUES (:id, :user, 'external', :identifier, TRUE, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))", ['id' => Uuid::v7(), 'user' => $userId, 'identifier' => $identifier]);
        $this->insert("INSERT INTO iam_authenticators (id, user_id, authenticator_type, secret_digest, metadata_json, created_at) VALUES (:id, :user, 'password', :digest, JSON_OBJECT('source', 'compatibility_fixture'), UTC_TIMESTAMP(6))", ['id' => Uuid::v7(), 'user' => $userId, 'digest' => $digest]);
    }

    /** @param array<string, scalar|null> $parameters */
    private function insert(string $sql, array $parameters): void
    {
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
    }

    private function expectPlatformException(string $code, callable $operation): void
    {
        try {
            $operation();
        } catch (PlatformException $error) {
            self::assert($error->errorCode === $code, "Expected {$code}, received {$error->errorCode}.");
            return;
        }
        throw new RuntimeException("Expected PlatformException {$code}.");
    }

    private static function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
        ++self::$assertions;
    }
}
