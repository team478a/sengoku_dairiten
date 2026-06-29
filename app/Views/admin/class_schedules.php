<?php
$pageTitle = '教室スケジュール';
ob_start();
$created     = !empty($_GET['created']);
$updated     = !empty($_GET['updated']);
$approvedAll = !empty($_GET['approved_all']);
?>

<?php if ($created):  ?><div class="alert alert-success">スケジュールを作成しました。</div><?php endif; ?>
<?php if ($updated):  ?><div class="alert alert-success">スケジュールを更新しました。</div><?php endif; ?>
<?php if ($approvedAll): ?><div class="alert alert-success">全員を承認しLINE通知しました。</div><?php endif; ?>
<?php if (isset($_GET['reminded'])): ?><div class="alert alert-success">リマインダーを<?= (int)$_GET['reminded'] ?>人に送信しました。</div><?php endif; ?>
<?php if (isset($_GET['canceled'])): ?><div class="alert alert-success">教室を中止し、予約者<?= (int)$_GET['canceled'] ?>人に通知しました。</div><?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <div></div>
  <a href="/admin/classes/create" class="btn btn-primary btn-sm">＋ 開催日を追加</a>
</div>

<!-- 本日の参加申請 -->
<?php if ($today): ?>
<div class="card" style="margin-bottom:20px;border-color:var(--accent)">
  <div class="card-header" style="color:var(--accent2)">
    🎓 本日の教室 — <?= htmlspecialchars($today['title']) ?>
    （<?= date('m/d', strtotime($today['class_date'])) ?>
    <?= substr($today['start_time'],0,5) ?>〜<?= substr($today['end_time'],0,5) ?>）
    <span style="margin-left:auto;font-size:11px;color:var(--muted)">
      チェックイン受付：<?= substr($today['checkin_open'],0,5) ?>〜<?= substr($today['checkin_close'],0,5) ?>
    </span>
  </div>
  <div class="card-body">
    <?php if ($attendances): ?>
    <div style="display:flex;justify-content:space-between;margin-bottom:12px;align-items:center">
      <div style="font-size:13px;color:var(--muted)">
        <?= count(array_filter($attendances, fn($a)=>$a['status']==='approved')) ?> 人承認済み /
        <?= count(array_filter($attendances, fn($a)=>!empty($a['attended_at']))) ?> 人参加 /
        <?= count(array_filter($attendances, fn($a)=>$a['status']==='pending')) ?> 人申請中 /
        定員 <?= $today['capacity'] ?> 人
      </div>
      <?php $pendingCount = count(array_filter($attendances, fn($a)=>$a['status']==='pending')); ?>
      <?php if ($pendingCount > 0): ?>
      <form method="POST" action="/admin/classes/<?= $today['id'] ?>
<?= csrf_field() ?>/approve-all"
            onsubmit="return confirm('全員（<?= $pendingCount ?>人）を承認してLINE通知しますか？')">
        <button type="submit" class="btn btn-success btn-sm">全員承認 & 通知（<?= $pendingCount ?>人）</button>
      </form>
      <?php endif; ?>
    </div>

    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>名前</th><th>申請時刻</th><th>ステータス</th><th>本日生成</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($attendances as $att): ?>
        <tr>
          <td><?= htmlspecialchars($att['display_name'] ?? '—') ?></td>
          <td style="color:var(--muted)"><?= date('H:i', strtotime($att['created_at'])) ?></td>
          <td>
            <?php if ($att['status'] === 'approved'): ?>
              <span class="badge-status badge-completed">承認済み</span>
              <?php if (!empty($att['attended_at'])): ?>
              <span class="badge-status" style="background:rgba(124,106,247,.2);color:var(--accent2)">🎉 参加済み</span>
              <?php
                $ps = $att['payment_status'] ?? 'unpaid';
                $psMap = [
                  'free'         => ['🎁 無料', 'background:rgba(148,163,184,.2);color:#94a3b8'],
                  'subscription' => ['🌟 サブスク', 'background:rgba(251,191,36,.2);color:#d97706'],
                  'ticket'       => ['🎫 チケット', 'background:rgba(96,165,250,.2);color:#3b82f6'],
                  'paid'         => ['✅ 集金済', 'background:rgba(34,197,94,.2);color:#16a34a'],
                  'unpaid'       => ['💴 未集金', 'background:rgba(248,113,113,.2);color:#dc2626'],
                ];
                [$plabel, $pstyle] = $psMap[$ps] ?? $psMap['unpaid'];
              ?>
              <span class="badge-status" style="<?= $pstyle ?>"><?= $plabel ?></span>
              <?php if ($ps === 'unpaid' && (int)($att['payment_amount'] ?? 0) > 0): ?>
              <span style="font-size:11px;color:var(--muted)">（<?= (int)$att['payment_amount'] ?>円）</span>
              <?php endif; ?>
              <?php endif; ?>
            <?php elseif ($att['status'] === 'pending'): ?>
              <span class="badge-status badge-received">申請中</span>
            <?php else: ?>
              <span class="badge-status badge-failed">却下</span>
            <?php endif; ?>
          </td>
          <td><?= $att['today_requests'] ?> / <?= $today['max_requests'] ?>件</td>
          <td style="display:flex;gap:6px">
            <?php if ($att['status'] === 'pending'): ?>
            <form method="POST" action="/admin/classes/attendance/<?= $att['id'] ?>
