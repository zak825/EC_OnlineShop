-- ============================================================
-- Sales.BuyerUserid から UserData への外部キーを外す
--
-- 購入者アカウントを削除しても、売れた商品を販売中（BuyerUserid IS NULL）に戻さないため。
-- BuyerUserid の値・BuyerName・発送フラグ・レビューはそのまま残す。
-- 出品者（Sales.Userid）の外部キーは残す。
--
-- serverSetUp.sql を実行し直す場合は不要（最初から外部キーを付けない）
-- 何度実行しても同じ状態になる
--
-- 実行方法（プロジェクトのフォルダで）
--   sqlcmd -S localhost\SQLEXPRESS -E -f 65001 -i sql\drop_sales_buyer_fk.sql
-- ============================================================

USE CSE_EC;
GO

DECLARE @constraint nvarchar(128);
DECLARE @sql nvarchar(max);

SELECT @constraint = fk.name
FROM sys.foreign_keys fk
INNER JOIN sys.foreign_key_columns fkc
    ON fk.object_id = fkc.constraint_object_id
INNER JOIN sys.columns c
    ON c.object_id = fkc.parent_object_id
   AND c.column_id = fkc.parent_column_id
WHERE fk.parent_object_id = OBJECT_ID(N'dbo.Sales')
  AND c.name = N'BuyerUserid';

IF @constraint IS NOT NULL
BEGIN
    SET @sql = N'ALTER TABLE dbo.Sales DROP CONSTRAINT ' + QUOTENAME(@constraint);
    EXEC sp_executesql @sql;
END
GO
