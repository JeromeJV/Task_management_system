ALTER TABLE delivery
    ADD COLUMN delivered_at DATETIME DEFAULT NULL AFTER status;

CREATE TABLE IF NOT EXISTS delivery_activity (
    activity_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    delivery_id INT NOT NULL,
    actor_user_id INT DEFAULT NULL,
    actor_name VARCHAR(255) NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    from_driver_id INT DEFAULT NULL,
    from_driver_name VARCHAR(255) DEFAULT NULL,
    to_driver_id INT DEFAULT NULL,
    to_driver_name VARCHAR(255) DEFAULT NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (activity_id),
    KEY idx_delivery_activity_task_time (delivery_id, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
