<?php
$pageTitle = '出席履歴';
// statsがfalseの場合のフォールバック
if (empty($stats) || !is_array($stats)) {
    $stats = ['total' => 0, 'unique_users' => 0, 'total_classes' => 0];
}
$stats['total']         = $stats['total']         ?? 0;
$stats['unique_users']  = $stats['unique_users']  ?? 0;
$stats['total_classes'] = $stats['total_classes'] ?? 0;
ob_start();
?>

<div style="display:flex;gap:8px;margin-bottom:16px;align-items:center;flex-wrap:wrap">
  <form method="GET" style="display:flex;gap:8px;flex:1;flex-wrap:wrap">
    <input type="text" name="keyword" placeholder="受講生名で検索" value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>" style="flex:1;min-width:150px">
    <input type="date" name="from" value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">
    <input type="date" name="to"   value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">
    <button type="submit" class="btn btn-primary btn-sm">絞り込み</button>
    <a href="/admin/attendance" class="btn btn-secondary btn-sm">リセット</a>
  </form>
</div>

<!-- 統計 -->
<div class="stats-grid" style="margin-bottom:16px">
  <div class="stat-card">
    <div class="stat-label">総出席数</div>
    <div class="stat-value accent"><?= number_format($stats['total']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">ユニーク参加者</div>
    <div class="stat-value accent"><?= number_format($stats['unique_users']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">開催回数</div>
    <div class="stat-value"><?= number_format($stats['total_classes']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">平均参加者数</div>
    <div class="stat-value"><?= $stats['total_classes'] > 0 ? round($stats['total'] / $stats['total_classes'], 1) : 0 ?></div>
  </div>
</div>

<!-- 出席一覧 -->
<div class="card">
  <div class="card-header">出席履歴（<?= number_format($total) ?>件）</div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>日付</th><th>教室名</th><th>受講生</th><th>申請時刻</th><th>承認時刻</th><th>生成件数</th>
      </tr></thead>
      <tbody>
      <?php foreach ($attendances as $a): ?>
      <tr>
        <td style="font-weight:600;white-space:nowrap"><?= date('Y/m/d', strtotime($a['class_date'])) ?></td>
        <td><?= htmlspecialchars($a['title'] ?? '—') ?></td>
        <td>
          <a href="/admin/users/<?= $a['user_id'] ?>" style="color:var(--accent2);text-decoration:none">
            <?= htmlspecialchars($a['display_name'] ?? '—') ?>
          </a>
        </td>
        <td style="color:var(--muted)"><?= date('H:i', strtotime($a['created_at'])) ?></td>
        <td style="color:var(--muted)"><?= $a['approved_at'] ? date('H:i', strtotime($a['approved_at'])) : '—' ?></td>
        <td><?= $a['request_count'] ?>件</td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($attendances)): ?>
      <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:32px">出席履歴がありません</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ページング -->
<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
    <?php $qs = http_build_query(array_merge($_GET, ['page' => $i])); ?>
    <?php if ($i === $page): ?><span><?= $i ?></span>
    <?php else: ?><a href="/admin/attendance?<?= $qs ?>"><?= $i ?></a><?php endif; ?>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
