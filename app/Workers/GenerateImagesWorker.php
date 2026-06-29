<?php
// app/Workers/GenerateImagesWorker.php

require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/settings.php';
require_once BASE_PATH . '/app/Services/Logger.php';
require_once BASE_PATH . '/app/Services/PromptService.php';
require_once BASE_PATH . '/app/Services/ImageGenerationService.php';
require_once BASE_PATH . '/app/Services/StorageService.php';
require_once BASE_PATH . '/app/Services/LineService.php';

class GenerateImagesWorker {
    private PDO $pdo;
    private PromptService $promptSvc;
    private ImageGenerationService $imageSvc;
    private StorageService $storageSvc;
    private LineService $lineSvc;

    public function __construct() {
        $this->pdo        = get_pdo();
        $this->promptSvc  = new PromptService();
        $this->imageSvc   = new ImageGenerationService();
        $this->storageSvc = new StorageService();
        $this->lineSvc    = new LineService();
    }

    public function run(): void {
        // 固まったジョブの自動復旧：5分以上 processing のままのジョブを pending に戻す
        // （API遅延・タイムアウト等で取り残されたジョブを次回処理で拾えるようにする）
        $this->pdo->prepare("
            UPDATE job_queue
            SET status = 'pending', available_at = NOW(),
                error_message = CONCAT(COALESCE(error_message,''), ' [自動復旧]'),
                updated_at = NOW()
            WHERE status = 'processing'
              AND updated_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ")->execute();

        // pending ジョブを1件取得してロック
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM job_queue
                WHERE status = 'pending' AND available_at <= NOW()
                ORDER BY id ASC
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute();
            $job = $stmt->fetch();

            if (!$job) {
                $this->pdo->rollBack();
                return;
            }

            // ステータスを processing に
            $this->pdo->prepare("UPDATE job_queue SET status = 'processing', updated_at = NOW() WHERE id = ?")
                ->execute([$job['id']]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $requestId = (int) $job['request_id'];
        try {
            $this->process($requestId, (int) $job['id']);
            // ジョブ完了
            $this->pdo->prepare("UPDATE job_queue SET status = 'completed', updated_at = NOW() WHERE id = ?")
                ->execute([$job['id']]);
        } catch (\Throwable $e) {
            $retry = (int) $job['retry_count'] + 1;
            Logger::error('worker', "ジョブ失敗 request_id={$requestId}: " . $e->getMessage(), $requestId);

            if ($retry <= 2) {
                // リトライ（30秒後）
                $this->pdo->prepare("
                    UPDATE job_queue
                    SET status = 'pending', retry_count = ?, error_message = ?, available_at = DATE_ADD(NOW(), INTERVAL 30 SECOND), updated_at = NOW()
                    WHERE id = ?
                ")->execute([$retry, $e->getMessage(), $job['id']]);
            } else {
                // 全失敗
                $this->pdo->prepare("UPDATE job_queue SET status = 'failed', error_message = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$e->getMessage(), $job['id']]);
                $this->updateRequestStatus($requestId, 'failed', $e->getMessage());
                // 管理者へ失敗通知
                require_once BASE_PATH . '/app/Services/AdminNotifier.php';
                AdminNotifier::notify('failure', "画像生成が失敗しました（依頼ID: {$requestId}）。\nエラー: " . mb_substr($e->getMessage(), 0, 100));
                // 受講者に失敗通知
                $req = $this->getRequest($requestId);
                if ($req) {
                    $this->lineSvc->pushText($req['line_user_id'],
                        "画像生成中にエラーが発生しました。\n時間をおいて再度お試しください。\n必要に応じて運営側で確認します。"
                    );
                }
            }
        }
    }

    private function process(int $requestId, int $jobId): void {
        $req = $this->getRequest($requestId);
        if (!$req) throw new \RuntimeException("request not found: {$requestId}");

        // 1. analyzing
        $this->updateRequestStatus($requestId, 'analyzing');
        Logger::info('worker', "プロンプト生成開始", $requestId);

        // 2. プロンプト生成（アンケートデータがあれば渡す）
        require_once BASE_PATH . '/app/Services/SurveyDefinition.php';
        $surveyContext = [];
        if (!empty($req['survey_style']) || !empty($req['survey_mood'])) {
            $styleKey = $req['survey_style'] ?? 'any_style';
            $moodKey  = $req['survey_mood']  ?? 'any_mood';
            $surveyContext = [
                'style_prompt'     => SurveyDefinition::STYLE_PROMPT[$styleKey] ?? '',
                'mood_prompt'      => SurveyDefinition::MOOD_PROMPT[$moodKey]   ?? '',
                'stability_preset' => SurveyDefinition::stabilityPreset($styleKey),
            ];
        }
        $promptData = $this->promptSvc->generate($req['input_text'], $surveyContext);

        // 3. プロンプト保存
        $promptAId = $this->savePrompt($requestId, 'A', $promptData);
        $promptBId = $this->savePrompt($requestId, 'B', $promptData);

        // input_type を更新
        $this->pdo->prepare("UPDATE image_requests SET input_type = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$promptData['input_type'] ?? 'simple_keywords', $requestId]);

        $this->updateRequestStatus($requestId, 'generating');
        Logger::info('worker', "画像生成開始", $requestId);

        // 4. 画像生成（A）
        // 生成枚数は設定で変更可能（通数・コスト削減用）。デフォルト4枚
        $perPattern = (int) Settings::get('images_per_pattern', '4');
        if ($perPattern < 1) $perPattern = 1;
        if ($perPattern > 4) $perPattern = 4;

        $stabilityPreset = $surveyContext['stability_preset'] ?? 'enhance';
        $imagesA = $this->generateWithRetry($promptData['prompt_a_en'], $perPattern, $stabilityPreset);

        // 5. 画像生成（B）
        $imagesB = $this->generateWithRetry($promptData['prompt_b_en'], $perPattern, $stabilityPreset);

        $this->updateRequestStatus($requestId, 'uploading');

        // 6. 保存
        $urlsA = $this->saveImages($requestId, $promptAId, 'A', $imagesA);
        $urlsB = $this->saveImages($requestId, $promptBId, 'B', $imagesB);

        $this->updateRequestStatus($requestId, 'sending');
        Logger::info('worker', "LINE送信開始", $requestId);

        // 7. LINE送信
        $lineUserId = $req['line_user_id'];
        $titleA = $promptData['prompt_a_title_ja'] ?? 'Aパターン';
        $titleB = $promptData['prompt_b_title_ja'] ?? 'Bパターン';

        // グリッド送信モード：複数枚を1枚にまとめてLINE通数を削減
        $gridMode = Settings::get('line_grid_mode', '0') === '1';

        if ($gridMode && count($urlsA) > 1) {
            // A/Bそれぞれを1枚のグリッド画像にまとめる
            $gridA = $this->makeGrid($requestId, 'A', $imagesA);
            $gridB = $this->makeGrid($requestId, 'B', $imagesB);
            if ($gridA && $gridB) {
                $cnt = count($urlsA);
                $this->lineSvc->pushImages($lineUserId, "画像が完成しました🎨\n【{$titleA}】（{$cnt}枚を1枚にまとめています）", [$gridA]);
                sleep(1);
                $this->lineSvc->pushImages($lineUserId, "【{$titleB}】（{$cnt}枚）", [$gridB]);
            } else {
                // グリッド失敗時は通常送信にフォールバック
                $this->lineSvc->pushImages($lineUserId, "画像が完成しました。\nまずは【{$titleA}】です。", $urlsA);
                sleep(1);
                $this->lineSvc->pushImages($lineUserId, "続いて【{$titleB}】です。", $urlsB);
            }
        } else {
            $cntA = count($urlsA);
            $cntB = count($urlsB);
            $this->lineSvc->pushImages($lineUserId, "画像が完成しました。\nまずは【{$titleA}】{$cntA}枚です。", $urlsA);
            sleep(1);
            $this->lineSvc->pushImages($lineUserId, "続いて【{$titleB}】{$cntB}枚です。", $urlsB);
        }

        // 8. 完了
        $this->updateRequestStatus($requestId, 'completed');

        // 「もう一回」案内
        $this->lineSvc->pushWithQuickReply($lineUserId,
            "気に入った作品はありましたか？😊\n別のパターンが欲しい場合は「もう一回」、新しく作る場合は「生成する」をどうぞ🎨",
            [
                ['type'=>'action','action'=>['type'=>'message','label'=>'🔄 もう一回','text'=>'もう一回']],
                ['type'=>'action','action'=>['type'=>'message','label'=>'✨ 新しく生成','text'=>'生成する']],
            ]
        );

        Logger::info('worker', "完了", $requestId);
    }

    private function generateWithRetry(string $prompt, int $count, string $preset = 'enhance'): array {
        $lastErr = null;
        for ($i = 0; $i < 3; $i++) {
            try {
                return $this->imageSvc->generate($prompt, $count, $preset);
            } catch (\Throwable $e) {
                $lastErr = $e;
                sleep(5);
            }
        }
        throw $lastErr;
    }

    // 複数画像を1枚のグリッドにまとめて保存し、URLを返す
    // 枚数に応じて 1x2 / 2x2 のレイアウト
    private function makeGrid(int $requestId, string $type, array $images): ?string {
        try {
            $n = count($images);
            if ($n === 0) return null;
            if ($n === 1) {
                // 1枚ならそのまま保存
                $path = "images/{$requestId}/grid_" . strtolower($type) . ".png";
                return $this->storageSvc->save($images[0]['data'], $path);
            }

            // レイアウト決定
            $cols = ($n <= 2) ? $n : 2;
            $rows = (int)ceil($n / $cols);
            $cell = 512; // 各画像のセルサイズ
            $gap  = 8;
            $W = $cols * $cell + ($cols + 1) * $gap;
            $H = $rows * $cell + ($rows + 1) * $gap;

            if (extension_loaded('imagick')) {
                $canvas = new \Imagick();
                $canvas->newImage($W, $H, new \ImagickPixel('#1a202c'));
                $canvas->setImageFormat('png');
                foreach ($images as $i => $img) {
                    $im = new \Imagick();
                    $im->readImageBlob($img['data']);
                    $im->thumbnailImage($cell, $cell, true, true); // アスペクト維持＋余白
                    $col = $i % $cols;
                    $row = intdiv($i, $cols);
                    $x = $gap + $col * ($cell + $gap);
                    $y = $gap + $row * ($cell + $gap);
                    $canvas->compositeImage($im, \Imagick::COMPOSITE_OVER, $x, $y);
                    $im->destroy();
                }
                $blob = $canvas->getImageBlob();
                $canvas->destroy();
                $path = "images/{$requestId}/grid_" . strtolower($type) . ".png";
                return $this->storageSvc->save($blob, $path);
            }

            // GDフォールバック
            if (function_exists('imagecreatetruecolor')) {
                $canvas = imagecreatetruecolor($W, $H);
                $bg = imagecolorallocate($canvas, 26, 32, 44);
                imagefill($canvas, 0, 0, $bg);
                foreach ($images as $i => $img) {
                    $src = imagecreatefromstring($img['data']);
                    if (!$src) continue;
                    $sw = imagesx($src); $sh = imagesy($src);
                    $col = $i % $cols;
                    $row = intdiv($i, $cols);
                    $x = $gap + $col * ($cell + $gap);
                    $y = $gap + $row * ($cell + $gap);
                    imagecopyresampled($canvas, $src, $x, $y, 0, 0, $cell, $cell, $sw, $sh);
                    imagedestroy($src);
                }
                ob_start();
                imagepng($canvas);
                $blob = ob_get_clean();
                imagedestroy($canvas);
                $path = "images/{$requestId}/grid_" . strtolower($type) . ".png";
                return $this->storageSvc->save($blob, $path);
            }

            return null;
        } catch (\Throwable $e) {
            Logger::error('worker', "グリッド作成失敗: " . $e->getMessage(), $requestId);
            return null;
        }
    }

    private function saveImages(int $requestId, int $promptId, string $type, array $images): array {
        $urls = [];
        foreach ($images as $i => $img) {
            $no   = $i + 1;
            $path = "images/{$requestId}/" . strtolower($type) . "_{$no}.{$img['ext']}";
            $url  = $this->storageSvc->save($img['data'], $path);

            $this->pdo->prepare("
                INSERT INTO generated_images (request_id, prompt_id, prompt_type, image_no, image_url, preview_url, storage_path, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
            ")->execute([$requestId, $promptId, $type, $no, $url, $url, $path]);

            $urls[] = $url;
        }
        return $urls;
    }

    private function savePrompt(int $requestId, string $type, array $data): int {
        $key = strtolower($type);
        $stmt = $this->pdo->prepare("
            INSERT INTO prompts (request_id, prompt_type, title_ja, input_summary_ja, prompt_en, safety_notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $requestId,
            $type,
            $data["prompt_{$key}_title_ja"] ?? '',
            $data['input_summary_ja'] ?? '',
            $data["prompt_{$key}_en"] ?? '',
            $data['safety_notes'] ?? '',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    private function updateRequestStatus(int $id, string $status, ?string $error = null): void {
        $this->pdo->prepare("
            UPDATE image_requests SET status = ?, error_message = ?, updated_at = NOW() WHERE id = ?
        ")->execute([$status, $error, $id]);
    }

    private function getRequest(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM image_requests WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
