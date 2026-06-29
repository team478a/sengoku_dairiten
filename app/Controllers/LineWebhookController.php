<?php
// app/Controllers/LineWebhookController.php

require_once BASE_PATH . '/config/app.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/settings.php';
require_once BASE_PATH . '/app/Services/LineService.php';
require_once BASE_PATH . '/app/Services/Logger.php';
require_once BASE_PATH . '/app/Services/SurveyDefinition.php';
require_once BASE_PATH . '/app/Services/UserSessionService.php';
require_once BASE_PATH . '/app/Services/ClassScheduleService.php';

class LineWebhookController {
    private LineService $line;
    private PDO $pdo;
    private UserSessionService $session;
    private ClassScheduleService $classSvc;

    public function __construct() {
        $this->line    = new LineService();
        $this->pdo     = get_pdo();
        $this->session  = new UserSessionService();
        $this->classSvc = new ClassScheduleService();
    }

    public function handle(): void {
        $body      = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_LINE_SIGNATURE'] ?? '';

        if (!$this->line->verifySignature($body, $signature)) {
            Logger::warning('line', "署名検証失敗");
            http_response_code(403);
            echo json_encode(['error' => 'Invalid signature']);
            return;
        }

        $payload = json_decode($body, true);
        if (!$payload) { http_response_code(400); return; }

        // 期限切れセッションを定期クリーンアップ（10%の確率）
        if (rand(1, 10) === 1) $this->session->cleanup();

        foreach ($payload['events'] ?? [] as $event) {
            try {
                $this->handleEvent($event);
            } catch (\Throwable $e) {
                Logger::error('line', "イベント処理エラー: " . $e->getMessage());
            }
        }

        http_response_code(200);
        echo json_encode(['status' => 'ok']);

        // LINEへの応答を即座に返し、裏で溜まったジョブを処理する
        // （cronが止まっていても受講生の操作で処理が進む保険）
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        $this->processQueuedJobs();
    }

