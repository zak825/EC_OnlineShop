<?php
// ============================================================
// マイページ画面
//   GET mypage.php            … 購入した商品の一覧
//   GET mypage.php?tab=sold   … 出品した商品の一覧
// 設計書: 詳細設計_Rev.1.0.xlsx「マイページ画面_設計処理」
//
// Sales テーブルの使い方
//   出品者 … Sales.Userid
//   購入者 … Sales.BuyerUserid（BuyerName は表示用の購入者名）
//   発送   … Sales.ShippingFlag（0:未発送 1:発送済）
//   取引完了 … Sales.sucre（レビューの評価値）が入っている
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';

// 商品画像のパスを取る関数（販売担当の src/lib/product_image.php）
require_once SRC_DIR . '/lib/product_image.php';

// 発送通知送信画面のファイル名（config.php に書いていなければこの名前を使う）
if (!defined('URL_SHIPPING')) {
    define('URL_SHIPPING', 'ship_notice.php');
}

$tab = ($_GET['tab'] ?? '') === 'sold' ? 'sold' : 'bought';

// ---- 1. ログイン確認 ---------------------------------------------
// 未ログインのときはログイン画面へ飛ばさず、ログインを勧めるメッセージとボタンを表示する
if (!is_logged_in()) {
    view('mypage', [
        'flash'    => get_flash(),
        'loggedIn' => false,
        'tab'      => $tab,
    ]);
    exit;
}
$userId = login_user_id();

// ---- プロフィール画像のアップロード（「アイコンを変更」ボタン） ----------
const ICON_MAX_BYTES = 2 * 1024 * 1024;                  // 2MB まで
const ICON_DIR       = __DIR__ . '/uploads/icons';        // 保存先フォルダ
const ICON_TYPES     = [                                  // 受け付ける画像の種類 => 拡張子
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_GIF  => 'gif',
    IMAGETYPE_WEBP => 'webp',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ファイルが大きすぎると（php.ini の post_max_size 超え）$_POST が空になる
    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        set_flash('画像のサイズは2MBまでです。', 'error');
        redirect('mypage.php');
    }

    if (($_POST['action'] ?? '') === 'upload_icon') {
        if (!check_csrf_token($_POST['csrf_token'] ?? null)) {
            set_flash('不正な操作です。もう一度やり直してください。', 'error');
            redirect('mypage.php');
        }

        $file  = $_FILES['icon'] ?? null;
        $error = '';
        $info  = false;

        // 入力チェック
        if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $error = '画像を選択してください。';
        } elseif (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                  || $file['size'] > ICON_MAX_BYTES) {
            $error = '画像のサイズは2MBまでです。';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $error = '画像のアップロードに失敗しました。';
        } else {
            // 拡張子ではなく中身で画像か判定する（画像に見せかけたファイル対策）
            $info = @getimagesize($file['tmp_name']);
            if ($info === false || !isset(ICON_TYPES[$info[2]])) {
                $error = 'JPEG・PNG・GIF・WebP の画像を選択してください。';
            }
        }

        if ($error !== '') {
            set_flash($error, 'error');
            redirect('mypage.php');
        }

        // ファイル名はランダムにする（元のファイル名は使わない）
        if (!is_dir(ICON_DIR)) {
            mkdir(ICON_DIR, 0777, true);
        }
        $fileName = 'user' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . ICON_TYPES[$info[2]];
        if (!move_uploaded_file($file['tmp_name'], ICON_DIR . '/' . $fileName)) {
            set_flash('画像の保存に失敗しました。', 'error');
            redirect('mypage.php');
        }

        // 古いアイコンのパスを取っておく（更新できたら削除する）
        $stmt = db()->prepare('SELECT IconPath FROM UserData WHERE Userid = :userId');
        $stmt->execute([':userId' => $userId]);
        $oldPath = $stmt->fetchColumn();

        // DB の IconPath を新しい画像に更新
        $stmt = db()->prepare('UPDATE UserData SET IconPath = :path WHERE Userid = :userId');
        $stmt->execute([':path' => 'uploads/icons/' . $fileName, ':userId' => $userId]);

        // 古いアイコンを削除（uploads/icons の中のファイルだけ）
        if ($oldPath) {
            $oldFile = ICON_DIR . '/' . basename($oldPath);
            if (is_file($oldFile)) {
                unlink($oldFile);
            }
        }

        set_flash('', 'success');
        redirect('mypage.php');
    }
}

// ---- プロフィール（名前・アイコン）を取得 ------------------------
$stmt = db()->prepare('SELECT UserName, IconPath FROM UserData WHERE Userid = :userId');
$stmt->execute([':userId' => $userId]);
$profile  = $stmt->fetch() ?: [];
$userName = $profile['UserName'] ?? '';
$iconPath = $profile['IconPath'] ?? null;   // 未設定（NULL）のときは名前の頭文字を表示する

