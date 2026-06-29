<?php
// app/Services/PaymentLog.php
// 決済履歴の記録・参照

require_once BASE_PATH . '/config/database.php';

class PaymentLog {
    public static function ensureTable(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS payment_transactions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                line_user_id VARCHAR(255) NULL,
                kind VARCHAR(30) NOT NULL,
                amount INT NOT NULL DEFAULT 0,
                status VARCHAR(30) NOT NULL DEFAULT 'paid',
                description VARCHAR(255) NULL,
                stripe_session_id VARCHAR(255) NULL,
                stripe_payment_intent VARCHAR(255) NULL,
                refunded_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_user (user_id),
                INDEX idx_created (created_at),
                UNIQUE KEY uq_stripe_session_id (stripe_session_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        // 既存テーブルへのUNIQUE制約追加（マイグレーション）
        try {
            $pdo->exec("ALTER TABLE payment_transactions ADD UNIQUE KEY uq_stripe_session_id (stripe_session_id)");
        } catch (\PDOException $e) {
            // 既に存在する場合は無視
        }
    }

    // 同一stripe_session_idが処理済みかチェック（Webhook冪等性）
    public static function existsByStripeSessionId(string $sessionId): bool {
        if (!$sessionId) return false;
        $pdo = get_pdo();
        self::ensureTable($pdo);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM payment_transactions WHERE stripe_session_id = ?");
        $stmt->execute([$sessionId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    // kind: attendance / ticket / subscription
    public static function record(array $data): int {
        $pdo = get_pdo();
        self::ensureTable($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO payment_transactions
                (user_id, line_user_id, kind, amount, status, description, stripe_session_id, stripe_payment_intent, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $data['user_id'] ?? null,
            $data['line_user_id'] ?? null,
            $data['kind'] ?? 'attendance',
            (int)($data['amount'] ?? 0),
            $data['status'] ?? 'paid',
            $data['description'] ?? '',
            $data['stripe_session_id'] ?? null,
            $data['stripe_payment_intent'] ?? null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function markRefunded(PDO $pdo, int $id): void {
        $pdo->prepare("UPDATE payment_transactions SET status='refunded', refunded_at=NOW() WHERE id=?")
            ->execute([$id]);
    }

    public static function recent(PDO $pdo, int $limit = 100, string $filter = ''): array {
        self::ensureTable($pdo);
        $sql = "SELECT p.*, u.display_name FROM payment_transactions p
                LEFT JOIN users u ON u.id = p.user_id";
        $params = [];
        if ($filter !== '') {
            $sql .= " WHERE p.kind = ?";
            $params[] = $filter;
        }
        $sql .= " ORDER BY p.id DESC LIMIT ?";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $i => $v) $stmt->bindValue($i+1, $v);
        $stmt->bindValue(count($params)+1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function summary(PDO $pdo): array {
        self::ensureTable($pdo);
        $row = $pdo->query("
            SELECT
                COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) AS total_paid,
                COALESCE(SUM(CASE WHEN status='refunded' THEN amount ELSE 0 END),0) AS total_refunded,
                COUNT(CASE WHEN status='paid' THEN 1 END) AS count_paid,
                COALESCE(SUM(CASE WHEN status='paid' AND DATE_FORMAT(created_at,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m') THEN amount ELSE 0 END),0) AS month_paid
            FROM payment_transactions
        ")->fetch();
        return $row ?: [];
    }
}
