<?php
// ============================================================
// セッション・ログイン関連の共通処理
//
// ログイン担当と合わせる前提:
//   ログイン成功時に $_SESSION['user_id'] / $_SESSION['user_name'] を入れる
//   セッションの有効期限切れ（タイムアウト）はログイン担当の処理で判定し、
//   切れていたら $_SESSION['user_id'] が無い状態になる
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

    return $userId;
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

/** ログイン画面へ遷移する（$return があればログイン後そこへ戻る） */
function redirect_to_login(?string $return = null): void
{
    $url = URL_LOGIN;
    if ($return !== null) {
        $url .= '?return=' . urlencode($return);
    }
    redirect($url);
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

/** 保存したメッセージを取り出す（取り出したら消える） */
function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
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
 * CSRFトークンを確認する（1回使ったら無効にする）
 * 引数を省略したときは、フォームから送られた csrf_token を確認する
 */
function check_csrf_token(?string $token = null): bool
{
    $token = $token ?? ($_POST['csrf_token'] ?? null);   // 引数が無ければ POST の値を使う
    $saved = $_SESSION['csrf_token'] ?? '';               // セッションに保存したトークン
    unset($_SESSION['csrf_token']);                       // 1回使ったら無効にする
    return $token !== null && $saved !== '' && hash_equals($saved, $token);
}