<?php
// app/Controllers/AdminBackupController.php
// データバックアップ（主要テーブルをSQLダンプ）

require_once BASE_PATH . '/config/database.php';

class AdminBackupController {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = get_pdo();
    }

    // 主要テーブルをSQL形式でダウンロード
    public function download(): void {
        $tables = ['users', 'class_schedules', 'class_attendances', 'image_requests', 'generated_images', 'system_settings'];

        header('Content-Type: application/sql; charset=UTF-8');
        header('Content-Disposition: attachment; filename="backup_' . date('Ymd_His') . '.sql"');

        echo "-- AIアート教室 バックアップ\n";
        echo "-- 生成日時: " . date('Y-m-d H:i:s') . "\n\n";
        echo "SET NAMES utf8mb4;\n\n";

        foreach ($tables as $table) {
            // テーブル存在チェック
            try {
                $exists = $this->pdo->query("SHOW TABLES LIKE " . $this->pdo->quote($table))->fetch();
                if (!$exists) continue;

                echo "-- ----------------------------\n";
                echo "-- Table: {$table}\n";
                echo "-- ----------------------------\n";

                $rows = $this->pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
                if (empty($rows)) {
                    echo "-- (空)\n\n";
                    continue;
                }

                $cols = array_keys($rows[0]);
                $colList = '`' . implode('`,`', $cols) . '`';

                foreach ($rows as $row) {
                    $vals = array_map(function($v) {
                        return $v === null ? 'NULL' : $this->pdo->quote($v);
                    }, array_values($row));
                    echo "INSERT INTO `{$table}` ({$colList}) VALUES (" . implode(',', $vals) . ");\n";
                }
                echo "\n";
            } catch (\Throwable $e) {
                echo "-- {$table}: エラー (" . $e->getMessage() . ")\n\n";
            }
        }
    }
}
