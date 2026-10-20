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

    // 必須セッション情報が欠落している場合もアカウント登録画面へリダイレクト
    if (empty($userId) || $userName === '' || $passwordHash === '') {
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

                // ユーザーIDの重複対策
                // ID は登録画面で発番しているので、「はい」を押すまでの間に他の人が同じ ID で登録していることがある。
                // テーブルをロックしてから確認し、使われていたら今の最大値 + 1 で取り直す
                $stmt = $pdo->prepare('SELECT ISNULL(MAX(UserId), 0) + 1 FROM dbo.UserData WITH (UPDLOCK, HOLDLOCK)');
                $stmt->execute();
                $nextUserId = (int)$stmt->fetchColumn();

                $stmt = $pdo->prepare('SELECT COUNT(*) FROM dbo.UserData WHERE UserId = :user_id');
                $stmt->execute([':user_id' => (int)$userId]);
                if ((int)$stmt->fetchColumn() > 0) {
                    $userId = $nextUserId;
                    $registerUser['UserId']  = $userId;
                    $registerUser['user_id'] = $userId;
                }

                // UserId は IDENTITY（自動採番）だが、発番済みの ID で登録するので一時的に指定できるようにする
                $pdo->exec("SET IDENTITY_INSERT dbo.UserData ON");

                $sql = "INSERT INTO dbo.UserData (UserId, UserName, ZipCode, Address, TEL, LoginPassword)
                        VALUES (:user_id, :user_name, :zip_code, :address, :tel, :password)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':user_id'   => (int)$userId,
                    ':user_name' => $userName,
                    ':zip_code'  => $zipCode,
                    ':address'   => $address,
                    ':tel'       => $tel,
                    ':password'  => $passwordHash,
                ]);

                $pdo->exec("SET IDENTITY_INSERT dbo.UserData OFF");

                // 共通項目（Commonテーブル）に登録ログを残す
                $commonSql = "INSERT INTO dbo.Common (InsertUserId, InsertDateTime)
                              VALUES (:user_id, :now)";
                $commonStmt = $pdo->prepare($commonSql);
                $commonStmt->execute([
                    ':user_id' => (int)$userId,
                    ':now'     => $now,
                ]);

                $pdo->commit();

                // 3. 一時セッション情報の削除
                unset($_SESSION['register_user']);

                if ($isAjax) {
                    header('Content-Type: application/json; charset=UTF-8');
                    // 重複で ID を取り直した場合があるので、実際に登録した ID を返す
                    echo json_encode(['success' => true, 'user_id' => (int)$userId]);
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
    ]);
