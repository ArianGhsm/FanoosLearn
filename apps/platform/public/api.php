<?php

declare(strict_types=1);

use Fanoos\Platform\Core\PlatformFactory;
use Fanoos\Platform\Core\Stage7Factory;
use Fanoos\Platform\Http\Request;

require dirname(__DIR__) . '/bootstrap.php';

$request = Request::fromGlobals();
if (str_starts_with($request->path, '/api/internal/v1/')) {
    Stage7Factory::internal()->handle($request)->emit();
    return;
}
if (str_starts_with($request->path, '/api/v1/messaging/')) {
    Stage7Factory::humanMessaging()->handle($request)->emit();
    return;
}
PlatformFactory::api()->handle($request)->emit();
