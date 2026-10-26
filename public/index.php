<?php
// ============================================================
// メイン画面
//   GET  index.php                      … 販売中の商品を一覧で表示
//   GET  index.php?q=マウス&genre=2      … 商品名であいまい検索＋ジャンル絞り込み
//   POST index.php (action=logout)      … ログアウトしてログイン画面へ
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';
require_once SRC_DIR . '/lib/product_image.php';

if (!defined('URL_SELL')) {
    define('URL_SELL', 'sell.php');
}

// ---- 1. ログインの確認 ---------------------------------------------
$loggedIn = is_logged_in();
$userId   = $loggedIn ? login_user_id() : null;

// ---- 絞り込み条件（ジャンル・検索キーワード） ----------------------
$stmt = db()->query('SELECT GenreCode, GenreName FROM Genre ORDER BY GenreCode');
$genres = $stmt->fetchAll();

$genreFilter = filter_input(INPUT_GET, 'genre', FILTER_VALIDATE_INT);
if ($genreFilter === false || $genreFilter === 0) {
    $genreFilter = null;
}

// 検索キーワード（前後の空白を除く）
$keyword = trim($_GET['q'] ?? '');

// ---- 2. DBの参照 ---------------------------------------------------
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

if ($keyword !== '') {
    // あいまい検索（部分一致）。LIKE のワイルドカード（% _ [）はエスケープして「ただの文字」として扱う
    $escaped = str_replace(['!', '%', '_', '['], ['!!', '!%', '!_', '!['], $keyword);
    $sql .= " AND s.ProductName LIKE :q ESCAPE '!'";
    $params[':q'] = '%' . $escaped . '%';
}

$sql .= ' ORDER BY s.ProductID DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

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
    'genres'      => $genres,
    'genreFilter' => $genreFilter,
    'keyword'     => $keyword,       // 検索ボックスに残す
]);
