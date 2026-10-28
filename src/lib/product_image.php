<?php
// ============================================================
// 商品画像
//
//   保存先：public/uploads/products/{商品ID}/1.jpg 〜 10.jpg（選んだ順に番号を付ける）
//   DB には保存しない。商品IDのフォルダを見れば画像が分かる
//
//   画像を表示する側（メイン画面・購入画面など）は product_image_urls() を使う
//     $images = product_image_urls($productId);   // ['uploads/products/3/1.jpg', ...]
// ============================================================

const PRODUCT_IMAGE_MAX       = 10;                         // 1商品あたりの枚数
const PRODUCT_IMAGE_MAX_BYTES = 2 * 1024 * 1024;            // 1枚 2MB まで
const PRODUCT_IMAGE_DIR       = ROOT_DIR . '/public/uploads/products';
const PRODUCT_IMAGE_URL       = 'uploads/products';         // public/ からの相対パス
const PRODUCT_IMAGE_TYPES     = [
    IMAGETYPE_JPEG => 'jpg',
    IMAGETYPE_PNG  => 'png',
    IMAGETYPE_GIF  => 'gif',
    IMAGETYPE_WEBP => 'webp',
];

/**
 * 商品の画像のURLを、番号順（1, 2, 3 …）の配列で返す。画像が無ければ空の配列
 * 1枚目を代表画像として使う想定
 */
function product_image_urls(int $productId): array
{
    return image_urls_in(PRODUCT_IMAGE_DIR . '/' . $productId, PRODUCT_IMAGE_URL . '/' . $productId);
}

/** フォルダ内の画像のURLを番号順で返す */
function image_urls_in(string $dir, string $url): array
{
    $files = glob($dir . '/*.{jpg,png,gif,webp}', GLOB_BRACE) ?: [];
    natsort($files);   // 1, 2, …, 10 の順にする（10 が 2 より前に来ないように）

    $urls = [];
    foreach ($files as $file) {
        $version = (string)@filemtime($file);
        $urls[] = $url . '/' . basename($file) . ($version !== '' ? '?v=' . rawurlencode($version) : '');
    }
    return $urls;
}

/**
 * アップロードされた画像（<input type="file" name="images[]" multiple>）をチェックする
 * エラーがなければ '' を返す
 */
function check_product_images(array $files): string
{
    $count = count($files['name']);
    if ($count > PRODUCT_IMAGE_MAX) {
        return '画像は' . PRODUCT_IMAGE_MAX . '枚までです';
    }

    for ($i = 0; $i < $count; $i++) {
        $error = $files['error'][$i];
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || $files['size'][$i] > PRODUCT_IMAGE_MAX_BYTES) {
            return '画像は1枚2MBまでです';
        }
        if ($error !== UPLOAD_ERR_OK) {
            return '画像のアップロードに失敗しました';
        }
        // 拡張子ではなく中身で画像か判定する（画像に見せかけたファイル対策）
        $info = @getimagesize($files['tmp_name'][$i]);
        if ($info === false || !isset(PRODUCT_IMAGE_TYPES[$info[2]])) {
            return 'JPEG・PNG・GIF・WebP の画像を選択してください';
        }
    }
    return '';
}

/**
 * アップロードされた画像を一時フォルダに 1.jpg, 2.png … の名前で保存し、一時フォルダ名を返す
 * （販売画面の時点では商品IDが決まっていないため。登録時に move_product_images() で移す）
 * check_product_images() でチェックしてから呼ぶ
 */
function save_product_images_tmp(array $files): string
{
    $tmpName = 'tmp_' . bin2hex(random_bytes(8));
    $dir     = PRODUCT_IMAGE_DIR . '/' . $tmpName;
    mkdir($dir, 0777, true);

    $count = count($files['name']);
    for ($i = 0; $i < $count; $i++) {
        $info = getimagesize($files['tmp_name'][$i]);
        move_uploaded_file($files['tmp_name'][$i], $dir . '/' . ($i + 1) . '.' . PRODUCT_IMAGE_TYPES[$info[2]]);
    }
    return $tmpName;
}

