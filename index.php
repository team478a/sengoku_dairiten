<?php
// index.php — フロントコントローラー
// 設置場所: public_html/a-iart.sengoku-ai.com/index.php

// BASE_PATH = index.php があるディレクトリ自身
define('BASE_PATH', __DIR__);

require_once BASE_PATH . '/config/app.php';

$path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// インストール未完了はインストーラーへ
if (!INSTALLED && $path !== '/install' && !preg_match('#^/install\.php#', $path)) {
    header('Location: /install');
    exit;
}

switch (true) {

    // LINE Webhook
    case $path === '/webhook/line' && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Services/Logger.php';
        require_once BASE_PATH . '/app/Services/LineService.php';
        require_once BASE_PATH . '/app/Services/SurveyDefinition.php';
        require_once BASE_PATH . '/app/Services/UserSessionService.php';
        require_once BASE_PATH . '/app/Services/ClassScheduleService.php';
        require_once BASE_PATH . '/app/Controllers/LineWebhookController.php';
        (new LineWebhookController())->handle();
        break;

    // Admin: ログイン
    case $path === '/admin/login':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        $ctrl = new AdminAuthController();
        $method === 'POST' ? $ctrl->login() : $ctrl->showLogin();
        break;

    // Admin: ログアウト
    case $path === '/admin/logout':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        (new AdminAuthController())->logout();
        break;

    // Admin: ダッシュボード
    case $path === '/admin' || $path === '/admin/' || $path === '/admin/dashboard':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminImageRequestController.php';
        AdminAuthController::requireLogin();
        (new AdminImageRequestController())->dashboard();
        break;

    // Admin: 依頼一覧
    case $path === '/admin/image-requests' && $method === 'GET':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminImageRequestController.php';
        AdminAuthController::requireLogin();
        (new AdminImageRequestController())->index();
        break;

    // Admin: 依頼詳細
    case preg_match('#^/admin/image-requests/(\d+)$#', $path, $m) && $method === 'GET':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminImageRequestController.php';
        AdminAuthController::requireLogin();
        (new AdminImageRequestController())->show((int)$m[1]);
        break;

    // Admin: 再生成
    case preg_match('#^/admin/image-requests/(\d+)/retry$#', $path, $m) && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminImageRequestController.php';
        AdminAuthController::requireLogin();
        (new AdminImageRequestController())->retry((int)$m[1]);
        break;

    // Admin: 設定（オーナー専用）
    case $path === '/admin/settings':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminSettingsController.php';
        AdminAuthController::requireOwner();
        $ctrl = new AdminSettingsController();
        $method === 'POST' ? $ctrl->save() : $ctrl->show();
        break;

    // Admin: Stabilityクレジット更新（ダッシュボード用）
    case $path === '/admin/stability-credits' && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminSettingsController.php';
        AdminAuthController::requireLogin();
        (new AdminSettingsController())->refreshStabilityCredits();
        break;

    // Admin: API接続テスト（オーナー専用）
    case $path === '/admin/settings/test' && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminSettingsController.php';
        AdminAuthController::requireOwner();
        (new AdminSettingsController())->testApi();
        break;

    // Admin: ZIPアップロードアップデート（オーナー専用）
    case $path === '/admin/update/upload' && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminSettingsController.php';
        AdminAuthController::requireOwner();
        (new AdminSettingsController())->uploadUpdate();
        break;

    // Admin: アップデート（オーナー専用）
    case $path === '/admin/update':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminSettingsController.php';
        AdminAuthController::requireOwner();
        $ctrl = new AdminSettingsController();
        $method === 'POST' ? $ctrl->runUpdate() : $ctrl->showUpdate();
        break;

    // Admin: スケジュール一覧
    case $path === '/admin/classes' && $method === 'GET':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminClassController.php';
        require_once BASE_PATH . '/app/Services/ClassScheduleService.php';
        require_once BASE_PATH . '/app/Services/LineService.php';
        require_once BASE_PATH . '/app/Services/Logger.php';
        AdminAuthController::requireLogin();
        (new AdminClassController())->index();
        break;

    // Admin: スケジュール作成
    case $path === '/admin/classes/create' || ($path === '/admin/classes' && $method === 'POST'):
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminClassController.php';
        require_once BASE_PATH . '/app/Services/ClassScheduleService.php';
        require_once BASE_PATH . '/app/Services/LineService.php';
        require_once BASE_PATH . '/app/Services/Logger.php';
        AdminAuthController::requireLogin();
        $ctrl = new AdminClassController();
        $method === 'POST' ? $ctrl->store() : $ctrl->create();
        break;

    // Admin: スケジュール編集
    case preg_match('#^/admin/classes/(\d+)/edit$#', $path, $m):
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminClassController.php';
        require_once BASE_PATH . '/app/Services/ClassScheduleService.php';
        require_once BASE_PATH . '/app/Services/LineService.php';
        require_once BASE_PATH . '/app/Services/Logger.php';
        AdminAuthController::requireLogin();
        $ctrl = new AdminClassController();
        $method === 'POST' ? $ctrl->update((int)$m[1]) : $ctrl->edit((int)$m[1]);
        break;

    // Admin: スケジュール更新
    case preg_match('#^/admin/classes/(\d+)/update$#', $path, $m) && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminClassController.php';
        require_once BASE_PATH . '/app/Services/ClassScheduleService.php';
        require_once BASE_PATH . '/app/Services/LineService.php';
        require_once BASE_PATH . '/app/Services/Logger.php';
        AdminAuthController::requireLogin();
        (new AdminClassController())->update((int)$m[1]);
        break;

    // Admin: スケジュールキャンセル
    case preg_match('#^/admin/classes/(\d+)/cancel$#', $path, $m) && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminClassController.php';
        require_once BASE_PATH . '/app/Services/ClassScheduleService.php';
        require_once BASE_PATH . '/app/Services/LineService.php';
        require_once BASE_PATH . '/app/Services/Logger.php';
        AdminAuthController::requireLogin();
        (new AdminClassController())->cancel((int)$m[1]);
        break;

    // Admin: 参加承認
    case preg_match('#^/admin/classes/attendance/(\d+)/approve$#', $path, $m) && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminClassController.php';
        require_once BASE_PATH . '/app/Services/ClassScheduleService.php';
        require_once BASE_PATH . '/app/Services/LineService.php';
        require_once BASE_PATH . '/app/Services/Logger.php';
        AdminAuthController::requireLogin();
        (new AdminClassController())->approve((int)$m[1]);
        break;

    // Admin: 集金済みにする
    case preg_match('#^/admin/classes/attendance/(\d+)/paid$#', $path, $m) && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminClassController.php';
        require_once BASE_PATH . '/app/Services/ClassScheduleService.php';
        require_once BASE_PATH . '/app/Services/LineService.php';
        require_once BASE_PATH . '/app/Services/Logger.php';
        AdminAuthController::requireLogin();
        (new AdminClassController())->markPaid((int)$m[1]);
        break;

    // Admin: 参加却下
    case preg_match('#^/admin/classes/attendance/(\d+)/reject$#', $path, $m) && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminClassController.php';
        require_once BASE_PATH . '/app/Services/ClassScheduleService.php';
        require_once BASE_PATH . '/app/Services/LineService.php';
        require_once BASE_PATH . '/app/Services/Logger.php';
        AdminAuthController::requireLogin();
        (new AdminClassController())->reject((int)$m[1]);
        break;

    // Admin: 全員承認
    case preg_match('#^/admin/classes/(\d+)/approve-all$#', $path, $m) && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminClassController.php';
        require_once BASE_PATH . '/app/Services/ClassScheduleService.php';
        require_once BASE_PATH . '/app/Services/LineService.php';
        require_once BASE_PATH . '/app/Services/Logger.php';
        AdminAuthController::requireLogin();
        (new AdminClassController())->approveAll((int)$m[1]);
        break;

    // Admin: 一斉メッセージ
    case $path === '/admin/broadcast':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminBroadcastController.php';
        require_once BASE_PATH . '/app/Services/LineService.php';
        require_once BASE_PATH . '/app/Services/Logger.php';
        require_once BASE_PATH . '/app/Services/ClassScheduleService.php';
        AdminAuthController::requireLogin();
        $ctrl = new AdminBroadcastController();
        $method === 'POST' ? $ctrl->send() : $ctrl->show();
        break;

    // Admin: 出席履歴
    case $path === '/admin/attendance':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminAttendanceController.php';
        AdminAuthController::requireLogin();
        (new AdminAttendanceController())->index();
        break;

    // Admin: ユーザー一覧
    case $path === '/admin/users' && $method === 'GET':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminUserController.php';
        require_once BASE_PATH . '/app/Services/LineService.php';
        require_once BASE_PATH . '/app/Services/Logger.php';
        AdminAuthController::requireLogin();
        (new AdminUserController())->index();
        break;

    // Admin: ユーザー詳細
    case preg_match('#^/admin/users/(\d+)$#', $path, $m) && $method === 'GET':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminUserController.php';
        require_once BASE_PATH . '/app/Services/LineService.php';
        require_once BASE_PATH . '/app/Services/Logger.php';
        AdminAuthController::requireLogin();
        (new AdminUserController())->show((int)$m[1]);
        break;

    // Admin: ユーザーステータス変更
    case preg_match('#^/admin/users/(\d+)/status$#', $path, $m) && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminUserController.php';
        require_once BASE_PATH . '/app/Services/LineService.php';
        require_once BASE_PATH . '/app/Services/Logger.php';
        AdminAuthController::requireLogin();
        (new AdminUserController())->updateStatus((int)$m[1]);
        break;

    // Admin: 会員区分変更
    case preg_match('#^/admin/users/(\d+)/member-type$#', $path, $m) && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminUserController.php';
        AdminAuthController::requireLogin();
        (new AdminUserController())->setMemberType((int)$m[1]);
        break;

    // Admin: チケット付与
    case preg_match('#^/admin/users/(\d+)/tickets$#', $path, $m) && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminUserController.php';
        AdminAuthController::requireLogin();
        (new AdminUserController())->addTickets((int)$m[1]);
        break;

    // Admin: ユーザーメモ
    case preg_match('#^/admin/users/(\d+)/memo$#', $path, $m) && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminUserController.php';
        require_once BASE_PATH . '/app/Services/LineService.php';
        require_once BASE_PATH . '/app/Services/Logger.php';
        AdminAuthController::requireLogin();
        (new AdminUserController())->updateMemo((int)$m[1]);
        break;

    // Admin: ユーザーメッセージ送信
    case preg_match('#^/admin/users/(\d+)/message$#', $path, $m) && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminUserController.php';
        require_once BASE_PATH . '/app/Services/LineService.php';
        require_once BASE_PATH . '/app/Services/Logger.php';
        AdminAuthController::requireLogin();
        (new AdminUserController())->sendMessage((int)$m[1]);
        break;

    // Admin: 今すぐリマインダー送信
    case preg_match('#^/admin/classes/(\d+)/remind$#', $path, $m) && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminClassController.php';
        require_once BASE_PATH . '/app/Services/ClassScheduleService.php';
        require_once BASE_PATH . '/app/Services/LineService.php';
        require_once BASE_PATH . '/app/Services/Logger.php';
        AdminAuthController::requireLogin();
        (new AdminClassController())->sendReminder((int)$m[1]);
        break;

    // Admin: 管理者管理（オーナー専用）
    case $path === '/admin/managers':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminManagerController.php';
        AdminAuthController::requireOwner();
        $ctrl = new AdminManagerController();
        $method === 'POST' ? $ctrl->store() : $ctrl->index();
        break;

    case preg_match('#^/admin/managers/(\d+)/role$#', $path, $m) && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminManagerController.php';
        AdminAuthController::requireOwner();
        (new AdminManagerController())->updateRole((int)$m[1]);
        break;

    case preg_match('#^/admin/managers/(\d+)/status$#', $path, $m) && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminManagerController.php';
        AdminAuthController::requireOwner();
        (new AdminManagerController())->updateStatus((int)$m[1]);
        break;

    case preg_match('#^/admin/managers/(\d+)/password$#', $path, $m) && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminManagerController.php';
        AdminAuthController::requireOwner();
        (new AdminManagerController())->resetPassword((int)$m[1]);
        break;

    case preg_match('#^/admin/managers/(\d+)/delete$#', $path, $m) && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminManagerController.php';
        AdminAuthController::requireOwner();
        (new AdminManagerController())->delete((int)$m[1]);
        break;

    // Admin: LINE設定（あいさつ・リッチメニュー）オーナー専用
    case $path === '/admin/line-config':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminLineConfigController.php';
        AdminAuthController::requireOwner();
        (new AdminLineConfigController())->show();
        break;

    case $path === '/admin/line-config/greeting' && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminLineConfigController.php';
        AdminAuthController::requireOwner();
        (new AdminLineConfigController())->saveGreeting();
        break;

    case $path === '/admin/line-config/contact' && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminLineConfigController.php';
        AdminAuthController::requireOwner();
        (new AdminLineConfigController())->saveContact();
        break;

    case $path === '/admin/line-config/liff' && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminLineConfigController.php';
        AdminAuthController::requireOwner();
        (new AdminLineConfigController())->saveLiff();
        break;

    case $path === '/admin/line-config/buttons' && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminLineConfigController.php';
        AdminAuthController::requireOwner();
        (new AdminLineConfigController())->saveButtons();
        break;

    case $path === '/admin/line-config/apply' && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminLineConfigController.php';
        AdminAuthController::requireOwner();
        (new AdminLineConfigController())->applyRichMenu();
        break;

    case $path === '/admin/line-config/remove' && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminLineConfigController.php';
        AdminAuthController::requireOwner();
        (new AdminLineConfigController())->removeRichMenu();
        break;

    // Admin: QRコード
    case $path === '/admin/qrcode':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminQrController.php';
        AdminAuthController::requireLogin();
        $ctrl = new AdminQrController();
        $method === 'POST' ? $ctrl->save() : $ctrl->show();
        break;

    // Admin: QRコード LINE ID削除
    case $path === '/admin/qrcode/delete' && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminQrController.php';
        AdminAuthController::requireLogin();
        (new AdminQrController())->delete();
        break;

    // Admin: 使い方マニュアル
    case $path === '/admin/manual':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        AdminAuthController::requireLogin();
        $pageTitle = '使い方マニュアル';
        require BASE_PATH . '/app/Views/admin/manual.php';
        break;

    // 外部監視サービス用のworker起動エンドポイント（cronの保険）
    // 例: https://school.sengoku-ai.com/cron/run?token=XXXX
    case $path === '/cron/run':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Services/Logger.php';
        require_once BASE_PATH . '/app/Services/LineService.php';
        require_once BASE_PATH . '/app/Services/PromptService.php';
        require_once BASE_PATH . '/app/Services/ImageGenerationService.php';
        require_once BASE_PATH . '/app/Services/StorageService.php';
        require_once BASE_PATH . '/app/Services/SurveyDefinition.php';
        require_once BASE_PATH . '/app/Workers/GenerateImagesWorker.php';

        // トークン照合（初回アクセス時に自動生成して保存）
        $savedToken = Settings::get('cron_token', '');
        if ($savedToken === '') {
            $savedToken = bin2hex(random_bytes(16));
            Settings::set('cron_token', $savedToken);
        }
        $given = $_GET['token'] ?? '';
        if (!hash_equals($savedToken, $given)) {
            http_response_code(403);
            echo 'Forbidden';
            break;
        }

        header('Content-Type: text/plain');
        try {
            $processed = 0;
            for ($i = 0; $i < 5; $i++) {
                $left = (int) get_pdo()->query(
                    "SELECT COUNT(*) FROM job_queue WHERE status='pending' AND available_at <= NOW()"
                )->fetchColumn();
                if ($left === 0) break;
                (new GenerateImagesWorker())->run();
                $processed++;
            }
            Settings::set('worker_last_run', date('Y-m-d H:i:s'));
            require_once BASE_PATH . '/app/Services/ReminderService.php';
            $reminded = (new ReminderService())->dispatchDue();
            echo "OK processed={$processed} reminded={$reminded} at " . date('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'ERROR: ' . $e->getMessage();
        }
        break;

    // 受講生向けLIFF予約カレンダー（公開）
    case $path === '/liff/calendar' || $path === '/liff':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/LiffCalendarController.php';
        (new LiffCalendarController())->show();
        break;

    // LIFF予約API（公開・IDトークンで認証）
    case $path === '/liff/reserve' && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/LiffCalendarController.php';
        (new LiffCalendarController())->reserve();
        break;

    // Admin: カレンダー表示
    case $path === '/admin/calendar':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminCalendarController.php';
        AdminAuthController::requireLogin();
        (new AdminCalendarController())->show();
        break;

    // Admin: 統計・エクスポート
    case $path === '/admin/report':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminReportController.php';
        AdminAuthController::requireLogin();
        (new AdminReportController())->stats();
        break;

    // Admin: 操作ログ（オーナー専用）
    case $path === '/admin/logs':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminReportController.php';
        AdminAuthController::requireOwner();
        (new AdminReportController())->logs();
        break;

    // Admin: CSVエクスポート
    case $path === '/admin/export/users':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminExportController.php';
        AdminAuthController::requireLogin();
        (new AdminExportController())->users();
        break;
    case $path === '/admin/export/attendance':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminExportController.php';
        AdminAuthController::requireLogin();
        (new AdminExportController())->attendance();
        break;
    case $path === '/admin/export/requests':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminExportController.php';
        AdminAuthController::requireLogin();
        (new AdminExportController())->requests();
        break;

    // Admin: ギャラリー
    case $path === '/admin/gallery':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminGalleryController.php';
        AdminAuthController::requireLogin();
        (new AdminGalleryController())->show();
        break;

    // Admin: バックアップ（オーナー専用）
    case $path === '/admin/backup':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminBackupController.php';
        AdminAuthController::requireOwner();
        (new AdminBackupController())->download();
        break;

    // Stripe Webhook（決済完了）
    case $path === '/stripe/webhook' && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/StripeWebhookController.php';
        (new StripeWebhookController())->handle();
        break;

    // 決済完了ページ（LIFFのsuccess_url）
    case $path === '/liff/paid':
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>お支払い完了</title>';
        echo '<script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>';
        echo '<style>body{font-family:sans-serif;text-align:center;padding:60px 20px;background:#f4f5f7;color:#1a202c}h1{color:#7c6af7}.btn{display:inline-block;margin-top:20px;padding:12px 24px;background:#7c6af7;color:#fff;border-radius:8px;text-decoration:none}</style></head><body>';
        echo '<h1>✅ お支払い完了</h1><p>ご参加が確定しました。<br>確認メッセージをLINEにお送りしました。</p>';
        echo '<p style="font-size:13px;color:#718096;margin-top:20px">このページは閉じて構いません。</p>';
        $liffId = Settings::get('liff_id', '');
        if ($liffId) {
            echo '<script>liff.init({liffId:' . json_encode($liffId) . '}).then(function(){setTimeout(function(){if(liff.isInClient())liff.closeWindow();},2500);});</script>';
        }
        echo '</body></html>';
        break;

    // Admin: 決済履歴
    case $path === '/admin/payments':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminPaymentController.php';
        AdminAuthController::requireOwner();
        (new AdminPaymentController())->index();
        break;

    case preg_match('#^/admin/payments/(\d+)/refund$#', $path, $m) && $method === 'POST':
        require_once BASE_PATH . '/config/database.php';
        require_once BASE_PATH . '/config/settings.php';
        require_once BASE_PATH . '/app/Controllers/AdminAuthController.php';
        require_once BASE_PATH . '/app/Controllers/AdminPaymentController.php';
        AdminAuthController::requireOwner();
        (new AdminPaymentController())->refund((int)$m[1]);
        break;

    // インストーラー
    case $path === '/install' || $path === '/install.php':
        require_once BASE_PATH . '/install.php';
        break;

    // 404
    default:
        if (strpos($path, '/admin') === 0) {
            header('Location: /admin/dashboard');
        } else {
            http_response_code(404);
            echo '404 Not Found';
        }
}
