<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

/** Zwraca JSON i kończy skrypt. */
function json_out(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false, 'message' => 'Metoda niedozwolona.'], 405);
}

if (!is_logged_in()) {
    json_out(['ok' => false, 'message' => 'Sesja wygasła – zaloguj się ponownie.'], 401);
}

if (!in_group(GROUP_FIRMA)) {
    json_out(['ok' => false, 'message' => 'Brak uprawnień do tej operacji.'], 403);
}

$token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!csrf_valid(is_string($token) ? $token : null)) {
    json_out(['ok' => false, 'message' => 'Nieprawidłowy token CSRF.'], 400);
}

$userId = (int) ($_POST['user_id'] ?? 0);
$cel    = (string) ($_POST['cel'] ?? 'logowanie');

if ($userId <= 0) {
    json_out(['ok' => false, 'message' => 'Nie wskazano użytkownika.'], 422);
}
if (!in_array($cel, ['logowanie', 'aktywacja'], true)) {
    $cel = 'logowanie';
}

try {
    $wynik = generate_and_send_code($userId, $cel);
} catch (Throwable $ex) {
    json_out(['ok' => false, 'message' => $ex->getMessage()], 500);
}

json_out([
    'ok'        => true,
    'kod'       => $wynik['kod'],
    'email'     => $wynik['email'],
    'wygasa_at' => $wynik['wygasa_at'],
    'wyslany'   => $wynik['wyslany'],
    'message'   => $wynik['wyslany']
        ? 'Kod ' . $wynik['kod'] . ' wysłany na ' . $wynik['email'] . '.'
        : 'Nie udało się wysłać e-maila. Kod: ' . $wynik['kod'],
]);
