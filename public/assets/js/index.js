// ============================================================
// メイン画面
//   ログアウトボタンを押したときに確認ポップアップを出す
// ============================================================

const logoutForm = document.getElementById('logout-form');

if (logoutForm) {
  logoutForm.addEventListener('submit', (event) => {
    // キャンセルを押したら送信しない（メイン画面のまま）
    if (!window.confirm('ログアウトしますか？')) {
      event.preventDefault();
    }
  });
}
