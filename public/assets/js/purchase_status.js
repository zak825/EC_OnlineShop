// 購入ボタンのリアルタイム制御
//   5秒ごとに purchase_status.php を呼び、買えなくなったらボタンを押せなくする
//   （最終的なチェックは今まで通りサーバー側。これは画面を見ている人への先回りの表示）
(function () {
  const form = document.querySelector('[data-status-url]');
  if (!form) {
    return;   // 購入できない状態の画面（フォームなし）では何もしない
  }

  const button  = form.querySelector('[data-live-buy]');
  const message = document.querySelector('[data-live-message]');
  const state   = document.querySelector('[data-live-state]');
  const url     = form.dataset.statusUrl + '?productId=' + encodeURIComponent(form.dataset.productId);

  const INTERVAL_MS = 5000;

  const MESSAGES = {
    sold:       'この商品は売り切れになりました。',
    own:        '自分の出品した商品は購入できません。',
    not_found:  '商品が見つかりません。出品が取り下げられた可能性があります。',
    logged_out: 'セッションの有効期限が切れました。再度ログインしてください。'
  };
  const BUTTON_LABELS = {
    sold:       '売り切れ',
    own:        '購入できません',
    not_found:  '購入できません',
    logged_out: '購入できません'
  };

  let timer   = null;
  let stopped = false;

  // 買えない状態になった：ボタンを押せなくして、理由を出す（一度止めたら元に戻さない）
  function block(status) {
    stopped = true;
    clearTimeout(timer);

    if (button) {
      button.disabled    = true;
      button.textContent = BUTTON_LABELS[status];
    }
    if (message) {
      message.textContent = MESSAGES[status];
      message.hidden      = false;
    }
    if (state && status === 'sold') {
      state.textContent = '売り切れ';
    }
  }

  // 次の確認の予約（タブが裏にあるときは止める）
  function schedule() {
    clearTimeout(timer);
    if (stopped || document.hidden) {
      return;
    }
    timer = setTimeout(check, INTERVAL_MS);
  }

  function check() {
    if (stopped) {
      return;
    }
    fetch(url, { cache: 'no-store', credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (res) {
        if (!res.ok) {
          throw new Error('HTTP ' + res.status);
        }
        return res.json();
      })
      .then(function (data) {
        if (MESSAGES[data.status]) {
          block(data.status);
        } else {
          schedule();   // ok のとき
        }
      })
      .catch(function () {
        schedule();     // 通信エラーのときは何も変えず、次回また確認する
      });
  }

  // タブを開き直したら、すぐ確認する
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      clearTimeout(timer);
    } else {
      check();
    }
  });

  schedule();
})();