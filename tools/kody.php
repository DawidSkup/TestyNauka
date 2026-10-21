<?php
// Skrypt pomocniczy do testów – wypisuje aktywne kody i zaproszenia z bazy.
require __DIR__ . '/../db.php';

$kody = db()->query(
    'SELECT id, user_id, kod, cel, wygasa_at FROM kody WHERE uzyty_at IS NULL ORDER BY id DESC LIMIT 5'
)->fetchAll();

foreach ($kody as $k) {
    printf("id=%d user=%d kod=%s cel=%s wygasa=%s\n", $k['id'], $k['user_id'], $k['kod'], $k['cel'], $k['wygasa_at']);
}

$zap = db()->query(
    'SELECT id, email, grupa, kod, wygasa_at FROM zaproszenia WHERE uzyty_at IS NULL ORDER BY id DESC LIMIT 5'
)->fetchAll();

foreach ($zap as $z) {
    printf("zaproszenie=%d email=%s grupa=%s kod=%s wygasa=%s\n", $z['id'], $z['email'], $z['grupa'], $z['kod'], $z['wygasa_at']);
}
