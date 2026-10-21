<?php
// Ręczna kontrola przepływu: wysłanie kodu rejestracyjnego -> rejestracja -> logowanie.
$base  = 'http://127.0.0.1:8099';
$jar   = sys_get_temp_dir() . '/testnauka_manual_' . getmypid() . '.txt';
$email = 'reczytnik' . date('His') . '@firma.pl';

function req(string $method, string $url, array $post = null, bool $follow = true, string $jar = ''): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => $follow,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_TIMEOUT        => 20,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post ?? []));
    }
    $res  = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $body = (string) $res;
    curl_close($ch);

    return ['status' => $code, 'html' => (string) (explode("\r\n\r\n", $body, 2)[1] ?? $body)];
}

function csrf(string $html): string
{
    return (string) (preg_match('/name="csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '');
}

function blad(string $html): array
{
    preg_match_all('/(Warning|Notice|Fatal error|Deprecated|Parse error)[^<\n]*/', $html, $m);
    return $m[0];
}

// 1. Logowanie administratora
$r = req('GET', $base . '/login.php', null, true, $jar);
$t = csrf($r['html']);
$r = req('POST', $base . '/login.php', ['csrf' => $t, 'email' => 'admin@mojafirma.pl', 'haslo' => 'TestNauka2026!'], true, $jar);
echo "1) logowanie admina            : HTTP {$r['status']}\n";

// 2. Panel uzytkownikow
$r = req('GET', $base . '/admin/uzytkownicy.php', null, true, $jar);
echo '2) panel uzytkownikow          : HTTP ' . $r['status']
    . ' | formularz="Wyślij kod rejestracyjny"=' . (str_contains($r['html'], 'Wyślij kod rejestracyjny') ? 'tak' : 'NIE')
    . ' | stary formularz dodawania=' . (str_contains($r['html'], 'Dodaj użytkownika') ? 'JEST (blad!)' : 'brak')
    . "\n";
print_r(blad($r['html']));

// 3. Wyslanie kodu rejestracyjnego
$t = csrf($r['html']);
$r = req('POST', $base . '/admin/uzytkownicy.php', [
    'csrf' => $t, 'akcja' => 'zaproszenie', 'email' => $email, 'grupa' => 'materialy',
], true, $jar);
preg_match('/Kod rejestracyjny ([A-Z0-9]{6})/', $r['html'], $m);
$kod = $m[1] ?? '';
echo "3) wyslanie kodu               : HTTP {$r['status']} | kod={$kod}\n";
print_r(blad($r['html']));

// 4. Rejestracja odbiorcy kodu
@unlink($jar);
$r = req('GET', $base . '/rejestracja.php', null, true, $jar);
$t = csrf($r['html']);
$r = req('POST', $base . '/rejestracja.php', [
    'csrf' => $t, 'email' => $email, 'kod' => $kod,
    'imie' => 'Anna Odbiorca', 'haslo' => 'Rejestracja123', 'haslo2' => 'Rejestracja123',
], true, $jar);
echo '4) rejestracja                 : HTTP ' . $r['status']
    . ' | komunikat="' . (str_contains($r['html'], 'Konto utworzone') ? 'Konto utworzone' : 'BRAK')
    . '"' . "\n";
print_r(blad($r['html']));

// 5. Logowanie nowego uzytkownika
@unlink($jar);
$r = req('GET', $base . '/login.php', null, true, $jar);
$t = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $t, 'email' => $email, 'haslo' => 'Rejestracja123',
], true, $jar);
echo '5) logowanie nowego konta      : HTTP ' . $r['status']
    . ' | panel="Materiały"=' . (str_contains($r['html'], 'Materiały') ? 'tak' : 'nie')
    . ' | brak menu Uzytkownicy=' . (str_contains($r['html'], 'admin/uzytkownicy.php') ? 'NIE (blad!)' : 'tak')
    . "\n";
print_r(blad($r['html']));

// 6. Brak dostepu do sekcji Uzytkownicy dla grupy materialow
$r = req('GET', $base . '/admin/uzytkownicy.php', null, true, $jar);
echo '6) sekcja Uzytkownicy (materialy): HTTP ' . $r['status'] . " (oczekiwane 403)\n";

@unlink($jar);

// 7. Sprzatanie konta testowego
require_once __DIR__ . '/../db.php';
db()->prepare('DELETE FROM users WHERE email = :e')->execute([':e' => $email]);
db()->prepare('DELETE FROM zaproszenia WHERE email = :e')->execute([':e' => $email]);
echo "7) sprzatanie konta testowego  : {$email}\n";

@unlink($jar);
