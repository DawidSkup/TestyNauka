<?php
declare(strict_types=1);

/**
 * Konfiguracja aplikacji "TestNauka".
 * Panel administracyjny: grupa firmy + grupa materiałów (wyłącznie slajdy).
 */

const APP_NAME       = 'Panel TestNauka';
const APP_COMPANY    = 'Firma Testowa';
const APP_SHORT      = 'TestNauka';

/* Strefa czasowa dla dat i terminów ważności kodów. */
date_default_timezone_set('Europe/Warsaw');

const DB_FILE        = __DIR__ . '/data/app.sqlite';
const UPLOAD_PATH    = __DIR__ . '/data/uploads';
const UPLOAD_URL     = 'data/uploads';

/* Materiały przyjmowane są WYŁĄCZNIE jako slajdy – dozwolone typy obrazów. */
const MAX_UPLOAD_BYTES = 5 * 1024 * 1024;
const ALLOWED_IMAGES   = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

/* Kod weryfikacyjny: 6 znaków (cyfry + wielkie litery). */
const CODE_LENGTH      = 6;
const CODE_TTL_MINUTES = 15;

const MAIL_FROM      = 'panel@testnauka.local';
const MAIL_FROM_NAME = APP_COMPANY . ' – Panel szkoleniowy';

const SESSION_NAME = 'testnauka_admin';

/* Grupy użytkowników. */
const GROUP_FIRMA     = 'firma';
const GROUP_MATERIALY = 'materialy';

const GROUPS = [
    GROUP_FIRMA     => 'Grupa firmy',
    GROUP_MATERIALY => 'Grupa materiałów (slajdy)',
];
