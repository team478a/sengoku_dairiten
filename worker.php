#!/usr/bin/env php
<?php
// worker.php — cronから実行するジョブワーカー
// cron設定例: * * * * * php /home/dzdspowl/public_html/a-iart.sengoku-ai.com/worker.php

define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/app.php';

if (!INSTALLED) {
    echo "[" . date('Y-m-d H:i:s') . "] インストール未完了\n";
    exit(1);
}

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/settings.php';
require_once BASE_PATH . '/app/Services/Logger.php';
require_once BASE_PATH . '/app/Services/LineService.php';
require_once BASE_PATH . '/app/Services/PromptService.php';
require_once BASE_PATH . '/app/Services/ImageGenerationService.php';
require_once BASE_PATH . '/app/Services/StorageService.php';
require_once BASE_PATH . '/app/Services/SurveyDefinition.php';
require_once BASE_PATH . '/app/Workers/GenerateImagesWorker.php';

// 多重起動防止
$lockFile = STORAGE_PATH . '/worker.lock';
$fp = fopen($lockFile, 'c');
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    exit(0);
}

try {
    $processed = 0;
    for ($i = 0; $i < 5; $i++) {
        $pdo   = get_pdo();
        $count = (int) $pdo->query("SELECT COUNT(*) FROM job_queue WHERE status = 'pending' AND available_at <= NOW()")->fetchColumn();
        if ($count === 0) break;
        (new GenerateImagesWorker())->run();
        $processed++;
    }
    // 死活監視：最終実行時刻を記録（処理がなくても毎回更新）
    Settings::set('worker_last_run', date('Y-m-d H:i:s'));

    // リマインダー送信（時刻が来たもの）
    require_once BASE_PATH . '/app/Services/ReminderService.php';
    $reminded = (new ReminderService())->dispatchDue();
    if ($reminded > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] リマインダー{$reminded}通送信\n";
    }

    if ($processed > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] {$processed}件処理\n";
    }
} catch (\Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] エラー: " . $e->getMessage() . "\n";
} finally {
    flock($fp, LOCK_UN);
    fclose($fp);
}
