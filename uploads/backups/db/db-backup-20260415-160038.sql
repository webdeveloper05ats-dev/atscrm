-- ATS CRM Database Backup
-- Database: u440631799_crm
-- Generated at: 2026-04-15 16:00:38

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Table: assessment

DROP TABLE IF EXISTS `assessment`;
CREATE TABLE `assessment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `staff_user_id` int(11) NOT NULL,
  `assessment_1` decimal(5,2) DEFAULT NULL,
  `assessment_2` decimal(5,2) DEFAULT NULL,
  `assessment_3` decimal(5,2) DEFAULT NULL,
  `average_marks` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_assessment_registration` (`registration_id`),
  KEY `idx_assessment_branch` (`branch_id`),
  KEY `idx_assessment_staff` (`staff_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `assessment` (`id`,`registration_id`,`branch_id`,`staff_user_id`,`assessment_1`,`assessment_2`,`assessment_3`,`average_marks`,`created_at`,`updated_at`) VALUES (1,2,1,8,'89.00','89.00','89.00','89.00','2026-03-31 10:43:30','2026-03-31 10:43:30');
INSERT INTO `assessment` (`id`,`registration_id`,`branch_id`,`staff_user_id`,`assessment_1`,`assessment_2`,`assessment_3`,`average_marks`,`created_at`,`updated_at`) VALUES (2,5,1,8,'99.00','89.00','99.00','95.67','2026-04-06 10:37:55','2026-04-07 09:26:28');

-- --------------------------------------------------------
-- Table: attendance

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('Present','Absent','Late') NOT NULL,
  `topics_taught` text DEFAULT NULL,
  `task_given` text DEFAULT NULL,
  `absent_informed` enum('yes','no') DEFAULT NULL,
  `absent_reason` text DEFAULT NULL,
  `absent_informed_by` varchar(150) DEFAULT NULL,
  `marked_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance` (`user_id`,`course_id`,`attendance_date`),
  UNIQUE KEY `unique_attendance_per_day` (`user_id`,`course_id`,`attendance_date`),
  KEY `course_id` (`course_id`),
  KEY `attendance_ibfk_registration` (`registration_id`),
  CONSTRAINT `attendance_ibfk_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `attendance` (`id`,`registration_id`,`user_id`,`course_id`,`branch_id`,`attendance_date`,`status`,`topics_taught`,`task_given`,`absent_informed`,`absent_reason`,`absent_informed_by`,`marked_by`,`created_at`,`updated_at`) VALUES (1,2,2,0,1,'2026-03-30','Present','qwertyuiop','ertyuiop[',NULL,NULL,NULL,8,'2026-03-30 13:45:16','2026-03-30 08:15:16');
INSERT INTO `attendance` (`id`,`registration_id`,`user_id`,`course_id`,`branch_id`,`attendance_date`,`status`,`topics_taught`,`task_given`,`absent_informed`,`absent_reason`,`absent_informed_by`,`marked_by`,`created_at`,`updated_at`) VALUES (2,3,3,0,1,'2026-03-30','Present','wzerxctvbyunimogfvhbjnk','zwesxrdcftvgybhujnkml',NULL,NULL,NULL,12,'2026-03-31 13:16:41','2026-03-31 07:47:17');
INSERT INTO `attendance` (`id`,`registration_id`,`user_id`,`course_id`,`branch_id`,`attendance_date`,`status`,`topics_taught`,`task_given`,`absent_informed`,`absent_reason`,`absent_informed_by`,`marked_by`,`created_at`,`updated_at`) VALUES (3,3,3,0,1,'2026-03-31','Absent',NULL,NULL,'yes','3wszxerdtfvgbyhunjmk,l','student',12,'2026-03-31 13:17:58','2026-03-31 07:47:58');
INSERT INTO `attendance` (`id`,`registration_id`,`user_id`,`course_id`,`branch_id`,`attendance_date`,`status`,`topics_taught`,`task_given`,`absent_informed`,`absent_reason`,`absent_informed_by`,`marked_by`,`created_at`,`updated_at`) VALUES (4,5,5,0,1,'2026-04-06','Absent',NULL,NULL,'no',NULL,NULL,8,'2026-04-06 10:32:01','2026-04-06 05:02:01');
INSERT INTO `attendance` (`id`,`registration_id`,`user_id`,`course_id`,`branch_id`,`attendance_date`,`status`,`topics_taught`,`task_given`,`absent_informed`,`absent_reason`,`absent_informed_by`,`marked_by`,`created_at`,`updated_at`) VALUES (5,2,2,0,1,'2026-04-05','Absent',NULL,NULL,'no',NULL,NULL,8,'2026-04-06 10:37:11','2026-04-06 05:07:11');
INSERT INTO `attendance` (`id`,`registration_id`,`user_id`,`course_id`,`branch_id`,`attendance_date`,`status`,`topics_taught`,`task_given`,`absent_informed`,`absent_reason`,`absent_informed_by`,`marked_by`,`created_at`,`updated_at`) VALUES (6,5,5,0,1,'2026-04-07','Absent',NULL,NULL,'no',NULL,NULL,8,'2026-04-07 09:20:52','2026-04-07 03:50:52');

-- --------------------------------------------------------
-- Table: audit_logs

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `browser` varchar(80) DEFAULT NULL,
  `device_type` varchar(40) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `location_text` varchar(255) DEFAULT NULL,
  `location_source` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_created_at` (`created_at`),
  KEY `idx_audit_table_name` (`table_name`),
  KEY `idx_audit_user_id` (`user_id`),
  KEY `idx_audit_created_id` (`created_at`,`id`),
  KEY `idx_audit_table_created` (`table_name`,`created_at`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_table` (`table_name`),
  KEY `idx_audit_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (1,1,'LOGOUT','users',1,'::1',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-14 16:50:48');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (2,1,'LOGIN_SUCCESS','users',1,'::1',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-14 16:51:32');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (3,1,'LOGOUT','users',1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,NULL,NULL,'2026-04-14 17:09:19');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (4,1,'LOGIN_SUCCESS','users',1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,NULL,NULL,'2026-04-14 17:14:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (5,1,'LOGOUT','users',1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','Chrome','Desktop','11.0153940','76.9625670','J & p car parking, 55, Nehru Street, Gandhipuram, Ward 51, Central Zone, Coimbatore, Coimbatore North, Coimbatore, Tamil Nadu, 641001, India','gps','2026-04-14 17:16:34');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (6,1,'LOGIN_SUCCESS','users',1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-14 17:16:56');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (7,NULL,'LOGIN_FAILED','users',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-14 17:32:25');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (8,1,'LOGIN_SUCCESS','users',1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-14 17:32:48');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (9,NULL,'LOGIN_FAILED','users',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-15 13:21:36');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (10,1,'LOGIN_SUCCESS','users',1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-15 13:28:44');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (11,1,'LOGOUT','users',1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop','11.0154230','76.9625780','J & p car parking, 55, Nehru Street, Gandhipuram, Ward 51, Central Zone, Coimbatore, Coimbatore North, Coimbatore, Tamil Nadu, 641001, India','gps','2026-04-15 13:55:39');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (12,3,'LOGIN_SUCCESS','users',3,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-15 13:56:00');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (13,3,'UPDATE [targets/setup] via id','monthly_targets',18,NULL,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,NULL,NULL,'2026-04-15 13:57:33');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (14,3,'LOGOUT','users',3,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-15 13:58:21');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (15,1,'LOGIN_SUCCESS','users',1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-15 13:58:50');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (16,1,'LOGOUT','users',1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-15 14:22:08');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (17,3,'LOGIN_SUCCESS','users',3,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-15 14:22:36');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (18,3,'New target set to Michael - Rs 55,000 (April 2026)','monthly_targets',3,NULL,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,NULL,NULL,'2026-04-15 14:24:27');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (19,3,'LOGOUT','users',3,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-15 14:32:07');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (20,1,'LOGIN_SUCCESS','users',1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-15 14:32:33');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (21,1,'LOGOUT','users',1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-15 14:35:22');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (22,3,'LOGIN_SUCCESS','users',3,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-15 14:35:45');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (23,3,'ASSIGN [leads/add] via csrf_token','leads',NULL,NULL,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,NULL,NULL,'2026-04-15 14:38:32');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (24,3,'LOGOUT','users',3,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-15 14:52:13');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (25,2,'LOGIN_SUCCESS','users',2,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-15 14:52:55');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (26,2,'LOGOUT','users',2,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-15 14:55:44');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (27,3,'LOGIN_SUCCESS','users',3,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-15 14:56:13');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (28,3,'LOGOUT','users',3,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-15 15:38:36');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`table_name`,`record_id`,`ip_address`,`user_agent`,`browser`,`device_type`,`latitude`,`longitude`,`location_text`,`location_source`,`created_at`) VALUES (29,1,'LOGIN_SUCCESS','users',1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36','Chrome','Desktop',NULL,NULL,'Local/Private Network','ip','2026-04-15 15:39:14');

-- --------------------------------------------------------
-- Table: batches

DROP TABLE IF EXISTS `batches`;
CREATE TABLE `batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `batch_name` varchar(100) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `trainer_id` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `trainer_id` (`trainer_id`),
  CONSTRAINT `batches_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `batches_ibfk_2` FOREIGN KEY (`trainer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table: branches

DROP TABLE IF EXISTS `branches`;
CREATE TABLE `branches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_name` varchar(150) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `branches` (`id`,`branch_name`,`location`,`phone`,`email`,`status`,`created_at`,`updated_at`) VALUES (1,'Main Branch',NULL,NULL,NULL,1,'2026-02-24 14:43:56','2026-02-24 14:43:56');

-- --------------------------------------------------------
-- Table: categories

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table: certificates

DROP TABLE IF EXISTS `certificates`;
CREATE TABLE `certificates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `certificate_number` varchar(100) DEFAULT NULL,
  `status` enum('Pending','Issued') DEFAULT 'Pending',
  `issued_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `certificate_number` (`certificate_number`),
  KEY `user_id` (`user_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `certificates_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table: clients

DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) DEFAULT NULL,
  `hr_name` varchar(150) DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `client_type` enum('old','new','placement') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table: colleges_master

DROP TABLE IF EXISTS `colleges_master`;
CREATE TABLE `colleges_master` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `college_name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `designation` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `college_type` enum('arts','engineering','polytechnic','other') DEFAULT 'other',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `college_name` (`college_name`),
  KEY `phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table: companies

DROP TABLE IF EXISTS `companies`;
CREATE TABLE `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(150) NOT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table: contacts_master

DROP TABLE IF EXISTS `contacts_master`;
CREATE TABLE `contacts_master` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) DEFAULT NULL,
  `designation` varchar(150) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `college_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `phone` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `contacts_master` (`id`,`name`,`designation`,`email`,`phone`,`college_id`,`created_at`) VALUES (1,'John',NULL,NULL,NULL,NULL,'2026-03-19 15:37:45');

-- --------------------------------------------------------
-- Table: courses

DROP TABLE IF EXISTS `courses`;
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_name` varchar(150) NOT NULL,
  `course_code` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `fees` decimal(10,2) DEFAULT 0.00,
  `branch_id` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_code` (`course_code`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table: dailyreport_frontoffice_activity

DROP TABLE IF EXISTS `dailyreport_frontoffice_activity`;
CREATE TABLE `dailyreport_frontoffice_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master_id` int(11) NOT NULL,
  `fresh_calls` int(11) NOT NULL DEFAULT 0,
  `follow_calls` int(11) NOT NULL DEFAULT 0,
  `messages_sent` int(11) NOT NULL DEFAULT 0,
  `mails_sent` int(11) NOT NULL DEFAULT 0,
  `total_calls` int(11) NOT NULL DEFAULT 0,
  `promotions` int(11) NOT NULL DEFAULT 0,
  `reference_count` int(11) NOT NULL DEFAULT 0,
  `db_calls` int(11) NOT NULL DEFAULT 0,
  `registration_total` int(11) NOT NULL DEFAULT 0,
  `billing` decimal(12,2) NOT NULL DEFAULT 0.00,
  `fresh_collection` decimal(12,2) NOT NULL DEFAULT 0.00,
  `old_collection` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_collection` decimal(12,2) NOT NULL DEFAULT 0.00,
  `walkins` int(11) NOT NULL DEFAULT 0,
  `conversion_ratio` decimal(6,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_drf_activity_master` (`master_id`),
  KEY `idx_drfo_activity_master` (`master_id`),
  CONSTRAINT `fk_drf_activity_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dailyreport_frontoffice_activity` (`id`,`master_id`,`fresh_calls`,`follow_calls`,`messages_sent`,`mails_sent`,`total_calls`,`promotions`,`reference_count`,`db_calls`,`registration_total`,`billing`,`fresh_collection`,`old_collection`,`total_collection`,`walkins`,`conversion_ratio`,`created_at`,`updated_at`) VALUES (1,1,2,2,3,4,11,2,2,2,6,'1000.00','750.00','150.00','900.00',5,'120.00','2026-04-08 08:33:20','2026-04-08 12:43:07');
INSERT INTO `dailyreport_frontoffice_activity` (`id`,`master_id`,`fresh_calls`,`follow_calls`,`messages_sent`,`mails_sent`,`total_calls`,`promotions`,`reference_count`,`db_calls`,`registration_total`,`billing`,`fresh_collection`,`old_collection`,`total_collection`,`walkins`,`conversion_ratio`,`created_at`,`updated_at`) VALUES (7,2,4,4,4,4,16,4,4,4,12,'1400.00','0.00','1000.00','1000.00',2,'600.00','2026-04-08 10:14:10','2026-04-08 10:14:10');
INSERT INTO `dailyreport_frontoffice_activity` (`id`,`master_id`,`fresh_calls`,`follow_calls`,`messages_sent`,`mails_sent`,`total_calls`,`promotions`,`reference_count`,`db_calls`,`registration_total`,`billing`,`fresh_collection`,`old_collection`,`total_collection`,`walkins`,`conversion_ratio`,`created_at`,`updated_at`) VALUES (8,3,2,2,22,2,28,2,2,2,6,'2220.00','0.00','220.00','220.00',20,'30.00','2026-04-08 10:50:12','2026-04-08 10:50:12');
INSERT INTO `dailyreport_frontoffice_activity` (`id`,`master_id`,`fresh_calls`,`follow_calls`,`messages_sent`,`mails_sent`,`total_calls`,`promotions`,`reference_count`,`db_calls`,`registration_total`,`billing`,`fresh_collection`,`old_collection`,`total_collection`,`walkins`,`conversion_ratio`,`created_at`,`updated_at`) VALUES (10,4,4,4,4,4,16,4,4,4,12,'15000.00','250.00','1500.00','1750.00',1,'1200.00','2026-04-10 03:35:46','2026-04-10 03:35:46');
INSERT INTO `dailyreport_frontoffice_activity` (`id`,`master_id`,`fresh_calls`,`follow_calls`,`messages_sent`,`mails_sent`,`total_calls`,`promotions`,`reference_count`,`db_calls`,`registration_total`,`billing`,`fresh_collection`,`old_collection`,`total_collection`,`walkins`,`conversion_ratio`,`created_at`,`updated_at`) VALUES (11,5,2,2,2,2,8,2,2,2,6,'0.00','0.00','0.00','0.00',0,'0.00','2026-04-10 03:37:46','2026-04-10 03:37:46');

-- --------------------------------------------------------
-- Table: dailyreport_frontoffice_college_followup_rows

DROP TABLE IF EXISTS `dailyreport_frontoffice_college_followup_rows`;
CREATE TABLE `dailyreport_frontoffice_college_followup_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `serial_no` varchar(20) DEFAULT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `designation` varchar(150) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `contact_no` varchar(50) DEFAULT NULL,
  `college_name` varchar(255) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_drf_college_master` (`master_id`),
  KEY `idx_drfo_college_master_sort` (`master_id`,`sort_order`),
  CONSTRAINT `fk_drf_college_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dailyreport_frontoffice_college_followup_rows` (`id`,`master_id`,`sort_order`,`serial_no`,`contact_name`,`designation`,`email`,`contact_no`,`college_name`,`location`,`created_at`,`updated_at`) VALUES (5,1,1,'1','Swarna','HOD\\CSE','hod.cse.svcet@gmail.com','9842749646','S.VeeraSammy Chettiar College of Engg','salem','2026-04-08 12:43:15','2026-04-08 12:43:15');
INSERT INTO `dailyreport_frontoffice_college_followup_rows` (`id`,`master_id`,`sort_order`,`serial_no`,`contact_name`,`designation`,`email`,`contact_no`,`college_name`,`location`,`created_at`,`updated_at`) VALUES (6,4,1,'1','ATS WEB DEVELOPER','HOD\\CSE','hod.cse.svcet@gmail.com','9842749646','S.VeeraSammy Chettiar College of Engg','salem','2026-04-10 03:35:53','2026-04-10 03:35:53');
INSERT INTO `dailyreport_frontoffice_college_followup_rows` (`id`,`master_id`,`sort_order`,`serial_no`,`contact_name`,`designation`,`email`,`contact_no`,`college_name`,`location`,`created_at`,`updated_at`) VALUES (7,5,1,'1','ATS WEB DEVELOPER','','hod.cse.svcet@gmail.com','9842749646','S.VeeraSammy Chettiar College of Engg','salem','2026-04-10 03:37:53','2026-04-10 03:37:53');

-- --------------------------------------------------------
-- Table: dailyreport_frontoffice_college_followup_status

DROP TABLE IF EXISTS `dailyreport_frontoffice_college_followup_status`;
CREATE TABLE `dailyreport_frontoffice_college_followup_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `followup_row_id` int(11) NOT NULL,
  `status_date` date NOT NULL,
  `status_text` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_drf_college_status` (`followup_row_id`,`status_date`),
  KEY `idx_drfo_college_status_row` (`followup_row_id`),
  CONSTRAINT `fk_drf_college_status_row` FOREIGN KEY (`followup_row_id`) REFERENCES `dailyreport_frontoffice_college_followup_rows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dailyreport_frontoffice_college_followup_status` (`id`,`followup_row_id`,`status_date`,`status_text`,`created_at`,`updated_at`) VALUES (5,5,'2026-04-08','Details shared','2026-04-08 12:43:16','2026-04-08 12:43:16');
INSERT INTO `dailyreport_frontoffice_college_followup_status` (`id`,`followup_row_id`,`status_date`,`status_text`,`created_at`,`updated_at`) VALUES (6,6,'2026-04-10','erfer','2026-04-10 03:35:53','2026-04-10 03:35:53');
INSERT INTO `dailyreport_frontoffice_college_followup_status` (`id`,`followup_row_id`,`status_date`,`status_text`,`created_at`,`updated_at`) VALUES (7,7,'2026-04-09','tgtgt','2026-04-10 03:37:53','2026-04-10 03:37:53');

-- --------------------------------------------------------
-- Table: dailyreport_frontoffice_database_followup_rows

DROP TABLE IF EXISTS `dailyreport_frontoffice_database_followup_rows`;
CREATE TABLE `dailyreport_frontoffice_database_followup_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `serial_no` varchar(20) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `college` varchar(255) DEFAULT NULL,
  `mobile` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_drf_db_master` (`master_id`),
  KEY `idx_drfo_db_master_sort` (`master_id`,`sort_order`),
  CONSTRAINT `fk_drf_db_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dailyreport_frontoffice_database_followup_rows` (`id`,`master_id`,`sort_order`,`serial_no`,`name`,`department`,`college`,`mobile`,`created_at`,`updated_at`) VALUES (5,2,1,'1','Palanisamy','CHE','ABC','9876543210','2026-04-08 10:14:19','2026-04-08 10:14:19');
INSERT INTO `dailyreport_frontoffice_database_followup_rows` (`id`,`master_id`,`sort_order`,`serial_no`,`name`,`department`,`college`,`mobile`,`created_at`,`updated_at`) VALUES (6,3,1,'1','Sridharshan','CHEM','Kongu','9876543210','2026-04-08 10:50:22','2026-04-08 10:50:22');
INSERT INTO `dailyreport_frontoffice_database_followup_rows` (`id`,`master_id`,`sort_order`,`serial_no`,`name`,`department`,`college`,`mobile`,`created_at`,`updated_at`) VALUES (7,1,1,'1','Bavatharavi','MCA','Mahaligam college of engineering and technology','6369623603','2026-04-08 12:43:16','2026-04-08 12:43:16');
INSERT INTO `dailyreport_frontoffice_database_followup_rows` (`id`,`master_id`,`sort_order`,`serial_no`,`name`,`department`,`college`,`mobile`,`created_at`,`updated_at`) VALUES (8,4,1,'1','Bavatharavi','MCA','Mahaligam college of engineering and technology','6369623603','2026-04-10 03:35:54','2026-04-10 03:35:54');
INSERT INTO `dailyreport_frontoffice_database_followup_rows` (`id`,`master_id`,`sort_order`,`serial_no`,`name`,`department`,`college`,`mobile`,`created_at`,`updated_at`) VALUES (9,4,2,'2','Palanisamy','MCA','Mahaligam college of engineering and technology','6369623603','2026-04-10 03:35:55','2026-04-10 03:35:55');
INSERT INTO `dailyreport_frontoffice_database_followup_rows` (`id`,`master_id`,`sort_order`,`serial_no`,`name`,`department`,`college`,`mobile`,`created_at`,`updated_at`) VALUES (10,5,1,'1','Bavatharavi','MCA','Mahaligam college of engineering and technology','6369623603','2026-04-10 03:37:54','2026-04-10 03:37:54');

-- --------------------------------------------------------
-- Table: dailyreport_frontoffice_database_followup_status

DROP TABLE IF EXISTS `dailyreport_frontoffice_database_followup_status`;
CREATE TABLE `dailyreport_frontoffice_database_followup_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `database_row_id` int(11) NOT NULL,
  `status_date` date NOT NULL,
  `status_text` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_drf_db_status` (`database_row_id`,`status_date`),
  KEY `idx_drfo_db_status_row` (`database_row_id`),
  CONSTRAINT `fk_drf_db_status_row` FOREIGN KEY (`database_row_id`) REFERENCES `dailyreport_frontoffice_database_followup_rows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dailyreport_frontoffice_database_followup_status` (`id`,`database_row_id`,`status_date`,`status_text`,`created_at`,`updated_at`) VALUES (5,5,'2026-04-07','Lead Status: converted | qwertyuiop','2026-04-08 10:14:19','2026-04-08 10:14:19');
INSERT INTO `dailyreport_frontoffice_database_followup_status` (`id`,`database_row_id`,`status_date`,`status_text`,`created_at`,`updated_at`) VALUES (6,6,'2026-04-06','Lead Status: converted | qwertyuio','2026-04-08 10:50:22','2026-04-08 10:50:22');
INSERT INTO `dailyreport_frontoffice_database_followup_status` (`id`,`database_row_id`,`status_date`,`status_text`,`created_at`,`updated_at`) VALUES (7,7,'2026-04-08','dicuss and call back','2026-04-08 12:43:17','2026-04-08 12:43:17');
INSERT INTO `dailyreport_frontoffice_database_followup_status` (`id`,`database_row_id`,`status_date`,`status_text`,`created_at`,`updated_at`) VALUES (8,8,'2026-04-10','regregrg','2026-04-10 03:35:55','2026-04-10 03:35:55');
INSERT INTO `dailyreport_frontoffice_database_followup_status` (`id`,`database_row_id`,`status_date`,`status_text`,`created_at`,`updated_at`) VALUES (9,9,'2026-04-10','rgergergerg','2026-04-10 03:35:55','2026-04-10 03:35:55');
INSERT INTO `dailyreport_frontoffice_database_followup_status` (`id`,`database_row_id`,`status_date`,`status_text`,`created_at`,`updated_at`) VALUES (10,10,'2026-04-09','rtgre','2026-04-10 03:37:54','2026-04-10 03:37:54');

-- --------------------------------------------------------
-- Table: dailyreport_frontoffice_hourly_rows

DROP TABLE IF EXISTS `dailyreport_frontoffice_hourly_rows`;
CREATE TABLE `dailyreport_frontoffice_hourly_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `time_from` varchar(20) DEFAULT NULL,
  `time_to` varchar(20) DEFAULT NULL,
  `particulars` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_drf_hourly_master` (`master_id`),
  KEY `idx_drfo_hourly_master_sort` (`master_id`,`sort_order`),
  CONSTRAINT `fk_drf_hourly_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dailyreport_frontoffice_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`remarks`,`created_at`,`updated_at`) VALUES (9,2,1,'09:30','10:30','following calls','fgererg','2026-04-08 10:14:17','2026-04-08 10:14:17');
INSERT INTO `dailyreport_frontoffice_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`remarks`,`created_at`,`updated_at`) VALUES (10,3,1,'09:30','10:30','following calls','ewdwe','2026-04-08 10:50:20','2026-04-08 10:50:20');
INSERT INTO `dailyreport_frontoffice_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`remarks`,`created_at`,`updated_at`) VALUES (11,1,1,'09:30','10:30','following calls','call-17 connecting call-13 not connecting -4','2026-04-08 12:43:14','2026-04-08 12:43:14');
INSERT INTO `dailyreport_frontoffice_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`remarks`,`created_at`,`updated_at`) VALUES (12,1,2,'10:30','11:30','Registation follow up','call-22 connecting call -18 not connecting -5','2026-04-08 12:43:14','2026-04-08 12:43:14');
INSERT INTO `dailyreport_frontoffice_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`remarks`,`created_at`,`updated_at`) VALUES (13,1,3,'11:30','12:30','One Hour Permission','Went out for personal reason','2026-04-08 12:43:15','2026-04-08 12:43:15');
INSERT INTO `dailyreport_frontoffice_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`remarks`,`created_at`,`updated_at`) VALUES (14,4,1,'09:30','10:30','following calls','dwsfffer ewfwerfewr ferfewrfwef werf','2026-04-10 03:35:51','2026-04-10 03:35:51');
INSERT INTO `dailyreport_frontoffice_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`remarks`,`created_at`,`updated_at`) VALUES (15,4,2,'10:30','11:30','One Hour Permission','dsfsdfwe erf ewrfewr','2026-04-10 03:35:52','2026-04-10 03:35:52');
INSERT INTO `dailyreport_frontoffice_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`remarks`,`created_at`,`updated_at`) VALUES (16,4,3,'11:30','12:30','following calls','wefwef wefwef wefwef wefwe','2026-04-10 03:35:52','2026-04-10 03:35:52');
INSERT INTO `dailyreport_frontoffice_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`remarks`,`created_at`,`updated_at`) VALUES (17,4,4,'12:30','13:30','following calls','fwef wefwf','2026-04-10 03:35:52','2026-04-10 03:35:52');
INSERT INTO `dailyreport_frontoffice_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`remarks`,`created_at`,`updated_at`) VALUES (18,5,1,'09:30','10:30','following calls','dgdfhn  ghd gdg gdgdgd','2026-04-10 03:37:52','2026-04-10 03:37:52');
INSERT INTO `dailyreport_frontoffice_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`remarks`,`created_at`,`updated_at`) VALUES (19,5,2,'10:30','11:30','following calls','dfgdfggd','2026-04-10 03:37:52','2026-04-10 03:37:52');

-- --------------------------------------------------------
-- Table: dailyreport_frontoffice_planner_rows

DROP TABLE IF EXISTS `dailyreport_frontoffice_planner_rows`;
CREATE TABLE `dailyreport_frontoffice_planner_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `time_slot` varchar(100) DEFAULT NULL,
  `activity` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_drf_plan_master` (`master_id`),
  KEY `idx_drfo_planner_master_sort` (`master_id`,`sort_order`),
  CONSTRAINT `fk_drf_plan_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dailyreport_frontoffice_planner_rows` (`id`,`master_id`,`sort_order`,`time_slot`,`activity`,`description`,`created_at`,`updated_at`) VALUES (5,2,1,'09:30 - 10:30','','','2026-04-08 10:14:17','2026-04-08 10:14:17');
INSERT INTO `dailyreport_frontoffice_planner_rows` (`id`,`master_id`,`sort_order`,`time_slot`,`activity`,`description`,`created_at`,`updated_at`) VALUES (6,3,1,'09:30 - 10:30','','','2026-04-08 10:50:19','2026-04-08 10:50:19');
INSERT INTO `dailyreport_frontoffice_planner_rows` (`id`,`master_id`,`sort_order`,`time_slot`,`activity`,`description`,`created_at`,`updated_at`) VALUES (7,1,1,'09:30 - 10:30','Planning & Prioritizing','Review daily goals, check pending follow-ups, arrange your leads.','2026-04-08 12:43:13','2026-04-08 12:43:13');
INSERT INTO `dailyreport_frontoffice_planner_rows` (`id`,`master_id`,`sort_order`,`time_slot`,`activity`,`description`,`created_at`,`updated_at`) VALUES (8,4,1,'09:30 - 10:30','Planning & Prioritizing','test','2026-04-10 03:35:51','2026-04-10 03:35:51');
INSERT INTO `dailyreport_frontoffice_planner_rows` (`id`,`master_id`,`sort_order`,`time_slot`,`activity`,`description`,`created_at`,`updated_at`) VALUES (9,5,1,'09:30 - 10:30','Planning & Prioritizing','jhmghmghj','2026-04-10 03:37:51','2026-04-10 03:37:51');
INSERT INTO `dailyreport_frontoffice_planner_rows` (`id`,`master_id`,`sort_order`,`time_slot`,`activity`,`description`,`created_at`,`updated_at`) VALUES (10,5,2,'10:30 - 11:30','Planning & Prioritizing','dfgdfgdgdfgb','2026-04-10 03:37:51','2026-04-10 03:37:51');
INSERT INTO `dailyreport_frontoffice_planner_rows` (`id`,`master_id`,`sort_order`,`time_slot`,`activity`,`description`,`created_at`,`updated_at`) VALUES (11,5,3,'11:30 - 12:30','dfgdfgd','dfgdfgdfgdfg','2026-04-10 03:37:51','2026-04-10 03:37:51');

-- --------------------------------------------------------
-- Table: dailyreport_frontoffice_registration_rows

DROP TABLE IF EXISTS `dailyreport_frontoffice_registration_rows`;
CREATE TABLE `dailyreport_frontoffice_registration_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master_id` int(11) NOT NULL,
  `serial_no` int(11) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `contact_no` varchar(30) DEFAULT NULL,
  `college` varchar(255) DEFAULT NULL,
  `date_of_registration` date DEFAULT NULL,
  `course` varchar(150) DEFAULT NULL,
  `billing` decimal(12,2) NOT NULL DEFAULT 0.00,
  `collection_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_mode` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_drf_reg_master` (`master_id`),
  KEY `idx_drfo_reg_master` (`master_id`),
  CONSTRAINT `fk_drf_reg_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dailyreport_frontoffice_registration_rows` (`id`,`master_id`,`serial_no`,`name`,`department`,`contact_no`,`college`,`date_of_registration`,`course`,`billing`,`collection_amount`,`balance_amount`,`payment_mode`,`created_at`,`updated_at`) VALUES (5,2,1,'Palanisamy','Student','9876543210','ABC','2026-04-07','DS','1500.00','310.00','1190.00','','2026-04-08 10:14:16','2026-04-08 10:14:16');
