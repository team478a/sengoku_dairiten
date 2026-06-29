<?php
// app/Controllers/AdminCalendarController.php
// 管理者用カレンダー表示

require_once BASE_PATH . '/config/database.php';

class AdminCalendarController {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = get_pdo();
    }

    public function show(): void {
        // 表示する年月（指定なければ今月）
        $year  = (int)($_GET['y'] ?? date('Y'));
        $month = (int)($_GET['m'] ?? date('n'));
        if ($month < 1) { $month = 12; $year--; }
        if ($month > 12) { $month = 1; $year++; }

        // その月の教室を取得
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));

        $stmt = $this->pdo->prepare("
            SELECT s.*,
                   (SELECT COUNT(*) FROM class_attendances a WHERE a.schedule_id = s.id) AS total_applicants,
                   (SELECT COUNT(*) FROM class_attendances a WHERE a.schedule_id = s.id AND a.status = 'approved') AS approved_count
            FROM class_schedules s
            WHERE s.class_date BETWEEN ? AND ?
            ORDER BY s.class_date ASC, s.start_time ASC
        ");
        $stmt->execute([$start, $end]);
        $rows = $stmt->fetchAll();

        // 日付ごとにまとめる
        $byDate = [];
        foreach ($rows as $r) {
            $byDate[$r['class_date']][] = $r;
        }

        require BASE_PATH . '/app/Views/admin/calendar.php';
    }
}
