document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('passreset-container');
    if (!container) return;

    const errorMsg = document.getElementById('error-message');
    const sections = container.querySelectorAll('[data-section]');

    // フェーズ1（照合）の要素
    const verifyForm = document.getElementById('verify-form');
    const userIdInput = document.getElementById('user_id');
    const questionSelect = document.getElementById('secret_question_id');
    const answerInput = document.getElementById('secret_answer');

    // フェーズ2（新パスワード）の要素
    const resetForm = document.getElementById('reset-form');
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('new_password_confirm');

    // パスワードポリシー（サーバー側で SystemConfiguration から取得した値）
    const minLen = parseInt(container.dataset.passMin, 10) || 8;
    const maxLen = parseInt(container.dataset.passMax, 10) || 50;

    const showError = (msg) => {
        errorMsg.textContent = msg;
        errorMsg.classList.remove('is-hidden');
    };

    const hideError = () => {
        errorMsg.textContent = '';
        errorMsg.classList.add('is-hidden');
    };

    // data-step に応じて該当フェーズのブロックのみ表示し、他は非表示にする
    const showStep = (step) => {
        sections.forEach((section) => {
            section.classList.toggle('is-hidden', section.dataset.section !== step);
        });

        // 表示したフェーズの先頭入力欄へフォーカスする
        if (step === 'verify' && userIdInput) {
            (userIdInput.value === '' ? userIdInput : answerInput).focus();
        } else if (step === 'reset' && newPasswordInput) {
            newPasswordInput.focus();
        }
    };

    const validSteps = ['verify', 'reset', 'complete'];
    const currentStep = validSteps.includes(container.dataset.step) ? container.dataset.step : 'verify';
    showStep(currentStep);

    // 【ステップ5】照合フォームの未入力・形式チェック
    if (verifyForm) {
        verifyForm.addEventListener('submit', (e) => {
            const userId = userIdInput.value.trim();
            const questionId = questionSelect.value;
            const answer = answerInput.value.trim();

            if (userId === '' || questionId === '' || answer === '' ||
                !/^\d{1,8}$/.test(userId) || parseInt(userId, 10) <= 0 ||
                answer.length > 100) {
                e.preventDefault();
                showError('すべての項目を正しく入力してください。');
                return;
            }

            hideError();
        });
    }

    // 【ステップ9】新パスワードの未入力・一致・ポリシーチェック
    if (resetForm) {
        resetForm.addEventListener('submit', (e) => {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;

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

            if (newPassword.length < minLen || newPassword.length > maxLen ||
                !/^[a-zA-Z0-9]+$/.test(newPassword)) {
                e.preventDefault();
                showError(`パスワードは${minLen}文字以上${maxLen}文字以内の半角英数字で入力してください。`);
                return;
            }

            hideError();
        });
    }
});
