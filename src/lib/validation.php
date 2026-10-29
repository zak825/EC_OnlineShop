<?php
// ============================================================
// 会員情報（氏名・郵便番号・住所・電話番号）と、
// パスワード・秘密の質問の回答の文字種チェック
//   使っている画面：register.php（新規登録） / mypage.php（会員情報編集） / passreset.php（再設定）
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
 * 氏名を保存する形式（語の間は半角スペース1つ）にそろえる。
 * 「山田　太郎」「山田  太郎  次郎」→「山田 太郎」「山田 太郎 次郎」。間の空白（全角スペース・連続スペースなど）を半角スペース1つにする。
 * trim_input() を通した値を渡すこと。
 */
function normalize_user_name(string $userName): string
{
    $normalized = preg_replace('/[\s\x{3000}\x{00A0}]+/u', ' ', $userName);
    // UTF-8 として不正な文字列のときは null が返るので、そのまま返す（チェックでエラーにする）
    return $normalized ?? $userName;
}

/**
 * POST などの入力から会員情報4項目を取り出し、前後の空白除去と氏名・郵便番号の正規化をして返す。
 *
 * @param array $source $_POST など
 * @return array{user_name:string, zip_code:string, address:string, tel:string}
 */
function sanitize_profile_input(array $source): array
{
    return [
        'user_name' => normalize_user_name(trim_input($source['user_name'] ?? '')),
        'zip_code'  => normalize_zip_code(trim_input($source['zip_code'] ?? '')),
        'address'   => trim_input($source['address'] ?? ''),
        'tel'       => trim_input($source['tel'] ?? ''),
    ];
}

/**
 * 会員情報4項目の未入力・桁数・形式チェック（すべてのエラーを返す）。
 * sanitize_profile_input() を通した値を渡すこと。
 *
 * @return string[] エラーメッセージの一覧（問題なければ空配列）
 */
function collect_profile_errors(string $userName, string $zipCode, string $address, string $tel): array
{
    $errors = [];

    if ($userName === '') {
        $errors[] = '氏名を入力してください。';
    } elseif (mb_strlen($userName, 'UTF-8') > PROFILE_NAME_MAX) {
        $errors[] = '氏名は' . PROFILE_NAME_MAX . '文字以内で入力してください。';
    } elseif (preg_match('/[^\p{L}\p{M} ]/u', $userName) !== 0) {
        // 文字（漢字・かな・カナ・英字。「ー」「々」を含む）以外 = 記号・数字は使えない
        $errors[] = '氏名に記号や数字は使えません。';
    } elseif (!preg_match('/^[\p{L}\p{M}]+( [\p{L}\p{M}]+){1,2}$/Du', $userName)) {
        // 2語（半角スペース1つ）または3語（半角スペース2つ）まで
        $errors[] = '氏名は2語または3語で、間を半角スペースで区切って入力してください（例：山田 太郎、Tony Shane Miller）。';
    }

    if ($zipCode === '') {
        $errors[] = '郵便番号を入力してください。';
    } elseif (!preg_match('/^[0-9]{7}$/D', $zipCode)) {
        $errors[] = '郵便番号は半角数字7桁（ハイフンなし、例：1234567）で入力してください。';
    }

    if ($address === '') {
        $errors[] = '住所を入力してください。';
    } elseif (mb_strlen($address, 'UTF-8') > PROFILE_ADDRESS_MAX) {
        $errors[] = '住所は' . PROFILE_ADDRESS_MAX . '文字以内で入力してください。';
    } elseif (!is_allowed_zenkaku_text($address)) {
        $errors[] = zenkaku_text_error('住所');
    }

    if ($tel === '') {
        $errors[] = '電話番号を入力してください。';
    } elseif (strlen($tel) > PROFILE_TEL_MAX || !preg_match('/^[0-9\-]{10,15}$/D', $tel)) {
        $errors[] = '電話番号は' . PROFILE_TEL_MAX . '桁以内の半角数字（ハイフン可）で入力してください。';
    }

    return $errors;
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
    return collect_profile_errors($userName, $zipCode, $address, $tel)[0] ?? null;
}

/**
 * 全角文字、半角英数字、半角ハイフン（-）、半角・全角スペースだけか。
 * 住所と秘密の質問の回答で使う。半角カナ・半角記号は不可。
 */
function is_allowed_zenkaku_text(string $value): bool
{
    if ($value === '') {
        return false;
    }
    return preg_match(
        '/^(?:[A-Za-z0-9\- ]|[\x{3000}-\x{303F}\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{F900}-\x{FAFF}\x{FF01}-\x{FF60}\x{FFE0}-\x{FFE6}\x{20000}-\x{3FFFF}])+$/u',
        $value
    ) === 1;
}

/** 住所・秘密の質問の回答で、使えない文字があったときの文言 */
function zenkaku_text_error(string $label): string
{
    return $label . 'は全角文字、半角英数字、ハイフン（-）、スペースのみ使用できます。';
}

