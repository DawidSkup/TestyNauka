<?php
declare(strict_types=1);

/**
 * Tworzenie / reset konta administratora (grupa `firma`) BEZ kodu weryfikacyjnego.
 *
 * Użycie:
 *   php tools/utworz_admina.php                                   # tryb interaktywny
 *   php tools/utworz_admina.php admin@firma.pl NoweHaslo123        # e-mail + hasło
 *   php tools/utworz_admina.php admin@firma.pl NoweHaslo123 "Jan Kowalski"
 *
 * Skrypt:
 *  - zakłada konto w grupie `firma`, aktywne i aktywowane (bez wysyłki kodu),
 *  - przy istniejącym koncie resetuje hasło i przywraca uprawnienia,
 *  - usuwa niewykorzystane kody logowania, żeby przy logowaniu nie był wymagany kod.
 */

require __DIR__ . '/../helpers.php';

function wczytaj(string $pytanie): string
{
    echo $pytanie;
    $linia = fgets(STDIN);

    return $linia === false ? '' : trim($linia);
}

$email = strtolower(trim((string) ($argv[1] ?? '')));
$haslo = (string) ($argv[2] ?? '');
$imie  = trim((string) ($argv[3] ?? ''));

if ($email === '') {
    $email = strtolower(wczytaj('E-mail konta: '));
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
    $imie = (string) wczytaj('Imię i nazwisko (Enter = Administrator): ');
    if ($imie === '') {
        $imie = 'Administrator';
    }
}

$pdo   = db();
$now   = date('Y-m-d H:i:s');
$hash  = password_hash($haslo, PASSWORD_DEFAULT);

$stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
$stmt->execute([':email' => $email]);
$istniejacy = $stmt->fetch();

if ($istniejacy) {
    $id = (int) $istniejacy['id'];

    $pdo->prepare(
        'UPDATE users
            SET imie = :imie, haslo_hash = :haslo, grupa = :grupa,
                aktywny = 1, aktywowany = 1, updated_at = :now
          WHERE id = :id'
    )->execute([
        ':imie'  => $imie,
        ':haslo' => $hash,
        ':grupa' => GROUP_FIRMA,
        ':now'   => $now,
        ':id'    => $id,
    ]);

    // Niewykorzystane kody są unieważniane – przy logowaniu nie będzie wymagany kod.
    $pdo->prepare('UPDATE kody SET uzyty_at = :now WHERE user_id = :u AND uzyty_at IS NULL')
        ->execute([':now' => $now, ':u' => $id]);

    log_action('zresetowano_konto_admina', $email, $id);

    echo "Zaktualizowano konto administratora:\n";
    echo "  e-mail : {$email}\n";
    echo "  hasło  : ustawione nowe\n";
    echo "  grupa  : " . GROUPS[GROUP_FIRMA] . "\n";
    echo "  status : aktywne, aktywowane\n";
    echo "  kody   : niewykorzystane kody unieważnione (logowanie bez kodu)\n";
    exit(0);
}

$pdo->prepare(
    'INSERT INTO users (imie, email, haslo_hash, grupa, aktywny, aktywowany, created_at, updated_at)
     VALUES (:imie, :email, :haslo, :grupa, 1, 1, :now, :now)'
)->execute([
    ':imie'  => $imie,
    ':email' => $email,
    ':haslo' => $hash,
    ':grupa' => GROUP_FIRMA,
    ':now'   => $now,
]);

$id = (int) $pdo->lastInsertId();
log_action('utworzono_konto_admina', $email, $id);

echo "Utworzono konto administratora:\n";
echo "  e-mail : {$email}\n";
echo "  hasło  : ustawione\n";
echo "  grupa  : " . GROUPS[GROUP_FIRMA] . "\n";
echo "  status : aktywne, aktywowane\n";
echo "  kod    : nie był generowany ani wysyłany\n";
