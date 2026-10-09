ALTER TABLE attendance
ADD COLUMN is_late TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

UPDATE attendance
SET is_late = CASE
    WHEN time_in IS NOT NULL AND time_in <> '00:00:00' AND time_in > '08:00:00' THEN 1
    ELSE 0
END;
