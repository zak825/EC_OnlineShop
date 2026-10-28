document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form');
    const userIdInput = document.getElementById('user_id');
    const passwordInput = document.getElementById('password');
    const clearBtn = document.getElementById('clear-btn');
    const errorMsg = document.getElementById('error-message');

    if (!loginForm) return;

    const showError = (msg) => {
        errorMsg.classList.remove('error-msg--warning');
        errorMsg.textContent = msg;
        errorMsg.style.display = 'block';
    };

    loginForm.addEventListener('submit', (e) => {
        const userId = userIdInput.value.trim();
        const password = passwordInput.value;

        if (userId === '' || password === '') {
            e.preventDefault();
            showError('userIDとパスワードを入力してください。');
            return;
        }

        if (!/^\d{1,8}$/.test(userId)) {
            e.preventDefault();
            showError('userIDは8桁以内の半角数字で入力してください。');
            return;
        }
    });

    clearBtn.addEventListener('click', () => {
        userIdInput.value = '';
        passwordInput.value = '';
        errorMsg.textContent = '';
        errorMsg.style.display = 'none';
        userIdInput.focus();
    });
});