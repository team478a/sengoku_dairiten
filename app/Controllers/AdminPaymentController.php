<?php
// app/Controllers/AdminPaymentController.php
// 決済履歴・返金

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Services/PaymentLog.php';

class AdminPaymentController {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = get_pdo();
    }

    public function index(): void {
        $filter   = $_GET['kind'] ?? '';
        $payments = PaymentLog::recent($this->pdo, 200, $filter);
        $summary  = PaymentLog::summary($this->pdo);
        require BASE_PATH . '/app/Views/admin/payments.php';
    }

    // 返金
    public function refund(int $id): void {
        require_once BASE_PATH . '/app/Services/StripeService.php';
        require_once BASE_PATH . '/app/Services/AuditLog.php';

        $stmt = $this->pdo->prepare("SELECT * FROM payment_transactions WHERE id = ?");
        $stmt->execute([$id]);
        $p = $stmt->fetch();

        if (!$p || $p['status'] !== 'paid') {
            header('Location: /admin/payments?error=1');
            exit;
        }

        $stripe = new StripeService();
        $ok = false;
        if (!empty($p['stripe_session_id'])) {
            $ok = $stripe->refundBySession($p['stripe_session_id']);
        }

        if ($ok) {
            PaymentLog::markRefunded($this->pdo, $id);
            // チケットの場合は付与分を戻す処理は運用判断（ここでは記録のみ）
            AuditLog::record('refund', "payment_id={$id}", "{$p['amount']}円");
            header('Location: /admin/payments?refunded=1');
        } else {
            header('Location: /admin/payments?error=1');
        }
        exit;
    }
}
