<?php
require_once __DIR__ . '/../src/bootstrap.php';

// 【画面を開く】すでにログイン済み（かつセッション有効期限内）であるか確認
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

// 【戻るボタンを押す】セッションの一時入力情報を破棄し、ログイン画面へ遷移する
if (isset($_GET['action']) && $_GET['action'] === 'back') {
    unset($_SESSION['register_user']);
    header('Location: login.php');
    exit;
}

$pdo = db();
// 画面に表示するエラーメッセージ（複数ある場合はすべて表示する）
$errorMessages = [];

// 入力フォーム保持用変数
$userNameInput = '';
$zipCodeInput  = '';
$addressInput  = '';
$telInput      = '';
$secretQuestionIdInput = '';
$secretAnswerInput     = '';

// 秘密の質問リスト（質問ID => 質問文）
$secretQuestions = get_secret_questions();

// 【確認画面でキャンセルして戻ってきたとき】セッションに一時保存した入力内容をフォームに戻す
// パスワードと秘密の質問の回答はハッシュ化して保存しているため戻せない（再入力してもらう）
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !empty($_SESSION['register_user'])) {
    $registerUser = $_SESSION['register_user'];
    $userNameInput = (string)($registerUser['UserName'] ?? $registerUser['user_name'] ?? '');
    $zipCodeInput  = (string)($registerUser['ZipCode'] ?? $registerUser['zip_code'] ?? '');
    $addressInput  = (string)($registerUser['Address'] ?? $registerUser['address'] ?? '');
    $telInput      = (string)($registerUser['TEL'] ?? $registerUser['tel'] ?? '');
    $secretQuestionIdInput = (string)($registerUser['SecretQuestionId'] ?? $registerUser['secret_question_id'] ?? '');
}

// パスワード文字数設定のデフォルト値
$passwordMinLength = 8;
$passwordMaxLength = 50;

// 【パスワードポリシーの取得】
// SystemConfigurationテーブルからパスワードの文字数制限（最小文字数・最大文字数）の設定値を取得する
try {
    $sql = "SELECT [Key], [Value]
                FROM dbo.SystemConfiguration
                WHERE Category = 'Password'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    if (isset($configs['PasswordMinLength']) && is_numeric($configs['PasswordMinLength'])) {
        $passwordMinLength = (int)$configs['PasswordMinLength'];
    } elseif (isset($configs['MinLength']) && is_numeric($configs['MinLength'])) {
        $passwordMinLength = (int)$configs['MinLength'];
    }

    if (isset($configs['PasswordMaxLength']) && is_numeric($configs['PasswordMaxLength'])) {
        $passwordMaxLength = (int)$configs['PasswordMaxLength'];
    } elseif (isset($configs['MaxLength']) && is_numeric($configs['MaxLength'])) {
        $passwordMaxLength = (int)$configs['MaxLength'];
    }
} catch (PDOException $e) {
    $errorMessages[] = 'パスワードポリシーの取得に失敗しました。';
    error_log('Password policy fetch error: ' . $e->getMessage());
}

