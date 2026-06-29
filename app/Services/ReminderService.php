<?php
// app/Services/ReminderService.php
// 教室リマインダーの自動送信

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Services/LineService.php';
require_once BASE_PATH . '/app/Services/Logger.php';

class ReminderService {
    private PDO $pdo;
    private LineService $line;

    public function __construct() {
        $this->pdo  = get_pdo();
        $this->line = new LineService();
    }

    // 送信時刻が来た未送信リマインダーを処理
    // worker / Webhook / cron から呼ばれる
    public function dispatchDue(): int {
        // reminder_atが過去 かつ 未送信 の教室
        $stmt = $this->pdo->query("
            SELECT * FROM class_schedules
            WHERE reminder_at IS NOT NULL
              AND reminder_at <= NOW()
              AND reminder_sent_at IS NULL
              AND status IN ('scheduled','active')
            ORDER BY reminder_at ASC
            LIMIT 5
        ");
        $schedules = $stmt->fetchAll();
        if (!$schedules) return 0;

        $sentTotal = 0;
        foreach ($schedules as $s) {
            $sentTotal += $this->sendForSchedule($s);
            // 送信済みフラグ
            $this->pdo->prepare("UPDATE class_schedules SET reminder_sent_at = NOW() WHERE id = ?")
                ->execute([$s['id']]);
        }
        return $sentTotal;
    }

    // 特定教室のリマインダーを今すぐ送信（管理画面の手動送信用）
    public function sendNow(int $scheduleId): int {
        $stmt = $this->pdo->prepare("SELECT * FROM class_schedules WHERE id = ?");
        $stmt->execute([$scheduleId]);
        $s = $stmt->fetch();
        if (!$s) return 0;

        $sent = $this->sendForSchedule($s);
        $this->pdo->prepare("UPDATE class_schedules SET reminder_sent_at = NOW() WHERE id = ?")
            ->execute([$scheduleId]);
        return $sent;
    }

    // 承認済み参加者にリマインダーを送る
    private function sendForSchedule(array $s): int {
        // 承認済み参加者を取得
        $stmt = $this->pdo->prepare("
            SELECT u.line_user_id
            FROM class_attendances a
            INNER JOIN users u ON u.id = a.user_id
            WHERE a.schedule_id = ? AND a.status = 'approved' AND u.status = 'active'
        ");
        $stmt->execute([$s['id']]);
        $users = $stmt->fetchAll();
        if (!$users) return 0;

        // メッセージ（カスタム or デフォルト）
        $message = trim($s['reminder_message'] ?? '');
        if ($message === '') {
            $date  = date('n月j日（D）', strtotime($s['class_date']));
            $start = substr($s['start_time'], 0, 5);
            $message = "📅 教室リマインダー\n\n";
            $message .= "{$s['title']}\n";
            $message .= "{$date} {$start}〜 開催です！\n";
            if (!empty($s['organizer'])) {
                $message .= "主催：{$s['organizer']}\n";
            }
            // 開催形式・アクセス
            $fmt = $s['event_format'] ?? 'realtime';
            if (($fmt === 'zoom' || $fmt === 'hybrid') && !empty($s['zoom_url'])) {
                $message .= "🎥 Zoom：{$s['zoom_url']}\n";
            }
            if (($fmt === 'realtime' || $fmt === 'hybrid') && !empty($s['location'])) {
                $message .= "📍 会場：{$s['location']}\n";
            }
            if (!empty($s['public_message'])) {
                $message .= "\n" . $s['public_message'] . "\n";
            }
            $message .= "\n当日は受付時間内に「参加予約」を押してください。お待ちしています🎨";
        }

        $sent = 0;
        foreach ($users as $u) {
            if ($this->line->pushText($u['line_user_id'], $message)) {
                $sent++;
            }
            usleep(100000);
        }
        Logger::info('reminder', "リマインダー送信 schedule={$s['id']} sent={$sent}");
        return $sent;
    }
}
