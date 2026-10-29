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
                // 行が無い・トークンが違う（別の端末でログインされ、このセッションが引き継がれた等）
                logout_session();
                set_flash('別の端末でログインされたため、ログアウトしました。', 'error');
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

// ============================================================
// 管理者ログイン（利用者の UserData / UserSession とは別）
//
//   アカウントは AdminAccount。セッションは $_SESSION['admin'] にまとめる。
//   利用者の logout_session() はこの配列を残す。
//   CSRF とフラッシュもこの中に置き、利用者画面と取り違えない。
// ============================================================

/**
 * $_SESSION['admin'] を配列として用意する
 */
function ensure_admin_session(): void {
    if (!isset($_SESSION['admin']) || !is_array($_SESSION['admin'])) {
        $_SESSION['admin'] = [];
    }
}

/**
 * 管理者ログイン後の戻り先（自サイトの .php だけ。管理者ログイン画面自身は不可）
 */
function admin_return_url(string $url): string {
    if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.php(\?[a-zA-Z0-9_\-\.=&]+)?$/', $url)) {
        return 'setting.php';
    }
    if (preg_match('/^admin_login\.php(\?|$)/', $url)) {
        return 'setting.php';
    }
    return $url;
}

/**
 * 管理者としてログイン中かつ有効期限内か
 * 利用者のログイン状態は見ない
 *
 * @return bool
 */
function is_admin(): bool {
    $admin = $_SESSION['admin'] ?? null;
    if (!is_array($admin) || empty($admin['id']) || empty($admin['login_token']) || empty($admin['last_activity'])) {
        return false;
    }

    $timeout = get_session_timeout_seconds();
    if ((time() - (int)$admin['last_activity']) > $timeout) {
        logout_admin();
        set_admin_flash('セッションの有効期限が切れました。再度ログインしてください。', 'error');
        return false;
    }

    try {
        if (!touch_admin_login_slot((int)$admin['id'], (string)$admin['login_token'])) {
            logout_admin();
            set_admin_flash('別の端末でログインされたため、ログアウトしました。', 'error');
            return false;
        }
    } catch (PDOException $e) {
        // DBエラー時は安全側（管理者ログイン扱いにしない）。セッションは残す
        error_log('Admin login slot check error: ' . $e->getMessage());
        return false;
    }

    $_SESSION['admin']['last_activity'] = time();
    return true;
}

/**
 * 管理画面の先頭で呼ぶ
 * 管理者としてログインしていなければ管理者ログイン画面へ（ログイン後は $returnTo に戻す）
 *
 * @param string $returnTo ログイン成功後に戻す画面パス
 */
function require_admin(string $returnTo): void {
    if (!is_admin()) {
        redirect_to_admin_login($returnTo);
    }
}

/**
 * 未ログインの管理者を管理者ログイン画面へリダイレクトする
 *
 * @param string|null $returnTo ログイン成功後に戻したい画面パス
 */
function redirect_to_admin_login(?string $returnTo = null): void {
    ensure_admin_session();
    if ($returnTo !== null && $returnTo !== '') {
        $_SESSION['admin']['redirect_after_login'] = admin_return_url($returnTo);
    }
    redirect('admin_login.php');
}

/**
 * 管理者画面用のCSRFトークンを生成・取得する
 */
