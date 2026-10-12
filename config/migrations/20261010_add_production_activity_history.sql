ALTER TABLE production_assignments
    ADD COLUMN completed_at DATETIME DEFAULT NULL AFTER work_status;

ALTER TABLE production_assignments
    ADD COLUMN completed_at DATETIME DEFAULT NULL AFTER work_status;

CREATE TABLE IF NOT EXISTS production_activity (
    activity_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    production_id VARCHAR(64) NOT NULL,
    actor_user_id INT DEFAULT NULL,
    actor_name VARCHAR(255) NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    work_type ENUM('Cooking', 'Packaging') DEFAULT NULL,
    from_employee_id INT DEFAULT NULL,
    from_employee_name VARCHAR(255) DEFAULT NULL,
    to_employee_id INT DEFAULT NULL,
    to_employee_name VARCHAR(255) DEFAULT NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (activity_id),
    KEY idx_production_activity_task_time (production_id, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO production_activity
    (production_id, actor_user_id, actor_name, event_type, work_type,
     to_employee_id, to_employee_name, occurred_at)
SELECT pa.production_id,
       pa.assigned_by,
       COALESCE(NULLIF(u.name, ''), 'Recorded assignment'),
       'stage_assigned',
       pa.work_type,
       pa.employee_id,
       e.username,
       pa.assigned_at
FROM production_assignments pa
LEFT JOIN users u ON u.id = pa.assigned_by
LEFT JOIN employee e ON e.employee_id = pa.employee_id
WHERE NOT EXISTS (
    SELECT 1
    FROM production_activity activity
    WHERE activity.production_id = pa.production_id
      AND activity.work_type = pa.work_type
);
