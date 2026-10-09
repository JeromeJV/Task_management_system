ALTER TABLE attendance
MODIFY status ENUM('Present', 'Early', 'Late', 'Overtime', 'Undertime', 'Absent', 'Half Day')
DEFAULT 'Absent';
