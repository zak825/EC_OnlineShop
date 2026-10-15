create table Sales(
    ProductID int not null primary key,
    GenreCode int not null references Genre(GenreCode),
    ProductName varchar(50) not null,
    Price int not null,
    Userid int not null references UserData(Userid),
    BuyerName varchar(50),
    ShippingFlag bit,
    SoldDate datetime,
    sucre int,
    revwe text(100)
)