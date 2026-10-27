<?php
// ============================================================
// セッション・ログイン関連の共通処理
//
// ログイン担当と合わせる前提:
//   ログイン成功時に $_SESSION['user_id'] / $_SESSION['user_name'] を入れる
//   セッションの有効期限切れ（タイムアウト）はログイン担当の処理で判定し、
//   切れていたら $_SESSION['user_id'] が無い状態になる
//   同時ログイン禁止のため、ログイン成功時に $_SESSION['login_token'] も入れる
//   （UserSession テーブルのトークンと一致するセッションだけが有効）
//   login.php は ?return=xxx を受け取り、ログイン成功後そこへ遷移する
//   （return は自サイト内のパスだけ許可すること）
// ============================================================

// セッションがまだ開始されていなければ開始する
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ログイン状態およびセッション有効期限（タイムアウト）を確認する
 *
 * @return bool ログイン中かつ有効期限内なら true、未ログインまたは期限切れなら false
 */
function is_logged_in(): bool {
    // 1. ユーザーID・最終操作時刻が存在するか確認
    if (
        empty($_SESSION['user_id']) ||
        empty($_SESSION['last_activity'])
    ) {
        $devUserId = development_login_user_id();
        if ($devUserId === null) {
            return false;
        }

        $_SESSION['logged_in']     = true;
        $_SESSION['user_id']       = $devUserId;
        $_SESSION['UserId']        = $devUserId; // 互換キー
        $_SESSION['last_activity'] = time();
    }

    // 2. DB (SystemConfiguration) からセッションタイムアウト秒数を取得（初期値: 1800秒 = 30分）
    $timeout = get_session_timeout_seconds();

    // 3. 最終操作時刻から設定秒数以上経過していたらログイン切れ（セッション破棄）
    if ((time() - (int)$_SESSION['last_activity']) > $timeout) {
        logout_session();
        set_flash('セッションの有効期限が切れました。再度ログインしてください。', 'error');
        return false;
    }

    // 3.5 同時ログイン禁止：このセッションが UserSession に登録された正規のセッションか確認する
    //     （確認と同時に DB 側の最終操作時刻も更新する）
    if (!is_dev_session_without_login_token()) {
        try {
            if (!touch_login_slot((int)$_SESSION['user_id'], (string)($_SESSION['login_token'] ?? ''))) {
                // 行が無い・トークンが違う（タイムアウト後に別の端末がログインした、パスワード再設定で解除された等）
                logout_session();
                set_flash('ログイン状態が無効になりました。再度ログインしてください。', 'error');
                return false;
            }
        } catch (PDOException $e) {
            // DBエラー時は安全側（ログイン扱いにしない）。セッションは残すので、DB復旧後は続きから使える
            error_log('Login slot check error: ' . $e->getMessage());
            return false;
        }
    }

    // 4. 有効期限内であれば最終操作時刻を現在時刻に更新してログイン状態を維持
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * 画面遷移して処理を終える
 *
 * @param string $url 遷移先URL
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * 未ログイン時にログイン画面へリダイレクトする
 *
 * @param string|null $returnTo ログイン成功後に戻したい画面パス（例: 'purchase.php?productId=123'）
 */
function redirect_to_login(?string $returnTo = null): void {
    if (!empty($returnTo)) {
        $_SESSION['redirect_after_login'] = $returnTo;
    }
    $url = defined('URL_LOGIN') ? URL_LOGIN : 'login.php';
    redirect($url);
}

/**
 * 現在ログイン中のユーザーIDを取得する（未ログイン時は null）
 *
 * @return int|null
 */
function current_user_id(): ?int {
    if (isset($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }
    if (isset($_SESSION['UserId'])) {
        return (int)$_SESSION['UserId'];
    }
    return null;
}

/**
 * ログイン中ユーザーIDを取得する（未ログイン時は例外をスロー）
 * 既存画面（purchase.php, purchase_confirm.php）との互換用
 *
 * @return int
 */
function login_user_id(): int {
    $userId = current_user_id();
    if ($userId === null) {
        throw new LogicException('ログイン中のユーザーが存在しません。');
    }
    return $userId;
}

/**
 * 現在ログイン中のユーザー名（氏名）を取得する
 * （購入時の BuyerName 登録やマイページ表示用）
 *
 * @return string|null
 */
function current_user_name(): ?string {
    if (isset($_SESSION['user_name'])) {
        return (string)$_SESSION['user_name'];
    }
    if (isset($_SESSION['UserName'])) {
        return (string)$_SESSION['UserName'];
    }
    return null;
}

/**
 * Sales.BuyerName に入れる値
 *
 * 名前は同姓同名がありえて「自分が買った商品」を探せないため、ユーザーIDを文字列で入れる。
 * チームで「名前を入れる」と決まった場合はここだけ変える。
 * 状況確認画面などで購入者を探すときも、この関数で同じ値を作って WHERE BuyerName = ? で検索する。
 */
function buyer_value(int $userId): string {
    return (string)$userId;
}

/**
 * ログアウト処理（セッション情報の初期化）
 */
function logout_session(): void {
    // 同時ログイン禁止の枠を解放する（このセッションが持っている枠だけ。他の端末の枠は消さない）
    $userId = current_user_id();
    $token  = $_SESSION['login_token'] ?? null;
    if ($userId !== null && is_string($token) && $token !== '') {
        release_login_slot($userId, $token);
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
    session_start();
}

/**
 * セッションタイムアウトの秒数を返す
 * SystemConfiguration (Category='Session', Key='SessionTimeout', 単位: 分) を参照し、
 * 取得できないときは 1800 秒（30分）
 */
function get_session_timeout_seconds(): int {
    $timeout = 30 * 60;
    if (function_exists('db')) {
        try {
            $pdo = db();
            $sql = "SELECT TOP (1) [Value]
                    FROM dbo.SystemConfiguration
                    WHERE Category = :category AND [Key] = :config_key";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':category'   => 'Session',
                ':config_key' => 'SessionTimeout',
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row !== false && is_numeric($row['Value']) && (int)$row['Value'] > 0) {
                $timeout = (int)$row['Value'] * 60;
            }
        } catch (PDOException $e) {
            // DB接続エラーやテーブル未作成時はデフォルト値（1800秒）を使用
            error_log('Session timeout config fetch error: ' . $e->getMessage());
        }
    }
    return $timeout;
}

// ============================================================
// 同時ログイン禁止（UserSession テーブル）
//
//   1ユーザーにつき有効なセッションは1つだけ。
//   UserSession に行があり、最終操作からタイムアウト内なら「ログイン中」とみなし、
//   他の端末からのログインは拒否する（先にログインした方が優先）。
//   時刻の比較はすべて DB の GETDATE() で行う（PHP と DB の時計ずれ対策）。
// ============================================================

/**
 * ログインの枠を取得する（ログイン認証が成功した後に呼ぶ）
 *
 * @param PDO $pdo         DB接続
 * @param int $userId      ログインするユーザーID
 * @param int $timeoutSec  セッションタイムアウト秒数（get_session_timeout_seconds()）
 * @return string|null     取得できたら新しいセッショントークン。他の端末がログイン中なら null
 * @throws PDOException    DBエラー時
 */
function acquire_login_slot(PDO $pdo, int $userId, int $timeoutSec): ?string {
    $token = bin2hex(random_bytes(32)); // 64文字

    $pdo->beginTransaction();
    try {
        // 行をロックして判定する（ほぼ同時の2つのログインを順番に処理するため）
        $stmt = $pdo->prepare(
            'SELECT CASE WHEN DATEDIFF(SECOND, LastActivityTime, GETDATE()) <= :timeout
                         THEN 1 ELSE 0 END AS IsActive
             FROM dbo.UserSession WITH (UPDLOCK, HOLDLOCK)
             WHERE Userid = :user_id'
        );
        $stmt->bindValue(':timeout', $timeoutSec, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row !== false && (int)$row['IsActive'] === 1) {
            // 他の端末がログイン中
            $pdo->rollBack();
            return null;
        }

        if ($row !== false) {
            // 期限切れの古い行を新しいセッションで置き換える
            $stmt = $pdo->prepare(
                'UPDATE dbo.UserSession
                 SET SessionToken = :token, LoginDateTime = GETDATE(), LastActivityTime = GETDATE()
                 WHERE Userid = :user_id'
            );
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO dbo.UserSession (Userid, SessionToken, LoginDateTime, LastActivityTime)
                 VALUES (:user_id, :token, GETDATE(), GETDATE())'
            );
        }
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $pdo->commit();
        return $token;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // 主キー重複（ほぼ同時に別の端末が先に登録した）は「ログイン中」と同じ扱い
        if ($e->getCode() === '23000') {
            return null;
        }
        throw $e;
    }
}

/**
 * このセッションが正規のログインか確認し、DB側の最終操作時刻を更新する
 * （確認と更新を1回のUPDATEで行う）
 *
 * @return bool 正規のセッションなら true
 * @throws PDOException DBエラー時
 */
function touch_login_slot(int $userId, string $token): bool {
    if ($token === '') {
        return false;
    }
    $stmt = db()->prepare(
        'UPDATE dbo.UserSession
         SET LastActivityTime = GETDATE()
         WHERE Userid = :user_id AND SessionToken = :token'
    );
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':token', $token, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->rowCount() === 1;
}

/**
 * ログインの枠を解放する（失敗してもログアウト等の処理は止めない）
 *
 * @param int         $userId ユーザーID
 * @param string|null $token  指定すると、トークンが一致する行だけ削除する。
 *                            null なら、そのユーザーの行を無条件に削除する（パスワード再設定用）
 */
function release_login_slot(int $userId, ?string $token = null): void {
    try {
        if ($token === null) {
            $stmt = db()->prepare('DELETE FROM dbo.UserSession WHERE Userid = :user_id');
        } else {
            $stmt = db()->prepare(
                'DELETE FROM dbo.UserSession WHERE Userid = :user_id AND SessionToken = :token'
            );
            $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        }
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log('Login slot release error: ' . $e->getMessage());
    }
}

/**
 * 開発用の自動ログインで入ったセッション（login_token を持たない）か判定する
 * 開発ログインが有効で、セッションのユーザーが DEV_LOGIN_USER_ID と同じ場合のみ true
 * （本番では DEV_LOGIN_USER_ID = 0 のため常に false）
 */
function is_dev_session_without_login_token(): bool {
    if (!empty($_SESSION['login_token'])) {
        return false;
    }
    $devUserId = development_login_user_id();
    return $devUserId !== null && current_user_id() === $devUserId;
}

/**
 * 開発用の自動ログインに使うユーザーIDを返す（有効な場合のみ）
 */
function development_login_user_id(): ?int {
    if (!defined('APP_DEBUG') || APP_DEBUG !== true) {
        return null;
    }
    if (!defined('DEV_LOGIN_USER_ID') || !is_numeric(DEV_LOGIN_USER_ID) || (int)DEV_LOGIN_USER_ID <= 0) {
        return null;
    }

    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($remoteAddr !== '127.0.0.1' && $remoteAddr !== '::1') {
        return null;
    }

    return (int)DEV_LOGIN_USER_ID;
}

/**
 * CSRFトークンを生成・取得する（フォームのhidden項目用）
 *
 * @return string
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * POST送信されたCSRFトークンを検証する（引数省略時は $_POST['csrf_token'] を使用）
 * 一度検証したトークンは破棄する（二重送信防止）
 *
 * @param string|null $token 検証するトークン（省略時は $_POST['csrf_token']）
 * @return bool トークンが正しければ true、不正なら false
 */
function check_csrf_token(?string $token = null): bool {
    $token = $token ?? $_POST['csrf_token'] ?? null;
    $saved = $_SESSION['csrf_token'] ?? '';

    // 二重送信対策として検証時にトークンを破棄
    unset($_SESSION['csrf_token']);

    if (empty($saved) || !is_string($token) || !hash_equals($saved, $token)) {
        return false;
    }
    return true;
}

/**
 * フラッシュメッセージ（次画面で1回だけ表示するメッセージ）をセットする
 *
 * @param string $message 表示するメッセージ
 * @param string $type メッセージ種別 (info / success / warning / error)
 */
function set_flash(string $message, string $type = 'info'): void {
    $_SESSION['flash'] = [
        'message' => $message,
        'type'    => $type,
    ];
}

/**
 * フラッシュメッセージを取得し、セッションから削除する
 *
 * @return array|null ['message' => string, 'type' => string]
 */
function get_flash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * 秘密の質問リストを取得する（パスワード再設定用）
 * アカウント登録画面（register.php / register_check.php）と
 * パスワード再設定画面（passreset.php）の双方から参照する
 *
 * @return array<int, string> [質問ID(SecretQuestionId) => 質問文]
 */
function get_secret_questions(): array {
    return [
        1 => '出身小学校の名前は？',
        2 => '初めて飼ったペットの名前は？',
        3 => '母親の旧姓は？',
        4 => '好きな食べ物は？',
        5 => '思い出の場所は？',
    ];
}
