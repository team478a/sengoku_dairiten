<?php
// app/Controllers/AdminReportController.php
// 統計・操作ログ・エクスポート画面

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Services/AuditLog.php';
require_once BASE_PATH . '/app/Services/ClassScheduleService.php';

class AdminReportController {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = get_pdo();
        // attended_at / payment_status 等の自動カラム追加を確実に実行
        new ClassScheduleService();
    }

    // 統計＆エクスポート画面
    public function stats(): void {
        // 全体サマリー
        $summary = [
            'total_users'    => (int)$this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
            'total_requests' => (int)$this->pdo->query("SELECT COUNT(*) FROM image_requests")->fetchColumn(),
            'total_images'   => (int)$this->pdo->query("SELECT COUNT(*) FROM generated_images")->fetchColumn(),
            'total_classes'  => (int)$this->pdo->query("SELECT COUNT(*) FROM class_schedules")->fetchColumn(),
        ];

        // 教室ごとの予約数・参加数・参加率
        $classStats = $this->pdo->query("
            SELECT s.id, s.class_date, s.title, s.capacity,
                   (SELECT COUNT(*) FROM class_attendances a WHERE a.schedule_id = s.id) AS reserved,
                   (SELECT COUNT(*) FROM class_attendances a WHERE a.schedule_id = s.id AND a.status='approved') AS approved,
                   (SELECT COUNT(*) FROM class_attendances a WHERE a.schedule_id = s.id AND a.attended_at IS NOT NULL) AS attended
            FROM class_schedules s
            ORDER BY s.class_date DESC
            LIMIT 20
        ")->fetchAll();

        // 月別生成数（直近6か月）
        $monthly = $this->pdo->query("
            SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt
            FROM image_requests
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY ym ORDER BY ym ASC
        ")->fetchAll();

        require BASE_PATH . '/app/Views/admin/report_stats.php';
    }

    // 操作ログ画面
    public function logs(): void {
        $logs = AuditLog::recent($this->pdo, 200);
        require BASE_PATH . '/app/Views/admin/report_logs.php';
    }
}
