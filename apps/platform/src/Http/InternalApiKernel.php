<?php

declare(strict_types=1);

namespace Fanoos\Platform\Http;

use Fanoos\Platform\Commerce\BotCommerceService;
use Fanoos\Platform\Content\DeliveryReceiptService;
use Fanoos\Platform\Content\ProtectedMediaEnqueueService;
use Fanoos\Platform\Content\ProtectedMediaJobService;
use Fanoos\Platform\Content\ProtectedMediaTransferService;
use Fanoos\Platform\Content\SecureDeliveryService;
use Fanoos\Platform\Core\BotReadProjectionService;
use Fanoos\Platform\Core\ClassProvisioningService;
use Fanoos\Platform\Integration\ServiceAuthenticator;
use Fanoos\Platform\Integration\ServicePrincipal;
use Fanoos\Platform\Messaging\MessagingLinkService;
use Fanoos\Platform\Messaging\MessagingUnlinkService;
use Fanoos\Platform\Notifications\NotificationDeliveryService;
use Fanoos\Platform\Onboarding\DirectoryReadService;
use Fanoos\Platform\Onboarding\OnboardingPhoneVerificationService;
use Fanoos\Platform\Operations\DeploymentControlService;
use Fanoos\Platform\Operations\OwnerControlPlaneService;
use Fanoos\Platform\Support\PlatformException;
use Throwable;

final class InternalApiKernel
{
    public function __construct(
        private readonly ServiceAuthenticator $serviceAuth,
        private readonly MessagingLinkService $links,
        private readonly MessagingUnlinkService $unlink,
        private readonly BotReadProjectionService $reads,
        private readonly BotCommerceService $commerce,
        private readonly NotificationDeliveryService $notifications,
        private readonly SecureDeliveryService $delivery,
        private readonly DeliveryReceiptService $deliveryReceipts,
        private readonly ProtectedMediaEnqueueService $mediaEnqueue,
        private readonly ProtectedMediaJobService $mediaJobs,
        private readonly ProtectedMediaTransferService $mediaTransfers,
        private readonly DeploymentControlService $deployments,
        private readonly OwnerControlPlaneService $ownerControl,
        private readonly ClassProvisioningService $classes,
        private readonly DirectoryReadService $directory,
        private readonly OnboardingPhoneVerificationService $onboardingPhones,
        private readonly bool $paymentsEnabled,
    ) {
    }

    public function handle(Request $request): Response|BinaryResponse
    {
        $requestId = bin2hex(random_bytes(8));
        try {
            if ($request->method !== 'POST') {
                throw new PlatformException('route_not_found', 'Internal API route was not found.', 404);
            }
            $data = $this->dispatch($request);
            if ($data instanceof BinaryResponse) {
                return $data;
            }
            return new Response(200, ['ok' => true, 'data' => $data, 'meta' => ['api_version' => 'internal-v1', 'request_id' => $requestId]]);
        } catch (PlatformException $error) {
            return new Response($error->httpStatus, [
                'ok' => false,
                'error' => ['code' => $error->errorCode, 'message' => $error->getMessage()],
                'meta' => ['api_version' => 'internal-v1', 'request_id' => $requestId],
            ]);
        } catch (Throwable) {
            return new Response(500, [
                'ok' => false,
                'error' => ['code' => 'internal_error', 'message' => 'An unexpected error occurred.'],
                'meta' => ['api_version' => 'internal-v1', 'request_id' => $requestId],
            ]);
        }
    }

