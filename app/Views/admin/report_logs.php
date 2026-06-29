<?php
$pageTitle = '操作ログ';
ob_start();

$actionLabels = [
    'class_create' => '教室作成', 'class_update' => '教室編集', 'class_cancel' => '教室キャンセル',
    'attendance_approve' => '参加承認', 'attendance_reject' => '参加却下', 'attendance_approve_all' => '一括承認',
    'reminder_send' => 'リマインダー送信', 'broadcast' => '一斉送信',
    'user_delete' => '受講生削除', 'settings_update' => '設定変更',
    'richmenu_apply' => 'リッチメニュー反映', 'manager_create' => '管理者追加', 'manager_delete' => '管理者削除',
];
?>

<div class="card">
  <div class="card-header">🔍 操作ログ（直近200件）</div>
  <div class="card-body" style="overflow-x:auto">
    <table class="data-table">
      <thead><tr><th>日時</th><th>操作者</th><th>操作</th><th>対象</th><th>詳細</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $log): ?>
      <tr>
        <td style="white-space:nowrap;font-size:12px;color:var(--muted)"><?= date('m/d H:i', strtotime($log['created_at'])) ?></td>
        <td style="white-space:nowrap"><?= htmlspecialchars($log['admin_name'] ?: '—') ?></td>
        <td><span class="badge-status badge-received"><?= htmlspecialchars($actionLabels[$log['action']] ?? $log['action']) ?></span></td>
        <td style="font-size:13px"><?= htmlspecialchars($log['target'] ?: '') ?></td>
        <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($log['detail'] ?: '') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($logs)): ?>
      <tr><td colspan="5" style="text-align:center;color:var(--muted);padding:20px">ログがありません</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
