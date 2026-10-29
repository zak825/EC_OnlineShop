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
// マイページ：購入・出品した商品をステータスで絞り込む
//   プルダウンを選んだら、すぐにフォームを送信する（mypage.php?tab=sold&status=…）
// ============================================================
(function () {
  ['bought', 'sold'].forEach(function(tab) {
    var form = document.getElementById(tab + '-filter');
    var select = document.getElementById(tab + '-status');
    if (!form || !select) return;
    select.addEventListener('change', function () {
      form.submit();
    });
  });
})();

// ============================================================
// マイページ：設定メニュー（吹き出しポップアップ）
// ============================================================
(function () {
  var settingsBtn    = document.getElementById('settingsToggleBtn');
  var balloon        = document.getElementById('settingsBalloon');
  var openProfileBtn = document.getElementById('openProfileModal');
  var openIconBtn    = document.getElementById('openIconModal');

  if (!settingsBtn || !balloon) return;

  function toggleBalloon(e) {
    if (e) e.stopPropagation();
    var isHidden = balloon.hasAttribute('hidden');
    if (isHidden) {
      balloon.removeAttribute('hidden');
      settingsBtn.setAttribute('aria-expanded', 'true');
    } else {
      closeBalloon();
    }
  }

  function closeBalloon() {
    balloon.setAttribute('hidden', '');
    settingsBtn.setAttribute('aria-expanded', 'false');
  }

  settingsBtn.addEventListener('click', toggleBalloon);

  // 吹き出し外のクリックで閉じる
  document.addEventListener('click', function (e) {
    if (!balloon.contains(e.target) && e.target !== settingsBtn) {
      closeBalloon();
    }
  });

  // Escキーで閉じる
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !balloon.hasAttribute('hidden')) {
      closeBalloon();
    }
  });

  // メニュー項目をクリックしたら吹き出しを閉じる
  if (openProfileBtn) {
    openProfileBtn.addEventListener('click', closeBalloon);
  }
  if (openIconBtn) {
    openIconBtn.addEventListener('click', closeBalloon);
  }
})();

