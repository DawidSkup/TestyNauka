<?php
declare(strict_types=1);

/**
 * Testy integracyjne panelu TestNauka.
 * Uruchomienie:  php tools/testy.php [adres-bazy]  (domyślnie http://127.0.0.1:8099)
 */

$base = rtrim($argv[1] ?? 'http://127.0.0.1:8099', '/');
$jar  = sys_get_temp_dir() . '/testnauka_testy_' . getmypid() . '.txt';

$wynik = ['ok' => 0, 'blad' => 0];

/* ------------------------------------------------------------------ */
/* Reset stanu – testy zakładają czystą bazę i pusty katalog uploadów */
/* ------------------------------------------------------------------ */
foreach (glob(dirname(__DIR__) . '/data/app.sqlite*') ?: [] as $plik) {
    @unlink($plik);
}
foreach (glob(dirname(__DIR__) . '/data/uploads/*') ?: [] as $plik) {
    @unlink($plik);
}

/** Wykonuje żądanie HTTP z obsługą ciasteczek. */
function req(string $method, string $url, array $post = null, array $files = null, bool $follow = true): array
{
    global $jar;

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
        if ($files !== null) {
            $fields = $post ?? [];
            foreach ($files as $name => $file) {
                $fields[$name] = new CURLFile($file['path'], $file['type'], $file['name']);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post ?? []));
        }
    }

    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $body     = (string) $response;
    curl_close($ch);

    $parts = explode("\r\n\r\n", $body, 2);
    $head  = $parts[0] ?? '';
    $html  = $parts[1] ?? $body;

    return ['status' => $status, 'html' => $html, 'head' => $head];
}

/** Wyciąga token CSRF z HTML. */
function csrf(string $html): string
{
    return (string) (preg_match('/name="csrf" value="([^"]+)"/', $html, $m) ? $m[1] : '');
}

function check(string $nazwa, bool $ok, string $info = ''): void
{
    global $wynik;
    $wynik[$ok ? 'ok' : 'blad']++;
    printf("%-46s %s %s\n", $nazwa, $ok ? 'OK  ' : 'BLAD', $info);
}

/**
 * Odczyt z bazy na świeżym połączeniu.
 * Długotrwałe połączenie PDO po zapisie „przypina” snapshot WAL i nie widzi
 * zmian wykonanych przez proces serwera – dlatego czytamy zawsze świeżo.
 */
function db_read(string $sql): array
{
    $pdo = new PDO('sqlite:' . dirname(__DIR__) . '/data/app.sqlite', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo->query($sql)->fetchAll();
}

/** Pierwsza kolumna pierwszego wiersza. */
function db_scalar(string $sql)
{
    $rows = db_read($sql);
    return $rows ? array_values($rows[0])[0] : null;
}

/**
 * Wykrywa komunikaty PHP (Warning/Notice/Deprecated/Fatal error) w treści strony.
 * Zwraca pierwszy znaleziony komunikat albo pusty string.
 */
function blad_php(string $html): string
{
    if (preg_match('/<(?:b|strong)>(Warning|Notice|Deprecated|Fatal error|Parse error)<\/b>|<b>(Warning|Notice|Deprecated|Fatal error|Parse error):/', $html, $m)) {
        $p = strpos($html, $m[0]);
        return trim(preg_replace('/\s+/', ' ', substr($html, (int) $p, 220)) ?? '');
    }

    if (preg_match('/Undefined (?:array key|variable|index|offset)/', $html, $m)) {
        $p = strpos($html, $m[0]);
        return trim(preg_replace('/\s+/', ' ', substr($html, (int) $p, 220)) ?? '');
    }

    return '';
}

/* ------------------------------------------------------------------ */
/* 1. Logowanie                                                        */
/* ------------------------------------------------------------------ */
$r = req('GET', $base . '/login.php');
$token = csrf($r['html']);
check('GET /login.php', $r['status'] === 200 && $token !== '');
check('Strona logowania - wybor trybu', str_contains($r['html'], 'login-tabs'));

$r = req('POST', $base . '/login.php', ['csrf' => $token, 'email' => 'admin@firma.pl', 'haslo' => 'zle']);
check('Logowanie - zle haslo', str_contains($r['html'], 'Nieprawidłowy e-mail lub hasło'));

$r = req('GET', $base . '/login.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', ['csrf' => $token, 'email' => 'admin@firma.pl', 'haslo' => 'Admin123!'], null, false);
check('Logowanie - grupa firma', $r['status'] === 302, '(HTTP ' . $r['status'] . ')');

$r = req('GET', $base . '/admin/index.php');
check('Panel startowy (grupa firma)', $r['status'] === 200 && str_contains($r['html'], 'Panel startowy'));
$bladPhp = blad_php($r['html']);
check('Panel startowy: brak bledow PHP', $bladPhp === '', $bladPhp);

$token = csrf($r['html']);
$r = req('POST', $base . '/admin/index.php', ['csrf' => $token, 'akcja' => 'moj_kod']);
check(
    'Dashboard - przycisk "wyślij kod na mój e-mail"',
    str_contains($r['html'], 'został wysłany na adres')
);

/* Panel startowy: kod na wskazany adres (administrator albo użytkownik) */
$r = req('GET', $base . '/admin/index.php');
$token = csrf($r['html']);
check('Panel startowy - formularz kodu na e-mail', str_contains($r['html'], 'Wyślij kod na e-mail'));

$r = req('POST', $base . '/admin/index.php', [
    'csrf' => $token, 'akcja' => 'kod_email', 'email' => 'admin@firma.pl',
]);
check(
    'Panel startowy - kod wyslany na wskazany e-mail',
    str_contains($r['html'], 'wysłany na adres')
);

$kodAdmin = (string) db_scalar(
    "SELECT kod FROM kody WHERE user_id = 1 AND cel = 'logowanie' AND uzyty_at IS NULL ORDER BY id DESC LIMIT 1"
);
check('Panel startowy - kod dla konta administratora', (bool) preg_match('/^[A-Z0-9]{6}$/', $kodAdmin), "($kodAdmin)");

/* Kod z panelu loguje konto administratora w zakladce "Uzytkownik" */
@unlink($jar);
$r = req('GET', $base . '/login.php?tryb=uzytkownik');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $token, 'tryb' => 'uzytkownik', 'kod' => $kodAdmin,
], null, false);
check(
    'Administrator loguje sie kodem z e-maila',
    $r['status'] === 302 && !str_contains((string) $r['head'], 'login.php'),
    '(HTTP ' . $r['status'] . ')'
);

