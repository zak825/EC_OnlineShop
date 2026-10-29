<?php
    require_once __DIR__ . '/../src/bootstrap.php';

    // 【画面を開く】すでに管理者としてログイン済みなら管理画面へ
    if (is_admin()) {
        $redirectTo = admin_return_url((string)($_SESSION['admin']['redirect_after_login'] ?? 'setting.php'));
        unset($_SESSION['admin']['redirect_after_login']);
        redirect($redirectTo);
    }

    $pdo = db();
    $errorMessage = '';
    $messageType = 'error'; // 'error'（赤） / 'warning'（黄）
    $userIdInput = '';
    $step = 'login'; // 'login'（ID・パスワード入力） / 'confirm'（他の端末でログイン中の確認）

    // 確認画面で「続行」を押せる時間（秒）：5分
    $pendingTimeout = 5 * 60;

    /**
     * 他の端末でログイン中の確認待ちか
     */
    $isLoginPending = function () use ($pendingTimeout): bool {
        if (empty($_SESSION['admin']['pending_id']) || empty($_SESSION['admin']['pending_at'])) {
            return false;
        }
        return (time() - (int)$_SESSION['admin']['pending_at']) <= $pendingTimeout;
    };

    $clearLoginPending = function (): void {
        if (!isset($_SESSION['admin']) || !is_array($_SESSION['admin'])) {
            return;
        }
        unset($_SESSION['admin']['pending_id'], $_SESSION['admin']['pending_at']);
    };

    /**
     * 管理者のログイン情報をセッションに書き込んで画面遷移する
     */
    $completeLogin = function (array $admin, string $loginToken) use ($clearLoginPending): void {
        session_regenerate_id(true);
        ensure_admin_session();
        $clearLoginPending();
        unset($_SESSION['admin']['flash']);

        $redirectTo = admin_return_url((string)($_SESSION['admin']['redirect_after_login'] ?? $_GET['return'] ?? 'setting.php'));
        unset($_SESSION['admin']['redirect_after_login']);

        $_SESSION['admin']['id']            = (int)$admin['AdminId'];
        $_SESSION['admin']['login_id']      = (string)$admin['LoginId'];
        $_SESSION['admin']['name']          = (string)$admin['DisplayName'];
        $_SESSION['admin']['login_token']   = $loginToken;
        $_SESSION['admin']['last_activity'] = time();

        redirect($redirectTo);
    };

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $loginAction = $_POST['login_action'] ?? '';

        if (!check_admin_csrf_token()) {
            $errorMessage = '不正なリクエストです（CSRFトークンエラー）。画面を開き直して再度お試しください。';
            $step = ($loginAction === 'confirm' && $isLoginPending()) ? 'confirm' : 'login';
        } elseif ($loginAction === 'cancel') {
            $clearLoginPending();
        } elseif ($loginAction === 'confirm') {
            if (!$isLoginPending()) {
                $clearLoginPending();
                $errorMessage = '確認の有効期限が切れました。お手数ですが、もう一度ログインしてください。';
            } else {
                try {
                    $stmt = $pdo->prepare(
                        'SELECT TOP (1) AdminId, LoginId, DisplayName
                         FROM dbo.AdminAccount
                         WHERE AdminId = :admin_id'
                    );
                    $stmt->bindValue(':admin_id', (int)$_SESSION['admin']['pending_id'], PDO::PARAM_INT);
                    $stmt->execute();
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$admin) {
                        $clearLoginPending();
                        $errorMessage = 'ログインできませんでした。もう一度お試しください。';
                    } else {
                        $loginToken = acquire_admin_login_slot(
                            $pdo,
                            (int)$admin['AdminId'],
                            get_session_timeout_seconds(),
                            true
                        );
                        $completeLogin($admin, (string)$loginToken);
                    }
                } catch (PDOException $e) {
                    $errorMessage = 'データベースエラーが発生しました。';
                    error_log($e->getMessage());
                    $step = 'confirm';
                }
            }
        } else {
            $clearLoginPending();
            $userIdInput = trim($_POST['user_id'] ?? '');
            $password    = $_POST['password'] ?? '';

            if ($userIdInput === '' || $password === '') {
                $errorMessage = 'ログインIDとパスワードを入力してください。';
            } elseif (!preg_match('/^[A-Za-z0-9]{1,32}$/', $userIdInput)) {
                $errorMessage = 'ログインIDは32文字以内の半角英数字で入力してください。';
            } else {
                try {
                    $stmt = $pdo->prepare(
                        'SELECT TOP (1) AdminId, LoginId, DisplayName, PasswordHash
                         FROM dbo.AdminAccount
                         WHERE LoginId = :login_id'
                    );
                    $stmt->bindValue(':login_id', $userIdInput, PDO::PARAM_STR);
                    $stmt->execute();
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

                    $adminId = $admin ? (int)$admin['AdminId'] : 0;
                    $lockMessage = $admin ? active_admin_login_lock_message($pdo, $adminId) : null;

                    if ($lockMessage !== null) {
                        $errorMessage = $lockMessage;
                        $messageType = 'warning';
                    } elseif ($admin && password_verify($password, (string)$admin['PasswordHash'])) {
                        clear_admin_login_lock($pdo, $adminId);
                        $loginToken = acquire_admin_login_slot(
                            $pdo,
                            $adminId,
                            get_session_timeout_seconds()
                        );

                        if ($loginToken === null) {
                            session_regenerate_id(true);
                            ensure_admin_session();
                            $_SESSION['admin']['pending_id'] = $adminId;
                            $_SESSION['admin']['pending_at'] = time();
                            $step = 'confirm';
                        } else {
                            $completeLogin($admin, $loginToken);
                        }
                    } elseif ($admin) {
                        $lockMessage = register_admin_login_failure($pdo, $adminId);
                        if ($lockMessage !== null) {
                            $errorMessage = $lockMessage;
                            $messageType = 'warning';
                        } else {
                            $errorMessage = 'ログインIDまたはパスワードが間違っています。';
                        }
                    } else {
                        $errorMessage = 'ログインIDまたはパスワードが間違っています。';
                    }
                } catch (PDOException $e) {
                    $errorMessage = 'データベースエラーが発生しました。';
                    error_log($e->getMessage());
                }
            }
        }
    }

    $flash = get_admin_flash();
    if ($errorMessage === '' && !empty($flash['message'])) {
        $errorMessage = $flash['message'];
        if (($flash['type'] ?? '') === 'warning') {
            $messageType = 'warning';
        }
    }

    view('login', [
        'token'              => admin_csrf_token(),
        'errorMessage'       => $errorMessage,
        'messageType'        => $messageType,
        'userIdInput'        => $userIdInput,
        'step'               => $step,
        'loginKind'          => 'admin',
        'formAction'         => 'admin_login.php',
        'pageTitle'          => '管理者ログイン',
        'loginIdLabel'       => 'ログインID',
        'loginIdMaxLength'   => 32,
        'loginIdPlaceholder' => '例: admin',
    ]);
