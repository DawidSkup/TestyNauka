<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

require_login();
require_group(GROUP_FIRMA);

$pdo = db();
$user = current_user();

/* ------------------------------------------------------------------ */
/* Akcje: POST                                                         */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf'] ?? null);
    $akcja = (string) ($_POST['akcja'] ?? '');
    $id    = (int) ($_POST['slajd_id'] ?? 0);

    /* --- Usunięcie slajdu (+ pliku obrazu) ------------------------------ */
    if ($akcja === 'usun') {
        $stmt = $pdo->prepare('SELECT * FROM slajdy WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $slajd = $stmt->fetch();

        if ($slajd) {
            if (!empty($slajd['obraz'])) {
                $sciezka = UPLOAD_PATH . '/' . $slajd['obraz'];
                if (is_file($sciezka)) {
                    @unlink($sciezka);
                }
            }
            $pdo->prepare('DELETE FROM slajdy WHERE id = :id')->execute([':id' => $id]);
            log_action('usunieto_slajd', (string) $slajd['tytul'], (int) $user['id']);
            flash('info', 'Slajd „' . $slajd['tytul'] . '” został usunięty.');
        }

        redirect(app_url('admin/materialy.php'));
    }

    /* --- Publikacja / szkic -------------------------------------------- */
    if ($akcja === 'status') {
        $stmt = $pdo->prepare('SELECT status FROM slajdy WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $status = $stmt->fetchColumn();

        $nowy = $status === 'opublikowany' ? 'szkic' : 'opublikowany';
        $pdo->prepare('UPDATE slajdy SET status = :s, updated_at = :n WHERE id = :id')
            ->execute([':s' => $nowy, ':n' => date('Y-m-d H:i:s'), ':id' => $id]);
        log_action('zmieniono_status_slajdu', 'id=' . $id . ' -> ' . $nowy, (int) $user['id']);
        flash('success', 'Status slajdu zmieniony na „' . $nowy . '”.');

        redirect(app_url('admin/materialy.php'));
    }

    /* --- Kolejność (góra / dół) ---------------------------------------- */
    if ($akcja === 'kolejnosc') {
        $kierunek = ($_POST['kierunek'] ?? 'up') === 'down' ? 1 : -1;
        $stmt = $pdo->prepare('SELECT * FROM slajdy WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $slajd = $stmt->fetch();

        if ($slajd) {
            $kolejnosc = (int) $slajd['kolejnosc'];

            // Szukamy slajdu bezpośrednio przed (lub za) przenoszonym.
            $sql = $kierunek === -1
                ? 'SELECT id, kolejnosc FROM slajdy WHERE id <> :id AND kolejnosc < :k ORDER BY kolejnosc DESC LIMIT 1'
                : 'SELECT id, kolejnosc FROM slajdy WHERE id <> :id AND kolejnosc > :k ORDER BY kolejnosc ASC LIMIT 1';

            $stmtSas = $pdo->prepare($sql);
            $stmtSas->execute([':id' => $id, ':k' => $kolejnosc]);
            $sasiedni = $stmtSas->fetch();

            if ($sasiedni) {
                $pdo->prepare('UPDATE slajdy SET kolejnosc = :k, updated_at = :n WHERE id = :id')
                    ->execute([
                        ':k'  => (int) $sasiedni['kolejnosc'],
                        ':n'  => date('Y-m-d H:i:s'),
                        ':id' => $id,
                    ]);
                $pdo->prepare('UPDATE slajdy SET kolejnosc = :k, updated_at = :n WHERE id = :id')
                    ->execute([
                        ':k'  => $kolejnosc,
                        ':n'  => date('Y-m-d H:i:s'),
                        ':id' => (int) $sasiedni['id'],
                    ]);
                log_action('zmieniono_kolejnosc', 'id=' . $id, (int) $user['id']);
            } else {
                flash('info', 'Ten slajd jest już pierwszy lub ostatni na liście.');
            }
        }

        redirect(app_url('admin/materialy.php'));
    }
}

/* ------------------------------------------------------------------ */
/* Dane                                                               */
/* ------------------------------------------------------------------ */
$slajdy = $pdo->query(
    'SELECT s.*, u.imie AS autor
     FROM slajdy s
     LEFT JOIN users u ON u.id = s.autor_id
     ORDER BY s.kolejnosc ASC, s.id ASC'
)->fetchAll();

if (in_group(GROUP_FIRMA) && ($_GET['status'] ?? '') === 'szkic') {
    $slajdy = array_values(array_filter($slajdy, static fn (array $s): bool => $s['status'] === 'szkic'));
}

layout_header('Materiały (slajdy)', 'materialy.php');
?>

<div class="page-toolbar">
    <p class="muted">
        Materiały w tym panelu przyjmują <strong>wyłącznie postać slajdów</strong>.
        Dokumenty (PDF, DOCX, XLSX) nie są obsługiwane.
    </p>
    <div class="page-toolbar-actions">
        <?php if (in_group(GROUP_FIRMA)): ?>
            <a class="btn btn-ghost btn-sm" href="?status=szkic">Tylko szkice</a>
        <?php endif; ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/podglad.php')) ?>">Podgląd</a>
        <?php if (in_group(GROUP_FIRMA)): ?>
            <a class="btn btn-primary btn-sm" href="<?= e(app_url('admin/slajd_form.php')) ?>">+ Dodaj slajd</a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$slajdy): ?>
    <div class="card card-empty">
        <p>Brak slajdów.</p>
        <a class="btn btn-primary" href="<?= e(app_url('admin/slajd_form.php')) ?>">Dodaj pierwszy slajd</a>
    </div>
<?php else: ?>
    <section class="grid grid-slides">
        <?php foreach ($slajdy as $s): ?>
            <article class="card slide-card">
                <div class="slide-card-media">
                    <?php if (!empty($s['obraz'])): ?>
                        <img src="<?= e(app_url(UPLOAD_URL . '/' . $s['obraz'])) ?>" alt="<?= e($s['tytul']) ?>">
                    <?php else: ?>
                        <span class="slide-card-placeholder"><?= e($s['uklad']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="slide-card-body">
                    <h3><?= e($s['tytul']) ?></h3>
                    <p class="muted small"><?= e(mb_strimwidth((string) $s['tresc'], 0, 120, '…')) ?></p>
                    <div class="slide-card-meta">
                        <span class="badge <?= $s['status'] === 'opublikowany' ? 'badge-ok' : 'badge-warn' ?>">
                            <?= e($s['status'] === 'opublikowany' ? 'opublikowany' : 'szkic') ?>
                        </span>
                        <span class="muted small">#{<?= e((string) $s['kolejnosc']) ?> · <?= e($s['autor'] ?? '—') ?></span>
                    </div>
                </div>
                <div class="slide-card-actions">
                    <a class="btn btn-sm btn-ghost" href="<?= e(app_url('admin/slajd_form.php?id=' . $s['id'])) ?>">Edytuj</a>

                    <form method="post" class="form-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="akcja" value="kolejnosc">
                        <input type="hidden" name="slajd_id" value="<?= e((string) $s['id']) ?>">
                        <input type="hidden" name="kierunek" value="up">
                        <button type="submit" class="btn btn-sm btn-ghost" title="W górę">▲</button>
                    </form>
                    <form method="post" class="form-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="akcja" value="kolejnosc">
                        <input type="hidden" name="slajd_id" value="<?= e((string) $s['id']) ?>">
                        <input type="hidden" name="kierunek" value="down">
                        <button type="submit" class="btn btn-sm btn-ghost" title="W dół">▼</button>
                    </form>
                    <form method="post" class="form-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="akcja" value="status">
                        <input type="hidden" name="slajd_id" value="<?= e((string) $s['id']) ?>">
                        <button type="submit" class="btn btn-sm btn-ghost">
                            <?= $s['status'] === 'opublikowany' ? 'Cofnij do szkicu' : 'Opublikuj' ?>
                        </button>
                    </form>
                    <form method="post" class="form-inline" data-confirm="Usunąć slajd?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="akcja" value="usun">
                        <input type="hidden" name="slajd_id" value="<?= e((string) $s['id']) ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Usuń</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>

<?php layout_footer(); ?>
