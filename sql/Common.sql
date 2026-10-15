create table Common(
    InsertUserId int not null references UserData(Userid),
    InsertDateTime datetime not null,
    UpdateUserId int references UserData(Userid),
    UpdateDateTime datetime
)