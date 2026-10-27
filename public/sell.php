<?php
// ============================================================
// 販売画面
//   GET  sell.php … 入力フォームを表示
//   POST sell.php … 販売（action=sell）／クリア（action=clear）／戻る（action=back）
//                   下書き保存して戻る（action=save_draft）／下書き保存しないで戻る（action=back_no_save）
//                   下書きを使う（action=load_draft）／下書きを使わない（action=discard_draft）
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';
require_once SRC_DIR . '/lib/sell_draft.php';

// 遷移先
const URL_SELL_CONFIRM = 'sell_confirm.php';

// SystemConfiguration に正しく登録されていないときに使う仮の値
const DEFAULT_PRICE_MIN = 300;
const DEFAULT_PRICE_MAX = 9999999;
const DEFAULT_PRODUCT_NAME_MAX = 50;

// 商品名の列（Sales.ProductName NVARCHAR(50)）に入る最大文字数
// SystemConfiguration の設定がこれより大きくても、これを超えないようにする（超えると登録時に DB エラーになる）
const PRODUCT_NAME_COLUMN_MAX = 50;

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
$user_id = login_user_id();

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
  unset($_SESSION['sell'], $_SESSION['sell_draft_loaded']);
  if (isset($_SESSION['sell_images'])) {
    delete_product_images_tmp($_SESSION['sell_images']);
    unset($_SESSION['sell_images']);
  }
}

// 下書き保存のモーダルで「保存しない」：下書きを使って入力していたなら、その下書きも削除する
// （「使う」を選んだ下書きを保存し直さなかった＝もう要らない、と考える）
if ($action === 'back_no_save') {
  if (!empty($_SESSION['sell_draft_loaded'])) {
    try {
      delete_sell_draft($user_id);
    } catch (PDOException $e) {
      error_log($e->getMessage());
    }
  }
  $action = 'back';   // あとは「戻る」と同じ
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

// 下書きのモーダルで「いいえ」：下書きを削除して空のフォームを表示する
if ($action === 'discard_draft') {
  try {
    delete_sell_draft($user_id);
  } catch (PDOException $e) {
    error_log($e->getMessage());
    $error_db = MSG_DB_ERROR;
  }
  clear_sell_input();
}

// ------------------------------------------------------------
// DB からジャンル一覧と、値段の下限・上限、商品名の最大文字数を取得する
// ------------------------------------------------------------
$genres    = [];   // [GenreCode => GenreName]
$price_min = DEFAULT_PRICE_MIN;
$price_max = DEFAULT_PRICE_MAX;
$name_max  = DEFAULT_PRODUCT_NAME_MAX;

try {
  $pdo = db();

  // ジャンル一覧
  $stmt = $pdo->prepare('SELECT GenreCode, GenreName FROM Genre ORDER BY GenreCode');
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $row) {
    $genres[$row['GenreCode']] = $row['GenreName'];
  }

  // 値段の下限・上限、商品名の最大文字数（SystemConfiguration の Category = 'Sales'）
  $setting_min      = '';
  $setting_max      = '';
  $setting_name_max = '';
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
    if ($row['Key'] === 'ProductNameMaxLength') {
      $setting_name_max = trim($row['Value']);
    }
  }

  // 半角数字のときだけ DB の値を使う
  if (preg_match('/^[0-9]+$/', $setting_min)) {
    $price_min = (int)$setting_min;
  }
  if (preg_match('/^[0-9]+$/', $setting_max)) {
    $price_max = (int)$setting_max;
  }
  if (preg_match('/^[0-9]+$/', $setting_name_max) && (int)$setting_name_max > 0) {
    $name_max = min((int)$setting_name_max, PRODUCT_NAME_COLUMN_MAX);
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
// 下書き
// ------------------------------------------------------------
$draft = null;   // 「下書きがあります。使用しますか？」のモーダルに出す下書き（出さないときは null）

try {
  // 下書きのモーダルで「はい」：下書きをフォームに入れる（画像は一時フォルダにコピーして「選択中の画像」にする）
  if ($action === 'load_draft') {
    $saved = find_sell_draft($user_id);
    if ($saved !== null) {
      clear_sell_input();
      $form_product_name = $saved['ProductName'];
      $form_genre_code   = (string)$saved['GenreCode'];
      $form_price        = (string)$saved['Price'];

      $tmp = copy_sell_draft_images_to_tmp($user_id);
      if ($tmp !== null) {
        $_SESSION['sell_images'] = $tmp;
      }

      // 下書きを使って入力中であることを覚えておく（「保存しない」で戻ったら下書きを削除するため）
      $_SESSION['sell_draft_loaded'] = true;
    }
  }

  // 画面を開いたとき（販売確認画面から戻ったとき以外）に下書きがあれば、モーダルで使うか聞く
  if ($action === '' && !isset($_SESSION['sell']) && $error_db === '') {
    $draft = find_sell_draft($user_id);
  }
} catch (PDOException $e) {
  error_log($e->getMessage());
  $error_db = MSG_DB_ERROR;
}

// ------------------------------------------------------------
// 販売ボタン・下書き保存：入力チェック
// （下書きは入力がすべて正しいときだけ保存できる。チェック内容は販売と同じ）
// ------------------------------------------------------------
if (($action === 'sell' || $action === 'save_draft') && $error_db === '') {
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
  } elseif (mb_strlen($form_product_name, 'UTF-8') > $name_max) {
    $error_product_name = '商品名は' . $name_max . '文字以内で入力してください';
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

  $all_ok = $error_product_name === '' && $error_genre_code === '' && $error_price === '' && $error_images === '';

  // 下書き保存：4項目ともエラーがなければ下書きに保存してメイン画面へ（今の入力値・一時保存の画像は消す）
  if ($action === 'save_draft' && $all_ok) {
    try {
      save_sell_draft($user_id, (int)$form_genre_code, $form_product_name, (int)$price, $_SESSION['sell_images'] ?? null);
      clear_sell_input();
      set_flash('下書きを保存しました。', 'success');
      redirect(URL_MAIN);
    } catch (PDOException $e) {
      error_log($e->getMessage());
      $error_db = MSG_DB_ERROR;
    }
  }

  // 販売：4項目ともエラーがなければセッションに保存して販売確認画面へ
  if ($action === 'sell' && $all_ok) {
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
  'name_max'           => $name_max,
  'error_db'           => $error_db,
  'error_product_name' => $error_product_name,
  'error_genre_code'   => $error_genre_code,
  'error_price'        => $error_price,
  'error_images'       => $error_images,
  'image_urls'         => isset($_SESSION['sell_images']) ? product_image_tmp_urls($_SESSION['sell_images']) : [],
  'image_max'          => PRODUCT_IMAGE_MAX,
  'draft'              => $draft,
  'draft_image_count'  => $draft !== null ? count(glob(sell_draft_image_dir($user_id) . '/*') ?: []) : 0,
  'draft_genre_name'   => $draft !== null ? ($genres[$draft['GenreCode']] ?? '') : '',
]);
