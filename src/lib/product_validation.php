<?php
const DEFAULT_PRICE_MIN = 300;
const DEFAULT_PRICE_MAX = 9999999;
const DEFAULT_PRODUCT_NAME_MAX = 50;
const DEFAULT_DESCRIPTION_MAX = 500;

// 商品名の列（Sales.ProductName NVARCHAR(50)）に入る最大文字数
// SystemConfiguration の設定がこれより大きくても、これを超えないようにする（超えると登録時に DB エラーになる）
const PRODUCT_NAME_COLUMN_MAX = 50;
const CONDITION_MAX_LENGTH = 30;
const PRODUCT_CONDITIONS = [
  '新品・未使用' => '新品・未使用',
  '目立った傷や汚れなし' => '目立った傷や汚れなし',
  'やや傷や汚れあり' => 'やや傷や汚れあり',
  '傷や汚れあり' => '傷や汚れあり',
];

function trim_all($value)
{
  if (!is_string($value)) return '';
  $result = preg_replace('/\A[\s\x{3000}]+|[\s\x{3000}]+\z/u', '', (string)$value);
  if ($result === null) {
    return '';
  }
  return $result;
}

function product_form_limits(PDO $pdo): array {
  $price_min = DEFAULT_PRICE_MIN;
  $price_max = DEFAULT_PRICE_MAX;
  $name_max = DEFAULT_PRODUCT_NAME_MAX;
  $description_max = DEFAULT_DESCRIPTION_MAX;
  // 値段の下限・上限、商品名の最大文字数（SystemConfiguration の Category = 'Sales'）
  $setting_min      = '';
  $setting_max      = '';
  $setting_name_max = '';
  $setting_description_max = '';
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
    if ($row['Key'] === 'DescriptionMaxLength') {
      $setting_description_max = trim($row['Value']);
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
  if (preg_match('/^[0-9]+$/', $setting_description_max) && (int)$setting_description_max > 0) {
    $description_max = min((int)$setting_description_max, 1000);
  }

  // 下限 > 上限 の設定ミスなら両方とも仮の値に戻す
  if ($price_min > $price_max) {
    $price_min = DEFAULT_PRICE_MIN;
    $price_max = DEFAULT_PRICE_MAX;
  }
  return compact('price_min', 'price_max', 'name_max', 'description_max');
}
function validate_product_form(array $input, array $genres, array $limits): array {
  extract($limits);
  $form_product_name = trim_all($input['product_name'] ?? '');
  $form_genre_code = trim_all($input['genre_code'] ?? '');
  $form_price = trim_all($input['price'] ?? '');
  $form_condition = trim_all($input['condition'] ?? '');
  $form_description = trim_all($input['description'] ?? '');
  $error_product_name = $error_genre_code = $error_price = $error_condition = $error_description = '';
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

  if (!isset(PRODUCT_CONDITIONS[$form_condition])) {
    $error_condition = '商品の状態を選択してください';
  }
  if ($form_description === '') {
    $error_description = '商品の説明を入力してください';
  } elseif (mb_strlen($form_description, 'UTF-8') > $description_max) {
    $error_description = '商品の説明は' . $description_max . '文字以内で入力してください';
  }

  return compact('form_product_name', 'form_genre_code', 'form_price', 'form_condition', 'form_description', 'price',
    'error_product_name', 'error_genre_code', 'error_price', 'error_condition', 'error_description');
}
