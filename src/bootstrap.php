<?php
// ============================================================
// 全画面の最初に読み込む共通ファイル
//   public/ の各PHPの先頭で
//   require_once __DIR__ . '/../src/bootstrap.php';
// ============================================================

define('ROOT_DIR',     dirname(__DIR__));
define('SRC_DIR',      ROOT_DIR . '/src');
define('TEMPLATE_DIR', ROOT_DIR . '/templates');

$configFile = SRC_DIR . '/config/config.php';
if (!is_file($configFile)) {
    exit('src/config/config.php がありません。config.sample.php をコピーして作成してください。');
}
require_once $configFile;

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

// 文字コードを UTF-8 に統一する
mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=UTF-8');

require_once SRC_DIR . '/lib/db.php';
require_once SRC_DIR . '/lib/auth.php';
require_once SRC_DIR . '/lib/view.php';
require_once SRC_DIR . '/lib/product_image.php';
