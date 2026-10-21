<?php
// ============================================================
// 販売画面
//   GET  sell.php … 入力フォームを表示
//   POST sell.php … 販売（action=sell）／クリア（action=clear）／戻る（action=back）
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';

// 遷移先
const URL_SELL_CONFIRM = 'sell_confirm.php';

// SystemConfiguration に正しく登録されていないときに使う仮の値
const DEFAULT_PRICE_MIN = 300;
const DEFAULT_PRICE_MAX = 9999999;

const MSG_DB_ERROR   = '処理に失敗しました。時間をおいて再度お試しください';
const MSG_CSRF_ERROR = '不正な操作です。もう一度やり直してください。';

// 前後の空白（全角スペースを含む）を取り除く
// UTF-8 として正しくない文字列は preg_replace が NULL を返すので、未入力扱い（''）にする
function trim_all($value)
{
  $result = preg_replace('/\A[\s\x{3000}]+|[\s\x{3000}]+\z/u', '', (string)$value);
  if ($result === null) {
    return '';
  }
  return $result;
}

// ------------------------------------------------------------
// ★テスト用の自動ログイン
// 自分の src/config/config.php の DEV_LOGIN_USER_ID に UserData の UserId を入れると、
// ログイン画面を通さずにログイン済みになる（0 なら何もしない）
// ------------------------------------------------------------
if (APP_DEBUG && defined('DEV_LOGIN_USER_ID') && DEV_LOGIN_USER_ID > 0) {
  if (!isset($_SESSION['logged_in'])) {
    $_SESSION['logged_in']     = true;
    $_SESSION['user_id']       = DEV_LOGIN_USER_ID;
    $_SESSION['user_name']     = 'テストユーザー';
    $_SESSION['last_activity'] = time();
  }
}

// ------------------------------------------------------------
// ログインチェック
// ------------------------------------------------------------
if (!is_logged_in()) {
  redirect_to_login('sell.php');
}

// ------------------------------------------------------------
// どのボタンが押されたか
// ------------------------------------------------------------
$error_db = '';   // フォームの上に出すエラー（DB エラー・不正な操作）

$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // 画像が大きすぎると（php.ini の post_max_size 超え）$_POST が空になる
  if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $error_db = '画像の合計サイズが大きすぎます。枚数を減らしてください。';
  } elseif (check_csrf_token()) {
    if (isset($_POST['action'])) {
      $action = $_POST['action'];
    }
  } else {
    $error_db = MSG_CSRF_ERROR;
  }
}

// 入力値と一時保存した画像を消す
function clear_sell_input()
{
  unset($_SESSION['sell']);
  if (isset($_SESSION['sell_images'])) {
    delete_product_images_tmp($_SESSION['sell_images']);
    unset($_SESSION['sell_images']);
  }
}

// 戻るボタン：入力値を消してメイン画面へ
if ($action === 'back') {
  clear_sell_input();
  redirect(URL_MAIN);
}

// クリアボタン：入力値を消して空のフォームを表示する
if ($action === 'clear') {
  clear_sell_input();
}

// ------------------------------------------------------------
// DB からジャンル一覧と値段の下限・上限を取得する
// ------------------------------------------------------------
$genres    = [];   // [GenreCode => GenreName]
$price_min = DEFAULT_PRICE_MIN;
$price_max = DEFAULT_PRICE_MAX;

try {
  $pdo = db();

  // ジャンル一覧
  $stmt = $pdo->prepare('SELECT GenreCode, GenreName FROM Genre ORDER BY GenreCode');
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $row) {
    $genres[$row['GenreCode']] = $row['GenreName'];
  }

  // 値段の下限・上限（SystemConfiguration の Category = 'Sales'）
  $setting_min = '';
  $setting_max = '';
  $stmt = $pdo->prepare('SELECT [Key], Value FROM SystemConfiguration WHERE Category = :category');
  $stmt->execute([':category' => 'Sales']);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $row) {
    if ($row['Key'] === 'PriceMin') {
      $setting_min = trim($row['Value']);
    }
    if ($row['Key'] === 'PriceMax') {
      $setting_max = trim($row['Value']);
    }
  }

  // 半角数字のときだけ DB の値を使う
  if (preg_match('/^[0-9]+$/', $setting_min)) {
    $price_min = (int)$setting_min;
  }
  if (preg_match('/^[0-9]+$/', $setting_max)) {
    $price_max = (int)$setting_max;
  }

  // 下限 > 上限 の設定ミスなら両方とも仮の値に戻す
  if ($price_min > $price_max) {
    $price_min = DEFAULT_PRICE_MIN;
    $price_max = DEFAULT_PRICE_MAX;
  }
} catch (PDOException $e) {
  error_log($e->getMessage());
  $error_db = MSG_DB_ERROR;
}

