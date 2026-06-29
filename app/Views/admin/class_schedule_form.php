<?php
$isEdit    = isset($schedule);
$pageTitle = $isEdit ? 'スケジュール編集' : 'スケジュール作成';
$action    = $isEdit ? "/admin/classes/{$schedule['id']}/update" : '/admin/classes';
$method    = 'POST';

$v = fn(string $k, string $d = '') => htmlspecialchars($schedule[$k] ?? $_POST[$k] ?? $d);

ob_start();
?>
<div style="margin-bottom:16px">
  <a href="/admin/classes" class="btn btn-secondary btn-sm">← 一覧へ戻る</a>
</div>

<div class="card" style="max-width:560px">
  <div class="card-header"><?= $pageTitle ?></div>
  <div class="card-body">
    <form method="POST" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>">
      <?= csrf_field() ?>
      <div class="form-group">
        <label>タイトル</label>
        <input type="text" name="title" value="<?= $v('title', 'AIアート教室') ?>" required>
      </div>
      <div class="form-group">
        <label>開催日</label>
        <input type="date" name="class_date" value="<?= $v('class_date') ?>" required>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>開始時刻</label>
          <input type="time" name="start_time" value="<?= $v('start_time', '10:00') ?>" required>
        </div>
        <div class="form-group">
          <label>終了時刻</label>
          <input type="time" name="end_time" value="<?= $v('end_time', '12:00') ?>" required>
        </div>
      </div>
      <hr class="divider">
      <p style="font-size:12px;color:var(--muted);margin-bottom:12px">
        チェックイン受付時間 — この時間帯だけ「参加する」が有効になります
      </p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>受付開始</label>
          <input type="time" name="checkin_open" value="<?= $v('checkin_open', '09:30') ?>" required>
        </div>
        <div class="form-group">
          <label>受付終了</label>
          <input type="time" name="checkin_close" value="<?= $v('checkin_close', '11:00') ?>" required>
        </div>
      </div>
      <hr class="divider">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label>定員（人）</label>
          <input type="number" name="capacity" value="<?= $v('capacity', '20') ?>" min="1" max="200" required>
        </div>
        <div class="form-group">
          <label>1人あたりの生成件数上限</label>
          <input type="number" name="max_requests" value="<?= $v('max_requests', '2') ?>" min="1" max="20" required>
        </div>
      </div>
      <div class="form-group">
        <label>参加費（円）</label>
        <input type="number" name="fee" value="<?= $v('fee') ?: '0' ?>" min="0" step="100" placeholder="0">
        <p style="font-size:11px;color:var(--muted);margin-top:4px">
          0なら無料教室。金額を設定すると、初回無料・サブスク会員無料・チケット保有者はチケット消費・それ以外は当日この金額を集金、と自動判定されます。
        </p>
      </div>

      <div class="form-group">
        <label>主催者</label>
        <input type="text" name="organizer" value="<?= $v('organizer') ?>" placeholder="例：戦国経済圏 運営事務局">
        <p style="font-size:11px;color:var(--muted);margin-top:4px">受講生のLINE案内（開催日案内・リマインダー）に表示されます。</p>
      </div>

      <div class="form-group">
        <label>開催形式</label>
        <?php $fmt = $v('event_format') ?: 'realtime'; ?>
        <select name="event_format" id="event_format" onchange="toggleFormat()">
          <option value="realtime" <?= $fmt === 'realtime' ? 'selected' : '' ?>>リアル会場</option>
          <option value="zoom"     <?= $fmt === 'zoom' ? 'selected' : '' ?>>オンライン（Zoom）</option>
          <option value="hybrid"   <?= $fmt === 'hybrid' ? 'selected' : '' ?>>ハイブリッド（会場＋Zoom）</option>
        </select>
      </div>

      <div class="form-group" id="field_location">
        <label>会場・場所</label>
        <input type="text" name="location" value="<?= $v('location') ?>" placeholder="例：神戸市中央区○○ビル3F">
        <p style="font-size:11px;color:var(--muted);margin-top:4px">受講生のLINE案内に表示されます。</p>
      </div>

      <div class="form-group" id="field_zoom">
        <label>Zoom URL</label>
        <input type="text" name="zoom_url" value="<?= $v('zoom_url') ?>" placeholder="https://zoom.us/j/...">
        <p style="font-size:11px;color:var(--muted);margin-top:4px">承認済み参加者のLINE案内・リマインダーに表示されます。</p>
      </div>

      <div class="form-group">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
          <input type="checkbox" name="auto_approve" value="1" <?= $v('auto_approve') ? 'checked' : '' ?> style="width:auto">
          <span>参加申請を自動承認する</span>
        </label>
        <p style="font-size:11px;color:var(--muted);margin-top:4px">
          オンにすると「参加する」を押した受講生を自動で承認し、すぐ画像生成できるようになります。<br>
          オフの場合は管理者が手動で承認します（参加予約 → 承認 → 参加の流れ）。
        </p>
      </div>

      <div class="form-group">
        <label>受講生向け案内メッセージ（任意）</label>
        <textarea name="public_message" rows="3" placeholder="持ち物や注意事項など、受講生のLINE案内に表示したい内容"><?= $v('public_message') ?></textarea>
        <p style="font-size:11px;color:var(--muted);margin-top:4px">開催日案内のLINEに表示されます。受講生に見えます。</p>
      </div>

      <div class="form-group">
        <label>内部メモ（管理用・受講生には見えません）</label>
        <textarea name="description"><?= $v('description') ?></textarea>
      </div>

      <div style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:14px">
        <div style="font-size:13px;font-weight:600;color:var(--accent2);margin-bottom:10px">📣 リマインダー（任意）</div>
        <div class="form-group">
          <label>送信日時（この時刻に承認済み参加者へLINE送信）</label>
          <input type="datetime-local" name="reminder_at" value="<?= $v('reminder_at') ? date('Y-m-d\TH:i', strtotime($v('reminder_at'))) : '' ?>">
          <p style="font-size:11px;color:var(--muted);margin-top:4px">空欄なら送信しません。例：前日の20:00、当日の朝8:00など</p>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label>リマインダー本文（空欄なら自動文面）</label>
          <textarea name="reminder_message" rows="3" placeholder="空欄の場合は「○月○日 ○時〜開催です！」を自動送信"><?= $v('reminder_message') ?></textarea>
        </div>
      </div>

      <button type="submit" class="btn btn-primary"><?= $isEdit ? '更新する' : '作成する' ?></button>
    </form>
  </div>
</div>
<script>
function toggleFormat() {
  var fmt = document.getElementById('event_format').value;
  var loc = document.getElementById('field_location');
  var zoom = document.getElementById('field_zoom');
  loc.style.display  = (fmt === 'realtime' || fmt === 'hybrid') ? 'block' : 'none';
  zoom.style.display = (fmt === 'zoom' || fmt === 'hybrid') ? 'block' : 'none';
}
toggleFormat();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
