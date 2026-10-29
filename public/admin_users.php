<?php
// ============================================================
// 管理画面：ユーザー管理
//   GET  admin_users.php  … UserData の一覧
//   POST admin_users.php  … 指定ユーザーと、その利用者にぶら下がる行を削除する
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';
require_once SRC_DIR . '/lib/sell_draft.php';

require_admin('admin_users.php');

const ADMIN_ICON_DIR = __DIR__ . '/uploads/icons';

$pdo = db();
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!check_admin_csrf_token()) {
        set_admin_flash('不正なリクエストです（CSRFトークンエラー）。画面を開き直して再度お試しください。', 'error');
        redirect('admin_users.php');
    }
    if ($userId === false || $userId <= 0) {
        set_admin_flash('ユーザーが見つかりません。', 'error');
        redirect('admin_users.php');
    }

    $productIds = [];
    $iconPath = '';
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT Userid, IconPath
             FROM dbo.UserData WITH (UPDLOCK, HOLDLOCK)
             WHERE Userid = :user_id'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user === false) {
            $pdo->rollBack();
            set_admin_flash('ユーザーが見つかりません。', 'error');
            redirect('admin_users.php');
        }
        $iconPath = (string)($user['IconPath'] ?? '');

        $stmt = $pdo->prepare('SELECT ProductID FROM dbo.Sales WHERE Userid = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $productIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $childTables = [
            'DELETE FROM dbo.UserSession WHERE Userid = :user_id',
            'DELETE FROM dbo.UserLoginLock WHERE Userid = :user_id',
            'DELETE FROM dbo.UserResetLock WHERE Userid = :user_id',
            'DELETE FROM dbo.SalesDraft WHERE Userid = :user_id',
            'DELETE FROM dbo.Sales WHERE Userid = :user_id',
        ];
        foreach ($childTables as $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
        }

        // この利用者が買った商品は、出品者の商品として残し、購入者だけ外す
        $stmt = $pdo->prepare(
            'UPDATE dbo.Sales SET BuyerUserid = NULL WHERE BuyerUserid = :user_id'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare('DELETE FROM dbo.Common WHERE InsertUserId = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare(
            'UPDATE dbo.Common
             SET UpdateUserId = NULL, UpdateDateTime = NULL
             WHERE UpdateUserId = :user_id'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare('DELETE FROM dbo.UserData WHERE Userid = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('User delete affected ' . $stmt->rowCount() . ' rows. Userid=' . $userId);
        }

        $pdo->commit();
    } catch (PDOException | RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Admin user delete error: ' . $e->getMessage());
        set_admin_flash('ユーザーの削除に失敗しました。時間をおいて再度お試しください。', 'error');
        redirect('admin_users.php');
    }

    foreach ($productIds as $productId) {
        delete_product_image_dir(PRODUCT_IMAGE_DIR . '/' . $productId);
    }
    delete_product_image_dir(sell_draft_image_dir($userId));
    if (str_starts_with($iconPath, 'uploads/icons/')) {
        $iconFile = ADMIN_ICON_DIR . '/' . basename($iconPath);
        if (is_file($iconFile)) {
            unlink($iconFile);
        }
    }

    set_admin_flash('ユーザーID ' . $userId . ' を削除しました。', 'success');
    redirect('admin_users.php');
}

$users = [];
try {
    $stmt = $pdo->query(
        'SELECT Userid, UserName, ZipCode, Address, TEL, PasswordResetRequired
         FROM dbo.UserData
         ORDER BY Userid'
    );
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Admin user list error: ' . $e->getMessage());
    $errorMessage = 'ユーザー一覧の取得に失敗しました。時間をおいて再度お試しください。';
}

view('admin_users', [
    'flash'        => get_admin_flash(),
    'token'        => admin_csrf_token(),
    'errorMessage' => $errorMessage,
    'users'        => $users,
]);
