<?php
// config/database.php - DB接続

require_once __DIR__ . '/app.php';

function get_db_config(): array {
    $cfg_file = CONFIG_PATH . '/db.php';
    if (file_exists($cfg_file)) {
        return require $cfg_file;
    }
    // .env fallback
    return [
        'host'     => getenv('DB_HOST')     ?: 'localhost',
        'name'     => getenv('DB_NAME')     ?: 'ai_art_line',
        'user'     => getenv('DB_USER')     ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset'  => 'utf8mb4',
    ];
}

function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $cfg = get_db_config();
    $dsn = "mysql:host={$cfg['host']};dbname={$cfg['name']};charset={$cfg['charset']}";
    $pdo = new PDO($dsn, $cfg['user'], $cfg['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}
