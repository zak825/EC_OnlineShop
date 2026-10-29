// 販売画面：リアルタイムバリデーション
// チェック内容とメッセージは sell.php と同じ（最終的なチェックはサーバー側で行う）

var form        = document.getElementById('sell_form');
var productName = document.getElementById('product_name');
var genreCode   = document.getElementById('genre_code');
var price       = document.getElementById('price');
var condition   = document.getElementById('condition');
var description = document.getElementById('description');
var descriptionCounter = document.getElementById('description_counter');

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

function validateCondition() {
  var message = condition.value === '' ? '商品の状態を選択してください' : '';
  showResult(condition, message);
  return message === '';
}

function validateDescription() {
  var value = trimAll(description.value);
  var message = value === '' ? '商品の説明を入力してください'
    : (Array.from(value).length > Number(description.maxLength)
      ? '商品の説明は' + description.maxLength + '文字以内で入力してください' : '');
  showResult(description, message);
  return message === '';
}

function updateDescriptionCounter() {
  descriptionCounter.textContent = Array.from(description.value).length
    + ' / ' + description.maxLength + '文字';
  descriptionCounter.classList.toggle(
    'is-limit',
    Array.from(description.value).length >= description.maxLength
  );
}

description.addEventListener('input', updateDescriptionCounter);
updateDescriptionCounter();

// 画像のチェック（任意。選ばれたときだけ）
var images    = document.getElementById('images');
var imageMax  = Number(images.dataset.imageMax);
var imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
var imageFileCount = document.getElementById('image_file_count');

function updateImageFileCount() {
  imageFileCount.textContent = images.files.length > 0
    ? images.files.length + 'ファイル'
    : '';
}

images.addEventListener('change', updateImageFileCount);

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

// ↓ 追加：input に入れる順番を、画面の左から順にそろえる
//   プレビューは row-reverse なので、picked の先頭は画面の一番右に見える。
//   画面の左から 1枚目・2枚目… として保存されるよう、逆順にして input に入れる
function syncInputFiles() {
  var list = Array.from(picked.files).reverse();
  var dt   = new DataTransfer();
  list.forEach(function(file) {
    dt.items.add(file);
  });
  images.files = dt.files;
}

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
    img.draggable = false;   // 画像ではなく枠ごとドラッグさせる

    // ×ボタンと、ドラッグ用の枠で包む（i は picked.files の何番目か）
    var cell = document.createElement('div');
    cell.className     = 'image-list__cell';
    cell.dataset.index = i;
    cell.draggable     = true;

    var remove = document.createElement('button');
    remove.type      = 'button';
    remove.className = 'image-list__remove';
    remove.setAttribute('aria-label', 'この画像を削除');
    remove.innerHTML = '&times;';

    cell.appendChild(img);
    cell.appendChild(remove);
    imagePreview.appendChild(cell);
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

// ×ボタン：選んだ画像を1枚だけ外す（サーバーに保存済みの画像には×を付けないので影響しない）
imagePreview.addEventListener('click', function(event) {
  var button = event.target.closest('.image-list__remove');
  if (!button) {
    return;
  }
  var index = Number(button.parentNode.dataset.index);

  picked.items.remove(index);     // ためている画像から1枚外す
  syncInputFiles();               // 送信される内容も合わせる

  validateImages();               // 「10枚まで」などのエラー表示を更新
  renderImagePreview();           // 0枚になったら保存済み画像の表示に戻る
});

// ------------------------------------------------------------
// ドラッグ&ドロップで画像の順番を入れ替える（選んだ画像だけ。保存済みの画像は対象外）
//   ドラッグした画像を、ドロップした画像の位置に移す
//   画像のない空きスペースで離したときは、カーソルの位置に合わせて入れる
//   （空きスペースは1枚目の右側なので、そこで離すと1枚目になる）
// ------------------------------------------------------------
var dragIndex = null;   // ドラッグ中の画像が picked の何番目か

function clearDragClasses() {
  imagePreview.querySelectorAll('.is-dragging, .is-dragover').forEach(function(el) {
    el.classList.remove('is-dragging', 'is-dragover');
  });
}