$r = req('GET', $base . '/admin/index.php');
check(
    'Panel administratora po zalogowaniu kodem',
    $r['status'] === 200 && str_contains($r['html'], 'Zarządzaj użytkownikami')
);

$r = req('GET', $base . '/admin/uzytkownicy.php');
check('Sekcja Użytkownicy dla grupy firma', $r['status'] === 200 && str_contains($r['html'], 'Generuj i wyślij kod'));
check(
    'Lista kont podzielona na Administratorow i Uzytkownikow',
    str_contains($r['html'], 'Administratorzy <span class="muted">(grupa firmy')
        && str_contains($r['html'], 'Użytkownicy <span class="muted">(grupa materiałów')
);
$bladPhp = blad_php($r['html']);
check('Panel: brak bledow PHP w sekcji Uzytkownicy', $bladPhp === '', $bladPhp);

/* ------------------------------------------------------------------ */
/* 2. Generowanie i wysyłka kodu                                      */
/* ------------------------------------------------------------------ */
$token = csrf($r['html']);
$r = req('POST', $base . '/api/kod.php', ['csrf' => $token, 'user_id' => '2', 'cel' => 'logowanie']);
$json = json_decode($r['html'], true);
$kod  = (string) ($json['kod'] ?? '');
check(
    'API /api/kod.php zwraca kod 6 znakow',
    ($json['ok'] ?? false) === true && (bool) preg_match('/^[A-Z0-9]{6}$/', $kod),
    "($kod)"
);
check(
    'API - kod zawiera cyfre i wielkie litery',
    (bool) preg_match('/^(?=.*[A-Z])(?=.*[0-9])[A-Z0-9]{6}$/', $kod),
    "($kod)"
);
check('API - e-mail wyslany', ($json['wyslany'] ?? false) === true);

$r = req('POST', $base . '/api/kod.php', ['user_id' => '2']);
check('API - brak tokenu CSRF = 400', $r['status'] === 400, '(HTTP ' . $r['status'] . ')');

$r = req('POST', $base . '/api/kod.php', ['csrf' => $token, 'user_id' => '2']);
$json2 = json_decode($r['html'], true);
check(
    'API - poprzedni kod uniewazniony (nowy kod)',
    ($json2['kod'] ?? '') !== '' && ($json2['kod'] ?? '') !== $kod,
    '(' . ($json2['kod'] ?? '') . ')'
);

/* ------------------------------------------------------------------ */
/* 2b. Logowanie dwoma trybami: administrator (e-mail + haslo)         */
/*     oraz uzytkownik (tylko kod z e-maila)                           */
/* ------------------------------------------------------------------ */
$pdoK = new PDO('sqlite:' . dirname(__DIR__) . '/data/app.sqlite');
$pdoK->prepare(
    'INSERT INTO users (imie, email, haslo_hash, grupa, aktywny, aktywowany, wymaga_kod, created_at, updated_at)
     VALUES (:imie, :email, :haslo, :grupa, 1, 1, 1, :now, :now)'
)->execute([
    ':imie'  => 'Kodowy User',
    ':email' => 'kodowy@firma.pl',
    ':haslo' => password_hash('Kodowy123!', PASSWORD_DEFAULT),
    ':grupa' => 'materialy',
    ':now'   => date('Y-m-d H:i:s'),
]);
$kodowyId = (int) $pdoK->lastInsertId();
$pdoK = null;
check('Konto z flaga wymaga_kod utworzone', $kodowyId > 0, "(id=$kodowyId)");

/* Konto grupy materialow nie loguje sie e-mailem i haslem */
@unlink($jar);
$r = req('GET', $base . '/login.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $token, 'tryb' => 'admin', 'email' => 'kodowy@firma.pl', 'haslo' => 'Kodowy123!',
]);
check(
    'Tryb administratora - konto materialow odrzucone',
    str_contains($r['html'], 'nie ma uprawnień administratora')
);

$r = req('GET', $base . '/admin/index.php');
check('Brak dostepu do panelu bez kodu', !str_contains($r['html'], 'Panel startowy'));

/* Administrator wysyla kod logowania */
@unlink($jar);
$pdoK = new PDO('sqlite:' . dirname(__DIR__) . '/data/app.sqlite');
$pdoK->exec("UPDATE kody SET uzyty_at = datetime('now') WHERE cel = 'logowanie' AND uzyty_at IS NULL AND user_id = 1");
$pdoK = null;

$r = req('GET', $base . '/login.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $token, 'tryb' => 'admin', 'email' => 'admin@firma.pl', 'haslo' => 'Admin123!',
], null, false);
check(
    'Logowanie administratora (tryb admin)',
    $r['status'] === 302 && !str_contains((string) $r['head'], 'weryfikacja.php')
);

$r = req('GET', $base . '/admin/uzytkownicy.php');
check('Panel: znacznik "wymaga kodu"', str_contains($r['html'], 'wymaga kodu'));

$token = csrf($r['html']);
$r = req('POST', $base . '/admin/uzytkownicy.php', [
    'csrf' => $token, 'akcja' => 'kod', 'user_id' => (string) $kodowyId, 'cel' => 'logowanie',
]);
check('Wyslanie kodu dla konta z wymogiem', str_contains($r['html'], 'wysłany na'));

$kodLog = (string) db_scalar(
    "SELECT kod FROM kody WHERE user_id = $kodowyId AND cel = 'logowanie' AND uzyty_at IS NULL ORDER BY id DESC LIMIT 1"
);
check('Kod logowania zapisany', (bool) preg_match('/^[A-Z0-9]{6}$/', $kodLog), "($kodLog)");

