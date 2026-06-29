<?php
// app/Services/UserSessionService.php
// アンケート進行状態をDBで管理

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/app/Services/SurveyDefinition.php';

class UserSessionService {
    private PDO $pdo;
    private int $ttlMinutes;

    public function __construct() {
        $this->pdo        = get_pdo();
        $this->ttlMinutes = (int) (Settings::get('survey_session_ttl_minutes') ?: 30);
    }

    // 現在のセッション取得（期限切れは削除）
    public function get(string $lineUserId): ?array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM user_sessions
            WHERE line_user_id = ? AND expires_at > NOW()
        ");
        $stmt->execute([$lineUserId]);
        $row = $stmt->fetch();
        if (!$row) return null;

        $row['survey_data'] = json_decode($row['survey_data'] ?? '{}', true) ?: [];
        return $row;
    }

    // セッション開始（Q1へ）
    public function start(string $lineUserId): void {
        $this->upsert($lineUserId, SurveyDefinition::STEP_STYLE, []);
    }

    // ステップを進める
    public function advance(string $lineUserId, string $nextStep, array $data): void {
        $this->upsert($lineUserId, $nextStep, $data);
    }

    // セッション終了
    public function clear(string $lineUserId): void {
        $this->pdo->prepare("DELETE FROM user_sessions WHERE line_user_id = ?")
            ->execute([$lineUserId]);
    }

    // セッション有効期限を延長
    public function touch(string $lineUserId): void {
        $expires = date('Y-m-d H:i:s', strtotime("+{$this->ttlMinutes} minutes"));
        $this->pdo->prepare("UPDATE user_sessions SET expires_at = ? WHERE line_user_id = ?")
            ->execute([$expires, $lineUserId]);
    }

    private function upsert(string $lineUserId, string $step, array $data): void {
        $expires = date('Y-m-d H:i:s', strtotime("+{$this->ttlMinutes} minutes"));
        $json    = json_encode($data, JSON_UNESCAPED_UNICODE);

        $this->pdo->prepare("
            INSERT INTO user_sessions (line_user_id, step, survey_data, expires_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE step = VALUES(step), survey_data = VALUES(survey_data), expires_at = VALUES(expires_at), updated_at = NOW()
        ")->execute([$lineUserId, $step, $json, $expires]);
    }

    // 期限切れセッションを定期クリーンアップ
    public function cleanup(): void {
        $this->pdo->exec("DELETE FROM user_sessions WHERE expires_at <= NOW()");
    }
}