    private function dispatch(Request $request): mixed
    {
        $path = $request->path;
        if ($path === '/api/internal/v1/messaging/link-challenges/consume') {
            $this->assertKeys($request->body, ['platform', 'challenge_token', 'subject']);
            $principal = $this->serviceAuth->authenticate($request, 'messaging.link.consume');
            $platform = $this->adapterPlatform($principal, (string) ($request->body['platform'] ?? ''));
            return $this->links->consumeChallenge($platform, (string) ($request->body['challenge_token'] ?? ''), (string) ($request->body['subject'] ?? ''));
        }
        if ($path === '/api/internal/v1/messaging/links/revoke') {
            $this->assertKeys($request->body, ['platform', 'subject']);
            $principal = $this->serviceAuth->authenticate($request, 'messaging.link.revoke');
            $platform = $this->adapterPlatform($principal, (string) ($request->body['platform'] ?? ''));
            return $this->unlink->revokeSubject($platform, (string) ($request->body['subject'] ?? ''));
        }
        if ($path === '/api/internal/v1/messaging/workspaces/list') {
            $this->assertKeys($request->body, ['platform', 'subject']);
            $principal = $this->serviceAuth->authenticate($request, 'messaging.workspace.read');
            $link = $this->linked($principal, $request->body);
            return ['workspaces' => $this->links->workspaces($link['link_id']), 'selected_workspace_id' => $this->links->selectedWorkspace($link['link_id'])];
        }
        if ($path === '/api/internal/v1/messaging/workspaces/select') {
            $this->assertKeys($request->body, ['platform', 'subject', 'workspace_id']);
            $principal = $this->serviceAuth->authenticate($request, 'messaging.workspace.select');
            $link = $this->linked($principal, $request->body);
            $workspace = (string) ($request->body['workspace_id'] ?? '');
            $this->links->selectWorkspace($link['link_id'], $workspace);
            return ['selected_workspace_id' => $workspace];
        }
        if ($path === '/api/internal/v1/academics/schedule') {
            $this->assertKeys($request->body, ['platform', 'subject', 'workspace_id', 'from_date', 'to_date', 'limit', 'cursor']);
            $principal = $this->serviceAuth->authenticate($request, 'academic.schedule.read');
            $context = $this->linkedWorkspace($principal, $request->body);
            return $this->reads->schedule(
                $context['user_id'], $context['workspace_id'],
                (string) ($request->body['from_date'] ?? ''), (string) ($request->body['to_date'] ?? ''),
                (int) ($request->body['limit'] ?? 100), $this->nullableString($request->body['cursor'] ?? null),
            );
        }
        if ($path === '/api/internal/v1/academics/grades') {
            $this->assertKeys($request->body, ['platform', 'subject', 'workspace_id', 'limit', 'cursor']);
            $principal = $this->serviceAuth->authenticate($request, 'grade.self.read');
            $context = $this->linkedWorkspace($principal, $request->body);
            return $this->reads->grades($context['user_id'], $context['workspace_id'], (int) ($request->body['limit'] ?? 50), $this->nullableString($request->body['cursor'] ?? null));
        }
        if ($path === '/api/internal/v1/announcements/list') {
            $this->assertKeys($request->body, ['platform', 'subject', 'workspace_id', 'limit', 'cursor', 'course_id']);
            $principal = $this->serviceAuth->authenticate($request, 'announcement.read');
            $context = $this->linkedWorkspace($principal, $request->body);
            return $this->reads->announcements(
                $context['user_id'], $context['workspace_id'],
                (int) ($request->body['limit'] ?? 20), $this->nullableString($request->body['cursor'] ?? null),
                $this->nullableString($request->body['course_id'] ?? null),
            );
        }
        if ($path === '/api/internal/v1/content/resources/list') {
            $this->assertKeys($request->body, ['platform', 'subject', 'workspace_id', 'limit', 'cursor']);
            $principal = $this->serviceAuth->authenticate($request, 'content.catalog.read');
            $context = $this->linkedWorkspace($principal, $request->body);
            return $this->reads->resourceCatalog($context['user_id'], $context['workspace_id'], (int) ($request->body['limit'] ?? 20), $this->nullableString($request->body['cursor'] ?? null));
        }
        if ($path === '/api/internal/v1/commerce/orders') {
            $this->assertKeys($request->body, ['platform', 'subject', 'workspace_id', 'product_id', 'idempotency_key']);
            $this->requirePayments();
            $principal = $this->serviceAuth->authenticate($request, 'commerce.order.create');
            $context = $this->linkedWorkspace($principal, $request->body);
            return $this->commerce->createOrder($context['user_id'], $context['workspace_id'], (string) ($request->body['product_id'] ?? ''), (string) ($request->body['idempotency_key'] ?? ''));
        }
        if ($path === '/api/internal/v1/commerce/orders/status') {
            $this->assertKeys($request->body, ['platform', 'subject', 'workspace_id', 'order_id']);
            $principal = $this->serviceAuth->authenticate($request, 'commerce.order.read');
            $context = $this->linkedWorkspace($principal, $request->body);
            return $this->commerce->status($context['user_id'], $context['workspace_id'], (string) ($request->body['order_id'] ?? ''));
        }
        if ($path === '/api/internal/v1/notifications/project') {
            $this->assertKeys($request->body, []);
            $principal = $this->serviceAuth->authenticate($request, 'notification.project');
            $this->requireServiceType($principal, 'notification_worker');
            return ['event_id' => $this->notifications->projectNext()];
        }
        if ($path === '/api/internal/v1/notifications/claim') {
            $this->assertKeys($request->body, ['platform']);
            $principal = $this->serviceAuth->authenticate($request, 'notification.claim');
            $platform = $this->adapterPlatform($principal, (string) ($request->body['platform'] ?? ''));
            return ['delivery' => $this->notifications->claim($platform)];
        }
        if ($path === '/api/internal/v1/notifications/receipt') {
            $this->assertKeys($request->body, ['platform', 'delivery_id', 'lease_token', 'idempotency_key', 'outcome', 'provider_message_ref', 'error_code']);
            $principal = $this->serviceAuth->authenticate($request, 'notification.receipt');
            $this->adapterPlatform($principal, (string) ($request->body['platform'] ?? ''));
            return $this->notifications->receipt(
                (string) ($request->body['delivery_id'] ?? ''), (string) ($request->body['lease_token'] ?? ''),
                (string) ($request->body['idempotency_key'] ?? ''), (string) ($request->body['outcome'] ?? ''),
                $this->nullableString($request->body['provider_message_ref'] ?? null), $this->nullableString($request->body['error_code'] ?? null),
            );
        }
        if ($path === '/api/internal/v1/deliveries/issue') {
            $this->assertKeys($request->body, ['platform', 'subject', 'workspace_id', 'resource_id']);
            $principal = $this->serviceAuth->authenticate($request, 'delivery.issue');
            $context = $this->linkedWorkspace($principal, $request->body);
            return $this->delivery->issue($context['user_id'], $context['workspace_id'], (string) ($request->body['resource_id'] ?? ''), $context['platform']);
        }
        if ($path === '/api/internal/v1/deliveries/consume') {
            $this->assertKeys($request->body, ['platform', 'subject', 'workspace_id', 'delivery_token']);
            $principal = $this->serviceAuth->authenticate($request, 'delivery.consume');
            $context = $this->linkedWorkspace($principal, $request->body);
            return $this->delivery->consume((string) ($request->body['delivery_token'] ?? ''), $context['user_id'], $context['workspace_id']);
        }
        if ($path === '/api/internal/v1/deliveries/receipt') {
            $this->assertKeys($request->body, ['platform', 'workspace_id', 'issuance_id', 'idempotency_key', 'outcome', 'provider_message_ref', 'error_code']);
            $principal = $this->serviceAuth->authenticate($request, 'delivery.receipt');
            $platform = $this->adapterPlatform($principal, (string) ($request->body['platform'] ?? ''));
            return $this->deliveryReceipts->record(
                (string) ($request->body['workspace_id'] ?? ''), (string) ($request->body['issuance_id'] ?? ''), $platform,
                (string) ($request->body['idempotency_key'] ?? ''), (string) ($request->body['outcome'] ?? ''),
                $this->nullableString($request->body['provider_message_ref'] ?? null), $this->nullableString($request->body['error_code'] ?? null),
            );
        }
        if ($path === '/api/internal/v1/protected-media/enqueue') {
            $this->assertKeys($request->body, ['workspace_id', 'issuance_id', 'renderer_algorithm_version', 'limits']);
            $this->serviceAuth->authenticate($request, 'protected_media.enqueue');
            $limits = is_array($request->body['limits'] ?? null) ? $request->body['limits'] : [];
            return $this->mediaEnqueue->enqueue((string) ($request->body['workspace_id'] ?? ''), (string) ($request->body['issuance_id'] ?? ''), (string) ($request->body['renderer_algorithm_version'] ?? ''), $limits);
        }
        if ($path === '/api/internal/v1/protected-media/claim') {
            $this->assertKeys($request->body, []);
            $principal = $this->serviceAuth->authenticate($request, 'protected_media.claim');
            $this->requireServiceType($principal, 'protected_media_worker');
            return ['job' => $this->mediaJobs->claim()];
        }
        if ($path === '/api/internal/v1/protected-media/source/redeem') {
            $this->assertKeys($request->body, ['job_id', 'lease_token', 'object_capability']);
            $principal = $this->serviceAuth->authenticate($request, 'protected_media.source.redeem');
            $this->requireServiceType($principal, 'protected_media_worker');
            $source = $this->mediaTransfers->redeemSource((string) ($request->body['job_id'] ?? ''), (string) ($request->body['lease_token'] ?? ''), (string) ($request->body['object_capability'] ?? ''));
            return new BinaryResponse(200, $source['stream'], $source['mime'], $source['size']);
        }
        if ($path === '/api/internal/v1/protected-media/artifacts/authorize-publish') {
            $this->assertKeys($request->body, ['job_id', 'lease_token', 'completion_key', 'checksum_sha256', 'size', 'mime']);
            $principal = $this->serviceAuth->authenticate($request, 'protected_media.artifact.authorize');
            $this->requireServiceType($principal, 'protected_media_worker');
            return $this->mediaTransfers->authorizeArtifactPublish(
                (string) ($request->body['job_id'] ?? ''), (string) ($request->body['lease_token'] ?? ''),
                (string) ($request->body['completion_key'] ?? ''), (string) ($request->body['checksum_sha256'] ?? ''),
                (int) ($request->body['size'] ?? 0), (string) ($request->body['mime'] ?? ''),
            );
        }
        if ($path === '/api/internal/v1/protected-media/artifacts/publish') {
            $this->assertKeys($request->body, []);
            $principal = $this->serviceAuth->authenticate($request, 'protected_media.artifact.publish');
            $this->requireServiceType($principal, 'protected_media_worker');
            if ($this->contentType($request) !== 'application/pdf') {
                throw new PlatformException('unsupported_media_type', 'Protected media artifact upload requires application/pdf.', 415);
            }
            $capability = $request->header('x-fanoos-upload-capability');
            if ($capability === '') {
                throw new PlatformException('artifact_upload_authorization_invalid', 'Artifact upload authorization is required.', 401);
            }
            return $this->mediaTransfers->publishAuthorizedArtifact($capability, $request->rawBody);
        }
        if ($path === '/api/internal/v1/protected-media/complete') {
            $this->assertKeys($request->body, ['job_id', 'lease_token', 'completion_key', 'result']);
            $principal = $this->serviceAuth->authenticate($request, 'protected_media.complete');
            $this->requireServiceType($principal, 'protected_media_worker');
            $result = is_array($request->body['result'] ?? null) ? $request->body['result'] : [];
            $this->assertKeys($result, ['checksum_sha256', 'size', 'mime', 'artifact_ref']);
            return $this->mediaTransfers->complete((string) ($request->body['job_id'] ?? ''), (string) ($request->body['lease_token'] ?? ''), (string) ($request->body['completion_key'] ?? ''), $result);
        }
        if ($path === '/api/internal/v1/protected-media/fail') {
            $this->assertKeys($request->body, ['job_id', 'lease_token', 'failure_code']);
            $principal = $this->serviceAuth->authenticate($request, 'protected_media.fail');
            $this->requireServiceType($principal, 'protected_media_worker');
            return $this->mediaJobs->fail((string) ($request->body['job_id'] ?? ''), (string) ($request->body['lease_token'] ?? ''), (string) ($request->body['failure_code'] ?? ''));
        }
        if ($path === '/api/internal/v1/protected-media/derivatives/issue') {
            $this->assertKeys($request->body, ['platform', 'subject', 'workspace_id', 'job_id']);
            $principal = $this->serviceAuth->authenticate($request, 'protected_media.derivative.issue');
            $context = $this->linkedWorkspace($principal, $request->body);
            return $this->mediaTransfers->issueDerivative($context['user_id'], $context['workspace_id'], (string) ($request->body['job_id'] ?? ''), $context['platform']);
        }
        if ($path === '/api/internal/v1/protected-media/derivatives/redeem') {
            $this->assertKeys($request->body, ['platform', 'subject', 'workspace_id', 'artifact_capability']);
            $principal = $this->serviceAuth->authenticate($request, 'protected_media.derivative.redeem');
            $context = $this->linkedWorkspace($principal, $request->body);
            $artifact = $this->mediaTransfers->redeemDerivative($context['user_id'], $context['workspace_id'], $context['platform'], (string) ($request->body['artifact_capability'] ?? ''));
            return new BinaryResponse(200, $artifact['stream'], $artifact['mime'], $artifact['size']);
        }
        if ($path === '/api/internal/v1/deployments/overview') {
            $this->assertKeys($request->body, ['platform', 'subject', 'target_key']);
            $principal = $this->serviceAuth->authenticate($request, 'deployment.overview');
            $platform = $this->adapterPlatform($principal, (string) ($request->body['platform'] ?? ''));
            if ($platform !== 'telegram') {
                throw new PlatformException('deployment_channel_forbidden', 'Deployment controls are not enabled for this channel.', 403);
            }
            $link = $this->linked($principal, $request->body);
            return $this->ownerControl->overview($link['user_id'], (string) ($request->body['target_key'] ?? ''));
        }
        if ($path === '/api/internal/v1/deployments/request') {
            $this->assertKeys($request->body, ['platform', 'subject', 'target_key', 'idempotency_key']);
            $principal = $this->serviceAuth->authenticate($request, 'deployment.request');
            $platform = $this->adapterPlatform($principal, (string) ($request->body['platform'] ?? ''));
            if ($platform !== 'telegram') {
                throw new PlatformException('deployment_channel_forbidden', 'Deployment requests are not enabled for this channel.', 403);
            }
            $link = $this->linked($principal, $request->body);
            return $this->deployments->request($link['user_id'], 'telegram', (string) ($request->body['target_key'] ?? ''), (string) ($request->body['idempotency_key'] ?? ''));
        }
        if ($path === '/api/internal/v1/deployments/status') {
            $this->assertKeys($request->body, ['platform', 'subject', 'request_id']);
            $principal = $this->serviceAuth->authenticate($request, 'deployment.status');
            $platform = $this->adapterPlatform($principal, (string) ($request->body['platform'] ?? ''));
            if ($platform !== 'telegram') {
                throw new PlatformException('deployment_channel_forbidden', 'Deployment status is not enabled for this channel.', 403);
            }
            $link = $this->linked($principal, $request->body);
            return $this->deployments->status($link['user_id'], (string) ($request->body['request_id'] ?? ''));
        }
        if ($path === '/api/internal/v1/onboarding/directory/provinces') {
            $this->assertKeys($request->body, ['platform', 'limit', 'cursor']);
            $principal = $this->serviceAuth->authenticate($request, 'onboarding.directory.read');
            $this->adapterPlatform($principal, (string) ($request->body['platform'] ?? ''));
            return $this->directory->provinces((int) ($request->body['limit'] ?? 10), $this->nullableString($request->body['cursor'] ?? null));
        }
        if ($path === '/api/internal/v1/onboarding/directory/institutions') {
            $this->assertKeys($request->body, ['platform', 'province_id', 'limit', 'cursor']);
            $principal = $this->serviceAuth->authenticate($request, 'onboarding.directory.read');
            $this->adapterPlatform($principal, (string) ($request->body['platform'] ?? ''));
            return $this->directory->institutionsByProvince(
                (string) ($request->body['province_id'] ?? ''),
                (int) ($request->body['limit'] ?? 10), $this->nullableString($request->body['cursor'] ?? null),
            );
        }
        if ($path === '/api/internal/v1/onboarding/directory/faculties') {
            $this->assertKeys($request->body, ['platform', 'institution_id', 'limit', 'cursor']);
            $principal = $this->serviceAuth->authenticate($request, 'onboarding.directory.read');
            $this->adapterPlatform($principal, (string) ($request->body['platform'] ?? ''));
            return $this->directory->facultiesByInstitution(
                (string) ($request->body['institution_id'] ?? ''),
                (int) ($request->body['limit'] ?? 10), $this->nullableString($request->body['cursor'] ?? null),
            );
        }
        if ($path === '/api/internal/v1/onboarding/directory/programs') {
            $this->assertKeys($request->body, ['platform', 'faculty_id', 'limit', 'cursor']);
            $principal = $this->serviceAuth->authenticate($request, 'onboarding.directory.read');
            $this->adapterPlatform($principal, (string) ($request->body['platform'] ?? ''));
            return $this->directory->programsByFaculty(
                (string) ($request->body['faculty_id'] ?? ''),
                (int) ($request->body['limit'] ?? 10), $this->nullableString($request->body['cursor'] ?? null),
            );
        }
        if ($path === '/api/internal/v1/onboarding/otp/request') {
            $this->assertKeys($request->body, ['platform', 'subject', 'phone_number']);
            $principal = $this->serviceAuth->authenticate($request, 'onboarding.otp.request');
            $platform = $this->adapterPlatform($principal, (string) ($request->body['platform'] ?? ''));
            return $this->onboardingPhones->requestOtp($platform, (string) ($request->body['subject'] ?? ''), (string) ($request->body['phone_number'] ?? ''));
        }
        if ($path === '/api/internal/v1/onboarding/otp/resend') {
            $this->assertKeys($request->body, ['platform', 'subject', 'challenge_token']);
            $principal = $this->serviceAuth->authenticate($request, 'onboarding.otp.resend');
            $platform = $this->adapterPlatform($principal, (string) ($request->body['platform'] ?? ''));
            return $this->onboardingPhones->resendOtp($platform, (string) ($request->body['subject'] ?? ''), (string) ($request->body['challenge_token'] ?? ''));
        }
        if ($path === '/api/internal/v1/onboarding/otp/verify') {
            $this->assertKeys($request->body, ['platform', 'subject', 'challenge_token', 'code']);
            $principal = $this->serviceAuth->authenticate($request, 'onboarding.otp.verify');
            $platform = $this->adapterPlatform($principal, (string) ($request->body['platform'] ?? ''));
            return $this->onboardingPhones->verifyOtp(
                $platform, (string) ($request->body['subject'] ?? ''),
                (string) ($request->body['challenge_token'] ?? ''), (string) ($request->body['code'] ?? ''),
            );
        }
        if ($path === '/api/internal/v1/onboarding/otp/status') {
            $this->assertKeys($request->body, ['platform', 'subject']);
            $principal = $this->serviceAuth->authenticate($request, 'onboarding.otp.status');
            $platform = $this->adapterPlatform($principal, (string) ($request->body['platform'] ?? ''));
            return $this->onboardingPhones->status($platform, (string) ($request->body['subject'] ?? ''));
        }
        if ($path === '/api/internal/v1/classes') {
            $this->assertKeys($request->body, [
                'platform', 'subject', 'country', 'province', 'city',
                'institution', 'faculty', 'department', 'program', 'cohort', 'workspace',
            ]);
            $principal = $this->serviceAuth->authenticate($request, 'workspace.provision');
            $link = $this->linked($principal, $request->body);
            return $this->classes->createClass($link['user_id'], $request->body);
        }

        throw new PlatformException('route_not_found', 'Internal API route was not found.', 404);
    }