/* Logowanie samym kodem z e-maila (tryb uzytkownik) */
@unlink($jar);
$r = req('GET', $base . '/login.php?tryb=uzytkownik');
$token = csrf($r['html']);
check(
    'Zakladka "Uzytkownik" na stronie logowania',
    $r['status'] === 200 && str_contains($r['html'], 'Zaloguj się kodem')
);

$r = req('POST', $base . '/login.php', ['csrf' => $token, 'tryb' => 'uzytkownik', 'kod' => 'ZLYKOD']);
check('Tryb uzytkownika - bledny kod odrzucony', str_contains($r['html'], 'Kod jest nieprawidłowy lub wygasł'));

$r = req('GET', $base . '/login.php?tryb=uzytkownik');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $token, 'tryb' => 'uzytkownik', 'kod' => $kodLog,
], null, false);
check(
    'Tryb uzytkownika - logowanie kodem',
    $r['status'] === 302 && !str_contains((string) $r['head'], 'login.php'),
    '(HTTP ' . $r['status'] . ')'
);

$r = req('GET', $base . '/admin/index.php');
check('Panel dostepny po zalogowaniu kodem', $r['status'] === 200 && str_contains($r['html'], 'Panel startowy'));
check(
    'Brak samodzielnego generowania kodu',
    !str_contains($r['html'], 'Generuj i wyślij kod na e-mail')
);

/* Grupa materialow nie moze wyslac kodu na inny adres */
$r = req('POST', $base . '/admin/index.php', [
    'csrf' => $token, 'akcja' => 'kod_email', 'email' => 'admin@firma.pl',
]);
check(
    'Grupa materialow nie wysle kodu na inny e-mail',
    str_contains($r['html'], 'Tylko grupa firmy'),
    'HTTP ' . $r['status'] . ' | ' . mb_substr(preg_replace('/\s+/', ' ', strip_tags($r['html'])) ?? '', 0, 300)
);

/* Kod użytkownika jest stały – działa wielokrotnie, nie wymaga aktywacji */
@unlink($jar);
$r = req('GET', $base . '/login.php?tryb=uzytkownik');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', ['csrf' => $token, 'tryb' => 'uzytkownik', 'kod' => $kodLog], null, false);
check(
    'Ten sam kod użytkownika działa wielokrotnie',
    $r['status'] === 302 && !str_contains((string) $r['head'], 'login.php'),
    '(HTTP ' . $r['status'] . ')'
);

@unlink($jar);
$pdoK = new PDO('sqlite:' . dirname(__DIR__) . '/data/app.sqlite');
$pdoK->prepare('UPDATE users SET aktywowany = 0 WHERE id = :id')->execute([':id' => $kodowyId]);
$pdoK = null;

$r = req('GET', $base . '/login.php?tryb=uzytkownik');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', ['csrf' => $token, 'tryb' => 'uzytkownik', 'kod' => $kodLog], null, false);
check(
    'Użytkownik nie musi aktywować konta – logowanie kodem bez aktywacji',
    $r['status'] === 302 && !str_contains((string) $r['head'], 'login.php'),
    '(HTTP ' . $r['status'] . ')'
);

$pdoK = new PDO('sqlite:' . dirname(__DIR__) . '/data/app.sqlite');
$pdoK->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $kodowyId]);
$pdoK = null;

/* ------------------------------------------------------------------ */
/* 2c. Konto administratora z wymogiem kodu (2FA po zalogowaniu)       */
/* ------------------------------------------------------------------ */
$pdoK = new PDO('sqlite:' . dirname(__DIR__) . '/data/app.sqlite');
$pdoK->prepare(
    'INSERT INTO users (imie, email, haslo_hash, grupa, aktywny, aktywowany, wymaga_kod, created_at, updated_at)
     VALUES (:imie, :email, :haslo, :grupa, 1, 1, 1, :now, :now)'
)->execute([
    ':imie'  => 'Kodowy Admin',
    ':email' => 'kodowyadmin@firma.pl',
    ':haslo' => password_hash('KodowyAdmin123!', PASSWORD_DEFAULT),
    ':grupa' => 'firma',
    ':now'   => date('Y-m-d H:i:s'),
]);
$kodowyAdminId = (int) $pdoK->lastInsertId();
$pdoK = null;
check('Konto admina z flaga wymaga_kod utworzone', $kodowyAdminId > 0, "(id=$kodowyAdminId)");

/* Logowanie bez kodu od administratora jest odrzucane */
@unlink($jar);
$r = req('GET', $base . '/login.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $token, 'tryb' => 'admin', 'email' => 'kodowyadmin@firma.pl', 'haslo' => 'KodowyAdmin123!',
]);
check('Logowanie admina bez kodu odrzucone', str_contains($r['html'], 'wymaga kodu weryfikacyjnego'));

/* Administrator (grupa firma) wysyla kod koncie wymagajacemu kodu */
@unlink($jar);
$r = req('GET', $base . '/login.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $token, 'tryb' => 'admin', 'email' => 'admin@firma.pl', 'haslo' => 'Admin123!',
], null, false);

$r = req('GET', $base . '/admin/uzytkownicy.php');
$token = csrf($r['html']);
check('Panel: znacznik "wymaga kodu"', str_contains($r['html'], 'wymaga kodu'));

$r = req('POST', $base . '/admin/uzytkownicy.php', [
    'csrf' => $token, 'akcja' => 'kod', 'user_id' => (string) $kodowyAdminId, 'cel' => 'logowanie',
]);
check('Wyslanie kodu koncie admina', str_contains($r['html'], 'wysłany na'));

$kodAdm = (string) db_scalar(
    "SELECT kod FROM kody WHERE user_id = $kodowyAdminId AND cel = 'logowanie' AND uzyty_at IS NULL ORDER BY id DESC LIMIT 1"
);
check('Kod dla konta admina zapisany', (bool) preg_match('/^[A-Z0-9]{6}$/', $kodAdm), "($kodAdm)");

