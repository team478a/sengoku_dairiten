<?php
// app/Controllers/AdminClassController.php

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Services/AuditLog.php';
require_once BASE_PATH . '/config/settings.php';
require_once BASE_PATH . '/app/Services/ClassScheduleService.php';
require_once BASE_PATH . '/app/Services/LineService.php';
require_once BASE_PATH . '/app/Services/Logger.php';

class AdminClassController {
    private PDO $pdo;
    private ClassScheduleService $svc;
    private LineService $line;

    public function __construct() {
        $this->pdo  = get_pdo();
        $this->svc  = new ClassScheduleService();
        $this->line = new LineService();
    }

    // スケジュール一覧
    public function index(): void {
        $stmt = $this->pdo->query("
            SELECT s.*,
                   (SELECT COUNT(*) FROM class_attendances a WHERE a.schedule_id = s.id) AS total_applicants,
                   (SELECT COUNT(*) FROM class_attendances a WHERE a.schedule_id = s.id AND a.status = 'approved') AS approved_count,
                   (SELECT COUNT(*) FROM class_attendances a WHERE a.schedule_id = s.id AND a.status = 'pending') AS pending_count,
                   (SELECT COUNT(*) FROM class_attendances a WHERE a.schedule_id = s.id AND a.attended_at IS NOT NULL) AS attended_count
            FROM class_schedules s
            ORDER BY s.class_date DESC
            LIMIT 30
        ");
        $schedules = $stmt->fetchAll();

        $today      = $this->svc->getTodaySchedule();
        $attendances = $today ? $this->svc->getTodayAttendances() : [];

        require BASE_PATH . '/app/Views/admin/class_schedules.php';
    }

    // スケジュール作成
    public function create(): void {
        require BASE_PATH . '/app/Views/admin/class_schedule_form.php';
    }

    public function store(): void {
        $this->validateScheduleInput();
        $reminderAt = !empty($_POST['reminder_at']) ? date('Y-m-d H:i:s', strtotime($_POST['reminder_at'])) : null;
        $this->pdo->prepare("
            INSERT INTO class_schedules
                (title, class_date, start_time, end_time, checkin_open, checkin_close, capacity, max_requests, description, organizer, public_message, event_format, location, zoom_url, auto_approve, fee, reminder_at, reminder_message, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW(), NOW())
        ")->execute([
            trim($_POST['title'] ?? '教室'),
            $_POST['class_date'],
            $_POST['start_time'],
            $_POST['end_time'],
            $_POST['checkin_open'],
            $_POST['checkin_close'],
            (int) ($_POST['capacity'] ?? 20),
            (int) ($_POST['max_requests'] ?? 2),
            trim($_POST['description'] ?? ''),
            trim($_POST['organizer'] ?? ''),
            trim($_POST['public_message'] ?? ''),
            in_array($_POST['event_format'] ?? 'realtime', ['realtime','zoom','hybrid']) ? $_POST['event_format'] : 'realtime',
            trim($_POST['location'] ?? ''),
            trim($_POST['zoom_url'] ?? ''),
            !empty($_POST['auto_approve']) ? 1 : 0,
            (int)($_POST['fee'] ?? 0),
            $reminderAt,
            trim($_POST['reminder_message'] ?? ''),
        ]);
        AuditLog::record('class_create', trim($_POST['title'] ?? ''), $_POST['class_date'] ?? '');
        header('Location: /admin/classes?created=1');
        exit;
    }

    // スケジュール編集
    public function edit(int $id): void {
        $schedule = $this->svc->getScheduleById($id);
        if (!$schedule) { http_response_code(404); return; }
        require BASE_PATH . '/app/Views/admin/class_schedule_form.php';
    }