INSERT INTO `dailyreport_frontoffice_registration_rows` (`id`,`master_id`,`serial_no`,`name`,`department`,`contact_no`,`college`,`date_of_registration`,`course`,`billing`,`collection_amount`,`balance_amount`,`payment_mode`,`created_at`,`updated_at`) VALUES (6,3,1,'Sridharshan','Student','9876543210','Kongu','2026-04-06','FSWD','12000.00','12000.00','0.00','','2026-04-08 10:50:19','2026-04-08 10:50:19');
INSERT INTO `dailyreport_frontoffice_registration_rows` (`id`,`master_id`,`serial_no`,`name`,`department`,`contact_no`,`college`,`date_of_registration`,`course`,`billing`,`collection_amount`,`balance_amount`,`payment_mode`,`created_at`,`updated_at`) VALUES (7,1,1,'John','MCA','9874563321','TEST','2026-04-08','PHP','15000.00','0.00','15000.00','','2026-04-08 12:43:13','2026-04-08 12:43:13');
INSERT INTO `dailyreport_frontoffice_registration_rows` (`id`,`master_id`,`serial_no`,`name`,`department`,`contact_no`,`college`,`date_of_registration`,`course`,`billing`,`collection_amount`,`balance_amount`,`payment_mode`,`created_at`,`updated_at`) VALUES (8,4,1,'John','MCA','9874563321','TEST','2026-04-09','PHP','15000.00','1000.00','14000.00','Cash','2026-04-10 03:35:50','2026-04-10 03:35:50');
INSERT INTO `dailyreport_frontoffice_registration_rows` (`id`,`master_id`,`serial_no`,`name`,`department`,`contact_no`,`college`,`date_of_registration`,`course`,`billing`,`collection_amount`,`balance_amount`,`payment_mode`,`created_at`,`updated_at`) VALUES (9,5,1,'John','MCA','9874563321','TEST','2026-04-08','PHP','1000.00','0.00','1000.00','','2026-04-10 03:37:50','2026-04-10 03:37:50');

-- --------------------------------------------------------
-- Table: dailyreport_hr_activity

DROP TABLE IF EXISTS `dailyreport_hr_activity`;
CREATE TABLE `dailyreport_hr_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master_id` int(11) NOT NULL,
  `fresh_calls` int(11) NOT NULL DEFAULT 0,
  `follow_calls` int(11) NOT NULL DEFAULT 0,
  `messages_sent` int(11) NOT NULL DEFAULT 0,
  `mails_sent` int(11) NOT NULL DEFAULT 0,
  `total_calls` int(11) NOT NULL DEFAULT 0,
  `forum_posting` int(11) NOT NULL DEFAULT 0,
  `promotions` int(11) NOT NULL DEFAULT 0,
  `reference_count` int(11) NOT NULL DEFAULT 0,
  `db_calls` int(11) NOT NULL DEFAULT 0,
  `registration_total` int(11) NOT NULL DEFAULT 0,
  `billing` decimal(12,2) NOT NULL DEFAULT 0.00,
  `fresh_collection` decimal(12,2) NOT NULL DEFAULT 0.00,
  `old_collection` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_collection` decimal(12,2) NOT NULL DEFAULT 0.00,
  `walkins` int(11) NOT NULL DEFAULT 0,
  `conversion_ratio` decimal(6,2) NOT NULL DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dailyreport_hr_activity_master` (`master_id`),
  KEY `idx_drhr_activity_master` (`master_id`),
  CONSTRAINT `fk_dailyreport_hr_activity_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dailyreport_hr_activity` (`id`,`master_id`,`fresh_calls`,`follow_calls`,`messages_sent`,`mails_sent`,`total_calls`,`forum_posting`,`promotions`,`reference_count`,`db_calls`,`registration_total`,`billing`,`fresh_collection`,`old_collection`,`total_collection`,`walkins`,`conversion_ratio`,`remarks`,`created_at`,`updated_at`) VALUES (1,6,2,2,2,2,8,0,2,2,2,6,'14000.00','1000.00','2000.00','3000.00',1,'75.00','','2026-04-10 05:51:37','2026-04-10 06:03:59');
INSERT INTO `dailyreport_hr_activity` (`id`,`master_id`,`fresh_calls`,`follow_calls`,`messages_sent`,`mails_sent`,`total_calls`,`forum_posting`,`promotions`,`reference_count`,`db_calls`,`registration_total`,`billing`,`fresh_collection`,`old_collection`,`total_collection`,`walkins`,`conversion_ratio`,`remarks`,`created_at`,`updated_at`) VALUES (3,24,120,0,0,0,120,0,0,0,0,0,'12000.00','6000.00','4500.00','10500.00',0,'0.00','','2026-04-14 17:59:43','2026-04-14 18:17:34');

-- --------------------------------------------------------
-- Table: dailyreport_hr_college_data_rows

DROP TABLE IF EXISTS `dailyreport_hr_college_data_rows`;
CREATE TABLE `dailyreport_hr_college_data_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master_id` int(11) NOT NULL,
  `serial_no` int(11) DEFAULT NULL,
  `contact_name` varchar(150) DEFAULT NULL,
  `contact_no` varchar(50) DEFAULT NULL,
  `college_name` varchar(255) DEFAULT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `days_text` varchar(100) DEFAULT NULL,
  `resource_person` varchar(150) DEFAULT NULL,
  `requirement` text DEFAULT NULL,
  `status_text` varchar(150) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dailyreport_hr_college_data_master` (`master_id`),
  KEY `idx_drhr_cd_master` (`master_id`),
  CONSTRAINT `fk_dailyreport_hr_college_data_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dailyreport_hr_college_data_rows` (`id`,`master_id`,`serial_no`,`contact_name`,`contact_no`,`college_name`,`topic`,`days_text`,`resource_person`,`requirement`,`status_text`,`created_at`,`updated_at`) VALUES (2,6,1,'rgeg','rege','grerg','egg','eergeg','rgerg','eger','grge','2026-04-10 06:04:08','2026-04-10 06:04:08');

-- --------------------------------------------------------
-- Table: dailyreport_hr_college_followup_rows

DROP TABLE IF EXISTS `dailyreport_hr_college_followup_rows`;
CREATE TABLE `dailyreport_hr_college_followup_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `name` varchar(150) DEFAULT NULL,
  `position` varchar(150) DEFAULT NULL,
  `mail_id` varchar(150) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `report_text` text DEFAULT NULL,
  `college` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dailyreport_hr_college_followup_master` (`master_id`),
  KEY `idx_drhr_cf_master_sort` (`master_id`,`sort_order`),
  CONSTRAINT `fk_dailyreport_hr_college_followup_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dailyreport_hr_college_followup_rows` (`id`,`master_id`,`sort_order`,`name`,`position`,`mail_id`,`contact_number`,`report_text`,`college`,`created_at`,`updated_at`) VALUES (2,6,1,'regeg','regerg','regreger','ergreg','regerg','gregg','2026-04-10 06:04:08','2026-04-10 06:04:08');

-- --------------------------------------------------------
-- Table: dailyreport_hr_hourly_rows

DROP TABLE IF EXISTS `dailyreport_hr_hourly_rows`;
CREATE TABLE `dailyreport_hr_hourly_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `time_from` varchar(20) DEFAULT NULL,
  `time_to` varchar(20) DEFAULT NULL,
  `particulars` varchar(255) DEFAULT NULL,
  `activities_undergone` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dailyreport_hr_hourly_master` (`master_id`),
  KEY `idx_dailyreport_hr_hourly_sort` (`master_id`,`sort_order`),
  KEY `idx_drhr_hourly_master_sort` (`master_id`,`sort_order`),
  CONSTRAINT `fk_dailyreport_hr_hourly_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dailyreport_hr_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`activities_undergone`,`created_at`,`updated_at`) VALUES (4,6,1,'09:30','10:30','Follow ups/Best performer','Follow ups/Best performer','2026-04-10 06:04:04','2026-04-10 06:04:04');
INSERT INTO `dailyreport_hr_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`activities_undergone`,`created_at`,`updated_at`) VALUES (5,6,2,'10:30','11:30','Follow ups/Best performer','Follow ups/Best performer','2026-04-10 06:04:04','2026-04-10 06:04:04');
INSERT INTO `dailyreport_hr_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`activities_undergone`,`created_at`,`updated_at`) VALUES (6,6,3,'11:30','12:30','Follow ups/Best performer','Follow ups/Best performer','2026-04-10 06:04:05','2026-04-10 06:04:05');
INSERT INTO `dailyreport_hr_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`activities_undergone`,`created_at`,`updated_at`) VALUES (16,24,1,'09:30','10:30','Internship followups & Searching the company','Ui/UX designing(Bangalore)','2026-04-14 18:17:34','2026-04-14 18:17:34');
INSERT INTO `dailyreport_hr_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`activities_undergone`,`created_at`,`updated_at`) VALUES (17,24,2,'10:30','11:30','Placements calls','For ReactJS, UI/UX Designing, full stack python & dotnet','2026-04-14 18:17:34','2026-04-14 18:17:34');
INSERT INTO `dailyreport_hr_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`activities_undergone`,`created_at`,`updated_at`) VALUES (18,24,3,'11:30','12:30','Class taken','Nirmala college(B.Com BA)','2026-04-14 18:17:34','2026-04-14 18:17:34');
INSERT INTO `dailyreport_hr_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`activities_undergone`,`created_at`,`updated_at`) VALUES (19,24,4,'12:30','13:30','Class Taken & Database calls','Digital Marketing - Nirmala college(B.Com BA) & PSG - B.Com BA','2026-04-14 18:17:34','2026-04-14 18:17:34');
INSERT INTO `dailyreport_hr_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`activities_undergone`,`created_at`,`updated_at`) VALUES (20,24,5,'13:30','14:50','Database calls','PSG - B.Com BA','2026-04-14 18:17:34','2026-04-14 18:17:34');
INSERT INTO `dailyreport_hr_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`activities_undergone`,`created_at`,`updated_at`) VALUES (21,24,6,'14:50','15:30','Lunch','','2026-04-14 18:17:34','2026-04-14 18:17:34');
INSERT INTO `dailyreport_hr_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`activities_undergone`,`created_at`,`updated_at`) VALUES (22,24,7,'15:30','16:30','Wishes Calls','Tamil New Year wishes','2026-04-14 18:17:34','2026-04-14 18:17:34');
INSERT INTO `dailyreport_hr_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`activities_undergone`,`created_at`,`updated_at`) VALUES (23,24,8,'16:30','17:30','Wishes calls','Tamil New Year - wishes','2026-04-14 18:17:34','2026-04-14 18:17:34');
INSERT INTO `dailyreport_hr_hourly_rows` (`id`,`master_id`,`sort_order`,`time_from`,`time_to`,`particulars`,`activities_undergone`,`created_at`,`updated_at`) VALUES (24,24,9,'17:30','18:30','Wishes calls & Report writing','Tamil New Year - wishes','2026-04-14 18:17:34','2026-04-14 18:17:34');

-- --------------------------------------------------------
-- Table: dailyreport_hr_internship_rows

DROP TABLE IF EXISTS `dailyreport_hr_internship_rows`;
CREATE TABLE `dailyreport_hr_internship_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master_id` int(11) NOT NULL,
  `serial_no` int(11) DEFAULT NULL,
  `staff_name` varchar(150) DEFAULT NULL,
  `college_name` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `student_count` int(11) NOT NULL DEFAULT 0,
  `platform` varchar(100) DEFAULT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `mode_type` varchar(50) DEFAULT NULL,
  `duration_text` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `finish_date` date DEFAULT NULL,
  `mini_project` varchar(100) DEFAULT NULL,
  `date_1` date DEFAULT NULL,
  `topic_1` text DEFAULT NULL,
  `date_2` date DEFAULT NULL,
  `topic_2` text DEFAULT NULL,
  `date_3` date DEFAULT NULL,
  `topic_3` text DEFAULT NULL,
  `date_4` date DEFAULT NULL,
  `topic_4` text DEFAULT NULL,
  `date_5` date DEFAULT NULL,
  `topic_5` text DEFAULT NULL,
  `date_6` date DEFAULT NULL,
  `topic_6` text DEFAULT NULL,
  `date_7` date DEFAULT NULL,
  `topic_7` text DEFAULT NULL,
  `date_8` date DEFAULT NULL,
  `topic_8` text DEFAULT NULL,
  `date_9` date DEFAULT NULL,
  `topic_9` text DEFAULT NULL,
  `date_10` date DEFAULT NULL,
  `topic_10` text DEFAULT NULL,
  `date_11` date DEFAULT NULL,
  `topic_11` text DEFAULT NULL,
  `date_12` date DEFAULT NULL,
  `topic_12` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dailyreport_hr_internship_master` (`master_id`),
  KEY `idx_drhr_intern_master` (`master_id`),
  CONSTRAINT `fk_dailyreport_hr_internship_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dailyreport_hr_internship_rows` (`id`,`master_id`,`serial_no`,`staff_name`,`college_name`,`department`,`student_count`,`platform`,`topic`,`mode_type`,`duration_text`,`start_date`,`finish_date`,`mini_project`,`date_1`,`topic_1`,`date_2`,`topic_2`,`date_3`,`topic_3`,`date_4`,`topic_4`,`date_5`,`topic_5`,`date_6`,`topic_6`,`date_7`,`topic_7`,`date_8`,`topic_8`,`date_9`,`topic_9`,`date_10`,`topic_10`,`date_11`,`topic_11`,`date_12`,`topic_12`,`created_at`,`updated_at`) VALUES (2,6,1,'dsg','reerer','rrefer',30,'ewr','34','rfwfwef','ffwewf','2026-03-04','2026-04-01','rg',NULL,'',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 06:04:05','2026-04-10 06:04:05');

-- --------------------------------------------------------
-- Table: dailyreport_hr_interview_rows

DROP TABLE IF EXISTS `dailyreport_hr_interview_rows`;
CREATE TABLE `dailyreport_hr_interview_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `candidate_name` varchar(150) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `interview_date` date DEFAULT NULL,
  `interview_status` varchar(100) DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dailyreport_hr_interview_master` (`master_id`),
  KEY `idx_dailyreport_hr_interview_date` (`master_id`,`interview_date`),
  KEY `idx_drhr_interview_master_sort` (`master_id`,`sort_order`),
  CONSTRAINT `fk_dailyreport_hr_interview_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dailyreport_hr_interview_rows` (`id`,`master_id`,`sort_order`,`candidate_name`,`company_name`,`interview_date`,`interview_status`,`remark`,`created_at`,`updated_at`) VALUES (2,6,1,NULL,'ebergerger','2026-04-15','frergerg','rgergre','2026-04-10 06:04:06','2026-04-10 06:04:06');

-- --------------------------------------------------------
-- Table: dailyreport_hr_new_client_rows

DROP TABLE IF EXISTS `dailyreport_hr_new_client_rows`;
CREATE TABLE `dailyreport_hr_new_client_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `company_name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `hr_name` varchar(150) DEFAULT NULL,
  `contact_number` varchar(120) DEFAULT NULL,
  `status_text` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dailyreport_hr_new_client_master` (`master_id`),
  KEY `idx_drhr_new_master_sort` (`master_id`,`sort_order`),
  CONSTRAINT `fk_dailyreport_hr_new_client_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dailyreport_hr_new_client_rows` (`id`,`master_id`,`sort_order`,`company_name`,`address`,`city`,`hr_name`,`contact_number`,`status_text`,`created_at`,`updated_at`) VALUES (2,6,1,'rfgr','rgg','ggrreg','ererg','rgg','erge','2026-04-10 06:04:07','2026-04-10 06:04:07');

-- --------------------------------------------------------
-- Table: dailyreport_hr_old_client_rows

DROP TABLE IF EXISTS `dailyreport_hr_old_client_rows`;
CREATE TABLE `dailyreport_hr_old_client_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master_id` int(11) NOT NULL,
  `serial_no` int(11) DEFAULT NULL,
  `client_company` varchar(255) DEFAULT NULL,
  `poc` varchar(150) DEFAULT NULL,
  `contact_no` varchar(100) DEFAULT NULL,
  `email_id` varchar(150) DEFAULT NULL,
  `followup_date` date DEFAULT NULL,
  `followup_report` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dailyreport_hr_old_client_master` (`master_id`),
  KEY `idx_drhr_old_master` (`master_id`),
  CONSTRAINT `fk_dailyreport_hr_old_client_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dailyreport_hr_old_client_rows` (`id`,`master_id`,`serial_no`,`client_company`,`poc`,`contact_no`,`email_id`,`followup_date`,`followup_report`,`created_at`,`updated_at`) VALUES (2,6,1,'rfereg','erger','ggrgr','rgegg','2026-04-10','gergerger','2026-04-10 06:04:07','2026-04-10 06:04:07');
INSERT INTO `dailyreport_hr_old_client_rows` (`id`,`master_id`,`serial_no`,`client_company`,`poc`,`contact_no`,`email_id`,`followup_date`,`followup_report`,`created_at`,`updated_at`) VALUES (4,24,1,'','','','','2026-04-14','','2026-04-14 18:17:34','2026-04-14 18:17:34');

-- --------------------------------------------------------
-- Table: dailyreport_hr_placement_call_rows

DROP TABLE IF EXISTS `dailyreport_hr_placement_call_rows`;
CREATE TABLE `dailyreport_hr_placement_call_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `master_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `entry_date` date DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `poc_name` varchar(150) DEFAULT NULL,
  `contact_no` varchar(100) DEFAULT NULL,
  `status_text` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dailyreport_hr_placement_master` (`master_id`),
  KEY `idx_drhr_placement_master_sort` (`master_id`,`sort_order`),
  CONSTRAINT `fk_dailyreport_hr_placement_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dailyreport_hr_placement_call_rows` (`id`,`master_id`,`sort_order`,`entry_date`,`company_name`,`poc_name`,`contact_no`,`status_text`,`remarks`,`created_at`,`updated_at`) VALUES (2,6,1,'2026-04-10','rger','greer','rerer','gerer','ererfger','2026-04-10 06:04:06','2026-04-10 06:04:06');
INSERT INTO `dailyreport_hr_placement_call_rows` (`id`,`master_id`,`sort_order`,`entry_date`,`company_name`,`poc_name`,`contact_no`,`status_text`,`remarks`,`created_at`,`updated_at`) VALUES (4,24,1,'2026-04-14','','','','','','2026-04-14 18:17:34','2026-04-14 18:17:34');

-- --------------------------------------------------------
-- Table: dailyreport_marketing_act_report_rows

