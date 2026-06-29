<?php
$pageTitle = 'API設定';
ob_start();

if (!function_exists('sv')) {
    function sv(string $key, array $settings): string {
        return htmlspecialchars($settings[$key] ?? '');
    }
}
?>

<?php if (!empty($_GET['saved'])): ?>
<div class="alert alert-success">設定を保存しました。</div>
<?php endif; ?>

<!-- API接続テスト結果 -->
<div id="test-result" style="display:none;margin-bottom:16px"></div>

<form method="POST" action="/admin/settings">
<?= csrf_field() ?>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

    <!-- LINE -->
    <div class="card">
      <div class="card-header">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        LINE Messaging API
      </div>
      <div class="card-body">
        <div class="form-group">
          <label>Channel Secret</label>
          <input type="text" name="line_channel_secret" id="line_channel_secret" value="<?= sv('line_channel_secret', $settings) ?>" placeholder="Channel Secretを入力">
        </div>
        <div class="form-group">
          <label>Channel Access Token</label>
          <input type="text" name="line_channel_access_token" id="line_channel_access_token" value="<?= sv('line_channel_access_token', $settings) ?>" placeholder="アクセストークンを入力">
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="testApi('line')" style="margin-top:4px">
          接続テスト
        </button>
      </div>
    </div>

    <!-- Claude API -->
    <div class="card">
      <div class="card-header">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Claude API（プロンプト生成）
      </div>
      <div class="card-body">
        <div class="form-group">
          <label>API Key</label>
          <input type="text" name="claude_api_key" id="claude_api_key" value="<?= sv('claude_api_key', $settings) ?>" placeholder="sk-ant-...">
        </div>
        <div style="font-size:11px;color:var(--muted);margin-bottom:8px">
          モデル：claude-haiku-4-5（コスト最小）／ 1リクエスト約0.01円以下
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="testApi('claude')">
          接続テスト
        </button>
      </div>
    </div>

    <!-- Stability AI -->
    <div class="card">
      <div class="card-header">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        Stability AI（画像生成）
      </div>
      <div class="card-body">
        <div class="form-group">
          <label>API Key</label>
          <input type="text" name="stability_api_key" id="stability_api_key" value="<?= sv('stability_api_key', $settings) ?>" placeholder="sk-...">
        </div>
        <div style="font-size:11px;color:var(--muted);margin-bottom:8px">
          1枚 約0.3〜0.5円、8枚で約3〜4円<br>
          <a href="https://platform.stability.ai" target="_blank" style="color:var(--accent2)">APIキー取得 →</a>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="testApi('stability')">
          接続テスト
        </button>
      </div>
    </div>

    <!-- 画像生成エンジン選択 -->
    <div class="card">
      <div class="card-header">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/><line x1="4" y1="4" x2="9" y2="9"/></svg>
        画像生成エンジン
      </div>
      <div class="card-body">
        <div class="form-group">
          <label>使用するエンジン</label>
          <select name="image_engine">
            <option value="stability" <?= ($settings['image_engine'] ?? 'stability') === 'stability' ? 'selected' : '' ?>>Stability AI（SDXL）— 低コスト・イラスト向き</option>
            <option value="grok"      <?= ($settings['image_engine'] ?? '') === 'grok' ? 'selected' : '' ?>>Grok（xAI）— 無料クレジット枠あり</option>
          </select>
          <p style="font-size:11px;color:var(--muted);margin-top:4px">受講生の画像生成に使うエンジンを選びます。いつでも切り替え可能です。</p>
        </div>
      </div>
    </div>

    <!-- Grok（xAI）-->
    <div class="card">
      <div class="card-header">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
        Grok（xAI）画像生成
      </div>
      <div class="card-body">
        <div class="form-group">
          <label>API Key</label>
          <input type="text" name="grok_api_key" id="grok_api_key" value="<?= sv('grok_api_key', $settings) ?>" placeholder="xai-...">
        </div>
        <div class="form-group">
          <label>モデル</label>
          <select name="grok_image_model">
            <option value="grok-imagine-image"     <?= ($settings['grok_image_model'] ?? 'grok-imagine-image') === 'grok-imagine-image' ? 'selected' : '' ?>>標準（1枚 約$0.02）</option>
            <option value="grok-imagine-image-pro" <?= ($settings['grok_image_model'] ?? '') === 'grok-imagine-image-pro' ? 'selected' : '' ?>>プロ・高品質（1枚 約$0.07）</option>
          </select>
        </div>
        <div style="font-size:11px;color:var(--muted);margin-bottom:8px">
          データ共有プログラムで月最大$175の無料クレジットあり<br>
          <a href="https://console.x.ai" target="_blank" style="color:var(--accent2)">APIキー取得 →</a>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="testApi('grok')">
          接続テスト
        </button>
      </div>
    </div>

    <!-- 画質・精度設定 -->
    <div class="card" style="grid-column:1 / -1">
      <div class="card-header">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        画質・精度設定
      </div>
      <div class="card-body">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">
          <div class="form-group">
            <label>プロンプト生成モデル</label>
            <select name="prompt_model">
              <?php $pm = $settings['prompt_model'] ?? 'haiku'; ?>
              <option value="haiku"  <?= $pm === 'haiku'  ? 'selected' : '' ?>>Haiku（高速・低コスト）</option>
              <option value="sonnet" <?= $pm === 'sonnet' ? 'selected' : '' ?>>Sonnet（高品質・推奨）</option>
            </select>
            <p style="font-size:11px;color:var(--muted);margin-top:4px">Sonnetにすると構図・光・質感の指定が精密になり画像の質が上がります。コスト差は1リクエスト数円以下。</p>
          </div>
          <div class="form-group">
            <label>Stabilityモデル（エンジン=Stabilityのとき）</label>
            <select name="stability_model">
              <?php $sm = $settings['stability_model'] ?? 'sdxl'; ?>
              <option value="sdxl"  <?= $sm === 'sdxl'  ? 'selected' : '' ?>>SDXL（低コスト・1枚約0.3円）</option>
              <option value="core"  <?= $sm === 'core'  ? 'selected' : '' ?>>Stable Image Core（高品質・1枚約3円）</option>
              <option value="ultra" <?= $sm === 'ultra' ? 'selected' : '' ?>>Stable Image Ultra（最高品質・1枚約7円）</option>
            </select>
            <p style="font-size:11px;color:var(--muted);margin-top:4px">CoreとUltraは新APIで人物・和風・幻想系に効果的。Core/Ultraはアスペクト比をAPIで指定します。</p>
          </div>
          <div class="form-group">
            <label>アスペクト比</label>
            <select name="image_aspect">
              <?php $ia = $settings['image_aspect'] ?? 'square'; ?>
              <option value="square"    <?= $ia === 'square'    ? 'selected' : '' ?>>正方形 1:1（汎用）</option>
              <option value="portrait"  <?= $ia === 'portrait'  ? 'selected' : '' ?>>縦長 2:3（人物・キャラ向き）</option>
              <option value="landscape" <?= $ia === 'landscape' ? 'selected' : '' ?>>横長 3:2（風景向き）</option>
            </select>
            <p style="font-size:11px;color:var(--muted);margin-top:4px">人物中心なら縦長が自然に仕上がります。</p>
          </div>
          <div class="form-group">
            <label>生成ステップ数（SDXLのみ）</label>
            <select name="image_steps">
              <?php $is = $settings['image_steps'] ?? '30'; ?>
              <option value="20" <?= $is === '20' ? 'selected' : '' ?>>20（高速・粗め）</option>
              <option value="30" <?= $is === '30' ? 'selected' : '' ?>>30（バランス・推奨）</option>
              <option value="40" <?= $is === '40' ? 'selected' : '' ?>>40（高品質・やや遅い）</option>
              <option value="50" <?= $is === '50' ? 'selected' : '' ?>>50（最高品質・遅い）</option>
            </select>
          </div>
          <div class="form-group">
            <label>CFG Scale（SDXLのみ）</label>
            <select name="image_cfg">
              <?php $ic = $settings['image_cfg'] ?? '7'; ?>
              <option value="5"  <?= $ic === '5'  ? 'selected' : '' ?>>5（自由・創造的）</option>
              <option value="7"  <?= $ic === '7'  ? 'selected' : '' ?>>7（バランス・推奨）</option>
              <option value="9"  <?= $ic === '9'  ? 'selected' : '' ?>>9（プロンプトに忠実）</option>
              <option value="12" <?= $ic === '12' ? 'selected' : '' ?>>12（厳密）</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- NGワード設定 -->
    <div class="card" style="grid-column:1 / -1">
      <div class="card-header">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
        NGワード（生成入力のフィルタ）
      </div>
      <div class="card-body">
        <div class="form-group">
          <label>追加でブロックしたい語句（カンマまたは改行区切り）</label>
          <textarea name="ng_words" rows="3" placeholder="例：特定の人名, 不適切な語句"><?= sv('ng_words', $settings) ?></textarea>
          <p style="font-size:11px;color:var(--muted);margin-top:4px">
            性的・暴力的・違法な内容は標準でブロックされます。ここには教室固有で禁止したい語句を追加できます。受講生がこれらを含む内容を送ると、生成されず再入力を促します。
          </p>
        </div>
      </div>
    </div>

    <!-- Stripe決済 -->
    <div class="card" style="grid-column:1 / -1">
      <div class="card-header">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        Stripe決済（オンライン教室の事前決済）
      </div>
      <div class="card-body">
        <p style="font-size:13px;color:var(--muted);margin-bottom:12px">
          オンライン（Zoom）教室で2回目以降の有料参加を事前決済にします。<a href="https://dashboard.stripe.com/apikeys" target="_blank" style="color:var(--accent2)">Stripeダッシュボード</a>でキーを取得してください。
        </p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <div class="form-group">
            <label>シークレットキー（sk_live_... / sk_test_...）</label>
            <input type="text" name="stripe_secret_key" id="stripe_secret_key" value="<?= sv('stripe_secret_key', $settings) ?>" placeholder="sk_live_...">
          </div>
          <div class="form-group">
            <label>公開可能キー（pk_...）</label>
            <input type="text" name="stripe_publishable_key" value="<?= sv('stripe_publishable_key', $settings) ?>" placeholder="pk_live_...">
          </div>
          <div class="form-group">
            <label>Webhook署名シークレット（whsec_...）</label>
            <input type="text" name="stripe_webhook_secret" value="<?= sv('stripe_webhook_secret', $settings) ?>" placeholder="whsec_...">
            <p style="font-size:11px;color:var(--muted);margin-top:4px">空欄でも動作しますが、設定すると安全性が高まります。</p>
          </div>
          <div class="form-group" style="display:flex;align-items:flex-end">
            <button type="button" class="btn btn-secondary btn-sm" onclick="testApi('stripe')">接続テスト</button>
          </div>
        </div>
        <div class="manual-note" style="background:rgba(124,106,247,.08);border-left:3px solid var(--accent);padding:10px 14px;border-radius:0 6px 6px 0;font-size:12px;margin-top:8px">
          📌 StripeダッシュボードのWebhook設定で、エンドポイントに次のURLを登録してください：<br>
          <code style="color:var(--accent2)"><?= (isset($_SERVER['HTTPS'])?'https':'http') . '://' . $_SERVER['HTTP_HOST'] ?>/stripe/webhook</code><br>
          イベントは <code>checkout.session.completed</code> と <code>customer.subscription.deleted</code> を選択します。
        </div>

        <hr style="border:none;border-top:1px solid var(--border);margin:20px 0">

        <!-- チケットプラン -->
        <h3 style="font-size:14px;font-weight:600;margin-bottom:10px">🎫 チケット（回数券）プラン</h3>
        <p style="font-size:12px;color:var(--muted);margin-bottom:10px">受講生がLINEで「チケット購入」と送ると、ここで設定したプランから選んで購入できます。</p>
        <?php
          $ticketPlans = json_decode($settings['ticket_plans'] ?? '[]', true) ?: [];
          // 表示用に最低3行
          while (count($ticketPlans) < 3) $ticketPlans[] = ['count'=>'','price'=>''];
        ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;max-width:400px;margin-bottom:16px">
          <div style="font-size:11px;color:var(--muted)">回数</div>
          <div style="font-size:11px;color:var(--muted)">価格（円）</div>
          <?php foreach ($ticketPlans as $tp): ?>
          <input type="number" name="ticket_count[]" value="<?= htmlspecialchars($tp['count']) ?>" placeholder="例:5" min="0">
          <input type="number" name="ticket_price[]" value="<?= htmlspecialchars($tp['price']) ?>" placeholder="例:5000" min="0" step="100">
          <?php endforeach; ?>
        </div>
        <div class="form-group" style="max-width:300px;margin-bottom:16px">
          <label>チケット有効日数（0で無期限）</label>
          <input type="number" name="ticket_valid_days" value="<?= sv('ticket_valid_days', $settings) ?: '0' ?>" min="0" placeholder="例:180">
          <p style="font-size:11px;color:var(--muted);margin-top:4px">購入・付与した日から何日間有効か。例：180で半年。</p>
        </div>

        <!-- サブスク -->
        <h3 style="font-size:14px;font-weight:600;margin-bottom:10px">🌟 月額サブスク</h3>
        <p style="font-size:12px;color:var(--muted);margin-bottom:10px">
          Stripeで月額の商品（料金）を作成し、その Price ID を入力します。受講生が「サブスク」と送ると登録リンクが届きます。
        </p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <div class="form-group">
            <label>サブスク Price ID（price_...）</label>
            <input type="text" name="stripe_subscription_price_id" value="<?= sv('stripe_subscription_price_id', $settings) ?>" placeholder="price_...">
          </div>
          <div class="form-group">
            <label>金額の表示ラベル（任意）</label>
            <input type="text" name="subscription_price_label" value="<?= sv('subscription_price_label', $settings) ?>" placeholder="月額3,000円">
          </div>
        </div>
      </div>
    </div>

    <!-- 管理者通知 -->
    <div class="card" style="grid-column:1 / -1">
      <div class="card-header">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        管理者への通知
      </div>
      <div class="card-body">
        <p style="font-size:13px;color:var(--muted);margin-bottom:12px">新しい予約・決済・生成失敗が発生したとき、管理者に通知します。</p>
        <div class="form-group">
          <label>管理者のLINEユーザーID（LINE通知の宛先）</label>
          <input type="text" name="admin_line_user_id" value="<?= sv('admin_line_user_id', $settings) ?>" placeholder="Uxxxxxxxx...">
          <p style="font-size:11px;color:var(--muted);margin-top:4px">管理者自身がこの公式LINEを友だち追加し、そのユーザーIDを入れます。ユーザー管理画面で自分のIDを確認できます。</p>
        </div>
        <div class="form-group">
          <label>通知する内容</label>
          <input type="hidden" name="notify_events_present" value="1">
          <?php $ne = $settings['admin_notify_events'] ?? 'reservation,payment,failure'; ?>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:6px">
            <input type="checkbox" name="admin_notify_events[]" value="reservation" <?= strpos($ne,'reservation')!==false?'checked':'' ?> style="width:auto"> 新規予約
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:6px">
            <input type="checkbox" name="admin_notify_events[]" value="payment" <?= strpos($ne,'payment')!==false?'checked':'' ?> style="width:auto"> 決済完了
          </label>
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="admin_notify_events[]" value="failure" <?= strpos($ne,'failure')!==false?'checked':'' ?> style="width:auto"> 生成失敗
          </label>
        </div>
        <hr style="border:none;border-top:1px solid var(--border);margin:14px 0">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-bottom:10px">
          <input type="checkbox" name="admin_notify_email" value="1" <?= ($settings['admin_notify_email'] ?? '0')==='1'?'checked':'' ?> style="width:auto"> メールでも通知する
        </label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <div class="form-group">
            <label>Resend APIキー（メール送信用・任意）</label>
            <input type="text" name="resend_api_key" value="<?= sv('resend_api_key', $settings) ?>" placeholder="re_...">
          </div>
          <div class="form-group">
            <label>送信元メールアドレス</label>
            <input type="text" name="mail_from" value="<?= sv('mail_from', $settings) ?>" placeholder="noreply@sengoku-ai.com">
          </div>
        </div>
      </div>
    </div>

    <!-- 利用規約・プライバシー -->
    <div class="card" style="grid-column:1 / -1">
      <div class="card-header">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        利用規約・プライバシー案内
      </div>
      <div class="card-body">
        <p style="font-size:13px;color:var(--muted);margin-bottom:12px">受講生に提示する利用規約・プライバシーポリシーのURLを設定します。「使い方」やフォロー時メッセージで案内できます。</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
          <div class="form-group">
            <label>利用規約URL</label>
            <input type="text" name="terms_url" value="<?= sv('terms_url', $settings) ?>" placeholder="https://...">
          </div>
          <div class="form-group">
            <label>プライバシーポリシーURL</label>
            <input type="text" name="privacy_url" value="<?= sv('privacy_url', $settings) ?>" placeholder="https://...">
          </div>
        </div>
      </div>
    </div>

    <!-- ストレージ -->
    <div class="card">
      <div class="card-header">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
        画像ストレージ
      </div>
      <div class="card-body">
        <div class="form-group">
          <label>ストレージドライバー</label>
          <select name="storage_driver">
            <option value="local" <?= ($settings['storage_driver'] ?? 'local') === 'local' ? 'selected' : '' ?>>ローカル（サーバー内）</option>
            <option value="r2"    <?= ($settings['storage_driver'] ?? '') === 'r2' ? 'selected' : '' ?>>Cloudflare R2</option>
          </select>
        </div>
        <div class="form-group">
          <label>公開URL（画像のベースURL）</label>
          <input type="text" name="storage_public_url" value="<?= sv('storage_public_url', $settings) ?>" placeholder="https://cdn.example.com">
        </div>
        <details style="margin-top:8px">
          <summary style="font-size:12px;color:var(--muted);cursor:pointer">Cloudflare R2 設定</summary>
          <div style="margin-top:10px;display:flex;flex-direction:column;gap:10px">
            <div class="form-group" style="margin-bottom:0"><label>Account ID</label>
              <input type="text" name="r2_account_id" value="<?= sv('r2_account_id', $settings) ?>"></div>
            <div class="form-group" style="margin-bottom:0"><label>Bucket Name</label>
              <input type="text" name="r2_bucket" value="<?= sv('r2_bucket', $settings) ?>"></div>
            <div class="form-group" style="margin-bottom:0"><label>Access Key ID</label>
              <input type="text" name="r2_access_key" value="<?= sv('r2_access_key', $settings) ?>"></div>
            <div class="form-group" style="margin-bottom:0"><label>Secret Access Key</label>
              <input type="text" name="r2_secret_key" value="<?= sv('r2_secret_key', $settings) ?>"></div>
          </div>
        </details>
      </div>
    </div>

    <!-- 利用制限 -->
    <div class="card">
      <div class="card-header">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
        利用制限
      </div>
      <div class="card-body">
        <div class="form-group">
          <label>1ユーザーあたりの1日最大依頼数</label>
          <input type="text" name="max_daily_requests_per_user" value="<?= sv('max_daily_requests_per_user', $settings) ?>" placeholder="2">
        </div>
        <div class="form-group">
          <label>1依頼あたりの最大生成枚数</label>
          <input type="text" name="max_images_per_request" value="<?= sv('max_images_per_request', $settings) ?>" placeholder="8">
        </div>
        <div class="form-group">
          <label>1パターンあたりの生成枚数（LINE通数・コスト削減）</label>
          <select name="images_per_pattern">
            <?php $ipp = $settings['images_per_pattern'] ?? '4'; ?>
            <option value="1" <?= $ipp === '1' ? 'selected' : '' ?>>1枚（2パターンで計2枚／最小）</option>
            <option value="2" <?= $ipp === '2' ? 'selected' : '' ?>>2枚（2パターンで計4枚／推奨）</option>
            <option value="3" <?= $ipp === '3' ? 'selected' : '' ?>>3枚（2パターンで計6枚）</option>
            <option value="4" <?= $ipp === '4' ? 'selected' : '' ?>>4枚（2パターンで計8枚／最大）</option>
          </select>
          <p style="font-size:11px;color:var(--muted);margin-top:4px">
            枚数を減らすとLINE送信通数と画像生成コストの両方が下がります。20名なら、計4枚で80通、計2枚で40通。
          </p>
        </div>

        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" name="line_grid_mode" value="1" <?= ($settings['line_grid_mode'] ?? '0') === '1' ? 'checked' : '' ?> style="width:auto">
            <span>グリッド送信（複数枚を1枚にまとめて送る）</span>
          </label>
          <p style="font-size:11px;color:var(--muted);margin-top:4px">
            オンにすると、各パターンの画像を1枚のコラージュにまとめて送信します。LINE通数が大幅に削減されます（計8枚→2通）。<br>
            ※ サーバーのImagickまたはGD拡張が必要です。
          </p>
        </div>

        <div class="form-group">
          <label>LINE月間送信上限（プランに合わせて）</label>
          <input type="text" name="line_monthly_limit" value="<?= sv('line_monthly_limit', $settings) ?: '200' ?>" placeholder="200">
          <p style="font-size:11px;color:var(--muted);margin-top:4px">無料プラン200通、ライトプラン5000通。ダッシュボードの送信数監視に使われます。</p>
        </div>
      </div>
    </div>

    <!-- 通知 -->
    <div class="card">
      <div class="card-header">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        通知
      </div>
      <div class="card-body">
        <div class="form-group">
          <label>管理者メールアドレス（将来機能）</label>
          <input type="email" name="admin_email" value="<?= sv('admin_email', $settings) ?>" placeholder="admin@example.com">
        </div>
      </div>
    </div>

    <!-- 自動処理の保険（外部監視URL）-->
    <div class="card" style="grid-column:1 / -1">
      <div class="card-header">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
        自動処理の保険（外部監視URL）
      </div>
      <div class="card-body">
        <p style="font-size:13px;color:var(--muted);margin-bottom:10px">
          このURLを <a href="https://uptimerobot.com" target="_blank" style="color:var(--accent2)">UptimeRobot</a> などの無料監視サービスに登録すると、1分ごとに自動で画像処理が起動します。サーバーのcronが止まっても処理が継続します。
        </p>
        <?php $cronUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/cron/run?token=' . sv('cron_token', $settings); ?>
        <div style="display:flex;gap:8px;align-items:center">
          <input type="text" readonly value="<?= $cronUrl ?>" id="cron-url"
                 style="flex:1;font-size:12px;background:var(--bg);color:var(--accent2)">
          <button type="button" class="btn btn-secondary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('cron-url').value);this.textContent='コピー済'">コピー</button>
        </div>
        <p style="font-size:11px;color:var(--muted);margin-top:6px">
          ※ トークンは初回アクセス時に自動生成されます。URLが空欄の場合は一度このURLをブラウザで開いてください。<br>
          ※ 監視間隔は1〜5分を推奨します。
        </p>
      </div>
    </div>

  </div>

  <div style="margin-top:20px;display:flex;justify-content:flex-end">
    <button type="submit" class="btn btn-primary">設定を保存</button>
  </div>
</form>

<script>
function testApi(type) {
  const resultEl = document.getElementById('test-result');
  resultEl.style.display = 'block';
  resultEl.className = 'alert alert-info';
  resultEl.textContent = type.toUpperCase() + ' の接続テスト中...';

  const params = { type };
  if (type === 'line') {
    params.token = document.getElementById('line_channel_access_token').value;
  } else if (type === 'claude') {
    params.key = document.getElementById('claude_api_key').value;
  } else if (type === 'stability') {
    params.key = document.getElementById('stability_api_key').value;
  } else if (type === 'grok') {
    params.key = document.getElementById('grok_api_key').value;
  } else if (type === 'stripe') {
    params.key = document.getElementById('stripe_secret_key').value;
  }

  fetch('/admin/settings/test', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(params)
  })
  .then(r => r.json())
  .then(data => {
    resultEl.className = 'alert ' + (data.ok ? 'alert-success' : 'alert-error');
    resultEl.textContent = data.message;
  })
  .catch(() => {
    resultEl.className = 'alert alert-error';
    resultEl.textContent = '通信エラーが発生しました';
  });
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
