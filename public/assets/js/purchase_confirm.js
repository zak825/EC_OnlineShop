// ============================================================
// 購入確認画面
//   確定ボタンの二重押し対策（押したらボタンを無効にする）
//   ※ サーバ側でもワンタイムトークンで二重送信を防いでいる
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('confirm-form');
  if (!form) {
    return;
  }

  form.addEventListener('submit', function (e) {
    var button = e.submitter;
    if (!button || button.value !== 'confirm') {
      return;
    }

    // disabled にすると action の値が送られないので hidden で補う
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'action';
    input.value = 'confirm';
    form.appendChild(input);

    button.disabled = true;
    button.textContent = '処理中…';
  });
});
