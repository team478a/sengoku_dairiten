<?php
// app/Services/StripeService.php
// Stripe決済（Checkout Session / Webhook）。SDK不要、REST直叩き。

require_once BASE_PATH . '/config/settings.php';

class StripeService {
    private string $secretKey;
    private string $apiBase = 'https://api.stripe.com/v1';

    public function __construct(?string $secretKey = null) {
        // $secretKey が渡された場合はそちらを優先（テスト接続用）
        $this->secretKey = $secretKey ?? Settings::get('stripe_secret_key', '');
    }

    public function isConfigured(): bool {
        return $this->secretKey !== '';
    }

    // Checkout Session を作成し、決済URLを返す
    // metadata に attendance_id 等を入れてWebhookで突き合わせる
    public function createCheckout(int $amount, string $productName, array $metadata, string $successUrl, string $cancelUrl): ?array {
        if (!$this->isConfigured()) return null;

        $params = [
            'mode'        => 'payment',
            'success_url' => $successUrl,
            'cancel_url'  => $cancelUrl,
            'line_items[0][price_data][currency]'            => 'jpy',
            'line_items[0][price_data][product_data][name]'  => $productName,
            'line_items[0][price_data][unit_amount]'         => $amount,
            'line_items[0][quantity]'                        => 1,
        ];
        foreach ($metadata as $k => $v) {
            $params["metadata[{$k}]"] = $v;
        }

        $res = $this->post('/checkout/sessions', $params);
        if (!$res || empty($res['url'])) return null;
        return ['id' => $res['id'], 'url' => $res['url']];
    }

    // サブスク用Checkout Session（継続課金）
    // priceId は Stripeダッシュボードで作成した月額Price ID
    public function createSubscriptionCheckout(string $priceId, array $metadata, string $successUrl, string $cancelUrl): ?array {
        if (!$this->isConfigured()) return null;

        $params = [
            'mode'              => 'subscription',
            'success_url'       => $successUrl,
            'cancel_url'        => $cancelUrl,
            'line_items[0][price]'    => $priceId,
            'line_items[0][quantity]' => 1,
        ];
        foreach ($metadata as $k => $v) {
            $params["metadata[{$k}]"] = $v;
            $params["subscription_data[metadata][{$k}]"] = $v;
        }

        $res = $this->post('/checkout/sessions', $params);
        if (!$res || empty($res['url'])) return null;
        return ['id' => $res['id'], 'url' => $res['url']];
    }

    // サブスクを解約
    public function cancelSubscription(string $subscriptionId): bool {
        if (!$this->isConfigured()) return false;
        $ch = curl_init($this->apiBase . '/subscriptions/' . $subscriptionId);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $this->secretKey . ':',
            CURLOPT_TIMEOUT        => 15,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 300;
    }

    // 返金（セッションIDから支払いを特定して返金）
    public function refundBySession(string $sessionId): bool {
        if (!$this->isConfigured() || !$sessionId) return false;
        // セッションから payment_intent を取得
        $session = $this->get('/checkout/sessions/' . $sessionId);
        $pi = $session['payment_intent'] ?? '';
        if (!$pi) return false;
        $res = $this->post('/refunds', ['payment_intent' => $pi]);
        return $res !== null && !empty($res['id']);
    }

    // 接続テスト（アカウント情報取得）
    public function testConnection(): array {
        if (!$this->secretKey) return ['ok' => false, 'message' => 'シークレットキーを入力してください'];
        $res = $this->get('/account');
        if ($res && !empty($res['id'])) {
            $name = $res['settings']['dashboard']['display_name'] ?? $res['id'];
            return ['ok' => true, 'message' => "✓ Stripe接続成功！（{$name}）"];
        }
        return ['ok' => false, 'message' => '✗ 接続失敗：シークレットキーを確認してください'];
    }

    // Webhook署名を検証してイベントを返す
    public function verifyWebhook(string $payload, string $sigHeader): ?array {
        $secret = Settings::get('stripe_webhook_secret', '');
        if (!$secret) {
            // 署名シークレット未設定時は拒否（セキュリティ上必須）
            error_log('[StripeService] stripe_webhook_secret が未設定です。Webhookを拒否しました。');
            return null;
        }

        // Stripe-Signature: t=...,v1=...
        $parts = [];
        foreach (explode(',', $sigHeader) as $p) {
            $kv = explode('=', $p, 2);
            if (count($kv) === 2) $parts[trim($kv[0])] = trim($kv[1]);
        }
        $timestamp = $parts['t'] ?? '';
        $sig       = $parts['v1'] ?? '';
        if (!$timestamp || !$sig) return null;

        $signedPayload = $timestamp . '.' . $payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);
        if (!hash_equals($expected, $sig)) return null;

        return json_decode($payload, true) ?: null;
    }

    private function post(string $path, array $params): ?array {
        $ch = curl_init($this->apiBase . $path);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $this->secretKey . ':',
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_TIMEOUT        => 20,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($res, true);
        if ($code >= 200 && $code < 300) return $data;
        return null;
    }

    private function get(string $path): ?array {
        $ch = curl_init($this->apiBase . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $this->secretKey . ':',
            CURLOPT_TIMEOUT        => 15,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) return json_decode($res, true);
        return null;
    }
}
