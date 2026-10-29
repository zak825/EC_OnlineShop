<?php
require_once __DIR__ . '/../src/bootstrap.php';

// 【画面を開く】すでにログイン済み（かつセッション有効期限内）であるか確認
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

// 照合済みセッションの有効期限（秒）：10分
$verifyTimeout = 10 * 60;

$pdo = db();
$errorMessage = '';
$messageType = 'error'; // 'error'（赤） / 'warning'（黄）
$step = 'verify'; // 'verify'（照合） / 'reset'（新パスワード入力） / 'complete'（完了）

// 秘密の質問リスト（質問ID => 質問文）
$secretQuestions = get_secret_questions();

// 入力フォーム保持用変数（回答・パスワードは再表示しない）
$userIdInput           = '';
$secretQuestionIdInput = '';

// パスワード文字数設定のデフォルト値
$passwordMinLength = 8;
$passwordMaxLength = 50;

// 【パスワードポリシーの取得】
// SystemConfigurationテーブルからパスワードの文字数制限（最小文字数・最大文字数）の設定値を取得する
// ※register.php と同じく Category = 'Password' を参照する
try {
    $sql = "SELECT [Key], [Value]
                FROM dbo.SystemConfiguration
                WHERE Category = 'Password'";
    $stmt = $pdo->prepare($sql);
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
    error_log('Password policy fetch error: ' . $e->getMessage());
}

/**
 * 照合済みセッションが存在し、有効期限内であるか判定する
 */
$isVerifiedSessionValid = function () use ($verifyTimeout): bool {
    if (empty($_SESSION['passreset_verified_uid']) || empty($_SESSION['passreset_verified_at'])) {
        return false;
    }
    return (time() - (int)$_SESSION['passreset_verified_at']) <= $verifyTimeout;
};

/**
 * 照合済みセッションを破棄する
 */