// ============================================================
// マイページ：会員情報編集モーダル
// ============================================================
(function () {
  var modal         = document.getElementById('profileModal');
  var openBtn       = document.getElementById('openProfileModal');
  var closeBtn      = document.getElementById('closeProfileModal');
  var toConfirmBtn  = document.getElementById('profileToConfirmBtn');
  var backToEditBtn = document.getElementById('profileBackToEditBtn');
  var submitBtn     = document.getElementById('profileSubmitBtn');
  var finishBtn     = document.getElementById('profileFinishBtn');

  var editSection     = document.getElementById('profileEditSection');
  var confirmSection  = document.getElementById('profileConfirmSection');
  var completeSection = document.getElementById('profileCompleteSection');

  var errorMsg       = document.getElementById('profileErrorMsg');
  var csrfTokenInput = document.getElementById('profileCsrfToken');

  var nameInput = document.getElementById('edit_user_name');
  var zipInput  = document.getElementById('edit_zip_code');
  var addrInput = document.getElementById('edit_address');
  var telInput  = document.getElementById('edit_tel');

  var confirmName = document.getElementById('confirm_user_name');
  var confirmZip  = document.getElementById('confirm_zip_code');
  var confirmAddr = document.getElementById('confirm_address');
  var confirmTel  = document.getElementById('confirm_tel');

  if (!modal || !openBtn) return;

  // 入力チェックは profile_validation.js（サーバー側は src/lib/validation.php）
  var PV = window.ProfileValidation;

  // 更新に成功した（または結果が分からない）あと、画面を読み込み直すかどうか
  //   → true のまま閉じると、プロフィール表示やCSRFトークンが古いままになるため再読み込みする
  var needsReload = false;

  // 初期値を記憶
  var initialValues = {
    name: nameInput ? nameInput.value : '',
    zip:  zipInput ? zipInput.value : '',
    addr: addrInput ? addrInput.value : '',
    tel:  telInput ? telInput.value : ''
  };

  function showError(msg) {
    if (errorMsg) {
      errorMsg.textContent = msg;
      errorMsg.style.display = 'block';
    }
  }

  function hideError() {
    if (errorMsg) {
      errorMsg.textContent = '';
      errorMsg.style.display = 'none';
    }
  }

  function resetToEdit() {
    hideError();
    if (editSection) editSection.style.display = 'block';
    if (confirmSection) confirmSection.style.display = 'none';
    if (completeSection) completeSection.style.display = 'none';
    if (submitBtn) submitBtn.disabled = false;
  }

  function resetFormValues() {
    if (nameInput) nameInput.value = initialValues.name;
    if (zipInput)  zipInput.value  = initialValues.zip;
    if (addrInput) addrInput.value = initialValues.addr;
    if (telInput)  telInput.value  = initialValues.tel;
    resetToEdit();
  }

  function openModal() {
    resetToEdit();
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
  }

  function closeModal() {
    // 更新済みのときは、Esc・背景クリックで閉じた場合でも最新の内容を表示し直す
    if (needsReload) {
      window.location.reload();
      return;
    }
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    resetFormValues();
  }

  // 入力欄の値を、送信する形（前後の空白除去・氏名の間は半角スペース1つ・郵便番号はハイフンなし）で取り出す
  function readValues() {
    return {
      name: nameInput ? PV.normalizeName(PV.trim(nameInput.value)) : '',
      zip:  zipInput ? PV.normalizeZip(PV.trim(zipInput.value)) : '',
      addr: addrInput ? PV.trim(addrInput.value) : '',
      tel:  telInput ? PV.trim(telInput.value) : ''
    };
  }

  // ページ内の CSRF トークン（会員情報・アイコン変更・発送フォーム）を新しいものに差し替える
  function updateCsrfTokens(token) {
    if (!token) return;
    var inputs = document.querySelectorAll('input[name="csrf_token"]');
    for (var i = 0; i < inputs.length; i++) {
      inputs[i].value = token;
    }
  }

  openBtn.addEventListener('click', openModal);
  if (closeBtn) closeBtn.addEventListener('click', closeModal);

  // 背景クリックで閉じる
  modal.addEventListener('click', function (e) {
    if (e.target === modal) closeModal();
  });

  // Escキーで閉じる
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.classList.contains('is-open')) {
      closeModal();
    }
  });

  // 「変更」ボタン押下：register.phpと同一のバリデーションチェックを行い確認画面へ遷移
  if (toConfirmBtn) {
    toConfirmBtn.addEventListener('click', function () {
      hideError();

      var v = readValues();

      // 1. 未入力・桁数・形式チェック（register.php と共通：profile_validation.js）
      var errorText = PV.validate(v.name, v.zip, v.addr, v.tel);
      if (errorText !== null) {
        showError(errorText);
        return;
      }

      // 確認項目にセット（郵便番号はハイフンなしの形で表示する）
      if (confirmName) confirmName.textContent = v.name;
      if (confirmZip)  confirmZip.textContent  = v.zip;
      if (confirmAddr) confirmAddr.textContent = v.addr;
      if (confirmTel)  confirmTel.textContent  = v.tel;

      // 確認セクションへ切り替え
      editSection.style.display = 'none';
      confirmSection.style.display = 'block';
    });
  }

  // 確認画面の「キャンセル」または「戻る」ボタン
  if (backToEditBtn) {
    backToEditBtn.addEventListener('click', function () {
      hideError();
      confirmSection.style.display = 'none';
      editSection.style.display = 'block';
    });
  }

  // 確認画面の「はい（変更する）」ボタン押下：AjaxでUPDATE文を実行
  if (submitBtn) {
    submitBtn.addEventListener('click', async function () {
      hideError();
      submitBtn.disabled = true;

      var token = csrfTokenInput ? csrfTokenInput.value : '';
      var v     = readValues();

      var formData = new FormData();
      formData.append('action', 'update_profile');
      formData.append('csrf_token', token);
      formData.append('user_name', v.name);
      formData.append('zip_code', v.zip);
      formData.append('address', v.addr);
      formData.append('tel', v.tel);

      var response;
      try {
        response = await fetch('mypage.php', {
          method: 'POST',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData
        });
      } catch (err) {
        // サーバーに届いていない（通信エラー）。トークンは使われていないのでそのまま再試行できる
        showError('サーバーとの通信に失敗しました。通信状況を確認して、もう一度お試しください。');
        submitBtn.disabled = false;
        console.error(err);
        return;
      }

      var result = null;
      try {
        if (response.ok) {
          result = await response.json();
        }
      } catch (err) {
        console.error(err);
      }

      if (result === null) {
        // サーバーまで届いたが結果を読み取れなかった。更新済みの可能性があるので、閉じるときは再読み込みする
        needsReload = true;
        showError('更新結果を確認できませんでした。画面を再読み込みして、内容をご確認ください。');
        submitBtn.disabled = false;
        return;
      }

      // CSRF トークンは1回で使い切りなので、成功・失敗に関わらず新しいものに差し替える
      updateCsrfTokens(result.csrf_token);

      if (result.success) {
        // 初期値を更新
        initialValues.name = v.name;
        initialValues.zip  = v.zip;
        initialValues.addr = v.addr;
        initialValues.tel  = v.tel;

        // モーダルが送信中に閉じられていたときは、すぐ最新の内容を表示し直す
        if (!modal.classList.contains('is-open')) {
          window.location.reload();
          return;
        }

        // 完了セクション表示（閉じるときは再読み込みして、プロフィール表示を最新にする）
        needsReload = true;
        confirmSection.style.display = 'none';
        completeSection.style.display = 'block';
      } else {
        showError(result.message || '会員情報の更新に失敗しました。');
        submitBtn.disabled = false;
      }
    });
  }

  // 完了画面の「閉じる」ボタン押下：画面を再読み込みして最新情報を反映
  if (finishBtn) {
    finishBtn.addEventListener('click', function () {
      window.location.reload();
    });
  }
})();
