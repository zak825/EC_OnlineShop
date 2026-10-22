// ============================================================
// 購入確認画面
// ============================================================

// 二重送信防止：確定ボタンを押したら一度だけ送信できるようにする
const confirmForm = document.getElementById('confirm-form');
if (confirmForm) {
  confirmForm.addEventListener('submit', (e) => {
    const btn = confirmForm.querySelector('button[value="confirm"]');
    // 確定ボタンのときだけ無効化（キャンセルは普通に動かす）
    if (e.submitter && e.submitter.value === 'confirm' && btn) {
      btn.disabled = true;
      btn.textContent = '処理中…';
    }
  });
}

// 購入完了画面なら、3秒後にメイン画面へ自動で移動する
const mainLink = document.getElementById('main_link');
if (mainLink) {
  setTimeout(() => { location.href = mainLink.href; }, 3000);
}