<?= csrf_field() ?>/approve">
              <button type="submit" class="btn btn-success btn-sm">承認</button>
            </form>
            <form method="POST" action="/admin/classes/attendance/<?= $att['id'] ?>
<?= csrf_field() ?>/reject">
              <button type="submit" class="btn btn-danger btn-sm"
                      onclick="this.form.reason.value=prompt('却下理由（任意）') ?? ''">却下</button>
              <input type="hidden" name="reason" value="">
            </form>
            <?php endif; ?>
            <?php if (!empty($att['attended_at']) && ($att['payment_status'] ?? '') === 'unpaid'): ?>
            <form method="POST" action="/admin/classes/attendance/<?= $att['id'] ?>
<?= csrf_field() ?>/paid"
                  onsubmit="return confirm('集金済みにしますか？')">
              <button type="submit" class="btn btn-success btn-sm">💴 集金</button>
            </form>
            <?php endif; ?>
            <a href="/admin/users/<?= (int)$att['user_id'] ?>" class="btn btn-secondary btn-sm">詳細</a>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div style="color:var(--muted);font-size:13px;text-align:center;padding:20px">まだ参加申請がありません</div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- スケジュール一覧 -->
<div class="card">
  <div class="card-header">開催スケジュール一覧</div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>開催日</th><th>タイトル</th><th>時間</th><th>予約人数</th><th>承認状況</th><th>ステータス</th><th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($schedules as $s): ?>
      <tr>
        <td style="font-weight:600"><?= date('Y/m/d', strtotime($s['class_date'])) ?></td>
        <td><?= htmlspecialchars($s['title']) ?></td>
        <td style="color:var(--muted);white-space:nowrap">
          <?= substr($s['start_time'],0,5) ?>〜<?= substr($s['end_time'],0,5) ?>
        </td>
        <td style="white-space:nowrap">
          <?php
            $cap = (int)$s['capacity'];
            $app = (int)$s['total_applicants'];
            $ratio = $cap > 0 ? min(100, round($app / $cap * 100)) : 0;
            $full = $cap > 0 && $app >= $cap;
          ?>
          <span style="font-weight:700;font-size:15px;color:<?= $full ? 'var(--danger)' : 'var(--accent2)' ?>"><?= $app ?></span>
          <span style="color:var(--muted);font-size:12px"> / 定員<?= $cap ?>人</span>
          <?php if ($full): ?>
          <span class="badge-status badge-failed" style="margin-left:4px">満席</span>
          <?php endif; ?>
          <div style="background:var(--bg);border-radius:4px;height:4px;margin-top:4px;overflow:hidden;width:120px">
            <div style="width:<?= $ratio ?>%;height:100%;background:<?= $full ? 'var(--danger)' : 'var(--accent)' ?>"></div>
          </div>
        </td>
        <td style="white-space:nowrap">
          <span style="color:#22c55e;font-weight:600"><?= $s['approved_count'] ?></span>
          <span style="color:var(--muted);font-size:12px">承認済み</span>
          <?php if ($s['pending_count'] > 0): ?>
          <br><span class="badge-status badge-received" style="margin-top:2px;display:inline-block"><?= $s['pending_count'] ?>人 承認待ち</span>
          <?php endif; ?>
          <?php if (($s['attended_count'] ?? 0) > 0): ?>
          <br><span style="color:var(--accent2);font-weight:600;font-size:13px">🎉 <?= $s['attended_count'] ?>人 参加</span>
          <?php endif; ?>
        </td>
        <td>
          <?php
          switch($s['status']) {
            case 'scheduled': $sClass='badge-received';  $sLabel='予定'; break;
            case 'active':    $sClass='badge-completed'; $sLabel='開催中'; break;
            case 'closed':    $sClass='badge-canceled';  $sLabel='終了'; break;
            case 'canceled':  $sClass='badge-failed';    $sLabel='キャンセル'; break;
            default:          $sClass='';                $sLabel=htmlspecialchars($s['status']); break;
          }
          ?>
          <span class="badge-status <?= $sClass ?>"><?= $sLabel ?></span>
        </td>
        <td style="display:flex;gap:4px">
          <a href="/admin/classes/<?= $s['id'] ?>/edit" class="btn btn-secondary btn-sm">編集</a>
          <?php if ($s['status'] !== 'canceled'): ?>
          <?php if (!empty($s['reminder_at'])): ?>
            <?php if (!empty($s['reminder_sent_at'])): ?>
              <span class="btn btn-sm" style="background:rgba(34,197,94,.15);color:#22c55e;cursor:default">送信済</span>
            <?php else: ?>
              <form method="POST" action="/admin/classes/<?= $s['id'] ?>
<?= csrf_field() ?>/remind"
                    onsubmit="return confirm('この教室の承認済み参加者にリマインダーを今すぐ送信しますか？')">
                <button type="submit" class="btn btn-secondary btn-sm">📣今すぐ送信</button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
          <form method="POST" action="/admin/classes/<?= $s['id'] ?>
<?= csrf_field() ?>/cancel"
                onsubmit="return confirm('この教室をキャンセルしますか？')">
            <button type="submit" class="btn btn-danger btn-sm">取消</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($schedules)): ?>
      <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:32px">スケジュールがありません</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
