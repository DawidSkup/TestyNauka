<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$user = current_user();
if ($user) {
    log_action('wylogowanie', (string) $user['email'], (int) $user['id']);
}

logout_user();

start_session();
flash('info', 'Wylogowano z panelu.');
redirect(app_url('login.php'));
