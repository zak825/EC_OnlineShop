-- ============================================================
-- 管理者アカウント（AdminAccount / AdminSession / AdminLoginLock）を、既存の CSE_EC に追加する
--
-- 利用者の UserData とは別。serverSetUp.sql を実行し直す場合は不要（同じ内容が入っている）
-- データを消さずに追加したい人はこちらを実行する。何度実行しても同じ状態になる
--
-- 実行方法（プロジェクトのフォルダで）
--   sqlcmd -S localhost\SQLEXPRESS -E -f 65001 -i sql\add_admin_account.sql
--   （sa で入る場合は -E の代わりに -U sa -P パスワード -C）
--
-- 初期アカウント
--   LoginId admin / パスワード password（利用者テストユーザーと同じハッシュ）
--
-- 運用メモ
--   ・存在するログインIDでパスワードを3回連続で間違えると、10分間ログインできない
--   ・同じ管理者で有効なセッションは1つだけ（後からログインした端末が優先）
--   ・ロックを手動で外すときは、該当行を削除する
--       DELETE FROM AdminLoginLock WHERE AdminId = 1;
--   ・全端末を強制的にログアウトさせたいときは、セッション行を削除する
--       DELETE FROM AdminSession WHERE AdminId = 1;
-- ============================================================

USE CSE_EC;
GO

IF OBJECT_ID(N'dbo.AdminAccount', N'U') IS NULL
    CREATE TABLE AdminAccount (
        AdminId      INT           NOT NULL IDENTITY(1,1) PRIMARY KEY,
        LoginId      VARCHAR(32)   NOT NULL,
        PasswordHash VARCHAR(255)  NOT NULL,
        DisplayName  NVARCHAR(50)  NOT NULL,
        CONSTRAINT UQ_AdminAccount_LoginId UNIQUE (LoginId)
    );
GO

IF OBJECT_ID(N'dbo.AdminSession', N'U') IS NULL
    CREATE TABLE AdminSession (
        AdminId          INT       NOT NULL PRIMARY KEY REFERENCES AdminAccount(AdminId),
        SessionToken     CHAR(64)  NOT NULL,
        LoginDateTime    DATETIME  NOT NULL,
        LastActivityTime DATETIME  NOT NULL
    );
GO

IF OBJECT_ID(N'dbo.AdminLoginLock', N'U') IS NULL
    CREATE TABLE AdminLoginLock (
        AdminId     INT      NOT NULL PRIMARY KEY REFERENCES AdminAccount(AdminId),
        FailCount   INT      NOT NULL,
        LockedUntil DATETIME NULL
    );
GO

IF NOT EXISTS (SELECT 1 FROM dbo.AdminAccount WHERE LoginId = 'admin')
    INSERT INTO dbo.AdminAccount (LoginId, PasswordHash, DisplayName) VALUES
        ('admin', '$2y$10$VwpD.iCpfuJ41BwquwZte.Co/fKmd/JT2zEn/HHvaGN1N.JQDuRSW', N'管理者');
GO

SELECT LoginId, DisplayName FROM dbo.AdminAccount;
GO