$clearVerifiedSession = function (): void {
    unset($_SESSION['passreset_verified_uid'], $_SESSION['passreset_verified_at']);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? '';

    if (!check_csrf_token()) {
        $errorMessage = '不正なリクエストです（CSRFトークンエラー）。画面を開き直して再度お試しください。';
        // 再設定フェーズ中で照合済みセッションが有効なら、新パスワード入力画面のまま再表示する
        $step = ($mode === 'reset' && $isVerifiedSessionValid()) ? 'reset' : 'verify';
    } elseif ($mode === 'verify') {
        // ============================================================
        // 【照合するボタンを押す】POST受信（mode=verify）
        // ============================================================
        // 照合をやり直す場合に備え、以前の照合結果は破棄しておく
        $clearVerifiedSession();

        $userIdInput           = trim($_POST['user_id'] ?? '');
        $secretQuestionIdInput = trim($_POST['secret_question_id'] ?? '');
        $secretAnswerInput     = trim($_POST['secret_answer'] ?? '');

        // 6. 入力値のサーバー側検証（未入力・型チェック）
        if (
            $userIdInput === '' || $secretQuestionIdInput === '' || $secretAnswerInput === ''
            || !preg_match('/^\d{1,8}$/', $userIdInput) || (int)$userIdInput <= 0
            || !ctype_digit($secretQuestionIdInput) || !isset($secretQuestions[(int)$secretQuestionIdInput])
            || mb_strlen($secretAnswerInput, 'UTF-8') > 100
        ) {
            $errorMessage = 'すべての項目を正しく入力してください。'; // E-PR-01
        } elseif (!is_allowed_zenkaku_text($secretAnswerInput)) {
            $errorMessage = zenkaku_text_error('秘密の質問の回答');
        } else {
            try {
                // 7. IDと秘密の質問のDB照合を行う（プレースホルダでSQLインジェクション対策）
                $sql = "SELECT TOP (1) UserId, SecretQuestionId, SecretAnswer
                            FROM dbo.UserData
                            WHERE UserId = :user_id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':user_id', (int)$userIdInput, PDO::PARAM_INT);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                $resetUserId = $user ? (int)$user['UserId'] : 0;
                $lockMessage = $user ? active_reset_lock_message($pdo, $resetUserId) : null;

                if ($lockMessage !== null) {
                    // ロック中は回答が合っていても照合を通さない（回数も期限も変えない）
                    $errorMessage = $lockMessage;
                    $messageType = 'warning';
                } elseif (
                    $user
                    && (int)$user['SecretQuestionId'] === (int)$secretQuestionIdInput
                    && password_verify($secretAnswerInput, (string)$user['SecretAnswer'])
                ) {
                    clear_reset_lock($pdo, $resetUserId);
                    // 8. 照合済みセッションの保存（セッション固定攻撃対策としてIDを再生成）
                    session_regenerate_id(true);
                    $_SESSION['passreset_verified_uid'] = (int)$user['UserId'];
                    $_SESSION['passreset_verified_at']  = time();
                    $step = 'reset';
                } elseif ($user) {
                    $lockMessage = register_reset_failure($pdo, $resetUserId);
                    if ($lockMessage !== null) {
                        $errorMessage = $lockMessage;
                        $messageType = 'warning';
                    } else {
                        $errorMessage = 'ユーザーID、または秘密の質問と回答の組み合わせが正しくありません。'; // E-PR-02
                    }
                } else {
                    $errorMessage = 'ユーザーID、または秘密の質問と回答の組み合わせが正しくありません。'; // E-PR-02
                }
            } catch (PDOException $e) {
                $errorMessage = 'データベースエラーが発生しました。';
                error_log('Password reset verify error: ' . $e->getMessage());
            }
        }
    } elseif ($mode === 'reset') {
        // ============================================================
        // 【パスワードを再設定するボタンを押す】POST受信（mode=reset）
        // ============================================================
        // 10. 照合済みセッションの確認（hiddenのUserIdは信用せず、セッションの値のみ使用する）
        if (!$isVerifiedSessionValid()) {
            $clearVerifiedSession();
            $errorMessage = 'セッションの有効期限が切れました。お手数ですが最初からやり直してください。'; // E-PR-06
            $step = 'verify';
        } else {
            $step = 'reset';
            $verifiedUserId     = (int)$_SESSION['passreset_verified_uid'];
            $newPassword        = $_POST['new_password'] ?? '';
            $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';
            $newPasswordLen     = mb_strlen($newPassword, 'UTF-8');

            // 11. サーバー側でのパスワード一致・ポリシー再検証
            if ($newPassword === '' || $newPasswordConfirm === '') {
                $errorMessage = '新しいパスワードと確認用パスワードを入力してください。'; // E-PR-03
            } elseif ($newPassword !== $newPasswordConfirm) {
                $errorMessage = '新しいパスワードと確認用パスワードが一致しません。'; // E-PR-04
            } elseif ($newPasswordLen < $passwordMinLength || $newPasswordLen > $passwordMaxLength) {
                $errorMessage = "パスワードは{$passwordMinLength}文字以上{$passwordMaxLength}文字以内で入力してください。"; // E-PR-05
            } elseif (password_has_forbidden_char($newPassword)) {
                $errorMessage = password_forbidden_char_error();
            } else {
                // 既存のログイン処理（password_verify）に合わせてハッシュ化して保存する
                $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

                try {
                    if ($passwordHash === false) {
                        throw new RuntimeException('password_hash failed');
                    }

                    // 12. 新しいパスワードをDBに登録する
                    $pdo->beginTransaction();

                    $sql = "UPDATE dbo.UserData
                                SET LoginPassword = :new_password,
                                    PasswordResetRequired = 0
                                WHERE UserId = :user_id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindValue(':new_password', $passwordHash, PDO::PARAM_STR);
                    $stmt->bindValue(':user_id', $verifiedUserId, PDO::PARAM_INT);
                    $stmt->execute();

                    if ($stmt->rowCount() !== 1) {
                        throw new RuntimeException('Target user not found. UserId=' . $verifiedUserId);
                    }

                    // 更新者・更新日時は UserData ではなく共通項目（Commonテーブル）に記録する
                    // （登録時に register_check.php が InsertUserId で記録した行を更新する）
                    $commonSql = "UPDATE dbo.Common
                                      SET UpdateUserId = :update_user_id,
                                          UpdateDateTime = GETDATE()
                                      WHERE InsertUserId = :insert_user_id";
                    $commonStmt = $pdo->prepare($commonSql);
                    $commonStmt->bindValue(':update_user_id', $verifiedUserId, PDO::PARAM_INT);
                    $commonStmt->bindValue(':insert_user_id', $verifiedUserId, PDO::PARAM_INT);
                    $commonStmt->execute();

                    // 再設定できたら、ログイン失敗と秘密の質問の失敗のロックを外す
                    clear_login_lock($pdo, $verifiedUserId);
                    clear_reset_lock($pdo, $verifiedUserId);

                    $pdo->commit();

                    // 13. 照合用セッションを破棄し、再設定完了画面を表示する
                    $clearVerifiedSession();
                    $step = 'complete';
                } catch (PDOException | RuntimeException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errorMessage = 'パスワードの登録に失敗しました。時間をおいて再度お試しください。'; // E-PR-07
                    error_log('Password reset update error: ' . $e->getMessage());
                }
            }
        }
    } else {
        // 想定外の mode は照合画面からやり直させる
        $clearVerifiedSession();
        $errorMessage = '不正なリクエストです。画面を開き直して再度お試しください。';
        $step = 'verify';
    }
} else {
    // 3. 初期表示時は照合用セッションを初期化する
    $clearVerifiedSession();
}

$flash = get_flash();
if ($errorMessage === '' && !empty($flash['message'])) {
    $errorMessage = $flash['message'];
    if (($flash['type'] ?? '') === 'warning') {
        $messageType = 'warning';
    }
}

view('passreset', [
    'step'                  => $step,
    'token'                 => csrf_token(),
    'errorMessage'          => $errorMessage,
    'messageType'           => $messageType,
    'secretQuestions'       => $secretQuestions,
    'userIdInput'           => $userIdInput,
    'secretQuestionIdInput' => $secretQuestionIdInput,
    'passwordMinLength'     => $passwordMinLength,
    'passwordMaxLength'     => $passwordMaxLength,
]);
