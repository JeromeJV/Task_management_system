ALTER TABLE applicant
MODIFY status ENUM('Scheduled', 'Pending', 'Passed', 'Failed', 'Hired')
DEFAULT NULL;

UPDATE applicant
SET status = 'Hired'
WHERE LOWER(TRIM(COALESCE(interview_type, ''))) = 'hired'
  AND (status IS NULL OR TRIM(status) = '');
