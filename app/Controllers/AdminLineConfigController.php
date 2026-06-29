<?php
// app/Controllers/AdminLineConfigController.php
// あいさつメッセージ・リッチメニュー設定（オーナー専用）

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/settings.php';
require_once BASE_PATH . '/app/Services/RichMenuService.php';
require_once BASE_PATH . '/app/Services/Logger.php';

class AdminLineConfigController {

    public function show(): void {
        $settings = Settings::all();

        // リッチメニューのボタン設定（JSON）を取得
        $richMenuJson = Settings::get('rich_menu_buttons', '');
        $richButtons  = $richMenuJson ? json_decode($richMenuJson, true) : $this->defaultButtons();

        // 現在のリッチメニュー画像URL
        $richMenuImage = Settings::get('rich_menu_image_url', '');

        $saved = $_GET['saved'] ?? '';
        $error = $_GET['error'] ?? '';
        require BASE_PATH . '/app/Views/admin/line_config.php';
    }

    // あいさつメッセージ保存
    public function saveGreeting(): void {
        $greeting = trim($_POST['greeting_message'] ?? '');
        Settings::set('greeting_message', $greeting);
        header('Location: /admin/line-config?saved=greeting');
        exit;
    }

    // お問合せメッセージ保存
    public function saveContact(): void {
        $contact = trim($_POST['contact_message'] ?? '');
        Settings::set('contact_message', $contact);
        header('Location: /admin/line-config?saved=contact');
        exit;
    }

    // LIFF設定保存
    public function saveLiff(): void {
        Settings::set('liff_id', trim($_POST['liff_id'] ?? ''));
        Settings::set('liff_channel_id', trim($_POST['liff_channel_id'] ?? ''));
        header('Location: /admin/line-config?saved=liff');
        exit;
    }

    // リッチメニューのボタン設定保存
    public function saveButtons(): void {
        $buttons = [];
        for ($i = 0; $i < 6; $i++) {
            $buttons[] = [
                'icon'   => trim($_POST["icon_{$i}"]   ?? ''),
                'label'  => trim($_POST["label_{$i}"]  ?? ''),
                'action' => ($_POST["action_{$i}"] ?? 'message') === 'url' ? 'url' : 'message',
                'text'   => trim($_POST["text_{$i}"]   ?? ''),
                'url'    => trim($_POST["url_{$i}"]    ?? ''),
            ];
        }
        Settings::set('rich_menu_buttons', json_encode($buttons, JSON_UNESCAPED_UNICODE));
        header('Location: /admin/line-config?saved=buttons');
        exit;
    }

    // リッチメニューをLINEに反映（画像アップロード or 自動生成）
    public function applyRichMenu(): void {
        try {
            $svc = new RichMenuService();

            // ボタン設定を取得
            $json = Settings::get('rich_menu_buttons', '');
            $buttons = $json ? json_decode($json, true) : $this->defaultButtons();

            // 画像を準備
            $imagePath = STORAGE_PATH . '/richmenu_tmp.png';
            $mode = $_POST['image_mode'] ?? 'generate';

            if ($mode === 'upload' && !empty($_FILES['rich_image']) && $_FILES['rich_image']['error'] === UPLOAD_ERR_OK) {
                // アップロード画像を使用
                $ext = strtolower(pathinfo($_FILES['rich_image']['name'], PATHINFO_EXTENSION));
                $imagePath = STORAGE_PATH . '/richmenu_tmp.' . ($ext === 'png' ? 'png' : 'jpg');
                move_uploaded_file($_FILES['rich_image']['tmp_name'], $imagePath);
            } else {
                // 自動生成
                $svc->generateImage($buttons, $imagePath);
            }

            // 既存のリッチメニューを削除
            $svc->deleteAll();

            // 新規作成
            $richMenuId = $svc->createRichMenu($buttons);
            $svc->uploadImage($richMenuId, $imagePath);
            $svc->setDefault($richMenuId);

            // 保存
            Settings::set('rich_menu_id', $richMenuId);

            @unlink($imagePath);
            Logger::info('richmenu', "リッチメニュー反映 id={$richMenuId}");

            header('Location: /admin/line-config?saved=richmenu');
            exit;

        } catch (\Throwable $e) {
            header('Location: /admin/line-config?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    // リッチメニューを削除
    public function removeRichMenu(): void {
        try {
            $svc = new RichMenuService();
            $svc->deleteAll();
            Settings::set('rich_menu_id', '');
            header('Location: /admin/line-config?saved=removed');
        } catch (\Throwable $e) {
            header('Location: /admin/line-config?error=' . urlencode($e->getMessage()));
        }
        exit;
    }

    private function defaultButtons(): array {
        // LIFF予約カレンダーが設定されていれば、参加予約ボタンをカレンダーURLにする
        $liffId = Settings::get('liff_id', '');
        $calendarUrl = $liffId ? 'https://liff.line.me/' . $liffId : '';

        return [
            $calendarUrl
                ? ['icon' => '📅', 'label' => '予約カレンダー', 'action' => 'url', 'text' => '参加予約', 'url' => $calendarUrl]
                : ['icon' => '🎓', 'label' => '参加予約', 'action' => 'message', 'text' => '参加予約', 'url' => ''],
            ['icon' => '🎨', 'label' => '生成する',  'action' => 'message', 'text' => '生成する', 'url' => ''],
            ['icon' => '📋', 'label' => '履歴',      'action' => 'message', 'text' => '履歴',     'url' => ''],
            ['icon' => '🎯', 'label' => '参加',      'action' => 'message', 'text' => '参加',     'url' => ''],
            ['icon' => '❓', 'label' => '使い方',    'action' => 'message', 'text' => '使い方',   'url' => ''],
            ['icon' => '💬', 'label' => 'お問合せ',  'action' => 'message', 'text' => 'お問合せ', 'url' => ''],
        ];
    }
}
