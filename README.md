# CSE_EC

インターン課題のフリマ形式オンラインショッピングサイト（PHP + Microsoft SQL Server / XAMPP）

## ディレクトリ構成

```
CSE_EC/
├─ public/                  ブラウザから見える場所（XAMPPはここを公開する）
│   ├─ index.php            各画面の入口（1画面 = 1ファイル）
│   ├─ assets/
│   │   ├─ css/
│   │   │   ├─ common.css     全画面共通（色・ボタン・カード・メッセージ）
│   │   │   └─ index.css      画面ごとのCSS
│   │   ├─ js/
│   │   │   ├─ common.js      全画面共通
│   │   │   └─ index.js       画面ごとのJS
│   │   └─ images/            サイトで使う画像（ロゴ等）
│   └─ uploads/               出品時にアップロードされた商品画像（gitに上げない）
│
├─ src/                     PHPの共通処理（ブラウザからは見えない）
│   ├─ bootstrap.php          全画面の先頭で読み込む
│   ├─ config/
│   │   ├─ config.sample.php  設定の見本（gitに上げる）
│   │   └─ config.php         自分用の設定（gitに上げない）
│   └─ lib/
│       ├─ db.php             DB接続 db()
│       ├─ auth.php           ログイン確認・セッション・CSRF
│       └─ view.php           テンプレート表示 view()・エスケープ h()
│
├─ templates/               画面のHTML（PHPから読み込む）
│    └─ index.html
│
└─ sql/
    ├─ serverSetUp.sql        DB,テーブル,テストデータ作成
    └─ 仮商品を並べる.sq　　　　テスト表示用の商品データ
```

## 環境構築（XAMPP）

1. **リポジトリを clone**
   ```powershell
   git clone https://github.com/zak825/CSE_EC.git C:\git\intern\CSE_EC
   ```

2. **public を htdocs にリンク**（PowerShell を管理者で実行）
   ```powershell
   cmd /c mklink /J C:\xampp\htdocs\CSE_EC C:\git\intern\CSE_EC\public
   ```

3. **設定ファイルを作成**
   ```powershell
   copy src\config\config.sample.php src\config\config.php
   ```
   `config.php` の `DB_DSN`（サーバー名・DB名）、`DB_USER`、`DB_PASS` を自分の SQL Server の接続情報に書き換える。

5. **DB構築**
   SSMS上で`serverSetUp.sql`を実行し、動作用のDB[CSE_EC]とテーブル、テストデータを作成する。

6. **動作確認**：`http://localhost/CSE_EC/index.php`

### テンプレート画面・JS・PHPをブラウザで開く

テンプレートはPHP変数を使うため、ファイル自体を静的HTMLとして開くのではなく、対応するPHP画面から表示します。次のテンプレートURLを開くと、対応する画面へ転送されます。

- 購入テンプレート：`http://localhost/CSE_EC/templates/purchase.html?productId=1`
- 購入確認テンプレート：`http://localhost/CSE_EC/templates/purchase_confirm.html`（購入画面から進んだ後に開く）

画面で使用しているリソースは公開フォルダから直接確認できます。

- JavaScript：`http://localhost/CSE_EC/assets/js/common.js`、`http://localhost/CSE_EC/assets/js/purchase_confirm.js`
- PHP画面：`http://localhost/CSE_EC/purchase.php?productId=1`、`http://localhost/CSE_EC/purchase_confirm.php`

リポジトリ直下を `htdocs` から公開している場合も、ルートの `.htaccess` が画面・JSのURLを `public/` 配下へ転送し、`src/`・`sql/`・`docs/` は公開しません。`public/` 自体を公開ルートにしている場合は `public/.htaccess` がテンプレートURLを処理します。どちらの構成でも Apache の `mod_rewrite` と `.htaccess` を有効にしてください（XAMPP 標準設定では通常 `AllowOverride All` です）。

### ログイン処理を省略した購入画面の動作確認

