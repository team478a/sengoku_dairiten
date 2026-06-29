<?php
// app/Controllers/AdminBroadcastController.php

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Services/LineService.php';
require_once BASE_PATH . '/app/Services/Logger.php';
require_once BASE_PATH . '/app/Services/ClassScheduleService.php';
require_once BASE_PATH . '/app/Services/AuditLog.php';

class AdminBroadcastController {
    private PDO $pdo;
    private LineService $line;

    public function __construct() {
        $this->pdo  = get_pdo();
        $this->line = new LineService();
    }

    public function show(): void {
        $stmtLog = $this->pdo->prepare("
            SELECT * FROM system_logs
            WHERE log_type LIKE 'broadcast%'
            ORDER BY created_at DESC LIMIT 10
        ");
        $stmtLog->execute();
        $broadcastLogs = $stmtLog->fetchAll();

        // 教室別送信用に、今後＆直近の教室を取得
        $schedules = $this->pdo->query("
            SELECT s.id, s.class_date, s.title,
                   (SELECT COUNT(*) FROM class_attendances a WHERE a.schedule_id = s.id AND a.status='approved') AS approved_count
            FROM class_schedules s
            WHERE s.class_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ORDER BY s.class_date DESC
            LIMIT 30
        ")->fetchAll();

        require BASE_PATH . '/app/Views/admin/broadcast.php';
    }

    public function send(): void {
        $target  = $_POST['target']  ?? 'all';
        $message = trim($_POST['message'] ?? '');

        if (!$message) {
            header('Location: /admin/broadcast?error=' . urlencode('メッセージを入力してください'));
            exit;
        }

        // 送信対象ユーザーを取得
        $users = $this->getTargetUsers($target);

        if (empty($users)) {
            header('Location: /admin/broadcast?error=' . urlencode('送信対象ユーザーがいません'));
            exit;
        }

        $sent = 0;
        foreach ($users as $user) {
            if ($this->line->pushText($user['line_user_id'], $message)) {
                $sent++;
            }
            usleep(100000); // 0.1秒待機（レート制限対策）
        }

        Logger::info('broadcast', "一斉送信 target={$target} sent={$sent} message=" . mb_substr($message, 0, 50));

        AuditLog::record('broadcast', "target={$target}", "{$sent}人に送信");
        header('Location: /admin/broadcast?sent=' . $sent);
        exit;
    }

    private function getTargetUsers(string $target): array {
        // 教室別: schedule:123 形式
        if (strpos($target, 'schedule:') === 0) {
            $scheduleId = (int)substr($target, 9);
            $stmt = $this->pdo->prepare("
                SELECT u.line_user_id FROM users u
                INNER JOIN class_attendances a ON a.user_id = u.id
                WHERE a.schedule_id = ? AND a.status = 'approved' AND u.status = 'active'
            ");
            $stmt->execute([$scheduleId]);
            return $stmt->fetchAll();
        }

        switch ($target) {
            case 'today_approved':
                $svc      = new ClassScheduleService();
                $schedule = $svc->getTodaySchedule();
                if (!$schedule) return [];
                $stmt = $this->pdo->prepare("
                    SELECT u.line_user_id FROM users u
                    INNER JOIN class_attendances a ON a.user_id = u.id
                    WHERE a.schedule_id = ? AND a.status = 'approved' AND u.status = 'active'
                ");
                $stmt->execute([$schedule['id']]);
                return $stmt->fetchAll();

            case 'active':
                $stmt = $this->pdo->query("SELECT line_user_id FROM users WHERE status = 'active'");
                return $stmt->fetchAll();

            default: // all
                $stmt = $this->pdo->query("SELECT line_user_id FROM users WHERE status != 'banned'");
                return $stmt->fetchAll();
        }
    }
}
