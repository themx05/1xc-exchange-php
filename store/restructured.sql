
DROP TABLE IF EXISTS Users;

CREATE TABLE Users(
    id varchar(255) NOT NULL,
    data JSON NOT NULL,
    primary key(`id`)
);

DROP TABLE IF EXISTS MerchantProfile;
CREATE TABLE MerchantProfile(
    id varchar(255) not null,
    data JSON NOT NULL,
    primary key(`id`)
);

DROP TABLE IF EXISTS RegistrationFeeTransaction;
CREATE TABLE RegistrationFeeTransaction(
    id varchar(255) not null,
    data json not null,
    primary key(`id`)
);

DROP TABLE IF EXISTS Wallets;
CREATE TABLE Wallets(
    id varchar(255) not null,
    data JSON NOT NULL,
    primary key(`id`)
);

DROP TABLE IF EXISTS WalletHistory;
CREATE TABLE WalletHistory(
    id varchar(255) not null,
    data JSON NOT NULL,
    primary key(`id`)
);

DROP TABLE IF EXISTS AccountVerificationCode;
CREATE TABLE AccountVerificationCode(
    accountId VARCHAR(255) NOT NULL,
    code VARCHAR(255) NOT NULL,
    constraint fk_code_account foreign key (`accountId`) references Users(`id`)
);

DROP TABLE IF EXISTS SupportedMethods;
CREATE TABLE SupportedMethods(
    id varchar(255) not null,
    data JSON not null
);

DROP TABLE IF EXISTS FixedRates;
CREATE TABLE FixedRates(
    id VARCHAR(255) NOT NULL,
    data JSON not null,
    primary key(`id`)
);

DROP TABLE IF EXISTS MethodAccount;
CREATE TABLE MethodAccount(
    id VARCHAR(255) NOT NULL,
    data JSON NOT NULL,
    primary key(`id`)
);

DROP TABLE IF EXISTS Tickets;
CREATE TABLE Tickets(
    id varchar(255) not null,
    data JSON NOT NULL,
    primary key(`id`)
);

DROP TABLE IF EXISTS ExpectedPayments;
CREATE TABLE ExpectedPayments(
    id VARCHAR(255) NOT NULL,
    data JSON NOT NULL,
    primary key(`id`)
);

DROP TABLE IF EXISTS Admins;
CREATE TABLE Admins(
    id varchar(255) not null,
    data JSON NOT NULL,
    primary key (`id`)
);

DROP TABLE IF EXISTS Transactions;
CREATE TABLE Transactions(
    id varchar(255) not null,
    data JSON NOT NULL,
    primary key(`id`)
);