<?php
$pageTitle = 'LINE設定';
ob_start();

if (!function_exists('lc_sv')) {
    function lc_sv($key, $settings, $default = '') {
        return htmlspecialchars($settings[$key] ?? $default);
    }
}

$savedMsg = [
    'greeting' => 'あいさつメッセージを保存しました',
    'contact'  => 'お問合せメッセージを保存しました',
    'liff'     => 'LIFF設定を保存しました',
    'buttons'  => 'メニューのボタン設定を保存しました',
    'richmenu' => 'リッチメニューをLINEに反映しました',
    'removed'  => 'リッチメニューを削除しました',
];
?>

<?php if ($saved && isset($savedMsg[$saved])): ?>
<div class="alert alert-success"><?= $savedMsg[$saved] ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

  <!-- あいさつメッセージ -->
  <div class="card">
    <div class="card-header">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
      友だち追加時のあいさつメッセージ
    </div>
    <div class="card-body">
      <form method="POST" action="/admin/line-config/greeting">
<?= csrf_field() ?>
        <div class="form-group">
          <label>あいさつメッセージ</label>
          <textarea name="greeting_message" rows="8" placeholder="AIアート教室へようこそ！..."><?= lc_sv('greeting_message', $settings, "AIアート教室へようこそ！\n\n教室の開催日に「参加する」を押すと、画像生成が使えます🎨\n\nメニューから操作してください。") ?></textarea>
          <p style="font-size:11px;color:var(--muted);margin-top:4px">友だち追加した瞬間に送られるメッセージです。改行・絵文字が使えます。</p>
        </div>
        <button type="submit" class="btn btn-primary">保存する</button>
      </form>
    </div>
  </div>

  <!-- お問合せメッセージ -->
  <div class="card">
    <div class="card-header">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      お問合せメッセージ
    </div>
    <div class="card-body">
      <form method="POST" action="/admin/line-config/contact">
<?= csrf_field() ?>
        <div class="form-group">
          <label>お問合せ案内メッセージ</label>
          <textarea name="contact_message" rows="4" placeholder="お問い合わせは..."><?= lc_sv('contact_message', $settings, "お問い合わせは教室スタッフまでお願いします。") ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">保存する</button>
      </form>
    </div>
  </div>

</div>

<!-- LIFF予約カレンダー設定 -->
<div class="card" style="margin-top:16px">
  <div class="card-header">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    予約カレンダー（LIFF）設定
  </div>
  <div class="card-body">
    <p style="font-size:13px;color:var(--muted);margin-bottom:12px">
      受講生がLINEから開く予約カレンダーを使うには、LINE DevelopersでLIFFアプリを作成し、IDを設定します。
      エンドポイントURLには <code><?= (isset($_SERVER['HTTPS'])?'https':'http') . '://' . $_SERVER['HTTP_HOST'] ?>/liff/calendar</code> を指定してください。
    </p>
    <form method="POST" action="/admin/line-config/liff">
<?= csrf_field() ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>LIFF ID</label>
          <input type="text" name="liff_id" value="<?= lc_sv('liff_id', $settings) ?>" placeholder="2001234567-AbCdEfGh">
        </div>
        <div class="form-group">
          <label>LINEログイン チャネルID</label>
          <input type="text" name="liff_channel_id" value="<?= lc_sv('liff_channel_id', $settings) ?>" placeholder="2001234567">
          <p style="font-size:11px;color:var(--muted);margin-top:4px">IDトークン検証に使用します</p>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">LIFF設定を保存</button>
    </form>
    <?php if (!empty($settings['liff_id'])): ?>
    <div style="margin-top:12px;padding:10px;background:var(--bg);border-radius:8px;font-size:12px">
      予約カレンダーURL（リッチメニューやメッセージに設定）：<br>
      <code style="color:var(--accent2);word-break:break-all">https://liff.line.me/<?= htmlspecialchars($settings['liff_id']) ?></code>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- リッチメニュー設定 -->
<div class="card" style="margin-top:16px">
  <div class="card-header">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="9" x2="9" y2="21"/><line x1="15" y1="9" x2="15" y2="21"/><line x1="3" y1="15" x2="21" y2="15"/></svg>
    リッチメニュー（6ボタン）
  </div>
  <div class="card-body">

    <!-- ボタン設定 -->
    <form method="POST" action="/admin/line-config/buttons">
