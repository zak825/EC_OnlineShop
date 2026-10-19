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
  try {
    $sql = 'INSERT INTO Sales (GenreCode, ProductName, Price, Userid, ShippingFlag, InsertUserId, InsertDateTime) '
      . 'VALUES (:genre_code, :product_name, :price, :userid, 0, :insert_userid, GETDATE())';
    $stmt = db()->prepare($sql);
    $stmt->execute([
      ':genre_code'    => $sell['genre_code'],
      ':product_name'  => $sell['product_name'],
      ':price'         => $sell['price'],
      ':userid'        => $user_id,
      ':insert_userid' => $user_id,
    ]);

    // 登録できたら入力値を消す（リロードしても二重登録されない）
    unset($_SESSION['sell']);
    $done = true;
  } catch (PDOException $e) {
    // 失敗したら入力値は残したまま、この画面にとどまる
    error_log($e->getMessage());
    $error = MSG_DB_ERROR;
  }
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>販売確認画面</title>
  <style>
    .error {
      color: #d00;
      margin: 4px 0;
    }
  </style>
</head>

<body>
  <main>
    <h1>販売確認画面</h1>

    <?php if ($error !== ''): ?>
      <p class="error"><?= h($error) ?></p>
    <?php endif; ?>

    <p><?= h($sell['product_name']) ?> <?= h(number_format($sell['price'])) ?>円 で販売しますか？</p>
    <p>ジャンル：<?= h($sell['genre_name']) ?></p>

    <?php if ($done): ?>
      <!-- 登録完了：ボタンは出さず、3秒後にメイン画面へ移動する -->
      <p>出品が完了しました</p>
      <p><a href="<?= h(URL_MAIN) ?>">移動しない場合はこちら</a></p>
      <script>
        setTimeout(function() {
          location.href = '<?= h(URL_MAIN) ?>';
        }, 3000);
      </script>
    <?php else: ?>
      <form action="sell_confirm.php" method="post" id="confirm_form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <button type="submit" name="action" value="sell" id="sell_button">販売する</button>
        <button type="submit" name="action" value="cancel">キャンセル</button>
      </form>
      <script>
        // 二重送信対策：販売するボタンを押したらボタンを押せなくする
        // disabled にしたボタンの値は送信されないので、hidden で action=sell を追加してから送信する
        var sellButton = document.getElementById('sell_button');
        sellButton.addEventListener('click', function(event) {
          event.preventDefault();

          var form = document.getElementById('confirm_form');
          var hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = 'action';
          hidden.value = 'sell';
          form.appendChild(hidden);

          sellButton.disabled = true;
          form.submit();
        });
      </script>
    <?php endif; ?>
  </main>
</body>

</html>
