<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Zwraca zalogowanego użytkownika (tablica) albo null.
 */
function current_user(): ?array
{
    start_session();
    $id = $_SESSION['user_id'] ?? null;
    if (!$id) {
        return null;
    }

    static $cache = null;
    if ($cache !== null && (int) $cache['id'] === (int) $id) {
        return $cache;
    }

    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id AND aktywny = 1');
    $stmt->execute([':id' => $id]);
    $user = $stmt->fetch();

    if (!$user) {
        unset($_SESSION['user_id']);
        return null;
    }

    $cache = $user;
    return $user;
}

/** Czy zalogowany? */
function is_logged_in(): bool
{
    return current_user() !== null;
}

/** Wymusza zalogowanie (przekierowanie do formularza logowania). */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('warning', 'Zaloguj się, aby uzyskać dostęp do panelu.');
        redirect(app_url('login.php'));
    }
}

/** Sprawdza, czy zalogowany użytkownik należy do wskazanej grupy. */
function in_group(string ...$groups): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    return in_array($user['grupa'], $groups, true);
}

/** Wymusza zalogowanie + przynależność do grupy (403 gdy brak uprawnień). */
function require_group(string ...$groups): void
{
    require_login();
    if (!in_group(...$groups)) {
        http_response_code(403);
        $dozwolone = implode(', ', array_map(
            static fn (string $g): string => GROUPS[$g] ?? $g,
            $groups
        ));
        echo '<!doctype html><html lang="pl"><head><meta charset="utf-8">'
            . '<title>403 – brak uprawnień</title>'
            . '<link rel="stylesheet" href="../assets/style.css"></head>'
            . '<body class="page-plain"><div class="card card-narrow">'
            . '<h1>403 – brak uprawnień</h1>'
            . '<p>Ta sekcja jest dostępna wyłącznie dla: <strong>' . e($dozwolone) . '</strong>.</p>'
            . '<a class="btn" href="index.php">Wróć do panelu</a>'
            . '</div></body></html>';
        exit;
    }
}

/** Logowanie użytkownika (ustawia sesję). */
function login_user(int $userId, bool $fresh = false): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    if ($fresh) {
        $_SESSION['fresh_login'] = true;
    }
}

/** Wylogowanie. */
function logout_user(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
    }
    session_destroy();
}

/* ------------------------------------------------------------------ */
/* Kody jednorazowe                                                    */
/* ------------------------------------------------------------------ */

/**
 * Tworzy nowy kod (6 znaków) dla użytkownika i unieważnia poprzednie.
 */
function create_code(int $userId, string $cel = 'logowanie'): array
{
    $pdo = db();
    $pdo->prepare('UPDATE kody SET uzyty_at = :now WHERE user_id = :u AND cel = :c AND uzyty_at IS NULL')
        ->execute([':now' => date('Y-m-d H:i:s'), ':u' => $userId, ':c' => $cel]);

    $kod = generate_code();
    $wygasa = date('Y-m-d H:i:s', time() + CODE_TTL_MINUTES * 60);

    $stmt = $pdo->prepare(
        'INSERT INTO kody (user_id, kod, cel, wygasa_at, wyslany_at, created_at)
         VALUES (:u, :k, :c, :w, :s, :n)'
    );
    $stmt->execute([
        ':u' => $userId,
        ':k' => $kod,
        ':c' => $cel,
        ':w' => $wygasa,
        ':s' => date('Y-m-d H:i:s'),
        ':n' => date('Y-m-d H:i:s'),
    ]);

    return ['kod' => $kod, 'wygasa_at' => $wygasa];
}

/** Aktywny (niewykorzystany, niewygasły) kod danego celu. */
function active_code(int $userId, string $cel = 'logowanie'): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM kody WHERE user_id = :u AND cel = :c AND uzyty_at IS NULL AND wygasa_at > :now
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([':u' => $userId, ':c' => $cel, ':now' => date('Y-m-d H:i:s')]);
    return $stmt->fetch() ?: null;
}

/** Sprawdza i oznacza kod jako zużyty. */
function consume_code(int $userId, string $code, string $cel = 'logowanie'): bool
{
    $stmt = db()->prepare(
        'SELECT * FROM kody WHERE user_id = :u AND kod = :k AND cel = :c AND uzyty_at IS NULL AND wygasa_at > :now
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([
        ':u' => $userId,
        ':k' => strtoupper(trim($code)),
        ':c' => $cel,
        ':now' => date('Y-m-d H:i:s'),
    ]);
    $row = $stmt->fetch();

    if (!$row) {
        return false;
    }

    db()->prepare('UPDATE kody SET uzyty_at = :now WHERE id = :id')
        ->execute([':now' => date('Y-m-d H:i:s'), ':id' => $row['id']]);

    return true;
}

/**
 * Generuje 6-znakowy kod i wysyła go e-mailem do wskazanego użytkownika.
 */
function generate_and_send_code(int $userId, string $cel = 'logowanie'): array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = :id');
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new RuntimeException('Nie znaleziono użytkownika.');
    }

    $data = create_code($userId, $cel);

    $subject = $cel === 'aktywacja'
        ? 'Kod aktywacyjny konta – ' . APP_NAME
        : 'Kod weryfikacyjny – ' . APP_NAME;

    $tresc = $cel === 'aktywacja'
        ? 'Otrzymujesz kod aktywacyjny konta w panelu ' . APP_NAME . '. '
            . 'Wpisz go na stronie aktywacji i ustaw nowe hasło.'
        : 'Otrzymujesz kod weryfikacyjny do panelu ' . APP_NAME . '. '
            . 'Kod jest wymagany przy następnym logowaniu.';

    $wyslany = send_email(
        (string) $user['email'],
        $subject,
        email_template($subject, $tresc, $data['kod'])
    );

    log_action(
        'wyslano_kod',
        $user['email'] . ' | cel=' . $cel . ' | wyslany=' . ($wyslany ? 'tak' : 'nie'),
        $userId
    );

    return [
        'kod'       => $data['kod'],
        'wygasa_at' => $data['wygasa_at'],
        'email'     => (string) $user['email'],
        'wyslany'   => $wyslany,
        'cel'       => $cel,
    ];
}
