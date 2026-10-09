ALTER TABLE office
    ADD COLUMN employee_id INT DEFAULT NULL AFTER supervisor_id,
    ADD COLUMN due_date DATE DEFAULT NULL AFTER department,
    ADD KEY idx_office_employee_status (employee_id, status),
    ADD KEY idx_office_due_date (due_date);