// ---- 評価の平均（自分が出品して、購入者からレビューされた商品） ------
$stmt = db()->prepare(
    'SELECT AVG(CAST(sucre AS FLOAT)) AS AvgScore, COUNT(sucre) AS ReviewCount
     FROM Sales
     WHERE Userid = :seller AND sucre IS NOT NULL'
);
$stmt->execute([':seller' => $userId]);
$rating      = $stmt->fetch();
$reviewCount = (int)$rating['ReviewCount'];
$avgScore    = $reviewCount > 0 ? round((float)$rating['AvgScore'], 1) : null;  // 小数第1位まで

// ---- 2. 購入情報参照（購入者が自分の商品） ------------------------
$stmt = db()->prepare(
    'SELECT s.ProductID, s.ProductName, s.Price, s.SoldDate, s.ShippingFlag,
            s.sucre, s.revwe,
            seller.UserName AS SellerName
     FROM Sales s
     JOIN UserData seller ON s.Userid = seller.Userid
     WHERE s.BuyerUserid = :buyer
     ORDER BY s.SoldDate DESC'
);
$stmt->execute([':buyer' => $userId]);
$bought = $stmt->fetchAll();

// 各商品の1枚目の画像を取る（無ければ null）
foreach ($bought as &$item) {
    $urls = product_image_urls((int)$item['ProductID']);
    $item['image_url'] = $urls[0] ?? null;
}
unset($item);

// ---- 3. 出品情報参照（出品者が自分の商品） ------------------------
// 購入者名・配送先は、BuyerUserid で UserData と結合して取る
$stmt = db()->prepare(
    'SELECT s.ProductID, s.ProductName, s.Price, s.BuyerUserid, s.SoldDate, s.ShippingFlag,
            s.sucre, s.revwe,
            buyer.UserName AS BuyerUserName, buyer.ZipCode, buyer.Address, buyer.TEL
     FROM Sales s
     LEFT JOIN UserData buyer ON s.BuyerUserid = buyer.Userid
     WHERE s.Userid = :seller
     ORDER BY s.ProductID'   // 出品した順（ProductID は出品するたびに 1 ずつ増える）
);
$stmt->execute([':seller' => $userId]);
$soldAll = $stmt->fetchAll();

// 出品した商品のステータス（プルダウンの絞り込み・背景色に使う）
const SOLD_STATUSES = [
    'selling'   => '販売中',
    'unshipped' => '未発送',
    'shipped'   => '発送済み',
    'done'      => '取引完了',
];

/** 出品した商品のステータスを返す（SOLD_STATUSES のキー） */
function sold_status(array $item): string
{
    if ($item['BuyerUserid'] === null) {
        return 'selling';     // まだ売れていない
    }
    if ($item['sucre'] !== null) {
        return 'done';        // 購入者がレビューした
    }
    if ((int)$item['ShippingFlag'] === 1) {
        return 'shipped';     // 発送したが、まだレビューされていない
    }
    return 'unshipped';       // 売れたが、まだ発送していない
}

// プルダウンで選ばれたステータス（'' = すべて）
$soldFilter = $_GET['status'] ?? '';
if (!isset(SOLD_STATUSES[$soldFilter])) {
    $soldFilter = '';
}

// ステータスごとの件数（プルダウンに出す）
$soldCounts = array_fill_keys(array_keys(SOLD_STATUSES), 0);

$sold = [];
foreach ($soldAll as $item) {
    $item['status'] = sold_status($item);
    $soldCounts[$item['status']]++;

    if ($soldFilter !== '' && $item['status'] !== $soldFilter) {
        continue;
    }
    $urls = product_image_urls((int)$item['ProductID']);
    $item['image_url'] = $urls[0] ?? null;
    $sold[] = $item;
}

// ---- 4. 情報表示 -------------------------------------------------
view('mypage', [
    'flash'       => get_flash(),      // メッセージ（アイコンを変更しました 等）
    'loggedIn'    => true,             // ログイン済み
    'userName'    => $userName,        // ユーザー名
    'iconPath'    => $iconPath,        // アイコン画像のパス（NULL = 未設定）
    'avgScore'    => $avgScore,        // 評価の平均（NULL = 評価なし）
    'reviewCount' => $reviewCount,     // 評価の件数
    'tab'         => $tab,             // 表示中のタブ（bought / sold）
    'bought'      => $bought,          // 購入した商品
    'sold'        => $sold,            // 出品した商品（プルダウンで絞り込んだあと）
    'soldTotal'   => count($soldAll),  // 出品した商品の件数（絞り込む前）
    'soldFilter'  => $soldFilter,      // 選ばれたステータス（'' = すべて）
    'soldCounts'  => $soldCounts,      // ステータスごとの件数
    'soldStatuses' => SOLD_STATUSES,   // ステータスの一覧（キー => 表示名）
    'token'       => csrf_token(),     // 発送ボタン・アイコン変更用
]);