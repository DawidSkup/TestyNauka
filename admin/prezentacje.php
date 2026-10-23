<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

require_login();

$pdo = db();
$user = current_user();
$isFirma = in_group(GROUP_FIRMA);
$now = date('Y-m-d H:i:s');

$akcja = (string) ($_GET['akcja'] ?? ($_POST['akcja'] ?? ''));
$prezentacjaId = (int) ($_GET['id'] ?? ($_POST['prezentacja_id'] ?? 0));

/* ------------------------------------------------------------------ */
/* Akcje: POST                                                         */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf'] ?? null);
    require_group(GROUP_FIRMA);

    if ($akcja === 'dodaj') {
        $opis = trim((string) ($_POST['opis'] ?? ''));

        if ($opis === '') {
            flash('error', 'Opis prezentacji jest wymagany.');
            redirect(app_url('admin/prezentacje.php'));
        }

        $stmt = $pdo->query('SELECT COALESCE(MAX(CAST(SUBSTR(tytul, INSTR(tytul, " ") + 1) AS INTEGER)), 0) + 1 FROM prezentacje WHERE tytul LIKE "Prezentacja %"');
        $nextNum = (int) $stmt->fetchColumn();
        $tytul = 'Prezentacja ' . $nextNum;

        $stmt = $pdo->prepare(
            'INSERT INTO prezentacje (tytul, opis, status, autor_id, created_at, updated_at)
             VALUES (:tytul, :opis, "opublikowany", :autor_id, :now, :now)'
        );
        $stmt->execute([
            ':tytul'     => $tytul,
            ':opis'      => $opis,
            ':autor_id'  => (int) $user['id'],
            ':now'       => $now,
        ]);
        $nowaId = (int) $pdo->lastInsertId();

        log_action('dodano_prezentacje', $tytul, (int) $user['id']);
        flash('success', 'Prezentacja dodana i opublikowana. Dodaj do niej slajdy.');
        redirect(app_url('admin/prezentacje.php?akcja=edytuj&id=' . $nowaId));
    }

    if ($akcja === 'edytuj') {
        $opis = trim((string) ($_POST['opis'] ?? ''));
        $status = (string) ($_POST['status'] ?? 'szkic');

        if (!in_array($status, ['szkic', 'opublikowany'], true)) {
            $status = 'szkic';
        }

        if ($prezentacjaId <= 0 || $opis === '') {
            flash('error', 'Opis prezentacji jest wymagany.');
            redirect(app_url('admin/prezentacje.php?akcja=edytuj&id=' . $prezentacjaId));
        }

        $stmt = $pdo->prepare(
            'UPDATE prezentacje
             SET opis = :opis, status = :status, updated_at = :now
             WHERE id = :id'
        );
        $stmt->execute([
            ':opis'   => $opis,
            ':status' => $status,
            ':now'    => $now,
            ':id'     => $prezentacjaId,
        ]);

        log_action('zaktualizowano_prezentacje', 'id=' . $prezentacjaId, (int) $user['id']);
        flash('success', $status === 'opublikowany'
            ? 'Prezentacja zaktualizowana i opublikowana.'
            : 'Prezentacja zaktualizowana. Pozostaje szkicem.');
        redirect(app_url('admin/prezentacje.php?akcja=edytuj&id=' . $prezentacjaId));
    }

    if ($akcja === 'usun') {
        $stmt = $pdo->prepare('SELECT tytul FROM prezentacje WHERE id = :id');
        $stmt->execute([':id' => $prezentacjaId]);
        $tytul = $stmt->fetchColumn();

        if ($tytul !== false) {
            $pdo->prepare('DELETE FROM prezentacje WHERE id = :id')->execute([':id' => $prezentacjaId]);
            log_action('usunieto_prezentacje', (string) $tytul, (int) $user['id']);
            flash('success', 'Prezentacja „' . $tytul . '” została usunięta. Slajdy pozostały w materiałach.');
        }

        redirect(app_url('admin/prezentacje.php'));
    }

    if ($akcja === 'dodaj_slajdy') {
        $ids = $_POST['slajd_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));

        if ($prezentacjaId <= 0 || $ids === []) {
            flash('warning', 'Wybierz co najmniej jeden slajd do dodania.');
            redirect(app_url('admin/prezentacje.php?akcja=edytuj&id=' . $prezentacjaId));
        }

        $stmt = $pdo->prepare('SELECT COALESCE(MAX(kolejnosc), 0) FROM prezentacje_slajdy WHERE prezentacja_id = :id');
        $stmt->execute([':id' => $prezentacjaId]);
        $kolejnosc = (int) $stmt->fetchColumn();

        $stmtSlajd = $pdo->prepare('SELECT COUNT(*) FROM slajdy WHERE id = :id');
        $stmtDodaj = $pdo->prepare(
            'INSERT OR IGNORE INTO prezentacje_slajdy (prezentacja_id, slajd_id, kolejnosc)
             VALUES (:prezentacja_id, :slajd_id, :kolejnosc)'
        );

        $dodane = 0;
        foreach ($ids as $slajdId) {
            $stmtSlajd->execute([':id' => $slajdId]);
            if ((int) $stmtSlajd->fetchColumn() === 0) {
                continue;
            }

            $kolejnosc++;
            $stmtDodaj->execute([
                ':prezentacja_id' => $prezentacjaId,
                ':slajd_id'       => $slajdId,
                ':kolejnosc'      => $kolejnosc,
            ]);
            $dodane += $stmtDodaj->rowCount();
        }

        log_action('dodano_slajdy_do_prezentacji', 'prezentacja=' . $prezentacjaId, (int) $user['id']);
        flash($dodane > 0 ? 'success' : 'info', $dodane > 0
            ? 'Dodano slajdy do prezentacji: ' . $dodane . '.'
            : 'Wybrane slajdy już znajdują się w tej prezentacji.');
        redirect(app_url('admin/prezentacje.php?akcja=edytuj&id=' . $prezentacjaId));
    }

    if ($akcja === 'usun_slajd') {
        $slajdId = (int) ($_POST['slajd_id'] ?? 0);

        if ($prezentacjaId > 0 && $slajdId > 0) {
            $pdo->prepare(
                'DELETE FROM prezentacje_slajdy WHERE prezentacja_id = :prezentacja_id AND slajd_id = :slajd_id'
            )->execute([':prezentacja_id' => $prezentacjaId, ':slajd_id' => $slajdId]);
            log_action('usunieto_slajd_z_prezentacji', 'prezentacja=' . $prezentacjaId . ' slajd=' . $slajdId, (int) $user['id']);
            flash('success', 'Slajd został usunięty z prezentacji. Pozostał w materiałach.');
        }

        redirect(app_url('admin/prezentacje.php?akcja=edytuj&id=' . $prezentacjaId));
    }

    if ($akcja === 'kolejnosc') {
        $slajdId = (int) ($_POST['slajd_id'] ?? 0);
        $kierunek = ($_POST['kierunek'] ?? 'up') === 'down' ? 1 : -1;

        $stmt = $pdo->prepare(
            'SELECT kolejnosc FROM prezentacje_slajdy WHERE prezentacja_id = :prezentacja_id AND slajd_id = :slajd_id'
        );
        $stmt->execute([':prezentacja_id' => $prezentacjaId, ':slajd_id' => $slajdId]);
        $kolejnosc = $stmt->fetchColumn();

        if ($kolejnosc !== false) {
            $sql = $kierunek === -1
                ? 'SELECT slajd_id, kolejnosc FROM prezentacje_slajdy
                   WHERE prezentacja_id = :prezentacja_id AND kolejnosc < :kolejnosc
                   ORDER BY kolejnosc DESC, slajd_id DESC LIMIT 1'
                : 'SELECT slajd_id, kolejnosc FROM prezentacje_slajdy
                   WHERE prezentacja_id = :prezentacja_id AND kolejnosc > :kolejnosc
                   ORDER BY kolejnosc ASC, slajd_id ASC LIMIT 1';

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':prezentacja_id' => $prezentacjaId, ':kolejnosc' => (int) $kolejnosc]);
            $sasiedni = $stmt->fetch();

            if ($sasiedni) {
                $pdo->prepare(
                    'UPDATE prezentacje_slajdy SET kolejnosc = :kolejnosc
                     WHERE prezentacja_id = :prezentacja_id AND slajd_id = :slajd_id'
                )->execute([
                    ':kolejnosc'      => (int) $sasiedni['kolejnosc'],
                    ':prezentacja_id' => $prezentacjaId,
                    ':slajd_id'       => $slajdId,
                ]);
                $pdo->prepare(
                    'UPDATE prezentacje_slajdy SET kolejnosc = :kolejnosc
                     WHERE prezentacja_id = :prezentacja_id AND slajd_id = :slajd_id'
                )->execute([
                    ':kolejnosc'      => (int) $kolejnosc,
                    ':prezentacja_id' => $prezentacjaId,
                    ':slajd_id'       => (int) $sasiedni['slajd_id'],
                ]);
            } else {
                flash('info', 'Ten slajd jest już pierwszy lub ostatni w prezentacji.');
            }
        }

        redirect(app_url('admin/prezentacje.php?akcja=edytuj&id=' . $prezentacjaId));
    }
}

