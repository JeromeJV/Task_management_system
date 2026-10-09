ALTER TABLE applicant
MODIFY interview_type ENUM('initial interview', 'Technical Interview', 'Training', 'Final Interview', 'hired')
DEFAULT NULL;

UPDATE applicant
SET interview_type = 'Training'
WHERE LOWER(REPLACE(TRIM(interview_type), '_', ' ')) = 'technical interview';

ALTER TABLE applicant
MODIFY interview_type ENUM('initial interview', 'Training', 'Final Interview', 'hired')
DEFAULT NULL;
