<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once SRC_DIR . '/lib/product_image.php';

const PRODUCT_EDIT_CONDITIONS = [
    '新品・未使用' => '新品・未使用',
    '目立った傷や汚れなし' => '目立った傷や汚れなし',
    'やや傷や汚れあり' => 'やや傷や汚れあり',
    '傷や汚れあり' => '傷や汚れあり',
];

if (!is_logged_in()) {
    redirect_to_login('mypage.php?tab=sold');
}
$userId = login_user_id();
$productId = filter_var($_GET['productId'] ?? $_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
if (!$productId) {
    set_flash('商品が見つかりません。', 'error');
    redirect('mypage.php?tab=sold');
}

$hasProductDetails = db_has_column('Sales', 'ProductCondition')
    && db_has_column('Sales', 'Description');
$detailSelect = $hasProductDetails
    ? 'ProductCondition, Description'
    : 'CAST(NULL AS NVARCHAR(30)) AS ProductCondition,
       CAST(NULL AS NVARCHAR(1000)) AS Description';
$stmt = db()->prepare(
    'SELECT ProductID, GenreCode, ProductName, Price, ' . $detailSelect . ', BuyerUserid
     FROM Sales WHERE ProductID = :product_id AND Userid = :user_id'
);
$stmt->execute([':product_id' => $productId, ':user_id' => $userId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$product || $product['BuyerUserid'] !== null) {
    set_flash('購入済みの商品は編集できません。', 'error');
    redirect('mypage.php?tab=sold');
}

$genres = db()->query('SELECT GenreCode, GenreName FROM Genre ORDER BY GenreCode')->fetchAll(PDO::FETCH_KEY_PAIR);
$descriptionMax = 500;
$setting = db()->prepare(
    'SELECT Value FROM SystemConfiguration
     WHERE Category = :category AND [Key] = :setting_key'
);
$setting->execute([':category' => 'Sales', ':setting_key' => 'DescriptionMaxLength']);
$configuredDescriptionMax = trim((string)$setting->fetchColumn());
if (preg_match('/^[0-9]+$/', $configuredDescriptionMax) && (int)$configuredDescriptionMax > 0) {
    $descriptionMax = min((int)$configuredDescriptionMax, 1000);
}
$errors = [];
$imageError = '';
$tmpImages = null;
$imageOrder = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = '不正な操作です。もう一度やり直してください。';
    } else {
        $name = trim((string)($_POST['product_name'] ?? ''));
        $condition = trim((string)($_POST['condition'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $removeImages = array_values(array_filter(
            (array)($_POST['remove_images'] ?? []),
            static fn($file): bool => is_string($file)
        ));
        $imageOrder = array_values(array_filter(
            (array)($_POST['image_order'] ?? []),
            static fn($file): bool => is_string($file)
        ));
        $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_INT);
        $genreCode = filter_var($_POST['genre_code'] ?? null, FILTER_VALIDATE_INT);
        if ($name === '' || mb_strlen($name, 'UTF-8') > 50) $errors[] = '商品名は1〜50文字で入力してください。';
        if (!$genreCode || !isset($genres[$genreCode])) $errors[] = 'ジャンルを選択してください。';
        if ($price === false || $price < 1) $errors[] = '値段は正の整数で入力してください。';
        if (!isset(PRODUCT_EDIT_CONDITIONS[$condition])) $errors[] = '商品の状態を選択してください。';
        if ($description === '' || mb_strlen($description, 'UTF-8') > $descriptionMax) {
            $errors[] = '商品の説明は1〜' . $descriptionMax . '文字で入力してください。';
        }
        $files = $_FILES['images'] ?? null;
        if ($files !== null && ($files['error'][0] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $imageError = check_product_images($files);
            if ($imageError === '') {
                $tmpImages = save_product_images_tmp($files);
            }
        }
        if ($imageError !== '') {
            $errors[] = $imageError;
        }
        if (!$errors) {
            try {
                $pdo = db();
                $pdo->beginTransaction();
                if ($hasProductDetails) {
                    $stmt = $pdo->prepare(
                        'UPDATE Sales SET GenreCode = :genre_code, ProductName = :product_name, Price = :price,
                            ProductCondition = :condition, Description = :description
                         WHERE ProductID = :product_id AND Userid = :user_id AND BuyerUserid IS NULL'
                    );
                    $params = [
                        ':genre_code' => $genreCode, ':product_name' => $name, ':price' => $price,
                        ':condition' => $condition, ':description' => $description,
                        ':product_id' => $productId, ':user_id' => $userId,
                    ];
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE Sales SET GenreCode = :genre_code, ProductName = :product_name, Price = :price
                         WHERE ProductID = :product_id AND Userid = :user_id AND BuyerUserid IS NULL'
                    );
                    $params = [
                        ':genre_code' => $genreCode, ':product_name' => $name, ':price' => $price,
                        ':product_id' => $productId, ':user_id' => $userId,
                    ];
                }
                $stmt->execute($params);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('商品情報を更新できませんでした。');
                }
                if ($tmpImages !== null || $removeImages) {
                    if (!update_product_images($productId, $removeImages, $tmpImages, $imageOrder)) {
                        throw new RuntimeException('商品画像を更新できませんでした。');
                    }
                    $tmpImages = null;
                }
                $pdo->commit();
            } catch (PDOException | RuntimeException $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($tmpImages !== null) {
                    delete_product_images_tmp($tmpImages);
                    $tmpImages = null;
                }
                error_log('[product_edit] ' . $e->getMessage());
                $errors[] = '商品情報の更新に失敗しました。時間をおいて再度お試しください。';
            }
        } elseif ($tmpImages !== null) {
            delete_product_images_tmp($tmpImages);
            $tmpImages = null;
        }
        if (!$errors) {
            set_flash('商品情報を更新しました。', 'success');
            redirect('mypage.php?tab=sold');
        }
        $product = array_merge($product, [
            'GenreCode' => $genreCode, 'ProductName' => $name, 'Price' => $price,
            'ProductCondition' => $condition, 'Description' => $description,
        ]);
    }
}

view('product_edit', [
    'product' => $product, 'genres' => $genres, 'conditions' => PRODUCT_EDIT_CONDITIONS,
    'errors' => $errors, 'token' => csrf_token(),
    'description_max' => $descriptionMax,
    'image_urls' => product_image_urls($productId),
]);
