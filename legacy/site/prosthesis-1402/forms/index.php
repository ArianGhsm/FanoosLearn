<?php
declare(strict_types=1);

$target = '/forms/?cohort=prosthesis-1402';
if (!empty($_SERVER['QUERY_STRING'])) {
    $target .= '&' . $_SERVER['QUERY_STRING'];
}
header('Location: ' . $target, true, 301);
exit;
