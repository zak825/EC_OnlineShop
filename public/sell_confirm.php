<?php
// ============================================================
// 販売確認画面
//   GET  sell_confirm.php … 販売画面の入力内容を表示
//   POST sell_confirm.php … 販売する（action=sell）／キャンセル（action=cancel）
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';

// 遷移先
const URL_SELL = 'sell.php';

const MSG_DB_ERROR   = '処理に失敗しました。時間をおいて再度お試しください';
const MSG_CSRF_ERROR = '不正な操作です。もう一度やり直してください。';

// ------------------------------------------------------------
// ログインチェック
// ------------------------------------------------------------
if (!is_logged_in()) {
  redirect_to_login('sell.php');
}
$user_id = login_user_id();

// ------------------------------------------------------------
// どのボタンが押されたか
// ------------------------------------------------------------
$error = '';

$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (check_csrf_token()) {
    if (isset($_POST['action'])) {
      $action = $_POST['action'];
    }
  } else {
    $error = MSG_CSRF_ERROR;
  }
}

// キャンセルボタン：入力値（$_SESSION['sell']）を残したまま販売画面へ戻る
if ($action === 'cancel') {
  redirect(URL_SELL);
}

// 販売画面の入力値がない（URL 直打ち・登録後のリロード）→ 販売画面へ
if (!isset($_SESSION['sell'])) {
  redirect(URL_SELL);
}

$sell = $_SESSION['sell'];
$done = false;   // 登録が完了したら true

// ------------------------------------------------------------
// 販売するボタン：Sales に登録する
// （$_POST ではなく $_SESSION['sell'] の値を使う）
// ------------------------------------------------------------
if ($action === 'sell') {
  $pdo = null;
  try {
    $pdo = db();

    // ProductID は自動採番ではないので「今ある最大値 + 1」を使う
    // 同時に販売されても同じ番号にならないよう、トランザクション内でロックしてから取る
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT ISNULL(MAX(ProductID), 0) + 1 FROM Sales WITH (UPDLOCK, HOLDLOCK)');
    $stmt->execute();
    $product_id = (int)$stmt->fetchColumn();

    $sql = 'INSERT INTO Sales (ProductID, GenreCode, ProductName, Price, Userid, ShippingFlag, InsertUserId, InsertDateTime) '
      . 'VALUES (:product_id, :genre_code, :product_name, :price, :userid, 0, :insert_userid, GETDATE())';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':product_id'    => $product_id,
      ':genre_code'    => $sell['genre_code'],
      ':product_name'  => $sell['product_name'],
      ':price'         => $sell['price'],
      ':userid'        => $user_id,
      ':insert_userid' => $user_id,
    ]);

    $pdo->commit();

    // 登録できたら入力値を消す（リロードしても二重登録されない）
    unset($_SESSION['sell']);
    $done = true;
  } catch (PDOException $e) {
    // 失敗したら入力値は残したまま、この画面にとどまる
    if ($pdo !== null && $pdo->inTransaction()) {
      $pdo->rollBack();
    }
    error_log($e->getMessage());
    $error = MSG_DB_ERROR;
  }
}

// ------------------------------------------------------------
// 画面を表示する（templates/sell_confirm.html）
// ------------------------------------------------------------
view('sell_confirm', [
  'token' => csrf_token(),
  'sell'  => $sell,
  'done'  => $done,
  'error' => $error,
]);
