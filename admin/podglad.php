<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

require_login();

$pdo = db();
$user = current_user();
$isFirma = in_group(GROUP_FIRMA);
$prezentacjaId = (int) ($_GET['id'] ?? 0);
$pokazSzkice = $isFirma && ($_GET['status'] ?? '') === 'wszystkie';
$tylkoPublikowane = !$pokazSzkice;

// Użytkownik odtwarza konkretną prezentację z listy – widok „wszystkie slajdy”
// jest zarezerwowany dla grupy firmy (omijałby blokady).
if (!$isFirma && $prezentacjaId <= 0) {
    flash('info', 'Wybierz prezentację z listy, aby ją odtworzyć.');
    redirect(app_url('admin/testy.php'));
}

$prezentacja = null;
$tytul = 'Wszystkie slajdy';
$backUrl = $isFirma
    ? app_url('admin/materialy.php')
    : app_url('admin/testy.php');
$backLabel = '← Panel';

$potwierdzona = false;
if (!$isFirma && $user !== null && $prezentacjaId > 0) {
    $obejrzaneLista = obejrzane_prezentacje($pdo, (int) $user['id']);
    $potwierdzona = in_array($prezentacjaId, $obejrzaneLista, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $prezentacjaId > 0 && !$isFirma && $user !== null) {
    csrf_require($_POST['csrf'] ?? null);
    $akcjaPost = $_POST['akcja'] ?? '';

    if ($akcjaPost === 'potwierdz_prezentacje') {
        $stmt = $pdo->prepare('SELECT * FROM prezentacje WHERE id = :id');
        $stmt->execute([':id' => $prezentacjaId]);
        $prezentacjaPotw = $stmt->fetch() ?: null;

        if (!$prezentacjaPotw) {
            flash('error', 'Prezentacja nie istnieje.');
            redirect(app_url('admin/testy.php'));
        }

        if ($prezentacjaPotw['status'] !== 'opublikowany') {
            flash('warning', 'Ta prezentacja nie jest jeszcze opublikowana.');
            redirect(app_url('admin/testy.php'));
        }

        $blokadaTestuPotw = blokujacy_test(
            (string) $prezentacjaPotw['tytul'],
            testy_wg_numeru($pdo),
            rozwiazane_testy($pdo, (int) $user['id'])
        );

        if ($blokadaTestuPotw !== null) {
            flash('warning', 'Zanim potwierdzisz zapoznanie się z tą prezentacją, rozwiąż test „' . $blokadaTestuPotw['tytul'] . '”.');
            redirect(app_url('admin/testy.php'));
        }

        if (!$potwierdzona) {
            oznacz_prezentacje_obejrzana($pdo, (int) $user['id'], $prezentacjaId);
            flash('success', 'Potwierdziłeś zapoznanie się z prezentacją. Test został odblokowany.');
        } else {
            flash('info', 'Zapoznanie z tą prezentacją zostało już potwierdzone.');
        }

        redirect(app_url('admin/testy.php'));
    }
}

if ($prezentacjaId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM prezentacje WHERE id = :id');
    $stmt->execute([':id' => $prezentacjaId]);
    $prezentacja = $stmt->fetch() ?: null;

    if (!$prezentacja) {
        flash('error', 'Prezentacja nie istnieje.');
        redirect(app_url('admin/testy.php'));
    }

    if (!$isFirma && $prezentacja['status'] !== 'opublikowany') {
        flash('warning', 'Ta prezentacja nie jest jeszcze opublikowana.');
        redirect(app_url('admin/testy.php'));
    }

    // Prezentacja o numerze N jest zablokowana do rozwiązania testu N-1.
    if (!$isFirma && $user !== null) {
        $blokadaTestu = blokujacy_test(
            (string) $prezentacja['tytul'],
            testy_wg_numeru($pdo),
            rozwiazane_testy($pdo, (int) $user['id'])
        );

        if ($blokadaTestu !== null) {
            flash('warning', 'Zanim obejrzysz tę prezentację, rozwiąż test „' . $blokadaTestu['tytul'] . '”.');
            redirect(app_url('admin/testy.php'));
        }
    }

    $tytul = (string) $prezentacja['tytul'];
    $backUrl = $isFirma
        ? app_url('admin/testy.php?akcja=edytuj&id=' . $prezentacjaId)
        : app_url('admin/testy.php');
    $backLabel = $isFirma ? '← Edycja prezentacji' : '← Prezentacje';

    $sql = 'SELECT s.*, u.imie AS autor
            FROM prezentacje_slajdy ps
            JOIN slajdy s ON s.id = ps.slajd_id
            LEFT JOIN users u ON u.id = s.autor_id
            WHERE ps.prezentacja_id = :id';
    if ($tylkoPublikowane) {
        $sql .= " AND s.status = 'opublikowany'";
    }
    $sql .= ' ORDER BY ps.kolejnosc ASC, s.id ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $prezentacjaId]);
    $slajdy = $stmt->fetchAll();
} else {
    $sql = 'SELECT s.*, u.imie AS autor FROM slajdy s LEFT JOIN users u ON u.id = s.autor_id';
    if ($tylkoPublikowane) {
        $sql .= " WHERE s.status = 'opublikowany'";
    }
    $sql .= ' ORDER BY s.kolejnosc ASC, s.id ASC';

    $slajdy = $pdo->query($sql)->fetchAll();
}

$statusUrl = $prezentacjaId > 0
    ? '?id=' . $prezentacjaId . '&status='
    : '?status=';

$uklady = [
    'tekst-obraz' => 'Tekst + obraz',
    'obraz-tekst' => 'Obraz + tekst',
    'tylko-tekst' => 'Tylko tekst',
    'cytat'        => 'Cytat',
];
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($tytul) ?> – <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_asset_url('assets/style.css')) ?>">
</head>
<body class="page-preview">
<header class="preview-bar">
    <a class="btn btn-ghost btn-sm" href="<?= e($backUrl) ?>" id="previewBack"><?= e($backLabel) ?></a>
    <span class="preview-title"><?= e($tytul) ?></span>
    <span class="preview-counter" id="previewCounter">0 / 0</span>
    <?php if ($isFirma): ?>
        <?php if ($pokazSzkice): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e($statusUrl) ?>">Pokaż tylko opublikowane</a>
        <?php else: ?>
            <a class="btn btn-ghost btn-sm" href="<?= e($statusUrl . 'wszystkie') ?>">Pokaż też szkice</a>
        <?php endif; ?>
    <?php endif; ?>
    <button type="button" class="btn btn-ghost btn-sm" id="previewFullscreen" aria-label="Pełny ekran">⛶ Pełny ekran</button>
    <button type="button" class="btn btn-ghost btn-sm" id="previewSound" aria-label="Wyłącz dźwięk slajdów" aria-pressed="true" title="Dźwięk slajdów: włączony">🔊</button>
    <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/testy.php')) ?>">Prezentacje</a>
