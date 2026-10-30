<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/_layout.php';

require_login();

$pdo  = db();
$user = current_user();

$jestFirma = in_group(GROUP_FIRMA);

/* Pobierz wszystkie opublikowane testy z numerami */
$testy = testy_wg_numeru($pdo);

/* Zbierz numerację testów */
$numeryTestow = array_keys($testy);
sort($numeryTestow, SORT_NUMERIC);

if ($jestFirma) {
    /* Administrator widzi wyniki zwykłych użytkowników (bez administratorów) */
    $stmtUsers = $pdo->query(
        "SELECT id, imie, email, grupa 
           FROM users 
          WHERE aktywny = 1 
            AND grupa != 'firma'
       ORDER BY grupa ASC, imie ASC, email ASC"
    );
    $users = $stmtUsers->fetchAll();

    /* Pobierz wszystkie wyniki testów */
    $stmtAllResults = $pdo->query('SELECT user_id, test_id, wynik, maks, procent FROM wyniki_testow');
    $allResults = [];
    foreach ($stmtAllResults->fetchAll() as $row) {
        $uid = (int) $row['user_id'];
        $tid = (int) $row['test_id'];
        if (!isset($allResults[$uid])) {
            $allResults[$uid] = [];
        }
        $allResults[$uid][$tid] = $row;
    }

    layout_header('Wyniki wszystkich użytkowników', 'wyniki.php');
    ?>

    <section class="card">
        <h2 class="card-title">Wyniki wszystkich użytkowników</h2>
        <p class="muted small">Podgląd wyników wszystkich aktywnych użytkowników</p>

        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Użytkownik</th>
                    <th>Grupa</th>
                    <?php foreach ($numeryTestow as $numer): ?>
                        <th>Test <?= e((string) $numer) ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="<?= 2 + count($numeryTestow) ?>" class="muted">Brak użytkowników</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <?php
                        $uid        = (int) $u['id'];
                        $grupaLabel = GROUPS[$u['grupa']] ?? $u['grupa'];
                        ?>
                        <tr>
                            <td>
                                <strong><?= e($u['imie']) ?></strong><br>
                                <span class="muted small"><?= e($u['email']) ?></span>
                            </td>
                            <td><?= e($grupaLabel) ?></td>
                            <?php foreach ($numeryTestow as $numer): ?>
                                <?php
                                $numerInt = (int) $numer;
                                $testData = $testy[$numerInt] ?? null;
                                $testId   = $testData['id'] ?? null;
                                $res      = null;

                                if ($testId !== null && isset($allResults[$uid][$testId])) {
                                    $res = $allResults[$uid][$testId];
                                }
                                ?>
                                <td>
                                    <?php if ($res !== null): ?>
                                        <?= e((string) ($res['wynik'] ?? 0)) ?>/<?= e((string) ($res['maks'] ?? 0)) ?>
                                        <span class="muted"> (<?= e((string) ($res['procent'] ?? 0)) ?>%)</span>
                                    <?php else: ?>
                                        <span class="muted">brak</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
} else {
    /* Użytkownik grupy materialy widzi tylko swoje wyniki */
    $stmtUserResults = $pdo->prepare('SELECT test_id, wynik, maks, procent FROM wyniki_testow WHERE user_id = :user_id');
    $stmtUserResults->execute([':user_id' => (int) $user['id']]);

    $userResults = [];
    foreach ($stmtUserResults->fetchAll() as $row) {
        $userResults[(int) $row['test_id']] = $row;
    }

    layout_header('Moje wyniki', 'wyniki.php');
    ?>

    <section class="card">
        <h2 class="card-title">Moje wyniki</h2>
        <p class="muted small">Tylko Twoje wyniki</p>

        <div class="table-wrap">
            <table class="table">
                <thead>
                <tr>
                    <th>Test</th>
                    <th>Wynik</th>
                    <th>Procent</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($numeryTestow as $numer): ?>
                    <?php
                    $numerInt = (int) $numer;
                    $testData = $testy[$numerInt] ?? null;
                    $testId   = $testData['id'] ?? null;
                    ?>
                    <tr>
                        <td>Test <?= e((string) $numerInt) ?></td>
                        <td>
                            <?php if ($testId !== null && isset($userResults[$testId])): ?>
                                <?= e((string) ($userResults[$testId]['wynik'] ?? 0)) ?>/<?= e((string) ($userResults[$testId]['maks'] ?? 0)) ?> (<?= e((string) ($userResults[$testId]['procent'] ?? 0)) ?>%)
                            <?php else: ?>
                                <span class="muted">brak</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($testId !== null && isset($userResults[$testId])): ?>
                                <?= e((string) ($userResults[$testId]['procent'] ?? 0)) ?>%
                            <?php else: ?>
                                0%
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
}

layout_footer(); ?>
