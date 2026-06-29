<?php
$pageTitle = 'QRコード';
ob_start();

$lineId    = $settings['line_basic_id'] ?? '';
$lineUrl   = $lineId ? 'https://line.me/R/ti/p/' . ltrim($lineId, '@') : '';
?>

<?php if (!empty($_GET['saved'])): ?>
<div class="alert alert-success">LINE IDを保存しました</div>
<?php endif; ?>
<?php if (!empty($_GET['deleted'])): ?>
<div class="alert alert-success">LINE ID設定を削除しました</div>
<?php endif; ?>

<?php if ($lineUrl): ?>
<!-- 友だち追加URL -->
<div class="card" style="margin-bottom:16px">
  <div class="card-header">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
    友だち追加URL
  </div>
  <div class="card-body">
    <p style="font-size:13px;color:var(--muted);margin-bottom:10px">SNSのプロフィール、メール、Webサイトのボタンなどに貼り付けて使えます。</p>
    <div style="display:flex;gap:8px;align-items:center">
      <input type="text" readonly value="<?= htmlspecialchars($lineUrl) ?>" id="line-url"
             style="flex:1;font-size:13px;background:var(--bg);color:var(--accent2)">
      <button type="button" class="btn btn-secondary btn-sm" onclick="copyUrl()">コピー</button>
    </div>
  </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">

  <!-- 友だち追加QR -->
  <div class="card">
    <div class="card-header">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
      友だち追加QRコード
    </div>
    <div class="card-body" style="text-align:center">
      <?php if ($lineUrl): ?>
      <img id="qr-friend" alt="友だち追加QR" style="width:220px;height:220px;background:#fff;padding:12px;border-radius:12px">
      <p style="font-size:12px;color:var(--muted);margin-top:12px">教室の案内チラシやポスターに掲載してください</p>
      <div style="margin-top:8px">
        <a id="dl-friend" download="友だち追加QR.png" class="btn btn-secondary btn-sm">画像保存</a>
      </div>
      <?php else: ?>
      <p style="color:var(--muted);font-size:13px;padding:20px">下のフォームでLINE IDを設定すると、友だち追加QRが表示されます。</p>
      <?php endif; ?>
    </div>
  </div>

  <!-- 参加受付QR -->
  <div class="card">
    <div class="card-header">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      参加受付QRコード
    </div>
    <div class="card-body" style="text-align:center">
      <?php if ($lineUrl): ?>
      <img id="qr-join" alt="参加受付QR" style="width:220px;height:220px;background:#fff;padding:12px;border-radius:12px">
      <p style="font-size:12px;color:var(--muted);margin-top:12px">教室会場に掲示してください。読み取ると友だち追加 → トークで「参加する」と送れます。</p>
      <div style="margin-top:8px">
        <a id="dl-join" download="参加受付QR.png" class="btn btn-secondary btn-sm">画像保存</a>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- LINE ID設定 -->
<div class="card" style="margin-top:16px;max-width:600px">
  <div class="card-header">LINE公式アカウントID設定</div>
  <div class="card-body">
    <form method="POST" action="/admin/qrcode">
<?= csrf_field() ?>
      <div class="form-group">
        <label>LINE ID（ベーシックID または プレミアムID）</label>
        <input type="text" name="line_basic_id" value="<?= htmlspecialchars($lineId) ?>" placeholder="@386ipbjr">
        <p style="font-size:11px;color:var(--muted);margin-top:4px">
          LINE Official Account Managerの「設定 → アカウント設定」で確認できます。@から始まるIDを入力してください。
        </p>
      </div>
      <button type="submit" class="btn btn-primary">保存してQRを生成</button>
    </form>
    <?php if ($lineId): ?>
    <form method="POST" action="/admin/qrcode/delete" style="margin-top:10px"
          onsubmit="return confirm('LINE ID設定を削除しますか？QRコードが非表示になります。')">
<?= csrf_field() ?>
      <button type="submit" class="btn btn-danger">LINE ID設定を削除</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($lineUrl): ?>
<script>
// QRコードは外部画像API（QR Server）で生成。CDNスクリプト不要で確実に表示される
(function() {
  var url = <?= json_encode($lineUrl) ?>;
  var enc = encodeURIComponent(url);
  var friendSrc = 'https://api.qrserver.com/v1/create-qr-code/?size=440x440&margin=0&color=1a202c&data=' + enc;
  var joinSrc   = 'https://api.qrserver.com/v1/create-qr-code/?size=440x440&margin=0&color=7c6af7&data=' + enc;

  var f = document.getElementById('qr-friend');
  var j = document.getElementById('qr-join');
  if (f) { f.src = friendSrc; document.getElementById('dl-friend').href = friendSrc; }
  if (j) { j.src = joinSrc;   document.getElementById('dl-join').href = joinSrc; }
})();

function copyUrl() {
  var el = document.getElementById('line-url');
  el.select();
  navigator.clipboard.writeText(el.value).then(function() {
    event.target.textContent = 'コピー済';
    setTimeout(function(){ event.target.textContent = 'コピー'; }, 1500);
  });
}
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
