# CSE_EC

インターン課題のフリマ形式オンラインショッピングサイト（PHP + MySQL / XAMPP）

## ディレクトリ構成

```
CSE_EC/
├─ public/                  ブラウザから見える場所（XAMPPはここを公開する）
│   ├─ purchase.php           各画面の入口（1画面 = 1ファイル）
│   ├─ purchase_confirm.php
│   ├─ assets/
│   │   ├─ css/
│   │   │   ├─ common.css     全画面共通（色・ボタン・カード・メッセージ）
│   │   │   └─ purchase.css   画面ごとのCSS
│   │   ├─ js/
│   │   │   ├─ common.js      全画面共通
│   │   │   └─ purchase.js    画面ごとのJS
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
├─ templates/               画面のHTML
│    ├─ purchase.html
│    └─ purchase_confirm.html
│
├─ sql/
│   ├─ 01_schema.sql          テーブル作成
│   └─ 02_seed.sql            テストデータ
│
└─ docs/design/             設計書（画面ごとに 設計処理・処理手順）
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
   XAMPP の初期設定（root / パスワードなし）ならそのままで動く。

4. **DBを作成**：XAMPP で Apache と MySQL を Start し、`http://localhost/phpmyadmin` の「SQL」タブで `sql/01_schema.sql` → `sql/02_seed.sql` の順に実行

5. **動作確認**：`http://localhost/CSE_EC/purchase.php?productId=1`

## 新しい画面を作るとき

例：販売画面（sell）を作る場合

| 作るもの | 置き場所 |
|---|---|
| 処理（PHP） | `public/sell.php` |
| HTML | `templates/sell/sell.html` |
| CSS | `public/assets/css/sell.css` |
| JS（必要なら） | `public/assets/js/sell.js` |
| 設計書 | `docs/design/販売画面_設計処理.md` / `販売画面_処理手順.md` |

PHP の書き方（`public/purchase.php` を参考に）

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

HTML の書き方（`templates/purchase/purchase.html` を参考に）

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

## 担当

| 画面 | 担当 |
|---|---|
| ログイン・アカウント登録 | |
| メイン画面 | |
| 販売・販売確認 | |
| 購入・購入確認 | |
| 状況確認・発送通知・レビュー | |
