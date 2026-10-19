<?php
// ============================================================
// セッション・ログイン関連の共通処理
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** ログイン済みか。セッションの有効期限もここで確認する */
function is_logged_in(): bool
{
    $userId = $_SESSION['user_id'] ?? $_SESSION['UserId'] ?? null;
    if ($userId === null && !isset($_SESSION['logged_in'])) {
        $userId = development_login_user_id();
        if ($userId !== null) {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $userId;
            $_SESSION['last_activity'] = time();
        }
    }

    if (
        (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] !== true)
        || filter_var($userId, FILTER_VALIDATE_INT) === false
        || (int)$userId <= 0
    ) {
        return false;
    }

    $lastActivity = $_SESSION['last_activity'] ?? null;
    if (!is_numeric($lastActivity)) {
        clear_login_session();
        return false;
    }

    $now = time();
    if ($now - (int)$lastActivity >= session_timeout_seconds()) {
        clear_login_session();
        return false;
    }

    $_SESSION['logged_in'] = true;
    $_SESSION['user_id'] = (int)$userId;
    $_SESSION['user_name'] = (string)($_SESSION['user_name'] ?? $_SESSION['UserName'] ?? '');
    $_SESSION['last_activity'] = $now;

    return true;
}

/** ログイン中のユーザーID */
function current_user_id(): int
{
    if (!is_logged_in()) {
        throw new LogicException('ログイン中のユーザーがありません。');
    }

    $userId = $_SESSION['user_id'] ?? $_SESSION['UserId'] ?? null;
    if (filter_var($userId, FILTER_VALIDATE_INT) === false || (int)$userId <= 0) {
        throw new LogicException('ログイン中のユーザーがありません。');
    }

    return (int)$userId;
}

/** 既存コード向けのユーザーID取得名 */
function login_user_id(): int
{
    return current_user_id();
}

/** ログイン中のユーザー名 */
function current_user_name(): string
{
    $userId = current_user_id();
    $userName = $_SESSION['user_name'] ?? $_SESSION['UserName'] ?? '';
    if ($userName !== '') {
        return (string)$userName;
    }

    $stmt = db()->prepare('SELECT UserName FROM UserData WHERE Userid = :userId');
    $stmt->execute([':userId' => $userId]);
    $user = $stmt->fetch();
    if (!$user || !isset($user['UserName'])) {
        throw new LogicException('ログイン中のユーザー名を取得できません。');
    }

    $_SESSION['user_name'] = (string)$user['UserName'];
    return $_SESSION['user_name'];
}

/** セッション設定から有効時間（秒）を取得する */
function session_timeout_seconds(): int
{
    static $timeoutSeconds = null;
    if ($timeoutSeconds !== null) {
        return $timeoutSeconds;
    }

    $stmt = db()->prepare(
        "SELECT Value
         FROM SystemConfiguration
         WHERE Category = :category AND [Key] = :settingKey"
    );
    $stmt->execute([
        ':category' => 'Session',
        ':settingKey' => 'SessionTimeout',
    ]);
    $value = $stmt->fetchColumn();
    $minutes = filter_var($value, FILTER_VALIDATE_INT);
    if ($minutes === false || $minutes <= 0) {
        throw new RuntimeException('SystemConfiguration.SessionTimeout に正の整数（分）を設定してください。');
    }

    $timeoutSeconds = $minutes * 60;
    return $timeoutSeconds;
}

/** 認証状態を破棄する（次の画面でflashを設定できるようセッション自体は維持） */
function clear_login_session(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/** ローカル開発用の固定ユーザーID（本番環境では常に無効） */
function development_login_user_id(): ?int
{
    if (
        !defined('APP_DEBUG')
        || !APP_DEBUG
        || !defined('DEV_LOGIN_USER_ID')
        || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
    ) {
        return null;
    }

    $userId = (int)DEV_LOGIN_USER_ID;
    return $userId > 0 ? $userId : null;
}

/**
 * Sales.BuyerName に入れる値
 *
 * 名前は同姓同名がありえて「自分が買った商品」を探せないため、ユーザーIDを文字列で入れる。
 * チームで「名前を入れる」と決まった場合はここだけ変える。
 * 状況確認画面などで購入者を探すときも、この関数で同じ値を作って WHERE BuyerName = ? で検索する。
 */
function buyer_value(int $userId): string
{
    return (string)$userId;
}

/** 画面遷移して処理を終える */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** ログイン画面へ遷移する（$return があればログイン後そこへ戻る） */
function redirect_to_login(?string $return = null): void
{
    $url = URL_LOGIN;
    if ($return !== null) {
        $url .= '?return=' . urlencode($return);
    }
    redirect($url);
}

/** 次の画面で1回だけ表示するメッセージを保存する（type: info / success / warning / error） */
function set_flash(string $message, string $type = 'info'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

/** 保存したメッセージを取り出す（取り出したら消える） */
function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/** CSRFトークン（二重送信対策も兼ねる）を発行する */
function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** CSRFトークンを確認する（1回使ったら無効にする） */
function check_csrf_token($token = null): bool
{
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? null;
    }

    $saved = $_SESSION['csrf_token'] ?? '';
    unset($_SESSION['csrf_token']);
    return is_string($token) && $saved !== '' && hash_equals($saved, $token);
}
