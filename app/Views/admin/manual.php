<?php
$pageTitle = '使い方マニュアル';
$isOwner = (($_SESSION['admin_role'] ?? 'staff') === 'owner');
ob_start();
?>

<style>
.manual-toc { position:sticky; top:0; }
.manual-toc a { display:block; padding:6px 10px; font-size:13px; color:var(--muted); text-decoration:none; border-radius:6px; }
.manual-toc a:hover { background:rgba(124,106,247,.1); color:var(--accent2); }
.manual-section { scroll-margin-top:20px; margin-bottom:32px; }
.manual-section h2 { font-size:18px; font-weight:700; color:var(--accent2); margin-bottom:12px; padding-bottom:8px; border-bottom:2px solid var(--border); }
.manual-section h3 { font-size:15px; font-weight:600; margin:16px 0 8px; color:var(--text); }
.manual-section p { font-size:14px; line-height:1.9; color:var(--text); margin-bottom:10px; }
.manual-section ol, .manual-section ul { padding-left:22px; line-height:2; font-size:14px; margin-bottom:12px; }
.manual-section code { font-size:12px; background:rgba(124,106,247,.12); color:var(--accent2); padding:2px 7px; border-radius:4px; }
.manual-step { background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:14px 18px; margin:10px 0; }
.manual-step-num { display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; border-radius:50%; background:var(--accent); color:#fff; font-size:12px; font-weight:700; margin-right:8px; }
.manual-note { background:rgba(245,158,11,.1); border-left:3px solid var(--warning); padding:10px 14px; border-radius:0 6px 6px 0; font-size:13px; margin:12px 0; }
.manual-tip { background:rgba(34,197,94,.1); border-left:3px solid var(--success); padding:10px 14px; border-radius:0 6px 6px 0; font-size:13px; margin:12px 0; }
.owner-badge { font-size:10px; background:rgba(34,197,94,.2); color:#22c55e; padding:1px 7px; border-radius:10px; margin-left:6px; vertical-align:middle; }
</style>

<div style="display:grid;grid-template-columns:200px 1fr;gap:24px">

  <!-- 目次 -->
  <div class="manual-toc">
    <div class="card" style="padding:8px">
      <div style="font-size:11px;color:var(--muted);font-weight:700;padding:6px 10px;text-transform:uppercase">目次</div>
      <a href="#overview">📖 システム概要</a>
      <a href="#flow">🔄 全体の流れ</a>
      <a href="#schedule">📅 開催日の登録</a>
      <a href="#reminder">📣 リマインダー</a>
      <a href="#qrcode">🔲 QRコード</a>
      <a href="#approve">✅ 参加申請の承認</a>
      <a href="#broadcast">📣 一斉メッセージ</a>
      <a href="#users">👥 ユーザー管理</a>
      <a href="#attendance">📋 出席履歴</a>
      <a href="#requests">🎨 画像生成の確認</a>
      <?php if ($isOwner): ?>
      <a href="#managers">🔑 管理者管理</a>
      <a href="#linecfg">📱 LINE設定</a>
      <a href="#api">⚙️ API設定</a>
      <a href="#update">🔄 アップデート</a>
      <?php endif; ?>
      <a href="#user-line">📱 受講生のLINE操作</a>
      <a href="#faq">❓ よくある質問</a>
    </div>
  </div>

  <!-- 本文 -->
  <div>

    <div class="manual-section" id="overview">
      <h2>📖 システム概要</h2>
      <p>このシステムは、AIアート教室の受講生がLINEでキーワードを送ると、AIが自動で画像を8枚生成してLINEに届けるサービスです。教室の開催日管理・参加申請・承認の仕組みを内蔵しています。</p>
      <h3>受講生の体験フロー</h3>
      <ol>
        <li>LINE公式アカウントを友だち追加</li>
        <li>教室開催日に「参加する」で参加申請</li>
        <li>管理者が承認</li>
        <li>「生成する」でアンケートに回答（画風・雰囲気・キーワード）</li>
        <li>3〜5分後に2パターン×4枚＝8枚の画像がLINEに届く</li>
      </ol>
    </div>

    <div class="manual-section" id="flow">
      <h2>🔄 全体の流れ</h2>
      <p>管理者として日々運用する基本サイクルです。</p>
      <div class="manual-step"><span class="manual-step-num">1</span><strong>開催日を登録</strong>「教室・参加管理」から開催日・時間・定員を設定</div>
      <div class="manual-step"><span class="manual-step-num">2</span><strong>当日、参加申請を承認</strong>受講生が「参加する」を送ると申請が届くので承認</div>
      <div class="manual-step"><span class="manual-step-num">3</span><strong>受講生が画像生成</strong>承認された受講生が画像を生成（自動処理）</div>
      <div class="manual-step"><span class="manual-step-num">4</span><strong>必要に応じて確認・サポート</strong>「依頼一覧」で生成状況、「出席履歴」で参加状況を確認</div>
    </div>

    <div class="manual-section" id="schedule">
      <h2>📅 開催日の登録</h2>
      <p>教室を開く日を事前に登録します。登録した日だけ受講生が参加・画像生成できます。</p>
      <h3>手順</h3>
      <ol>
        <li>左メニュー「教室・参加管理」を開く</li>
        <li>右上の「＋ 開催日を追加」をクリック</li>
        <li>以下を入力して「作成する」</li>
      </ol>
      <ul>
        <li><strong>開催日</strong>：教室を開く日</li>
        <li><strong>開始/終了時刻</strong>：教室の時間帯</li>
        <li><strong>チェックイン受付時間</strong>：この時間内だけ受講生が「参加する」を押せます</li>
        <li><strong>定員</strong>：参加できる人数の上限</li>
        <li><strong>1人あたりの生成件数上限</strong>：その日に各受講生が生成できる回数</li>
      </ul>
      <div class="manual-note">⚠ チェックイン受付時間を過ぎると、受講生は「参加する」を押せなくなります。教室開始の30分前から受付開始にするのが一般的です。</div>
    </div>

    <div class="manual-section" id="reminder">
      <h2>📣 リマインダー</h2>
      <p>教室の開催日ごとに、承認済み参加者へ自動でLINEリマインダーを送れます。参加率の向上に効果的です。</p>
      <h3>設定方法</h3>
      <ol>
        <li>「教室・参加管理」で開催日を作成または編集</li>
        <li>「リマインダー」欄に送信日時を設定（例：前日の20:00）</li>
        <li>本文は空欄でOK（自動で開催案内文が入ります）</li>
        <li>保存すると、その時刻に承認済み参加者へ自動送信されます</li>
      </ol>
      <div class="manual-tip">💡 教室一覧の「📣今すぐ送信」ボタンで、設定時刻を待たず手動送信もできます。</div>
      <div class="manual-note">⚠ 送信対象はその教室の「承認済み」参加者のみです。承認前の人には届きません。</div>
    </div>

    <div class="manual-section" id="qrcode">
      <h2>🔲 QRコード</h2>
      <p>左メニュー「QRコード」で、友だち追加用と参加受付用のQRコードを表示・ダウンロードできます。</p>
      <h3>準備</h3>
      <ol>
        <li>「QRコード」画面下部でLINE ID（@から始まるID）を設定</li>
        <li>2種類のQRコードが自動生成されます</li>
        <li>「画像保存」でPNGをダウンロード</li>
      </ol>
      <ul>
        <li><strong>友だち追加QR</strong>：チラシ・ポスター・SNSに掲載</li>
        <li><strong>参加受付QR</strong>：教室会場に掲示。読み取ると友だち追加 → 「参加する」へ誘導</li>
      </ul>
    </div>

    <div class="manual-section" id="approve">
      <h2>✅ 参加申請の承認</h2>
      <p>受講生がLINEで「参加する」を送ると、申請が管理画面に届きます。承認するとその受講生が画像生成を使えるようになります。</p>
      <h3>手順</h3>
      <ol>
        <li>「教室・参加管理」を開くと、本日の教室の申請一覧が表示されます</li>
        <li>各受講生の「承認」ボタンを押す（または「却下」）</li>
        <li>承認すると受講生のLINEに自動で「承認されました」と通知が届きます</li>
      </ol>
      <div class="manual-tip">💡 申請が多いときは「全員承認 &amp; 通知」ボタンで一括承認できます。</div>
    </div>

    <div class="manual-section" id="broadcast">
      <h2>📣 一斉メッセージ</h2>
      <p>受講生全員、または本日の参加者にLINEで一斉メッセージを送れます。</p>
      <h3>手順</h3>
      <ol>
        <li>左メニュー「一斉メッセージ」を開く</li>
        <li>送信対象を選ぶ（全受講生 / 本日の承認済み参加者 / アクティブ全員）</li>
        <li>メッセージを入力して「送信する」</li>
      </ol>
      <div class="manual-note">⚠ LINEの無料プランでは月200通までの送信制限があります。送信数にご注意ください。</div>
    </div>

    <div class="manual-section" id="users">
      <h2>👥 ユーザー管理</h2>
      <p>受講生（LINEユーザー）の一覧・詳細を確認できます。</p>
      <h3>できること</h3>
      <ul>
        <li>受講生の参加履歴・生成履歴の確認</li>
        <li>ステータス変更（有効 / 一時停止 / 禁止）</li>
        <li>個別にLINEメッセージを送信</li>
        <li>管理者用メモの記録</li>
      </ul>
    </div>

    <div class="manual-section" id="attendance">
      <h2>📋 出席履歴</h2>
      <p>過去の全ての出席記録を一覧で確認できます。日付や受講生名で絞り込みができ、統計（総出席数・参加者数・開催回数・平均参加者数）も表示されます。</p>
    </div>

    <div class="manual-section" id="requests">
      <h2>🎨 画像生成の確認</h2>
      <p>「依頼一覧」で受講生の画像生成リクエストの状況を確認できます。</p>
      <h3>ステータスの意味</h3>
      <ul>
        <li><code>received</code> 受付済み</li>
        <li><code>analyzing</code> プロンプト生成中</li>
        <li><code>generating</code> 画像生成中</li>
        <li><code>sending</code> LINE送信中</li>
        <li><code>completed</code> 完了</li>
        <li><code>failed</code> 失敗（詳細画面から再生成できます）</li>
      </ul>
      <div class="manual-tip">💡 生成に失敗した依頼は、詳細画面の「再生成」ボタンでやり直せます。</div>
    </div>

    <?php if ($isOwner): ?>
    <div class="manual-section" id="managers">
      <h2>🔑 管理者管理 <span class="owner-badge">オーナー専用</span></h2>
      <p>複数の管理者（スタッフ）を追加して、教室運営を分担できます。</p>
      <h3>権限の違い</h3>
      <ul>
        <li><strong>オーナー</strong>：全機能 + 管理者管理・API設定・アップデート</li>
        <li><strong>スタッフ</strong>：教室運営・参加承認・一斉送信・閲覧のみ</li>
      </ul>
      <h3>管理者の追加手順</h3>
      <ol>
        <li>左メニュー「管理者アカウント」を開く</li>
        <li>右側の「管理者を追加」に名前・メール・権限・初期パスワードを入力</li>
        <li>「追加する」をクリック</li>
        <li>追加した管理者にメールとパスワードを伝える</li>
      </ol>
      <div class="manual-note">⚠ 最後のオーナーは削除・降格できません（締め出し防止のため）。</div>
    </div>

    <div class="manual-section" id="linecfg">
      <h2>📱 LINE設定 <span class="owner-badge">オーナー専用</span></h2>
      <p>友だち追加時のあいさつメッセージや、トーク画面下部のリッチメニュー（6ボタン）を管理画面から設定できます。</p>
      <h3>あいさつメッセージ</h3>
      <p>友だち追加した瞬間に送られるメッセージを編集できます。改行・絵文字が使えます。</p>
      <h3>リッチメニュー（6ボタン）</h3>
      <ol>
        <li>6つのボタンのアイコン・ラベル・送信テキストを設定</li>
        <li>「ボタン設定を保存」をクリック</li>
        <li>メニュー画像を「自動生成」か「アップロード」から選ぶ</li>
        <li>「LINEに反映する」で全受講生に表示</li>
      </ol>
      <div class="manual-note">⚠ 自動生成はサーバーにImagick拡張が必要です。使えない場合は2500×1686pxの画像をアップロードしてください。</div>
      <div class="manual-tip">💡 ボタンの「送信テキスト」を「参加する」「生成する」などにすると、タップだけで操作できます。</div>
    </div>

    <div class="manual-section" id="api">
      <h2>⚙️ API設定 <span class="owner-badge">オーナー専用</span></h2>
      <p>システムが使う外部サービスのAPIキーを設定します。</p>
      <ul>
        <li><strong>LINE</strong>：メッセージの送受信</li>
        <li><strong>Claude API</strong>：キーワードから画像生成プロンプトを作成</li>
        <li><strong>Stability AI</strong>：実際の画像生成</li>
      </ul>
      <div class="manual-tip">💡 各APIには「接続テスト」ボタンがあります。キーを入力したら必ずテストして接続を確認してください。</div>
    </div>

    <div class="manual-section" id="update">
      <h2>🔄 アップデート <span class="owner-badge">オーナー専用</span></h2>
      <p>新しいバージョンのZIPファイルをアップロードするだけでシステムを更新できます。</p>
      <h3>手順</h3>
      <ol>
        <li>左メニュー「アップデート」を開く</li>
        <li>新しいバージョンのZIPファイルを選択</li>
        <li>「アップロードしてアップデート」をクリック</li>
      </ol>
      <div class="manual-tip">💡 設定・画像・受講生データは保持されます。</div>
    </div>
    <?php endif; ?>

    <div class="manual-section" id="user-line">
      <h2>📱 受講生のLINE操作</h2>
      <p>受講生がLINEで使えるコマンドです。受講生から問い合わせがあったときの参考にしてください。</p>
      <ul>
        <li><strong>参加する</strong>：開催日に参加申請する</li>
        <li><strong>生成する</strong>：画像生成のアンケートを始める</li>
        <li><strong>履歴</strong>：自分の参加履歴・生成履歴を確認する</li>
        <li><strong>キャンセル</strong>：アンケートを途中でやめる</li>
      </ul>
      <h3>画像生成のアンケート</h3>
      <ol>
        <li>画風を選ぶ（アニメ / 水彩 / リアル / 和風 / おまかせ）</li>
        <li>雰囲気を選ぶ（幻想的 / かわいい / クール / ダーク / 温かい / おまかせ）</li>
        <li>キーワードを入力（例：月、少女、森）</li>
      </ol>
    </div>

    <div class="manual-section" id="faq">
      <h2>❓ よくある質問</h2>
      <h3>Q. 受講生が「画像が来ない」と言っている</h3>
      <p>「依頼一覧」でその受講生の依頼ステータスを確認してください。<code>failed</code> なら詳細画面から再生成できます。<code>generating</code> のままなら、サーバーのcron（自動処理）が止まっている可能性があります。</p>
      <h3>Q. 受講生が「参加するボタンが効かない」と言っている</h3>
      <p>チェックイン受付時間外の可能性があります。「教室・参加管理」で本日の受付時間を確認してください。</p>
      <h3>Q. ダークモードが見づらい</h3>
      <p>左下の🌙ボタンでライトモードに切り替えられます。</p>
      <h3>Q. 月の途中でLINEメッセージが送れなくなった</h3>
      <p>LINE無料プランの月間送信上限（200通）に達した可能性があります。LINE公式アカウントの管理画面で確認してください。</p>
    </div>

  </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
