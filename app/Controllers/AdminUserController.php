<?php
// app/Controllers/AdminUserController.php

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Services/LineService.php';
require_once BASE_PATH . '/app/Services/Logger.php';

class AdminUserController {
    private PDO $pdo;
    private LineService $line;

    public function __construct() {
        $this->pdo  = get_pdo();
        $this->line = new LineService();
    }

    // ユーザー一覧
    public function index(): void {
        $where  = [];
        $params = [];
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset  = ($page - 1) * $perPage;

        if (!empty($_GET['status'])) {
            $where[]  = 'u.status = ?';
            $params[] = $_GET['status'];
        }
        if (!empty($_GET['keyword'])) {
            $where[]  = 'u.display_name LIKE ?';
            $params[] = '%' . $_GET['keyword'] . '%';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmtCount = $this->pdo->prepare("SELECT COUNT(*) FROM users u {$whereClause}");
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT u.*,
                   (SELECT COUNT(*) FROM image_requests r WHERE r.user_id = u.id) AS total_requests,
                   (SELECT COUNT(*) FROM image_requests r WHERE r.user_id = u.id AND DATE(r.created_at) = CURDATE()) AS today_requests,
                   (SELECT COUNT(*) FROM class_attendances a WHERE a.user_id = u.id AND a.status = 'approved') AS total_classes
            FROM users u
            {$whereClause}
            ORDER BY u.created_at DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $users = $stmt->fetchAll();

        $totalPages = (int)ceil($total / $perPage);
        require BASE_PATH . '/app/Views/admin/users.php';
    }

    // ユーザー詳細
    // 会員区分を変更
    public function setMemberType(int $id): void {
        require_once BASE_PATH . '/app/Services/BillingService.php';
        (new BillingService())->setMemberType($id, $_POST['member_type'] ?? 'none');
        header('Location: /admin/users/' . $id);
        exit;
    }

    // チケット付与
    public function addTickets(int $id): void {
        require_once BASE_PATH . '/app/Services/BillingService.php';
        $count = (int)($_POST['ticket_count'] ?? 0);
        if ($count !== 0) {
            (new BillingService())->addTickets($id, $count);
        }
        header('Location: /admin/users/' . $id);
        exit;
    }

    public function show(int $id): void {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if (!$user) { http_response_code(404); echo '見つかりません'; return; }

        // 参加履歴
        $stmtA = $this->pdo->prepare("
            SELECT a.*, s.title, s.class_date, s.start_time
            FROM class_attendances a
            LEFT JOIN class_schedules s ON s.id = a.schedule_id
            WHERE a.user_id = ?
            ORDER BY s.class_date DESC
            LIMIT 20
        ");
        $stmtA->execute([$id]);
        $attendances = $stmtA->fetchAll();

        // 生成履歴
        $stmtR = $this->pdo->prepare("
            SELECT r.*,
                   (SELECT COUNT(*) FROM generated_images gi WHERE gi.request_id = r.id) AS image_count
            FROM image_requests r
            WHERE r.user_id = ?
            ORDER BY r.created_at DESC
            LIMIT 20
        ");
        $stmtR->execute([$id]);
        $requests = $stmtR->fetchAll();

        require BASE_PATH . '/app/Views/admin/user_detail.php';
    }

    // ステータス変更
    public function updateStatus(int $id): void {
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['active', 'suspended', 'banned'])) {
            http_response_code(400);
            return;
        }
        $this->pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$status, $id]);

        // 停止通知
        if ($status === 'suspended') {
            $stmt = $this->pdo->prepare("SELECT line_user_id FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $u = $stmt->fetch();
            if ($u) {
                $this->line->pushText($u['line_user_id'],
                    "アカウントが一時停止されました。\n詳しくはお問い合わせください。"
                );
            }
        }

        Logger::info('admin', "ユーザーステータス変更 user_id={$id} status={$status}");
        header('Location: /admin/users/' . $id . '?updated=1');
        exit;
    }

    // メモ更新
    public function updateMemo(int $id): void {
        $memo = trim($_POST['memo'] ?? '');
        $this->pdo->prepare("UPDATE users SET memo = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$memo, $id]);
        header('Location: /admin/users/' . $id . '?updated=1');
        exit;
    }

    // LINEメッセージ送信
    public function sendMessage(int $id): void {
        $msg = trim($_POST['message'] ?? '');
        if (!$msg) { header('Location: /admin/users/' . $id); exit; }

        $stmt = $this->pdo->prepare("SELECT line_user_id FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        if ($u) {
            $this->line->pushText($u['line_user_id'], $msg);
            Logger::info('admin', "個別LINE送信 user_id={$id}");
        }
        header('Location: /admin/users/' . $id . '?sent=1');
        exit;
    }
}
