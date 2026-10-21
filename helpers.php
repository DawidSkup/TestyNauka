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
    $diff = strtotime($iso) - time();
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
 */
function email_template(string $heading, string $paragraph, string $code = ''): string
{
    $codeBlock = '';
    if ($code !== '') {
        $codeBlock = '<p style="margin:24px 0;text-align:center;">'
            . '<span style="display:inline-block;padding:14px 28px;font-size:30px;letter-spacing:8px;'
            . 'font-family:Consolas,Menlo,monospace;font-weight:bold;background:#f1f5f9;color:#0f172a;'
            . 'border:1px solid #cbd5e1;border-radius:12px;">' . e($code) . '</span></p>'
            . '<p style="text-align:center;color:#64748b;font-size:13px;">'
            . 'Kod jest ważny ' . CODE_TTL_MINUTES . ' minut i może zostać użyty tylko raz.</p>';
    }

    return '<!doctype html><html lang="pl"><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;padding:24px;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">'
        . '<div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:14px;padding:32px;'
        . 'box-shadow:0 1px 3px rgba(15,23,42,.12);">'
        . '<h1 style="margin:0 0 12px;font-size:22px;">' . e($heading) . '</h1>'
        . '<p style="margin:0;font-size:15px;line-height:1.6;color:#334155;">' . e($paragraph) . '</p>'
        . $codeBlock
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
