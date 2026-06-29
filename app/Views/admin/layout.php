<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle ?? 'AIアート教室 管理画面') ?></title>
<style>
:root {
  --bg:        #0f1117;
  --sidebar:   #16191f;
  --surface:   #1e2128;
  --border:    #2a2d36;
  --accent:    #7c6af7;
  --accent2:   #a78bfa;
  --danger:    #ef4444;
  --success:   #22c55e;
  --warning:   #f59e0b;
  --text:      #e2e4ea;
  --muted:     #6b7280;
  --font:      'Hiragino Sans', 'Noto Sans JP', sans-serif;
}
body.light-mode {
  --bg:        #f4f5f7;
  --sidebar:   #ffffff;
  --surface:   #ffffff;
  --border:    #e2e4ea;
  --accent:    #6c5ce7;
  --accent2:   #8b7cf8;
  --danger:    #e53e3e;
  --success:   #38a169;
  --warning:   #d69e2e;
  --text:      #1a202c;
  --muted:     #718096;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--font); background: var(--bg); color: var(--text); display: flex; min-height: 100vh; font-size: 14px; }

/* Sidebar */
.sidebar { width: 220px; background: var(--sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; flex-shrink: 0; }
.sidebar-logo { padding: 20px 16px; border-bottom: 1px solid var(--border); }
.sidebar-logo h1 { font-size: 13px; font-weight: 700; color: var(--accent2); line-height: 1.4; }
.sidebar-logo span { font-size: 11px; color: var(--muted); display: block; margin-top: 2px; }
nav { flex: 1; padding: 12px 0; }
nav a { display: flex; align-items: center; gap: 10px; padding: 9px 16px; color: var(--muted); text-decoration: none; font-size: 13px; transition: all .15s; }
nav a:hover { color: var(--text); background: rgba(124,106,247,.1); }
nav a.active { color: var(--accent2); background: rgba(124,106,247,.15); border-right: 2px solid var(--accent); }
nav a svg { flex-shrink: 0; }
.sidebar-footer { padding: 12px 16px; border-top: 1px solid var(--border); }
.sidebar-footer a { font-size: 12px; color: var(--muted); text-decoration: none; }

/* Main */
.main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.topbar { height: 52px; border-bottom: 1px solid var(--border); display: flex; align-items: center; padding: 0 24px; gap: 12px; }
.topbar h2 { font-size: 15px; font-weight: 600; }
.topbar .badge { font-size: 11px; background: rgba(124,106,247,.2); color: var(--accent2); padding: 2px 8px; border-radius: 20px; }
.content { flex: 1; padding: 24px; overflow-y: auto; }

/* Cards */
.card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; }
.card-header { padding: 14px 20px; border-bottom: 1px solid var(--border); font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 8px; }
.card-body { padding: 20px; }

/* Stats grid */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; margin-bottom: 24px; }
.stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 16px; }
.stat-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: .05em; }
.stat-value { font-size: 28px; font-weight: 700; margin-top: 4px; }
.stat-value.accent  { color: var(--accent2); }
.stat-value.danger  { color: var(--danger); }
.stat-value.success { color: var(--success); }
.stat-value.warning { color: var(--warning); }

/* Table */
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--border); color: var(--muted); font-weight: 500; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; }
td { padding: 10px 12px; border-bottom: 1px solid var(--border); }
tr:last-child td { border-bottom: none; }
tr:hover td { background: rgba(255,255,255,.02); }
a.row-link { color: var(--accent2); text-decoration: none; }
a.row-link:hover { text-decoration: underline; }

