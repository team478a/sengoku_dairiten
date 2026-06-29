<?php
$pageTitle = 'ユーザー管理';
ob_start();
?>
<form method="GET" class="filter-bar">
  <input type="text" name="keyword" placeholder="名前で検索" value="<?= htmlspecialchars($_GET['keyword'] ?? '') ?>">
  <select name="status">
    <option value="">全ステータス</option>
    <option value="active"    <?= ($_GET['status']??'')==='active'    ?'selected':'' ?>>active（有効）</option>
    <option value="suspended" <?= ($_GET['status']??'')==='suspended' ?'selected':'' ?>>suspended（停止）</option>
    <option value="banned"    <?= ($_GET['status']??'')==='banned'    ?'selected':'' ?>>banned（禁止）</option>
  </select>
  <button type="submit" class="btn btn-primary btn-sm">検索</button>
  <a href="/admin/users" class="btn btn-secondary btn-sm">リセット</a>
</form>

<div class="card">
  <div class="card-header">全 <?= number_format($total) ?> 人</div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>名前</th><th>ステータス</th><th>参加教室数</th><th>累計依頼</th><th>本日依頼</th><th>登録日</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <?php if ($u['picture_url']): ?>
            <img src="<?= htmlspecialchars($u['picture_url']) ?>" style="width:28px;height:28px;border-radius:50%;object-fit:cover">
            <?php endif; ?>
            <?= htmlspecialchars($u['display_name'] ?? '—') ?>
          </div>
        </td>
        <td>
          <?php
          switch($u['status']) {
            case 'active':    $cls='badge-completed'; break;
            case 'suspended': $cls='badge-warning';   break;
            case 'banned':    $cls='badge-failed';    break;
            default:          $cls='badge-received';  break;
          }
          ?>
          <span class="badge-status <?= $cls ?>"><?= htmlspecialchars($u['status']) ?></span>
        </td>
        <td><?= $u['total_classes'] ?></td>
        <td><?= $u['total_requests'] ?></td>
        <td><?= $u['today_requests'] ?> 件</td>
        <td style="color:var(--muted)"><?= $u['created_at'] ? date('Y/m/d', strtotime($u['created_at'])) : '—' ?></td>
        <td><a href="/admin/users/<?= $u['id'] ?>" class="btn btn-secondary btn-sm">詳細</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($users)): ?>
      <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:32px">ユーザーがいません</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <?php $qs = http_build_query(array_merge($_GET, ['page'=>$i])); ?>
    <?php if ($i === $page): ?><span><?= $i ?></span>
    <?php else: ?><a href="/admin/users?<?= $qs ?>"><?= $i ?></a><?php endif; ?>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