function admin_csrf_token(): string {
    ensure_admin_session();
    if (empty($_SESSION['admin']['csrf_token']) || !is_string($_SESSION['admin']['csrf_token'])) {
        $_SESSION['admin']['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin']['csrf_token'];
}

/**
 * 管理者画面のCSRFトークンを検証する（引数省略時は $_POST['csrf_token']）
 * 一度検証したトークンは破棄する（二重送信防止）。利用者側のトークンは触らない
 */
function check_admin_csrf_token(?string $token = null): bool {
    $token = $token ?? $_POST['csrf_token'] ?? null;
    $saved = '';
    if (isset($_SESSION['admin']) && is_array($_SESSION['admin'])) {
        $saved = $_SESSION['admin']['csrf_token'] ?? '';
        unset($_SESSION['admin']['csrf_token']);
    }

    if (!is_string($saved) || $saved === '' || !is_string($token) || !hash_equals($saved, $token)) {
        return false;
    }
    return true;
}

/**
 * 管理者画面用のフラッシュメッセージをセットする
 */
function set_admin_flash(string $message, string $type = 'info'): void {
    ensure_admin_session();
    $_SESSION['admin']['flash'] = [
        'message' => $message,
        'type'    => $type,
    ];
}

/**
 * 管理者画面用のフラッシュメッセージを取得し、セッションから削除する
 *
 * @return array|null ['message' => string, 'type' => string]
 */
function get_admin_flash(): ?array {
    if (!isset($_SESSION['admin']) || !is_array($_SESSION['admin'])) {
        return null;
    }
    $flash = $_SESSION['admin']['flash'] ?? null;
    unset($_SESSION['admin']['flash']);
    return is_array($flash) ? $flash : null;
}

/**
 * 管理者ログアウト（利用者のセッションは残す）
 */
function logout_admin(): void {
    ensure_admin_session();
    $adminId = isset($_SESSION['admin']['id']) ? (int)$_SESSION['admin']['id'] : 0;
    $token   = $_SESSION['admin']['login_token'] ?? null;
    if ($adminId > 0 && is_string($token) && $token !== '') {
        release_admin_login_slot($adminId, $token);
    }

    unset(
        $_SESSION['admin']['id'],
        $_SESSION['admin']['login_id'],
        $_SESSION['admin']['name'],
        $_SESSION['admin']['login_token'],
        $_SESSION['admin']['last_activity'],
        $_SESSION['admin']['pending_id'],
        $_SESSION['admin']['pending_at'],
        $_SESSION['admin']['redirect_after_login']
    );
}

/**
 * 管理者のログイン枠を取得する（パスワード認証の成功後に呼ぶ）
 *
 * @param bool $force true なら、他の端末がログイン中でも新しいセッションで置き換える
 * @return string|null 取得できたらセッショントークン。$force = false で他端末がログイン中なら null
 * @throws PDOException DBエラー時
 */
function acquire_admin_login_slot(PDO $pdo, int $adminId, int $timeoutSec, bool $force = false): ?string {
    $token = bin2hex(random_bytes(32));

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT CASE WHEN DATEDIFF(SECOND, LastActivityTime, GETDATE()) <= :timeout
                         THEN 1 ELSE 0 END AS IsActive
             FROM dbo.AdminSession WITH (UPDLOCK, HOLDLOCK)
             WHERE AdminId = :admin_id'
        );
        $stmt->bindValue(':timeout', $timeoutSec, PDO::PARAM_INT);
        $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$force && $row !== false && (int)$row['IsActive'] === 1) {
            $pdo->rollBack();
            return null;
        }

        if ($row !== false) {
            $stmt = $pdo->prepare(
                'UPDATE dbo.AdminSession
                 SET SessionToken = :token, LoginDateTime = GETDATE(), LastActivityTime = GETDATE()
                 WHERE AdminId = :admin_id'
            );
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO dbo.AdminSession (AdminId, SessionToken, LoginDateTime, LastActivityTime)
                 VALUES (:admin_id, :token, GETDATE(), GETDATE())'
            );
        }
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmt->execute();

        $pdo->commit();
        return $token;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000' && !$force) {
            return null;
        }
        throw $e;
    }
}

/**
 * このセッションが正規の管理者ログインか確認し、DB側の最終操作時刻を更新する
 *
 * @throws PDOException DBエラー時
 */
