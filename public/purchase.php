<?php
// ============================================================
// 購入画面
//   GET  purchase.php?productId=123   … 商品情報を表示
//   POST purchase.php                 … 購入可能か確認 → 購入確認画面へ
// 設計書: docs/design/購入画面_設計処理.md
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';

// 商品ID（GET でも POST でも受け取る）
$productId = filter_var($_REQUEST['productId'] ?? null, FILTER_VALIDATE_INT);

// ---- 1. ログイン済みか確認する ---------------------------------
if (!is_logged_in()) {
    // 商品IDを戻り先にして、ログイン後この画面に戻す
    redirect_to_login($productId ? 'purchase.php?productId=' . $productId : null);
}
$userId = login_user_id();

// ---- 2. 商品の存在チェック --------------------------------------
$product = false;
if ($productId) {
    $sql = 'SELECT s.ProductID, s.ProductName, s.Price, s.Userid,
                   s.BuyerName, s.SoldDate,
                   g.GenreName, u.UserName AS SellerName
            FROM Sales s
            JOIN Genre    g ON s.GenreCode = g.GenreCode
            JOIN UserData u ON s.Userid    = u.Userid
            WHERE s.ProductID = :productId';
    $stmt = db()->prepare($sql);
    $stmt->execute([':productId' => $productId]);
    $product = $stmt->fetch();
}

if (!$product) {
    set_flash('商品が見つかりません。', 'error');
    redirect(URL_MAIN);
}

// ---- 3. 販売状況チェック / 4. 出品者チェック ---------------------
$canBuy       = true;
$blockMessage = '';

if ($product['BuyerName'] !== null || $product['SoldDate'] !== null) {
    $canBuy       = false;
    $blockMessage = 'この商品は売り切れです。';
} elseif ((int)$product['Userid'] === $userId) {
    $canBuy       = false;
    $blockMessage = '自分の出品した商品は購入できません。';
}

// ---- 購入ボタン押下 ---------------------------------------------
$error    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = '不正な操作です。もう一度やり直してください。';
    } elseif (!$canBuy) {
        // 画面を開いた後に売り切れた場合など
        $error = $blockMessage;
    } else {
        $_SESSION['purchase'] = [
            'product_id' => (int)$product['ProductID'],
        ];
        redirect('purchase_confirm.php');
    }
}

// ---- 5. 商品情報を表示 ------------------------------------------
view('purchase/purchase', [
    'flash'        => get_flash(),
    'token'        => csrf_token(),
    'product'      => $product,
    'canBuy'       => $canBuy,
    'blockMessage' => $blockMessage,
    'error'        => $error,
]);
