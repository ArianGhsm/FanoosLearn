<?php

declare(strict_types=1);

namespace Fanoos\Tests\Integration;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Commerce\CommerceService;
use Fanoos\Platform\Commerce\FakePaymentGateway;
use Fanoos\Platform\Content\ProtectedResourceAuthorizer;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Core\WorkspacePlatformService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Http\ApiKernel;
use Fanoos\Platform\Http\Request;
use Fanoos\Platform\Identity\AuthService;
use Fanoos\Platform\Identity\PasswordHasher;
use Fanoos\Platform\Identity\SessionCsrf;
use Fanoos\Platform\Support\Uuid;
use Fanoos\Platform\Web\AssetVersioner;
use Fanoos\Platform\Web\PageRenderer;
use Fanoos\Platform\Web\WebRouter;
use PDO;
use RuntimeException;

/**
 * Every write on the website was refused: AuthService::authenticate()
 * returned an empty CSRF token because iam_sessions stored only a digest,
 * and a digest cannot be reversed into the value a rendered page needs to
 * embed. The fix derives the token from the session token itself
 * (SessionCsrf) instead of storing one. These prove the fix through the
 * real paths that shipped broken -- WebRouter rendering a page, and
 * ApiKernel refusing or accepting a write -- not by calling AuthService
 * methods directly, which would not have caught the original bug either.
 */
final class SessionCsrfTest
{
    private int $assertions = 0;

    public function __construct(private readonly PDO $database)
    {
    }

    public function run(): int
    {
        $this->assertAuthenticateReturnsTheSameCsrfLoginDid();
        $this->assertRenderedPagesTokenIsAcceptedByRequireCsrf();
        $this->assertWriteAcceptedWithTokenRefusedWithoutOrWrong();
        $this->assertLogoutRevokesTheSession();
        $this->assertOwnerSelectingAWorkspaceRevealsExamsNav();

        return $this->assertions;
    }

    private function assertAuthenticateReturnsTheSameCsrfLoginDid(): void
    {
        $suffix = $this->suffix();
        $auth = $this->auth();
        $userId = $this->plainUser('Csrf Login User ' . $suffix);
        $identifier = 'csrf-login-' . $suffix . '@example.test';
        $this->seedPasswordAuthenticator($userId, $identifier, 'a correct horse battery staple');

        $login = $auth->login($identifier, 'a correct horse battery staple', 'test-source');
        $this->assert($login->csrfToken !== '', 'login() must return a non-empty CSRF token.');

        $authenticated = $auth->authenticate($login->token);
        $this->assert(
            $authenticated->csrfToken === $login->csrfToken,
            'authenticate() must return the same CSRF token login() returned for the same session.',
        );
    }

    /**
     * The one assertion that would have caught the original bug: a page
     * rendered by WebRouter for a live session must embed a non-empty CSRF
     * token, and that exact value -- read back out of the HTML the way a
     * browser would, not the value a fixture happens to know -- must be
     * accepted by requireCsrf().
     */
    private function assertRenderedPagesTokenIsAcceptedByRequireCsrf(): void
    {
        $suffix = $this->suffix();
        $auth = $this->auth();
        $userId = $this->plainUser('Csrf Render User ' . $suffix);
        $identifier = 'csrf-render-' . $suffix . '@example.test';
        $this->seedPasswordAuthenticator($userId, $identifier, 'a correct horse battery staple');
        $session = $auth->login($identifier, 'a correct horse battery staple', 'test-source');

        $renderer = new PageRenderer(new AssetVersioner(dirname(__DIR__, 2) . '/apps/platform/public'));
        $router = new WebRouter($auth, $renderer);
        $response = $router->handle('/app', ['fanoos_session' => $session->token]);
        $this->assert($response['status'] === 200, 'A live session must render the app.');

        $this->assert(
            preg_match('/<meta name="fanoos-csrf" content="([^"]*)">/', $response['body'], $match) === 1,
            'The rendered page must embed a fanoos-csrf meta tag.',
        );
        $embedded = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
        $this->assert($embedded !== '', 'The embedded CSRF token must not be empty -- this is the bug: authenticate() returned "".');
        $this->assert($embedded === SessionCsrf::derive($session->token), 'The embedded token must be the session-token derivation, not something else.');

        // authenticate() (not login()) is what a rendered page's token came
        // from, so re-resolve through it exactly as WebRouter::viewer() did.
        $reAuthenticated = $auth->authenticate($session->token);
        $auth->requireCsrf($reAuthenticated, $embedded);
        $this->assert(true, 'requireCsrf() must accept the exact token a rendered page embeds.');
    }

