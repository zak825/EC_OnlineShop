// ============================================================
// マイページ：アイコン変更モーダル
//   「アイコンを変更」→ モーダルが開く
//   → ファイルを選ぶとプレビューに表示
//   → 「決定」でフォームを送信（mypage.php の upload_icon で保存）
// ============================================================
(function () {
  var MAX_BYTES = 2 * 1024 * 1024;   // PHP側と同じ 2MB
  var TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

  var modal       = document.getElementById('iconModal');
  var openBtn     = document.getElementById('openIconModal');
  var closeBtn    = document.getElementById('closeIconModal');
  var form        = document.getElementById('iconForm');
  var fileInput   = document.getElementById('iconFile');
  var fileName    = document.getElementById('iconFileName');
  var errorEl     = document.getElementById('iconError');
  var submitBtn   = document.getElementById('iconSubmit');
  var previewImg  = document.getElementById('iconPreviewImg');
  var previewInit = document.getElementById('iconPreviewInitial');

  // 未ログイン時などモーダルが無いページでは何もしない
  if (!modal || !openBtn) return;

  // 開いた時点の表示を覚えておく（キャンセル時に元へ戻すため）
  var originalSrc = previewImg.getAttribute('src');
  var hadIcon = !previewImg.hidden;
  var objectUrl = null;

  function showError(msg) {
    errorEl.textContent = msg;
    errorEl.hidden = (msg === '');
  }

  function resetModal() {
    if (objectUrl) {
      URL.revokeObjectURL(objectUrl);
      objectUrl = null;
    }
    form.reset();
    fileName.textContent = 'ファイルが選択されていません';
    submitBtn.disabled = true;
    showError('');
    // プレビューを元のアイコン（または頭文字）に戻す
    previewImg.src = originalSrc || '';
    previewImg.hidden = !hadIcon;
    previewInit.hidden = hadIcon;
  }

  function openModal() {
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeModal() {
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    resetModal();
  }

  openBtn.addEventListener('click', openModal);
  closeBtn.addEventListener('click', closeModal);

  // 背景の暗い部分をクリックしても閉じる
  modal.addEventListener('click', function (e) {
    if (e.target === modal) closeModal();
  });

  // Escキーでも閉じる
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
  });

  // ファイルを選んだらプレビューに表示
  fileInput.addEventListener('change', function () {
    var file = fileInput.files[0];

    if (!file) {
      resetModal();
      return;
    }

    // 画像の種類・サイズをチェック（最終的なチェックはPHP側でも行う）
    if (TYPES.indexOf(file.type) === -1) {
      fileInput.value = '';
      submitBtn.disabled = true;
      fileName.textContent = 'ファイルが選択されていません';
      showError('JPEG・PNG・GIF・WebP の画像を選択してください。');
      return;
    }
    if (file.size > MAX_BYTES) {
      fileInput.value = '';
      submitBtn.disabled = true;
      fileName.textContent = 'ファイルが選択されていません';
      showError('画像のサイズは2MBまでです。');
      return;
    }

    showError('');
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = URL.createObjectURL(file);

    previewImg.src = objectUrl;
    previewImg.hidden = false;
    previewInit.hidden = true;
    fileName.textContent = file.name;
    submitBtn.disabled = false;   // 画像を選んだら「決定」を押せるようにする
  });
})();

// ============================================================
// マイページ：出品した商品をステータスで絞り込む
//   プルダウンを選んだら、すぐにフォームを送信する（mypage.php?tab=sold&status=…）
// ============================================================
(function () {
  var form   = document.getElementById('sold-filter');
  var select = document.getElementById('sold-status');
  if (!form || !select) return;

  select.addEventListener('change', function () {
    form.submit();
  });
})();