/* Logowanie z kodem -> przekierowanie na weryfikacje */
@unlink($jar);
$r = req('GET', $base . '/login.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $token, 'tryb' => 'admin', 'email' => 'kodowyadmin@firma.pl', 'haslo' => 'KodowyAdmin123!',
], null, false);
check(
    'Logowanie admina wymaga kodu (przekierowanie na weryfikacje)',
    $r['status'] === 302 && str_contains((string) $r['head'], 'weryfikacja.php')
);

$r = req('GET', $base . '/weryfikacja.php');
$token = csrf($r['html']);
check('Ekran weryfikacji kodu', $r['status'] === 200 && str_contains($r['html'], 'input-code'));

$r = req('POST', $base . '/weryfikacja.php', ['csrf' => $token, 'kod' => 'ZLYKOD']);
check('Weryfikacja - bledny kod', str_contains($r['html'], 'nieprawidłowy lub wygasł'));

$r = req('GET', $base . '/weryfikacja.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/weryfikacja.php', ['csrf' => $token, 'kod' => $kodAdm], null, false);
check('Weryfikacja - poprawny kod', $r['status'] === 302);

$r = req('GET', $base . '/admin/index.php');
check('Panel dostepny po weryfikacji kodu', $r['status'] === 200 && str_contains($r['html'], 'Panel startowy'));

$pdoK = new PDO('sqlite:' . dirname(__DIR__) . '/data/app.sqlite');
$pdoK->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $kodowyAdminId]);
$pdoK = null;

/* ------------------------------------------------------------------ */
/* 3. Uprawnienia grupy materialow (logowanie samym kodem)              */
/* ------------------------------------------------------------------ */
@unlink($jar);
$r = req('GET', $base . '/login.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $token, 'tryb' => 'admin', 'email' => 'marketing@firma.pl', 'haslo' => 'Marketing123!',
]);
check(
    'Tryb administratora - konto materialow odrzucone',
    str_contains($r['html'], 'nie ma uprawnień administratora')
);

$kodMaterialy = (string) db_scalar(
    "SELECT kod FROM kody WHERE user_id = 2 AND cel = 'logowanie' AND uzyty_at IS NULL ORDER BY id DESC LIMIT 1"
);
check('Kod logowania dla konta materialow istnieje', (bool) preg_match('/^[A-Z0-9]{6}$/', $kodMaterialy), "($kodMaterialy)");

@unlink($jar);
$r = req('GET', $base . '/login.php?tryb=uzytkownik');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $token, 'tryb' => 'uzytkownik', 'kod' => $kodMaterialy,
], null, false);
check('Logowanie grupy materialy kodem', $r['status'] === 302, '(HTTP ' . $r['status'] . ')');

$r = req('GET', $base . '/admin/uzytkownicy.php');
check('Grupa materialy bez dostepu do Uzytkownikow = 403', $r['status'] === 403, '(HTTP ' . $r['status'] . ')');

$r = req('GET', $base . '/admin/materialy.php');
check('Grupa materialy ma dostep do Materialow', $r['status'] === 200);

/* ------------------------------------------------------------------ */
/* 4. Slajdy: dodanie, walidacja, podglad                              */
/* ------------------------------------------------------------------ */
$png = sys_get_temp_dir() . '/testnauka_test.png';
file_put_contents($png, base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
));

$r = req('GET', $base . '/admin/slajd_form.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/admin/slajd_form.php', [
    'csrf'     => $token,
    'tytul'    => 'Slajd testowy automatyczny',
    'tresc'    => 'Tresc slajdu',
    'uklad'    => 'tekst-obraz',
    'status'   => 'opublikowany',
    'przycisk' => 'Zobacz',
    'link'     => 'https://example.com',
], ['obraz' => ['path' => $png, 'type' => 'image/png', 'name' => 'test.png']]);
check('Dodanie slajdu z obrazem', $r['status'] === 302 || str_contains($r['html'], 'dodany'));

$r = req('GET', $base . '/admin/materialy.php');
check('Slajd widoczny na liscie', str_contains($r['html'], 'Slajd testowy automatyczny'));

$r = req('GET', $base . '/admin/podglad.php');
check('Slajd widoczny w podgladzie', str_contains($r['html'], 'Slajd testowy automatyczny'));

$r = req('GET', $base . '/admin/slajd_form.php');
$token = csrf($r['html']);
$txt   = sys_get_temp_dir() . '/testnauka_test.txt';
file_put_contents($txt, 'nie obraz');
$r = req('POST', $base . '/admin/slajd_form.php', [
    'csrf' => $token, 'tytul' => 'Zly typ',
], ['obraz' => ['path' => $txt, 'type' => 'text/plain', 'name' => 'test.txt']]);
check('Upload niedozwolonego typu odrzucony', str_contains($r['html'], 'Dozwolone są wyłącznie'));

$big = sys_get_temp_dir() . '/testnauka_big.png';
file_put_contents($big, str_repeat('0', 6 * 1024 * 1024));
$r = req('GET', $base . '/admin/slajd_form.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/admin/slajd_form.php', [
    'csrf' => $token, 'tytul' => 'Duzy plik',
], ['obraz' => ['path' => $big, 'type' => 'image/png', 'name' => 'big.png']]);
check('Upload pliku > 5 MB odrzucony', str_contains($r['html'], 'Obraz jest za duży'));

$r = req('GET', $base . '/admin/slajd_form.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/admin/slajd_form.php', ['csrf' => $token, 'tytul' => '   ']);
check('Pusty tytul odrzucony', str_contains($r['html'], 'Tytuł slajdu jest wymagany'));

$r = req('POST', $base . '/admin/slajd_form.php', ['tytul' => 'Bez tokenu']);
check('Formularz bez tokenu CSRF odrzucony', str_contains($r['html'], 'Nieprawidłowy token CSRF'));

