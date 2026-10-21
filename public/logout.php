<?php
    require_once __DIR__ . '/../src/bootstrap.php';

    // 確認ポップアップは index.js で出す（OK のときだけ送信される）
    if($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('index.php');
    }

    if (!check_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('不正な操作です。もう一度やり直してください。', 'error');
        redirect('index.php');
    }

    // 2. ログイン情報をセッションから消す（ログインフラグを落とす）
    logout_session();

    // 画面遷移：ログイン画面へ
    set_flash('ログアウトしました。', 'success');
    redirect(URL_LOGIN);
?>