-- ============================================================
-- CSE_EC 開発用DBセットアップ（SQL Server）
--
-- データベース作成 → テーブル作成 → テストデータ投入 までを一括で行う。
-- 何度実行しても同じ状態に戻る（テーブルは毎回作り直す＝データは消える）。
--
-- 実行方法（どちらか）
--   ・SSMS でこのファイルを開いて「実行」(F5)
--   ・コマンドで実行（Windows認証の場合）
--       sqlcmd -S localhost -E -f 65001 -i sql\serverSetUp.sql
--     SQL Server Express なら -S localhost\SQLEXPRESS
--     -f 65001 は「ファイルをUTF-8として読む」指定（無いと日本語が化ける）
--
-- テストユーザー（パスワードは全員 password）
--   Userid 1 : 出品 太郎 … 商品を出品している人
--   Userid 2 : 購入 花子 … 購入テスト・レビューテスト用（商品8をレビューできる）
--   Userid 3 : 閲覧 次郎 … 同時購入テスト用
--
-- 秘密の質問（パスワード再設定用。質問IDは src/lib/auth.php の get_secret_questions() を参照）
--   Userid 1 : 質問1（出身小学校の名前は？）       … 回答「千代田小学校」
--   Userid 2 : 質問2（初めて飼ったペットの名前は？） … 回答「ポチ」
--   Userid 3 : 質問4（好きな食べ物は？）           … 回答「カレー」
-- ============================================================

-- ------------------------------------------------------------
-- データベース作成（無ければ）
-- Japanese_CI_AS にすると varchar 列にも日本語を入れられる
-- ------------------------------------------------------------
IF DB_ID(N'CSE_EC') IS NULL
    CREATE DATABASE CSE_EC COLLATE Japanese_CI_AS;
GO

USE CSE_EC;
GO

-- ------------------------------------------------------------
-- 既存テーブルの削除（外部キーで参照している側から消す）
-- ------------------------------------------------------------
DROP TABLE IF EXISTS Common;
DROP TABLE IF EXISTS Sales;
DROP TABLE IF EXISTS SystemConfiguration;
DROP TABLE IF EXISTS Genre;
DROP TABLE IF EXISTS UserData;
GO

-- ------------------------------------------------------------
-- テーブル作成（外部キーの参照先から作る）
-- チームのDB要件どおり。SQL Server でエラーになる2か所だけ変更
--   SystemConfiguration.Key … 予約語なので [Key]
--   Sales.revwe text(100)   … text は長さ指定不可なので nvarchar(100)
-- ------------------------------------------------------------

-- ユーザー情報
CREATE TABLE UserData (
    Userid        INT           NOT NULL IDENTITY(1,1) PRIMARY KEY,
    UserName      NVARCHAR(50)  NOT NULL,
    ZipCode       VARCHAR(8)    NOT NULL,
    Address       NVARCHAR(256) NOT NULL,
    TEL           VARCHAR(15)   NOT NULL,
    LoginPassword VARCHAR(255)  NOT NULL,
    IconPath      NVARCHAR(256),           -- プロフィールアイコンの画像パス（NULL = 未設定）
    SecretQuestionId INT           NOT NULL CONSTRAINT DF_UserData_SecretQuestionId DEFAULT (1),          -- 秘密の質問ID（1〜5）
    SecretAnswer     VARCHAR(255)  NOT NULL CONSTRAINT DF_UserData_SecretAnswer     DEFAULT ('$2y$10$wCYGp7jFrtgpfosV6EY.Re8PAVgU8mVKpnIQ4W8GyudYVu3ds8gh6') -- 秘密の質問の回答（password_hash のハッシュ値）
);

-- 商品ジャンル
CREATE TABLE Genre (
    GenreCode INT         NOT NULL PRIMARY KEY,
    GenreName VARCHAR(50) NOT NULL
);

-- 商品情報
CREATE TABLE Sales (
    ProductID    INT           NOT NULL PRIMARY KEY,
    GenreCode    INT           NOT NULL REFERENCES Genre(GenreCode),
    ProductName  NVARCHAR(50)  NOT NULL,                      -- NVARCHAR … 日本語でも50文字入る（VARCHAR だと50バイト＝日本語25文字まで）
    Price        INT           NOT NULL,
    Userid       INT           NOT NULL REFERENCES UserData(Userid),
    BuyerUserid  INT           REFERENCES UserData(Userid),  -- 購入者のユーザーID（NULL = 販売中）
    BuyerName    NVARCHAR(50),                                -- 購入者の名前（表示用）
    ShippingFlag BIT,
    SoldDate     DATETIME,
    sucre        INT,
    revwe        NVARCHAR(100)
);

-- システム設定
CREATE TABLE SystemConfiguration (
    id       INT           NOT NULL PRIMARY KEY,
    Category VARCHAR(50)   NOT NULL,
    [Key]    VARCHAR(50)   NOT NULL,
    Value    NVARCHAR(256) NOT NULL,
    Type     VARCHAR(128)  NOT NULL,
    Comment  NVARCHAR(512)
);

-- 共通項目
CREATE TABLE Common (
    InsertUserId   INT      NOT NULL REFERENCES UserData(Userid),
    InsertDateTime DATETIME NOT NULL,
    UpdateUserId   INT      REFERENCES UserData(Userid),
    UpdateDateTime DATETIME
);
GO

-- ------------------------------------------------------------
-- テストデータ
-- ------------------------------------------------------------

