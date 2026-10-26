<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/* ------------------------------------------------------------------ */
/* Widok / formatowanie                                                */
/* ------------------------------------------------------------------ */

/** Escape dla HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Format daty ISO -> "12.05.2026 14:30". */
function fmt_date(?string $iso): string
{
    if (!$iso) {
        return '—';
    }
    $ts = strtotime($iso);
    return $ts ? date('d.m.Y H:i', $ts) : '—';
}

/** Czas do wygaśnięcia w formie "12 min". */
function fmt_remaining(?string $iso): string
{
    if (!$iso) {
        return '—';
    }
    $ts = strtotime($iso);
    if ($ts === false) {
        return '—';
    }
    if (code_is_permanent($iso)) {
        return 'bezterminowo';
    }
    $diff = $ts - time();
    if ($diff <= 0) {
        return 'wygasł';
    }
    if ($diff < 60) {
        return $diff . ' s';
    }
    return intdiv($diff, 60) . ' min';
}

/* ------------------------------------------------------------------ */
/* Sesja / CSRF / komunikaty                                           */
/* ------------------------------------------------------------------ */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Token CSRF sesyjny. */
function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Gotowy ukryty input z tokenem CSRF. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Weryfikacja tokenu CSRF. */
function csrf_valid(?string $token): bool
{
    start_session();
    return is_string($token)
        && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

/** Wymuszenie poprawnego tokenu (przerywa request w razie błędu). */
function csrf_require(?string $token): void
{
    if (!csrf_valid($token)) {
        http_response_code(400);
        exit('Nieprawidłowy token CSRF – formularz odrzucony.');
    }
}

/** Dodaje komunikat typu flash. */
function flash(string $type, string $message): void
{
    start_session();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Pobiera i czyści komunikaty flash. */
function flash_take(): array
{
    start_session();
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

/** Przekierowanie z zatrzymaniem sesji. */
function redirect(string $url): never
{
    start_session();
    header('Location: ' . $url);
    exit;
}

/**
 * Adres bazowy aplikacji w stosunku do document root (np. "/TestNauka/").
 * Dzięki temu linki działają niezależnie od tego, czy aplikacja jest w podkatalogu.
 */
function app_base(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $base = '/';
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $appDir  = realpath(__DIR__) ?: __DIR__;

    if ($docRoot !== '' && $appDir !== '') {
        $docRoot = str_replace('\\', '/', $docRoot);
        $appDir  = str_replace('\\', '/', $appDir);
        if (str_starts_with($appDir, $docRoot)) {
            $rel = trim(substr($appDir, strlen($docRoot)), '/');
            $base = $rel === '' ? '/' : '/' . $rel . '/';
        }
    }

    return $base;
}

/** Pełny adres URL wewnątrz aplikacji. */
function app_url(string $path = ''): string
{
    return app_base() . ltrim($path, '/');
}

/**
 * Adres URL z hostem – używany w linkach w e-mailach (względny URL
 * nie jest klikalny w klientach poczty). Gdy brak hosta (CLI),
 * zwraca adres względny.
 */
function app_absolute_url(string $path = ''): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');

    if ($host === '') {
        return app_url($path);
    }

    return ($https ? 'https://' : 'http://') . $host . app_url($path);
}

/* ------------------------------------------------------------------ */
/* Kody weryfikacyjne                                                  */
/* ------------------------------------------------------------------ */

/**
 * Generuje kod składający się z 6 znaków: cyfry + wielkie litery (A-Z, 0-9).
 * Gwarantuje obecność co najmniej jednej cyfry i jednej litery.
 */
function generate_code(int $length = CODE_LENGTH): string
{
    $litery  = 'ABCDEFGHJKLMNPQRSTUVWXYZ'; // bez znaków mylących (I, O)
    $cyfry   = '23456789';                // bez znaków mylących (0, 1)
    $alfabet = $litery . $cyfry;

    $maxLitery  = strlen($litery) - 1;
    $maxCyfry   = strlen($cyfry) - 1;
    $maxAlfabet = strlen($alfabet) - 1;

    // Pozycje losowe + gwarantowana cyfra na pierwszym miejscu i litera na ostatnim.
    $kod = $cyfry[random_int(0, $maxCyfry)];
    for ($i = 1; $i < $length - 1; $i++) {
        $kod .= $alfabet[random_int(0, $maxAlfabet)];
    }
    $kod .= $litery[random_int(0, $maxLitery)];

    return $kod;
}

/**
 * Data ważności kodu stałego (bezterminowego) – używana dla kodów logowania
 * kont, które nie wymagają ponownego generowania kodu.
 */
function permanent_code_expiry(): string
{
    return '9999-12-31 23:59:59';
}

/** Czy podana data ważności oznacza kod stały (bezterminowy)? */
function code_is_permanent(?string $wygasaAt): bool
{
    if ($wygasaAt === null || $wygasaAt === '') {
        return false;
    }
    $ts = strtotime($wygasaAt);
    return $ts !== false && $ts >= strtotime('9000-01-01');
}

/* ------------------------------------------------------------------ */
/* Prezentacje i testy – odblokowywanie testów                         */
/* ------------------------------------------------------------------ */

/** Numer porządkowy z automatycznego tytułu („Test 3” → 3, „Prezentacja 3” → 3). */
function numer_z_tytulu(?string $tytul): int
{
    if (!is_string($tytul) || preg_match('/(\d+)\s*$/u', $tytul, $m) !== 1) {
        return 0;
    }

    return (int) $m[1];
}

/**
 * Opublikowane prezentacje z co najmniej jednym opublikowanym slajdem,
 * pogrupowane po numerze z tytułu („Prezentacja 2” → 2).
 *
 * @return array<int, array{id:int, tytul:string}>
 */
function prezentacje_wg_numeru(PDO $pdo): array
{
    $sql = "SELECT p.id, p.tytul,
                   (SELECT COUNT(*)
                      FROM prezentacje_slajdy ps
                      JOIN slajdy s ON s.id = ps.slajd_id
                     WHERE ps.prezentacja_id = p.id AND s.status = 'opublikowany') AS liczba_slajdow
              FROM prezentacje p
             WHERE p.status = 'opublikowany'
             ORDER BY p.id ASC";

    $mapa = [];
    foreach ($pdo->query($sql)->fetchAll() as $prezentacja) {
        $numer = numer_z_tytulu((string) $prezentacja['tytul']);

        if ($numer > 0 && (int) $prezentacja['liczba_slajdow'] > 0 && !isset($mapa[$numer])) {
            $mapa[$numer] = ['id' => (int) $prezentacja['id'], 'tytul' => (string) $prezentacja['tytul']];
        }
    }

    return $mapa;
}

/**
 * ID prezentacji obejrzanych przez użytkownika.
 *
 * @return int[]
 */
function obejrzane_prezentacje(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT prezentacja_id FROM prezentacje_obejrzane WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Zapisuje obejrzenie prezentacji (powtórne wywołanie nic nie zmienia). */
function oznacz_prezentacje_obejrzana(PDO $pdo, int $userId, int $prezentacjaId): void
{
    $stmt = $pdo->prepare(
        'INSERT OR IGNORE INTO prezentacje_obejrzane (user_id, prezentacja_id, created_at)
         VALUES (:user_id, :prezentacja_id, :now)'
    );
    $stmt->execute([
        ':user_id'        => $userId,
        ':prezentacja_id' => $prezentacjaId,
        ':now'            => date('Y-m-d H:i:s'),
    ]);
}

/**
 * Prezentacja, którą trzeba obejrzeć przed testem o tym samym numerze,
 * albo null, gdy test jest odblokowany (brak pasującej prezentacji = brak blokady).
 *
 * @param array<int, array{id:int, tytul:string}> $prezentacje wynik prezentacje_wg_numeru()
 * @param int[]                                   $obejrzane   wynik obejrzane_prezentacje()
 * @return array{id:int, tytul:string}|null
 */
function blokujaca_prezentacja(string $tytulTestu, array $prezentacje, array $obejrzane): ?array
{
    $numer = numer_z_tytulu($tytulTestu);

    if ($numer <= 0 || !isset($prezentacje[$numer])) {
        return null;
    }

    return in_array($prezentacje[$numer]['id'], $obejrzane, true) ? null : $prezentacje[$numer];
}

/**
 * Opublikowane testy z co najmniej jednym pytaniem, pogrupowane po numerze
 * z tytułu („Test 2” → 2). Test bez pytań nie da się rozwiązać, więc nie blokuje.
 *
 * @return array<int, array{id:int, tytul:string}>
 */
function testy_wg_numeru(PDO $pdo): array
{
    $sql = "SELECT t.id, t.tytul,
                   (SELECT COUNT(*) FROM pytania p WHERE p.test_id = t.id) AS liczba_pytan
              FROM testy t
             WHERE t.status = 'opublikowany'
             ORDER BY t.id ASC";

    $mapa = [];
    foreach ($pdo->query($sql)->fetchAll() as $test) {
        $numer = numer_z_tytulu((string) $test['tytul']);

        if ($numer > 0 && (int) $test['liczba_pytan'] > 0 && !isset($mapa[$numer])) {
            $mapa[$numer] = ['id' => (int) $test['id'], 'tytul' => (string) $test['tytul']];
        }
    }

    return $mapa;
}

/**
 * ID testów, które użytkownik rozwiązał (ma zapisany co najmniej jeden wynik).
 *
 * @return int[]
 */
function rozwiazane_testy(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT DISTINCT test_id FROM wyniki_testow WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $userId]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Test, który trzeba rozwiązać, aby odblokować prezentację o numerze N
 * (test o numerze N-1), albo null, gdy prezentacja jest odblokowana.
 *
 * @param array<int, array{id:int, tytul:string}> $testy      wynik testy_wg_numeru()
 * @param int[]                                   $rozwiazane wynik rozwiazane_testy()
 * @return array{id:int, tytul:string}|null
 */
function blokujacy_test(string $tytulPrezentacji, array $testy, array $rozwiazane): ?array
{
    $numer = numer_z_tytulu($tytulPrezentacji);

    // Pierwsza prezentacja jest dostępna od razu.
    if ($numer <= 1) {
        return null;
    }

    $wymagany = $numer - 1;

    if (!isset($testy[$wymagany])) {
        return null;
    }

    return in_array($testy[$wymagany]['id'], $rozwiazane, true) ? null : $testy[$wymagany];
}

/* ------------------------------------------------------------------ */
/* Wysyłka e-mail                                                      */
/* ------------------------------------------------------------------ */

/**
 * Wysyła e-mail HTML. W środowisku Laragon korespondent jest przechwytywany
 * przez Mailpit (http://127.0.0.1:8025).
 */
function send_email(string $to, string $subject, string $html): bool
{
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $body = chunk_split(base64_encode($html));

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'Reply-To: ' . MAIL_FROM,
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    $ok = @mail($to, $encodedSubject, $body, implode("\r\n", $headers));

    if (!$ok) {
        db()->prepare('INSERT INTO log (user_id, akcja, szczegoly, created_at) VALUES (NULL, :a, :s, :c)')
            ->execute([
                ':a' => 'blad_wysylki_maila',
                ':s' => $to . ' – ' . $subject,
                ':c' => date('Y-m-d H:i:s'),
            ]);
    }

    return $ok;
}

/**
 * Buduje prosty szablon e-maila (HTML) z kodem wyróżnionym.
 * Opcjonalny $link dodaje przycisk z odnośnikiem (np. do rejestracji konta).
 */
function email_template(
    string $heading,
    string $paragraph,
    string $code = '',
    ?string $link = null,
    string $linkText = 'Przejdź do panelu',
    ?string $codeNote = null
): string {
    $codeBlock = '';
    if ($code !== '') {
        $note = $codeNote ?? ('Kod jest ważny ' . CODE_TTL_MINUTES . ' minut i może zostać użyty tylko raz.');
        $codeBlock = '<p style="margin:24px 0;text-align:center;">'
            . '<span style="display:inline-block;padding:14px 28px;font-size:30px;letter-spacing:8px;'
            . 'font-family:Consolas,Menlo,monospace;font-weight:bold;background:#f1f5f9;color:#0f172a;'
            . 'border:1px solid #cbd5e1;border-radius:12px;">' . e($code) . '</span></p>'
            . '<p style="text-align:center;color:#64748b;font-size:13px;">' . e($note) . '</p>';
    }

    $linkBlock = '';
    if ($link !== null && $link !== '') {
        $linkBlock = '<p style="margin:20px 0 0;text-align:center;">'
            . '<a href="' . e($link) . '" style="display:inline-block;padding:12px 24px;background:#2563eb;'
            . 'color:#ffffff;text-decoration:none;border-radius:10px;font-weight:600;">' . e($linkText) . '</a></p>'
            . '<p style="margin:10px 0 0;text-align:center;color:#64748b;font-size:12px;word-break:break-all;">'
            . e($link) . '</p>';
    }

    return '<!doctype html><html lang="pl"><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;padding:24px;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">'
        . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:14px;padding:32px;'
        . 'box-shadow:0 1px 3px rgba(15,23,42,.12);">'
        . '<h1 style="margin:0 0 12px;font-size:22px;">' . e($heading) . '</h1>'
        . '<p style="margin:0;font-size:15px;line-height:1.6;color:#334155;">' . e($paragraph) . '</p>'
        . $codeBlock
        . $linkBlock
        . '<p style="margin:28px 0 0;font-size:12px;color:#94a3b8;">' . e(MAIL_FROM_NAME) . '</p>'
        . '</div></body></html>';
}

/* ------------------------------------------------------------------ */
/* Log zdarzeń                                                         */
/* ------------------------------------------------------------------ */

function log_action(string $action, string $details = '', ?int $userId = null): void
{
    db()->prepare('INSERT INTO log (user_id, akcja, szczegoly, created_at) VALUES (:u, :a, :s, :c)')
        ->execute([
            ':u' => $userId,
            ':a' => $action,
            ':s' => $details,
            ':c' => date('Y-m-d H:i:s'),
        ]);
}
