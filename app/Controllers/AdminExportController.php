<?php
// app/Controllers/AdminExportController.php
// CSVエクスポート

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Services/ClassScheduleService.php';

class AdminExportController {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = get_pdo();
        // attended_at / payment_status 等の自動カラム追加を確実に実行
        new ClassScheduleService();
    }

    private function outputCsv(string $filename, array $header, array $rows): void {
        header('Content-Type: text/csv; charset=Shift_JIS');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        // Excelで文字化けしないようSJIS変換
        $toSjis = function($arr) {
            return array_map(function($v) {
                return mb_convert_encoding((string)$v, 'SJIS-win', 'UTF-8');
            }, $arr);
        };
        fputcsv($out, $toSjis($header));
        foreach ($rows as $row) {
            fputcsv($out, $toSjis($row));
        }
        fclose($out);
    }

    // 受講生一覧
    public function users(): void {
        $rows = $this->pdo->query("
            SELECT u.id, u.display_name, u.line_user_id, u.status, u.registered_at,
                   (SELECT COUNT(*) FROM image_requests r WHERE r.line_user_id = u.line_user_id) AS total_requests,
                   (SELECT COUNT(*) FROM class_attendances a WHERE a.user_id = u.id AND a.attended_at IS NOT NULL) AS attended_count
            FROM users u ORDER BY u.id ASC
        ")->fetchAll();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r['id'], $r['display_name'] ?: '(未取得)', $r['line_user_id'],
                $r['status'], $r['registered_at'], $r['total_requests'], $r['attended_count'],
            ];
        }
        $this->outputCsv('users_' . date('Ymd') . '.csv',
            ['ID','表示名','LINE ID','状態','登録日','生成回数','参加回数'], $data);
    }

    // 参加履歴
    public function attendance(): void {
        $rows = $this->pdo->query("
            SELECT s.class_date, s.title, u.display_name, a.status,
                   a.created_at AS applied_at, a.approved_at, a.attended_at
            FROM class_attendances a
            INNER JOIN class_schedules s ON s.id = a.schedule_id
            LEFT JOIN users u ON u.id = a.user_id
            ORDER BY s.class_date DESC, a.created_at ASC
        ")->fetchAll();

        $statusMap = ['pending'=>'承認待ち','approved'=>'承認済み','rejected'=>'却下'];
        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r['class_date'], $r['title'], $r['display_name'] ?: '(未取得)',
                $statusMap[$r['status']] ?? $r['status'],
                $r['applied_at'], $r['approved_at'] ?: '',
                $r['attended_at'] ? '参加' : '未参加',
                $r['attended_at'] ?: '',
            ];
        }
        $this->outputCsv('attendance_' . date('Ymd') . '.csv',
            ['開催日','教室名','受講生','承認状態','予約日時','承認日時','参加','参加日時'], $data);
    }

    // 生成履歴
    public function requests(): void {
        $rows = $this->pdo->query("
            SELECT r.id, u.display_name, r.input_text, r.status, r.created_at,
                   (SELECT COUNT(*) FROM generated_images g WHERE g.request_id = r.id) AS image_count
            FROM image_requests r
            LEFT JOIN users u ON u.line_user_id = r.line_user_id
            ORDER BY r.id DESC
        ")->fetchAll();

        $data = [];
        foreach ($rows as $r) {
            $data[] = [
                $r['id'], $r['display_name'] ?: '(未取得)',
                mb_substr($r['input_text'] ?? '', 0, 100),
                $r['status'], $r['image_count'], $r['created_at'],
            ];
        }
        $this->outputCsv('requests_' . date('Ymd') . '.csv',
            ['ID','受講生','入力内容','状態','生成枚数','日時'], $data);
    }
}
