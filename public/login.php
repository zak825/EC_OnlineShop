<?php
    require_once __DIR__ . '/../src/bootstrap.php';

    // 【画面を開く】すでにログイン済み（かつセッション有効期限内）であるか確認
    if (is_logged_in()) {
        header('Location: index.php');
        exit;
    }

    $pdo = db();
    $errorMessage = '';
    $userIdInput = '';

    // 【ログインボタンを押す】POST送信時の処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!check_csrf_token()) {
            $errorMessage = '不正なリクエストです（CSRFトークンエラー）。画面を開き直して再度お試しください。';
        } else {
            $userIdInput = trim($_POST['user_id'] ?? '');
            $password    = $_POST['password'] ?? '';

            // 1. 未入力・形式のチェックを行う
            if ($userIdInput === '' || $password === '') {
                $errorMessage = 'userIDとパスワードを入力してください。';
            } elseif (!preg_match('/^\d{1,8}$/', $userIdInput)) {
                $errorMessage = 'userIDは8桁以内の半角数字で入力してください。';
            } else {
                try {
                    // 2. SQLインジェクションのチェック（プレースホルダ）
                    // 3. userIDとパスワードのチェックを行う
                    $sql = "SELECT TOP (1) UserId, UserName, LoginPassword
                            FROM dbo.UserData
                            WHERE UserId = :user_id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindValue(':user_id', (int)$userIdInput, PDO::PARAM_INT);
                    $stmt->execute();
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user && password_verify($password, $user['LoginPassword'])) {
                        // 4. ログイン情報をセッションに書き込む
                        session_regenerate_id(true);
                        $_SESSION['logged_in']     = true;
                        $_SESSION['user_id']       = (int)$user['UserId'];
                        $_SESSION['UserId']        = (int)$user['UserId']; // src/lib/auth.php のキー名に合わせて両方保持
                        $_SESSION['user_name']     = $user['UserName'];
                        $_SESSION['UserName']      = $user['UserName'];
                        $_SESSION['last_activity'] = time();

                        // 画面遷移：リダイレクト指定があればその画面へ、なければメイン画面へ
                        $redirectTo = $_SESSION['redirect_after_login'] ?? $_GET['redirect'] ?? $_GET['return'] ?? 'index.php';
                        unset($_SESSION['redirect_after_login']);
                        // 外部URLへのオープンリダイレクト防止（クエリパラメータ付きURLも許可）
                        if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.php(\?[a-zA-Z0-9_\-\.=&]+)?$/', $redirectTo)) {
                            $redirectTo = 'index.php';
                        }

                        header('Location: ' . $redirectTo);
                        exit;
                    } else {
                        $errorMessage = 'userIDまたはパスワードが間違っています。';
                    }
                } catch (PDOException $e) {
                    $errorMessage = 'データベースエラーが発生しました。';
                    error_log($e->getMessage());
                }
            }
        }
    }

    $flash = get_flash();
    if ($errorMessage === '' && !empty($flash['message'])) {
        $errorMessage = $flash['message'];
    }

    view('login', [
        'flash'        => $flash,
        'token'        => csrf_token(),
        'errorMessage' => $errorMessage,
        'userIdInput'  => $userIdInput,
    ]);
