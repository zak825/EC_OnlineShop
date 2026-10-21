<?php
require_once __DIR__ . '/../src/bootstrap.php';

// ログイン状態とユーザー名（index.php から移動）
$headerLoggedIn = is_logged_in();
$headerUserName = '';
if ($headerLoggedIn) {
    $stmt = db()->prepare('SELECT UserName FROM UserData WHERE Userid = :userId');
    $stmt->execute([':userId' => login_user_id()]);
    $headerUserName = (string)($stmt->fetchColumn() ?: '');
}
?>
<link rel="stylesheet" href="assets/css/header.css">

<header>
    <div class="logo">
        <a href="index.php">
            <img src="assets/images/logo.png" width="150" height="60" alt="elogo">
        </a>
    </div>

    <nav>
        <?php if ($headerLoggedIn): ?>
            <div class="right">
                <a href="mypage.php" class="UserName"><?= name_label($headerUserName) ?></a>
                <a href="sell.php" class="btn-sell">出品</a>
                <a href="mypage.php" class="UserName"><?= htmlspecialchars($headerUserName, ENT_QUOTES, 'UTF-8') ?></a>
                <form action="logout.php" method="post"
                      onsubmit="return confirm('ログアウトしますか？');">
                    <input type="hidden" name="csrf_token"
                       value="<?= h(csrf_token()) ?>">
                    <button type="submit" class="btn-logout">ログアウト</button>
            </form>
            </div>
        <?php else: ?>
            <a href="<?= URL_LOGIN ?>" class="btn-login">ログイン</a>
        <?php endif; ?>
    </nav>
</header>
<hr>