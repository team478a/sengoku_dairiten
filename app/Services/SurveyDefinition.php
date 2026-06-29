<?php
// app/Services/SurveyDefinition.php
// アンケートの質問・選択肢を一元管理

class SurveyDefinition {

    // ステップ定義
    const STEP_IDLE    = 'idle';
    const STEP_MODE    = 'q_mode';
    const STEP_FREE    = 'q_free';
    const STEP_STYLE   = 'q_style';
    const STEP_MOOD    = 'q_mood';
    const STEP_KEYWORD = 'q_keyword';

    // ステップの順序
    const FLOW = [
        self::STEP_IDLE    => self::STEP_STYLE,
        self::STEP_STYLE   => self::STEP_MOOD,
        self::STEP_MOOD    => self::STEP_KEYWORD,
        self::STEP_KEYWORD => self::STEP_IDLE,
    ];

    // Q1: 画風
    const STYLES = [
        'anime'       => '🎨 アニメ・イラスト',
        'watercolor'  => '🖌️ 水彩・手描き風',
        'realistic'   => '📷 リアル・写実',
        'japanese'    => '🏯 和風・浮世絵',
        'any_style'   => '✨ おまかせ',
    ];

    // Q2: 雰囲気
    const MOODS = [
        'fantasy'     => '🌙 幻想的・神秘的',
        'cute'        => '🌸 かわいい・ほのぼの',
        'cool'        => '❄️ クール・スタイリッシュ',
        'dark'        => '🌑 ダーク・幻想',
        'warm'        => '☀️ 温かい・懐かしい',
        'any_mood'    => '✨ おまかせ',
    ];

    // 画風 → Stability AI style_preset のマッピング
    const STABILITY_PRESET = [
        'anime'      => 'anime',
        'watercolor' => 'watercolor',
        'realistic'  => 'photographic',
        'japanese'   => 'japanese-art',
        'any_style'  => 'enhance',
    ];

    // 画風 → プロンプト補完テキスト（英語）
    const STYLE_PROMPT = [
        'anime'      => 'anime style, cel shading, vibrant colors, clean lines',
        'watercolor' => 'watercolor painting, soft edges, flowing colors, hand-painted',
        'realistic'  => 'photorealistic, highly detailed, professional photography',
        'japanese'   => 'japanese art style, ukiyo-e inspired, traditional ink painting',
        'any_style'  => 'digital art, high quality',
    ];

    // 雰囲気 → プロンプト補完テキスト（英語）
    const MOOD_PROMPT = [
        'fantasy'    => 'magical atmosphere, mystical light, ethereal, dreamlike',
        'cute'       => 'cute, cheerful, pastel colors, soft lighting, heartwarming',
        'cool'       => 'cool, stylish, sleek design, cinematic lighting',
        'dark'       => 'dark fantasy, mysterious shadows, dramatic contrast, moody',
        'warm'       => 'warm sunlight, nostalgic, cozy atmosphere, golden hour',
        'any_mood'   => 'beautiful, expressive, atmospheric',
    ];

    public static function styleLabel(string $key): string {
        return self::STYLES[$key] ?? $key;
    }

    public static function moodLabel(string $key): string {
        return self::MOODS[$key] ?? $key;
    }

    public static function stabilityPreset(string $styleKey): string {
        return self::STABILITY_PRESET[$styleKey] ?? 'enhance';
    }

    public static function buildPromptContext(string $styleKey, string $moodKey): string {
        $styleHint = self::STYLE_PROMPT[$styleKey] ?? '';
        $moodHint  = self::MOOD_PROMPT[$moodKey]   ?? '';
        return trim("{$styleHint}, {$moodHint}", ', ');
    }

    // クイックリプライアイテムを生成
    public static function quickReplyItems(array $choices): array {
        $items = [];
        foreach ($choices as $value => $label) {
            $items[] = [
                'type'   => 'action',
                'action' => [
                    'type'  => 'message',
                    'label' => mb_substr($label, 0, 20), // LINEの上限
                    'text'  => $label,
                ],
            ];
        }
        return $items;
    }
}
