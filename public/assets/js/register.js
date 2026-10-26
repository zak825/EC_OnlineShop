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
        // 氏名・郵便番号・住所・電話番号のチェックは profile_validation.js（サーバー側は src/lib/validation.php）
        const PV = window.ProfileValidation;
        const userName = PV.trim(userNameInput.value);
        const zipCode = PV.normalizeZip(PV.trim(zipCodeInput.value));   // 123-4567 → 1234567
        const address = PV.trim(addressInput.value);
        const tel = PV.trim(telInput.value);
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

        const profileError = PV.validate(userName, zipCode, address, tel);
        if (profileError !== null) {
            e.preventDefault();
            showError(profileError);
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

        // 郵便番号はハイフンなしの形に直してから送信する
        zipCodeInput.value = zipCode;

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
