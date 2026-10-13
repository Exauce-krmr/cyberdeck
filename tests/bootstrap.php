<?php

declare(strict_types=1);

// Tests sans Composer ni MariaDB : autoloader minimal + base SQLite créée depuis db/init.sql.

define('BASE_PATH', dirname(__DIR__));
$_SESSION = [];

// Un avertissement PHP (clé absente, etc.) fait échouer le test au lieu de passer inaperçu.
set_error_handler(function (int $level, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $level, $file, $line);
});

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        require BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    }
});

/** Crée une base SQLite en mémoire avec le même schéma et les mêmes cartes que MariaDB. */
function testDatabase(): PDO
{
    $sql = file_get_contents(BASE_PATH . '/db/init.sql');
    $sql = preg_replace('/\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;/', ');', $sql);
    // Particularités MariaDB de db/init.sql, sans équivalent direct en SQLite : ALTER ... IF NOT EXISTS (inutile sur une base neuve)
    // et l'upsert « ON DUPLICATE KEY UPDATE col = VALUES(col) » (SQLite : ON CONFLICT(slug) DO UPDATE SET col = excluded.col).
    $sql = preg_replace('/^ALTER TABLE .*;$/m', '', $sql);
    $sql = str_replace('ON DUPLICATE KEY UPDATE', 'ON CONFLICT(slug) DO UPDATE SET', $sql);
    $sql = preg_replace('/VALUES\((\w+)\)/', 'excluded.$1', $sql);
    $sql = str_replace('INT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace('/(TINYINT|SMALLINT|INT) UNSIGNED/', 'INTEGER', $sql);

    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec($sql);

    return $pdo;
}
