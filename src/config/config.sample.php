<?php
// ============================================================
// 設定ファイルの見本
//
// 使い方：このファイルをコピーして config.php を作り、自分の環境に合わせて書き換える
//   copy src\config\config.sample.php src\config\config.php
//
// config.php は人によってパスワード等が違うので git に上げない（.gitignore 済み）
// ============================================================

// 購入日時などを日本時間で記録する
date_default_timezone_set('Asia/Tokyo');

// ---- DB接続設定（SQL Server） ----
// PHP に pdo_sqlsrv ドライバが必要（README 参照）
// SQL Server Express なら Server=localhost\SQLEXPRESS
define('DB_DSN',  'sqlsrv:Server=localhost\SQLEXPRESS;Database=CSE_EC');
define('DB_USER', 'sa');
define('DB_PASS', 'password');   // 自分の sa のパスワードに変える

// ---- 画面のURL（public/ からの相対パス） ----
define('URL_LOGIN', 'login.php');  // ログイン画面（?return= で戻り先を受け取る）
define('URL_MAIN',   'main.php');    // メイン画面
define('URL_STATUS', 'main.php');    // レビュー画面の戻り先（マイページができたら 'mypage.php'）
define('URL_MYPAGE', 'main.php');    // 同上（新しい版のコードはこちらを使う）

// ---- 開発中はエラーを画面に出す（本番サーバでは false にする） ----
define('APP_DEBUG', true);

// ---- 開発用の自動ログイン ----
// UserData に存在するテスト用ユーザーIDを指定する。0 にすると無効。
//   2 … 購入 花子（購入・レビューのテスト用）
// ログイン画面ができたら 0 に戻す。セッション切れの動きを試すときも 0 にする。
define('DEV_LOGIN_USER_ID', 0);