/* ------------------------------------------------------------------ */
/* 5. Wygasanie kodu                                                  */
/* ------------------------------------------------------------------ */
$pdo = new PDO('sqlite:' . dirname(__DIR__) . '/data/app.sqlite');
$pdo->exec("UPDATE kody SET wygasa_at = '2000-01-01 00:00:00' WHERE uzyty_at IS NULL");

/* Wygasly kod nie pozwala zalogowac sie kodem */
@unlink($jar);
$r = req('GET', $base . '/login.php?tryb=uzytkownik');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $token, 'tryb' => 'uzytkownik', 'kod' => $kodMaterialy,
]);
check('Wygasly kod nie pozwala zalogowac sie', str_contains($r['html'], 'Kod jest nieprawidłowy lub wygasł'));

/* Konto administratora loguje sie bez kodu */
@unlink($jar);
$r = req('GET', $base . '/login.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $token, 'tryb' => 'admin', 'email' => 'admin@firma.pl', 'haslo' => 'Admin123!',
], null, false);
check(
    'Wygasly kod nie blokuje logowania administratora',
    $r['status'] === 302 && !str_contains((string) $r['head'], 'weryfikacja.php')
);

/* ------------------------------------------------------------------ */
/* 6. Mailpit                                                         */
/* ------------------------------------------------------------------ */
$mp = @file_get_contents('http://127.0.0.1:8025/api/v1/messages?limit=5');
$mp = $mp ? json_decode($mp, true) : null;
check('Mailpit odebral wiadomosci', is_array($mp) && ($mp['total'] ?? 0) > 0, '(' . ($mp['total'] ?? 0) . ' szt.)');

if (is_array($mp) && !empty($mp['messages'])) {
    $id   = $mp['messages'][0]['ID'];
    $full = json_decode((string) @file_get_contents('http://127.0.0.1:8025/api/v1/message/' . $id), true);
    $tekst = preg_replace('/\s+/u', ' ', strip_tags((string) ($full['HTML'] ?? '')));

    $ostatni = (string) db_scalar('SELECT kod FROM kody ORDER BY id DESC LIMIT 1');
    check(
        'E-mail zawiera wygenerowany kod',
        is_string($ostatni) && $ostatni !== '' && str_contains($tekst, (string) $ostatni),
        '(' . (string) $ostatni . ')'
    );
    check('E-mail poprawnie zakodowany UTF-8', str_contains((string) ($full['Subject'] ?? ''), 'Kod weryfikacyjny'));
}

/* ------------------------------------------------------------------ */
/* 7. Panel firmy: kod rejestracyjny -> samodzielna rejestracja,       */
/*    operacje na slajdach                                              */
/* ------------------------------------------------------------------ */
@unlink($jar);
$r = req('GET', $base . '/login.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', ['csrf' => $token, 'email' => 'admin@firma.pl', 'haslo' => 'Admin123!'], null, false);

$r = req('GET', $base . '/admin/uzytkownicy.php');
check('Panel: formularz kodu rejestracyjnego', str_contains($r['html'], 'Wyślij kod rejestracyjny'));
check('Panel: brak tworzenia konta przez admina', !str_contains($r['html'], 'Dodaj użytkownika'));

$token = csrf($r['html']);
$r = req('POST', $base . '/admin/uzytkownicy.php', [
    'csrf'  => $token,
    'akcja' => 'zaproszenie',
    'email' => 'nowy.pracownik@firma.pl',
    'grupa' => 'materialy',
]);
check('Wyslanie kodu rejestracyjnego', str_contains($r['html'], 'Kod rejestracyjny'));
check('Zaproszenie widoczne na liscie', str_contains($r['html'], 'oczekuje'));

$kodRej = (string) db_scalar(
    "SELECT kod FROM zaproszenia WHERE email = 'nowy.pracownik@firma.pl' AND uzyty_at IS NULL ORDER BY id DESC LIMIT 1"
);
check('Kod rejestracyjny zapisany w bazie', (bool) preg_match('/^[A-Z0-9]{6}$/', $kodRej), "($kodRej)");
check(
    'Administrator nie utworzyl konta uzytkownika',
    (int) db_scalar("SELECT COUNT(*) FROM users WHERE email = 'nowy.pracownik@firma.pl'") === 0
);

/* Odbiorca kodu rejestruje sie samodzielnie */
@unlink($jar);
$r = req('GET', $base . '/rejestracja.php');
$token = csrf($r['html']);
check('Strona rejestracji dostepna', $r['status'] === 200 && str_contains($r['html'], 'Załóż konto'));

$r = req('POST', $base . '/rejestracja.php', [
    'csrf'   => $token,
    'email'  => 'nowy.pracownik@firma.pl',
    'kod'    => 'ZLYKOD',
    'imie'   => 'Nowy Pracownik',
    'haslo'  => 'NoweHaslo123',
    'haslo2' => 'NoweHaslo123',
]);
check('Rejestracja - bledny kod odrzucony', str_contains($r['html'], 'Kod jest nieprawidłowy'));

$r = req('GET', $base . '/rejestracja.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/rejestracja.php', [
    'csrf'   => $token,
    'email'  => 'nowy.pracownik@firma.pl',
    'kod'    => $kodRej,
    'imie'   => 'Nowy Pracownik',
    'haslo'  => 'NoweHaslo123',
    'haslo2' => 'NoweHaslo123',
]);
check('Rejestracja kodem rejestracyjnym', str_contains($r['html'], 'Konto utworzone'));

$nowy = db_read(
    "SELECT id, grupa, aktywny, aktywowany, haslo_hash FROM users WHERE email = 'nowy.pracownik@firma.pl'"
)[0] ?? [];
$newId = (int) ($nowy['id'] ?? 0);
check('Konto utworzone przez uzytkownika', $newId > 0, "(id=$newId)");
check('Konto w grupie materialow', (string) ($nowy['grupa'] ?? '') === 'materialy');
check(
    'Konto aktywne i aktywowane',
    (int) ($nowy['aktywny'] ?? 0) === 1 && (int) ($nowy['aktywowany'] ?? 0) === 1
);
check(
    'Nowe haslo zapisane w formie hashu',
    !empty($nowy['haslo_hash']) && password_verify('NoweHaslo123', (string) $nowy['haslo_hash'])
);
check(
    'Zaproszenie oznaczone jako wykorzystane',
    (int) db_scalar(
        "SELECT COUNT(*) FROM zaproszenia WHERE email = 'nowy.pracownik@firma.pl' AND uzyty_at IS NOT NULL"
    ) === 1
);

