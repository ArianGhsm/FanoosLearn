<?php

declare(strict_types=1);

namespace Fanoos\Platform\Http;

use Fanoos\Platform\Commerce\CommerceService;
use Fanoos\Platform\Content\ProtectedResourceAuthorizer;
use Fanoos\Platform\Content\ContentService;
use Fanoos\Platform\Content\ExamService;
use Fanoos\Platform\Content\SecureDeliveryService;
use Fanoos\Platform\Content\SecureObjectDownloadService;
use Fanoos\Platform\Core\ScheduleProjectionService;
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
        private readonly ?ContentService $content = null,
        private readonly ?ExamService $exams = null,
        private readonly ?SecureDeliveryService $delivery = null,
        private readonly ?SecureObjectDownloadService $downloads = null,
        private readonly ?ScheduleProjectionService $schedule = null,
    ) {
    }

    public function handle(Request $request): Response|BinaryResponse
    {
        $requestId = bin2hex(random_bytes(8));
        try {
            $result = $this->dispatch($request);
            if ($result instanceof BinaryResponse) {
                return $result;
            }
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

    /** @return array{status:int,data:mixed,headers?:list<string>}|BinaryResponse */
    private function dispatch(Request $request): array|BinaryResponse
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
            $from = $request->query['from'] ?? gmdate('Y-m-01');
            $to = $request->query['to'] ?? gmdate('Y-m-d', strtotime('+90 days'));
            $data = $this->schedule !== null
                ? $this->schedule->list($session->userId, $workspaceId, $from, $to)
                : $this->platform->schedule($session->userId, $workspaceId, $from, $to);
            return ['status' => 200, 'data' => $data];
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
        if ($request->method === 'GET' && $suffix === '/resources') {
            return ['status' => 200, 'data' => $this->requireContent()->library($session->userId, $workspaceId, [
                'q' => $request->query['q'] ?? '', 'type' => $request->query['type'] ?? '',
                'course_id' => $request->query['course_id'] ?? '', 'status' => $request->query['status'] ?? '',
                'sort' => $request->query['sort'] ?? 'newest',
            ])];
        }
        if ($request->method === 'POST' && $suffix === '/resources') {
            $payload = is_array($request->body['content'] ?? null) ? $request->body['content'] : [];
            $metadata = is_array($request->body['metadata'] ?? null) ? $request->body['metadata'] : [];
            return ['status' => 201, 'data' => $this->requireContent()->createResource(
                $session->userId, $workspaceId, (string) ($request->body['type'] ?? ''),
                (string) ($request->body['title'] ?? ''), $payload, $metadata,
            )];
        }
        if ($request->method === 'GET' && preg_match('#^/resources/([0-9a-f-]+)$#', $suffix, $match)) {
            return ['status' => 200, 'data' => $this->requireContent()->view($session->userId, $workspaceId, $match[1])];
        }
        if ($request->method === 'POST' && preg_match('#^/resources/([0-9a-f-]+)/versions$#', $suffix, $match)) {
            $payload = is_array($request->body['content'] ?? null) ? $request->body['content'] : [];
            return ['status' => 201, 'data' => $this->requireContent()->addStructuredVersion($session->userId, $workspaceId, $match[1], $payload)];
        }
        if ($request->method === 'POST' && preg_match('#^/resources/([0-9a-f-]+)/versions/([0-9a-f-]+)/review-request$#', $suffix, $match)) {
            $this->requireContent()->submitForReview($session->userId, $workspaceId, $match[1], $match[2]);
            return ['status' => 200, 'data' => ['status' => 'review']];
        }
        if ($request->method === 'POST' && preg_match('#^/resources/([0-9a-f-]+)/versions/([0-9a-f-]+)/review$#', $suffix, $match)) {
            $this->requireContent()->reviewVersion(
                $session->userId, $workspaceId, $match[1], $match[2],
                (string) ($request->body['decision'] ?? ''),
                isset($request->body['note']) ? (string) $request->body['note'] : null,
            );
            return ['status' => 200, 'data' => ['status' => (string) ($request->body['decision'] ?? '')]];
        }
        if ($request->method === 'POST' && preg_match('#^/resources/([0-9a-f-]+)/versions/([0-9a-f-]+)/publish$#', $suffix, $match)) {
            $this->requireContent()->publishVersion($session->userId, $workspaceId, $match[1], $match[2]);
            return ['status' => 200, 'data' => ['status' => 'published']];
        }
        if ($request->method === 'POST' && preg_match('#^/resources/([0-9a-f-]+)/deliveries$#', $suffix, $match)) {
            return ['status' => 201, 'data' => $this->requireDelivery()->issue(
                $session->userId, $workspaceId, $match[1], (string) ($request->body['channel'] ?? 'web'),
            )];
        }
        if ($request->method === 'POST' && $suffix === '/deliveries/consume') {
            return ['status' => 200, 'data' => $this->requireDelivery()->consume(
                (string) ($request->body['delivery_token'] ?? ''), $session->userId, $workspaceId,
            )];
        }
        if ($request->method === 'POST' && $suffix === '/downloads/consume') {
            $download = $this->requireDownload()->redeem(
                $session->userId, $workspaceId, (string) ($request->body['download_token'] ?? ''),
            );
            return new BinaryResponse(200, $download['stream'], $download['mime'], $download['size']);
        }
        if ($request->method === 'GET' && $suffix === '/assessments') {
            return ['status' => 200, 'data' => $this->requireExams()->catalog(
                $session->userId, $workspaceId,
                ($request->query['course_id'] ?? '') === '' ? null : $request->query['course_id'],
                ($request->query['kind'] ?? '') === '' ? null : $request->query['kind'],
            )];
        }
        if ($request->method === 'POST' && $suffix === '/assessments') {
            $definition = is_array($request->body['definition'] ?? null) ? $request->body['definition'] : [];
            $metadata = is_array($request->body['metadata'] ?? null) ? $request->body['metadata'] : [];
            return ['status' => 201, 'data' => $this->requireExams()->createAssessment(
                $session->userId, $workspaceId, (string) ($request->body['title'] ?? ''), $definition, $metadata,
            )];
        }
        if ($request->method === 'POST' && preg_match('#^/assessments/([0-9a-f-]+)/versions/([0-9a-f-]+)/review-request$#', $suffix, $match)) {
            $this->requireExams()->submitForReview($session->userId, $workspaceId, $match[1], $match[2]);
            return ['status' => 200, 'data' => ['status' => 'review']];
        }
        if ($request->method === 'POST' && preg_match('#^/assessments/([0-9a-f-]+)/versions/([0-9a-f-]+)/review$#', $suffix, $match)) {
            $this->requireExams()->reviewVersion(
                $session->userId, $workspaceId, $match[1], $match[2],
                (string) ($request->body['decision'] ?? ''),
                isset($request->body['note']) ? (string) $request->body['note'] : null,
            );
            return ['status' => 200, 'data' => ['status' => (string) ($request->body['decision'] ?? '')]];
        }
        if ($request->method === 'POST' && preg_match('#^/assessments/([0-9a-f-]+)/versions/([0-9a-f-]+)/publish$#', $suffix, $match)) {
            $this->requireExams()->publishVersion($session->userId, $workspaceId, $match[1], $match[2]);
            return ['status' => 200, 'data' => ['status' => 'published']];
        }
        if ($request->method === 'POST' && preg_match('#^/assessments/([0-9a-f-]+)/attempts$#', $suffix, $match)) {
            return ['status' => 201, 'data' => $this->requireExams()->startAttempt($session->userId, $workspaceId, $match[1])];
        }
        if ($request->method === 'GET' && preg_match('#^/assessments/([0-9a-f-]+)/analytics$#', $suffix, $match)) {
            return ['status' => 200, 'data' => $this->requireExams()->analytics($session->userId, $workspaceId, $match[1])];
        }
        if ($request->method === 'PATCH' && preg_match('#^/attempts/([0-9a-f-]+)$#', $suffix, $match)) {
            $answers = is_array($request->body['answers'] ?? null) ? $request->body['answers'] : [];
            return ['status' => 200, 'data' => $this->requireExams()->saveProgress(
                $session->userId, $workspaceId, $match[1], (int) ($request->body['revision'] ?? 0), $answers,
            )];
        }
        if ($request->method === 'POST' && preg_match('#^/attempts/([0-9a-f-]+)/submit$#', $suffix, $match)) {
            $answers = is_array($request->body['answers'] ?? null) ? $request->body['answers'] : [];
            return ['status' => 200, 'data' => $this->requireExams()->submitAttempt(
                $session->userId, $workspaceId, $match[1], (int) ($request->body['revision'] ?? 0), $answers,
            )];
        }
        if ($request->method === 'GET' && preg_match('#^/attempts/([0-9a-f-]+)/review$#', $suffix, $match)) {
            return ['status' => 200, 'data' => $this->requireExams()->attemptReview($session->userId, $workspaceId, $match[1])];
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

    private function requireContent(): ContentService
    {
        if ($this->content === null) {
            throw new PlatformException('content_service_unavailable', 'Content service is not configured.', 503);
        }

        return $this->content;
    }

    private function requireExams(): ExamService
    {
        if ($this->exams === null) {
            throw new PlatformException('exam_service_unavailable', 'Assessment service is not configured.', 503);
        }

        return $this->exams;
    }

    private function requireDelivery(): SecureDeliveryService
    {
        if ($this->delivery === null) {
            throw new PlatformException('delivery_service_unavailable', 'Secure delivery is not configured.', 503);
        }

        return $this->delivery;
    }

    private function requireDownload(): SecureObjectDownloadService
    {
        if ($this->downloads === null) {
            throw new PlatformException('download_service_unavailable', 'Secure object download is not configured.', 503);
        }

        return $this->downloads;
    }
}
