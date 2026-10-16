-- Schema-only snapshot from the current TaskTrack database. Import into a new, empty database only.
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `applicant` (
  `applicant_id` int(11) NOT NULL AUTO_INCREMENT,
  `lastname` varchar(255) NOT NULL,
  `firstname` varchar(255) NOT NULL,
  `middlename` varchar(255) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `facebook` varchar(300) NOT NULL,
  `house_number` int(255) NOT NULL,
  `street` varchar(255) NOT NULL,
  `barangay` varchar(255) NOT NULL,
  `city` varchar(255) NOT NULL,
  `province` varchar(255) NOT NULL,
  `position_applied` enum('HR','payroll','driver','logistics','production','supervisor','employee') NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `position` varchar(255) NOT NULL,
  `date_of_start` date NOT NULL,
  `date_of_end` date NOT NULL,
  `education` enum('highschool_grad','senior_high_undergrad','senior_high_grad','college_undergrad','college_grad','vacational','n/a') NOT NULL,
  `start_date` date NOT NULL,
  `resume_path` mediumblob NOT NULL,
  `interview_type` enum('initial interview','Training','Final Interview','hired') DEFAULT NULL,
  `interview_mode` enum('Online','On-site') DEFAULT 'Online',
  `status` enum('Scheduled','Pending','Passed','Failed','Hired') DEFAULT NULL,
  `interview_date` datetime DEFAULT NULL,
  PRIMARY KEY (`applicant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `time_in_2` time DEFAULT NULL,
  `time_out_2` time DEFAULT NULL,
  `status` enum('Present','Early','Late','Overtime','Undertime','Absent','Half Day','Paid Leave','Unpaid Leave') DEFAULT 'Absent',
  `is_late` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`attendance_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `delivery` (
  `delivery_id` int(11) NOT NULL AUTO_INCREMENT,
  `production_id` int(11) DEFAULT NULL,
  `route` varchar(255) NOT NULL,
  `pieces` int(11) NOT NULL,
  `stock` int(11) NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Pending',
  `delivered_at` datetime DEFAULT NULL,
  `completion_review_status` varchar(24) NOT NULL DEFAULT 'none',
  `completion_review_note` text DEFAULT NULL,
  `completion_reviewed_by` int(11) DEFAULT NULL,
  `completion_reviewed_at` datetime DEFAULT NULL,
  `delivery_proof_image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`delivery_id`),
  KEY `idx_delivery_review` (`completion_review_status`,`assigned_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `delivery_activity` (
  `activity_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `delivery_id` int(11) NOT NULL,
  `actor_user_id` int(11) DEFAULT NULL,
  `actor_name` varchar(255) NOT NULL,
  `event_type` varchar(40) NOT NULL,
  `from_driver_id` int(11) DEFAULT NULL,
  `from_driver_name` varchar(255) DEFAULT NULL,
  `to_driver_id` int(11) DEFAULT NULL,
  `to_driver_name` varchar(255) DEFAULT NULL,
  `occurred_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`activity_id`),
  KEY `idx_delivery_activity_task_time` (`delivery_id`,`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee` (
  `employee_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `tin_no` varchar(50) DEFAULT 'N/A',
  `sss_no` varchar(50) DEFAULT 'N/A',
  `hdmf_no` varchar(50) DEFAULT 'N/A',
  `basic_pay` decimal(10,2) DEFAULT 0.00,
  `email` varchar(255) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  PRIMARY KEY (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_leave_requests` (
  `leave_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `leave_type` enum('SIL','LWOP') NOT NULL,
  `day_fraction` decimal(2,1) NOT NULL DEFAULT 1.0,
  `reason` varchar(500) NOT NULL,
  `status` enum('active','cancelled') NOT NULL DEFAULT 'active',
  `recorded_by` int(11) NOT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `cancelled_by` int(11) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`leave_id`),
  KEY `idx_employee_leave_dates` (`employee_id`,`status`,`start_date`,`end_date`),
  KEY `idx_employee_leave_recorder` (`recorded_by`,`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `human_resource` (
  `hr_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `attendance_id` int(11) DEFAULT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `philhealth` decimal(10,2) DEFAULT 0.00,
  `sss` decimal(10,2) DEFAULT 0.00,
  `mms` decimal(10,2) DEFAULT 0.00,
  `pagibig` decimal(10,2) DEFAULT 0.00,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `deduction` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`hr_id`),
  KEY `user_id` (`user_id`),
  KEY `fk_hr_attendance` (`attendance_id`),
  CONSTRAINT `fk_hr_attendance` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`attendance_id`) ON DELETE CASCADE,
  CONSTRAINT `human_resource_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `logistics` (
  `logistics_id` int(11) NOT NULL AUTO_INCREMENT,
  `production_id` int(11) NOT NULL,
  `route` varchar(150) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  PRIMARY KEY (`logistics_id`),
  KEY `production_id` (`production_id`),
  CONSTRAINT `logistics_ibfk_1` FOREIGN KEY (`production_id`) REFERENCES `production` (`production_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `office` (
  `office_id` int(11) NOT NULL AUTO_INCREMENT,
  `supervisor_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `data_type` varchar(50) NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `content` text NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `progress` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `completion_review_status` varchar(24) NOT NULL DEFAULT 'none',
  `completion_review_note` text DEFAULT NULL,
  `completion_reviewed_by` int(11) DEFAULT NULL,
  `completion_reviewed_at` datetime DEFAULT NULL,
  `priority_level` varchar(20) DEFAULT 'medium',
  `submission_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`office_id`),
  KEY `idx_office_employee_status` (`employee_id`,`status`),
  KEY `idx_office_due_date` (`due_date`),
  KEY `idx_office_review_status` (`completion_review_status`,`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll` (
  `payroll_id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `attendance_id` int(11) DEFAULT NULL,
  `employee_name` varchar(100) NOT NULL,
  `pay_period` enum('Kinsenas','Katapusan') DEFAULT 'Kinsenas',
  `pay_date` date NOT NULL,
  `regular_pay` decimal(10,2) DEFAULT 0.00,
  `paid_leaves` decimal(10,2) DEFAULT 0.00,
  `daily_allowance` decimal(10,2) DEFAULT 0.00,
  `reimbursement` decimal(10,2) DEFAULT 0.00,
  `gross_pay` decimal(10,2) DEFAULT 0.00,
  `late_deduction` decimal(10,2) DEFAULT 0.00,
  `sss` decimal(10,2) DEFAULT 0.00,
  `pagibig` decimal(10,2) DEFAULT 0.00,
  `philhealth` decimal(10,2) DEFAULT 0.00,
  `calamity_loan` decimal(10,2) DEFAULT 0.00,
  `hmo` decimal(10,2) DEFAULT 0.00,
  `total_deductions` decimal(10,2) DEFAULT 0.00,
  `net_pay` decimal(10,2) DEFAULT 0.00,
  `total_credited` decimal(10,2) DEFAULT 0.00,
  `status` enum('Paid','Pending') DEFAULT 'Pending',
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`payroll_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `production` (
  `production_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_name` varchar(150) NOT NULL,
  `target_pcs` int(11) NOT NULL,
  `department` varchar(100) NOT NULL,
  `due_date` date NOT NULL,
  `Stock_number` int(50) NOT NULL,
  `quantity` int(50) NOT NULL,
  `product_status` varchar(50) NOT NULL DEFAULT 'In Production',
  PRIMARY KEY (`production_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `production_activity` (
  `activity_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `production_id` varchar(64) NOT NULL,
  `actor_user_id` int(11) DEFAULT NULL,
  `actor_name` varchar(255) NOT NULL,
  `event_type` varchar(40) NOT NULL,
  `work_type` enum('Cooking','Packaging') DEFAULT NULL,
  `from_employee_id` int(11) DEFAULT NULL,
  `from_employee_name` varchar(255) DEFAULT NULL,
  `to_employee_id` int(11) DEFAULT NULL,
  `to_employee_name` varchar(255) DEFAULT NULL,
  `occurred_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`activity_id`),
  KEY `idx_production_activity_task_time` (`production_id`,`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `production_assignments` (
  `production_id` varchar(64) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `work_type` enum('Cooking','Packaging') NOT NULL,
  `work_status` enum('Pending','Done') NOT NULL DEFAULT 'Pending',
  `completion_review_status` varchar(24) NOT NULL DEFAULT 'none',
  `completion_review_note` text DEFAULT NULL,
  `completion_reviewed_by` int(11) DEFAULT NULL,
  `completion_reviewed_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`production_id`,`work_type`),
  KEY `idx_production_assignments_employee` (`employee_id`),
  KEY `idx_production_assignment_review` (`completion_review_status`,`assigned_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supervisor` (
  `supervisor_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `task` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`supervisor_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `supervisor_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_audit_log` (
  `audit_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` int(11) DEFAULT NULL,
  `actor_name` varchar(255) NOT NULL,
  `action_type` varchar(60) NOT NULL,
  `subject_type` varchar(40) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `details` text NOT NULL,
  `occurred_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`audit_id`),
  KEY `idx_admin_audit_time` (`occurred_at`),
  KEY `idx_admin_audit_subject` (`subject_type`,`subject_id`,`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_notifications` (
  `notification_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `actor_user_id` int(11) DEFAULT NULL,
  `notification_type` varchar(40) NOT NULL,
  `title` varchar(180) NOT NULL,
  `message` varchar(500) NOT NULL,
  `target_url` varchar(500) NOT NULL,
  `entity_type` varchar(40) NOT NULL,
  `entity_id` varchar(80) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`notification_id`),
  KEY `idx_task_notifications_user_unread` (`user_id`,`is_read`,`created_at`),
  KEY `idx_task_notifications_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','HR','payroll','log','super','pro','cooking','packaging','employee') NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `login_attempts` int(11) DEFAULT 0,
  `lockout_time` datetime DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uq_users_employee_id` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
