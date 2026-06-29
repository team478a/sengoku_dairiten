<?php
$pageTitle = 'ユーザー詳細';
ob_start();
$updated = !empty($_GET['updated']);
$sent    = !empty($_GET['sent']);
?>

<?php if ($updated): ?><div class="alert alert-success">更新しました。</div><?php endif; ?>
<?php if ($sent):    ?><div class="alert alert-success">メッセージを送信しました。</div><?php endif; ?>

<div style="margin-bottom:16px">
  <a href="/admin/users" class="btn btn-secondary btn-sm">← 一覧へ</a>
</div>

<div style="display:grid;grid-template-columns:300px 1fr;gap:16px;margin-bottom:16px">

  <!-- プロフィール -->
  <div>
    <div class="card" style="margin-bottom:12px">
      <div class="card-body" style="text-align:center">
        <?php if ($user['picture_url']): ?>
        <img src="<?= htmlspecialchars($user['picture_url']) ?>"
             style="width:72px;height:72px;border-radius:50%;object-fit:cover;margin-bottom:10px">
        <?php endif; ?>
        <div style="font-size:16px;font-weight:700"><?= htmlspecialchars($user['display_name'] ?? '—') ?></div>
        <div style="margin-top:6px">
          <?php
          switch($user['status']) {
            case 'active':    $cls='badge-completed'; break;
            case 'suspended': $cls='badge-warning';   break;
            case 'banned':    $cls='badge-failed';    break;
            default:          $cls='badge-received';  break;
          }
          ?>
          <span class="badge-status <?= $cls ?>"><?= htmlspecialchars($user['status']) ?></span>
        </div>
        <div style="font-size:11px;color:var(--muted);margin-top:8px">
          登録：<?= $user['created_at'] ? date('Y/m/d', strtotime($user['created_at'])) : '—' ?>
        </div>
      </div>
    </div>

    <!-- ステータス変更 -->
    <div class="card" style="margin-bottom:12px">
      <div class="card-header">ステータス変更</div>
      <div class="card-body">
        <form method="POST" action="/admin/users/<?= $user['id'] ?>
<?= csrf_field() ?>/status">
          <div class="form-group">
            <select name="status">
              <?php foreach (['active'=>'active（有効）','suspended'=>'suspended（一時停止）','banned'=>'banned（禁止）'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= $user['status']===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-secondary btn-sm" style="width:100%">変更する</button>
        </form>
      </div>
    </div>

    <!-- 会員区分・チケット -->
    <div class="card" style="margin-bottom:12px">
      <div class="card-header">💳 会員区分・チケット</div>
      <div class="card-body">
        <form method="POST" action="/admin/users/<?= $user['id'] ?>
<?= csrf_field() ?>/member-type">
          <div class="form-group">
            <label>会員区分</label>
            <select name="member_type">
              <option value="none"       <?= ($user['member_type'] ?? 'none')==='none'?'selected':'' ?>>都度（一般）</option>
              <option value="subscriber" <?= ($user['member_type'] ?? '')==='subscriber'?'selected':'' ?>>サブスク会員（通い放題）</option>
            </select>
          </div>
          <button type="submit" class="btn btn-secondary btn-sm" style="width:100%">区分を変更</button>
        </form>
        <hr style="border:none;border-top:1px solid var(--border);margin:14px 0">
        <div style="text-align:center;margin-bottom:10px">
          <span style="font-size:12px;color:var(--muted)">チケット残</span>
          <div style="font-size:24px;font-weight:800;color:var(--accent2)"><?= (int)($user['ticket_balance'] ?? 0) ?>枚</div>
        </div>
        <form method="POST" action="/admin/users/<?= $user['id'] ?>
<?= csrf_field() ?>/tickets">
          <div class="form-group">
            <label>チケットを追加（マイナスで減算）</label>
            <input type="number" name="ticket_count" value="5" step="1">
          </div>
          <button type="submit" class="btn btn-primary btn-sm" style="width:100%">チケットを反映</button>
        </form>
      </div>
    </div>

    <!-- LINEメッセージ送信 -->
    <div class="card" style="margin-bottom:12px">
      <div class="card-header">LINEメッセージ送信</div>
      <div class="card-body">
        <form method="POST" action="/admin/users/<?= $user['id'] ?>
<?= csrf_field() ?>/message">
          <div class="form-group">
            <textarea name="message" rows="3" placeholder="送信するメッセージ"></textarea>
          </div>
          <button type="submit" class="btn btn-primary btn-sm" style="width:100%">送信</button>
        </form>
      </div>
    </div>

    <!-- メモ -->
    <div class="card">
      <div class="card-header">メモ（管理者のみ表示）</div>
      <div class="card-body">
        <form method="POST" action="/admin/users/<?= $user['id'] ?>
<?= csrf_field() ?>/memo">
          <div class="form-group">
            <textarea name="memo" rows="3"><?= htmlspecialchars($user['memo'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn btn-secondary btn-sm" style="width:100%">保存</button>
        </form>
      </div>
    </div>
  </div>

  <!-- 履歴 -->
  <div>
    <!-- 参加履歴 -->
    <div class="card" style="margin-bottom:12px">
      <div class="card-header">教室参加履歴</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>日付</th><th>教室名</th><th>ステータス</th><th>承認日時</th></tr></thead>
          <tbody>
          <?php foreach ($attendances as $a): ?>
          <tr>
            <td><?= $a['class_date'] ? date('Y/m/d', strtotime($a['class_date'])) : '—' ?></td>
            <td><?= htmlspecialchars($a['title'] ?? '—') ?></td>
            <td>
              <?php
              switch($a['status']) {
                case 'approved': $ac='badge-completed'; $al='承認済み'; break;
                case 'rejected': $ac='badge-failed';    $al='却下';     break;
                case 'pending':  $ac='badge-received';  $al='申請中';   break;
                default:         $ac='badge-received';  $al=htmlspecialchars($a['status']); break;
              }
              ?>
              <span class="badge-status <?= $ac ?>"><?= $al ?></span>
            </td>
            <td style="color:var(--muted)"><?= $a['approved_at'] ? date('m/d H:i', strtotime($a['approved_at'])) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($attendances)): ?>
          <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:20px">参加履歴なし</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 生成履歴 -->
    <div class="card">
      <div class="card-header">画像生成履歴（最近20件）</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>日時</th><th>入力</th><th>画風</th><th>雰囲気</th><th>枚数</th><th>ステータス</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($requests as $r): ?>
          <tr>
            <td style="color:var(--muted);white-space:nowrap"><?= date('m/d H:i', strtotime($r['created_at'])) ?></td>
            <td><?= htmlspecialchars(mb_strimwidth($r['input_text'],0,24,'…')) ?></td>
            <td style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($r['survey_style'] ?? '—') ?></td>
            <td style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($r['survey_mood'] ?? '—') ?></td>
            <td><?= $r['image_count'] ?>/8</td>
            <td><span class="badge-status badge-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
            <td><a href="/admin/image-requests/<?= $r['id'] ?>" class="btn btn-secondary btn-sm">詳細</a></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($requests)): ?>
          <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:20px">生成履歴なし</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
