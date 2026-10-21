<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

require_login();

$user = current_user();

/* --- Obsługa akcji: wyślij kod na własny e-mail (self-service) --------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['akcja'] ?? '') === 'moj_kod') {
    csrf_require($_POST['csrf'] ?? null);

    $wynik = generate_and_send_code((int) $user['id'], 'logowanie');

    if ($wynik['wyslany']) {
        flash('success', 'Kod ' . $wynik['kod'] . ' został wysłany na adres ' . $wynik['email'] . '.');
    } else {
        flash('warning', 'Nie udało się wysłać e-maila. Kod to ' . $wynik['kod'] . ' (ważny do '
            . fmt_date($wynik['wygasa_at']) . ').');
    }

    redirect(app_url('admin/index.php'));
}

/* --- Statystyki --------------------------------------------------------- */
$pdo = db();

$statUsers   = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$statActive  = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE aktywny = 1 AND aktywowany = 1')->fetchColumn();
$statSlajdy  = (int) $pdo->query('SELECT COUNT(*) FROM slajdy')->fetchColumn();
$statPublik  = (int) $pdo->query("SELECT COUNT(*) FROM slajdy WHERE status = 'opublikowany'")->fetchColumn();
$stmtKody = $pdo->prepare('SELECT COUNT(*) FROM kody WHERE uzyty_at IS NULL AND wygasa_at > :now');
$stmtKody->execute([':now' => date('Y-m-d H:i:s')]);
$statKody = (int) $stmtKody->fetchColumn();

$mojKod = active_code((int) $user['id'], 'logowanie');

$ostatnie = $pdo->query('SELECT * FROM log ORDER BY id DESC LIMIT 8')->fetchAll();

layout_header('Panel startowy', 'index.php');
?>

<section class="grid grid-4">
    <div class="card stat">
        <span class="stat-label">Użytkownicy</span>
        <span class="stat-value"><?= e((string) $statUsers) ?></span>
        <span class="stat-hint"><?= e((string) $statActive) ?> aktywnych kont</span>
    </div>
    <div class="card stat">
        <span class="stat-label">Materiały (slajdy)</span>
        <span class="stat-value"><?= e((string) $statSlajdy) ?></span>
        <span class="stat-hint"><?= e((string) $statPublik) ?> opublikowanych</span>
    </div>
    <div class="card stat">
        <span class="stat-label">Aktywne kody</span>
        <span class="stat-value"><?= e((string) $statKody) ?></span>
        <span class="stat-hint">ważne <?= e((string) CODE_TTL_MINUTES) ?> min</span>
    </div>
    <div class="card stat">
        <span class="stat-label">Twoja grupa</span>
        <span class="stat-value stat-value-sm"><?= e(GROUPS[$user['grupa']] ?? $user['grupa']) ?></span>
        <span class="stat-hint"><?= e($user['email']) ?></span>
    </div>
</section>

<section class="grid grid-2">
    <div class="card">
        <h2 class="card-title">Kod weryfikacyjny</h2>
        <p class="muted">
            Wygeneruj 6-znakowy kod (wielkie litery i cyfry) i wyślij go na swój adres e-mail.
            Kod będzie wymagany przy następnym logowaniu.
        </p>

        <?php if ($mojKod): ?>
            <div class="code-box">
                <span class="code-label">Twój aktywny kod</span>
                <span class="code-value" id="myCode"><?= e($mojKod['kod']) ?></span>
                <span class="muted small">wygasa <?= e(fmt_remaining($mojKod['wygasa_at'])) ?></span>
                <button class="btn btn-ghost btn-sm" type="button" data-copy="#myCode">Kopiuj kod</button>
            </div>
        <?php else: ?>
            <p class="muted small">Brak aktywnego kodu.</p>
        <?php endif; ?>

        <form method="post" class="form-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="akcja" value="moj_kod">
            <button type="submit" class="btn btn-primary">Generuj i wyślij kod na e-mail</button>
        </form>
    </div>

    <div class="card">
        <h2 class="card-title">Skróty</h2>
        <div class="quick-links">
            <a class="btn btn-ghost" href="<?= e(app_url('admin/materialy.php')) ?>">Materiały (slajdy)</a>
            <a class="btn btn-ghost" href="<?= e(app_url('admin/slajd_form.php')) ?>">+ Dodaj slajd</a>
            <a class="btn btn-ghost" href="<?= e(app_url('admin/podglad.php')) ?>">Podgląd slajdów</a>
            <?php if (in_group(GROUP_FIRMA)): ?>
                <a class="btn btn-ghost" href="<?= e(app_url('admin/uzytkownicy.php')) ?>">Zarządzaj użytkownikami</a>
            <?php endif; ?>
        </div>

        <h3 class="card-subtitle">Ostatnie zdarzenia</h3>
        <?php if (!$ostatnie): ?>
            <p class="muted small">Brak zdarzeń.</p>
        <?php else: ?>
            <ul class="event-list">
                <?php foreach ($ostatnie as $o): ?>
                    <li>
                        <span class="event-time"><?= e(fmt_date($o['created_at'])) ?></span>
                        <span class="event-action"><?= e($o['akcja']) ?></span>
                        <span class="event-details muted"><?= e($o['szczegoly']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<?php layout_footer(); ?>
