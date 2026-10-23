<?php
// ============================================================
// 発送通知送信画面
//   POST ship_notice.php … マイページの発送ボタン（action=open）
//                          はい（action=ship）／キャンセル（action=cancel）
//   マイページのフォームには product_id と csrf_token を hidden で入れる
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';

const MSG_DB_ERROR   = '処理に失敗しました。時間をおいて再度お試しください';
const MSG_NOT_UPDATE = '発送済みにできませんでした';

// ------------------------------------------------------------
// ログインチェック
// ------------------------------------------------------------
if (!is_logged_in()) {
  redirect_to_login(URL_STATUS);
}
$user_id = login_user_id();

// ------------------------------------------------------------
// 受け取る値
// ------------------------------------------------------------
$action = '';
if (isset($_POST['action'])) {
  $action = $_POST['action'];
}

$product_id = '';
if (isset($_POST['product_id'])) {
  $product_id = (string)$_POST['product_id'];
}

// キャンセルボタン：何も更新せずマイページへ
if ($action === 'cancel') {
  redirect(URL_STATUS);
}

// 発送ボタン（open）・はいボタン（ship）以外 → マイページへ
if ($action !== 'open' && $action !== 'ship') {
  redirect(URL_STATUS);
}

// 不正な操作（CSRF トークンが違う）→ マイページへ
if (!check_csrf_token()) {
  set_flash('不正な操作です。もう一度やり直してください。', 'error');
  redirect(URL_STATUS);
}

// product_id が数字だけでない → マイページへ
if (!preg_match('/^[0-9]{1,9}$/', $product_id)) {
  redirect(URL_STATUS);
}

$error   = '';
$product = null;   // 画面に出す商品と配送先（購入者）の情報

try {
  $pdo = db();

  // ----------------------------------------------------------
  // 発送ボタン（マイページから来たとき）：確認画面を出してよいか調べる
  // ----------------------------------------------------------
  if ($action === 'open') {
    $sql = 'SELECT s.Userid, s.BuyerUserid, s.ShippingFlag, s.ProductName, s.Price, s.SoldDate, '
      . 'u.UserName AS BuyerUserName, u.ZipCode, u.Address, u.TEL '
      . 'FROM Sales s '
      . 'LEFT JOIN UserData u ON u.Userid = s.BuyerUserid '
      . 'WHERE s.ProductID = :product_id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':product_id' => (int)$product_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // 商品がない
    if ($row === false) {
      redirect(URL_STATUS);
    }
    // 他人の商品
    if ((int)$row['Userid'] !== $user_id) {
      redirect(URL_STATUS);
    }
    // 未購入の商品は発送できない
    if ($row['BuyerUserid'] === null) {
      redirect(URL_STATUS);
    }
    // 発送済み
    if ((int)$row['ShippingFlag'] === 1) {
      redirect(URL_STATUS);
    }

    $product = $row;
  }

  // ----------------------------------------------------------
  // はいボタン：発送済みにする
  // チェックは更新条件にまとめる（hidden の書き換え・連打対策）
  // ----------------------------------------------------------
  if ($action === 'ship') {
    $sql = 'UPDATE Sales '
      . 'SET ShippingFlag = 1 '
      . 'WHERE ProductID = :product_id '
      . 'AND Userid = :userid '
      . 'AND BuyerUserid IS NOT NULL '
      . 'AND (ShippingFlag = 0 OR ShippingFlag IS NULL)';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':product_id'    => (int)$product_id,
      ':userid'        => $user_id,
    ]);

    if ($stmt->rowCount() === 1) {
      // 更新できた → マイページへ
      redirect(URL_STATUS);
    } else {
      // 更新できなかった（他人の商品・未購入・発送済みなど）
      $error = MSG_NOT_UPDATE;
    }
  }
} catch (PDOException $e) {
  error_log($e->getMessage());
  $error = MSG_DB_ERROR;
}

// ------------------------------------------------------------
// 画面を表示する（templates/ship_notice.html）
// ------------------------------------------------------------
view('ship_notice', [
  'token'      => csrf_token(),
  'product_id' => $product_id,
  'product'    => $product,
  'error'      => $error,
]);
