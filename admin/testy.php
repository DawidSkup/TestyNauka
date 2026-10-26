<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/_layout.php';

require_login();

$pdo = db();
$action = $_GET['akcja'] ?? ($_POST['akcja'] ?? '');
$testId = (int) ($_GET['test_id'] ?? ($_POST['test_id'] ?? 0));
$user = current_user();
$now = date('Y-m-d H:i:s');
$isFirma = in_group(GROUP_FIRMA);

/* ------------------------------------------------------------------ */
/* Akcje: POST                                                         */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf'] ?? null);

    if ($action === 'dodaj_test') {
        require_group(GROUP_FIRMA);
        $opis = trim((string) ($_POST['opis'] ?? ''));

        if ($opis === '') {
            flash('error', 'Opis testu jest wymagany.');
            redirect(app_url('admin/testy.php'));
        }

        $stmt = $pdo->query('SELECT COALESCE(MAX(CAST(SUBSTR(tytul, INSTR(tytul, " ") + 1) AS INTEGER)), 0) + 1 FROM testy WHERE tytul LIKE "Test %"');
        $nextNum = (int) $stmt->fetchColumn();
        $tytul = 'Test ' . $nextNum;

        $stmt = $pdo->prepare('INSERT INTO testy (tytul, opis, status, autor_id, created_at, updated_at) VALUES (:tytul, :opis, "opublikowany", :autor_id, :now, :now)');
        $stmt->execute([':tytul' => $tytul, ':opis' => $opis, ':autor_id' => (int) $user['id'], ':now' => $now]);
        $newId = (int) $pdo->lastInsertId();
        flash('success', 'Test dodany i opublikowany. Dodaj teraz pytania i odpowiedzi.');
        redirect(app_url('admin/testy.php?akcja=edytuj&test_id=' . $newId));
    }

    if ($action === 'edytuj_test') {
        require_group(GROUP_FIRMA);
        $opis = trim((string) ($_POST['opis'] ?? ''));
        $status = $_POST['status'] ?? 'szkic';
        if (!in_array($status, ['szkic', 'opublikowany'], true)) {
            $status = 'szkic';
        }

        if ($opis === '') {
            flash('error', 'Opis testu jest wymagany.');
            redirect(app_url('admin/testy.php?akcja=edytuj&test_id=' . $testId));
        }

        $stmt = $pdo->prepare('UPDATE testy SET opis = :opis, status = :status, updated_at = :now WHERE id = :id');
        $stmt->execute([':opis' => $opis, ':status' => $status, ':now' => $now, ':id' => $testId]);
        flash('success', $status === 'opublikowany'
            ? 'Test zaktualizowany i opublikowany – jest już widoczny dla użytkowników.'
            : 'Test zaktualizowany. Pozostaje szkicem – użytkownicy go nie widzą.');
        redirect(app_url('admin/testy.php?akcja=edytuj&test_id=' . $testId));
    }

    if ($action === 'usun_test' && $testId > 0) {
        require_group(GROUP_FIRMA);
        $stmt = $pdo->prepare('DELETE FROM testy WHERE id = :id');
        $stmt->execute([':id' => $testId]);
        flash('success', 'Test usunięty.');
        redirect(app_url('admin/testy.php'));
    }

    if ($action === 'dodaj_pytanie' && $testId > 0) {
        require_group(GROUP_FIRMA);
        $tresc = trim((string) ($_POST['tresc'] ?? ''));
        if ($tresc === '') {
            flash('error', 'Treść pytania jest wymagana.');
            redirect(app_url('admin/testy.php?akcja=edytuj&test_id=' . $testId));
        }
        $max = (int) $pdo->query('SELECT COALESCE(MAX(kolejnosc), -1) + 1 AS next FROM pytania WHERE test_id = ' . $testId)->fetchColumn();
        $stmt = $pdo->prepare('INSERT INTO pytania (test_id, tresc, kolejnosc) VALUES (:test_id, :tresc, :kolejnosc)');
        $stmt->execute([':test_id' => $testId, ':tresc' => $tresc, ':kolejnosc' => $max]);
        flash('success', 'Pytanie dodane. Dodaj do niego odpowiedzi.');
        redirect(app_url('admin/testy.php?akcja=edytuj&test_id=' . $testId));
    }

    if ($action === 'usun_pytanie') {
        require_group(GROUP_FIRMA);
        $pytanieId = (int) ($_POST['pytanie_id'] ?? 0);
        if ($pytanieId > 0) {
            $stmt = $pdo->prepare('DELETE FROM pytania WHERE id = :id');
            $stmt->execute([':id' => $pytanieId]);
            flash('success', 'Pytanie usunięte.');
        }
        redirect(app_url('admin/testy.php?akcja=edytuj&test_id=' . $testId));
    }

    if ($action === 'dodaj_odpowiedz') {
        require_group(GROUP_FIRMA);
        $pytanieId = (int) ($_POST['pytanie_id'] ?? 0);
        $tresc = trim((string) ($_POST['tresc'] ?? ''));
        $poprawna = isset($_POST['poprawna']) ? 1 : 0;

        if ($pytanieId === 0 || $tresc === '') {
            flash('error', 'Treść odpowiedzi jest wymagana.');
            redirect(app_url('admin/testy.php?akcja=edytuj&test_id=' . $testId));
        }

        $max = (int) $pdo->query('SELECT COALESCE(MAX(kolejnosc), -1) + 1 AS next FROM odpowiedzi WHERE pytanie_id = ' . $pytanieId)->fetchColumn();
        $stmt = $pdo->prepare('INSERT INTO odpowiedzi (pytanie_id, tresc, poprawna, kolejnosc) VALUES (:pytanie_id, :tresc, :poprawna, :kolejnosc)');
        $stmt->execute([':pytanie_id' => $pytanieId, ':tresc' => $tresc, ':poprawna' => $poprawna, ':kolejnosc' => $max]);
        flash('success', 'Odpowiedź dodana.');
        redirect(app_url('admin/testy.php?akcja=edytuj&test_id=' . $testId));
    }

    if ($action === 'usun_odpowiedz') {
        require_group(GROUP_FIRMA);
        $odpowiedzId = (int) ($_POST['odpowiedz_id'] ?? 0);
        if ($odpowiedzId > 0) {
            $stmt = $pdo->prepare('DELETE FROM odpowiedzi WHERE id = :id');
            $stmt->execute([':id' => $odpowiedzId]);
            flash('success', 'Odpowiedź usunięta.');
        }
        redirect(app_url('admin/testy.php?akcja=edytuj&test_id=' . $testId));
    }

    if ($action === 'przelacz_poprawna') {
        require_group(GROUP_FIRMA);
        $odpowiedzId = (int) ($_POST['odpowiedz_id'] ?? 0);
        $poprawna = ((int) ($_POST['poprawna'] ?? 0)) === 1 ? 1 : 0;

        if ($odpowiedzId > 0) {
            // Odpowiedź musi należeć do pytania z edytowanego testu.
            $stmt = $pdo->prepare(
                'SELECT p.test_id FROM odpowiedzi o JOIN pytania p ON p.id = o.pytanie_id WHERE o.id = :id'
            );
            $stmt->execute([':id' => $odpowiedzId]);
            $testOdpowiedzi = $stmt->fetchColumn();

            if ($testOdpowiedzi !== false && (int) $testOdpowiedzi === $testId) {
                $stmt = $pdo->prepare('UPDATE odpowiedzi SET poprawna = :poprawna WHERE id = :id');
                $stmt->execute([':poprawna' => $poprawna, ':id' => $odpowiedzId]);
                flash('success', $poprawna === 1
                    ? 'Odpowiedź oznaczona jako poprawna.'
                    : 'Odpowiedź oznaczona jako niepoprawna.');
            }
        }
        redirect(app_url('admin/testy.php?akcja=edytuj&test_id=' . $testId));
    }

    if ($action === 'rozwiaz') {
        // Test odblokowuje obejrzenie prezentacji o tym samym numerze.
        if (!$isFirma) {
            $stmt = $pdo->prepare('SELECT * FROM testy WHERE id = :id AND status = "opublikowany"');
            $stmt->execute([':id' => $testId]);
            $testDoSprawdzenia = $stmt->fetch();

            if (!$testDoSprawdzenia) {
                flash('error', 'Test nie jest dostępny.');
                redirect(app_url('admin/testy.php'));
            }

            // Test można rozwiązać tylko raz – kolejna próba pokazuje zapisany wynik.
            $wynikiUzytkownika = wyniki_uzytkownika($pdo, (int) $user['id']);
            if (isset($wynikiUzytkownika[$testId])) {
                flash('info', 'Ten test możesz rozwiązać tylko raz. Pokazuję zapisany wynik.');
                redirect(app_url('admin/testy.php?akcja=wynik&test_id=' . $testId));
            }

            $blokada = blokujaca_prezentacja(
                (string) $testDoSprawdzenia['tytul'],
                prezentacje_wg_numeru($pdo),
                obejrzane_prezentacje($pdo, (int) $user['id'])
            );

            if ($blokada !== null) {
                flash('warning', 'Zanim rozwiążesz ten test, obejrzyj prezentację „' . $blokada['tytul'] . '”.');
                redirect(app_url('admin/testy.php'));
            }
        }

        $odpowiedzi = $_POST['odpowiedz'] ?? [];
        if (!is_array($odpowiedzi)) {
            $odpowiedzi = [];
        }

        $pytania = $pdo->prepare('SELECT id FROM pytania WHERE test_id = :test_id');
        $pytania->execute([':test_id' => $testId]);
        $pytania = $pytania->fetchAll(PDO::FETCH_COLUMN);

        $poprawne = 0;
        $maks = count($pytania);

        foreach ($pytania as $pid) {
            $pid = (int) $pid;
            $wybrane = [];
            if (isset($odpowiedzi[$pid])) {
                $wartosc = $odpowiedzi[$pid];
                if (is_array($wartosc)) {
                    foreach ($wartosc as $w) {
                        $w = (int) $w;
                        if ($w > 0) {
                            $wybrane[] = $w;
                        }
                    }
                } elseif ((int) $wartosc > 0) {
                    $wybrane[] = (int) $wartosc;
                }
            }
            $wybrane = array_values(array_unique($wybrane));

            $poprawneIds = $pdo->query('SELECT id FROM odpowiedzi WHERE pytanie_id = ' . $pid . ' AND poprawna = 1')->fetchAll(PDO::FETCH_COLUMN);
            $poprawneIds = array_map('intval', $poprawneIds);
            sort($poprawneIds);
            sort($wybrane);

            if ($poprawneIds !== [] && $wybrane === $poprawneIds) {
                $poprawne++;
            }
        }

        $procent = $maks > 0 ? round(($poprawne / $maks) * 100, 2) : 0;
        $stmt = $pdo->prepare('INSERT INTO wyniki_testow (user_id, test_id, wynik, maks, procent, created_at) VALUES (:user_id, :test_id, :wynik, :maks, :procent, :now)');
        $stmt->execute([':user_id' => (int) $user['id'], ':test_id' => $testId, ':wynik' => $poprawne, ':maks' => $maks, ':procent' => $procent, ':now' => $now]);

        redirect(app_url('admin/testy.php?akcja=wynik&test_id=' . $testId));
    }
}

