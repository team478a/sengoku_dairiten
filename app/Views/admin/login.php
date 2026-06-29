<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ログイン — AIアート教室管理画面</title>
<style>
:root { --bg:#0f1117; --surface:#1e2128; --border:#2a2d36; --accent:#7c6af7; --accent2:#a78bfa; --text:#e2e4ea; --muted:#6b7280; --danger:#ef4444; }
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Hiragino Sans','Noto Sans JP',sans-serif; background:var(--bg); color:var(--text); min-height:100vh; display:flex; align-items:center; justify-content:center; }
.login-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:40px; width:100%; max-width:380px; }
.logo { text-align:center; margin-bottom:32px; }
.logo h1 { font-size:18px; font-weight:700; color:var(--accent2); }
.logo p { font-size:12px; color:var(--muted); margin-top:4px; }
.form-group { margin-bottom:16px; }
label { display:block; font-size:12px; color:var(--muted); margin-bottom:6px; }
input { width:100%; background:var(--bg); border:1px solid var(--border); border-radius:6px; padding:10px 12px; color:var(--text); font-size:14px; }
input:focus { outline:none; border-color:var(--accent); }
.btn { width:100%; padding:10px; background:var(--accent); color:#fff; border:none; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer; margin-top:8px; }
.btn:hover { background:var(--accent2); }
.error { background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3); color:#f87171; padding:10px 12px; border-radius:6px; font-size:13px; margin-bottom:16px; }
</style>
</head>
<body>
<div class="login-card">
  <div class="logo">
    <h1>AIアート教室</h1>
    <p>管理画面</p>
  </div>
  <?php if (!empty($error)): ?>
  <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST" action="/admin/login">
    <div class="form-group">
      <label>メールアドレス</label>
      <input type="email" name="email" required autofocus value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>パスワード</label>
      <input type="password" name="password" required>
    </div>
    <button type="submit" class="btn">ログイン</button>
  </form>
</div>
</body>
</html>
