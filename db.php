<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Połączenie PDO do bazy SQLite (tworzona automatycznie razem ze schematem).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    foreach ([dirname(DB_FILE), UPLOAD_PATH] as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }

    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    db_migrate($pdo);

    return $pdo;
}

/**
 * Dodaje kolumnę do tabeli, jeśli jeszcze nie istnieje (migracja SQLite).
 */
function db_add_column(PDO $pdo, string $table, string $column, string $definition): void
{
    $kolumny = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);

    foreach ($kolumny as $k) {
        if ((string) ($k['name'] ?? '') === $column) {
            return;
        }
    }

    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
}

/**
 * Tworzy tabele (jeśli nie istnieją) oraz konta startowe.
 */
function db_migrate(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS users (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            imie        TEXT    NOT NULL,
            email       TEXT    NOT NULL UNIQUE,
            haslo_hash  TEXT    NOT NULL DEFAULT '',
            grupa       TEXT    NOT NULL DEFAULT 'materialy',
            aktywny     INTEGER NOT NULL DEFAULT 1,
            aktywowany  INTEGER NOT NULL DEFAULT 0,
            created_at  TEXT    NOT NULL,
            updated_at  TEXT    NOT NULL
        )
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS kody (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id     INTEGER NOT NULL,
            kod         TEXT    NOT NULL,
            cel         TEXT    NOT NULL DEFAULT 'logowanie',
            wygasa_at   TEXT    NOT NULL,
            uzyty_at    TEXT,
            wyslany_at  TEXT,
            created_at  TEXT    NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    SQL);

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_kody_user ON kody(user_id, cel)');

    // Konto z flagą `wymaga_kod` nie da się zalogować bez kodu wysłanego przez administratora.
    db_add_column($pdo, 'users', 'wymaga_kod', 'INTEGER NOT NULL DEFAULT 0');

    // Zaproszenia: administrator wysyła kod na e-mail, a odbiorca zakłada konto sam.
    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS zaproszenia (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            email         TEXT    NOT NULL,
            grupa         TEXT    NOT NULL DEFAULT 'materialy',
            kod           TEXT    NOT NULL,
            wygasa_at     TEXT    NOT NULL,
            uzyty_at      TEXT,
            utworzony_przez INTEGER,
            created_at    TEXT    NOT NULL,
            FOREIGN KEY (utworzony_przez) REFERENCES users(id) ON DELETE SET NULL
        )
    SQL);

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_zaproszenia_email ON zaproszenia(email, uzyty_at)');

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS slajdy (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            tytul       TEXT    NOT NULL,
            tresc       TEXT    NOT NULL DEFAULT '',
            obraz       TEXT,
            uklad       TEXT    NOT NULL DEFAULT 'tekst-obraz',
            przycisk    TEXT    NOT NULL DEFAULT '',
            link        TEXT    NOT NULL DEFAULT '',
            status      TEXT    NOT NULL DEFAULT 'szkic',
            kolejnosc   INTEGER NOT NULL DEFAULT 0,
            autor_id    INTEGER,
            created_at  TEXT    NOT NULL,
            updated_at  TEXT    NOT NULL,
            FOREIGN KEY (autor_id) REFERENCES users(id) ON DELETE SET NULL
        )
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS testy (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            tytul       TEXT    NOT NULL,
            opis        TEXT    NOT NULL DEFAULT '',
            status      TEXT    NOT NULL DEFAULT 'szkic',
            autor_id    INTEGER,
            created_at  TEXT    NOT NULL,
            updated_at  TEXT    NOT NULL,
            FOREIGN KEY (autor_id) REFERENCES users(id) ON DELETE SET NULL
        )
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS pytania (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            test_id     INTEGER NOT NULL,
            tresc       TEXT    NOT NULL,
            kolejnosc   INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY (test_id) REFERENCES testy(id) ON DELETE CASCADE
        )
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS odpowiedzi (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            pytanie_id  INTEGER NOT NULL,
            tresc       TEXT    NOT NULL,
            poprawna    INTEGER NOT NULL DEFAULT 0,
            kolejnosc   INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY (pytanie_id) REFERENCES pytania(id) ON DELETE CASCADE
        )
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS wyniki_testow (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id     INTEGER NOT NULL,
            test_id     INTEGER NOT NULL,
            wynik       INTEGER NOT NULL DEFAULT 0,
            maks        INTEGER NOT NULL DEFAULT 0,
            procent     REAL    NOT NULL DEFAULT 0,
            created_at  TEXT    NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (test_id) REFERENCES testy(id) ON DELETE CASCADE
        )
    SQL);

    $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS log (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id     INTEGER,
            akcja       TEXT    NOT NULL,
            szczegoly   TEXT    NOT NULL DEFAULT '',
            created_at  TEXT    NOT NULL
        )
    SQL);

    // Konta startowe (tylko przy pierwszym uruchomieniu).
    $ile = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($ile === 0) {
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'INSERT INTO users (imie, email, haslo_hash, grupa, aktywny, aktywowany, created_at, updated_at)
             VALUES (:imie, :email, :haslo, :grupa, 1, :aktyw, :now, :now)'
        );
        $stmt->execute([
            ':imie'  => 'Administrator',
            ':email' => 'admin@firma.pl',
            ':haslo' => password_hash('Admin123!', PASSWORD_DEFAULT),
            ':grupa' => GROUP_FIRMA,
            ':aktyw' => 1,
            ':now'   => $now,
        ]);
        $stmt->execute([
            ':imie'  => 'Anna Marketing',
            ':email' => 'marketing@firma.pl',
            ':haslo' => password_hash('Marketing123!', PASSWORD_DEFAULT),
            ':grupa' => GROUP_MATERIALY,
            ':aktyw' => 1,
            ':now'   => $now,
        ]);
    }
}
