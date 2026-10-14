ALTER TABLE office
    ADD COLUMN completion_review_status VARCHAR(24) NOT NULL DEFAULT 'none' AFTER progress,
    ADD COLUMN completion_review_note TEXT DEFAULT NULL AFTER completion_review_status,
    ADD COLUMN completion_reviewed_by INT DEFAULT NULL AFTER completion_review_note,
    ADD COLUMN completion_reviewed_at DATETIME DEFAULT NULL AFTER completion_reviewed_by,
    ADD KEY idx_office_review_status (completion_review_status, employee_id);