</header>

<?php if (!$slajdy): ?>
    <div class="preview-empty">
        <?php if ($prezentacja): ?>
            <p>ta prezentacja nie jest zakonczona</p>
            <?php if ($isFirma): ?>
                <a class="btn btn-primary" href="<?= e(app_url('admin/testy.php?akcja=edytuj&id=' . $prezentacjaId)) ?>">Dodaj slajdy</a>
            <?php else: ?>
                <?php if (!$potwierdzona): ?>
                    <p class="muted small">Materiał nie ma jeszcze slajdów. Potwierdź zapoznanie się z prezentacją, aby odblokować test.</p>
                    <form method="post" action="<?= e(app_url('admin/podglad.php?id=' . $prezentacjaId)) ?>" class="slide-confirm-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="akcja" value="potwierdz_prezentacje">
                        <button class="btn btn-primary" type="submit">Zapoznałem(am) się z prezentacją</button>
                    </form>
                <?php else: ?>
                    <p class="muted small">Zapoznanie z tą prezentacją zostało już potwierdzone. Przypisany test jest odblokowany.</p>
                    <button class="btn btn-primary" type="button" disabled>Zapoznałem(am) się z prezentacją ✓</button>
                    <a class="btn btn-ghost" href="<?= e(app_url('admin/testy.php')) ?>">Przejdź do testów →</a>
                <?php endif; ?>
                <a class="btn btn-ghost" href="<?= e(app_url('admin/testy.php')) ?>">Wróć do prezentacji</a>
            <?php endif; ?>
        <?php else: ?>
            <p>Brak slajdów do wyświetlenia.</p>
            <?php if ($isFirma): ?>
                <a class="btn btn-primary" href="<?= e(app_url('admin/slajd_form.php')) ?>">Dodaj slajd</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="preview-stage" id="previewStage" data-count="<?= e((string) count($slajdy) + ($prezentacjaId > 0 && $user !== null ? 1 : 0)) ?>">
        <?php foreach ($slajdy as $i => $s): ?>
            <article class="slide slide-<?= e($s['uklad']) ?><?= $i === 0 ? ' is-current' : '' ?>" data-index="<?= e((string) $i) ?>">
                <?php if (!empty($s['obraz'])): ?>
                    <div class="slide-media">
                        <img src="<?= e(app_url(UPLOAD_URL . '/' . $s['obraz'])) ?>" alt="<?= e($s['tytul']) ?>">
                    </div>
                <?php endif; ?>
                <div class="slide-body">
                    <h1 class="slide-title"><?= e($s['tytul']) ?></h1>
                    <?php if ($s['uklad'] === 'cytat'): ?>
                        <blockquote class="slide-quote"><?= nl2br(e($s['tresc'])) ?></blockquote>
                    <?php else: ?>
                        <div class="slide-text"><?= nl2br(e($s['tresc'])) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($s['przycisk'])): ?>
                        <a class="btn btn-primary slide-cta" href="<?= e($s['link'] !== '' ? $s['link'] : '#') ?>"
                           <?= $s['link'] !== '' ? 'target="_blank" rel="noopener"' : '' ?>>
                            <?= e($s['przycisk']) ?>
                        </a>
                    <?php endif; ?>
                    <p class="slide-meta">
                        <span class="badge <?= $s['status'] === 'opublikowany' ? 'badge-ok' : 'badge-warn' ?>">
                            <?= e($s['status'] === 'opublikowany' ? 'opublikowany' : 'szkic') ?>
                        </span>
                        <span class="muted small"><?= e($s['autor'] ?? '—') ?></span>
                    </p>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if ($prezentacjaId > 0 && $user !== null && $slajdy): ?>
            <article class="slide slide-confirm" data-index="<?= e((string) count($slajdy)) ?>">
                <div class="slide-body">
                    <h1 class="slide-title">Potwierdzenie</h1>
                    <?php if ($isFirma): ?>
                        <p class="slide-text">Podgląd administratora. Przycisk potwierdzenia zapoznania się z prezentacją widzą użytkownicy grupy materiałów – kliknięcie odblokowuje przypisany test.</p>
                        <div class="slide-confirm-form">
                            <button class="btn btn-primary" type="button" disabled>Zapoznałem(am) się z prezentacją</button>
                        </div>
                    <?php elseif (!$potwierdzona): ?>
                        <p class="slide-text">Aby odblokować przypisany do tej prezentacji test, potwierdź, że zapoznałeś(aś) się z całością prezentacji.</p>
                        <form method="post" action="<?= e(app_url('admin/podglad.php?id=' . $prezentacjaId)) ?>" class="slide-confirm-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="akcja" value="potwierdz_prezentacje">
                            <button class="btn btn-primary" type="submit">Zapoznałem(am) się z prezentacją</button>
                        </form>
                    <?php else: ?>
                        <p class="slide-text">Zapoznanie z tą prezentacją zostało już potwierdzone. Przypisany test jest odblokowany.</p>
                        <div class="slide-confirm-form">
                            <button class="btn btn-primary" type="button" disabled>Zapoznałem(am) się z prezentacją ✓</button>
                            <a class="btn btn-ghost" href="<?= e(app_url('admin/testy.php')) ?>">Przejdź do testów →</a>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endif; ?>
    </div>

    <nav class="preview-nav">
        <button type="button" class="btn btn-ghost" id="previewPrev" aria-label="Poprzedni slajd">‹ Poprzedni</button>
        <button type="button" class="btn btn-ghost" id="previewNext" aria-label="Następny slajd">Następny ›</button>
    </nav>
<?php endif; ?>



<script src="<?= e(app_asset_url('assets/app.js')) ?>"></script>
</body>
</html>
