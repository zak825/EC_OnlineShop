// ============================================================
// 購入画面
//   数量を変えたら小計を更新する
//   （数量の範囲チェックはサーバ側でも必ず行っている）
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
  var input = document.getElementById('quantity');
  var subtotal = document.getElementById('subtotal');
  if (!input || !subtotal) {
    return; // 売り切れ等でフォームが無い場合
  }

  var price = Number(input.dataset.price);

  input.addEventListener('input', function () {
    var qty = parseInt(input.value, 10);
    if (isNaN(qty) || qty < 1) {
      subtotal.textContent = '-';
      return;
    }
    subtotal.textContent = formatYen(price * qty);
  });
});