/**
 * パスワードに禁止文字があるか。
 * 禁止するもの：-!"#$%&()*,./:;?[]^_`{}~+<=> と半角スペース・全角スペース（@ は使える）
 */
function password_has_forbidden_char(string $value): bool
{
    $found = preg_match('/[\[\]!"#$%&()*,.\/:;?^_`{}~+<=> \x{3000}-]/u', $value);
    return $found !== 0;
}

/** パスワードに禁止文字があったときの文言 */
function password_forbidden_char_error(): string
{
    return 'パスワードに半角スペース、全角スペース、および次の記号は使用できません。-!"#$%&()*,./:;?[]^_`{}~+<=>';
}

// ============================================================
// システム設定（SystemConfiguration）の読み込みと値チェック
//   使っている画面：system_setting.php（入力） / system_setting_confirm.php（確認・実行）
//   画面側の setting.js は、system_setting.html の data 属性に出したこの上限・下限でチェックする。
//   エラー文言を変えるときは setting.js も一緒に直すこと。
// ============================================================

// 設定ごとの [下限, 上限]。ここに無い Key は半角数字かどうかだけチェックする
const SETTING_LIMITS = [
    'PasswordMinLength'    => [1, 72],          // password_hash（bcrypt）は 72 バイトを超えた部分を無視する
    'PasswordMaxLength'    => [1, 72],
    'SessionTimeout'       => [1, 1440],        // 分（最長1日）
    'PriceMin'             => [1, 2147483647],  // Sales.Price は INT
    'PriceMax'             => [1, 2147483647],
    'ProductNameMaxLength' => [1, 50],          // Sales.ProductName は NVARCHAR(50)
    'DescriptionMaxLength' => [1, 1000],        // Sales.Description は NVARCHAR(1000)
];

// 大小関係がある設定（下限側の Key => 上限側の Key）。同じ値は許可し、衝突したときは下限側にエラーを出す
const SETTING_RANGE_PAIRS = [
    'PasswordMinLength' => 'PasswordMaxLength',
    'PriceMin'          => 'PriceMax',
];

/**
 * SystemConfiguration の全行を id 順で取得する
 * Label には Comment（未設定なら Key）を入れる
 *
 * @return array<int, array{id:int, Key:string, Value:string, Label:string}>
 * @throws PDOException DBエラー時
 */
function fetch_system_settings(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        'SELECT id, [Key], [Value], Comment
         FROM dbo.SystemConfiguration
         ORDER BY id'
    );
    $stmt->execute();

    $settings = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $comment = trim((string)($row['Comment'] ?? ''));
        $settings[] = [
            'id'    => (int)$row['id'],
            'Key'   => (string)$row['Key'],
            'Value' => (string)$row['Value'],
            'Label' => $comment !== '' ? $comment : (string)$row['Key'],
        ];
    }
    return $settings;
}

/**
 * 設定値の未入力・文字種・上限下限・大小関係のチェック（すべてのエラーを返す）
 * trim_input() を通した値を渡すこと。
 *
 * @param array<string, string> $values Key => 入力値
 * @param array<string, string> $labels Key => 表示名（大小関係のエラー文言に使う）
 * @return array<string, string> Key => エラーメッセージ（問題なければ空配列）
 */
function collect_setting_errors(array $values, array $labels): array
{
    $errors = [];

    foreach ($values as $key => $value) {
        if ($value === '') {
            $errors[$key] = '入力してください。';
            continue;
        }
        if (!preg_match('/^[0-9]+$/D', $value)) {
            $errors[$key] = '半角数字で入力してください。';
            continue;
        }
        if (isset(SETTING_LIMITS[$key])) {
            [$min, $max] = SETTING_LIMITS[$key];
            $number = normalize_setting_value($value);
            // 桁数で先に判定する（INT の上限を超える桁数を (int) にするとあふれるため）
            if (strlen($number) > strlen((string)$max) || (int)$number < $min || (int)$number > $max) {
                $errors[$key] = $min . '〜' . $max . 'の範囲で入力してください。';
            }
        }
    }

    foreach (SETTING_RANGE_PAIRS as $minKey => $maxKey) {
        if (!isset($values[$minKey], $values[$maxKey]) || isset($errors[$minKey]) || isset($errors[$maxKey])) {
            continue;
        }
        if ((int)$values[$minKey] > (int)$values[$maxKey]) {
            $errors[$minKey] = '「' . ($labels[$maxKey] ?? $maxKey) . '」以下の値を入力してください。';
        }
    }

    return $errors;
}

/**
 * 半角数字の設定値を保存する形にそろえる（先頭の 0 を取る。「0008」→「8」）
 */
function normalize_setting_value(string $value): string
{
    $trimmed = ltrim($value, '0');
    return $trimmed === '' ? '0' : $trimmed;
}
