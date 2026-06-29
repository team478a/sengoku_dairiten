<?php
$pageTitle = 'ダッシュボード';
ob_start();
?>
<?php if (($stats['failed_count'] ?? 0) > 0): ?>
<div class="alert alert-error" style="margin-bottom:16px">
  ⚠ 本日 <?= (int)$stats['failed_count'] ?> 件の生成が失敗しています。
  <a href="/admin/image-requests?status=failed" style="color:inherit;text-decoration:underline">失敗した依頼を確認</a>
</div>
<?php endif; ?>
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">本日の依頼</div>
    <div class="stat-value accent"><?= number_format($stats['today_requests']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">本日の生成枚数</div>
    <div class="stat-value accent"><?= number_format($stats['today_images']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">本日の失敗</div>
    <div class="stat-value <?= $stats['failed_count'] > 0 ? 'danger' : 'success' ?>"><?= number_format($stats['failed_count']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">処理中</div>
    <div class="stat-value warning"><?= number_format($stats['processing_count']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">累計依頼</div>
    <div class="stat-value"><?= number_format($stats['total_requests']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-label">累計生成枚数</div>
    <div class="stat-value"><?= number_format($stats['total_images']) ?></div>
  </div>
</div>

<!-- ===== 運用監視パネル ===== -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px">

  <!-- ① cron死活監視 -->
  <div class="card" style="<?= $monitor['worker_alert'] ? 'border-color:var(--danger)' : '' ?>">
    <div class="card-header">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
      自動処理（cron）
    </div>
    <div class="card-body">
      <?php if ($monitor['worker_alert']): ?>
        <div style="font-size:22px;font-weight:800;color:var(--danger)">⚠ 停止の疑い</div>
        <div style="font-size:12px;color:var(--muted);margin-top:6px">
          <?php if ($monitor['worker_last_run']): ?>
            最終実行：<?= date('m/d H:i', strtotime($monitor['worker_last_run'])) ?>
            （<?= floor($monitor['worker_diff_sec'] / 60) ?>分前）
          <?php else: ?>
            まだ一度も実行されていません
          <?php endif; ?>
        </div>
        <div style="font-size:11px;color:var(--danger);margin-top:8px">cronの設定を確認してください</div>
      <?php else: ?>
        <div style="font-size:22px;font-weight:800;color:var(--success)">✓ 正常</div>
        <div style="font-size:12px;color:var(--muted);margin-top:6px">
          最終実行：<?= date('H:i:s', strtotime($monitor['worker_last_run'])) ?>
          （<?= $monitor['worker_diff_sec'] ?>秒前）
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ② LINE当月送信数 -->
  <div class="card" style="<?= $monitor['line_push_alert'] ? 'border-color:var(--warning)' : '' ?>">
    <div class="card-header">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      LINE送信数（今月）
    </div>
    <div class="card-body">
      <div style="font-size:22px;font-weight:800;color:<?= $monitor['line_push_alert'] ? 'var(--warning)' : 'var(--accent2)' ?>">
        <?= number_format($monitor['line_push_count']) ?>
        <span style="font-size:13px;color:var(--muted);font-weight:400">/ <?= number_format($monitor['line_push_limit']) ?>通</span>
      </div>
      <?php
        $pct = $monitor['line_push_limit'] > 0 ? min(100, round($monitor['line_push_count'] / $monitor['line_push_limit'] * 100)) : 0;
      ?>
      <div style="background:var(--bg);border-radius:6px;height:6px;margin-top:10px;overflow:hidden">
        <div style="width:<?= $pct ?>%;height:100%;background:<?= $monitor['line_push_alert'] ? 'var(--warning)' : 'var(--accent)' ?>"></div>
      </div>
      <div style="font-size:11px;color:var(--muted);margin-top:6px">
        <?php if ($monitor['line_push_alert']): ?>
          <span style="color:var(--warning)">⚠ 上限が近づいています</span>
        <?php else: ?>
          残り約<?= number_format(max(0, $monitor['line_push_limit'] - $monitor['line_push_count'])) ?>通
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ③ Stability AIクレジット -->
  <div class="card">
    <div class="card-header">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
      画像生成エンジン
    </div>
    <div class="card-body">
      <?php $engine = $settings['image_engine'] ?? 'stability'; ?>
      <div style="font-size:13px;margin-bottom:8px">
        現在：<strong style="color:var(--accent2)"><?= $engine === 'grok' ? 'Grok（xAI）' : 'Stability AI' ?></strong>
      </div>
      <?php if ($engine === 'stability'): ?>
        <?php if ($monitor['stability_credits'] !== ''): ?>
          <?php $cr = (float)$monitor['stability_credits']; ?>
          <div style="font-size:20px;font-weight:800;color:<?= $cr < 50 ? 'var(--danger)' : 'var(--success)' ?>">
            <?= number_format($cr, 1) ?>
            <span style="font-size:12px;color:var(--muted);font-weight:400">クレジット</span>
          </div>
          <?php if ($cr < 50): ?>
          <div style="font-size:11px;color:var(--danger);margin-top:4px">⚠ 残高が少なくなっています</div>
          <?php endif; ?>
        <?php else: ?>
          <div style="font-size:13px;color:var(--muted)">残高未取得</div>
        <?php endif; ?>
        <button onclick="refreshCredits(this)" class="btn btn-secondary btn-sm" style="margin-top:10px">残高を更新</button>
      <?php else: ?>
        <div style="font-size:12px;color:var(--muted)">Grokは「データ共有プログラム」で月最大$175の無料枠があります。残高はxAIコンソールで確認してください。</div>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
function refreshCredits(btn) {
  btn.disabled = true;
  btn.textContent = '取得中...';
  fetch('/admin/stability-credits', { method: 'POST' })
    .then(r => r.json())
    .then(d => { location.reload(); })
    .catch(() => { btn.disabled = false; btn.textContent = '残高を更新'; });
}
</script>

<div class="card">
  <div class="card-header">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    最近の依頼
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>ID</th><th>ユーザー</th><th>入力</th><th>ステータス</th><th>日時</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($recent as $r): ?>
      <tr>
        <td style="color:var(--muted)">#<?= $r['id'] ?></td>
        <td><?= htmlspecialchars($r['display_name'] ?? '—') ?></td>
        <td><?= htmlspecialchars(mb_strimwidth($r['input_text'], 0, 30, '…')) ?></td>
        <td><span class="badge-status badge-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
        <td style="color:var(--muted)"><?= date('m/d H:i', strtotime($r['created_at'])) ?></td>
        <td><a href="/admin/image-requests/<?= $r['id'] ?>" class="row-link btn btn-sm btn-secondary">詳細</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($recent)): ?>
      <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:24px">まだ依頼がありません</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
