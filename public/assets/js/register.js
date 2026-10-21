document.addEventListener('DOMContentLoaded', () => {
    const registerForm = document.getElementById('register-form');
    const userNameInput = document.getElementById('user_name');
    const zipCodeInput = document.getElementById('zip_code');
    const addressInput = document.getElementById('address');
    const telInput = document.getElementById('tel');
    const passwordInput = document.getElementById('password');
    const secretQuestionSelect = document.getElementById('secret_question_id');
    const secretAnswerInput = document.getElementById('secret_answer');
    const clearBtn = document.getElementById('clear-btn');
    const errorMsg = document.getElementById('error-message');
    const minLenElem = document.getElementById('pass-min-length');
    const maxLenElem = document.getElementById('pass-max-length');

    if (!registerForm) return;

    const showError = (msg) => {
        errorMsg.textContent = msg;
        errorMsg.style.display = 'block';
    };

    const hideError = () => {
        errorMsg.textContent = '';
        errorMsg.style.display = 'none';
    };

    registerForm.addEventListener('submit', (e) => {
        const userName = userNameInput.value.trim();
        const zipCode = zipCodeInput.value.trim();
        const address = addressInput.value.trim();
        const tel = telInput.value.trim();
        const password = passwordInput.value;
        const secretQuestionId = secretQuestionSelect ? secretQuestionSelect.value : '';
        const secretAnswer = secretAnswerInput ? secretAnswerInput.value.trim() : '';

        // 1. 未入力・桁数チェックを行う
        if (userName === '' || zipCode === '' || address === '' || tel === '' || password === ''
            || secretQuestionId === '' || secretAnswer === '') {
            e.preventDefault();
            showError('すべての項目を入力してください。');
            return;
        }

        if (userName.length > 50) {
            e.preventDefault();
            showError('氏名は50文字以内で入力してください。');
            return;
        }

        if (zipCode.length > 8 || !/^\d{3}-?\d{4}$/.test(zipCode)) {
            e.preventDefault();
            showError('郵便番号は8桁以内の半角数字（例：1234567 または 123-4567）で入力してください。');
            return;
        }

        if (address.length > 256) {
            e.preventDefault();
            showError('住所は256文字以内で入力してください。');
            return;
        }

        if (tel.length > 15 || !/^[0-9\-]{10,15}$/.test(tel)) {
            e.preventDefault();
            showError('電話番号は15桁以内の半角数字（ハイフン可）で入力してください。');
            return;
        }

        // 秘密の質問の回答は100文字以内
        if (secretAnswer.length > 100) {
            e.preventDefault();
            showError('秘密の質問の回答は100文字以内で入力してください。');
            return;
        }

        // 2. パスワードポリシーのチェックを行う
        const minLen = parseInt(minLenElem ? minLenElem.value : '8', 10) || 8;
        const maxLen = parseInt(maxLenElem ? maxLenElem.value : '50', 10) || 50;
        const passwordLen = password.length;

        if (passwordLen < minLen || passwordLen > maxLen) {
            e.preventDefault();
            showError(`パスワードは${minLen}文字以上${maxLen}文字以内で入力してください。`);
            return;
        }

        hideError();
    });

    // クリアボタン：入力内容を全て消去する
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            userNameInput.value = '';
            zipCodeInput.value = '';
            addressInput.value = '';
            telInput.value = '';
            passwordInput.value = '';
            if (secretQuestionSelect) secretQuestionSelect.value = '';
            if (secretAnswerInput) secretAnswerInput.value = '';
            hideError();
            userNameInput.focus();
        });
    }

    // 戻るボタン：セッション一時情報を破棄してログイン画面へ遷移する
    const backBtn = document.getElementById('back-btn');
    if (backBtn) {
        backBtn.addEventListener('click', () => {
            window.location.href = 'register.php?action=back';
        });
    }
});
