<?php
// Kontrola przepływu: konto wymagające kodu od administratora.
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

// 1) Logowanie BEZ kodu od administratora – powinno zostać odrzucone
@unlink($jar);
$r = req('GET', $base . '/login.php', null, true, $jar);
$t = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $t, 'email' => $email, 'haslo' => 'Pracownik123!',
], true, $jar);
echo '1) logowanie bez kodu          : ' . (str_contains($r['html'], 'wymaga kodu weryfikacyjnego') ? 'odrzucone (OK)' : 'PRZESZLO - BLAD!') . "\n";
echo '   panel dostepny bez kodu     : ' . (str_contains($r['html'], 'Panel startowy') ? 'TAK - BLAD!' : 'nie (OK)') . "\n";

// 2) Administrator wysyła kod
@unlink($jar);
$r = req('GET', $base . '/login.php', null, true, $jar);
$t = csrf($r['html']);
$r = req('POST', $base . '/login.php', ['csrf' => $t, 'email' => 'admin@mojafirma.pl', 'haslo' => 'TestNauka2026!'], true, $jar);
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
echo '   wysłanie kodu adminowi      : HTTP ' . $r['status'] . ' | kod=' . $kod . "\n";

// 3) Logowanie z kodem -> przekierowanie na weryfikację
@unlink($jar);
$r = req('GET', $base . '/login.php', null, true, $jar);
$t = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $t, 'email' => $email, 'haslo' => 'Pracownik123!',
], false, $jar);
echo '3) logowanie z kodem           : HTTP ' . $r['status']
    . ' | przekierowanie na weryfikacje=' . (str_contains((string) $r['head'], 'weryfikacja.php') ? 'tak (OK)' : 'NIE - BLAD!')
    . "\n";

$r = req('GET', $base . '/weryfikacja.php', null, true, $jar);
$t = csrf($r['html']);
$r = req('POST', $base . '/weryfikacja.php', ['csrf' => $t, 'kod' => $kod], false, $jar);
echo '   weryfikacja kodu            : HTTP ' . $r['status'] . "\n";

$r = req('GET', $base . '/admin/index.php', null, true, $jar);
echo '4) panel po weryfikacji        : HTTP ' . $r['status']
    . ' | zalogowany=' . (str_contains($r['html'], 'Panel startowy') ? 'tak (OK)' : 'nie - BLAD!')
    . ' | brak samodzielnego kodu=' . (str_contains($r['html'], 'Generuj i wyślij kod na e-mail') ? 'NIE - BLAD!' : 'tak (OK)')
    . "\n";

// 5) Drugie logowanie bez nowego kodu -> odrzucenie (kod jest jednorazowy)
@unlink($jar);
$r = req('GET', $base . '/login.php', null, true, $jar);
$t = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $t, 'email' => $email, 'haslo' => 'Pracownik123!',
], true, $jar);
echo '5) kolejne logowanie bez kodu  : ' . (str_contains($r['html'], 'wymaga kodu weryfikacyjnego') ? 'odrzucone (OK)' : 'PRZESZLO - BLAD!') . "\n";

@unlink($jar);
