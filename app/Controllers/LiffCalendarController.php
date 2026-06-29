<?php
// app/Controllers/LiffCalendarController.php
// 受講生向けLIFF予約カレンダー（公開ページ）

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/settings.php';

class LiffCalendarController {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = get_pdo();
    }

    // カレンダーページ（HTML）を表示
    public function show(): void {
        $liffId = Settings::get('liff_id', '');

        // 今後3か月分の開催予定を取得
        $stmt = $this->pdo->query("
            SELECT s.*,
                   (SELECT COUNT(*) FROM class_attendances a WHERE a.schedule_id = s.id AND a.status IN ('pending','approved')) AS total_applicants
            FROM class_schedules s
            WHERE s.class_date >= CURDATE()
              AND s.status IN ('scheduled','active')
            ORDER BY s.class_date ASC, s.start_time ASC
        ");
        $schedules = $stmt->fetchAll();

        // JS用にJSON化
        $events = [];
        foreach ($schedules as $s) {
            $cap = (int)$s['capacity'];
            $app = (int)$s['total_applicants'];
            $events[] = [
                'id'        => (int)$s['id'],
                'date'      => $s['class_date'],
                'title'     => $s['title'],
                'start'     => substr($s['start_time'], 0, 5),
                'end'       => substr($s['end_time'], 0, 5),
                'capacity'  => $cap,
                'reserved'  => $app,
                'full'      => $cap > 0 && $app >= $cap,
                'format'    => $s['event_format'] ?? 'realtime',
                'location'  => $s['location'] ?? '',
                'organizer' => $s['organizer'] ?? '',
                'checkin_open'  => substr($s['checkin_open'], 0, 5),
                'checkin_close' => substr($s['checkin_close'], 0, 5),
            ];
        }

        header('Content-Type: text/html; charset=UTF-8');
        require BASE_PATH . '/app/Views/liff/calendar.php';
    }

    // 予約API（LIFFからのPOST）
    public function reserve(): void {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);

        $idToken    = $input['idToken'] ?? '';
        $scheduleId = (int)($input['scheduleId'] ?? 0);

        if (!$idToken || !$scheduleId) {
            echo json_encode(['ok' => false, 'message' => 'パラメータが不足しています']);
            return;
        }

        // IDトークンを検証してLINEユーザーIDを取得
        $lineUserId = $this->verifyIdToken($idToken);
        if (!$lineUserId) {
            echo json_encode(['ok' => false, 'message' => 'LINE認証に失敗しました']);
            return;
        }

        // スケジュール確認
        $stmt = $this->pdo->prepare("SELECT * FROM class_schedules WHERE id = ? AND status IN ('scheduled','active')");
        $stmt->execute([$scheduleId]);
        $schedule = $stmt->fetch();
        if (!$schedule) {
            echo json_encode(['ok' => false, 'message' => 'この教室は予約できません']);
            return;
        }

        // ユーザーを取得or作成
        $user = $this->upsertUser($lineUserId);

        // 定員チェック
        $cnt = $this->pdo->prepare("SELECT COUNT(*) FROM class_attendances WHERE schedule_id = ? AND status IN ('pending','approved')");
        $cnt->execute([$scheduleId]);
        if ((int)$schedule['capacity'] > 0 && (int)$cnt->fetchColumn() >= (int)$schedule['capacity']) {
            echo json_encode(['ok' => false, 'message' => 'この教室は満席です']);
            return;
        }

        // 既存予約チェック
        $exist = $this->pdo->prepare("SELECT status FROM class_attendances WHERE schedule_id = ? AND user_id = ?");
        $exist->execute([$scheduleId, $user['id']]);
        $existing = $exist->fetch();
        if ($existing) {
            $msg = $existing['status'] === 'approved' ? 'すでに承認済みです' : 'すでに予約済みです';
            echo json_encode(['ok' => true, 'message' => $msg, 'already' => true]);
            return;
        }

        // 課金判定
        require_once BASE_PATH . '/app/Services/BillingService.php';
        $billing = new BillingService();
        $judge = $billing->judge($user, $schedule);

        $fmt = $schedule['event_format'] ?? 'realtime';
        $isOnline = ($fmt === 'zoom' || $fmt === 'hybrid');

        // オンライン教室で支払いが必要（paid）な場合は事前決済へ
        if ($isOnline && $judge['type'] === 'paid' && $judge['amount'] > 0) {
            require_once BASE_PATH . '/app/Services/StripeService.php';
            $stripe = new StripeService();

            if ($stripe->isConfigured()) {
                // 先に pending で予約レコードを作成（決済待ち）
                $this->pdo->prepare("
                    INSERT INTO class_attendances (schedule_id, user_id, line_user_id, status, payment_status, payment_amount, created_at, updated_at)
                    VALUES (?, ?, ?, 'pending', 'unpaid', ?, NOW(), NOW())
                ")->execute([$scheduleId, $user['id'], $lineUserId, $judge['amount']]);
                $attendanceId = (int)$this->pdo->lastInsertId();

                $base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
                $checkout = $stripe->createCheckout(
                    $judge['amount'],
                    $schedule['title'] . ' 参加費',
                    ['attendance_id' => $attendanceId, 'schedule_id' => $scheduleId, 'user_id' => $user['id']],
                    $base . '/liff/paid?attendance=' . $attendanceId,
                    $base . '/liff/calendar'
                );

                if ($checkout) {
                    $this->pdo->prepare("UPDATE class_attendances SET stripe_session_id = ? WHERE id = ?")
                        ->execute([$checkout['id'], $attendanceId]);
                    echo json_encode([
                        'ok' => true,
                        'payment_required' => true,
                        'payment_url' => $checkout['url'],
                        'message' => "参加費 {$judge['amount']}円のお支払いに進みます",
                    ]);
                    return;
                }
                // 決済リンク作成失敗 → pending予約を削除してエラー返却（重複INSERT防止）
                $this->pdo->prepare("DELETE FROM class_attendances WHERE id = ?")->execute([$attendanceId]);
                echo json_encode(['ok' => false, 'message' => '決済ページの作成に失敗しました。時間をおいて再度お試しください。']);
                return;
            }
        }

        // 自動承認かどうか
        $autoApprove = !empty($schedule['auto_approve']);
        $status = $autoApprove ? 'approved' : 'pending';

        $this->pdo->prepare("
            INSERT INTO class_attendances (schedule_id, user_id, line_user_id, status, payment_status, payment_amount, approved_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, " . ($autoApprove ? 'NOW()' : 'NULL') . ", NOW(), NOW())
        ")->execute([$scheduleId, $user['id'], $lineUserId, $status, $judge['type'] === 'paid' ? 'unpaid' : $judge['type'], $judge['amount']]);

        // チケット消費
        if ($judge['type'] === 'ticket') {
            $billing->addTickets((int)$user['id'], -1);
        }

        $feeNote = '';
        if ($judge['type'] === 'free' && $judge['message'] === '初回無料') $feeNote = '（初回無料）';
        elseif ($judge['type'] === 'ticket') $feeNote = '（チケット1枚使用）';
        elseif ($judge['type'] === 'subscription') $feeNote = '（サブスク会員）';
        elseif ($judge['type'] === 'paid') $feeNote = "（参加費{$judge['amount']}円は当日会場で）";

        $message = $autoApprove
            ? "予約が完了し、承認されました！{$feeNote}\n教室当日に「生成する」で画像生成できます🎨"
            : "予約を受け付けました{$feeNote}。承認されたらLINEでお知らせします。";

        // 管理者へ新規予約を通知
        require_once BASE_PATH . '/app/Services/AdminNotifier.php';
        $uname = $user['display_name'] ?? '受講生';
        AdminNotifier::notify('reservation',
            "{$uname} さんが「{$schedule['title']}」({$schedule['class_date']})を予約しました。");

        echo json_encode(['ok' => true, 'message' => $message, 'auto' => $autoApprove]);
    }

    // LINE IDトークン検証
    private function verifyIdToken(string $idToken): ?string {
        $channelId = Settings::get('liff_channel_id', '');
        $ch = curl_init('https://api.line.me/oauth2/v2.1/verify');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'id_token'  => $idToken,
                'client_id' => $channelId,
            ]),
            CURLOPT_TIMEOUT        => 10,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) return null;
        $data = json_decode($res, true);
        return $data['sub'] ?? null; // sub = LINE User ID
    }

    private function upsertUser(string $lineUserId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE line_user_id = ?");
        $stmt->execute([$lineUserId]);
        $user = $stmt->fetch();
        if ($user) return $user;

        $this->pdo->prepare("
            INSERT INTO users (line_user_id, status, registered_at, created_at, updated_at)
            VALUES (?, 'active', NOW(), NOW(), NOW())
        ")->execute([$lineUserId]);

        $stmt->execute([$lineUserId]);
        return $stmt->fetch();
    }
}
