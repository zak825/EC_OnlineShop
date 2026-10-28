<?php
// ============================================================
// 出品の下書き（1人1件）
//
//   入力値：SalesDraft テーブル（Userid ごとに1行）
//   画像  ：public/uploads/products/draft_{Userid}/1.jpg …（product_image.php と同じ並べ方）
//
//   販売画面（sell.php）で保存・読み込み・削除し、出品が完了したら sell_confirm.php で削除する
// ============================================================

/** 下書きの画像フォルダ */
function sell_draft_image_dir(int $userId): string
{
    return PRODUCT_IMAGE_DIR . '/draft_' . $userId;
}

/** 下書きを返す（無ければ null） */
function find_sell_draft(int $userId): ?array
{
    $stmt = db()->prepare(
        'SELECT GenreCode, ProductName, Price, ProductCondition, Description, UpdateDateTime
         FROM SalesDraft WHERE Userid = :userid'
    );
    $stmt->execute([':userid' => $userId]);
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);
    return $draft === false ? null : $draft;
}

/**
 * 下書きを保存する（すでにあれば上書きする）
 * $tmpImages … 販売画面で一時保存している画像のフォルダ名（無ければ null）。その画像を下書きの画像にする
 */
function save_sell_draft(
    int $userId,
    int $genreCode,
    string $productName,
    int $price,
    string $condition,
    string $description,
    ?string $tmpImages
): void
{
    $params = [
        ':userid'       => $userId,
        ':genre_code'   => $genreCode,
        ':product_name' => $productName,
        ':price'        => $price,
        ':condition'    => $condition,
        ':description'  => $description,
    ];

    $stmt = db()->prepare(
        'UPDATE SalesDraft
         SET GenreCode = :genre_code, ProductName = :product_name, Price = :price,
             ProductCondition = :condition, Description = :description, UpdateDateTime = GETDATE()
         WHERE Userid = :userid'
    );
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        $stmt = db()->prepare(
            'INSERT INTO SalesDraft
                (Userid, GenreCode, ProductName, Price, ProductCondition, Description, UpdateDateTime)
             VALUES (:userid, :genre_code, :product_name, :price, :condition, :description, GETDATE())'
        );
        $stmt->execute($params);
    }

    // 画像は前の下書きの分を消してから、今の画像をコピーする
    $dir = sell_draft_image_dir($userId);
    delete_product_image_dir($dir);
    if ($tmpImages !== null) {
        copy_image_files(PRODUCT_IMAGE_DIR . '/' . basename($tmpImages), $dir);
    }
}

/**
 * 下書きの画像を新しい一時フォルダにコピーして、そのフォルダ名を返す（画像が無ければ null）
 * 下書きを使うときに、販売画面の「選択中の画像」にするため
 */
function copy_sell_draft_images_to_tmp(int $userId): ?string
{
    $dir = sell_draft_image_dir($userId);
    if (!glob($dir . '/*')) {
        return null;
    }
    $tmpName = 'tmp_' . bin2hex(random_bytes(8));
    copy_image_files($dir, PRODUCT_IMAGE_DIR . '/' . $tmpName);
    return $tmpName;
}

/** 下書きを削除する（入力値も画像も） */
function delete_sell_draft(int $userId): void
{
    $stmt = db()->prepare('DELETE FROM SalesDraft WHERE Userid = :userid');
    $stmt->execute([':userid' => $userId]);
    delete_product_image_dir(sell_draft_image_dir($userId));
}

/** フォルダ内の画像を、別のフォルダにそのままの名前でコピーする */
function copy_image_files(string $from, string $to): void
{
    if (!is_dir($from)) {
        return;
    }
    if (!is_dir($to)) {
        mkdir($to, 0777, true);
    }
    foreach (glob($from . '/*') ?: [] as $file) {
        copy($file, $to . '/' . basename($file));
    }
}