// 【登録ボタンを押す】POST送信時の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf_token()) {
        $errorMessages[] = '不正なリクエストです（CSRFトークンエラー）。画面を開き直して再度お試しください。';
    } else {
        // 入力された文字列の取得および前後の空白除去（サニタイズ処理）
        // 会員情報4項目は共通関数（src/lib/validation.php）で整形する（郵便番号はハイフンなし7桁にそろえる）
        $profileInput  = sanitize_profile_input($_POST);
        $userNameInput = $profileInput['user_name'];
        $zipCodeInput  = $profileInput['zip_code'];
        $addressInput  = $profileInput['address'];
        $telInput      = $profileInput['tel'];
        $password        = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $secretQuestionIdInput = trim($_POST['secret_question_id'] ?? '');
        $secretAnswerInput     = trim($_POST['secret_answer'] ?? '');

        // 1. 未入力・桁数チェックを行う
        // 各項目が入力されているか、また各項目（氏名、郵便番号、住所、電話番号）がUserDataテーブル定義の桁数・形式内であるかチェックする
        // エラーはまとめて表示するため、途中で止めずにすべての項目をチェックする
        $inputErrors = collect_profile_errors($userNameInput, $zipCodeInput, $addressInput, $telInput);

        // 秘密の質問は定義済みの質問ID（1〜5）であること
        if (
            $secretQuestionIdInput === '' || !ctype_digit($secretQuestionIdInput)
            || !isset($secretQuestions[(int)$secretQuestionIdInput])
        ) {
            $inputErrors[] = '秘密の質問を選択してください。';
        }

        if ($secretAnswerInput === '') {
            $inputErrors[] = '秘密の質問の回答を入力してください。';
        } elseif (mb_strlen($secretAnswerInput, 'UTF-8') > 100) {
            $inputErrors[] = '秘密の質問の回答は100文字以内で入力してください。';
        }

        // 2. パスワードポリシーのチェックを行う
        // 入力されたパスワードの長さが、SystemConfigurationテーブルから取得した文字数設定の範囲内であるかチェックする
        if ($password === '') {
            $inputErrors[] = 'パスワードを入力してください。';
        } else {
            $passwordLen = mb_strlen($password, 'UTF-8');
            if ($passwordLen < $passwordMinLength || $passwordLen > $passwordMaxLength) {
                $inputErrors[] = "パスワードは{$passwordMinLength}文字以上{$passwordMaxLength}文字以内で入力してください。";
            }
        }

        // パスワード（確認用）が入力したパスワードと一致しているかチェックする
        if ($passwordConfirm === '') {
            $inputErrors[] = 'パスワード（確認用）を入力してください。';
        } elseif ($password !== '' && $password !== $passwordConfirm) {
            $inputErrors[] = 'パスワードとパスワード（確認用）が一致しません。';
        }

        if ($inputErrors !== []) {
            $errorMessages = array_merge($errorMessages, $inputErrors);
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            // 秘密の質問の回答もパスワードと同様にハッシュ化してから保存する
            $secretAnswerHash = password_hash($secretAnswerInput, PASSWORD_DEFAULT);
            if ($passwordHash === false) {
                $errorMessages[] = 'パスワードの処理に失敗しました。';
            } elseif ($secretAnswerHash === false) {
                $errorMessages[] = '秘密の質問の回答の処理に失敗しました。';
            } else {
                // 3. SQLインジェクション・XSS対策を行う
                // プレースホルダおよびサニタイズ処理を行い、不正な入力を回避する

                // 4. ユーザーIDを発番し、セッションに一時保存する
                // UserDataテーブルから現在の最大UserIdを取得して+1した新規UserIdを算出し、入力情報とあわせてセッションに書き込む
                try {
                    $sql = "SELECT ISNULL(MAX(UserId), 0) AS max_id FROM dbo.UserData";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute();
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $newUserId = ((int)($row['max_id'] ?? 0)) + 1;

                    // 入力情報と新規UserIdをセッションに一時保存
                    $_SESSION['register_user'] = [
                        'UserId'        => $newUserId,
                        'user_id'       => $newUserId,
                        'UserName'      => $userNameInput,
                        'user_name'     => $userNameInput,
                        'ZipCode'       => $zipCodeInput,
                        'zip_code'      => $zipCodeInput,
                        'Address'       => $addressInput,
                        'address'       => $addressInput,
                        'TEL'           => $telInput,
                        'tel'           => $telInput,
                        'LoginPassword' => $passwordHash,
                        'password'      => $passwordHash,
                        'SecretQuestionId'   => (int)$secretQuestionIdInput,
                        'secret_question_id' => (int)$secretQuestionIdInput,
                        'SecretAnswer'       => $secretAnswerHash,
                        'secret_answer'      => $secretAnswerHash,
                    ];

                    // 5. 画面遷移：ユーザーID発行（登録確認）画面へ遷移する
                    header('Location: register_check.php');
                    exit;
                } catch (PDOException $e) {
                    $errorMessages[] = 'ユーザーIDの発番に失敗しました。';
                    error_log('User ID generation error: ' . $e->getMessage());
                }
            }
        }
    }
}

$flash = get_flash();
if ($errorMessages === [] && !empty($flash['message'])) {
    $errorMessages[] = $flash['message'];
}

view('register', [
    'flash'             => $flash,
    'token'             => csrf_token(),
    'errorMessages'     => $errorMessages,
    'userNameInput'     => $userNameInput,
    'zipCodeInput'      => $zipCodeInput,
    'addressInput'      => $addressInput,
    'telInput'          => $telInput,
    'passwordMinLength' => $passwordMinLength,
    'passwordMaxLength' => $passwordMaxLength,
    'secretQuestions'       => $secretQuestions,
    'secretQuestionIdInput' => $secretQuestionIdInput,
    'secretAnswerInput'     => $secretAnswerInput,
]);
