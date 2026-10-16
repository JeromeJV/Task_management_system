ALTER TABLE `applicant`
    MODIFY COLUMN `position_applied` ENUM('HR', 'payroll', 'driver', 'logistics', 'production', 'supervisor', 'employee') NOT NULL;
