<?php

declare(strict_types=1);

namespace Fanoos\Platform\Http;

use Fanoos\Platform\Commerce\CommerceService;
use Fanoos\Platform\Content\ProtectedResourceAuthorizer;
use Fanoos\Platform\Core\WorkspacePlatformService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Identity\AuthService;
use Fanoos\Platform\Identity\AuthenticatedSession;
use Fanoos\Platform\Support\PlatformException;
use Throwable;

final class ApiKernel
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly WorkspacePlatformService $platform,
        private readonly CommerceService $commerce,
        private readonly EntitlementService $entitlements,
        private readonly ProtectedResourceAuthorizer $resources,
        private readonly bool $paymentsEnabled,
    ) {
    }

    public function handle(Request $request): Response
    {
        $requestId = bin2hex(random_bytes(8));
        try {
            $result = $this->dispatch($request);
            return new Response($result['status'], [
                'ok' => true,
                'data' => $result['data'],
                'meta' => ['api_version' => 'v1', 'request_id' => $requestId],
            ], $result['headers'] ?? []);
        } catch (PlatformException $error) {
            return new Response($error->httpStatus, [
                'ok' => false,
                'error' => ['code' => $error->errorCode, 'message' => $error->getMessage()],
                'meta' => ['api_version' => 'v1', 'request_id' => $requestId],
            ]);
        } catch (Throwable) {
            return new Response(500, [
                'ok' => false,
                'error' => ['code' => 'internal_error', 'message' => 'An unexpected error occurred.'],
                'meta' => ['api_version' => 'v1', 'request_id' => $requestId],
            ]);
        }
    }

    /** @return array{status:int,data:mixed,headers?:list<string>} */
    private function dispatch(Request $request): array
    {
        if ($request->method === 'POST' && $request->path === '/api/v1/auth/login') {
            $session = $this->auth->login(
                (string) ($request->body['identifier'] ?? ''),
                (string) ($request->body['password'] ?? ''),
                $request->source,
                ['user_agent' => substr($request->header('user-agent'), 0, 300)],
            );
            return [
                'status' => 200,
                'data' => [
                    'token' => $session->token, 'csrf_token' => $session->csrfToken,
                    'expires_at' => $session->expiresAt, 'account' => $this->auth->account($session),
                ],
                'headers' => ['Set-Cookie: fanoos_session=' . rawurlencode($session->token) . '; Path=/; HttpOnly; Secure; SameSite=Lax'],
            ];
        }

        if ($request->method === 'POST' && $request->path === '/api/v1/payments/callback') {
            $this->requirePayments();
            return ['status' => 200, 'data' => $this->commerce->handleCallback(
                (string) ($request->body['callback_token'] ?? ''),
                (string) ($request->body['authority'] ?? ''),
                is_array($request->body['provider_payload'] ?? null) ? $request->body['provider_payload'] : [],
            )];
        }

        $session = $this->auth->authenticate($request->bearerToken());
        if (!in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $this->auth->requireCsrf($session, $request->header('x-csrf-token'));
        }

        if ($request->method === 'POST' && $request->path === '/api/v1/auth/logout') {
            $this->auth->logout($session);
            return ['status' => 200, 'data' => ['logged_out' => true], 'headers' => ['Set-Cookie: fanoos_session=; Path=/; Max-Age=0; HttpOnly; Secure; SameSite=Lax']];
        }
        if ($request->method === 'GET' && in_array($request->path, ['/api/v1/account', '/api/v1/workspaces'], true)) {
            return ['status' => 200, 'data' => $this->auth->account($session)];
        }
        if ($request->method === 'POST' && $request->path === '/api/v1/workspaces/select') {
            $this->auth->selectWorkspace($session, (string) ($request->body['workspace_id'] ?? ''));
            return ['status' => 200, 'data' => ['selected_workspace_id' => (string) ($request->body['workspace_id'] ?? '')]];
        }
        if ($request->method === 'GET' && $request->path === '/api/v1/admin/dashboard') {
            return ['status' => 200, 'data' => $this->platform->globalAdminDashboard($session->userId)];
        }

        [$workspaceId, $suffix] = $this->workspaceRoute($request->path, $session);
        if ($request->method === 'GET' && $suffix === '/academics') {
            return ['status' => 200, 'data' => $this->platform->academicNavigation($session->userId, $workspaceId)];
        }
        if ($request->method === 'GET' && $suffix === '/schedule') {
            return ['status' => 200, 'data' => $this->platform->schedule(
                $session->userId, $workspaceId,
                $request->query['from'] ?? gmdate('Y-m-01'),
                $request->query['to'] ?? gmdate('Y-m-d', strtotime('+90 days')),
            )];
        }
        if ($request->method === 'GET' && $suffix === '/grades/me') {
            return ['status' => 200, 'data' => $this->platform->myGrades($session->userId, $workspaceId)];
        }
        if ($request->method === 'GET' && $suffix === '/announcements') {
            return ['status' => 200, 'data' => $this->platform->announcements($session->userId, $workspaceId)];
        }
        if ($request->method === 'POST' && $suffix === '/announcements') {
            return ['status' => 201, 'data' => ['id' => $this->platform->publishAnnouncement(
                $session->userId, $workspaceId,
                (string) ($request->body['title'] ?? ''), (string) ($request->body['body'] ?? ''),
            )]];
        }
        if ($request->method === 'POST' && preg_match('#^/announcements/([0-9a-f-]+)/read$#', $suffix, $match)) {
            $this->platform->markAnnouncementRead($session->userId, $workspaceId, $match[1]);
            return ['status' => 200, 'data' => ['read' => true]];
        }
        if ($request->method === 'GET' && $suffix === '/forms') {
            return ['status' => 200, 'data' => $this->platform->forms($session->userId, $workspaceId)];
        }
        if ($request->method === 'POST' && $suffix === '/forms') {
            $schema = is_array($request->body['schema'] ?? null) ? $request->body['schema'] : [];
            return ['status' => 201, 'data' => ['id' => $this->platform->createForm(
                $session->userId, $workspaceId, (string) ($request->body['title'] ?? ''),
                $schema, (bool) ($request->body['open'] ?? false),
            )]];
        }
        if ($request->method === 'POST' && preg_match('#^/forms/([0-9a-f-]+)/submissions$#', $suffix, $match)) {
            $answers = is_array($request->body['answers'] ?? null) ? $request->body['answers'] : [];
            return ['status' => 201, 'data' => ['id' => $this->platform->submitForm(
                $session->userId, $workspaceId, $match[1], $answers,
                (string) ($request->body['idempotency_key'] ?? ''),
            )]];
        }
        if ($request->method === 'GET' && $suffix === '/search') {
            return ['status' => 200, 'data' => $this->platform->search($session->userId, $workspaceId, $request->query['q'] ?? '')];
        }
        if ($request->method === 'GET' && $suffix === '/admin/members') {
            return ['status' => 200, 'data' => $this->platform->members($session->userId, $workspaceId)];
        }
        if ($request->method === 'POST' && $suffix === '/admin/representatives') {
            return ['status' => 201, 'data' => ['assignment_id' => $this->platform->assignRepresentative(
                $session->userId, $workspaceId, (string) ($request->body['user_id'] ?? ''),
            )]];
        }
        if ($request->method === 'GET' && $suffix === '/admin/dashboard') {
            return ['status' => 200, 'data' => $this->platform->adminDashboard($session->userId, $workspaceId)];
        }
        if ($request->method === 'POST' && $suffix === '/orders') {
            $this->requirePayments();
            return ['status' => 201, 'data' => $this->commerce->createOrder(
                $session->userId, $workspaceId,
                (string) ($request->body['product_id'] ?? ''),
                (string) ($request->body['idempotency_key'] ?? ''),
            )];
        }
        if ($request->method === 'GET' && $suffix === '/orders') {
            return ['status' => 200, 'data' => $this->commerce->history($session->userId, $workspaceId)];
        }
        if ($request->method === 'POST' && preg_match('#^/payments/([0-9a-f-]+)/reconcile$#', $suffix, $match)) {
            $this->requirePayments();
            return ['status' => 200, 'data' => $this->commerce->reconcile($session->userId, $workspaceId, $match[1])];
        }
        if ($request->method === 'POST' && $suffix === '/entitlements') {
            return ['status' => 201, 'data' => ['id' => $this->entitlements->grantByAdministrator(
                $session->userId, $workspaceId,
                (string) ($request->body['user_id'] ?? ''),
                (string) ($request->body['target_scope_id'] ?? ''),
                (string) ($request->body['reason'] ?? 'manual_grant'),
            )]];
        }
        if ($request->method === 'POST' && preg_match('#^/entitlements/([0-9a-f-]+)/revoke$#', $suffix, $match)) {
            $this->entitlements->revoke($session->userId, $workspaceId, $match[1], (string) ($request->body['reason'] ?? ''));
            return ['status' => 200, 'data' => ['revoked' => true]];
        }
        if ($request->method === 'GET' && $suffix === '/entitlements/check') {
            return ['status' => 200, 'data' => ['allowed' => $this->entitlements->has($session->userId, $workspaceId, $request->query['scope_id'] ?? '')]];
        }
        if ($request->method === 'GET' && preg_match('#^/resources/([0-9a-f-]+)/authorize$#', $suffix, $match)) {
            return ['status' => 200, 'data' => $this->resources->decide($session->userId, $workspaceId, $match[1])];
        }

        throw new PlatformException('route_not_found', 'API route was not found.', 404);
    }

    /** @return array{0:string,1:string} */
    private function workspaceRoute(string $path, AuthenticatedSession $session): array
    {
        if (!preg_match('#^/api/v1/workspaces/([0-9a-f-]+)(/.*)$#', $path, $match)) {
            throw new PlatformException('route_not_found', 'API route was not found.', 404);
        }
        if ($session->selectedWorkspaceId !== null && !hash_equals($session->selectedWorkspaceId, $match[1])) {
            throw new PlatformException('workspace_context_mismatch', 'Route does not match the selected workspace.', 409);
        }
        return [$match[1], $match[2]];
    }

    private function requirePayments(): void
    {
        if (!$this->paymentsEnabled) {
            throw new PlatformException('payment_provider_not_configured', 'A payment provider is not configured in this environment.', 503);
        }
    }
}
