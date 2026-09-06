<?php

declare(strict_types=1);

use Fanoos\Platform\Core\PlatformFactory;
use Fanoos\Platform\Http\Request;

require dirname(__DIR__) . '/bootstrap.php';

PlatformFactory::api()->handle(Request::fromGlobals())->emit();
