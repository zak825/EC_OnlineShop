<?php
require_once __DIR__ . '/../src/bootstrap.php';

// ログイン状態とユーザー名（index.php から移動）
$headerLoggedIn = is_logged_in();
$headerUserName = '';
$headerIconPath = '';
if ($headerLoggedIn) {
    $stmt = db()->prepare('SELECT UserName, IconPath FROM UserData WHERE Userid = :userId');
    $stmt->execute([':userId' => login_user_id()]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $headerUserName = (string)($row['UserName'] ?? '');
    $headerIconPath = (string)($row['IconPath'] ?? '');
}
?>
<link rel="stylesheet" href="assets/css/header.css">

<header>
    <div class="logo">
        <a href="index.php">
            <img src="assets/images/logo.png" width="140" height="50" alt="elogo">
        </a>
    </div>

    <nav>
       <?php if ($headerLoggedIn): ?>
            <div class="right">
                <a href="mypage.php" class="icon">
                    <?php if ($headerIconPath !== ''): ?>
                        <img src="<?= h($headerIconPath) ?>" alt="" class="profile__icon profile__icon--header">
                    <?php else: ?>
                        <!-- アイコン未設定のときは名前の頭文字を表示 -->
                        <span class="profile__icon profile__icon--initial profile__icon--header" aria-hidden="true">
                            <?= h(mb_substr($headerUserName, 0, 1)) ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="mypage.php" class="UserName"><?= name_label($headerUserName) ?></a>
                <a href="sell.php" class="btn-header btn-header--primary">出品</a>
                <form action="logout.php" method="post"
                    onsubmit="return confirm('ログアウトしますか？');">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <button type="submit" class="btn-header btn-header--secondary">ログアウト</button>
                </form>
            </div>
        <?php else: ?>
            <a href="<?= URL_LOGIN ?>" class="btn-header btn-header--primary" id="login-btn">ログイン</a>
        <?php endif; ?>
    </nav>
</header>