/* Po rejestracji uzytkownik jest zalogowany */
$r = req('GET', $base . '/admin/index.php');
check(
    'Po rejestracji uzytkownik jest zalogowany',
    $r['status'] === 200 && str_contains($r['html'], 'Panel startowy')
);

/* Kolejne logowanie nowego uzytkownika: tylko kodem od administratora */
@unlink($jar);
$r = req('GET', $base . '/login.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $token, 'tryb' => 'admin', 'email' => 'nowy.pracownik@firma.pl', 'haslo' => 'NoweHaslo123',
]);
check(
    'Tryb administratora - nowe konto materialow odrzucone',
    str_contains($r['html'], 'nie ma uprawnień administratora')
);

/* Administrator wysyla kod logowania nowemu uzytkownikowi */
$r = req('GET', $base . '/login.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $token, 'tryb' => 'admin', 'email' => 'admin@firma.pl', 'haslo' => 'Admin123!',
], null, false);

$r = req('GET', $base . '/admin/uzytkownicy.php');
$token = csrf($r['html']);
check('Panel: konto materialow loguje sie kodem', str_contains($r['html'], 'logowanie kodem'));
$r = req('POST', $base . '/admin/uzytkownicy.php', [
    'csrf' => $token, 'akcja' => 'kod', 'user_id' => (string) $newId, 'cel' => 'logowanie',
]);
check('Wyslanie kodu logowania nowemu uzytkownikowi', str_contains($r['html'], 'wysłany na'));

$kodNowego = (string) db_scalar(
    "SELECT kod FROM kody WHERE user_id = $newId AND cel = 'logowanie' AND uzyty_at IS NULL ORDER BY id DESC LIMIT 1"
);
check('Kod dla nowego uzytkownika zapisany', (bool) preg_match('/^[A-Z0-9]{6}$/', $kodNowego), "($kodNowego)");

@unlink($jar);
$r = req('GET', $base . '/login.php?tryb=uzytkownik');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $token, 'tryb' => 'uzytkownik', 'kod' => $kodNowego,
], null, false);
$zalogowany = $r['status'] === 302 && !str_contains((string) $r['head'], 'login.php');
check('Logowanie nowego uzytkownika kodem', $zalogowany);

/* Ten sam kod rejestracyjny działa jako stały kod logowania */
@unlink($jar);
$r = req('GET', $base . '/login.php?tryb=uzytkownik');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $token, 'tryb' => 'uzytkownik', 'kod' => $kodRej,
], null, false);
check(
    'Kod rejestracyjny staje się stałym kodem logowania',
    $r['status'] === 302 && !str_contains((string) $r['head'], 'login.php'),
    '(HTTP ' . $r['status'] . ')'
);

/* Operacje na slajdach: publikacja, kolejnosc, edycja, usuniecie */
$r = req('GET', $base . '/admin/materialy.php');
if ($r['status'] !== 200) {
    check('Panel materialow dostepny dla nowego uzytkownika', false, '(HTTP ' . $r['status'] . ')');
} else {
    preg_match('/name="slajd_id" value="(\d+)"/', $r['html'], $m);
    $slajdId = (int) ($m[1] ?? 0);
    $token = csrf($r['html']);

    $statusPrzed = (string) db_scalar('SELECT status FROM slajdy WHERE id = ' . $slajdId);

    $r = req('POST', $base . '/admin/materialy.php', [
        'csrf' => $token, 'akcja' => 'status', 'slajd_id' => (string) $slajdId,
    ]);
    $statusPo = (string) db_scalar('SELECT status FROM slajdy WHERE id = ' . $slajdId);
    check(
        'Zmiana statusu slajdu',
        $statusPrzed !== $statusPo && in_array($statusPo, ['szkic', 'opublikowany'], true),
        "($statusPrzed -> $statusPo)"
    );

    // Przenosimy ostatni slajd w górę (pierwszy nie ma sąsiada).
    // W razie potrzeby dokładamy drugi slajd, aby ruch miał sąsiada.
    $liczba = (int) db_scalar('SELECT COUNT(*) FROM slajdy');
    if ($liczba < 2) {
        $r = req('GET', $base . '/admin/slajd_form.php');
        $token = csrf($r['html']);
        $r = req('POST', $base . '/admin/slajd_form.php', [
            'csrf' => $token, 'tytul' => 'Drugi slajd testowy', 'tresc' => 'Tresc', 'uklad' => 'tylko-tekst',
        ], null, false);
        check(
            'Utworzenie drugiego slajdu (dla testu kolejnosci)',
            $r['status'] === 302,
            '(HTTP ' . $r['status'] . ' slajdy=' . (int) db_scalar('SELECT COUNT(*) FROM slajdy') . ')'
        );
    }

    $ostatni = db_read('SELECT id, kolejnosc FROM slajdy ORDER BY kolejnosc DESC LIMIT 1')[0] ?? [];
    $ostatniId = (int) ($ostatni['id'] ?? 0);
    $kolejnoscPrzed = (int) ($ostatni['kolejnosc'] ?? 0);

    $r = req('GET', $base . '/admin/materialy.php');
    $token = csrf($r['html']);
    $r = req('POST', $base . '/admin/materialy.php', [
        'csrf' => $token, 'akcja' => 'kolejnosc', 'slajd_id' => (string) $ostatniId, 'kierunek' => 'up',
    ], null, false);

    $kolejnoscPo = (int) db_scalar('SELECT kolejnosc FROM slajdy WHERE id = ' . $ostatniId);
    check(
        'Zmiana kolejnosci slajdu',
        $r['status'] === 302 && $kolejnoscPo < $kolejnoscPrzed,
        "($kolejnoscPrzed -> $kolejnoscPo)"
    );

    $r = req('GET', $base . '/admin/slajd_form.php?id=' . $slajdId);
    $token = csrf($r['html']);
    $r = req('POST', $base . '/admin/slajd_form.php?id=' . $slajdId, [
        'csrf' => $token, 'tytul' => 'Zaktualizowany tytul slajdu', 'tresc' => 'Tresc', 'uklad' => 'cytat',
    ]);
    check('Edycja slajdu', str_contains($r['html'], 'Zaktualizowany tytul slajdu'));

    $przedUsunieciem = (int) db_scalar('SELECT COUNT(*) FROM slajdy WHERE id = ' . $slajdId);

    $r = req('GET', $base . '/admin/materialy.php');
    $token = csrf($r['html']);
    $r = req('POST', $base . '/admin/materialy.php', [
        'csrf' => $token, 'akcja' => 'usun', 'slajd_id' => (string) $slajdId,
    ]);
    $poUsunieciu = (int) db_scalar('SELECT COUNT(*) FROM slajdy WHERE id = ' . $slajdId);
    check('Usuniecie slajdu', $przedUsunieciem === 1 && $poUsunieciu === 0);
}

