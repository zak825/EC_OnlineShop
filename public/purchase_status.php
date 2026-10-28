<?php
// ============================================================
// 商品の購入可否を JSON で返す（購入画面・購入確認画面の JS が定期的に呼ぶ）
//   GET purchase_status.php?productId=123
//   status … ok（買える） / sold（売り切れ） / own（自分の出品）
//            not_found（商品なし） / logged_out（ログイン切れ）
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function purchase_status_respond(string $status, int $code = 200): void
{
    http_response_code($code);
    echo json_encode(['status' => $status]);
    exit;
}

if (!is_logged_in()) {
    purchase_status_respond('logged_out');
}
$userId = login_user_id();

// 何度も呼ばれるので、セッションのロックを早めに外す（ほかの画面の表示が待たされないように）
session_write_close();

$productId = filter_var($_GET['productId'] ?? null, FILTER_VALIDATE_INT);
if (!$productId) {
    purchase_status_respond('not_found');
}

try {
    $stmt = db()->prepare('SELECT Userid, BuyerUserid FROM Sales WHERE ProductID = :productId');
    $stmt->execute([':productId' => $productId]);
    $row = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[purchase_status] ' . $e->getMessage());
    purchase_status_respond('error', 500);
}

if (!$row) {
    purchase_status_respond('not_found');
}
if ($row['BuyerUserid'] !== null) {
    purchase_status_respond('sold');
}
if ((int)$row['Userid'] === $userId) {
    purchase_status_respond('own');
}
purchase_status_respond('ok');