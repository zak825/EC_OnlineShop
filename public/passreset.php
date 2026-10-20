<?php
    require_once __DIR__ . '/../src/bootstrap.php';

    // 照合済みセッションの有効期限（秒）：10分
    const PASSRESET_VERIFY_TIMEOUT = 10 * 60;

    // エラーメッセージ定義（設計書「7. エラーメッセージ一覧」）
    const MSG_PR_01 = 'すべての項目を正しく入力してください。';
    const MSG_PR_02 = 'ユーザーID、または秘密の質問と回答の組み合わせが正しくありません。';
    const MSG_PR_03 = '新しいパスワードと確認用パスワードを入力してください。';
    const MSG_PR_04 = '新しいパスワードと確認用パスワードが一致しません。';
    const MSG_PR_06 = 'セッションの有効期限が切れました。お手数ですが最初からやり直してください。';
    const MSG_PR_07 = 'パスワードの登録に失敗しました。時間をおいて再度お試しください。';
    const MSG_CSRF  = '不正なリクエストです（CSRFトークンエラー）。画面を開き直して再度お試しください。';

    /**
     * 照合用セッションを破棄する
     */
    function clear_passreset_session(): void {
        unset($_SESSION['passreset_verified_uid'], $_SESSION['passreset_verified_at']);
    }

    /**
     * 照合済みセッションが存在し、有効期限内であれば UserId を返す（無効なら null）
     *
     * @return int|null
     */
    function get_verified_user_id(): ?int {
        $uid = $_SESSION['passreset_verified_uid'] ?? null;
        $at  = $_SESSION['passreset_verified_at'] ?? null;
        if (empty($uid) || empty($at)) {
            return null;
        }
        if ((time() - (int)$at) > PASSRESET_VERIFY_TIMEOUT) {
            return null;
        }
        return (int)$uid;
    }

    // 【ステップ2】すでにログイン済み（かつセッション有効期限内）であるか確認
    if (is_logged_in()) {
        header('Location: main.php');
        exit;
    }

    $pdo = db();
    $secretQuestions = get_secret_questions();

    $step          = 'verify';
    $errorMessage  = '';
    $verifiedUserId = null;

    // 入力フォーム保持用変数（回答・パスワードはセキュリティ上、再表示しない）
    $userIdInput           = '';
    $secretQuestionIdInput = '';

    // パスワード文字数設定のデフォルト値
    $passwordMinLength = 8;
    $passwordMaxLength = 50;

    // 【ステップ3】システム設定（パスワード文字数ポリシー）の取得
    // ※既存データは Category = 'Password' で登録されているため、設計書の 'Account' と両方を参照する
    try {
        $sql = "SELECT [Key], [Value]
                FROM dbo.SystemConfiguration
                WHERE Category IN ('Account', 'Password')
                  AND [Key] IN ('PasswordMinLength', 'PasswordMaxLength')";
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
        // 取得失敗時はデフォルト値（8〜50文字）で続行する
        error_log('Password policy fetch error: ' . $e->getMessage());
    }

    $policyMessage = "パスワードは{$passwordMinLength}文字以上{$passwordMaxLength}文字以内の半角英数字で入力してください。";

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        // 【ステップ3〜4】初期表示：照合用セッションを初期化し、照合フォームを表示する
        clear_passreset_session();
        $step = 'verify';
    } else {
        $mode = $_POST['mode'] ?? '';

        if ($mode === 'reset') {
            // ============================================================
            // フェーズ2：新しいパスワードの登録（mode=reset）
            // ============================================================
            $csrfOk         = check_csrf_token();
            $verifiedUserId = get_verified_user_id();

            // 【ステップ10】照合済みセッションの確認（存在しない・期限切れなら最初からやり直し）
            if ($verifiedUserId === null) {
                clear_passreset_session();
                $step         = 'verify';
                $errorMessage = MSG_PR_06;
            } elseif (!$csrfOk) {
                $step         = 'reset';
                $errorMessage = MSG_CSRF;
            } else {
                $step            = 'reset';
                $newPassword     = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['new_password_confirm'] ?? '';
                $newPasswordLen  = mb_strlen($newPassword, 'UTF-8');

                // 【ステップ11】サーバー側でのパスワード一致・ポリシー再検証
                if ($newPassword === '' || $confirmPassword === '') {
                    $errorMessage = MSG_PR_03;
                } elseif (!hash_equals($newPassword, $confirmPassword)) {
                    $errorMessage = MSG_PR_04;
                } elseif (
                    $newPasswordLen < $passwordMinLength ||
                    $newPasswordLen > $passwordMaxLength ||
                    !preg_match('/\A[a-zA-Z0-9]+\z/', $newPassword)
                ) {
                    $errorMessage = $policyMessage;
                } else {
                    // 【ステップ12】新しいパスワードをDBに登録する
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

                    try {
                        if ($passwordHash === false) {
                            throw new RuntimeException('password_hash failed');
                        }

                        $pdo->beginTransaction();

                        // ※UserData には更新者・更新日時の列が無いため、LoginPassword のみ更新する
                        //   （共通監査項目は Common テーブルへ記録する）
                        $sql = "UPDATE dbo.UserData
                                SET LoginPassword = :new_password
                                WHERE UserId = :user_id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->bindValue(':new_password', $passwordHash, PDO::PARAM_STR);
                        $stmt->bindValue(':user_id', $verifiedUserId, PDO::PARAM_INT);
                        $stmt->execute();

                        // 対象ユーザーが存在しない（削除済み等）場合は失敗扱い
                        if ($stmt->rowCount() < 1) {
                            throw new RuntimeException('Target user not found: ' . $verifiedUserId);
                        }

                        // 共通項目（Commonテーブル）が存在する場合は更新ログを残す
                        try {
                            $commonSql = "INSERT INTO dbo.Common (InsertUserId, InsertDateTime, UpdateUserId, UpdateDateTime)
                                          VALUES (:insert_user_id, GETDATE(), :update_user_id, GETDATE())";
                            $commonStmt = $pdo->prepare($commonSql);
                            $commonStmt->bindValue(':insert_user_id', $verifiedUserId, PDO::PARAM_INT);
                            $commonStmt->bindValue(':update_user_id', $verifiedUserId, PDO::PARAM_INT);
                            $commonStmt->execute();
                        } catch (PDOException $e) {
                            // Commonテーブル未作成環境等の場合はスキップ
                            error_log('Common table insert skipped: ' . $e->getMessage());
                        }

                        $pdo->commit();

                        // 【ステップ13】照合用セッションを破棄し、完了画面を表示する
                        clear_passreset_session();
                        $verifiedUserId = null;
                        $step = 'complete';
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $errorMessage = MSG_PR_07;
                        error_log('Password reset update error: ' . $e->getMessage());
                    }
                }
            }
        } else {
            // ============================================================
            // フェーズ1：ユーザーID・秘密の質問の照合（mode=verify）
            // ============================================================
            $step = 'verify';
            // 照合をやり直す場合に備え、以前の照合結果は破棄する
            clear_passreset_session();

            $userIdInput           = trim((string)($_POST['user_id'] ?? ''));
            $secretQuestionIdInput = trim((string)($_POST['secret_question_id'] ?? ''));
            $secretAnswerInput     = trim((string)($_POST['secret_answer'] ?? ''));

            // 【ステップ6】CSRFトークンおよび入力値のサーバー側検証
            if (!check_csrf_token()) {
                $errorMessage = MSG_CSRF;
            } elseif ($mode !== 'verify') {
                $errorMessage = MSG_PR_01;
            } elseif (
                $userIdInput === '' ||
                $secretQuestionIdInput === '' ||
                $secretAnswerInput === '' ||
                !preg_match('/\A\d{1,8}\z/', $userIdInput) ||
                (int)$userIdInput <= 0 ||
                !preg_match('/\A\d+\z/', $secretQuestionIdInput) ||
                !array_key_exists((int)$secretQuestionIdInput, $secretQuestions) ||
                mb_strlen($secretAnswerInput, 'UTF-8') > 100
            ) {
                $errorMessage = MSG_PR_01;
            } else {
                // 【ステップ7】IDと秘密の質問のDB照合を行う
                try {
                    $sql = "SELECT TOP (1) UserId, SecretQuestionId, SecretAnswer
                            FROM dbo.UserData
                            WHERE UserId = :user_id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindValue(':user_id', (int)$userIdInput, PDO::PARAM_INT);
                    $stmt->execute();
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    $isMatched = $user !== false
                        && (int)$user['SecretQuestionId'] === (int)$secretQuestionIdInput
                        && hash_equals(trim((string)$user['SecretAnswer']), $secretAnswerInput);

                    if ($isMatched) {
                        // 【ステップ8】照合済みセッションを保存し、新パスワード入力フォームを表示する
                        session_regenerate_id(true);
                        $verifiedUserId = (int)$user['UserId'];
                        $_SESSION['passreset_verified_uid'] = $verifiedUserId;
                        $_SESSION['passreset_verified_at']  = time();
                        $step = 'reset';
                    } else {
                        // ユーザーの存在有無が推測されないよう、同一メッセージを表示する
                        $errorMessage = MSG_PR_02;
                    }
                } catch (PDOException $e) {
                    $errorMessage = 'データベースエラーが発生しました。';
                    error_log('Password reset verify error: ' . $e->getMessage());
                }
            }
        }
    }

    view('passreset', [
        'step'                  => $step,
        'token'                 => csrf_token(),
        'errorMessage'          => $errorMessage,
        'secretQuestions'       => $secretQuestions,
        'userIdInput'           => $userIdInput,
        'secretQuestionIdInput' => $secretQuestionIdInput,
        'verifiedUserId'        => $verifiedUserId,
        'passwordMinLength'     => $passwordMinLength,
        'passwordMaxLength'     => $passwordMaxLength,
    ]);
