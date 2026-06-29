<?php
$pageTitle = '決済履歴';
ob_start();

$kindLabels = ['attendance'=>'教室参加','ticket'=>'チケット','subscription'=>'サブスク'];
$currentKind = $_GET['kind'] ?? '';
?>

<?php if (isset($_GET['refunded'])): ?><div class="alert alert-success">返金を実行しました。</div><?php endif; ?>
<?php if (isset($_GET['error'])): ?><div class="alert alert-error">処理に失敗しました。Stripeダッシュボードをご確認ください。</div><?php endif; ?>

<!-- サマリー -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
  <div class="stat-card"><div class="stat-label">累計売上</div><div class="stat-value">¥<?= number_format($summary['total_paid'] ?? 0) ?></div></div>
  <div class="stat-card"><div class="stat-label">今月の売上</div><div class="stat-value accent">¥<?= number_format($summary['month_paid'] ?? 0) ?></div></div>
  <div class="stat-card"><div class="stat-label">決済件数</div><div class="stat-value"><?= number_format($summary['count_paid'] ?? 0) ?></div></div>
  <div class="stat-card"><div class="stat-label">返金累計</div><div class="stat-value danger">¥<?= number_format($summary['total_refunded'] ?? 0) ?></div></div>
</div>

<!-- フィルタ -->
<div style="display:flex;gap:8px;margin-bottom:16px">
  <a href="/admin/payments" class="btn btn-sm <?= $currentKind===''?'btn-primary':'btn-secondary' ?>">すべて</a>
  <a href="/admin/payments?kind=attendance" class="btn btn-sm <?= $currentKind==='attendance'?'btn-primary':'btn-secondary' ?>">教室参加</a>
  <a href="/admin/payments?kind=ticket" class="btn btn-sm <?= $currentKind==='ticket'?'btn-primary':'btn-secondary' ?>">チケット</a>
  <a href="/admin/payments?kind=subscription" class="btn btn-sm <?= $currentKind==='subscription'?'btn-primary':'btn-secondary' ?>">サブスク</a>
</div>

<div class="card">
  <div class="card-body" style="overflow-x:auto">
    <table class="data-table">
      <thead><tr><th>日時</th><th>受講生</th><th>種別</th><th>内容</th><th>金額</th><th>状態</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($payments as $p): ?>
      <tr>
        <td style="white-space:nowrap;font-size:12px;color:var(--muted)"><?= date('m/d H:i', strtotime($p['created_at'])) ?></td>
        <td><?= htmlspecialchars($p['display_name'] ?: '—') ?></td>
        <td><span class="badge-status badge-received"><?= $kindLabels[$p['kind']] ?? $p['kind'] ?></span></td>
        <td style="font-size:13px"><?= htmlspecialchars($p['description'] ?: '') ?></td>
        <td style="font-weight:600">¥<?= number_format($p['amount']) ?></td>
        <td>
          <?php if ($p['status'] === 'refunded'): ?>
            <span class="badge-status badge-failed">返金済</span>
          <?php else: ?>
            <span class="badge-status badge-completed">完了</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($p['status'] === 'paid' && $p['amount'] > 0 && !empty($p['stripe_session_id'])): ?>
          <form method="POST" action="/admin/payments/<?= $p['id'] ?>
<?= csrf_field() ?>/refund"
                onsubmit="return confirm('¥<?= number_format($p['amount']) ?> を返金しますか？この操作は取り消せません。')">
            <button type="submit" class="btn btn-danger btn-sm">返金</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($payments)): ?>
      <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:20px">決済履歴がありません</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
