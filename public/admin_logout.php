<?php
    require_once __DIR__ . '/../src/bootstrap.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('admin_login.php');
    }

    if (!check_admin_csrf_token($_POST['csrf_token'] ?? null)) {
        set_admin_flash('不正な操作です。もう一度やり直してください。', 'error');
        redirect('setting.php');
    }

    logout_admin();

    set_admin_flash('ログアウトしました。', 'success');
    redirect('admin_login.php');
