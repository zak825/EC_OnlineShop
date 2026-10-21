<?php
// ============================================================
// レビュー画面
//   販売状況確認・発送状況確認画面 ⇄ レビュー画面 の1画面だけで完結する
//
//   GET  review.php?productId=123
//     購入者 かつ 発送済み かつ 未レビュー … 受け取り完了・レビュー記入フォームを表示
//     レビュー済み（購入者・出品者とも）  … 書かれたレビューを表示
//     出品者 かつ 未レビュー             … 「まだレビューはありません」を表示
//   POST review.php
//     評価値・レビューを登録 → 状況確認画面へ
//
// 設計書: docs/design/レビュー画面_設計処理.md
//
// DBに「受け取り完了」の列がないので、
// レビュー（sucre）が入っている = 受け取り完了 として扱う
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';

const REVIEW_MAX_LENGTH = 100; // Sales.revwe の桁数

// 商品ID（GET でも POST でも受け取る）
$productId = filter_var($_REQUEST['productId'] ?? null, FILTER_VALIDATE_INT);

// ---- 1. ログイン済みか確認する ---------------------------------
if (!is_logged_in()) {
    redirect_to_login($productId ? 'review.php?productId=' . $productId : null);
}
$userId = login_user_id();

// ---- 2. 商品の存在チェック --------------------------------------
$product = false;
if ($productId) {
    $sql = 'SELECT s.ProductID, s.ProductName, s.Price, s.Userid, s.BuyerUserid,
                   s.ShippingFlag, s.SoldDate, s.sucre, s.revwe,
                   seller.UserName AS SellerName,
                   buyer.UserName  AS BuyerUserName
            FROM Sales s
            JOIN UserData seller     ON s.Userid    = seller.Userid
            LEFT JOIN UserData buyer ON s.BuyerUserid = buyer.Userid
            WHERE s.ProductID = :productId';
    $stmt = db()->prepare($sql);
    $stmt->execute([':productId' => $productId]);
    $product = $stmt->fetch();
}

if (!$product || $product['BuyerUserid'] === null) {
    // 存在しない、またはまだ売れていない商品
    set_flash('商品が見つかりません。', 'error');
    redirect(URL_STATUS);
}

// ---- 3. 購入者か出品者かを判定する -------------------------------
$isBuyer  = (int)$product['BuyerUserid'] === $userId;
$isSeller = (int)$product['Userid'] === $userId;

if (!$isBuyer && !$isSeller) {
    set_flash('自分が出品・購入した商品のレビューのみ表示できます。', 'error');
    redirect(URL_STATUS);
}

// ---- 4. 表示モードを決める ---------------------------------------
$reviewed = $product['sucre'] !== null;
$shipped  = (int)$product['ShippingFlag'] === 1;

if ($reviewed) {
    $mode = 'view';     // レビュー済み → 表示
} elseif ($isBuyer && $shipped) {
    $mode = 'form';     // 受け取り完了・レビュー記入
} elseif ($isBuyer) {
    // 未発送なのに受け取り完了はできない
    set_flash('まだ発送されていません。発送後に受け取り完了・レビューができます。', 'error');
    redirect(URL_STATUS);
} else {
    $mode = 'waiting';  // 出品者側・まだレビューされていない
}

// ---- 投稿ボタン押下 ---------------------------------------------
$errors = [];
$score  = '';
$review = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode !== 'form') {
        // 画面を開いた後にレビュー済みになった等
        set_flash('この商品はすでにレビュー済みです。', 'error');
        redirect(URL_STATUS);
    }

    $score  = $_POST['score'] ?? '';
    $review = trim($_POST['review'] ?? '');

    if (!check_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = '不正な操作です。もう一度やり直してください。';
    } else {
        // 入力チェック
        $scoreInt = filter_var($score, FILTER_VALIDATE_INT);
        if ($scoreInt === false || $scoreInt < 1 || $scoreInt > 5) {
            $errors[] = '評価を★1〜5から選んでください。';
        }
        if (mb_strlen($review) > REVIEW_MAX_LENGTH) {
            $errors[] = 'レビューは' . REVIEW_MAX_LENGTH . '文字以内で入力してください。';
        }
    }

    if (!$errors) {
        try {
            // レビューを登録する（条件を全部 WHERE に入れて不正・二重投稿を防ぐ）
            $sql = 'UPDATE Sales
                    SET sucre = :score,
                        revwe = :review
                    WHERE ProductID    = :productId
                      AND BuyerUserid  = :buyer
                      AND ShippingFlag = 1
                      AND sucre IS NULL';
            $stmt = db()->prepare($sql);
            $stmt->execute([
                ':score'     => $scoreInt,
                ':review'    => $review === '' ? null : $review,
                ':productId' => (int)$product['ProductID'],
                ':buyer'     => $userId,
            ]);

            if ($stmt->rowCount() !== 1) {
                set_flash('この商品はすでにレビュー済みです。', 'error');
                redirect(URL_STATUS);
            }
        } catch (PDOException $e) {
            error_log('[review] ' . $e->getMessage());
            set_flash('レビューの投稿に失敗しました。', 'error');
            redirect(URL_STATUS);
        }

        set_flash('受け取りが完了しました。レビューを投稿しました。', 'success');
        redirect(URL_STATUS);
    }
}

// ---- 画面を表示 -------------------------------------------------
view('review', [
    'flash'     => get_flash(),
    'token'     => csrf_token(),
    'mode'      => $mode,
    'isBuyer'   => $isBuyer,
    'product'   => $product,
    'errors'    => $errors,
    'score'     => (string)$score,
    'review'    => $review,
    'maxLength' => REVIEW_MAX_LENGTH,
]);