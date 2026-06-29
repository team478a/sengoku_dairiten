<?php
// app/Services/AdminNotifier.php
// 管理者への能動通知（LINE / メール）

require_once BASE_PATH . '/config/settings.php';
require_once BASE_PATH . '/app/Services/LineService.php';

class AdminNotifier {
    // イベント種別ごとに通知。設定でオン/オフ
    // type: reservation / payment / failure
    public static function notify(string $type, string $message): void {
        try {
            // この種別の通知が有効か
            $enabledTypes = Settings::get('admin_notify_events', 'reservation,payment,failure');
            if (strpos($enabledTypes, $type) === false) return;

            $prefix = [
                'reservation' => '【新規予約】',
                'payment'     => '【決済】',
                'failure'     => '【生成失敗】',
            ][$type] ?? '【お知らせ】';

            $full = $prefix . "\n" . $message;

            // LINE通知（管理者のLINE ID）
            $adminLine = Settings::get('admin_line_user_id', '');
            if ($adminLine) {
                (new LineService())->pushText($adminLine, $full);
            }

            // メール通知
            $adminEmail = Settings::get('admin_email', '');
            $notifyByMail = Settings::get('admin_notify_email', '0') === '1';
            if ($adminEmail && $notifyByMail) {
                self::sendMail($adminEmail, $prefix . ' AIアート教室', $message);
            }
        } catch (\Throwable $e) {
            // 通知失敗は無視（本処理に影響させない）
        }
    }

    private static function sendMail(string $to, string $subject, string $body): void {
        // Resend API があれば優先、なければPHP mail()
        $resendKey = Settings::get('resend_api_key', '');
        $fromEmail = Settings::get('mail_from', 'noreply@sengoku-ai.com');

        if ($resendKey) {
            $payload = json_encode([
                'from'    => $fromEmail,
                'to'      => [$to],
                'subject' => $subject,
                'text'    => $body,
            ]);
            $ch = curl_init('https://api.resend.com/emails');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    "Authorization: Bearer {$resendKey}",
                ],
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_TIMEOUT        => 10,
            ]);
            curl_exec($ch);
            curl_close($ch);
            return;
        }

        // フォールバック：PHP mail()
        $headers = "From: {$fromEmail}\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        @mb_send_mail($to, $subject, $body, $headers);
    }
}
