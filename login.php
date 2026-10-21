<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    redirect(app_url('admin/index.php'));
}

$error = '';
$email = '';

/* Throttling: 5 błędnych próbl = blokada logowania na 10 minut. */
$blokadaDo = (int) ($_SESSION['login_blokada_do'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf'] ?? null);

    $email = trim((string) ($_POST['email'] ?? ''));
    $haslo = (string) ($_POST['haslo'] ?? '');

    if ($blokadaDo > time()) {
        $error = 'Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za '
            . (int) ceil(($blokadaDo - time()) / 60) . ' min.';
    } elseif ($email === '' || $haslo === '') {
        $error = 'Podaj e-mail i hasło.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => mb_strtolower($email)]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($haslo, (string) $user['haslo_hash'])) {
            $error = 'Nieprawidłowy e-mail lub hasło.';
            log_action('nieudane_logowanie', $email);

            $_SESSION['login_bledy'] = (int) ($_SESSION['login_bledy'] ?? 0) + 1;
            if ((int) $_SESSION['login_bledy'] >= 5) {
                $_SESSION['login_bledy'] = 0;
                $_SESSION['login_blokada_do'] = time() + 600;
                $error .= ' Po 5 próbach logowanie jest blokowane na 10 minut.';
            }
        } elseif ((int) $user['aktywny'] !== 1) {
            $error = 'Konto zostało zablokowane. Skontaktuj się z administratorem.';
        } elseif ((int) $user['aktywowany'] !== 1) {
            flash('warning', 'Konto nie zostało jeszcze aktywowane. Użyj kodu aktywacyjnego.');
            redirect(app_url('aktywacja.php?email=' . urlencode($email)));
        } else {
            unset($_SESSION['login_bledy'], $_SESSION['login_blokada_do']);

            // Drugi czynnik: jeśli istnieje aktywny kod logowania – wymagamy go.
            if (active_code((int) $user['id'], 'logowanie') !== null) {
                $_SESSION['pending_user_id'] = (int) $user['id'];
                redirect(app_url('weryfikacja.php'));
            }

            login_user((int) $user['id'], true);
            log_action('logowanie', (string) $user['email'], (int) $user['id']);
            flash('success', 'Zalogowano pomyślnie. Witaj, ' . $user['imie'] . '!');
            redirect(app_url('admin/index.php'));
        }
    }
}

$flashes = flash_take();
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Logowanie – <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_url('assets/style.css')) ?>">
</head>
<body class="page-login">
<div class="login-box">
    <div class="login-head">
        <div class="logo"><?= e(mb_substr(APP_COMPANY, 0, 1)) ?></div>
        <h1><?= e(APP_NAME) ?></h1>
        <p class="muted"><?= e(APP_COMPANY) ?></p>
    </div>

    <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="form" autocomplete="off">
        <?= csrf_field() ?>
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus>

        <label for="haslo">Hasło</label>
        <input type="password" id="haslo" name="haslo" required>

        <button type="submit" class="btn btn-primary btn-block">Zaloguj się</button>
    </form>

    <p class="login-foot muted">
        Nie masz aktywnego konta? Poproś administratora grupy firmy o wygenerowanie kodu.
    </p>
    <p class="login-foot"><a href="<?= e(app_url('aktywacja.php')) ?>">Aktywuj konto kodem</a></p>
</div>
</body>
</html>
