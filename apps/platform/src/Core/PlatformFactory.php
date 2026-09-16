<?php

declare(strict_types=1);

namespace Fanoos\Platform\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Commerce\CommerceService;
use Fanoos\Platform\Commerce\FakePaymentGateway;
use Fanoos\Platform\Content\ProtectedResourceAuthorizer;
use Fanoos\Platform\Content\ContentService;
use Fanoos\Platform\Content\ExamQuestionRateGuard;
use Fanoos\Platform\Content\ExamService;
use Fanoos\Platform\Content\SecureDeliveryService;
use Fanoos\Platform\Content\SecureObjectDownloadService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Http\ApiKernel;
use Fanoos\Platform\Identity\AuthService;
use Fanoos\Platform\Identity\OwnerRecoveryService;
use Fanoos\Platform\Web\AssetVersioner;
use Fanoos\Platform\Web\PageRenderer;
use Fanoos\Platform\Web\WebRouter;
use Fanoos\Platform\Identity\PasswordHasher;
use Fanoos\Platform\Storage\FilesystemObjectStore;
use Fanoos\Platform\Storage\SignedDownloadToken;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;

final class PlatformFactory
{
    /**
     * The website's router. Shares nothing with the API kernel except the
     * database and AuthService: pages resolve the viewer from the session
     * cookie and then render, while every piece of data on them still comes
     * from the same public API the browser would call.
     */
    public static function web(): WebRouter
    {
        $database = DatabaseConnection::fromEnvironment();
        $config = RuntimeConfig::load();
        $release = $config->optionalString('FANOOS_ASSET_VERSION');
        $audit = new AuditLogger($database);
        // Same dependency chain as api()'s $exams, built separately because
        // web() and api() are different front controllers/processes with no
        // shared instance to reuse -- see ExamService::catalogEntry(), which
        // is all the exam attempt page actually calls.
        $authorizer = new ScopeAuthorizer($database);
        $access = new AccessGate($database, $authorizer);
        $entitlements = new EntitlementService($database, $access, $audit);
        $exams = new ExamService($database, $access, $authorizer, $entitlements, $audit, new ExamQuestionRateGuard($database));

        return new WebRouter(
            new AuthService($database, new PasswordHasher(), $audit),
            new PageRenderer(new AssetVersioner(__DIR__ . '/../../public', $release)),
            $exams,
        );
    }

    public static function api(): ApiKernel
    {
        $database = DatabaseConnection::fromEnvironment();
        $config = RuntimeConfig::load();
        $audit = new AuditLogger($database);
        $authorizer = new ScopeAuthorizer($database);
        $access = new AccessGate($database, $authorizer);
        $entitlements = new EntitlementService($database, $access, $audit);
        $environment = $config->optionalString('FANOOS_ENV', 'production');
        $gatewayKey = $config->optionalString('FANOOS_PAYMENT_GATEWAY', 'disabled');
        $paymentsEnabled = $gatewayKey === 'fake' && in_array($environment, ['development', 'test'], true);
        $callbackKey = $config->optionalString('FANOOS_PAYMENT_CALLBACK_KEY', 'disabled-payment-callback-key-000000') ?? '';
        $resources = new ProtectedResourceAuthorizer($database, $authorizer, $entitlements);
        $content = new ContentService($database, $access, $resources, $audit);
        $exams = new ExamService($database, $access, $authorizer, $entitlements, $audit, new ExamQuestionRateGuard($database));
        $deliveryKey = $config->optionalString('FANOOS_DELIVERY_SIGNING_KEY');
        $downloadKey = $config->optionalString('FANOOS_DOWNLOAD_SIGNING_KEY');
        $storageRoot = $config->optionalString('FANOOS_OBJECT_STORAGE_ROOT');
        $downloadTokens = is_string($downloadKey) && strlen($downloadKey) >= 32
            ? new SignedDownloadToken($downloadKey)
            : null;
        $delivery = is_string($deliveryKey) && strlen($deliveryKey) >= 32 && $downloadTokens !== null
            ? new SecureDeliveryService($database, $resources, $downloadTokens, $audit, $deliveryKey)
            : null;
        $downloads = $downloadTokens !== null && is_string($storageRoot) && trim($storageRoot) !== ''
            ? new SecureObjectDownloadService($database, $resources, $downloadTokens, new FilesystemObjectStore($storageRoot))
            : null;
        $schedule = new ScheduleProjectionService(
            $database,
            $access,
            new ScheduleWindowResolver($database),
        );
        $auth = new AuthService($database, new PasswordHasher(), $audit);

        return new ApiKernel(
            $auth,
            new WorkspacePlatformService($database, $access, $audit, $resources),
            new CommerceService($database, $access, $entitlements, $audit, new FakePaymentGateway(), $callbackKey),
            $entitlements,
            $resources,
            $paymentsEnabled,
            $content,
            $exams,
            $delivery,
            $downloads,
            $schedule,
            new OwnerRecoveryService($database, $auth, $audit),
        );
    }
}