    private function assertWriteAcceptedWithTokenRefusedWithoutOrWrong(): void
    {
        $suffix = $this->suffix();
        $kernel = $this->apiKernel();
        $userId = $this->plainUser('Csrf Write User ' . $suffix);
        $identifier = 'csrf-write-' . $suffix . '@example.test';
        $this->seedPasswordAuthenticator($userId, $identifier, 'a correct horse battery staple');

        $login = $kernel->handle(new Request('POST', '/api/v1/auth/login', [], [], [
            'identifier' => $identifier, 'password' => 'a correct horse battery staple',
        ]));
        $this->assert($login->status === 200, 'Login must succeed.');
        $token = (string) $login->payload['data']['token'];
        $csrf = (string) $login->payload['data']['csrf_token'];
        $this->assert($csrf !== '', 'The login response must carry a non-empty CSRF token.');

        $noHeader = $kernel->handle(new Request('POST', '/api/v1/auth/password', ['authorization' => 'Bearer ' . $token], [], [
            'new_password' => 'irrelevant-does-not-matter-0000',
        ]));
        $this->assert($noHeader->status === 403 && $noHeader->payload['error']['code'] === 'csrf_failed', 'A write with no CSRF header must be refused.');

        $wrongHeader = $kernel->handle(new Request('POST', '/api/v1/auth/password', [
            'authorization' => 'Bearer ' . $token, 'x-csrf-token' => 'not-the-real-token',
        ], [], ['new_password' => 'irrelevant-does-not-matter-0000']));
        $this->assert($wrongHeader->status === 403 && $wrongHeader->payload['error']['code'] === 'csrf_failed', 'A write with the wrong CSRF value must be refused.');

        $accepted = $kernel->handle(new Request('POST', '/api/v1/auth/password', [
            'authorization' => 'Bearer ' . $token, 'x-csrf-token' => $csrf,
        ], [], ['new_password' => 'a genuinely different password 42']));
        $this->assert($accepted->status === 200, 'A write with the real embedded CSRF token must succeed.');
    }

    private function assertLogoutRevokesTheSession(): void
    {
        $suffix = $this->suffix();
        $kernel = $this->apiKernel();
        $userId = $this->plainUser('Csrf Logout User ' . $suffix);
        $identifier = 'csrf-logout-' . $suffix . '@example.test';
        $this->seedPasswordAuthenticator($userId, $identifier, 'a correct horse battery staple');

        $login = $kernel->handle(new Request('POST', '/api/v1/auth/login', [], [], [
            'identifier' => $identifier, 'password' => 'a correct horse battery staple',
        ]));
        $token = (string) $login->payload['data']['token'];
        $csrf = (string) $login->payload['data']['csrf_token'];

        $logout = $kernel->handle(new Request('POST', '/api/v1/auth/logout', [
            'authorization' => 'Bearer ' . $token, 'x-csrf-token' => $csrf,
        ]));
        $this->assert($logout->status === 200, 'Logout with the real CSRF token must succeed.');

        $afterLogout = $kernel->handle(new Request('GET', '/api/v1/account', ['authorization' => 'Bearer ' . $token]));
        $this->assert($afterLogout->status === 401, 'A revoked session must no longer authenticate.');
    }

