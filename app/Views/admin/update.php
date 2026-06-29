<?php
$pageTitle = 'アップデート';
ob_start();
?>

<?php if (!empty($_GET['updated'])): ?>
<div class="alert alert-success">アップデートが完了しました。ページを再読み込みしてください。</div>
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
<div class="alert alert-error">エラー：<?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:800px">

  <!-- バージョン情報 -->
  <div class="card">
    <div class="card-header">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.95"/></svg>
      現在のバージョン
    </div>
    <div class="card-body">
      <div style="font-size:32px;font-weight:800;color:var(--accent2);margin-bottom:8px">
        v<?= htmlspecialchars($currentVersion) ?>
      </div>
      <div style="font-size:12px;color:var(--muted)">AIアート教室 LINE画像生成システム</div>
    </div>
  </div>

  <!-- ZIPアップロード -->
  <div class="card">
    <div class="card-header">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>
      ZIPファイルでアップデート
    </div>
    <div class="card-body">
      <p style="font-size:13px;color:var(--muted);margin-bottom:16px">
        新しいバージョンのZIPファイルをアップロードするとファイルを自動更新します。<br>
        設定・画像データは保持されます。
      </p>
      <form method="POST" action="/admin/update/upload" enctype="multipart/form-data">
<?= csrf_field() ?>
        <div class="form-group">
          <label>ZIPファイルを選択</label>
          <input type="file" name="update_zip" accept=".zip" required
                 style="padding:6px;background:var(--bg);border:1px solid var(--border);border-radius:6px;width:100%;color:var(--text)">
        </div>
        <div style="font-size:11px;color:var(--muted);margin-bottom:12px">
          ※ アップロード中はページを閉じないでください<br>
          ※ config/ storage/ のデータは上書きされません
        </div>
        <button type="submit" class="btn btn-primary"
                onclick="return confirm('アップデートを実行しますか？\n実行中はサービスが一時中断されます。')">
          アップロードしてアップデート
        </button>
      </form>
    </div>
  </div>

</div>

<!-- アップデート手順 -->
<div class="card" style="max-width:800px;margin-top:16px">
  <div class="card-header">アップデート手順</div>
  <div class="card-body" style="font-size:13px;color:var(--muted);line-height:2">
    <ol style="padding-left:20px">
      <li>最新のZIPファイル（<code>ai-art-vX.X.X-final.zip</code>）を入手する</li>
      <li>上の「ZIPファイルを選択」からZIPを選ぶ</li>
      <li>「アップロードしてアップデート」ボタンをクリック</li>
      <li>完了メッセージが表示されたらページを再読み込み</li>
    </ol>
    <p style="margin-top:12px">アップデートで更新されるもの：<code>app/</code> 配下のファイル、<code>index.php</code>、<code>worker.php</code></p>
    <p>アップデートで保持されるもの：<code>config/db.php</code>、<code>config/installed.lock</code>、<code>storage/</code>、<code>uploads/</code></p>
  </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
