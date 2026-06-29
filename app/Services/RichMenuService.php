<?php
// app/Services/RichMenuService.php
// LINEリッチメニューの作成・画像アップロード・反映

require_once BASE_PATH . '/config/settings.php';

class RichMenuService {
    private string $accessToken;
    private string $apiBase = 'https://api.line.me/v2/bot';
    private string $dataApiBase = 'https://api-data.line.me/v2/bot';

    public function __construct() {
        $this->accessToken = Settings::lineAccessToken();
    }

    // リッチメニューを作成（6ボタン 2x3グリッド）
    // 画像サイズ: 2500x1686
    public function createRichMenu(array $buttons): string {
        // 2x3グリッド: 各セル 833x843
        $cellW = 833;
        $cellH = 843;
        $areas = [];
        for ($row = 0; $row < 2; $row++) {
            for ($col = 0; $col < 3; $col++) {
                $idx = $row * 3 + $col;
                $btn = $buttons[$idx] ?? null;
                if (!$btn) continue;
                $x = $col * $cellW;
                $y = $row * $cellH;
                // 端のセルは余り幅を吸収
                $w = ($col === 2) ? (2500 - $x) : $cellW;
                $h = ($row === 1) ? (1686 - $y) : $cellH;

                // アクションタイプ: url なら URI、それ以外は message
                if (($btn['action'] ?? 'message') === 'url' && !empty($btn['url'])) {
                    $action = ['type' => 'uri', 'uri' => $btn['url']];
                } else {
                    $action = ['type' => 'message', 'text' => $btn['text']];
                }

                $areas[] = [
                    'bounds' => ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h],
                    'action' => $action,
                ];
            }
        }

        $body = json_encode([
            'size'        => ['width' => 2500, 'height' => 1686],
            'selected'    => true,
            'name'        => 'AIアート教室メニュー',
            'chatBarText' => 'メニュー',
            'areas'       => $areas,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init("{$this->apiBase}/richmenu");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer {$this->accessToken}",
            ],
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            $err = json_decode($res, true);
            throw new \RuntimeException("リッチメニュー作成失敗: " . ($err['message'] ?? "HTTP {$code}"));
        }
        $data = json_decode($res, true);
        return $data['richMenuId'];
    }

    // 画像をアップロード
    public function uploadImage(string $richMenuId, string $imagePath): void {
        $imageData = file_get_contents($imagePath);
        $mime = (strtolower(pathinfo($imagePath, PATHINFO_EXTENSION)) === 'png') ? 'image/png' : 'image/jpeg';

        $ch = curl_init("{$this->dataApiBase}/richmenu/{$richMenuId}/content");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Content-Type: {$mime}",
                "Authorization: Bearer {$this->accessToken}",
            ],
            CURLOPT_POSTFIELDS     => $imageData,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            $err = json_decode($res, true);
            throw new \RuntimeException("画像アップロード失敗: " . ($err['message'] ?? "HTTP {$code}"));
        }
    }

    // デフォルトメニューに設定（全ユーザーに表示）
    public function setDefault(string $richMenuId): void {
        $ch = curl_init("{$this->apiBase}/user/all/richmenu/{$richMenuId}");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$this->accessToken}"],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) {
            throw new \RuntimeException("デフォルト設定失敗: HTTP {$code}");
        }
    }

    // 既存のリッチメニュー一覧
    public function listRichMenus(): array {
        $ch = curl_init("{$this->apiBase}/richmenu/list");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$this->accessToken}"],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) return [];
        $data = json_decode($res, true);
        return $data['richmenus'] ?? [];
    }

    // リッチメニュー削除
    public function deleteRichMenu(string $richMenuId): void {
        $ch = curl_init("{$this->apiBase}/richmenu/{$richMenuId}");
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$this->accessToken}"],
            CURLOPT_TIMEOUT        => 15,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    // 全削除
    public function deleteAll(): void {
        foreach ($this->listRichMenus() as $menu) {
            $this->deleteRichMenu($menu['richMenuId']);
        }
    }

    // 自動生成画像（SVG→PNG）で6ボタンメニュー画像を作る
    public function generateImage(array $buttons, string $outputPath): void {
        $W = 2500; $H = 1686;
        $cellW = 833; $cellH = 843;
        $colors = ['#7c6af7', '#a78bfa', '#60a5fa', '#34d399', '#fbbf24', '#f87171'];

        $svg = '<?xml version="1.0" encoding="UTF-8"?>';
        $svg .= '<svg xmlns="http://www.w3.org/2000/svg" width="' . $W . '" height="' . $H . '" viewBox="0 0 ' . $W . ' ' . $H . '">';
        $svg .= '<rect width="' . $W . '" height="' . $H . '" fill="#1a202c"/>';

        for ($row = 0; $row < 2; $row++) {
            for ($col = 0; $col < 3; $col++) {
                $idx = $row * 3 + $col;
                $btn = $buttons[$idx] ?? ['label' => '', 'icon' => ''];
                $x = $col * $cellW;
                $y = $row * $cellH;
                $w = ($col === 2) ? ($W - $x) : $cellW;
                $h = ($row === 1) ? ($H - $y) : $cellH;

                $color = $colors[$idx % 6];
                $cx = $x + $w / 2;
                $cy = $y + $h / 2;

                // セル
                $svg .= '<rect x="' . ($x + 12) . '" y="' . ($y + 12) . '" width="' . ($w - 24) . '" height="' . ($h - 24) . '" rx="24" fill="' . $color . '"/>';
                // アイコン（絵文字）
                $icon = htmlspecialchars($btn['icon'] ?? '', ENT_XML1);
                $svg .= '<text x="' . $cx . '" y="' . ($cy - 40) . '" font-size="160" text-anchor="middle" dominant-baseline="middle">' . $icon . '</text>';
                // ラベル
                $label = htmlspecialchars($btn['label'] ?? '', ENT_XML1);
                $svg .= '<text x="' . $cx . '" y="' . ($cy + 120) . '" font-size="90" font-weight="bold" fill="#ffffff" text-anchor="middle" dominant-baseline="middle" font-family="sans-serif">' . $label . '</text>';
            }
        }
        $svg .= '</svg>';

        // SVGをPNGに変換（Imagick優先、なければSVGファイルとして保存しエラー）
        if (extension_loaded('imagick')) {
            $im = new \Imagick();
            $im->setBackgroundColor(new \ImagickPixel('#1a202c'));
            $im->readImageBlob($svg);
            $im->setImageFormat('png');
            $im->writeImage($outputPath);
            $im->destroy();
        } else {
            throw new \RuntimeException('Imagick拡張が必要です。画像を手動でアップロードしてください。');
        }
    }
}
