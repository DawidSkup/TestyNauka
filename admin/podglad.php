<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

require_login();

$pdo = db();

$tylkoPublikowane = ($_GET['status'] ?? '') !== 'wszystkie';

$sql = 'SELECT s.*, u.imie AS autor FROM slajdy s LEFT JOIN users u ON u.id = s.autor_id';
if ($tylkoPublikowane) {
    $sql .= " WHERE s.status = 'opublikowany'";
}
$sql .= ' ORDER BY s.kolejnosc ASC, s.id ASC';

$slajdy = $pdo->query($sql)->fetchAll();

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
    <title>Podgląd slajdów – <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(app_url('assets/style.css')) ?>">
</head>
<body class="page-preview">
<header class="preview-bar">
    <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/materialy.php')) ?>">← Panel</a>
    <span class="preview-title">Podgląd slajdów</span>
    <span class="preview-counter" id="previewCounter">0 / 0</span>
    <?php if (in_group(GROUP_FIRMA)): ?>
        <a class="btn btn-ghost btn-sm" href="?status=wszystkie">Pokaż też szkice</a>
    <?php endif; ?>
    <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/testy.php')) ?>">Testy</a>
</header>

<?php if (!$slajdy): ?>
    <div class="preview-empty">
        <p>Brak slajdów do wyświetlenia.</p>
        <a class="btn btn-primary" href="<?= e(app_url('admin/slajd_form.php')) ?>">Dodaj slajd</a>
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
