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

// 開発用の自動ログイン（APP_DEBUG が true のときだけ）
if (APP_DEBUG && defined('DEV_LOGIN_USER_ID') && DEV_LOGIN_USER_ID > 0 && !isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = DEV_LOGIN_USER_ID;
}

/** ログイン済みか */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']) || development_login_user_id() !== null;
}

/** ログイン中のユーザーID */
function login_user_id(): int
{
    if (isset($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }

    $userId = development_login_user_id();
    if ($userId === null) {
        throw new LogicException('ログイン中のユーザーがありません。');
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
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

/** CSRFトークンを確認する（1回使ったら無効にする） */
function check_csrf_token(?string $token): bool
{
    $saved = $_SESSION['csrf_token'] ?? '';
    unset($_SESSION['csrf_token']);
    return $token !== null && $saved !== '' && hash_equals($saved, $token);
}