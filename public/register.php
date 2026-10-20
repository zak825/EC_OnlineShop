<?php
    require_once __DIR__ . '/../src/bootstrap.php';

    // 【画面を開く】すでにログイン済み（かつセッション有効期限内）であるか確認
    if (is_logged_in()) {
        header('Location: main.php');
        exit;
    }

    // 【戻るボタンを押す】セッションの一時入力情報を破棄し、ログイン画面へ遷移する
    if (isset($_GET['action']) && $_GET['action'] === 'back') {
        unset($_SESSION['register_user']);
        header('Location: login.php');
        exit;
    }

    $pdo = db();
    $errorMessage = '';
    $secretQuestions = get_secret_questions();

    // 入力フォーム保持用変数
    $userNameInput         = '';
    $zipCodeInput          = '';
    $addressInput          = '';
    $telInput              = '';
    $secretQuestionIdInput = '';
    $secretAnswerInput     = '';

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
        $errorMessage = 'パスワードポリシーの取得に失敗しました。';
        error_log('Password policy fetch error: ' . $e->getMessage());
    }

    // 【登録ボタンを押す】POST送信時の処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!check_csrf_token()) {
            $errorMessage = '不正なリクエストです（CSRFトークンエラー）。画面を開き直して再度お試しください。';
        } else {
            // 入力された文字列の取得および前後の空白除去（サニタイズ処理）
            $userNameInput = trim($_POST['user_name'] ?? '');
            $zipCodeInput  = trim($_POST['zip_code'] ?? '');
            $addressInput  = trim($_POST['address'] ?? '');
            $telInput      = trim($_POST['tel'] ?? '');
            $password      = $_POST['password'] ?? '';
            $secretQuestionIdInput = trim((string)($_POST['secret_question_id'] ?? ''));
            $secretAnswerInput     = trim((string)($_POST['secret_answer'] ?? ''));

            // 1. 未入力・桁数チェックを行う
            // すべての項目が入力されているか、また各項目（氏名、郵便番号、住所、電話番号）がUserDataテーブル定義の桁数・形式内であるかチェックする
            $userNameLen = mb_strlen($userNameInput, 'UTF-8');
            $addressLen  = mb_strlen($addressInput, 'UTF-8');
            $zipCodeLen  = strlen($zipCodeInput);
            $telLen      = strlen($telInput);
            $secretAnswerLen = mb_strlen($secretAnswerInput, 'UTF-8');

            if ($userNameInput === '' || $zipCodeInput === '' || $addressInput === '' || $telInput === '' || $password === ''
                || $secretQuestionIdInput === '' || $secretAnswerInput === '') {
                $errorMessage = 'すべての項目を入力してください。';
            } elseif ($userNameLen > 50) {
                $errorMessage = '氏名は50文字以内で入力してください。';
            } elseif ($zipCodeLen > 8 || !preg_match('/^\d{3}-?\d{4}$/', $zipCodeInput)) {
                $errorMessage = '郵便番号は8桁以内の半角数字（例：1234567 または 123-4567）で入力してください。';
            } elseif ($addressLen > 256) {
                $errorMessage = '住所は256文字以内で入力してください。';
            } elseif ($telLen > 15 || !preg_match('/^[0-9\-]{10,15}$/', $telInput)) {
                $errorMessage = '電話番号は15桁以内の半角数字（ハイフン可）で入力してください。';
            } elseif (!preg_match('/\A\d+\z/', $secretQuestionIdInput) || !array_key_exists((int)$secretQuestionIdInput, $secretQuestions)) {
                // 秘密の質問IDが定義済み（1〜5）であるかチェックする
                $errorMessage = '秘密の質問を正しく選択してください。';
            } elseif ($secretAnswerLen > 100) {
                $errorMessage = '秘密の質問の回答は100文字以内で入力してください。';
            } else {
                // 2. パスワードポリシーのチェックを行う
                // 入力されたパスワードの長さが、SystemConfigurationテーブルから取得した文字数設定の範囲内であるかチェックする
                $passwordLen = mb_strlen($password, 'UTF-8');
                if ($passwordLen < $passwordMinLength || $passwordLen > $passwordMaxLength) {
                    $errorMessage = "パスワードは{$passwordMinLength}文字以上{$passwordMaxLength}文字以内で入力してください。";
                } else {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    if ($passwordHash === false) {
                        $errorMessage = 'パスワードの処理に失敗しました。';
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
                                'SecretAnswer'       => $secretAnswerInput,
                                'secret_answer'      => $secretAnswerInput,
                            ];

                            // 5. 画面遷移：ユーザーID発行（登録確認）画面へ遷移する
                            header('Location: register_check.php');
                            exit;
                        } catch (PDOException $e) {
                            $errorMessage = 'ユーザーIDの発番に失敗しました。';
                            error_log('User ID generation error: ' . $e->getMessage());
                        }
                    }
                }
            }
        }
    }

    $flash = get_flash();
    if ($errorMessage === '' && !empty($flash['message'])) {
        $errorMessage = $flash['message'];
    }

    view('register', [
        'flash'             => $flash,
        'token'             => csrf_token(),
        'errorMessage'      => $errorMessage,
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
