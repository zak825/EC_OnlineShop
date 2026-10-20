<?php
    require_once __DIR__ . '/../src/bootstrap.php';

    // 【画面を開く】すでにログイン済み（かつセッション有効期限内）であるか確認
    if (is_logged_in()) {
        header('Location: main.php');
        exit;
    }

    // 【セッション情報の確認】
    // セッション情報を参照し、見つからなかった場合は画面上に何も表示せずそのままアカウント登録画面にリダイレクト
    if (empty($_SESSION['register_user'])) {
        header('Location: register.php');
        exit;
    }

    $registerUser = $_SESSION['register_user'];
    $userId   = $registerUser['UserId'] ?? $registerUser['user_id'] ?? null;
    $userName = $registerUser['UserName'] ?? $registerUser['user_name'] ?? '';
    $zipCode  = $registerUser['ZipCode'] ?? $registerUser['zip_code'] ?? '';
    $address  = $registerUser['Address'] ?? $registerUser['address'] ?? '';
    $tel      = $registerUser['TEL'] ?? $registerUser['tel'] ?? '';
    $passwordHash = $registerUser['LoginPassword'] ?? $registerUser['password'] ?? '';
    $secretQuestionId = (int)($registerUser['SecretQuestionId'] ?? $registerUser['secret_question_id'] ?? 0);
    $secretAnswer     = $registerUser['SecretAnswer'] ?? $registerUser['secret_answer'] ?? '';
    $secretQuestions  = get_secret_questions();

    // 必須セッション情報が欠落している場合もアカウント登録画面へリダイレクト
    if (empty($userId) || $userName === '' || $passwordHash === ''
        || !array_key_exists($secretQuestionId, $secretQuestions) || $secretAnswer === '') {
        header('Location: register.php');
        exit;
    }

    $pdo = db();
    $errorMessage = '';

    // 【「はい」ボタン押下】POST送信時の登録処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
               || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
               || (isset($_POST['ajax']) && $_POST['ajax'] === '1');

        if (!check_csrf_token()) {
            $errorMessage = '不正なリクエストです（CSRFトークンエラー）。画面を開き直して再度お試しください。';
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['success' => false, 'message' => $errorMessage]);
                exit;
            }
        } else {
            try {
                // 1. SQLインジェクション対策実施（プレースホルダ利用）
                // 2. ユーザー情報をUserDataに登録
                $pdo->beginTransaction();
                $now = date('Y-m-d H:i:s');

                // SQL Server IDENTITY列の挿入対応
                try {
                    $pdo->exec("SET IDENTITY_INSERT dbo.UserData ON");
                } catch (PDOException $e) {
                    // IDENTITYが設定されていないテーブル構成の場合はスキップ
                }

                $sql = "INSERT INTO dbo.UserData (UserId, UserName, ZipCode, Address, TEL, LoginPassword, SecretQuestionId, SecretAnswer)
                        VALUES (:user_id, :user_name, :zip_code, :address, :tel, :password, :secret_question_id, :secret_answer)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':user_id'            => (int)$userId,
                    ':user_name'          => $userName,
                    ':zip_code'           => $zipCode,
                    ':address'            => $address,
                    ':tel'                => $tel,
                    ':password'           => $passwordHash,
                    ':secret_question_id' => $secretQuestionId,
                    ':secret_answer'      => $secretAnswer,
                ]);

                try {
                    $pdo->exec("SET IDENTITY_INSERT dbo.UserData OFF");
                } catch (PDOException $e) {
                }

                // 共通項目（Commonテーブル）が存在する場合は登録ログを残す
                try {
                    $commonSql = "INSERT INTO dbo.Common (InsertUserId, InsertDateTime)
                                  VALUES (:user_id, :now)";
                    $commonStmt = $pdo->prepare($commonSql);
                    $commonStmt->execute([
                        ':user_id' => (int)$userId,
                        ':now'     => $now,
                    ]);
                } catch (PDOException $e) {
                    // Commonテーブル未作成環境等の場合はスキップ
                    error_log('Common table insert skipped: ' . $e->getMessage());
                }

                $pdo->commit();

                // 3. 一時セッション情報の削除
                unset($_SESSION['register_user']);

                if ($isAjax) {
                    header('Content-Type: application/json; charset=UTF-8');
                    echo json_encode(['success' => true]);
                    exit;
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errorMessage = 'ユーザー情報の登録に失敗しました。';
                error_log('User registration error: ' . $e->getMessage());

                if ($isAjax) {
                    header('Content-Type: application/json; charset=UTF-8');
                    echo json_encode(['success' => false, 'message' => $errorMessage]);
                    exit;
                }
            }
        }
    }

    $flash = get_flash();
    if ($errorMessage === '' && !empty($flash['message'])) {
        $errorMessage = $flash['message'];
    }

    view('register_check', [
        'flash'        => $flash,
        'token'        => csrf_token(),
        'errorMessage' => $errorMessage,
        'user'         => $registerUser,
        'secretQuestionText' => $secretQuestions[$secretQuestionId] ?? '',
        'secretAnswer'       => $secretAnswer,
    ]);
