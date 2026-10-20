<?php
/**
 * Shared admin bootstrap. Must be included before any output.
 */

declare(strict_types=1);

define('QB_ADMIN', true);

require dirname(__DIR__) . '/app/bootstrap.php';

if (!QB_INSTALLED) {
    redirect(url('install.php'));
}

qb_session_start();
