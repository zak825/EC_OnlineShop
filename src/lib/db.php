<?php
/**
 * DB接続を返す（1リクエスト中は同じ接続を使い回す）
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        // SQL Server とのやりとりを UTF-8 にする（日本語の文字化け対策）
        if (defined('PDO::SQLSRV_ATTR_ENCODING')) {
            $options[PDO::SQLSRV_ATTR_ENCODING] = PDO::SQLSRV_ENCODING_UTF8;
        }
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}

/**
 * テーブルに列があるかを返す（1リクエスト中は結果を使い回す）
 *
 * DB の定義が人によって違う（OnlineShop と sql/serverSetUp.sql）ため、
 * 列の有無で INSERT する列を切り替えるときに使う
 *   db_has_column('UserData', 'InsertUserId')
 */
function db_has_column(string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (!array_key_exists($key, $cache)) {
        // COL_LENGTH は列が無ければ NULL を返す
        $stmt = db()->prepare('SELECT COL_LENGTH(:table, :column)');
        $stmt->execute([':table' => $table, ':column' => $column]);
        $cache[$key] = $stmt->fetchColumn() !== null;
    }
    return $cache[$key];
}
