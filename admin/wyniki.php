require_once __DIR__ . '/_layout.php';

$user = current_user();

/* Pobierz wyniki bieżącego użytkownika */
$stmtUserResults = $pdo->query("SELECT test_id, wynik, maks, procent FROM wyniki_testow WHERE user_id = :user_id");
$stmtUserResults->execute([':user_id' => $user['id']]);
$userResults = $stmtUserResults->fetchAll(PDO::FETCH_KEY_PAIR);

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
                    <?php foreach ($numeryTestow as $numer => $etykieta): ?>
                        <tr>
                            <td>Test <?= e($numer) ?></td>
                            <td>
                                <?php if (isset($userResults[$testy[$numer]['id']])): ?>
                                    <?= sprintf('%d/%d (%d%%)', $userResults[$testy[$numer]['id']]['wynik'] ?? 0, $userResults[$testy[$numer]['id']]['maks'] ?? 0, $userResults[$testy[$numer]['id']]['procent'] ?? 0); ?>
                                <?php else: ?>
                                    brak
                                <?php endif; ?>
                            </td>
                            <td><?php echo $userResults[$testy[$numer]['id']]['procent'] ?? 0; ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</section>

<?php layout_footer(); ?>