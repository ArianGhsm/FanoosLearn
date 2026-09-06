<?php

declare(strict_types=1);

namespace Fanoos\Platform\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Commerce\CommerceService;
use Fanoos\Platform\Commerce\FakePaymentGateway;
use Fanoos\Platform\Content\ProtectedResourceAuthorizer;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Http\ApiKernel;
use Fanoos\Platform\Identity\AuthService;
use Fanoos\Platform\Identity\PasswordHasher;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;

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

        return new ApiKernel(
            new AuthService($database, new PasswordHasher(), $audit),
            new WorkspacePlatformService($database, $access, $audit),
            new CommerceService($database, $access, $entitlements, $audit, new FakePaymentGateway(), $callbackKey),
            $entitlements,
            new ProtectedResourceAuthorizer($database, $authorizer, $entitlements),
            $paymentsEnabled,
        );
    }
}