    // 溜まっているジョブを最大2件処理（Webカウント時の保険処理）
    private function processQueuedJobs(): void {
        try {
            $pending = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM job_queue WHERE status = 'pending' AND available_at <= NOW()"
            )->fetchColumn();
            if ($pending === 0) return;

            require_once BASE_PATH . '/app/Services/PromptService.php';
            require_once BASE_PATH . '/app/Services/ImageGenerationService.php';
            require_once BASE_PATH . '/app/Services/StorageService.php';
            require_once BASE_PATH . '/app/Workers/GenerateImagesWorker.php';

            $worker = new GenerateImagesWorker();
            // 最大2件だけ処理（Webhookを重くしすぎない）
            for ($i = 0; $i < 2; $i++) {
                $left = (int) $this->pdo->query(
                    "SELECT COUNT(*) FROM job_queue WHERE status = 'pending' AND available_at <= NOW()"
                )->fetchColumn();
                if ($left === 0) break;
                $worker->run();
            }
            // 死活監視の時刻も更新
            Settings::set('worker_last_run', date('Y-m-d H:i:s'));

            // リマインダー送信
            require_once BASE_PATH . '/app/Services/ReminderService.php';
            (new ReminderService())->dispatchDue();
        } catch (\Throwable $e) {
            Logger::error('worker', "Webhook処理エラー: " . $e->getMessage());
        }
    }

    private function handleEvent(array $event): void {
        $type       = $event['type'] ?? '';
        $lineUserId = $event['source']['userId'] ?? '';

        if ($type === 'follow') {
            $this->handleFollow($lineUserId, $event);
            return;
        }

        // アンフォロー（ブロック・友だち削除）時はユーザーを非アクティブに
        if ($type === 'unfollow') {
            if ($lineUserId) {
                $this->pdo->prepare("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE line_user_id = ?")
                    ->execute([$lineUserId]);
                Logger::info('line', "アンフォロー line={$lineUserId}");
            }
            return;
        }

        if ($type === 'message' && ($event['message']['type'] ?? '') === 'text') {
            $this->handleTextMessage($lineUserId, $event);
        }
    }

    // フォロー時
    private function handleFollow(string $lineUserId, array $event): void {
        $this->upsertUser($lineUserId);
        // 再フォロー時は active に戻す
        $this->pdo->prepare("UPDATE users SET status = 'active', updated_at = NOW() WHERE line_user_id = ?")
            ->execute([$lineUserId]);
        $replyToken = $event['replyToken'] ?? '';
        if ($replyToken) {
            // カスタムあいさつメッセージ（管理画面で編集可能）
            $greeting = Settings::get('greeting_message', '');
            if (!$greeting) {
                $greeting = "AIアート教室へようこそ！\n\n教室の開催日に「参加予約」を押すと、画像生成が使えます🎨\n\nメニューから操作してください。";
            }
            // 規約URLがあれば案内を添える
            $terms = Settings::get('terms_url', '');
            if ($terms) {
                $greeting .= "\n\nご利用にあたっては利用規約をご確認ください。「規約」と送るとご案内します。";
            }
            $this->line->replyText($replyToken, $greeting);
        }
    }

    // テキスト受信 — アンケートの状態によって分岐
    private function handleTextMessage(string $lineUserId, array $event): void {
        $text       = trim($event['message']['text'] ?? '');
        $replyToken = $event['replyToken'] ?? '';
        if (!$text) return;

        $user    = $this->upsertUser($lineUserId);
        $session = $this->session->get($lineUserId);
        $step    = $session['step'] ?? SurveyDefinition::STEP_IDLE;

        // 履歴コマンド
        if (in_array(mb_strtolower($text), ['履歴', 'history', 'きろく', '記録'])) {
            $this->handleHistory($lineUserId, $replyToken);
            return;
        }

        // 開催日コマンド
        if (in_array($text, ['開催日', 'スケジュール', '日程'])) {
            $this->handleScheduleInfo($replyToken);
            return;
        }

        // 使い方コマンド
        if (in_array($text, ['使い方', 'つかいかた', 'ヘルプ', 'help'])) {
            $this->handleHelp($replyToken);
            return;
        }

        // お問合せコマンド
        if (in_array($text, ['お問合せ', 'お問い合わせ', '問い合わせ', 'contact'])) {
            $contactMsg = Settings::get('contact_message', '');
            if (!$contactMsg) {
                $contactMsg = "お問い合わせは教室スタッフまでお願いします。";
            }
            $this->line->replyText($replyToken, $contactMsg);
            return;
        }

        // キャンセルコマンド
        if (in_array($text, ['キャンセル', 'cancel', 'やめる', 'やりなおす'])) {
            $this->session->clear($lineUserId);
            $this->line->replyText($replyToken, "キャンセルしました。\nまた「生成する」で始められます。");
            return;
        }

        switch ($step) {
            case SurveyDefinition::STEP_IDLE:
                $this->handleIdle($lineUserId, $user, $text, $replyToken);
                break;
            case SurveyDefinition::STEP_MODE:
                $this->handleModeSelect($lineUserId, $session, $text, $replyToken);
                break;
            case SurveyDefinition::STEP_FREE:
                $this->handleFreeInput($lineUserId, $user, $session, $text, $replyToken);
                break;
            case SurveyDefinition::STEP_STYLE:
                $this->handleStyleAnswer($lineUserId, $session, $text, $replyToken);
                break;
            case SurveyDefinition::STEP_MOOD:
                $this->handleMoodAnswer($lineUserId, $session, $text, $replyToken);
                break;
            case SurveyDefinition::STEP_KEYWORD:
                $this->handleKeywordInput($lineUserId, $user, $session, $text, $replyToken);
                break;
        }
    }

    // idle状態 — 「参加する」チェックイン or「生成する」でアンケート開始
    private function handleIdle(string $lineUserId, array $user, string $text, string $replyToken): void {
        $classMode = Settings::get('class_mode_enabled', '1') === '1';

        // 「参加予約」＝事前予約
        $isReserve = in_array(mb_strtolower($text), ['参加予約', '予約', 'よやく']);
        if ($isReserve) {
            $this->handleCheckin($lineUserId, $user, $replyToken);
            return;
        }

        // 「参加」＝当日チェックイン（実際に来た記録）
        $isAttend = in_array(mb_strtolower($text), ['参加', '参加する', 'チェックイン', 'join', '出席']);
        if ($isAttend) {
            $this->handleAttend($lineUserId, $user, $replyToken);
            return;
        }

        // 「キャンセル」＝予約取消
        if (in_array(mb_strtolower($text), ['キャンセル', 'cancel', '取消', '取り消し', 'よやくとりけし'])) {
            $this->handleCancel($lineUserId, $user, $replyToken);
            return;
        }

        // 「もう一回」＝直近の依頼を再生成
        if (in_array($text, ['もう一回', 'もういちど', 'もう一度', '再生成', 'リトライ', 'やり直し'])) {
            $this->handleRegenerate($lineUserId, $user, $replyToken);
            return;
        }

        // 「規約」「プライバシー」案内
        if (in_array($text, ['規約', '利用規約', 'プライバシー', 'プライバシーポリシー', '個人情報'])) {
            $terms = Settings::get('terms_url', '');
            $privacy = Settings::get('privacy_url', '');
            if (!$terms && !$privacy) {
                $this->line->replyText($replyToken, "現在準備中です。詳しくは教室スタッフにお問い合わせください。");
            } else {
                $msg = "📄 各種ご案内\n\n";
                if ($terms)   $msg .= "【利用規約】\n{$terms}\n\n";
                if ($privacy) $msg .= "【プライバシーポリシー】\n{$privacy}\n";
                $this->line->replyText($replyToken, trim($msg));
            }
            return;
        }

        // 「チケット購入」「回数券」
        if (in_array($text, ['チケット購入', 'チケット', '回数券', 'チケットを買う'])) {
            $this->handleTicketPurchase($lineUserId, $user, $replyToken);
            return;
        }

        // チケットプラン選択（チケット購入:0 形式）
        if (preg_match('/^チケット購入:(\d+)$/u', $text, $tm)) {
            $this->handleTicketSelect($lineUserId, $user, (int)$tm[1], $replyToken);
            return;
        }

        // 「サブスク」「会員登録」
        if (in_array($text, ['サブスク', '会員登録', 'サブスク登録', '月額会員'])) {
            $this->handleSubscribe($lineUserId, $user, $replyToken);
            return;
        }

        // 「生成する」トリガー
        $triggers = ['生成する', '生成', '作る', '作って', 'start', '始める', '画像'];
        $isStart  = false;
        foreach ($triggers as $t) {
            if (mb_strpos($text, $t) !== false) { $isStart = true; break; }
        }

        if (!$isStart) {
            // クイックリプライボタンの内容をクラスモードに応じて変える
            $buttons = [['type'=>'action','action'=>['type'=>'message','label'=>'✨ 生成する','text'=>'生成する']]];
            if ($classMode && $this->classSvc->isCheckinOpen()) {
                array_unshift($buttons, ['type'=>'action','action'=>['type'=>'message','label'=>'🎓 参加予約','text'=>'参加予約']]);
            }
            $buttons[] = ['type'=>'action','action'=>['type'=>'message','label'=>'❓ 使い方','text'=>'使い方']];
            $buttons[] = ['type'=>'action','action'=>['type'=>'message','label'=>'💬 お問合せ','text'=>'お問合せ']];
            $this->line->replyWithQuickReply($replyToken,
                "メニューから操作を選んでください🎨\n「生成する」で画像生成、「使い方」で操作説明が見られます。", $buttons);
            return;
        }

        // クラスモードのチェック
        if ($classMode) {
            if (!$this->classSvc->hasTodayClass()) {
                $next = $this->classSvc->getNextSchedule();
                $nextStr = $next ? date('m月d日（D）', strtotime($next['class_date'])) : 'お知らせをお待ちください';
                $this->line->replyText($replyToken,
                    "現在、教室の開催日ではありません。
次回：{$nextStr}");
                return;
            }
            if (!$this->classSvc->isApprovedToday($lineUserId)) {
                $canApply = $this->classSvc->isCheckinOpen();
                if ($canApply) {
                    $this->line->replyWithQuickReply($replyToken,
                        "画像生成を使うには、まず本日の教室への参加予約が必要です。",
                        [['type'=>'action','action'=>['type'=>'message','label'=>'🎓 参加予約','text'=>'参加予約']]]
                    );
                } else {
                    $this->line->replyText($replyToken,
                        "参加申請の受付時間外です。
開催時間内に「参加予約」を送ってください。");
                }
                return;
            }
        }

        // 上限チェック（スケジュールのmax_requestsを使用）
        if (!$this->checkDailyLimit($lineUserId, $replyToken)) return;

        // 形式選択（アンケート / 自由記述）へ
        $this->session->start($lineUserId);
        $this->session->advance($lineUserId, SurveyDefinition::STEP_MODE, []);
        $this->askMode($replyToken);
    }

    // 生成方式の選択
    private function askMode(string $replyToken): void {
        $this->line->replyWithQuickReply($replyToken,
            "画像生成を始めます🎨\nどちらの方法で作りますか？",
            [
                ['type'=>'action','action'=>['type'=>'message','label'=>'📋 アンケートで選ぶ','text'=>'アンケート']],
                ['type'=>'action','action'=>['type'=>'message','label'=>'✍️ 自由に書く','text'=>'自由記述']],
            ]
        );
    }

    // LINE用の会場/Zoom案内文
    private function buildAccessInfoForLine(array $schedule): string {
        $fmt = $schedule['event_format'] ?? 'realtime';
        $info = '';
        if (($fmt === 'zoom' || $fmt === 'hybrid') && !empty($schedule['zoom_url'])) {
            $info .= "🎥 Zoom参加URL\n{$schedule['zoom_url']}\n";
        }
        if (($fmt === 'realtime' || $fmt === 'hybrid') && !empty($schedule['location'])) {
            $info .= "📍 会場：{$schedule['location']}\n";
        }
        if ($info !== '') $info .= "\n";
        return $info;
    }

    // 出席・生成履歴をユーザーに送信
    private function handleHistory(string $lineUserId, string $replyToken): void {
        // 参加履歴（直近5件）
        $stmtA = $this->pdo->prepare("
            SELECT a.*, s.title, s.class_date
            FROM class_attendances a
            LEFT JOIN class_schedules s ON s.id = a.schedule_id
            WHERE a.line_user_id = ? AND a.status = 'approved'
            ORDER BY s.class_date DESC LIMIT 5
        ");
        $stmtA->execute([$lineUserId]);
        $attendances = $stmtA->fetchAll();

        // 生成履歴（直近5件）
        $stmtR = $this->pdo->prepare("
            SELECT * FROM image_requests
            WHERE line_user_id = ? AND status = 'completed'
            ORDER BY created_at DESC LIMIT 5
        ");
        $stmtR->execute([$lineUserId]);
        $requests = $stmtR->fetchAll();

        // メッセージ作成
        $msg = "📋 あなたの履歴
";
        $msg .= str_repeat('─', 15) . "
";

        if ($attendances) {
            $msg .= "
🎓 参加履歴（直近5回）
";
            foreach ($attendances as $a) {
                $date = $a['class_date'] ? date('m/d', strtotime($a['class_date'])) : '—';
                $msg .= "・{$date} {$a['title']}
";
            }
        } else {
            $msg .= "
🎓 参加履歴：なし
";
        }

        if ($requests) {
            $msg .= "
🎨 画像生成履歴（直近5件）
";
            foreach ($requests as $r) {
                $date  = date('m/d', strtotime($r['created_at']));
                $input = mb_strimwidth($r['input_text'], 0, 15, '…');
                $style = $r['survey_style'] ?? '';
                $mood  = $r['survey_mood']  ?? '';
                $tag   = $style ? "[{$style}]" : '';
                $msg .= "・{$date} {$tag}{$input}
";
            }
        } else {
            $msg .= "
🎨 生成履歴：なし
";
        }

        $msg .= "
「生成する」で画像生成を始められます。";

        $this->line->replyText($replyToken, $msg);
    }

    // 開催日案内
    private function handleScheduleInfo(string $replyToken): void {
        $next = $this->classSvc->getNextSchedule();
        if (!$next) {
            $msg = Settings::get('next_class_message', '次回の教室開催日をお待ちください。');
        } else {
            $date  = date('n月j日（D）', strtotime($next['class_date']));
            $start = substr($next['start_time'], 0, 5);
            $end   = substr($next['end_time'], 0, 5);
            $open  = substr($next['checkin_open'], 0, 5);
            $close = substr($next['checkin_close'], 0, 5);
            $msg  = "📅 次回の教室\n";
            $msg .= str_repeat('─', 12) . "\n";
            $msg .= "{$next['title']}\n";
            $msg .= "日時：{$date} {$start}〜{$end}\n";
            $msg .= "参加受付：{$open}〜{$close}\n";
            if (!empty($next['organizer'])) {
                $msg .= "主催：{$next['organizer']}\n";
            }
            // 開催形式・場所
            $fmt = $next['event_format'] ?? 'realtime';
            if ($fmt === 'zoom') {
                $msg .= "形式：オンライン（Zoom）\n";
                $msg .= "※ ZoomのURLは参加承認後にご案内します\n";
            } elseif ($fmt === 'hybrid') {
                $msg .= "形式：会場＋オンライン\n";
                if (!empty($next['location'])) $msg .= "会場：{$next['location']}\n";
                $msg .= "※ ZoomのURLは参加承認後にご案内します\n";
            } else {
                if (!empty($next['location'])) $msg .= "会場：{$next['location']}\n";
            }
            if (!empty($next['public_message'])) {
                $msg .= "\n" . $next['public_message'] . "\n";
            }
            $msg .= "\n受付時間内に「参加予約」を押してください。";
        }
        $this->line->replyText($replyToken, $msg);
    }

    // 使い方案内
    private function handleHelp(string $replyToken): void {
        $msg  = "❓ 使い方\n";
        $msg .= str_repeat('─', 12) . "\n\n";
        $msg .= "【1】開催日に「参加予約」を押す\n";
        $msg .= "→ スタッフが承認します\n\n";
        $msg .= "【2】「生成する」を押す\n";
        $msg .= "→ 画風・雰囲気を選んで\n  キーワードを送ります\n\n";
        $msg .= "【3】画像が届く\n";
        $msg .= "→ 2パターン8枚をお届け🎨\n\n";
        $msg .= "「履歴」で過去の作品を確認できます。";
        $this->line->replyText($replyToken, $msg);
    }

    // チェックイン処理
    // 直近の依頼を再生成
    private function handleRegenerate(string $lineUserId, array $user, string $replyToken): void {
        $stmt = $this->pdo->prepare("
            SELECT * FROM image_requests
            WHERE line_user_id = ?
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$lineUserId]);
        $last = $stmt->fetch();

        if (!$last) {
            $this->line->replyText($replyToken,
                "再生成できる前回の作品が見つかりませんでした。\n「生成する」から新しく作成してください🎨");
            return;
        }

        if (!$this->checkDailyLimit($lineUserId, $replyToken)) return;

        $stmt = $this->pdo->prepare("
            INSERT INTO image_requests
                (user_id, line_user_id, input_type, input_text, survey_style, survey_mood, status, created_at, updated_at)
            VALUES
                (?, ?, ?, ?, ?, ?, 'received', NOW(), NOW())
        ");
        $stmt->execute([
            $user['id'], $lineUserId,
            $last['input_type'], $last['input_text'],
            $last['survey_style'] ?? null, $last['survey_mood'] ?? null,
        ]);
        $requestId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("
            INSERT INTO job_queue (request_id, job_type, status, created_at, updated_at)
            VALUES (?, 'generate_images', 'pending', NOW(), NOW())
        ")->execute([$requestId]);

        Logger::info('line', "再生成依頼受付 request_id={$requestId}（元={$last['id']}）", $requestId);

        $this->line->replyText($replyToken,
            "🔄 もう一度生成します！\n\n前回と同じ内容で、新しいパターンの画像を作成中です。\n完成までしばらくお待ちください（通常3〜5分）🎨");
    }

    // チケット購入メニュー
    private function handleTicketPurchase(string $lineUserId, array $user, string $replyToken): void {
        require_once BASE_PATH . '/app/Services/StripeService.php';
        $stripe = new StripeService();
        if (!$stripe->isConfigured()) {
            $this->line->replyText($replyToken, "現在チケットのオンライン購入は準備中です。教室スタッフにお問い合わせください。");
            return;
        }
        $plans = json_decode(Settings::get('ticket_plans', '[]'), true) ?: [];
        if (empty($plans)) {
            $this->line->replyText($replyToken, "現在販売中のチケットがありません。");
            return;
        }
        $bubbles = [];
        foreach ($plans as $i => $p) {
            $bubbles[] = ['type'=>'action','action'=>[
                'type'=>'message',
                'label'=> mb_substr("{$p['count']}回 {$p['price']}円", 0, 20),
                'text'=> "チケット購入:{$i}",
            ]];
            if (count($bubbles) >= 13) break;
        }
        $this->line->replyWithQuickReply($replyToken,
            "🎫 回数券をお選びください\n購入後すぐにご利用いただけます。", $bubbles);
    }

    // チケットプラン選択後の決済リンク発行
    private function handleTicketSelect(string $lineUserId, array $user, int $planIndex, string $replyToken): void {
        require_once BASE_PATH . '/app/Services/StripeService.php';
        $stripe = new StripeService();
        $plans = json_decode(Settings::get('ticket_plans', '[]'), true) ?: [];
        if (!isset($plans[$planIndex])) {
            $this->line->replyText($replyToken, "そのプランは見つかりませんでした。");
            return;
        }
        $plan = $plans[$planIndex];
        $base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $checkout = $stripe->createCheckout(
            (int)$plan['price'],
            "回数券 {$plan['count']}回分",
            ['kind' => 'ticket', 'user_id' => $user['id'], 'ticket_count' => $plan['count']],
            $base . '/liff/paid?type=ticket',
            $base . '/liff/calendar'
        );
        if ($checkout) {
            $this->line->replyText($replyToken,
                "🎫 {$plan['count']}回券（{$plan['price']}円）\n\n下記リンクからお支払いください👇\n{$checkout['url']}\n\nお支払い完了後、自動でチケットが追加されます。");
        } else {
            $this->line->replyText($replyToken, "決済リンクの発行に失敗しました。時間をおいてお試しください。");
        }
    }

    // サブスク加入
    private function handleSubscribe(string $lineUserId, array $user, string $replyToken): void {
        if (($user['member_type'] ?? 'none') === 'subscriber') {
            $this->line->replyText($replyToken, "すでにサブスク会員です🌟\n教室に何度でも無料でご参加いただけます。");
            return;
        }
        require_once BASE_PATH . '/app/Services/StripeService.php';
        $stripe = new StripeService();
        $priceId = Settings::get('stripe_subscription_price_id', '');
        if (!$stripe->isConfigured() || !$priceId) {
            $this->line->replyText($replyToken, "現在サブスクのオンライン登録は準備中です。教室スタッフにお問い合わせください。");
            return;
        }
        $base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
        $checkout = $stripe->createSubscriptionCheckout(
            $priceId,
            ['kind' => 'subscription', 'user_id' => $user['id']],
            $base . '/liff/paid?type=subscription',
            $base . '/liff/calendar'
        );
        if ($checkout) {
            $price = Settings::get('subscription_price_label', '');
            $priceNote = $price ? "（{$price}）" : '';
            $this->line->replyText($replyToken,
                "🌟 月額サブスク会員{$priceNote}\n\n会員になると教室に何度でも無料でご参加いただけます。\n\n下記リンクからご登録ください👇\n{$checkout['url']}");
        } else {
            $this->line->replyText($replyToken, "登録リンクの発行に失敗しました。時間をおいてお試しください。");
        }
    }

    // 予約キャンセル
    private function handleCancel(string $lineUserId, array $user, string $replyToken): void {
        // 今後の予約を探す（今日以降で未参加のもの）
        $cancelled = $this->classSvc->cancelUpcomingReservation((int)$user['id']);
        if ($cancelled) {
            $date = date('n月j日', strtotime($cancelled['class_date']));
            $this->line->replyText($replyToken,
                "{$date}「{$cancelled['title']}」の予約をキャンセルしました。\nまたのご参加をお待ちしています。");
            Logger::info('class', "予約キャンセル line={$lineUserId} schedule={$cancelled['id']}");
        } else {
            $this->line->replyText($replyToken,
                "キャンセル可能な予約が見つかりませんでした。");
        }
    }

    // 当日参加チェックイン（実際に来た記録）
    private function handleAttend(string $lineUserId, array $user, string $replyToken): void {
        $schedule = $this->classSvc->getTodaySchedule();

        if (!$schedule) {
            $this->line->replyText($replyToken, "本日は教室の開催がありません。");
            return;
        }

        // 受付時間内のみ
        if (!$this->classSvc->isCheckinOpen($schedule)) {
            $open  = substr($schedule['checkin_open'], 0, 5);
            $close = substr($schedule['checkin_close'], 0, 5);
            $this->line->replyText($replyToken,
                "当日参加の受付時間は {$open}〜{$close} です。\nお時間になりましたら「参加」を送ってください。");
            return;
        }

        $result = $this->classSvc->checkInToday((int)$schedule['id'], (int)$user['id'], $lineUserId);
        $maxReq = $schedule['max_requests'] ?? 2;
        $access = $this->buildAccessInfoForLine($schedule);

        // 課金判定（決済なし・現金運用の管理）
        require_once BASE_PATH . '/app/Services/BillingService.php';
        $billing = new BillingService();
        $feeNote = '';
        if ($result['result'] !== 'already') {
            // 最新のattendanceを取得して課金区分を適用
            $att = $this->classSvc->getAttendance((int)$schedule['id'], $lineUserId);
            if ($att) {
                $judge = $billing->judge($user, $schedule);
                $billing->applyToAttendance((int)$att['id'], (int)$user['id'], $judge);
                $feeNote = $this->buildFeeNote($judge, $user);
            }
        }

        switch ($result['result']) {
            case 'already':
                $this->line->replyText($replyToken,
                    "すでに参加チェックイン済みです✅\n「生成する」で画像生成を始められます🎨");
                break;
            case 'checked_in':
                $this->line->replyText($replyToken,
                    "✅ 参加を確認しました！ようこそ🎉\n\n" . $feeNote . $access .
                    "本日は {$maxReq}件まで画像生成できます。\n「生成する」と送って始めてください🎨");
                Logger::info('class', "当日チェックイン（予約者） line={$lineUserId} schedule={$schedule['id']}");
                break;
            case 'walk_in':
                $this->line->replyText($replyToken,
                    "✅ 当日参加を受け付けました！ようこそ🎉\n\n" . $feeNote . $access .
                    "本日は {$maxReq}件まで画像生成できます。\n「生成する」と送って始めてください🎨");
                Logger::info('class', "当日チェックイン（飛び込み） line={$lineUserId} schedule={$schedule['id']}");
                break;
        }
    }

    // 料金案内文を組み立て
    private function buildFeeNote(array $judge, array $user): string {
        switch ($judge['type']) {
            case 'free':
                return ($judge['message'] === '初回無料') ? "🎁 初回参加は無料です！\n\n" : '';
            case 'subscription':
                return "🌟 サブスク会員ですので参加無料です。\n\n";
            case 'ticket':
                $remain = max(0, (int)($user['ticket_balance'] ?? 0) - 1);
                return "🎫 チケットを1枚使用しました（残り{$remain}枚）。\n\n";
            case 'paid':
                return "💴 本日の参加費：{$judge['amount']}円\n会場でお支払いをお願いします。\n\n";
        }
        return '';
    }

    private function handleCheckin(string $lineUserId, array $user, string $replyToken): void {
        $schedule = $this->classSvc->getTodaySchedule();

        if (!$schedule) {
            $next = $this->classSvc->getNextSchedule();
            $nextStr = $next ? date('m月d日', strtotime($next['class_date'])) : 'お知らせをお待ちください';
            $this->line->replyText($replyToken, "本日の教室はありません。
次回：{$nextStr}");
            return;
        }

        if (!$this->classSvc->isCheckinOpen($schedule)) {
            $open  = substr($schedule['checkin_open'], 0, 5);
            $close = substr($schedule['checkin_close'], 0, 5);
            $this->line->replyText($replyToken,
                "参加申請の受付時間は {$open}〜{$close} です。
お時間になりましたら「参加予約」を送ってください。");
            return;
        }

        $result = $this->classSvc->applyAttendance((int)$schedule['id'], (int)$user['id'], $lineUserId);

        switch ($result['result']) {
            case 'applied':
                // 自動承認がオンなら即承認
                if (!empty($schedule['auto_approve'])) {
                    $this->classSvc->approveByScheduleUser((int)$schedule['id'], (int)$user['id']);
                    $maxReq = $schedule['max_requests'] ?? 2;
                    $access = $this->buildAccessInfoForLine($schedule);
                    $this->line->replyText($replyToken,
                        "✅ 参加が承認されました！

" . $access .
                        "本日は {$maxReq}件まで画像生成できます。
「生成する」と送って始めてください🎨");
                    Logger::info('class', "参加申請→自動承認 line={$lineUserId} schedule={$schedule['id']}");
                } else {
                    $this->line->replyText($replyToken,
                        "参加申請を受け付けました🎓
承認されたらLINEでお知らせします。
しばらくお待ちください。");
                    Logger::info('class', "参加申請 line={$lineUserId} schedule={$schedule['id']}");
                }
                break;
            case 'already':
                $statusMsg = $result['status'] === 'approved'
                    ? "すでに承認済みです。「生成する」で画像生成を始められます🎨"
                    : "すでに申請中です。承認をお待ちください。";
                $this->line->replyText($replyToken, $statusMsg);
                break;
            case 'full':
                $this->line->replyText($replyToken,
                    "本日の教室は定員に達しました。
次回のご参加をお待ちしています。");
                break;
        }
    }

    // Q1: 画風の回答処理
    private function handleStyleAnswer(string $lineUserId, array $session, string $text, string $replyToken): void {
        $styleKey = $this->matchChoice($text, SurveyDefinition::STYLES);

        if (!$styleKey) {
            $this->askStyle($replyToken, "下のボタンから選んでください👇");
            return;
        }

        // Q2へ進む
        $data = array_merge($session['survey_data'], ['style' => $styleKey]);
        $this->session->advance($lineUserId, SurveyDefinition::STEP_MOOD, $data);
        $this->askMood($replyToken, SurveyDefinition::styleLabel($styleKey));
    }

    // Q2: 雰囲気の回答処理
    private function handleMoodAnswer(string $lineUserId, array $session, string $text, string $replyToken): void {
        $moodKey = $this->matchChoice($text, SurveyDefinition::MOODS);

        if (!$moodKey) {
            $this->askMood($replyToken, null, "下のボタンから選んでください👇");
            return;
        }

        // Q3へ進む
        $data = array_merge($session['survey_data'], ['mood' => $moodKey]);
        $this->session->advance($lineUserId, SurveyDefinition::STEP_KEYWORD, $data);
        $this->askKeyword($replyToken, $session['survey_data']['style'] ?? '', $moodKey);
    }

    // Q3: キーワード入力 → 画像生成開始
    // 形式選択の回答
    private function handleModeSelect(string $lineUserId, array $session, string $text, string $replyToken): void {
        $t = mb_strtolower(trim($text));
        if (in_array($t, ['自由記述', '自由', 'じゆう', 'free', '✍️ 自由に書く', '自由に書く'])) {
            // 自由記述モードへ
            $this->session->advance($lineUserId, SurveyDefinition::STEP_FREE, []);
            $this->line->replyText($replyToken,
                "✍️ 自由記述モードです。\n\nどんな画像を作りたいか、自由に書いて送ってください。\n\n例：夕暮れの海辺に立つ着物姿の少女、桜が舞う幻想的な雰囲気\n\nなるべく具体的に書くと、イメージに近い画像ができます🎨");
            return;
        }
        if (in_array($t, ['アンケート', 'あんけーと', 'survey', '📋 アンケートで選ぶ', 'アンケートで選ぶ'])) {
            // アンケートモードへ（従来フロー）
            $this->session->advance($lineUserId, SurveyDefinition::STEP_STYLE, []);
            $this->askStyle($replyToken);
            return;
        }
        // どちらでもない → 再提示
        $this->askMode($replyToken);
    }

    // 自由記述の入力受付
    private function handleFreeInput(string $lineUserId, array $user, array $session, string $text, string $replyToken): void {
        if (mb_strlen(trim($text)) < 3) {
            $this->line->replyText($replyToken,
                "もう少し詳しく書いてください🙏\n例：星空の下で踊る妖精、幻想的な雰囲気");
            return;
        }

        // NGワードチェック
        require_once BASE_PATH . '/app/Services/ContentFilter.php';
        if (!ContentFilter::isSafe($text)) {
            $this->line->replyText($replyToken,
                "申し訳ありません。その内容では画像を作成できません🙏\n別の表現でお試しください。");
            Logger::info('line', "NGワード検出（自由記述） line={$lineUserId}");
            return;
        }

        $this->session->clear($lineUserId);

        // 依頼保存（input_type = free）
        $stmt = $this->pdo->prepare("
            INSERT INTO image_requests
                (user_id, line_user_id, input_type, input_text, status, created_at, updated_at)
            VALUES
                (?, ?, 'free', ?, 'received', NOW(), NOW())
        ");
        $stmt->execute([$user['id'], $lineUserId, $text]);
        $requestId = (int) $this->pdo->lastInsertId();

        $this->pdo->prepare("
            INSERT INTO job_queue (request_id, job_type, status, created_at, updated_at)
            VALUES (?, 'generate_images', 'pending', NOW(), NOW())
        ")->execute([$requestId]);

        Logger::info('line', "自由記述依頼受付 request_id={$requestId}", $requestId);

        $perPattern = (int) Settings::get('images_per_pattern', '4');
        $total = $perPattern * 2;
        $this->line->replyText($replyToken,
            "ありがとうございます！画像生成を始めます🎨\n\n" .
            "【ご希望】{$text}\n\n" .
            "2パターン×{$perPattern}枚の合計{$total}枚を作成中です。\n完成したらこのLINEにお送りします（通常3〜5分）"
        );
    }

    private function handleKeywordInput(string $lineUserId, array $user, array $session, string $text, string $replyToken): void {
        if (mb_strlen($text) < 2) {
            $this->line->replyText($replyToken, "もう少し詳しく教えてください🙏\n例：月、少女、森");
            return;
        }

        // NGワードチェック
        require_once BASE_PATH . '/app/Services/ContentFilter.php';
        if (!ContentFilter::isSafe($text)) {
            $this->line->replyText($replyToken,
                "申し訳ありません。その内容では画像を作成できません🙏\n別の表現でお試しください。");
            Logger::info('line', "NGワード検出（キーワード） line={$lineUserId}");
            return;
        }

        $surveyData = $session['survey_data'];
        $styleKey   = $surveyData['style'] ?? 'any_style';
        $moodKey    = $surveyData['mood']  ?? 'any_mood';

        // セッションを閉じる
        $this->session->clear($lineUserId);

        // 依頼保存
        $stmt = $this->pdo->prepare("
            INSERT INTO image_requests
                (user_id, line_user_id, input_type, input_text, survey_style, survey_mood, status, created_at, updated_at)
            VALUES
                (?, ?, 'survey', ?, ?, ?, 'received', NOW(), NOW())
        ");
        $stmt->execute([$user['id'], $lineUserId, $text, $styleKey, $moodKey]);
        $requestId = (int) $this->pdo->lastInsertId();

        // ジョブ登録
        $this->pdo->prepare("
            INSERT INTO job_queue (request_id, job_type, status, created_at, updated_at)
            VALUES (?, 'generate_images', 'pending', NOW(), NOW())
        ")->execute([$requestId]);

        Logger::info('line', "アンケート依頼受付 style={$styleKey} mood={$moodKey} request_id={$requestId}", $requestId);

        $styleLabel = SurveyDefinition::styleLabel($styleKey);
        $moodLabel  = SurveyDefinition::moodLabel($moodKey);

        $this->line->replyText($replyToken,
            "ありがとうございます！画像生成を始めます🎨\n\n" .
            "【画風】{$styleLabel}\n" .
            "【雰囲気】{$moodLabel}\n" .
            "【キーワード】{$text}\n\n" .
            "2パターン×4枚の合計8枚を作成中です。\n完成したらこのLINEにお送りします（通常3〜5分）"
        );
    }

    // ---- Q送信ヘルパー ----

    private function askStyle(string $replyToken, string $prefix = ''): void {
        $msg = ($prefix ? $prefix . "\n\n" : '') .
               "Q1｜どんな画風がいいですか？\n（下から選んでください）";
        $this->line->replyWithQuickReply($replyToken, $msg,
            SurveyDefinition::quickReplyItems(SurveyDefinition::STYLES)
        );
    }

    private function askMood(string $replyToken, ?string $styleLabel = null, string $prefix = ''): void {
        $selected = $styleLabel ? "画風：{$styleLabel} ✅\n\n" : '';
        $msg = ($prefix ? $prefix . "\n\n" : '') .
               $selected .
               "Q2｜どんな雰囲気にしますか？\n（下から選んでください）";
        $this->line->replyWithQuickReply($replyToken, $msg,
            SurveyDefinition::quickReplyItems(SurveyDefinition::MOODS)
        );
    }

    private function askKeyword(string $replyToken, string $styleKey, string $moodKey): void {
        $styleLabel = SurveyDefinition::styleLabel($styleKey);
        $moodLabel  = SurveyDefinition::moodLabel($moodKey);
        $this->line->replyText($replyToken,
            "画風：{$styleLabel} ✅\n雰囲気：{$moodLabel} ✅\n\n" .
            "Q3｜最後に、描きたいものを教えてください✏️\n\n" .
            "キーワード（例：月、少女、森）\nまたは文章（例：夕暮れの海辺を歩く少年）\n\n" .
            "※「キャンセル」で最初に戻れます"
        );
    }

    // ---- ユーティリティ ----

    // 選択肢テキストからキーを逆引き（ラベルの部分一致）
    private function matchChoice(string $text, array $choices): ?string {
        // 完全一致優先
        foreach ($choices as $key => $label) {
            if ($text === $label) return $key;
        }
        // 絵文字を除いた部分一致
        foreach ($choices as $key => $label) {
            $plain = preg_replace('/[\x{1F000}-\x{1FFFF}\x{2600}-\x{27BF}]/u', '', $label);
            $plain = trim($plain);
            if (mb_strpos($text, $plain) !== false || mb_strpos($plain, $text) !== false) {
                return $key;
            }
        }
        return null;
    }

    private function checkDailyLimit(string $lineUserId, string $replyToken): bool {
        $classMode = Settings::get('class_mode_enabled', '1') === '1';
        $maxDaily  = $classMode ? $this->classSvc->getTodayMaxRequests($lineUserId) : Settings::maxDailyPerUser();
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM image_requests
            WHERE line_user_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$lineUserId]);
        $count = (int) $stmt->fetchColumn();

        if ($count >= $maxDaily) {
            $this->line->replyText($replyToken,
                "本日の依頼数（{$maxDaily}件）に達しました。\n明日またお試しください🙏"
            );
            return false;
        }
        return true;
    }

    private function upsertUser(string $lineUserId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE line_user_id = ?");
        $stmt->execute([$lineUserId]);
        $user = $stmt->fetch();
        if ($user) return $user;

        $profile = $this->line->getProfile($lineUserId);
        $this->pdo->prepare("
            INSERT INTO users (line_user_id, display_name, picture_url, status, created_at, updated_at)
            VALUES (?, ?, ?, 'active', NOW(), NOW())
            ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), updated_at = NOW()
        ")->execute([
            $lineUserId,
            $profile['displayName'] ?? 'Unknown',
            $profile['pictureUrl']  ?? null,
        ]);

        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE line_user_id = ?");
        $stmt->execute([$lineUserId]);
        return $stmt->fetch();
    }
}
