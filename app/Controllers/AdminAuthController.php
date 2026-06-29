<?php
// app/Controllers/AdminAuthController.php

require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/config/database.php';

class AdminAuthController {

    public static function isLoggedIn(): bool {
        return !empty($_SESSION['admin_id']);
    }

    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            header('Location: /admin/login');
            exit;
        }
        // 管理画面の POST リクエストは CSRFトークンを検証する
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
        }
    }

    // オーナー権限が必要なページで使う
    public static function requireOwner(): void {
        self::requireLogin();
        if (self::role() !== 'owner') {
            http_response_code(403);
            echo '<div style="font-family:sans-serif;padding:40px;text-align:center;color:#666">この操作にはオーナー権限が必要です。<br><a href="/admin/dashboard">ダッシュボードへ戻る</a></div>';
            exit;
        }
    }

    public static function role(): string {
        return $_SESSION['admin_role'] ?? 'staff';
    }

    public static function isOwner(): bool {
        return self::role() === 'owner';
    }

    public static function adminName(): string {
        return $_SESSION['admin_name'] ?? ($_SESSION['admin_email'] ?? '管理者');
    }

    public function showLogin(): void {
        if (self::isLoggedIn()) {
            header('Location: /admin/dashboard');
            exit;
        }
        require BASE_PATH . '/app/Views/admin/login.php';
    }

    public function login(): void {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $error    = null;

        if (!$email || !$password) {
            $error = 'メールアドレスとパスワードを入力してください';
        } else {
            try {
                $pdo = get_pdo();
                // role/status/nameカラムが無い場合に備えて自動追加
                self::ensureColumns($pdo);

                $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE email = ?");
                $stmt->execute([$email]);
                $admin = $stmt->fetch();

                if ($admin && password_verify($password, $admin['password_hash'])) {
                    // 停止アカウントは拒否
                    if (($admin['status'] ?? 'active') !== 'active') {
                        $error = 'このアカウントは停止されています';
                        require BASE_PATH . '/app/Views/admin/login.php';
                        return;
                    }
                    session_regenerate_id(true);
                    $_SESSION['admin_id']    = $admin['id'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_name']  = $admin['name'] ?? $admin['email'];
                    $_SESSION['admin_role']  = $admin['role'] ?? 'owner'; // 旧データはowner扱い

                    // 最終ログイン記録
                    $pdo->prepare("UPDATE admin_users SET last_login_at = NOW() WHERE id = ?")
                        ->execute([$admin['id']]);

                    header('Location: /admin/dashboard');
                    exit;
                } else {
                    $error = 'メールアドレスまたはパスワードが違います';
                }
            } catch (\Throwable $e) {
                $error = 'ログインに失敗しました：' . $e->getMessage();
            }
        }

        require BASE_PATH . '/app/Views/admin/login.php';
    }

    public function logout(): void {
        $_SESSION = [];
        session_destroy();
        header('Location: /admin/login');
        exit;
    }

    // admin_usersにrole/status/name/last_login_atが無ければ追加
    public static function ensureColumns(PDO $pdo): void {
        $cols = $pdo->query("SHOW COLUMNS FROM admin_users")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('name', $cols)) {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN name VARCHAR(255) AFTER email");
        }
        if (!in_array('role', $cols)) {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'staff'");
            // 既存の最初の管理者をオーナーに
            $pdo->exec("UPDATE admin_users SET role='owner' WHERE id=(SELECT t.mid FROM (SELECT MIN(id) AS mid FROM admin_users) AS t)");
        }
        if (!in_array('status', $cols)) {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'");
        }
        if (!in_array('last_login_at', $cols)) {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN last_login_at DATETIME");
        }
    }
}
