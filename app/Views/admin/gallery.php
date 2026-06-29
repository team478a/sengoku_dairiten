<?php
$pageTitle = 'ギャラリー';
ob_start();
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <p style="font-size:13px;color:var(--muted);margin:0">生成された画像 全<?= number_format($total) ?>枚</p>
</div>

<?php if (empty($images)): ?>
<div class="card"><div class="card-body" style="text-align:center;color:var(--muted);padding:40px">まだ画像がありません</div></div>
<?php else: ?>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px">
  <?php foreach ($images as $img):
    $src = $img['preview_url'] ?: $img['image_url'];
    if (!$src) continue;
  ?>
  <div style="position:relative;aspect-ratio:1;border-radius:10px;overflow:hidden;background:var(--bg);group">
    <a href="<?= htmlspecialchars($img['image_url'] ?: $src) ?>" target="_blank">
      <img src="<?= htmlspecialchars($src) ?>" loading="lazy" alt=""
           style="width:100%;height:100%;object-fit:cover;display:block">
    </a>
    <div style="position:absolute;bottom:0;left:0;right:0;background:linear-gradient(transparent,rgba(0,0,0,.7));padding:14px 6px 5px;font-size:10px;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
      <?= htmlspecialchars($img['display_name'] ?: '受講生') ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($totalPages > 1): ?>
<div style="display:flex;gap:6px;justify-content:center;margin-top:20px;flex-wrap:wrap">
  <?php for ($p = 1; $p <= $totalPages; $p++): ?>
    <?php if ($p == $page): ?>
    <span class="btn btn-primary btn-sm"><?= $p ?></span>
    <?php else: ?>
    <a href="/admin/gallery?page=<?= $p ?>" class="btn btn-secondary btn-sm"><?= $p ?></a>
    <?php endif; ?>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
