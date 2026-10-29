<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

start_session();

$userId = (int) ($_SESSION['pending_user_id'] ?? 0);
if ($userId === 0) {
    flash('warning', 'Sesja weryfikacji wygasła. Zaloguj się ponownie.');
    redirect(app_url('login.php'));
}

$stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch();

if (!$user) {
    unset($_SESSION['pending_user_id']);
    redirect(app_url('login.php'));
}

$error = '';
$code  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf'] ?? null);

    $code = strtoupper(trim((string) ($_POST['kod'] ?? '')));

    if ($code === '') {
        $error = 'Podaj kod weryfikacyjny.';
    } elseif (!preg_match('/^[A-Z0-9]{6}$/', $code)) {
        $error = 'Kod musi składać się z 6 znaków – wielkich liter lub cyfr.';
    } elseif (consume_code($userId, $code, 'logowanie')) {
        unset($_SESSION['pending_user_id']);
        login_user($userId, true);
        log_action('weryfikacja_kodu', (string) $user['email'], $userId);
        flash('success', 'Kod poprawny – zalogowano.');
        redirect(app_url('admin/index.php'));
    } else {
        $error = 'Kod jest nieprawidłowy lub wygasł.';
        log_action('bledny_kod', (string) $user['email'], $userId);
    }
}

$aktywny = active_code($userId, 'logowanie');
$flashes = flash_take();
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Weryfikacja kodu – <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_asset_url('assets/style.css')) ?>">
</head>
<body class="page-login">
<div class="login-box">
    <div class="login-head">
        <h1>Weryfikacja kodu</h1>
        <p class="muted">Kod został wysłany na adres <?= e($user['email']) ?></p>
    </div>

    <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="form">
        <?= csrf_field() ?>
        <label for="kod">Kod weryfikacyjny (6 znaków)</label>
        <input type="text" id="kod" name="kod" class="input-code" value="<?= e($code) ?>"
               maxlength="6" pattern="[A-Za-z0-9]{6}" placeholder="K7M2QX" required autofocus
               autocomplete="one-time-code">
        <?php if ($aktywny): ?>
            <p class="muted small">Kod ważny do <?= e(fmt_date($aktywny['wygasa_at'])) ?>
                (pozostało <?= e(fmt_remaining($aktywny['wygasa_at'])) ?>).</p>
        <?php else: ?>
            <p class="muted small">Brak aktywnego kodu – poproś administratora o nowy.</p>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary btn-block">Potwierdź kod</button>
    </form>

    <p class="login-foot"><a href="<?= e(app_url('login.php')) ?>">Wróć do logowania</a></p>
</div>
</body>
</html>
