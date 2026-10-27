<?php
// ============================================================
// 会員情報（氏名・郵便番号・住所・電話番号）の入力チェック共通関数
//   使っている画面：register.php（新規登録） / mypage.php（会員情報編集）
//   画面側(JS)のチェックは public/assets/js/profile_validation.js に同じ内容がある。
//   ルールを変えるときは、この2つを一緒に直すこと。
// ============================================================

// UserData テーブル定義に合わせた上限
const PROFILE_NAME_MAX    = 50;    // 氏名（文字数）
const PROFILE_ADDRESS_MAX = 256;   // 住所（文字数）
const PROFILE_TEL_MAX     = 15;    // 電話番号（桁数）

/**
 * 前後の空白を取り除く（半角・全角スペース、NBSP、改行など）
 * PHP の trim() は全角スペースを取らないため、JS の trim() と同じ結果になるようにここで揃える。
 * 文字列以外（配列など）が送られてきたときは空文字にする。
 *
 * @param mixed $value
 */
function trim_input($value): string
{
    if (!is_string($value)) {
        return '';
    }
    $trimmed = preg_replace('/^[\s\x{3000}\x{00A0}]+|[\s\x{3000}\x{00A0}]+$/u', '', $value);
    // UTF-8 として不正な文字列のときは null が返るので、通常の trim にしておく
    return $trimmed ?? trim($value);
}

/**
 * 郵便番号を保存する形式（ハイフンなし7桁）にそろえる。
 * 「123-4567」→「1234567」。それ以外の文字列はそのまま返す（チェックでエラーにする）。
 */
function normalize_zip_code(string $zipCode): string
{
    if (preg_match('/^([0-9]{3})-([0-9]{4})$/D', $zipCode, $m)) {
        return $m[1] . $m[2];
    }
    return $zipCode;
}

/**
 * POST などの入力から会員情報4項目を取り出し、前後の空白除去と郵便番号の正規化をして返す。
 *
 * @param array $source $_POST など
 * @return array{user_name:string, zip_code:string, address:string, tel:string}
 */
function sanitize_profile_input(array $source): array
{
    return [
        'user_name' => trim_input($source['user_name'] ?? ''),
        'zip_code'  => normalize_zip_code(trim_input($source['zip_code'] ?? '')),
        'address'   => trim_input($source['address'] ?? ''),
        'tel'       => trim_input($source['tel'] ?? ''),
    ];
}

/**
 * 会員情報4項目の未入力・桁数・形式チェック。
 * sanitize_profile_input() を通した値を渡すこと。
 *
 * @return string|null エラーメッセージ（問題なければ null）
 */
function validate_profile_fields(string $userName, string $zipCode, string $address, string $tel): ?string
{
    if ($userName === '' || $zipCode === '' || $address === '' || $tel === '') {
        return 'すべての項目を入力してください。';
    }
    if (mb_strlen($userName, 'UTF-8') > PROFILE_NAME_MAX) {
        return '氏名は' . PROFILE_NAME_MAX . '文字以内で入力してください。';
    }
    if (!preg_match('/^[0-9]{7}$/D', $zipCode)) {
        return '郵便番号は半角数字7桁（ハイフンなし、例：1234567）で入力してください。';
    }
    if (mb_strlen($address, 'UTF-8') > PROFILE_ADDRESS_MAX) {
        return '住所は' . PROFILE_ADDRESS_MAX . '文字以内で入力してください。';
    }
    if (strlen($tel) > PROFILE_TEL_MAX || !preg_match('/^[0-9\-]{10,15}$/D', $tel)) {
        return '電話番号は' . PROFILE_TEL_MAX . '桁以内の半角数字（ハイフン可）で入力してください。';
    }
    return null;
}
