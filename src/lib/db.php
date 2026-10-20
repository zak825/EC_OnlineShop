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
