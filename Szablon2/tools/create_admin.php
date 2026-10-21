<?php
/**
 * Tworzy (lub aktualizuje hasło) konta administratora. Działa wyłącznie z wiersza poleceń:
 *   php tools/create_admin.php <login> <hasło>
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Dostępne tylko z wiersza poleceń.\n");
}

require_once __DIR__ . '/../includes/bootstrap.php';

if ($argc < 3) {
    fwrite(STDERR, "Użycie: php tools/create_admin.php <login> <hasło>\n");
    exit(1);
}

$login    = trim($argv[1]);
$password = $argv[2];

if ($login === '' || strlen($password) < 8) {
    fwrite(STDERR, "Login nie może być pusty, a hasło musi mieć min. 8 znaków.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$pdo  = db();

$st = $pdo->prepare('SELECT COUNT(*) FROM admins WHERE login = ?');
$st->execute([$login]);

if ((int)$st->fetchColumn() > 0) {
    $pdo->prepare('UPDATE admins SET `password` = ? WHERE login = ?')->execute([$hash, $login]);
    echo "Zaktualizowano hasło administratora „$login\".\n";
} else {
    $pdo->prepare('INSERT INTO admins (login, `password`) VALUES (?, ?)')->execute([$login, $hash]);
    echo "Utworzono administratora „$login\".\n";
}
