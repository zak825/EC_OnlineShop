// 販売画面：リアルタイムバリデーション
// チェック内容とメッセージは sell.php と同じ（最終的なチェックはサーバー側で行う）

var form        = document.getElementById('sell_form');
var productName = document.getElementById('product_name');
var genreCode   = document.getElementById('genre_code');
var price       = document.getElementById('price');

// 値段の下限・上限（sell.php が DB から取得した値）
var priceMin = Number(price.dataset.priceMin);
var priceMax = Number(price.dataset.priceMax);

// 前後の空白（全角スペースを含む）を取り除く
function trimAll(value) {
  return value.replace(/^[\s　]+|[\s　]+$/g, '');
}

// 商品名のチェック（エラーがなければ '' を返す）
function checkProductName() {
  var value = trimAll(productName.value);
  if (value === '') {
    return '商品名を入力してください';
  }
  // 絵文字なども1文字として数える（PHP の mb_strlen と同じ）
  if (Array.from(value).length > 50) {
    return '商品名は50文字以内で入力してください';
  }
  return '';
}

// ジャンルのチェック
function checkGenreCode() {
  if (genreCode.value === '') {
    return 'ジャンルを選択してください';
  }
  return '';
}

// 値段のチェック
function checkPrice() {
  // カンマ（全角含む）を取り除く → 全角数字を半角に変換する
  var value = trimAll(price.value).replace(/[,，]/g, '');
  value = value.replace(/[０-９]/g, function(c) {
    return String.fromCharCode(c.charCodeAt(0) - 0xFEE0);
  });

  if (value === '') {
    return '値段を入力してください';
  }
  if (!/^[0-9]+$/.test(value)) {
    return '値段は数字で入力してください';
  }

  // 先頭の0を除いて11桁以上ある値は範囲外とする
  var digits = value.replace(/^0+/, '');
  var number = Number(value);
  if (digits.length > 10 || number < priceMin || number > priceMax) {
    return '値段は' + priceMin.toLocaleString() + '円以上' + priceMax.toLocaleString() + '円以下で入力してください';
  }
  return '';
}

// チェック結果を画面に出す（エラー欄と入力欄の色）
function showResult(input, message) {
  document.getElementById('error_' + input.id).textContent = message;
  input.classList.toggle('is-invalid', message !== '');
  input.classList.toggle('is-valid', message === '');
}

function validateProductName() {
  var message = checkProductName();
  showResult(productName, message);
  return message === '';
}

function validateGenreCode() {
  var message = checkGenreCode();
  showResult(genreCode, message);
  return message === '';
}

function validatePrice() {
  var message = checkPrice();
  showResult(price, message);
  return message === '';
}

// サーバー側のエラーで画面が戻ってきたときは、その入力欄を最初から赤くする
[productName, genreCode, price].forEach(function(input) {
  if (document.getElementById('error_' + input.id).textContent !== '') {
    input.classList.add('is-invalid');
  }
});

// 入力するたび・入力欄から離れたときにチェックする
productName.addEventListener('input', validateProductName);
productName.addEventListener('blur', validateProductName);
genreCode.addEventListener('change', validateGenreCode);
genreCode.addEventListener('blur', validateGenreCode);
price.addEventListener('input', validatePrice);
price.addEventListener('blur', validatePrice);

// 販売ボタン：エラーがあれば送信しない（クリア・戻るはそのまま送信する）
form.addEventListener('submit', function(event) {
  if (!event.submitter || event.submitter.value !== 'sell') {
    return;
  }

  // 3項目とも必ずチェックする（&& でつなぐと途中で止まるので別々に呼ぶ）
  var okProductName = validateProductName();
  var okGenreCode   = validateGenreCode();
  var okPrice       = validatePrice();

  if (!okProductName || !okGenreCode || !okPrice) {
    event.preventDefault();
  }
});
