ALTER TABLE users
    ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER employee_id;
