<?php
/**
 * v3.6.1 patch_apply.php
 * AdminReportController / AdminExportController（およびその他の影響コントローラー）の
 * 先頭に ClassScheduleService 初期化コードを自動注入します。
 *
 * 使い方: https://school.sengoku-ai.com/patch_apply.php にアクセス
 * 完了後このファイルを削除してください。
 */

define('BASE_PATH', __DIR__);

if (!file_exists(__DIR__ . '/config/db.php')) {
    die('config/db.php が見つかりません。ドキュメントルートに配置してください。');
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/app/Services/ClassScheduleService.php';

// --- Step 1: カラムマイグレーション ---
echo "<pre>\n";
echo "=== v3.6.1 パッチ適用 ===\n\n";
echo "[1] ClassScheduleService によるカラムマイグレーション...\n";
try {
    new ClassScheduleService();
    echo "    ✅ 完了\n";
} catch (Throwable $e) {
    echo "    ❌ エラー: " . $e->getMessage() . "\n";
    echo "    マイグレーション失敗。処理を中断します。\n</pre>";
    exit(1);
}

// --- Step 2: コントローラーパッチ ---
// 対象コントローラーと、注入するコードブロック
// 各コントローラーの public function stats() / index() / export() の最初の行に挿入する

$injection = <<<'PHP'

        // v3.6.1 fix: ensure attended_at and related columns exist before query
        require_once BASE_PATH . '/app/Services/ClassScheduleService.php';
        (new ClassScheduleService());

PHP;

// 対象ファイルと、挿入トリガー文字列（この直後に注入）
$targets = [
    [
        'path' => __DIR__ . '/app/Controllers/Admin/AdminReportController.php',
        // stats() メソッド内、最初の try{ または DB呼び出しの前
        'trigger' => 'public function stats(',
        'method'  => 'after_open_brace', // { の直後
    ],
    [
        'path' => __DIR__ . '/app/Controllers/Admin/AdminExportController.php',
        'trigger' => 'public function export(',
        'method'  => 'after_open_brace',
    ],
    [
        'path' => __DIR__ . '/app/Controllers/Admin/AdminExportController.php',
        'trigger' => 'public function index(',
        'method'  => 'after_open_brace',
    ],
];

echo "\n[2] コントローラーパッチ...\n";

$alreadyInjectedMarker = 'v3.6.1 fix: ensure attended_at';

foreach ($targets as $target) {
    $path = $target['path'];
    $label = basename(dirname($path)) . '/' . basename($path) . ' [' . $target['trigger'] . ']';

    if (!file_exists($path)) {
        echo "    ⚠️  スキップ（ファイル不在）: $label\n";
        continue;
    }

    $src = file_get_contents($path);

    // 既に適用済みかチェック
    if (strpos($src, $alreadyInjectedMarker) !== false) {
        echo "    ✅ 適用済みスキップ: $label\n";
        continue;
    }

    // trigger 文字列を探す
    $triggerPos = strpos($src, $target['trigger']);
    if ($triggerPos === false) {
        echo "    ⚠️  トリガー未発見（既に書き換え済み？）: $label\n";
        continue;
    }

    // { の位置を探す（trigger の直後）
    $bracePos = strpos($src, '{', $triggerPos);
    if ($bracePos === false) {
        echo "    ❌ 開き波括弧が見つかりません: $label\n";
        continue;
    }

    // バックアップ
    $backupPath = $path . '.bak361';
    if (!file_exists($backupPath)) {
        file_put_contents($backupPath, $src);
    }

    // 注入
    $newSrc = substr($src, 0, $bracePos + 1) . $injection . substr($src, $bracePos + 1);
    file_put_contents($path, $newSrc);
    echo "    ✅ パッチ適用: $label\n";
}

// --- Step 3: VERSION更新 ---
echo "\n[3] VERSION ファイル更新...\n";
$versionFile = __DIR__ . '/VERSION';
if (file_exists($versionFile)) {
    $current = trim(file_get_contents($versionFile));
    file_put_contents($versionFile, 'v3.6.1');
    echo "    ✅ $current → v3.6.1\n";
} else {
    file_put_contents($versionFile, 'v3.6.1');
    echo "    ✅ VERSION ファイル作成（v3.6.1）\n";
}

echo "\n=== パッチ適用完了 ===\n";
echo "/admin/report を開いて Fatal error が出なければ成功です。\n";
echo "このファイル（patch_apply.php）と migrate_attended_at.php は削除してください。\n";
echo "</pre>\n";
