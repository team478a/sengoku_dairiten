<?php
$pageTitle = '一斉メッセージ送信';
ob_start();
?>

<?php if (!empty($_GET['sent'])): ?>
<div class="alert alert-success"><?= (int)$_GET['sent'] ?>人に送信しました。</div>
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
<div class="alert alert-error">エラー：<?= htmlspecialchars($_GET['error']) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:900px">

  <!-- 送信フォーム -->
  <div class="card">
    <div class="card-header">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13 19.79 19.79 0 0 1 1.61 4.4 2 2 0 0 1 3.6 2.22h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.29 6.29l.95-.95a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
      一斉LINE送信
    </div>
    <div class="card-body">
      <form method="POST" action="/admin/broadcast">
<?= csrf_field() ?>
        <div class="form-group">
          <label>送信対象</label>
          <select name="target">
            <option value="all">全受講生（LINEフォロワー全員）</option>
            <option value="today_approved">本日の承認済み参加者</option>
            <option value="active">アクティブユーザー全員</option>
            <?php if (!empty($schedules)): ?>
            <optgroup label="── 教室別（承認済み参加者）──">
              <?php foreach ($schedules as $s): ?>
              <option value="schedule:<?= $s['id'] ?>">
                <?= date('m/d', strtotime($s['class_date'])) ?> <?= htmlspecialchars($s['title']) ?>（<?= $s['approved_count'] ?>人）
              </option>
              <?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
          </select>
        </div>
        <div class="form-group">
          <label>メッセージ</label>
          <textarea name="message" rows="6" placeholder="送信するメッセージを入力してください" required></textarea>
        </div>
        <div style="font-size:12px;color:var(--muted);margin-bottom:12px">
          ※ LINEのメッセージ送信数には月間上限があります（無料プランは200通/月）
        </div>
        <button type="submit" class="btn btn-primary"
                onclick="return confirm('一斉送信しますか？送信後は取り消せません。')">
          送信する
        </button>
      </form>
    </div>
  </div>

  <!-- 送信履歴 -->
  <div class="card">
    <div class="card-header">送信履歴（直近10件）</div>
    <div class="card-body">
      <?php if (empty($broadcastLogs)): ?>
      <div style="color:var(--muted);font-size:13px;text-align:center;padding:20px">送信履歴がありません</div>
      <?php else: ?>
      <?php foreach ($broadcastLogs as $log): ?>
      <div class="log-item" style="flex-direction:column;align-items:flex-start;gap:4px">
        <div style="display:flex;gap:8px;width:100%">
          <span class="log-time"><?= date('m/d H:i', strtotime($log['created_at'])) ?></span>
          <span style="font-size:11px;background:rgba(124,106,247,.2);color:var(--accent2);padding:1px 6px;border-radius:10px">
            <?= htmlspecialchars($log['log_type']) ?>
          </span>
        </div>
        <span style="font-size:12px;color:var(--text)"><?= htmlspecialchars(mb_strimwidth($log['message'], 0, 50, '…')) ?></span>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
