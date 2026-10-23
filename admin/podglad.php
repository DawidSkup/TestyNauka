<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

require_login();

$pdo = db();
$isFirma = in_group(GROUP_FIRMA);
$prezentacjaId = (int) ($_GET['id'] ?? 0);
$pokazSzkice = $isFirma && ($_GET['status'] ?? '') === 'wszystkie';
$tylkoPublikowane = !$pokazSzkice;

$prezentacja = null;
$tytul = 'Wszystkie slajdy';
$backUrl = $isFirma
    ? app_url('admin/materialy.php')
    : app_url('admin/prezentacje.php');
$backLabel = '← Panel';

if ($prezentacjaId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM prezentacje WHERE id = :id');
    $stmt->execute([':id' => $prezentacjaId]);
    $prezentacja = $stmt->fetch() ?: null;

    if (!$prezentacja) {
        flash('error', 'Prezentacja nie istnieje.');
        redirect(app_url('admin/prezentacje.php'));
    }

    if (!$isFirma && $prezentacja['status'] !== 'opublikowany') {
        flash('warning', 'Ta prezentacja nie jest jeszcze opublikowana.');
        redirect(app_url('admin/prezentacje.php'));
    }

    $tytul = (string) $prezentacja['tytul'];
    $backUrl = $isFirma
        ? app_url('admin/prezentacje.php?akcja=edytuj&id=' . $prezentacjaId)
        : app_url('admin/prezentacje.php');
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
    <link rel="stylesheet" href="<?= e(app_url('assets/style.css')) ?>">
</head>
<body class="page-preview">
<header class="preview-bar">
    <a class="btn btn-ghost btn-sm" href="<?= e($backUrl) ?>"><?= e($backLabel) ?></a>
    <span class="preview-title"><?= e($tytul) ?></span>
    <span class="preview-counter" id="previewCounter">0 / 0</span>
    <?php if ($isFirma): ?>
        <?php if ($pokazSzkice): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e($statusUrl) ?>">Pokaż tylko opublikowane</a>
        <?php else: ?>
            <a class="btn btn-ghost btn-sm" href="<?= e($statusUrl . 'wszystkie') ?>">Pokaż też szkice</a>
        <?php endif; ?>
    <?php endif; ?>
    <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/prezentacje.php')) ?>">Prezentacje</a>
</header>

<?php if (!$slajdy): ?>
    <div class="preview-empty">
        <?php if ($prezentacja): ?>
            <p>Ta prezentacja nie ma slajdów do wyświetlenia.</p>
            <?php if ($isFirma): ?>
                <a class="btn btn-primary" href="<?= e(app_url('admin/prezentacje.php?akcja=edytuj&id=' . $prezentacjaId)) ?>">Dodaj slajdy</a>
            <?php else: ?>
                <a class="btn btn-primary" href="<?= e(app_url('admin/prezentacje.php')) ?>">Wróć do prezentacji</a>
            <?php endif; ?>
        <?php else: ?>
            <p>Brak slajdów do wyświetlenia.</p>
            <?php if ($isFirma): ?>
                <a class="btn btn-primary" href="<?= e(app_url('admin/slajd_form.php')) ?>">Dodaj slajd</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="preview-stage" id="previewStage" data-count="<?= e((string) count($slajdy)) ?>">
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
    </div>

    <nav class="preview-nav">
        <button type="button" class="btn btn-ghost" id="previewPrev" aria-label="Poprzedni slajd">‹ Poprzedni</button>
        <button type="button" class="btn btn-ghost" id="previewNext" aria-label="Następny slajd">Następny ›</button>
    </nav>
<?php endif; ?>

<script src="<?= e(app_url('assets/app.js')) ?>"></script>
</body>
</html>
