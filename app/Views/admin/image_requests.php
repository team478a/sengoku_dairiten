<?php
$pageTitle = '依頼一覧';
ob_start();
?>
<form method="GET" class="filter-bar">
  <input type="text" name="keyword" placeholder="キーワード検索" value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>">
  <select name="status">
    <option value="">全ステータス</option>
    <?php foreach (['received','analyzing','prompt_generated','generating','uploading','sending','completed','failed','canceled'] as $s): ?>
    <option value="<?= $s ?>" <?= ($_GET['status'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
    <?php endforeach; ?>
  </select>
  <input type="date" name="date" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>">
  <button type="submit" class="btn btn-primary btn-sm">絞り込み</button>
  <a href="/admin/image-requests" class="btn btn-secondary btn-sm">リセット</a>
</form>

<div class="card">
  <div class="card-header">
    全 <?= number_format($total) ?> 件
    <?php if ($total > 0): ?>
    <span style="color:var(--muted);font-weight:400;font-size:11px">（ページ <?= $page ?>/<?= $totalPages ?>）</span>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>ID</th><th>ユーザー</th><th>入力タイプ</th><th>入力内容</th><th>画像</th><th>ステータス</th><th>日時</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($requests as $r): ?>
      <tr>
        <td style="color:var(--muted)">#<?= $r['id'] ?></td>
        <td><?= htmlspecialchars($r['display_name'] ?? '—') ?></td>
        <td style="color:var(--muted);font-size:11px"><?php
          $itLabels = ['survey'=>'アンケート','free'=>'自由記述','simple_keywords'=>'キーワード','free_text'=>'自由記述'];
          echo htmlspecialchars($itLabels[$r['input_type']] ?? $r['input_type']);
        ?></td>
        <td><?= htmlspecialchars(mb_strimwidth($r['input_text'], 0, 40, '…')) ?></td>
        <td><?= $r['image_count'] ?>/8</td>
        <td><span class="badge-status badge-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
        <td style="color:var(--muted);white-space:nowrap"><?= date('m/d H:i', strtotime($r['created_at'])) ?></td>
        <td><a href="/admin/image-requests/<?= $r['id'] ?>" class="btn btn-sm btn-secondary">詳細</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($requests)): ?>
      <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:32px">依頼がありません</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <?php
    $q = array_merge($_GET, ['page' => $i]);
    $qs = http_build_query($q);
    ?>
    <?php if ($i === $page): ?>
      <span><?= $i ?></span>
    <?php else: ?>
      <a href="/admin/image-requests?<?= $qs ?>"><?= $i ?></a>
    <?php endif; ?>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