/* ------------------------------------------------------------------ */
/* Dane dla widoków                                                    */
/* ------------------------------------------------------------------ */
$view = 'lista';
$test = null;
$pytania = [];
$odpowiedziWgPytania = [];
$wynik = null;
$testy = [];
$blokady = [];
$wynikiUzytkownika = [];

/** Pobiera odpowiedzi dla listy pytań i grupuje je po pytaniu. */
function testy_odpowiedzi(PDO $pdo, array $pytania): array
{
    $ids = array_map(static fn (array $p): int => (int) $p['id'], $pytania);
    if ($ids === []) {
        return [];
    }

    $sql = 'SELECT * FROM odpowiedzi WHERE pytanie_id IN (' . implode(',', $ids) . ') ORDER BY kolejnosc ASC, id ASC';
    $grupowane = [];
    foreach ($pdo->query($sql)->fetchAll() as $odp) {
        $grupowane[(int) $odp['pytanie_id']][] = $odp;
    }

    return $grupowane;
}

if ($action === 'edytuj' && $testId > 0) {
    if (!$isFirma) {
        flash('warning', 'Tylko grupa firmy może zarządzać testami.');
        redirect(app_url('admin/testy.php'));
    }

    $stmt = $pdo->prepare('SELECT * FROM testy WHERE id = :id');
    $stmt->execute([':id' => $testId]);
    $test = $stmt->fetch();

    if (!$test) {
        flash('error', 'Test nie istnieje.');
        redirect(app_url('admin/testy.php'));
    }

    $stmt = $pdo->prepare('SELECT * FROM pytania WHERE test_id = :test_id ORDER BY kolejnosc ASC, id ASC');
    $stmt->execute([':test_id' => $testId]);
    $pytania = $stmt->fetchAll();
    $odpowiedziWgPytania = testy_odpowiedzi($pdo, $pytania);
    $view = 'edycja';
} elseif ($action === 'rozwiaz' && $testId > 0 && !$isFirma) {
    $stmt = $pdo->prepare('SELECT * FROM testy WHERE id = :id AND status = "opublikowany"');
    $stmt->execute([':id' => $testId]);
    $test = $stmt->fetch();

    if (!$test) {
        flash('error', 'Test nie jest dostępny.');
        redirect(app_url('admin/testy.php'));
    }

    // Test można rozwiązać tylko raz – kolejna próba pokazuje zapisany wynik.
    $wynikiUzytkownika = wyniki_uzytkownika($pdo, (int) $user['id']);
    if (isset($wynikiUzytkownika[$testId])) {
        flash('info', 'Ten test możesz rozwiązać tylko raz. Pokazuję zapisany wynik.');
        redirect(app_url('admin/testy.php?akcja=wynik&test_id=' . $testId));
    }

    $blokada = blokujaca_prezentacja(
        (string) $test['tytul'],
        prezentacje_wg_numeru($pdo),
        obejrzane_prezentacje($pdo, (int) $user['id'])
    );

    if ($blokada !== null) {
        flash('warning', 'Zanim rozwiążesz ten test, obejrzyj prezentację „' . $blokada['tytul'] . '”.');
        redirect(app_url('admin/testy.php'));
    }

    $stmt = $pdo->prepare('SELECT * FROM pytania WHERE test_id = :test_id ORDER BY kolejnosc ASC, id ASC');
    $stmt->execute([':test_id' => $testId]);
    $pytania = $stmt->fetchAll();
    $odpowiedziWgPytania = testy_odpowiedzi($pdo, $pytania);
    $view = 'rozwiaz';
} elseif ($action === 'wynik' && $testId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM testy WHERE id = :id');
    $stmt->execute([':id' => $testId]);
    $test = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT * FROM wyniki_testow WHERE user_id = :user_id AND test_id = :test_id ORDER BY id DESC LIMIT 1');
    $stmt->execute([':user_id' => (int) $user['id'], ':test_id' => $testId]);
    $wynik = $stmt->fetch();

    if (!$test || !$wynik) {
        flash('warning', 'Brak wyniku dla tego testu.');
        redirect(app_url('admin/testy.php'));
    }

    $view = 'wynik';
} else {
    $sql = 'SELECT t.*, u.imie,
                   (SELECT COUNT(*) FROM pytania p WHERE p.test_id = t.id) AS liczba_pytan,
                   (SELECT COUNT(*) FROM odpowiedzi o JOIN pytania p2 ON p2.id = o.pytanie_id WHERE p2.test_id = t.id) AS liczba_odpowiedzi
              FROM testy t
              LEFT JOIN users u ON u.id = t.autor_id';
    if (!$isFirma) {
        $sql .= " WHERE t.status = 'opublikowany'";
    }
    $sql .= ' ORDER BY t.id ASC';
    $testy = $pdo->query($sql)->fetchAll();
    $view = 'lista';

    if (!$isFirma) {
        $prezentacjeWgNumeru = prezentacje_wg_numeru($pdo);
        $obejrzanePrezentacje = obejrzane_prezentacje($pdo, (int) $user['id']);
        $wynikiUzytkownika = wyniki_uzytkownika($pdo, (int) $user['id']);

        foreach ($testy as $t) {
            $blokada = blokujaca_prezentacja((string) $t['tytul'], $prezentacjeWgNumeru, $obejrzanePrezentacje);

            if ($blokada !== null) {
                $blokady[(int) $t['id']] = $blokada;
            }
        }
    }
}

