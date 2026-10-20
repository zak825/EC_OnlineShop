// ============================================================
// レビュー画面（受け取り完了・レビュー記入フォーム）
//   ・選んだ★の意味を表示する
//   ・コメントの文字数を表示する
//   ・投稿ボタンの二重押し対策
//   （入力チェックはサーバ側でも必ず行っている）
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('review-form');
  if (!form) {
    return;
  }

  // ---- ★の意味 ----
  var labels = {
    1: '★1 悪い',
    2: '★2 やや悪い',
    3: '★3 普通',
    4: '★4 良い',
    5: '★5 とても良い'
  };
  var ratingText = document.getElementById('rating-text');
  var radios = form.querySelectorAll('input[name="score"]');

  function updateRatingText() {
    var checked = form.querySelector('input[name="score"]:checked');
    ratingText.textContent = checked ? labels[checked.value] : '';
  }

  radios.forEach(function (radio) {
    radio.addEventListener('change', updateRatingText);
  });
  updateRatingText();

  // ---- 文字数 ----
  var textarea = document.getElementById('review');
  var counter = document.getElementById('char-count');
  var max = Number(textarea.getAttribute('maxlength'));

  function updateCount() {
    // サロゲートペア（絵文字など）も1文字として数える
    var length = Array.from(textarea.value).length;
    counter.textContent = length;
    counter.parentElement.classList.toggle('is-over', length > max);
  }

  textarea.addEventListener('input', updateCount);
  updateCount();

  // ---- 二重押し対策 ----
  form.addEventListener('submit', function () {
    var button = document.getElementById('review-submit');
    button.disabled = true;
    button.textContent = '投稿中…';
  });
});
