<?php
// install.php - AIアート教室 インストーラー v1.2.1
// PHP処理を最初に完結させてからHTMLを出力（header問題を根本解決）

error_reporting(E_ALL);
ini_set('display_errors', 0);

$BASE_PATH    = __DIR__;
$CONFIG_PATH  = $BASE_PATH . '/config';
$STORAGE_PATH = $BASE_PATH . '/storage';

$isInstalled = file_exists($CONFIG_PATH . '/installed.lock');
$redirectUrl = '';
$step    = 1;
$errors  = [];
$success = '';

if ($isInstalled) {
    $redirectUrl = '/admin/login';
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // DB設定 & テーブル作成
        if ($action === 'setup_db') {
            $dbHost = trim($_POST['db_host'] ?? '');
            $dbName = trim($_POST['db_name'] ?? '');
            $dbUser = trim($_POST['db_user'] ?? '');
            $dbPass = $_POST['db_password'] ?? '';

            if (!$dbHost || !$dbName || !$dbUser) {
                $errors[] = 'ホスト・DB名・ユーザー名は必須です';
                $step = 2;
            } else {
                try {
                    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
                    $pdo = new PDO($dsn, $dbUser, $dbPass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 5,
                    ]);

                    $sqls = [
                        "CREATE TABLE IF NOT EXISTS users (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, line_user_id VARCHAR(255) NOT NULL UNIQUE, display_name VARCHAR(255), picture_url TEXT, memo TEXT, registered_at DATETIME, status VARCHAR(50) NOT NULL DEFAULT 'active', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        "CREATE TABLE IF NOT EXISTS class_schedules (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL DEFAULT '教室', class_date DATE NOT NULL, start_time TIME NOT NULL DEFAULT '10:00:00', end_time TIME NOT NULL DEFAULT '12:00:00', checkin_open TIME NOT NULL DEFAULT '09:30:00', checkin_close TIME NOT NULL DEFAULT '11:00:00', capacity INT NOT NULL DEFAULT 20, max_requests INT NOT NULL DEFAULT 2, description TEXT, status VARCHAR(20) NOT NULL DEFAULT 'scheduled', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        "CREATE TABLE IF NOT EXISTS class_attendances (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, schedule_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, line_user_id VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', approved_by BIGINT UNSIGNED, approved_at DATETIME, rejected_reason TEXT, notified_at DATETIME, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uq_schedule_user (schedule_id, user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        "CREATE TABLE IF NOT EXISTS user_sessions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, line_user_id VARCHAR(255) NOT NULL UNIQUE, step VARCHAR(50) NOT NULL DEFAULT 'idle', survey_data JSON, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        "CREATE TABLE IF NOT EXISTS image_requests (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, line_user_id VARCHAR(255) NOT NULL, input_type VARCHAR(50) NOT NULL DEFAULT 'survey', survey_style VARCHAR(50), survey_mood VARCHAR(50), input_text TEXT NOT NULL, requested_size VARCHAR(50) NOT NULL DEFAULT 'square', status VARCHAR(50) NOT NULL DEFAULT 'received', error_message TEXT, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        "CREATE TABLE IF NOT EXISTS prompts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, request_id BIGINT UNSIGNED NOT NULL, prompt_type VARCHAR(10) NOT NULL, title_ja VARCHAR(255), input_summary_ja TEXT, prompt_en TEXT NOT NULL, safety_notes TEXT, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        "CREATE TABLE IF NOT EXISTS generated_images (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, request_id BIGINT UNSIGNED NOT NULL, prompt_id BIGINT UNSIGNED NOT NULL, prompt_type VARCHAR(10) NOT NULL, image_no INT NOT NULL, image_url TEXT, preview_url TEXT, storage_path TEXT, status VARCHAR(50) NOT NULL DEFAULT 'generated', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        "CREATE TABLE IF NOT EXISTS job_queue (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, request_id BIGINT UNSIGNED NOT NULL, job_type VARCHAR(50) NOT NULL DEFAULT 'generate_images', status VARCHAR(50) NOT NULL DEFAULT 'pending', retry_count INT NOT NULL DEFAULT 0, error_message TEXT, available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        "CREATE TABLE IF NOT EXISTS system_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, request_id BIGINT UNSIGNED, log_level VARCHAR(20) NOT NULL DEFAULT 'info', log_type VARCHAR(50) NOT NULL DEFAULT 'system', message TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        "CREATE TABLE IF NOT EXISTS system_settings (`key` VARCHAR(100) NOT NULL PRIMARY KEY, `value` TEXT, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        "CREATE TABLE IF NOT EXISTS admin_users (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        "INSERT IGNORE INTO system_settings (`key`, `value`) VALUES ('max_daily_requests_per_user','2'),('max_images_per_request','8'),('storage_driver','local'),('image_size','1024x1024'),('class_mode_enabled','1'),('checkin_required','1'),('next_class_message','次回の教室開催日をお待ちください。'),('survey_enabled','1'),('survey_session_ttl_minutes','30')",
                        // v3.6.0 拡張カラム
                        "ALTER TABLE class_schedules ADD COLUMN IF NOT EXISTS organizer VARCHAR(255) NOT NULL DEFAULT '' AFTER description",
                        "ALTER TABLE class_schedules ADD COLUMN IF NOT EXISTS public_message TEXT AFTER organizer",
                        "ALTER TABLE class_schedules ADD COLUMN IF NOT EXISTS event_format VARCHAR(20) NOT NULL DEFAULT 'realtime' AFTER public_message",
                        "ALTER TABLE class_schedules ADD COLUMN IF NOT EXISTS location VARCHAR(255) NOT NULL DEFAULT '' AFTER event_format",
                        "ALTER TABLE class_schedules ADD COLUMN IF NOT EXISTS zoom_url TEXT AFTER location",
                        "ALTER TABLE class_schedules ADD COLUMN IF NOT EXISTS auto_approve TINYINT(1) NOT NULL DEFAULT 0 AFTER zoom_url",
                        "ALTER TABLE class_schedules ADD COLUMN IF NOT EXISTS fee INT NOT NULL DEFAULT 0 AFTER auto_approve",
                        "ALTER TABLE class_schedules ADD COLUMN IF NOT EXISTS reminder_at DATETIME AFTER fee",
                        "ALTER TABLE class_schedules ADD COLUMN IF NOT EXISTS reminder_message TEXT AFTER reminder_at",
                        "ALTER TABLE class_schedules ADD COLUMN IF NOT EXISTS reminder_sent_at DATETIME AFTER reminder_message",
                        "ALTER TABLE class_attendances ADD COLUMN IF NOT EXISTS attended_at DATETIME AFTER notified_at",
                        "ALTER TABLE class_attendances ADD COLUMN IF NOT EXISTS payment_status VARCHAR(30) NOT NULL DEFAULT 'free' AFTER attended_at",
                        "ALTER TABLE class_attendances ADD COLUMN IF NOT EXISTS payment_amount INT NOT NULL DEFAULT 0 AFTER payment_status",
                        "ALTER TABLE class_attendances ADD COLUMN IF NOT EXISTS paid_at DATETIME AFTER payment_amount",
                        "ALTER TABLE class_attendances ADD COLUMN IF NOT EXISTS stripe_session_id VARCHAR(255) AFTER paid_at",
                        "ALTER TABLE users ADD COLUMN IF NOT EXISTS member_type VARCHAR(30) NOT NULL DEFAULT 'standard' AFTER memo",
                        "ALTER TABLE users ADD COLUMN IF NOT EXISTS ticket_balance INT NOT NULL DEFAULT 0 AFTER member_type",
                        "ALTER TABLE users ADD COLUMN IF NOT EXISTS ticket_expires_at DATETIME AFTER ticket_balance",
                        "ALTER TABLE users ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(255) AFTER ticket_expires_at",
                        "ALTER TABLE users ADD COLUMN IF NOT EXISTS stripe_subscription_id VARCHAR(255) AFTER stripe_customer_id",
                        "ALTER TABLE users ADD COLUMN IF NOT EXISTS subscription_until DATETIME AFTER stripe_subscription_id",
                        "ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS name VARCHAR(255) NOT NULL DEFAULT '' AFTER email",
                        "ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS role VARCHAR(50) NOT NULL DEFAULT 'manager' AFTER name",
                        "CREATE TABLE IF NOT EXISTS payment_transactions (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NULL, line_user_id VARCHAR(255) NULL, kind VARCHAR(30) NOT NULL, amount INT NOT NULL DEFAULT 0, status VARCHAR(30) NOT NULL DEFAULT 'paid', description VARCHAR(255) NULL, stripe_session_id VARCHAR(255) NULL, stripe_payment_intent VARCHAR(255) NULL, refunded_at DATETIME NULL, created_at DATETIME NOT NULL, UNIQUE KEY uq_stripe_session_id (stripe_session_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                        "CREATE TABLE IF NOT EXISTS audit_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, action VARCHAR(100) NOT NULL, target VARCHAR(255), detail TEXT, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                    ];

                    foreach ($sqls as $sql) {
                        $pdo->exec($sql);
                    }

                    file_put_contents($CONFIG_PATH . '/db.php',
                        "<?php\nreturn " . var_export([
                            'host' => $dbHost, 'name' => $dbName,
                            'user' => $dbUser, 'password' => $dbPass, 'charset' => 'utf8mb4',
                        ], true) . ";\n"
                    );

                    $step = 3;
                    $success = 'DB接続成功・テーブルを作成しました';

                } catch (Throwable $e) {
                    $errors[] = 'DBエラー：' . $e->getMessage();
                    $step = 2;
                }
            }
        }

        // 管理者作成
        elseif ($action === 'create_admin') {
            $email = trim($_POST['admin_email'] ?? '');
            $pass  = $_POST['admin_password']  ?? '';
            $pass2 = $_POST['admin_password2'] ?? '';

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'メールアドレスの形式が正しくありません';
            if (strlen($pass) < 8)  $errors[] = 'パスワードは8文字以上にしてください';
            if ($pass !== $pass2)   $errors[] = 'パスワードが一致しません';

            if (!$errors) {
                try {
                    $dbCfg = require $CONFIG_PATH . '/db.php';
                    $dsn   = "mysql:host={$dbCfg['host']};dbname={$dbCfg['name']};charset=utf8mb4";
                    $pdo   = new PDO($dsn, $dbCfg['user'], $dbCfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $pdo->prepare("INSERT INTO admin_users (email,password_hash,created_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)")
                        ->execute([$email, password_hash($pass, PASSWORD_DEFAULT)]);

                    @mkdir($STORAGE_PATH . '/logs', 0755, true);
                    @mkdir($BASE_PATH . '/uploads', 0755, true);
                    file_put_contents($CONFIG_PATH . '/installed.lock', date('Y-m-d H:i:s') . "\nv1.2.1\n");
                    $step = 4;
                } catch (Throwable $e) {
                    $errors[] = 'エラー：' . $e->getMessage();
                    $step = 3;
                }
            } else {
                $step = 3;
            }
        }

    } else {
        $step = (int)($_GET['step'] ?? 1);
        if ($step === 1 && file_exists($CONFIG_PATH . '/db.php')) $step = 3;
    }
}

$checks = [
    'PHP 7.4+'            => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO MySQL'           => extension_loaded('pdo_mysql'),
    'cURL'                => extension_loaded('curl'),
    'mbstring'            => extension_loaded('mbstring'),
    'json'                => extension_loaded('json'),
    'openssl'             => extension_loaded('openssl'),
    'config/ 書き込み可能' => is_writable($CONFIG_PATH),
    'storage/ 書き込み可能'=> is_writable($STORAGE_PATH) || @mkdir($STORAGE_PATH, 0755, true),
];
$allOk = !in_array(false, array_values($checks), true);
$dbHost = htmlspecialchars($_POST['db_host'] ?? 'localhost');
$dbName = htmlspecialchars($_POST['db_name'] ?? '');
$dbUser = htmlspecialchars($_POST['db_user'] ?? '');
$adminEmail = htmlspecialchars($_POST['admin_email'] ?? '');
?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>インストーラー — AIアート教室</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Hiragino Sans','Noto Sans JP',sans-serif;background:#0f1117;color:#e2e4ea;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.wrap{width:100%;max-width:500px}
.logo{text-align:center;margin-bottom:24px}
.logo h1{font-size:20px;font-weight:800;color:#a78bfa}
.logo p{font-size:12px;color:#6b7280;margin-top:4px}
.steps{display:flex;justify-content:center;align-items:center;margin-bottom:20px}
.dot{width:28px;height:28px;border-radius:50%;border:2px solid #2a2d36;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#6b7280;background:#1e2128;flex-shrink:0}
.dot.on{border-color:#7c6af7;color:#a78bfa;background:rgba(124,106,247,.15)}
.dot.ok{border-color:#22c55e;color:#22c55e}
.ln{width:28px;height:2px;background:#2a2d36}
.ln.ok{background:#22c55e}
.card{background:#1e2128;border:1px solid #2a2d36;border-radius:10px;padding:22px;margin-bottom:12px}
h2{font-size:15px;font-weight:700;margin-bottom:14px}
.fg{margin-bottom:11px}
label{display:block;font-size:11px;color:#6b7280;margin-bottom:4px;font-weight:500}
input[type=text],input[type=email],input[type=password]{width:100%;background:#0f1117;border:1px solid #2a2d36;border-radius:6px;padding:8px 11px;color:#e2e4ea;font-size:13px;font-family:inherit}
input:focus{outline:none;border-color:#7c6af7}
.btn{display:block;width:100%;padding:10px;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;border:none;margin-top:8px;color:#fff;background:#7c6af7;text-align:center;font-family:inherit;text-decoration:none}
.btn:hover{background:#a78bfa}
.row{display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid #2a2d36;font-size:13px}
.row:last-child{border-bottom:none}
.ok-i{color:#22c55e}.ng-i{color:#ef4444}
.err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:9px 12px;border-radius:6px;font-size:13px;margin-bottom:12px}
.suc{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#4ade80;padding:9px 12px;border-radius:6px;font-size:13px;margin-bottom:12px}
.hint{font-size:11px;color:#6b7280;margin-top:3px}
code{font-size:11px;background:rgba(255,255,255,.08);padding:2px 6px;border-radius:3px;word-break:break-all}
ol{padding-left:16px;line-height:2;font-size:13px;color:#6b7280}
</style>
</head>
<body>
<?php if ($redirectUrl): ?>
<script>location.href='<?= $redirectUrl ?>';</script>
<?php endif; ?>
<div class="wrap">
  <div class="logo"><h1>AIアート教室</h1><p>LINE画像生成システム v1.2.1 インストーラー</p></div>
  <div class="steps">
    <?php
    $labels=['環境','DB設定','管理者','完了'];
    foreach($labels as $i=>$lb){
      $n=$i+1;
      $cls=$n<$step?'ok':($n===$step?'on':'');
      $icon=$n<$step?'✓':$n;
      echo '<div class="dot '.$cls.'">'.$icon.'</div>';
      if($i<count($labels)-1){
        $lncls=$n<$step?'ok':'';
        echo '<div class="ln '.$lncls.'"></div>';
      }
    }
    ?>
  </div>
  <?php foreach($errors as $e): ?><div class="err">⚠ <?=htmlspecialchars($e)?></div><?php endforeach; ?>
  <?php if($success): ?><div class="suc">✓ <?=htmlspecialchars($success)?></div><?php endif; ?>

  <?php if($step===1): ?>
  <div class="card"><h2>Step 1 — 環境チェック</h2>
    <?php foreach($checks as $label=>$ok): ?>
    <div class="row"><span class="<?=$ok?'ok-i':'ng-i'?>"><?=$ok?'✓':'✗'?></span>
    <span style="<?=$ok?'':'color:#ef4444'?>"><?=htmlspecialchars($label)?></span></div>
    <?php endforeach; ?>
    <?php if($allOk): ?>
    <form method="GET" action="install.php"><input type="hidden" name="step" value="2">
    <button type="submit" class="btn" style="margin-top:16px">次へ → DB設定</button></form>
    <?php else: ?><p style="color:#ef4444;font-size:13px;margin-top:12px">✗ の項目を解決してください</p><?php endif; ?>
  </div>

  <?php elseif($step===2): ?>
  <div class="card"><h2>Step 2 — データベース設定</h2>
    <form method="POST" action="install.php">
      <input type="hidden" name="action" value="setup_db">
      <div class="fg"><label>DBホスト</label>
        <input type="text" name="db_host" value="<?=$dbHost?>" required>
        <p class="hint">通常は localhost</p></div>
      <div class="fg"><label>DB名</label>
        <input type="text" name="db_name" value="<?=$dbName?>" required placeholder="例: dzdspowl_aiart"></div>
      <div class="fg"><label>DBユーザー名</label>
        <input type="text" name="db_user" value="<?=$dbUser?>" required placeholder="例: dzdspowl_aiart"></div>
      <div class="fg"><label>DBパスワード</label>
        <input type="password" name="db_password" placeholder="DBパスワード"></div>
      <button type="submit" class="btn">接続テスト &amp; テーブル作成 →</button>
    </form>
  </div>

  <?php elseif($step===3): ?>
  <div class="card"><h2>Step 3 — 管理者アカウント</h2>
    <form method="POST" action="install.php">
      <input type="hidden" name="action" value="create_admin">
      <div class="fg"><label>メールアドレス</label>
        <input type="email" name="admin_email" value="<?=$adminEmail?>" required></div>
      <div class="fg"><label>パスワード（8文字以上）</label>
        <input type="password" name="admin_password" required minlength="8"></div>
      <div class="fg"><label>パスワード（確認）</label>
        <input type="password" name="admin_password2" required></div>
      <button type="submit" class="btn">インストール完了 →</button>
    </form>
  </div>

  <?php elseif($step===4): ?>
  <div class="card" style="text-align:center">
    <div style="font-size:48px;margin-bottom:12px">🎉</div>
    <h2 style="color:#22c55e;margin-bottom:8px">インストール完了！</h2>
    <p style="color:#6b7280;font-size:13px;margin-bottom:20px">v1.2.1 が利用可能になりました。</p>
    <div style="background:#0f1117;border:1px solid #2a2d36;border-radius:8px;padding:16px;text-align:left;margin-bottom:16px">
      <p style="font-size:12px;color:#a78bfa;font-weight:700;margin-bottom:8px">次の手順</p>
      <ol>
        <li>管理画面にログイン</li>
        <li>「API設定」でLINE・Claude・Stability AIのキーを入力</li>
        <li>LINE Webhook URLを設定<br><code>https://<?=$_SERVER['HTTP_HOST']?>/webhook/line</code></li>
        <li>cronに追加（1分ごと）<br><code>* * * * * php <?=htmlspecialchars($BASE_PATH)?>/worker.php</code></li>
        <li>「教室・参加管理」から開催日を登録</li>
      </ol>
    </div>
    <a href="/admin/login" class="btn">管理画面へ →</a>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
