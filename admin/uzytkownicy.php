<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

require_group(GROUP_FIRMA);

$pdo = db();
$user = current_user();

$error = '';
$info  = '';

/* ------------------------------------------------------------------ */
/* Akcje: POST                                                         */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf'] ?? null);
    $akcja = (string) ($_POST['akcja'] ?? '');

    /* --- Dodanie użytkownika ------------------------------------------- */
    if ($akcja === 'dodaj') {
        $imie  = trim((string) ($_POST['imie'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $grupa = (string) ($_POST['grupa'] ?? GROUP_MATERIALY);
        $haslo = (string) ($_POST['haslo'] ?? '');

        if (!isset(GROUPS[$grupa])) {
            $grupa = GROUP_MATERIALY;
        }

        if ($imie === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Podaj imię oraz poprawny adres e-mail.';
        } elseif ($haslo !== '' && strlen($haslo) < 8) {
            $error = 'Hasło musi mieć co najmniej 8 znaków.';
        } else {
            $istnieje = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
            $istnieje->execute([':email' => $email]);

            if ((int) $istnieje->fetchColumn() > 0) {
                $error = 'Konto o takim adresie e-mail już istnieje.';
            } else {
                $now = date('Y-m-d H:i:s');

                // Bez hasła startowego konto powstaje nieaktywne – użytkownik
                // aktywuje je kodem, który wysyłamy na jego adres e-mail.
                $aktywowane = $haslo !== '' ? 1 : 0;

                $pdo->prepare(
                    'INSERT INTO users (imie, email, haslo_hash, grupa, aktywny, aktywowany, created_at, updated_at)
                     VALUES (:imie, :email, :haslo, :grupa, 1, :aktyw, :now, :now)'
                )->execute([
                    ':imie'  => $imie,
                    ':email' => $email,
                    ':haslo' => $haslo !== '' ? password_hash($haslo, PASSWORD_DEFAULT) : '',
                    ':grupa' => $grupa,
                    ':aktyw' => $aktywowane,
                    ':now'   => $now,
                ]);

                $newId = (int) $pdo->lastInsertId();
                log_action('dodano_uzytkownika', $email . ' | grupa=' . $grupa, (int) $user['id']);

                if ($aktywowane === 1) {
                    flash('success', 'Dodano konto ' . $email . '. Możesz wygenerować dla niego kod.');
                } else {
                    $wynik = generate_and_send_code($newId, 'aktywacja');
                    flash(
                        $wynik['wyslany'] ? 'success' : 'warning',
                        'Dodano konto. Kod aktywacyjny ' . $wynik['kod'] . ' wysłany na ' . $wynik['email'] . '.'
                    );
                }

                redirect(app_url('admin/uzytkownicy.php'));
            }
        }
    }

    /* --- Generuj i wyślij kod ------------------------------------------ */
    if ($akcja === 'kod') {
        $id  = (int) ($_POST['user_id'] ?? 0);
        $cel = (string) ($_POST['cel'] ?? 'logowanie');
        if (!in_array($cel, ['logowanie', 'aktywacja'], true)) {
            $cel = 'logowanie';
        }

        try {
            $wynik = generate_and_send_code($id, $cel);

            // Kod aktywacyjny wymaga ponownej aktywacji konta przez użytkownika.
            if ($cel === 'aktywacja') {
                $pdo->prepare('UPDATE users SET aktywowany = 0, updated_at = :n WHERE id = :id')
                    ->execute([':n' => date('Y-m-d H:i:s'), ':id' => $id]);
            }

            $info  = $wynik['wyslany']
                ? 'Kod ' . $wynik['kod'] . ' wysłany na ' . $wynik['email'] . ' (ważny do '
                    . fmt_date($wynik['wygasa_at']) . ').'
                : 'Nie udało się wysłać e-maila. Kod ' . $wynik['kod'] . ' (ważny do '
                    . fmt_date($wynik['wygasa_at']) . ').';

            flash($wynik['wyslany'] ? 'success' : 'warning', $info);
        } catch (Throwable $ex) {
            flash('error', $ex->getMessage());
        }

        redirect(app_url('admin/uzytkownicy.php'));
    }

    /* --- Zmiana grupy / blokada ---------------------------------------- */
    if ($akcja === 'przełącz') {
        $id = (int) ($_POST['user_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $cel = $stmt->fetch();

        if ($cel) {
            if ($cel['aktywny']) {
                $pdo->prepare('UPDATE users SET aktywny = 0, updated_at = :n WHERE id = :id')
                    ->execute([':n' => date('Y-m-d H:i:s'), ':id' => $id]);
                log_action('zablokowano_konto', (string) $cel['email'], (int) $user['id']);
                flash('info', 'Konto ' . $cel['email'] . ' zostało zablokowane.');
            } else {
                $pdo->prepare('UPDATE users SET aktywny = 1, updated_at = :n WHERE id = :id')
                    ->execute([':n' => date('Y-m-d H:i:s'), ':id' => $id]);
                log_action('odblokowano_konto', (string) $cel['email'], (int) $user['id']);
                flash('success', 'Konto ' . $cel['email'] . ' zostało odblokowane.');
            }
        }

        redirect(app_url('admin/uzytkownicy.php'));
    }

    /* --- Zmiana grupy użytkownika --------------------------------------- */
    if ($akcja === 'grupa') {
        $id    = (int) ($_POST['user_id'] ?? 0);
        $grupa = (string) ($_POST['grupa'] ?? '');

        if (isset(GROUPS[$grupa])) {
            $pdo->prepare('UPDATE users SET grupa = :g, updated_at = :n WHERE id = :id')
                ->execute([':g' => $grupa, ':n' => date('Y-m-d H:i:s'), ':id' => $id]);
            log_action('zmieniono_grupe', 'user_id=' . $id . ' -> ' . $grupa, (int) $user['id']);
            flash('success', 'Grupa użytkownika została zmieniona.');
        }

        redirect(app_url('admin/uzytkownicy.php'));
    }

    /* --- Reset hasła ----------------------------------------------------- */
    if ($akcja === 'reset') {
        $id    = (int) ($_POST['user_id'] ?? 0);
        $haslo = (string) ($_POST['haslo'] ?? '');

        if (strlen($haslo) < 8) {
            flash('warning', 'Nowe hasło musi mieć co najmniej 8 znaków.');
        } else {
            $pdo->prepare('UPDATE users SET haslo_hash = :h, updated_at = :n WHERE id = :id')
                ->execute([':h' => password_hash($haslo, PASSWORD_DEFAULT), ':n' => date('Y-m-d H:i:s'), ':id' => $id]);
            log_action('reset_hasla', 'user_id=' . $id, (int) $user['id']);
            flash('success', 'Hasło zostało zresetowane.');
        }

        redirect(app_url('admin/uzytkownicy.php'));
    }
}

/* ------------------------------------------------------------------ */
/* Dane do listy                                                       */
/* ------------------------------------------------------------------ */
$stmtUsers = $pdo->prepare(
    'SELECT u.*,
            (SELECT k.kod FROM kody k
              WHERE k.user_id = u.id AND k.uzyty_at IS NULL AND k.wygasa_at > :now
              ORDER BY k.id DESC LIMIT 1) AS aktywny_kod,
            (SELECT COUNT(*) FROM slajdy s WHERE s.autor_id = u.id) AS liczba_slajdow
     FROM users u
     ORDER BY u.imie COLLATE NOCASE'
);
$stmtUsers->execute([':now' => date('Y-m-d H:i:s')]);
$users = $stmtUsers->fetchAll();

layout_header('Użytkownicy', 'uzytkownicy.php');
?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($info !== ''): ?>
    <div class="alert alert-success"><?= e($info) ?></div>
<?php endif; ?>

<section class="card">
    <h2 class="card-title">Dodaj użytkownika</h2>
    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="akcja" value="dodaj">

        <div>
            <label for="imie">Imię i nazwisko</label>
            <input type="text" id="imie" name="imie" required>
        </div>
        <div>
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div>
            <label for="haslo">Hasło startowe <span class="muted">(opcjonalnie)</span></label>
            <input type="text" id="haslo" name="haslo" minlength="8" value="">
            <small class="muted">
                min. 8 znaków · puste = konto nieaktywne, użytkownik aktywuje je kodem z e-maila
            </small>
        </div>
        <div>
            <label for="grupa">Grupa</label>
            <select id="grupa" name="grupa">
                <?php foreach (GROUPS as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-grid-actions">
            <button type="submit" class="btn btn-primary">Dodaj użytkownika</button>
        </div>
    </form>
</section>

<section class="card">
    <h2 class="card-title">Lista użytkowników <span class="muted">(<?= e((string) count($users)) ?>)</span></h2>

    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Imię</th>
                <th>E-mail</th>
                <th>Grupa</th>
                <th>Status</th>
                <th>Aktywny kod</th>
                <th>Slajdy</th>
                <th class="th-actions">Akcje</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <strong><?= e($u['imie']) ?></strong>
                        <?php if ((int) $u['id'] === (int) $user['id']): ?>
                            <span class="badge badge-you">Ty</span>
                        <?php endif; ?>
                    </td>
                    <td class="mono"><?= e($u['email']) ?></td>
                    <td>
                        <form method="post" class="form-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="akcja" value="grupa">
                            <input type="hidden" name="user_id" value="<?= e((string) $u['id']) ?>">
                            <select name="grupa" class="select-sm" onchange="this.form.submit()">
                                <?php foreach (GROUPS as $key => $label): ?>
                                    <option value="<?= e($key) ?>"<?= $u['grupa'] === $key ? ' selected' : '' ?>>
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <noscript><button type="submit" class="btn btn-sm btn-ghost">Zapisz</button></noscript>
                        </form>
                    </td>
                    <td>
                        <?php if ((int) $u['aktywny'] === 1): ?>
                            <span class="badge badge-ok">aktywne</span>
                        <?php else: ?>
                            <span class="badge badge-off">zablokowane</span>
                        <?php endif; ?>
                        <?php if ((int) $u['aktywowany'] !== 1): ?>
                            <span class="badge badge-warn">nieaktywowane</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($u['aktywny_kod'])): ?>
                            <span class="code-inline"><?= e($u['aktywny_kod']) ?></span>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="muted"><?= e((string) $u['liczba_slajdow']) ?></td>
                    <td class="td-actions">
                        <form method="post" class="form-inline" id="formKod<?= e((string) $u['id']) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="akcja" value="kod">
                            <input type="hidden" name="user_id" value="<?= e((string) $u['id']) ?>">
                            <input type="hidden" name="cel" value="logowanie">
                            <button type="submit" class="btn btn-sm btn-primary">
                                Generuj i wyślij kod
                            </button>
                        </form>

                        <form method="post" class="form-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="akcja" value="kod">
                            <input type="hidden" name="user_id" value="<?= e((string) $u['id']) ?>">
                            <input type="hidden" name="cel" value="aktywacja">
                            <button type="submit" class="btn btn-sm btn-ghost">Kod aktywacyjny</button>
                        </form>

                        <form method="post" class="form-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="akcja" value="przełącz">
                            <input type="hidden" name="user_id" value="<?= e((string) $u['id']) ?>">
                            <button type="submit" class="btn btn-sm btn-ghost">
                                <?= (int) $u['aktywny'] === 1 ? 'Zablokuj' : 'Odblokuj' ?>
                            </button>
                        </form>

                        <details class="inline-details">
                            <summary>Reset hasła</summary>
                            <form method="post" class="form-stack">
                                <?= csrf_field() ?>
                                <input type="hidden" name="akcja" value="reset">
                                <input type="hidden" name="user_id" value="<?= e((string) $u['id']) ?>">
                                <input type="text" name="haslo" minlength="8" placeholder="Nowe hasło" required>
                                <button type="submit" class="btn btn-sm">Ustaw hasło</button>
                            </form>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php layout_footer(); ?>
