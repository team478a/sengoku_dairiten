<?php
// app/Services/AuditLog.php
// 管理操作ログ

require_once BASE_PATH . '/config/database.php';

class AuditLog {
    // テーブル自動作成
    public static function ensureTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NULL,
                admin_name VARCHAR(255) NULL,
                action VARCHAR(100) NOT NULL,
                target VARCHAR(255) NULL,
                detail TEXT NULL,
                created_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    public static function record(string $action, string $target = '', string $detail = ''): void {
        try {
            $pdo = get_pdo();
            self::ensureTable($pdo);
            $adminId   = $_SESSION['admin_id']   ?? null;
            $adminName = $_SESSION['admin_name'] ?? ($_SESSION['admin_email'] ?? 'システム');
            $pdo->prepare("
                INSERT INTO audit_logs (admin_id, admin_name, action, target, detail, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ")->execute([$adminId, $adminName, $action, $target, $detail]);
        } catch (\Throwable $e) {
            // ログ失敗は無視
        }
    }

    public static function recent(PDO $pdo, int $limit = 100): array {
        self::ensureTable($pdo);
        $stmt = $pdo->prepare("SELECT * FROM audit_logs ORDER BY id DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