    public function update(int $id): void {
        $this->validateScheduleInput();
        $reminderAt = !empty($_POST['reminder_at']) ? date('Y-m-d H:i:s', strtotime($_POST['reminder_at'])) : null;
        // 送信日時が変更されたら未送信に戻す
        $this->pdo->prepare("
            UPDATE class_schedules
            SET title=?, class_date=?, start_time=?, end_time=?, checkin_open=?, checkin_close=?,
                capacity=?, max_requests=?, description=?, organizer=?, public_message=?,
                event_format=?, location=?, zoom_url=?, auto_approve=?, fee=?, reminder_at=?, reminder_message=?,
                reminder_sent_at = CASE WHEN reminder_at <=> ? THEN reminder_sent_at ELSE NULL END,
                updated_at=NOW()
            WHERE id=?
        ")->execute([
            trim($_POST['title'] ?? '教室'),
            $_POST['class_date'],
            $_POST['start_time'],
            $_POST['end_time'],
            $_POST['checkin_open'],
            $_POST['checkin_close'],
            (int) ($_POST['capacity'] ?? 20),
            (int) ($_POST['max_requests'] ?? 2),
            trim($_POST['description'] ?? ''),
            trim($_POST['organizer'] ?? ''),
            trim($_POST['public_message'] ?? ''),
            in_array($_POST['event_format'] ?? 'realtime', ['realtime','zoom','hybrid']) ? $_POST['event_format'] : 'realtime',
            trim($_POST['location'] ?? ''),
            trim($_POST['zoom_url'] ?? ''),
            !empty($_POST['auto_approve']) ? 1 : 0,
            (int)($_POST['fee'] ?? 0),
            $reminderAt,
            trim($_POST['reminder_message'] ?? ''),
            $reminderAt,
            $id,
        ]);
        AuditLog::record('class_update', trim($_POST['title'] ?? ''), 'id=' . $id);
        header('Location: /admin/classes?updated=1');
        exit;
    }

    // 集金済みにする
    public function markPaid(int $attendanceId): void {
        require_once BASE_PATH . '/app/Services/BillingService.php';
        (new BillingService())->markPaid($attendanceId);
        AuditLog::record('payment_collect', 'attendance_id=' . $attendanceId, '');
        header('Location: /admin/classes');
        exit;
    }

    // 今すぐリマインダー送信（手動）
    public function sendReminder(int $id): void {
        require_once BASE_PATH . '/app/Services/ReminderService.php';
        $sent = (new ReminderService())->sendNow($id);
        header('Location: /admin/classes?reminded=' . $sent);
        exit;
    }

    // キャンセル
    public function cancel(int $id): void {
        // 教室情報を取得
        $stmt = $this->pdo->prepare("SELECT * FROM class_schedules WHERE id = ?");
        $stmt->execute([$id]);
        $schedule = $stmt->fetch();

        $this->pdo->prepare("UPDATE class_schedules SET status='canceled', updated_at=NOW() WHERE id=?")
            ->execute([$id]);

        // 予約者（承認待ち＋承認済み）へキャンセル通知
        $notified = 0;
        if ($schedule) {
            $users = $this->pdo->prepare("
                SELECT u.line_user_id FROM class_attendances a
                INNER JOIN users u ON u.id = a.user_id
                WHERE a.schedule_id = ? AND a.status IN ('pending','approved') AND u.status = 'active'
            ");
            $users->execute([$id]);
            $date = date('n月j日', strtotime($schedule['class_date']));
            foreach ($users->fetchAll() as $u) {
                $sent = $this->line->pushText($u['line_user_id'],
                    "【教室中止のお知らせ】\n\n{$date}「{$schedule['title']}」は中止となりました。\nご予約いただいたのに申し訳ございません。\n\nまたの機会をお待ちしております。");
                if ($sent) $notified++;
                usleep(150000);
            }
        }

        AuditLog::record('class_cancel', $schedule['title'] ?? ('id=' . $id), "{$notified}人に通知");
        header('Location: /admin/classes?canceled=' . $notified);
        exit;
    }

    // 参加申請を承認
    // 承認済み参加者向けの会場/Zoom案内文を生成
    private function buildAccessInfo(?array $schedule): string {
        if (!$schedule) return '';
        $fmt = $schedule['event_format'] ?? 'realtime';
        $info = '';
        if ($fmt === 'zoom' || $fmt === 'hybrid') {
            if (!empty($schedule['zoom_url'])) {
                $info .= "🎥 Zoom参加URL\n{$schedule['zoom_url']}\n";
            }
        }
        if ($fmt === 'realtime' || $fmt === 'hybrid') {
            if (!empty($schedule['location'])) {
                $info .= "📍 会場：{$schedule['location']}\n";
            }
        }
        if ($info !== '') $info .= "\n";
        return $info;
    }

    public function approve(int $attendanceId): void {
        $adminId = $_SESSION['admin_id'];
        $att     = $this->svc->approve($attendanceId, $adminId);
        if (!$att) { http_response_code(404); return; }

        // スケジュール情報取得
        $schedule = $this->svc->getScheduleById($att['schedule_id']);
        $dateStr  = $schedule ? date('m月d日', strtotime($schedule['class_date'])) : '本日';
        $maxReq   = $schedule ? $schedule['max_requests'] : 2;

        // LINE通知
        $sent = $this->line->pushText($att['line_user_id'],
            "✅ {$dateStr}の教室参加が承認されました！\n\n" .
            $this->buildAccessInfo($schedule) .
            "本日は {$maxReq}件まで画像生成できます。\n" .
            "「生成する」と送って始めてください🎨"
        );
        if ($sent) $this->svc->markNotified($attendanceId);
        AuditLog::record('attendance_approve', $att['line_user_id'] ?? '', 'attendance_id=' . $attendanceId);

        Logger::info('class', "参加承認 attendance_id={$attendanceId} line={$att['line_user_id']}");

        // AJAXの場合はJSONで返す
        if ($this->isAjax()) {
            echo json_encode(['ok' => true]);
            return;
        }
        header('Location: /admin/classes');
        exit;
    }

    // 参加申請を却下
    public function reject(int $attendanceId): void {
        $adminId = $_SESSION['admin_id'];
        $reason  = trim($_POST['reason'] ?? '');
        $att     = $this->svc->reject($attendanceId, $adminId, $reason);
        if (!$att) { http_response_code(404); return; }

        // LINE通知
        $msg = "今回の参加はお受けできませんでした。";
        if ($reason) $msg .= "\n理由：{$reason}";
        $this->line->pushText($att['line_user_id'], $msg);

        Logger::info('class', "参加却下 attendance_id={$attendanceId}");

        if ($this->isAjax()) {
            echo json_encode(['ok' => true]);
            return;
        }
        header('Location: /admin/classes');
        exit;
    }

    // 一括承認（全pending）
    public function approveAll(int $scheduleId): void {
        $stmt = $this->pdo->prepare("
            SELECT id FROM class_attendances WHERE schedule_id = ? AND status = 'pending'
        ");
        $stmt->execute([$scheduleId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($ids as $id) {
            $this->svc->approve((int)$id, (int)$_SESSION['admin_id']);
            usleep(200000); // 0.2秒
        }

        // まとめて通知
        $schedule = $this->svc->getScheduleById($scheduleId);
        $dateStr  = $schedule ? date('m月d日', strtotime($schedule['class_date'])) : '本日';
        $maxReq   = $schedule ? $schedule['max_requests'] : 2;

        $stmt2 = $this->pdo->prepare("
            SELECT * FROM class_attendances WHERE schedule_id = ? AND status = 'approved' AND notified_at IS NULL
        ");
        $stmt2->execute([$scheduleId]);
        foreach ($stmt2->fetchAll() as $att) {
            $this->line->pushText($att['line_user_id'],
                "✅ {$dateStr}の教室参加が承認されました！\n" .
                $this->buildAccessInfo($schedule) .
                "本日は {$maxReq}件まで画像生成できます🎨"
            );
            $this->svc->markNotified((int)$att['id']);
            usleep(200000);
        }

        AuditLog::record('attendance_approve_all', 'schedule_id=' . $scheduleId, '');
        header('Location: /admin/classes?approved_all=1');
        exit;
    }

    private function validateScheduleInput(): void {
        if (empty($_POST['class_date'])) {
            die('開催日は必須です');
        }
    }

    private function isAjax(): bool {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }
}