/* Usuwanie konta testowego */
$pdo->prepare("DELETE FROM users WHERE email = 'nowy.pracownik@firma.pl'")->execute();

/* ------------------------------------------------------------------ */
/* 8. Throttling logowania (na końcu – bo blokuje sesję)              */
/* ------------------------------------------------------------------ */
$blokada = false;
for ($i = 1; $i <= 5; $i++) {
    $r = req('GET', $base . '/login.php');
    $token = csrf($r['html']);
    $r = req('POST', $base . '/login.php', [
        'csrf' => $token, 'email' => 'admin@firma.pl', 'haslo' => 'zle' . $i,
    ]);
    if (str_contains($r['html'], 'blokowane na 10 minut')) {
        $blokada = true;
    }
}
check('Blokada logowania po 5 blednych probach', $blokada);

$r = req('GET', $base . '/login.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $token, 'email' => 'admin@firma.pl', 'haslo' => 'Admin123!',
]);
check('Poprawne haslo odrzucone podczas blokady', str_contains($r['html'], 'Zbyt wiele nieudanych prób'));

@unlink($jar);
@unlink($png);
@unlink($txt);
@unlink($big);

/* ------------------------------------------------------------------ */
/* 9. Testy: dodawanie testu (regresja przycisku „Dodaj test”)         */
/* ------------------------------------------------------------------ */
@unlink($jar);
$r = req('GET', $base . '/login.php');
$token = csrf($r['html']);
req('POST', $base . '/login.php', [
    'csrf' => $token, 'tryb' => 'admin', 'email' => 'admin@firma.pl', 'haslo' => 'Admin123!',
], null, false);

$r = req('GET', $base . '/admin/testy.php');
check(
    'Testy: formularz dodawania testu',
    $r['status'] === 200 && str_contains($r['html'], 'Nowy test') && str_contains($r['html'], 'Utwórz test'),
    '(HTTP ' . $r['status'] . ')'
);
check('Testy: lista bez bledow PHP', blad_php($r['html']) === '', blad_php($r['html']));
$token = csrf($r['html']);

$r = req('POST', $base . '/admin/testy.php', [
    'csrf' => $token, 'akcja' => 'dodaj_test', 'tytul' => 'Test automatyczny', 'opis' => 'Opis testu',
]);
check(
    'Testy: dodanie testu',
    str_contains($r['html'], 'Ustawienia testu') && str_contains($r['html'], 'Dodaj pytanie') && blad_php($r['html']) === '',
    blad_php($r['html'])
);

$testId = (int) db_scalar("SELECT id FROM testy WHERE tytul = 'Test automatyczny' ORDER BY id DESC LIMIT 1");
check('Testy: rekord testu w bazie', $testId > 0, "(id=$testId)");
check('Testy: nowy test ma status szkic', (string) db_scalar('SELECT status FROM testy WHERE id = ' . $testId) === 'szkic');

$r = req('POST', $base . '/admin/testy.php', [
    'csrf' => $token, 'akcja' => 'dodaj_test', 'tytul' => '   ',
]);
check('Testy: pusty tytul odrzucony', str_contains($r['html'], 'Tytuł testu jest wymagany'));

$r = req('POST', $base . '/admin/testy.php', ['akcja' => 'dodaj_test', 'tytul' => 'Bez tokenu']);
check(
    'Testy: formularz bez tokenu CSRF odrzucony',
    $r['status'] === 400 && str_contains($r['html'], 'Nieprawidłowy token CSRF'),
    '(HTTP ' . $r['status'] . ')'
);

$r = req('POST', $base . '/admin/testy.php', [
    'csrf' => $token, 'akcja' => 'dodaj_pytanie', 'test_id' => (string) $testId, 'tresc' => 'Pytanie testowe?',
]);
check(
    'Testy: dodanie pytania',
    str_contains($r['html'], 'Pytanie testowe?') && str_contains($r['html'], 'Pytania w teście') && blad_php($r['html']) === '',
    blad_php($r['html'])
);

$pytanieId = (int) db_scalar('SELECT id FROM pytania WHERE test_id = ' . $testId . ' ORDER BY id DESC LIMIT 1');
$r = req('POST', $base . '/admin/testy.php', [
    'csrf' => $token, 'akcja' => 'dodaj_odpowiedz', 'test_id' => (string) $testId,
    'pytanie_id' => (string) $pytanieId, 'tresc' => 'Odpowiedz A', 'poprawna' => 'on',
]);
check(
    'Testy: dodanie odpowiedzi',
    str_contains($r['html'], 'Odpowiedz A') && str_contains($r['html'], 'poprawna') && blad_php($r['html']) === '',
    blad_php($r['html'])
);
$odpowiedzId = (int) db_scalar('SELECT id FROM odpowiedzi WHERE pytanie_id = ' . $pytanieId . ' ORDER BY id DESC LIMIT 1');
check(
    'Testy: odpowiedz oznaczona jako poprawna',
    (int) db_scalar('SELECT poprawna FROM odpowiedzi WHERE id = ' . $odpowiedzId) === 1
);

