<?php
 session_start();
 $_SESSION = [];
 session_destroy();

 // ログイン画面にリダイレクト
 header('Location: login.php');
?>