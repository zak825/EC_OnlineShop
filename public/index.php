<?php
// ============================================================
// メイン画面
//   GET  index.php                  … 販売中の商品を一覧で表示
//   GET  index.php?genre=3          … ジャンルで絞り込み
//   POST index.php (action=logout)  … ログアウトしてログイン画面へ
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';

// 商品画像のパスを取る関数（販売担当の src/lib/product_image.php）
require_once SRC_DIR . '/lib/product_image.php';

// 販売画面のファイル名（config.php に書いていなければこの名前を使う）
if (!defined('URL_SELL')) {
    define('URL_SELL', 'sell.php');
}

// ---- 1. ログインの確認 ---------------------------------------------
$loggedIn = is_logged_in();
$userId   = $loggedIn ? login_user_id() : null;

// ---- 絞り込み用のジャンル一覧（プルダウンに出す） ------------------
$stmt = db()->query('SELECT GenreCode, GenreName FROM Genre ORDER BY GenreCode');
$genres = $stmt->fetchAll();

// 選ばれたジャンル（未選択や不正値なら null＝すべて表示）
$genreFilter = filter_input(INPUT_GET, 'genre', FILTER_VALIDATE_INT);
if ($genreFilter === false || $genreFilter === 0) {
    $genreFilter = null;
}

// ---- 2. DBの参照 ---------------------------------------------------
// 販売中の商品を新しい順に取得する（ジャンルが選ばれていれば絞り込む）
$sql =
    'SELECT s.ProductID, s.ProductName, s.Price, s.Userid,
            g.GenreName,
            u.UserName AS SellerName
     FROM Sales s
     JOIN Genre    g ON s.GenreCode = g.GenreCode
     JOIN UserData u ON s.Userid    = u.Userid
     WHERE s.BuyerUserid IS NULL';

$params = [];
if ($loggedIn) {
    $sql .= ' AND s.Userid <> :me';   // 自分の出品を除く
    $params[':me'] = $userId;
}
if ($genreFilter !== null) {
    $sql .= ' AND s.GenreCode = :genre';   // ジャンルで絞り込む
    $params[':genre'] = $genreFilter;
}

$sql .= ' ORDER BY s.ProductID DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// 各商品の1枚目の画像をサムネイルに使う（無ければ null）
foreach ($products as &$item) {
    $urls = product_image_urls((int)$item['ProductID']);
    $item['image_url'] = $urls[0] ?? null;
}
unset($item);

// ---- 3. 情報表示 ---------------------------------------------------
view('index', [
    'flash'       => get_flash(),
    'loggedIn'    => $loggedIn,
    'userId'      => $userId,
    'products'    => $products,
    'genres'      => $genres,        // プルダウンの選択肢
    'genreFilter' => $genreFilter,   // 今選ばれているジャンル
]);
