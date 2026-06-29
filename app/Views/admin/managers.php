<?php
$pageTitle = '管理者アカウント';
ob_start();

$savedMessages = [
    'created'  => '管理者を追加しました',
    'role'     => '権限を変更しました',
    'status'   => 'ステータスを変更しました',
    'password' => 'パスワードを再設定しました',
    'deleted'  => '管理者を削除しました',
];
$currentAdminId = (int)($_SESSION['admin_id'] ?? 0);
?>

<?php if ($saved && isset($savedMessages[$saved])): ?>
<div class="alert alert-success"><?= $savedMessages[$saved] ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 360px;gap:16px">

  <!-- 管理者一覧 -->
  <div class="card">
    <div class="card-header">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      管理者一覧（<?= count($admins) ?>人）
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>名前 / メール</th><th>権限</th><th>状態</th><th>最終ログイン</th><th>操作</th>
        </tr></thead>
        <tbody>
        <?php foreach ($admins as $a): ?>
        <tr>
          <td>
            <div style="font-weight:600"><?= htmlspecialchars($a['name'] ?: '（名前未設定）') ?>
              <?php if ((int)$a['id'] === $currentAdminId): ?>
              <span style="font-size:10px;background:rgba(124,106,247,.2);color:var(--accent2);padding:1px 6px;border-radius:10px;margin-left:4px">あなた</span>
              <?php endif; ?>
            </div>
            <div style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($a['email']) ?></div>
          </td>
          <td>
            <?php if ($a['role'] === 'owner'): ?>
            <span class="badge-status badge-completed">オーナー</span>
            <?php else: ?>
            <span class="badge-status badge-received">スタッフ</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($a['status'] === 'active'): ?>
            <span class="badge-status badge-completed">有効</span>
            <?php else: ?>
            <span class="badge-status badge-failed">停止</span>
            <?php endif; ?>
          </td>
          <td style="color:var(--muted);font-size:12px">
            <?= $a['last_login_at'] ? date('m/d H:i', strtotime($a['last_login_at'])) : '未ログイン' ?>
          </td>
          <td>
            <details style="position:relative">
              <summary style="cursor:pointer;list-style:none;font-size:12px;color:var(--accent2)">操作 ▾</summary>
              <div style="position:absolute;right:0;top:24px;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:10px;width:200px;z-index:10;box-shadow:0 4px 12px rgba(0,0,0,.3)">

                <!-- 権限変更 -->
                <form method="POST" action="/admin/managers/<?= $a['id'] ?>
<?= csrf_field() ?>/role" style="margin-bottom:8px">
                  <select name="role" style="font-size:12px;padding:4px;width:100%;margin-bottom:4px">
                    <option value="staff" <?= $a['role']==='staff'?'selected':'' ?>>スタッフ</option>
                    <option value="owner" <?= $a['role']==='owner'?'selected':'' ?>>オーナー</option>
                  </select>
                  <button type="submit" class="btn btn-secondary btn-sm" style="width:100%">権限変更</button>
                </form>

                <!-- 停止/有効化 -->
                <?php if ((int)$a['id'] !== $currentAdminId): ?>
                <form method="POST" action="/admin/managers/<?= $a['id'] ?>
<?= csrf_field() ?>/status" style="margin-bottom:8px">
                  <input type="hidden" name="status" value="<?= $a['status']==='active'?'suspended':'active' ?>">
                  <button type="submit" class="btn btn-secondary btn-sm" style="width:100%">
                    <?= $a['status']==='active'?'停止する':'有効化する' ?>
                  </button>
                </form>
                <?php endif; ?>

                <!-- パスワード再設定 -->
                <form method="POST" action="/admin/managers/<?= $a['id'] ?>
<?= csrf_field() ?>/password" style="margin-bottom:8px"
                      onsubmit="this.password.value=prompt('新しいパスワード（8文字以上）')||''; return this.password.value.length>=8">
                  <input type="hidden" name="password" value="">
                  <button type="submit" class="btn btn-secondary btn-sm" style="width:100%">パスワード再設定</button>
                </form>

                <!-- 削除 -->
                <?php if ((int)$a['id'] !== $currentAdminId): ?>
                <form method="POST" action="/admin/managers/<?= $a['id'] ?>
<?= csrf_field() ?>/delete"
                      onsubmit="return confirm('<?= htmlspecialchars($a['name'] ?: $a['email']) ?> を削除しますか？')">
                  <button type="submit" class="btn btn-danger btn-sm" style="width:100%">削除</button>
                </form>
                <?php endif; ?>
              </div>
            </details>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- 新規追加 -->
  <div class="card" style="align-self:start">
    <div class="card-header">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
      管理者を追加
    </div>
    <div class="card-body">
      <form method="POST" action="/admin/managers">
<?= csrf_field() ?>
        <div class="form-group">
          <label>名前</label>
          <input type="text" name="name" placeholder="山田 太郎">
        </div>
        <div class="form-group">
          <label>メールアドレス</label>
          <input type="email" name="email" required>
        </div>
        <div class="form-group">
          <label>権限</label>
          <select name="role">
            <option value="staff">スタッフ（教室運営のみ）</option>
            <option value="owner">オーナー（全権限）</option>
          </select>
        </div>
        <div class="form-group">
          <label>初期パスワード（8文字以上）</label>
          <input type="password" name="password" required minlength="8">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%">追加する</button>
      </form>

      <div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--border);font-size:11px;color:var(--muted);line-height:1.8">
        <strong style="color:var(--text)">権限の違い</strong><br>
        <span style="color:var(--accent2)">オーナー</span>：全機能 + 管理者管理・API設定・アップデート<br>
        <span style="color:var(--muted)">スタッフ</span>：教室運営・参加承認・一斉送信・閲覧のみ
      </div>
    </div>
  </div>

</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
