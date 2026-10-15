<?php
// ============================================================
// 共通設定（チームの環境に合わせて書き換えてください）
// ============================================================

// 購入日時などを日本時間で記録する
date_default_timezone_set('Asia/Tokyo');

// DB接続設定
// SQL Server の例: 'sqlsrv:Server=localhost;Database=CSE_EC'
// MySQL の例    : 'mysql:host=localhost;dbname=CSE_EC;charset=utf8mb4'
define('DB_DSN',  'sqlsrv:Server=localhost;Database=CSE_EC');
define('DB_USER', 'sa');
define('DB_PASS', 'password');

// 他の担当者が作る画面のファイル名
define('URL_LOGIN', 'login.php');  // ログイン画面（?return= で戻り先を受け取る）
define('URL_MAIN',  'main.php');   // メイン画面
