// ============================================================
// 発送通知送信画面
// ============================================================

// 二重送信防止：「発送通知を送る」を押したら一度だけ送信できるようにする
// 無効にしたボタンの値（action=ship）は送信されないので、hidden で追加してから無効にする
const shipForm = document.getElementById('ship-form');
if (shipForm) {
  shipForm.addEventListener('submit', (e) => {
    // キャンセルは普通に送信する
    if (!e.submitter || e.submitter.value !== 'ship') {
      return;
    }

    const hidden = document.createElement('input');
    hidden.type  = 'hidden';
    hidden.name  = 'action';
    hidden.value = 'ship';
    shipForm.appendChild(hidden);

    e.submitter.disabled    = true;
    e.submitter.textContent = '送信中…';
  });
}
