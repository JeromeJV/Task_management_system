ALTER TABLE production_assignments
    ADD COLUMN completion_review_status VARCHAR(24) NOT NULL DEFAULT 'none' AFTER work_status,
    ADD COLUMN completion_review_note TEXT DEFAULT NULL AFTER completion_review_status,
    ADD COLUMN completion_reviewed_by INT DEFAULT NULL AFTER completion_review_note,
    ADD COLUMN completion_reviewed_at DATETIME DEFAULT NULL AFTER completion_reviewed_by,
    ADD KEY idx_production_assignment_review (completion_review_status, assigned_by);

ALTER TABLE delivery
    ADD COLUMN assigned_by INT DEFAULT NULL AFTER driver_id,
    ADD COLUMN completion_review_status VARCHAR(24) NOT NULL DEFAULT 'none' AFTER delivered_at,
    ADD COLUMN completion_review_note TEXT DEFAULT NULL AFTER completion_review_status,
    ADD COLUMN completion_reviewed_by INT DEFAULT NULL AFTER completion_review_note,
    ADD COLUMN completion_reviewed_at DATETIME DEFAULT NULL AFTER completion_reviewed_by,
    ADD KEY idx_delivery_review (completion_review_status, assigned_by);

UPDATE delivery d
JOIN delivery_activity assignment_history
  ON assignment_history.activity_id = (
      SELECT activity.activity_id
      FROM delivery_activity activity
      WHERE activity.delivery_id = d.delivery_id
        AND activity.event_type = 'delivery_assigned'
        AND activity.actor_user_id IS NOT NULL
      ORDER BY activity.occurred_at DESC, activity.activity_id DESC
      LIMIT 1
  )
SET d.assigned_by = assignment_history.actor_user_id
WHERE d.assigned_by IS NULL;
