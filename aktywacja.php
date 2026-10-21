<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$email = trim((string) ($_GET['email'] ?? ($_POST['email'] ?? '')));
$error = '';
$info  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf'] ?? null);

    $email  = strtolower(trim((string) ($_POST['email'] ?? '')));
    $kod    = strtoupper(trim((string) ($_POST['kod'] ?? '')));
    $haslo  = (string) ($_POST['haslo'] ?? '');
    $haslo2 = (string) ($_POST['haslo2'] ?? '');

    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'Nie znaleziono konta o takim adresie e-mail.';
    } elseif (!preg_match('/^[A-Z0-9]{6}$/', $kod)) {
        $error = 'Kod musi składać się z 6 znaków – wielkich liter lub cyfr.';
    } elseif (strlen($haslo) < 8) {
        $error = 'Hasło musi mieć co najmniej 8 znaków.';
    } elseif ($haslo !== $haslo2) {
        $error = 'Hasła nie są identyczne.';
    } elseif (!consume_code((int) $user['id'], $kod, 'aktywacja')) {
        $error = 'Kod jest nieprawidłowy lub wygasł.';
        log_action('bledny_kod_aktywacja', (string) $user['email'], (int) $user['id']);
    } else {
        db()->prepare('UPDATE users SET haslo_hash = :h, aktywowany = 1, updated_at = :n WHERE id = :id')
            ->execute([
                ':h'  => password_hash($haslo, PASSWORD_DEFAULT),
                ':n'  => date('Y-m-d H:i:s'),
                ':id' => (int) $user['id'],
            ]);
        log_action('aktywacja_konta', (string) $user['email'], (int) $user['id']);
        flash('success', 'Konto aktywowane. Możesz się zalogować.');
        redirect(app_url('login.php'));
    }
}

$flashes = flash_take();
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aktywacja konta – <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_url('assets/style.css')) ?>">
</head>
<body class="page-login">
<div class="login-box">
    <div class="login-head">
        <h1>Aktywacja konta</h1>
        <p class="muted">Wpisz kod otrzymany e-mailem i ustaw nowe hasło.</p>
    </div>

    <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="form">
        <?= csrf_field() ?>
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required>

        <label for="kod">Kod aktywacyjny (6 znaków)</label>
        <input type="text" id="kod" name="kod" class="input-code" maxlength="6"
               pattern="[A-Za-z0-9]{6}" placeholder="K7M2QX" required autocomplete="one-time-code">

        <label for="haslo">Nowe hasło</label>
        <input type="password" id="haslo" name="haslo" minlength="8" required autocomplete="new-password">

        <label for="haslo2">Powtórz hasło</label>
        <input type="password" id="haslo2" name="haslo2" minlength="8" required autocomplete="new-password">

        <button type="submit" class="btn btn-primary btn-block">Aktywuj konto</button>
    </form>

    <p class="login-foot"><a href="<?= e(app_url('login.php')) ?>">Wróć do logowania</a></p>
</div>
</body>
</html>
