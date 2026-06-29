<?php
// config/settings.php - システム設定（DB管理）

require_once __DIR__ . '/database.php';

class Settings {
    private static array $cache = [];
    private static bool $loaded = false;

    public static function load(): void {
        if (self::$loaded) return;
        try {
            $pdo  = get_pdo();
            $rows = $pdo->query("SELECT `key`, `value` FROM system_settings")->fetchAll();
            foreach ($rows as $row) {
                self::$cache[$row['key']] = $row['value'];
            }
        } catch (\Throwable $e) {
            // DBが未初期化の場合は無視
        }
        self::$loaded = true;
    }

    public static function get(string $key, string $default = ''): string {
        self::load();
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, string $value): void {
        $pdo  = get_pdo();
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (`key`, `value`, updated_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE `value` = ?, updated_at = NOW()
        ");
        $stmt->execute([$key, $value, $value]);
        self::$cache[$key] = $value;
    }

    public static function all(): array {
        self::load();
        return self::$cache;
    }

    public static function lineChannelSecret(): string    { return self::get('line_channel_secret'); }
    public static function lineAccessToken(): string      { return self::get('line_channel_access_token'); }
    public static function claudeApiKey(): string         { return self::get('claude_api_key'); }
    public static function stabilityApiKey(): string      { return self::get('stability_api_key'); }
    public static function storageDriver(): string        { return self::get('storage_driver', 'local'); }
    public static function storagePublicUrl(): string     { return self::get('storage_public_url'); }
    public static function r2AccountId(): string          { return self::get('r2_account_id'); }
    public static function r2AccessKey(): string          { return self::get('r2_access_key'); }
    public static function r2SecretKey(): string          { return self::get('r2_secret_key'); }
    public static function r2Bucket(): string             { return self::get('r2_bucket'); }
    public static function maxDailyPerUser(): int         { return (int) self::get('max_daily_requests_per_user', '2'); }
    public static function maxImagesPerRequest(): int     { return (int) self::get('max_images_per_request', '8'); }
    public static function adminEmail(): string           { return self::get('admin_email'); }
}

// CSRF ヘルパー（config/app.php で定義済みだが、settings.php は保護対象外なので
// こちらにも定義することで確実に読み込まれるようにする）
if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $token = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(): void {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $sessionToken = $_SESSION['csrf_token'] ?? '';
        $postToken    = $_POST['csrf_token'] ?? '';
        if (!$sessionToken || !hash_equals($sessionToken, $postToken)) {
            http_response_code(403);
            exit('Invalid CSRF token');
        }
    }
}
