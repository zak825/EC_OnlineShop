<?php
// ============================================================
// メイン画面
//   GET  index.php                  … 販売中の商品を一覧で表示
//   POST index.php (action=logout)  … ログアウトしてログイン画面へ
// 設計書: 詳細設計_Rev.1.0.xlsx「メイン画面_設計処理」
//
// 販売中 = Sales.BuyerUserid が NULL（まだ誰も買っていない）
// 未ログインでも一覧は見られる（購入ボタンを押すと購入画面でログインを求める）
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';

// 販売画面のファイル名（config.php に書いていなければこの名前を使う）
if (!defined('URL_SELL')) {
    define('URL_SELL', 'sell.php');
}

// ---- ログアウトボタン ---------------------------------------------
// 確認ポップアップは index.js で出す（OK のときだけ送信される）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    if (!check_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('不正な操作です。もう一度やり直してください。', 'error');
        redirect('index.php');
    }

    // 2. ログイン情報をセッションから消す（ログインフラグを落とす）
    logout_session();

    // 画面遷移：ログイン画面へ
    set_flash('ログアウトしました。', 'success');
    redirect(URL_LOGIN);
}

// ---- 1. ログインの確認 ---------------------------------------------
// セッションが切れていないか確認する（切れていても一覧は表示する）
$loggedIn = is_logged_in();
$userId   = $loggedIn ? login_user_id() : null;

// ヘッダーに出すユーザー名（マイページへのリンクにする）
$userName = '';
if ($loggedIn) {
    $stmt = db()->prepare('SELECT UserName FROM UserData WHERE Userid = :userId');
    $stmt->execute([':userId' => $userId]);
    $userName = (string)($stmt->fetchColumn() ?: '');
}

// ---- 2. DBの参照 ---------------------------------------------------
// 販売中の商品を新しい順に取得する（ジャンル名・出品者名も結合して取る）
$stmt = db()->prepare(
    'SELECT s.ProductID, s.ProductName, s.Price, s.Userid,
            g.GenreName,
            u.UserName AS SellerName
     FROM Sales s
     JOIN Genre    g ON s.GenreCode = g.GenreCode
     JOIN UserData u ON s.Userid    = u.Userid
     WHERE s.BuyerUserid IS NULL
     ORDER BY s.ProductID DESC'
);
$stmt->execute();
$products = $stmt->fetchAll();

// ---- 3. 情報表示 ---------------------------------------------------
view('index', [
    'flash'    => get_flash(),     // メッセージ（購入が完了しました 等）
    'loggedIn' => $loggedIn,       // ログイン中か
    'userId'   => $userId,         // ログイン中のユーザーID（自分の出品の判定用）
    'userName' => $userName,       // ヘッダーに出すユーザー名
    'products' => $products,       // 販売中の商品
    'token'    => csrf_token(),    // ログアウトボタン用
]);
