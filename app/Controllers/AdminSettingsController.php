<?php
// app/Controllers/AdminSettingsController.php

require_once BASE_PATH . '/config/settings.php';

class AdminSettingsController {

    public function show(): void {
        // 外部監視用cronトークンを初回生成
        if (Settings::get('cron_token', '') === '') {
            Settings::set('cron_token', bin2hex(random_bytes(16)));
        }
        $settings = Settings::all();
        $saved    = !empty($_GET['saved']);
        require BASE_PATH . '/app/Views/admin/settings.php';
    }

    public function save(): void {
        $allowed = [
            'line_channel_secret', 'line_channel_access_token',
            'claude_api_key', 'stability_api_key',
            'image_engine', 'grok_api_key', 'grok_image_model',
            'stability_model', 'image_aspect', 'image_steps', 'image_cfg',
            'prompt_model',
            'ng_words',
            'stripe_secret_key', 'stripe_webhook_secret', 'stripe_publishable_key',
            'stripe_subscription_price_id', 'subscription_price_label', 'ticket_valid_days',
            'admin_line_user_id', 'admin_notify_email', 'resend_api_key', 'mail_from',
            'terms_url', 'privacy_url',
            'storage_driver', 'storage_public_url',
            'r2_account_id', 'r2_access_key', 'r2_secret_key', 'r2_bucket',
            'max_daily_requests_per_user', 'max_images_per_request',
            'images_per_pattern', 'line_grid_mode',
            'line_monthly_limit',
            'admin_email',
        ];
        foreach ($allowed as $key) {
            if (isset($_POST[$key])) {
                Settings::set($key, trim($_POST[$key]));
            }
        }

        // チケットプラン（count[]とprice[]の配列からJSON生成）
        if (isset($_POST['ticket_count']) && is_array($_POST['ticket_count'])) {
            $plans = [];
            foreach ($_POST['ticket_count'] as $i => $cnt) {
                $cnt   = (int)$cnt;
                $price = (int)($_POST['ticket_price'][$i] ?? 0);
                if ($cnt > 0 && $price > 0) {
                    $plans[] = ['count' => $cnt, 'price' => $price];
                }
            }
            Settings::set('ticket_plans', json_encode($plans, JSON_UNESCAPED_UNICODE));
        }

        // 管理者通知イベント（チェックボックス配列）
        if (isset($_POST['notify_events_present'])) {
            $events = $_POST['admin_notify_events'] ?? [];
            if (is_array($events)) {
                Settings::set('admin_notify_events', implode(',', $events));
            } else {
                Settings::set('admin_notify_events', '');
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
                case 'grok':
                    $result = $this->testGrok($input['key'] ?? '');
                    break;
                case 'stripe':
                    require_once BASE_PATH . '/app/Services/StripeService.php';
                    // テスト時はキーを保存せず、引数で直接渡す
                    $result = (new StripeService($input['key'] ?? ''))->testConnection();
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

        // 残高エンドポイントで接続確認＆クレジット取得
        $ch = curl_init('https://api.stability.ai/v1/user/balance');
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
            $credits = $data['credits'] ?? null;
            if (is_numeric($credits)) {
                // ダッシュボード用にキャッシュ
                Settings::set('stability_credits_cache', (string)$credits);
                Settings::set('stability_credits_checked_at', date('Y-m-d H:i:s'));
                return ['ok' => true, 'message' => "✓ Stability AI 接続成功！ 残クレジット：" . number_format((float)$credits, 2)];
            }
            return ['ok' => true, 'message' => '✓ Stability AI 接続成功！（残高情報が取得できませんでした）'];
        }
        if ($code === 401) {
            return ['ok' => false, 'message' => "✗ 接続失敗：APIキーが正しくありません"];
        }
        return ['ok' => false, 'message' => "✗ 接続失敗（HTTP {$code}）"];
    }

    // Grok（xAI）接続テスト
    private function testGrok(string $key): array {
        if (!$key) return ['ok' => false, 'message' => 'API Keyを入力してください'];

        // モデル一覧で疎通確認（生成は課金されるため軽量に）
        $ch = curl_init('https://api.x.ai/v1/models');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$key}"],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200) {
            return ['ok' => true, 'message' => '✓ Grok（xAI）接続成功！'];
        }
        $err = json_decode($res, true);
        $msg = $err['error']['message'] ?? ($err['error'] ?? "HTTP {$code}");
        if (is_array($msg)) $msg = json_encode($msg);
        return ['ok' => false, 'message' => "✗ 接続失敗：{$msg}"];
    }

    // ダッシュボードからの残高更新（保存済みキーを使う）
    public function refreshStabilityCredits(): void {
        header('Content-Type: application/json');
        $key = Settings::stabilityApiKey();
        if (!$key) {
            echo json_encode(['ok' => false, 'message' => 'APIキーが未設定です']);
            return;
        }
        $result = $this->testStability($key);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
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

            // 一時ディレクトリに展開（Zip Slip 対策：エントリを個別検証してから展開）
            if (is_dir($tmpDir)) $this->removeDir($tmpDir);
            mkdir($tmpDir, 0755, true);

            $realTmpDir = realpath($tmpDir);
            $allowedExt = ['php', 'html', 'css', 'js', 'json', 'sql', 'txt', 'md', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'lock', 'htaccess', ''];
            for ($i = 0; $i < $za->numFiles; $i++) {
                $name = $za->getNameIndex($i);
                // ディレクトリエントリはスキップ（後で自動作成される）
                if (substr($name, -1) === '/') continue;
                // 絶対パス・../ を含むエントリを拒否
                if (strpos($name, '..') !== false || $name[0] === '/') {
                    throw new \RuntimeException("危険なZIPエントリを検出しました: {$name}");
                }
                // 展開先がtmpDir配下に収まるか確認
                $destPath = $realTmpDir . '/' . $name;
                // 拡張子チェック
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt, true)) {
                    throw new \RuntimeException("許可されていない拡張子のファイルがZIPに含まれています: {$name}");
                }
                // 展開
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) mkdir($destDir, 0755, true);
                $data = $za->getFromIndex($i);
                if ($data === false) throw new \RuntimeException("ZIPエントリの読み込みに失敗しました: {$name}");
                file_put_contents($destPath, $data);
                // シムリンクチェック
                if (is_link($destPath)) {
                    unlink($destPath);
                    throw new \RuntimeException("ZIPにシンボリックリンクが含まれています: {$name}");
                }
            }
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

            // VERSIONファイルを確実に更新（ZIP内にあれば反映）
            $srcVersion = $srcBase . '/VERSION';
            if (is_file($srcVersion)) {
                copy($srcVersion, BASE_PATH . '/VERSION');
            }

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
