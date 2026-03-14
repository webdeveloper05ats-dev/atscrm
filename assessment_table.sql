CREATE TABLE `assessment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `registration_id` int NOT NULL,
  `branch_id` int NOT NULL,
  `staff_user_id` int NOT NULL,
  `assessment_1` decimal(5,2) DEFAULT NULL,
  `assessment_2` decimal(5,2) DEFAULT NULL,
  `assessment_3` decimal(5,2) DEFAULT NULL,
  `average_marks` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_assessment_registration` (`registration_id`),
  KEY `idx_assessment_branch` (`branch_id`),
  KEY `idx_assessment_staff` (`staff_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
