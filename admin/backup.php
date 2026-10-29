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
            $info = 'Backup utworzony: <strong>' . e($dst) . '</strong>. Możesz pobrać go z serwera.';
        }
    }
}
?>
<h2 class="card-title">Kopia zapasowa bazy danych</h2>
<?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php elseif ($info): ?>
    <div class="alert alert-success"><?= e($info) ?></div>
<?php endif; ?>
<form method="post" class="form-inline">
    <button type="submit" name="create" class="btn btn-primary">Utwórz kopię zapasową</button>
</form>