<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/_layout.php';

$pdo = db();
$action = $_GET['akcja'] ?? ($_POST['akcja'] ?? '');
$testId = (int) ($_GET['test_id'] ?? ($_POST['test_id'] ?? 0));
$user = current_user();
$now = date('Y-m-d H:i:s');
$isFirma = in_group(GROUP_FIRMA);

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    if ($action === 'dodaj_test') {
        require_group(GROUP_FIRMA);
        $tytul = trim((string) ($_POST['tytul'] ?? ''));
        $opis = trim((string) ($_POST['opis'] ?? ''));

        if ($tytul === '') {
            flash_set('error', 'Tytuł testu jest wymagany.');
            header('Location: ' . app_url('admin/testy.php'));
            exit;
        }

        $stmt = $pdo->prepare('INSERT INTO testy (tytul, opis, status, autor_id, created_at, updated_at) VALUES (:tytul, :opis, "szkic", :autor_id, :now, :now)');
        $stmt->execute([':tytul' => $tytul, ':opis' => $opis, ':autor_id' => (int) $user['id'], ':now' => $now]);
        $newId = (int) $pdo->lastInsertId();
        flash_set('success', 'Test dodany.');
        header('Location: ' . app_url('admin/testy.php?akcja=edytuj&test_id=' . $newId));
        exit;
    }

    if ($action === 'edytuj_test') {
        require_group(GROUP_FIRMA);
        $tytul = trim((string) ($_POST['tytul'] ?? ''));
        $opis = trim((string) ($_POST['opis'] ?? ''));
        $status = $_POST['status'] ?? 'szkic';
        if (!in_array($status, ['szkic', 'opublikowany'], true)) {
            $status = 'szkic';
        }

        if ($tytul === '') {
            flash_set('error', 'Tytuł testu jest wymagany.');
            header('Location: ' . app_url('admin/testy.php?akcja=edytuj&test_id=' . $testId));
            exit;
        }

        $stmt = $pdo->prepare('UPDATE testy SET tytul = :tytul, opis = :opis, status = :status, updated_at = :now WHERE id = :id');
        $stmt->execute([':tytul' => $tytul, ':opis' => $opis, ':status' => $status, ':now' => $now, ':id' => $testId]);
        flash_set('success', 'Test zaktualizowany.');
        header('Location: ' . app_url('admin/testy.php?akcja=edytuj&test_id=' . $testId));
        exit;
    }

    if ($action === 'usun_test' && $testId > 0) {
        require_group(GROUP_FIRMA);
        $stmt = $pdo->prepare('DELETE FROM testy WHERE id = :id');
        $stmt->execute([':id' => $testId]);
        flash_set('success', 'Test usunięty.');
        header('Location: ' . app_url('admin/testy.php'));
        exit;
    }

    if ($action === 'dodaj_pytanie' && $testId > 0) {
        require_group(GROUP_FIRMA);
        $tresc = trim((string) ($_POST['tresc'] ?? ''));
        if ($tresc === '') {
            flash_set('error', 'Treść pytania jest wymagana.');
            header('Location: ' . app_url('admin/testy.php?akcja=edytuj&test_id=' . $testId));
            exit;
        }
        $max = (int) $pdo->query('SELECT COALESCE(MAX(kolejnosc), -1) + 1 AS next FROM pytania WHERE test_id = ' . $testId)->fetchColumn();
        $stmt = $pdo->prepare('INSERT INTO pytania (test_id, tresc, kolejnosc) VALUES (:test_id, :tresc, :kolejnosc)');
        $stmt->execute([':test_id' => $testId, ':tresc' => $tresc, ':kolejnosc' => $max]);
        flash_set('success', 'Pytanie dodane.');
        header('Location: ' . app_url('admin/testy.php?akcja=edytuj&test_id=' . $testId));
        exit;
    }

    if ($action === 'usun_pytanie') {
        require_group(GROUP_FIRMA);
        $pytanieId = (int) ($_POST['pytanie_id'] ?? 0);
        if ($pytanieId > 0) {
            $stmt = $pdo->prepare('DELETE FROM pytania WHERE id = :id');
            $stmt->execute([':id' => $pytanieId]);
            flash_set('success', 'Pytanie usunięte.');
        }
        header('Location: ' . app_url('admin/testy.php?akcja=edytuj&test_id=' . $testId));
        exit;
    }

    if ($action === 'dodaj_odpowiedz') {
        require_group(GROUP_FIRMA);
        $pytanieId = (int) ($_POST['pytanie_id'] ?? 0);
        $tresc = trim((string) ($_POST['tresc'] ?? ''));
        $poprawna = isset($_POST['poprawna']) ? 1 : 0;

        if ($pytanieId === 0 || $tresc === '') {
            flash_set('error', 'Treść odpowiedzi jest wymagana.');
            header('Location: ' . app_url('admin/testy.php?akcja=edytuj&test_id=' . $testId));
            exit;
        }

        if ($poprawna === 1) {
            $pdo->prepare('UPDATE odpowiedzi SET poprawna = 0 WHERE pytanie_id = :pytanie_id')->execute([':pytanie_id' => $pytanieId]);
        }

        $max = (int) $pdo->query('SELECT COALESCE(MAX(kolejnosc), -1) + 1 AS next FROM odpowiedzi WHERE pytanie_id = ' . $pytanieId)->fetchColumn();
        $stmt = $pdo->prepare('INSERT INTO odpowiedzi (pytanie_id, tresc, poprawna, kolejnosc) VALUES (:pytanie_id, :tresc, :poprawna, :kolejnosc)');
        $stmt->execute([':pytanie_id' => $pytanieId, ':tresc' => $tresc, ':poprawna' => $poprawna, ':kolejnosc' => $max]);
        flash_set('success', 'Odpowiedź dodana.');
        header('Location: ' . app_url('admin/testy.php?akcja=edytuj&test_id=' . $testId));
        exit;
    }

    if ($action === 'usun_odpowiedz') {
        require_group(GROUP_FIRMA);
        $odpowiedzId = (int) ($_POST['odpowiedz_id'] ?? 0);
        if ($odpowiedzId > 0) {
            $stmt = $pdo->prepare('DELETE FROM odpowiedzi WHERE id = :id');
            $stmt->execute([':id' => $odpowiedzId]);
            flash_set('success', 'Odpowiedź usunięta.');
        }
        header('Location: ' . app_url('admin/testy.php?akcja=edytuj&test_id=' . $testId));
        exit;
    }

    if ($action === 'rozwiaz') {
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
            $wybrana = isset($odpowiedzi[$pid]) ? (int) $odpowiedzi[$pid] : 0;
            if ($wybrana > 0) {
                $ok = (int) $pdo->query('SELECT COUNT(*) FROM odpowiedzi WHERE id = ' . $wybrana . ' AND pytanie_id = ' . $pid . ' AND poprawna = 1')->fetchColumn();
                if ($ok > 0) {
                    $poprawne++;
                }
            }
        }

        $procent = $maks > 0 ? round(($poprawne / $maks) * 100, 2) : 0;
        $stmt = $pdo->prepare('INSERT INTO wyniki_testow (user_id, test_id, wynik, maks, procent, created_at) VALUES (:user_id, :test_id, :wynik, :maks, :procent, :now)');
        $stmt->execute([':user_id' => (int) $user['id'], ':test_id' => $testId, ':wynik' => $poprawne, ':maks' => $maks, ':procent' => $procent, ':now' => $now]);

        flash_set('success', "Wynik: $poprawne / $maks ($procent%)");
        header('Location: ' . app_url('admin/testy.php'));
        exit;
    }
}

