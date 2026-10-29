<?php
declare(strict_types=1);
require_once __DIR__ . '/_layout.php';
require_group(GROUP_FIRMA);

$error = '';
$info  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['create'])) {
        $error = 'Nieznana akcja.';
    } else {
        $src = 'data/app.sqlite';
        $dst = 'data/app.sqlite.bak-' . date('Ymd-His');
        if (!file_exists($src)) {
            $error = 'Błąd: baza danych nie istnieje.';
        } elseif (!copy($src, $dst)) {
            $error = 'Błąd kopiowania pliku – sprawdź uprawnienia do zapisu w folderze data/.';
        } else {
            $info = 'Backup utworzony: <strong>' . e($dst) . '</strong>. Plik można pobrać ręcznie z serwera.';
        }
    }
}
?>
<?php layout_header('Kopia zapasowa', 'admin/index.php'); ?>

<section class="card">
    <h2 class="card-title">Kopia zapasowa bazy danych</h2>

    <div class="alert alert-<?= $error ? 'error' : ($info ? 'success' : 'info') ?>">
        <?= $error ? e($error) : e($info) ?>
    </div>

    <?php if (!$error && !$info): ?>
        <p class="muted small">
            Kliknij poniższy przycisk, aby utworzyć kopię zapasową bazy danych <code>data/app.sqlite</code>.
            Plik zostanie zapisany jako <code>data/app.sqlite.bak-RRRR-MMM-DD-HH-MM-SS</code>.
        </p>
    <?php endif; ?>

    <form method="post" class="form-inline">
        <button type="submit" name="create" class="btn btn-primary">
            <?= $error || $info ? 'Spróbuj ponownie' : 'Utwórz kopię zapasową' ?>
        </button>
    </form>

    <?php if ($info): ?>
        <p class="muted small mt-2">
            Kopia zapasowa pozwala przywrócić stan bazy przed wykonywanymi zmianami.
        </p>
    <?php endif; ?>
</section>

<?php layout_footer(); ?>