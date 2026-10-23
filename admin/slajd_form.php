<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

require_login();
require_group(GROUP_FIRMA);

$pdo = db();
$user = current_user();

$UKLADY = [
    'tekst-obraz'  => 'Tekst + obraz',
    'obraz-tekst'  => 'Obraz + tekst',
    'tylko-tekst'  => 'Tylko tekst',
    'cytat'         => 'Cytat',
];

$id    = (int) ($_GET['id'] ?? 0);
$slajd = null;

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM slajdy WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $slajd = $stmt->fetch() ?: null;
}

$prezentacjaId = (int) ($_GET['prezentacja_id'] ?? ($_POST['prezentacja_id'] ?? 0));
$prezentacja = null;

if ($prezentacjaId > 0) {
    $stmt = $pdo->prepare('SELECT id, tytul FROM prezentacje WHERE id = :id');
    $stmt->execute([':id' => $prezentacjaId]);
    $prezentacja = $stmt->fetch() ?: null;

    if (!$prezentacja) {
        $prezentacjaId = 0;
    }
}

$values = [
    'tytul'    => $slajd['tytul'] ?? '',
    'tresc'    => $slajd['tresc'] ?? '',
    'uklad'    => $slajd['uklad'] ?? 'tekst-obraz',
    'przycisk' => $slajd['przycisk'] ?? '',
    'link'     => $slajd['link'] ?? '',
    'status'   => $slajd['status'] ?? 'opublikowany',
    'obraz'    => $slajd['obraz'] ?? '',
];

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf'] ?? null);

    $values['tytul']    = trim((string) ($_POST['tytul'] ?? ''));
    $values['tresc']    = trim((string) ($_POST['tresc'] ?? ''));
    $values['uklad']    = (string) ($_POST['uklad'] ?? 'tekst-obraz');
    $values['przycisk'] = trim((string) ($_POST['przycisk'] ?? ''));
    $values['link']     = trim((string) ($_POST['link'] ?? ''));
    $values['status']   = (string) ($_POST['status'] ?? 'opublikowany');

    if (!isset($UKLADY[$values['uklad']])) {
        $values['uklad'] = 'tekst-obraz';
    }
    if (!in_array($values['status'], ['szkic', 'opublikowany'], true)) {
        $values['status'] = 'szkic';
    }

    /* --- Upload obrazu (materiał = slajd, obraz jest jedynym załącznikiem) */
    $nowyObraz = $values['obraz'];
    if (isset($_FILES['obraz']) && $_FILES['obraz']['error'] !== UPLOAD_ERR_NO_FILE) {
        $plik = $_FILES['obraz'];

        if ($plik['error'] !== UPLOAD_ERR_OK) {
            $error = 'Błąd przesyłania pliku.';
        } elseif ($plik['size'] > MAX_UPLOAD_BYTES) {
            $error = 'Obraz jest za duży (maks. ' . (int) (MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB).';
        } else {
            $info = @getimagesize($plik['tmp_name']);
            $mime = $info['mime'] ?? ($plik['type'] ?? '');

            if (!isset(ALLOWED_IMAGES[$mime])) {
                $error = 'Dozwolone są wyłącznie pliki: JPG, PNG, GIF, WEBP.';
            } elseif (!is_dir(UPLOAD_PATH) && !@mkdir(UPLOAD_PATH, 0777, true)) {
                $error = 'Nie udało się utworzyć katalogu na pliki.';
            } else {
                $nazwa = bin2hex(random_bytes(16)) . '.' . ALLOWED_IMAGES[$mime];
                if (@move_uploaded_file($plik['tmp_name'], UPLOAD_PATH . '/' . $nazwa)) {
                    // Usunięcie poprzedniego obrazu.
                    if ($nowyObraz !== '' && is_file(UPLOAD_PATH . '/' . $nowyObraz)) {
                        @unlink(UPLOAD_PATH . '/' . $nowyObraz);
                    }
                    $nowyObraz = $nazwa;
                } else {
                    $error = 'Nie udało się zapisać pliku.';
                }
            }
        }
    }

    if ($error === '') {
        if ($values['tytul'] === '') {
            $error = 'Tytuł slajdu jest wymagany.';
        } else {
            $now = date('Y-m-d H:i:s');

            if ($id > 0 && $slajd) {
                $pdo->prepare(
                    'UPDATE slajdy SET tytul = :t, tresc = :tr, obraz = :o, uklad = :u, przycisk = :p,
                            link = :l, status = :s, updated_at = :n
                     WHERE id = :id'
                )->execute([
                    ':t'  => $values['tytul'],
                    ':tr' => $values['tresc'],
                    ':o'  => $nowyObraz,
                    ':u'  => $values['uklad'],
                    ':p'  => $values['przycisk'],
                    ':l'  => $values['link'],
                    ':s'  => $values['status'],
                    ':n'  => $now,
                    ':id' => $id,
                ]);
                log_action('zaktualizowano_slajd', $values['tytul'], (int) $user['id']);
                flash('success', 'Slajd został zaktualizowany.');
            } else {
                $max = $pdo->query('SELECT COALESCE(MAX(kolejnosc), 0) FROM slajdy')->fetchColumn();
                $pdo->prepare(
                    'INSERT INTO slajdy (tytul, tresc, obraz, uklad, przycisk, link, status, kolejnosc, autor_id, created_at, updated_at)
                     VALUES (:t, :tr, :o, :u, :p, :l, :s, :k, :a, :n, :n)'
                )->execute([
                    ':t'  => $values['tytul'],
                    ':tr' => $values['tresc'],
                    ':o'  => $nowyObraz,
                    ':u'  => $values['uklad'],
                    ':p'  => $values['przycisk'],
                    ':l'  => $values['link'],
                    ':s'  => $values['status'],
                    ':k'  => (int) $max + 1,
                    ':a'  => (int) $user['id'],
                    ':n'  => $now,
                ]);
                $nowySlajdId = (int) $pdo->lastInsertId();

                if ($prezentacjaId > 0 && $prezentacja) {
                    $stmt = $pdo->prepare(
                        'SELECT COALESCE(MAX(kolejnosc), 0) FROM prezentacje_slajdy WHERE prezentacja_id = :id'
                    );
                    $stmt->execute([':id' => $prezentacjaId]);
                    $kolejnoscPrezentacji = (int) $stmt->fetchColumn();

                    $pdo->prepare(
                        'INSERT OR IGNORE INTO prezentacje_slajdy (prezentacja_id, slajd_id, kolejnosc)
                         VALUES (:prezentacja_id, :slajd_id, :kolejnosc)'
                    )->execute([
                        ':prezentacja_id' => $prezentacjaId,
                        ':slajd_id'       => $nowySlajdId,
                        ':kolejnosc'      => $kolejnoscPrezentacji + 1,
                    ]);

                    log_action('dodano_slajd', $values['tytul'], (int) $user['id']);
                    flash('success', 'Slajd został dodany i przypisany do prezentacji „' . $prezentacja['tytul'] . '”.');
                    redirect(app_url('admin/prezentacje.php?akcja=edytuj&id=' . $prezentacjaId));
                }

                log_action('dodano_slajd', $values['tytul'], (int) $user['id']);
                flash('success', 'Slajd został dodany.');
            }

            redirect(app_url('admin/materialy.php'));
        }
    }
}

