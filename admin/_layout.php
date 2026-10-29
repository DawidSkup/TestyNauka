<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

/**
 * Wspólny nagłówek panelu: sidebar z menu zależnym od grupy użytkownika.
 */
function layout_header(string $title, string $active = ''): void
{
    $user = current_user();
    $flashes = flash_take();

    $menu = [
        ['url' => 'index.php',       'label' => 'Panel startowy', 'grp' => null,             'icon' => '▤'],
        ['url' => 'prezentacje.php', 'label' => 'Prezentacje',    'grp' => null,             'icon' => '▶'],
        ['url' => 'testy.php',       'label' => 'Testy',         'grp' => null,             'icon' => '✓'],
        ['url' => 'uzytkownicy.php', 'label' => 'Użytkownicy',    'grp' => GROUP_FIRMA,      'icon' => '☰'],
        ['url' => 'wyniki.php', 'label' => 'Wyniki',        'grp' => null,      'icon' => '📊'],
    ];
    ?>
    <!doctype html>
    <html lang="pl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> – <?= e(APP_NAME) ?></title>
        <link rel="stylesheet" href="<?= e(app_asset_url('assets/style.css')) ?>">
    </head>
    <body class="page-panel">
    <div class="layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <span class="logo"><?= e(mb_substr(APP_COMPANY, 0, 1)) ?></span>
                <div>
                    <strong><?= e(APP_SHORT) ?></strong>
                    <small><?= e(APP_COMPANY) ?></small>
                </div>
            </div>

            <nav class="menu">
                <?php foreach ($menu as $item): ?>
                    <?php if ($item['grp'] !== null && !in_group($item['grp'])) { continue; } ?>
                    <a class="menu-item<?= $active === $item['url'] ? ' is-active' : '' ?>"
                       href="<?= e(app_url('admin/' . $item['url'])) ?>">
                        <span class="menu-icon"><?= e($item['icon']) ?></span>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar-user">
                <div class="avatar"><?= e(mb_strtoupper(mb_substr((string) $user['imie'], 0, 1))) ?></div>
                <div class="sidebar-user-info">
                    <strong><?= e($user['imie']) ?></strong>
                    <small><?= e(GROUPS[$user['grupa']] ?? $user['grupa']) ?></small>
                </div>
            </div>
            <a class="btn btn-ghost btn-block btn-sm" href="<?= e(app_url('logout.php')) ?>">Wyloguj się</a>
        </aside>

        <main class="content">
            <header class="content-head">
                <button class="btn btn-ghost btn-sm nav-toggle" id="navToggle" type="button" aria-label="Menu">☰</button>
                <h1 class="page-title"><?= e($title) ?></h1>
                <div class="content-head-actions">
                    <?= $user['grupa'] === GROUP_FIRMA
                        ? '<span class="badge badge-firma">Grupa firmy</span>'
                        : '<span class="badge badge-materialy">Grupa materiałów</span>' ?>
                    <button class="btn btn-ghost btn-sm theme-toggle" aria-label="Tryb widoku" id="themeToggle" title="Jasny">
                        ☀️
                    </button>
                </div>
            </header>

            <?php foreach ($flashes as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
            <?php endforeach; ?>
    <?php
}

/**
 * Stopka panelu + skrypt JS.
 */
function layout_footer(): void
{
    ?>
        </main>
    </div>
    <script src="<?= e(app_asset_url('assets/app.js')) ?>"></script>
    <script>
        // Theme toggle - class-based approach for reliable theming
        (function () {
            'use strict';
            const html = document.documentElement;
            const btn = document.getElementById('themeToggle');
            if (!btn) return;

            const themes = ['light', 'dark', 'auto'];
            const stored = localStorage.getItem('tn-theme');
            let theme = 'light';

            if (stored) {
                theme = stored;
            } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                theme = 'dark';
            }

            // Apply theme using classes
            html.classList.remove('theme-light', 'theme-dark', 'theme-auto');
            if (theme === 'dark') html.classList.add('theme-dark');
            else if (theme === 'light') html.classList.add('theme-light');
            else if (theme === 'auto') html.classList.add('theme-auto');

            // Update button appearance
            btn.innerHTML = theme === 'light' ? '☀️' : theme === 'dark' ? '🌙' : 'A';
            btn.title = theme === 'Jasny' ? 'Jasny' : theme === 'Ciemny' ? 'Ciemny' : 'Automatyczny';

            btn.addEventListener('click', function () {
                const currentIdx = themes.indexOf(theme);
                const nextIdx = (currentIdx + 1) % themes.length;
                theme = themes[nextIdx];
                html.classList.remove('theme-light', 'theme-dark', 'theme-auto');
                if (theme === 'dark') html.classList.add('theme-dark');
                else if (theme === 'light') html.classList.add('theme-light');
                else if (theme === 'auto') html.classList.add('theme-auto');
                localStorage.setItem('tn-theme', theme);
                btn.innerHTML = theme === 'light' ? '☀️' : theme === 'dark' ? '🌙' : 'A';
                btn.title = theme === 'Jasny' ? 'Jasny' : theme === 'Ciemny' ? 'Ciemny' : 'Automatyczny';
            });

            // Initialize button state
            function updateButtonTheme(t) {
                btn.innerHTML = t === 'light' ? '☀️' : t === 'dark' ? '🌙' : 'A';
                btn.title = t === 'Jasny' ? 'Jasny' : t === 'Ciemny' ? 'Ciemny' : 'Automatyczny';
            }
            updateButtonTheme(theme);
        })();

        // Obsługa zmiany hasła administratora
        (function () {
    <?php
}