/* ------------------------------------------------------------------ */
/* Dane dla widoków                                                    */
/* ------------------------------------------------------------------ */
$view = 'lista';
$prezentacja = null;
$slajdyPrezentacji = [];
$prezentacje = [];

if ($akcja === 'edytuj' && $prezentacjaId > 0) {
    require_group(GROUP_FIRMA);

    $stmt = $pdo->prepare('SELECT * FROM prezentacje WHERE id = :id');
    $stmt->execute([':id' => $prezentacjaId]);
    $prezentacja = $stmt->fetch() ?: null;

    if (!$prezentacja) {
        flash('error', 'Prezentacja nie istnieje.');
        redirect(app_url('admin/prezentacje.php'));
    }

    $stmt = $pdo->prepare(
        'SELECT s.*, u.imie AS autor, ps.kolejnosc AS kolejnosc_prezentacji
         FROM prezentacje_slajdy ps
         JOIN slajdy s ON s.id = ps.slajd_id
         LEFT JOIN users u ON u.id = s.autor_id
         WHERE ps.prezentacja_id = :id
         ORDER BY ps.kolejnosc ASC, s.id ASC'
    );
    $stmt->execute([':id' => $prezentacjaId]);
    $slajdyPrezentacji = $stmt->fetchAll();

    $view = 'edycja';
} else {
    $sql = 'SELECT p.*, u.imie AS autor,
                   (SELECT COUNT(*) FROM prezentacje_slajdy ps WHERE ps.prezentacja_id = p.id) AS liczba_slajdow
            FROM prezentacje p
            LEFT JOIN users u ON u.id = p.autor_id';
    if (!$isFirma) {
        $sql .= " WHERE p.status = 'opublikowany'";
    }
    $sql .= ' ORDER BY p.id DESC';
    $prezentacje = $pdo->query($sql)->fetchAll();

    $view = 'lista';
}

