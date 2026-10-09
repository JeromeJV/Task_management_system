ALTER TABLE users
    MODIFY COLUMN role ENUM('HR', 'payroll', 'log', 'super', 'pro', 'employee') NOT NULL;
