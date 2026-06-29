<?php
$pageTitle = 'カレンダー';
ob_start();

$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int)date('t', $firstDay);
$startWeekday = (int)date('w', $firstDay); // 0=日
$prevM = $month - 1; $prevY = $year; if ($prevM < 1) { $prevM = 12; $prevY--; }
$nextM = $month + 1; $nextY = $year; if ($nextM > 12) { $nextM = 1; $nextY++; }
$today = date('Y-m-d');
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <div style="display:flex;gap:8px;align-items:center">
    <a href="/admin/calendar?y=<?= $prevY ?>&m=<?= $prevM ?>" class="btn btn-secondary btn-sm">← 前月</a>
    <h2 style="font-size:18px;font-weight:700;margin:0"><?= $year ?>年<?= $month ?>月</h2>
    <a href="/admin/calendar?y=<?= $nextY ?>&m=<?= $nextM ?>" class="btn btn-secondary btn-sm">翌月 →</a>
    <a href="/admin/calendar" class="btn btn-secondary btn-sm">今月</a>
  </div>
  <a href="/admin/classes/create" class="btn btn-primary btn-sm">＋ 開催日を追加</a>
</div>

<div class="card">
  <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:1px;background:var(--border)">
    <?php
    $weekdays = ['日','月','火','水','木','金','土'];
    foreach ($weekdays as $i => $wd):
      $col = $i === 0 ? '#f87171' : ($i === 6 ? '#60a5fa' : 'var(--muted)');
    ?>
    <div style="background:var(--surface);padding:8px;text-align:center;font-size:12px;font-weight:600;color:<?= $col ?>"><?= $wd ?></div>
    <?php endforeach; ?>

    <?php
    // 月初の空白
    for ($i = 0; $i < $startWeekday; $i++):
    ?>
    <div style="background:var(--bg);min-height:90px"></div>
    <?php endfor; ?>

    <?php for ($d = 1; $d <= $daysInMonth; $d++):
      $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $d);
      $events = $byDate[$dateStr] ?? [];
      $isToday = ($dateStr === $today);
      $weekday = ($startWeekday + $d - 1) % 7;
      $dateColor = $weekday === 0 ? '#f87171' : ($weekday === 6 ? '#60a5fa' : 'var(--text)');
    ?>
    <div style="background:var(--surface);min-height:90px;padding:6px;<?= $isToday ? 'box-shadow:inset 0 0 0 2px var(--accent)' : '' ?>">
      <div style="font-size:13px;font-weight:600;color:<?= $dateColor ?>;margin-bottom:4px"><?= $d ?></div>
      <?php foreach ($events as $ev):
        $cap = (int)$ev['capacity'];
        $app = (int)$ev['total_applicants'];
        $full = $cap > 0 && $app >= $cap;
        $canceled = $ev['status'] === 'canceled';
      ?>
      <a href="/admin/classes/<?= $ev['id'] ?>/edit" style="display:block;text-decoration:none;margin-bottom:3px">
        <div style="background:<?= $canceled ? 'var(--border)' : ($full ? 'rgba(248,113,113,.2)' : 'rgba(124,106,247,.2)') ?>;border-radius:4px;padding:3px 5px;font-size:10px;line-height:1.3;<?= $canceled ? 'text-decoration:line-through;opacity:.6' : '' ?>">
          <div style="color:var(--text);font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= substr($ev['start_time'],0,5) ?> <?= htmlspecialchars($ev['title']) ?></div>
          <div style="color:<?= $full ? '#f87171' : 'var(--accent2)' ?>">予約<?= $app ?>/<?= $cap ?><?= $full ? ' 満席' : '' ?></div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endfor; ?>

    <?php
    // 月末の空白
    $totalCells = $startWeekday + $daysInMonth;
    $trailing = (7 - ($totalCells % 7)) % 7;
    for ($i = 0; $i < $trailing; $i++):
    ?>
    <div style="background:var(--bg);min-height:90px"></div>
    <?php endfor; ?>
  </div>
</div>

<div style="margin-top:12px;font-size:12px;color:var(--muted);display:flex;gap:16px">
  <span><span style="display:inline-block;width:12px;height:12px;background:rgba(124,106,247,.2);border-radius:3px;vertical-align:middle"></span> 受付中</span>
  <span><span style="display:inline-block;width:12px;height:12px;background:rgba(248,113,113,.2);border-radius:3px;vertical-align:middle"></span> 満席</span>
  <span>日付の教室をクリックで編集</span>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