// 項目ごとのエラー（各入力欄の下に表示）
$error_product_name = '';
$error_genre_code   = '';
$error_price        = '';
$error_images       = '';

// ------------------------------------------------------------
// フォームに表示する値
// ------------------------------------------------------------
$form_product_name = '';
$form_genre_code   = '';
$form_price        = '';

// 販売確認画面のキャンセルで戻ってきた場合は、入力値を復元する
if ($action === '' && isset($_SESSION['sell'])) {
  $form_product_name = $_SESSION['sell']['product_name'];
  $form_genre_code   = (string)$_SESSION['sell']['genre_code'];
  $form_price        = (string)$_SESSION['sell']['price'];
}

// ------------------------------------------------------------
// 販売ボタン：入力チェック
// ------------------------------------------------------------
if ($action === 'sell' && $error_db === '') {
  // 入力値を受け取り、前後の空白を取り除く
  if (isset($_POST['product_name'])) {
    $form_product_name = trim_all($_POST['product_name']);
  }
  if (isset($_POST['genre_code'])) {
    $form_genre_code = trim_all($_POST['genre_code']);
  }
  if (isset($_POST['price'])) {
    $form_price = trim_all($_POST['price']);
  }

  // 商品名
  if ($form_product_name === '') {
    $error_product_name = '商品名を入力してください';
  } elseif (mb_strlen($form_product_name, 'UTF-8') > 50) {
    $error_product_name = '商品名は50文字以内で入力してください';
  }

  // ジャンル
  if ($form_genre_code === '') {
    $error_genre_code = 'ジャンルを選択してください';
  } elseif (!isset($genres[$form_genre_code])) {
    $error_genre_code = 'ジャンルが正しくありません';
  }

  // 値段：カンマ（全角含む）を取り除く → 全角数字を半角に変換する
  $price = str_replace(',', '', $form_price);
  $price = str_replace('，', '', $price);
  $price = mb_convert_kana($price, 'n', 'UTF-8');

  if ($price === '') {
    $error_price = '値段を入力してください';
  } elseif (!preg_match('/^[0-9]+$/', $price)) {
    $error_price = '値段は数字で入力してください';
  } else {
    // 先頭の0を除いて11桁以上ある値は int に変換せず範囲外とする
    $price_digits = ltrim($price, '0');
    if (strlen($price_digits) > 10) {
      $is_out_of_range = true;
    } elseif ((int)$price < $price_min) {
      $is_out_of_range = true;
    } elseif ((int)$price > $price_max) {
      $is_out_of_range = true;
    } else {
      $is_out_of_range = false;
    }

    if ($is_out_of_range) {
      $error_price = '値段は' . number_format($price_min) . '円以上' . number_format($price_max) . '円以下で入力してください';
    }
  }

  // 画像（任意・10枚まで）
  // 選ばれたときだけチェックして一時フォルダに保存する。選ばれなかったら前に保存した画像をそのまま使う
  // 他の項目がエラーでも保存しておく（選び直さなくて済むように）
  $files = $_FILES['images'] ?? null;
  if ($files !== null && $files['error'][0] !== UPLOAD_ERR_NO_FILE) {
    $error_images = check_product_images($files);
    if ($error_images === '') {
      if (isset($_SESSION['sell_images'])) {
        delete_product_images_tmp($_SESSION['sell_images']);   // 前に選んだ画像は入れ替える
      }
      $_SESSION['sell_images'] = save_product_images_tmp($files);
    }
  }

  // 4項目ともエラーがなければセッションに保存して販売確認画面へ
  if ($error_product_name === '' && $error_genre_code === '' && $error_price === '' && $error_images === '') {
    $_SESSION['sell'] = [
      'genre_code'   => (int)$form_genre_code,
      'genre_name'   => $genres[$form_genre_code],
      'product_name' => $form_product_name,
      'price'        => (int)$price,
    ];
    redirect(URL_SELL_CONFIRM);
  }
}

// ------------------------------------------------------------
// 画面を表示する（templates/sell.html）
// ------------------------------------------------------------
view('sell', [
  'token'              => csrf_token(),
  'genres'             => $genres,
  'form_product_name'  => $form_product_name,
  'form_genre_code'    => $form_genre_code,
  'form_price'         => $form_price,
  'price_min'          => $price_min,
  'price_max'          => $price_max,
  'error_db'           => $error_db,
  'error_product_name' => $error_product_name,
  'error_genre_code'   => $error_genre_code,
  'error_price'        => $error_price,
  'error_images'       => $error_images,
  'image_urls'         => isset($_SESSION['sell_images']) ? product_image_tmp_urls($_SESSION['sell_images']) : [],
  'image_max'          => PRODUCT_IMAGE_MAX,
]);
