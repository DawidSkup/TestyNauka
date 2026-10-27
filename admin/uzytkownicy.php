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

    /* --- Wysłanie kodu rejestracyjnego ----------------------------------- */
    if ($akcja === 'zaproszenie') {
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $grupa = (string) ($_POST['grupa'] ?? GROUP_MATERIALY);

        if (!isset(GROUPS[$grupa])) {
            $grupa = GROUP_MATERIALY;
        }

        try {
            $wynik = generate_and_send_invitation($email, $grupa, (int) $user['id']);

            flash(
                $wynik['wyslany'] ? 'success' : 'warning',
                $wynik['wyslany']
                    ? 'Kod rejestracyjny ' . $wynik['kod'] . ' wysłany na ' . $wynik['email']
                        . ' (ważny do ' . fmt_date($wynik['wygasa_at']) . ').'
                    : 'Nie udało się wysłać e-maila. Kod rejestracyjny to ' . $wynik['kod']
                        . ' (ważny do ' . fmt_date($wynik['wygasa_at']) . ').'
            );
        } catch (Throwable $ex) {
            flash('error', $ex->getMessage());
        }

        redirect(app_url('admin/uzytkownicy.php'));
    }

    /* --- Ponowna wysyłka kodu rejestracyjnego ---------------------------- */
    if ($akcja === 'zaproszenie_ponow') {
        $zapId = (int) ($_POST['zaproszenie_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT * FROM zaproszenia WHERE id = :id');
        $stmt->execute([':id' => $zapId]);
        $zap = $stmt->fetch();

        if ($zap) {
            try {
                $wynik = generate_and_send_invitation((string) $zap['email'], (string) $zap['grupa'], (int) $user['id']);

                flash(
                    $wynik['wyslany'] ? 'success' : 'warning',
                    $wynik['wyslany']
                        ? 'Nowy kod rejestracyjny ' . $wynik['kod'] . ' wysłany na ' . $wynik['email'] . '.'
                        : 'Nie udało się wysłać e-maila. Kod to ' . $wynik['kod'] . '.'
                );
            } catch (Throwable $ex) {
                flash('error', $ex->getMessage());
            }
        } else {
            flash('error', 'Nie znaleziono zaproszenia.');
        }

        redirect(app_url('admin/uzytkownicy.php'));
    }

    /* --- Anulowanie zaproszenia ------------------------------------------ */
    if ($akcja === 'zaproszenie_anuluj') {
        $zapId = (int) ($_POST['zaproszenie_id'] ?? 0);

        $stmt = $pdo->prepare('SELECT * FROM zaproszenia WHERE id = :id');
        $stmt->execute([':id' => $zapId]);
        $zap = $stmt->fetch();

        if ($zap && $zap['uzyty_at'] === null) {
            $pdo->prepare('UPDATE zaproszenia SET uzyty_at = :now WHERE id = :id')
                ->execute([':now' => date('Y-m-d H:i:s'), ':id' => $zapId]);
            log_action('anulowano_zaproszenie', (string) $zap['email'], (int) $user['id']);
            flash('info', 'Zaproszenie dla ' . $zap['email'] . ' zostało anulowane.');
        }

        redirect(app_url('admin/uzytkownicy.php'));
    }

    /* --- Generuj i wyślij kod ------------------------------------------ */
    if ($akcja === 'kod') {
        $id  = (int) ($_POST['user_id'] ?? 0);
        $cel = (string) ($_POST['cel'] ?? 'logowanie');
        if (!in_array($cel, ['logowanie', 'aktywacja'], true)) {
            $cel = 'logowanie';
        }

        // Administrator nie moze generowac kodu dla innego administratora
        if ($user['grupa'] === GROUP_FIRMA) {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $celUser = $stmt->fetch();
            if ($celUser && $celUser['grupa'] === GROUP_FIRMA && $user['id'] !== $id) {
                flash('warning', 'Nie mozna generowac kodu dla innego administratora.');
                redirect(app_url('admin/uzytkownicy.php'));
            }
        }

        try {
            $wynik = generate_and_send_code($id, $cel);

            // Kod aktywacyjny wymaga ponownej aktywacji konta przez użytkownika.
            if ($cel === 'aktywacja') {
                $pdo->prepare('UPDATE users SET aktywowany = 0, updated_at = :n WHERE id = :id')
                    ->execute([':n' => date('Y-m-d H:i:s'), ':id' => $id]);
            }

            $waznosc = code_is_permanent((string) $wynik['wygasa_at'])
                ? 'kod stały – działa przy każdym logowaniu'
                : 'ważny do ' . fmt_date($wynik['wygasa_at']);

            $info  = $wynik['wyslany']
                ? 'Kod ' . $wynik['kod'] . ' wysłany na ' . $wynik['email'] . ' (' . $waznosc . ').'
                : 'Nie udało się wysłać e-maila. Kod ' . $wynik['kod'] . ' (' . $waznosc . ').';

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

        // Administrator nie moze blokowac innego administratora
        if ($cel && $user['grupa'] === GROUP_FIRMA && $cel['grupa'] === GROUP_FIRMA && $user['id'] !== $cel['id']) {
            flash('warning', 'Nie mozna zmieniac statusu innego administratora.');
            redirect(app_url('admin/uzytkownicy.php'));
        }

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

        // Administrator nie moze zmieniac grupy innego administratora
        if ($id > 0 && $user['grupa'] === GROUP_FIRMA && $cel['grupa'] === GROUP_FIRMA && $user['id'] !== $cel['id']) {
            flash('warning', 'Nie mozna zmieniac grupy innego administratora.');
            redirect(app_url('admin/uzytkownicy.php'));
        }

        if (isset(GROUPS[$grupa])) {
            $pdo->prepare('UPDATE users SET grupa = :g, updated_at = :n WHERE id = :id')
                ->execute([':g' => $grupa, ':n' => date('Y-m-d H:i:s'), ':id' => $id]);
            log_action('zmieniono_grupe', 'user_id=' . $id . ' -> ' . $grupa, (int) $user['id']);
            flash('success', 'Grupa użytkownika została zmieniona.');
        }

        redirect(app_url('admin/uzytkownicy.php'));
    }

    /* --- Wymóg kodu administratora przy logowaniu ------------------------ */
    if ($akcja === 'wymaga_kod') {
        $id = (int) ($_POST['user_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $cel = $stmt->fetch();

        // Administrator nie moze zmieniac wymogu kodu innego administratora
        if ($cel && $user['grupa'] === GROUP_FIRMA && $cel['grupa'] === GROUP_FIRMA && $user['id'] !== $cel['id']) {
            flash('warning', 'Nie mozna zmieniac wymogu kodu innego administratora.');
            redirect(app_url('admin/uzytkownicy.php'));
        }

        if ($cel) {
            $nowa = (int) $cel['wymaga_kod'] === 1 ? 0 : 1;

            $pdo->prepare('UPDATE users SET wymaga_kod = :w, updated_at = :n WHERE id = :id')
                ->execute([':w' => $nowa, ':n' => date('Y-m-d H:i:s'), ':id' => $id]);

            // Po wyłączeniu wymogu kasujemy niewykorzystany kod logowania.
            if ($nowa === 0) {
                $pdo->prepare('UPDATE kody SET uzyty_at = :now WHERE user_id = :u AND cel = :c AND uzyty_at IS NULL')
                    ->execute([
                        ':now' => date('Y-m-d H:i:s'),
                        ':u'   => $id,
                        ':c'   => 'logowanie',
                    ]);
            }

            log_action(
                $nowa === 1 ? 'wlaczono_wymog_kodu' : 'wylaczono_wymog_kodu',
                (string) $cel['email'],
                (int) $user['id']
            );

            flash(
                'success',
                $nowa === 1
                    ? 'Konto ' . $cel['email'] . ' wymaga teraz kodu wysyłanego przez administratora.'
                    : 'Konto ' . $cel['email'] . ' nie wymaga już kodu administratora.'
            );
        }

        redirect(app_url('admin/uzytkownicy.php'));
    }

    /* --- Reset hasła ----------------------------------------------------- */
    if ($akcja === 'reset') {
        $id    = (int) ($_POST['user_id'] ?? 0);
        $haslo = (string) ($_POST['haslo'] ?? '');

        // Administrator nie moze resetowac hasla innego administratora
        if ($id > 0 && $user['grupa'] === GROUP_FIRMA && $user['id'] !== $id) {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $cel = $stmt->fetch();
            if ($cel && $cel['grupa'] === GROUP_FIRMA) {
                flash('warning', 'Nie mozna resetowac hasla innego administratora.');
                redirect(app_url('admin/uzytkownicy.php'));
            }
        }

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

/* Podział listy kont na dwie grupy: administratorzy (firma) i użytkownicy (materiały). */
$admins     = array_values(array_filter(
    $users,
    static fn (array $u): bool => $u['grupa'] === GROUP_FIRMA
));
$listaUsers = array_values(array_filter(
    $users,
    static fn (array $u): bool => $u['grupa'] !== GROUP_FIRMA
));

/* Zaproszenia – oczekujące na rejestrację (najnowsze na górze). */
$stmtZap = $pdo->prepare(
    'SELECT z.*, u.imie AS imie_zapraszajacego
       FROM zaproszenia z
       LEFT JOIN users u ON u.id = z.utworzony_przez
      ORDER BY (z.uzyty_at IS NOT NULL), z.created_at DESC
      LIMIT 50'
);
$stmtZap->execute();
$zaproszenia = $stmtZap->fetchAll();

/**
 * Renderuje tabelę kont dla jednej grupy (wiersz = jedno konto).
 *
 * @param array<int,array<string,mixed>> $lista  konta danej grupy
 * @param array<string,mixed>            $biezacy zalogowany użytkownik (znacznik „Ty”)
 */
function render_tabela_kont(array $lista, array $biezacy): void
{
    ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr>
                <th>Imię</th>
                <th>E-mail</th>
                <th>Grupa</th>
                <th>Status</th>
                <?php if ((int) $biezacy['grupa'] === GROUP_FIRMA && $u['grupa'] !== GROUP_FIRMA): ?>
                <th>Aktywny kod</th>
                <?php endif; ?>
                <th>Wymóg kodu</th>
                <th>Slajdy</th>
                <th class="th-actions">Akcje</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lista as $u): ?>
                <tr>
                    <td>
                        <strong><?= e($u['imie']) ?></strong>
                        <?php if ((int) $u['id'] === (int) $biezacy['id']): ?>
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
                    <?php if ((int) $biezacy['grupa'] === GROUP_FIRMA && $u['grupa'] !== GROUP_FIRMA): ?>
                    <td>
                        <?php if (!empty($u['aktywny_kod'])): ?>
                            <span class="code-inline"><?= e($u['aktywny_kod']) ?></span>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td>
                        <?php if ((int) $u['wymaga_kod'] === 1): ?>
                            <span class="badge badge-warn">wymaga kodu</span>
                        <?php elseif ($u['grupa'] === GROUP_MATERIALY): ?>
                            <span class="badge badge-ok">logowanie kodem</span>
                        <?php else: ?>
                            <span class="muted small">bez wymogu</span>
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
                            <input type="hidden" name="akcja" value="wymaga_kod">
                            <input type="hidden" name="user_id" value="<?= e((string) $u['id']) ?>">
                            <button type="submit" class="btn btn-sm btn-ghost">
                                <?= (int) $u['wymaga_kod'] === 1 ? 'Bez wymogu kodu' : 'Wymaga kodu' ?>
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
    <?php
}

layout_header('Użytkownicy', 'uzytkownicy.php');
?>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>
<?php if ($info !== ''): ?>
    <div class="alert alert-success"><?= e($info) ?></div>
<?php endif; ?>

<section class="card">
    <h2 class="card-title">Wyślij kod rejestracyjny</h2>
    <p class="muted">
        Administrator nie zakłada kont – wysyła kod na adres e-mail, a odbiorca sam rejestruje się
        w panelu (e-mail + kod + hasło). Kod jest ważny <?= e((string) CODE_TTL_MINUTES) ?> minut
        i może zostać użyty tylko raz.
    </p>

    <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <input type="hidden" name="akcja" value="zaproszenie">

        <div>
            <label for="email">E-mail odbiorcy</label>
            <input type="email" id="email" name="email" required
                   placeholder="np. jan.kowalski@firma.pl">
        </div>
        <div>
            <label for="grupa">Grupa po rejestracji</label>
            <select id="grupa" name="grupa">
                <?php foreach (GROUPS as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-grid-actions">
            <button type="submit" class="btn btn-primary">Generuj kod i wyślij</button>
        </div>
    </form>
</section>

<section class="card">
    <h2 class="card-title">
        Zaproszenia <span class="muted">(<?= e((string) count($zaproszenia)) ?>)</span>
    </h2>

    <?php if (!$zaproszenia): ?>
        <p class="muted small">Brak zaproszeń.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>E-mail</th>
                    <th>Grupa</th>
                    <th>Kod</th>
                    <th>Ważność</th>
                    <th>Status</th>
                    <th class="th-actions">Akcje</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($zaproszenia as $z): ?>
                    <?php
                    $wygaslo = strtotime((string) $z['wygasa_at']) <= time();
                    $uzyte   = $z['uzyty_at'] !== null;
                    ?>
                    <tr>
                        <td class="mono"><?= e($z['email']) ?></td>
                        <td><?= e(GROUPS[$z['grupa']] ?? $z['grupa']) ?></td>
                        <td>
                            <?php if (!$uzyte && !$wygaslo): ?>
                                <span class="code-inline"><?= e($z['kod']) ?></span>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="muted">
                            <?= e(fmt_date($z['wygasa_at'])) ?>
                            <?php if (!$uzyte && !$wygaslo): ?>
                                <span class="muted small">(<?= e(fmt_remaining($z['wygasa_at'])) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($uzyte): ?>
                                <span class="badge badge-off">wykorzystane</span>
                            <?php elseif ($wygaslo): ?>
                                <span class="badge badge-warn">wygasłe</span>
                            <?php else: ?>
                                <span class="badge badge-ok">oczekuje</span>
                            <?php endif; ?>
                        </td>
                        <td class="td-actions">
                            <?php if (!$uzyte): ?>
                                <form method="post" class="form-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="akcja" value="zaproszenie_ponow">
                                    <input type="hidden" name="zaproszenie_id" value="<?= e((string) $z['id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-ghost">Wyślij ponownie</button>
                                </form>

                                <form method="post" class="form-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="akcja" value="zaproszenie_anuluj">
                                    <input type="hidden" name="zaproszenie_id" value="<?= e((string) $z['id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-ghost">Anuluj</button>
                                </form>
                            <?php else: ?>
                                <span class="muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <h2 class="card-title">
        Administratorzy <span class="muted">(grupa firmy, <?= e((string) count($admins)) ?>)</span>
    </h2>
    <p class="muted small">
        Konta grupy firmy – logują się w zakładce „Administrator” (e-mail + hasło), opcjonalnie
        z dodatkowym kodem („Wymaga kodu”).
    </p>

    <?php if (!$admins): ?>
        <p class="muted small">Brak kont administratorów.</p>
    <?php else: ?>
        <?php render_tabela_kont($admins, $user); ?>
    <?php endif; ?>
</section>

<section class="card">
    <h2 class="card-title">
        Użytkownicy <span class="muted">(grupa materiałów, <?= e((string) count($listaUsers)) ?>)</span>
    </h2>
    <p class="muted small">
        Konta zakładają sami użytkownicy po otrzymaniu kodu rejestracyjnego.
        „Generuj i wyślij kod” wysyła na e-mail konta kod logowania: użytkownik grupy
        materiałów loguje się nim samym (zakładka „Użytkownik”), a konto grupy firmy
        może dodatkowo wymagać kodu przy każdym logowaniu (przycisk „Wymaga kodu”).
    </p>

    <?php if (!$listaUsers): ?>
        <p class="muted small">Brak kont użytkowników.</p>
    <?php else: ?>
        <?php render_tabela_kont($listaUsers, $user); ?>
    <?php endif; ?>
</section>

<?php layout_footer(); ?>
