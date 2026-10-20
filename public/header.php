<?php
require_once __DIR__ . '/../src/bootstrap.php';
?>

<header>
    <div class="logo">
        <a href="index.php">
            <img src="assets/images/logo.png" width="150" height="60" alt="elogo">
        </a>
    </div>
    
    <nav>
        <?php if (isset($_SESSION['Userid'])): ?>
            <!-- 🔓 ログイン済みの表示 -->
            <a href="mypage.php"><?php echo($_SESSION['Userid']); ?></a>
            <a href="../templates/login.html">ログアウト</a>
            
        <?php else: ?>
            <!-- 🔒 未ログインの表示 -->
            <a href="../templates/login.html" class="btn">ログイン</a>
        <?php endif; ?>
    </nav>
</header>
<hr>