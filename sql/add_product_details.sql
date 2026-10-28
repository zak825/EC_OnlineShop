-- 既存DBへ出品商品の状態・説明を追加する移行SQL
USE CSE_EC;
GO

IF OBJECT_ID(N'dbo.Sales', N'U') IS NULL
    THROW 50001, N'テーブル dbo.Sales が見つかりません。CSE_EC データベースを選択して実行してください。', 1;
IF OBJECT_ID(N'dbo.SalesDraft', N'U') IS NULL
    THROW 50002, N'テーブル dbo.SalesDraft が見つかりません。先に sql\add_sales_draft.sql を実行してください。', 1;
GO

IF COL_LENGTH('dbo.Sales', 'ProductCondition') IS NULL
    ALTER TABLE dbo.Sales ADD ProductCondition NVARCHAR(30) NULL;
IF COL_LENGTH('dbo.Sales', 'Description') IS NULL
    ALTER TABLE dbo.Sales ADD Description NVARCHAR(1000) NULL;
IF COL_LENGTH('dbo.SalesDraft', 'ProductCondition') IS NULL
    ALTER TABLE dbo.SalesDraft ADD ProductCondition NVARCHAR(30) NULL;
IF COL_LENGTH('dbo.SalesDraft', 'Description') IS NULL
    ALTER TABLE dbo.SalesDraft ADD Description NVARCHAR(1000) NULL;
GO

UPDATE dbo.Sales
SET ProductCondition = COALESCE(ProductCondition, N'中古'),
    Description = COALESCE(Description, N'');
UPDATE dbo.SalesDraft
SET ProductCondition = COALESCE(ProductCondition, N'中古'),
    Description = COALESCE(Description, N'');
GO

ALTER TABLE dbo.Sales ALTER COLUMN ProductCondition NVARCHAR(30) NOT NULL;
ALTER TABLE dbo.Sales ALTER COLUMN Description NVARCHAR(1000) NOT NULL;
ALTER TABLE dbo.SalesDraft ALTER COLUMN ProductCondition NVARCHAR(30) NOT NULL;
ALTER TABLE dbo.SalesDraft ALTER COLUMN Description NVARCHAR(1000) NOT NULL;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.default_constraints
    WHERE parent_object_id = OBJECT_ID(N'dbo.Sales')
      AND name = N'DF_Sales_ProductCondition'
)
    ALTER TABLE dbo.Sales ADD CONSTRAINT DF_Sales_ProductCondition DEFAULT (N'中古') FOR ProductCondition;
IF NOT EXISTS (
    SELECT 1 FROM sys.default_constraints
    WHERE parent_object_id = OBJECT_ID(N'dbo.Sales')
      AND name = N'DF_Sales_Description'
)
    ALTER TABLE dbo.Sales ADD CONSTRAINT DF_Sales_Description DEFAULT (N'') FOR Description;
IF NOT EXISTS (
    SELECT 1 FROM sys.default_constraints
    WHERE parent_object_id = OBJECT_ID(N'dbo.SalesDraft')
      AND name = N'DF_SalesDraft_ProductCondition'
)
    ALTER TABLE dbo.SalesDraft ADD CONSTRAINT DF_SalesDraft_ProductCondition DEFAULT (N'中古') FOR ProductCondition;
IF NOT EXISTS (
    SELECT 1 FROM sys.default_constraints
    WHERE parent_object_id = OBJECT_ID(N'dbo.SalesDraft')
      AND name = N'DF_SalesDraft_Description'
)
    ALTER TABLE dbo.SalesDraft ADD CONSTRAINT DF_SalesDraft_Description DEFAULT (N'') FOR Description;
GO

SELECT
    OBJECT_NAME(c.object_id) AS TableName,
    c.name AS ColumnName,
    c.is_nullable AS IsNullable
FROM sys.columns c
WHERE c.object_id IN (OBJECT_ID(N'dbo.Sales'), OBJECT_ID(N'dbo.SalesDraft'))
  AND c.name IN (N'ProductCondition', N'Description')
ORDER BY TableName, ColumnName;
GO
