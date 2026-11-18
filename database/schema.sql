-- PayGuard database (MySQL / MariaDB). Safe to run more than once.
-- Run it with:  php tools/setup.php      (or paste it into phpMyAdmin > SQL)

-- Staff logins
CREATE TABLE IF NOT EXISTS users (
  id            INTEGER PRIMARY KEY AUTO_INCREMENT,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          VARCHAR(20)  NOT NULL DEFAULT 'staff',
  created_at    DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- What the customer wanted to buy
CREATE TABLE IF NOT EXISTS orders (
  id            INTEGER PRIMARY KEY AUTO_INCREMENT,
  customer_name VARCHAR(120) NOT NULL,
  phone         VARCHAR(15)  NOT NULL,
  item          VARCHAR(200) NOT NULL,
  amount        DECIMAL(10,2) NOT NULL,
  created_at    DATETIME     NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One payment attempt. Saved as PENDING BEFORE the gateway is called.
CREATE TABLE IF NOT EXISTS transactions (
  id                 INTEGER PRIMARY KEY AUTO_INCREMENT,
  order_id           INTEGER      NOT NULL,
  reference          VARCHAR(40)  NOT NULL UNIQUE,
  amount             DECIMAL(10,2) NOT NULL,
  status             VARCHAR(15)  NOT NULL DEFAULT 'PENDING',  -- PENDING SUCCESS FAILED CANCELLED TIMEOUT FLAGGED
  gateway            VARCHAR(10)  NOT NULL DEFAULT 'SIMULATOR', -- SIMULATOR or DARAJA
  gateway_request_id VARCHAR(60),
  mpesa_receipt      VARCHAR(30),
  gateway_message    VARCHAR(255),
  reconciled         TINYINT(1)   NOT NULL DEFAULT 0,
  created_at         DATETIME     NOT NULL,
  updated_at         DATETIME     NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders (id),
  KEY idx_txn_status (status),
  KEY idx_txn_request (gateway_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A reported problem with a payment (from a customer, from staff, or found by reconciliation)
CREATE TABLE IF NOT EXISTS incidents (
  id              INTEGER PRIMARY KEY AUTO_INCREMENT,
  transaction_id  INTEGER     NOT NULL,
  incident_type   VARCHAR(30) NOT NULL,
  description     TEXT        NOT NULL,
  trust_score     TINYINT,                                  -- customer trust 1 (low) to 5 (high)
  status          VARCHAR(10) NOT NULL DEFAULT 'OPEN',      -- OPEN or RESOLVED
  resolution_note TEXT,
  source          VARCHAR(10) NOT NULL DEFAULT 'MANUAL',    -- MANUAL or AUTO
  created_at      DATETIME    NOT NULL,
  resolved_at     DATETIME,
  FOREIGN KEY (transaction_id) REFERENCES transactions (id),
  KEY idx_inc_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The simulator's own record of what it charged (stands in for Safaricom's books)
CREATE TABLE IF NOT EXISTS gateway_ledger (
  id         INTEGER PRIMARY KEY AUTO_INCREMENT,
  reference  VARCHAR(40)  NOT NULL,
  receipt    VARCHAR(30)  NOT NULL,
  amount     DECIMAL(10,2) NOT NULL,
  charged_at DATETIME     NOT NULL,
  KEY idx_ledger_ref (reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