    /** @param array<string,mixed> $body @return array{link_id:string,user_id:string,platform:string} */
    private function linked(ServicePrincipal $principal, array $body): array
    {
        $platform = $this->adapterPlatform($principal, (string) ($body['platform'] ?? ''));
        $resolved = $this->links->resolve($platform, (string) ($body['subject'] ?? ''));
        if ($resolved === null) {
            throw new PlatformException('messaging_link_required', 'Messaging account is not linked.', 403);
        }
        return ['link_id' => $resolved['link_id'], 'user_id' => $resolved['user_id'], 'platform' => $platform];
    }

    /** @param array<string,mixed> $body @return array{link_id:string,user_id:string,platform:string,workspace_id:string} */
    private function linkedWorkspace(ServicePrincipal $principal, array $body): array
    {
        $link = $this->linked($principal, $body);
        $workspaceId = (string) ($body['workspace_id'] ?? '');
        $allowed = false;
        foreach ($this->links->workspaces($link['link_id']) as $workspace) {
            if (isset($workspace['id']) && hash_equals((string) $workspace['id'], $workspaceId)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            throw new PlatformException('workspace_forbidden', 'Linked account is not an active member of this workspace.', 403);
        }
        return $link + ['workspace_id' => $workspaceId];
    }

    private function adapterPlatform(ServicePrincipal $principal, string $requested): string
    {
        $expected = match ($principal->serviceType) {
            'telegram_adapter' => 'telegram',
            'bale_adapter' => 'bale',
            default => null,
        };
        if ($expected === null || !hash_equals($expected, strtolower(trim($requested)))) {
            throw new PlatformException('service_channel_mismatch', 'Service is not authorized for this messaging channel.', 403);
        }
        return $expected;
    }

    private function requireServiceType(ServicePrincipal $principal, string $type): void
    {
        if (!hash_equals($type, $principal->serviceType)) {
            throw new PlatformException('service_scope_denied', 'Service action is not permitted.', 403);
        }
    }

    /** @param array<string,mixed> $body @param list<string> $allowed */
    private function assertKeys(array $body, array $allowed): void
    {
        $unknown = array_diff(array_keys($body), $allowed);
        if ($unknown !== []) {
            throw new PlatformException('unexpected_request_field', 'Request contains a field that is not allowed by this contract.', 422);
        }
    }

    private function requirePayments(): void
    {
        if (!$this->paymentsEnabled) {
            throw new PlatformException('payment_provider_not_configured', 'A payment provider is not configured in this environment.', 503);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private function contentType(Request $request): string
    {
        return strtolower(trim(explode(';', $request->header('content-type'))[0]));
    }
}
