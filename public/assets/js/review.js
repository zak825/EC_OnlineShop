// ============================================================
// レビュー画面（受け取り完了・レビュー記入フォーム）
//   ・選んだ★の意味を表示する
//   ・コメントの文字数を表示し、入力チェックする
//   ・投稿前に評価・コメントをチェックする（エラーがあれば送信しない）
//   ・投稿ボタンの二重押し対策
//   チェック内容とメッセージは review.php と同じ（最終的なチェックはサーバー側で行う）
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('review-form');
  if (!form) {
    return;
  }

  var ratingText  = document.getElementById('rating-text');
  var radios      = form.querySelectorAll('input[name="score"]');
  var scoreError  = document.getElementById('error_score');
  var textarea    = document.getElementById('review');
  var counter     = document.getElementById('char-count');
  var reviewError = document.getElementById('error_review');
  var button      = document.getElementById('review-submit');
  var max         = Number(textarea.dataset.max);

  // ---- ★の意味 ----
  var labels = {
    1: '★1 悪い',
    2: '★2 やや悪い',
    3: '★3 普通',
    4: '★4 良い',
    5: '★5 とても良い'
  };

  function updateRatingText() {
    var checked = form.querySelector('input[name="score"]:checked');
    ratingText.textContent = checked ? labels[checked.value] : '';
  }

  // ---- 評価のチェック（エラーがなければ '' を返す） ----
  function checkScore() {
    if (!form.querySelector('input[name="score"]:checked')) {
      return '評価を★1〜5から選んでください。';
    }
    return '';
  }

  function validateScore() {
    var message = checkScore();
    scoreError.textContent = message;
    return message === '';
  }

  // ---- コメントの文字数（サーバーの mb_strlen と同じ数え方にそろえる） ----
  function reviewLength() {
    // 前後の空白を取り除く（PHP の trim() と同じ範囲。全角スペースは取らない）
    var value = textarea.value.replace(/^[ \t\n\r\x00\x0B]+|[ \t\n\r\x00\x0B]+$/g, '');
    // 送信すると改行は \r\n（2文字）になるので、同じように数える
    value = value.replace(/\r?\n/g, '\r\n');
    // 絵文字なども1文字として数える
    return Array.from(value).length;
  }

  function checkReview() {
    if (reviewLength() > max) {
      return 'レビューは' + max + '文字以内で入力してください。';
    }
    return '';
  }

  function validateReview() {
    var length  = reviewLength();
    var message = checkReview();

    counter.textContent = length;
    counter.parentElement.classList.toggle('is-over', length > max);
    reviewError.textContent = message;
    textarea.classList.toggle('is-invalid', message !== '');
    return message === '';
  }

  // ---- 入力中のチェック ----
  radios.forEach(function (radio) {
    radio.addEventListener('change', function () {
      updateRatingText();
      if (scoreError.textContent !== '') {
        validateScore();   // エラーが出ているときだけ、選んだ時点で消す
      }
    });
  });
  textarea.addEventListener('input', validateReview);

  // ---- 投稿ボタン ----
  // エラーがあれば送信しない。なければ二重押しを防いで送信する
  form.addEventListener('submit', function (event) {
    // 2項目とも必ずチェックする（&& でつなぐと途中で止まるので別々に呼ぶ）
    var okScore  = validateScore();
    var okReview = validateReview();

    if (!okScore || !okReview) {
      event.preventDefault();
      if (!okScore) {
        form.querySelector('.rating').scrollIntoView({ behavior: 'smooth', block: 'center' });
      } else {
        textarea.focus();
      }
      return;
    }

    button.disabled    = true;
    button.textContent = '投稿中…';
  });

  // 投稿後に「戻る」で戻ってきたとき、ボタンが押せないままにならないようにする
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
      button.disabled    = false;
      button.textContent = '受け取り完了';
    }
  });

  // 画面を開いたとき：★の意味と文字数を表示する（エラーは投稿を押すまで出さない）
  updateRatingText();
  validateReview();
});