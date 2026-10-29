<?php
// ============================================================
// 購入確認画面
//   GET  purchase_confirm.php                   … 購入内容を表示
//   POST purchase_confirm.php (action=confirm)  … 購入を確定 → 完了画面
//   POST purchase_confirm.php (action=cancel)   … 購入画面へ戻る
// 設計書: docs/design/購入確認画面_設計処理.md
//
// 購入確定 = Sales の BuyerUserid / BuyerName / ShippingFlag / SoldDate を埋める
// 確定できたら、メイン画面へは戻さず「購入完了」画面を表示する
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';

$purchase = $_SESSION['purchase'] ?? null;

// ============================================================
// ボタン押下時
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // セッションが切れていても使えるよう、商品IDは hidden でも受け取る
    $hiddenProductId = filter_var($_POST['productId'] ?? null, FILTER_VALIDATE_INT);
    $action          = $_POST['action'] ?? '';

    // ---- キャンセル ---------------------------------------------
    if ($action === 'cancel') {
        $id = $purchase['product_id'] ?? $hiddenProductId;
        redirect($id ? 'purchase.php?productId=' . $id : URL_MAIN);
    }

    // ---- 確定 ---------------------------------------------------
    // 1. セッションが有効か確認する
    if (!is_logged_in()) {
        // セッション無効時：購入は確定させず、ログイン後は購入画面からやり直し
        set_flash('セッションの有効期限が切れました。再度ログインしてください。', 'error');
        redirect_to_login($hiddenProductId ? 'purchase.php?productId=' . $hiddenProductId : null);
    }
    if ($purchase === null) {
        // ログイン中だが購入情報がない（購入済みの画面で再送信した等）
        redirect(URL_MAIN);
    }

    // 二重送信・不正送信の対策
    if (!check_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('不正な操作、または二重送信です。購入履歴を確認してください。', 'error');
        redirect(URL_MAIN);
    }

    $userId    = login_user_id();
    $productId = (int)$purchase['product_id'];

    try {
        // 2. 購入情報を登録する
        //    BuyerUserid IS NULL … まだ誰も買っていない（同時購入対策）
        //    Userid <> 自分     … 自分の出品は買えない
        $sql = 'UPDATE Sales
                SET BuyerUserid  = :buyerId,
                    BuyerName    = (SELECT UserName FROM UserData WHERE Userid = :buyerId2),
                    ShippingFlag = 0,
                    SoldDate     = GETDATE()
                WHERE ProductID = :productId
                  AND BuyerUserid IS NULL
                  AND Userid   <> :loginUser';
        $stmt = db()->prepare($sql);
        $stmt->execute([
            ':buyerId'   => $userId,
            ':buyerId2'  => $userId,
            ':productId' => $productId,
            ':loginUser' => $userId,
        ]);

        if ($stmt->rowCount() !== 1) {
            // 他の人に先に買われた・自分の出品など
            unset($_SESSION['purchase']);
            set_flash('この商品は購入できません。（売り切れの可能性があります）', 'error');
            redirect('purchase.php?productId=' . $productId);
        }
    } catch (PDOException $e) {
        error_log('[purchase_confirm] ' . $e->getMessage());
        set_flash('購入に失敗しました。', 'error');
        redirect(URL_MAIN);
    }

    // 3. セッションの購入情報を削除（リロードで二重登録されないように）
    unset($_SESSION['purchase']);

    // 4. 購入完了画面を表示する
    view('purchase_confirm', [
        'done'    => true,
        'flash'   => null,
        'product' => null,
        'user'    => null,
        'token'   => '',
    ]);
    exit;
}

// ============================================================
// 画面を開いたとき
// ============================================================

$requestedProductId = filter_var($_GET['productId'] ?? null, FILTER_VALIDATE_INT);
if (isset($_GET['productId'])) {
    if (!$requestedProductId || $requestedProductId < 1) {
        redirect(URL_MAIN);
    }
    $purchase = ['product_id' => $requestedProductId];
}

// 1. ログイン済みか確認する
if (!is_logged_in()) {
    set_flash('セッションの有効期限が切れました。再度ログインしてください。', 'error');
    redirect_to_login($requestedProductId ? 'purchase_confirm.php?productId=' . $requestedProductId : null);
}

// 2. 購入情報の有無を確認する
if ($purchase === null) {
    redirect(URL_MAIN);
}

// 3. 購入情報を表示する
$stmt = db()->prepare(
    'SELECT ProductID, ProductName, Price, Userid, BuyerUserid FROM Sales WHERE ProductID = :productId'
);
$stmt->execute([':productId' => $purchase['product_id']]);
$product = $stmt->fetch();

if (!$product) {
    unset($_SESSION['purchase']);
    set_flash('商品が見つかりません。', 'error');
    redirect(URL_MAIN);
}

if ($product['BuyerUserid'] !== null || (int)$product['Userid'] === login_user_id()) {
    unset($_SESSION['purchase']);
    set_flash($product['BuyerUserid'] !== null
        ? 'この商品は売り切れです。'
        : '自分の出品した商品は購入できません。', 'warning');
    redirect('purchase.php?productId=' . (int)$product['ProductID']);
}

$_SESSION['purchase'] = ['product_id' => (int)$product['ProductID']];

$stmt = db()->prepare(
    'SELECT UserName, ZipCode, Address, TEL FROM UserData WHERE Userid = :userId'
);
$stmt->execute([':userId' => login_user_id()]);
$user = $stmt->fetch();

view('purchase_confirm', [
    'done'    => false,
    'flash'   => get_flash(),
    'token'   => csrf_token(),
    'product' => $product,
    'user'    => $user,
]);