/** 一時フォルダの画像のURLを番号順で返す（販売画面・販売確認画面での表示用） */
function product_image_tmp_urls(string $tmpName): array
{
    return image_urls_in(PRODUCT_IMAGE_DIR . '/' . basename($tmpName), PRODUCT_IMAGE_URL . '/' . basename($tmpName));
}

/** 一時フォルダの画像を、商品IDのフォルダに移す */
function move_product_images(string $tmpName, int $productId): bool
{
    $from = PRODUCT_IMAGE_DIR . '/' . basename($tmpName);
    $to   = PRODUCT_IMAGE_DIR . '/' . $productId;
    // 画像なしでも、同じ商品IDに残った古い画像を表示しない。
    if (is_dir($to)) {
        delete_product_image_dir($to);
    }

    if (!is_dir($from)) {
        return true;   // 画像なし
    }
    return rename($from, $to);
}

/** 商品編集で、削除・追加・並び替え後の順番で画像を再構築する */
function update_product_images(
    int $productId,
    array $removedFiles,
    ?string $tmpName,
    array $imageOrder = []
): bool {
    $target = PRODUCT_IMAGE_DIR . '/' . $productId;
    $removed = [];
    foreach ($removedFiles as $file) {
        $name = basename((string)$file);
        if ($name === (string)$file && preg_match('/^\d+\.(jpg|png|gif|webp)$/', $name)) {
            $removed[$name] = true;
        }
    }

    $existing = [];
    foreach (glob($target . '/*.{jpg,png,gif,webp}', GLOB_BRACE) ?: [] as $file) {
        $name = basename($file);
        if (!isset($removed[$name])) {
            $existing[$name] = $file;
        }
    }
    natsort($existing);
    $newFiles = $tmpName === null
        ? []
        : (glob(PRODUCT_IMAGE_DIR . '/' . basename($tmpName) . '/*.{jpg,png,gif,webp}', GLOB_BRACE) ?: []);
    natsort($newFiles);

    $ordered = [];
    $used = [];
    foreach ($imageOrder as $token) {
        if (isset($existing[$token])) {
            $ordered[] = $existing[$token];
            $used[$token] = true;
            continue;
        }
        if (preg_match('/^new:(\d+)$/', $token, $match)) {
            $index = (int)$match[1];
            if (isset($newFiles[$index])) {
                $ordered[] = $newFiles[$index];
                $used['new:' . $index] = true;
            }
        }
    }
    foreach ($existing as $name => $file) {
        if (!isset($used[$name])) $ordered[] = $file;
    }
    foreach ($newFiles as $index => $file) {
        if (!isset($used['new:' . $index])) $ordered[] = $file;
    }

    if (!$removed && !$newFiles && !$imageOrder) return true;
    $staging = PRODUCT_IMAGE_DIR . '/edit_' . bin2hex(random_bytes(8));
    if (!mkdir($staging, 0777, true)) return false;
    foreach ($ordered as $number => $source) {
        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if (!copy($source, $staging . '/' . ($number + 1) . '.' . $extension)) {
            delete_product_image_dir($staging);
            return false;
        }
    }
    delete_product_image_dir($target);
    if (!rename($staging, $target)) {
        delete_product_image_dir($staging);
        return false;
    }
    if ($tmpName !== null) delete_product_images_tmp($tmpName);
    return true;
}

/** 一時フォルダを中の画像ごと削除する（クリア・戻る・選び直しのとき） */
function delete_product_images_tmp(string $tmpName): void
{
    delete_product_image_dir(PRODUCT_IMAGE_DIR . '/' . basename($tmpName));
}

/** フォルダを中の画像ごと削除する */
function delete_product_image_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (glob($dir . '/*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($dir);
}
