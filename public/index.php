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

// ---- 1. ログインの確認 ---------------------------------------------
// セッションが切れていないか確認する（切れていても一覧は表示する）
$loggedIn = is_logged_in();
$userId   = $loggedIn ? login_user_id() : null;

// ---- 2. DBの参照 ---------------------------------------------------
// 販売中の商品を新しい順に取得する（ジャンル名・出品者名も結合して取る）
// ログイン中は、自分が出品した商品は一覧に出さない
$sql =
    'SELECT s.ProductID, s.ProductName, s.Price, s.Userid,
            g.GenreName,
            u.UserName AS SellerName
     FROM Sales s
     JOIN Genre    g ON s.GenreCode = g.GenreCode
     JOIN UserData u ON s.Userid    = u.Userid
     WHERE s.BuyerUserid IS NULL';

$params = [];
if ($loggedIn) {
    $sql .= ' AND s.Userid <> :me';   // 自分の出品を除く
    $params[':me'] = $userId;
}

$sql .= ' ORDER BY s.ProductID DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();



// ---- 3. 情報表示 ---------------------------------------------------
view('index', [
    'flash'    => get_flash(),     // メッセージ（購入が完了しました 等）
    'loggedIn' => $loggedIn,       // ログイン中か
    'userId'   => $userId,         // ログイン中のユーザーID（自分の出品の判定用）
    'products' => $products,       // 販売中の商品
]);
