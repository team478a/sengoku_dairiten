<?php
// app/Services/LineService.php

require_once BASE_PATH . '/config/settings.php';

class LineService {
    private string $channelSecret;
    private string $accessToken;
    private string $apiBase = 'https://api.line.me/v2/bot';

    public function __construct() {
        $this->channelSecret = Settings::lineChannelSecret();
        $this->accessToken   = Settings::lineAccessToken();
    }

    // 署名検証
    public function verifySignature(string $body, string $signature): bool {
        $hash = base64_encode(hash_hmac('sha256', $body, $this->channelSecret, true));
        return hash_equals($hash, $signature);
    }

    // 受付返信（reply）
    public function replyText(string $replyToken, string $text): bool {
        return $this->apiPost('/message/reply', [
            'replyToken' => $replyToken,
            'messages'   => [['type' => 'text', 'text' => $text]],
        ]);
    }

    // 画像Push送信
    public function pushImages(string $lineUserId, string $headerText, array $imageUrls): bool {
        $messages = [['type' => 'text', 'text' => $headerText]];
        foreach ($imageUrls as $url) {
            $messages[] = [
                'type'             => 'image',
                'originalContentUrl' => $url,
                'previewImageUrl'    => $url,
            ];
        }
        // LINEは1回で最大5メッセージ
        foreach (array_chunk($messages, 5) as $chunk) {
            $result = $this->apiPost('/message/push', [
                'to'       => $lineUserId,
                'messages' => $chunk,
            ]);
            if (!$result) return false;
        }
        return true;
    }

    // テキストPush
    public function pushText(string $lineUserId, string $text): bool {
        return $this->apiPost('/message/push', [
            'to'       => $lineUserId,
            'messages' => [['type' => 'text', 'text' => $text]],
        ]);
    }

    // ユーザープロフィール取得
    public function getProfile(string $lineUserId): ?array {
        $ch = curl_init("{$this->apiBase}/profile/{$lineUserId}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$this->accessToken}"],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || !$res) return null;
        return json_decode($res, true);
    }

    // クイックリプライ付きテキスト返信
    public function replyWithQuickReply(string $replyToken, string $text, array $quickReplyItems): bool {
        return $this->apiPost('/message/reply', [
            'replyToken' => $replyToken,
            'messages'   => [[
                'type' => 'text',
                'text' => $text,
                'quickReply' => ['items' => $quickReplyItems],
            ]],
        ]);
    }

    // クイックリプライ付きテキストPush
    public function pushWithQuickReply(string $lineUserId, string $text, array $quickReplyItems): bool {
        return $this->apiPost('/message/push', [
            'to'       => $lineUserId,
            'messages' => [[
                'type' => 'text',
                'text' => $text,
                'quickReply' => ['items' => $quickReplyItems],
            ]],
        ]);
    }

    private function apiPost(string $endpoint, array $data): bool {
        $ch = curl_init("{$this->apiBase}{$endpoint}");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer {$this->accessToken}",
            ],
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_TIMEOUT        => 15,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $ok = ($code === 200);
        // push送信が成功したら当月カウンターを増やす（returnも課金対象だが集計はpushのみ）
        if ($ok && strpos($endpoint, '/message/push') === 0) {
            $this->incrementPushCount(count($data['messages'] ?? [1]));
        }
        return $ok;
    }

    // 当月のpush送信数をカウント（キー: line_push_count_YYYYMM）
    private function incrementPushCount(int $n = 1): void {
        try {
            $key = 'line_push_count_' . date('Ym');
            $current = (int) Settings::get($key, '0');
            Settings::set($key, (string)($current + $n));
        } catch (\Throwable $e) {
            // 集計失敗は無視
        }
    }
}
