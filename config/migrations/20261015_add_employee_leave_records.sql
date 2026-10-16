ALTER TABLE employee
    ADD COLUMN hire_date DATE DEFAULT NULL AFTER email;

ALTER TABLE attendance
    MODIFY status ENUM('Present','Early','Late','Overtime','Undertime','Absent','Half Day','Paid Leave','Unpaid Leave')
    DEFAULT 'Absent';

CREATE TABLE employee_leave_requests (
    leave_id INT NOT NULL AUTO_INCREMENT,
    employee_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    leave_type ENUM('SIL', 'LWOP') NOT NULL,
    day_fraction DECIMAL(2,1) NOT NULL DEFAULT 1.0,
    reason VARCHAR(500) NOT NULL,
    status ENUM('active', 'cancelled') NOT NULL DEFAULT 'active',
    recorded_by INT NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cancelled_by INT DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    PRIMARY KEY (leave_id),
    KEY idx_employee_leave_dates (employee_id, status, start_date, end_date),
    KEY idx_employee_leave_recorder (recorded_by, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
