CREATE TABLE UserData (
    Userid        INT           NOT NULL IDENTITY(1,1) PRIMARY KEY,
    UserName      NVARCHAR(50)  NOT NULL,
    ZipCode       VARCHAR(8)    NOT NULL,
    Address       NVARCHAR(256) NOT NULL,
    TEL           VARCHAR(15)   NOT NULL,
    LoginPassword VARCHAR(255)  NOT NULL
);