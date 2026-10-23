-- ============================================================
-- 販売画面の入力制限を DB で管理するための追加分（既存の CSE_EC に実行する）
--
-- serverSetUp.sql を実行し直す場合は不要（同じ内容が入っている）
-- データを消さずに追加したい人はこちらを実行する。何度実行しても同じ状態になる
--
-- 実行方法
--   sqlcmd -S localhost\SQLEXPRESS -E -f 65001 -i sql\update_sell_limits.sql
--   （sa で入る場合は -E の代わりに -U sa -P パスワード）
--
-- 内容
--   1. Sales.ProductName を VARCHAR(50) → NVARCHAR(50) に変更
--      VARCHAR(50) は 50 バイトなので、日本語は 25 文字までしか入らなかった
--   2. SystemConfiguration に販売の設定（値段の下限・上限、商品名の最大文字数）を追加
-- ============================================================

USE CSE_EC;
GO

-- 1. 商品名を NVARCHAR(50) にする（入っているデータはそのまま残る）
IF EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'Sales' AND COLUMN_NAME = 'ProductName' AND DATA_TYPE = 'varchar'
)
    ALTER TABLE Sales ALTER COLUMN ProductName NVARCHAR(50) NOT NULL;
GO

-- 2. 販売の設定（無ければ追加する）
IF NOT EXISTS (SELECT 1 FROM SystemConfiguration WHERE Category = 'Sales' AND [Key] = 'PriceMin')
    INSERT INTO SystemConfiguration (id, Category, [Key], Value, Type, Comment)
    VALUES (3, 'Sales', 'PriceMin', '300', 'int', N'出品価格の下限（円）');

IF NOT EXISTS (SELECT 1 FROM SystemConfiguration WHERE Category = 'Sales' AND [Key] = 'PriceMax')
    INSERT INTO SystemConfiguration (id, Category, [Key], Value, Type, Comment)
    VALUES (4, 'Sales', 'PriceMax', '9999999', 'int', N'出品価格の上限（円）');

IF NOT EXISTS (SELECT 1 FROM SystemConfiguration WHERE Category = 'Sales' AND [Key] = 'ProductNameMaxLength')
    INSERT INTO SystemConfiguration (id, Category, [Key], Value, Type, Comment)
    VALUES (5, 'Sales', 'ProductNameMaxLength', '50', 'int', N'商品名の最大文字数（Sales.ProductName の NVARCHAR(50) 以下にする）');
GO

-- 確認
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'Sales' AND COLUMN_NAME = 'ProductName';

SELECT id, Category, [Key], Value FROM SystemConfiguration ORDER BY id;
GO
