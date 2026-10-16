ALTER TABLE delivery
    ADD COLUMN delivery_proof_image VARCHAR(255) DEFAULT NULL AFTER completion_reviewed_at;
