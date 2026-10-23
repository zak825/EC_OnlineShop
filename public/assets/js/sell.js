// 販売画面：リアルタイムバリデーション
// チェック内容とメッセージは sell.php と同じ（最終的なチェックはサーバー側で行う）

var form        = document.getElementById('sell_form');
var productName = document.getElementById('product_name');
var genreCode   = document.getElementById('genre_code');
var price       = document.getElementById('price');

// 値段の下限・上限（sell.php が DB から取得した値）
var priceMin = Number(price.dataset.priceMin);
var priceMax = Number(price.dataset.priceMax);

// 商品名の最大文字数（sell.php が DB から取得した値）
var nameMax = Number(productName.dataset.nameMax);

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
  if (Array.from(value).length > nameMax) {
    return '商品名は' + nameMax + '文字以内で入力してください';
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

// 画像のチェック（任意。選ばれたときだけ）
var images    = document.getElementById('images');
var imageMax  = Number(images.dataset.imageMax);
var imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

function checkImages() {
  var files = images.files;
  if (files.length > imageMax) {
    return '画像は' + imageMax + '枚までです';
  }
  for (var i = 0; i < files.length; i++) {
    if (files[i].size > 2 * 1024 * 1024) {
      return '画像は1枚2MBまでです';
    }
    if (imageTypes.indexOf(files[i].type) === -1) {
      return 'JPEG・PNG・GIF・WebP の画像を選択してください';
    }
  }
  return '';
}

function validateImages() {
  var message = checkImages();
  document.getElementById('error_images').textContent = message;
  return message === '';
}

// 選んだ画像をすぐにプレビューする（サーバーには送らず、ブラウザの中だけで表示する）
// file input は選び直すたびに前の選択を捨てるので、DataTransfer に自分でためていく
var imagePreview      = document.getElementById('image_preview');
var imagePreviewLabel = document.getElementById('image_preview_label');
var previewUrls       = [];   // 前のプレビューのURL（作り直すときに解放する）
var savedPreviewHtml  = imagePreview.innerHTML;   // サーバーに保存済みの画像（販売確認画面から戻ったとき）
var picked            = new DataTransfer();   // 選んだ画像をためておく入れ物

// ためている画像でプレビューを作り直す（DOM で後の img ほど CSS で左に来る）
function renderImagePreview() {
  previewUrls.forEach(function(url) {
    URL.revokeObjectURL(url);
  });
  previewUrls = [];

  // 1枚も無いときは、保存済みの画像の表示に戻す
  if (picked.files.length === 0) {
    imagePreview.innerHTML = savedPreviewHtml;
    imagePreviewLabel.hidden = savedPreviewHtml.trim() === '';
    return;
  }

  imagePreview.innerHTML = '';
  for (var i = 0; i < picked.files.length; i++) {
    var url = URL.createObjectURL(picked.files[i]);
    previewUrls.push(url);

    var img = document.createElement('img');
    img.src       = url;
    img.alt       = '商品画像';
    img.className = 'image-list__item';
    img.width     = 100;
    img.height    = 100;
    imagePreview.appendChild(img);
  }
  imagePreviewLabel.hidden = imagePreview.children.length === 0;
}

// 同じファイルが既にためられているか
function isAlreadyPicked(file) {
  for (var i = 0; i < picked.files.length; i++) {
    var g = picked.files[i];
    if (g.name === file.name && g.size === file.size && g.lastModified === file.lastModified) {
      return true;
    }
  }
  return false;
}

images.addEventListener('change', function() {
  // 今回ダイアログで選ばれた分を、ためているリストに足していく
  var files = images.files;
  var over  = false;

  for (var i = 0; i < files.length; i++) {
    if (picked.files.length >= imageMax) {   // 上限まで来たらそれ以上足さない
      over = true;
      break;
    }
    if (imageTypes.indexOf(files[i].type) === -1) {
      continue;   // 画像でないファイルは足さない（エラーは validateImages が出す）
    }
    if (!isAlreadyPicked(files[i])) {
      picked.items.add(files[i]);
    }
  }

  // input 自体のファイルを、ためた全部に差し替える（送信もこの全部になる）
  images.files = picked.files;

  validateImages();
  if (over) {
    document.getElementById('error_images').textContent = '画像は' + imageMax + '枚までです';
  }
  renderImagePreview();
});

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

  // 4項目とも必ずチェックする（&& でつなぐと途中で止まるので別々に呼ぶ）
  var okProductName = validateProductName();
  var okGenreCode   = validateGenreCode();
  var okPrice       = validatePrice();
  var okImages      = validateImages();

  if (!okProductName || !okGenreCode || !okPrice || !okImages) {
    event.preventDefault();
  }
});