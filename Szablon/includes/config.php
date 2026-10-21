<?php
/**
 * Domyślna konfiguracja aplikacji.
 * Własne ustawienia umieść w includes/config.local.php (ten plik jest ignorowany przez Git)
 * – wystarczy, że zwróci tablicę z nadpisywanymi kluczami.
 */
return [
    'app_name'  => 'Harmonogram Pracy',
    'ward_name' => 'Oddział [NAZWA ODDZIAŁU]',
    'timezone'  => 'Europe/Warsaw',

    // true = wyświetlaj błędy PHP (tylko na maszynie developerskiej!)
    'debug' => false,

    'db' => [
        'host' => getenv('HP_DB_HOST') ?: 'localhost',
        'name' => getenv('HP_DB_NAME') ?: 'szablon',
        'user' => getenv('HP_DB_USER') ?: 'root',
        'pass' => getenv('HP_DB_PASS') !== false ? getenv('HP_DB_PASS') : '',
    ],

    // Hasło początkowe pracownika = Nazwisko + ten sufiks
    'default_password_suffix' => '@[SKRÓT ODDZIAŁU]',

    // Minimalna długość nowego hasła
    'password_min_length' => 8,

    // Wylogowanie po bezczynności (sekundy)
    'session_idle_timeout' => 7200,

    // Blokada logowania po kolejnych nieudanych próbach
    'login_max_attempts' => 5,
    'login_lock_seconds' => 120,
];
