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
    $step = 'login'; // 'login'（ID・パスワード入力） / 'confirm'（他の端末でログイン中の確認）

    // 確認画面で「続行」を押せる時間（秒）：5分
    $pendingTimeout = 5 * 60;

    /**
     * 他の端末でログイン中の確認待ちか（パスワード認証は済んでいて、続行の返事を待っている状態）
     */
    $isLoginPending = function () use ($pendingTimeout): bool {
        if (empty($_SESSION['login_pending_uid']) || empty($_SESSION['login_pending_at'])) {
            return false;
        }
        return (time() - (int)$_SESSION['login_pending_at']) <= $pendingTimeout;
    };

    $clearLoginPending = function (): void {
        unset($_SESSION['login_pending_uid'], $_SESSION['login_pending_at']);
    };

    /**
     * ログイン情報をセッションに書き込んで画面遷移する
     */
    $completeLogin = function (array $user, string $loginToken) use ($clearLoginPending): void {
        session_regenerate_id(true);
        $clearLoginPending();
        $_SESSION['logged_in']     = true;
        $_SESSION['user_id']       = (int)$user['UserId'];
        $_SESSION['UserId']        = (int)$user['UserId']; // src/lib/auth.php のキー名に合わせて両方保持
        $_SESSION['user_name']     = $user['UserName'];
        $_SESSION['UserName']      = $user['UserName'];
        $_SESSION['last_activity'] = time();
        $_SESSION['login_token']   = $loginToken;

        // 画面遷移：リダイレクト指定があればその画面へ、なければメイン画面へ
        $redirectTo = $_SESSION['redirect_after_login'] ?? $_GET['redirect'] ?? $_GET['return'] ?? 'index.php';
        unset($_SESSION['redirect_after_login']);
        // 外部URLへのオープンリダイレクト防止（クエリパラメータ付きURLも許可）
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.php(\?[a-zA-Z0-9_\-\.=&]+)?$/', $redirectTo)) {
            $redirectTo = 'index.php';
        }

        header('Location: ' . $redirectTo);
        exit;
    };

    // 【ログインボタンを押す】POST送信時の処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $loginAction = $_POST['login_action'] ?? '';

        if (!check_csrf_token()) {
            $errorMessage = '不正なリクエストです（CSRFトークンエラー）。画面を開き直して再度お試しください。';
            $step = ($loginAction === 'confirm' && $isLoginPending()) ? 'confirm' : 'login';
        } elseif ($loginAction === 'cancel') {
            // 【確認画面でキャンセル】ログインせずに入力画面へ戻る（他の端末のセッションはそのまま）
            $clearLoginPending();
        } elseif ($loginAction === 'confirm') {
            // 【確認画面で続行】他の端末のセッションを無効にして、この端末でログインする
            if (!$isLoginPending()) {
                $clearLoginPending();
                $errorMessage = '確認の有効期限が切れました。お手数ですが、もう一度ログインしてください。';
            } else {
                try {
                    $stmt = $pdo->prepare(
                        "SELECT TOP (1) UserId, UserName
                         FROM dbo.UserData
                         WHERE UserId = :user_id"
                    );
                    $stmt->bindValue(':user_id', (int)$_SESSION['login_pending_uid'], PDO::PARAM_INT);
                    $stmt->execute();
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$user) {
                        $clearLoginPending();
                        $errorMessage = 'ログインできませんでした。もう一度お試しください。';
                    } else {
                        // $force = true：他の端末のトークンを無効にして置き換える
                        $loginToken = acquire_login_slot(
                            $pdo,
                            (int)$user['UserId'],
                            get_session_timeout_seconds(),
                            true
                        );
                        $completeLogin($user, (string)$loginToken);
                    }
                } catch (PDOException $e) {
                    $errorMessage = 'データベースエラーが発生しました。';
                    error_log($e->getMessage());
                    $step = 'confirm';
                }
            }
        } else {
            // 【通常のログイン】
            $clearLoginPending();
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
                        // 3.5 同時ログイン禁止：同じアカウントが他の端末でログイン中なら、確認画面を出す
                        //     （パスワード認証が通った人にだけ、ログイン中かどうかを伝える）
                        $loginToken = acquire_login_slot(
                            $pdo,
                            (int)$user['UserId'],
                            get_session_timeout_seconds()
                        );

                        if ($loginToken === null) {
                            // 確認待ちにする（まだログインはさせない。user_id もセッションに入れない）
                            session_regenerate_id(true);
                            $_SESSION['login_pending_uid'] = (int)$user['UserId'];
                            $_SESSION['login_pending_at']  = time();
                            $step = 'confirm';
                        } else {
                            // 4. ログイン情報をセッションに書き込んで画面遷移する
                            $completeLogin($user, $loginToken);
                        }
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

    // メッセージの種別：'error'（赤） / 'warning'（黄）
    $messageType = 'error';

    $flash = get_flash();
    if ($errorMessage === '' && !empty($flash['message'])) {
        $errorMessage = $flash['message'];
        if (($flash['type'] ?? '') === 'warning') {
            $messageType = 'warning';
        }
    }

    view('login', [
        'flash'        => $flash,
        'token'        => csrf_token(),
        'errorMessage' => $errorMessage,
        'messageType'  => $messageType,
        'userIdInput'  => $userIdInput,
        'step'         => $step,
    ]);