$r = req('POST', $base . '/admin/testy.php', [
    'csrf' => $token, 'akcja' => 'edytuj_test', 'test_id' => (string) $testId,
    'tytul' => 'Test automatyczny', 'opis' => 'Opis testu', 'status' => 'opublikowany',
]);
check(
    'Testy: publikacja testu',
    str_contains($r['html'], 'jest już widoczny') && blad_php($r['html']) === '',
    blad_php($r['html'])
);

$r = req('POST', $base . '/admin/testy.php', [
    'csrf' => $token, 'akcja' => 'rozwiaz', 'test_id' => (string) $testId,
    'odpowiedz[' . $pytanieId . ']' => (string) $odpowiedzId,
]);
check(
    'Testy: strona wyniku po rozwiazaniu',
    str_contains($r['html'], 'Wynik testu') && str_contains($r['html'], '100% poprawnych') && blad_php($r['html']) === '',
    blad_php($r['html'])
);
check(
    'Testy: wynik zapisany w bazie',
    (int) db_scalar('SELECT wynik FROM wyniki_testow WHERE test_id = ' . $testId . ' ORDER BY id DESC LIMIT 1') === 1
);

// Pytanie wielokrotnego wyboru: kilka poprawnych odpowiedzi.
$r = req('POST', $base . '/admin/testy.php', [
    'csrf' => $token, 'akcja' => 'dodaj_odpowiedz', 'test_id' => (string) $testId,
    'pytanie_id' => (string) $pytanieId, 'tresc' => 'Odpowiedz B',
]);
$odpowiedzB = (int) db_scalar('SELECT id FROM odpowiedzi WHERE pytanie_id = ' . $pytanieId . ' ORDER BY id DESC LIMIT 1');
check(
    'Testy: dodanie drugiej odpowiedzi',
    $odpowiedzB > 0 && str_contains($r['html'], 'Odpowiedz B') && blad_php($r['html']) === '',
    blad_php($r['html'])
);

$r = req('POST', $base . '/admin/testy.php', [
    'csrf' => $token, 'akcja' => 'przelacz_poprawna', 'test_id' => (string) $testId,
    'odpowiedz_id' => (string) $odpowiedzB, 'poprawna' => '1',
]);
check(
    'Testy: druga odpowiedz oznaczona jako poprawna',
    (int) db_scalar('SELECT poprawna FROM odpowiedzi WHERE id = ' . $odpowiedzB) === 1
    && str_contains($r['html'], 'wielokrotny wybór')
    && blad_php($r['html']) === '',
    blad_php($r['html'])
);

$r = req('POST', $base . '/admin/testy.php', [
    'csrf' => $token, 'akcja' => 'rozwiaz', 'test_id' => (string) $testId,
    'odpowiedz[' . $pytanieId . '][0]' => (string) $odpowiedzId,
    'odpowiedz[' . $pytanieId . '][1]' => (string) $odpowiedzB,
]);
check(
    'Testy: pelne zaznaczenie kilku poprawnych = 100%',
    str_contains($r['html'], '100% poprawnych') && blad_php($r['html']) === '',
    blad_php($r['html'])
);

$r = req('POST', $base . '/admin/testy.php', [
    'csrf' => $token, 'akcja' => 'rozwiaz', 'test_id' => (string) $testId,
    'odpowiedz[' . $pytanieId . '][0]' => (string) $odpowiedzId,
]);
check(
    'Testy: czesciowe zaznaczenie = 0%',
    str_contains($r['html'], '0% poprawnych') && blad_php($r['html']) === '',
    blad_php($r['html'])
);

$r = req('POST', $base . '/admin/testy.php', [
    'csrf' => $token, 'akcja' => 'przelacz_poprawna', 'test_id' => (string) $testId,
    'odpowiedz_id' => (string) $odpowiedzB, 'poprawna' => '0',
]);
check(
    'Testy: odpowiedz mozna odznaczyc jako niepoprawna',
    (int) db_scalar('SELECT poprawna FROM odpowiedzi WHERE id = ' . $odpowiedzB) === 0
    && blad_php($r['html']) === '',
    blad_php($r['html'])
);

// Przywróć dwie poprawne odpowiedzi i sprawdź widok rozwiązywania jako użytkownik.
req('POST', $base . '/admin/testy.php', [
    'csrf' => $token, 'akcja' => 'przelacz_poprawna', 'test_id' => (string) $testId,
    'odpowiedz_id' => (string) $odpowiedzB, 'poprawna' => '1',
]);

$marketingId = (int) db_scalar("SELECT id FROM users WHERE email = 'marketing@firma.pl'");
$kod = 'ABC123';
db_read(
    "INSERT INTO kody (user_id, kod, cel, wygasa_at, created_at)
     VALUES ($marketingId, '$kod', 'logowanie', datetime('now', '+1 day'), datetime('now'))"
);

req('GET', $base . '/logout.php');
$r = req('GET', $base . '/login.php?tryb=uzytkownik');
$userToken = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $userToken, 'tryb' => 'uzytkownik', 'kod' => $kod,
], null, false);
check('Testy: logowanie uzytkownika do widoku rozwiazywania', $r['status'] === 302, '(HTTP ' . $r['status'] . ')');

$r = req('GET', $base . '/admin/testy.php?akcja=rozwiaz&test_id=' . $testId);
check(
    'Testy: pytanie wielokrotnego wyboru ma checkboxy',
    str_contains($r['html'], 'type="checkbox"')
    && str_contains($r['html'], 'Zaznacz wszystkie poprawne odpowiedzi')
    && blad_php($r['html']) === '',
    blad_php($r['html'])
);

echo "\n";
echo 'ZALICZONE : ' . $wynik['ok'] . "\n";
echo 'BLEDY     : ' . $wynik['blad'] . "\n";
exit($wynik['blad'] > 0 ? 1 : 0);