DROP TABLE IF EXISTS `dailyreport_marketing_act_report_rows`;
CREATE TABLE `dailyreport_marketing_act_report_rows` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `master_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `metric_name` varchar(150) NOT NULL,
  `day_1` varchar(50) DEFAULT NULL,
  `day_2` varchar(50) DEFAULT NULL,
  `day_3` varchar(50) DEFAULT NULL,
  `day_4` varchar(50) DEFAULT NULL,
  `day_5` varchar(50) DEFAULT NULL,
  `day_6` varchar(50) DEFAULT NULL,
  `day_7` varchar(50) DEFAULT NULL,
  `day_8` varchar(50) DEFAULT NULL,
  `day_9` varchar(50) DEFAULT NULL,
  `day_10` varchar(50) DEFAULT NULL,
  `day_11` varchar(50) DEFAULT NULL,
  `day_12` varchar(50) DEFAULT NULL,
  `day_13` varchar(50) DEFAULT NULL,
  `day_14` varchar(50) DEFAULT NULL,
  `day_15` varchar(50) DEFAULT NULL,
  `day_16` varchar(50) DEFAULT NULL,
  `day_17` varchar(50) DEFAULT NULL,
  `day_18` varchar(50) DEFAULT NULL,
  `day_19` varchar(50) DEFAULT NULL,
  `day_20` varchar(50) DEFAULT NULL,
  `day_21` varchar(50) DEFAULT NULL,
  `day_22` varchar(50) DEFAULT NULL,
  `day_23` varchar(50) DEFAULT NULL,
  `day_24` varchar(50) DEFAULT NULL,
  `day_25` varchar(50) DEFAULT NULL,
  `day_26` varchar(50) DEFAULT NULL,
  `day_27` varchar(50) DEFAULT NULL,
  `day_28` varchar(50) DEFAULT NULL,
  `day_29` varchar(50) DEFAULT NULL,
  `day_30` varchar(50) DEFAULT NULL,
  `day_31` varchar(50) DEFAULT NULL,
  `total_value` varchar(80) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_marketing_act_master` (`master_id`,`sort_order`),
  KEY `idx_drmk_act_master_sort` (`master_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


-- --------------------------------------------------------
-- Table: dailyreport_marketing_activity

DROP TABLE IF EXISTS `dailyreport_marketing_activity`;
CREATE TABLE `dailyreport_marketing_activity` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `master_id` int(10) unsigned NOT NULL,
  `fresh_calls` int(11) NOT NULL DEFAULT 0,
  `follow_calls` int(11) NOT NULL DEFAULT 0,
  `messages_sent` int(11) NOT NULL DEFAULT 0,
  `mails_sent` int(11) NOT NULL DEFAULT 0,
  `forum_posting` int(11) NOT NULL DEFAULT 0,
  `total_calls` int(11) NOT NULL DEFAULT 0,
  `promotions` int(11) NOT NULL DEFAULT 0,
  `reference_count` int(11) NOT NULL DEFAULT 0,
  `db_calls` int(11) NOT NULL DEFAULT 0,
  `registration_total` int(11) NOT NULL DEFAULT 0,
  `billing` decimal(12,2) NOT NULL DEFAULT 0.00,
  `fresh_collection` decimal(12,2) NOT NULL DEFAULT 0.00,
  `old_collection` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_collection` decimal(12,2) NOT NULL DEFAULT 0.00,
  `walkins` int(11) NOT NULL DEFAULT 0,
  `conversion_ratio` decimal(8,2) NOT NULL DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_marketing_activity_master` (`master_id`),
  KEY `idx_marketing_activity_master` (`master_id`),
  KEY `idx_drmk_activity_master` (`master_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


-- --------------------------------------------------------
-- Table: dailyreport_marketing_amount_rows

DROP TABLE IF EXISTS `dailyreport_marketing_amount_rows`;
CREATE TABLE `dailyreport_marketing_amount_rows` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `master_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `serial_no` int(11) NOT NULL DEFAULT 0,
  `entry_date` date DEFAULT NULL,
  `college_name` varchar(255) DEFAULT NULL,
  `dept_or_name` varchar(190) DEFAULT NULL,
  `particulars` varchar(255) DEFAULT NULL,
  `bank` decimal(12,2) NOT NULL DEFAULT 0.00,
  `cash` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_marketing_amount_master` (`master_id`,`sort_order`),
  KEY `idx_drmk_amount_master_sort` (`master_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


-- --------------------------------------------------------
-- Table: dailyreport_marketing_arts_college_rows

DROP TABLE IF EXISTS `dailyreport_marketing_arts_college_rows`;
CREATE TABLE `dailyreport_marketing_arts_college_rows` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `master_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `serial_no` int(11) NOT NULL DEFAULT 0,
  `college_name` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `designation` varchar(120) DEFAULT NULL,
  `phone_number` varchar(30) DEFAULT NULL,
  `email_id` varchar(190) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_marketing_arts_college_master` (`master_id`,`sort_order`),
  KEY `idx_drmk_arts_college_master_sort` (`master_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


-- --------------------------------------------------------
-- Table: dailyreport_marketing_arts_pc_rows

DROP TABLE IF EXISTS `dailyreport_marketing_arts_pc_rows`;
CREATE TABLE `dailyreport_marketing_arts_pc_rows` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `master_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `serial_no` int(11) NOT NULL DEFAULT 0,
  `place_name` varchar(150) DEFAULT NULL,
  `college_name` varchar(255) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `designation` varchar(120) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_marketing_arts_pc_master` (`master_id`,`sort_order`),
  KEY `idx_drmk_arts_pc_master_sort` (`master_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


-- --------------------------------------------------------
-- Table: dailyreport_marketing_colleges_rows

DROP TABLE IF EXISTS `dailyreport_marketing_colleges_rows`;
CREATE TABLE `dailyreport_marketing_colleges_rows` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `master_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `serial_no` int(11) NOT NULL DEFAULT 0,
  `entry_date` date DEFAULT NULL,
  `college_name` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `designation` varchar(120) DEFAULT NULL,
  `mobile_no` varchar(30) DEFAULT NULL,
  `mail_id` varchar(190) DEFAULT NULL,
  `status_1` varchar(255) DEFAULT NULL,
  `status_2` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_marketing_colleges_master` (`master_id`,`sort_order`),
  KEY `idx_drmk_college_master_sort` (`master_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


-- --------------------------------------------------------
-- Table: dailyreport_marketing_engg_college_rows

DROP TABLE IF EXISTS `dailyreport_marketing_engg_college_rows`;
CREATE TABLE `dailyreport_marketing_engg_college_rows` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `master_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `serial_no` int(11) NOT NULL DEFAULT 0,
  `college_name` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `designation` varchar(120) DEFAULT NULL,
  `phone_number` varchar(30) DEFAULT NULL,
  `email_id` varchar(190) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `doa` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_marketing_engg_college_master` (`master_id`,`sort_order`),
  KEY `idx_drmk_engg_college_master_sort` (`master_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


-- --------------------------------------------------------
-- Table: dailyreport_marketing_engg_pc_rows

DROP TABLE IF EXISTS `dailyreport_marketing_engg_pc_rows`;
CREATE TABLE `dailyreport_marketing_engg_pc_rows` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `master_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `serial_no` int(11) NOT NULL DEFAULT 0,
  `place_name` varchar(150) DEFAULT NULL,
  `college_name` varchar(255) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `email_id` varchar(190) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_marketing_engg_pc_master` (`master_id`,`sort_order`),
  KEY `idx_drmk_engg_pc_master_sort` (`master_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


-- --------------------------------------------------------
-- Table: dailyreport_marketing_hourly_rows

DROP TABLE IF EXISTS `dailyreport_marketing_hourly_rows`;
CREATE TABLE `dailyreport_marketing_hourly_rows` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `master_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `time_from` varchar(10) DEFAULT NULL,
  `time_to` varchar(10) DEFAULT NULL,
  `particulars` varchar(255) DEFAULT NULL,
  `activities_undergone` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_marketing_hourly_master` (`master_id`,`sort_order`),
  KEY `idx_drmk_hourly_master_sort` (`master_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


-- --------------------------------------------------------
-- Table: dailyreport_marketing_polytech_college_rows

DROP TABLE IF EXISTS `dailyreport_marketing_polytech_college_rows`;
CREATE TABLE `dailyreport_marketing_polytech_college_rows` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `master_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `serial_no` int(11) NOT NULL DEFAULT 0,
  `college_name` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `designation` varchar(120) DEFAULT NULL,
  `phone_number` varchar(30) DEFAULT NULL,
  `email_id` varchar(190) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `doa` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_marketing_polytech_master` (`master_id`,`sort_order`),
  KEY `idx_drmk_polytech_master_sort` (`master_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


-- --------------------------------------------------------
-- Table: dailyreport_marketing_program_rows

DROP TABLE IF EXISTS `dailyreport_marketing_program_rows`;
CREATE TABLE `dailyreport_marketing_program_rows` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `master_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `serial_no` int(11) NOT NULL DEFAULT 0,
  `college` varchar(255) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `class_name` varchar(100) DEFAULT NULL,
  `program_given_by` varchar(150) DEFAULT NULL,
  `designation` varchar(120) DEFAULT NULL,
  `program_type` varchar(120) DEFAULT NULL,
  `domain` varchar(120) DEFAULT NULL,
  `trainer` varchar(150) DEFAULT NULL,
  `topics` text DEFAULT NULL,
  `no_days` varchar(30) DEFAULT NULL,
  `day_start` date DEFAULT NULL,
  `end_day` date DEFAULT NULL,
  `hours` varchar(30) DEFAULT NULL,
  `no_of_students` int(11) NOT NULL DEFAULT 0,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `collection` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_marketing_program_master` (`master_id`,`sort_order`),
  KEY `idx_drmk_program_master_sort` (`master_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


-- --------------------------------------------------------
-- Table: dailyreport_marketing_prospect_rows

DROP TABLE IF EXISTS `dailyreport_marketing_prospect_rows`;
CREATE TABLE `dailyreport_marketing_prospect_rows` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `master_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `serial_no` int(11) NOT NULL DEFAULT 0,
  `staff_name` varchar(150) DEFAULT NULL,
  `college` varchar(255) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `designation` varchar(120) DEFAULT NULL,
  `mobile_number` varchar(30) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `status_1` varchar(255) DEFAULT NULL,
  `date_1` date DEFAULT NULL,
  `status_2` varchar(255) DEFAULT NULL,
  `date_2` date DEFAULT NULL,
  `status_3` varchar(255) DEFAULT NULL,
  `date_3` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_marketing_prospect_master` (`master_id`,`sort_order`),
  KEY `idx_drmk_prospect_master_sort` (`master_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


-- --------------------------------------------------------
-- Table: dailyreport_marketing_prospect_status_rows

DROP TABLE IF EXISTS `dailyreport_marketing_prospect_status_rows`;
CREATE TABLE `dailyreport_marketing_prospect_status_rows` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `prospect_row_id` int(10) unsigned NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `status_date` date DEFAULT NULL,
  `status_text` varchar(255) DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mk_prospect_status_row` (`prospect_row_id`,`sort_order`),
  KEY `idx_drmk_prospect_status_row_sort` (`prospect_row_id`,`sort_order`),
  CONSTRAINT `fk_mk_prospect_status_row` FOREIGN KEY (`prospect_row_id`) REFERENCES `dailyreport_marketing_prospect_rows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `dailyreport_marketing_prospect_status_rows` (`id`,`prospect_row_id`,`sort_order`,`status_date`,`status_text`,`remarks`,`created_at`,`updated_at`) VALUES (1,1,1,'2026-02-19','','','2026-04-14 09:58:44','2026-04-14 09:58:44');
INSERT INTO `dailyreport_marketing_prospect_status_rows` (`id`,`prospect_row_id`,`sort_order`,`status_date`,`status_text`,`remarks`,`created_at`,`updated_at`) VALUES (2,2,1,'2026-04-16','call','call','2026-04-14 10:16:18','2026-04-14 10:16:18');
INSERT INTO `dailyreport_marketing_prospect_status_rows` (`id`,`prospect_row_id`,`sort_order`,`status_date`,`status_text`,`remarks`,`created_at`,`updated_at`) VALUES (3,3,1,NULL,'She is a Internship Coordinator , Shared the Our Brochure Through WhatsApp','','2026-04-14 10:18:37','2026-04-14 10:18:37');
INSERT INTO `dailyreport_marketing_prospect_status_rows` (`id`,`prospect_row_id`,`sort_order`,`status_date`,`status_text`,`remarks`,`created_at`,`updated_at`) VALUES (4,4,1,NULL,'Enquired About VAC 57 Students With & Without Placement 36 Hrs, Refer the Students internship also','','2026-04-14 10:18:37','2026-04-14 10:18:37');
INSERT INTO `dailyreport_marketing_prospect_status_rows` (`id`,`prospect_row_id`,`sort_order`,`status_date`,`status_text`,`remarks`,`created_at`,`updated_at`) VALUES (5,5,1,NULL,'Shared the Our Brochure Through WhatsApp','','2026-04-14 10:18:37','2026-04-14 10:18:37');
INSERT INTO `dailyreport_marketing_prospect_status_rows` (`id`,`prospect_row_id`,`sort_order`,`status_date`,`status_text`,`remarks`,`created_at`,`updated_at`) VALUES (6,6,1,NULL,'He Reffer the 15 Students for Internship','','2026-04-14 10:18:37','2026-04-14 10:18:37');
INSERT INTO `dailyreport_marketing_prospect_status_rows` (`id`,`prospect_row_id`,`sort_order`,`status_date`,`status_text`,`remarks`,`created_at`,`updated_at`) VALUES (7,7,1,NULL,'Shared the Our Brochure Through WhatsApp','','2026-04-14 10:18:37','2026-04-14 10:18:37');
INSERT INTO `dailyreport_marketing_prospect_status_rows` (`id`,`prospect_row_id`,`sort_order`,`status_date`,`status_text`,`remarks`,`created_at`,`updated_at`) VALUES (8,15,1,'2026-04-14','NOW ONLY MAM TAKING CARE OF CSE DEPT, WE SHARED OUR COMPANY PROPOSAL & MOU DOC WE NEED TO FOLLOW FOR VL PROGRAM','erfer','2026-04-14 11:41:39','2026-04-14 11:41:39');

-- --------------------------------------------------------
-- Table: dailyreport_master

DROP TABLE IF EXISTS `dailyreport_master`;
CREATE TABLE `dailyreport_master` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_date` date NOT NULL,
  `role_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `report_type` enum('frontoffice','marketing','hr') NOT NULL,
  `status` enum('draft','submitted','locked') NOT NULL DEFAULT 'draft',
  `submitted_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dailyreport_master` (`report_date`,`user_id`,`report_type`),
  KEY `idx_dailyreport_master_branch` (`branch_id`),
  KEY `idx_dailyreport_master_role` (`role_id`),
  KEY `idx_dailyreport_master_user` (`user_id`),
  KEY `idx_dm_reportdate_user_branch_type` (`report_date`,`user_id`,`branch_id`,`report_type`),
  KEY `idx_dm_user_branch_date` (`user_id`,`branch_id`,`report_date`),
  KEY `idx_dm_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dailyreport_master` (`id`,`report_date`,`role_id`,`user_id`,`branch_id`,`report_type`,`status`,`submitted_at`,`locked_at`,`created_at`,`updated_at`) VALUES (1,'2026-04-08',2,2,1,'frontoffice','submitted','2026-04-08 12:43:17',NULL,'2026-04-08 08:21:44','2026-04-08 12:43:17');
INSERT INTO `dailyreport_master` (`id`,`report_date`,`role_id`,`user_id`,`branch_id`,`report_type`,`status`,`submitted_at`,`locked_at`,`created_at`,`updated_at`) VALUES (2,'2026-04-07',2,2,1,'frontoffice','submitted','2026-04-08 10:14:20',NULL,'2026-04-08 10:11:28','2026-04-08 10:14:20');
INSERT INTO `dailyreport_master` (`id`,`report_date`,`role_id`,`user_id`,`branch_id`,`report_type`,`status`,`submitted_at`,`locked_at`,`created_at`,`updated_at`) VALUES (3,'2026-04-06',2,2,1,'frontoffice','submitted','2026-04-08 10:50:23',NULL,'2026-04-08 10:49:19','2026-04-08 10:50:23');
INSERT INTO `dailyreport_master` (`id`,`report_date`,`role_id`,`user_id`,`branch_id`,`report_type`,`status`,`submitted_at`,`locked_at`,`created_at`,`updated_at`) VALUES (4,'2026-04-10',2,7,1,'frontoffice','submitted','2026-04-10 03:35:56',NULL,'2026-04-10 03:14:08','2026-04-10 03:35:56');
INSERT INTO `dailyreport_master` (`id`,`report_date`,`role_id`,`user_id`,`branch_id`,`report_type`,`status`,`submitted_at`,`locked_at`,`created_at`,`updated_at`) VALUES (5,'2026-04-09',2,7,1,'frontoffice','submitted','2026-04-10 03:37:55',NULL,'2026-04-10 03:36:23','2026-04-10 03:37:55');
INSERT INTO `dailyreport_master` (`id`,`report_date`,`role_id`,`user_id`,`branch_id`,`report_type`,`status`,`submitted_at`,`locked_at`,`created_at`,`updated_at`) VALUES (6,'2026-04-10',3,3,1,'hr','submitted','2026-04-10 06:04:09',NULL,'2026-04-10 05:02:06','2026-04-10 06:04:09');
INSERT INTO `dailyreport_master` (`id`,`report_date`,`role_id`,`user_id`,`branch_id`,`report_type`,`status`,`submitted_at`,`locked_at`,`created_at`,`updated_at`) VALUES (9,'2026-04-08',3,3,1,'hr','draft',NULL,NULL,'2026-04-10 16:59:08','2026-04-10 16:59:08');
INSERT INTO `dailyreport_master` (`id`,`report_date`,`role_id`,`user_id`,`branch_id`,`report_type`,`status`,`submitted_at`,`locked_at`,`created_at`,`updated_at`) VALUES (10,'2026-04-11',3,3,1,'hr','draft',NULL,NULL,'2026-04-11 09:15:24','2026-04-11 09:15:24');
INSERT INTO `dailyreport_master` (`id`,`report_date`,`role_id`,`user_id`,`branch_id`,`report_type`,`status`,`submitted_at`,`locked_at`,`created_at`,`updated_at`) VALUES (12,'2026-04-11',2,2,1,'frontoffice','draft',NULL,NULL,'2026-04-11 10:06:11','2026-04-11 10:06:11');
INSERT INTO `dailyreport_master` (`id`,`report_date`,`role_id`,`user_id`,`branch_id`,`report_type`,`status`,`submitted_at`,`locked_at`,`created_at`,`updated_at`) VALUES (13,'2026-04-11',1,1,1,'frontoffice','draft',NULL,NULL,'2026-04-11 12:51:51','2026-04-11 12:51:51');
INSERT INTO `dailyreport_master` (`id`,`report_date`,`role_id`,`user_id`,`branch_id`,`report_type`,`status`,`submitted_at`,`locked_at`,`created_at`,`updated_at`) VALUES (19,'2026-04-14',2,2,1,'frontoffice','draft',NULL,NULL,'2026-04-14 10:44:12','2026-04-14 10:44:12');
INSERT INTO `dailyreport_master` (`id`,`report_date`,`role_id`,`user_id`,`branch_id`,`report_type`,`status`,`submitted_at`,`locked_at`,`created_at`,`updated_at`) VALUES (24,'2026-04-14',3,3,1,'hr','submitted','2026-04-14 18:17:34',NULL,'2026-04-14 17:44:56','2026-04-14 18:17:34');

-- --------------------------------------------------------
-- Table: enquiries

DROP TABLE IF EXISTS `enquiries`;
CREATE TABLE `enquiries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `enquiry_date` date DEFAULT NULL,
  `enquiry_no` varchar(50) DEFAULT NULL,
  `branch_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `dob` date DEFAULT NULL,
  `profession` varchar(150) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `instagram_id` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `course_interest` varchar(150) DEFAULT NULL,
  `qualification` varchar(150) DEFAULT NULL,
  `year_of_passout` smallint(6) DEFAULT NULL,
  `college` varchar(200) DEFAULT NULL,
  `percentage_marks` decimal(5,2) DEFAULT NULL,
  `father_name` varchar(150) DEFAULT NULL,
  `father_occupation` varchar(150) DEFAULT NULL,
  `father_contact_no` varchar(20) DEFAULT NULL,
  `parent_email` varchar(150) DEFAULT NULL,
  `software_languages_known` text DEFAULT NULL,
  `technologies` text DEFAULT NULL,
  `interested_in` text DEFAULT NULL,
  `placements_required` tinyint(1) DEFAULT 0,
  `know_about` text DEFAULT NULL,
  `know_about_other` varchar(200) DEFAULT NULL,
  `candidate_signature_path` varchar(255) DEFAULT NULL,
  `counselor_signature_path` varchar(255) DEFAULT NULL,
  `status` enum('new','followup','converted','closed') DEFAULT 'new',
  `handled_by` int(11) DEFAULT NULL,
  `converted_registration_id` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `platform` varchar(20) DEFAULT 'web',
  `app_version` varchar(20) DEFAULT NULL,
  `device_type` varchar(50) DEFAULT NULL,
  `created_ip` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_enquiry_no` (`enquiry_no`),
  KEY `idx_enquiries_branch` (`branch_id`),
  KEY `idx_enquiries_status` (`status`),
  KEY `idx_enquiries_handled_by` (`handled_by`),
  KEY `idx_enquiries_date` (`enquiry_date`),
  CONSTRAINT `enquiries_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `enquiries` (`id`,`enquiry_date`,`enquiry_no`,`branch_id`,`name`,`dob`,`profession`,`gender`,`address`,`instagram_id`,`phone`,`email`,`course_interest`,`qualification`,`year_of_passout`,`college`,`percentage_marks`,`father_name`,`father_occupation`,`father_contact_no`,`parent_email`,`software_languages_known`,`technologies`,`interested_in`,`placements_required`,`know_about`,`know_about_other`,`candidate_signature_path`,`counselor_signature_path`,`status`,`handled_by`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`platform`,`app_version`,`device_type`,`created_ip`) VALUES (1,'2026-03-30','ENQ-20260330-0188',1,'Suresh','2001-01-01','Student','male','7, Nehru street, Ramnagar coimbatore','nil','7402740298','ats.pythondeveloper05@gmail.com','Data Science','BE in textiles',2025,'Sri Ramakrishna Institute of Technology','89.00','Pranesh','Cab Driver','1234567890','user550@gmail.com','C,C++,python','Artificial Intelligence,Data Science,Full Stack Web Development,Web Designing,Python,Java,PHP & MySQL,Tally,MS Office,Digital Marketing','Technology Training,Internship,Placement Assistance,Project Development',1,'Other','Walk-in',NULL,NULL,'converted',10,NULL,'xzcvxbnm,.','2026-03-30 07:30:35','2026-03-30 07:31:40',10,NULL,'2401:4900:8825:644e:cd5f:391a:3b0c:1f39','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','web',NULL,NULL,NULL);
INSERT INTO `enquiries` (`id`,`enquiry_date`,`enquiry_no`,`branch_id`,`name`,`dob`,`profession`,`gender`,`address`,`instagram_id`,`phone`,`email`,`course_interest`,`qualification`,`year_of_passout`,`college`,`percentage_marks`,`father_name`,`father_occupation`,`father_contact_no`,`parent_email`,`software_languages_known`,`technologies`,`interested_in`,`placements_required`,`know_about`,`know_about_other`,`candidate_signature_path`,`counselor_signature_path`,`status`,`handled_by`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`platform`,`app_version`,`device_type`,`created_ip`) VALUES (2,'2026-03-30','ENQ-20260330-0192',1,'Joseph','2001-01-01','Student','male','qwerweetyjkghionllsdgnklhiosdghiosrgabjkbjlsdggsdhiodgshiosgdy8gsehoetsdgbjkdgsbjkSDG','qwerty','9876543210','joseph@gmail.com','Data science','BE',2023,'ATS','78.00','qwertyui','wertyuio','9876543210','parent@gmail.com','qwertyuiop','Artificial Intelligence,Python','Technology Training',1,'Walk-in','Walk-in',NULL,NULL,'converted',2,NULL,'qwertyuiop','2026-03-30 07:58:30','2026-03-30 08:02:34',2,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','web',NULL,NULL,NULL);
INSERT INTO `enquiries` (`id`,`enquiry_date`,`enquiry_no`,`branch_id`,`name`,`dob`,`profession`,`gender`,`address`,`instagram_id`,`phone`,`email`,`course_interest`,`qualification`,`year_of_passout`,`college`,`percentage_marks`,`father_name`,`father_occupation`,`father_contact_no`,`parent_email`,`software_languages_known`,`technologies`,`interested_in`,`placements_required`,`know_about`,`know_about_other`,`candidate_signature_path`,`counselor_signature_path`,`status`,`handled_by`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`platform`,`app_version`,`device_type`,`created_ip`) VALUES (3,'2026-03-30','ENQ-20260330-0213',1,'Arjun','2026-03-30','Student','male','iwdnqndNqd','ajrun_','7402740298','ats.pythondeveloper05@gmail.com','Data Science','MCA',2026,'NPR','80.00','Raghul','Developer','0987654321','sakthivelpcseb@gmail.com','python,sql','Data Science','Internship',0,'Other','Website',NULL,NULL,'converted',10,NULL,NULL,'2026-03-30 08:45:04','2026-03-30 09:22:04',10,NULL,'2401:4900:8825:644e:84a2:27d2:1df3:b321','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','web',NULL,NULL,NULL);
INSERT INTO `enquiries` (`id`,`enquiry_date`,`enquiry_no`,`branch_id`,`name`,`dob`,`profession`,`gender`,`address`,`instagram_id`,`phone`,`email`,`course_interest`,`qualification`,`year_of_passout`,`college`,`percentage_marks`,`father_name`,`father_occupation`,`father_contact_no`,`parent_email`,`software_languages_known`,`technologies`,`interested_in`,`placements_required`,`know_about`,`know_about_other`,`candidate_signature_path`,`counselor_signature_path`,`status`,`handled_by`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`platform`,`app_version`,`device_type`,`created_ip`) VALUES (4,'2026-03-30','ENQ-20260330-0217',1,'Akash','2001-12-01','Student','male','7, Nehru street, Ramnagar coimbatore','nil','9876543210','Akash@gmail.com','Data Analytics','BE in textiles',2025,'Sri Ramakrishna Institute of Technology','89.00','Pranesh','Cab Driver','1234567890','user550@gmail.com','JAVA','Artificial Intelligence,Data Science,Full Stack Web Development,Web Designing,Python,Java,PHP & MySQL,Tally,MS Office,Digital Marketing','Technology Training,Internship,Placement Assistance,Project Development',0,'Other','Instagram',NULL,NULL,'converted',10,NULL,'sadsfbdg','2026-03-30 08:50:20','2026-03-30 08:51:11',10,NULL,'2405:201:e015:50bd:9d77:1c6b:5172:a21d','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','web',NULL,NULL,NULL);
INSERT INTO `enquiries` (`id`,`enquiry_date`,`enquiry_no`,`branch_id`,`name`,`dob`,`profession`,`gender`,`address`,`instagram_id`,`phone`,`email`,`course_interest`,`qualification`,`year_of_passout`,`college`,`percentage_marks`,`father_name`,`father_occupation`,`father_contact_no`,`parent_email`,`software_languages_known`,`technologies`,`interested_in`,`placements_required`,`know_about`,`know_about_other`,`candidate_signature_path`,`counselor_signature_path`,`status`,`handled_by`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`platform`,`app_version`,`device_type`,`created_ip`) VALUES (5,'2026-04-06','ENQ-20260406-0235',1,'Sridharshan','2001-01-01','Student','male','qwertyuiop','qwertyuio','9876543210','sridharshany.2001@gmail.com','FSWD','BE',2023,'Kongu','78.00','Yogaprabhu','Business','9876543210','sriveprus@gmail.com','C','Full Stack Web Development','Technology Training,Placement Assistance',1,'Website','Reference',NULL,NULL,'converted',2,NULL,'qwertyuiop[','2026-04-06 04:33:37','2026-04-06 04:34:58',2,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','web',NULL,NULL,NULL);
INSERT INTO `enquiries` (`id`,`enquiry_date`,`enquiry_no`,`branch_id`,`name`,`dob`,`profession`,`gender`,`address`,`instagram_id`,`phone`,`email`,`course_interest`,`qualification`,`year_of_passout`,`college`,`percentage_marks`,`father_name`,`father_occupation`,`father_contact_no`,`parent_email`,`software_languages_known`,`technologies`,`interested_in`,`placements_required`,`know_about`,`know_about_other`,`candidate_signature_path`,`counselor_signature_path`,`status`,`handled_by`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`platform`,`app_version`,`device_type`,`created_ip`) VALUES (6,'2026-04-07','ENQ-20260407-0236',1,'Palanisamy','2001-01-01','Student','male','qwertyuiop','qwertyui','9876543210','webdeveloper005.ats@gmail.com','DS','BE',2023,'ABC','89.00','Prabakaran','Business','9876543210','webdeveloper05.ats@gmail.com','q','PHP & MySQL',NULL,1,'Friends/Reference','Reference',NULL,NULL,'converted',2,NULL,'qwertyui;','2026-04-07 04:01:36','2026-04-07 04:03:13',2,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','web',NULL,NULL,NULL);
INSERT INTO `enquiries` (`id`,`enquiry_date`,`enquiry_no`,`branch_id`,`name`,`dob`,`profession`,`gender`,`address`,`instagram_id`,`phone`,`email`,`course_interest`,`qualification`,`year_of_passout`,`college`,`percentage_marks`,`father_name`,`father_occupation`,`father_contact_no`,`parent_email`,`software_languages_known`,`technologies`,`interested_in`,`placements_required`,`know_about`,`know_about_other`,`candidate_signature_path`,`counselor_signature_path`,`status`,`handled_by`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`platform`,`app_version`,`device_type`,`created_ip`) VALUES (7,'2026-04-09','ENQ-20260409-0240',1,'Ragul kanna','2004-04-14','students','male','Fun Mall & Lakshmi Mills\r\nBus Stop, Avinashi Road\r\nBetween, Nava India Rd',NULL,'7402740298','ragulkanna123@gamil.com','data science','bca',2027,'abc college arts and science','78.00','suresh kumar','busineess','7777777777','counseloraccenttechnosoft@gmail.com','c c++','Artificial Intelligence,Web Designing,Python','Technology Training',1,'Website',NULL,NULL,NULL,'new',2,NULL,NULL,'2026-04-09 07:29:33','2026-04-11 14:51:03',2,2,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','web',NULL,NULL,NULL);
INSERT INTO `enquiries` (`id`,`enquiry_date`,`enquiry_no`,`branch_id`,`name`,`dob`,`profession`,`gender`,`address`,`instagram_id`,`phone`,`email`,`course_interest`,`qualification`,`year_of_passout`,`college`,`percentage_marks`,`father_name`,`father_occupation`,`father_contact_no`,`parent_email`,`software_languages_known`,`technologies`,`interested_in`,`placements_required`,`know_about`,`know_about_other`,`candidate_signature_path`,`counselor_signature_path`,`status`,`handled_by`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`platform`,`app_version`,`device_type`,`created_ip`) VALUES (8,'2026-04-11','ENQ-20260411-0241',1,'Dinesh','2001-01-01','BE','male','qwertyui','qwertyu','9876543210','webdeveloper005.ats@gmail.com','DS','BE',2021,'ABC','78.50','Suresh','Teacher','9876543210','webdeveloper05.ats@gmail.com','C','Artificial Intelligence,Data Science,Full Stack Web Development,Web Designing,Python,Java,PHP & MySQL,Tally,MS Office,Digital Marketing','Technology Training,Internship,Placement Assistance,Project Development',0,'Website,Google Search,Instagram,Facebook,Friends/Reference,Walk-in,Other','Website',NULL,NULL,'converted',2,NULL,'qwerty','2026-04-11 09:32:22','2026-04-11 12:02:34',2,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','web',NULL,NULL,NULL);

-- --------------------------------------------------------
-- Table: enquiry_followup_files

DROP TABLE IF EXISTS `enquiry_followup_files`;
CREATE TABLE `enquiry_followup_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `followup_id` int(11) NOT NULL,
  `enquiry_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` enum('audio','image','video','document','other') DEFAULT 'other',
  `original_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `followup_id` (`followup_id`),
  KEY `enquiry_id` (`enquiry_id`),
  KEY `branch_id` (`branch_id`),
  KEY `file_type` (`file_type`),
  CONSTRAINT `fk_followup_files_followup` FOREIGN KEY (`followup_id`) REFERENCES `enquiry_followups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table: enquiry_followups

DROP TABLE IF EXISTS `enquiry_followups`;
CREATE TABLE `enquiry_followups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `enquiry_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `followup_date` date NOT NULL,
  `followup_time` time DEFAULT NULL,
  `followup_type` enum('call','whatsapp','sms','email','walkin','other') DEFAULT 'call',
  `status` enum('pending','done','missed') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `next_followup_date` date DEFAULT NULL,
  `next_followup_time` time DEFAULT NULL,
  `done_at` datetime DEFAULT NULL,
  `verification_status` enum('pending','approved','rejected') DEFAULT 'pending',
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `verification_remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `enquiry_id` (`enquiry_id`),
  KEY `branch_id` (`branch_id`),
  KEY `followup_date` (`followup_date`),
  KEY `status` (`status`),
  KEY `verification_status` (`verification_status`),
  KEY `idx_ef_date_user_branch` (`followup_date`,`created_by`,`branch_id`),
  CONSTRAINT `fk_enq_followup_enquiry` FOREIGN KEY (`enquiry_id`) REFERENCES `enquiries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `enquiry_followups` (`id`,`enquiry_id`,`branch_id`,`followup_date`,`followup_time`,`followup_type`,`status`,`notes`,`next_followup_date`,`next_followup_time`,`done_at`,`verification_status`,`verified_by`,`verified_at`,`verification_remarks`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`created_at`,`updated_at`) VALUES (1,1,1,'2026-03-30','13:00:00','call','done','sadfbxcnvm.,/','2026-03-31','18:30:00','2026-03-30 07:31:40','pending',NULL,NULL,NULL,10,10,'2401:4900:8825:644e:cd5f:391a:3b0c:1f39','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-30 07:31:25','2026-03-30 07:31:40');
INSERT INTO `enquiry_followups` (`id`,`enquiry_id`,`branch_id`,`followup_date`,`followup_time`,`followup_type`,`status`,`notes`,`next_followup_date`,`next_followup_time`,`done_at`,`verification_status`,`verified_by`,`verified_at`,`verification_remarks`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`created_at`,`updated_at`) VALUES (2,2,1,'2026-03-30','13:31:00','call','done','qwertyuio','2026-03-31','13:31:00','2026-03-30 08:02:33','pending',NULL,NULL,NULL,2,2,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-03-30 08:01:48','2026-03-30 08:02:33');
INSERT INTO `enquiry_followups` (`id`,`enquiry_id`,`branch_id`,`followup_date`,`followup_time`,`followup_type`,`status`,`notes`,`next_followup_date`,`next_followup_time`,`done_at`,`verification_status`,`verified_by`,`verified_at`,`verification_remarks`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`created_at`,`updated_at`) VALUES (3,3,1,'2026-03-30',NULL,'call','pending','hello','2026-03-30','19:23:00',NULL,'pending',NULL,NULL,NULL,10,10,'2401:4900:8825:644e:84a2:27d2:1df3:b321','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-03-30 08:48:16','2026-03-30 08:48:16');
INSERT INTO `enquiry_followups` (`id`,`enquiry_id`,`branch_id`,`followup_date`,`followup_time`,`followup_type`,`status`,`notes`,`next_followup_date`,`next_followup_time`,`done_at`,`verification_status`,`verified_by`,`verified_at`,`verification_remarks`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`created_at`,`updated_at`) VALUES (4,4,1,'2026-03-30',NULL,'call','done','dafsgdbfasdfbdgfd','2026-03-30','15:20:00','2026-03-30 08:51:11','pending',NULL,NULL,NULL,10,10,'2405:201:e015:50bd:9d77:1c6b:5172:a21d','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-30 08:50:56','2026-03-30 08:51:11');
INSERT INTO `enquiry_followups` (`id`,`enquiry_id`,`branch_id`,`followup_date`,`followup_time`,`followup_type`,`status`,`notes`,`next_followup_date`,`next_followup_time`,`done_at`,`verification_status`,`verified_by`,`verified_at`,`verification_remarks`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`created_at`,`updated_at`) VALUES (5,3,1,'2026-03-30','16:30:00','sms','done','follow ups','2026-04-03','17:50:00','2026-03-30 09:22:04','pending',NULL,NULL,NULL,11,11,'2401:4900:8825:644e:5c49:878b:2a0:8236','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0','2026-03-30 09:21:45','2026-03-30 09:22:04');
INSERT INTO `enquiry_followups` (`id`,`enquiry_id`,`branch_id`,`followup_date`,`followup_time`,`followup_type`,`status`,`notes`,`next_followup_date`,`next_followup_time`,`done_at`,`verification_status`,`verified_by`,`verified_at`,`verification_remarks`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`created_at`,`updated_at`) VALUES (6,5,1,'2026-04-06','10:03:00','call','done','qwertyuio','2026-04-07','10:04:00','2026-04-06 04:34:57','pending',NULL,NULL,NULL,2,2,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-06 04:34:30','2026-04-06 04:34:57');
INSERT INTO `enquiry_followups` (`id`,`enquiry_id`,`branch_id`,`followup_date`,`followup_time`,`followup_type`,`status`,`notes`,`next_followup_date`,`next_followup_time`,`done_at`,`verification_status`,`verified_by`,`verified_at`,`verification_remarks`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`created_at`,`updated_at`) VALUES (7,6,1,'2026-04-07','09:32:00','call','done','qwertyuio','2026-04-07','09:32:00','2026-04-07 04:03:13','pending',NULL,NULL,NULL,2,2,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-07 04:02:41','2026-04-07 04:03:13');
INSERT INTO `enquiry_followups` (`id`,`enquiry_id`,`branch_id`,`followup_date`,`followup_time`,`followup_type`,`status`,`notes`,`next_followup_date`,`next_followup_time`,`done_at`,`verification_status`,`verified_by`,`verified_at`,`verification_remarks`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`created_at`,`updated_at`) VALUES (8,8,1,'2026-04-11','09:32:00','call','done','wertyuio','2026-04-12','09:33:00','2026-04-11 12:02:33','pending',NULL,NULL,NULL,2,2,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36','2026-04-11 09:33:24','2026-04-11 12:02:33');

-- --------------------------------------------------------
-- Table: enquiry_sequence

DROP TABLE IF EXISTS `enquiry_sequence`;
CREATE TABLE `enquiry_sequence` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=244 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (2,'2026-03-17 12:37:01');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (3,'2026-03-17 12:37:59');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (4,'2026-03-17 12:38:54');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (5,'2026-03-17 12:38:55');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (6,'2026-03-17 12:40:55');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (7,'2026-03-17 12:40:55');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (8,'2026-03-17 12:41:07');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (9,'2026-03-17 12:44:18');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (10,'2026-03-17 12:44:18');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (11,'2026-03-17 13:29:29');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (12,'2026-03-17 17:52:30');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (13,'2026-03-17 17:53:26');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (14,'2026-03-17 17:56:07');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (15,'2026-03-19 16:28:15');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (16,'2026-03-19 16:29:07');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (17,'2026-03-19 16:29:08');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (18,'2026-03-19 16:55:28');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (19,'2026-03-19 16:59:42');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (20,'2026-03-19 16:59:42');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (21,'2026-03-19 17:45:02');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (22,'2026-03-19 17:47:11');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (23,'2026-03-19 17:51:03');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (24,'2026-03-19 17:51:03');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (25,'2026-03-19 17:56:12');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (26,'2026-03-20 11:08:24');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (27,'2026-03-21 12:12:46');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (28,'2026-03-21 12:18:24');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (29,'2026-03-21 12:23:05');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (30,'2026-03-21 12:24:31');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (31,'2026-03-21 12:27:56');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (32,'2026-03-21 12:30:12');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (33,'2026-03-21 12:31:59');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (34,'2026-03-21 12:33:10');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (35,'2026-03-21 12:40:14');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (36,'2026-03-21 12:53:10');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (37,'2026-03-21 13:00:29');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (38,'2026-03-21 13:10:12');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (39,'2026-03-21 16:38:17');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (40,'2026-03-24 11:44:30');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (41,'2026-03-24 12:20:31');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (42,'2026-03-24 12:33:15');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (43,'2026-03-24 12:39:31');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (44,'2026-03-24 12:39:58');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (45,'2026-03-24 12:40:11');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (46,'2026-03-24 12:40:26');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (47,'2026-03-24 12:40:35');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (48,'2026-03-24 12:42:55');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (49,'2026-03-24 12:43:27');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (50,'2026-03-24 13:01:49');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (51,'2026-03-24 13:05:20');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (52,'2026-03-24 13:06:07');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (53,'2026-03-24 13:07:57');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (54,'2026-03-24 13:09:12');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (55,'2026-03-24 13:12:31');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (56,'2026-03-24 13:13:56');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (57,'2026-03-24 13:14:51');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (58,'2026-03-24 14:23:00');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (59,'2026-03-24 14:25:00');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (60,'2026-03-24 14:25:56');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (61,'2026-03-24 14:28:30');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (62,'2026-03-24 14:36:08');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (63,'2026-03-24 14:36:36');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (64,'2026-03-24 14:37:32');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (65,'2026-03-24 16:04:11');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (66,'2026-03-24 16:04:21');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (67,'2026-03-24 17:10:47');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (68,'2026-03-24 17:11:42');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (69,'2026-03-24 18:25:52');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (70,'2026-03-25 09:58:16');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (71,'2026-03-25 09:58:58');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (72,'2026-03-25 10:00:27');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (73,'2026-03-25 10:01:36');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (74,'2026-03-25 10:02:03');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (75,'2026-03-25 10:02:19');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (76,'2026-03-25 10:02:31');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (77,'2026-03-25 10:03:36');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (78,'2026-03-25 10:04:20');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (79,'2026-03-25 10:04:54');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (80,'2026-03-25 10:11:30');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (81,'2026-03-25 10:13:20');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (82,'2026-03-25 10:14:16');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (83,'2026-03-25 10:14:17');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (84,'2026-03-25 10:44:06');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (85,'2026-03-25 10:45:12');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (86,'2026-03-25 10:46:09');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (87,'2026-03-25 10:46:51');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (88,'2026-03-25 10:46:52');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (89,'2026-03-25 11:53:53');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (90,'2026-03-25 13:06:46');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (91,'2026-03-25 13:09:53');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (92,'2026-03-25 13:28:24');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (93,'2026-03-25 13:31:52');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (94,'2026-03-25 14:45:20');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (95,'2026-03-25 14:45:49');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (96,'2026-03-25 14:46:28');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (97,'2026-03-25 14:47:47');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (98,'2026-03-25 14:49:17');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (99,'2026-03-25 14:49:51');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (100,'2026-03-25 14:59:54');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (101,'2026-03-25 15:01:53');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (102,'2026-03-25 15:02:22');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (103,'2026-03-25 15:10:22');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (104,'2026-03-25 15:35:05');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (105,'2026-03-25 15:35:58');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (106,'2026-03-25 15:35:59');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (107,'2026-03-25 15:38:43');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (108,'2026-03-25 15:39:28');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (109,'2026-03-25 15:39:29');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (110,'2026-03-26 09:08:35');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (111,'2026-03-26 09:09:45');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (112,'2026-03-26 09:09:46');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (113,'2026-03-26 09:12:46');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (114,'2026-03-26 09:13:30');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (115,'2026-03-26 09:13:32');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (116,'2026-03-26 11:14:09');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (117,'2026-03-26 11:15:31');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (118,'2026-03-26 11:16:45');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (119,'2026-03-26 11:16:47');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (120,'2026-03-27 12:20:22');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (121,'2026-03-27 12:36:40');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (122,'2026-03-27 12:41:41');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (123,'2026-03-27 12:41:41');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (124,'2026-03-27 13:01:00');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (125,'2026-03-27 13:01:51');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (126,'2026-03-27 13:01:53');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (127,'2026-03-27 13:19:47');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (128,'2026-03-27 13:19:47');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (129,'2026-03-27 14:28:18');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (130,'2026-03-27 14:34:07');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (131,'2026-03-27 14:34:07');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (132,'2026-03-27 14:38:20');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (133,'2026-03-27 14:44:02');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (134,'2026-03-27 14:44:02');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (135,'2026-03-27 14:47:54');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (136,'2026-03-27 14:53:07');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (137,'2026-03-27 14:53:07');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (138,'2026-03-27 14:58:06');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (139,'2026-03-27 15:00:42');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (140,'2026-03-27 15:00:42');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (141,'2026-03-27 15:03:01');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (142,'2026-03-27 15:07:01');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (143,'2026-03-27 15:07:01');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (144,'2026-03-27 15:09:17');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (145,'2026-03-27 15:17:16');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (146,'2026-03-27 15:17:16');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (147,'2026-03-27 15:31:12');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (148,'2026-03-27 15:33:57');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (149,'2026-03-27 15:33:57');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (150,'2026-03-27 15:38:46');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (151,'2026-03-27 15:41:17');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (152,'2026-03-27 15:41:17');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (153,'2026-03-27 15:43:52');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (154,'2026-03-27 15:47:18');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (155,'2026-03-27 15:47:18');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (156,'2026-03-28 08:44:58');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (157,'2026-03-28 09:11:08');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (158,'2026-03-28 09:31:15');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (159,'2026-03-28 10:16:08');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (160,'2026-03-28 11:24:49');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (161,'2026-03-28 12:02:02');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (162,'2026-03-28 16:05:11');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (163,'2026-03-28 16:08:19');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (164,'2026-03-28 16:10:36');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (165,'2026-03-28 16:12:09');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (166,'2026-03-28 16:48:04');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (167,'2026-03-28 16:49:47');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (168,'2026-03-28 16:51:13');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (169,'2026-03-28 16:52:42');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (170,'2026-03-28 16:52:49');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (171,'2026-03-28 16:53:26');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (172,'2026-03-28 16:54:41');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (173,'2026-03-28 16:55:14');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (174,'2026-03-28 16:56:47');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (175,'2026-03-28 16:58:09');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (176,'2026-03-28 16:58:58');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (177,'2026-03-28 16:59:13');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (178,'2026-03-30 10:03:14');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (179,'2026-03-30 10:06:19');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (180,'2026-03-30 10:06:20');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (181,'2026-03-30 10:21:28');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (182,'2026-03-30 10:34:49');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (183,'2026-03-30 10:39:59');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (184,'2026-03-30 10:49:14');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (185,'2026-03-30 10:59:47');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (186,'2026-03-30 11:44:47');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (187,'2026-03-30 12:58:53');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (188,'2026-03-30 13:00:35');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (189,'2026-03-30 13:00:35');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (190,'2026-03-30 13:22:21');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (191,'2026-03-30 13:22:54');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (192,'2026-03-30 13:28:29');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (193,'2026-03-30 13:28:31');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (194,'2026-03-30 13:35:18');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (195,'2026-03-30 13:35:31');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (196,'2026-03-30 13:36:51');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (197,'2026-03-30 13:39:15');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (198,'2026-03-30 13:41:40');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (199,'2026-03-30 13:44:12');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (200,'2026-03-30 13:44:34');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (201,'2026-03-30 13:44:56');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (202,'2026-03-30 13:45:05');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (203,'2026-03-30 13:45:20');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (204,'2026-03-30 13:47:56');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (205,'2026-03-30 13:49:02');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (206,'2026-03-30 13:51:44');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (207,'2026-03-30 13:52:50');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (208,'2026-03-30 13:53:13');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (209,'2026-03-30 13:53:26');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (210,'2026-03-30 13:53:58');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (211,'2026-03-30 13:55:38');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (212,'2026-03-30 14:11:46');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (213,'2026-03-30 14:15:04');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (214,'2026-03-30 14:15:04');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (215,'2026-03-30 14:19:03');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (216,'2026-03-30 14:19:38');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (217,'2026-03-30 14:20:20');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (218,'2026-03-30 14:20:20');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (219,'2026-03-30 14:45:13');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (220,'2026-04-02 11:05:40');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (221,'2026-04-02 11:05:46');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (222,'2026-04-02 11:07:48');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (223,'2026-04-02 11:09:43');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (224,'2026-04-02 11:14:15');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (225,'2026-04-02 11:16:07');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (226,'2026-04-02 11:16:52');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (227,'2026-04-02 13:19:08');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (228,'2026-04-06 09:20:00');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (229,'2026-04-06 09:22:16');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (230,'2026-04-06 09:32:42');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (231,'2026-04-06 09:37:03');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (232,'2026-04-06 09:45:27');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (233,'2026-04-06 09:59:35');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (234,'2026-04-06 10:01:29');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (235,'2026-04-06 10:01:51');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (236,'2026-04-07 09:28:49');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (237,'2026-04-08 09:22:11');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (238,'2026-04-08 09:26:35');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (239,'2026-04-08 10:25:32');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (240,'2026-04-09 12:54:51');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (241,'2026-04-11 09:28:04');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (242,'2026-04-11 14:48:45');
INSERT INTO `enquiry_sequence` (`id`,`created_at`) VALUES (243,'2026-04-11 15:36:51');

-- --------------------------------------------------------
-- Table: fee_structures

DROP TABLE IF EXISTS `fee_structures`;
CREATE TABLE `fee_structures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `total_fee` decimal(10,2) NOT NULL,
  `installment_count` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `fee_structures_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table: followups

DROP TABLE IF EXISTS `followups`;
CREATE TABLE `followups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `contact_id` int(11) DEFAULT NULL,
  `college_id` int(11) DEFAULT NULL,
  `followup_date` date DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `next_followup_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  KEY `followup_date` (`followup_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table: interviews

DROP TABLE IF EXISTS `interviews`;
CREATE TABLE `interviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `interview_date` date NOT NULL,
  `interview_mode` enum('Online','Offline') DEFAULT 'Offline',
  `status` enum('Scheduled','Selected','Rejected','On Hold') DEFAULT 'Scheduled',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `interviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table: lead_import_batches

DROP TABLE IF EXISTS `lead_import_batches`;
CREATE TABLE `lead_import_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `total_rows` int(11) NOT NULL DEFAULT 0,
  `success_rows` int(11) NOT NULL DEFAULT 0,
  `failed_rows` int(11) NOT NULL DEFAULT 0,
  `status` enum('completed','partial','failed') NOT NULL DEFAULT 'completed',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lib_branch` (`branch_id`),
  KEY `idx_lib_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `lead_import_batches` (`id`,`branch_id`,`created_by`,`file_name`,`total_rows`,`success_rows`,`failed_rows`,`status`,`created_at`) VALUES (1,1,5,'leads_20260409_123329_8163.xlsx',54,0,54,'failed','2026-04-09 07:03:29');
INSERT INTO `lead_import_batches` (`id`,`branch_id`,`created_by`,`file_name`,`total_rows`,`success_rows`,`failed_rows`,`status`,`created_at`) VALUES (2,1,5,'leads_20260409_123402_7127.xlsx',18,14,4,'partial','2026-04-09 07:04:02');
INSERT INTO `lead_import_batches` (`id`,`branch_id`,`created_by`,`file_name`,`total_rows`,`success_rows`,`failed_rows`,`status`,`created_at`) VALUES (3,1,5,'leads_20260409_123542_8658.xlsx',18,14,4,'partial','2026-04-09 07:05:42');
INSERT INTO `lead_import_batches` (`id`,`branch_id`,`created_by`,`file_name`,`total_rows`,`success_rows`,`failed_rows`,`status`,`created_at`) VALUES (4,1,2,'leads_20260409_125335_5329.xlsx',18,18,0,'completed','2026-04-09 07:23:35');
INSERT INTO `lead_import_batches` (`id`,`branch_id`,`created_by`,`file_name`,`total_rows`,`success_rows`,`failed_rows`,`status`,`created_at`) VALUES (5,1,2,'leads_20260414_104328_8348.csv',16,16,0,'completed','2026-04-14 10:43:28');

-- --------------------------------------------------------
-- Table: leads

DROP TABLE IF EXISTS `leads`;
CREATE TABLE `leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `source` varchar(100) DEFAULT NULL,
  `course_interest` varchar(150) DEFAULT NULL,
  `company_college_name` varchar(200) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `lead_year` varchar(20) DEFAULT NULL,
  `status` enum('new','contacted','qualified','converted','closed') DEFAULT 'new',
  `qualified_at` datetime DEFAULT NULL,
  `converted_at` datetime DEFAULT NULL,
  `converted_by` int(11) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `import_batch_id` int(11) DEFAULT NULL,
  `converted_enquiry_id` int(11) DEFAULT NULL,
  `converted_registration_id` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `branch_id` (`branch_id`),
  KEY `idx_leads_import_batch_id` (`import_batch_id`),
  KEY `idx_leads_branch` (`branch_id`),
  KEY `idx_leads_assigned` (`assigned_to`),
  KEY `idx_leads_status` (`status`),
  KEY `idx_leads_created` (`created_by`),
  KEY `idx_leads_phone` (`phone`),
  CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=76 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (1,1,'Suresh','7402740298','ats.pythondeveloper05@gmail.com','Walk-in','Data Science','krishna college of arts and science','CIVIL','2','converted',NULL,NULL,NULL,10,NULL,NULL,NULL,'dgflkjlhfsdafghnmj,','2026-03-30 07:28:36','2026-03-30 07:30:35',10,10,'2401:4900:8825:644e:cd5f:391a:3b0c:1f39','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (2,1,'Joseph','9876543210','joseph@gmail.com','Walk-in','Data science','ATS','CS','2026','converted',NULL,NULL,NULL,2,NULL,NULL,NULL,'qwertyuio','2026-03-30 07:44:33','2026-03-30 07:58:30',10,2,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (3,1,'Arjun','7402740298','ats.pythondeveloper05@gmail.com','Website','Data Science','krishna college of arts and science','CIVIL','2','converted',NULL,NULL,NULL,10,NULL,NULL,NULL,'Please follow this student','2026-03-30 08:33:26','2026-03-30 08:45:04',1,10,'2405:201:e015:50bd:4ed:c645:2aa7:4635','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (4,1,'Akash','9876543210','Akash@gmail.com','Instagram','Data Analytics','krishna college of arts and science','Computer Science','1','converted',NULL,NULL,NULL,10,NULL,NULL,NULL,'asdfsghkjlhj;uioytrsfdgbnmhjgfdsadfvb','2026-03-30 08:47:04','2026-03-30 08:50:20',1,10,'2405:201:e015:50bd:9d77:1c6b:5172:a21d','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (5,1,'Akashaya','9876543210','akashaya@gmail.com','Walk-in','PHP','krishna college of arts and science','Computer Science','3','new',NULL,NULL,NULL,11,NULL,NULL,NULL,'Please follow this student','2026-03-30 08:55:49','2026-03-30 08:55:49',1,NULL,'2405:201:e015:50bd:9d77:1c6b:5172:a21d','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (6,1,'Praveen','9876543211','praveen@gmail.com','Reference','Data Science','krishna college of arts and science','BCA','1','new',NULL,NULL,NULL,11,NULL,NULL,NULL,'Please follow this student','2026-03-30 09:03:26','2026-03-30 09:03:26',1,NULL,'2401:4900:8825:644e:5c49:878b:2a0:8236','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (7,1,'Varsha','9876543210','varsha@gmail.com','Instagram','Python with BI','krishna college of arts and science','CIVIL','3','new',NULL,NULL,NULL,10,NULL,NULL,NULL,'Follow this student','2026-03-30 09:12:09','2026-03-30 09:12:09',1,NULL,'2401:4900:8825:644e:5c49:878b:2a0:8236','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (8,1,'Sridharshan','9876543210','sridharshany.2001@gmail.com','Reference','FSWD','Kongu','CHEM','2023','converted',NULL,NULL,NULL,2,NULL,NULL,NULL,'qwertyuio','2026-04-06 04:29:13','2026-04-06 04:33:37',2,2,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (9,1,'Palanisamy','9876543210','palani@gmail.com','Reference','DS','ABC','CHE','2023','converted',NULL,NULL,NULL,2,NULL,NULL,NULL,'qwertyuiop','2026-04-07 03:58:32','2026-04-07 04:01:36',2,2,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (10,1,'pramodhini','7402740298','pramodhiniganesan@gmail.com','Walk-in','data science','Nirmala college for women','bsc computer science','2026','new',NULL,NULL,NULL,10,NULL,NULL,NULL,NULL,'2026-04-09 07:02:31','2026-04-09 07:02:31',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (11,1,'Laavanya',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,'2026-04-09 07:04:02','2026-04-09 07:04:02',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (12,1,'Bharathi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,'2026-04-09 07:04:02','2026-04-09 07:04:02',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (13,1,'Name',NULL,NULL,NULL,NULL,NULL,'Department',NULL,'new',NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,'2026-04-09 07:04:02','2026-04-09 07:04:02',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (14,1,'SubaShree',NULL,NULL,NULL,NULL,NULL,'B.Sc(AI&DS)',NULL,'new',NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,'2026-04-09 07:04:02','2026-04-09 07:04:02',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (15,1,'Name',NULL,NULL,NULL,NULL,NULL,'Department',NULL,'new',NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,'2026-04-09 07:04:02','2026-04-09 07:04:02',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (16,1,'Nathiya',NULL,NULL,NULL,NULL,NULL,'ECE',NULL,'new',NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,'2026-04-09 07:04:02','2026-04-09 07:04:02',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (17,1,'Name',NULL,NULL,NULL,NULL,NULL,'Department',NULL,'new',NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,'2026-04-09 07:04:02','2026-04-09 07:04:02',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (18,1,'Abinaya Sri',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,'2026-04-09 07:04:02','2026-04-09 07:04:02',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (19,1,'Name',NULL,NULL,NULL,NULL,NULL,'Department',NULL,'new',NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,'2026-04-09 07:04:02','2026-04-09 07:04:02',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (20,1,'Priyadharshini.A',NULL,NULL,NULL,NULL,NULL,'B.Tech(IT)',NULL,'new',NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,'2026-04-09 07:04:02','2026-04-09 07:04:02',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (21,1,'Mohammed aadhil appavu .A',NULL,NULL,NULL,NULL,NULL,'B.Tech(IT)',NULL,'new',NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,'2026-04-09 07:04:02','2026-04-09 07:04:02',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (22,1,'Sivaranjini.N',NULL,NULL,NULL,NULL,NULL,'B.Tech(IT)',NULL,'new',NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,'2026-04-09 07:04:02','2026-04-09 07:04:02',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (23,1,'Name',NULL,NULL,NULL,NULL,NULL,'Department',NULL,'new',NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,'2026-04-09 07:04:02','2026-04-09 07:04:02',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (24,1,'Thivyanth Baratvaj.P',NULL,NULL,NULL,NULL,NULL,'B.E(CSE)',NULL,'new',NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,'2026-04-09 07:04:02','2026-04-09 07:04:02',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (25,1,'Laavanya',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,'2026-04-09 07:05:42','2026-04-09 07:05:42',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (26,1,'Bharathi',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,'2026-04-09 07:05:42','2026-04-09 07:05:42',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (27,1,'Name',NULL,NULL,NULL,NULL,NULL,'Department',NULL,'new',NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,'2026-04-09 07:05:42','2026-04-09 07:05:42',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (28,1,'SubaShree',NULL,NULL,NULL,NULL,NULL,'B.Sc(AI&DS)',NULL,'new',NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,'2026-04-09 07:05:42','2026-04-09 07:05:42',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (29,1,'Name',NULL,NULL,NULL,NULL,NULL,'Department',NULL,'new',NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,'2026-04-09 07:05:42','2026-04-09 07:05:42',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (30,1,'Nathiya',NULL,NULL,NULL,NULL,NULL,'ECE',NULL,'new',NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,'2026-04-09 07:05:42','2026-04-09 07:05:42',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (31,1,'Name',NULL,NULL,NULL,NULL,NULL,'Department',NULL,'new',NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,'2026-04-09 07:05:42','2026-04-09 07:05:42',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (32,1,'Abinaya Sri',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,'2026-04-09 07:05:42','2026-04-09 07:05:42',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (33,1,'Name',NULL,NULL,NULL,NULL,NULL,'Department',NULL,'new',NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,'2026-04-09 07:05:42','2026-04-09 07:05:42',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (34,1,'Priyadharshini.A',NULL,NULL,NULL,NULL,NULL,'B.Tech(IT)',NULL,'new',NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,'2026-04-09 07:05:42','2026-04-09 07:05:42',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (35,1,'Mohammed aadhil appavu .A',NULL,NULL,NULL,NULL,NULL,'B.Tech(IT)',NULL,'new',NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,'2026-04-09 07:05:42','2026-04-09 07:05:42',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (36,1,'Sivaranjini.N',NULL,NULL,NULL,NULL,NULL,'B.Tech(IT)',NULL,'new',NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,'2026-04-09 07:05:42','2026-04-09 07:05:42',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (37,1,'Name',NULL,NULL,NULL,NULL,NULL,'Department',NULL,'new',NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,'2026-04-09 07:05:42','2026-04-09 07:05:42',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (38,1,'Thivyanth Baratvaj.P',NULL,NULL,NULL,NULL,NULL,'B.E(CSE)',NULL,'new',NULL,NULL,NULL,NULL,3,NULL,NULL,NULL,'2026-04-09 07:05:42','2026-04-09 07:05:42',5,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (39,1,'juil mary','7402740225','juilmaryjhon@gmail.com','Instagram','python with data science','Nirmala college for women','bsc computer science','2026','new',NULL,NULL,NULL,10,NULL,NULL,NULL,'I WILL CHECK AND GET BACK','2026-04-09 07:23:04','2026-04-09 07:23:04',2,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (40,1,'Sangavi K',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,'2026-04-09 07:23:36','2026-04-09 07:23:36',2,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (41,1,'Saravanakumar',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,'2026-04-09 07:23:36','2026-04-09 07:23:36',2,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (42,1,'Sharmila',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,'2026-04-09 07:23:36','2026-04-09 07:23:36',2,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (43,1,'Shobika',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,'2026-04-09 07:23:36','2026-04-09 07:23:36',2,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (44,1,'Shreeya',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,'2026-04-09 07:23:36','2026-04-09 07:23:36',2,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (45,1,'Shyam',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,'2026-04-09 07:23:36','2026-04-09 07:23:36',2,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (46,1,'Sri Ram S',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,'2026-04-09 07:23:36','2026-04-09 07:23:36',2,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (47,1,'Subaha Sri',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,'2026-04-09 07:23:36','2026-04-09 07:23:36',2,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (48,1,'Sudharsun P',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,'2026-04-09 07:23:36','2026-04-09 07:23:36',2,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (49,1,'Surya Prakash S',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,'2026-04-09 07:23:36','2026-04-09 07:23:36',2,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (50,1,'Tharun R',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,'2026-04-09 07:23:36','2026-04-09 07:23:36',2,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (51,1,'Vaitheeshwaran',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,'2026-04-09 07:23:36','2026-04-09 07:23:36',2,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (52,1,'Vishmitha',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,'2026-04-09 07:23:36','2026-04-09 07:23:36',2,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (53,1,'Deepika',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,'2026-04-09 07:23:36','2026-04-09 07:23:36',2,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (54,1,'GuruDev',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,'2026-04-09 07:23:36','2026-04-09 07:23:36',2,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (55,1,'Kavyaa',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,'2026-04-09 07:23:36','2026-04-09 07:23:36',2,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (56,1,'Rokith',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,'2026-04-09 07:23:36','2026-04-09 07:23:36',2,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (57,1,'Sarvesh B',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'new',NULL,NULL,NULL,NULL,4,NULL,NULL,NULL,'2026-04-09 07:23:36','2026-04-09 07:23:36',2,NULL,'2401:4900:8826:dbf2:d57b:7138:1670:e9c8','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (58,1,'Dinesh','9876543210','webdeveloper005.ats@gmail.com','Website','DS','ABC','BE','2021','converted',NULL,NULL,NULL,2,NULL,NULL,NULL,'qwerty','2026-04-11 09:26:09','2026-04-11 09:32:23',2,2,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (59,1,'John Doe','9876543210','john@example.com','Website','Full Stack','ABC College','CSE','2026','new',NULL,NULL,NULL,NULL,5,NULL,NULL,'Interested in weekend batch','2026-04-14 10:43:28','2026-04-14 10:43:28',2,NULL,'2409:40f4:204d:a38f:c1b8:d2e7:5ec2:6844','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (60,1,'John Doe','9876543211','john@example.com','Website','Full Stack','ABC College','CSE','2027','new',NULL,NULL,NULL,NULL,5,NULL,NULL,'Interested in weekend batch','2026-04-14 10:43:28','2026-04-14 10:43:28',2,NULL,'2409:40f4:204d:a38f:c1b8:d2e7:5ec2:6844','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (61,1,'John Doe','9876543212','john@example.com','Website','Full Stack','ABC College','CSE','2028','new',NULL,NULL,NULL,NULL,5,NULL,NULL,'Interested in weekend batch','2026-04-14 10:43:28','2026-04-14 10:43:28',2,NULL,'2409:40f4:204d:a38f:c1b8:d2e7:5ec2:6844','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (62,1,'John Doe','9876543213','john@example.com','Website','Full Stack','ABC College','CSE','2029','new',NULL,NULL,NULL,NULL,5,NULL,NULL,'Interested in weekend batch','2026-04-14 10:43:28','2026-04-14 10:43:28',2,NULL,'2409:40f4:204d:a38f:c1b8:d2e7:5ec2:6844','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (63,1,'John Doe','9876543214','john@example.com','Website','Full Stack','ABC College','CSE','2030','new',NULL,NULL,NULL,NULL,5,NULL,NULL,'Interested in weekend batch','2026-04-14 10:43:28','2026-04-14 10:43:28',2,NULL,'2409:40f4:204d:a38f:c1b8:d2e7:5ec2:6844','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (64,1,'John Doe','9876543215','john@example.com','Website','Full Stack','ABC College','CSE','2031','new',NULL,NULL,NULL,NULL,5,NULL,NULL,'Interested in weekend batch','2026-04-14 10:43:28','2026-04-14 10:43:28',2,NULL,'2409:40f4:204d:a38f:c1b8:d2e7:5ec2:6844','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (65,1,'John Doe','9876543216','john@example.com','Website','Full Stack','ABC College','CSE','2032','new',NULL,NULL,NULL,NULL,5,NULL,NULL,'Interested in weekend batch','2026-04-14 10:43:28','2026-04-14 10:43:28',2,NULL,'2409:40f4:204d:a38f:c1b8:d2e7:5ec2:6844','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (66,1,'John Doe','9876543217','john@example.com','Website','Full Stack','ABC College','CSE','2033','new',NULL,NULL,NULL,NULL,5,NULL,NULL,'Interested in weekend batch','2026-04-14 10:43:28','2026-04-14 10:43:28',2,NULL,'2409:40f4:204d:a38f:c1b8:d2e7:5ec2:6844','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (67,1,'John Doe','9876543218','john@example.com','Website','Full Stack','ABC College','CSE','2034','new',NULL,NULL,NULL,NULL,5,NULL,NULL,'Interested in weekend batch','2026-04-14 10:43:28','2026-04-14 10:43:28',2,NULL,'2409:40f4:204d:a38f:c1b8:d2e7:5ec2:6844','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (68,1,'John Doe','9876543219','john@example.com','Website','Full Stack','ABC College','CSE','2035','new',NULL,NULL,NULL,NULL,5,NULL,NULL,'Interested in weekend batch','2026-04-14 10:43:28','2026-04-14 10:43:28',2,NULL,'2409:40f4:204d:a38f:c1b8:d2e7:5ec2:6844','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (69,1,'John Doe','9876543220','john@example.com','Website','Full Stack','ABC College','CSE','2036','new',NULL,NULL,NULL,NULL,5,NULL,NULL,'Interested in weekend batch','2026-04-14 10:43:28','2026-04-14 10:43:28',2,NULL,'2409:40f4:204d:a38f:c1b8:d2e7:5ec2:6844','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (70,1,'John Doe','9876543221','john@example.com','Website','Full Stack','ABC College','CSE','2037','new',NULL,NULL,NULL,NULL,5,NULL,NULL,'Interested in weekend batch','2026-04-14 10:43:28','2026-04-14 10:43:28',2,NULL,'2409:40f4:204d:a38f:c1b8:d2e7:5ec2:6844','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (71,1,'John Doe','9876543222','john@example.com','Website','Full Stack','ABC College','CSE','2038','new',NULL,NULL,NULL,NULL,5,NULL,NULL,'Interested in weekend batch','2026-04-14 10:43:28','2026-04-14 10:43:28',2,NULL,'2409:40f4:204d:a38f:c1b8:d2e7:5ec2:6844','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (72,1,'John Doe','9876543223','john@example.com','Website','Full Stack','ABC College','CSE','2039','new',NULL,NULL,NULL,NULL,5,NULL,NULL,'Interested in weekend batch','2026-04-14 10:43:28','2026-04-14 10:43:28',2,NULL,'2409:40f4:204d:a38f:c1b8:d2e7:5ec2:6844','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (73,1,'John Doe','9876543224','john@example.com','Website','Full Stack','ABC College','CSE','2040','new',NULL,NULL,NULL,NULL,5,NULL,NULL,'Interested in weekend batch','2026-04-14 10:43:28','2026-04-14 10:43:28',2,NULL,'2409:40f4:204d:a38f:c1b8:d2e7:5ec2:6844','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (74,1,'John Doe','9876543225','john@example.com','Website','Full Stack','ABC College','CSE','2041','new',NULL,NULL,NULL,NULL,5,NULL,NULL,'Interested in weekend batch','2026-04-14 10:43:28','2026-04-14 10:43:28',2,NULL,'2409:40f4:204d:a38f:c1b8:d2e7:5ec2:6844','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');
INSERT INTO `leads` (`id`,`branch_id`,`name`,`phone`,`email`,`source`,`course_interest`,`company_college_name`,`department`,`lead_year`,`status`,`qualified_at`,`converted_at`,`converted_by`,`assigned_to`,`import_batch_id`,`converted_enquiry_id`,`converted_registration_id`,`remarks`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`) VALUES (75,1,'Testname','7896541230','testname@gmail.com','Website','Full Stack Webdevelopment','Sample','BCA','2010','new',NULL,NULL,NULL,2,NULL,NULL,NULL,'fttfbhhb','2026-04-15 14:38:49','2026-04-15 14:38:49',3,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36');

-- --------------------------------------------------------
-- Table: marketing_amount

DROP TABLE IF EXISTS `marketing_amount`;
CREATE TABLE `marketing_amount` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `date` date DEFAULT NULL,
  `college` varchar(255) DEFAULT NULL,
  `dept_name` varchar(150) DEFAULT NULL,
  `particulars` text DEFAULT NULL,
  `bank` decimal(10,2) DEFAULT NULL,
  `cash` decimal(10,2) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table: menus

DROP TABLE IF EXISTS `menus`;
CREATE TABLE `menus` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_name` varchar(150) NOT NULL,
  `menu_slug` varchar(150) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (1,'Menu Management','menu_management',NULL,'fas fa-bars',3,1,'2026-02-25 10:45:15','2026-02-26 13:45:42');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (5,'User Management','user_management',NULL,'fas fa-users-cog',5,1,'2026-03-09 13:58:33','2026-03-09 13:58:33');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (6,'Add User','user_add',5,'fas fa-users',1,1,'2026-02-26 11:25:51','2026-02-26 11:25:51');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (7,'Role Management','role_management',NULL,'fas fa-user-shield',2,1,'2026-02-26 11:54:10','2026-02-26 15:47:23');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (10,'Branch Management','branch_management',NULL,'fas fa-code-branch',1,1,'2026-02-26 13:03:48','2026-02-26 13:46:04');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (11,'Permission Management','permission_management',NULL,'fas fa-key',4,1,'2026-02-26 13:05:42','2026-02-26 15:47:51');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (12,'Lead','lead',NULL,'fas fa-user-plus',6,1,'2026-02-26 13:29:10','2026-02-26 15:48:34');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (13,'Enquiries','enquiries',NULL,'fas fa-question-circle',7,1,'2026-02-26 13:30:54','2026-02-26 13:51:48');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (14,'Registrations','registrations',NULL,'fas fa-user-check',8,1,'2026-02-26 13:33:00','2026-02-26 15:48:59');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (15,'Programs','programs',NULL,'fas fa-graduation-cap',9,0,'2026-02-26 13:33:56','2026-03-11 08:25:31');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (16,'Students','students',NULL,'fas fa-user-graduate',10,1,'2026-02-26 13:35:03','2026-02-26 13:52:16');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (17,'Payments','payments/index',NULL,'fas fa-rupee-sign',11,1,'2026-02-26 13:36:05','2026-03-13 07:01:16');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (18,'Staff Panel','staffpanel',NULL,'fas fa-chalkboard-teacher',12,1,'2026-02-26 13:37:16','2026-02-26 15:49:59');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (19,'HR Panel','hr_panel',NULL,'fas fa-briefcase',13,1,'2026-02-26 13:38:11','2026-02-26 15:50:14');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (20,'Reports','reports',NULL,'fas fa-chart-line',14,1,'2026-02-26 13:39:05','2026-02-26 15:50:25');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (22,'Student Allocation','student_allocation',18,'fas fa-user-tag',1,1,'2026-02-26 14:00:47','2026-02-26 14:00:47');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (23,'Attendance','attendance',18,'fas fa-calendar-check',2,1,'2026-02-26 14:01:39','2026-02-26 14:01:39');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (25,'Assessment','assessment',18,'fas fa-tasks',4,1,'2026-02-26 14:02:35','2026-02-26 14:02:35');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (26,'Internship Report','internship_report',18,'fas fa-file-alt',5,1,'2026-02-26 14:02:58','2026-02-26 14:02:58');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (27,'Certificate Status','certificate_status',18,'fas fa-certificate',6,1,'2026-02-26 14:03:20','2026-02-26 14:03:20');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (32,'Dashboard','dashboard/frontoffice',NULL,'fas fa-home',1,1,'2026-02-27 10:25:41','2026-02-27 10:25:41');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (33,'Dashboard','dashboard/hr',NULL,'fas fa-home',1,1,'2026-02-27 10:26:33','2026-02-27 10:26:33');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (34,'Dashboard','dashboard/staff',NULL,'fas fa-home',1,1,'2026-02-27 10:27:04','2026-02-27 10:27:04');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (36,'Dashboard','dashboard/marketing',35,'fas fa-home',1,1,'2026-02-27 11:44:41','2026-02-27 11:44:41');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (38,'Dashboard','dashboard/test',NULL,'fas fa-home',1,1,'2026-02-27 13:20:11','2026-02-27 13:20:11');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (39,'Add Enquiry','enquiries/add',13,'fas fa-plus-circle',1,1,'2026-02-27 13:52:12','2026-02-27 13:52:12');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (40,'Enquiry List','enquiries/list',13,'fas fa-list',2,1,'2026-02-27 13:52:55','2026-02-27 13:52:55');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (41,'Follow-ups','enquiries/followups',13,'fas fa-phone-alt',3,1,'2026-02-27 13:53:35','2026-02-27 13:53:35');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (42,'Add Registration','registrations/add',14,'fas fa-plus-circle',1,1,'2026-03-04 11:24:02','2026-03-04 11:24:02');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (43,'Registrations List','registrations/list',14,'fas fa-list',2,1,'2026-03-04 11:24:02','2026-03-04 11:24:02');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (44,'Draft Conversions','registrations/drafts',14,'fas fa-file-alt',3,1,'2026-03-04 11:24:02','2026-03-04 11:24:02');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (45,'Convert Registration','registrations/convert',14,'fas fa-random',99,1,'2026-03-04 11:51:13','2026-03-04 11:51:13');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (46,'Add Lead','leads/add',12,'fas fa-user-plus',1,1,'2026-03-07 13:45:16','2026-03-07 13:45:16');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (47,'Lead List','leads/list',12,'fas fa-list',2,1,'2026-03-07 13:45:25','2026-03-07 13:45:25');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (48,'Targets','targets',NULL,'fas fa-bullseye',16,1,'2026-03-07 15:47:17','2026-03-07 15:47:17');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (49,'Setup Targets','targets/setup',48,'fas fa-cog',1,1,'2026-03-07 15:48:57','2026-03-07 15:48:57');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (50,'Target List','targets/list',48,'fas fa-list',2,1,'2026-03-07 15:50:10','2026-03-07 15:50:10');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (51,'My Target','targets/my-target',48,'fas fa-user-check',3,1,'2026-03-07 15:50:46','2026-03-07 15:50:46');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (52,'Target Report','targets/report',48,'fas fa-chart-line',4,1,'2026-03-07 15:51:19','2026-03-07 15:51:19');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (55,'Excel Upload','leads/import',12,'fas fa-file-excel',3,1,'2026-03-10 11:20:41','2026-03-10 11:20:41');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (56,'Assign Students','students/assign',16,'fas fa-user-tag',1,1,'2026-03-11 06:39:55','2026-03-11 06:39:55');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (57,'Intern Students','students/internships',16,'fas fa-user-clock',1,1,'2026-03-11 08:16:32','2026-03-11 08:16:32');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (58,'Mock Interview','mock_interview',18,'fas fa-user-tie',5,1,'2026-03-16 03:46:53','2026-03-16 04:04:57');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (59,'Internship Report','reports/internship',20,'fas fa-user-graduate',1,1,'2026-03-16 06:41:57','2026-03-16 06:41:57');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (60,'Course Report','reports/course',20,'fas fa-book',2,1,'2026-03-16 06:44:00','2026-03-16 06:44:00');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (62,'Interview Students','interviews/students',28,'fas fa-user-check',1,0,'2026-03-16 11:51:04','2026-03-19 04:42:00');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (63,'Course Completed Students','interviews/schedule',19,'fas fa-calendar-alt',1,1,'2026-03-16 11:51:04','2026-03-19 04:53:42');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (65,'Interview Status','interviews/placement',19,'fas fa-briefcase',2,1,'2026-03-19 04:12:44','2026-03-19 04:55:16');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (69,'Student Report','reports/student_schedule',20,'fas fa-calendar-day',1,1,'2026-03-19 11:06:50','2026-03-26 12:46:24');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (70,'Student Overall','reports/student_overall',20,'fas fa-clipboard',1,1,'2026-03-19 11:10:20','2026-03-19 11:10:20');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (71,'Dashboard test 1','dashboard/superadmin',NULL,'fas fa-chart-line',1,1,'2026-03-24 06:47:33','2026-03-24 06:47:33');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (73,'Daily Report Test1','reports/daily',20,'fas fa-calendar-day',1,1,'2026-03-24 06:58:34','2026-03-24 06:58:34');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (74,'Monthly Report','reports/monthly',20,'fas fa-calendar-alt',2,1,'2026-03-24 06:59:08','2026-03-24 06:59:08');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (77,'Course Students','students/course',16,'fas fa-clipboard',3,1,'2026-03-31 08:06:21','2026-03-31 08:06:21');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (78,'Daily Reports','dailyreports/entry',20,'fas fa-calendar-check',3,1,'2026-04-08 07:59:07','2026-04-08 08:01:29');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (79,'Daily Report View','dailyreports/view',20,'fas fa-list',4,1,'2026-04-08 08:02:39','2026-04-08 08:02:39');
INSERT INTO `menus` (`id`,`menu_name`,`menu_slug`,`parent_id`,`icon`,`sort_order`,`status`,`created_at`,`updated_at`) VALUES (80,'Export Report','dailyreports/export',20,'fas fa-file-export',5,1,'2026-04-08 08:04:46','2026-04-08 08:04:46');

-- --------------------------------------------------------
-- Table: mock_interviews

DROP TABLE IF EXISTS `mock_interviews`;
CREATE TABLE `mock_interviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `staff_user_id` int(11) NOT NULL,
  `theoretical_marks` decimal(5,2) DEFAULT NULL,
  `machine_task_marks` decimal(5,2) DEFAULT NULL,
  `mock_average` decimal(5,2) DEFAULT NULL,
  `workflow_status` enum('pending','done','sent_to_hr') NOT NULL DEFAULT 'pending',
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_mock_interview_registration` (`registration_id`),
  KEY `idx_mock_interviews_branch` (`branch_id`),
  KEY `idx_mock_interviews_staff` (`staff_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `mock_interviews` (`id`,`registration_id`,`branch_id`,`staff_user_id`,`theoretical_marks`,`machine_task_marks`,`mock_average`,`workflow_status`,`completed_at`,`completed_by`,`created_at`,`updated_at`) VALUES (1,2,1,8,'78.00','78.00','78.00','sent_to_hr','2026-03-31 07:52:48',8,'2026-03-31 10:44:51','2026-03-31 13:23:07');
INSERT INTO `mock_interviews` (`id`,`registration_id`,`branch_id`,`staff_user_id`,`theoretical_marks`,`machine_task_marks`,`mock_average`,`workflow_status`,`completed_at`,`completed_by`,`created_at`,`updated_at`) VALUES (2,5,1,8,'90.00','90.00','90.00','done','2026-04-08 04:58:09',8,'2026-04-06 10:38:54','2026-04-08 10:28:09');

-- --------------------------------------------------------
-- Table: monthly_target_results

DROP TABLE IF EXISTS `monthly_target_results`;
CREATE TABLE `monthly_target_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `target_year` smallint(6) NOT NULL,
  `target_month` tinyint(4) NOT NULL,
  `target_id` int(11) NOT NULL,
  `base_target_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `opening_carry_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `effective_target_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `achieved_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `excess_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `shortfall_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `closing_carry_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `incentive_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `incentive_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `last_calculated_at` datetime DEFAULT NULL,
  `calculation_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_result_user_month_branch` (`branch_id`,`user_id`,`target_year`,`target_month`),
  KEY `idx_result_branch` (`branch_id`),
  KEY `idx_result_user` (`user_id`),
  KEY `idx_result_role` (`role_id`),
  KEY `idx_result_period` (`target_year`,`target_month`),
  KEY `idx_result_target_id` (`target_id`),
  CONSTRAINT `fk_monthly_target_results_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monthly_target_results_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `fk_monthly_target_results_target` FOREIGN KEY (`target_id`) REFERENCES `monthly_targets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monthly_target_results_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table: monthly_targets

DROP TABLE IF EXISTS `monthly_targets`;
CREATE TABLE `monthly_targets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `target_year` smallint(6) NOT NULL,
  `target_month` tinyint(4) NOT NULL,
  `target_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `incentive_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `assigned_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_target_user_month_branch` (`branch_id`,`user_id`,`target_year`,`target_month`),
  KEY `idx_target_branch` (`branch_id`),
  KEY `idx_target_user` (`user_id`),
  KEY `idx_target_role` (`role_id`),
  KEY `idx_target_period` (`target_year`,`target_month`),
  KEY `idx_target_assigned_by` (`assigned_by`),
  CONSTRAINT `fk_monthly_targets_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_monthly_targets_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monthly_targets_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `fk_monthly_targets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `monthly_targets` (`id`,`branch_id`,`user_id`,`role_id`,`target_year`,`target_month`,`target_amount`,`incentive_percent`,`remarks`,`status`,`assigned_by`,`created_at`,`updated_at`) VALUES (1,1,2,2,2026,4,'100000.00','10.00','qwertyui','active',3,'2026-04-08 05:49:55','2026-04-08 05:49:55');
INSERT INTO `monthly_targets` (`id`,`branch_id`,`user_id`,`role_id`,`target_year`,`target_month`,`target_amount`,`incentive_percent`,`remarks`,`status`,`assigned_by`,`created_at`,`updated_at`) VALUES (2,1,5,6,2026,4,'50000.00','10.00','All the best','active',3,'2026-04-13 14:05:38','2026-04-13 14:05:38');
INSERT INTO `monthly_targets` (`id`,`branch_id`,`user_id`,`role_id`,`target_year`,`target_month`,`target_amount`,`incentive_percent`,`remarks`,`status`,`assigned_by`,`created_at`,`updated_at`) VALUES (3,1,18,6,2026,4,'75000.00','10.00',NULL,'active',3,'2026-04-15 13:57:50','2026-04-15 13:57:50');
INSERT INTO `monthly_targets` (`id`,`branch_id`,`user_id`,`role_id`,`target_year`,`target_month`,`target_amount`,`incentive_percent`,`remarks`,`status`,`assigned_by`,`created_at`,`updated_at`) VALUES (4,1,3,3,2026,4,'55000.00','10.00',NULL,'active',3,'2026-04-15 14:24:42','2026-04-15 14:24:42');

-- --------------------------------------------------------
-- Table: notifications

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table: payments

DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','UPI','Card','Bank Transfer') NOT NULL,
  `transaction_id` varchar(150) DEFAULT NULL,
  `payment_status` enum('Pending','Paid','Failed') DEFAULT 'Pending',
  `payment_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table: placement_interviews

DROP TABLE IF EXISTS `placement_interviews`;
CREATE TABLE `placement_interviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `hr_workflow_id` int(11) DEFAULT NULL,
  `company_name` varchar(150) NOT NULL,
  `interview_date` date NOT NULL,
  `interview_time` time DEFAULT NULL,
  `interview_mode` enum('Online','Offline') NOT NULL DEFAULT 'Offline',
  `status` enum('scheduled','attended','selected','rejected','on_hold') NOT NULL DEFAULT 'scheduled',
  `remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pi_registration` (`registration_id`),
  KEY `idx_pi_branch` (`branch_id`),
  KEY `idx_pi_workflow` (`hr_workflow_id`),
  KEY `idx_pi_status` (`status`),
  CONSTRAINT `fk_pi_hr_workflow` FOREIGN KEY (`hr_workflow_id`) REFERENCES `student_hr_interviews` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pi_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `placement_interviews` (`id`,`registration_id`,`branch_id`,`hr_workflow_id`,`company_name`,`interview_date`,`interview_time`,`interview_mode`,`status`,`remarks`,`created_by`,`updated_by`,`created_at`,`updated_at`) VALUES (1,2,1,1,'Mechpro','2026-03-31','13:27:00','Offline','rejected','qwertyuiop[',3,3,'2026-03-31 13:27:32','2026-03-31 13:27:32');
INSERT INTO `placement_interviews` (`id`,`registration_id`,`branch_id`,`hr_workflow_id`,`company_name`,`interview_date`,`interview_time`,`interview_mode`,`status`,`remarks`,`created_by`,`updated_by`,`created_at`,`updated_at`) VALUES (2,2,1,1,'Nettel','2026-04-01','13:28:00','Online','selected','qwertyuio',3,3,'2026-03-31 13:28:12','2026-03-31 13:28:12');
INSERT INTO `placement_interviews` (`id`,`registration_id`,`branch_id`,`hr_workflow_id`,`company_name`,`interview_date`,`interview_time`,`interview_mode`,`status`,`remarks`,`created_by`,`updated_by`,`created_at`,`updated_at`) VALUES (3,2,1,1,'ATS','2026-04-07','09:53:00','Online','scheduled','qwertyuiop',3,3,'2026-04-06 09:54:08','2026-04-06 09:54:08');

-- --------------------------------------------------------
-- Table: placements

DROP TABLE IF EXISTS `placements`;
CREATE TABLE `placements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `job_role` varchar(150) NOT NULL,
  `package` varchar(100) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `placement_status` enum('Offered','Joined','Declined') DEFAULT 'Offered',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `placements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table: programs

DROP TABLE IF EXISTS `programs`;
CREATE TABLE `programs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_id` int(11) NOT NULL,
  `college` varchar(255) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `class` varchar(100) DEFAULT NULL,
  `program_given_by` varchar(150) DEFAULT NULL,
  `designation` varchar(150) DEFAULT NULL,
  `program_type` varchar(100) DEFAULT NULL,
  `domain` varchar(100) DEFAULT NULL,
  `trainer` varchar(150) DEFAULT NULL,
  `topics` text DEFAULT NULL,
  `no_days` int(11) DEFAULT NULL,
  `start_day` date DEFAULT NULL,
  `end_day` date DEFAULT NULL,
  `hours` int(11) DEFAULT NULL,
  `no_students` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `collection` decimal(10,2) DEFAULT NULL,
  `pending` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- --------------------------------------------------------
-- Table: registration_courses

DROP TABLE IF EXISTS `registration_courses`;
CREATE TABLE `registration_courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `guide_staff_id` int(11) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `course_status` enum('draft','active','completed','cancelled') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_registration_courses_registration` (`registration_id`),
  KEY `idx_registration_courses_guide_staff` (`guide_staff_id`),
  KEY `idx_registration_courses_assigned_by` (`assigned_by`),
  CONSTRAINT `fk_registration_courses_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_registration_courses_guide_staff` FOREIGN KEY (`guide_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_registration_courses_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `registration_courses` (`id`,`registration_id`,`guide_staff_id`,`assigned_by`,`assigned_at`,`course_status`,`created_at`,`updated_at`) VALUES (1,2,8,2,'2026-03-30 08:08:09','active','2026-03-30 08:08:09','2026-03-30 08:08:09');
INSERT INTO `registration_courses` (`id`,`registration_id`,`guide_staff_id`,`assigned_by`,`assigned_at`,`course_status`,`created_at`,`updated_at`) VALUES (2,3,12,10,'2026-03-30 08:52:47','active','2026-03-30 08:52:27','2026-03-30 08:52:47');
INSERT INTO `registration_courses` (`id`,`registration_id`,`guide_staff_id`,`assigned_by`,`assigned_at`,`course_status`,`created_at`,`updated_at`) VALUES (4,5,8,2,'2026-04-06 04:56:26','active','2026-04-06 04:56:26','2026-04-06 04:56:26');

-- --------------------------------------------------------
-- Table: registration_internships

DROP TABLE IF EXISTS `registration_internships`;
CREATE TABLE `registration_internships` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `guide_staff_id` int(11) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `internship_days` int(11) DEFAULT NULL,
  `internship_batch` varchar(100) DEFAULT NULL,
  `internship_start_date` date DEFAULT NULL,
  `internship_end_date` date DEFAULT NULL,
  `completion_status` enum('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
  `certificate_status` enum('not_given','given') NOT NULL DEFAULT 'not_given',
  `certificate_issued_at` datetime DEFAULT NULL,
  `report_status` enum('not_provided','provided') NOT NULL DEFAULT 'not_provided',
  `report_issued_at` datetime DEFAULT NULL,
  `report_due_days` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_registration_internships_registration` (`registration_id`),
  KEY `idx_registration_internships_guide_staff` (`guide_staff_id`),
  KEY `idx_registration_internships_assigned_by` (`assigned_by`),
  CONSTRAINT `fk_registration_internships_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_registration_internships_guide_staff` FOREIGN KEY (`guide_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_registration_internships_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `registration_internships` (`id`,`registration_id`,`guide_staff_id`,`assigned_by`,`assigned_at`,`internship_days`,`internship_batch`,`internship_start_date`,`internship_end_date`,`completion_status`,`certificate_status`,`certificate_issued_at`,`report_status`,`report_issued_at`,`report_due_days`,`created_at`,`updated_at`) VALUES (1,1,8,10,'2026-03-30 07:43:13',21,'Morning',NULL,NULL,'pending','not_given',NULL,'not_provided',NULL,NULL,'2026-03-30 07:43:13','2026-03-30 07:43:13');
INSERT INTO `registration_internships` (`id`,`registration_id`,`guide_staff_id`,`assigned_by`,`assigned_at`,`internship_days`,`internship_batch`,`internship_start_date`,`internship_end_date`,`completion_status`,`certificate_status`,`certificate_issued_at`,`report_status`,`report_issued_at`,`report_due_days`,`created_at`,`updated_at`) VALUES (2,8,4,2,'2026-04-13 16:31:56',21,'Morning',NULL,NULL,'pending','not_given',NULL,'not_provided',NULL,NULL,'2026-04-13 16:31:56','2026-04-13 16:31:56');

-- --------------------------------------------------------
-- Table: registration_payments

DROP TABLE IF EXISTS `registration_payments`;
CREATE TABLE `registration_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL COMMENT 'Front office / owner who gets target credit',
  `collected_by` int(11) NOT NULL COMMENT 'User who entered/received payment',
  `approved_by` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_date` date NOT NULL,
  `payment_mode` enum('cash','upi','card','bank_transfer','cheque','other') DEFAULT 'cash',
  `payment_type` enum('advance','partial','full','refund') DEFAULT 'partial',
  `reference_no` varchar(100) DEFAULT NULL,
  `receipt_no` varchar(100) DEFAULT NULL,
  `approval_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'approved',
  `remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_registration_id` (`registration_id`),
  KEY `idx_branch_id` (`branch_id`),
  KEY `idx_staff_id` (`staff_id`),
  KEY `idx_payment_date` (`payment_date`),
  KEY `idx_approval_status` (`approval_status`),
  KEY `idx_target_report` (`branch_id`,`collected_by`,`approval_status`,`payment_date`),
  CONSTRAINT `fk_registration_payments_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `registration_payments` (`id`,`registration_id`,`branch_id`,`staff_id`,`collected_by`,`approved_by`,`amount`,`payment_date`,`payment_mode`,`payment_type`,`reference_no`,`receipt_no`,`approval_status`,`remarks`,`created_at`,`updated_at`) VALUES (1,2,1,2,2,2,'2000.00','2026-03-30','cash','partial','qwertyuio','RCPT-202603-0001','approved','qwertyuiop','2026-03-30 09:55:21','2026-03-30 09:55:21');
INSERT INTO `registration_payments` (`id`,`registration_id`,`branch_id`,`staff_id`,`collected_by`,`approved_by`,`amount`,`payment_date`,`payment_mode`,`payment_type`,`reference_no`,`receipt_no`,`approval_status`,`remarks`,`created_at`,`updated_at`) VALUES (2,2,1,2,2,2,'1500.00','2026-03-30','cash','partial','11234567890','RCPT-202603-0002','approved','wedwe','2026-03-30 11:59:28','2026-03-30 11:59:28');
INSERT INTO `registration_payments` (`id`,`registration_id`,`branch_id`,`staff_id`,`collected_by`,`approved_by`,`amount`,`payment_date`,`payment_mode`,`payment_type`,`reference_no`,`receipt_no`,`approval_status`,`remarks`,`created_at`,`updated_at`) VALUES (3,2,1,2,2,2,'100.00','2026-03-30','cash','partial','wertyuio','RCPT-202603-0003','approved','wertyuio','2026-03-30 11:59:48','2026-03-30 11:59:48');
INSERT INTO `registration_payments` (`id`,`registration_id`,`branch_id`,`staff_id`,`collected_by`,`approved_by`,`amount`,`payment_date`,`payment_mode`,`payment_type`,`reference_no`,`receipt_no`,`approval_status`,`remarks`,`created_at`,`updated_at`) VALUES (4,2,1,2,2,2,'1400.00','2026-03-31','cash','full','REF-20260331-0002-104839469','RCPT-202603-0004','approved','qwertyuio','2026-03-31 05:18:57','2026-03-31 05:18:57');
INSERT INTO `registration_payments` (`id`,`registration_id`,`branch_id`,`staff_id`,`collected_by`,`approved_by`,`amount`,`payment_date`,`payment_mode`,`payment_type`,`reference_no`,`receipt_no`,`approval_status`,`remarks`,`created_at`,`updated_at`) VALUES (5,5,1,2,2,2,'100.00','2026-04-06','cash','partial','REF-20260406-0005-105944352','RCPT-202604-0001','approved',NULL,'2026-04-06 05:30:02','2026-04-06 05:30:02');
INSERT INTO `registration_payments` (`id`,`registration_id`,`branch_id`,`staff_id`,`collected_by`,`approved_by`,`amount`,`payment_date`,`payment_mode`,`payment_type`,`reference_no`,`receipt_no`,`approval_status`,`remarks`,`created_at`,`updated_at`) VALUES (6,5,1,2,2,2,'200.00','2026-04-06','cash','partial','REF-20260406-0005-140653812','RCPT-202604-0002','approved',NULL,'2026-04-06 08:37:04','2026-04-06 08:37:04');
INSERT INTO `registration_payments` (`id`,`registration_id`,`branch_id`,`staff_id`,`collected_by`,`approved_by`,`amount`,`payment_date`,`payment_mode`,`payment_type`,`reference_no`,`receipt_no`,`approval_status`,`remarks`,`created_at`,`updated_at`) VALUES (7,5,1,2,2,2,'100.00','2026-04-06','cash','partial','REF-20260406-0005-150359667','RCPT-202604-0003','approved',NULL,'2026-04-06 09:34:12','2026-04-06 09:34:12');
INSERT INTO `registration_payments` (`id`,`registration_id`,`branch_id`,`staff_id`,`collected_by`,`approved_by`,`amount`,`payment_date`,`payment_mode`,`payment_type`,`reference_no`,`receipt_no`,`approval_status`,`remarks`,`created_at`,`updated_at`) VALUES (8,5,1,2,2,2,'100.00','2026-04-06','cash','partial','REF-20260406-0005-151746895','RCPT-202604-0004','approved',NULL,'2026-04-06 09:47:58','2026-04-06 09:47:58');
INSERT INTO `registration_payments` (`id`,`registration_id`,`branch_id`,`staff_id`,`collected_by`,`approved_by`,`amount`,`payment_date`,`payment_mode`,`payment_type`,`reference_no`,`receipt_no`,`approval_status`,`remarks`,`created_at`,`updated_at`) VALUES (9,5,1,2,2,2,'100.00','2026-04-06','cash','partial','REF-20260406-0005-170849609','RCPT-202604-0005','approved',NULL,'2026-04-06 11:39:02','2026-04-06 11:39:02');
INSERT INTO `registration_payments` (`id`,`registration_id`,`branch_id`,`staff_id`,`collected_by`,`approved_by`,`amount`,`payment_date`,`payment_mode`,`payment_type`,`reference_no`,`receipt_no`,`approval_status`,`remarks`,`created_at`,`updated_at`) VALUES (10,5,1,2,2,2,'100.00','2026-04-06','cash','partial','REF-20260406-0005-172157185','RCPT-202604-0006','approved',NULL,'2026-04-06 11:52:13','2026-04-06 11:52:13');
INSERT INTO `registration_payments` (`id`,`registration_id`,`branch_id`,`staff_id`,`collected_by`,`approved_by`,`amount`,`payment_date`,`payment_mode`,`payment_type`,`reference_no`,`receipt_no`,`approval_status`,`remarks`,`created_at`,`updated_at`) VALUES (11,5,1,2,2,2,'11300.00','2026-04-08','cash','full','REF-20260408-0005-102850851','RCPT-202604-0007','approved','qwertyu','2026-04-08 04:59:13','2026-04-08 04:59:13');
INSERT INTO `registration_payments` (`id`,`registration_id`,`branch_id`,`staff_id`,`collected_by`,`approved_by`,`amount`,`payment_date`,`payment_mode`,`payment_type`,`reference_no`,`receipt_no`,`approval_status`,`remarks`,`created_at`,`updated_at`) VALUES (12,6,1,2,2,2,'100.00','2026-04-08','cash','partial','REF-20260408-0006-105120284','RCPT-202604-0008','approved',NULL,'2026-04-08 05:21:32','2026-04-08 05:21:32');
INSERT INTO `registration_payments` (`id`,`registration_id`,`branch_id`,`staff_id`,`collected_by`,`approved_by`,`amount`,`payment_date`,`payment_mode`,`payment_type`,`reference_no`,`receipt_no`,`approval_status`,`remarks`,`created_at`,`updated_at`) VALUES (13,6,1,2,2,2,'100.00','2026-04-08','cash','partial','REF-20260408-0006-110207690','RCPT-202604-0009','approved',NULL,'2026-04-08 05:33:49','2026-04-08 05:33:49');
INSERT INTO `registration_payments` (`id`,`registration_id`,`branch_id`,`staff_id`,`collected_by`,`approved_by`,`amount`,`payment_date`,`payment_mode`,`payment_type`,`reference_no`,`receipt_no`,`approval_status`,`remarks`,`created_at`,`updated_at`) VALUES (14,6,1,2,2,2,'1.00','2026-04-08','cash','partial','REF-20260408-0006-111348739','RCPT-202604-0010','approved',NULL,'2026-04-08 05:44:02','2026-04-08 05:44:02');
INSERT INTO `registration_payments` (`id`,`registration_id`,`branch_id`,`staff_id`,`collected_by`,`approved_by`,`amount`,`payment_date`,`payment_mode`,`payment_type`,`reference_no`,`receipt_no`,`approval_status`,`remarks`,`created_at`,`updated_at`) VALUES (15,6,1,2,2,2,'9.00','2026-04-08','cash','partial','REF-20260408-0006-113313160','RCPT-202604-0011','approved',NULL,'2026-04-08 06:03:27','2026-04-08 06:03:27');
INSERT INTO `registration_payments` (`id`,`registration_id`,`branch_id`,`staff_id`,`collected_by`,`approved_by`,`amount`,`payment_date`,`payment_mode`,`payment_type`,`reference_no`,`receipt_no`,`approval_status`,`remarks`,`created_at`,`updated_at`) VALUES (16,6,1,2,2,2,'100.00','2026-04-08','cash','partial','REF-20260408-0006-113709596','RCPT-202604-0012','approved',NULL,'2026-04-08 06:07:38','2026-04-08 06:07:38');
INSERT INTO `registration_payments` (`id`,`registration_id`,`branch_id`,`staff_id`,`collected_by`,`approved_by`,`amount`,`payment_date`,`payment_mode`,`payment_type`,`reference_no`,`receipt_no`,`approval_status`,`remarks`,`created_at`,`updated_at`) VALUES (17,6,1,2,2,2,'100.00','2026-04-10','cash','partial','REF-20260410-0006-092222836','RCPT-202604-0013','approved',NULL,'2026-04-10 03:52:33','2026-04-10 03:52:33');
INSERT INTO `registration_payments` (`id`,`registration_id`,`branch_id`,`staff_id`,`collected_by`,`approved_by`,`amount`,`payment_date`,`payment_mode`,`payment_type`,`reference_no`,`receipt_no`,`approval_status`,`remarks`,`created_at`,`updated_at`) VALUES (18,6,1,2,2,2,'10.00','2026-04-10','cash','partial','REF-20260410-0006-092832636','RCPT-202604-0014','approved',NULL,'2026-04-10 03:58:44','2026-04-10 03:58:44');

-- --------------------------------------------------------
-- Table: registration_profiles

DROP TABLE IF EXISTS `registration_profiles`;
CREATE TABLE `registration_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `student_name` varchar(150) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `address` text DEFAULT NULL,
  `qualification` varchar(150) DEFAULT NULL,
  `college_name` varchar(150) DEFAULT NULL,
  `year_of_passout` varchar(20) DEFAULT NULL,
  `parent_name` varchar(150) DEFAULT NULL,
  `parent_phone` varchar(20) DEFAULT NULL,
  `parent_occupation` varchar(150) DEFAULT NULL,
  `parent_email` varchar(150) DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `aadhaar_no` varchar(30) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_registration_profile` (`registration_id`),
  CONSTRAINT `fk_registration_profiles_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `registration_profiles` (`id`,`registration_id`,`student_name`,`gender`,`dob`,`address`,`qualification`,`college_name`,`year_of_passout`,`parent_name`,`parent_phone`,`parent_occupation`,`parent_email`,`emergency_contact`,`aadhaar_no`,`photo_path`,`signature_path`,`remarks`,`created_at`,`updated_at`) VALUES (1,1,'Suresh','male','2001-01-01','7, Nehru street, Ramnagar coimbatore','BE in textiles','Sri Ramakrishna Institute of Technology','2025','Pranesh','1234567890','Cab Driver','user550@gmail.com','7418526303','147852963012',NULL,NULL,'sdfagh','2026-03-30 07:33:06','2026-03-30 07:33:36');
INSERT INTO `registration_profiles` (`id`,`registration_id`,`student_name`,`gender`,`dob`,`address`,`qualification`,`college_name`,`year_of_passout`,`parent_name`,`parent_phone`,`parent_occupation`,`parent_email`,`emergency_contact`,`aadhaar_no`,`photo_path`,`signature_path`,`remarks`,`created_at`,`updated_at`) VALUES (2,2,'Joseph','male','2001-01-01','qwerweetyjkghionllsdgnklhiosdghiosrgabjkbjlsdggsdhiodgshiosgdy8gsehoetsdgbjkdgsbjkSDG','BE','ATS','2023','qwertyui','9876543210','wertyuio','parent@gmail.com','9876543210','959595959595',NULL,NULL,NULL,'2026-03-30 08:04:45','2026-03-30 08:04:45');
INSERT INTO `registration_profiles` (`id`,`registration_id`,`student_name`,`gender`,`dob`,`address`,`qualification`,`college_name`,`year_of_passout`,`parent_name`,`parent_phone`,`parent_occupation`,`parent_email`,`emergency_contact`,`aadhaar_no`,`photo_path`,`signature_path`,`remarks`,`created_at`,`updated_at`) VALUES (3,3,'Akash','male','2001-12-01','7, Nehru street, Ramnagar coimbatore','BE in textiles','Sri Ramakrishna Institute of Technology','2025','Pranesh','1234567890','Cab Driver','user550@gmail.com','7418526303','147852963012',NULL,NULL,'sdafs','2026-03-30 08:51:55','2026-03-30 08:51:55');
INSERT INTO `registration_profiles` (`id`,`registration_id`,`student_name`,`gender`,`dob`,`address`,`qualification`,`college_name`,`year_of_passout`,`parent_name`,`parent_phone`,`parent_occupation`,`parent_email`,`emergency_contact`,`aadhaar_no`,`photo_path`,`signature_path`,`remarks`,`created_at`,`updated_at`) VALUES (4,4,'Arjun','male','2026-03-30','iwdnqndNqd','MCA','NPR','2026','Raghul','0987654321','Developer','sakthivelpcseb@gmail.com','6556846986','685436565653',NULL,NULL,'zexdtfcygvubhijnmk,l','2026-03-30 09:22:48','2026-03-30 09:22:48');
INSERT INTO `registration_profiles` (`id`,`registration_id`,`student_name`,`gender`,`dob`,`address`,`qualification`,`college_name`,`year_of_passout`,`parent_name`,`parent_phone`,`parent_occupation`,`parent_email`,`emergency_contact`,`aadhaar_no`,`photo_path`,`signature_path`,`remarks`,`created_at`,`updated_at`) VALUES (5,5,'Sridharshan','male','2001-01-01','qwertyuiop','BE','Kongu','2023','Yogaprabhu','9876543210','Business','sriveprus@gmail.com','9876543210','989898989898',NULL,NULL,'qwertyuio','2026-04-06 04:36:51','2026-04-06 04:36:51');
INSERT INTO `registration_profiles` (`id`,`registration_id`,`student_name`,`gender`,`dob`,`address`,`qualification`,`college_name`,`year_of_passout`,`parent_name`,`parent_phone`,`parent_occupation`,`parent_email`,`emergency_contact`,`aadhaar_no`,`photo_path`,`signature_path`,`remarks`,`created_at`,`updated_at`) VALUES (6,6,'Palanisamy','male','2001-01-01','qwertyuiop','BE','ABC','2023','Prabakaran','9876543210','Business','webdeveloper05.ats@gmail.com','9876543210','959595959595',NULL,NULL,'qwertyu','2026-04-07 04:05:43','2026-04-07 04:05:43');
INSERT INTO `registration_profiles` (`id`,`registration_id`,`student_name`,`gender`,`dob`,`address`,`qualification`,`college_name`,`year_of_passout`,`parent_name`,`parent_phone`,`parent_occupation`,`parent_email`,`emergency_contact`,`aadhaar_no`,`photo_path`,`signature_path`,`remarks`,`created_at`,`updated_at`) VALUES (7,7,'Harsha Vardhini','female','2004-06-21','70e mudakiyar street, kuruchi, sudrapuram, coimbatore - 641024','B.Sc Computer Science','Angappa College Of Arts & Science','2025','Jeevanantham','9159244320','Driver','HARSHAVARDHINI7716@GMAIL.COM','9159244320','234567896543',NULL,NULL,NULL,'2026-04-09 06:54:17','2026-04-09 06:54:17');
INSERT INTO `registration_profiles` (`id`,`registration_id`,`student_name`,`gender`,`dob`,`address`,`qualification`,`college_name`,`year_of_passout`,`parent_name`,`parent_phone`,`parent_occupation`,`parent_email`,`emergency_contact`,`aadhaar_no`,`photo_path`,`signature_path`,`remarks`,`created_at`,`updated_at`) VALUES (8,8,'Dinesh','male','2001-01-01','qwertyui','BE','ABC','2021','Suresh','9876543210','Teacher','webdeveloper05.ats@gmail.com','9876543210','959595959595',NULL,NULL,NULL,'2026-04-11 11:17:33','2026-04-11 11:17:33');
INSERT INTO `registration_profiles` (`id`,`registration_id`,`student_name`,`gender`,`dob`,`address`,`qualification`,`college_name`,`year_of_passout`,`parent_name`,`parent_phone`,`parent_occupation`,`parent_email`,`emergency_contact`,`aadhaar_no`,`photo_path`,`signature_path`,`remarks`,`created_at`,`updated_at`) VALUES (9,9,'Dinesh','male','2001-01-01','qwertyui','BE','ABC','2021','Suresh','9876543210','Teacher','webdeveloper05.ats@gmail.com','9898989898','988989898989',NULL,NULL,NULL,'2026-04-11 11:58:36','2026-04-11 12:03:15');

-- --------------------------------------------------------
-- Table: registrations

DROP TABLE IF EXISTS `registrations`;
CREATE TABLE `registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_no` varchar(50) DEFAULT NULL,
  `enquiry_id` int(11) DEFAULT NULL,
  `branch_id` int(11) NOT NULL,
  `reg_type` enum('course','internship','workshop') NOT NULL,
  `source_type` enum('lead','direct','walkin','reference','online','other') DEFAULT 'direct',
  `joined_on` date DEFAULT NULL,
  `enquiry_snapshot_name` varchar(150) DEFAULT NULL,
  `enquiry_snapshot_phone` varchar(20) DEFAULT NULL,
  `enquiry_snapshot_email` varchar(150) DEFAULT NULL,
  `program_name` varchar(150) DEFAULT NULL,
  `batch_name` varchar(150) DEFAULT NULL,
  `internship_days` int(11) DEFAULT NULL,
  `internship_batch` varchar(100) DEFAULT NULL,
  `internship_start_date` date DEFAULT NULL,
  `internship_end_date` date DEFAULT NULL,
  `internship_completion_status` enum('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
  `internship_certificate_status` enum('not_given','given') NOT NULL DEFAULT 'not_given',
  `internship_certificate_issued_at` datetime DEFAULT NULL,
  `internship_report_status` enum('not_provided','provided') NOT NULL DEFAULT 'not_provided',
  `internship_report_issued_at` datetime DEFAULT NULL,
  `internship_report_due_days` int(11) DEFAULT NULL,
  `total_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `final_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `notes` text DEFAULT NULL,
  `registration_status` enum('draft','active','completed','cancelled') NOT NULL DEFAULT 'draft',
  `assigned_to` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_registration_no` (`registration_no`),
  KEY `enquiry_id` (`enquiry_id`),
  KEY `branch_id` (`branch_id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `status` (`registration_status`),
  KEY `idx_enquiry_id` (`enquiry_id`),
  KEY `idx_branch_id` (`branch_id`),
  KEY `idx_reg_type` (`reg_type`),
  KEY `idx_assigned_to` (`assigned_to`),
  KEY `idx_registration_status` (`registration_status`),
  CONSTRAINT `fk_reg_enquiry` FOREIGN KEY (`enquiry_id`) REFERENCES `enquiries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `registrations` (`id`,`registration_no`,`enquiry_id`,`branch_id`,`reg_type`,`source_type`,`joined_on`,`enquiry_snapshot_name`,`enquiry_snapshot_phone`,`enquiry_snapshot_email`,`program_name`,`batch_name`,`internship_days`,`internship_batch`,`internship_start_date`,`internship_end_date`,`internship_completion_status`,`internship_certificate_status`,`internship_certificate_issued_at`,`internship_report_status`,`internship_report_issued_at`,`internship_report_due_days`,`total_fee`,`discount_amount`,`final_fee`,`paid_amount`,`balance_amount`,`payment_status`,`notes`,`registration_status`,`assigned_to`,`created_by`,`created_at`,`updated_at`) VALUES (1,'REG-202603-0001',1,1,'internship','walkin','2026-03-30','Suresh','7402740298','ats.pythondeveloper05@gmail.com','Data Science','morning',NULL,NULL,NULL,NULL,'pending','not_given',NULL,'not_provided',NULL,NULL,'15000.00','0.00','15000.00','0.00','15000.00','unpaid','sadfg','active',10,10,'2026-03-30 07:31:40','2026-03-30 07:33:36');
INSERT INTO `registrations` (`id`,`registration_no`,`enquiry_id`,`branch_id`,`reg_type`,`source_type`,`joined_on`,`enquiry_snapshot_name`,`enquiry_snapshot_phone`,`enquiry_snapshot_email`,`program_name`,`batch_name`,`internship_days`,`internship_batch`,`internship_start_date`,`internship_end_date`,`internship_completion_status`,`internship_certificate_status`,`internship_certificate_issued_at`,`internship_report_status`,`internship_report_issued_at`,`internship_report_due_days`,`total_fee`,`discount_amount`,`final_fee`,`paid_amount`,`balance_amount`,`payment_status`,`notes`,`registration_status`,`assigned_to`,`created_by`,`created_at`,`updated_at`) VALUES (2,'REG-202603-0002',2,1,'course','direct','2026-03-30','Joseph','9876543210','joseph@gmail.com','Data science','Morning',NULL,NULL,NULL,NULL,'completed','given','2026-04-01 09:21:53','not_provided',NULL,NULL,'5000.00','0.00','5000.00','5000.00','0.00','paid',NULL,'completed',2,2,'2026-03-30 08:02:36','2026-04-11 12:42:13');
INSERT INTO `registrations` (`id`,`registration_no`,`enquiry_id`,`branch_id`,`reg_type`,`source_type`,`joined_on`,`enquiry_snapshot_name`,`enquiry_snapshot_phone`,`enquiry_snapshot_email`,`program_name`,`batch_name`,`internship_days`,`internship_batch`,`internship_start_date`,`internship_end_date`,`internship_completion_status`,`internship_certificate_status`,`internship_certificate_issued_at`,`internship_report_status`,`internship_report_issued_at`,`internship_report_due_days`,`total_fee`,`discount_amount`,`final_fee`,`paid_amount`,`balance_amount`,`payment_status`,`notes`,`registration_status`,`assigned_to`,`created_by`,`created_at`,`updated_at`) VALUES (3,'REG-202603-0003',4,1,'course','direct','2026-03-30','Akash','9876543210','Akash@gmail.com','Data Analytics','morning',NULL,NULL,NULL,NULL,'pending','not_given',NULL,'not_provided',NULL,NULL,'5000.00','0.00','5000.00','0.00','5000.00','unpaid','sadfd','active',10,10,'2026-03-30 08:51:11','2026-03-30 08:51:55');
INSERT INTO `registrations` (`id`,`registration_no`,`enquiry_id`,`branch_id`,`reg_type`,`source_type`,`joined_on`,`enquiry_snapshot_name`,`enquiry_snapshot_phone`,`enquiry_snapshot_email`,`program_name`,`batch_name`,`internship_days`,`internship_batch`,`internship_start_date`,`internship_end_date`,`internship_completion_status`,`internship_certificate_status`,`internship_certificate_issued_at`,`internship_report_status`,`internship_report_issued_at`,`internship_report_due_days`,`total_fee`,`discount_amount`,`final_fee`,`paid_amount`,`balance_amount`,`payment_status`,`notes`,`registration_status`,`assigned_to`,`created_by`,`created_at`,`updated_at`) VALUES (4,'REG-202603-0004',3,1,'course','direct','2026-03-30','Arjun','7402740298','ats.pythondeveloper05@gmail.com','Data Science','Evening',NULL,NULL,NULL,NULL,'pending','not_given',NULL,'not_provided',NULL,NULL,'25000.00','0.00','25000.00','0.00','25000.00','unpaid','trxcfghbjlkm,;.','active',10,11,'2026-03-30 09:22:04','2026-03-30 09:22:48');
INSERT INTO `registrations` (`id`,`registration_no`,`enquiry_id`,`branch_id`,`reg_type`,`source_type`,`joined_on`,`enquiry_snapshot_name`,`enquiry_snapshot_phone`,`enquiry_snapshot_email`,`program_name`,`batch_name`,`internship_days`,`internship_batch`,`internship_start_date`,`internship_end_date`,`internship_completion_status`,`internship_certificate_status`,`internship_certificate_issued_at`,`internship_report_status`,`internship_report_issued_at`,`internship_report_due_days`,`total_fee`,`discount_amount`,`final_fee`,`paid_amount`,`balance_amount`,`payment_status`,`notes`,`registration_status`,`assigned_to`,`created_by`,`created_at`,`updated_at`) VALUES (5,'REG-202604-0001',5,1,'course','direct','2026-04-06','Sridharshan','9876543210','sridharshany.2001@gmail.com','FSWD','Morning',NULL,NULL,NULL,NULL,'pending','not_given',NULL,'not_provided',NULL,NULL,'12000.00','0.00','12000.00','12000.00','0.00','paid','qwertyuiop','active',2,2,'2026-04-06 04:35:00','2026-04-08 04:59:15');
INSERT INTO `registrations` (`id`,`registration_no`,`enquiry_id`,`branch_id`,`reg_type`,`source_type`,`joined_on`,`enquiry_snapshot_name`,`enquiry_snapshot_phone`,`enquiry_snapshot_email`,`program_name`,`batch_name`,`internship_days`,`internship_batch`,`internship_start_date`,`internship_end_date`,`internship_completion_status`,`internship_certificate_status`,`internship_certificate_issued_at`,`internship_report_status`,`internship_report_issued_at`,`internship_report_due_days`,`total_fee`,`discount_amount`,`final_fee`,`paid_amount`,`balance_amount`,`payment_status`,`notes`,`registration_status`,`assigned_to`,`created_by`,`created_at`,`updated_at`) VALUES (6,'REG-202604-0002',6,1,'internship','direct','2026-04-07','Palanisamy','9876543210','webdeveloper005.ats@gmail.com','DS','Morning',NULL,NULL,NULL,NULL,'pending','not_given',NULL,'not_provided',NULL,NULL,'1500.00','0.00','1500.00','420.00','1080.00','partial','qwert','active',2,2,'2026-04-07 04:03:16','2026-04-10 03:58:46');
INSERT INTO `registrations` (`id`,`registration_no`,`enquiry_id`,`branch_id`,`reg_type`,`source_type`,`joined_on`,`enquiry_snapshot_name`,`enquiry_snapshot_phone`,`enquiry_snapshot_email`,`program_name`,`batch_name`,`internship_days`,`internship_batch`,`internship_start_date`,`internship_end_date`,`internship_completion_status`,`internship_certificate_status`,`internship_certificate_issued_at`,`internship_report_status`,`internship_report_issued_at`,`internship_report_due_days`,`total_fee`,`discount_amount`,`final_fee`,`paid_amount`,`balance_amount`,`payment_status`,`notes`,`registration_status`,`assigned_to`,`created_by`,`created_at`,`updated_at`) VALUES (7,'REG-202604-0003',NULL,1,'course','direct','2025-08-19','Harsha Vardhini','8438544320','harshavardhini7716@gmail.com','Ui/Ux Designing','2025',NULL,NULL,NULL,NULL,'pending','not_given',NULL,'not_provided',NULL,NULL,'22500.00','0.00','22500.00','0.00','22500.00','unpaid','Registration for course ui/ux designing 6months','active',11,2,'2026-04-09 06:54:17','2026-04-09 06:54:17');
INSERT INTO `registrations` (`id`,`registration_no`,`enquiry_id`,`branch_id`,`reg_type`,`source_type`,`joined_on`,`enquiry_snapshot_name`,`enquiry_snapshot_phone`,`enquiry_snapshot_email`,`program_name`,`batch_name`,`internship_days`,`internship_batch`,`internship_start_date`,`internship_end_date`,`internship_completion_status`,`internship_certificate_status`,`internship_certificate_issued_at`,`internship_report_status`,`internship_report_issued_at`,`internship_report_due_days`,`total_fee`,`discount_amount`,`final_fee`,`paid_amount`,`balance_amount`,`payment_status`,`notes`,`registration_status`,`assigned_to`,`created_by`,`created_at`,`updated_at`) VALUES (8,'REG-202604-0004',8,1,'internship','direct','2026-04-11','Dinesh','9876543210','webdeveloper005.ats@gmail.com','DS','Afternoon',NULL,NULL,NULL,NULL,'pending','not_given',NULL,'not_provided',NULL,NULL,'2000.00','0.00','2000.00','0.00','2000.00','unpaid','qwerty','active',2,2,'2026-04-11 09:34:56','2026-04-11 11:17:32');
INSERT INTO `registrations` (`id`,`registration_no`,`enquiry_id`,`branch_id`,`reg_type`,`source_type`,`joined_on`,`enquiry_snapshot_name`,`enquiry_snapshot_phone`,`enquiry_snapshot_email`,`program_name`,`batch_name`,`internship_days`,`internship_batch`,`internship_start_date`,`internship_end_date`,`internship_completion_status`,`internship_certificate_status`,`internship_certificate_issued_at`,`internship_report_status`,`internship_report_issued_at`,`internship_report_due_days`,`total_fee`,`discount_amount`,`final_fee`,`paid_amount`,`balance_amount`,`payment_status`,`notes`,`registration_status`,`assigned_to`,`created_by`,`created_at`,`updated_at`) VALUES (9,'REG-202604-0005',8,1,'course','direct','2026-04-11','Dinesh','9876543210','webdeveloper005.ats@gmail.com','DS','Afternoon',NULL,NULL,NULL,NULL,'pending','not_given',NULL,'not_provided',NULL,NULL,'20000.00','0.00','20000.00','0.00','20000.00','unpaid','qwerty','active',2,2,'2026-04-11 11:56:54','2026-04-11 12:03:14');

-- --------------------------------------------------------
-- Table: role_permissions

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_add` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `can_export` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  KEY `menu_id` (`menu_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3013 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (251,7,10,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (252,7,32,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (253,7,33,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (254,7,34,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (255,7,38,1,1,1,1,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (256,7,7,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (257,7,1,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (258,7,11,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (260,7,12,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (261,7,13,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (262,7,14,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (263,7,15,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (264,7,16,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (265,7,17,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (266,7,18,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (267,7,19,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (268,7,20,1,1,1,1,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (270,7,6,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (272,7,22,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (273,7,23,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (275,7,25,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (276,7,26,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (277,7,27,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (280,7,36,0,0,0,0,'2026-02-27 13:29:45','2026-02-27 13:29:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1499,8,10,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1500,8,32,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1501,8,33,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1502,8,34,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1503,8,38,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1504,8,7,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1505,8,1,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1506,8,11,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1507,8,5,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1508,8,12,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1509,8,13,1,1,1,1,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1510,8,14,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1511,8,16,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1512,8,17,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1513,8,18,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1514,8,19,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1515,8,20,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1517,8,48,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1518,8,6,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1519,8,46,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1520,8,47,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1521,8,55,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1522,8,39,1,1,1,1,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1523,8,40,1,1,1,1,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1524,8,41,1,1,1,1,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1525,8,42,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1526,8,43,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1527,8,44,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1528,8,45,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1529,8,56,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1530,8,57,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1531,8,22,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1532,8,23,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1534,8,25,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1535,8,26,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1536,8,27,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1539,8,36,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1541,8,49,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1542,8,50,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1543,8,51,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (1544,8,52,0,0,0,0,'2026-03-11 12:18:52','2026-03-11 12:18:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2415,4,10,0,0,0,0,'2026-03-19 11:14:26','2026-03-19 11:14:26',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2416,4,32,0,0,0,0,'2026-03-19 11:14:26','2026-03-19 11:14:26',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2417,4,33,0,0,0,0,'2026-03-19 11:14:27','2026-03-19 11:14:27',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2418,4,34,1,1,1,1,'2026-03-19 11:14:27','2026-03-19 11:14:27',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2419,4,38,0,0,0,0,'2026-03-19 11:14:27','2026-03-19 11:14:27',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2420,4,7,0,0,0,0,'2026-03-19 11:14:28','2026-03-19 11:14:28',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2421,4,1,0,0,0,0,'2026-03-19 11:14:28','2026-03-19 11:14:28',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2422,4,11,0,0,0,0,'2026-03-19 11:14:28','2026-03-19 11:14:28',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2423,4,5,0,0,0,0,'2026-03-19 11:14:29','2026-03-19 11:14:29',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2424,4,12,0,0,0,0,'2026-03-19 11:14:29','2026-03-19 11:14:29',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2425,4,13,0,0,0,0,'2026-03-19 11:14:29','2026-03-19 11:14:29',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2426,4,14,0,0,0,0,'2026-03-19 11:14:30','2026-03-19 11:14:30',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2427,4,16,0,0,0,0,'2026-03-19 11:14:30','2026-03-19 11:14:30',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2428,4,17,0,0,0,0,'2026-03-19 11:14:30','2026-03-19 11:14:30',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2429,4,18,1,1,1,1,'2026-03-19 11:14:30','2026-03-19 11:14:30',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2430,4,19,0,0,0,0,'2026-03-19 11:14:31','2026-03-19 11:14:31',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2431,4,20,1,1,1,1,'2026-03-19 11:14:31','2026-03-19 11:14:31',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2432,4,48,0,0,0,0,'2026-03-19 11:14:31','2026-03-19 11:14:31',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2433,4,6,0,0,0,0,'2026-03-19 11:14:32','2026-03-19 11:14:32',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2434,4,46,0,0,0,0,'2026-03-19 11:14:32','2026-03-19 11:14:32',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2435,4,47,0,0,0,0,'2026-03-19 11:14:32','2026-03-19 11:14:32',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2436,4,55,0,0,0,0,'2026-03-19 11:14:33','2026-03-19 11:14:33',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2437,4,39,0,0,0,0,'2026-03-19 11:14:33','2026-03-19 11:14:33',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2438,4,40,0,0,0,0,'2026-03-19 11:14:33','2026-03-19 11:14:33',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2439,4,41,0,0,0,0,'2026-03-19 11:14:33','2026-03-19 11:14:33',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2440,4,42,0,0,0,0,'2026-03-19 11:14:34','2026-03-19 11:14:34',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2441,4,43,0,0,0,0,'2026-03-19 11:14:34','2026-03-19 11:14:34',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2442,4,44,0,0,0,0,'2026-03-19 11:14:34','2026-03-19 11:14:34',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2443,4,45,0,0,0,0,'2026-03-19 11:14:35','2026-03-19 11:14:35',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2444,4,56,0,0,0,0,'2026-03-19 11:14:35','2026-03-19 11:14:35',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2445,4,57,0,0,0,0,'2026-03-19 11:14:35','2026-03-19 11:14:35',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2446,4,22,1,0,0,0,'2026-03-19 11:14:36','2026-03-19 11:14:36',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2447,4,23,1,1,0,0,'2026-03-19 11:14:36','2026-03-19 11:14:36',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2448,4,25,1,1,0,0,'2026-03-19 11:14:36','2026-03-19 11:14:36',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2449,4,26,0,0,0,0,'2026-03-19 11:14:37','2026-03-19 11:14:37',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2450,4,58,1,1,1,1,'2026-03-19 11:14:37','2026-03-19 11:14:37',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2451,4,27,0,0,0,0,'2026-03-19 11:14:37','2026-03-19 11:14:37',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2452,4,63,0,0,0,0,'2026-03-19 11:14:37','2026-03-19 11:14:37',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2453,4,65,0,0,0,0,'2026-03-19 11:14:38','2026-03-19 11:14:38',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2454,4,59,0,0,0,0,'2026-03-19 11:14:38','2026-03-19 11:14:38',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2455,4,69,1,1,1,1,'2026-03-19 11:14:38','2026-03-19 11:14:38',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2456,4,70,0,0,0,0,'2026-03-19 11:14:39','2026-03-19 11:14:39',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2457,4,60,0,0,0,0,'2026-03-19 11:14:39','2026-03-19 11:14:39',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2461,4,36,0,0,0,0,'2026-03-19 11:14:40','2026-03-19 11:14:40',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2462,4,49,0,0,0,0,'2026-03-19 11:14:41','2026-03-19 11:14:41',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2463,4,50,0,0,0,0,'2026-03-19 11:14:41','2026-03-19 11:14:41',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2464,4,51,0,0,0,0,'2026-03-19 11:14:41','2026-03-19 11:14:41',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2465,4,52,0,0,0,0,'2026-03-19 11:14:42','2026-03-19 11:14:42',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2790,1,10,1,1,1,1,'2026-04-08 04:11:06','2026-04-08 04:11:06',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2791,1,32,1,1,1,1,'2026-04-08 04:11:06','2026-04-08 04:11:06',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2792,1,33,1,1,1,1,'2026-04-08 04:11:06','2026-04-08 04:11:06',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2793,1,34,1,1,1,1,'2026-04-08 04:11:07','2026-04-08 04:11:07',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2794,1,38,1,1,1,1,'2026-04-08 04:11:07','2026-04-08 04:11:07',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2795,1,71,1,1,1,1,'2026-04-08 04:11:07','2026-04-08 04:11:07',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2796,1,7,1,1,1,1,'2026-04-08 04:11:08','2026-04-08 04:11:08',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2797,1,1,1,1,1,1,'2026-04-08 04:11:08','2026-04-08 04:11:08',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2798,1,11,1,1,1,1,'2026-04-08 04:11:08','2026-04-08 04:11:08',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2799,1,5,1,1,1,1,'2026-04-08 04:11:08','2026-04-08 04:11:08',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2800,1,12,1,1,1,1,'2026-04-08 04:11:09','2026-04-08 04:11:09',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2801,1,13,1,1,1,1,'2026-04-08 04:11:09','2026-04-08 04:11:09',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2802,1,14,1,1,1,1,'2026-04-08 04:11:09','2026-04-08 04:11:09',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2803,1,16,1,1,1,1,'2026-04-08 04:11:09','2026-04-08 04:11:09',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2804,1,17,1,1,1,1,'2026-04-08 04:11:10','2026-04-08 04:11:10',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2805,1,18,1,1,1,1,'2026-04-08 04:11:10','2026-04-08 04:11:10',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2806,1,19,1,1,1,1,'2026-04-08 04:11:10','2026-04-08 04:11:10',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2807,1,20,1,1,1,1,'2026-04-08 04:11:11','2026-04-08 04:11:11',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2808,1,48,1,1,1,1,'2026-04-08 04:11:11','2026-04-08 04:11:11',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2809,1,6,1,1,1,1,'2026-04-08 04:11:11','2026-04-08 04:11:11',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2810,1,46,1,1,1,1,'2026-04-08 04:11:11','2026-04-08 04:11:11',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2811,1,47,1,1,1,1,'2026-04-08 04:11:12','2026-04-08 04:11:12',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2812,1,55,1,1,1,1,'2026-04-08 04:11:12','2026-04-08 04:11:12',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2813,1,39,1,1,1,1,'2026-04-08 04:11:12','2026-04-08 04:11:12',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2814,1,40,1,1,1,1,'2026-04-08 04:11:13','2026-04-08 04:11:13',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2815,1,41,1,1,1,1,'2026-04-08 04:11:13','2026-04-08 04:11:13',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2816,1,42,1,1,1,1,'2026-04-08 04:11:13','2026-04-08 04:11:13',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2817,1,43,1,1,1,1,'2026-04-08 04:11:13','2026-04-08 04:11:13',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2818,1,44,1,1,1,1,'2026-04-08 04:11:14','2026-04-08 04:11:14',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2819,1,45,1,1,1,1,'2026-04-08 04:11:14','2026-04-08 04:11:14',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2820,1,56,1,1,1,1,'2026-04-08 04:11:14','2026-04-08 04:11:14',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2821,1,57,1,1,1,1,'2026-04-08 04:11:15','2026-04-08 04:11:15',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2822,1,77,1,1,1,1,'2026-04-08 04:11:15','2026-04-08 04:11:15',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2823,1,22,1,1,1,1,'2026-04-08 04:11:15','2026-04-08 04:11:15',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2824,1,23,1,1,1,1,'2026-04-08 04:11:15','2026-04-08 04:11:15',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2825,1,25,1,1,1,1,'2026-04-08 04:11:16','2026-04-08 04:11:16',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2826,1,26,1,1,1,1,'2026-04-08 04:11:16','2026-04-08 04:11:16',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2827,1,58,1,1,1,1,'2026-04-08 04:11:16','2026-04-08 04:11:16',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2828,1,27,1,1,1,1,'2026-04-08 04:11:16','2026-04-08 04:11:16',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2829,1,63,1,1,1,1,'2026-04-08 04:11:17','2026-04-08 04:11:17',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2830,1,65,1,1,1,1,'2026-04-08 04:11:17','2026-04-08 04:11:17',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2831,1,59,1,1,1,1,'2026-04-08 04:11:17','2026-04-08 04:11:17',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2832,1,69,1,1,1,1,'2026-04-08 04:11:18','2026-04-08 04:11:18',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2833,1,70,0,0,0,0,'2026-04-08 04:11:18','2026-04-08 04:11:18',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2834,1,73,0,0,0,0,'2026-04-08 04:11:18','2026-04-08 04:11:18',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2835,1,60,1,1,1,1,'2026-04-08 04:11:18','2026-04-08 04:11:18',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2836,1,74,0,0,0,0,'2026-04-08 04:11:19','2026-04-08 04:11:19',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2840,1,36,0,0,0,0,'2026-04-08 04:11:20','2026-04-08 04:11:20',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2841,1,49,1,1,1,1,'2026-04-08 04:11:20','2026-04-08 04:11:20',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2842,1,50,1,1,1,1,'2026-04-08 04:11:20','2026-04-08 04:11:20',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2843,1,51,0,0,0,0,'2026-04-08 04:11:21','2026-04-08 04:11:21',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2844,1,52,1,1,1,1,'2026-04-08 04:11:21','2026-04-08 04:11:21',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2845,1,78,1,1,1,1,'2026-04-08 07:59:08','2026-04-08 07:59:08',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2846,1,79,1,1,1,1,'2026-04-08 08:02:40','2026-04-08 08:02:40',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2847,1,80,1,1,1,1,'2026-04-08 08:04:47','2026-04-08 08:04:47',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2848,2,10,0,0,0,0,'2026-04-08 08:05:31','2026-04-08 08:05:31',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2849,2,32,1,1,1,1,'2026-04-08 08:05:31','2026-04-08 08:05:31',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2850,2,33,0,0,0,0,'2026-04-08 08:05:32','2026-04-08 08:05:32',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2851,2,34,0,0,0,0,'2026-04-08 08:05:32','2026-04-08 08:05:32',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2852,2,38,0,0,0,0,'2026-04-08 08:05:32','2026-04-08 08:05:32',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2853,2,71,0,0,0,0,'2026-04-08 08:05:33','2026-04-08 08:05:33',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2854,2,7,0,0,0,0,'2026-04-08 08:05:33','2026-04-08 08:05:33',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2855,2,1,0,0,0,0,'2026-04-08 08:05:33','2026-04-08 08:05:33',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2856,2,11,0,0,0,0,'2026-04-08 08:05:33','2026-04-08 08:05:33',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2857,2,5,0,0,0,0,'2026-04-08 08:05:34','2026-04-08 08:05:34',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2858,2,12,1,1,1,1,'2026-04-08 08:05:34','2026-04-08 08:05:34',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2859,2,13,1,1,1,1,'2026-04-08 08:05:34','2026-04-08 08:05:34',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2860,2,14,1,1,1,1,'2026-04-08 08:05:34','2026-04-08 08:05:34',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2861,2,16,1,1,1,1,'2026-04-08 08:05:35','2026-04-08 08:05:35',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2862,2,17,1,1,1,1,'2026-04-08 08:05:35','2026-04-08 08:05:35',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2863,2,18,1,1,1,1,'2026-04-08 08:05:35','2026-04-08 08:05:35',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2864,2,19,0,0,0,0,'2026-04-08 08:05:36','2026-04-08 08:05:36',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2865,2,20,1,1,1,1,'2026-04-08 08:05:36','2026-04-08 08:05:36',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2866,2,48,1,1,1,1,'2026-04-08 08:05:36','2026-04-08 08:05:36',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2867,2,6,0,0,0,0,'2026-04-08 08:05:36','2026-04-08 08:05:36',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2868,2,46,1,1,1,1,'2026-04-08 08:05:37','2026-04-08 08:05:37',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2869,2,47,1,1,1,1,'2026-04-08 08:05:37','2026-04-08 08:05:37',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2870,2,55,1,1,1,1,'2026-04-08 08:05:37','2026-04-08 08:05:37',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2871,2,39,1,1,1,1,'2026-04-08 08:05:38','2026-04-08 08:05:38',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2872,2,40,1,1,1,1,'2026-04-08 08:05:38','2026-04-08 08:05:38',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2873,2,41,1,1,1,1,'2026-04-08 08:05:38','2026-04-08 08:05:38',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2874,2,42,1,1,1,1,'2026-04-08 08:05:38','2026-04-08 08:05:38',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2875,2,43,1,1,1,1,'2026-04-08 08:05:39','2026-04-08 08:05:39',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2876,2,44,1,1,1,1,'2026-04-08 08:05:39','2026-04-08 08:05:39',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2877,2,45,1,1,1,1,'2026-04-08 08:05:39','2026-04-08 08:05:39',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2878,2,56,1,1,1,1,'2026-04-08 08:05:40','2026-04-08 08:05:40',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2879,2,57,1,1,1,1,'2026-04-08 08:05:40','2026-04-08 08:05:40',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2880,2,77,0,0,0,0,'2026-04-08 08:05:40','2026-04-08 08:05:40',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2881,2,22,1,1,1,1,'2026-04-08 08:05:40','2026-04-08 08:05:40',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2882,2,23,1,1,1,1,'2026-04-08 08:05:41','2026-04-08 08:05:41',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2883,2,25,1,1,1,1,'2026-04-08 08:05:41','2026-04-08 08:05:41',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2884,2,26,0,0,0,0,'2026-04-08 08:05:41','2026-04-08 08:05:41',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2885,2,58,0,0,0,0,'2026-04-08 08:05:41','2026-04-08 08:05:41',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2886,2,27,0,0,0,0,'2026-04-08 08:05:42','2026-04-08 08:05:42',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2887,2,63,0,0,0,0,'2026-04-08 08:05:42','2026-04-08 08:05:42',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2888,2,65,0,0,0,0,'2026-04-08 08:05:42','2026-04-08 08:05:42',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2889,2,59,1,1,1,1,'2026-04-08 08:05:43','2026-04-08 08:05:43',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2890,2,69,0,0,0,0,'2026-04-08 08:05:43','2026-04-08 08:05:43',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2891,2,70,0,0,0,0,'2026-04-08 08:05:43','2026-04-08 08:05:43',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2892,2,73,0,0,0,0,'2026-04-08 08:05:43','2026-04-08 08:05:43',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2893,2,60,1,1,1,1,'2026-04-08 08:05:44','2026-04-08 08:05:44',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2894,2,74,0,0,0,0,'2026-04-08 08:05:44','2026-04-08 08:05:44',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2895,2,78,1,1,1,1,'2026-04-08 08:05:44','2026-04-08 08:05:44',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2896,2,79,1,1,1,1,'2026-04-08 08:05:45','2026-04-08 08:05:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2897,2,80,1,1,1,1,'2026-04-08 08:05:45','2026-04-08 08:05:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2898,2,36,0,0,0,0,'2026-04-08 08:05:45','2026-04-08 08:05:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2899,2,49,0,0,0,0,'2026-04-08 08:05:45','2026-04-08 08:05:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2900,2,50,0,0,0,0,'2026-04-08 08:05:46','2026-04-08 08:05:46',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2901,2,51,1,1,1,1,'2026-04-08 08:05:46','2026-04-08 08:05:46',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2902,2,52,0,0,0,0,'2026-04-08 08:05:46','2026-04-08 08:05:46',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2903,3,10,0,0,0,0,'2026-04-08 10:52:54','2026-04-08 10:52:54',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2904,3,32,0,0,0,0,'2026-04-08 10:52:55','2026-04-08 10:52:55',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2905,3,33,1,1,1,1,'2026-04-08 10:52:55','2026-04-08 10:52:55',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2906,3,34,0,0,0,0,'2026-04-08 10:52:55','2026-04-08 10:52:55',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2907,3,38,0,0,0,0,'2026-04-08 10:52:56','2026-04-08 10:52:56',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2908,3,71,0,0,0,0,'2026-04-08 10:52:56','2026-04-08 10:52:56',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2909,3,7,0,0,0,0,'2026-04-08 10:52:56','2026-04-08 10:52:56',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2910,3,1,0,0,0,0,'2026-04-08 10:52:57','2026-04-08 10:52:57',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2911,3,11,0,0,0,0,'2026-04-08 10:52:57','2026-04-08 10:52:57',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2912,3,5,0,0,0,0,'2026-04-08 10:52:58','2026-04-08 10:52:58',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2913,3,12,1,1,1,1,'2026-04-08 10:52:58','2026-04-08 10:52:58',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2914,3,13,1,1,1,1,'2026-04-08 10:52:58','2026-04-08 10:52:58',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2915,3,14,0,0,0,0,'2026-04-08 10:52:58','2026-04-08 10:52:58',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2916,3,16,1,1,1,1,'2026-04-08 10:52:59','2026-04-08 10:52:59',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2917,3,17,1,1,1,1,'2026-04-08 10:52:59','2026-04-08 10:52:59',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2918,3,18,1,1,1,1,'2026-04-08 10:52:59','2026-04-08 10:52:59',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2919,3,19,1,1,1,1,'2026-04-08 10:53:00','2026-04-08 10:53:00',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2920,3,20,1,1,1,1,'2026-04-08 10:53:00','2026-04-08 10:53:00',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2921,3,48,1,1,1,1,'2026-04-08 10:53:00','2026-04-08 10:53:00',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2922,3,6,0,0,0,0,'2026-04-08 10:53:01','2026-04-08 10:53:01',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2923,3,46,1,1,1,1,'2026-04-08 10:53:01','2026-04-08 10:53:01',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2924,3,47,1,1,1,1,'2026-04-08 10:53:01','2026-04-08 10:53:01',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2925,3,55,1,1,1,1,'2026-04-08 10:53:02','2026-04-08 10:53:02',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2926,3,39,0,0,0,0,'2026-04-08 10:53:02','2026-04-08 10:53:02',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2927,3,40,1,0,0,0,'2026-04-08 10:53:02','2026-04-08 10:53:02',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2928,3,41,1,1,1,1,'2026-04-08 10:53:02','2026-04-08 10:53:02',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2929,3,42,0,0,0,0,'2026-04-08 10:53:03','2026-04-08 10:53:03',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2930,3,43,0,0,0,0,'2026-04-08 10:53:03','2026-04-08 10:53:03',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2931,3,44,0,0,0,0,'2026-04-08 10:53:03','2026-04-08 10:53:03',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2932,3,45,0,0,0,0,'2026-04-08 10:53:04','2026-04-08 10:53:04',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2933,3,56,0,0,0,0,'2026-04-08 10:53:04','2026-04-08 10:53:04',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2934,3,57,0,0,0,0,'2026-04-08 10:53:05','2026-04-08 10:53:05',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2935,3,77,1,1,1,1,'2026-04-08 10:53:05','2026-04-08 10:53:05',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2936,3,22,0,0,0,0,'2026-04-08 10:53:05','2026-04-08 10:53:05',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2937,3,23,1,1,1,1,'2026-04-08 10:53:05','2026-04-08 10:53:05',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2938,3,25,0,0,0,0,'2026-04-08 10:53:06','2026-04-08 10:53:06',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2939,3,26,0,0,0,0,'2026-04-08 10:53:06','2026-04-08 10:53:06',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2940,3,58,0,0,0,0,'2026-04-08 10:53:07','2026-04-08 10:53:07',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2941,3,27,0,0,0,0,'2026-04-08 10:53:07','2026-04-08 10:53:07',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2942,3,63,1,1,1,1,'2026-04-08 10:53:07','2026-04-08 10:53:07',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2943,3,65,1,1,1,1,'2026-04-08 10:53:08','2026-04-08 10:53:08',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2944,3,59,1,1,1,1,'2026-04-08 10:53:08','2026-04-08 10:53:08',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2945,3,69,0,0,0,0,'2026-04-08 10:53:08','2026-04-08 10:53:08',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2946,3,70,1,1,1,1,'2026-04-08 10:53:09','2026-04-08 10:53:09',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2947,3,73,0,0,0,0,'2026-04-08 10:53:09','2026-04-08 10:53:09',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2948,3,60,1,1,1,1,'2026-04-08 10:53:09','2026-04-08 10:53:09',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2949,3,74,0,0,0,0,'2026-04-08 10:53:09','2026-04-08 10:53:09',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2950,3,78,1,1,1,1,'2026-04-08 10:53:10','2026-04-08 10:53:10',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2951,3,79,1,1,1,1,'2026-04-08 10:53:10','2026-04-08 10:53:10',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2952,3,80,1,1,1,1,'2026-04-08 10:53:11','2026-04-08 10:53:11',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2953,3,36,0,0,0,0,'2026-04-08 10:53:11','2026-04-08 10:53:11',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2954,3,49,1,1,1,1,'2026-04-08 10:53:11','2026-04-08 10:53:11',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2955,3,50,1,1,1,1,'2026-04-08 10:53:12','2026-04-08 10:53:12',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2956,3,51,1,1,1,1,'2026-04-08 10:53:12','2026-04-08 10:53:12',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2957,3,52,1,1,1,1,'2026-04-08 10:53:12','2026-04-08 10:53:12',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2958,6,10,0,0,0,0,'2026-04-10 12:49:45','2026-04-10 12:49:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2959,6,32,0,0,0,0,'2026-04-10 12:49:45','2026-04-10 12:49:45',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2960,6,33,0,0,0,0,'2026-04-10 12:49:46','2026-04-10 12:49:46',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2961,6,34,0,0,0,0,'2026-04-10 12:49:46','2026-04-10 12:49:46',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2962,6,38,0,0,0,0,'2026-04-10 12:49:46','2026-04-10 12:49:46',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2963,6,71,0,0,0,0,'2026-04-10 12:49:46','2026-04-10 12:49:46',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2964,6,7,0,0,0,0,'2026-04-10 12:49:47','2026-04-10 12:49:47',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2965,6,1,0,0,0,0,'2026-04-10 12:49:47','2026-04-10 12:49:47',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2966,6,11,0,0,0,0,'2026-04-10 12:49:47','2026-04-10 12:49:47',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2967,6,5,0,0,0,0,'2026-04-10 12:49:47','2026-04-10 12:49:47',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2968,6,12,1,1,1,1,'2026-04-10 12:49:48','2026-04-10 12:49:48',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2969,6,13,0,0,0,0,'2026-04-10 12:49:48','2026-04-10 12:49:48',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2970,6,14,1,0,0,0,'2026-04-10 12:49:48','2026-04-10 12:49:48',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2971,6,16,0,0,0,0,'2026-04-10 12:49:48','2026-04-10 12:49:48',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2972,6,17,0,0,0,0,'2026-04-10 12:49:49','2026-04-10 12:49:49',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2973,6,18,0,0,0,0,'2026-04-10 12:49:49','2026-04-10 12:49:49',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2974,6,19,0,0,0,0,'2026-04-10 12:49:49','2026-04-10 12:49:49',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2975,6,20,1,1,1,1,'2026-04-10 12:49:50','2026-04-10 12:49:50',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2976,6,48,1,1,1,1,'2026-04-10 12:49:50','2026-04-10 12:49:50',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2977,6,6,0,0,0,0,'2026-04-10 12:49:50','2026-04-10 12:49:50',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2978,6,46,1,1,1,1,'2026-04-10 12:49:50','2026-04-10 12:49:50',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2979,6,47,1,1,1,1,'2026-04-10 12:49:51','2026-04-10 12:49:51',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2980,6,55,1,1,1,1,'2026-04-10 12:49:51','2026-04-10 12:49:51',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2981,6,39,0,0,0,0,'2026-04-10 12:49:51','2026-04-10 12:49:51',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2982,6,40,0,0,0,0,'2026-04-10 12:49:51','2026-04-10 12:49:51',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2983,6,41,0,0,0,0,'2026-04-10 12:49:52','2026-04-10 12:49:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2984,6,42,0,0,0,0,'2026-04-10 12:49:52','2026-04-10 12:49:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2985,6,43,0,0,0,0,'2026-04-10 12:49:52','2026-04-10 12:49:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2986,6,44,0,0,0,0,'2026-04-10 12:49:52','2026-04-10 12:49:52',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2987,6,45,1,1,1,1,'2026-04-10 12:49:53','2026-04-10 12:49:53',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2988,6,56,0,0,0,0,'2026-04-10 12:49:53','2026-04-10 12:49:53',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2989,6,57,0,0,0,0,'2026-04-10 12:49:53','2026-04-10 12:49:53',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2990,6,77,0,0,0,0,'2026-04-10 12:49:53','2026-04-10 12:49:53',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2991,6,22,0,0,0,0,'2026-04-10 12:49:54','2026-04-10 12:49:54',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2992,6,23,0,0,0,0,'2026-04-10 12:49:54','2026-04-10 12:49:54',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2993,6,25,0,0,0,0,'2026-04-10 12:49:54','2026-04-10 12:49:54',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2994,6,26,0,0,0,0,'2026-04-10 12:49:54','2026-04-10 12:49:54',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2995,6,58,0,0,0,0,'2026-04-10 12:49:55','2026-04-10 12:49:55',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2996,6,27,0,0,0,0,'2026-04-10 12:49:55','2026-04-10 12:49:55',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2997,6,63,0,0,0,0,'2026-04-10 12:49:55','2026-04-10 12:49:55',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2998,6,65,0,0,0,0,'2026-04-10 12:49:55','2026-04-10 12:49:55',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (2999,6,59,0,0,0,0,'2026-04-10 12:49:56','2026-04-10 12:49:56',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (3000,6,69,0,0,0,0,'2026-04-10 12:49:56','2026-04-10 12:49:56',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (3001,6,70,0,0,0,0,'2026-04-10 12:49:56','2026-04-10 12:49:56',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (3002,6,73,0,0,0,0,'2026-04-10 12:49:57','2026-04-10 12:49:57',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (3003,6,60,0,0,0,0,'2026-04-10 12:49:57','2026-04-10 12:49:57',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (3004,6,74,0,0,0,0,'2026-04-10 12:49:57','2026-04-10 12:49:57',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (3005,6,78,1,1,1,1,'2026-04-10 12:49:57','2026-04-10 12:49:57',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (3006,6,79,1,1,1,1,'2026-04-10 12:49:58','2026-04-10 12:49:58',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (3007,6,80,1,1,1,1,'2026-04-10 12:49:58','2026-04-10 12:49:58',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (3008,6,36,0,0,0,0,'2026-04-10 12:49:58','2026-04-10 12:49:58',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (3009,6,49,0,0,0,0,'2026-04-10 12:49:58','2026-04-10 12:49:58',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (3010,6,50,0,0,0,0,'2026-04-10 12:49:59','2026-04-10 12:49:59',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (3011,6,51,1,1,1,1,'2026-04-10 12:49:59','2026-04-10 12:49:59',0);
INSERT INTO `role_permissions` (`id`,`role_id`,`menu_id`,`can_view`,`can_add`,`can_edit`,`can_delete`,`created_at`,`updated_at`,`can_export`) VALUES (3012,6,52,0,0,0,0,'2026-04-10 12:49:59','2026-04-10 12:49:59',0);

-- --------------------------------------------------------
-- Table: roles

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) NOT NULL,
  `default_dashboard_slug` varchar(150) DEFAULT NULL,
  `can_access_all_branches` tinyint(1) DEFAULT 0,
  `is_target_applicable` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `roles` (`id`,`role_name`,`default_dashboard_slug`,`can_access_all_branches`,`is_target_applicable`,`status`,`created_at`,`updated_at`) VALUES (1,'Super Admin','dashboard/superadmin',1,0,1,'2026-02-24 14:43:56','2026-03-24 06:59:00');
INSERT INTO `roles` (`id`,`role_name`,`default_dashboard_slug`,`can_access_all_branches`,`is_target_applicable`,`status`,`created_at`,`updated_at`) VALUES (2,'Front Office','dashboard/frontoffice',0,1,1,'2026-02-26 11:54:51','2026-03-24 06:38:40');
INSERT INTO `roles` (`id`,`role_name`,`default_dashboard_slug`,`can_access_all_branches`,`is_target_applicable`,`status`,`created_at`,`updated_at`) VALUES (3,'HR','dashboard/hr',0,1,1,'2026-02-26 11:55:14','2026-03-07 16:14:59');
INSERT INTO `roles` (`id`,`role_name`,`default_dashboard_slug`,`can_access_all_branches`,`is_target_applicable`,`status`,`created_at`,`updated_at`) VALUES (4,'Staff','dashboard/staff',0,1,1,'2026-02-26 11:55:20','2026-03-07 16:14:54');
INSERT INTO `roles` (`id`,`role_name`,`default_dashboard_slug`,`can_access_all_branches`,`is_target_applicable`,`status`,`created_at`,`updated_at`) VALUES (6,'Marketing','dashboard/marketing',0,1,1,'2026-02-27 11:35:47','2026-03-07 16:14:46');
INSERT INTO `roles` (`id`,`role_name`,`default_dashboard_slug`,`can_access_all_branches`,`is_target_applicable`,`status`,`created_at`,`updated_at`) VALUES (7,'Test','dashboard/test',0,0,1,'2026-02-27 13:11:38','2026-03-09 18:05:34');
INSERT INTO `roles` (`id`,`role_name`,`default_dashboard_slug`,`can_access_all_branches`,`is_target_applicable`,`status`,`created_at`,`updated_at`) VALUES (8,'Corporate','dashboad/test',0,0,1,'2026-03-11 12:17:50','2026-03-11 12:17:50');

-- --------------------------------------------------------
-- Table: student_hr_interviews

DROP TABLE IF EXISTS `student_hr_interviews`;
CREATE TABLE `student_hr_interviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `registration_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `staff_user_id` int(11) NOT NULL,
  `sent_to_hr_by` int(11) DEFAULT NULL,
  `sent_to_hr_at` datetime NOT NULL DEFAULT current_timestamp(),
  `company_name` varchar(150) DEFAULT NULL,
  `interview_date` date DEFAULT NULL,
  `interview_status` enum('pending','scheduled','selected','rejected','on_hold') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `hr_updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_student_hr_interviews_registration` (`registration_id`),
  KEY `idx_student_hr_interviews_branch` (`branch_id`),
  KEY `idx_student_hr_interviews_staff` (`staff_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `student_hr_interviews` (`id`,`registration_id`,`branch_id`,`staff_user_id`,`sent_to_hr_by`,`sent_to_hr_at`,`company_name`,`interview_date`,`interview_status`,`rejection_reason`,`hr_updated_by`,`created_at`,`updated_at`) VALUES (1,2,1,8,8,'2026-03-31 07:53:07','ATS','2026-04-07','scheduled',NULL,3,'2026-03-31 13:22:24','2026-04-06 09:54:09');

-- --------------------------------------------------------
-- Table: tasks

DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assigned_to` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('Pending','Completed') DEFAULT 'Pending',
  PRIMARY KEY (`id`),
  KEY `assigned_to` (`assigned_to`),
  CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------
-- Table: user_notifications

DROP TABLE IF EXISTS `user_notifications`;
CREATE TABLE `user_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `notif_key` varchar(191) NOT NULL,
  `type` varchar(80) NOT NULL,
  `priority` varchar(20) NOT NULL DEFAULT 'medium',
  `title` varchar(191) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_notif` (`user_id`,`notif_key`),
  KEY `idx_user_read_created` (`user_id`,`is_read`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=224 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_notifications` (`id`,`user_id`,`notif_key`,`type`,`priority`,`title`,`message`,`link`,`is_read`,`created_at`,`read_at`,`expires_at`) VALUES (1,2,'target_assigned_1_1775607595','target_assigned','medium','New Target Assigned','Your target for April 2026 is Rs 100,000.00.','http://localhost/new_2025/demo/crm gitcopy/index.php?page=targets/my-target',1,'2026-04-13 13:58:18','2026-04-13 14:01:34','2026-04-30 23:59:59');
INSERT INTO `user_notifications` (`id`,`user_id`,`notif_key`,`type`,`priority`,`title`,`message`,`link`,`is_read`,`created_at`,`read_at`,`expires_at`) VALUES (5,5,'target_assigned_2_1776069338','target_assigned','medium','New Target Assigned','Your target for April 2026 is Rs 50,000.00.','http://localhost/new_2025/demo/crm gitcopy/index.php?page=targets/my-target',1,'2026-04-13 14:12:23','2026-04-13 14:12:41','2026-04-30 23:59:59');
INSERT INTO `user_notifications` (`id`,`user_id`,`notif_key`,`type`,`priority`,`title`,`message`,`link`,`is_read`,`created_at`,`read_at`,`expires_at`) VALUES (37,2,'lead_assigned_58','lead_assigned','medium','New Lead Assigned','A new lead \"Dinesh\" has been assigned to you.','http://localhost/new_2025/demo/crm gitcopy/index.php?page=leads/list',1,'2026-04-13 16:40:40','2026-04-13 16:58:04','2026-05-13 16:40:40');
INSERT INTO `user_notifications` (`id`,`user_id`,`notif_key`,`type`,`priority`,`title`,`message`,`link`,`is_read`,`created_at`,`read_at`,`expires_at`) VALUES (38,2,'lead_assigned_9','lead_assigned','medium','New Lead Assigned','A new lead \"Palanisamy\" has been assigned to you.','http://localhost/new_2025/demo/crm gitcopy/index.php?page=leads/list',1,'2026-04-13 16:40:41','2026-04-13 16:58:04','2026-05-13 16:40:40');
INSERT INTO `user_notifications` (`id`,`user_id`,`notif_key`,`type`,`priority`,`title`,`message`,`link`,`is_read`,`created_at`,`read_at`,`expires_at`) VALUES (39,2,'lead_assigned_8','lead_assigned','medium','New Lead Assigned','A new lead \"Sridharshan\" has been assigned to you.','http://localhost/new_2025/demo/crm gitcopy/index.php?page=leads/list',1,'2026-04-13 16:40:41','2026-04-13 16:58:04','2026-05-13 16:40:40');
INSERT INTO `user_notifications` (`id`,`user_id`,`notif_key`,`type`,`priority`,`title`,`message`,`link`,`is_read`,`created_at`,`read_at`,`expires_at`) VALUES (40,2,'lead_assigned_2','lead_assigned','medium','New Lead Assigned','A new lead \"Joseph\" has been assigned to you.','http://localhost/new_2025/demo/crm gitcopy/index.php?page=leads/list',1,'2026-04-13 16:40:41','2026-04-13 16:58:04','2026-05-13 16:40:41');
INSERT INTO `user_notifications` (`id`,`user_id`,`notif_key`,`type`,`priority`,`title`,`message`,`link`,`is_read`,`created_at`,`read_at`,`expires_at`) VALUES (46,10,'lead_assigned_39','lead_assigned','medium','New Lead Assigned','A new lead \"juil mary\" has been assigned to you.','http://localhost/new_2025/demo/crm gitcopy/index.php?page=leads/list',0,'2026-04-13 16:42:19',NULL,'2026-05-13 16:42:18');
INSERT INTO `user_notifications` (`id`,`user_id`,`notif_key`,`type`,`priority`,`title`,`message`,`link`,`is_read`,`created_at`,`read_at`,`expires_at`) VALUES (47,10,'lead_assigned_10','lead_assigned','medium','New Lead Assigned','A new lead \"pramodhini\" has been assigned to you.','http://localhost/new_2025/demo/crm gitcopy/index.php?page=leads/list',1,'2026-04-13 16:42:19','2026-04-13 17:17:32','2026-05-13 16:42:19');
INSERT INTO `user_notifications` (`id`,`user_id`,`notif_key`,`type`,`priority`,`title`,`message`,`link`,`is_read`,`created_at`,`read_at`,`expires_at`) VALUES (48,10,'lead_assigned_7','lead_assigned','medium','New Lead Assigned','A new lead \"Varsha\" has been assigned to you.','http://localhost/new_2025/demo/crm gitcopy/index.php?page=leads/list',1,'2026-04-13 16:42:19','2026-04-13 17:15:06','2026-05-13 16:42:19');
INSERT INTO `user_notifications` (`id`,`user_id`,`notif_key`,`type`,`priority`,`title`,`message`,`link`,`is_read`,`created_at`,`read_at`,`expires_at`) VALUES (49,10,'lead_assigned_4','lead_assigned','medium','New Lead Assigned','A new lead \"Akash\" has been assigned to you.','http://localhost/new_2025/demo/crm gitcopy/index.php?page=leads/list',1,'2026-04-13 16:42:20','2026-04-13 17:14:58','2026-05-13 16:42:19');
INSERT INTO `user_notifications` (`id`,`user_id`,`notif_key`,`type`,`priority`,`title`,`message`,`link`,`is_read`,`created_at`,`read_at`,`expires_at`) VALUES (50,10,'lead_assigned_3','lead_assigned','medium','New Lead Assigned','A new lead \"Arjun\" has been assigned to you.','http://localhost/new_2025/demo/crm gitcopy/index.php?page=leads/list',0,'2026-04-13 16:42:20',NULL,'2026-05-13 16:42:20');
INSERT INTO `user_notifications` (`id`,`user_id`,`notif_key`,`type`,`priority`,`title`,`message`,`link`,`is_read`,`created_at`,`read_at`,`expires_at`) VALUES (51,10,'lead_assigned_1','lead_assigned','medium','New Lead Assigned','A new lead \"Suresh\" has been assigned to you.','http://localhost/new_2025/demo/crm gitcopy/index.php?page=leads/list',1,'2026-04-13 16:42:20','2026-04-13 17:10:59','2026-05-13 16:42:20');
INSERT INTO `user_notifications` (`id`,`user_id`,`notif_key`,`type`,`priority`,`title`,`message`,`link`,`is_read`,`created_at`,`read_at`,`expires_at`) VALUES (196,3,'target_assigned_4_1776243282','target_assigned','medium','New Target Assigned','Your target for April 2026 is Rs 55,000.00.','http://localhost/new_2025/demo/crm gitcopy/index.php?page=targets/my-target',0,'2026-04-15 14:24:52',NULL,'2026-04-30 23:59:59');
INSERT INTO `user_notifications` (`id`,`user_id`,`notif_key`,`type`,`priority`,`title`,`message`,`link`,`is_read`,`created_at`,`read_at`,`expires_at`) VALUES (209,2,'lead_assigned_75','lead_assigned','medium','New Lead Assigned','A new lead \"Testname\" has been assigned to you.','http://localhost/new_2025/demo/crm gitcopy/index.php?page=leads/list',0,'2026-04-15 14:53:06',NULL,'2026-05-15 14:53:05');

-- --------------------------------------------------------
-- Table: users

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `must_change_password` tinyint(1) DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `remember_selector` varchar(24) DEFAULT NULL,
  `remember_token_hash` varchar(255) DEFAULT NULL,
  `remember_expires` datetime DEFAULT NULL,
  `reset_token_hash` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `branch_id` (`branch_id`),
  KEY `role_id` (`role_id`),
  KEY `idx_remember_selector` (`remember_selector`),
  KEY `idx_users_name` (`name`),
  KEY `idx_users_email` (`email`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`,`branch_id`,`role_id`,`name`,`email`,`phone`,`password`,`must_change_password`,`last_login`,`last_login_ip`,`status`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`remember_selector`,`remember_token_hash`,`remember_expires`,`reset_token_hash`,`reset_expires`) VALUES (1,1,1,'admin','admin@gmail.com',NULL,'$2y$10$XSJvOcrYzm1Si2hMt4XBAO8woxn0B2kN1/7sQAFN5GXTqgWZJx6r6',0,'2026-04-15 15:39:08','::1',1,'2026-02-24 14:43:56','2026-04-15 15:39:08',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`branch_id`,`role_id`,`name`,`email`,`phone`,`password`,`must_change_password`,`last_login`,`last_login_ip`,`status`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`remember_selector`,`remember_token_hash`,`remember_expires`,`reset_token_hash`,`reset_expires`) VALUES (2,1,2,'John','webdeveloper05.ats@gmail.com','9942133944','$2y$10$RndVVNRzU5.otdBKdQ0GrucyKFszBSxuf1eBKo2IdNi7mKpDckkM.',0,'2026-04-15 14:52:51','::1',1,'2026-02-27 09:40:22','2026-04-15 14:52:51',1,1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`branch_id`,`role_id`,`name`,`email`,`phone`,`password`,`must_change_password`,`last_login`,`last_login_ip`,`status`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`remember_selector`,`remember_token_hash`,`remember_expires`,`reset_token_hash`,`reset_expires`) VALUES (3,1,3,'Michael','michael@gmail.com','9942133944','$2y$10$1v.h78Rz.VEfLfzF4mzUIOfYt/ucjkL0qpq4i.57N7AkUHuWyBKF.',0,'2026-04-15 14:56:07','::1',1,'2026-02-27 09:55:00','2026-04-15 14:56:07',1,1,'2401:4900:8825:644e:4140:caba:bff:f0c0','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`branch_id`,`role_id`,`name`,`email`,`phone`,`password`,`must_change_password`,`last_login`,`last_login_ip`,`status`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`remember_selector`,`remember_token_hash`,`remember_expires`,`reset_token_hash`,`reset_expires`) VALUES (4,1,4,'Fredrick','fredrick@gmail.com','7896541236','$2y$10$NyGE/HNP0cteOIu13cNBrulKKUxPjbgJ1igsjcY8nSiuEuJlL/EiS',0,'2026-04-11 14:32:26','::1',1,'2026-02-27 09:55:31','2026-04-11 14:32:26',1,1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`branch_id`,`role_id`,`name`,`email`,`phone`,`password`,`must_change_password`,`last_login`,`last_login_ip`,`status`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`remember_selector`,`remember_token_hash`,`remember_expires`,`reset_token_hash`,`reset_expires`) VALUES (5,1,6,'Fredrick John','fredrickjohn@gmail.com','9942133944','$2y$10$8xOZr5zGQCXQbah699/iv.scVJCDTAFOBaYSU3rT8TlfjvF2f1BSy',0,'2026-04-14 10:08:27','2405:201:e015:50bd:955c:e1e9:8856:73b4',1,'2026-02-27 11:48:43','2026-04-14 10:08:27',1,1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`branch_id`,`role_id`,`name`,`email`,`phone`,`password`,`must_change_password`,`last_login`,`last_login_ip`,`status`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`remember_selector`,`remember_token_hash`,`remember_expires`,`reset_token_hash`,`reset_expires`) VALUES (6,1,7,'Test','test@gmail.com','9874563210','$2y$10$CYWliVwyZj8aAXljmNjrke8QM/jhkktGlOw46obUIp1N8buTUb4/q',0,'2026-02-27 13:30:01','::1',1,'2026-02-27 13:12:51','2026-02-27 13:30:01',1,1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`branch_id`,`role_id`,`name`,`email`,`phone`,`password`,`must_change_password`,`last_login`,`last_login_ip`,`status`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`remember_selector`,`remember_token_hash`,`remember_expires`,`reset_token_hash`,`reset_expires`) VALUES (7,1,2,'Suresh','suresh@gmail.com','9874563214','$2y$10$.Chm89m3LfxsgYaKMzd47OCMBAp5nVfXpwHFWK7R7DrLKcev3jIm6',0,'2026-04-10 03:13:31','::1',1,'2026-03-09 14:38:38','2026-04-10 03:13:31',1,1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`branch_id`,`role_id`,`name`,`email`,`phone`,`password`,`must_change_password`,`last_login`,`last_login_ip`,`status`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`remember_selector`,`remember_token_hash`,`remember_expires`,`reset_token_hash`,`reset_expires`) VALUES (8,1,4,'Sridharshan','webdeveloper005.ats@gmail.com','9876543210','$2y$10$.Chm89m3LfxsgYaKMzd47OCMBAp5nVfXpwHFWK7R7DrLKcev3jIm6',0,'2026-04-11 17:28:40','::1',1,'2026-03-11 06:51:11','2026-04-11 17:28:41',1,1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`branch_id`,`role_id`,`name`,`email`,`phone`,`password`,`must_change_password`,`last_login`,`last_login_ip`,`status`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`remember_selector`,`remember_token_hash`,`remember_expires`,`reset_token_hash`,`reset_expires`) VALUES (9,1,8,'Sample','sample@gmail.com','234567890','$2y$10$St4Untsr9wTc1f7FRT.8aeFB1cwGHn2KDVeXGuh0acJBmsCqK2iX.',0,'2026-03-11 13:04:41','2401:4900:8825:644e:c116:fd72:e0c1:8291',1,'2026-03-11 12:19:43','2026-03-11 13:04:41',1,1,'2401:4900:8825:644e:44a5:d376:2e7f:954b','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36',NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`branch_id`,`role_id`,`name`,`email`,`phone`,`password`,`must_change_password`,`last_login`,`last_login_ip`,`status`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`remember_selector`,`remember_token_hash`,`remember_expires`,`reset_token_hash`,`reset_expires`) VALUES (10,1,2,'pramo','pramo@gmail.com','7896541236','$2y$10$JPP0QaZrwfnWsoMDW.9YxuPnl8Ul1/Co96o0DIFhelHMJY9VZClUm',0,'2026-04-13 16:12:34','::1',1,'2026-03-24 06:08:43','2026-04-13 16:12:34',1,1,'2401:4900:8825:644e:85f7:385b:a1f4:35a1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`branch_id`,`role_id`,`name`,`email`,`phone`,`password`,`must_change_password`,`last_login`,`last_login_ip`,`status`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`remember_selector`,`remember_token_hash`,`remember_expires`,`reset_token_hash`,`reset_expires`) VALUES (11,1,2,'Theju','theju@gmail.com','874596521','$2y$10$5oyokvxQZ5W.mBIGlF7Huetm0O8bO7suhyUG0FGlpFi13S.Yay.l.',0,'2026-03-30 09:14:30','2401:4900:8825:644e:5c49:878b:2a0:8236',1,'2026-03-24 06:09:24','2026-03-30 09:14:30',1,1,'2401:4900:8825:644e:85f7:385b:a1f4:35a1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`branch_id`,`role_id`,`name`,`email`,`phone`,`password`,`must_change_password`,`last_login`,`last_login_ip`,`status`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`remember_selector`,`remember_token_hash`,`remember_expires`,`reset_token_hash`,`reset_expires`) VALUES (12,1,4,'Raghul','raghul@gmail.com','789654136','$2y$10$9qKZb8lz8OHNcU2bWNN.Nueo/fVfMS3Nnw20dA6JE.U4OLF2N/gJm',0,'2026-03-31 07:45:58','223.185.27.31',1,'2026-03-24 06:10:59','2026-03-31 07:45:58',1,1,'2405:201:e015:50bd:8df4:ad31:f677:3c13','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0',NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`branch_id`,`role_id`,`name`,`email`,`phone`,`password`,`must_change_password`,`last_login`,`last_login_ip`,`status`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`remember_selector`,`remember_token_hash`,`remember_expires`,`reset_token_hash`,`reset_expires`) VALUES (13,1,1,'Admin User Test1','admin@atscrm.com','9000000001','$2y$10$dneLsIcGaIjJQsGXakz.N.jkDj/y8tujl1D8Rs6ir90KJlQraxuTW',0,NULL,NULL,1,'2026-03-24 07:01:47','2026-03-24 07:59:10',1,1,'2405:201:e015:50bd:f51b:db:6227:915c','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`branch_id`,`role_id`,`name`,`email`,`phone`,`password`,`must_change_password`,`last_login`,`last_login_ip`,`status`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`remember_selector`,`remember_token_hash`,`remember_expires`,`reset_token_hash`,`reset_expires`) VALUES (14,1,3,'Krishnan','krishnan.hr@gmail.com','9000000002','$2y$10$Gf9RVnpdYshfeEj0cyzIgOKqqcMLNJtfZvL0IiUabsC.lm7fhmboC',0,NULL,NULL,1,'2026-03-24 07:02:57','2026-03-24 07:59:14',1,1,'2405:201:e015:50bd:f51b:db:6227:915c','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`branch_id`,`role_id`,`name`,`email`,`phone`,`password`,`must_change_password`,`last_login`,`last_login_ip`,`status`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`remember_selector`,`remember_token_hash`,`remember_expires`,`reset_token_hash`,`reset_expires`) VALUES (15,1,4,'Arunkumar','arun@gmail.com','8148903261','$2y$10$2NcAq6j3kSS/csXBji0d0uIts2Vb.QaN6cTa1D9PeBe3vM2KI5S3W',0,'2026-03-30 07:31:02','2401:4900:8825:644e:84a2:27d2:1df3:b321',1,'2026-03-24 07:19:24','2026-03-30 07:31:02',1,1,'2401:4900:8825:644e:21e1:1230:a94a:f720','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`branch_id`,`role_id`,`name`,`email`,`phone`,`password`,`must_change_password`,`last_login`,`last_login_ip`,`status`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`remember_selector`,`remember_token_hash`,`remember_expires`,`reset_token_hash`,`reset_expires`) VALUES (16,1,2,'Testuser','testuser@gmail.com','7896541236','$2y$10$4.EDVgwBicCbIWECnGcElenLTgmqqi56JnCYRfngKvFmB1Z050hmS',0,NULL,NULL,1,'2026-03-25 04:11:25','2026-03-25 04:11:25',1,1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`branch_id`,`role_id`,`name`,`email`,`phone`,`password`,`must_change_password`,`last_login`,`last_login_ip`,`status`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`remember_selector`,`remember_token_hash`,`remember_expires`,`reset_token_hash`,`reset_expires`) VALUES (17,1,6,'Saravana','saravanan@gmail.com','9874563210','$2y$10$ftHfb6DRliKMnY9uJ2pgROR6pkL6LnJKc3aE1MOEwQgv658A50IB6',0,'2026-04-14 14:11:36','::1',1,'2026-04-10 13:03:20','2026-04-14 14:11:36',1,1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',NULL,NULL,NULL,NULL,NULL);
INSERT INTO `users` (`id`,`branch_id`,`role_id`,`name`,`email`,`phone`,`password`,`must_change_password`,`last_login`,`last_login_ip`,`status`,`created_at`,`updated_at`,`created_by`,`updated_by`,`ip_address`,`user_agent`,`remember_selector`,`remember_token_hash`,`remember_expires`,`reset_token_hash`,`reset_expires`) VALUES (18,1,6,'kumar','kumar@gmail.com','7896541365','$2y$10$CahTgAKxmceIl73Lg12GHuGsCM/L1kFPSTTG7TdwtVITvZ5r9mvDS',0,'2026-04-14 13:38:11','::1',1,'2026-04-14 11:12:32','2026-04-14 13:38:11',1,1,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36',NULL,NULL,NULL,NULL,NULL);

SET FOREIGN_KEY_CHECKS = 1;
