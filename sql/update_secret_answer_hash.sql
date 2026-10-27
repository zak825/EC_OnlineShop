-- ============================================================
-- 秘密の質問の回答（UserData.SecretAnswer）をハッシュ化して保存するための更新（既存の CSE_EC に実行する）
--
-- serverSetUp.sql を実行し直す場合は不要（同じ内容が入っている）
-- データを消さずに更新したい人はこちらを実行する。何度実行しても同じ状態になる
--
-- 実行方法:
--   sqlcmd -S localhost\SQLEXPRESS -E -f 65001 -i sql\update_secret_answer_hash.sql
--   （sa で入る場合は -E の代わりに -U sa -P パスワード）
--
-- 内容
--   1. テストユーザー（1〜3）の回答をハッシュ値に置き換える
--   2. まだハッシュになっていないその他のユーザーの回答は、
--      SQL ではハッシュ化できないため「初期回答」のハッシュ値に置き換える
--      （該当ユーザーは、必要なら新しい回答を設定し直すこと）
--   3. SecretAnswer を NVARCHAR(100) → VARCHAR(255) に変更
--      password_hash() の結果（bcrypt で60文字）が入るようにする（LoginPassword と同じ長さ）
-- ============================================================

USE CSE_EC;
GO

-- 1. テストユーザーの回答をハッシュ値にする（まだ元の回答のままのときだけ）
UPDATE UserData SET SecretAnswer = '$2y$10$oDHIqXypG55YwMZCMbuCEedxsj0wyRsqlsVcMxRWpHzoNA5OAeuWm'  -- 千代田小学校
    WHERE Userid = 1 AND SecretAnswer = N'千代田小学校';
UPDATE UserData SET SecretAnswer = '$2y$10$.IcB6WLODnzHxy1zBN0Yh.n3eqKd8ZcpEbFnoNJlFjNnr6qqsZbW.'  -- ポチ
    WHERE Userid = 2 AND SecretAnswer = N'ポチ';
UPDATE UserData SET SecretAnswer = '$2y$10$bVgjw0C3TpWOE7PWOpFgguxRxVLBHfivgxyQoYIsymTesZ4oL3PJO'  -- カレー
    WHERE Userid = 3 AND SecretAnswer = N'カレー';
GO

-- 2. ハッシュになっていないその他の回答は「初期回答」のハッシュ値にする
UPDATE UserData SET SecretAnswer = '$2y$10$wCYGp7jFrtgpfosV6EY.Re8PAVgU8mVKpnIQ4W8GyudYVu3ds8gh6'
    WHERE SecretAnswer NOT LIKE '$2y$%';
GO

-- 3. 列の型を変更する（先に値を入れ替えるのは、VARCHAR にすると日本語の元の回答が読めなくなるため。既定値の制約が付いていると変更できないので、いったん外して付け直す）
IF EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_NAME = 'UserData' AND COLUMN_NAME = 'SecretAnswer' AND DATA_TYPE = 'nvarchar'
)
BEGIN
    IF EXISTS (SELECT 1 FROM sys.default_constraints WHERE name = 'DF_UserData_SecretAnswer')
        ALTER TABLE UserData DROP CONSTRAINT DF_UserData_SecretAnswer;

    ALTER TABLE UserData ALTER COLUMN SecretAnswer VARCHAR(255) NOT NULL;
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.default_constraints WHERE name = 'DF_UserData_SecretAnswer')
    ALTER TABLE UserData ADD CONSTRAINT DF_UserData_SecretAnswer
        DEFAULT ('$2y$10$wCYGp7jFrtgpfosV6EY.Re8PAVgU8mVKpnIQ4W8GyudYVu3ds8gh6') FOR SecretAnswer;
GO
