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

        /**
         * SQL Serverの指定テーブルに列が存在するかを返す。
         * 既存DBへ段階的に移行する期間の互換チェックに使う。
         */
        function db_has_column(string $table, string $column): bool
        {
            static $cache = [];
            $key = $table . "\0" . $column;
            if (array_key_exists($key, $cache)) {
                return $cache[$key];
            }

            $stmt = db()->prepare(
                'SELECT 1
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = \'dbo\' AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
            );
            $stmt->execute([
                ':table_name' => $table,
                ':column_name' => $column,
            ]);
            $cache[$key] = $stmt->fetchColumn() !== false;
            return $cache[$key];
        }
        $pdo = new PDO(DB_DSN, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}
