-- ============================================================
-- 出品の下書き機能のテーブル（SalesDraft）を、既存の CSE_EC に追加する
--
-- serverSetUp.sql を実行し直す場合は不要（同じ内容が入っている）
-- データを消さずに追加したい人はこちらを実行する。何度実行しても同じ状態になる
--
-- 実行方法（プロジェクトのフォルダで）
--   sqlcmd -S localhost\SQLEXPRESS -E -f 65001 -i sql\add_sales_draft.sql
--   （sa で入る場合は -E の代わりに -U sa -P パスワード -C）
-- ============================================================

USE CSE_EC;
GO

-- 出品の下書き（1人1件。画像は public/uploads/products/draft_{Userid}/ に保存する）
IF OBJECT_ID(N'dbo.SalesDraft', N'U') IS NULL
    CREATE TABLE SalesDraft (
        Userid         INT           NOT NULL PRIMARY KEY REFERENCES UserData(Userid),
        GenreCode      INT           NOT NULL,
        ProductName    NVARCHAR(50)  NOT NULL,
        Price          INT           NOT NULL,
        UpdateDateTime DATETIME      NOT NULL
    );
GO

-- 確認
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'SalesDraft'
ORDER BY ORDINAL_POSITION;
GO