-- ユーザー（Userid は IDENTITY なので、番号を指定するときだけ ON にする）
SET IDENTITY_INSERT UserData ON;
INSERT INTO UserData (Userid, UserName, ZipCode, Address, TEL, LoginPassword, SecretQuestionId, SecretAnswer) VALUES
    (1, N'出品 太郎', '1000001', N'東京都千代田区千代田1-1',  '0312345678', '$2y$10$VwpD.iCpfuJ41BwquwZte.Co/fKmd/JT2zEn/HHvaGN1N.JQDuRSW', 1, '$2y$10$oDHIqXypG55YwMZCMbuCEedxsj0wyRsqlsVcMxRWpHzoNA5OAeuWm'),  -- 回答: 千代田小学校
    (2, N'購入 花子', '5300001', N'大阪府大阪市北区梅田1-1',  '0612345678', '$2y$10$VwpD.iCpfuJ41BwquwZte.Co/fKmd/JT2zEn/HHvaGN1N.JQDuRSW', 2, '$2y$10$.IcB6WLODnzHxy1zBN0Yh.n3eqKd8ZcpEbFnoNJlFjNnr6qqsZbW.'),  -- 回答: ポチ
    (3, N'閲覧 次郎', '4600001', N'愛知県名古屋市中区三の丸1-1', '0521234567', '$2y$10$VwpD.iCpfuJ41BwquwZte.Co/fKmd/JT2zEn/HHvaGN1N.JQDuRSW', 4, '$2y$10$bVgjw0C3TpWOE7PWOpFgguxRxVLBHfivgxyQoYIsymTesZ4oL3PJO');  -- 回答: カレー
SET IDENTITY_INSERT UserData OFF;

-- ジャンル
INSERT INTO Genre (GenreCode, GenreName) VALUES
    (1, N'本'),
    (2, N'家電'),
    (3, N'服'),
    (4, N'ゲーム'),
    (5, N'その他');

-- 商品
--   BuyerUserid が NULL     … 販売中
--   BuyerUserid が入っている … 売り切れ（BuyerName には購入者の UserName）
INSERT INTO Sales (ProductID, GenreCode, ProductName, Price, Userid, BuyerUserid, BuyerName, ShippingFlag, SoldDate, sucre, revwe) VALUES
    -- 販売中（出品者=1）… 花子(2)・次郎(3)で購入テストできる
    (1,  1, N'PHP入門',                1500, 1, NULL, NULL, NULL, NULL, NULL, NULL),
    (2,  2, N'ワイヤレスマウス',        2980, 1, NULL, NULL, NULL, NULL, NULL, NULL),
    (3,  3, N'パーカー（黒・Mサイズ）', 3500, 1, NULL, NULL, NULL, NULL, NULL, NULL),
    (4,  4, N'ボードゲーム',            4200, 1, NULL, NULL, NULL, NULL, NULL, NULL),
    -- 販売中（出品者=2）… 花子でログインすると「自分の出品」になる
    (5,  1, N'SQL入門',                 1800, 2, NULL, NULL, NULL, NULL, NULL, NULL),
    -- 売り切れ・未発送（購入者=2）
    (6,  2, N'USBケーブル',              500, 1, 2, N'購入 花子',  0,    DATEADD(DAY, -1, GETDATE()), NULL, NULL),
    -- 売り切れ・発送済・レビュー済（購入者=3）
    (7,  5, N'マグカップ',               800, 1, 3, N'閲覧 次郎',  1,    DATEADD(DAY, -7, GETDATE()), 5, N'きれいな状態で届きました。'),
    -- レビュー確認用 ------------------------------------------------
    -- 発送済・未レビュー（購入者=2）… 花子で「受け取り完了・レビューを書く」が出る
    (8,  1, N'JavaScript入門',          1200, 1, 2, N'購入 花子',  1,    DATEADD(DAY, -3, GETDATE()), NULL, NULL),
    -- 花子の出品が売れて、次郎(3)がレビュー済 … 花子の「出品した商品」タブに出る
    (9,  4, N'トランプ',                 300, 2, 3, N'閲覧 次郎',  1,    DATEADD(DAY, -5, GETDATE()), 4, N'少し箱に傷がありましたが、中身は問題なしです。'),
    -- 花子の出品が売れて、まだ未発送（購入者=3）
    (10, 3, N'マフラー',                1600, 2, 3, N'閲覧 次郎',  0,    DATEADD(HOUR, -5, GETDATE()), NULL, NULL);

-- システム設定
INSERT INTO SystemConfiguration (id, Category, [Key], Value, Type, Comment) VALUES
    (1, 'Password', 'PasswordMinLength',    '8',       'int', N'パスワードの最小文字数'),
    (2, 'Session',  'SessionTimeout',       '30',      'int', N'セッションの有効時間（分）'),
    (3, 'Sales',    'PriceMin',             '300',     'int', N'出品価格の下限（円）'),
    (4, 'Sales',    'PriceMax',             '9999999', 'int', N'出品価格の上限（円）'),
    (5, 'Sales',    'ProductNameMaxLength', '50',      'int', N'商品名の最大文字数（Sales.ProductName の NVARCHAR(50) 以下にする）');
GO

-- ------------------------------------------------------------
-- 確認
-- ------------------------------------------------------------
SELECT 'UserData' AS TableName, COUNT(*) AS RecordCount FROM UserData
UNION ALL SELECT 'Genre',               COUNT(*) FROM Genre
UNION ALL SELECT 'Sales',               COUNT(*) FROM Sales
UNION ALL SELECT 'SystemConfiguration', COUNT(*) FROM SystemConfiguration;
GO
