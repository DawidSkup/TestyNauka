<?php
// Skrypt pomocniczy do testów – sprawdza wiadomości w Mailpit.
$json = @file_get_contents('http://127.0.0.1:8025/api/v1/messages?limit=3');
if ($json === false) {
    echo "Mailpit niedostepny\n";
    exit(1);
}

$d = json_decode($json, true);
echo "Mailpit: total=" . ($d['total'] ?? 0) . "\n\n";

foreach ($d['messages'] as $m) {
    $full = json_decode((string) file_get_contents('http://127.0.0.1:8025/api/v1/message/' . $m['ID']), true);
    echo 'Subject : ' . ($full['Subject'] ?? '') . "\n";
    echo 'Do      : ' . ($full['To'][0]['Address'] ?? '') . "\n";
    $body = strip_tags((string) ($full['HTML'] ?? ''));
    $body = preg_replace('/\s+/u', ' ', $body);
    echo 'Treść   : ' . mb_substr((string) $body, 0, 220) . "\n";
    echo "---\n";
}
