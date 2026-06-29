<?php
$pageTitle = '統計・エクスポート';
ob_start();
?>

<!-- サマリー -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
  <div class="stat-card"><div class="stat-label">受講生数</div><div class="stat-value"><?= number_format($summary['total_users']) ?></div></div>
  <div class="stat-card"><div class="stat-label">生成リクエスト</div><div class="stat-value"><?= number_format($summary['total_requests']) ?></div></div>
  <div class="stat-card"><div class="stat-label">生成画像数</div><div class="stat-value"><?= number_format($summary['total_images']) ?></div></div>
  <div class="stat-card"><div class="stat-label">開催教室数</div><div class="stat-value"><?= number_format($summary['total_classes']) ?></div></div>
</div>

<!-- CSVエクスポート -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header">📥 CSVエクスポート / バックアップ</div>
  <div class="card-body">
    <p style="font-size:13px;color:var(--muted);margin-bottom:12px">CSVはExcelで開ける形式（Shift_JIS）でダウンロードします。</p>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
      <a href="/admin/export/users" class="btn btn-secondary btn-sm">受講生一覧 CSV</a>
      <a href="/admin/export/attendance" class="btn btn-secondary btn-sm">参加履歴 CSV</a>
      <a href="/admin/export/requests" class="btn btn-secondary btn-sm">生成履歴 CSV</a>
    </div>
    <hr style="border:none;border-top:1px solid var(--border);margin:12px 0">
    <p style="font-size:13px;color:var(--muted);margin-bottom:8px">データベースのバックアップ（SQL形式）。定期的に保存しておくと安心です。</p>
    <a href="/admin/backup" class="btn btn-primary btn-sm">💾 データをバックアップ</a>
  </div>
</div>

<!-- 教室ごとの参加率 -->
<div class="card" style="margin-bottom:20px">
  <div class="card-header">📊 教室ごとの予約・参加率</div>
  <div class="card-body" style="overflow-x:auto">
    <table class="data-table">
      <thead><tr><th>開催日</th><th>教室</th><th>予約</th><th>承認</th><th>参加</th><th>参加率</th></tr></thead>
      <tbody>
      <?php foreach ($classStats as $c):
        $rate = $c['approved'] > 0 ? round($c['attended'] / $c['approved'] * 100) : 0;
      ?>
      <tr>
        <td style="white-space:nowrap"><?= date('m/d', strtotime($c['class_date'])) ?></td>
        <td><?= htmlspecialchars($c['title']) ?></td>
        <td><?= $c['reserved'] ?></td>
        <td><?= $c['approved'] ?></td>
        <td style="color:var(--accent2);font-weight:600"><?= $c['attended'] ?></td>
        <td>
          <div style="display:flex;align-items:center;gap:6px">
            <div style="background:var(--bg);border-radius:4px;height:6px;width:60px;overflow:hidden">
              <div style="width:<?= $rate ?>%;height:100%;background:<?= $rate >= 70 ? '#22c55e' : ($rate >= 40 ? '#fbbf24' : '#f87171') ?>"></div>
            </div>
            <span style="font-size:12px;font-weight:600"><?= $rate ?>%</span>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($classStats)): ?>
      <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:20px">データがありません</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- 月別生成数 -->
<div class="card">
  <div class="card-header">📈 月別生成リクエスト数（直近6か月）</div>
  <div class="card-body">
    <?php
      $max = 1;
      foreach ($monthly as $m) { $max = max($max, (int)$m['cnt']); }
    ?>
    <div style="display:flex;align-items:flex-end;gap:12px;height:160px;padding-top:10px">
      <?php foreach ($monthly as $m):
        $h = round($m['cnt'] / $max * 140);
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
        <div style="font-size:12px;font-weight:600;color:var(--accent2)"><?= $m['cnt'] ?></div>
        <div style="width:100%;max-width:48px;height:<?= $h ?>px;background:linear-gradient(to top,#7c6af7,#a78bfa);border-radius:6px 6px 0 0"></div>
        <div style="font-size:11px;color:var(--muted)"><?= substr($m['ym'],5,2) ?>月</div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($monthly)): ?>
      <div style="color:var(--muted);font-size:13px">データがありません</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
