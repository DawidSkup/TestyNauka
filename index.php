<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    redirect(app_url('admin/index.php'));
}

redirect(app_url('login.php'));
