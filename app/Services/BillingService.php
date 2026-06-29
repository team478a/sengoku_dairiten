<?php
// app/Services/BillingService.php
// 参加の課金判定（決済なし・現金運用の管理）

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/settings.php';

class BillingService {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = get_pdo();
    }

    // ユーザーの過去の参加回数（attended_at が記録された数）
    public function attendedCount(int $userId): int {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM class_attendances
            WHERE user_id = ? AND attended_at IS NOT NULL
        ");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    // 参加に対する課金区分を判定
    // 戻り値: ['type' => free|subscription|ticket|paid, 'amount' => int, 'message' => string]
    public function judge(array $user, array $schedule): array {
        $fee = (int)($schedule['fee'] ?? 0);

        // そもそも無料教室
        if ($fee <= 0) {
            return ['type' => 'free', 'amount' => 0, 'message' => '無料'];
        }

        // サブスク会員は無料（通い放題）
        if (($user['member_type'] ?? 'none') === 'subscriber') {
            return ['type' => 'subscription', 'amount' => 0, 'message' => 'サブスク会員'];
        }

        // 初回は無料
        if ($this->attendedCount((int)$user['id']) === 0) {
            return ['type' => 'free', 'amount' => 0, 'message' => '初回無料'];
        }

        // チケット残があり、有効期限内なら消費
        if ((int)($user['ticket_balance'] ?? 0) > 0) {
            $exp = $user['ticket_expires_at'] ?? null;
            if (!$exp || strtotime($exp) >= time()) {
                return ['type' => 'ticket', 'amount' => 0, 'message' => 'チケット利用'];
            }
            // 期限切れ → 残数を0にして都度払いへ
        }

        // それ以外は有料（都度）
        return ['type' => 'paid', 'amount' => $fee, 'message' => "{$fee}円（当日お支払い）"];
    }

    // 課金区分をチェックイン時に確定（チケット消費・支払い記録）
    public function applyToAttendance(int $attendanceId, int $userId, array $judge): void {
        $paymentStatus = $judge['type']; // free/subscription/ticket/paid
        $amount = (int)$judge['amount'];

        // paid の場合は unpaid（未集金）として記録、それ以外はそのまま
        $status = ($judge['type'] === 'paid') ? 'unpaid' : $judge['type'];

        $this->pdo->prepare("
            UPDATE class_attendances
            SET payment_status = ?, payment_amount = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$status, $amount, $attendanceId]);

        // チケット消費
        if ($judge['type'] === 'ticket') {
            $this->pdo->prepare("
                UPDATE users SET ticket_balance = GREATEST(0, ticket_balance - 1), updated_at = NOW()
                WHERE id = ?
            ")->execute([$userId]);
        }
    }

    // 集金済みにする（管理画面から）
    public function markPaid(int $attendanceId): void {
        $this->pdo->prepare("
            UPDATE class_attendances
            SET payment_status = 'paid', paid_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ")->execute([$attendanceId]);
    }

    // チケット付与（管理画面から）
    public function addTickets(int $userId, int $count): void {
        // 有効日数（設定。0なら無期限）
        $days = (int)Settings::get('ticket_valid_days', '0');
        if ($count > 0 && $days > 0) {
            // 購入時に期限を延長
            $this->pdo->prepare("
                UPDATE users
                SET ticket_balance = ticket_balance + ?,
                    ticket_expires_at = DATE_ADD(NOW(), INTERVAL ? DAY),
                    updated_at = NOW()
                WHERE id = ?
            ")->execute([$count, $days, $userId]);
        } else {
            $this->pdo->prepare("
                UPDATE users SET ticket_balance = GREATEST(0, ticket_balance + ?), updated_at = NOW()
                WHERE id = ?
            ")->execute([$count, $userId]);
        }
    }

    // 会員区分を変更
    public function setMemberType(int $userId, string $type): void {
        $type = in_array($type, ['none','subscriber']) ? $type : 'none';
        $this->pdo->prepare("UPDATE users SET member_type = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$type, $userId]);
    }
}
