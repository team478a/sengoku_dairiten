<?php
// app/Controllers/AdminManagerController.php
// 管理者アカウントの管理（オーナーのみ）

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';

class AdminManagerController {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = get_pdo();
        AdminAuthController::ensureColumns($this->pdo);
    }

    public function index(): void {
        $admins = $this->pdo->query("
            SELECT id, email, name, role, status, last_login_at, created_at
            FROM admin_users ORDER BY role = 'owner' DESC, created_at ASC
        ")->fetchAll();

        $saved = $_GET['saved'] ?? '';
        $error = $_GET['error'] ?? '';
        require BASE_PATH . '/app/Views/admin/managers.php';
    }

    public function store(): void {
        $email = trim($_POST['email'] ?? '');
        $name  = trim($_POST['name'] ?? '');
        $role  = ($_POST['role'] ?? 'staff') === 'owner' ? 'owner' : 'staff';
        $pass  = $_POST['password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: /admin/managers?error=' . urlencode('メールアドレスが正しくありません'));
            exit;
        }
        if (strlen($pass) < 8) {
            header('Location: /admin/managers?error=' . urlencode('パスワードは8文字以上にしてください'));
            exit;
        }

        // 重複チェック
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE email = ?");
        $stmt->execute([$email]);
        if ((int)$stmt->fetchColumn() > 0) {
            header('Location: /admin/managers?error=' . urlencode('このメールアドレスは既に登録されています'));
            exit;
        }

        $this->pdo->prepare("
            INSERT INTO admin_users (email, name, role, status, password_hash, created_at)
            VALUES (?, ?, ?, 'active', ?, NOW())
        ")->execute([$email, $name, $role, password_hash($pass, PASSWORD_DEFAULT)]);

        header('Location: /admin/managers?saved=created');
        exit;
    }

    public function updateRole(int $id): void {
        $role = ($_POST['role'] ?? 'staff') === 'owner' ? 'owner' : 'staff';

        // 自分自身のオーナー権限は剥奪できない（最後のオーナー保護）
        if ($id === (int)$_SESSION['admin_id'] && $role !== 'owner') {
            $ownerCount = (int)$this->pdo->query("SELECT COUNT(*) FROM admin_users WHERE role='owner'")->fetchColumn();
            if ($ownerCount <= 1) {
                header('Location: /admin/managers?error=' . urlencode('最後のオーナー権限は変更できません'));
                exit;
            }
        }

        $this->pdo->prepare("UPDATE admin_users SET role = ? WHERE id = ?")->execute([$role, $id]);
        header('Location: /admin/managers?saved=role');
        exit;
    }

    public function updateStatus(int $id): void {
        $status = ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'suspended';

        // 自分自身は停止できない
        if ($id === (int)$_SESSION['admin_id'] && $status !== 'active') {
            header('Location: /admin/managers?error=' . urlencode('自分自身を停止することはできません'));
            exit;
        }

        $this->pdo->prepare("UPDATE admin_users SET status = ? WHERE id = ?")->execute([$status, $id]);
        header('Location: /admin/managers?saved=status');
        exit;
    }

    public function resetPassword(int $id): void {
        $pass = $_POST['password'] ?? '';
        if (strlen($pass) < 8) {
            header('Location: /admin/managers?error=' . urlencode('パスワードは8文字以上にしてください'));
            exit;
        }
        $this->pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?")
            ->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
        header('Location: /admin/managers?saved=password');
        exit;
    }

    public function delete(int $id): void {
        // 自分自身は削除できない
        if ($id === (int)$_SESSION['admin_id']) {
            header('Location: /admin/managers?error=' . urlencode('自分自身は削除できません'));
            exit;
        }
        // 最後のオーナーは削除できない
        $stmt = $this->pdo->prepare("SELECT role FROM admin_users WHERE id = ?");
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if ($target && $target['role'] === 'owner') {
            $ownerCount = (int)$this->pdo->query("SELECT COUNT(*) FROM admin_users WHERE role='owner'")->fetchColumn();
            if ($ownerCount <= 1) {
                header('Location: /admin/managers?error=' . urlencode('最後のオーナーは削除できません'));
                exit;
            }
        }
        $this->pdo->prepare("DELETE FROM admin_users WHERE id = ?")->execute([$id]);
        header('Location: /admin/managers?saved=deleted');
        exit;
    }
}