    private function assertOwnerSelectingAWorkspaceRevealsExamsNav(): void
    {
        $suffix = $this->suffix();
        $kernel = $this->apiKernel();
        $owner = $this->platformSuperAdmin('Csrf Owner ' . $suffix);
        $identifier = 'csrf-owner-' . $suffix . '@example.test';
        $this->seedPasswordAuthenticator($owner, $identifier, 'a correct horse battery staple');
        $class = (new ClassProvisioningService($this->database, $this->accessGate(), new AuditLogger($this->database)))->createClass($owner, [
            'country' => ['code' => 'ZZ', 'name' => 'Fixture Country'],
            'province' => ['name' => 'Fixture Province ' . $suffix],
            'city' => ['name' => 'Fixture City ' . $suffix],
            'institution' => ['name' => 'Fixture University ' . $suffix],
            'faculty' => ['name' => 'Fixture Faculty ' . $suffix],
            'program' => ['name' => 'Fixture Program ' . $suffix, 'degree_level' => 'professional-doctorate'],
            'cohort' => ['entry_year' => 5900, 'label' => 'Fixture Cohort ' . $suffix],
            'workspace' => ['name' => 'Fixture Csrf Class ' . $suffix],
        ]);

        $login = $kernel->handle(new Request('POST', '/api/v1/auth/login', [], [], [
            'identifier' => $identifier, 'password' => 'a correct horse battery staple',
        ]));
        $this->assert($login->status === 200, 'Owner login must succeed.');
        $token = (string) $login->payload['data']['token'];
        $csrf = (string) $login->payload['data']['csrf_token'];

        $select = $kernel->handle(new Request('POST', '/api/v1/workspaces/select', [
            'authorization' => 'Bearer ' . $token, 'x-csrf-token' => $csrf,
        ], [], ['workspace_id' => $class['workspace_id']]));
        $this->assert($select->status === 200, 'A platform owner selecting a workspace they do not belong to must still succeed.');

        $renderer = new PageRenderer(new AssetVersioner(dirname(__DIR__, 2) . '/apps/platform/public'));
        $router = new WebRouter($this->auth(), $renderer);
        $home = $router->handle('/app', ['fanoos_session' => $token]);
        $this->assert(
            str_contains($home['body'], '/app/exams'),
            'The exams nav entry must appear once a workspace is actually selectable -- it was missing only because selection itself was refused.',
        );
    }

    // -- fixtures and small helpers ------------------------------------------

    private function suffix(): string
    {
        return substr(str_replace('-', '', Uuid::v7()), -10);
    }

    private function auth(): AuthService
    {
        return new AuthService($this->database, new PasswordHasher(), new AuditLogger($this->database), 3600, 5, 900);
    }

    private function accessGate(): AccessGate
    {
        return new AccessGate($this->database, new ScopeAuthorizer($this->database));
    }

    private function apiKernel(): ApiKernel
    {
        $audit = new AuditLogger($this->database);
        $authorizer = new ScopeAuthorizer($this->database);
        $access = new AccessGate($this->database, $authorizer);
        $entitlements = new EntitlementService($this->database, $access, $audit);
        $commerce = new CommerceService(
            $this->database, $access, $entitlements, $audit,
            new FakePaymentGateway(), 'test-only-callback-key-not-for-production-0002',
        );
        $resources = new ProtectedResourceAuthorizer($this->database, $authorizer, $entitlements);

        return new ApiKernel($this->auth(), new WorkspacePlatformService($this->database, $access, $audit, $resources), $commerce, $entitlements, $resources, true);
    }

    private function plainUser(string $displayName): string
    {
        $id = Uuid::v7();
        $this->database->prepare("INSERT INTO iam_users (id, display_name, status, locale, created_at, updated_at) VALUES (:id, :name, 'active', 'fa-IR', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))")
            ->execute(['id' => $id, 'name' => $displayName]);

        return $id;
    }

    private function platformSuperAdmin(string $displayName): string
    {
        $id = $this->plainUser($displayName);
        $role = $this->database->prepare("SELECT id FROM rbac_role_templates WHERE role_key = 'platform-super-admin' LIMIT 1");
        $role->execute();
        $roleId = $role->fetchColumn();
        if ($roleId === false) {
            throw new RuntimeException('Role template is missing: platform-super-admin');
        }
        $this->database->prepare('INSERT INTO rbac_role_assignments (id, user_id, role_template_id, scope_id, valid_from, created_at) VALUES (:id, :user, :role, :scope, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))')
            ->execute(['id' => Uuid::v7(), 'user' => $id, 'role' => $roleId, 'scope' => '00000000-0000-7000-8000-000000000001']);

        return $id;
    }

    private function seedPasswordAuthenticator(string $userId, string $identifier, string $password): void
    {
        $this->database->prepare(<<<'SQL'
INSERT INTO iam_user_identifiers (id, user_id, identifier_type, normalized_value, is_verified, verified_at, created_at)
VALUES (:id, :user, 'email', :value, TRUE, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
SQL)->execute(['id' => Uuid::v7(), 'user' => $userId, 'value' => strtolower($identifier)]);
        $this->database->prepare(<<<'SQL'
INSERT INTO iam_authenticators (id, user_id, authenticator_type, secret_digest, metadata_json, created_at)
VALUES (:id, :user, 'password', :digest, JSON_OBJECT(), UTC_TIMESTAMP(6))
SQL)->execute(['id' => Uuid::v7(), 'user' => $userId, 'digest' => (new PasswordHasher())->hash($password)]);
    }

    private function assert(bool $condition, string $message): void
    {
        ++$this->assertions;
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}
