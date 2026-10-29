<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once SRC_DIR . '/lib/product_image.php';

require_once SRC_DIR . '/lib/product_validation.php';

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
$limits = product_form_limits(db());
extract($limits);
$fieldErrors = array_fill_keys(['product_name', 'genre_code', 'price', 'condition', 'description', 'images'], '');
$errors = [];
$imageError = '';
$tmpImages = null;
$imageOrder = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = '不正な操作です。もう一度やり直してください。';
    } else {
        extract(validate_product_form($_POST, $genres, $limits));
        $name = $form_product_name;
        $condition = $form_condition;
        $description = $form_description;
        $genreCode = $form_genre_code;
        foreach ($fieldErrors as $field => $_) {
            if ($field !== 'images') $fieldErrors[$field] = ${'error_' . $field};
        }
        $removeImages = array_values(array_filter(
            (array)($_POST['remove_images'] ?? []),
            static fn($file): bool => is_string($file)
        ));
        $imageOrder = array_values(array_filter(
            (array)($_POST['image_order'] ?? []),
            static fn($file): bool => is_string($file)
        ));
        $files = $_FILES['images'] ?? null;
        if ($files !== null && ($files['error'][0] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $imageError = check_product_images($files);
            if ($imageError === '') {
                $tmpImages = save_product_images_tmp($files);
            }
        }
        $existingNames = array_map(static fn($url) => basename(parse_url($url, PHP_URL_PATH)), product_image_urls($productId));
        $remainingCount = count(array_diff($existingNames, $removeImages));
        $newCount = $files !== null && ($files['error'][0] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? count($files['name']) : 0;
        if ($remainingCount + $newCount > PRODUCT_IMAGE_MAX) {
            $imageError = '画像は' . PRODUCT_IMAGE_MAX . '枚までです';
        }
        $fieldErrors['images'] = $imageError;
        if (!$errors && !array_filter($fieldErrors)) {
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
                if ($tmpImages !== null || $removeImages || $imageOrder) {
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
        if (!$errors && !array_filter($fieldErrors)) {
            set_flash('商品情報を更新しました。', 'success');
            redirect('mypage.php?tab=sold');
        }
        $product = array_merge($product, [
            'GenreCode' => $genreCode, 'ProductName' => $name, 'Price' => $form_price,
            'ProductCondition' => $condition, 'Description' => $description,
        ]);
    }
}

view('product_edit', [
    'product' => $product, 'genres' => $genres, 'conditions' => PRODUCT_CONDITIONS,
    'errors' => $errors, 'token' => csrf_token(),
    'description_max' => $description_max,
    'name_max' => $name_max, 'price_min' => $price_min, 'price_max' => $price_max,
    'fieldErrors' => $fieldErrors,
    'image_urls' => product_image_urls($productId),
]);
