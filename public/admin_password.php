<?php
// ============================================================
// 管理画面：管理パスワードの変更
//   現在のパスワードで本人確認してから、新しいパスワードを保存する。
//   秘密の質問は使わない。変更後も管理セッションは維持する。
//
// セッション $_SESSION['admin']['password_verified_at']
//   本人確認が通った時刻。10分を過ぎたら最初からやり直す。
// ============================================================
require_once __DIR__ . '/../src/bootstrap.php';

require_admin('admin_password.php');

$verifyTimeout = 10 * 60;

$pdo = db();
$errorMessage = '';
$step = 'verify'; // verify（現在のパスワード） / reset（新しいパスワード） / complete（完了）

$passwordMinLength = 8;
$passwordMaxLength = 50;
try {
    $stmt = $pdo->prepare(
        "SELECT [Key], [Value]
         FROM dbo.SystemConfiguration
         WHERE Category = 'Password'"
    );
    $stmt->execute();
    $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    if (isset($configs['PasswordMinLength']) && is_numeric($configs['PasswordMinLength'])) {
        $passwordMinLength = (int)$configs['PasswordMinLength'];
    }
    if (isset($configs['PasswordMaxLength']) && is_numeric($configs['PasswordMaxLength'])) {
        $passwordMaxLength = (int)$configs['PasswordMaxLength'];
    }
} catch (PDOException $e) {
    $errorMessage = 'パスワードポリシーの取得に失敗しました。';
    error_log('Admin password policy fetch error: ' . $e->getMessage());
}

$isVerified = function () use ($verifyTimeout): bool {
    $verifiedAt = $_SESSION['admin']['password_verified_at'] ?? null;
    if ($verifiedAt === null || $verifiedAt === '') {
        return false;
    }
    return (time() - (int)$verifiedAt) <= $verifyTimeout;
};

$clearVerified = function (): void {
    unset($_SESSION['admin']['password_verified_at']);
};

$adminId = (int)($_SESSION['admin']['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? '';

    if (!check_admin_csrf_token()) {
        $errorMessage = '不正なリクエストです（CSRFトークンエラー）。画面を開き直して再度お試しください。';
        $step = ($mode === 'reset' && $isVerified()) ? 'reset' : 'verify';
    } elseif ($mode === 'verify') {
        $clearVerified();
        $currentPassword = $_POST['current_password'] ?? '';

        if ($currentPassword === '') {
            $errorMessage = '現在のパスワードを入力してください。';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'SELECT PasswordHash FROM dbo.AdminAccount WHERE AdminId = :admin_id'
                );
                $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
                $stmt->execute();
                $hash = $stmt->fetchColumn();

                if ($hash === false || !password_verify($currentPassword, (string)$hash)) {
                    $errorMessage = '現在のパスワードが正しくありません。';
                } else {
                    session_regenerate_id(true);
                    ensure_admin_session();
                    $_SESSION['admin']['password_verified_at'] = time();
                    $step = 'reset';
                }
            } catch (PDOException $e) {
                $errorMessage = 'データベースエラーが発生しました。';
                error_log('Admin password verify error: ' . $e->getMessage());
            }
        }
    } elseif ($mode === 'reset') {
        if (!$isVerified()) {
            $clearVerified();
            $errorMessage = 'セッションの有効期限が切れました。お手数ですが最初からやり直してください。';
            $step = 'verify';
        } else {
            $step = 'reset';
            $newPassword        = $_POST['new_password'] ?? '';
            $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';
            $newPasswordLen     = mb_strlen($newPassword, 'UTF-8');

            if ($newPassword === '' || $newPasswordConfirm === '') {
                $errorMessage = '新しいパスワードと確認用パスワードを入力してください。';
            } elseif ($newPassword !== $newPasswordConfirm) {
                $errorMessage = '新しいパスワードと確認用パスワードが一致しません。';
            } elseif ($newPasswordLen < $passwordMinLength || $newPasswordLen > $passwordMaxLength) {
                $errorMessage = "パスワードは{$passwordMinLength}文字以上{$passwordMaxLength}文字以内で入力してください。";
            } elseif (password_has_forbidden_char($newPassword)) {
                $errorMessage = password_forbidden_char_error();
            } else {
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                try {
                    if ($passwordHash === false) {
                        throw new RuntimeException('password_hash failed');
                    }

                    $pdo->beginTransaction();
                    $stmt = $pdo->prepare(
                        'SELECT PasswordHash
                         FROM dbo.AdminAccount WITH (UPDLOCK, HOLDLOCK)
                         WHERE AdminId = :admin_id'
                    );
                    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
                    $stmt->execute();
                    $currentHash = $stmt->fetchColumn();
                    if ($currentHash === false) {
                        throw new RuntimeException('Admin account not found. AdminId=' . $adminId);
                    }
                    if (password_verify($newPassword, (string)$currentHash)) {
                        $pdo->rollBack();
                        $errorMessage = '現在のパスワードと同じ値は設定できません。';
                    } else {
                        $stmt = $pdo->prepare(
                            'UPDATE dbo.AdminAccount
                             SET PasswordHash = :password_hash
                             WHERE AdminId = :admin_id'
                        );
                        $stmt->bindValue(':password_hash', $passwordHash, PDO::PARAM_STR);
                        $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
                        $stmt->execute();
                        if ($stmt->rowCount() !== 1) {
                            throw new RuntimeException('Admin password update affected ' . $stmt->rowCount() . ' rows.');
                        }
                        $pdo->commit();
                        $clearVerified();
                        $step = 'complete';
                    }
                } catch (PDOException | RuntimeException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if ($errorMessage === '') {
                        $errorMessage = 'パスワードの登録に失敗しました。時間をおいて再度お試しください。';
                        error_log('Admin password update error: ' . $e->getMessage());
                    }
                }
            }
        }
    } else {
        $clearVerified();
        $errorMessage = '不正なリクエストです。画面を開き直して再度お試しください。';
        $step = 'verify';
    }
} else {
    $clearVerified();
}

view('admin_password', [
    'step'               => $step,
    'token'              => admin_csrf_token(),
    'errorMessage'       => $errorMessage,
    'passwordMinLength'  => $passwordMinLength,
    'passwordMaxLength'  => $passwordMaxLength,
]);
