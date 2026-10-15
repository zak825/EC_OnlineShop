create table SystemConfiguration(
    id int not null primary key,
    Category varchar(50) not null,
    Key varchar(50) not null,
    Value nvarchar(256) not null,
    Type varchar(128) not null,
    Comment nvarchar(512)
)