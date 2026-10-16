<?php
    require_once __DIR__ . '/../src/bootstrap.php';

    // 【画面を開く】すでにログイン済み（かつセッション有効期限内）であるか確認
    if (is_logged_in()) {
        header('Location: main.php');
        exit;
    }

    $pdo = db();
    $errorMessage = '';
    $userIdInput = '';

    // 【ログインボタンを押す】POST送信時の処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf_token();

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

                if ($user && $user['LoginPassword'] === $password) {
                    // 4. ログイン情報をセッションに書き込む
                    session_regenerate_id(true);
                    $_SESSION['logged_in']     = true;
                    $_SESSION['user_id']       = (int)$user['UserId'];
                    $_SESSION['UserId']        = (int)$user['UserId']; // src/lib/auth.php のキー名に合わせて両方保持
                    $_SESSION['user_name']     = $user['UserName'];
                    $_SESSION['UserName']      = $user['UserName'];
                    $_SESSION['last_activity'] = time();

                    // 画面遷移：リダイレクト指定があればその画面へ、なければメイン画面へ
                    $redirectTo = $_SESSION['redirect_after_login'] ?? $_GET['redirect'] ?? 'main.php';
                    unset($_SESSION['redirect_after_login']);
                    // 外部URLへのオープンリダイレクト防止
                    if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.php$/', $redirectTo)) {
                        $redirectTo = 'main.php';
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

    view('login/login', [
        'flash'        => get_flash(),
        'token'        => csrf_token(),
        'errorMessage' => $errorMessage,
        'userIdInput'  => $userIdInput,
    ]);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>ログイン画面</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="container">
        <!-- サーバー側およびJS側のエラーメッセージ表示領域 -->
        <p id="error-message" class="error-msg" style="<?= $errorMessage === '' ? 'display:none;' : '' ?>">
            <?= h($errorMessage) ?>
        </p>

        <form id="login-form" action="login.php" method="post" novalidate>
            <div class="form-group">
                <label for="user_id">userID</label>
                <input type="text" id="user_id" name="user_id" maxlength="8" value="<?= h($_POST['user_id'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">パスワード</label>
                <input type="password" id="password" name="password" maxlength="50">
            </div>

            <div class="btn-row">
                <button type="submit" id="login-btn">ログイン</button>
                <button type="button" id="clear-btn">クリア</button>
            </div>
        </form>

        <div class="register-section">
            <p class="section-label">ユーザー登録</p>
            <button type="button" onclick="location.href='register.php'">登録</button>
        </div>
    </div>

    <script src="login.js"></script>
</body>
</html>