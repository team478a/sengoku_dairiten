<?php
// app/Services/PromptService.php

require_once BASE_PATH . '/config/settings.php';

class PromptService {
    private string $apiKey;
    private string $model;

    private string $apiUrl = 'https://api.anthropic.com/v1/messages';

    public function __construct() {
        $this->apiKey = Settings::claudeApiKey();
        // プロンプト生成モデルを設定で選択（haiku=最安 / sonnet=高品質）
        $choice = Settings::get('prompt_model', 'haiku');
        $this->model = ($choice === 'sonnet') ? 'claude-sonnet-4-6' : 'claude-haiku-4-5-20251001';
    }

    public function generate(string $inputText, array $surveyContext = []): ?array {
        $styleHint = $surveyContext['style_prompt'] ?? '';
        $moodHint  = $surveyContext['mood_prompt']  ?? '';
        $contextNote = '';
        if ($styleHint || $moodHint) {
            $contextNote = "\n\n受講者が選んだスタイル設定（必ず反映すること）：\n" .
                ($styleHint ? "- 画風: {$styleHint}\n" : '') .
                ($moodHint  ? "- 雰囲気: {$moodHint}\n"  : '');
        }

        $systemPrompt = <<<PROMPT
あなたはAIアート教室の画像生成プロンプト作成アシスタントです。
受講者から送られた日本語のキーワードまたは文章をもとに、画像生成AIで美しい画像を作りやすい英語プロンプトを作成してください。

要件：
- 画像は生成しない
- 英語の画像生成プロンプトのみ作成する
- 1つの入力からPrompt AとPrompt Bの2案を作る
- Prompt Aは王道でまとまりの良い案（失敗しにくい、主題が明確）
- Prompt Bは少し演出と世界観を強めた案（幻想感・物語性・光を豊かに）
- 受講者が選んだ画風・雰囲気の指定がある場合は、それを最優先でプロンプトに組み込む
- 短いキーワードの場合は、背景、構図、光、色味、雰囲気を補完する
- 各プロンプトには必ず画質を高める表現を含める（例：highly detailed, sharp focus, professional lighting, high resolution, intricate details, masterpiece quality）
- 人物を描く場合は、自然な顔・手・プロポーションになるよう anatomically correct, detailed face, natural pose などを補う
- 構図の指定（rule of thirds, depth of field, cinematic composition など）を適切に加える
- 固有の作家名、スタジオ名、作品名は使わず、特徴表現に置き換える
- 不適切、危険、権利侵害リスクが高い内容は避ける
- 出力はJSON形式のみ（前後にテキスト不要）{$contextNote}

出力形式（JSONのみ）：
{
  "input_summary_ja": "受講者入力の解釈を日本語で簡潔に記載",
  "input_type": "survey または simple_keywords または free_text",
  "prompt_a_title_ja": "A案の日本語タイトル",
  "prompt_a_en": "英語の画像生成プロンプトA（詳細で具体的に）",
  "prompt_b_title_ja": "B案の日本語タイトル",
  "prompt_b_en": "英語の画像生成プロンプトB（演出・世界観強め）",
  "safety_notes": "問題なければ空文字"
}
PROMPT;

        $body = json_encode([
            'model'      => $this->model,
            'max_tokens' => 1500,
            'system'     => $systemPrompt,
            'messages'   => [
                ['role' => 'user', 'content' => $inputText],
            ],
        ]);

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "x-api-key: {$this->apiKey}",
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 60,
        ]);

        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || $code !== 200) {
            throw new \RuntimeException("Claude API error: HTTP {$code} / {$err}");
        }

        $data = json_decode($res, true);
        $text = $data['content'][0]['text'] ?? '';

        // JSON部分だけ抽出
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $parsed = json_decode($m[0], true);
            if ($parsed) return $parsed;
        }

        throw new \RuntimeException("プロンプト生成レスポンスのパースに失敗: " . substr($text, 0, 200));
    }
}
