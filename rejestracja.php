<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    redirect(app_url('admin/index.php'));
}

$error = '';
$email = trim((string) ($_GET['email'] ?? ''));
$kod   = strtoupper(trim((string) ($_GET['kod'] ?? '')));
$imie  = trim((string) ($_GET['imie'] ?? ''));

/* Throttling: 5 błędnych prób = blokada rejestracji na 10 minut. */
$blokadaDo = (int) ($_SESSION['rej_blokada_do'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf'] ?? null);

    $email  = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $kod    = strtoupper(trim((string) ($_POST['kod'] ?? '')));
    $imie   = trim((string) ($_POST['imie'] ?? ''));
    $haslo  = (string) ($_POST['haslo'] ?? '');
    $haslo2 = (string) ($_POST['haslo2'] ?? '');

    if ($blokadaDo > time()) {
        $error = 'Zbyt wiele nieudanych prób rejestracji. Spróbuj ponownie za '
            . (int) ceil(($blokadaDo - time()) / 60) . ' min.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Podaj poprawny adres e-mail.';
    } elseif (!preg_match('/^[A-Z0-9]{6}$/', $kod)) {
        $error = 'Kod musi składać się z 6 znaków – wielkich liter lub cyfr.';
    } elseif (mb_strlen($imie) < 2) {
        $error = 'Podaj imię i nazwisko.';
    } elseif (strlen($haslo) < 8) {
        $error = 'Hasło musi mieć co najmniej 8 znaków.';
    } elseif ($haslo !== $haslo2) {
        $error = 'Hasła nie są identyczne.';
    } else {
        try {
            $newId = register_from_invitation($email, $kod, $imie, $haslo);

            unset($_SESSION['rej_bledy'], $_SESSION['rej_blokada_do']);

            login_user($newId, true);
            flash('success', 'Konto utworzone. Witaj, ' . $imie . '!');
            redirect(app_url('admin/index.php'));
        } catch (RuntimeException $ex) {
            $error = $ex->getMessage();

            $_SESSION['rej_bledy'] = (int) ($_SESSION['rej_bledy'] ?? 0) + 1;
            if ((int) $_SESSION['rej_bledy'] >= 5) {
                $_SESSION['rej_bledy'] = 0;
                $_SESSION['rej_blokada_do'] = time() + 600;
                $error .= ' Po 5 próbach rejestracja jest blokowana na 10 minut.';
            }
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
    <title>Rejestracja konta – <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_url('assets/style.css')) ?>">
</head>
<body class="page-login">
<div class="login-box">
    <div class="login-head">
        <h1>Załóż konto</h1>
        <p class="muted">Wpisz e-mail i kod rejestracyjny otrzymany od administratora.</p>
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

        <label for="kod">Kod rejestracyjny (6 znaków)</label>
        <input type="text" id="kod" name="kod" class="input-code" maxlength="6"
               pattern="[A-Za-z0-9]{6}" placeholder="K7M2QX" value="<?= e($kod) ?>" required autocomplete="one-time-code">

        <label for="imie">Imię i nazwisko</label>
        <input type="text" id="imie" name="imie" value="<?= e($imie) ?>" required autocomplete="name">

        <label for="haslo">Hasło</label>
        <input type="password" id="haslo" name="haslo" minlength="8" required autocomplete="new-password">

        <label for="haslo2">Powtórz hasło</label>
        <input type="password" id="haslo2" name="haslo2" minlength="8" required autocomplete="new-password">

        <button type="submit" class="btn btn-primary btn-block">Załóż konto</button>
    </form>

    <p class="login-foot muted">
        Kod rejestracyjny jest ważny 15 minut i można założyć nim konto tylko raz.
        Po założeniu konta ten sam kod staje się stałym kodem logowania – nie wygasa
        i możesz używać go przy każdym logowaniu.
    </p>
    <p class="login-foot"><a href="<?= e(app_url('login.php')) ?>">Wróć do logowania</a></p>
</div>
</body>
</html>
