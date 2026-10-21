<?php
/** Pojedyncze połączenie PDO na czas żądania. */
function db()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $c = cfg('db');
    try {
        $pdo = new PDO(
            'mysql:host=' . $c['host'] . ';dbname=' . $c['name'] . ';charset=utf8mb4',
            $c['user'],
            $c['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        fatal_page(
            'Brak połączenia z bazą danych',
            'Sprawdź ustawienia w includes/config.php (lub config.local.php) oraz czy serwer MySQL działa.'
            . (cfg('debug') ? '<br><small>' . e($e->getMessage()) . '</small>' : '')
        );
    }
    return $pdo;
}

/** Prosta strona błędu krytycznego. */
function fatal_page($title, $messageHtml)
{
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="pl"><head><meta charset="UTF-8"><title>' . e($title) . '</title>'
        . '<style>body{font-family:system-ui,sans-serif;background:#0d1b2a;color:#fff;display:flex;'
        . 'align-items:center;justify-content:center;min-height:100vh;margin:0}'
        . '.b{background:#162436;padding:40px;border-radius:16px;max-width:520px}h1{font-size:20px;margin:0 0 12px}'
        . 'p{color:rgba(255,255,255,.7);line-height:1.6;margin:0}</style></head><body><div class="b"><h1>'
        . e($title) . '</h1><p>' . $messageHtml . '</p></div></body></html>';
    exit;
}
