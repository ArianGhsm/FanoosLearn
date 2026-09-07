<?php

declare(strict_types=1);

namespace Fanoos\Platform\Core;

use Fanoos\Platform\Audit\AuditLogger;
use Fanoos\Platform\Authorization\AccessGate;
use Fanoos\Platform\Authorization\ScopeAuthorizer;
use Fanoos\Platform\Commerce\BotCommerceService;
use Fanoos\Platform\Commerce\CommerceService;
use Fanoos\Platform\Commerce\FakePaymentGateway;
use Fanoos\Platform\Content\DeliveryReceiptService;
use Fanoos\Platform\Content\ProtectedMediaEnqueueService;
use Fanoos\Platform\Content\ProtectedMediaJobService;
use Fanoos\Platform\Content\ProtectedResourceAuthorizer;
use Fanoos\Platform\Content\SecureDeliveryService;
use Fanoos\Platform\Entitlements\EntitlementService;
use Fanoos\Platform\Http\HumanMessagingApiKernel;
use Fanoos\Platform\Http\InternalApiKernel;
use Fanoos\Platform\Identity\AuthService;
use Fanoos\Platform\Identity\PasswordHasher;
use Fanoos\Platform\Integration\ServiceAuthenticator;
use Fanoos\Platform\Messaging\ChannelSubjectProtector;
use Fanoos\Platform\Messaging\MessagingLinkService;
use Fanoos\Platform\Notifications\NotificationDeliveryService;
use Fanoos\Platform\Operations\DeploymentControlService;
use Fanoos\Platform\Storage\SignedDownloadToken;
use Fanoos\Platform\Support\DatabaseConnection;
use Fanoos\Platform\Support\RuntimeConfig;

final class Stage7Factory
{
    public static function humanMessaging(): HumanMessagingApiKernel
    {
        $database = DatabaseConnection::fromEnvironment();
        $config = RuntimeConfig::load();
        $audit = new AuditLogger($database);
        $links = new MessagingLinkService(
            $database,
            $audit,
            new ChannelSubjectProtector($config->requireString('FANOOS_MESSAGING_SUBJECT_KEY')),
        );
        return new HumanMessagingApiKernel(new AuthService($database, new PasswordHasher(), $audit), $links);
    }

    public static function internal(): InternalApiKernel
    {
        $database = DatabaseConnection::fromEnvironment();
        $config = RuntimeConfig::load();
        $audit = new AuditLogger($database);
        $authorizer = new ScopeAuthorizer($database);
        $access = new AccessGate($database, $authorizer);
        $entitlements = new EntitlementService($database, $access, $audit);
        $resources = new ProtectedResourceAuthorizer($database, $authorizer, $entitlements);
        $environment = $config->optionalString('FANOOS_ENV', 'production');
        $gatewayKey = $config->optionalString('FANOOS_PAYMENT_GATEWAY', 'disabled');
        $paymentsEnabled = $gatewayKey === 'fake' && in_array($environment, ['development', 'test'], true);
        $callbackKey = $config->optionalString('FANOOS_PAYMENT_CALLBACK_KEY', 'disabled-payment-callback-key-000000') ?? '';
        $commerce = new CommerceService($database, $access, $entitlements, $audit, new FakePaymentGateway(), $callbackKey);
        $links = new MessagingLinkService(
            $database,
            $audit,
            new ChannelSubjectProtector($config->requireString('FANOOS_MESSAGING_SUBJECT_KEY')),
        );
        $downloadTokens = new SignedDownloadToken($config->requireString('FANOOS_DOWNLOAD_SIGNING_KEY'));
        $delivery = new SecureDeliveryService(
            $database,
            $resources,
            $downloadTokens,
            $audit,
            $config->requireString('FANOOS_DELIVERY_SIGNING_KEY'),
        );
        $mediaJobs = new ProtectedMediaJobService($database, $resources, $downloadTokens);

        return new InternalApiKernel(
            new ServiceAuthenticator($database),
            $links,
            new BotCommerceService($database, $access, $commerce, $entitlements),
            new NotificationDeliveryService($database, $links),
            $delivery,
            new DeliveryReceiptService($database),
            new ProtectedMediaEnqueueService($database, $mediaJobs),
            $mediaJobs,
            new DeploymentControlService($database, $access, $audit),
            $paymentsEnabled,
        );
    }
}