$backUrl = $prezentacjaId > 0
    ? app_url('admin/prezentacje.php?akcja=edytuj&id=' . $prezentacjaId)
    : app_url('admin/materialy.php');
$backLabel = $prezentacjaId > 0 ? '← Wróć do prezentacji' : '← Wróć do listy';

layout_header($id > 0 ? 'Edycja slajdu' : 'Nowy slajd', $prezentacjaId > 0 ? 'prezentacje.php' : 'materialy.php');
?>

<div class="page-toolbar">
    <p class="muted">
        <?php if ($prezentacja): ?>
            Nowy slajd zostanie dodany do prezentacji <strong><?= e($prezentacja['tytul']) ?></strong>.
        <?php endif; ?>
        Materiał wprowadzany jest wyłącznie jako slajd: tytuł, treść, obraz (JPG/PNG/GIF/WEBP,
        maks. 5 MB), układ i opcjonalny przycisk.
    </p>
    <div class="page-toolbar-actions">
        <a class="btn btn-ghost btn-sm" href="<?= e($backUrl) ?>"><?= e($backLabel) ?></a>
    </div>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<section class="card">
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <?= csrf_field() ?>
        <?php if ($prezentacjaId > 0): ?>
            <input type="hidden" name="prezentacja_id" value="<?= $prezentacjaId ?>">
        <?php endif; ?>

        <div class="span-2">
            <label for="tytul">Tytuł slajdu *</label>
            <input type="text" id="tytul" name="tytul" value="<?= e($values['tytul']) ?>" required>
        </div>

        <div class="span-2">
            <label for="tresc">Treść / notatki</label>
            <textarea id="tresc" name="tresc" rows="7"><?= e($values['tresc']) ?></textarea>
        </div>

        <div>
            <label for="uklad">Układ</label>
            <select id="uklad" name="uklad">
                <?php foreach ($UKLADY as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $values['uklad'] === $key ? ' selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="szkic"<?= $values['status'] === 'szkic' ? ' selected' : '' ?>>Szkic</option>
                <option value="opublikowany"<?= $values['status'] === 'opublikowany' ? ' selected' : '' ?>>Opublikowany</option>
            </select>
        </div>

        <div>
            <label for="przycisk">Etykieta przycisku (CTA)</label>
            <input type="text" id="przycisk" name="przycisk" value="<?= e($values['przycisk']) ?>"
                   placeholder="np. Dowiedz się więcej">
        </div>

        <div>
            <label for="link">Adres odnośnika</label>
            <input type="url" id="link" name="link" value="<?= e($values['link']) ?>"
                   placeholder="https://...">
        </div>

        <div class="span-2">
            <label for="obraz">Obraz slajdu</label>
            <?php if (!empty($values['obraz'])): ?>
                <div class="upload-current">
                    <img src="<?= e(app_url(UPLOAD_URL . '/' . $values['obraz'])) ?>" alt="Podgląd">
                    <span class="muted small">Aktualny obraz: <?= e($values['obraz']) ?></span>
                </div>
            <?php endif; ?>
            <input type="file" id="obraz" name="obraz" accept="image/jpeg,image/png,image/gif,image/webp">
            <small class="muted">Dozwolone: JPG, PNG, GIF, WEBP · maks. 5 MB</small>
        </div>

        <div class="span-2 form-grid-actions">
            <button type="submit" class="btn btn-primary">
                <?= $id > 0 ? 'Zapisz zmiany' : 'Dodaj slajd' ?>
            </button>
            <a class="btn btn-ghost" href="<?= e($backUrl) ?>">Anuluj</a>
        </div>
    </form>
</section>

<?php layout_footer(); ?>
