<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

require_login();

$user = current_user();

/* --- Obsługa akcji: wyślij kod na e-mail ------------------------------- */
$akcja = (string) ($_POST['akcja'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($akcja, ['moj_kod', 'kod_email'], true)) {
    csrf_require($_POST['csrf'] ?? null);

    if ($akcja === 'moj_kod') {
        /* Kod na własny adres (self-service). */
        if (user_requires_code($user)) {
            flash('warning', 'Twoje konto wymaga kodu wysyłanego przez administratora – nie możesz wygenerować go samodzielnie.');
            redirect(app_url('admin/index.php'));
        }

        $wynik = generate_and_send_code((int) $user['id'], 'logowanie');
    } else {
        /* Kod na dowolny adres – tylko grupa firmy. */
        if (!in_group(GROUP_FIRMA)) {
            flash('error', 'Tylko grupa firmy może wysyłać kod na inny adres e-mail.');
            redirect(app_url('admin/index.php'));
        }

        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));

        // Konto z wymogiem kodu nie może samo wygenerować sobie kodu.
        if (user_requires_code($user) && $email === mb_strtolower((string) $user['email'])) {
            flash('warning', 'Twoje konto wymaga kodu wysyłanego przez innego administratora.');
            redirect(app_url('admin/index.php'));
        }

        try {
            $wynik = generate_and_send_code_for_email($email, 'logowanie');
        } catch (Throwable $ex) {
            flash('error', $ex->getMessage());
            redirect(app_url('admin/index.php'));
        }
    }

    $grupa = GROUPS[$wynik['grupa'] ?? $user['grupa']] ?? ($wynik['grupa'] ?? $user['grupa']);
    $waznosc = code_is_permanent((string) $wynik['wygasa_at'])
        ? 'kod stały – działa przy każdym logowaniu'
        : 'ważny do ' . fmt_date($wynik['wygasa_at']);

    if ($wynik['wyslany']) {
        flash('success', 'Kod ' . $wynik['kod'] . ' został wysłany na adres ' . $wynik['email']
            . ' (' . $grupa . ', ' . $waznosc . '). Odbiorca zaloguje się nim w zakładce „Użytkownik”.');
    } else {
        flash('warning', 'Nie udało się wysłać e-maila. Kod to ' . $wynik['kod'] . ' (' . $waznosc . ').');
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

$stmtZap = $pdo->prepare('SELECT COUNT(*) FROM zaproszenia WHERE uzyty_at IS NULL AND wygasa_at > :now');
$stmtZap->execute([':now' => date('Y-m-d H:i:s')]);
$statZaproszenia = (int) $stmtZap->fetchColumn();

$mojKod = active_code((int) $user['id'], 'logowanie');

$ostatnie = $pdo->query('SELECT * FROM log ORDER BY id DESC LIMIT 8')->fetchAll();

/* Dla użytkownika widoczny tylko kod weryfikacyjny i grupa */
if ((string) $user['grupa'] === GROUP_MATERIALY) {
    layout_header('Panel startowy', 'index.php');
    ?>
    <section class="grid grid-2">
        <div class="card">
            <h2 class="card-title">Twoja grupa</h2>
            <p class="stat-value stat-value-sm"><?= e(GROUPS[$user['grupa']] ?? $user['grupa']) ?></p>
            <p class="muted"><?= e($user['email']) ?></p>
            <p class="muted small">Dostęp do opublikowanych prezentacji i testów.</p>
            <div class="quick-links">
                <a class="btn btn-primary btn-sm" href="<?= e(app_url('admin/prezentacje.php')) ?>">Otwórz prezentacje</a>
                <a class="btn btn-ghost btn-sm" href="<?= e(app_url('admin/testy.php')) ?>">Rozwiąż test</a>
            </div>
        </div>

        <div class="card">
            <h2 class="card-title">Kod weryfikacyjny</h2>

            <?php if (user_requires_code($user)): ?>
                <p class="muted">
                    Twoje konto wymaga kodu wysyłanego przez administratora – poproś grupę firmy
                    o wygenerowanie kodu.
                </p>
                <p class="muted small">Nie możesz wygenerować kodu samodzielnie.</p>
            <?php else: ?>
                <?php if ($mojKod): ?>
                    <div class="code-box">
                        <span class="code-label">Twój kod weryfikacyjny</span>
                        <span class="code-value" id="myCode"><?= e($mojKod['kod']) ?></span>
                        <span class="muted small">Kod jest stały – działa przy każdym logowaniu</span>
                        <button class="btn btn-ghost btn-sm" type="button" data-copy="#myCode">Kopiuj kod</button>
                    </div>
                <?php else: ?>
                    <p class="muted small">Brak aktywnego kodu.</p>
                    <form method="post" class="form-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="akcja" value="moj_kod">
                        <button type="submit" class="btn btn-primary">Generuj i wyślij kod na e-mail</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
    <?php
    layout_footer();
    exit;
}

layout_header('Panel startowy', 'index.php');
?>

<section class="grid grid-4">
    <div class="card stat">
        <span class="stat-label">Użytkownicy</span>
        <span class="stat-value"><?= e((string) $statUsers) ?></span>
        <span class="stat-hint"><?= e((string) $statActive) ?> aktywnych kont</span>
    </div>
    <div class="card stat">
        <span class="stat-label">Prezentacje</span>
        <span class="stat-value"><?= e((string) $statSlajdy) ?></span>
        <span class="stat-hint"><?= e((string) $statPublik) ?> opublikowanych</span>
    </div>
    <div class="card stat">
        <span class="stat-label">Aktywne kody</span>
        <span class="stat-value"><?= e((string) $statKody) ?></span>
        <span class="stat-hint">
            ważne <?= e((string) CODE_TTL_MINUTES) ?> min · <?= e((string) $statZaproszenia) ?> zaproszeń
        </span>
    </div>
    <div class="card stat">
        <span class="stat-label">Twoja grupa</span>
        <span class="stat-value stat-value-sm"><?= e(GROUPS[$user['grupa']] ?? $user['grupa']) ?></span>
        <span class="stat-hint"><?= e($user['email']) ?></span>
    </div>
    <div class="card stat">
        <span class="stat-label">Twoje testy</span>
        <span class="stat-value"><?= e(count(wyniki_uzytkownika($pdo, (int) $user['id']))) ?></span>
        <span class="stat-hint">rozwiązanych testów</span>
    </div>

    <?php if (in_group(GROUP_FIRMA)): ?>
    <div class="card stat">
        <span class="stat-label">Twoje rozwiązane testy</span>
        <span class="stat-value"><?= e(count(wyniki_uzytkownika($pdo, (int) $user['id']))) ?></span>
        <span class="stat-hint">rozwiązanych testów</span>
    </div>

    <!-- Tabela wyników wszystkich użytkowników -->
    <div class="card">
        <h2 class="card-title">Wyniki wszystkich użytkowników</h2>
        <p class="muted small">Tylko dla grupy firmy</p>

        <?php
        // Pobierz wszystkich użytkowników
        $stmtUsers = $pdo->query("SELECT u.id, u.imie, u.nazwisko, u.email, u.grupa FROM users u ORDER BY u.imie COLLATE NOCASE");
        $users = $stmtUsers->fetchAll();

        // Pobierz wszystkie opublikowane testy z numerami
        $testy = testy_wg_numeru($pdo);

        // Zbierz numerację testów
        $numeryTestow = [];
        foreach ($testy as $numer => $data) {
            $numeryTestow[$numer] = $numer;
        }
        ksort($numeryTestow);
        ?>

        <div class="table-responsive">
            <table class="table">
                <thead>
                <tr>
                    <th>Użytkownik</th>
                    <?php foreach ($numeryTestow as $numer): ?>
                        <th>Test <?= e($numer) ?></th>
                    <?php endforeach; ?>
                    <th>Razem</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>
                            <strong><?= e($u['imie']) ?></strong><br>
                            <span class="muted small"><?= e($u['email']) ?></span>
                            <span class="muted small">(<?= e($u['grupa'] ?? 'brak') ?>)</span>
                        </td>
                        <?php
                        // Pobierz wyniki tego użytkownika
                        $wyniki = wyniki_uzytkownika($pdo, (int) $u['id']);
                        $rozwiazane = 0;
                        ?>
                        <?php foreach ($numeryTestow as $numer => $etykieta): ?>
                            <?php
                            $testId = null;
                            foreach ($testy as $tN => $tData) {
                                if ($tN == $numer) {
                                    $testId = $tData['id'];
                                    break;
                                }
                            }
                            if ($testId !== null && isset($wyniki[$testId])) {
                                $wynik = $wyniki[$testId];
                                $rozwiazane++;
                                $procent = $wynik['procent'] ?? 0;
                                $tresc = sprintf('%d/%d (%d%%)', $wynik['wynik'] ?? 0, $wynik['maks'] ?? 0, $procent);
                            } else {
                                $tresc = 'brak';
                            }
                            ?>
                            <td><?= e($tresc) ?></td>
                        <?php endforeach; ?>
                        <td><?= e($rozwiazane) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
</section>

<section class="grid grid-2">
    <div class="card">
        <h2 class="card-title">Kod weryfikacyjny</h2>

        <?php if (user_requires_code($user)): ?>
            <p class="muted">
                Twoje konto wymaga kodu wysyłanego przez administratora – poproś grupę firmy
                o wygenerowanie kodu przyciskiem „Generuj i wyślij kod”.
            </p>
            <p class="muted small">Nie możesz wygenerować kodu samodzielnie.</p>
        <?php else: ?>
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
        <?php endif; ?>

        <?php if (in_group(GROUP_FIRMA)): ?>
            <p class="muted">
                Wygeneruj 6-znakowy kod (wielkie litery i cyfry) i wyślij go na e-mail wybranego
                konta – administratora albo użytkownika. Odbiorca zaloguje się nim w zakładce
                „Użytkownik”. Kod jest ważny <?= e((string) CODE_TTL_MINUTES) ?> minut i działa raz.
            </p>

            <form method="post" class="form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="akcja" value="kod_email">
                <div>
                    <label for="kod_email">E-mail odbiorcy</label>
                    <input type="email" id="kod_email" name="email" required
                           value="<?= e($user['email']) ?>" placeholder="np. jan.kowalski@firma.pl">
                </div>
                <div class="form-grid-actions">
                    <button type="submit" class="btn btn-primary">Wyślij kod na e-mail</button>
                </div>
            </form>
        <?php elseif (!user_requires_code($user)): ?>
            <p class="muted">
                Wygeneruj 6-znakowy kod (wielkie litery i cyfry) i wyślij go na swój adres e-mail.
                Kod będzie wymagany przy następnym logowaniu.
            </p>

            <form method="post" class="form-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="akcja" value="moj_kod">
                <button type="submit" class="btn btn-primary">Generuj i wyślij kod na e-mail</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 class="card-title">Skróty</h2>
        <div class="quick-links">
            <a class="btn btn-ghost" href="<?= e(app_url('admin/prezentacje.php')) ?>">Prezentacje</a>
            <a class="btn btn-ghost" href="<?= e(app_url('admin/slajd_form.php')) ?>">+ Dodaj slajd</a>
            <a class="btn btn-ghost" href="<?= e(app_url('admin/testy.php')) ?>">Testy</a>
            <a class="btn btn-ghost" href="<?= e(app_url('admin/uzytkownicy.php')) ?>">Użytkownicy</a>
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
