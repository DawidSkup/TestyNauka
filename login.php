<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

if (is_logged_in()) {
    redirect(app_url('admin/index.php'));
}

/**
 * Zapisuje nieudaną próbę logowania (throttling) i zwraca komunikat błędu.
 */
function login_fail(string $komunikat, string $akcja = 'nieudane_logowanie', string $szczegoly = ''): string
{
    $_SESSION['login_bledy'] = (int) ($_SESSION['login_bledy'] ?? 0) + 1;

    if ((int) $_SESSION['login_bledy'] >= 5) {
        $_SESSION['login_bledy'] = 0;
        $_SESSION['login_blokada_do'] = time() + 600;
        $komunikat .= ' Po 5 próbach logowanie jest blokowane na 10 minut.';
    }

    log_action($akcja, $szczegoly);

    return $komunikat;
}

$error = '';
$email = '';
$kod   = '';

/* Tryb logowania: 'admin' (e-mail + hasło) albo 'uzytkownik' (tylko kod z e-maila). */
$tryb = (string) ($_POST['tryb'] ?? ($_GET['tryb'] ?? 'admin'));
if (!in_array($tryb, ['admin', 'uzytkownik'], true)) {
    $tryb = 'admin';
}

/* Throttling: 5 błędnych próbl = blokada logowania na 10 minut. */
$blokadaDo = (int) ($_SESSION['login_blokada_do'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf'] ?? null);

    if ($blokadaDo > time()) {
        $error = 'Zbyt wiele nieudanych prób logowania. Spróbuj ponownie za '
            . (int) ceil(($blokadaDo - time()) / 60) . ' min.';
    } elseif ($tryb === 'uzytkownik') {
        /* -------------------------------------------------------------- */
        /* Tryb użytkownika – logowanie samym kodem z e-maila             */
        /* -------------------------------------------------------------- */
        $kod = strtoupper(trim((string) ($_POST['kod'] ?? '')));

        if (!preg_match('/^[A-Z0-9]{6}$/', $kod)) {
            $error = 'Kod musi składać się z 6 znaków – wielkich liter lub cyfr.';
        } else {
            $user = user_by_login_code($kod);

            if (!$user) {
                $error = login_fail(
                    'Kod jest nieprawidłowy lub wygasł. Poproś administratora o nowy kod.',
                    'nieudane_logowanie_kod',
                    'kod=' . $kod
                );
            } elseif ((int) $user['aktywny'] !== 1) {
                $error = 'Konto zostało zablokowane. Skontaktuj się z administratorem.';
            } elseif ((int) $user['aktywowany'] !== 1 && $user['grupa'] === GROUP_FIRMA) {
                // Konta użytkowników nie wymagają aktywacji – działają od razu.
                $error = 'Konto nie zostało jeszcze aktywowane.';
            } elseif ($user['grupa'] === GROUP_FIRMA && !consume_login_code($kod)) {
                // Kod administratora jest jednorazowy; kod użytkownika działa wielokrotnie.
                $error = login_fail(
                    'Kod jest nieprawidłowy lub wygasł. Poproś administratora o nowy kod.',
                    'nieudane_logowanie_kod',
                    'kod=' . $kod
                );
            } else {
                unset($_SESSION['login_bledy'], $_SESSION['login_blokada_do']);

                login_user((int) $user['id'], true);
                log_action('logowanie_kodem', (string) $user['email'], (int) $user['id']);
                flash('success', 'Zalogowano pomyślnie. Witaj, ' . $user['imie'] . '!');
                redirect(app_url('admin/index.php'));
            }
        }
    } else {
        /* -------------------------------------------------------------- */
        /* Tryb administratora – e-mail + hasło                            */
        /* -------------------------------------------------------------- */
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $haslo = (string) ($_POST['haslo'] ?? '');

        if ($email === '' || $haslo === '') {
            $error = 'Podaj e-mail i hasło.';
        } else {
            $stmt = db()->prepare('SELECT * FROM users WHERE email = :email');
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($haslo, (string) $user['haslo_hash'])) {
                $error = login_fail('Nieprawidłowy e-mail lub hasło.', 'nieudane_logowanie', $email);
            } elseif ($user['grupa'] !== GROUP_FIRMA) {
                $error = login_fail(
                    'To konto nie ma uprawnień administratora – zaloguj się jako użytkownik, kodem z e-maila.',
                    'nieudane_logowanie_tryb',
                    $email
                );
            } elseif ((int) $user['aktywny'] !== 1) {
                $error = 'Konto zostało zablokowane. Skontaktuj się z administratorem.';
            } elseif ((int) $user['aktywowany'] !== 1) {
                flash('warning', 'Konto nie zostało jeszcze aktywowane. Użyj kodu aktywacyjnego.');
                redirect(app_url('aktywacja.php?email=' . urlencode($email)));
            } else {
                unset($_SESSION['login_bledy'], $_SESSION['login_blokada_do']);

                // Drugi czynnik: jeśli istnieje aktywny kod logowania – wymagamy go.
                $maKod = active_code((int) $user['id'], 'logowanie') !== null;

                if ((int) ($user['wymaga_kod'] ?? 0) === 1 && !$maKod) {
                    // Konto z flagą „wymaga kod” – bez kodu od administratora nie da się zalogować.
                    $error = 'To konto wymaga kodu weryfikacyjnego wysyłanego przez administratora. '
                        . 'Poproś administratora o wygenerowanie kodu i spróbuj ponownie.';
                    log_action('brak_kodu_logowania', (string) $user['email'], (int) $user['id']);
                } elseif ($maKod) {
                    $_SESSION['pending_user_id'] = (int) $user['id'];
                    redirect(app_url('weryfikacja.php'));
                } else {
                    login_user((int) $user['id'], true);
                    log_action('logowanie', (string) $user['email'], (int) $user['id']);
                    flash('success', 'Zalogowano pomyślnie. Witaj, ' . $user['imie'] . '!');
                    redirect(app_url('admin/index.php'));
                }
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
    <title>Logowanie – <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_asset_url('assets/style.css')) ?>">
</head>
<body class="page-login">
<div class="login-box">
    <div class="login-head">
        <div class="logo"><?= e(mb_substr(APP_COMPANY, 0, 1)) ?></div>
        <h1><?= e(APP_NAME) ?></h1>
        <p class="muted"><?= e(APP_COMPANY) ?></p>
    </div>

    <nav class="login-tabs">
        <a class="login-tab<?= $tryb === 'admin' ? ' is-active' : '' ?>"
           href="<?= e(app_url('login.php')) ?>">Administrator</a>
        <a class="login-tab<?= $tryb === 'uzytkownik' ? ' is-active' : '' ?>"
           href="<?= e(app_url('login.php?tryb=uzytkownik')) ?>">Użytkownik</a>
    </nav>

    <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
    <?php endforeach; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($tryb === 'uzytkownik'): ?>
        <form method="post" class="form" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="tryb" value="uzytkownik">

            <label for="kod">Kod z e-maila (6 znaków)</label>
            <input type="text" id="kod" name="kod" class="input-code" maxlength="6"
                   pattern="[A-Za-z0-9]{6}" placeholder="K7M2QX" value="<?= e($kod) ?>"
                   required autofocus autocomplete="one-time-code">

            <button type="submit" class="btn btn-primary btn-block">Zaloguj się kodem</button>
        </form>

        <p class="login-foot muted">
            Kod wysyła administrator na Twój adres e-mail. Kod użytkownika jest stały –
            możesz logować się nim wielokrotnie (nowy kod unieważnia poprzedni).
            Kod administratora jest jednorazowy i wygasa po <?= e((string) CODE_TTL_MINUTES) ?> minutach.
        </p>
        <p class="login-foot"><a href="<?= e(app_url('rejestracja.php')) ?>">Załóż konto kodem rejestracyjnym</a></p>
    <?php else: ?>
        <form method="post" class="form" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="tryb" value="admin">

            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="<?= e($email) ?>" required autofocus>

            <label for="haslo">Hasło</label>
            <input type="password" id="haslo" name="haslo" required>

            <button type="submit" class="btn btn-primary btn-block">Zaloguj się</button>
        </form>

        <p class="login-foot muted">
            Konta użytkowników logują się samym kodem z e-maila – zakładka „Użytkownik”.
            Kodem można też zalogować konto administratora (kod wysyła grupa firmy).
        </p>
        <p class="login-foot"><a href="<?= e(app_url('rejestracja.php')) ?>">Załóż konto kodem rejestracyjnym</a></p>
        <p class="login-foot"><a href="<?= e(app_url('aktywacja.php')) ?>">Aktywuj konto kodem</a></p>
    <?php endif; ?>
</div>
</body>
</html>
