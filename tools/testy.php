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

/* ------------------------------------------------------------------ */
/* 1. Logowanie                                                        */
/* ------------------------------------------------------------------ */
$r = req('GET', $base . '/login.php');
$token = csrf($r['html']);
check('GET /login.php', $r['status'] === 200 && $token !== '');

$r = req('POST', $base . '/login.php', ['csrf' => $token, 'email' => 'admin@firma.pl', 'haslo' => 'zle']);
check('Logowanie - zle haslo', str_contains($r['html'], 'Nieprawidłowy e-mail lub hasło'));

$r = req('GET', $base . '/login.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', ['csrf' => $token, 'email' => 'admin@firma.pl', 'haslo' => 'Admin123!'], null, false);
check('Logowanie - grupa firma', $r['status'] === 302, '(HTTP ' . $r['status'] . ')');

$r = req('GET', $base . '/admin/index.php');
check('Panel startowy (grupa firma)', $r['status'] === 200 && str_contains($r['html'], 'Panel startowy'));

$token = csrf($r['html']);
$r = req('POST', $base . '/admin/index.php', ['csrf' => $token, 'akcja' => 'moj_kod']);
check(
    'Dashboard - przycisk "wyślij kod na mój e-mail"',
    str_contains($r['html'], 'został wysłany na adres')
);

$r = req('GET', $base . '/admin/uzytkownicy.php');
check('Sekcja Użytkownicy dla grupy firma', $r['status'] === 200 && str_contains($r['html'], 'Generuj i wyślij kod'));

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
/* 3. Uprawnienia grupy materialow                                     */
/* ------------------------------------------------------------------ */
@unlink($jar);
$r = req('GET', $base . '/login.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', ['csrf' => $token, 'email' => 'marketing@firma.pl', 'haslo' => 'Marketing123!'], null, false);
$needs2fa = $r['status'] === 302 && str_contains((string) $r['head'], 'weryfikacja.php');
check('Logowanie - grupa materialy', $r['status'] === 302, $needs2fa ? '(wymaga kodu)' : '');

if ($needs2fa) {
    $r = req('GET', $base . '/weryfikacja.php');
    $token = csrf($r['html']);
    check('Ekran weryfikacji kodu', $r['status'] === 200 && str_contains($r['html'], 'input-code'));

    $r = req('POST', $base . '/weryfikacja.php', ['csrf' => $token, 'kod' => 'ZLYKOD']);
    check('Weryfikacja - bledny kod', str_contains($r['html'], 'nieprawidłowy lub wygasł'));

    $aktywny = trim((string) @shell_exec('php "' . dirname(__DIR__) . '/tools/kody.php"'));
    preg_match('/kod=([A-Z0-9]{6})/', $aktywny, $m);
    $prawidlowy = $m[1] ?? '';
    $r = req('GET', $base . '/weryfikacja.php');
    $token = csrf($r['html']);
    $r = req('POST', $base . '/weryfikacja.php', ['csrf' => $token, 'kod' => $prawidlowy], null, false);
    check('Weryfikacja - poprawny kod', $r['status'] === 302, "($prawidlowy)");
}

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
$r = req('GET', $base . '/login.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', ['csrf' => $token, 'email' => 'marketing@firma.pl', 'haslo' => 'Marketing123!'], null, false);
check(
    'Wygasly kod nie blokuje logowania',
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

/* Logowanie nowego uzytkownika (bez 2FA – brak aktywnego kodu logowania) */
@unlink($jar);
$r = req('GET', $base . '/login.php');
$token = csrf($r['html']);
$r = req('POST', $base . '/login.php', [
    'csrf' => $token, 'email' => 'nowy.pracownik@firma.pl', 'haslo' => 'NoweHaslo123',
], null, false);
$zalogowany = $r['status'] === 302 && !str_contains((string) $r['head'], 'weryfikacja.php');
check('Logowanie nowego uzytkownika', $zalogowany);

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

echo "\n";
echo 'ZALICZONE : ' . $wynik['ok'] . "\n";
echo 'BLEDY     : ' . $wynik['blad'] . "\n";
exit($wynik['blad'] > 0 ? 1 : 0);
