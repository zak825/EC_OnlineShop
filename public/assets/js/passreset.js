document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('passreset-container');
    const errorMsg = document.getElementById('error-message');
    const verifyForm = document.getElementById('verify-form');
    const resetForm = document.getElementById('reset-form');

    if (!container) return;

    const showError = (msg) => {
        errorMsg.classList.remove('error-msg--warning');
        errorMsg.textContent = msg;
        errorMsg.style.display = 'block';
    };

    const hideError = () => {
        errorMsg.textContent = '';
        errorMsg.style.display = 'none';
    };

    // 二重送信防止：送信ボタンを無効化する
    const disableSubmit = (form) => {
        const btn = form.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;
    };

    // ------------------------------------------------------------
    // フェーズに応じたブロックの表示切り替え
    //   data-step（verify / reset / complete）と一致するブロックだけ表示する
    // ------------------------------------------------------------
    const validSteps = ['verify', 'reset', 'complete'];
    const step = validSteps.includes(container.dataset.step) ? container.dataset.step : 'verify';

    container.querySelectorAll('[data-step-block]').forEach((block) => {
        block.classList.toggle('is-hidden', block.dataset.stepBlock !== step);
    });

    // 表示したフェーズの最初の入力欄にフォーカスを当てる
    if (step === 'verify') {
        const userIdInput = document.getElementById('user_id');
        if (userIdInput) userIdInput.focus();
    } else if (step === 'reset') {
        const newPasswordInput = document.getElementById('new_password');
        if (newPasswordInput) newPasswordInput.focus();
    }

    // ------------------------------------------------------------
    // フェーズ1：照合フォームの入力チェック
    // ------------------------------------------------------------
    if (verifyForm) {
        const userIdInput = document.getElementById('user_id');
        const questionSelect = document.getElementById('secret_question_id');
        const answerInput = document.getElementById('secret_answer');

        verifyForm.addEventListener('submit', (e) => {
            const userId = userIdInput.value.trim();
            const questionId = questionSelect.value;
            const answer = answerInput.value.trim();

            // 未入力・形式チェック（userID は8桁以内の半角数字、質問は選択必須、回答は100文字以内）
            if (
                userId === '' || questionId === '' || answer === '' ||
                !/^\d{1,8}$/.test(userId) || parseInt(userId, 10) <= 0 ||
                answer.length > 100
            ) {
                e.preventDefault();
                showError('すべての項目を正しく入力してください。');
                return;
            }

            hideError();
            disableSubmit(verifyForm);
        });
    }

    // ------------------------------------------------------------
    // フェーズ2：新パスワードの一致・ポリシーチェック
    // ------------------------------------------------------------
    if (resetForm) {
        const newPasswordInput = document.getElementById('new_password');
        const confirmInput = document.getElementById('new_password_confirm');
        const minLen = parseInt(container.dataset.passMinLength, 10) || 8;
        const maxLen = parseInt(container.dataset.passMaxLength, 10) || 50;

        resetForm.addEventListener('submit', (e) => {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmInput.value;

            if (newPassword === '' || confirmPassword === '') {
                e.preventDefault();
                showError('新しいパスワードと確認用パスワードを入力してください。');
                return;
            }

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                showError('新しいパスワードと確認用パスワードが一致しません。');
                return;
            }

            // 文字種の制限は設けず、登録画面（register.js）と同じく文字数のみチェックする
            if (newPassword.length < minLen || newPassword.length > maxLen) {
                e.preventDefault();
                showError(`パスワードは${minLen}文字以上${maxLen}文字以内で入力してください。`);
                return;
            }

            hideError();
            disableSubmit(resetForm);
        });
    }
});