<?= csrf_field() ?>
      <p style="font-size:13px;color:var(--muted);margin-bottom:14px">トーク画面下部に表示される6つのボタンを設定します。タップすると「送信テキスト」がメッセージとして送られます。</p>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
        <?php for ($i = 0; $i < 6; $i++):
          $btn = $richButtons[$i] ?? ['icon' => '', 'label' => '', 'text' => '', 'action' => 'message', 'url' => ''];
          $btnAction = $btn['action'] ?? 'message';
        ?>
        <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:12px">
          <div style="font-size:11px;color:var(--muted);margin-bottom:8px">ボタン <?= $i + 1 ?></div>
          <div class="form-group" style="margin-bottom:8px">
            <label>アイコン（絵文字）</label>
            <input type="text" name="icon_<?= $i ?>" value="<?= htmlspecialchars($btn['icon'] ?? '') ?>" maxlength="4" style="text-align:center;font-size:18px">
          </div>
          <div class="form-group" style="margin-bottom:8px">
            <label>ラベル</label>
            <input type="text" name="label_<?= $i ?>" value="<?= htmlspecialchars($btn['label'] ?? '') ?>" placeholder="参加予約">
          </div>
          <div class="form-group" style="margin-bottom:8px">
            <label>動作</label>
            <select name="action_<?= $i ?>" onchange="toggleBtnAction(<?= $i ?>)" id="action_<?= $i ?>">
              <option value="message" <?= $btnAction === 'message' ? 'selected' : '' ?>>メッセージ送信</option>
              <option value="url"     <?= $btnAction === 'url' ? 'selected' : '' ?>>URLを開く（カレンダー等）</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0" id="text_field_<?= $i ?>">
            <label>送信テキスト</label>
            <input type="text" name="text_<?= $i ?>" value="<?= htmlspecialchars($btn['text'] ?? '') ?>" placeholder="参加予約">
          </div>
          <div class="form-group" style="margin-bottom:0" id="url_field_<?= $i ?>">
            <label>開くURL</label>
            <input type="text" name="url_<?= $i ?>" value="<?= htmlspecialchars($btn['url'] ?? '') ?>" placeholder="https://liff.line.me/...">
          </div>
        </div>
        <?php endfor; ?>
      </div>
      <script>
      function toggleBtnAction(i) {
        var a = document.getElementById('action_' + i).value;
        document.getElementById('text_field_' + i).style.display = (a === 'message') ? 'block' : 'none';
        document.getElementById('url_field_' + i).style.display  = (a === 'url') ? 'block' : 'none';
      }
      for (var i = 0; i < 6; i++) toggleBtnAction(i);
      </script>
      <div style="margin-top:14px">
        <button type="submit" class="btn btn-secondary">ボタン設定を保存</button>
      </div>
    </form>

    <hr style="border:none;border-top:1px solid var(--border);margin:20px 0">

    <!-- LINEへ反映 -->
    <form method="POST" action="/admin/line-config/apply" enctype="multipart/form-data">
<?= csrf_field() ?>
      <h3 style="font-size:14px;font-weight:600;margin-bottom:12px">LINEに反映する</h3>

      <div class="form-group">
        <label>メニュー画像</label>
        <div style="display:flex;flex-direction:column;gap:8px">
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text);cursor:pointer">
            <input type="radio" name="image_mode" value="generate" checked style="width:auto">
            システムが自動でボタン画像を生成する（簡単）
          </label>
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text);cursor:pointer">
            <input type="radio" name="image_mode" value="upload" style="width:auto">
            自分で画像をアップロードする（2500×1686px推奨）
          </label>
        </div>
      </div>

      <div class="form-group" id="upload-field" style="display:none">
        <label>画像ファイル（PNG / JPG）</label>
        <input type="file" name="rich_image" accept="image/png,image/jpeg"
               style="padding:6px;background:var(--bg);border:1px solid var(--border);border-radius:6px;width:100%;color:var(--text)">
      </div>

      <div class="manual-note" style="background:rgba(245,158,11,.1);border-left:3px solid var(--warning);padding:10px 14px;border-radius:0 6px 6px 0;font-size:12px;color:var(--text);margin:12px 0">
        ⚠ 自動生成はImagick拡張が必要です。使えない場合は画像をアップロードしてください。<br>
        反映には数秒かかります。受講生のLINEには次回トークを開いたときに表示されます。
      </div>

      <div style="display:flex;gap:8px">
        <button type="submit" class="btn btn-primary"
                onclick="return confirm('リッチメニューをLINEに反映しますか？全受講生に表示されます。')">
          LINEに反映する
        </button>
        <button type="submit" formaction="/admin/line-config/remove" class="btn btn-danger"
                onclick="return confirm('リッチメニューを削除しますか？')">
          リッチメニューを削除
        </button>
      </div>
    </form>

  </div>
</div>

<script>
document.querySelectorAll('input[name="image_mode"]').forEach(function(radio) {
  radio.addEventListener('change', function() {
    document.getElementById('upload-field').style.display =
      (this.value === 'upload') ? 'block' : 'none';
  });
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
