<?php
// ============================================================
// 【仮】メイン画面（購入画面の動作確認用）
//   本物のメイン画面は担当の人が作るので、これは push しない
//   置き場所: public/main.php
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';

$flash    = get_flash();
$products = db()->query(
    'SELECT ProductID, ProductName, Price, Userid, BuyerName FROM Sales ORDER BY ProductID'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>仮メイン | CSE_EC</title>
  <link rel="stylesheet" href="assets/css/common.css">
  <link rel="stylesheet" href="assets/css/purchase.css">
</head>
<body>
  <header class="site-header">
    <a href="main.php" class="site-logo">CSE_EC</a>
  </header>

  <main class="container">
    <h1 class="page-title">【仮】メイン画面</h1>

    <?php if ($flash): ?>
      <p class="message message--<?= h($flash['type']) ?>"><?= h($flash['message']) ?></p>
    <?php endif; ?>

    <p>ログイン中のユーザーID：<?= is_logged_in() ? login_user_id() : '（未ログイン）' ?></p>

    <section class="card">
      <h2 class="section-title">商品一覧</h2>
      <table class="summary-table">
        <?php foreach ($products as $p): ?>
          <tr>
            <th>
              <a href="purchase.php?productId=<?= (int)$p['ProductID'] ?>"><?= h($p['ProductName']) ?></a>
            </th>
            <td>
              ¥<?= number_format((int)$p['Price']) ?>
              ／<?= $p['BuyerName'] === null ? '販売中' : '売り切れ' ?>
              <?php if (is_logged_in() && (int)$p['Userid'] === login_user_id()): ?>（自分の出品）<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </section>
  </main>
</body>
</html>