// picked の from 番目を to 番目の位置に移す
function movePicked(from, to) {
  var list  = Array.from(picked.files);
  var moved = list.splice(from, 1)[0];
  list.splice(to, 0, moved);

  picked = new DataTransfer();
  list.forEach(function(file) {
    picked.items.add(file);
  });
  syncInputFiles();   // 送信される順番も合わせる
  renderImagePreview();
}

// 空きスペースで離したとき、入れる位置を決める（カーソルより右にある、ほかの画像の数）
function indexFromX(x, from) {
  var count = 0;
  Array.from(imagePreview.children).forEach(function(c, i) {
    if (i === from) {
      return;
    }
    var r = c.getBoundingClientRect();
    if (r.left + r.width / 2 > x) {
      count++;
    }
  });
  return count;
}

imagePreview.addEventListener('dragstart', function(event) {
  var cell = event.target.closest('.image-list__cell');
  if (!cell) {
    return;
  }
  dragIndex = Number(cell.dataset.index);
  event.dataTransfer.effectAllowed = 'move';
  event.dataTransfer.setData('text/plain', String(dragIndex));   // Firefox は何か入れないとドラッグが始まらない
  cell.classList.add('is-dragging');
});

imagePreview.addEventListener('dragover', function(event) {
  if (dragIndex === null) {
    return;
  }
  event.preventDefault();   // これがないとドロップできない
  event.dataTransfer.dropEffect = 'move';
  imagePreview.querySelectorAll('.is-dragover').forEach(function(el) {
    el.classList.remove('is-dragover');
  });
  var cell = event.target.closest('.image-list__cell');
  if (cell) {
    cell.classList.add('is-dragover');
  }
});

imagePreview.addEventListener('drop', function(event) {
  if (dragIndex === null) {
    return;
  }
  event.preventDefault();
  var from = dragIndex;
  dragIndex = null;

  var cell = event.target.closest('.image-list__cell');
  var to   = cell ? Number(cell.dataset.index) : indexFromX(event.clientX, from);

  if (from !== to) {
    movePicked(from, to);
  } else {
    clearDragClasses();
  }
});

imagePreview.addEventListener('dragend', function() {
  dragIndex = null;
  clearDragClasses();
});

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

  syncInputFiles();

  validateImages();
  if (over) {
    document.getElementById('error_images').textContent = '画像は' + imageMax + '枚までです';
  }
  renderImagePreview();
});