layout_header('Testy', 'testy.php');
?>

<?php if ($action === 'edytuj' && $testId > 0): ?>
    <?php
    $test = $pdo->prepare('SELECT * FROM testy WHERE id = :id');
    $test->execute([':id' => $testId]);
    $test = $test->fetch();
    if (!$test) {
        flash_set('error', 'Test nie istnieje.');
        header('Location: ' . app_url('admin/testy.php'));
        exit;
    }
    $pytania = $pdo->prepare('SELECT * FROM pytania WHERE test_id = :test_id ORDER BY kolejnosc ASC, id ASC');
    $pytania->execute([':test_id' => $testId]);
    $pytania = $pytania->fetchAll();
    ?>
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Edycja testu</h2>
                <p class="card-subtitle">Zarządzaj pytaniami i odpowiedziami</p>
            </div>
            <div class="card-actions">
                <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/testy.php')) ?>">← Powrót do listy</a>
            </div>
        </div>
        <div class="card-body">
            <form method="post" action="<?= e(app_url('admin/testy.php')) ?>" class="form-grid">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="akcja" value="edytuj_test">
                <input type="hidden" name="test_id" value="<?= (int) $test['id'] ?>">
                <label class="form-field">
                    <span>Tytuł testu</span>
                    <input class="input" type="text" name="tytul" value="<?= e($test['tytul']) ?>" required>
                </label>
                <label class="form-field form-field-full">
                    <span>Opis</span>
                    <textarea class="textarea" name="opis"><?= e($test['opis']) ?></textarea>
                </label>
                <label class="form-field">
                    <span>Status</span>
                    <select class="select" name="status">
                        <option value="szkic" <?= $test['status'] === 'szkic' ? 'selected' : '' ?>>Szkic</option>
                        <option value="opublikowany" <?= $test['status'] === 'opublikowany' ? 'selected' : '' ?>>Opublikowany</option>
                    </select>
                </label>
                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Zapisz zmiany</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Pytania</h2>
                <p class="card-subtitle">Dodaj pytania i odpowiedzi</p>
            </div>
        </div>
        <div class="card-body">
            <form method="post" action="<?= e(app_url('admin/testy.php')) ?>" class="form-grid">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="akcja" value="dodaj_pytanie">
                <input type="hidden" name="test_id" value="<?= (int) $test['id'] ?>">
                <label class="form-field form-field-full">
                    <span>Treść pytania</span>
                    <input class="input" type="text" name="tresc" placeholder="Wpisz treść pytania" required>
                </label>
                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Dodaj pytanie</button>
                </div>
            </form>

            <div class="mt-4">
                <?php if (empty($pytania)): ?>
                    <p class="muted">Brak pytań. Dodaj pierwsze pytanie powyżej.</p>
                <?php else: ?>
                    <?php foreach ($pytania as $idx => $pyt): ?>
                        <div class="card" style="margin-top: 1rem;">
                            <div class="card-header">
                                <div>
                                    <strong>Pytanie <?= $idx + 1 ?>.</strong> <?= e($pyt['tresc']) ?>
                                </div>
                                <div class="card-actions">
                                    <form method="post" action="<?= e(app_url('admin/testy.php')) ?>" onsubmit="return confirm('Usunąć pytanie?');">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="akcja" value="usun_pytanie">
                                        <input type="hidden" name="test_id" value="<?= (int) $test['id'] ?>">
                                        <input type="hidden" name="pytanie_id" value="<?= (int) $pyt['id'] ?>">
                                        <button class="btn btn-ghost btn-sm btn-danger" type="submit">Usuń pytanie</button>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php
                                $odpowiedzi = $pdo->prepare('SELECT * FROM odpowiedzi WHERE pytanie_id = :pytanie_id ORDER BY kolejnosc ASC, id ASC');
                                $odpowiedzi->execute([':pytanie_id' => $pyt['id']]);
                                $odpowiedzi = $odpowiedzi->fetchAll();
                                ?>
                                <ul>
                                    <?php foreach ($odpowiedzi as $odp): ?>
                                        <li style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem;">
                                            <span><?= $odp['poprawna'] ? '✓' : '•' ?></span>
                                            <span style="flex:1;"><?= e($odp['tresc']) ?></span>
                                            <form method="post" action="<?= e(app_url('admin/testy.php')) ?>" onsubmit="return confirm('Usunąć odpowiedź?');">
                                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="akcja" value="usun_odpowiedz">
                                                <input type="hidden" name="test_id" value="<?= (int) $test['id'] ?>">
                                                <input type="hidden" name="odpowiedz_id" value="<?= (int) $odp['id'] ?>">
                                                <button class="btn btn-ghost btn-sm" type="submit">Usuń</button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>

                                <form method="post" action="<?= e(app_url('admin/testy.php')) ?>" class="form-inline" style="margin-top:1rem; gap:0.5rem; flex-wrap:wrap;">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="akcja" value="dodaj_odpowiedz">
                                    <input type="hidden" name="test_id" value="<?= (int) $test['id'] ?>">
                                    <input type="hidden" name="pytanie_id" value="<?= (int) $pyt['id'] ?>">
                                    <input class="input" type="text" name="tresc" placeholder="Treść odpowiedzi" required style="flex:1; min-width:200px;">
                                    <label style="display:flex; align-items:center; gap:0.25rem;">
                                        <input type="checkbox" name="poprawna"> Poprawna
                                    </label>
                                    <button class="btn btn-ghost btn-sm" type="submit">Dodaj odpowiedź</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <?php if ($isFirma): ?>
        <div class="card">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Testy</h2>
                    <p class="card-subtitle">Tworzenie i zarządzanie testami</p>
                </div>
            </div>
            <div class="card-body">
                <form method="post" action="<?= e(app_url('admin/testy.php')) ?>" class="form-grid">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="akcja" value="dodaj_test">
                    <label class="form-field">
                        <span>Tytuł testu</span>
                        <input class="input" type="text" name="tytul" placeholder="np. Test z materiału" required>
                    </label>
                    <label class="form-field form-field-full">
                        <span>Opis</span>
                        <textarea class="textarea" name="opis" placeholder="Krótki opis testu"></textarea>
                    </label>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Dodaj test</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?= $isFirma ? 'Lista testów' : 'Dostępne testy' ?></h2>
                <p class="card-subtitle"><?= $isFirma ? '' : 'Rozwiąż testy' ?></p>
            </div>
        </div>
        <div class="card-body">
            <?php
            if ($isFirma) {
                $testy = $pdo->query('SELECT t.*, u.imie FROM testy t LEFT JOIN users u ON u.id = t.autor_id ORDER BY t.id DESC')->fetchAll();
            } else {
                $stmt = $pdo->prepare('SELECT t.*, u.imie FROM testy t LEFT JOIN users u ON u.id = t.autor_id WHERE t.status = "opublikowany" ORDER BY t.id DESC');
                $stmt->execute();
                $testy = $stmt->fetchAll();
            }
            ?>
            <?php if (empty($testy)): ?>
                <p class="muted">Brak testów.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tytuł</th>
                                <?php if ($isFirma): ?><th>Status</th><?php endif; ?>
                                <th>Autor</th>
                                <th>Utworzony</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($testy as $t): ?>
                                <tr>
                                    <td><?= (int) $t['id'] ?></td>
                                    <td>
                                        <strong><?= e($t['tytul']) ?></strong>
                                        <?php if ($t['opis']): ?><br><span class="muted"><?= e($t['opis']) ?></span><?php endif; ?>
                                    </td>
                                    <?php if ($isFirma): ?><td><span class="badge"><?= e($t['status']) ?></span></td><?php endif; ?>
                                    <td><?= e($t['imie'] ?? '-') ?></td>
                                    <td><?= e($t['created_at']) ?></td>
                                    <td class="text-right">
                                        <?php if ($isFirma): ?>
                                            <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/testy.php?akcja=edytuj&test_id=' . (int) $t['id'])) ?>">Edytuj pytania/odpowiedzi</a>
                                            <form method="post" action="<?= e(app_url('admin/testy.php')) ?>" style="display:inline;" onsubmit="return confirm('Usunąć test?');">
                                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="akcja" value="usun_test">
                                                <input type="hidden" name="test_id" value="<?= (int) $t['id'] ?>">
                                                <button class="btn btn-ghost btn-sm btn-danger" type="submit">Usuń</button>
                                            </form>
                                        <?php else: ?>
                                            <a class="btn btn-primary btn-sm" href="<?= e(app_url('admin/testy.php?akcja=rozwiaz&test_id=' . (int) $t['id'])) ?>">Rozwiąż test</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($action === 'rozwiaz' && !$isFirma && $testId > 0): ?>
    <?php
    $test = $pdo->prepare('SELECT * FROM testy WHERE id = :id AND status = "opublikowany"');
    $test->execute([':id' => $testId]);
    $test = $test->fetch();
    if (!$test) {
        flash_set('error', 'Test nie jest dostępny.');
        header('Location: ' . app_url('admin/testy.php'));
        exit;
    }
    $pytania = $pdo->prepare('SELECT * FROM pytania WHERE test_id = :test_id ORDER BY kolejnosc ASC, id ASC');
    $pytania->execute([':test_id' => $testId]);
    $pytania = $pytania->fetchAll();
    ?>
    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title"><?= e($test['tytul']) ?></h2>
                <?php if ($test['opis']): ?><p class="card-subtitle"><?= e($test['opis']) ?></p><?php endif; ?>
            </div>
            <div class="card-actions">
                <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/testy.php')) ?>">← Powrót do listy</a>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($pytania)): ?>
                <p class="muted">Ten test nie ma pytań.</p>
            <?php else: ?>
                <form method="post" action="<?= e(app_url('admin/testy.php')) ?>">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="akcja" value="rozwiaz">
                    <input type="hidden" name="test_id" value="<?= (int) $test['id'] ?>">
                    <?php foreach ($pytania as $idx => $pyt): ?>
                        <div class="card" style="margin-bottom:1rem;">
                            <div class="card-header">
                                <strong>Pytanie <?= $idx + 1 ?>.</strong> <?= e($pyt['tresc']) ?>
                            </div>
                            <div class="card-body">
                                <?php
                                $odpowiedzi = $pdo->prepare('SELECT * FROM odpowiedzi WHERE pytanie_id = :pytanie_id ORDER BY kolejnosc ASC, id ASC');
                                $odpowiedzi->execute([':pytanie_id' => $pyt['id']]);
                                $odpowiedzi = $odpowiedzi->fetchAll();
                                ?>
                                <?php foreach ($odpowiedzi as $odp): ?>
                                    <label style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem;">
                                        <input type="radio" name="odpowiedz[<?= (int) $pyt['id'] ?>]" value="<?= (int) $odp['id'] ?>">
                                        <span><?= e($odp['tresc']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <button class="btn btn-primary" type="submit">Zakończ test</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php layout_footer(); ?>

<?php layout_footer(); ?>
