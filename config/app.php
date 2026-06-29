<?php
// config/app.php

// エラー設定：本番は画面非表示・ログのみ。ローカル開発時は APP_ENV=local に
if (getenv('APP_ENV') === 'local') {
    ini_set('display_errors', 1);
} else {
    ini_set('display_errors', 0);
}
ini_set('log_errors', 1);
error_reporting(E_ALL);

// バージョンは VERSION ファイルから読み込む（アップデート時に更新される）
if (!defined('APP_VERSION')) {
    $__vf = dirname(__DIR__) . '/VERSION';
    define('APP_VERSION', is_file($__vf) ? trim(file_get_contents($__vf)) : '1.0.0');
}
defined('APP_NAME')     || define('APP_NAME',     'AIアート教室 LINE画像生成システム');
defined('BASE_PATH')    || define('BASE_PATH',    dirname(__DIR__));
defined('CONFIG_PATH')  || define('CONFIG_PATH',  BASE_PATH . '/config');
defined('STORAGE_PATH') || define('STORAGE_PATH', BASE_PATH . '/storage');
defined('LOG_PATH')     || define('LOG_PATH',     STORAGE_PATH . '/logs');
defined('INSTALLED')    || define('INSTALLED',    file_exists(CONFIG_PATH . '/installed.lock'));

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.gc_maxlifetime', 3600);
    session_start();
}

date_default_timezone_set('Asia/Tokyo');

// CSRF トークン生成（管理画面POSTのセキュリティ用）
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * CSRFトークンの hidden input を出力する
 */
if (!function_exists('csrf_field')) {
function csrf_field(): string {
    $token = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}
}

/**
 * POSTリクエストのCSRFトークンを検証する。失敗時は 403 で停止。
 * 管理画面の POST ハンドラ冒頭で呼ぶ。
 */
if (!function_exists('verify_csrf')) {
function verify_csrf(): void {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $postToken    = $_POST['csrf_token'] ?? '';
    if (!$sessionToken || !hash_equals($sessionToken, $postToken)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}
}
