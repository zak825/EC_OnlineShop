document.addEventListener('DOMContentLoaded', () => {
    const submitBtn = document.getElementById('submit-btn');
    const cancelBtn = document.getElementById('cancel-btn');
    const errorMsg = document.getElementById('error-message');
    const csrfTokenInput = document.getElementById('csrf-token');
    const confirmSection = document.getElementById('confirm-section');
    const completeSection = document.getElementById('complete-section');

    const showError = (msg) => {
        if (errorMsg) {
            errorMsg.textContent = msg;
            errorMsg.style.display = 'block';
        }
    };

    const hideError = () => {
        if (errorMsg) {
            errorMsg.textContent = '';
            errorMsg.style.display = 'none';
        }
    };

    // 「はい」ボタン押下：登録処理実行後、JSで完了画面を表示
    if (submitBtn) {
        submitBtn.addEventListener('click', async () => {
            hideError();
            submitBtn.disabled = true;

            const token = csrfTokenInput ? csrfTokenInput.value : '';

            try {
                const formData = new FormData();
                formData.append('csrf_token', token);
                formData.append('ajax', '1');

                const response = await fetch('register_check.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                if (!response.ok) {
                    throw new Error('サーバーとの通信に失敗しました。');
                }

                const result = await response.json();

                if (result.success) {
                    // 実際に登録したユーザーIDを表示する（重複していたときは確認画面の ID から変わる）
                    const completeUserId = document.getElementById('complete-user-id');
                    if (completeUserId && result.user_id) {
                        completeUserId.textContent = result.user_id;
                    }

                    // 登録完了後、JSで完了画面表示
                    if (confirmSection) {
                        confirmSection.style.display = 'none';
                    }
                    if (completeSection) {
                        completeSection.style.display = 'block';
                    }
                } else {
                    showError(result.message || 'ユーザー情報の登録に失敗しました。');
                    submitBtn.disabled = false;
                }
            } catch (err) {
                showError('登録処理中にエラーが発生しました。再度お試しください。');
                submitBtn.disabled = false;
                console.error(err);
            }
        });
    }

    // 「キャンセル」ボタン押下：アカウント登録画面へ遷移
    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            window.location.href = 'register.php';
        });
    }
});
