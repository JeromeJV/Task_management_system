ALTER TABLE users
    ADD COLUMN employee_id INT DEFAULT NULL AFTER email,
    ADD UNIQUE KEY uq_users_employee_id (employee_id);

UPDATE users u
JOIN (
    SELECT LOWER(TRIM(email)) AS email_key, MIN(id) AS user_id
    FROM users
    WHERE email IS NOT NULL AND TRIM(email) <> ''
    GROUP BY LOWER(TRIM(email))
    HAVING COUNT(*) = 1
) unique_users ON unique_users.user_id = u.id
JOIN (
    SELECT LOWER(TRIM(email)) AS email_key, MIN(employee_id) AS employee_id
    FROM employee
    WHERE email IS NOT NULL AND TRIM(email) <> ''
    GROUP BY LOWER(TRIM(email))
    HAVING COUNT(*) = 1
) unique_profiles ON unique_profiles.email_key = unique_users.email_key
SET u.employee_id = unique_profiles.employee_id
WHERE u.employee_id IS NULL;
