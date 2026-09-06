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
use Fanoos\Platform\Content\ExamService;
use Fanoos\Platform\Content\SecureDeliveryService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Http\ApiKernel;
use Fanoos\Platform\Identity\AuthService;
use Fanoos\Platform\Identity\PasswordHasher;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;
use Fanoos\Platform\Storage\SignedDownloadToken;

final class PlatformFactory
{
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
        $exams = new ExamService($database, $access, $authorizer, $entitlements, $audit);
        $deliveryKey = $config->optionalString('FANOOS_DELIVERY_SIGNING_KEY');
        $downloadKey = $config->optionalString('FANOOS_DOWNLOAD_SIGNING_KEY');
        $delivery = is_string($deliveryKey) && strlen($deliveryKey) >= 32
            && is_string($downloadKey) && strlen($downloadKey) >= 32
            ? new SecureDeliveryService($database, $resources, new SignedDownloadToken($downloadKey), $audit, $deliveryKey)
            : null;

        return new ApiKernel(
            new AuthService($database, new PasswordHasher(), $audit),
            new WorkspacePlatformService($database, $access, $audit),
            new CommerceService($database, $access, $entitlements, $audit, new FakePaymentGateway(), $callbackKey),
            $entitlements,
            $resources,
            $paymentsEnabled,
            $content,
            $exams,
            $delivery,
        );
    }
}