$statOpublikowane = 0;
$statSzkice = 0;
$statPytania = 0;
foreach ($testy as $t) {
    if ($t['status'] === 'opublikowany') {
        $statOpublikowane++;
    } else {
        $statSzkice++;
    }
    $statPytania += (int) $t['liczba_pytan'];
}
$statWszystkie = count($testy);

layout_header('Testy', 'testy.php');
?>

<?php if ($view === 'edycja'): ?>
    <?php
    $liczbaPytan = count($pytania);
    $liczbaOdpowiedzi = array_sum(array_map('count', $odpowiedziWgPytania));
    ?>

    <div class="page-toolbar">
        <div>
            <p class="muted">
                Edytujesz test <strong><?= e($test['tytul']) ?></strong>.
                Pytania są widoczne dla użytkowników w kolejności dodania.
            </p>
        </div>
        <div class="page-toolbar-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/testy.php')) ?>">← Wróć do listy testów</a>
        </div>
    </div>

    <section class="grid grid-4">
        <div class="card stat">
            <span class="stat-label">Pytania</span>
            <span class="stat-value"><?= $liczbaPytan ?></span>
            <span class="stat-hint">w tym teście</span>
        </div>
        <div class="card stat">
            <span class="stat-label">Odpowiedzi</span>
            <span class="stat-value"><?= $liczbaOdpowiedzi ?></span>
            <span class="stat-hint">łącznie we wszystkich pytaniach</span>
        </div>
        <div class="card stat">
            <span class="stat-label">Status</span>
            <span class="stat-value stat-value-sm">
                <span class="badge <?= $test['status'] === 'opublikowany' ? 'badge-ok' : 'badge-warn' ?>">
                    <?= $test['status'] === 'opublikowany' ? 'opublikowany' : 'szkic' ?>
                </span>
            </span>
            <span class="stat-hint"><?= $test['status'] === 'opublikowany' ? 'widoczny dla użytkowników' : 'ukryty przed użytkownikami' ?></span>
        </div>
    </section>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Ustawienia testu</h2>
                <p class="card-subtitle">Opis i widoczność. Tytuł jest nadawany automatycznie i nie można go zmienić.</p>
            </div>
        </div>
        <form method="post" action="<?= e(app_url('admin/testy.php')) ?>" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="akcja" value="edytuj_test">
            <input type="hidden" name="test_id" value="<?= (int) $test['id'] ?>">

            <label class="form-field">
                <span>Tytuł (automatyczny)</span>
                <input type="text" value="<?= e($test['tytul']) ?>" readonly style="background: var(--surface-2); color: var(--muted);">
            </label>

            <label class="form-field">
                <span>Status</span>
                <select name="status">
                    <option value="szkic" <?= $test['status'] === 'szkic' ? 'selected' : '' ?>>Szkic – widoczny tylko dla administratora</option>
                    <option value="opublikowany" <?= $test['status'] === 'opublikowany' ? 'selected' : '' ?>>Opublikowany – dostępny dla użytkowników</option>
                </select>
            </label>

            <label class="form-field form-field-full">
                <span>Opis <em class="req">*</em></span>
                <textarea name="opis" placeholder="Krótki opis testu widoczny na liście" required><?= e($test['opis']) ?></textarea>
            </label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Zapisz ustawienia</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Pytania w teście <span class="badge"><?= $liczbaPytan ?></span></h2>
                <p class="card-subtitle">Kliknij przycisk poniżej, aby dodać nowe pytanie w oknie modalnym.</p>
            </div>
            <div class="card-actions">
                <button type="button" class="btn btn-primary" data-modal-open="#modalDodajPytanie" onclick="openModal('modalDodajPytanie')">+ Dodaj pytanie</button>
            </div>
        </div>

    <!-- Modal: Dodaj pytanie -->
    <dialog id="modalDodajPytanie" class="modal">
        <form method="post" action="<?= e(app_url('admin/testy.php')) ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="akcja" value="dodaj_pytanie">
            <input type="hidden" name="test_id" value="<?= (int) $test['id'] ?>">

            <header class="modal-header">
                <h3>Nowe pytanie</h3>
                <button type="button" class="modal-close" data-modal-close aria-label="Zamknij">&times;</button>
            </header>

            <div class="modal-body">
                <label class="form-field form-field-full">
                    <span>Treść pytania <em class="req">*</em></span>
                    <textarea name="tresc" rows="3" placeholder="np. Który dokument potwierdza zawarcie umowy?" required></textarea>
                </label>
            </div>

            <footer class="modal-footer">
                <button type="button" class="btn btn-ghost" data-modal-close>Anuluj</button>
                <button type="submit" class="btn btn-primary">+ Dodaj pytanie</button>
            </footer>
        </form>
    </dialog>

    <?php if ($pytania === []): ?>
            <div class="card card-empty">
                <p><strong>Ten test nie ma jeszcze pytań.</strong></p>
                <p class="muted">Dodaj pierwsze pytanie przyciskiem powyżej, a potem przypisz do niego odpowiedzi.</p>
            </div>
        <?php else: ?>
            <div class="question-list">
            <?php foreach ($pytania as $idx => $pyt): ?>
                <?php
                $pytId = (int) $pyt['id'];
                $odpowiedzi = $odpowiedziWgPytania[$pytId] ?? [];
                $liczbaPoprawnych = 0;
                foreach ($odpowiedzi as $odp) {
                    if ((int) $odp['poprawna'] === 1) {
                        $liczbaPoprawnych++;
                    }
                }
                ?>
                <article class="question-card">
                    <div class="question-head">
                        <span class="question-number"><?= $idx + 1 ?></span>
                        <div class="question-main">
                            <p class="question-title"><?= e($pyt['tresc']) ?></p>
                            <div class="question-meta">
                                <span class="badge"><?= count($odpowiedzi) ?> <?= count($odpowiedzi) === 1 ? 'odpowiedź' : 'odpowiedzi' ?></span>
                                <?php if ($liczbaPoprawnych === 0): ?>
                                    <span class="badge badge-warn">brak poprawnej odpowiedzi</span>
                                <?php elseif ($liczbaPoprawnych === 1): ?>
                                    <span class="badge badge-ok">✓ 1 poprawna</span>
                                <?php else: ?>
                                    <span class="badge badge-ok">✓ <?= $liczbaPoprawnych ?> <?= $liczbaPoprawnych < 5 ? 'poprawne' : 'poprawnych' ?></span>
                                    <span class="badge">wielokrotny wybór</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($isFirma): ?>
                                <span class="question-edit-link">
                                    <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/testy.php?akcja=edytuj&test_id=' . (int) $test['id'] . '#pytanie-' . $pytId)) ?>">Edytuj</a>
                                </span>
                            <?php endif; ?>
                        </div>
                        <form method="post" action="<?= e(app_url('admin/testy.php')) ?>" class="question-actions" data-confirm="Usunąć pytanie wraz z odpowiedziami?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="akcja" value="usun_pytanie">
                            <input type="hidden" name="test_id" value="<?= (int) $test['id'] ?>">
                            <input type="hidden" name="pytanie_id" value="<?= $pytId ?>">
                            <button class="btn btn-ghost btn-sm text-danger" type="submit">Usuń pytanie</button>
                        </form>
                    </div>

                    <?php if ($odpowiedzi === []): ?>
                        <p class="empty-inline">Brak odpowiedzi. Dodaj co najmniej dwie i zaznacz jedną lub kilka jako poprawne.</p>
                    <?php else: ?>
                        <ul class="answer-list">
                            <?php foreach ($odpowiedzi as $odp): ?>
                                <?php $jestPoprawna = (int) $odp['poprawna'] === 1; ?>
                                <li class="answer-item<?= $jestPoprawna ? ' is-correct' : '' ?>">
                                    <span class="answer-mark" aria-hidden="true"><?= $jestPoprawna ? '✓' : '•' ?></span>
                                    <span class="answer-text"><?= e($odp['tresc']) ?></span>
                                    <?php if ($jestPoprawna): ?>
                                        <span class="badge badge-ok">poprawna</span>
                                    <?php endif; ?>
                                    <div class="answer-actions">
                                        <form method="post" action="<?= e(app_url('admin/testy.php')) ?>" class="answer-toggle">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="akcja" value="przelacz_poprawna">
                                            <input type="hidden" name="test_id" value="<?= (int) $test['id'] ?>">
                                            <input type="hidden" name="odpowiedz_id" value="<?= (int) $odp['id'] ?>">
                                            <input type="hidden" name="poprawna" value="<?= $jestPoprawna ? '0' : '1' ?>">
                                            <button class="btn btn-ghost btn-sm" type="submit" title="<?= $jestPoprawna ? 'Oznacz jako niepoprawną' : 'Oznacz jako poprawną' ?>">
                                                <?= $jestPoprawna ? 'Niepoprawna' : 'Poprawna' ?>
                                            </button>
                                        </form>
                                        <form method="post" action="<?= e(app_url('admin/testy.php')) ?>" class="answer-delete" data-confirm="Usunąć tę odpowiedź?">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="akcja" value="usun_odpowiedz">
                                            <input type="hidden" name="test_id" value="<?= (int) $test['id'] ?>">
                                            <input type="hidden" name="odpowiedz_id" value="<?= (int) $odp['id'] ?>">
                                            <button class="btn btn-ghost btn-sm text-danger" type="submit" title="Usuń odpowiedź" aria-label="Usuń odpowiedź">✕</button>
                                        </form>
                                        <?php if ($isFirma): ?>
                                            <span class="answer-edit-link">
                                                <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/testy.php?akcja=edytuj&test_id=' . (int) $test['id'] . '#odpowiedz-' . $odp['id'])) ?>">Edytuj</a>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <form method="post" action="<?= e(app_url('admin/testy.php')) ?>" class="answer-form inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="akcja" value="dodaj_odpowiedz">
                        <input type="hidden" name="test_id" value="<?= (int) $test['id'] ?>">
                        <input type="hidden" name="pytanie_id" value="<?= $pytId ?>">
                        <input type="text" name="tresc" placeholder="Treść odpowiedzi" required>
                        <label class="check-inline">
                            <input type="checkbox" name="poprawna">
                            <span>Poprawna</span>
                        </label>
                        <button class="btn btn-sm btn-primary" type="submit">+ Dodaj</button>
                    </form>
                    <p class="form-hint inline">Możesz dodać wiele odpowiedzi i zaznaczyć kilka jako poprawne. Przy rozwiązywaniu użytkownik musi zaznaczyć wszystkie poprawne odpowiedzi.</p>
                    <button class="btn btn-sm btn-secondary" data-modal-open="#modalDodajOdpowiedź">Dodaj jako popup</button>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Modal: Dodaj odpowiedź -->
    <dialog id="modalDodajOdpowiedź" class="modal">
        <form method="post" action="<?= e(app_url('admin/testy.php')) ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="akcja" value="dodaj_odpowiedz">
            <input type="hidden" name="test_id" value="<?= (int) $test['id'] ?>">
            <input type="hidden" name="pytanie_id" value="<?= $pytId ?>">

            <header class="modal-header">
                <h3>Nowa odpowiedź</h3>
                <button type="button" class="modal-close" data-modal-close aria-label="Zamknij">&times;</button>
            </header>

            <div class="modal-body">
                <label class="form-field form-field-full">
                    <span>Treść odpowiedzi <em class="req">*</em></span>
                    <textarea name="tresc" rows="3" placeholder="np. Poprawna odpowiedź" required></textarea>
                </label>
            </div>

            <footer class="modal-footer">
                <button type="button" class="btn btn-ghost" data-modal-close>Anuluj</button>
                <button type="submit" class="btn btn-primary">+ Dodaj</button>
            </footer>
        </form>
    </dialog>

