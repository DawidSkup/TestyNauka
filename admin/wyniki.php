<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/_layout.php';

require_login();

$pdo  = db();
$user = current_user();

/* Pobierz wyniki bieżącego użytkownika */
$stmtUserResults = $pdo->prepare('SELECT test_id, wynik, maks, procent FROM wyniki_testow WHERE user_id = :user_id');
$stmtUserResults->execute([':user_id' => (int) $user['id']]);

$userResults = [];
foreach ($stmtUserResults->fetchAll() as $row) {
    $userResults[(int) $row['test_id']] = $row;
}

/* Pobierz wszystkie opublikowane testy z numerami */
$testy = testy_wg_numeru($pdo);

/* Zbierz numerację testów */
$numeryTestow = [];
foreach ($testy as $numer => $data) {
    $numeryTestow[(int) $numer] = (int) $numer;
}
ksort($numeryTestow);

layout_header('Moje wyniki', 'wyniki.php');
?> ?>

<section class="page panel">
    <div class="layout">
        <main class="content">
            <div class="page-header">
                <h1 class="page-title">Moje wyniki</h1>
                <p class="muted small">Tylko Twoje wyniki</p>
            </div>

            <div class="table-responsive">
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
                        $testId = null;
                        foreach ($testy as $tN => $tData) {
                            if ((int) $tN === (int) $numer) {
                                $testId = (int) $tData['id'];
                                break;
                            }
                        }
                        ?>
                        <tr>
                            <td>Test <?= e((string) $numer) ?></td>
                            <td>
                                <?php if ($testId !== null && isset($userResults[$testId])): ?>
                                    <?= e((string) ($userResults[$testId]['wynik'] ?? 0)) ?>/<?= e((string) ($userResults[$testId]['maks'] ?? 0)) ?> (<?= e((string) ($userResults[$testId]['procent'] ?? 0)) ?>%)
                                <?php else: ?>
                                    brak
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
        </main>
    </div>
</section>

<?php layout_footer(); ?>