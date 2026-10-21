<?php
// Kontrola przepływu: logowanie użytkownika (grupa materiałów) samym kodem z e-maila.
require __DIR__ . '/../db.php';

$base  = rtrim($argv[1] ?? 'http://127.0.0.1:8099', '/');
$jar   = sys_get_temp_dir() . '/testnauka_kod_' . getmypid() . '.txt';
$email = 'pracownik@firma.pl';

$stmt = db()->prepare('SELECT id, wymaga_kod FROM users WHERE email = :e');
$stmt->execute([':e' => $email]);
$uzytkownik = $stmt->fetch();
$userId     = (int) ($uzytkownik['id'] ?? 0);

echo "Konto {$email} -> id={$userId}, wymaga_kod=" . (int) ($uzytkownik['wymaga_kod'] ?? 0) . "\n\n";

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

    return ['status' => $code, 'html' => (string) (explode("\r\n\r\n", $body, 2)[1] ?? $body), 'head' => explode("\r\n\r\n", $body, 2)[0] ?? ''];
}

function csrf(string $html): string
{
    return (string) (preg_match('/name="csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '');
}

// 1) Tryb administratora (e-mail + hasło) dla konta użytkownika – powinno zostać odrzucone
@unlink($jar);
$r = req('GET', $base . '/login.php', null, true, $jar);
$t = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $t, 'tryb' => 'admin', 'email' => $email, 'haslo' => 'Pracownik123!',
], true, $jar);
echo '1) tryb administratora          : ' . (str_contains($r['html'], 'nie ma uprawnień administratora') ? 'odrzucone (OK)' : 'PRZESZLO - BLAD!') . "\n";
echo '   panel dostepny bez kodu     : ' . (str_contains($r['html'], 'Panel startowy') ? 'TAK - BLAD!' : 'nie (OK)') . "\n";

// 2) Administrator wysyła kod logowania
@unlink($jar);
$r = req('GET', $base . '/login.php', null, true, $jar);
$t = csrf($r['html']);
$r = req('POST', $base . '/login.php', ['csrf' => $t, 'tryb' => 'admin', 'email' => 'admin@mojafirma.pl', 'haslo' => 'TestNauka2026!'], true, $jar);
$r = req('GET', $base . '/admin/uzytkownicy.php', null, true, $jar);
echo '2) panel admina                : HTTP ' . $r['status']
    . ' | badge "wymaga kodu"=' . (str_contains($r['html'], 'wymaga kodu') ? 'tak' : 'nie')
    . "\n";

$t = csrf($r['html']);
$r = req('POST', $base . '/admin/uzytkownicy.php', [
    'csrf' => $t, 'akcja' => 'kod', 'user_id' => (string) $userId, 'cel' => 'logowanie',
], true, $jar);
preg_match('/Kod ([A-Z0-9]{6}) wysłany/', $r['html'], $m);
$kod = $m[1] ?? '';
echo '   wysłanie kodu użytkownikowi : HTTP ' . $r['status'] . ' | kod=' . $kod . "\n";

// 3) Logowanie użytkownika samym kodem (zakładka „Użytkownik”)
@unlink($jar);
$r = req('GET', $base . '/login.php?tryb=uzytkownik', null, true, $jar);
$t = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $t, 'tryb' => 'uzytkownik', 'kod' => $kod,
], false, $jar);
echo '3) logowanie kodem             : HTTP ' . $r['status']
    . ' | przekierowanie do panelu=' . (str_contains((string) $r['head'], 'admin/index.php') ? 'tak (OK)' : 'NIE - BLAD!')
    . "\n";

$r = req('GET', $base . '/admin/index.php', null, true, $jar);
echo '4) panel po zalogowaniu        : HTTP ' . $r['status']
    . ' | zalogowany=' . (str_contains($r['html'], 'Panel startowy') ? 'tak (OK)' : 'nie - BLAD!')
    . ' | brak samodzielnego kodu=' . (str_contains($r['html'], 'Generuj i wyślij kod na e-mail') ? 'NIE - BLAD!' : 'tak (OK)')
    . "\n";

$r = req('GET', $base . '/admin/uzytkownicy.php', null, true, $jar);
echo '   brak dostepu do Uzytkownikow : HTTP ' . $r['status'] . ' (oczekiwane 403)' . "\n";

// 5) Ponowne użycie tego samego kodu -> odrzucenie (kod jest jednorazowy)
@unlink($jar);
$r = req('GET', $base . '/login.php?tryb=uzytkownik', null, true, $jar);
$t = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $t, 'tryb' => 'uzytkownik', 'kod' => $kod,
], true, $jar);
echo '5) ponowne uzycie kodu        : ' . (str_contains($r['html'], 'Kod jest nieprawidłowy lub wygasł') ? 'odrzucone (OK)' : 'PRZESZLO - BLAD!') . "\n";

@unlink($jar);
