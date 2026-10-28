-- ============================================================
-- ログイン失敗ロックのテーブル（UserLoginLock）を、既存の CSE_EC に追加する
--
-- serverSetUp.sql を実行し直す場合は不要（同じ内容が入っている）
-- データを消さずに追加したい人はこちらを実行する。何度実行しても同じ状態になる
--
-- 実行方法（プロジェクトのフォルダで）
--   sqlcmd -S localhost\SQLEXPRESS -E -f 65001 -i sql\add_user_login_lock.sql
--   （sa で入る場合は -E の代わりに -U sa -P パスワード -C）
--
-- 運用メモ
--   ・存在するユーザーIDでパスワードを3回連続で間違えると、10分間ログインできない
--   ・ロックの期限は SQL Server の GETDATE() から10分後（失敗のたびに延びない）
--   ・パスワードが一致したとき、およびパスワード再設定が成功したときに行を削除する
--   ・期限が切れたあとの失敗は、1回目から数え直す
--   ・特定ユーザーのロックを手動で外すときは、該当行を削除する
--       DELETE FROM UserLoginLock WHERE Userid = 1;
-- ============================================================

USE CSE_EC;
GO

-- ログイン失敗の回数とロック期限（1ユーザー1行。行がなければ失敗0回・ロックなし）
IF OBJECT_ID(N'dbo.UserLoginLock', N'U') IS NULL
    CREATE TABLE UserLoginLock (
        Userid      INT      NOT NULL PRIMARY KEY REFERENCES UserData(Userid),
        FailCount   INT      NOT NULL,   -- 連続失敗回数（0〜2）。3回目でロックした時点で 0 に戻す
        LockedUntil DATETIME NULL        -- この時刻までログイン不可。NULL または過去ならロックなし
    );
GO

-- 確認
SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'UserLoginLock'
ORDER BY ORDINAL_POSITION;
GO
