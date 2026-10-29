-- ============================================================
-- パスワード最小文字数の変更後に再設定を求める列（UserData.PasswordResetRequired）を追加する
--
-- serverSetUp.sql を実行し直す場合は不要（同じ内容が入っている）
-- データを消さずに追加したい人はこちらを実行する。何度実行しても同じ状態になる
--
-- 実行方法（プロジェクトのフォルダで）
--   sqlcmd -S localhost\SQLEXPRESS -E -f 65001 -i sql\add_password_reset_required.sql
--
-- 運用メモ
--   ・PasswordMinLength を変更したとき、登録済み利用者は全員 1 になる
--   ・1 の利用者は、次のログインでパスワード再設定画面へ送られる
--   ・再設定が成功すると 0 に戻り、ショップへログインできる
--   ・新規登録の利用者は 0 のまま（登録時点の規則でパスワードを決めている）
-- ============================================================

USE CSE_EC;
GO

IF COL_LENGTH('dbo.UserData', 'PasswordResetRequired') IS NULL
    ALTER TABLE dbo.UserData
        ADD PasswordResetRequired BIT NOT NULL
            CONSTRAINT DF_UserData_PasswordResetRequired DEFAULT (0);
GO
