<?php
// app/Services/ContentFilter.php
// 生成入力のNGワードチェック

require_once BASE_PATH . '/config/settings.php';

class ContentFilter {
    // 既定のNGワード（性的・暴力・違法など最低限）
    private static array $defaultNg = [
        // 性的
        'セックス','ヌード','全裸','裸体','エロ','ポルノ','性器','陰部','naked','nude','nsfw','sex','porn',
        // 暴力・グロ
        '殺害','死体','グロ','流血','虐待','gore','blood',
        // 違法・危険
        '児童ポルノ','幼児','ロリ','loli','child porn',
    ];

    // チェック：問題があれば該当ワードを返す、なければ null
    public static function check(string $text): ?string {
        $lower = mb_strtolower($text);

        // 管理者が追加したNGワード
        $custom = Settings::get('ng_words', '');
        $ngList = self::$defaultNg;
        if ($custom) {
            foreach (preg_split('/[\s,、\n]+/u', $custom) as $w) {
                $w = trim($w);
                if ($w !== '') $ngList[] = mb_strtolower($w);
            }
        }

        foreach ($ngList as $ng) {
            if ($ng !== '' && mb_strpos($lower, $ng) !== false) {
                return $ng;
            }
        }
        return null;
    }

    // 安全な入力か
    public static function isSafe(string $text): bool {
        return self::check($text) === null;
    }
}
