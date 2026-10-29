// 管理画面（システム設定）
//   入力画面：入力のたびに全項目をチェックし、エラーがあれば「変更内容を確認」ボタンを押せなくする
//   確認画面：「実行」の二重送信を防ぐ
// チェック内容とエラー文言はサーバー側（src/lib/validation.php の collect_setting_errors）と同じにすること
document.addEventListener('DOMContentLoaded', () => {
    const settingForm = document.getElementById('setting-form');
    if (settingForm) {
        const inputs = Array.from(settingForm.querySelectorAll('.setting-input'));
        const confirmBtn = document.getElementById('confirm-btn');
        const resetBtn = document.getElementById('reset-btn');

        const inputByKey = {};
        inputs.forEach((input) => {
            inputByKey[input.dataset.key] = input;
        });

        // 未入力・文字種・上限下限のチェック（問題なければ空文字）
        const basicError = (input) => {
            const value = input.value.trim();
            if (value === '') {
                return '入力してください。';
            }
            if (!/^[0-9]+$/.test(value)) {
                return '半角数字で入力してください。';
            }
            const { min, max } = input.dataset;
            if (min !== undefined && max !== undefined) {
                const number = value.replace(/^0+(?=[0-9])/, '');
                if (number.length > max.length || Number(number) < Number(min) || Number(number) > Number(max)) {
                    return `${min}〜${max}の範囲で入力してください。`;
                }
            }
            return '';
        };

        // 大小関係のチェックまで含めたエラー（衝突したときは下限側に出す）
        const errorOf = (input) => {
            const error = basicError(input);
            if (error !== '') {
                return error;
            }
            const maxInput = inputByKey[input.dataset.pairMax];
            if (maxInput && basicError(maxInput) === ''
                && Number(input.value.trim()) > Number(maxInput.value.trim())) {
                return `「${maxInput.dataset.label}」以下の値を入力してください。`;
            }
            return '';
        };

        // 大小関係があるので、1項目の入力でも全項目をチェックし直す
        const validateAll = () => {
            let valid = true;
            inputs.forEach((input) => {
                const error = errorOf(input);
                const errorArea = document.getElementById(`error_${input.dataset.key}`);
                if (errorArea) {
                    errorArea.textContent = error;
                }
                input.classList.toggle('is-invalid', error !== '');
                if (error !== '') {
                    valid = false;
                }
            });
            confirmBtn.disabled = !valid;
            return valid;
        };

        inputs.forEach((input) => {
            input.addEventListener('input', validateAll);
        });

        settingForm.addEventListener('submit', (e) => {
            if (!validateAll()) {
                e.preventDefault();
                return;
            }
            confirmBtn.disabled = true;
        });

        // リセットボタン：全ての入力欄を DB の現在の値に戻す
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                inputs.forEach((input) => {
                    input.value = input.dataset.initial;
                });
                validateAll();
            });
        }

        validateAll();
        // ブラウザの「戻る」で表示し直されたとき、送信時に押せなくしたボタンを戻す
        window.addEventListener('pageshow', validateAll);
    }

    const confirmForm = document.getElementById('setting-confirm-form');
    if (confirmForm) {
        const executeBtn = document.getElementById('execute-btn');
        confirmForm.addEventListener('submit', () => {
            executeBtn.disabled = true;
        });
        window.addEventListener('pageshow', () => {
            executeBtn.disabled = false;
        });
    }
});
