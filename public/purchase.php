<?php
// ============================================================
// 購入画面
//   GET  purchase.php?productId=123   … 商品情報を表示
//   POST purchase.php                 … 購入確認画面へ
// 設計書: docs/design/購入画面_設計処理.md
//
// Sales テーブルは「1行 = 1商品（1点もの）」
//   BuyerUserid が NULL … 販売中
//   BuyerUserid が入っている … 売り切れ
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';
// 商品画像のパスを取る関数（販売担当の src/lib/product_image.php）
require_once __DIR__ . '/../src/lib/product_image.php';

// ===== 開発用：ログインをスキップする =====
// 0 以外の Userid を入れると、その人としてログインした扱いになる
//   2 … 購入 花子（購入テスト用）
// ログイン画面ができたら、または提出前には必ず 0 に戻す
$DEV_SKIP_LOGIN_USER_ID = 0;

if ($DEV_SKIP_LOGIN_USER_ID > 0 && !isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $DEV_SKIP_LOGIN_USER_ID;
}
// ===== ここまで =====

// 商品ID（GET でも POST でも受け取る）
$productId = filter_var($_REQUEST['productId'] ?? null, FILTER_VALIDATE_INT);

// ---- 1. ログイン済みか確認する ---------------------------------
if (!is_logged_in()) {
    // 商品IDを戻り先にして、ログイン後この画面に戻す
    set_flash('購入にはログイン、またはアカウント登録が必要です。', 'warning');
    redirect_to_login($productId ? 'purchase.php?productId=' . $productId : null);
}
$userId = login_user_id();

// ---- 2. 商品の存在チェック --------------------------------------
$product = false;
if ($productId) {
    $sql = 'SELECT s.ProductID, s.ProductName, s.Price, s.Userid, s.BuyerUserid,
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

// 商品画像（無ければ空配列）
$imageUrls = product_image_urls((int)$product['ProductID']);

// ---- 3. 売り切れチェック / 4. 出品者チェック ---------------------
$canBuy       = true;
$blockMessage = '';

if ($product['BuyerUserid'] !== null) {
    $canBuy       = false;
    $blockMessage = 'この商品は売り切れです。';
} elseif ((int)$product['Userid'] === $userId) {
    $canBuy       = false;
    $blockMessage = '自分の出品した商品は購入できません。';
}

// ---- 購入ボタン押下 ---------------------------------------------
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf_token($_POST['csrf_token'] ?? null)) {
        $error = '不正な操作です。もう一度やり直してください。';
    } elseif (!$canBuy) {
        // 画面を開いた後に売り切れた場合など
        $error = $blockMessage;
    } else {
        // 購入する商品IDをセッションに書き込んで購入確認画面へ
        $_SESSION['purchase'] = ['product_id' => (int)$product['ProductID']];
        redirect('purchase_confirm.php');
    }
}

// ---- 5. 商品情報を表示 ------------------------------------------
view('purchase', [
    'flash'        => get_flash(),
    'token'        => csrf_token(),
    'product'      => $product,
    'imageUrls'    => $imageUrls,
    'canBuy'       => $canBuy,
    'blockMessage' => $blockMessage,
    'error'        => $error,
]);
