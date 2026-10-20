<?php
// ============================================================
// マイページ画面
//   GET mypage.php            … 購入した商品の一覧
//   GET mypage.php?tab=sold   … 出品した商品の一覧
// 設計書: 詳細設計_Rev.1.0.xlsx「マイページ画面_設計処理」
//
// Sales テーブルの使い方
//   出品者 … Sales.Userid
//   購入者 … Sales.BuyerUserid（BuyerName は表示用の購入者名）
//   発送   … Sales.ShippingFlag（0:未発送 1:発送済）
//   取引完了 … Sales.sucre（レビューの評価値）が入っている
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';

// 発送通知送信画面のファイル名（config.php に書いていなければこの名前を使う）
if (!defined('URL_SHIPPING')) {
    define('URL_SHIPPING', 'shipping.php');
}

$tab = ($_GET['tab'] ?? '') === 'sold' ? 'sold' : 'bought';

// ---- 1. ログイン確認 ---------------------------------------------
// 未ログインのときはログイン画面へ飛ばさず、ログインを勧めるメッセージとボタンを表示する
if (!is_logged_in()) {
    view('mypage', [
        'flash'    => get_flash(),
        'loggedIn' => false,
        'tab'      => $tab,
    ]);
    exit;
}
$userId = login_user_id();

// ログインユーザーの名前を取得（画面上部に表示する）
$stmt = db()->prepare('SELECT UserName FROM UserData WHERE Userid = :userId');
$stmt->execute([':userId' => $userId]);
$userName = $stmt->fetchColumn() ?: '';

// ---- 2. 購入情報参照（購入者が自分の商品） ------------------------
$stmt = db()->prepare(
    'SELECT s.ProductID, s.ProductName, s.Price, s.SoldDate, s.ShippingFlag,
            s.sucre, s.revwe,
            seller.UserName AS SellerName
     FROM Sales s
     JOIN UserData seller ON s.Userid = seller.Userid
     WHERE s.BuyerUserid = :buyer
     ORDER BY s.SoldDate DESC'
);
$stmt->execute([':buyer' => $userId]);
$bought = $stmt->fetchAll();

// ---- 3. 出品情報参照（出品者が自分の商品） ------------------------
// 購入者名・配送先は、BuyerUserid で UserData と結合して取る
$stmt = db()->prepare(
    'SELECT s.ProductID, s.ProductName, s.Price, s.BuyerUserid, s.SoldDate, s.ShippingFlag,
            s.sucre, s.revwe,
            buyer.UserName AS BuyerUserName, buyer.ZipCode, buyer.Address, buyer.TEL
     FROM Sales s
     LEFT JOIN UserData buyer ON s.BuyerUserid = buyer.Userid
     WHERE s.Userid = :seller
     ORDER BY CASE WHEN s.BuyerUserid IS NULL THEN 1 ELSE 0 END, s.SoldDate DESC, s.ProductID DESC'
);
$stmt->execute([':seller' => $userId]);
$sold = $stmt->fetchAll();

// ---- 4. 情報表示 -------------------------------------------------
view('mypage', [
    'flash'    => get_flash(),
    'loggedIn' => true,
    'userName' => $userName,
    'tab'      => $tab,
    'bought'   => $bought,
    'sold'     => $sold,
]);