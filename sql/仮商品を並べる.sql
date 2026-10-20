USE CSE_EC;
GO

-- 今ある ProductID の最大値の続きから番号を振る
DECLARE @id INT = (SELECT ISNULL(MAX(ProductID), 0) FROM Sales);

INSERT INTO Sales (ProductID, GenreCode, ProductName, Price, Userid, BuyerUserid, BuyerName, ShippingFlag, SoldDate, sucre, revwe) VALUES
    -- GenreCode: 1 本 / 2 家電 / 3 服 / 4 ゲーム / 5 その他
    -- Userid（出品者）: 1 出品太郎 / 2 購入花子 / 3 閲覧次郎
    (@id + 1,  1, N'Java入門',              2200, 1, NULL, NULL, NULL, NULL, NULL, NULL),
    (@id + 2,  1, N'デザインの基本',         1980, 3, NULL, NULL, NULL, NULL, NULL, NULL),
    (@id + 3,  2, N'Bluetoothイヤホン',     4980, 1, NULL, NULL, NULL, NULL, NULL, NULL),
    (@id + 4,  2, N'電気ケトル',             2500, 3, NULL, NULL, NULL, NULL, NULL, NULL),
    (@id + 5,  3, N'デニムジャケット',       5800, 1, NULL, NULL, NULL, NULL, NULL, NULL),
    (@id + 6,  3, N'ニット帽（グレー）',      900, 2, NULL, NULL, NULL, NULL, NULL, NULL),
    (@id + 7,  4, N'カードゲーム',           1500, 3, NULL, NULL, NULL, NULL, NULL, NULL),
    (@id + 8,  4, N'ゲームコントローラー',   3800, 1, NULL, NULL, NULL, NULL, NULL, NULL),
    (@id + 9,  5, N'観葉植物（小）',         1200, 2, NULL, NULL, NULL, NULL, NULL, NULL),
    (@id + 10, 5, N'キャンプ用ランタン',     3300, 3, NULL, NULL, NULL, NULL, NULL, NULL);

-- 確認：販売中の商品一覧（メイン画面に並ぶもの）
SELECT ProductID, ProductName, Price, Userid
FROM Sales
WHERE BuyerUserid IS NULL
ORDER BY ProductID DESC;