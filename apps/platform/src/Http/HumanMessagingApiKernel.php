<?php

declare(strict_types=1);

namespace Fanoos\Platform\Http;

use Fanoos\Platform\Identity\AuthService;
use Fanoos\Platform\Messaging\MessagingLinkService;
use Fanoos\Platform\Support\PlatformException;
use Throwable;

final class HumanMessagingApiKernel
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly MessagingLinkService $links,
    ) {
    }

    public function handle(Request $request): Response
    {
        $requestId = bin2hex(random_bytes(8));
        try {
            $session = $this->auth->authenticate($request->bearerToken());
            if (!in_array($request->method, ['GET', 'HEAD', 'OPTIONS'], true)) {
                $this->auth->requireCsrf($session, $request->header('x-csrf-token'));
            }

            if ($request->method === 'POST' && $request->path === '/api/v1/messaging/link-challenges') {
                return $this->success(201, $this->links->createChallenge($session->userId, (string) ($request->body['platform'] ?? '')), $requestId);
            }
            if ($request->method === 'POST' && preg_match('#^/api/v1/messaging/links/(telegram|bale)/revoke$#', $request->path, $match)) {
                $this->links->revoke($session->userId, $match[1], (string) ($request->body['reason'] ?? 'user_unlink'));
                return $this->success(200, ['revoked' => true, 'platform' => $match[1]], $requestId);
            }
            throw new PlatformException('route_not_found', 'API route was not found.', 404);
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

    private function success(int $status, mixed $data, string $requestId): Response
    {
        return new Response($status, ['ok' => true, 'data' => $data, 'meta' => ['api_version' => 'v1', 'request_id' => $requestId]]);
    }
}
