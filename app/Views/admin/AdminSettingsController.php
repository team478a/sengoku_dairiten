<?php
// app/Controllers/AdminSettingsController.php

require_once BASE_PATH . '/config/settings.php';

class AdminSettingsController {

    public function show(): void {
        $settings = Settings::all();
        $saved    = !empty($_GET['saved']);
        require BASE_PATH . '/app/Views/admin/settings.php';
    }

    public function save(): void {
        $allowed = [
            'line_channel_secret', 'line_channel_access_token',
            'claude_api_key', 'stability_api_key',
            'storage_driver', 'storage_public_url',
            'r2_account_id', 'r2_access_key', 'r2_secret_key', 'r2_bucket',
            'max_daily_requests_per_user', 'max_images_per_request',
            'admin_email',
        ];
        foreach ($allowed as $key) {
            if (isset($_POST[$key])) {
                Settings::set($key, trim($_POST[$key]));
            }
        }
        header('Location: /admin/settings?saved=1');
        exit;
    }

    // API接続テスト（JSON返却）
    public function testApi(): void {
        header('Content-Type: application/json');
        $input = json_decode(file_get_contents('php://input'), true);
        $type  = $input['type'] ?? '';

        try {
            switch ($type) {
                case 'line':
                    $result = $this->testLine($input['token'] ?? '');
                    break;
                case 'claude':
                    $result = $this->testClaude($input['key'] ?? '');
                    break;
                case 'stability':
                    $result = $this->testStability($input['key'] ?? '');
                    break;
                default:
                    $result = ['ok' => false, 'message' => '不明なAPIタイプです'];
            }
        } catch (\Throwable $e) {
            $result = ['ok' => false, 'message' => 'エラー：' . $e->getMessage()];
        }

        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    private function testLine(string $token): array {
        if (!$token) return ['ok' => false, 'message' => 'Access Tokenを入力してください'];

        $ch = curl_init('https://api.line.me/v2/bot/info');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}"],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) {
            $data = json_decode($res, true);
            $name = $data['displayName'] ?? '不明';
            return ['ok' => true, 'message' => "✓ 接続成功！ ボット名：{$name}"];
        }
        return ['ok' => false, 'message' => "✗ 接続失敗（HTTP {$code}）トークンを確認してください"];
    }

    private function testClaude(string $key): array {
        if (!$key) return ['ok' => false, 'message' => 'API Keyを入力してください'];

        $body = json_encode([
            'model'      => 'claude-haiku-4-5-20251001',
            'max_tokens' => 16,
            'messages'   => [['role' => 'user', 'content' => 'hi']],
        ]);
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "x-api-key: {$key}",
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) {
            return ['ok' => true, 'message' => '✓ Claude API 接続成功！'];
        }
        $err = json_decode($res, true);
        $msg = $err['error']['message'] ?? "HTTP {$code}";
        return ['ok' => false, 'message' => "✗ 接続失敗：{$msg}"];
    }

    private function testStability(string $key): array {
        if (!$key) return ['ok' => false, 'message' => 'API Keyを入力してください'];

        $ch = curl_init('https://api.stability.ai/v1/user/account');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$key}"],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) {
            $data    = json_decode($res, true);
            $credits = $data['credits'] ?? '不明';
            $credits = is_numeric($credits) ? number_format((float)$credits, 2) : $credits;
            return ['ok' => true, 'message' => "✓ Stability AI 接続成功！ 残クレジット：{$credits}"];
        }
        return ['ok' => false, 'message' => "✗ 接続失敗（HTTP {$code}）APIキーを確認してください"];
    }

    // アップデート画面
    public function showUpdate(): void {
        $currentVersion = APP_VERSION;
        $error          = null;
        require BASE_PATH . '/app/Views/admin/update.php';
    }

    // ZIPアップロードでアップデート
    public function uploadUpdate(): void {
        if (empty($_FILES['update_zip']) || $_FILES['update_zip']['error'] !== UPLOAD_ERR_OK) {
            header('Location: /admin/update?error=' . urlencode('ZIPファイルのアップロードに失敗しました'));
            exit;
        }

        $zipPath = STORAGE_PATH . '/update_upload.zip';
        $tmpDir  = STORAGE_PATH . '/update_tmp';

        try {
            // アップロードZIPを保存
            move_uploaded_file($_FILES['update_zip']['tmp_name'], $zipPath);

            // ZipArchive で展開
            if (!class_exists('ZipArchive')) {
                throw new \RuntimeException('ZipArchive が利用できません（サーバーに php-zip 拡張が必要です）');
            }
            $za = new \ZipArchive();
            if ($za->open($zipPath) !== true) {
                throw new \RuntimeException('ZIPファイルを開けませんでした');
            }

            // 一時ディレクトリに展開
            if (is_dir($tmpDir)) $this->removeDir($tmpDir);
            mkdir($tmpDir, 0755, true);
            $za->extractTo($tmpDir);
            $za->close();

            // ZIPのルート構造を判定（サブフォルダがある場合は中に入る）
            $srcBase = $tmpDir;
            $items   = array_diff(scandir($tmpDir), ['.', '..']);
            if (count($items) === 1) {
                $first = reset($items);
                if (is_dir($tmpDir . '/' . $first)) {
                    $srcBase = $tmpDir . '/' . $first;
                }
            }

            // 保護対象（上書きしない）
            $protected = [
                BASE_PATH . '/config/db.php',
                BASE_PATH . '/config/installed.lock',
                BASE_PATH . '/storage',
                BASE_PATH . '/uploads',
            ];

            // ファイルコピー
            $this->copyDir($srcBase, BASE_PATH, $protected);

            // 後処理
            @unlink($zipPath);
            $this->removeDir($tmpDir);

            header('Location: /admin/update?updated=1');
            exit;

        } catch (\Throwable $e) {
            @unlink($zipPath);
            if (is_dir($tmpDir)) $this->removeDir($tmpDir);
            header('Location: /admin/update?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    private function copyDir(string $src, string $dst, array $protectedDsts = []): void {
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iter as $item) {
            $rel    = substr($item->getPathname(), strlen($src));
            $target = rtrim($dst, '/') . $rel;

            // 保護対象はスキップ
            foreach ($protectedDsts as $p) {
                if (strpos($target, $p) === 0) continue 2;
            }

            if ($item->isDir()) {
                if (!is_dir($target)) mkdir($target, 0755, true);
            } else {
                copy($item->getPathname(), $target);
            }
        }
    }

    private function removeDir(string $dir): void {
        if (!is_dir($dir)) return;
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
