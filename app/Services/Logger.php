<?php
// app/Services/Logger.php

require_once BASE_PATH . '/config/database.php';

class Logger {
    public static function info(string $type, string $message, ?int $requestId = null): void {
        self::write('info', $type, $message, $requestId);
    }
    public static function warning(string $type, string $message, ?int $requestId = null): void {
        self::write('warning', $type, $message, $requestId);
    }
    public static function error(string $type, string $message, ?int $requestId = null): void {
        self::write('error', $type, $message, $requestId);
    }

    private static function write(string $level, string $type, string $message, ?int $requestId): void {
        try {
            $pdo  = get_pdo();
            $stmt = $pdo->prepare("
                INSERT INTO system_logs (request_id, log_level, log_type, message, created_at)
                VALUES (:request_id, :level, :type, :message, NOW())
            ");
            $stmt->execute([
                'request_id' => $requestId,
                'level'      => $level,
                'type'       => $type,
                'message'    => $message,
            ]);
        } catch (\Throwable $e) {
            // ファイルへフォールバック
            $line = "[" . date('Y-m-d H:i:s') . "] [{$level}] [{$type}] {$message}\n";
            @file_put_contents(LOG_PATH . '/system.log', $line, FILE_APPEND);
        }
    }
}