<?php elseif ($view === 'rozwiaz'): ?>
    <?php $liczbaPytan = count($pytania); ?>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?= e($test['tytul']) ?></h2>
                <?php if (trim((string) $test['opis']) !== ''): ?>
                    <p class="card-subtitle"><?= e($test['opis']) ?></p>
                <?php endif; ?>
            </div>
            <div class="card-actions">
                <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/testy.php')) ?>">← Wróć do listy testów</a>
            </div>
        </div>

        <?php if ($liczbaPytan === 0): ?>
            <div class="empty-state">
                <p><strong>Ten test nie ma jeszcze pytań.</strong></p>
                <p class="muted">Poproś administratora o uzupełnienie testu.</p>
            </div>
        <?php else: ?>
            <div class="test-progress">
                <span id="testProgressLabel">0 / <?= $liczbaPytan ?> odpowiedzi</span>
                <div class="progress">
                    <div class="progress-bar" id="testProgressBar" style="width: 0%"></div>
                </div>
            </div>

            <form method="post" action="<?= e(app_url('admin/testy.php')) ?>" class="test-form" data-test-progress="<?= $liczbaPytan ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="akcja" value="rozwiaz">
                <input type="hidden" name="test_id" value="<?= (int) $test['id'] ?>">

                <div class="question-list">
                    <?php foreach ($pytania as $idx => $pyt): ?>
                        <?php
                        $pytId = (int) $pyt['id'];
                        $odpowiedzi = $odpowiedziWgPytania[$pytId] ?? [];
                        $liczbaPoprawnych = 0;
                        foreach ($odpowiedzi as $odp) {
                            if ((int) $odp['poprawna'] === 1) {
                                $liczbaPoprawnych++;
                            }
                        }
                        $wielokrotny = $liczbaPoprawnych > 1;
                        ?>
                        <article class="question-card question-solve">
                            <div class="question-head">
                                <span class="question-number"><?= $idx + 1 ?></span>
                                <div class="question-main">
                                    <p class="question-title"><?= e($pyt['tresc']) ?></p>
                                    <?php if ($wielokrotny): ?>
                                        <p class="question-hint">Zaznacz wszystkie poprawne odpowiedzi.</p>
                                    <?php endif; ?>
                                </div>
                                <span class="question-counter muted small"><?= $idx + 1 ?> z <?= $liczbaPytan ?></span>
                            </div>

                            <?php if ($odpowiedzi === []): ?>
                                <p class="empty-inline">To pytanie nie ma jeszcze odpowiedzi.</p>
                            <?php else: ?>
                                <div class="answer-options">
                                    <?php foreach ($odpowiedzi as $i => $odp): ?>
                                        <label class="answer-option">
                                            <input
                                                type="<?= $wielokrotny ? 'checkbox' : 'radio' ?>"
                                                name="odpowiedz[<?= $pytId ?>]<?= $wielokrotny ? '[]' : '' ?>"
                                                value="<?= (int) $odp['id'] ?>"
                                                data-answer
                                            >
                                            <span class="answer-letter"><?= $i < 26 ? chr(65 + $i) : (string) ($i + 1) ?></span>
                                            <span class="answer-text"><?= e($odp['tresc']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div class="test-submit">
                    <button class="btn btn-primary" type="submit">Zakończ test</button>
                    <span class="muted small">Wynik zobaczysz od razu po zakończeniu. Możesz też wrócić do testu później.</span>
                </div>
            </form>
        <?php endif; ?>
    </div>

<?php elseif ($view === 'wynik'): ?>
    <?php
    $poprawne = (int) $wynik['wynik'];
    $maks = (int) $wynik['maks'];
    $procent = (float) $wynik['procent'];
    $procentZaokraglony = (int) round($procent);

    if ($procent >= 100) {
        $klasa = 'badge-ok';
        $naglowek = 'Perfekcyjnie!';
        $komunikat = 'Wszystkie odpowiedzi są poprawne. Świetna robota!';
    } elseif ($procent >= 80) {
        $klasa = 'badge-ok';
        $naglowek = 'Świetny wynik!';
        $komunikat = 'Materiał opanowany bardzo dobrze. Warto rzucić okiem na pojedyncze pytania.';
    } elseif ($procent >= 50) {
        $klasa = 'badge-warn';
        $naglowek = 'Dobry początek';
        $komunikat = 'Część materiału wymaga powtórki. Wróć do slajdów i spróbuj ponownie.';
    } else {
        $klasa = 'badge-off';
        $naglowek = 'Warto powtórzyć materiał';
        $komunikat = 'Przejrzyj materiały i podejdź do testu jeszcze raz – z każdą próbą będzie lepiej.';
    }
    ?>

    <div class="page-toolbar">
        <p class="muted">Wynik testu <strong><?= e($test['tytul']) ?></strong></p>
        <div class="page-toolbar-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/testy.php')) ?>">← Wróć do listy testów</a>
        </div>
    </div>

    <div class="card result-card">
        <div class="result-hero">
            <span class="badge <?= $klasa ?>"><?= e($naglowek) ?></span>
            <div class="result-score">
                <?= $poprawne ?><span> / <?= $maks ?></span>
            </div>
            <p class="result-percent"><?= $procentZaokraglony ?>% poprawnych odpowiedzi</p>

            <div class="progress result-progress">
                <div class="progress-bar <?= $procent >= 50 ? '' : 'progress-bar-warn' ?>" style="width: <?= max(0, min(100, $procent)) ?>%"></div>
            </div>

            <p class="result-message"><?= e($komunikat) ?></p>

            <div class="result-actions">
                <a class="btn btn-primary" href="<?= e(app_url('admin/testy.php?akcja=rozwiaz&test_id=' . (int) $test['id'])) ?>">Spróbuj ponownie</a>
                <a class="btn btn-ghost" href="<?= e(app_url('admin/testy.php')) ?>">Wróć do listy testów</a>
            </div>

            <p class="muted small">Rozwiązano: <?= e(fmt_date($wynik['created_at'])) ?></p>
        </div>
    </div>

<?php else: ?>
    <?php if ($isFirma): ?>
        <section class="grid grid-4">
            <div class="card stat">
                <span class="stat-label">Wszystkie testy</span>
                <span class="stat-value"><?= count($testy) ?></span>
                <span class="stat-hint">w systemie</span>
            </div>
            <div class="card stat">
                <span class="stat-label">Opublikowane</span>
                <span class="stat-value"><?= $statOpublikowane ?></span>
                <span class="stat-hint">widoczne dla użytkowników</span>
            </div>
            <div class="card stat">
                <span class="stat-label">Szkice</span>
                <span class="stat-value"><?= $statSzkice ?></span>
                <span class="stat-hint">niewidoczne dla użytkowników</span>
            </div>
            <div class="card stat">
                <span class="stat-label">Pytania</span>
                <span class="stat-value"><?= $statPytania ?></span>
                <span class="stat-hint">łącznie we wszystkich testach</span>
            </div>
        </section>

        <div class="card" id="nowy-test">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Nowy test</h2>
                    <p class="card-subtitle">Utwórz test, a następnie dodaj pytania i odpowiedzi. Nowy test jest od razu opublikowany.</p>
                </div>
            </div>
            <form method="post" action="<?= e(app_url('admin/testy.php')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="akcja" value="dodaj_test">

                <label class="form-field">
                    <span>Tytuł (automatyczny)</span>
                    <input type="text" value="Test <?= (int) $statWszystkie + 1 ?>" readonly style="background: var(--surface-2); color: var(--muted);">
                </label>

                <label class="form-field form-field-full">
                    <span>Opis <em class="req">*</em></span>
                    <textarea name="opis" placeholder="Krótki opis testu widoczny na liście obok tytułu" required></textarea>
                </label>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">+ Utwórz test</button>
                    <span class="form-hint">Po utworzeniu przejdziesz od razu do dodawania pytań.</span>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Lista testów</h2>
                    <p class="card-subtitle">Kliknij „Pytania i odpowiedzi”, aby edytować zawartość testu.</p>
                </div>
            </div>

            <?php if ($testy === []): ?>
                <div class="empty-state">
                    <p><strong>Nie masz jeszcze żadnego testu.</strong></p>
                    <p class="muted">Utwórz pierwszy test w formularzu powyżej.</p>
                    <a class="btn btn-primary" href="#nowy-test">Utwórz pierwszy test</a>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Test</th>
                                <th>Pytania</th>
                                <th>Status</th>
                                <th>Autor</th>
                                <th>Utworzono</th>
                                <th class="th-actions">Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($testy as $t): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($t['tytul']) ?></strong>
                                        <?php if (trim((string) $t['opis']) !== ''): ?>
                                            <br><span class="muted small"><?= e($t['opis']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge"><?= (int) $t['liczba_pytan'] ?></span>
                                        <span class="muted small"><?= (int) $t['liczba_odpowiedzi'] ?> odp.</span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $t['status'] === 'opublikowany' ? 'badge-ok' : 'badge-warn' ?>">
                                            <?= $t['status'] === 'opublikowany' ? 'opublikowany' : 'szkic' ?>
                                        </span>
                                        <?php if ($t['status'] === 'opublikowany' && (int) $t['liczba_pytan'] === 0): ?>
                                            <span class="badge badge-off">brak pytań</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($t['imie'] ?? '—') ?></td>
                                    <td class="muted small"><?= e(fmt_date($t['created_at'])) ?></td>
                                    <td>
                                        <div class="td-actions">
                                            <a class="btn btn-primary btn-sm" href="<?= e(app_url('admin/testy.php?akcja=edytuj&test_id=' . (int) $t['id'])) ?>">Pytania i odpowiedzi</a>
                                            <form method="post" action="<?= e(app_url('admin/testy.php')) ?>" class="form-inline" data-confirm="Usunąć test wraz z pytaniami i wynikami?">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="akcja" value="usun_test">
                                                <input type="hidden" name="test_id" value="<?= (int) $t['id'] ?>">
                                                <button class="btn btn-ghost btn-sm text-danger" type="submit">Usuń</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="page-toolbar">
            <p class="muted">
                Wybierz test i sprawdź swoją wiedzę. Widoczne są tylko testy opublikowane przez administratora.
                Test N odblokujesz po obejrzeniu prezentacji N, a prezentacja N – po rozwiązaniu testu N-1.
            </p>
            <div class="page-toolbar-actions">
                <span class="badge"><?= count($testy) ?> <?= count($testy) === 1 ? 'test' : 'testów' ?></span>
            </div>
        </div>

        <?php if ($testy === []): ?>
            <div class="card card-empty">
                <p><strong>Brak dostępnych testów.</strong></p>
                <p class="muted">Wróć tutaj, gdy administrator opublikuje nowy test.</p>
            </div>
        <?php else: ?>
            <section class="grid grid-slides">
                <?php foreach ($testy as $t): ?>
                    <?php $blokada = $blokady[(int) $t['id']] ?? null; ?>
                    <article class="card test-card">
                        <div class="test-card-top">
                            <span class="badge badge-firma">Test</span>
                            <?php if ($blokada !== null): ?>
                                <span class="badge badge-off">zablokowany</span>
                            <?php endif; ?>
                            <span class="muted small"><?= (int) $t['liczba_pytan'] ?> <?= (int) $t['liczba_pytan'] === 1 ? 'pytanie' : 'pytań' ?></span>
                        </div>
                        <h3><?= e($t['tytul']) ?></h3>
                        <?php if (trim((string) $t['opis']) !== ''): ?>
                            <p class="muted small"><?= e($t['opis']) ?></p>
                        <?php else: ?>
                            <p class="muted small">Bez opisu</p>
                        <?php endif; ?>
                        <?php if ($blokada !== null): ?>
                            <p class="lock-note">
                                🔒 Najpierw obejrzyj prezentację <strong><?= e($blokada['tytul']) ?></strong>,
                                aby odblokować ten test.
                            </p>
                            <div class="test-card-meta">
                                <a class="btn btn-primary btn-sm" href="<?= e(app_url('admin/podglad.php?id=' . (int) $blokada['id'])) ?>">
                                    ▶ Odtwórz <?= e($blokada['tytul']) ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="test-card-meta">
                                <a class="btn btn-primary btn-sm" href="<?= e(app_url('admin/testy.php?akcja=rozwiaz&test_id=' . (int) $t['id'])) ?>">
                                    Rozwiąż test →
                                </a>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<?php layout_footer(); ?>
