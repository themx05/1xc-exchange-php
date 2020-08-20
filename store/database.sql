DROP TABLE IF EXISTS SystemProperties;
CREATE TABLE SystemProperties(
    id varchar(255) not null,
    properties JSON not null,
    primary key(`id`)
);

DROP TABLE IF EXISTS Users;
/*CREATE TABLE Users(
    id varchar(255) not null,
    firstName VARCHAR(255) NOT NULL,
    lastName VARCHAR(255) NOT NULL,
    gender enum('male','female') NOT NULL DEFAULT 'male',
    email VARCHAR(255) NOT NULL,
    passwordHash VARCHAR(255) NOT NULL,
    country varchar(255) NOT NULL,
    verified TINYINT NOT NULL DEFAULT 0,
    isMerchant TINYINT NOT NULL DEFAULT 0,
    status enum('active','disabled') NOT NULL default 'active',
    insertionDate datetime not null default CURRENT_TIMESTAMP,
    primary key (`id`)
); */

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

DROP TABLE IF EXISTS WalletDeposit;
CREATE TABLE WalletDeposit(
    id varchar(255) not null,
    data JSON NOT NULL,
    primary key(`id`)
);

DROP TABLE IF EXISTS WalletWithdrawal;
CREATE TABLE WalletWithdrawal(
    id varchar(255) not null,
    data JSON NOT NULL,
    primary key(`id`)
);

DROP TABLE IF EXISTS WalletWithdrawalRequest;
CREATE TABLE WalletWithdrawalRequest(
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
    type VARCHAR(255) NOT NULL,
    details JSON NOT NULL,
    primary key(`id`)
);

DROP TABLE IF EXISTS Tickets;
CREATE TABLE Tickets(
    id varchar(255) not null,
    userId varchar(255) not null,
    source JSON NOT NULL,
    dest JSON NOT NULL,
    amount DOUBLE NOT NULL,
    rate double NOT NULL,
    address varchar(255) not null,
    status enum('pending','confirmed','paid','cancelled'),
    allowed TINYINT NOT NULL DEFAULT 0,
    enableCommission TINYINT NOT NULL DEFAULT 0,
    emissionDate datetime not null default CURRENT_TIMESTAMP,
    confirmedAt datetime,
    paidAt datetime,
    cancelledAt datetime,
    primary key(`id`),
    constraint fk_ticket_user foreign key(`userId`) references Users(`id`),
    constraint ticket_amount_nn check(amount > 0),
    constraint ticket_Rate_nn check(rate > 0)
);

DROP TABLE IF EXISTS ExpectedPayments;
CREATE TABLE ExpectedPayments(
    id VARCHAR(255) NOT NULL,
    ticketId varchar(255) NOT NULL,
    type VARCHAR(255) NOT NULL,
    amount DOUBLE NOT NULL,
    currency VARCHAR(255) NOT NULL,
    address VARCHAR(255) NOT NULL,
    paymentUrl TEXT NOT NULL default "",
    primary key(`id`),
    constraint fk_payment_ticket foreign key (`ticketId`) references Tickets(`id`),
    constraint check_expected_payment_amount check( amount > 0)
);

DROP TABLE IF EXISTS PaymentSecurityCode;
CREATE TABLE PaymentSecurityCode(
    code varchar(255) NOT NULL,
    paymentId VARCHAR(255) NOT NULL,
    PRIMARY KEY(`code`),
    CONSTRAINT fk_security_payment FOREIGN KEY(`paymentId`) REFERENCES ExpectedPayments(`id`)
);

DROP TABLE IF EXISTS Admins;
CREATE TABLE Admins(
    id varchar(255) not null,
    firstName varchar(255) not null,
    lastName varchar(255) not null,
    alias varchar(255) not null,
    passwordHash varchar(255) not null,
    insertionDate datetime not null default CURRENT_TIMESTAMP,
    primary key (`id`)
);

DROP TABLE IF EXISTS Transactions;
CREATE TABLE Transactions(
    id varchar(255) not null,
    ticketId varchar(255) NOT NULL,
    variant enum('in','out') NOT NULL DEFAULT 'in',
    type varchar(255) NOT NULL,
    amount DOUBLE NOT NULL,
    currency varchar(255) NOT NULL,
    source varchar(255) NOT NULL,
    dest varchar(255) NOT NULL,
    reference varchar(255) NOT NULL,
    status enum('pending','done') NOT NULL DEFAULT 'done',
    timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    insertionDate datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    validationDate datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    primary key (`id`),
    constraint tx_check_amount_nn check(amount > 0)
);