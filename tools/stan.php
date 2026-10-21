<?php
// Skrypt pomocniczy – stan bazy (użytkownicy, zaproszenia, slajdy).
require __DIR__ . '/../db.php';

echo "USERS:\n";
foreach (db()->query('SELECT id, imie, email, grupa, aktywny, aktywowany FROM users')->fetchAll() as $u) {
    printf("  %d  %-14s %-28s %-10s aktywny=%d aktywowany=%d\n",
        $u['id'], $u['imie'], $u['email'], $u['grupa'], $u['aktywny'], $u['aktywowany']);
}

echo "ZAPROSZENIA:\n";
foreach (db()->query('SELECT id, email, grupa, kod, uzyty_at FROM zaproszenia')->fetchAll() as $z) {
    printf("  %d  %-28s %-10s %s uzyty=%s\n",
        $z['id'], $z['email'], $z['grupa'], $z['kod'], $z['uzyty_at'] ?? '-');
}

echo 'SLAJDY: ' . db()->query('SELECT COUNT(*) FROM slajdy')->fetchColumn() . "\n";
echo 'KODY  : ' . db()->query('SELECT COUNT(*) FROM kody')->fetchColumn() . "\n";
