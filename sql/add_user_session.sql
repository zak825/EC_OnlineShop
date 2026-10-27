-- ============================================================
-- 同時ログイン禁止のテーブル（UserSession）を、既存の CSE_EC に追加する
--
-- serverSetUp.sql を実行し直す場合は不要（同じ内容が入っている）
-- データを消さずに追加したい人はこちらを実行する。何度実行しても同じ状態になる
--
-- 実行方法（プロジェクトのフォルダで）
--   sqlcmd -S localhost\SQLEXPRESS -E -f 65001 -i sql\add_user_session.sql
--   （sa で入る場合は -E の代わりに -U sa -P パスワード -C）
--
-- 運用メモ
--   ・同じアカウントで有効なセッションは1つだけ（後からログインした端末が優先）
--   ・ログイン中のアカウントに別の端末からログインすると確認画面が出て、
--     続行すると古い端末は次の操作でログアウトされる
--   ・ログアウトせずに最終操作から SessionTimeout（分）が過ぎた場合は、確認なしでログインできる
--   ・全端末を強制的にログアウトさせたいときは、該当ユーザーの行を削除する
--       DELETE FROM UserSession WHERE Userid = 1;
-- ============================================================

USE CSE_EC;
GO

-- ログイン中セッション（1ユーザー1行。行があり最終操作がタイムアウト内なら「ログイン中」）
IF OBJECT_ID(N'dbo.UserSession', N'U') IS NULL
    CREATE TABLE UserSession (
        Userid           INT       NOT NULL PRIMARY KEY REFERENCES UserData(Userid),
        SessionToken     CHAR(64)  NOT NULL,   -- ログイン成功時に発行するランダム値（このセッションの持ち主の証明）
        LoginDateTime    DATETIME  NOT NULL,
        LastActivityTime DATETIME  NOT NULL    -- 最終操作時刻（有効なセッションかの判定用）
    );
GO

-- 確認
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'UserSession'
ORDER BY ORDINAL_POSITION;
GO
