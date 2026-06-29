<?php
// app/Controllers/AdminGalleryController.php
// 生成画像ギャラリー

require_once BASE_PATH . '/config/database.php';

class AdminGalleryController {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = get_pdo();
    }

    public function show(): void {
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 60;
        $offset  = ($page - 1) * $perPage;

        $total = (int)$this->pdo->query("SELECT COUNT(*) FROM generated_images WHERE status='completed' OR status IS NULL")->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT g.id, g.image_url, g.preview_url, g.created_at,
                   r.input_text, u.display_name
            FROM generated_images g
            LEFT JOIN image_requests r ON r.id = g.request_id
            LEFT JOIN users u ON u.line_user_id = r.line_user_id
            WHERE g.status = 'completed' OR g.status IS NULL
            ORDER BY g.id DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $images = $stmt->fetchAll();

        $totalPages = (int)ceil($total / $perPage);
        require BASE_PATH . '/app/Views/admin/gallery.php';
    }
}