function touch_admin_login_slot(int $adminId, string $token): bool {
    if ($token === '') {
        return false;
    }
    $stmt = db()->prepare(
        'UPDATE dbo.AdminSession
         SET LastActivityTime = GETDATE()
         WHERE AdminId = :admin_id AND SessionToken = :token'
    );
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->bindValue(':token', $token, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->rowCount() === 1;
}

/**
 * 管理者のログイン枠を解放する（失敗してもログアウト処理は止めない）
 */
function release_admin_login_slot(int $adminId, string $token): void {
    try {
        $stmt = db()->prepare(
            'DELETE FROM dbo.AdminSession WHERE AdminId = :admin_id AND SessionToken = :token'
        );
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log('Admin login slot release error: ' . $e->getMessage());
    }
}

/**
 * 管理者ログインのロック解除時刻を警告文にする（パスワード再設定の導線は出さない）
 */
function format_admin_login_lock_message(string $lockedUntil): string {
    $unlockAt = lock_until_hhmm($lockedUntil);

    return "アカウントはロックされました。\n"
        . "再度ログインを行うには、{$unlockAt}までお待ちください。";
}

/**
 * いま管理者ログインをロック中なら警告文を返す。ロックなしなら null
 *
 * @throws PDOException DBエラー時
 */
function active_admin_login_lock_message(PDO $pdo, int $adminId): ?string {
    $stmt = $pdo->prepare(
        'SELECT CONVERT(VARCHAR(19), LockedUntil, 120) AS LockedUntil
         FROM dbo.AdminLoginLock
         WHERE AdminId = :admin_id
           AND LockedUntil IS NOT NULL
           AND LockedUntil > GETDATE()'
    );
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->execute();
    $lockedUntil = $stmt->fetchColumn();
    if ($lockedUntil === false || $lockedUntil === null || $lockedUntil === '') {
        return null;
    }

    return format_admin_login_lock_message((string)$lockedUntil);
}

/**
 * 管理者のパスワード不一致を1回記録する
 * 3回目でロックし、ロック中（今回ロックした場合を含む）は警告文を返す。まだロックしないときは null
 *
 * @throws PDOException DBエラー時
 */
function register_admin_login_failure(PDO $pdo, int $adminId): ?string {
    $minutes = (int)LOGIN_LOCK_MINUTES;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT FailCount,
                    CONVERT(VARCHAR(19), LockedUntil, 120) AS LockedUntil,
                    CASE WHEN LockedUntil IS NOT NULL AND LockedUntil > GETDATE()
                         THEN 1 ELSE 0 END AS IsLocked
             FROM dbo.AdminLoginLock WITH (UPDLOCK, HOLDLOCK)
             WHERE AdminId = :admin_id'
        );
        $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row !== false && (int)$row['IsLocked'] === 1) {
            $pdo->commit();
            return format_admin_login_lock_message((string)$row['LockedUntil']);
        }

        $failCount = 0;
        if ($row !== false && ($row['LockedUntil'] === null || $row['LockedUntil'] === '')) {
            $failCount = (int)$row['FailCount'];
        }
        $failCount++;

        if ($failCount >= LOGIN_FAIL_LIMIT) {
            if ($row === false) {
                $stmt = $pdo->prepare(
                    "INSERT INTO dbo.AdminLoginLock (AdminId, FailCount, LockedUntil)
                     VALUES (:admin_id, 0, DATEADD(MINUTE, {$minutes}, GETDATE()))"
                );
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE dbo.AdminLoginLock
                     SET FailCount = 0,
                         LockedUntil = DATEADD(MINUTE, {$minutes}, GETDATE())
                     WHERE AdminId = :admin_id"
                );
            }
            $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $pdo->prepare(
                'SELECT CONVERT(VARCHAR(19), LockedUntil, 120) AS LockedUntil
                 FROM dbo.AdminLoginLock
                 WHERE AdminId = :admin_id'
            );
            $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
            $stmt->execute();
            $lockedUntil = (string)$stmt->fetchColumn();
            $pdo->commit();
            return format_admin_login_lock_message($lockedUntil);
        }

        if ($row === false) {
            $stmt = $pdo->prepare(
                'INSERT INTO dbo.AdminLoginLock (AdminId, FailCount, LockedUntil)
                 VALUES (:admin_id, :fail_count, NULL)'
            );
        } else {
            $stmt = $pdo->prepare(
                'UPDATE dbo.AdminLoginLock
                 SET FailCount = :fail_count,
                     LockedUntil = NULL
                 WHERE AdminId = :admin_id'
            );
        }
        $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':fail_count', $failCount, PDO::PARAM_INT);
        $stmt->execute();
        $pdo->commit();
        return null;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * 管理者ログインの失敗回数とロックを消す
 *
 * @throws PDOException DBエラー時
 */
