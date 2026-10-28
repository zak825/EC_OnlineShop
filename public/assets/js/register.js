document.addEventListener('DOMContentLoaded', () => {
    const registerForm = document.getElementById('register-form');
    const userNameInput = document.getElementById('user_name');
    const zipCodeInput = document.getElementById('zip_code');
    const addressInput = document.getElementById('address');
    const telInput = document.getElementById('tel');
    const passwordInput = document.getElementById('password');
    const passwordConfirmInput = document.getElementById('password_confirm');
    const secretQuestionSelect = document.getElementById('secret_question_id');
    const secretAnswerInput = document.getElementById('secret_answer');
    const clearBtn = document.getElementById('clear-btn');
    const errorMsg = document.getElementById('error-message');
    const minLenElem = document.getElementById('pass-min-length');
    const maxLenElem = document.getElementById('pass-max-length');

    if (!registerForm) return;

    // エラーメッセージを1件ずつリストで表示する
    const showErrors = (messages) => {
        const list = document.createElement('ul');
        list.className = 'error-list';
        messages.forEach((msg) => {
            const item = document.createElement('li');
            item.textContent = msg;
            list.appendChild(item);
        });
        errorMsg.replaceChildren(list);
        errorMsg.style.display = 'block';
        // エラーはフォーム上部にあるため、ページ先頭まで戻す
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const hideError = () => {
        errorMsg.replaceChildren();
        errorMsg.style.display = 'none';
    };

    registerForm.addEventListener('submit', (e) => {
        // 氏名・郵便番号・住所・電話番号のチェックは profile_validation.js（サーバー側は src/lib/validation.php）
        const PV = window.ProfileValidation;
        const userName = PV.normalizeName(PV.trim(userNameInput.value));   // 山田　太郎 → 山田 太郎
        const zipCode = PV.normalizeZip(PV.trim(zipCodeInput.value));   // 123-4567 → 1234567
        const address = PV.trim(addressInput.value);
        const tel = PV.trim(telInput.value);
        const password = passwordInput.value;
        const passwordConfirm = passwordConfirmInput ? passwordConfirmInput.value : '';
        const secretQuestionId = secretQuestionSelect ? secretQuestionSelect.value : '';
        const secretAnswer = secretAnswerInput ? secretAnswerInput.value.trim() : '';

        // 1. 未入力・桁数チェックを行う（エラーはまとめて表示するため、すべての項目をチェックする）
        const errors = PV.collectErrors(userName, zipCode, address, tel);

        if (secretQuestionId === '') {
            errors.push('秘密の質問を選択してください。');
        }

        // 秘密の質問の回答は100文字以内
        if (secretAnswer === '') {
            errors.push('秘密の質問の回答を入力してください。');
        } else if (secretAnswer.length > 100) {
            errors.push('秘密の質問の回答は100文字以内で入力してください。');
        } else if (!PV.isAllowedZenkakuText(secretAnswer)) {
            errors.push(PV.zenkakuTextError('秘密の質問の回答'));
        }

        // 2. パスワードポリシーのチェックを行う
        const minLen = parseInt(minLenElem ? minLenElem.value : '8', 10) || 8;
        const maxLen = parseInt(maxLenElem ? maxLenElem.value : '50', 10) || 50;
        const passwordLen = password.length;

        if (password === '') {
            errors.push('パスワードを入力してください。');
        } else {
            if (passwordLen < minLen || passwordLen > maxLen) {
                errors.push(`パスワードは${minLen}文字以上${maxLen}文字以内で入力してください。`);
            }
            if (PV.passwordHasForbiddenChar(password)) {
                errors.push(PV.passwordForbiddenCharError());
            }
        }

        // パスワード（確認用）が入力したパスワードと一致しているかチェックする
        if (passwordConfirm === '') {
            errors.push('パスワード（確認用）を入力してください。');
        } else if (password !== '' && password !== passwordConfirm) {
            errors.push('パスワードとパスワード（確認用）が一致しません。');
        }

        if (errors.length > 0) {
            e.preventDefault();
            showErrors(errors);
            return;
        }

        // 氏名は語の間を半角スペース1つに、郵便番号はハイフンなしの形に直してから送信する
        userNameInput.value = userName;
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
            if (passwordConfirmInput) passwordConfirmInput.value = '';
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
