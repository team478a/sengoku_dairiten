<?php
// app/Controllers/StripeWebhookController.php
// Stripe Webhook（決済完了で予約確定・Zoom案内送信）

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/settings.php';
require_once BASE_PATH . '/app/Services/StripeService.php';
require_once BASE_PATH . '/app/Services/LineService.php';
require_once BASE_PATH . '/app/Services/Logger.php';

class StripeWebhookController {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = get_pdo();
    }

    public function handle(): void {
        $payload   = file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        $stripe = new StripeService();
        $event  = $stripe->verifyWebhook($payload, $sigHeader);

        if (!$event) {
            http_response_code(400);
            echo 'invalid';
            return;
        }

        // 決済完了イベント
        if (($event['type'] ?? '') === 'checkout.session.completed') {
            $session = $event['data']['object'] ?? [];
            $meta    = $session['metadata'] ?? [];
            $kind    = $meta['kind'] ?? 'attendance';

            if ($kind === 'ticket') {
                // チケット購入
                $this->confirmTicketPurchase(
                    (int)($meta['user_id'] ?? 0),
                    (int)($meta['ticket_count'] ?? 0),
                    $session['id'] ?? '',
                    (int)($session['amount_total'] ?? 0)
                );
            } elseif ($kind === 'subscription' || ($session['mode'] ?? '') === 'subscription') {
                // サブスク加入
                $this->confirmSubscription(
                    (int)($meta['user_id'] ?? 0),
                    $session['customer'] ?? '',
                    $session['subscription'] ?? ''
                );
            } else {
                // 教室参加の決済
                $attendanceId = (int)($meta['attendance_id'] ?? 0);
                if ($attendanceId) {
                    $this->confirmPayment($attendanceId, $session['id'] ?? '');
                }
            }
        }

        // サブスク解約・期限切れ
        if (in_array(($event['type'] ?? ''), ['customer.subscription.deleted'])) {
            $sub = $event['data']['object'] ?? [];
            $this->endSubscription($sub['id'] ?? '');
        }

        // サブスク支払い失敗（カード期限切れ等）
        if (($event['type'] ?? '') === 'invoice.payment_failed') {
            $invoice = $event['data']['object'] ?? [];
            $this->handlePaymentFailed($invoice['subscription'] ?? '');
        }

        http_response_code(200);
        echo 'ok';
    }

    // チケット購入確定
    private function confirmTicketPurchase(int $userId, int $count, string $sessionId = '', int $amount = 0): void {
        if (!$userId || $count <= 0) return;
        // 冪等性チェック：同一セッションIDが既に処理済みなら二重付与しない
        require_once BASE_PATH . '/app/Services/PaymentLog.php';
        if ($sessionId && PaymentLog::existsByStripeSessionId($sessionId)) {
            Logger::info('stripe', "チケット購入 重複Webhook スキップ session={$sessionId}");
            return;
        }
        require_once BASE_PATH . '/app/Services/BillingService.php';
        (new BillingService())->addTickets($userId, $count);

        $stmt = $this->pdo->prepare("SELECT line_user_id, ticket_balance FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $u = $stmt->fetch();

        PaymentLog::record([
            'user_id' => $userId, 'line_user_id' => $u['line_user_id'] ?? null,
            'kind' => 'ticket', 'amount' => $amount, 'status' => 'paid',
            'description' => "回数券{$count}回分", 'stripe_session_id' => $sessionId,
        ]);

        if ($u) {
            (new LineService())->pushText($u['line_user_id'],
                "🎫 チケット{$count}回分のご購入ありがとうございます！\n現在の残り：{$u['ticket_balance']}回\n\n教室参加時に自動で使用されます。");
        }
        Logger::info('stripe', "チケット購入確定 user={$userId} +{$count}");
    }

    // サブスク加入確定
    private function confirmSubscription(int $userId, string $customerId, string $subscriptionId): void {
        if (!$userId) return;
        $this->pdo->prepare("
            UPDATE users
            SET member_type = 'subscriber', stripe_customer_id = ?, stripe_subscription_id = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$customerId, $subscriptionId, $userId]);

        $stmt = $this->pdo->prepare("SELECT line_user_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $u = $stmt->fetch();

        require_once BASE_PATH . '/app/Services/PaymentLog.php';
        PaymentLog::record([
            'user_id' => $userId, 'line_user_id' => $u['line_user_id'] ?? null,
            'kind' => 'subscription', 'amount' => 0, 'status' => 'paid',
            'description' => 'サブスク加入', 'stripe_session_id' => $subscriptionId,
        ]);

        if ($u) {
            (new LineService())->pushText($u['line_user_id'],
                "🌟 サブスク会員へのご登録ありがとうございます！\n\nこれから教室に何度でも無料でご参加いただけます。\n「参加予約」からお申し込みください🎨");
        }
        Logger::info('stripe', "サブスク加入確定 user={$userId} sub={$subscriptionId}");
    }

    // サブスク支払い失敗
    private function handlePaymentFailed(string $subscriptionId): void {
        if (!$subscriptionId) return;
        $stmt = $this->pdo->prepare("SELECT id, line_user_id FROM users WHERE stripe_subscription_id = ?");
        $stmt->execute([$subscriptionId]);
        $u = $stmt->fetch();
        if (!$u) return;

        (new LineService())->pushText($u['line_user_id'],
            "⚠ サブスクのお支払いが確認できませんでした。\n\nカードの有効期限切れなどが考えられます。お手数ですが、お支払い方法をご確認ください。\n継続できない場合、会員特典が一時停止されることがあります。");
        Logger::info('stripe', "サブスク支払い失敗 user={$u['id']}");
    }

    // サブスク終了（解約・期限切れ）
    private function endSubscription(string $subscriptionId): void {
        if (!$subscriptionId) return;
        $stmt = $this->pdo->prepare("SELECT id, line_user_id FROM users WHERE stripe_subscription_id = ?");
        $stmt->execute([$subscriptionId]);
        $u = $stmt->fetch();
        if (!$u) return;

        $this->pdo->prepare("
            UPDATE users SET member_type = 'none', stripe_subscription_id = NULL, updated_at = NOW()
            WHERE id = ?
        ")->execute([$u['id']]);

        (new LineService())->pushText($u['line_user_id'],
            "サブスク会員の解約手続きが完了しました。\nまたのご利用をお待ちしております。");
        Logger::info('stripe', "サブスク終了 user={$u['id']}");
    }

    private function confirmPayment(int $attendanceId, string $sessionId): void {
        // 予約を承認＋支払い済みに
        $stmt = $this->pdo->prepare("
            SELECT a.*, s.title, s.class_date, s.start_time, s.event_format, s.location, s.zoom_url, s.max_requests
            FROM class_attendances a
            INNER JOIN class_schedules s ON s.id = a.schedule_id
            WHERE a.id = ?
        ");
        $stmt->execute([$attendanceId]);
        $att = $stmt->fetch();
        if (!$att) return;

        // すでに処理済みなら何もしない
        if ($att['payment_status'] === 'paid') return;

        $this->pdo->prepare("
            UPDATE class_attendances
            SET status = 'approved', payment_status = 'paid', paid_at = NOW(),
                approved_at = COALESCE(approved_at, NOW()), notified_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ")->execute([$attendanceId]);

        // Zoom URL等の案内を送信
        $line = new LineService();
        $date = date('n月j日', strtotime($att['class_date']));
        $start = substr($att['start_time'], 0, 5);

        $access = '';
        $fmt = $att['event_format'] ?? 'realtime';
        if (($fmt === 'zoom' || $fmt === 'hybrid') && !empty($att['zoom_url'])) {
            $access .= "🎥 Zoom参加URL\n{$att['zoom_url']}\n";
        }
        if (($fmt === 'realtime' || $fmt === 'hybrid') && !empty($att['location'])) {
            $access .= "📍 会場：{$att['location']}\n";
        }
        if ($access) $access .= "\n";

        $line->pushText($att['line_user_id'],
            "✅ お支払いが完了しました！\n\n{$date} {$start}〜「{$att['title']}」のご参加を確定しました。\n\n" .
            $access . "当日は「生成する」で画像生成もお楽しみいただけます🎨");

        require_once BASE_PATH . '/app/Services/PaymentLog.php';
        PaymentLog::record([
            'user_id' => $att['user_id'], 'line_user_id' => $att['line_user_id'],
            'kind' => 'attendance', 'amount' => (int)$att['payment_amount'], 'status' => 'paid',
            'description' => $att['title'] . ' 参加費', 'stripe_session_id' => $sessionId,
        ]);

        require_once BASE_PATH . '/app/Services/AdminNotifier.php';
        AdminNotifier::notify('payment', "「{$att['title']}」の参加費 {$att['payment_amount']}円 の決済が完了しました。");

        Logger::info('stripe', "決済完了・予約確定 attendance={$attendanceId}");
    }
}