// サーバー側のエラーで画面が戻ってきたときは、その入力欄を最初から赤くする
[productName, genreCode, price, condition, description].forEach(function(input) {
  if (document.getElementById('error_' + input.id).textContent.trim() !== '') {   // 空白だけなら「エラーなし」
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
condition.addEventListener('change', validateCondition);
description.addEventListener('input', validateDescription);
description.addEventListener('blur', validateDescription);

// ------------------------------------------------------------
// 下書き保存のモーダル
//   「戻る」を押したとき、入力がすべて正しければ「下書き保存しますか？」を出す
//   保存する → action=save_draft で送信／保存しない → action=back_no_save で送信／キャンセル → 閉じて入力に戻る
// ------------------------------------------------------------
var draftSaveModal = document.getElementById('draft_save_modal');

// 入力がすべて正しいか（エラーメッセージは出さずに調べる）
function isAllValid() {
  return checkProductName() === '' && checkGenreCode() === '' && checkPrice() === ''
    && condition.value !== '' && checkImages() === '' && validateDescription();
}

// action を hidden で付けてフォームを送信する（form.submit() は押したボタンの値を送らないため）
function submitWithAction(action) {
  var hidden = document.createElement('input');
  hidden.type  = 'hidden';
  hidden.name  = 'action';
  hidden.value = action;
  form.appendChild(hidden);
  form.submit();
}

function closeDraftSaveModal() {
  draftSaveModal.hidden = true;
}

document.getElementById('draft_save_yes').addEventListener('click', function() {
  this.disabled = true;   // 二重送信防止
  submitWithAction('save_draft');
});
document.getElementById('draft_save_no').addEventListener('click', function() {
  // 「戻る」と区別して送る（下書きを使って入力していたなら、サーバー側で下書きを削除する）
  submitWithAction('back_no_save');
});
document.getElementById('draft_save_cancel').addEventListener('click', closeDraftSaveModal);
document.addEventListener('keydown', function(event) {
  if (event.key === 'Escape' && !draftSaveModal.hidden) {
    closeDraftSaveModal();
  }
});

// 販売ボタン：エラーがあれば送信しない
// 戻るボタン：入力がすべて正しければ、下書き保存のモーダルを出す（正しくなければそのまま戻る）
// クリアボタン：そのまま送信する
form.addEventListener('submit', function(event) {
  if (event.submitter && event.submitter.value === 'back') {
    if (isAllValid()) {
      event.preventDefault();
      draftSaveModal.hidden = false;
      document.getElementById('draft_save_yes').focus();
    }
    return;
  }

  if (!event.submitter || event.submitter.value !== 'sell') {
    return;
  }

  // すべての項目を必ず個別にチェックする（&& でつなぐと途中で止まる）
  var okProductName = validateProductName();
  var okGenreCode   = validateGenreCode();
  var okPrice       = validatePrice();
  var okCondition   = validateCondition();
  var okDescription = validateDescription();
  var okImages      = validateImages();

  if (!okProductName || !okGenreCode || !okPrice || !okCondition || !okDescription || !okImages) {
    event.preventDefault();
  }
});

// ロゴからメイン画面へ移動する場合も、入力が完成していれば自動保存する。
document.querySelectorAll('header a[href="index.php"], header a[href="mypage.php"]').forEach(function(link) {
  link.addEventListener('click', function(event) {
    if (!isAllValid()) {
      return;
    }
    event.preventDefault();
    submitWithAction('save_draft');
  });
});
// ------------------------------------------------------------
// 画像プレビューの左右の矢印
//   押すとその方向に横スクロールする。はみ出していないとき・端まで来たときは、その矢印を隠す
//   （並びが row-reverse なので、scrollLeft ではなく画像の位置で端かどうかを判断する）
// ------------------------------------------------------------
var imagePrev = document.getElementById('image_prev');
var imageNext = document.getElementById('image_next');

function updateImageArrows() {
  var items = imagePreview.children;
  if (items.length === 0) {
    imagePrev.hidden = true;
    imageNext.hidden = true;
    return;
  }

  // いちばん左の画像の左端・いちばん右の画像の右端を探す
  var box   = imagePreview.getBoundingClientRect();
  var left  = Infinity;
  var right = -Infinity;
  for (var i = 0; i < items.length; i++) {
    var r = items[i].getBoundingClientRect();
    left  = Math.min(left, r.left);
    right = Math.max(right, r.right);
  }

  imagePrev.hidden = left >= box.left - 1;     // 左に隠れている画像が無い
  imageNext.hidden = right <= box.right + 1;   // 右に隠れている画像が無い
}

// 1回押すと、見えている幅の8割ぶん動かす
function scrollImages(direction) {
  imagePreview.scrollBy({ left: direction * imagePreview.clientWidth * 0.8, behavior: 'smooth' });
}

imagePrev.addEventListener('click', function() { scrollImages(-1); });
imageNext.addEventListener('click', function() { scrollImages(1); });
imagePreview.addEventListener('scroll', updateImageArrows);
window.addEventListener('resize', updateImageArrows);

// いちばん左（新しく選んだ画像）が見える位置に戻す
// row-reverse ではスクロールの基準が右端なので、左端は scrollLeft がマイナス側になる（大きく引けば左端で止まる）
function showNewestImages() {
  imagePreview.scrollLeft = -imagePreview.scrollWidth;
  updateImageArrows();
}

// プレビューの画像が入れ替わったら左端に戻して矢印を出し直す（登場の動き 0.25 秒が終わったあとにも、もう一度）
new MutationObserver(function() {
  showNewestImages();
  setTimeout(updateImageArrows, 300);
}).observe(imagePreview, { childList: true });

showNewestImages();
