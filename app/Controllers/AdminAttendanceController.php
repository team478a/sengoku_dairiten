<?php
// app/Controllers/AdminAttendanceController.php

require_once BASE_PATH . '/config/database.php';

class AdminAttendanceController {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = get_pdo();
    }

    public function index(): void {
        $where  = ['a.status = ?'];
        $params = ['approved'];
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 30;
        $offset  = ($page - 1) * $perPage;

        if (!empty($_GET['keyword'])) {
            $where[]  = 'u.display_name LIKE ?';
            $params[] = '%' . $_GET['keyword'] . '%';
        }
        if (!empty($_GET['from'])) {
            $where[]  = 's.class_date >= ?';
            $params[] = $_GET['from'];
        }
        if (!empty($_GET['to'])) {
            $where[]  = 's.class_date <= ?';
            $params[] = $_GET['to'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // 統計
        $stmtStats = $this->pdo->prepare("
            SELECT
                COUNT(*) AS total,
                COUNT(DISTINCT a.user_id) AS unique_users,
                COUNT(DISTINCT a.schedule_id) AS total_classes
            FROM class_attendances a
            LEFT JOIN users u ON u.id = a.user_id
            LEFT JOIN class_schedules s ON s.id = a.schedule_id
            {$whereClause}
        ");
        $stmtStats->execute($params);
        $stats = $stmtStats->fetch();

        // 件数
        $stmtCount = $this->pdo->prepare("
            SELECT COUNT(*) FROM class_attendances a
            LEFT JOIN users u ON u.id = a.user_id
            LEFT JOIN class_schedules s ON s.id = a.schedule_id
            {$whereClause}
        ");
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();
        $totalPages = (int)ceil($total / $perPage);

        // 一覧
        $stmtList = $this->pdo->prepare("
            SELECT a.*, u.display_name,
                   s.title, s.class_date, s.start_time,
                   (SELECT COUNT(*) FROM image_requests r
                    WHERE r.user_id = a.user_id AND DATE(r.created_at) = s.class_date) AS request_count
            FROM class_attendances a
            LEFT JOIN users u ON u.id = a.user_id
            LEFT JOIN class_schedules s ON s.id = a.schedule_id
            {$whereClause}
            ORDER BY s.class_date DESC, a.created_at ASC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmtList->execute($params);
        $attendances = $stmtList->fetchAll();

        require BASE_PATH . '/app/Views/admin/attendance_history.php';
    }
}
