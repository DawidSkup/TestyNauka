<?php
declare(strict_types=1);

/**
 * Tworzenie konta użytkownika, który musi zalogować się kodem od administratora.
 *
 * Użycie:
 *   php tools/utworz_uzytkownika.php                                       # tryb interaktywny
 *   php tools/utworz_uzytkownika.php pracownik@firma.pl Pracownik123!      # e-mail + hasło
 *   php tools/utworz_uzytkownika.php pracownik@firma.pl Pracownik123! "Jan Kowalski" materialy
 *
 * Konto zakładane tym skryptem:
 *   - aktywne i aktywowane,
 *   - z flagą `wymaga_kod = 1` – bez kodu wysłanego przez administratora
 *     logowanie jest odrzucane (kod jednorazowy, ważny 15 minut),
 *   - niewykorzystane kody logowania są unieważniane.
 *
 * Skrypt nie wysyła żadnego e-maila – kod wysyła administrator z panelu
 * (sekcja Użytkownicy → „Generuj i wyślij kod”).
 */

require __DIR__ . '/../helpers.php';

function wczytaj(string $pytanie): string
{
    echo $pytanie;
    $linia = fgets(STDIN);

    return $linia === false ? '' : trim($linia);
}

$email = mb_strtolower(trim((string) ($argv[1] ?? '')));
$haslo = (string) ($argv[2] ?? '');
$imie  = trim((string) ($argv[3] ?? ''));
$grupa = mb_strtolower(trim((string) ($argv[4] ?? GROUP_MATERIALY)));

if ($email === '') {
    $email = mb_strtolower(wczytaj('E-mail konta: '));
}
if ($email === '') {
    fwrite(STDERR, "Błąd: brak adresu e-mail.\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Błąd: adres e-mail jest nieprawidłowy.\n");
    exit(1);
}
if ($haslo === '') {
    $haslo = (string) wczytaj('Hasło (min. 8 znaków): ');
}
if (strlen($haslo) < 8) {
    fwrite(STDERR, "Błąd: hasło musi mieć co najmniej 8 znaków.\n");
    exit(1);
}
if ($imie === '') {
    $imie = (string) wczytaj('Imię i nazwisko (Enter = Użytkownik): ');
    if ($imie === '') {
        $imie = 'Użytkownik';
    }
}
if (!isset(GROUPS[$grupa])) {
    fwrite(STDERR, "Błąd: nieznana grupa „{$grupa}”. Dostępne: " . implode(', ', array_keys(GROUPS)) . "\n");
    exit(1);
}

$pdo  = db();
$now  = date('Y-m-d H:i:s');
$hash = password_hash($haslo, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
$stmt->execute([':email' => $email]);
$istniejacy = $stmt->fetch();

if ($istniejacy) {
    $id = (int) $istniejacy['id'];

    $pdo->prepare(
        'UPDATE users
            SET imie = :imie, haslo_hash = :haslo, grupa = :grupa,
                aktywny = 1, aktywowany = 1, wymaga_kod = 1, updated_at = :now
          WHERE id = :id'
    )->execute([
        ':imie'  => $imie,
        ':haslo' => $hash,
        ':grupa' => $grupa,
        ':now'   => $now,
        ':id'    => $id,
    ]);

    $pdo->prepare('UPDATE kody SET uzyty_at = :now WHERE user_id = :u AND uzyty_at IS NULL')
        ->execute([':now' => $now, ':u' => $id]);

    log_action('zaktualizowano_konto_uzytkownika', $email, $id);

    echo "Zaktualizowano konto użytkownika:\n";
} else {
    $pdo->prepare(
        'INSERT INTO users (imie, email, haslo_hash, grupa, aktywny, aktywowany, wymaga_kod, created_at, updated_at)
         VALUES (:imie, :email, :haslo, :grupa, 1, 1, 1, :now, :now)'
    )->execute([
        ':imie'  => $imie,
        ':email' => $email,
        ':haslo' => $hash,
        ':grupa' => $grupa,
        ':now'   => $now,
    ]);

    $id = (int) $pdo->lastInsertId();
    log_action('utworzono_konto_uzytkownika', $email . ' | grupa=' . $grupa, $id);

    echo "Utworzono konto użytkownika:\n";
}

echo "  e-mail    : {$email}\n";
echo "  hasło     : ustawione\n";
echo "  grupa     : " . GROUPS[$grupa] . "\n";
echo "  status    : aktywne, aktywowane\n";
echo "  logowanie : wymaga kodu od administratora (jednorazowy, ważny "
    . CODE_TTL_MINUTES . ' min)' . "\n";
echo "  następny krok: panel → Użytkownicy → „Generuj i wyślij kod” dla tego konta\n";