$statWszystkie = count($prezentacje);
$statOpublikowane = 0;
$statSzkice = 0;
$statSlajdy = 0;
foreach ($prezentacje as $p) {
    if ($p['status'] === 'opublikowany') {
        $statOpublikowane++;
    } else {
        $statSzkice++;
    }
    $statSlajdy += (int) $p['liczba_slajdow'];
}

layout_header($view === 'edycja' ? 'Edycja prezentacji' : 'Prezentacje', 'prezentacje.php');
?>

<?php if ($view === 'edycja'): ?>
    <?php
    $liczbaSlajdow = count($slajdyPrezentacji);
    ?>

    <div class="page-toolbar">
        <div>
            <p class="muted">
                Prezentacja <strong><?= e($prezentacja['tytul']) ?></strong>.
                Dodawaj, usuwaj i układaj slajdy w kolejności odtwarzania.
            </p>
        </div>
        <div class="page-toolbar-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/prezentacje.php')) ?>">← Wróć do listy</a>
            <?php if ($liczbaSlajdow > 0): ?>
                <a class="btn btn-primary btn-sm" href="<?= e(app_url('admin/podglad.php?id=' . (int) $prezentacja['id'] . '&status=wszystkie')) ?>">
                    ▶ Odtwórz prezentację
                </a>
            <?php endif; ?>
        </div>
    </div>

    <section class="grid grid-3">
        <div class="card stat">
            <span class="stat-label">Slajdy</span>
            <span class="stat-value"><?= $liczbaSlajdow ?></span>
            <span class="stat-hint">w tej prezentacji</span>
        </div>
        <div class="card stat">
            <span class="stat-label">Status</span>
            <span class="stat-value stat-value-sm">
                <span class="badge <?= $prezentacja['status'] === 'opublikowany' ? 'badge-ok' : 'badge-warn' ?>">
                    <?= $prezentacja['status'] === 'opublikowany' ? 'opublikowana' : 'szkic' ?>
                </span>
            </span>
            <span class="stat-hint"><?= $prezentacja['status'] === 'opublikowany' ? 'widoczna dla użytkowników' : 'ukryta przed użytkownikami' ?></span>
        </div>
    </section>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Ustawienia prezentacji</h2>
                <p class="card-subtitle">Opis i widoczność. Tytuł jest nadawany automatycznie i nie można go zmienić.</p>
            </div>
        </div>
        <form method="post" action="<?= e(app_url('admin/prezentacje.php')) ?>" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="akcja" value="edytuj">
            <input type="hidden" name="prezentacja_id" value="<?= (int) $prezentacja['id'] ?>">

            <label class="form-field">
                <span>Tytuł (automatyczny)</span>
                <input type="text" value="<?= e($prezentacja['tytul']) ?>" readonly style="background: var(--surface-2); color: var(--muted);">
            </label>

            <label class="form-field">
                <span>Status</span>
                <select name="status">
                    <option value="szkic" <?= $prezentacja['status'] === 'szkic' ? 'selected' : '' ?>>Szkic – widoczna tylko dla administratora</option>
                    <option value="opublikowany" <?= $prezentacja['status'] === 'opublikowany' ? 'selected' : '' ?>>Opublikowana – dostępna dla użytkowników</option>
                </select>
            </label>

            <label class="form-field form-field-full">
                <span>Opis <em class="req">*</em></span>
                <textarea name="opis" placeholder="Krótki opis prezentacji widoczny na liście" required><?= e($prezentacja['opis']) ?></textarea>
            </label>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Zapisz ustawienia</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">Slajdy w prezentacji <span class="badge"><?= $liczbaSlajdow ?></span></h2>
                <p class="card-subtitle">Kolejność na liście odpowiada kolejności odtwarzania.</p>
            </div>
            <div class="card-actions">
                <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/slajd_form.php?prezentacja_id=' . (int) $prezentacja['id'])) ?>">+ Dodaj nowy slajd</a>
            </div>
        </div>

        <?php if ($slajdyPrezentacji === []): ?>
            <div class="empty-state">
                <p><strong>Ta prezentacja nie ma jeszcze slajdów.</strong></p>
                <p class="muted">Dodaj slajdy z listy poniżej albo utwórz nowy slajd.</p>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Slajd</th>
                            <th>Status</th>
                            <th>Autor</th>
                            <th class="th-actions">Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($slajdyPrezentacji as $idx => $s): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td>
                                    <strong><?= e($s['tytul']) ?></strong>
                                    <br><span class="muted small"><?= e($s['uklad']) ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $s['status'] === 'opublikowany' ? 'badge-ok' : 'badge-warn' ?>">
                                        <?= $s['status'] === 'opublikowany' ? 'opublikowany' : 'szkic' ?>
                                    </span>
                                </td>
                                <td><?= e($s['autor'] ?? '—') ?></td>
                                <td>
                                    <div class="td-actions">
                                        <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/slajd_form.php?id=' . (int) $s['id'])) ?>">Edytuj</a>

                                        <form method="post" action="<?= e(app_url('admin/prezentacje.php')) ?>" class="form-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="akcja" value="kolejnosc">
                                            <input type="hidden" name="prezentacja_id" value="<?= (int) $prezentacja['id'] ?>">
                                            <input type="hidden" name="slajd_id" value="<?= (int) $s['id'] ?>">
                                            <input type="hidden" name="kierunek" value="up">
                                            <button type="submit" class="btn btn-sm btn-ghost" title="W górę" aria-label="W górę">▲</button>
                                        </form>
                                        <form method="post" action="<?= e(app_url('admin/prezentacje.php')) ?>" class="form-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="akcja" value="kolejnosc">
                                            <input type="hidden" name="prezentacja_id" value="<?= (int) $prezentacja['id'] ?>">
                                            <input type="hidden" name="slajd_id" value="<?= (int) $s['id'] ?>">
                                            <input type="hidden" name="kierunek" value="down">
                                            <button type="submit" class="btn btn-sm btn-ghost" title="W dół" aria-label="W dół">▼</button>
                                        </form>
                                        <form method="post" action="<?= e(app_url('admin/prezentacje.php')) ?>" class="form-inline" data-confirm="Usunąć slajd z prezentacji? Slajd pozostanie w materiałach.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="akcja" value="usun_slajd">
                                            <input type="hidden" name="prezentacja_id" value="<?= (int) $prezentacja['id'] ?>">
                                            <input type="hidden" name="slajd_id" value="<?= (int) $s['id'] ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm text-danger">Usuń z prezentacji</button>
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
        <div>
            <p class="muted">
                Prezentacje grupują slajdy w gotowe pokazy. Odtworzysz je w trybie pełnoekranowym.
            </p>
        </div>
        <div class="page-toolbar-actions">
            <span class="badge"><?= $statWszystkie ?> <?= $statWszystkie === 1 ? 'prezentacja' : 'prezentacji' ?></span>
        </div>
    </div>

    <?php if ($isFirma): ?>
        <section class="grid grid-4">
            <div class="card stat">
                <span class="stat-label">Prezentacje</span>
                <span class="stat-value"><?= $statWszystkie ?></span>
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
                <span class="stat-label">Slajdy w prezentacjach</span>
                <span class="stat-value"><?= $statSlajdy ?></span>
                <span class="stat-hint">łącznie przypisań</span>
            </div>
        </section>

        <div class="card" id="nowa-prezentacja">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Nowa prezentacja</h2>
                    <p class="card-subtitle">Utwórz prezentację, a następnie przypisz do niej slajdy. Nowa prezentacja jest od razu opublikowana.</p>
                </div>
            </div>
            <form method="post" action="<?= e(app_url('admin/prezentacje.php')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="akcja" value="dodaj">

                <label class="form-field">
                    <span>Tytuł (automatyczny)</span>
                    <input type="text" value="Prezentacja <?= (int) $statWszystkie + 1 ?>" readonly style="background: var(--surface-2); color: var(--muted);">
                </label>

                <label class="form-field form-field-full">
                    <span>Opis <em class="req">*</em></span>
                    <textarea name="opis" placeholder="Krótki opis prezentacji widoczny na liście" required></textarea>
                </label>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">+ Utwórz prezentację</button>
                    <span class="form-hint">Po utworzeniu przejdziesz od razu do dodawania slajdów.</span>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($prezentacje === []): ?>
        <div class="card card-empty">
            <p><strong>Brak prezentacji do wyświetlenia.</strong></p>
            <?php if ($isFirma): ?>
                <p class="muted">Utwórz pierwszą prezentację w formularzu powyżej.</p>
                <a class="btn btn-primary" href="#nowa-prezentacja">Utwórz pierwszą prezentację</a>
            <?php else: ?>
                <p class="muted">Wróć tutaj, gdy administrator opublikuje prezentację.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <section class="grid grid-slides">
            <?php foreach ($prezentacje as $p): ?>
                <article class="card test-card">
                    <div class="test-card-top">
                        <span class="badge badge-firma">Prezentacja</span>
                        <span class="badge <?= $p['status'] === 'opublikowany' ? 'badge-ok' : 'badge-warn' ?>">
                            <?= $p['status'] === 'opublikowany' ? 'opublikowana' : 'szkic' ?>
                        </span>
                    </div>
                    <h3><?= e($p['tytul']) ?></h3>
                    <?php if (trim((string) $p['opis']) !== ''): ?>
                        <p class="muted small"><?= e($p['opis']) ?></p>
                    <?php else: ?>
                        <p class="muted small">Bez opisu</p>
                    <?php endif; ?>
                    <div class="slide-card-meta">
                        <span class="badge"><?= (int) $p['liczba_slajdow'] ?> <?= (int) $p['liczba_slajdow'] === 1 ? 'slajd' : 'slajdów' ?></span>
                        <span class="muted small"><?= e($p['autor'] ?? '—') ?></span>
                    </div>
                    <div class="test-card-meta">
                        <a class="btn btn-primary btn-sm" href="<?= e(app_url('admin/podglad.php?id=' . (int) $p['id'] . ($isFirma ? '&status=wszystkie' : ''))) ?>">
                            ▶ Odtwórz
                        </a>
                        <?php if ($isFirma): ?>
                            <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/prezentacje.php?akcja=edytuj&id=' . (int) $p['id'])) ?>">Edytuj</a>
                            <form method="post" action="<?= e(app_url('admin/prezentacje.php')) ?>" class="form-inline" data-confirm="Usunąć prezentację? Slajdy pozostaną w materiałach.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="akcja" value="usun">
                                <input type="hidden" name="prezentacja_id" value="<?= (int) $p['id'] ?>">
                                <button class="btn btn-ghost btn-sm text-danger" type="submit">Usuń</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php layout_footer(); ?>
