<?php
$pageTitle = '依頼詳細 #' . $request['id'];
ob_start();

$promptA = null; $promptB = null;
foreach ($prompts as $p) {
    if ($p['prompt_type'] === 'A') $promptA = $p;
    if ($p['prompt_type'] === 'B') $promptB = $p;
}
$imagesA = array_values(array_filter($images, fn($i) => $i['prompt_type'] === 'A'));
$imagesB = array_values(array_filter($images, fn($i) => $i['prompt_type'] === 'B'));
?>
<div style="display:flex;gap:8px;margin-bottom:16px;align-items:center">
  <a href="/admin/image-requests" class="btn btn-secondary btn-sm">← 一覧</a>
  <?php if (in_array($request['status'], ['failed','completed'])): ?>
  <form method="POST" action="/admin/image-requests/<?= $request['id'] ?>
<?= csrf_field() ?>/retry" style="display:inline">
    <button type="submit" class="btn btn-sm" style="background:rgba(245,158,11,.2);color:var(--warning);border:1px solid rgba(245,158,11,.3)">再生成</button>
  </form>
  <?php endif; ?>
  <span class="badge-status badge-<?= $request['status'] ?>" style="margin-left:auto"><?= $request['status'] ?></span>
</div>

<!-- 受講者情報 + 入力 -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
  <div class="card">
    <div class="card-header">受講者情報</div>
    <div class="card-body" style="font-size:13px">
      <div style="margin-bottom:6px"><span style="color:var(--muted)">名前：</span><?= htmlspecialchars($request['display_name'] ?? '—') ?></div>
      <div style="margin-bottom:6px"><span style="color:var(--muted)">LINE ID：</span><code style="font-size:11px"><?= htmlspecialchars($request['line_user_id']) ?></code></div>
      <div style="margin-bottom:6px"><span style="color:var(--muted)">依頼日時：</span><?= date('Y/m/d H:i:s', strtotime($request['created_at'])) ?></div>
      <div><span style="color:var(--muted)">入力タイプ：</span><?= htmlspecialchars($request['input_type']) ?></div>
    </div>
  </div>
  <div class="card">
    <div class="card-header">入力テキスト</div>
    <div class="card-body">
      <div class="prompt-box"><?= htmlspecialchars($request['input_text']) ?></div>
      <?php if ($promptA): ?>
      <div style="margin-top:10px;font-size:12px;color:var(--muted)">AIの解釈：<?= htmlspecialchars($promptA['input_summary_ja'] ?? '—') ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($request['error_message']): ?>
<div class="alert alert-error" style="margin-bottom:16px">エラー：<?= htmlspecialchars($request['error_message']) ?></div>
<?php endif; ?>

<!-- Prompt A -->
<?php if ($promptA): ?>
<div class="card" style="margin-bottom:12px">
  <div class="card-header" style="color:var(--accent2)">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
    Prompt A — <?= htmlspecialchars($promptA['title_ja'] ?? '') ?>
  </div>
  <div class="card-body">
    <div class="prompt-box" style="margin-bottom:12px"><?= htmlspecialchars($promptA['prompt_en']) ?></div>
    <?php if ($promptA['safety_notes']): ?>
    <div style="font-size:12px;color:var(--warning)">⚠ <?= htmlspecialchars($promptA['safety_notes']) ?></div>
    <?php endif; ?>
    <?php if ($imagesA): ?>
    <div class="image-grid" style="margin-top:12px">
      <?php foreach ($imagesA as $img): ?>
      <a href="<?= htmlspecialchars($img['image_url']) ?>" target="_blank">
        <img src="<?= htmlspecialchars($img['image_url']) ?>" alt="A-<?= $img['image_no'] ?>" loading="lazy">
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="color:var(--muted);font-size:12px;margin-top:8px">画像はまだ生成されていません</div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Prompt B -->
<?php if ($promptB): ?>
<div class="card" style="margin-bottom:12px">
  <div class="card-header" style="color:#c084fc">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
    Prompt B — <?= htmlspecialchars($promptB['title_ja'] ?? '') ?>
  </div>
  <div class="card-body">
    <div class="prompt-box" style="margin-bottom:12px"><?= htmlspecialchars($promptB['prompt_en']) ?></div>
    <?php if ($promptB['safety_notes']): ?>
    <div style="font-size:12px;color:var(--warning)">⚠ <?= htmlspecialchars($promptB['safety_notes']) ?></div>
    <?php endif; ?>
    <?php if ($imagesB): ?>
    <div class="image-grid" style="margin-top:12px">
      <?php foreach ($imagesB as $img): ?>
      <a href="<?= htmlspecialchars($img['image_url']) ?>" target="_blank">
        <img src="<?= htmlspecialchars($img['image_url']) ?>" alt="B-<?= $img['image_no'] ?>" loading="lazy">
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="color:var(--muted);font-size:12px;margin-top:8px">画像はまだ生成されていません</div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php if (!$promptA && !$promptB): ?>
<div class="alert alert-info">プロンプトはまだ生成されていません。ステータス：<?= $request['status'] ?></div>
<?php endif; ?>

<!-- ログ -->
<?php if ($logs): ?>
<div class="card">
  <div class="card-header">処理ログ</div>
  <div class="card-body">
    <?php foreach ($logs as $log): ?>
    <div class="log-item">
      <span class="log-time"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
      <span class="log-level-<?= $log['log_level'] ?>">[<?= strtoupper($log['log_level']) ?>]</span>
      <span style="color:var(--muted)">[<?= $log['log_type'] ?>]</span>
      <span><?= htmlspecialchars($log['message']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
