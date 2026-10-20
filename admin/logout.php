<?php
declare(strict_types=1);

require __DIR__ . '/_init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    Auth::logout();
    redirect(admin_url('login.php?logged_out=1'));
}

redirect(admin_url('index.php'));
