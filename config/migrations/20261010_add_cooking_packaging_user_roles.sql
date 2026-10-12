ALTER TABLE users
    MODIFY COLUMN role ENUM('HR', 'payroll', 'log', 'super', 'pro', 'cooking', 'packaging', 'employee') NOT NULL;
