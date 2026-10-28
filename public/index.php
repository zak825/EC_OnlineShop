<?php
// ============================================================
// メイン画面
//   GET  index.php                              … 販売中の商品を一覧で表示
//   GET  index.php?q=マウス&genre=2              … 商品名であいまい検索＋ジャンル絞り込み
//   GET  index.php?seller=山田&rating=4&sort=price_asc
//                                               … 出品者名・出品者評価での絞り込み、並び替え
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';
require_once SRC_DIR . '/lib/product_image.php';

if (!defined('URL_SELL')) {
    define('URL_SELL', 'sell.php');
}

// 出品者評価の絞り込み（キーは URL の rating の値）
const RATING_OPTIONS = [
    '5'    => '★5',
    '4'    => '★4以上',
    '3'    => '★3以上',
    '2'    => '★2以上',
    '1'    => '★1以上',
    'none' => '評価なし',
];

// 並び順（キーは URL の sort の値。order は SQL の ORDER BY に入れるので、ここに書いたものしか使われない）
// 評価なし（NULL）は、評価順ではどちらの向きでも最後にする
const SORT_OPTIONS = [
    'new'         => ['label' => '新着順',             'order' => 's.ProductID DESC'],
    'price_asc'   => ['label' => '値段が安い順',       'order' => 's.Price ASC, s.ProductID DESC'],
    'price_desc'  => ['label' => '値段が高い順',       'order' => 's.Price DESC, s.ProductID DESC'],
    'rating_desc' => ['label' => '出品者評価が高い順', 'order' => 'CASE WHEN seller_rating.AvgScore IS NULL THEN 1 ELSE 0 END, seller_rating.AvgScore DESC, s.ProductID DESC'],
    'rating_asc'  => ['label' => '出品者評価が低い順', 'order' => 'CASE WHEN seller_rating.AvgScore IS NULL THEN 1 ELSE 0 END, seller_rating.AvgScore ASC, s.ProductID DESC'],
];

/** GET の値を文字列で受け取る（配列などが来たら空文字にする）。前後の空白は除く */
function get_text(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/** LIKE のワイルドカード（% _ [）をエスケープして「ただの文字」にする（ESCAPE '!' とセットで使う） */
function like_escape(string $text): string
{
    return str_replace(['!', '%', '_', '['], ['!!', '!%', '!_', '!['], $text);
}

// ---- 1. ログインの確認 ---------------------------------------------
$loggedIn = is_logged_in();
$userId   = $loggedIn ? login_user_id() : null;

// ---- 絞り込み・並び替えの条件 --------------------------------------
$stmt = db()->query('SELECT GenreCode, GenreName FROM Genre ORDER BY GenreCode');
$genres = $stmt->fetchAll();

$genreFilter = filter_input(INPUT_GET, 'genre', FILTER_VALIDATE_INT);
if ($genreFilter === false || $genreFilter === 0) {
    $genreFilter = null;
}

$keyword = get_text('q');        // 商品名
$seller  = get_text('seller');   // 出品者名

$ratingFilter = get_text('rating');
if (!isset(RATING_OPTIONS[$ratingFilter])) {
    $ratingFilter = '';
}

$sort = get_text('sort');
if (!isset(SORT_OPTIONS[$sort])) {
    $sort = 'new';
}

// ---- 2. DBの参照 ---------------------------------------------------
$hasProductDetails = db_has_column('Sales', 'ProductCondition')
    && db_has_column('Sales', 'Description');
$productDetailSelect = $hasProductDetails
    ? 's.ProductCondition, s.Description,'
    : 'CAST(NULL AS NVARCHAR(30)) AS ProductCondition,
       CAST(NULL AS NVARCHAR(1000)) AS Description,';
$sql =
    'SELECT s.ProductID, s.ProductName, s.Price, ' . $productDetailSelect . ' s.Userid,
            g.GenreName,
            u.UserName AS SellerName,
            seller_rating.AvgScore AS SellerAvgScore
     FROM Sales s
     JOIN Genre    g ON s.GenreCode = g.GenreCode
     JOIN UserData u ON s.Userid    = u.Userid
     LEFT JOIN (
         SELECT Userid, AVG(CAST(sucre AS FLOAT)) AS AvgScore
         FROM Sales
         WHERE sucre IS NOT NULL
         GROUP BY Userid
     ) seller_rating ON seller_rating.Userid = s.Userid
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
    // あいまい検索（部分一致）
    $sql .= " AND s.ProductName LIKE :q ESCAPE '!'";
    $params[':q'] = '%' . like_escape($keyword) . '%';
}

if ($seller !== '') {
    // 出品者名もあいまい検索（部分一致）
    $sql .= " AND u.UserName LIKE :seller ESCAPE '!'";
    $params[':seller'] = '%' . like_escape($seller) . '%';
}

if ($ratingFilter === 'none') {
    $sql .= ' AND seller_rating.AvgScore IS NULL';   // 評価がまだ無い出品者
} elseif ($ratingFilter !== '') {
    // 画面の星（四捨五入した値）と同じ基準で「N以上」にする
    $sql .= ' AND ROUND(seller_rating.AvgScore, 0) >= :rating';
    $params[':rating'] = (int)$ratingFilter;
}

// 並び順（SORT_OPTIONS の中に無い値は上で 'new' にしてあるので、SQL に入るのは決まった文字列だけ）
$sql .= ' ORDER BY ' . SORT_OPTIONS[$sort]['order'];

$stmt = db()->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

foreach ($products as &$item) {
    $urls = product_image_urls((int)$item['ProductID']);
    $item['image_url'] = $urls[0] ?? null;
}
unset($item);

// ---- 検索結果の見出し用：指定中の条件の一覧 -------------------------
$conditions = [];
if ($keyword !== '') {
    $conditions[] = '商品名「' . $keyword . '」';
}
if ($seller !== '') {
    $conditions[] = '出品者「' . $seller . '」';
}
if ($genreFilter !== null) {
    foreach ($genres as $g) {
        if ((int)$g['GenreCode'] === $genreFilter) {
            $conditions[] = 'ジャンル「' . $g['GenreName'] . '」';
            break;
        }
    }
}
if ($ratingFilter !== '') {
    $conditions[] = '出品者評価 ' . RATING_OPTIONS[$ratingFilter];
}

// ---- 3. 情報表示 ---------------------------------------------------
view('index', [
    'flash'         => get_flash(),
    'loggedIn'      => $loggedIn,
    'userId'        => $userId,
    'products'      => $products,
    'genres'        => $genres,
    'genreFilter'   => $genreFilter,
    'keyword'       => $keyword,          // 検索ボックスに残す
    'seller'        => $seller,
    'ratingFilter'  => $ratingFilter,
    'ratingOptions' => RATING_OPTIONS,
    'sort'          => $sort,
    'sortOptions'   => SORT_OPTIONS,
    'conditions'    => $conditions,
]);