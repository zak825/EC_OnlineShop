<?php
// ============================================================
// 購入確認画面
//   GET  purchase_confirm.php                   … 購入内容を表示
//   POST purchase_confirm.php (action=confirm)  … 購入を確定
//   POST purchase_confirm.php (action=cancel)   … 購入画面へ戻る
// 設計書: docs/design/購入確認画面_設計処理.md
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
        set_flash('不正な操作、または二重送信です。販売状況を確認してください。', 'error');
        redirect(URL_MAIN);
    }

    $userId    = login_user_id();
    $productId = (int)$purchase['product_id'];
    $now       = date('Y-m-d H:i:s');

    $pdo = db();
    try {
        $stmt = $pdo->prepare('SELECT UserName FROM UserData WHERE Userid = :userId');
        $stmt->execute([':userId' => $userId]);
        $buyer = $stmt->fetch();
        if (!$buyer) {
            throw new RuntimeException('購入者が見つかりません。');
        }

        // 3. トランザクション開始
        $pdo->beginTransaction();

        // 4. 未購入の商品を購入済みにする（自分の出品は購入不可）
        $sql = 'UPDATE Sales
                SET BuyerName    = :buyerName,
                    ShippingFlag = 0,
                    SoldDate     = :soldDate
                WHERE ProductID = :productId
                  AND BuyerName IS NULL
                  AND SoldDate IS NULL
                  AND Userid <> :loginUser';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':buyerName' => $buyer['UserName'],
            ':soldDate'  => $now,
            ':productId' => $productId,
            ':loginUser' => $userId,
        ]);

        if ($stmt->rowCount() !== 1) {
            // 購入失敗（すでに購入済み・自分の出品など）
            $pdo->rollBack();
            set_flash('この商品は購入できません。', 'error');
            redirect('purchase.php?productId=' . $productId);
        }

        // 5. コミット
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[purchase_confirm] ' . $e->getMessage());
        set_flash('購入に失敗しました。', 'error');
        redirect(URL_MAIN);
    }

    // 6. セッションの購入情報を削除
    unset($_SESSION['purchase']);

    // 7. メイン画面へ
    set_flash('購入が完了しました。', 'success');
    redirect(URL_MAIN);
}

// ============================================================
// 画面を開いたとき
// ============================================================

// 1. ログイン済みか確認する
if (!is_logged_in()) {
    set_flash('セッションの有効期限が切れました。再度ログインしてください。', 'error');
    redirect_to_login(); // ログイン後はメイン画面へ
}

// 2. 購入情報の有無を確認する
if ($purchase === null) {
    redirect(URL_MAIN);
}

// 3. 購入情報を表示する
$stmt = db()->prepare(
    'SELECT ProductID, ProductName, Price, BuyerName, SoldDate
     FROM Sales WHERE ProductID = :productId'
);
$stmt->execute([':productId' => $purchase['product_id']]);
$product = $stmt->fetch();

if (!$product) {
    unset($_SESSION['purchase']);
    set_flash('商品が見つかりません。', 'error');
    redirect(URL_MAIN);
}

if ($product['BuyerName'] !== null || $product['SoldDate'] !== null) {
    unset($_SESSION['purchase']);
    set_flash('この商品は売り切れです。', 'error');
    redirect('purchase.php?productId=' . (int)$product['ProductID']);
}

$stmt = db()->prepare(
    'SELECT UserName, ZipCode, Address, TEL FROM UserData WHERE Userid = :userId'
);
$stmt->execute([':userId' => login_user_id()]);
$user = $stmt->fetch();

view('purchase/purchase_confirm', [
    'flash'    => get_flash(),
    'token'    => csrf_token(),
    'product'  => $product,
    'user'     => $user,
    'total'    => (int)$product['Price'],
]);
