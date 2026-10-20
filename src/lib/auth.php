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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---- 開発用の自動ログイン -------------------------------------------
// config.php の DEV_LOGIN_USER_ID に Userid を入れると、ログイン画面なしで
// そのユーザーとしてログインした状態になる（APP_DEBUG が true のときだけ）
if (APP_DEBUG && defined('DEV_LOGIN_USER_ID') && DEV_LOGIN_USER_ID > 0 && !isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = DEV_LOGIN_USER_ID;
}

/** ログイン済みか */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

/** ログイン中のユーザーID */
function login_user_id(): int
{
    return (int)$_SESSION['user_id'];
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
