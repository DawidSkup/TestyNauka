<?php
declare(strict_types=1);

require_once __DIR__ . '/_layout.php';

require_group(GROUP_FIRMA);

$pdo = db();
$user = current_user();

/* Pobierz wszystkich użytkowników */
$stmtUsers = $pdo->query("SELECT u.id, u.imie, u.email, u.grupa FROM users u ORDER BY u.imie COLLATE NOCASE");
$users = $stmtUsers->fetchAll();

/* Pobierz wszystkie opublikowane testy z numerami */
$testy = testy_wg_numeru($pdo);

/* Zbierz numerację testów */
$numeryTestow = [];
foreach ($testy as $numer => $data) {
    $numeryTestow[$numer] = $numer;
}
ksort($numeryTestow);
?>
<?php layout_header('Wyniki wszystkich użytkowników', 'wyniki.php'); ?>

<section class="page panel">
    <div class="layout">
        <aside class="sidebar">
            <?php include __DIR__ . '/_sidebar.php'; ?>
        </aside>
        <main class="content">
            <div class="page-header">
                <h1 class="page-title">Wyniki wszystkich użytkowników</h1>
                <p class="muted small">Tylko dla grupy firmy</p>
            </div>

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
        </main>
    </div>
</section>

<?php layout_footer(); ?>