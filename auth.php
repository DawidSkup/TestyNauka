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

/* ------------------------------------------------------------------ */
/* Zaproszenia – administrator wysyła kod, konto zakłada odbiorca      */
/* ------------------------------------------------------------------ */

/**
 * Tworzy zaproszenie (6-znakowy kod) dla podanego e-maila.
 * Poprzednie niewykorzystane zaproszenia dla tego adresu są unieważniane.
 */
function create_invitation(string $email, string $grupa = GROUP_MATERIALY, ?int $byUserId = null): array
{
    $pdo = db();
    $email = mb_strtolower(trim($email));

    if (!isset(GROUPS[$grupa])) {
        $grupa = GROUP_MATERIALY;
    }

    $pdo->prepare('UPDATE zaproszenia SET uzyty_at = :now WHERE email = :e AND uzyty_at IS NULL')
        ->execute([':now' => date('Y-m-d H:i:s'), ':e' => $email]);

    $kod    = generate_code();
    $wygasa = date('Y-m-d H:i:s', time() + CODE_TTL_MINUTES * 60);

    $stmt = $pdo->prepare(
        'INSERT INTO zaproszenia (email, grupa, kod, wygasa_at, utworzony_przez, created_at)
         VALUES (:e, :g, :k, :w, :u, :n)'
    );
    $stmt->execute([
        ':e' => $email,
        ':g' => $grupa,
        ':k' => $kod,
        ':w' => $wygasa,
        ':u' => $byUserId,
        ':n' => date('Y-m-d H:i:s'),
    ]);

    return [
        'id'        => (int) $pdo->lastInsertId(),
        'kod'       => $kod,
        'wygasa_at' => $wygasa,
        'email'     => $email,
        'grupa'     => $grupa,
    ];
}

/**
 * Generuje kod rejestracyjny i wysyła go e-mailem.
 * Konto zakłada dopiero odbiorca kodu (rejestracja.php).
 */
function generate_and_send_invitation(string $email, string $grupa = GROUP_MATERIALY, ?int $byUserId = null): array
{
    $email = mb_strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Podaj poprawny adres e-mail.');
    }

    $stmt = db()->prepare('SELECT id FROM users WHERE email = :e');
    $stmt->execute([':e' => $email]);

    if ($stmt->fetch()) {
        throw new RuntimeException('Konto o takim adresie e-mail już istnieje.');
    }

    $data   = create_invitation($email, $grupa, $byUserId);
    $subject = 'Kod rejestracyjny – ' . APP_NAME;
    $link    = app_absolute_url('rejestracja.php');
    $tresc   = 'Otrzymujesz kod rejestracyjny do panelu ' . APP_NAME . '. '
        . 'Wejdź na adres ' . $link . ', wpisz ten adres e-mail oraz kod i ustal hasło. '
        . 'Konto powstanie w grupie: ' . (GROUPS[$data['grupa']] ?? $data['grupa']) . '.';

    $wyslany = send_email(
        $email,
        $subject,
        email_template($subject, $tresc, $data['kod'], $link, 'Załóż konto')
    );

    log_action(
        'wyslano_zaproszenie',
        $email . ' | grupa=' . $data['grupa'] . ' | wyslany=' . ($wyslany ? 'tak' : 'nie'),
        $byUserId
    );

    return $data + ['wyslany' => $wyslany];
}

/**
 * Znajduje aktywne zaproszenie dla pary e-mail + kod.
 */
function find_invitation(string $email, string $kod): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM zaproszenia
          WHERE email = :e AND kod = :k AND uzyty_at IS NULL AND wygasa_at > :now
          ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([
        ':e'   => mb_strtolower(trim($email)),
        ':k'   => strtoupper(trim($kod)),
        ':now' => date('Y-m-d H:i:s'),
    ]);

    return $stmt->fetch() ?: null;
}

/**
 * Oznacza zaproszenie jako wykorzystane. Zwraca true, gdy zaproszenie
 * zostało faktycznie oznaczone (brak wyścigu między żądaniami).
 */
function consume_invitation(int $invitationId): bool
{
    $stmt = db()->prepare('UPDATE zaproszenia SET uzyty_at = :now WHERE id = :id AND uzyty_at IS NULL');
    $stmt->execute([':now' => date('Y-m-d H:i:s'), ':id' => $invitationId]);

    return $stmt->rowCount() === 1;
}

/**
 * Zakłada konto na podstawie zaproszenia (e-mail + kod).
 * Zwraca id nowego użytkownika albo rzuca RuntimeException z komunikatem dla użytkownika.
 */
function register_from_invitation(string $email, string $kod, string $imie, string $haslo): int
{
    $pdo = db();
    $email = mb_strtolower(trim($email));

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :e');
    $stmt->execute([':e' => $email]);

    if ($stmt->fetch()) {
        throw new RuntimeException('Konto o takim adresie e-mail już istnieje.');
    }

    $zap = find_invitation($email, $kod);

    if (!$zap) {
        throw new RuntimeException('Kod jest nieprawidłowy, wygasł albo został już wykorzystany.');
    }

    $grupa = isset(GROUPS[$zap['grupa']]) ? (string) $zap['grupa'] : GROUP_MATERIALY;
    $now   = date('Y-m-d H:i:s');

    $pdo->beginTransaction();

    try {
        if (!consume_invitation((int) $zap['id'])) {
            throw new RuntimeException('Kod jest nieprawidłowy, wygasł albo został już wykorzystany.');
        }

        $pdo->prepare(
            'INSERT INTO users (imie, email, haslo_hash, grupa, aktywny, aktywowany, created_at, updated_at)
             VALUES (:imie, :email, :haslo, :grupa, 1, 1, :now, :now)'
        )->execute([
            ':imie'  => $imie,
            ':email' => $email,
            ':haslo' => password_hash($haslo, PASSWORD_DEFAULT),
            ':grupa' => $grupa,
            ':now'   => $now,
        ]);

        $newId = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }

    log_action('rejestracja_konta', $email . ' | grupa=' . $grupa, $newId);

    return $newId;
}