`src/config/config.php` の `DEV_LOGIN_USER_ID` に `UserData` に登録済みのユーザーIDを設定すると、`APP_DEBUG` が `true` かつ `localhost` からのアクセスに限り、そのユーザーとして画面を試せます。現在は `1` を設定しています。ログイン画面は経由しませんが、商品・配送先の表示と購入確定には通常どおりDB接続が必要です。確定操作はSalesの購入状態を書き換えるため、テスト用DBと未購入の商品を使ってください。無効にする場合は `DEV_LOGIN_USER_ID` を `0` にするか、`APP_DEBUG` を `false` にしてください。
## 文字コード

- PHP・HTMLテンプレート・CSS・JavaScriptのファイルは **UTF-8** で保存する。
- PHP画面はHTTPレスポンスに `Content-Type: text/html; charset=UTF-8` を指定し、HTMLには `<meta charset="UTF-8">` を設定する。
- 日本語が化ける場合は、ファイルの保存文字コードに加えて、HTTPレスポンスの `Content-Type` とデータベース接続・保存データの文字コードも確認する。

## 新しい画面を作るとき

例：販売画面（sell）を作る場合

| 作るもの | 置き場所 |
|---|---|
| 処理（PHP） | `public/index.php` |
| HTML | `templates/index.html` |
| CSS | `public/assets/css/common.css` |
| JS（必要なら） | `public/assets/js/index.js` |

PHP の書き方（`public/index.php` を参考に）

```php
<?php
require_once __DIR__ . '/../src/bootstrap.php';

if (!is_logged_in()) {
    redirect_to_login('sell.php');
}

// ...処理...

view('sell/sell', [
    'flash' => get_flash(),
    'token' => csrf_token(),
    // テンプレートで使う変数
]);
```

HTML の書き方（`templates/purchase/index.html` を参考に）

```php
<?php
$title   = '販売画面';
$styles  = ['sell.css'];
$scripts = ['sell.js'];
include TEMPLATE_DIR . '/layout/header.html';
?>

<h1 class="page-title">商品を出品する</h1>
...

<?php include TEMPLATE_DIR . '/layout/footer.html'; ?>
```

## チームのルール

- **値を画面に出すときは必ず `h()` を通す**（XSS対策）
- **SQLは必ずプレースホルダ（`:name`）を使う**。文字列連結でSQLを作らない
- **フォームには `csrf_token` を入れ、受け取り側で `check_csrf_token()` する**
- **共通ファイル（`src/`・`templates/layout/`・`common.css`・`common.js`）を変えるときはチームに一声かける**
- **DBの変更は `sql/` のSQLを更新して共有する**
- ブランチは `feature/画面名`（例：`feature/purchase`）で切り、main へはプルリクで入れる

### ログインセッションとCSRF

ログイン処理では `$_SESSION['logged_in'] = true`、`$_SESSION['user_id']`（または互換キー `UserId`）、`$_SESSION['user_name']`（または互換キー `UserName`）、`$_SESSION['last_activity'] = time()` を設定します。認証が必要な各画面では `is_logged_in()` を呼び、未ログインまたはタイムアウトの場合は `redirect_to_login()` でログイン画面へ遷移します。タイムアウト時間は `SystemConfiguration` の `Category = 'Session'`、`Key = 'SessionTimeout'` に設定された分数を参照します。

同じアカウントの同時ログインは禁止です（先にログインした端末が優先され、後からのログインは拒否されます）。ログイン成功時に `$_SESSION['login_token']` も設定し、`UserSession` テーブル（1ユーザー1行）のトークンと一致するセッションだけが有効になります。ログアウトできずに残った場合は、最終操作から `SessionTimeout` 分が過ぎると別の端末からログインできます（パスワード再設定でも解除されます）。既存のDBには `sql/add_user_session.sql` を実行してテーブルを追加してください。

ログイン中のユーザー情報は `current_user_id()`（互換関数 `login_user_id()`）と `current_user_name()` で取得できます。POSTフォームには `csrf_token()` の値を hidden 項目として含め、受信時は `check_csrf_token()` を呼びます。この関数は引数を省略すると `$_POST['csrf_token']` を検証します。

