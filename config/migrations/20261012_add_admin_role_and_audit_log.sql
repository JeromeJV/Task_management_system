ALTER TABLE users
    MODIFY COLUMN role ENUM('admin', 'HR', 'payroll', 'log', 'super', 'pro', 'cooking', 'packaging', 'employee') NOT NULL;

ALTER TABLE users
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role;

CREATE TABLE IF NOT EXISTS system_audit_log (
    audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_user_id INT DEFAULT NULL,
    actor_name VARCHAR(255) NOT NULL,
    action_type VARCHAR(60) NOT NULL,
    subject_type VARCHAR(40) NOT NULL,
    subject_id INT DEFAULT NULL,
    details TEXT NOT NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (audit_id),
    KEY idx_admin_audit_time (occurred_at),
    KEY idx_admin_audit_subject (subject_type, subject_id, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
