<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>教室予約カレンダー</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Hiragino Sans','Noto Sans JP',sans-serif;background:#f4f5f7;color:#1a202c;padding:16px;max-width:600px;margin:0 auto}
.header{text-align:center;margin-bottom:20px}
.header h1{font-size:18px;color:#7c6af7;font-weight:800}
.header p{font-size:12px;color:#718096;margin-top:4px}
.event{background:#fff;border-radius:12px;padding:16px;margin-bottom:12px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.event-date{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.event-day{background:#7c6af7;color:#fff;border-radius:8px;padding:6px 10px;text-align:center;min-width:54px}
.event-day .d{font-size:20px;font-weight:800;line-height:1}
.event-day .m{font-size:10px}
.event-title{font-weight:700;font-size:15px}
.event-time{font-size:13px;color:#718096;margin-top:2px}
.event-meta{font-size:12px;color:#718096;margin:8px 0;line-height:1.7}
.reserve-bar{background:#edf0f5;border-radius:6px;height:6px;overflow:hidden;margin:8px 0}
.reserve-bar > div{height:100%;background:#7c6af7}
.btn{display:block;width:100%;padding:12px;border-radius:8px;font-size:15px;font-weight:600;border:none;cursor:pointer;color:#fff;background:#7c6af7;margin-top:8px}
.btn:disabled{background:#cbd5e0;cursor:not-allowed}
.btn-full{background:#f87171}
.empty{text-align:center;color:#718096;padding:40px;font-size:14px}
.badge{display:inline-block;font-size:11px;padding:2px 8px;border-radius:10px;font-weight:600}
.badge-zoom{background:rgba(96,165,250,.2);color:#3b82f6}
.badge-real{background:rgba(52,211,153,.2);color:#059669}
.loading{text-align:center;padding:40px;color:#718096}
#toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#1a202c;color:#fff;padding:12px 20px;border-radius:8px;font-size:14px;opacity:0;transition:opacity .3s;z-index:100;max-width:90%;text-align:center}
#toast.show{opacity:1}
</style>
</head>
<body>
<div class="header">
  <h1>🎨 教室予約カレンダー</h1>
  <p>参加したい教室を選んで予約してください</p>
</div>

<div id="loading" class="loading">読み込み中...</div>
<div id="events"></div>
<div id="toast"></div>

<script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
<script>
const LIFF_ID = <?= json_encode(Settings::get('liff_id', '')) ?>;
const EVENTS = <?= json_encode($events, JSON_UNESCAPED_UNICODE) ?>;
let idToken = null;

function toast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3000);
}

async function main() {
  if (!LIFF_ID) {
    document.getElementById('loading').textContent = 'LIFF IDが設定されていません。管理者にお問い合わせください。';
    return;
  }
  try {
    await liff.init({ liffId: LIFF_ID });
    if (!liff.isLoggedIn()) {
      liff.login();
      return;
    }
    idToken = liff.getIDToken();
    renderEvents();
  } catch (e) {
    document.getElementById('loading').textContent = 'LINE連携の初期化に失敗しました：' + e.message;
  }
}

function renderEvents() {
  document.getElementById('loading').style.display = 'none';
  const container = document.getElementById('events');

  if (EVENTS.length === 0) {
    container.innerHTML = '<div class="empty">現在予約できる教室はありません。<br>次回の開催をお待ちください。</div>';
    return;
  }

  const weekdays = ['日','月','火','水','木','金','土'];
  container.innerHTML = EVENTS.map(ev => {
    const dt = new Date(ev.date + 'T00:00:00');
    const wd = weekdays[dt.getDay()];
    const ratio = ev.capacity > 0 ? Math.min(100, Math.round(ev.reserved / ev.capacity * 100)) : 0;
    const fmtBadge = ev.format === 'zoom'
      ? '<span class="badge badge-zoom">オンライン</span>'
      : (ev.format === 'hybrid' ? '<span class="badge badge-zoom">ハイブリッド</span>' : '<span class="badge badge-real">会場</span>');

    return `
    <div class="event">
      <div class="event-date">
        <div class="event-day">
          <div class="d">${dt.getDate()}</div>
          <div class="m">${dt.getMonth()+1}月(${wd})</div>
        </div>
        <div>
          <div class="event-title">${escapeHtml(ev.title)} ${fmtBadge}</div>
          <div class="event-time">${ev.start}〜${ev.end}</div>
        </div>
      </div>
      ${ev.organizer ? `<div class="event-meta">主催：${escapeHtml(ev.organizer)}</div>` : ''}
      ${ev.location && ev.format !== 'zoom' ? `<div class="event-meta">📍 ${escapeHtml(ev.location)}</div>` : ''}
      <div class="reserve-bar"><div style="width:${ratio}%;${ev.full?'background:#f87171':''}"></div></div>
      <div class="event-meta">予約 ${ev.reserved}${ev.capacity>0?' / 定員'+ev.capacity:''}人 ${ev.full?'（満席）':''}</div>
      <button class="btn ${ev.full?'btn-full':''}" ${ev.full?'disabled':''} onclick="reserve(${ev.id}, this)">
        ${ev.full ? '満席' : 'この教室を予約する'}
      </button>
    </div>`;
  }).join('');
}

async function reserve(scheduleId, btn) {
  btn.disabled = true;
  btn.textContent = '予約中...';
  try {
    const res = await fetch('/liff/reserve', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ idToken, scheduleId })
    });
    const data = await res.json();
    if (data.ok) {
      if (data.payment_required && data.payment_url) {
        // 決済ページへ遷移
        toast(data.message);
        setTimeout(function() { window.location.href = data.payment_url; }, 800);
        return;
      }
      btn.textContent = data.already ? '予約済み' : '✓ 予約完了';
      toast(data.message);
    } else {
      btn.disabled = false;
      btn.textContent = 'この教室を予約する';
      toast(data.message || '予約に失敗しました');
    }
  } catch (e) {
    btn.disabled = false;
    btn.textContent = 'この教室を予約する';
    toast('通信エラーが発生しました');
  }
}

function escapeHtml(s) {
  const d = document.createElement('div');
  d.textContent = s;
  return d.innerHTML;
}

main();
</script>
</body>
</html>