function clear_admin_login_lock(PDO $pdo, int $adminId): void {
    $stmt = $pdo->prepare('DELETE FROM dbo.AdminLoginLock WHERE AdminId = :admin_id');
    $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
    $stmt->execute();
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
    // 管理者ログインは、利用者のログアウトやタイムアウトでは消さない
    $adminSession = (isset($_SESSION['admin']) && is_array($_SESSION['admin'])) ? $_SESSION['admin'] : null;

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

    if ($adminSession !== null) {
        $_SESSION['admin'] = $adminSession;
    }
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
//   UserSession に行があり、最終操作からタイムアウト内なら「ログイン中」とみなす。
//   ログイン中のアカウントに別の端末からログインするときは、確認画面を出し、
//   了承されたら新しい端末を有効にして、古い端末のセッションを無効にする（後から
//   ログインした方が優先）。無効になった端末は、次の操作でログアウトされる。
//   時刻の比較はすべて DB の GETDATE() で行う（PHP と DB の時計ずれ対策）。
// ============================================================

/**
 * ログインの枠を取得する（ログイン認証が成功した後に呼ぶ）
 *
 * @param PDO $pdo         DB接続
 * @param int $userId      ログインするユーザーID
 * @param int  $timeoutSec  セッションタイムアウト秒数（get_session_timeout_seconds()）
 * @param bool $force       true なら、他の端末がログイン中でも新しいセッションで置き換える
 *                          （古い端末のトークンは無効になる。確認画面で了承された後に使う）
 * @return string|null      取得できたら新しいセッショントークン。
 *                          $force = false で他の端末がログイン中なら null
 * @throws PDOException     DBエラー時
 */
function acquire_login_slot(PDO $pdo, int $userId, int $timeoutSec, bool $force = false): ?string {
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

        if (!$force && $row !== false && (int)$row['IsActive'] === 1) {
            // 他の端末がログイン中（呼び出し側で確認画面を出す）
            $pdo->rollBack();
            return null;
        }

        if ($row !== false) {
            // 古い行（期限切れ、または了承済みの引き継ぎ）を新しいセッションで置き換える
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
        // 主キー重複（ほぼ同時に別の端末が先に登録した）は「ログイン中」と同じ扱い（確認画面へ）
        if ($e->getCode() === '23000' && !$force) {
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
 * トークンが一致する行だけ削除する（引き継がれて無効になった古い端末が、新しい端末の枠を消さないため）
 *
 * @param int    $userId ユーザーID
 * @param string $token  このセッションのトークン
 */
function release_login_slot(int $userId, string $token): void {
    try {
        $stmt = db()->prepare(
            'DELETE FROM dbo.UserSession WHERE Userid = :user_id AND SessionToken = :token'
        );
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log('Login slot release error: ' . $e->getMessage());
    }
}

// ============================================================
// 失敗ロック（UserLoginLock / UserResetLock）
//
//   ログイン: 存在するユーザーIDでパスワードを3回連続で間違えると、10分間ログインできない。
//   再設定:   存在するユーザーIDで秘密の質問または回答を3回連続で間違えると、10分間照合できない。
//   2つは別テーブル。ログインがロック中でも、秘密の質問からの再設定はできる。
//   期限の計算と比較は GETDATE() で行う（PHP と DB の時計ずれ対策）。
//   ロック中の再送信では回数を増やさず、期限も延ばさない。
//   認証が通ったとき、およびパスワード再設定が成功したときに行を削除する。
//   期限切れ後の失敗は 1 回目から数え直す。
// ============================================================

const LOGIN_FAIL_LIMIT = 3;
const LOGIN_LOCK_MINUTES = 10;
const RESET_FAIL_LIMIT = 3;
const RESET_LOCK_MINUTES = 10;

/**
 * ロック用テーブル名を返す（呼び出し側の文字列を SQL に埋め込まない）
 */
function lock_table_name(string $table): string {
    if ($table !== 'UserLoginLock' && $table !== 'UserResetLock') {
        throw new InvalidArgumentException('Unknown lock table');
    }
    return 'dbo.' . $table;
}

/**
 * CONVERT(..., 120) の "YYYY-MM-DD HH:MM:SS" から hh:mm を取る
 */
function lock_until_hhmm(string $lockedUntil): string {
    $unlockAt = substr($lockedUntil, 11, 5);
    if (!preg_match('/^\d{2}:\d{2}$/', $unlockAt)) {
        $timestamp = strtotime($lockedUntil);
        $unlockAt = $timestamp === false ? '' : date('H:i', $timestamp);
    }
    return $unlockAt;
}

/**
 * ロック解除時刻を、ログイン画面の警告文にする
 * $lockedUntil は CONVERT(..., 120) の "YYYY-MM-DD HH:MM:SS"
 */
function format_login_lock_message(string $lockedUntil): string {
    $unlockAt = lock_until_hhmm($lockedUntil);

    return "アカウントはロックされました。\n"
        . "再度ログインを行うには、{$unlockAt}までお待ちください。\n"
        . "パスワードを忘れた方は再設定をお願いします。";
}

/**
 * ロック解除時刻を、パスワード再設定（秘密の質問）の警告文にする
 */
function format_reset_lock_message(string $lockedUntil): string {
    $unlockAt = lock_until_hhmm($lockedUntil);

    return "アカウントはロックされました。\n"
        . "再度秘密の質問の回答を行うには、{$unlockAt}までお待ちください。";
}

/**
 * いまロック中なら警告文を返す。ロックなしなら null
 *
 * @param callable(string): string $formatMessage
 * @throws PDOException DBエラー時
 */
function active_lock_message(PDO $pdo, string $table, int $userId, callable $formatMessage): ?string {
    $lockTable = lock_table_name($table);
    $stmt = $pdo->prepare(
        "SELECT CONVERT(VARCHAR(19), LockedUntil, 120) AS LockedUntil
         FROM {$lockTable}
         WHERE Userid = :user_id
           AND LockedUntil IS NOT NULL
           AND LockedUntil > GETDATE()"
    );
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $lockedUntil = $stmt->fetchColumn();
    if ($lockedUntil === false || $lockedUntil === null || $lockedUntil === '') {
        return null;
    }

    return $formatMessage((string)$lockedUntil);
}

/**
 * 失敗を1回記録する
 *
 * 3回目でロックし、ロック中（今回ロックした場合を含む）は警告文を返す。
 * まだロックしないときは null。
 *
 * @param callable(string): string $formatMessage
 * @throws PDOException DBエラー時
 */
function register_lock_failure(PDO $pdo, string $table, int $userId, int $failLimit, int $lockMinutes, callable $formatMessage): ?string {
    $lockTable = lock_table_name($table);
    $minutes = (int)$lockMinutes;
    $pdo->beginTransaction();
    try {
        // 同じユーザーへの同時送信を順番に処理する
        $stmt = $pdo->prepare(
            "SELECT FailCount,
                    CONVERT(VARCHAR(19), LockedUntil, 120) AS LockedUntil,
                    CASE WHEN LockedUntil IS NOT NULL AND LockedUntil > GETDATE()
                         THEN 1 ELSE 0 END AS IsLocked
             FROM {$lockTable} WITH (UPDLOCK, HOLDLOCK)
             WHERE Userid = :user_id"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row !== false && (int)$row['IsLocked'] === 1) {
            $pdo->commit();
            return $formatMessage((string)$row['LockedUntil']);
        }

        // 期限切れのロックが残っているときは、回数を1回目から数え直す
        $failCount = 0;
        if ($row !== false && ($row['LockedUntil'] === null || $row['LockedUntil'] === '')) {
            $failCount = (int)$row['FailCount'];
        }
        $failCount++;

        if ($failCount >= $failLimit) {
            if ($row === false) {
                $stmt = $pdo->prepare(
                    "INSERT INTO {$lockTable} (Userid, FailCount, LockedUntil)
                     VALUES (:user_id, 0, DATEADD(MINUTE, {$minutes}, GETDATE()))"
                );
            } else {
                $stmt = $pdo->prepare(
                    "UPDATE {$lockTable}
                     SET FailCount = 0,
                         LockedUntil = DATEADD(MINUTE, {$minutes}, GETDATE())
                     WHERE Userid = :user_id"
                );
            }
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $pdo->prepare(
                "SELECT CONVERT(VARCHAR(19), LockedUntil, 120) AS LockedUntil
                 FROM {$lockTable}
                 WHERE Userid = :user_id"
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $lockedUntil = (string)$stmt->fetchColumn();
            $pdo->commit();
            return $formatMessage($lockedUntil);
        }

        if ($row === false) {
            $stmt = $pdo->prepare(
                "INSERT INTO {$lockTable} (Userid, FailCount, LockedUntil)
                 VALUES (:user_id, :fail_count, NULL)"
            );
        } else {
            $stmt = $pdo->prepare(
                "UPDATE {$lockTable}
                 SET FailCount = :fail_count,
                     LockedUntil = NULL
                 WHERE Userid = :user_id"
            );
        }
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':fail_count', $failCount, PDO::PARAM_INT);
        $stmt->execute();
        $pdo->commit();
        return null;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * 失敗回数とロックを消す
 *
 * @throws PDOException DBエラー時
 */
function clear_lock(PDO $pdo, string $table, int $userId): void {
    $lockTable = lock_table_name($table);
    $stmt = $pdo->prepare("DELETE FROM {$lockTable} WHERE Userid = :user_id");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * いまログインをロック中なら警告文を返す。ロックなしなら null
 *
 * @throws PDOException DBエラー時
 */
function active_login_lock_message(PDO $pdo, int $userId): ?string {
    return active_lock_message($pdo, 'UserLoginLock', $userId, 'format_login_lock_message');
}

/**
 * パスワード不一致を1回記録する
 *
 * 3回目でロックし、ロック中（今回ロックした場合を含む）は警告文を返す。
 * まだロックしないときは null。
 *
 * @throws PDOException DBエラー時
 */
function register_login_failure(PDO $pdo, int $userId): ?string {
    return register_lock_failure(
        $pdo,
        'UserLoginLock',
        $userId,
        LOGIN_FAIL_LIMIT,
        LOGIN_LOCK_MINUTES,
        'format_login_lock_message'
    );
}

/**
 * ログインの失敗回数とロックを消す（パスワード一致、またはパスワード再設定の成功時）
 *
 * @throws PDOException DBエラー時
 */
function clear_login_lock(PDO $pdo, int $userId): void {
    clear_lock($pdo, 'UserLoginLock', $userId);
}

/**
 * パスワード最小文字数の変更後、まだ再設定していない利用者か
 *
 * @throws PDOException DBエラー時
 */
function user_must_reset_password(PDO $pdo, int $userId): bool {
    $stmt = $pdo->prepare(
        'SELECT PasswordResetRequired FROM dbo.UserData WHERE Userid = :user_id'
    );
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    return (int)$stmt->fetchColumn() === 1;
}

/**
 * パスワード規則の変更を知らせ、再設定画面へ送る（ショップのログインは成立させない）
 */
function redirect_to_password_reset_for_policy_change(): void {
    set_flash(
        'サービスのパスワード規則が変更されました。<br>お手数ですがパスワードの再設定をお願いします。',
        'warning'
    );
    redirect('passreset.php');
}

/**
 * いま秘密の質問の照合をロック中なら警告文を返す。ロックなしなら null
 *
 * @throws PDOException DBエラー時
 */
function active_reset_lock_message(PDO $pdo, int $userId): ?string {
    return active_lock_message($pdo, 'UserResetLock', $userId, 'format_reset_lock_message');
}

/**
 * 秘密の質問または回答の不一致を1回記録する
 *
 * 3回目でロックし、ロック中（今回ロックした場合を含む）は警告文を返す。
 * まだロックしないときは null。
 *
 * @throws PDOException DBエラー時
 */
function register_reset_failure(PDO $pdo, int $userId): ?string {
    return register_lock_failure(
        $pdo,
        'UserResetLock',
        $userId,
        RESET_FAIL_LIMIT,
        RESET_LOCK_MINUTES,
        'format_reset_lock_message'
    );
}

/**
 * 秘密の質問の失敗回数とロックを消す（回答一致、またはパスワード再設定の成功時）
 *
 * @throws PDOException DBエラー時
 */
function clear_reset_lock(PDO $pdo, int $userId): void {
    clear_lock($pdo, 'UserResetLock', $userId);
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
