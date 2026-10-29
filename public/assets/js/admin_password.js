// 管理パスワード変更
//   照合は現在のパスワードのみ。新しいパスワードのチェックは passreset.js と同じにする。
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('admin-password-card');
    const errorMsg = document.getElementById('error-message');
    const verifyForm = document.getElementById('verify-form');
    const resetForm = document.getElementById('reset-form');

    if (!container) return;

    const showError = (msg) => {
        errorMsg.textContent = msg;
        errorMsg.style.display = 'block';
    };

    const hideError = () => {
        errorMsg.textContent = '';
        errorMsg.style.display = 'none';
    };

    const disableSubmit = (form) => {
        const btn = form.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;
    };

    const validSteps = ['verify', 'reset', 'complete'];
    const step = validSteps.includes(container.dataset.step) ? container.dataset.step : 'verify';

    container.querySelectorAll('[data-step-block]').forEach((block) => {
        block.classList.toggle('is-hidden', block.dataset.stepBlock !== step);
    });

    if (step === 'verify') {
        const currentPassword = document.getElementById('current_password');
        if (currentPassword) currentPassword.focus();
    } else if (step === 'reset') {
        const newPasswordInput = document.getElementById('new_password');
        if (newPasswordInput) newPasswordInput.focus();
    }

    if (verifyForm) {
        const currentPassword = document.getElementById('current_password');
        verifyForm.addEventListener('submit', (e) => {
            if (currentPassword.value === '') {
                e.preventDefault();
                showError('現在のパスワードを入力してください。');
                return;
            }
            hideError();
            disableSubmit(verifyForm);
        });
        window.addEventListener('pageshow', () => {
            const btn = verifyForm.querySelector('button[type="submit"]');
            if (btn) btn.disabled = false;
        });
    }

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
            if (newPassword.length < minLen || newPassword.length > maxLen) {
                e.preventDefault();
                showError(`パスワードは${minLen}文字以上${maxLen}文字以内で入力してください。`);
                return;
            }
            const PV = window.ProfileValidation;
            if (PV && PV.passwordHasForbiddenChar(newPassword)) {
                e.preventDefault();
                showError(PV.passwordForbiddenCharError());
                return;
            }
            hideError();
            disableSubmit(resetForm);
        });
        window.addEventListener('pageshow', () => {
            const btn = resetForm.querySelector('button[type="submit"]');
            if (btn) btn.disabled = false;
        });
    }
});
