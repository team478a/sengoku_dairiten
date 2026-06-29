<?php
// app/Services/ImageGenerationService.php
// 画像生成エンジン切り替え対応（Stability AI / Grok）

require_once BASE_PATH . '/config/settings.php';

class ImageGenerationService {

    private string $engine;

    public function __construct() {
        // image_engine: 'stability'（デフォルト）or 'grok'
        $this->engine = Settings::get('image_engine', 'stability');
    }

    /**
     * プロンプトから画像を生成し、バイナリデータの配列を返す
     * @return array ['data' => string (binary), 'ext' => 'png'][]
     */
    public function generate(string $promptEn, int $count = 4, string $stylePreset = 'anime'): array {
        if ($this->engine === 'grok') {
            return $this->generateWithGrok($promptEn, $count);
        }
        return $this->generateWithStability($promptEn, $count, $stylePreset);
    }

    // ===== Stability AI =====
    private function generateWithStability(string $promptEn, int $count, string $stylePreset): array {
        $apiKey = Settings::stabilityApiKey();
        if (!$apiKey) {
            throw new \RuntimeException('Stability AIのAPIキーが設定されていません');
        }

        // モデル選択（sdxl=低コスト / core=高品質 / ultra=最高品質）
        $model = Settings::get('stability_model', 'sdxl');

        // アスペクト比（square / portrait / landscape）
        $aspect = Settings::get('image_aspect', 'square');
        [$w, $h, $arRatio] = $this->aspectDimensions($aspect);

        // 品質パラメータ
        $steps    = (int) Settings::get('image_steps', '30');
        $cfgScale = (float) Settings::get('image_cfg', '7');
        if ($steps < 20) $steps = 20;
        if ($steps > 50) $steps = 50;

        // 強化ネガティブプロンプト
        $negative = $this->negativePrompt();

        // Core / Ultra は新しいv2beta APIを使用
        if ($model === 'core' || $model === 'ultra') {
            return $this->generateWithStabilityV2($apiKey, $promptEn, $count, $model, $arRatio, $negative);
        }

        // SDXL（v1 API）
        $apiUrl = 'https://api.stability.ai/v1/generation/stable-diffusion-xl-1024-v1-0/text-to-image';
        $body = json_encode([
            'text_prompts' => [
                ['text' => $promptEn, 'weight' => 1],
                ['text' => $negative, 'weight' => -1],
            ],
            'cfg_scale'    => $cfgScale,
            'height'       => $h,
            'width'        => $w,
            'samples'      => $count,
            'steps'        => $steps,
            'style_preset' => $stylePreset,
        ]);

        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                "Authorization: Bearer {$apiKey}",
            ],
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 120,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new \RuntimeException("Stability AI curl error: {$err}");
        }
        if ($code !== 200) {
            $errBody = json_decode($res, true);
            $msg = $errBody['message'] ?? $res;
            throw new \RuntimeException("Stability AI HTTP {$code}: {$msg}");
        }

        $data = json_decode($res, true);
        $images = [];
        foreach (($data['artifacts'] ?? []) as $artifact) {
            if (($artifact['finishReason'] ?? '') === 'SUCCESS') {
                $images[] = ['data' => base64_decode($artifact['base64']), 'ext' => 'png'];
            }
        }
        if (empty($images)) {
            throw new \RuntimeException("画像が生成されませんでした（Stability）");
        }
        return $images;
    }

    // Stable Image Core / Ultra（v2beta API、1リクエスト1枚）
    private function generateWithStabilityV2(string $apiKey, string $promptEn, int $count, string $model, string $aspectRatio, string $negative): array {
        $endpoint = $model === 'ultra'
            ? 'https://api.stability.ai/v2beta/stable-image/generate/ultra'
            : 'https://api.stability.ai/v2beta/stable-image/generate/core';

        $images = [];
        for ($i = 0; $i < $count; $i++) {
            $ch = curl_init($endpoint);
            $post = [
                'prompt'          => $promptEn,
                'negative_prompt' => $negative,
                'aspect_ratio'    => $aspectRatio,
                'output_format'   => 'png',
            ];
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Accept: image/*',
                    "Authorization: Bearer {$apiKey}",
                ],
                CURLOPT_POSTFIELDS     => $post,
                CURLOPT_TIMEOUT        => 120,
            ]);
            $res  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code === 200 && $res) {
                $images[] = ['data' => $res, 'ext' => 'png'];
            }
        }
        if (empty($images)) {
            throw new \RuntimeException("画像が生成されませんでした（Stability {$model}）");
        }
        return $images;
    }

    // アスペクト比に応じた寸法と比率文字列
    private function aspectDimensions(string $aspect): array {
        switch ($aspect) {
            case 'portrait':  return [832, 1216, '2:3'];
            case 'landscape': return [1216, 832, '3:2'];
            default:          return [1024, 1024, '1:1'];
        }
    }

    // 強化ネガティブプロンプト
    private function negativePrompt(): string {
        return 'blurry, low quality, worst quality, bad quality, ugly, deformed, disfigured, '
            . 'bad anatomy, bad proportions, extra limbs, extra fingers, missing fingers, '
            . 'mutated hands, poorly drawn hands, poorly drawn face, malformed limbs, '
            . 'watermark, text, signature, username, jpeg artifacts, lowres, cropped, '
            . 'out of frame, duplicate, error';
    }

    // ===== Grok（xAI / grok-imagine-image）=====
    private function generateWithGrok(string $promptEn, int $count): array {
        $apiKey = Settings::get('grok_api_key', '');
        if (!$apiKey) {
            throw new \RuntimeException('GrokのAPIキーが設定されていません');
        }
        // モデル: grok-imagine-image（標準）/ grok-imagine-image-pro（高品質）
        $model = Settings::get('grok_image_model', 'grok-imagine-image');
        $apiUrl = 'https://api.x.ai/v1/images/generations';

        // xAIはOpenAI互換。nで枚数指定（上限がある場合はループ）
        $images = [];
        $remaining = $count;
        $safety = 0;

        while ($remaining > 0 && $safety < 8) {
            $batch = min($remaining, 4); // 念のため1リクエスト最大4枚
            $body = json_encode([
                'model'           => $model,
                'prompt'          => $promptEn,
                'n'               => $batch,
                'response_format' => 'b64_json',
            ]);

            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    "Authorization: Bearer {$apiKey}",
                ],
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_TIMEOUT        => 120,
            ]);
            $res  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($err) {
                throw new \RuntimeException("Grok curl error: {$err}");
            }
            if ($code !== 200) {
                $errBody = json_decode($res, true);
                $msg = $errBody['error']['message'] ?? ($errBody['error'] ?? $res);
                if (is_array($msg)) $msg = json_encode($msg);
                throw new \RuntimeException("Grok HTTP {$code}: {$msg}");
            }

            $data = json_decode($res, true);
            foreach (($data['data'] ?? []) as $item) {
                if (!empty($item['b64_json'])) {
                    $images[] = ['data' => base64_decode($item['b64_json']), 'ext' => 'png'];
                } elseif (!empty($item['url'])) {
                    // URL形式で返ってきた場合はダウンロード
                    $bin = @file_get_contents($item['url']);
                    if ($bin !== false) {
                        $images[] = ['data' => $bin, 'ext' => 'png'];
                    }
                }
            }

            $remaining -= $batch;
            $safety++;
        }

        if (empty($images)) {
            throw new \RuntimeException("画像が生成されませんでした（Grok）");
        }
        return $images;
    }
}
