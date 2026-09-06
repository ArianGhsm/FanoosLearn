<?php

declare(strict_types=1);

putenv('FANOOS_TEST_MODE=static');
require dirname(__DIR__, 2) . '/tests/run.php';
