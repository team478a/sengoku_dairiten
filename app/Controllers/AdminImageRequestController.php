<?php
// app/Controllers/AdminImageRequestController.php

require_once BASE_PATH . '/config/database.php';

class AdminImageRequestController {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = get_pdo();
    }

    public function dashboard(): void {
        // 統計
        $stats = [];
        $stats['today_requests'] = (int) $this->pdo->query("
            SELECT COUNT(*) FROM image_requests WHERE DATE(created_at) = CURDATE()
        ")->fetchColumn();
        $stats['today_images'] = (int) $this->pdo->query("
            SELECT COUNT(*) FROM generated_images WHERE DATE(created_at) = CURDATE()
        ")->fetchColumn();
        $stats['failed_count'] = (int) $this->pdo->query("
            SELECT COUNT(*) FROM image_requests WHERE status = 'failed' AND DATE(created_at) = CURDATE()
        ")->fetchColumn();
        $stats['processing_count'] = (int) $this->pdo->query("
            SELECT COUNT(*) FROM image_requests WHERE status NOT IN ('completed','failed','canceled')
        ")->fetchColumn();
        $stats['total_requests'] = (int) $this->pdo->query("
            SELECT COUNT(*) FROM image_requests
        ")->fetchColumn();
        $stats['total_images'] = (int) $this->pdo->query("
            SELECT COUNT(*) FROM generated_images
        ")->fetchColumn();

        // 最近の依頼
        $recent = $this->pdo->query("
            SELECT r.*, u.display_name
            FROM image_requests r
            LEFT JOIN users u ON u.id = r.user_id
            ORDER BY r.created_at DESC
            LIMIT 10
        ")->fetchAll();

        // ===== 運用監視データ =====
        $monitor = [];

        // ① cron死活監視
        $lastRun = Settings::get('worker_last_run', '');
        $monitor['worker_last_run'] = $lastRun;
        if ($lastRun) {
            $diff = time() - strtotime($lastRun);
            $monitor['worker_diff_sec'] = $diff;
            // 5分以上動いていなければ異常
            $monitor['worker_alert'] = $diff > 300;
        } else {
            $monitor['worker_diff_sec'] = null;
            $monitor['worker_alert'] = true;
        }

        // ② LINE当月push送信数
        $pushKey = 'line_push_count_' . date('Ym');
        $monitor['line_push_count'] = (int) Settings::get($pushKey, '0');
        $monitor['line_push_limit'] = (int) Settings::get('line_monthly_limit', '200');
        $monitor['line_push_alert']  = $monitor['line_push_limit'] > 0
            && $monitor['line_push_count'] >= $monitor['line_push_limit'] * 0.8;

        // ③ Stability AI クレジット残高（キャッシュ。なければ取得を試みる）
        $monitor['stability_credits'] = Settings::get('stability_credits_cache', '');
        $monitor['stability_checked_at'] = Settings::get('stability_credits_checked_at', '');

        // エンジン表示用に設定を渡す
        $settings = Settings::all();

        require BASE_PATH . '/app/Views/admin/dashboard.php';
    }

    public function index(): void {
        $where    = [];
        $params   = [];
        $page     = max(1, (int) ($_GET['page'] ?? 1));
        $perPage  = 20;
        $offset   = ($page - 1) * $perPage;

        if (!empty($_GET['status'])) {
            $where[]  = 'r.status = ?';
            $params[] = $_GET['status'];
        }
        if (!empty($_GET['date'])) {
            $where[]  = 'DATE(r.created_at) = ?';
            $params[] = $_GET['date'];
        }
        if (!empty($_GET['keyword'])) {
            $where[]  = 'r.input_text LIKE ?';
            $params[] = '%' . $_GET['keyword'] . '%';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM image_requests r {$whereClause}");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $stmtList = $this->pdo->prepare("
            SELECT r.*, u.display_name,
                   (SELECT COUNT(*) FROM generated_images gi WHERE gi.request_id = r.id) AS image_count
            FROM image_requests r
            LEFT JOIN users u ON u.id = r.user_id
            {$whereClause}
            ORDER BY r.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmtList->execute($params);
        $requests = $stmtList->fetchAll();

        $totalPages = (int) ceil($total / $perPage);
        require BASE_PATH . '/app/Views/admin/image_requests.php';
    }

    public function show(int $id): void {
        $stmt = $this->pdo->prepare("
            SELECT r.*, u.display_name, u.picture_url
            FROM image_requests r
            LEFT JOIN users u ON u.id = r.user_id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $request = $stmt->fetch();
        if (!$request) {
            http_response_code(404);
            echo '依頼が見つかりません';
            return;
        }

        $stmtP = $this->pdo->prepare("SELECT * FROM prompts WHERE request_id = ? ORDER BY prompt_type");
        $stmtP->execute([$id]);
        $prompts = $stmtP->fetchAll();

        $stmtI = $this->pdo->prepare("SELECT * FROM generated_images WHERE request_id = ? ORDER BY prompt_type, image_no");
        $stmtI->execute([$id]);
        $images = $stmtI->fetchAll();

        $stmtL = $this->pdo->prepare("SELECT * FROM system_logs WHERE request_id = ? ORDER BY created_at DESC LIMIT 50");
        $stmtL->execute([$id]);
        $logs = $stmtL->fetchAll();

        require BASE_PATH . '/app/Views/admin/image_request_detail.php';
    }

    public function retry(int $id): void {
        // ジョブ再登録
        $pdo = $this->pdo;
        $pdo->prepare("UPDATE image_requests SET status = 'received', error_message = NULL, updated_at = NOW() WHERE id = ?")
            ->execute([$id]);
        $pdo->prepare("UPDATE job_queue SET status = 'pending', retry_count = 0, available_at = NOW(), updated_at = NOW() WHERE request_id = ? AND status = 'failed'")
            ->execute([$id]);
        // ジョブが無ければ新規登録
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM job_queue WHERE request_id = ? AND status IN ('pending','processing')");
        $stmtCheck->execute([$id]);
        if ((int) $stmtCheck->fetchColumn() === 0) {
            $pdo->prepare("INSERT INTO job_queue (request_id, job_type, status, created_at, updated_at) VALUES (?, 'generate_images', 'pending', NOW(), NOW())")
                ->execute([$id]);
        }
        header('Location: /admin/image-requests/' . $id);
        exit;
    }
}
