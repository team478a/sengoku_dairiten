<?php
/**
 * attended_at / 関連カラム 強制マイグレーション
 * v3.6.1 パッチ同梱
 * 直接ブラウザでアクセスするか、patch_apply.php から呼ばれる
 */

define('BASE_PATH', __DIR__);

// config読み込み
if (!file_exists(__DIR__ . '/config/db.php')) {
    die('config/db.php が見つかりません。ドキュメントルートに配置してください。');
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/app/Services/ClassScheduleService.php';

// ClassScheduleService のコンストラクタで ensureReminderColumns() が走る
try {
    new ClassScheduleService();
    echo "✅ カラムマイグレーション完了（または既に適用済み）\n";
} catch (Throwable $e) {
    echo "❌ エラー: " . htmlspecialchars($e->getMessage()) . "\n";
    exit(1);
}
echo "このファイルは削除しても構いません。\n";