/* Status badges */
.badge-status { font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 500; display: inline-block; }
.badge-received    { background: rgba(107,114,128,.2); color: #9ca3af; }
.badge-analyzing   { background: rgba(245,158,11,.2); color: var(--warning); }
.badge-generating  { background: rgba(124,106,247,.2); color: var(--accent2); }
.badge-uploading   { background: rgba(124,106,247,.2); color: var(--accent2); }
.badge-sending     { background: rgba(34,197,94,.15); color: #4ade80; }
.badge-completed   { background: rgba(34,197,94,.2); color: var(--success); }
.badge-failed      { background: rgba(239,68,68,.2); color: var(--danger); }
.badge-canceled    { background: rgba(107,114,128,.15); color: var(--muted); }

/* Forms */
.form-group { margin-bottom: 16px; }
label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 6px; font-weight: 500; }
input[type=text], input[type=email], input[type=password], input[type=date], select, textarea {
  width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 6px;
  padding: 8px 12px; color: var(--text); font-size: 13px; font-family: var(--font);
  transition: border-color .15s;
}
input:focus, select:focus, textarea:focus { outline: none; border-color: var(--accent); }
textarea { resize: vertical; min-height: 80px; }

/* Buttons */
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; text-decoration: none; transition: all .15s; }
.btn-primary { background: var(--accent); color: #fff; }
.btn-primary:hover { background: var(--accent2); }
.btn-secondary { background: var(--surface); border: 1px solid var(--border); color: var(--text); }
.btn-secondary:hover { background: var(--border); }
.btn-danger { background: rgba(239,68,68,.2); color: var(--danger); border: 1px solid rgba(239,68,68,.3); }
.btn-danger:hover { background: rgba(239,68,68,.3); }
.btn-success { background: rgba(34,197,94,.2); color: var(--success); border: 1px solid rgba(34,197,94,.3); }
.btn-sm { padding: 4px 10px; font-size: 12px; }

/* Alert */
.alert { padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; }
.alert-success { background: rgba(34,197,94,.1); border: 1px solid rgba(34,197,94,.3); color: #4ade80; }
.alert-error   { background: rgba(239,68,68,.1);  border: 1px solid rgba(239,68,68,.3);  color: #f87171; }
.alert-info    { background: rgba(124,106,247,.1); border: 1px solid rgba(124,106,247,.3); color: var(--accent2); }

/* Pagination */
.pagination { display: flex; gap: 4px; margin-top: 16px; }
.pagination a, .pagination span { padding: 5px 10px; border-radius: 4px; font-size: 12px; text-decoration: none; }
.pagination a { background: var(--surface); border: 1px solid var(--border); color: var(--text); }
.pagination a:hover { border-color: var(--accent); color: var(--accent2); }
.pagination span { background: var(--accent); color: #fff; }

/* Filter bar */
.filter-bar { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
.filter-bar input, .filter-bar select { width: auto; flex: 1; min-width: 120px; }

/* Image grid */
.image-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 8px; }
.image-grid img { width: 100%; aspect-ratio: 1; object-fit: cover; border-radius: 6px; border: 1px solid var(--border); }
.image-grid a { display: block; }
.image-grid a:hover img { border-color: var(--accent); }

/* Prompt box */
.prompt-box { background: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 12px; font-size: 12px; color: var(--muted); font-family: monospace; white-space: pre-wrap; word-break: break-all; }

/* Log list */
.log-item { display: flex; gap: 10px; padding: 6px 0; border-bottom: 1px solid var(--border); font-size: 12px; }
.log-item:last-child { border-bottom: none; }
.log-time { color: var(--muted); white-space: nowrap; }
.log-level-info    { color: var(--accent2); }
.log-level-warning { color: var(--warning); }
.log-level-error   { color: var(--danger); }
</style>
<?= $extraHead ?? '' ?>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-logo">
    <h1>AIアート教室</h1>
    <span>画像生成システム</span>
  </div>
  <nav>
    <?php
    $current = $_SERVER['REQUEST_URI'] ?? '';
    $isOwner = (($_SESSION['admin_role'] ?? 'staff') === 'owner');
    $navItems = [
      ['/admin/dashboard',      'ダッシュボード', '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>'],
      ['/admin/manual',          '使い方マニュアル','<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>'],
      ['/admin/image-requests', '依頼一覧',       '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>'],
      ['/admin/broadcast',        '一斉メッセージ',  '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13 19.79 19.79 0 0 1 1.61 4.4 2 2 0 0 1 3.6 2.22h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 9.91a16 16 0 0 0 6.29 6.29l.95-.95a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>'],
      ['/admin/attendance',        '出席履歴',        '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>'],
      ['/admin/gallery',         'ギャラリー',      '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>'],
      ['/admin/payments',        '決済履歴',        '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>'],
      ['/admin/report',          '統計・出力',      '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>'],
      ['/admin/logs',            '操作ログ',        '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>'],
      ['/admin/qrcode',          'QRコード',        '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>'],
      ['/admin/calendar',        'カレンダー',      '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>'],
      ['/admin/classes',        '教室・参加管理', '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'],
      ['/admin/users',           'ユーザー',       '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'],
      ['/admin/managers',        '管理者アカウント','<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'],
      ['/admin/line-config',     'LINE設定',       '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>'],
      ['/admin/settings',       'API設定',        '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>'],
      ['/admin/update',         'アップデート',   '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.95"/></svg>'],
    ];
    // オーナー専用ページ
    $ownerOnly = ['/admin/payments', '/admin/logs', '/admin/line-config', '/admin/settings', '/admin/update', '/admin/managers'];
    foreach ($navItems as [$href, $label, $icon]) {
      if (in_array($href, $ownerOnly) && !$isOwner) continue;
      $active = strpos($current, $href) === 0 ? 'active' : '';
      echo "<a href=\"{$href}\" class=\"{$active}\">{$icon} {$label}</a>";
    }
    ?>
  </nav>
  <div class="sidebar-footer">
    <div style="font-size:12px;color:var(--text);margin-bottom:6px">
      <?= htmlspecialchars($_SESSION['admin_name'] ?? $_SESSION['admin_email'] ?? '管理者') ?>
      <?php if (($_SESSION['admin_role'] ?? '') === 'owner'): ?>
      <span style="font-size:10px;background:rgba(34,197,94,.2);color:#22c55e;padding:1px 6px;border-radius:10px">オーナー</span>
      <?php else: ?>
      <span style="font-size:10px;background:rgba(124,106,247,.2);color:var(--accent2);padding:1px 6px;border-radius:10px">スタッフ</span>
      <?php endif; ?>
    </div>
    <div style="display:flex;justify-content:space-between;align-items:center">
    <a href="/admin/logout" style="font-size:12px;color:var(--muted);text-decoration:none">ログアウト</a>
    <button id="theme-toggle" onclick="toggleTheme()" title="ライト/ダーク切り替え"
            style="background:none;border:1px solid var(--border);border-radius:6px;padding:4px 8px;cursor:pointer;color:var(--muted);font-size:14px">
      🌙
    </button>
  </div>
  <script>
  function toggleTheme() {
    const body = document.body;
    const btn  = document.getElementById('theme-toggle');
    if (body.classList.contains('light-mode')) {
      body.classList.remove('light-mode');
      btn.textContent = '🌙';
      localStorage.setItem('theme', 'dark');
    } else {
      body.classList.add('light-mode');
      btn.textContent = '☀️';
      localStorage.setItem('theme', 'light');
    }
  }
  // 保存されたテーマを適用
  (function() {
    const saved = localStorage.getItem('theme');
    if (saved === 'light') {
      document.body.classList.add('light-mode');
      document.addEventListener('DOMContentLoaded', function() {
        const btn = document.getElementById('theme-toggle');
        if (btn) btn.textContent = '☀️';
      });
    }
  })();
  </script>
</aside>

<div class="main">
  <div class="topbar">
    <h2><?= htmlspecialchars($pageTitle ?? '') ?></h2>
    <?php if (!empty($topbarBadge)): ?>
    <span class="badge"><?= htmlspecialchars($topbarBadge) ?></span>
    <?php endif; ?>
  </div>
  <div class="content">
    <?= $content ?? '' ?>
  </div>
</div>

</body>
</html>
