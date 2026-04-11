-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Apr 11, 2026 at 05:06 AM
-- Server version: 11.8.6-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u440631799_crm`
--

-- --------------------------------------------------------

--
-- Table structure for table `assessment`
--

CREATE TABLE `assessment` (
  `id` int(11) NOT NULL,
  `registration_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `staff_user_id` int(11) NOT NULL,
  `assessment_1` decimal(5,2) DEFAULT NULL,
  `assessment_2` decimal(5,2) DEFAULT NULL,
  `assessment_3` decimal(5,2) DEFAULT NULL,
  `average_marks` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assessment`
--

INSERT INTO `assessment` (`id`, `registration_id`, `branch_id`, `staff_user_id`, `assessment_1`, `assessment_2`, `assessment_3`, `average_marks`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 8, 89.00, 89.00, 89.00, 89.00, '2026-03-31 05:13:30', '2026-03-31 05:13:30'),
(2, 5, 1, 8, 99.00, 89.00, 99.00, 95.67, '2026-04-06 05:07:55', '2026-04-07 03:56:28');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `registration_id`, `user_id`, `course_id`, `branch_id`, `attendance_date`, `status`, `topics_taught`, `task_given`, `absent_informed`, `absent_reason`, `absent_informed_by`, `marked_by`, `created_at`, `updated_at`) VALUES
(1, 2, 2, 0, 1, '2026-03-30', 'Present', 'qwertyuiop', 'ertyuiop[', NULL, NULL, NULL, 8, '2026-03-30 08:15:16', '2026-03-30 08:15:16'),
(2, 3, 3, 0, 1, '2026-03-30', 'Present', 'wzerxctvbyunimogfvhbjnk', 'zwesxrdcftvgybhujnkml', NULL, NULL, NULL, 12, '2026-03-31 07:46:41', '2026-03-31 07:47:17'),
(3, 3, 3, 0, 1, '2026-03-31', 'Absent', NULL, NULL, 'yes', '3wszxerdtfvgbyhunjmk,l', 'student', 12, '2026-03-31 07:47:58', '2026-03-31 07:47:58'),
(4, 5, 5, 0, 1, '2026-04-06', 'Absent', NULL, NULL, 'no', NULL, NULL, 8, '2026-04-06 05:02:01', '2026-04-06 05:02:01'),
(5, 2, 2, 0, 1, '2026-04-05', 'Absent', NULL, NULL, 'no', NULL, NULL, 8, '2026-04-06 05:07:11', '2026-04-06 05:07:11'),
(6, 5, 5, 0, 1, '2026-04-07', 'Absent', NULL, NULL, 'no', NULL, NULL, 8, '2026-04-07 03:50:52', '2026-04-07 03:50:52');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `table_name` varchar(100) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `batches`
--

CREATE TABLE `batches` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `batch_name` varchar(100) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `trainer_id` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `branch_name` varchar(150) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `branch_name`, `location`, `phone`, `email`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Main Branch', NULL, NULL, NULL, 1, '2026-02-24 14:43:56', '2026-02-24 14:43:56');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `certificate_number` varchar(100) DEFAULT NULL,
  `status` enum('Pending','Issued') DEFAULT 'Pending',
  `issued_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `hr_name` varchar(150) DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `client_type` enum('old','new','placement') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `colleges_master`
--

CREATE TABLE `colleges_master` (
  `id` int(11) NOT NULL,
  `college_name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `designation` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `college_type` enum('arts','engineering','polytechnic','other') DEFAULT 'other',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int(11) NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contacts_master`
--

CREATE TABLE `contacts_master` (
  `id` int(11) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `designation` varchar(150) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `college_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contacts_master`
--

INSERT INTO `contacts_master` (`id`, `name`, `designation`, `email`, `phone`, `college_id`, `created_at`) VALUES
(1, 'John', NULL, NULL, NULL, NULL, '2026-03-19 10:07:45');

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
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
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_frontoffice_activity`
--

CREATE TABLE `dailyreport_frontoffice_activity` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dailyreport_frontoffice_activity`
--

INSERT INTO `dailyreport_frontoffice_activity` (`id`, `master_id`, `fresh_calls`, `follow_calls`, `messages_sent`, `mails_sent`, `total_calls`, `promotions`, `reference_count`, `db_calls`, `registration_total`, `billing`, `fresh_collection`, `old_collection`, `total_collection`, `walkins`, `conversion_ratio`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 2, 3, 4, 11, 2, 2, 2, 6, 1000.00, 750.00, 150.00, 900.00, 5, 120.00, '2026-04-08 08:33:20', '2026-04-08 12:43:07'),
(7, 2, 4, 4, 4, 4, 16, 4, 4, 4, 12, 1400.00, 0.00, 1000.00, 1000.00, 2, 600.00, '2026-04-08 10:14:10', '2026-04-08 10:14:10'),
(8, 3, 2, 2, 22, 2, 28, 2, 2, 2, 6, 2220.00, 0.00, 220.00, 220.00, 20, 30.00, '2026-04-08 10:50:12', '2026-04-08 10:50:12'),
(10, 4, 4, 4, 4, 4, 16, 4, 4, 4, 12, 15000.00, 250.00, 1500.00, 1750.00, 1, 1200.00, '2026-04-10 03:35:46', '2026-04-10 03:35:46'),
(11, 5, 2, 2, 2, 2, 8, 2, 2, 2, 6, 0.00, 0.00, 0.00, 0.00, 0, 0.00, '2026-04-10 03:37:46', '2026-04-10 03:37:46');

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_frontoffice_college_followup_rows`
--

CREATE TABLE `dailyreport_frontoffice_college_followup_rows` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dailyreport_frontoffice_college_followup_rows`
--

INSERT INTO `dailyreport_frontoffice_college_followup_rows` (`id`, `master_id`, `sort_order`, `serial_no`, `contact_name`, `designation`, `email`, `contact_no`, `college_name`, `location`, `created_at`, `updated_at`) VALUES
(5, 1, 1, '1', 'Swarna', 'HOD\\CSE', 'hod.cse.svcet@gmail.com', '9842749646', 'S.VeeraSammy Chettiar College of Engg', 'salem', '2026-04-08 12:43:15', '2026-04-08 12:43:15'),
(6, 4, 1, '1', 'ATS WEB DEVELOPER', 'HOD\\CSE', 'hod.cse.svcet@gmail.com', '9842749646', 'S.VeeraSammy Chettiar College of Engg', 'salem', '2026-04-10 03:35:53', '2026-04-10 03:35:53'),
(7, 5, 1, '1', 'ATS WEB DEVELOPER', '', 'hod.cse.svcet@gmail.com', '9842749646', 'S.VeeraSammy Chettiar College of Engg', 'salem', '2026-04-10 03:37:53', '2026-04-10 03:37:53');

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_frontoffice_college_followup_status`
--

CREATE TABLE `dailyreport_frontoffice_college_followup_status` (
  `id` int(11) NOT NULL,
  `followup_row_id` int(11) NOT NULL,
  `status_date` date NOT NULL,
  `status_text` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dailyreport_frontoffice_college_followup_status`
--

INSERT INTO `dailyreport_frontoffice_college_followup_status` (`id`, `followup_row_id`, `status_date`, `status_text`, `created_at`, `updated_at`) VALUES
(5, 5, '2026-04-08', 'Details shared', '2026-04-08 12:43:16', '2026-04-08 12:43:16'),
(6, 6, '2026-04-10', 'erfer', '2026-04-10 03:35:53', '2026-04-10 03:35:53'),
(7, 7, '2026-04-09', 'tgtgt', '2026-04-10 03:37:53', '2026-04-10 03:37:53');

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_frontoffice_database_followup_rows`
--

CREATE TABLE `dailyreport_frontoffice_database_followup_rows` (
  `id` int(11) NOT NULL,
  `master_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `serial_no` varchar(20) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `college` varchar(255) DEFAULT NULL,
  `mobile` varchar(30) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dailyreport_frontoffice_database_followup_rows`
--

INSERT INTO `dailyreport_frontoffice_database_followup_rows` (`id`, `master_id`, `sort_order`, `serial_no`, `name`, `department`, `college`, `mobile`, `created_at`, `updated_at`) VALUES
(5, 2, 1, '1', 'Palanisamy', 'CHE', 'ABC', '9876543210', '2026-04-08 10:14:19', '2026-04-08 10:14:19'),
(6, 3, 1, '1', 'Sridharshan', 'CHEM', 'Kongu', '9876543210', '2026-04-08 10:50:22', '2026-04-08 10:50:22'),
(7, 1, 1, '1', 'Bavatharavi', 'MCA', 'Mahaligam college of engineering and technology', '6369623603', '2026-04-08 12:43:16', '2026-04-08 12:43:16'),
(8, 4, 1, '1', 'Bavatharavi', 'MCA', 'Mahaligam college of engineering and technology', '6369623603', '2026-04-10 03:35:54', '2026-04-10 03:35:54'),
(9, 4, 2, '2', 'Palanisamy', 'MCA', 'Mahaligam college of engineering and technology', '6369623603', '2026-04-10 03:35:55', '2026-04-10 03:35:55'),
(10, 5, 1, '1', 'Bavatharavi', 'MCA', 'Mahaligam college of engineering and technology', '6369623603', '2026-04-10 03:37:54', '2026-04-10 03:37:54');

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_frontoffice_database_followup_status`
--

CREATE TABLE `dailyreport_frontoffice_database_followup_status` (
  `id` int(11) NOT NULL,
  `database_row_id` int(11) NOT NULL,
  `status_date` date NOT NULL,
  `status_text` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dailyreport_frontoffice_database_followup_status`
--

INSERT INTO `dailyreport_frontoffice_database_followup_status` (`id`, `database_row_id`, `status_date`, `status_text`, `created_at`, `updated_at`) VALUES
(5, 5, '2026-04-07', 'Lead Status: converted | qwertyuiop', '2026-04-08 10:14:19', '2026-04-08 10:14:19'),
(6, 6, '2026-04-06', 'Lead Status: converted | qwertyuio', '2026-04-08 10:50:22', '2026-04-08 10:50:22'),
(7, 7, '2026-04-08', 'dicuss and call back', '2026-04-08 12:43:17', '2026-04-08 12:43:17'),
(8, 8, '2026-04-10', 'regregrg', '2026-04-10 03:35:55', '2026-04-10 03:35:55'),
(9, 9, '2026-04-10', 'rgergergerg', '2026-04-10 03:35:55', '2026-04-10 03:35:55'),
(10, 10, '2026-04-09', 'rtgre', '2026-04-10 03:37:54', '2026-04-10 03:37:54');

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_frontoffice_hourly_rows`
--

CREATE TABLE `dailyreport_frontoffice_hourly_rows` (
  `id` int(11) NOT NULL,
  `master_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `time_from` varchar(20) DEFAULT NULL,
  `time_to` varchar(20) DEFAULT NULL,
  `particulars` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dailyreport_frontoffice_hourly_rows`
--

INSERT INTO `dailyreport_frontoffice_hourly_rows` (`id`, `master_id`, `sort_order`, `time_from`, `time_to`, `particulars`, `remarks`, `created_at`, `updated_at`) VALUES
(9, 2, 1, '09:30', '10:30', 'following calls', 'fgererg', '2026-04-08 10:14:17', '2026-04-08 10:14:17'),
(10, 3, 1, '09:30', '10:30', 'following calls', 'ewdwe', '2026-04-08 10:50:20', '2026-04-08 10:50:20'),
(11, 1, 1, '09:30', '10:30', 'following calls', 'call-17 connecting call-13 not connecting -4', '2026-04-08 12:43:14', '2026-04-08 12:43:14'),
(12, 1, 2, '10:30', '11:30', 'Registation follow up', 'call-22 connecting call -18 not connecting -5', '2026-04-08 12:43:14', '2026-04-08 12:43:14'),
(13, 1, 3, '11:30', '12:30', 'One Hour Permission', 'Went out for personal reason', '2026-04-08 12:43:15', '2026-04-08 12:43:15'),
(14, 4, 1, '09:30', '10:30', 'following calls', 'dwsfffer ewfwerfewr ferfewrfwef werf', '2026-04-10 03:35:51', '2026-04-10 03:35:51'),
(15, 4, 2, '10:30', '11:30', 'One Hour Permission', 'dsfsdfwe erf ewrfewr', '2026-04-10 03:35:52', '2026-04-10 03:35:52'),
(16, 4, 3, '11:30', '12:30', 'following calls', 'wefwef wefwef wefwef wefwe', '2026-04-10 03:35:52', '2026-04-10 03:35:52'),
(17, 4, 4, '12:30', '13:30', 'following calls', 'fwef wefwf', '2026-04-10 03:35:52', '2026-04-10 03:35:52'),
(18, 5, 1, '09:30', '10:30', 'following calls', 'dgdfhn  ghd gdg gdgdgd', '2026-04-10 03:37:52', '2026-04-10 03:37:52'),
(19, 5, 2, '10:30', '11:30', 'following calls', 'dfgdfggd', '2026-04-10 03:37:52', '2026-04-10 03:37:52');

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_frontoffice_planner_rows`
--

CREATE TABLE `dailyreport_frontoffice_planner_rows` (
  `id` int(11) NOT NULL,
  `master_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `time_slot` varchar(100) DEFAULT NULL,
  `activity` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dailyreport_frontoffice_planner_rows`
--

INSERT INTO `dailyreport_frontoffice_planner_rows` (`id`, `master_id`, `sort_order`, `time_slot`, `activity`, `description`, `created_at`, `updated_at`) VALUES
(5, 2, 1, '09:30 - 10:30', '', '', '2026-04-08 10:14:17', '2026-04-08 10:14:17'),
(6, 3, 1, '09:30 - 10:30', '', '', '2026-04-08 10:50:19', '2026-04-08 10:50:19'),
(7, 1, 1, '09:30 - 10:30', 'Planning & Prioritizing', 'Review daily goals, check pending follow-ups, arrange your leads.', '2026-04-08 12:43:13', '2026-04-08 12:43:13'),
(8, 4, 1, '09:30 - 10:30', 'Planning & Prioritizing', 'test', '2026-04-10 03:35:51', '2026-04-10 03:35:51'),
(9, 5, 1, '09:30 - 10:30', 'Planning & Prioritizing', 'jhmghmghj', '2026-04-10 03:37:51', '2026-04-10 03:37:51'),
(10, 5, 2, '10:30 - 11:30', 'Planning & Prioritizing', 'dfgdfgdgdfgb', '2026-04-10 03:37:51', '2026-04-10 03:37:51'),
(11, 5, 3, '11:30 - 12:30', 'dfgdfgd', 'dfgdfgdfgdfg', '2026-04-10 03:37:51', '2026-04-10 03:37:51');

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_frontoffice_registration_rows`
--

CREATE TABLE `dailyreport_frontoffice_registration_rows` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dailyreport_frontoffice_registration_rows`
--

INSERT INTO `dailyreport_frontoffice_registration_rows` (`id`, `master_id`, `serial_no`, `name`, `department`, `contact_no`, `college`, `date_of_registration`, `course`, `billing`, `collection_amount`, `balance_amount`, `payment_mode`, `created_at`, `updated_at`) VALUES
(5, 2, 1, 'Palanisamy', 'Student', '9876543210', 'ABC', '2026-04-07', 'DS', 1500.00, 310.00, 1190.00, '', '2026-04-08 10:14:16', '2026-04-08 10:14:16'),
(6, 3, 1, 'Sridharshan', 'Student', '9876543210', 'Kongu', '2026-04-06', 'FSWD', 12000.00, 12000.00, 0.00, '', '2026-04-08 10:50:19', '2026-04-08 10:50:19'),
(7, 1, 1, 'John', 'MCA', '9874563321', 'TEST', '2026-04-08', 'PHP', 15000.00, 0.00, 15000.00, '', '2026-04-08 12:43:13', '2026-04-08 12:43:13'),
(8, 4, 1, 'John', 'MCA', '9874563321', 'TEST', '2026-04-09', 'PHP', 15000.00, 1000.00, 14000.00, 'Cash', '2026-04-10 03:35:50', '2026-04-10 03:35:50'),
(9, 5, 1, 'John', 'MCA', '9874563321', 'TEST', '2026-04-08', 'PHP', 1000.00, 0.00, 1000.00, '', '2026-04-10 03:37:50', '2026-04-10 03:37:50');

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_hr_activity`
--

CREATE TABLE `dailyreport_hr_activity` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dailyreport_hr_activity`
--

INSERT INTO `dailyreport_hr_activity` (`id`, `master_id`, `fresh_calls`, `follow_calls`, `messages_sent`, `mails_sent`, `total_calls`, `forum_posting`, `promotions`, `reference_count`, `db_calls`, `registration_total`, `billing`, `fresh_collection`, `old_collection`, `total_collection`, `walkins`, `conversion_ratio`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 6, 2, 2, 2, 2, 8, 0, 2, 2, 2, 6, 14000.00, 1000.00, 2000.00, 3000.00, 1, 75.00, '', '2026-04-10 05:51:37', '2026-04-10 06:03:59');

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_hr_college_data_rows`
--

CREATE TABLE `dailyreport_hr_college_data_rows` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dailyreport_hr_college_data_rows`
--

INSERT INTO `dailyreport_hr_college_data_rows` (`id`, `master_id`, `serial_no`, `contact_name`, `contact_no`, `college_name`, `topic`, `days_text`, `resource_person`, `requirement`, `status_text`, `created_at`, `updated_at`) VALUES
(2, 6, 1, 'rgeg', 'rege', 'grerg', 'egg', 'eergeg', 'rgerg', 'eger', 'grge', '2026-04-10 06:04:08', '2026-04-10 06:04:08');

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_hr_college_followup_rows`
--

CREATE TABLE `dailyreport_hr_college_followup_rows` (
  `id` int(11) NOT NULL,
  `master_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `name` varchar(150) DEFAULT NULL,
  `position` varchar(150) DEFAULT NULL,
  `mail_id` varchar(150) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `report_text` text DEFAULT NULL,
  `college` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dailyreport_hr_college_followup_rows`
--

INSERT INTO `dailyreport_hr_college_followup_rows` (`id`, `master_id`, `sort_order`, `name`, `position`, `mail_id`, `contact_number`, `report_text`, `college`, `created_at`, `updated_at`) VALUES
(2, 6, 1, 'regeg', 'regerg', 'regreger', 'ergreg', 'regerg', 'gregg', '2026-04-10 06:04:08', '2026-04-10 06:04:08');

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_hr_hourly_rows`
--

CREATE TABLE `dailyreport_hr_hourly_rows` (
  `id` int(11) NOT NULL,
  `master_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `time_from` varchar(20) DEFAULT NULL,
  `time_to` varchar(20) DEFAULT NULL,
  `particulars` varchar(255) DEFAULT NULL,
  `activities_undergone` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dailyreport_hr_hourly_rows`
--

INSERT INTO `dailyreport_hr_hourly_rows` (`id`, `master_id`, `sort_order`, `time_from`, `time_to`, `particulars`, `activities_undergone`, `created_at`, `updated_at`) VALUES
(4, 6, 1, '09:30', '10:30', 'Follow ups/Best performer', 'Follow ups/Best performer', '2026-04-10 06:04:04', '2026-04-10 06:04:04'),
(5, 6, 2, '10:30', '11:30', 'Follow ups/Best performer', 'Follow ups/Best performer', '2026-04-10 06:04:04', '2026-04-10 06:04:04'),
(6, 6, 3, '11:30', '12:30', 'Follow ups/Best performer', 'Follow ups/Best performer', '2026-04-10 06:04:05', '2026-04-10 06:04:05');

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_hr_internship_rows`
--

CREATE TABLE `dailyreport_hr_internship_rows` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dailyreport_hr_internship_rows`
--

INSERT INTO `dailyreport_hr_internship_rows` (`id`, `master_id`, `serial_no`, `staff_name`, `college_name`, `department`, `student_count`, `platform`, `topic`, `mode_type`, `duration_text`, `start_date`, `finish_date`, `mini_project`, `date_1`, `topic_1`, `date_2`, `topic_2`, `date_3`, `topic_3`, `date_4`, `topic_4`, `date_5`, `topic_5`, `date_6`, `topic_6`, `date_7`, `topic_7`, `date_8`, `topic_8`, `date_9`, `topic_9`, `date_10`, `topic_10`, `date_11`, `topic_11`, `date_12`, `topic_12`, `created_at`, `updated_at`) VALUES
(2, 6, 1, 'dsg', 'reerer', 'rrefer', 30, 'ewr', '34', 'rfwfwef', 'ffwewf', '2026-03-04', '2026-04-01', 'rg', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-10 06:04:05', '2026-04-10 06:04:05');

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_hr_interview_rows`
--

CREATE TABLE `dailyreport_hr_interview_rows` (
  `id` int(11) NOT NULL,
  `master_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `candidate_name` varchar(150) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `interview_date` date DEFAULT NULL,
  `interview_status` varchar(100) DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dailyreport_hr_interview_rows`
--

INSERT INTO `dailyreport_hr_interview_rows` (`id`, `master_id`, `sort_order`, `candidate_name`, `company_name`, `interview_date`, `interview_status`, `remark`, `created_at`, `updated_at`) VALUES
(2, 6, 1, NULL, 'ebergerger', '2026-04-15', 'frergerg', 'rgergre', '2026-04-10 06:04:06', '2026-04-10 06:04:06');

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_hr_new_client_rows`
--

CREATE TABLE `dailyreport_hr_new_client_rows` (
  `id` int(11) NOT NULL,
  `master_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `company_name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `hr_name` varchar(150) DEFAULT NULL,
  `contact_number` varchar(120) DEFAULT NULL,
  `status_text` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dailyreport_hr_new_client_rows`
--

INSERT INTO `dailyreport_hr_new_client_rows` (`id`, `master_id`, `sort_order`, `company_name`, `address`, `city`, `hr_name`, `contact_number`, `status_text`, `created_at`, `updated_at`) VALUES
(2, 6, 1, 'rfgr', 'rgg', 'ggrreg', 'ererg', 'rgg', 'erge', '2026-04-10 06:04:07', '2026-04-10 06:04:07');

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_hr_old_client_rows`
--

CREATE TABLE `dailyreport_hr_old_client_rows` (
  `id` int(11) NOT NULL,
  `master_id` int(11) NOT NULL,
  `serial_no` int(11) DEFAULT NULL,
  `client_company` varchar(255) DEFAULT NULL,
  `poc` varchar(150) DEFAULT NULL,
  `contact_no` varchar(100) DEFAULT NULL,
  `email_id` varchar(150) DEFAULT NULL,
  `followup_date` date DEFAULT NULL,
  `followup_report` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dailyreport_hr_old_client_rows`
--

INSERT INTO `dailyreport_hr_old_client_rows` (`id`, `master_id`, `serial_no`, `client_company`, `poc`, `contact_no`, `email_id`, `followup_date`, `followup_report`, `created_at`, `updated_at`) VALUES
(2, 6, 1, 'rfereg', 'erger', 'ggrgr', 'rgegg', '2026-04-10', 'gergerger', '2026-04-10 06:04:07', '2026-04-10 06:04:07');

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_hr_placement_call_rows`
--

CREATE TABLE `dailyreport_hr_placement_call_rows` (
  `id` int(11) NOT NULL,
  `master_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `entry_date` date DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `poc_name` varchar(150) DEFAULT NULL,
  `contact_no` varchar(100) DEFAULT NULL,
  `status_text` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dailyreport_hr_placement_call_rows`
--

INSERT INTO `dailyreport_hr_placement_call_rows` (`id`, `master_id`, `sort_order`, `entry_date`, `company_name`, `poc_name`, `contact_no`, `status_text`, `remarks`, `created_at`, `updated_at`) VALUES
(2, 6, 1, '2026-04-10', 'rger', 'greer', 'rerer', 'gerer', 'ererfger', '2026-04-10 06:04:06', '2026-04-10 06:04:06');

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_marketing_activity`
--

CREATE TABLE `dailyreport_marketing_activity` (
  `id` int(10) UNSIGNED NOT NULL,
  `master_id` int(10) UNSIGNED NOT NULL,
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
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_marketing_act_report_rows`
--

CREATE TABLE `dailyreport_marketing_act_report_rows` (
  `id` int(10) UNSIGNED NOT NULL,
  `master_id` int(10) UNSIGNED NOT NULL,
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
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_marketing_amount_rows`
--

CREATE TABLE `dailyreport_marketing_amount_rows` (
  `id` int(10) UNSIGNED NOT NULL,
  `master_id` int(10) UNSIGNED NOT NULL,
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
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_marketing_arts_college_rows`
--

CREATE TABLE `dailyreport_marketing_arts_college_rows` (
  `id` int(10) UNSIGNED NOT NULL,
  `master_id` int(10) UNSIGNED NOT NULL,
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
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_marketing_arts_pc_rows`
--

CREATE TABLE `dailyreport_marketing_arts_pc_rows` (
  `id` int(10) UNSIGNED NOT NULL,
  `master_id` int(10) UNSIGNED NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `serial_no` int(11) NOT NULL DEFAULT 0,
  `place_name` varchar(150) DEFAULT NULL,
  `college_name` varchar(255) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `designation` varchar(120) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_marketing_colleges_rows`
--

CREATE TABLE `dailyreport_marketing_colleges_rows` (
  `id` int(10) UNSIGNED NOT NULL,
  `master_id` int(10) UNSIGNED NOT NULL,
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
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_marketing_engg_college_rows`
--

CREATE TABLE `dailyreport_marketing_engg_college_rows` (
  `id` int(10) UNSIGNED NOT NULL,
  `master_id` int(10) UNSIGNED NOT NULL,
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
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_marketing_engg_pc_rows`
--

CREATE TABLE `dailyreport_marketing_engg_pc_rows` (
  `id` int(10) UNSIGNED NOT NULL,
  `master_id` int(10) UNSIGNED NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `serial_no` int(11) NOT NULL DEFAULT 0,
  `place_name` varchar(150) DEFAULT NULL,
  `college_name` varchar(255) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `name` varchar(150) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `email_id` varchar(190) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_marketing_hourly_rows`
--

CREATE TABLE `dailyreport_marketing_hourly_rows` (
  `id` int(10) UNSIGNED NOT NULL,
  `master_id` int(10) UNSIGNED NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `time_from` varchar(10) DEFAULT NULL,
  `time_to` varchar(10) DEFAULT NULL,
  `particulars` varchar(255) DEFAULT NULL,
  `activities_undergone` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_marketing_polytech_college_rows`
--

CREATE TABLE `dailyreport_marketing_polytech_college_rows` (
  `id` int(10) UNSIGNED NOT NULL,
  `master_id` int(10) UNSIGNED NOT NULL,
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
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_marketing_program_rows`
--

CREATE TABLE `dailyreport_marketing_program_rows` (
  `id` int(10) UNSIGNED NOT NULL,
  `master_id` int(10) UNSIGNED NOT NULL,
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
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_marketing_prospect_rows`
--

CREATE TABLE `dailyreport_marketing_prospect_rows` (
  `id` int(10) UNSIGNED NOT NULL,
  `master_id` int(10) UNSIGNED NOT NULL,
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
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_marketing_prospect_status_rows`
--

CREATE TABLE `dailyreport_marketing_prospect_status_rows` (
  `id` int(10) UNSIGNED NOT NULL,
  `prospect_row_id` int(10) UNSIGNED NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 1,
  `status_date` date DEFAULT NULL,
  `status_text` varchar(255) DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dailyreport_master`
--

CREATE TABLE `dailyreport_master` (
  `id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `role_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `report_type` enum('frontoffice','marketing','hr') NOT NULL,
  `status` enum('draft','submitted','locked') NOT NULL DEFAULT 'draft',
  `submitted_at` datetime DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dailyreport_master`
--

INSERT INTO `dailyreport_master` (`id`, `report_date`, `role_id`, `user_id`, `branch_id`, `report_type`, `status`, `submitted_at`, `locked_at`, `created_at`, `updated_at`) VALUES
(1, '2026-04-08', 2, 2, 1, 'frontoffice', 'submitted', '2026-04-08 12:43:17', NULL, '2026-04-08 08:21:44', '2026-04-08 12:43:17'),
(2, '2026-04-07', 2, 2, 1, 'frontoffice', 'submitted', '2026-04-08 10:14:20', NULL, '2026-04-08 10:11:28', '2026-04-08 10:14:20'),
(3, '2026-04-06', 2, 2, 1, 'frontoffice', 'submitted', '2026-04-08 10:50:23', NULL, '2026-04-08 10:49:19', '2026-04-08 10:50:23'),
(4, '2026-04-10', 2, 7, 1, 'frontoffice', 'submitted', '2026-04-10 03:35:56', NULL, '2026-04-10 03:14:08', '2026-04-10 03:35:56'),
(5, '2026-04-09', 2, 7, 1, 'frontoffice', 'submitted', '2026-04-10 03:37:55', NULL, '2026-04-10 03:36:23', '2026-04-10 03:37:55'),
(6, '2026-04-10', 3, 3, 1, 'hr', 'submitted', '2026-04-10 06:04:09', NULL, '2026-04-10 05:02:06', '2026-04-10 06:04:09'),
(7, '2026-04-10', 6, 5, 1, 'marketing', 'draft', NULL, NULL, '2026-04-10 13:04:06', '2026-04-10 13:04:06'),
(8, '2026-04-09', 6, 5, 1, 'marketing', 'draft', NULL, NULL, '2026-04-10 16:08:26', '2026-04-10 16:08:26'),
(9, '2026-04-08', 3, 3, 1, 'hr', 'draft', NULL, NULL, '2026-04-10 16:59:08', '2026-04-10 16:59:08'),
(10, '2026-04-11', 3, 3, 1, 'hr', 'draft', NULL, NULL, '2026-04-11 09:15:24', '2026-04-11 09:15:24'),
(11, '2026-04-11', 6, 17, 1, 'marketing', 'draft', NULL, NULL, '2026-04-11 09:16:35', '2026-04-11 09:16:35'),
(12, '2026-04-11', 2, 2, 1, 'frontoffice', 'draft', NULL, NULL, '2026-04-11 10:06:11', '2026-04-11 10:06:11');

-- --------------------------------------------------------

--
-- Table structure for table `enquiries`
--

CREATE TABLE `enquiries` (
  `id` int(11) NOT NULL,
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
  `created_ip` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enquiries`
--

INSERT INTO `enquiries` (`id`, `enquiry_date`, `enquiry_no`, `branch_id`, `name`, `dob`, `profession`, `gender`, `address`, `instagram_id`, `phone`, `email`, `course_interest`, `qualification`, `year_of_passout`, `college`, `percentage_marks`, `father_name`, `father_occupation`, `father_contact_no`, `parent_email`, `software_languages_known`, `technologies`, `interested_in`, `placements_required`, `know_about`, `know_about_other`, `candidate_signature_path`, `counselor_signature_path`, `status`, `handled_by`, `converted_registration_id`, `remarks`, `created_at`, `updated_at`, `created_by`, `updated_by`, `ip_address`, `user_agent`, `platform`, `app_version`, `device_type`, `created_ip`) VALUES
(1, '2026-03-30', 'ENQ-20260330-0188', 1, 'Suresh', '2001-01-01', 'Student', 'male', '7, Nehru street, Ramnagar coimbatore', 'nil', '7402740298', 'ats.pythondeveloper05@gmail.com', 'Data Science', 'BE in textiles', 2025, 'Sri Ramakrishna Institute of Technology', 89.00, 'Pranesh', 'Cab Driver', '1234567890', 'user550@gmail.com', 'C,C++,python', 'Artificial Intelligence,Data Science,Full Stack Web Development,Web Designing,Python,Java,PHP & MySQL,Tally,MS Office,Digital Marketing', 'Technology Training,Internship,Placement Assistance,Project Development', 1, 'Other', 'Walk-in', NULL, NULL, 'converted', 10, NULL, 'xzcvxbnm,.', '2026-03-30 07:30:35', '2026-03-30 07:31:40', 10, NULL, '2401:4900:8825:644e:cd5f:391a:3b0c:1f39', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 'web', NULL, NULL, NULL),
(2, '2026-03-30', 'ENQ-20260330-0192', 1, 'Joseph', '2001-01-01', 'Student', 'male', 'qwerweetyjkghionllsdgnklhiosdghiosrgabjkbjlsdggsdhiodgshiosgdy8gsehoetsdgbjkdgsbjkSDG', 'qwerty', '9876543210', 'joseph@gmail.com', 'Data science', 'BE', 2023, 'ATS', 78.00, 'qwertyui', 'wertyuio', '9876543210', 'parent@gmail.com', 'qwertyuiop', 'Artificial Intelligence,Python', 'Technology Training', 1, 'Walk-in', 'Walk-in', NULL, NULL, 'converted', 2, NULL, 'qwertyuiop', '2026-03-30 07:58:30', '2026-03-30 08:02:34', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(3, '2026-03-30', 'ENQ-20260330-0213', 1, 'Arjun', '2026-03-30', 'Student', 'male', 'iwdnqndNqd', 'ajrun_', '7402740298', 'ats.pythondeveloper05@gmail.com', 'Data Science', 'MCA', 2026, 'NPR', 80.00, 'Raghul', 'Developer', '0987654321', 'sakthivelpcseb@gmail.com', 'python,sql', 'Data Science', 'Internship', 0, 'Other', 'Website', NULL, NULL, 'converted', 10, NULL, NULL, '2026-03-30 08:45:04', '2026-03-30 09:22:04', 10, NULL, '2401:4900:8825:644e:84a2:27d2:1df3:b321', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(4, '2026-03-30', 'ENQ-20260330-0217', 1, 'Akash', '2001-12-01', 'Student', 'male', '7, Nehru street, Ramnagar coimbatore', 'nil', '9876543210', 'Akash@gmail.com', 'Data Analytics', 'BE in textiles', 2025, 'Sri Ramakrishna Institute of Technology', 89.00, 'Pranesh', 'Cab Driver', '1234567890', 'user550@gmail.com', 'JAVA', 'Artificial Intelligence,Data Science,Full Stack Web Development,Web Designing,Python,Java,PHP & MySQL,Tally,MS Office,Digital Marketing', 'Technology Training,Internship,Placement Assistance,Project Development', 0, 'Other', 'Instagram', NULL, NULL, 'converted', 10, NULL, 'sadsfbdg', '2026-03-30 08:50:20', '2026-03-30 08:51:11', 10, NULL, '2405:201:e015:50bd:9d77:1c6b:5172:a21d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', 'web', NULL, NULL, NULL),
(5, '2026-04-06', 'ENQ-20260406-0235', 1, 'Sridharshan', '2001-01-01', 'Student', 'male', 'qwertyuiop', 'qwertyuio', '9876543210', 'sridharshany.2001@gmail.com', 'FSWD', 'BE', 2023, 'Kongu', 78.00, 'Yogaprabhu', 'Business', '9876543210', 'sriveprus@gmail.com', 'C', 'Full Stack Web Development', 'Technology Training,Placement Assistance', 1, 'Website', 'Reference', NULL, NULL, 'converted', 2, NULL, 'qwertyuiop[', '2026-04-06 04:33:37', '2026-04-06 04:34:58', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(6, '2026-04-07', 'ENQ-20260407-0236', 1, 'Palanisamy', '2001-01-01', 'Student', 'male', 'qwertyuiop', 'qwertyui', '9876543210', 'webdeveloper005.ats@gmail.com', 'DS', 'BE', 2023, 'ABC', 89.00, 'Prabakaran', 'Business', '9876543210', 'webdeveloper05.ats@gmail.com', 'q', 'PHP & MySQL', NULL, 1, 'Friends/Reference', 'Reference', NULL, NULL, 'converted', 2, NULL, 'qwertyui;', '2026-04-07 04:01:36', '2026-04-07 04:03:13', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(7, '2026-04-09', 'ENQ-20260409-0240', 1, 'Ragul kanna', '2004-04-14', 'students', 'male', 'Fun Mall & Lakshmi Mills\r\nBus Stop, Avinashi Road\r\nBetween, Nava India Rd', NULL, '7402740298', 'ragulkanna123@gamil.com', 'data science', 'bca', 2027, 'abc college arts and science', 78.00, 'suresh kumar', 'busineess', '7777777777', 'counseloraccenttechnosoft@gmail.com', 'c c++', 'Artificial Intelligence,Web Designing,Python', 'Technology Training', 1, 'Website', NULL, NULL, NULL, 'new', 2, NULL, NULL, '2026-04-09 07:29:33', '2026-04-09 07:32:30', 2, 2, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(8, '2026-04-11', 'ENQ-20260411-0241', 1, 'Dinesh', '2001-01-01', 'BE', 'male', 'qwertyui', 'qwertyu', '9876543210', 'webdeveloper005.ats@gmail.com', 'DS', 'BE', 2021, 'ABC', 78.50, 'Suresh', 'Teacher', '9876543210', 'webdeveloper05.ats@gmail.com', 'C', 'Artificial Intelligence,Data Science,Full Stack Web Development,Web Designing,Python,Java,PHP & MySQL,Tally,MS Office,Digital Marketing', 'Technology Training,Internship,Placement Assistance,Project Development', 0, 'Website,Google Search,Instagram,Facebook,Friends/Reference,Walk-in,Other', 'Website', NULL, NULL, 'converted', 2, NULL, 'qwerty', '2026-04-11 09:32:22', '2026-04-11 09:34:53', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `enquiry_followups`
--

CREATE TABLE `enquiry_followups` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `enquiry_followups`
--

INSERT INTO `enquiry_followups` (`id`, `enquiry_id`, `branch_id`, `followup_date`, `followup_time`, `followup_type`, `status`, `notes`, `next_followup_date`, `next_followup_time`, `done_at`, `verification_status`, `verified_by`, `verified_at`, `verification_remarks`, `created_by`, `updated_by`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2026-03-30', '13:00:00', 'call', 'done', 'sadfbxcnvm.,/', '2026-03-31', '18:30:00', '2026-03-30 07:31:40', 'pending', NULL, NULL, NULL, 10, 10, '2401:4900:8825:644e:cd5f:391a:3b0c:1f39', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-30 07:31:25', '2026-03-30 07:31:40'),
(2, 2, 1, '2026-03-30', '13:31:00', 'call', 'done', 'qwertyuio', '2026-03-31', '13:31:00', '2026-03-30 08:02:33', 'pending', NULL, NULL, NULL, 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 08:01:48', '2026-03-30 08:02:33'),
(3, 3, 1, '2026-03-30', NULL, 'call', 'pending', 'hello', '2026-03-30', '19:23:00', NULL, 'pending', NULL, NULL, NULL, 10, 10, '2401:4900:8825:644e:84a2:27d2:1df3:b321', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-30 08:48:16', '2026-03-30 08:48:16'),
(4, 4, 1, '2026-03-30', NULL, 'call', 'done', 'dafsgdbfasdfbdgfd', '2026-03-30', '15:20:00', '2026-03-30 08:51:11', 'pending', NULL, NULL, NULL, 10, 10, '2405:201:e015:50bd:9d77:1c6b:5172:a21d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-30 08:50:56', '2026-03-30 08:51:11'),
(5, 3, 1, '2026-03-30', '16:30:00', 'sms', 'done', 'follow ups', '2026-04-03', '17:50:00', '2026-03-30 09:22:04', 'pending', NULL, NULL, NULL, 11, 11, '2401:4900:8825:644e:5c49:878b:2a0:8236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '2026-03-30 09:21:45', '2026-03-30 09:22:04'),
(6, 5, 1, '2026-04-06', '10:03:00', 'call', 'done', 'qwertyuio', '2026-04-07', '10:04:00', '2026-04-06 04:34:57', 'pending', NULL, NULL, NULL, 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-06 04:34:30', '2026-04-06 04:34:57'),
(7, 6, 1, '2026-04-07', '09:32:00', 'call', 'done', 'qwertyuio', '2026-04-07', '09:32:00', '2026-04-07 04:03:13', 'pending', NULL, NULL, NULL, 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-07 04:02:41', '2026-04-07 04:03:13'),
(8, 8, 1, '2026-04-11', '09:32:00', 'call', 'done', 'wertyuio', '2026-04-12', '09:33:00', '2026-04-11 09:34:53', 'pending', NULL, NULL, NULL, 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-11 09:33:24', '2026-04-11 09:34:53');

-- --------------------------------------------------------

--
-- Table structure for table `enquiry_followup_files`
--

CREATE TABLE `enquiry_followup_files` (
  `id` int(11) NOT NULL,
  `followup_id` int(11) NOT NULL,
  `enquiry_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` enum('audio','image','video','document','other') DEFAULT 'other',
  `original_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enquiry_sequence`
--

CREATE TABLE `enquiry_sequence` (
  `id` bigint(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enquiry_sequence`
--

INSERT INTO `enquiry_sequence` (`id`, `created_at`) VALUES
(2, '2026-03-17 07:07:01'),
(3, '2026-03-17 07:07:59'),
(4, '2026-03-17 07:08:54'),
(5, '2026-03-17 07:08:55'),
(6, '2026-03-17 07:10:55'),
(7, '2026-03-17 07:10:55'),
(8, '2026-03-17 07:11:07'),
(9, '2026-03-17 07:14:18'),
(10, '2026-03-17 07:14:18'),
(11, '2026-03-17 07:59:29'),
(12, '2026-03-17 12:22:30'),
(13, '2026-03-17 12:23:26'),
(14, '2026-03-17 12:26:07'),
(15, '2026-03-19 10:58:15'),
(16, '2026-03-19 10:59:07'),
(17, '2026-03-19 10:59:08'),
(18, '2026-03-19 11:25:28'),
(19, '2026-03-19 11:29:42'),
(20, '2026-03-19 11:29:42'),
(21, '2026-03-19 12:15:02'),
(22, '2026-03-19 12:17:11'),
(23, '2026-03-19 12:21:03'),
(24, '2026-03-19 12:21:03'),
(25, '2026-03-19 12:26:12'),
(26, '2026-03-20 05:38:24'),
(27, '2026-03-21 06:42:46'),
(28, '2026-03-21 06:48:24'),
(29, '2026-03-21 06:53:05'),
(30, '2026-03-21 06:54:31'),
(31, '2026-03-21 06:57:56'),
(32, '2026-03-21 07:00:12'),
(33, '2026-03-21 07:01:59'),
(34, '2026-03-21 07:03:10'),
(35, '2026-03-21 07:10:14'),
(36, '2026-03-21 07:23:10'),
(37, '2026-03-21 07:30:29'),
(38, '2026-03-21 07:40:12'),
(39, '2026-03-21 11:08:17'),
(40, '2026-03-24 06:14:30'),
(41, '2026-03-24 06:50:31'),
(42, '2026-03-24 07:03:15'),
(43, '2026-03-24 07:09:31'),
(44, '2026-03-24 07:09:58'),
(45, '2026-03-24 07:10:11'),
(46, '2026-03-24 07:10:26'),
(47, '2026-03-24 07:10:35'),
(48, '2026-03-24 07:12:55'),
(49, '2026-03-24 07:13:27'),
(50, '2026-03-24 07:31:49'),
(51, '2026-03-24 07:35:20'),
(52, '2026-03-24 07:36:07'),
(53, '2026-03-24 07:37:57'),
(54, '2026-03-24 07:39:12'),
(55, '2026-03-24 07:42:31'),
(56, '2026-03-24 07:43:56'),
(57, '2026-03-24 07:44:51'),
(58, '2026-03-24 08:53:00'),
(59, '2026-03-24 08:55:00'),
(60, '2026-03-24 08:55:56'),
(61, '2026-03-24 08:58:30'),
(62, '2026-03-24 09:06:08'),
(63, '2026-03-24 09:06:36'),
(64, '2026-03-24 09:07:32'),
(65, '2026-03-24 10:34:11'),
(66, '2026-03-24 10:34:21'),
(67, '2026-03-24 11:40:47'),
(68, '2026-03-24 11:41:42'),
(69, '2026-03-24 12:55:52'),
(70, '2026-03-25 04:28:16'),
(71, '2026-03-25 04:28:58'),
(72, '2026-03-25 04:30:27'),
(73, '2026-03-25 04:31:36'),
(74, '2026-03-25 04:32:03'),
(75, '2026-03-25 04:32:19'),
(76, '2026-03-25 04:32:31'),
(77, '2026-03-25 04:33:36'),
(78, '2026-03-25 04:34:20'),
(79, '2026-03-25 04:34:54'),
(80, '2026-03-25 04:41:30'),
(81, '2026-03-25 04:43:20'),
(82, '2026-03-25 04:44:16'),
(83, '2026-03-25 04:44:17'),
(84, '2026-03-25 05:14:06'),
(85, '2026-03-25 05:15:12'),
(86, '2026-03-25 05:16:09'),
(87, '2026-03-25 05:16:51'),
(88, '2026-03-25 05:16:52'),
(89, '2026-03-25 06:23:53'),
(90, '2026-03-25 07:36:46'),
(91, '2026-03-25 07:39:53'),
(92, '2026-03-25 07:58:24'),
(93, '2026-03-25 08:01:52'),
(94, '2026-03-25 09:15:20'),
(95, '2026-03-25 09:15:49'),
(96, '2026-03-25 09:16:28'),
(97, '2026-03-25 09:17:47'),
(98, '2026-03-25 09:19:17'),
(99, '2026-03-25 09:19:51'),
(100, '2026-03-25 09:29:54'),
(101, '2026-03-25 09:31:53'),
(102, '2026-03-25 09:32:22'),
(103, '2026-03-25 09:40:22'),
(104, '2026-03-25 10:05:05'),
(105, '2026-03-25 10:05:58'),
(106, '2026-03-25 10:05:59'),
(107, '2026-03-25 10:08:43'),
(108, '2026-03-25 10:09:28'),
(109, '2026-03-25 10:09:29'),
(110, '2026-03-26 03:38:35'),
(111, '2026-03-26 03:39:45'),
(112, '2026-03-26 03:39:46'),
(113, '2026-03-26 03:42:46'),
(114, '2026-03-26 03:43:30'),
(115, '2026-03-26 03:43:32'),
(116, '2026-03-26 05:44:09'),
(117, '2026-03-26 05:45:31'),
(118, '2026-03-26 05:46:45'),
(119, '2026-03-26 05:46:47'),
(120, '2026-03-27 06:50:22'),
(121, '2026-03-27 07:06:40'),
(122, '2026-03-27 07:11:41'),
(123, '2026-03-27 07:11:41'),
(124, '2026-03-27 07:31:00'),
(125, '2026-03-27 07:31:51'),
(126, '2026-03-27 07:31:53'),
(127, '2026-03-27 07:49:47'),
(128, '2026-03-27 07:49:47'),
(129, '2026-03-27 08:58:18'),
(130, '2026-03-27 09:04:07'),
(131, '2026-03-27 09:04:07'),
(132, '2026-03-27 09:08:20'),
(133, '2026-03-27 09:14:02'),
(134, '2026-03-27 09:14:02'),
(135, '2026-03-27 09:17:54'),
(136, '2026-03-27 09:23:07'),
(137, '2026-03-27 09:23:07'),
(138, '2026-03-27 09:28:06'),
(139, '2026-03-27 09:30:42'),
(140, '2026-03-27 09:30:42'),
(141, '2026-03-27 09:33:01'),
(142, '2026-03-27 09:37:01'),
(143, '2026-03-27 09:37:01'),
(144, '2026-03-27 09:39:17'),
(145, '2026-03-27 09:47:16'),
(146, '2026-03-27 09:47:16'),
(147, '2026-03-27 10:01:12'),
(148, '2026-03-27 10:03:57'),
(149, '2026-03-27 10:03:57'),
(150, '2026-03-27 10:08:46'),
(151, '2026-03-27 10:11:17'),
(152, '2026-03-27 10:11:17'),
(153, '2026-03-27 10:13:52'),
(154, '2026-03-27 10:17:18'),
(155, '2026-03-27 10:17:18'),
(156, '2026-03-28 03:14:58'),
(157, '2026-03-28 03:41:08'),
(158, '2026-03-28 04:01:15'),
(159, '2026-03-28 04:46:08'),
(160, '2026-03-28 05:54:49'),
(161, '2026-03-28 06:32:02'),
(162, '2026-03-28 10:35:11'),
(163, '2026-03-28 10:38:19'),
(164, '2026-03-28 10:40:36'),
(165, '2026-03-28 10:42:09'),
(166, '2026-03-28 11:18:04'),
(167, '2026-03-28 11:19:47'),
(168, '2026-03-28 11:21:13'),
(169, '2026-03-28 11:22:42'),
(170, '2026-03-28 11:22:49'),
(171, '2026-03-28 11:23:26'),
(172, '2026-03-28 11:24:41'),
(173, '2026-03-28 11:25:14'),
(174, '2026-03-28 11:26:47'),
(175, '2026-03-28 11:28:09'),
(176, '2026-03-28 11:28:58'),
(177, '2026-03-28 11:29:13'),
(178, '2026-03-30 04:33:14'),
(179, '2026-03-30 04:36:19'),
(180, '2026-03-30 04:36:20'),
(181, '2026-03-30 04:51:28'),
(182, '2026-03-30 05:04:49'),
(183, '2026-03-30 05:09:59'),
(184, '2026-03-30 05:19:14'),
(185, '2026-03-30 05:29:47'),
(186, '2026-03-30 06:14:47'),
(187, '2026-03-30 07:28:53'),
(188, '2026-03-30 07:30:35'),
(189, '2026-03-30 07:30:35'),
(190, '2026-03-30 07:52:21'),
(191, '2026-03-30 07:52:54'),
(192, '2026-03-30 07:58:29'),
(193, '2026-03-30 07:58:31'),
(194, '2026-03-30 08:05:18'),
(195, '2026-03-30 08:05:31'),
(196, '2026-03-30 08:06:51'),
(197, '2026-03-30 08:09:15'),
(198, '2026-03-30 08:11:40'),
(199, '2026-03-30 08:14:12'),
(200, '2026-03-30 08:14:34'),
(201, '2026-03-30 08:14:56'),
(202, '2026-03-30 08:15:05'),
(203, '2026-03-30 08:15:20'),
(204, '2026-03-30 08:17:56'),
(205, '2026-03-30 08:19:02'),
(206, '2026-03-30 08:21:44'),
(207, '2026-03-30 08:22:50'),
(208, '2026-03-30 08:23:13'),
(209, '2026-03-30 08:23:26'),
(210, '2026-03-30 08:23:58'),
(211, '2026-03-30 08:25:38'),
(212, '2026-03-30 08:41:46'),
(213, '2026-03-30 08:45:04'),
(214, '2026-03-30 08:45:04'),
(215, '2026-03-30 08:49:03'),
(216, '2026-03-30 08:49:38'),
(217, '2026-03-30 08:50:20'),
(218, '2026-03-30 08:50:20'),
(219, '2026-03-30 09:15:13'),
(220, '2026-04-02 05:35:40'),
(221, '2026-04-02 05:35:46'),
(222, '2026-04-02 05:37:48'),
(223, '2026-04-02 05:39:43'),
(224, '2026-04-02 05:44:15'),
(225, '2026-04-02 05:46:07'),
(226, '2026-04-02 05:46:52'),
(227, '2026-04-02 07:49:08'),
(228, '2026-04-06 03:50:00'),
(229, '2026-04-06 03:52:16'),
(230, '2026-04-06 04:02:42'),
(231, '2026-04-06 04:07:03'),
(232, '2026-04-06 04:15:27'),
(233, '2026-04-06 04:29:35'),
(234, '2026-04-06 04:31:29'),
(235, '2026-04-06 04:31:51'),
(236, '2026-04-07 03:58:49'),
(237, '2026-04-08 03:52:11'),
(238, '2026-04-08 03:56:35'),
(239, '2026-04-08 04:55:32'),
(240, '2026-04-09 07:24:51'),
(241, '2026-04-11 03:58:04');

-- --------------------------------------------------------

--
-- Table structure for table `fee_structures`
--

CREATE TABLE `fee_structures` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `total_fee` decimal(10,2) NOT NULL,
  `installment_count` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `followups`
--

CREATE TABLE `followups` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `contact_id` int(11) DEFAULT NULL,
  `college_id` int(11) DEFAULT NULL,
  `followup_date` date DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `next_followup_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `interviews`
--

CREATE TABLE `interviews` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `interview_date` date NOT NULL,
  `interview_mode` enum('Online','Offline') DEFAULT 'Offline',
  `status` enum('Scheduled','Selected','Rejected','On Hold') DEFAULT 'Scheduled',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leads`
--

CREATE TABLE `leads` (
  `id` int(11) NOT NULL,
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
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leads`
--

INSERT INTO `leads` (`id`, `branch_id`, `name`, `phone`, `email`, `source`, `course_interest`, `company_college_name`, `department`, `lead_year`, `status`, `qualified_at`, `converted_at`, `converted_by`, `assigned_to`, `import_batch_id`, `converted_enquiry_id`, `converted_registration_id`, `remarks`, `created_at`, `updated_at`, `created_by`, `updated_by`, `ip_address`, `user_agent`) VALUES
(1, 1, 'Suresh', '7402740298', 'ats.pythondeveloper05@gmail.com', 'Walk-in', 'Data Science', 'krishna college of arts and science', 'CIVIL', '2', 'converted', NULL, NULL, NULL, 10, NULL, NULL, NULL, 'dgflkjlhfsdafghnmj,', '2026-03-30 07:28:36', '2026-03-30 07:30:35', 10, 10, '2401:4900:8825:644e:cd5f:391a:3b0c:1f39', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(2, 1, 'Joseph', '9876543210', 'joseph@gmail.com', 'Walk-in', 'Data science', 'ATS', 'CS', '2026', 'converted', NULL, NULL, NULL, 2, NULL, NULL, NULL, 'qwertyuio', '2026-03-30 07:44:33', '2026-03-30 07:58:30', 10, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(3, 1, 'Arjun', '7402740298', 'ats.pythondeveloper05@gmail.com', 'Website', 'Data Science', 'krishna college of arts and science', 'CIVIL', '2', 'converted', NULL, NULL, NULL, 10, NULL, NULL, NULL, 'Please follow this student', '2026-03-30 08:33:26', '2026-03-30 08:45:04', 1, 10, '2405:201:e015:50bd:4ed:c645:2aa7:4635', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(4, 1, 'Akash', '9876543210', 'Akash@gmail.com', 'Instagram', 'Data Analytics', 'krishna college of arts and science', 'Computer Science', '1', 'converted', NULL, NULL, NULL, 10, NULL, NULL, NULL, 'asdfsghkjlhj;uioytrsfdgbnmhjgfdsadfvb', '2026-03-30 08:47:04', '2026-03-30 08:50:20', 1, 10, '2405:201:e015:50bd:9d77:1c6b:5172:a21d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(5, 1, 'Akashaya', '9876543210', 'akashaya@gmail.com', 'Walk-in', 'PHP', 'krishna college of arts and science', 'Computer Science', '3', 'new', NULL, NULL, NULL, 11, NULL, NULL, NULL, 'Please follow this student', '2026-03-30 08:55:49', '2026-03-30 08:55:49', 1, NULL, '2405:201:e015:50bd:9d77:1c6b:5172:a21d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(6, 1, 'Praveen', '9876543211', 'praveen@gmail.com', 'Reference', 'Data Science', 'krishna college of arts and science', 'BCA', '1', 'new', NULL, NULL, NULL, 11, NULL, NULL, NULL, 'Please follow this student', '2026-03-30 09:03:26', '2026-03-30 09:03:26', 1, NULL, '2401:4900:8825:644e:5c49:878b:2a0:8236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(7, 1, 'Varsha', '9876543210', 'varsha@gmail.com', 'Instagram', 'Python with BI', 'krishna college of arts and science', 'CIVIL', '3', 'new', NULL, NULL, NULL, 10, NULL, NULL, NULL, 'Follow this student', '2026-03-30 09:12:09', '2026-03-30 09:12:09', 1, NULL, '2401:4900:8825:644e:5c49:878b:2a0:8236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0'),
(8, 1, 'Sridharshan', '9876543210', 'sridharshany.2001@gmail.com', 'Reference', 'FSWD', 'Kongu', 'CHEM', '2023', 'converted', NULL, NULL, NULL, 2, NULL, NULL, NULL, 'qwertyuio', '2026-04-06 04:29:13', '2026-04-06 04:33:37', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(9, 1, 'Palanisamy', '9876543210', 'palani@gmail.com', 'Reference', 'DS', 'ABC', 'CHE', '2023', 'converted', NULL, NULL, NULL, 2, NULL, NULL, NULL, 'qwertyuiop', '2026-04-07 03:58:32', '2026-04-07 04:01:36', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(10, 1, 'pramodhini', '7402740298', 'pramodhiniganesan@gmail.com', 'Walk-in', 'data science', 'Nirmala college for women', 'bsc computer science', '2026', 'new', NULL, NULL, NULL, 10, NULL, NULL, NULL, NULL, '2026-04-09 07:02:31', '2026-04-09 07:02:31', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(11, 1, 'Laavanya', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-04-09 07:04:02', '2026-04-09 07:04:02', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(12, 1, 'Bharathi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-04-09 07:04:02', '2026-04-09 07:04:02', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(13, 1, 'Name', NULL, NULL, NULL, NULL, NULL, 'Department', NULL, 'new', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-04-09 07:04:02', '2026-04-09 07:04:02', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(14, 1, 'SubaShree', NULL, NULL, NULL, NULL, NULL, 'B.Sc(AI&DS)', NULL, 'new', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-04-09 07:04:02', '2026-04-09 07:04:02', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(15, 1, 'Name', NULL, NULL, NULL, NULL, NULL, 'Department', NULL, 'new', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-04-09 07:04:02', '2026-04-09 07:04:02', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(16, 1, 'Nathiya', NULL, NULL, NULL, NULL, NULL, 'ECE', NULL, 'new', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-04-09 07:04:02', '2026-04-09 07:04:02', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(17, 1, 'Name', NULL, NULL, NULL, NULL, NULL, 'Department', NULL, 'new', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-04-09 07:04:02', '2026-04-09 07:04:02', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(18, 1, 'Abinaya Sri', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-04-09 07:04:02', '2026-04-09 07:04:02', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(19, 1, 'Name', NULL, NULL, NULL, NULL, NULL, 'Department', NULL, 'new', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-04-09 07:04:02', '2026-04-09 07:04:02', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(20, 1, 'Priyadharshini.A', NULL, NULL, NULL, NULL, NULL, 'B.Tech(IT)', NULL, 'new', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-04-09 07:04:02', '2026-04-09 07:04:02', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(21, 1, 'Mohammed aadhil appavu .A', NULL, NULL, NULL, NULL, NULL, 'B.Tech(IT)', NULL, 'new', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-04-09 07:04:02', '2026-04-09 07:04:02', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(22, 1, 'Sivaranjini.N', NULL, NULL, NULL, NULL, NULL, 'B.Tech(IT)', NULL, 'new', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-04-09 07:04:02', '2026-04-09 07:04:02', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(23, 1, 'Name', NULL, NULL, NULL, NULL, NULL, 'Department', NULL, 'new', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-04-09 07:04:02', '2026-04-09 07:04:02', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(24, 1, 'Thivyanth Baratvaj.P', NULL, NULL, NULL, NULL, NULL, 'B.E(CSE)', NULL, 'new', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-04-09 07:04:02', '2026-04-09 07:04:02', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(25, 1, 'Laavanya', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, NULL, '2026-04-09 07:05:42', '2026-04-09 07:05:42', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(26, 1, 'Bharathi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, NULL, '2026-04-09 07:05:42', '2026-04-09 07:05:42', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(27, 1, 'Name', NULL, NULL, NULL, NULL, NULL, 'Department', NULL, 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, NULL, '2026-04-09 07:05:42', '2026-04-09 07:05:42', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(28, 1, 'SubaShree', NULL, NULL, NULL, NULL, NULL, 'B.Sc(AI&DS)', NULL, 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, NULL, '2026-04-09 07:05:42', '2026-04-09 07:05:42', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(29, 1, 'Name', NULL, NULL, NULL, NULL, NULL, 'Department', NULL, 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, NULL, '2026-04-09 07:05:42', '2026-04-09 07:05:42', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(30, 1, 'Nathiya', NULL, NULL, NULL, NULL, NULL, 'ECE', NULL, 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, NULL, '2026-04-09 07:05:42', '2026-04-09 07:05:42', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(31, 1, 'Name', NULL, NULL, NULL, NULL, NULL, 'Department', NULL, 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, NULL, '2026-04-09 07:05:42', '2026-04-09 07:05:42', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(32, 1, 'Abinaya Sri', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, NULL, '2026-04-09 07:05:42', '2026-04-09 07:05:42', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(33, 1, 'Name', NULL, NULL, NULL, NULL, NULL, 'Department', NULL, 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, NULL, '2026-04-09 07:05:42', '2026-04-09 07:05:42', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(34, 1, 'Priyadharshini.A', NULL, NULL, NULL, NULL, NULL, 'B.Tech(IT)', NULL, 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, NULL, '2026-04-09 07:05:42', '2026-04-09 07:05:42', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(35, 1, 'Mohammed aadhil appavu .A', NULL, NULL, NULL, NULL, NULL, 'B.Tech(IT)', NULL, 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, NULL, '2026-04-09 07:05:42', '2026-04-09 07:05:42', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(36, 1, 'Sivaranjini.N', NULL, NULL, NULL, NULL, NULL, 'B.Tech(IT)', NULL, 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, NULL, '2026-04-09 07:05:42', '2026-04-09 07:05:42', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(37, 1, 'Name', NULL, NULL, NULL, NULL, NULL, 'Department', NULL, 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, NULL, '2026-04-09 07:05:42', '2026-04-09 07:05:42', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(38, 1, 'Thivyanth Baratvaj.P', NULL, NULL, NULL, NULL, NULL, 'B.E(CSE)', NULL, 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, NULL, '2026-04-09 07:05:42', '2026-04-09 07:05:42', 5, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(39, 1, 'juil mary', '7402740225', 'juilmaryjhon@gmail.com', 'Instagram', 'python with data science', 'Nirmala college for women', 'bsc computer science', '2026', 'new', NULL, NULL, NULL, 10, NULL, NULL, NULL, 'I WILL CHECK AND GET BACK', '2026-04-09 07:23:04', '2026-04-09 07:23:04', 2, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(40, 1, 'Sangavi K', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 4, NULL, NULL, NULL, '2026-04-09 07:23:36', '2026-04-09 07:23:36', 2, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(41, 1, 'Saravanakumar', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 4, NULL, NULL, NULL, '2026-04-09 07:23:36', '2026-04-09 07:23:36', 2, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(42, 1, 'Sharmila', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 4, NULL, NULL, NULL, '2026-04-09 07:23:36', '2026-04-09 07:23:36', 2, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(43, 1, 'Shobika', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 4, NULL, NULL, NULL, '2026-04-09 07:23:36', '2026-04-09 07:23:36', 2, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(44, 1, 'Shreeya', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 4, NULL, NULL, NULL, '2026-04-09 07:23:36', '2026-04-09 07:23:36', 2, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(45, 1, 'Shyam', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 4, NULL, NULL, NULL, '2026-04-09 07:23:36', '2026-04-09 07:23:36', 2, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(46, 1, 'Sri Ram S', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 4, NULL, NULL, NULL, '2026-04-09 07:23:36', '2026-04-09 07:23:36', 2, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(47, 1, 'Subaha Sri', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 4, NULL, NULL, NULL, '2026-04-09 07:23:36', '2026-04-09 07:23:36', 2, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(48, 1, 'Sudharsun P', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 4, NULL, NULL, NULL, '2026-04-09 07:23:36', '2026-04-09 07:23:36', 2, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(49, 1, 'Surya Prakash S', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 4, NULL, NULL, NULL, '2026-04-09 07:23:36', '2026-04-09 07:23:36', 2, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(50, 1, 'Tharun R', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 4, NULL, NULL, NULL, '2026-04-09 07:23:36', '2026-04-09 07:23:36', 2, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(51, 1, 'Vaitheeshwaran', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 4, NULL, NULL, NULL, '2026-04-09 07:23:36', '2026-04-09 07:23:36', 2, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(52, 1, 'Vishmitha', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 4, NULL, NULL, NULL, '2026-04-09 07:23:36', '2026-04-09 07:23:36', 2, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(53, 1, 'Deepika', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 4, NULL, NULL, NULL, '2026-04-09 07:23:36', '2026-04-09 07:23:36', 2, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(54, 1, 'GuruDev', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 4, NULL, NULL, NULL, '2026-04-09 07:23:36', '2026-04-09 07:23:36', 2, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(55, 1, 'Kavyaa', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 4, NULL, NULL, NULL, '2026-04-09 07:23:36', '2026-04-09 07:23:36', 2, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(56, 1, 'Rokith', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 4, NULL, NULL, NULL, '2026-04-09 07:23:36', '2026-04-09 07:23:36', 2, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(57, 1, 'Sarvesh B', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'new', NULL, NULL, NULL, NULL, 4, NULL, NULL, NULL, '2026-04-09 07:23:36', '2026-04-09 07:23:36', 2, NULL, '2401:4900:8826:dbf2:d57b:7138:1670:e9c8', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(58, 1, 'Dinesh', '9876543210', 'webdeveloper005.ats@gmail.com', 'Website', 'DS', 'ABC', 'BE', '2021', 'converted', NULL, NULL, NULL, 2, NULL, NULL, NULL, 'qwerty', '2026-04-11 09:26:09', '2026-04-11 09:32:23', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');

-- --------------------------------------------------------

--
-- Table structure for table `lead_import_batches`
--

CREATE TABLE `lead_import_batches` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `total_rows` int(11) NOT NULL DEFAULT 0,
  `success_rows` int(11) NOT NULL DEFAULT 0,
  `failed_rows` int(11) NOT NULL DEFAULT 0,
  `status` enum('completed','partial','failed') NOT NULL DEFAULT 'completed',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lead_import_batches`
--

INSERT INTO `lead_import_batches` (`id`, `branch_id`, `created_by`, `file_name`, `total_rows`, `success_rows`, `failed_rows`, `status`, `created_at`) VALUES
(1, 1, 5, 'leads_20260409_123329_8163.xlsx', 54, 0, 54, 'failed', '2026-04-09 07:03:29'),
(2, 1, 5, 'leads_20260409_123402_7127.xlsx', 18, 14, 4, 'partial', '2026-04-09 07:04:02'),
(3, 1, 5, 'leads_20260409_123542_8658.xlsx', 18, 14, 4, 'partial', '2026-04-09 07:05:42'),
(4, 1, 2, 'leads_20260409_125335_5329.xlsx', 18, 18, 0, 'completed', '2026-04-09 07:23:35');

-- --------------------------------------------------------

--
-- Table structure for table `marketing_amount`
--

CREATE TABLE `marketing_amount` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `date` date DEFAULT NULL,
  `college` varchar(255) DEFAULT NULL,
  `dept_name` varchar(150) DEFAULT NULL,
  `particulars` text DEFAULT NULL,
  `bank` decimal(10,2) DEFAULT NULL,
  `cash` decimal(10,2) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `menus`
--

CREATE TABLE `menus` (
  `id` int(11) NOT NULL,
  `menu_name` varchar(150) NOT NULL,
  `menu_slug` varchar(150) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menus`
--

INSERT INTO `menus` (`id`, `menu_name`, `menu_slug`, `parent_id`, `icon`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Menu Management', 'menu_management', NULL, 'fas fa-bars', 3, 1, '2026-02-25 10:45:15', '2026-02-26 13:45:42'),
(5, 'User Management', 'user_management', NULL, 'fas fa-users-cog', 5, 1, '2026-03-09 13:58:33', '2026-03-09 13:58:33'),
(6, 'Add User', 'user_add', 5, 'fas fa-users', 1, 1, '2026-02-26 11:25:51', '2026-02-26 11:25:51'),
(7, 'Role Management', 'role_management', NULL, 'fas fa-user-shield', 2, 1, '2026-02-26 11:54:10', '2026-02-26 15:47:23'),
(10, 'Branch Management', 'branch_management', NULL, 'fas fa-code-branch', 1, 1, '2026-02-26 13:03:48', '2026-02-26 13:46:04'),
(11, 'Permission Management', 'permission_management', NULL, 'fas fa-key', 4, 1, '2026-02-26 13:05:42', '2026-02-26 15:47:51'),
(12, 'Lead', 'lead', NULL, 'fas fa-user-plus', 6, 1, '2026-02-26 13:29:10', '2026-02-26 15:48:34'),
(13, 'Enquiries', 'enquiries', NULL, 'fas fa-question-circle', 7, 1, '2026-02-26 13:30:54', '2026-02-26 13:51:48'),
(14, 'Registrations', 'registrations', NULL, 'fas fa-user-check', 8, 1, '2026-02-26 13:33:00', '2026-02-26 15:48:59'),
(15, 'Programs', 'programs', NULL, 'fas fa-graduation-cap', 9, 0, '2026-02-26 13:33:56', '2026-03-11 08:25:31'),
(16, 'Students', 'students', NULL, 'fas fa-user-graduate', 10, 1, '2026-02-26 13:35:03', '2026-02-26 13:52:16'),
(17, 'Payments', 'payments/index', NULL, 'fas fa-rupee-sign', 11, 1, '2026-02-26 13:36:05', '2026-03-13 07:01:16'),
(18, 'Staff Panel', 'staffpanel', NULL, 'fas fa-chalkboard-teacher', 12, 1, '2026-02-26 13:37:16', '2026-02-26 15:49:59'),
(19, 'HR Panel', 'hr_panel', NULL, 'fas fa-briefcase', 13, 1, '2026-02-26 13:38:11', '2026-02-26 15:50:14'),
(20, 'Reports', 'reports', NULL, 'fas fa-chart-line', 14, 1, '2026-02-26 13:39:05', '2026-02-26 15:50:25'),
(22, 'Student Allocation', 'student_allocation', 18, 'fas fa-user-tag', 1, 1, '2026-02-26 14:00:47', '2026-02-26 14:00:47'),
(23, 'Attendance', 'attendance', 18, 'fas fa-calendar-check', 2, 1, '2026-02-26 14:01:39', '2026-02-26 14:01:39'),
(25, 'Assessment', 'assessment', 18, 'fas fa-tasks', 4, 1, '2026-02-26 14:02:35', '2026-02-26 14:02:35'),
(26, 'Internship Report', 'internship_report', 18, 'fas fa-file-alt', 5, 1, '2026-02-26 14:02:58', '2026-02-26 14:02:58'),
(27, 'Certificate Status', 'certificate_status', 18, 'fas fa-certificate', 6, 1, '2026-02-26 14:03:20', '2026-02-26 14:03:20'),
(32, 'Dashboard', 'dashboard/frontoffice', NULL, 'fas fa-home', 1, 1, '2026-02-27 10:25:41', '2026-02-27 10:25:41'),
(33, 'Dashboard', 'dashboard/hr', NULL, 'fas fa-home', 1, 1, '2026-02-27 10:26:33', '2026-02-27 10:26:33'),
(34, 'Dashboard', 'dashboard/staff', NULL, 'fas fa-home', 1, 1, '2026-02-27 10:27:04', '2026-02-27 10:27:04'),
(36, 'Dashboard', 'dashboard/marketing', 35, 'fas fa-home', 1, 1, '2026-02-27 11:44:41', '2026-02-27 11:44:41'),
(38, 'Dashboard', 'dashboard/test', NULL, 'fas fa-home', 1, 1, '2026-02-27 13:20:11', '2026-02-27 13:20:11'),
(39, 'Add Enquiry', 'enquiries/add', 13, 'fas fa-plus-circle', 1, 1, '2026-02-27 13:52:12', '2026-02-27 13:52:12'),
(40, 'Enquiry List', 'enquiries/list', 13, 'fas fa-list', 2, 1, '2026-02-27 13:52:55', '2026-02-27 13:52:55'),
(41, 'Follow-ups', 'enquiries/followups', 13, 'fas fa-phone-alt', 3, 1, '2026-02-27 13:53:35', '2026-02-27 13:53:35'),
(42, 'Add Registration', 'registrations/add', 14, 'fas fa-plus-circle', 1, 1, '2026-03-04 11:24:02', '2026-03-04 11:24:02'),
(43, 'Registrations List', 'registrations/list', 14, 'fas fa-list', 2, 1, '2026-03-04 11:24:02', '2026-03-04 11:24:02'),
(44, 'Draft Conversions', 'registrations/drafts', 14, 'fas fa-file-alt', 3, 1, '2026-03-04 11:24:02', '2026-03-04 11:24:02'),
(45, 'Convert Registration', 'registrations/convert', 14, 'fas fa-random', 99, 1, '2026-03-04 11:51:13', '2026-03-04 11:51:13'),
(46, 'Add Lead', 'leads/add', 12, 'fas fa-user-plus', 1, 1, '2026-03-07 13:45:16', '2026-03-07 13:45:16'),
(47, 'Lead List', 'leads/list', 12, 'fas fa-list', 2, 1, '2026-03-07 13:45:25', '2026-03-07 13:45:25'),
(48, 'Targets', 'targets', NULL, 'fas fa-bullseye', 16, 1, '2026-03-07 15:47:17', '2026-03-07 15:47:17'),
(49, 'Setup Targets', 'targets/setup', 48, 'fas fa-cog', 1, 1, '2026-03-07 15:48:57', '2026-03-07 15:48:57'),
(50, 'Target List', 'targets/list', 48, 'fas fa-list', 2, 1, '2026-03-07 15:50:10', '2026-03-07 15:50:10'),
(51, 'My Target', 'targets/my-target', 48, 'fas fa-user-check', 3, 1, '2026-03-07 15:50:46', '2026-03-07 15:50:46'),
(52, 'Target Report', 'targets/report', 48, 'fas fa-chart-line', 4, 1, '2026-03-07 15:51:19', '2026-03-07 15:51:19'),
(55, 'Excel Upload', 'leads/import', 12, 'fas fa-file-excel', 3, 1, '2026-03-10 11:20:41', '2026-03-10 11:20:41'),
(56, 'Assign Students', 'students/assign', 16, 'fas fa-user-tag', 1, 1, '2026-03-11 06:39:55', '2026-03-11 06:39:55'),
(57, 'Intern Students', 'students/internships', 16, 'fas fa-user-clock', 1, 1, '2026-03-11 08:16:32', '2026-03-11 08:16:32'),
(58, 'Mock Interview', 'mock_interview', 18, 'fas fa-user-tie', 5, 1, '2026-03-16 03:46:53', '2026-03-16 04:04:57'),
(59, 'Internship Report', 'reports/internship', 20, 'fas fa-user-graduate', 1, 1, '2026-03-16 06:41:57', '2026-03-16 06:41:57'),
(60, 'Course Report', 'reports/course', 20, 'fas fa-book', 2, 1, '2026-03-16 06:44:00', '2026-03-16 06:44:00'),
(62, 'Interview Students', 'interviews/students', 28, 'fas fa-user-check', 1, 0, '2026-03-16 11:51:04', '2026-03-19 04:42:00'),
(63, 'Course Completed Students', 'interviews/schedule', 19, 'fas fa-calendar-alt', 1, 1, '2026-03-16 11:51:04', '2026-03-19 04:53:42'),
(65, 'Interview Status', 'interviews/placement', 19, 'fas fa-briefcase', 2, 1, '2026-03-19 04:12:44', '2026-03-19 04:55:16'),
(69, 'Student Report', 'reports/student_schedule', 20, 'fas fa-calendar-day', 1, 1, '2026-03-19 11:06:50', '2026-03-26 12:46:24'),
(70, 'Student Overall', 'reports/student_overall', 20, 'fas fa-clipboard', 1, 1, '2026-03-19 11:10:20', '2026-03-19 11:10:20'),
(71, 'Dashboard test 1', 'dashboard/superadmin', NULL, 'fas fa-chart-line', 1, 1, '2026-03-24 06:47:33', '2026-03-24 06:47:33'),
(73, 'Daily Report Test1', 'reports/daily', 20, 'fas fa-calendar-day', 1, 1, '2026-03-24 06:58:34', '2026-03-24 06:58:34'),
(74, 'Monthly Report', 'reports/monthly', 20, 'fas fa-calendar-alt', 2, 1, '2026-03-24 06:59:08', '2026-03-24 06:59:08'),
(77, 'Course Students', 'students/course', 16, 'fas fa-clipboard', 3, 1, '2026-03-31 08:06:21', '2026-03-31 08:06:21'),
(78, 'Daily Reports', 'dailyreports/entry', 20, 'fas fa-calendar-check', 3, 1, '2026-04-08 07:59:07', '2026-04-08 08:01:29'),
(79, 'Daily Report View', 'dailyreports/view', 20, 'fas fa-list', 4, 1, '2026-04-08 08:02:39', '2026-04-08 08:02:39'),
(80, 'Export Report', 'dailyreports/export', 20, 'fas fa-file-export', 5, 1, '2026-04-08 08:04:46', '2026-04-08 08:04:46');

-- --------------------------------------------------------

--
-- Table structure for table `mock_interviews`
--

CREATE TABLE `mock_interviews` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mock_interviews`
--

INSERT INTO `mock_interviews` (`id`, `registration_id`, `branch_id`, `staff_user_id`, `theoretical_marks`, `machine_task_marks`, `mock_average`, `workflow_status`, `completed_at`, `completed_by`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 8, 78.00, 78.00, 78.00, 'sent_to_hr', '2026-03-31 07:52:48', 8, '2026-03-31 05:14:51', '2026-03-31 07:53:07'),
(2, 5, 1, 8, 90.00, 90.00, 90.00, 'done', '2026-04-08 04:58:09', 8, '2026-04-06 05:08:54', '2026-04-08 04:58:09');

-- --------------------------------------------------------

--
-- Table structure for table `monthly_targets`
--

CREATE TABLE `monthly_targets` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `monthly_targets`
--

INSERT INTO `monthly_targets` (`id`, `branch_id`, `user_id`, `role_id`, `target_year`, `target_month`, `target_amount`, `incentive_percent`, `remarks`, `status`, `assigned_by`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 2, 2026, 4, 100000.00, 10.00, 'qwertyui', 'active', 3, '2026-04-08 05:49:55', '2026-04-08 05:49:55');

-- --------------------------------------------------------

--
-- Table structure for table `monthly_target_results`
--

CREATE TABLE `monthly_target_results` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','UPI','Card','Bank Transfer') NOT NULL,
  `transaction_id` varchar(150) DEFAULT NULL,
  `payment_status` enum('Pending','Paid','Failed') DEFAULT 'Pending',
  `payment_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `placements`
--

CREATE TABLE `placements` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `job_role` varchar(150) NOT NULL,
  `package` varchar(100) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  `placement_status` enum('Offered','Joined','Declined') DEFAULT 'Offered',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `placement_interviews`
--

CREATE TABLE `placement_interviews` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `placement_interviews`
--

INSERT INTO `placement_interviews` (`id`, `registration_id`, `branch_id`, `hr_workflow_id`, `company_name`, `interview_date`, `interview_time`, `interview_mode`, `status`, `remarks`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 1, 'Mechpro', '2026-03-31', '13:27:00', 'Offline', 'rejected', 'qwertyuiop[', 3, 3, '2026-03-31 07:57:32', '2026-03-31 07:57:32'),
(2, 2, 1, 1, 'Nettel', '2026-04-01', '13:28:00', 'Online', 'selected', 'qwertyuio', 3, 3, '2026-03-31 07:58:12', '2026-03-31 07:58:12'),
(3, 2, 1, 1, 'ATS', '2026-04-07', '09:53:00', 'Online', 'scheduled', 'qwertyuiop', 3, 3, '2026-04-06 04:24:08', '2026-04-06 04:24:08');

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registrations`
--

INSERT INTO `registrations` (`id`, `registration_no`, `enquiry_id`, `branch_id`, `reg_type`, `source_type`, `joined_on`, `enquiry_snapshot_name`, `enquiry_snapshot_phone`, `enquiry_snapshot_email`, `program_name`, `batch_name`, `internship_days`, `internship_batch`, `internship_start_date`, `internship_end_date`, `internship_completion_status`, `internship_certificate_status`, `internship_certificate_issued_at`, `internship_report_status`, `internship_report_issued_at`, `internship_report_due_days`, `total_fee`, `discount_amount`, `final_fee`, `paid_amount`, `balance_amount`, `payment_status`, `notes`, `registration_status`, `assigned_to`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'REG-202603-0001', 1, 1, 'internship', 'walkin', '2026-03-30', 'Suresh', '7402740298', 'ats.pythondeveloper05@gmail.com', 'Data Science', 'morning', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 15000.00, 0.00, 15000.00, 0.00, 15000.00, 'unpaid', 'sadfg', 'active', 10, 10, '2026-03-30 07:31:40', '2026-03-30 07:33:36'),
(2, 'REG-202603-0002', 2, 1, 'course', 'direct', '2026-03-30', 'Joseph', '9876543210', 'joseph@gmail.com', 'Data science', 'Morning', NULL, NULL, NULL, NULL, 'completed', 'given', '2026-04-01 09:21:53', 'not_provided', NULL, NULL, 5000.00, 0.00, 5000.00, 5000.00, 0.00, 'paid', NULL, 'completed', 2, 2, '2026-03-30 08:02:36', '2026-04-01 03:51:55'),
(3, 'REG-202603-0003', 4, 1, 'course', 'direct', '2026-03-30', 'Akash', '9876543210', 'Akash@gmail.com', 'Data Analytics', 'morning', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 5000.00, 0.00, 5000.00, 0.00, 5000.00, 'unpaid', 'sadfd', 'active', 10, 10, '2026-03-30 08:51:11', '2026-03-30 08:51:55'),
(4, 'REG-202603-0004', 3, 1, 'course', 'direct', '2026-03-30', 'Arjun', '7402740298', 'ats.pythondeveloper05@gmail.com', 'Data Science', 'Evening', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 25000.00, 0.00, 25000.00, 0.00, 25000.00, 'unpaid', 'trxcfghbjlkm,;.', 'active', 10, 11, '2026-03-30 09:22:04', '2026-03-30 09:22:48'),
(5, 'REG-202604-0001', 5, 1, 'course', 'direct', '2026-04-06', 'Sridharshan', '9876543210', 'sridharshany.2001@gmail.com', 'FSWD', 'Morning', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 12000.00, 0.00, 12000.00, 12000.00, 0.00, 'paid', 'qwertyuiop', 'active', 2, 2, '2026-04-06 04:35:00', '2026-04-08 04:59:15'),
(6, 'REG-202604-0002', 6, 1, 'internship', 'direct', '2026-04-07', 'Palanisamy', '9876543210', 'webdeveloper005.ats@gmail.com', 'DS', 'Morning', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 1500.00, 0.00, 1500.00, 420.00, 1080.00, 'partial', 'qwert', 'active', 2, 2, '2026-04-07 04:03:16', '2026-04-10 03:58:46'),
(7, 'REG-202604-0003', NULL, 1, 'course', 'direct', '2025-08-19', 'Harsha Vardhini', '8438544320', 'harshavardhini7716@gmail.com', 'Ui/Ux Designing', '2025', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 22500.00, 0.00, 22500.00, 0.00, 22500.00, 'unpaid', 'Registration for course ui/ux designing 6months', 'active', 11, 2, '2026-04-09 06:54:17', '2026-04-09 06:54:17'),
(8, 'REG-202604-0004', 8, 1, 'internship', 'direct', '2026-04-11', 'Dinesh', '9876543210', 'webdeveloper005.ats@gmail.com', 'DS', NULL, NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid', NULL, 'active', 2, 2, '2026-04-11 09:34:56', '2026-04-11 09:34:56');

-- --------------------------------------------------------

--
-- Table structure for table `registration_courses`
--

CREATE TABLE `registration_courses` (
  `id` int(11) NOT NULL,
  `registration_id` int(11) NOT NULL,
  `guide_staff_id` int(11) DEFAULT NULL,
  `assigned_by` int(11) DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `course_status` enum('draft','active','completed','cancelled') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registration_courses`
--

INSERT INTO `registration_courses` (`id`, `registration_id`, `guide_staff_id`, `assigned_by`, `assigned_at`, `course_status`, `created_at`, `updated_at`) VALUES
(1, 2, 8, 2, '2026-03-30 08:08:09', 'active', '2026-03-30 08:08:09', '2026-03-30 08:08:09'),
(2, 3, 12, 10, '2026-03-30 08:52:47', 'active', '2026-03-30 08:52:27', '2026-03-30 08:52:47'),
(4, 5, 8, 2, '2026-04-06 04:56:26', 'active', '2026-04-06 04:56:26', '2026-04-06 04:56:26');

-- --------------------------------------------------------

--
-- Table structure for table `registration_internships`
--

CREATE TABLE `registration_internships` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registration_internships`
--

INSERT INTO `registration_internships` (`id`, `registration_id`, `guide_staff_id`, `assigned_by`, `assigned_at`, `internship_days`, `internship_batch`, `internship_start_date`, `internship_end_date`, `completion_status`, `certificate_status`, `certificate_issued_at`, `report_status`, `report_issued_at`, `report_due_days`, `created_at`, `updated_at`) VALUES
(1, 1, 8, 10, '2026-03-30 07:43:13', 21, 'Morning', NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-30 07:43:13', '2026-03-30 07:43:13');

-- --------------------------------------------------------

--
-- Table structure for table `registration_payments`
--

CREATE TABLE `registration_payments` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registration_payments`
--

INSERT INTO `registration_payments` (`id`, `registration_id`, `branch_id`, `staff_id`, `collected_by`, `approved_by`, `amount`, `payment_date`, `payment_mode`, `payment_type`, `reference_no`, `receipt_no`, `approval_status`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 2, 2, 2, 2000.00, '2026-03-30', 'cash', 'partial', 'qwertyuio', 'RCPT-202603-0001', 'approved', 'qwertyuiop', '2026-03-30 09:55:21', '2026-03-30 09:55:21'),
(2, 2, 1, 2, 2, 2, 1500.00, '2026-03-30', 'cash', 'partial', '11234567890', 'RCPT-202603-0002', 'approved', 'wedwe', '2026-03-30 11:59:28', '2026-03-30 11:59:28'),
(3, 2, 1, 2, 2, 2, 100.00, '2026-03-30', 'cash', 'partial', 'wertyuio', 'RCPT-202603-0003', 'approved', 'wertyuio', '2026-03-30 11:59:48', '2026-03-30 11:59:48'),
(4, 2, 1, 2, 2, 2, 1400.00, '2026-03-31', 'cash', 'full', 'REF-20260331-0002-104839469', 'RCPT-202603-0004', 'approved', 'qwertyuio', '2026-03-31 05:18:57', '2026-03-31 05:18:57'),
(5, 5, 1, 2, 2, 2, 100.00, '2026-04-06', 'cash', 'partial', 'REF-20260406-0005-105944352', 'RCPT-202604-0001', 'approved', NULL, '2026-04-06 05:30:02', '2026-04-06 05:30:02'),
(6, 5, 1, 2, 2, 2, 200.00, '2026-04-06', 'cash', 'partial', 'REF-20260406-0005-140653812', 'RCPT-202604-0002', 'approved', NULL, '2026-04-06 08:37:04', '2026-04-06 08:37:04'),
(7, 5, 1, 2, 2, 2, 100.00, '2026-04-06', 'cash', 'partial', 'REF-20260406-0005-150359667', 'RCPT-202604-0003', 'approved', NULL, '2026-04-06 09:34:12', '2026-04-06 09:34:12'),
(8, 5, 1, 2, 2, 2, 100.00, '2026-04-06', 'cash', 'partial', 'REF-20260406-0005-151746895', 'RCPT-202604-0004', 'approved', NULL, '2026-04-06 09:47:58', '2026-04-06 09:47:58'),
(9, 5, 1, 2, 2, 2, 100.00, '2026-04-06', 'cash', 'partial', 'REF-20260406-0005-170849609', 'RCPT-202604-0005', 'approved', NULL, '2026-04-06 11:39:02', '2026-04-06 11:39:02'),
(10, 5, 1, 2, 2, 2, 100.00, '2026-04-06', 'cash', 'partial', 'REF-20260406-0005-172157185', 'RCPT-202604-0006', 'approved', NULL, '2026-04-06 11:52:13', '2026-04-06 11:52:13'),
(11, 5, 1, 2, 2, 2, 11300.00, '2026-04-08', 'cash', 'full', 'REF-20260408-0005-102850851', 'RCPT-202604-0007', 'approved', 'qwertyu', '2026-04-08 04:59:13', '2026-04-08 04:59:13'),
(12, 6, 1, 2, 2, 2, 100.00, '2026-04-08', 'cash', 'partial', 'REF-20260408-0006-105120284', 'RCPT-202604-0008', 'approved', NULL, '2026-04-08 05:21:32', '2026-04-08 05:21:32'),
(13, 6, 1, 2, 2, 2, 100.00, '2026-04-08', 'cash', 'partial', 'REF-20260408-0006-110207690', 'RCPT-202604-0009', 'approved', NULL, '2026-04-08 05:33:49', '2026-04-08 05:33:49'),
(14, 6, 1, 2, 2, 2, 1.00, '2026-04-08', 'cash', 'partial', 'REF-20260408-0006-111348739', 'RCPT-202604-0010', 'approved', NULL, '2026-04-08 05:44:02', '2026-04-08 05:44:02'),
(15, 6, 1, 2, 2, 2, 9.00, '2026-04-08', 'cash', 'partial', 'REF-20260408-0006-113313160', 'RCPT-202604-0011', 'approved', NULL, '2026-04-08 06:03:27', '2026-04-08 06:03:27'),
(16, 6, 1, 2, 2, 2, 100.00, '2026-04-08', 'cash', 'partial', 'REF-20260408-0006-113709596', 'RCPT-202604-0012', 'approved', NULL, '2026-04-08 06:07:38', '2026-04-08 06:07:38'),
(17, 6, 1, 2, 2, 2, 100.00, '2026-04-10', 'cash', 'partial', 'REF-20260410-0006-092222836', 'RCPT-202604-0013', 'approved', NULL, '2026-04-10 03:52:33', '2026-04-10 03:52:33'),
(18, 6, 1, 2, 2, 2, 10.00, '2026-04-10', 'cash', 'partial', 'REF-20260410-0006-092832636', 'RCPT-202604-0014', 'approved', NULL, '2026-04-10 03:58:44', '2026-04-10 03:58:44');

-- --------------------------------------------------------

--
-- Table structure for table `registration_profiles`
--

CREATE TABLE `registration_profiles` (
  `id` int(11) NOT NULL,
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
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registration_profiles`
--

INSERT INTO `registration_profiles` (`id`, `registration_id`, `student_name`, `gender`, `dob`, `address`, `qualification`, `college_name`, `year_of_passout`, `parent_name`, `parent_phone`, `parent_occupation`, `parent_email`, `emergency_contact`, `aadhaar_no`, `photo_path`, `signature_path`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, 'Suresh', 'male', '2001-01-01', '7, Nehru street, Ramnagar coimbatore', 'BE in textiles', 'Sri Ramakrishna Institute of Technology', '2025', 'Pranesh', '1234567890', 'Cab Driver', 'user550@gmail.com', '7418526303', '147852963012', NULL, NULL, 'sdfagh', '2026-03-30 07:33:06', '2026-03-30 07:33:36'),
(2, 2, 'Joseph', 'male', '2001-01-01', 'qwerweetyjkghionllsdgnklhiosdghiosrgabjkbjlsdggsdhiodgshiosgdy8gsehoetsdgbjkdgsbjkSDG', 'BE', 'ATS', '2023', 'qwertyui', '9876543210', 'wertyuio', 'parent@gmail.com', '9876543210', '959595959595', NULL, NULL, NULL, '2026-03-30 08:04:45', '2026-03-30 08:04:45'),
(3, 3, 'Akash', 'male', '2001-12-01', '7, Nehru street, Ramnagar coimbatore', 'BE in textiles', 'Sri Ramakrishna Institute of Technology', '2025', 'Pranesh', '1234567890', 'Cab Driver', 'user550@gmail.com', '7418526303', '147852963012', NULL, NULL, 'sdafs', '2026-03-30 08:51:55', '2026-03-30 08:51:55'),
(4, 4, 'Arjun', 'male', '2026-03-30', 'iwdnqndNqd', 'MCA', 'NPR', '2026', 'Raghul', '0987654321', 'Developer', 'sakthivelpcseb@gmail.com', '6556846986', '685436565653', NULL, NULL, 'zexdtfcygvubhijnmk,l', '2026-03-30 09:22:48', '2026-03-30 09:22:48'),
(5, 5, 'Sridharshan', 'male', '2001-01-01', 'qwertyuiop', 'BE', 'Kongu', '2023', 'Yogaprabhu', '9876543210', 'Business', 'sriveprus@gmail.com', '9876543210', '989898989898', NULL, NULL, 'qwertyuio', '2026-04-06 04:36:51', '2026-04-06 04:36:51'),
(6, 6, 'Palanisamy', 'male', '2001-01-01', 'qwertyuiop', 'BE', 'ABC', '2023', 'Prabakaran', '9876543210', 'Business', 'webdeveloper05.ats@gmail.com', '9876543210', '959595959595', NULL, NULL, 'qwertyu', '2026-04-07 04:05:43', '2026-04-07 04:05:43'),
(7, 7, 'Harsha Vardhini', 'female', '2004-06-21', '70e mudakiyar street, kuruchi, sudrapuram, coimbatore - 641024', 'B.Sc Computer Science', 'Angappa College Of Arts & Science', '2025', 'Jeevanantham', '9159244320', 'Driver', 'HARSHAVARDHINI7716@GMAIL.COM', '9159244320', '234567896543', NULL, NULL, NULL, '2026-04-09 06:54:17', '2026-04-09 06:54:17');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `default_dashboard_slug` varchar(150) DEFAULT NULL,
  `can_access_all_branches` tinyint(1) DEFAULT 0,
  `is_target_applicable` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `default_dashboard_slug`, `can_access_all_branches`, `is_target_applicable`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'dashboard/superadmin', 1, 0, 1, '2026-02-24 14:43:56', '2026-03-24 06:59:00'),
(2, 'Front Office', 'dashboard/frontoffice', 0, 1, 1, '2026-02-26 11:54:51', '2026-03-24 06:38:40'),
(3, 'HR', 'dashboard/hr', 0, 1, 1, '2026-02-26 11:55:14', '2026-03-07 16:14:59'),
(4, 'Staff', 'dashboard/staff', 0, 1, 1, '2026-02-26 11:55:20', '2026-03-07 16:14:54'),
(6, 'Marketing', 'dashboard/marketing', 0, 1, 1, '2026-02-27 11:35:47', '2026-03-07 16:14:46'),
(7, 'Test', 'dashboard/test', 0, 0, 1, '2026-02-27 13:11:38', '2026-03-09 18:05:34'),
(8, 'Corporate', 'dashboad/test', 0, 0, 1, '2026-03-11 12:17:50', '2026-03-11 12:17:50');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_add` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `can_export` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role_id`, `menu_id`, `can_view`, `can_add`, `can_edit`, `can_delete`, `created_at`, `updated_at`, `can_export`) VALUES
(251, 7, 10, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(252, 7, 32, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(253, 7, 33, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(254, 7, 34, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(255, 7, 38, 1, 1, 1, 1, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(256, 7, 7, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(257, 7, 1, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(258, 7, 11, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(260, 7, 12, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(261, 7, 13, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(262, 7, 14, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(263, 7, 15, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(264, 7, 16, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(265, 7, 17, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(266, 7, 18, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(267, 7, 19, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(268, 7, 20, 1, 1, 1, 1, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(270, 7, 6, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(272, 7, 22, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(273, 7, 23, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(275, 7, 25, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(276, 7, 26, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(277, 7, 27, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(280, 7, 36, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45', 0),
(1499, 8, 10, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1500, 8, 32, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1501, 8, 33, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1502, 8, 34, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1503, 8, 38, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1504, 8, 7, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1505, 8, 1, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1506, 8, 11, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1507, 8, 5, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1508, 8, 12, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1509, 8, 13, 1, 1, 1, 1, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1510, 8, 14, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1511, 8, 16, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1512, 8, 17, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1513, 8, 18, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1514, 8, 19, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1515, 8, 20, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1517, 8, 48, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1518, 8, 6, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1519, 8, 46, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1520, 8, 47, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1521, 8, 55, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1522, 8, 39, 1, 1, 1, 1, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1523, 8, 40, 1, 1, 1, 1, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1524, 8, 41, 1, 1, 1, 1, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1525, 8, 42, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1526, 8, 43, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1527, 8, 44, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1528, 8, 45, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1529, 8, 56, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1530, 8, 57, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1531, 8, 22, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1532, 8, 23, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1534, 8, 25, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1535, 8, 26, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1536, 8, 27, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1539, 8, 36, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1541, 8, 49, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1542, 8, 50, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1543, 8, 51, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(1544, 8, 52, 0, 0, 0, 0, '2026-03-11 12:18:52', '2026-03-11 12:18:52', 0),
(2415, 4, 10, 0, 0, 0, 0, '2026-03-19 11:14:26', '2026-03-19 11:14:26', 0),
(2416, 4, 32, 0, 0, 0, 0, '2026-03-19 11:14:26', '2026-03-19 11:14:26', 0),
(2417, 4, 33, 0, 0, 0, 0, '2026-03-19 11:14:27', '2026-03-19 11:14:27', 0),
(2418, 4, 34, 1, 1, 1, 1, '2026-03-19 11:14:27', '2026-03-19 11:14:27', 0),
(2419, 4, 38, 0, 0, 0, 0, '2026-03-19 11:14:27', '2026-03-19 11:14:27', 0),
(2420, 4, 7, 0, 0, 0, 0, '2026-03-19 11:14:28', '2026-03-19 11:14:28', 0),
(2421, 4, 1, 0, 0, 0, 0, '2026-03-19 11:14:28', '2026-03-19 11:14:28', 0),
(2422, 4, 11, 0, 0, 0, 0, '2026-03-19 11:14:28', '2026-03-19 11:14:28', 0),
(2423, 4, 5, 0, 0, 0, 0, '2026-03-19 11:14:29', '2026-03-19 11:14:29', 0),
(2424, 4, 12, 0, 0, 0, 0, '2026-03-19 11:14:29', '2026-03-19 11:14:29', 0),
(2425, 4, 13, 0, 0, 0, 0, '2026-03-19 11:14:29', '2026-03-19 11:14:29', 0),
(2426, 4, 14, 0, 0, 0, 0, '2026-03-19 11:14:30', '2026-03-19 11:14:30', 0),
(2427, 4, 16, 0, 0, 0, 0, '2026-03-19 11:14:30', '2026-03-19 11:14:30', 0),
(2428, 4, 17, 0, 0, 0, 0, '2026-03-19 11:14:30', '2026-03-19 11:14:30', 0),
(2429, 4, 18, 1, 1, 1, 1, '2026-03-19 11:14:30', '2026-03-19 11:14:30', 0),
(2430, 4, 19, 0, 0, 0, 0, '2026-03-19 11:14:31', '2026-03-19 11:14:31', 0),
(2431, 4, 20, 1, 1, 1, 1, '2026-03-19 11:14:31', '2026-03-19 11:14:31', 0),
(2432, 4, 48, 0, 0, 0, 0, '2026-03-19 11:14:31', '2026-03-19 11:14:31', 0),
(2433, 4, 6, 0, 0, 0, 0, '2026-03-19 11:14:32', '2026-03-19 11:14:32', 0),
(2434, 4, 46, 0, 0, 0, 0, '2026-03-19 11:14:32', '2026-03-19 11:14:32', 0),
(2435, 4, 47, 0, 0, 0, 0, '2026-03-19 11:14:32', '2026-03-19 11:14:32', 0),
(2436, 4, 55, 0, 0, 0, 0, '2026-03-19 11:14:33', '2026-03-19 11:14:33', 0),
(2437, 4, 39, 0, 0, 0, 0, '2026-03-19 11:14:33', '2026-03-19 11:14:33', 0),
(2438, 4, 40, 0, 0, 0, 0, '2026-03-19 11:14:33', '2026-03-19 11:14:33', 0),
(2439, 4, 41, 0, 0, 0, 0, '2026-03-19 11:14:33', '2026-03-19 11:14:33', 0),
(2440, 4, 42, 0, 0, 0, 0, '2026-03-19 11:14:34', '2026-03-19 11:14:34', 0),
(2441, 4, 43, 0, 0, 0, 0, '2026-03-19 11:14:34', '2026-03-19 11:14:34', 0),
(2442, 4, 44, 0, 0, 0, 0, '2026-03-19 11:14:34', '2026-03-19 11:14:34', 0),
(2443, 4, 45, 0, 0, 0, 0, '2026-03-19 11:14:35', '2026-03-19 11:14:35', 0),
(2444, 4, 56, 0, 0, 0, 0, '2026-03-19 11:14:35', '2026-03-19 11:14:35', 0),
(2445, 4, 57, 0, 0, 0, 0, '2026-03-19 11:14:35', '2026-03-19 11:14:35', 0),
(2446, 4, 22, 1, 0, 0, 0, '2026-03-19 11:14:36', '2026-03-19 11:14:36', 0),
(2447, 4, 23, 1, 1, 0, 0, '2026-03-19 11:14:36', '2026-03-19 11:14:36', 0),
(2448, 4, 25, 1, 1, 0, 0, '2026-03-19 11:14:36', '2026-03-19 11:14:36', 0),
(2449, 4, 26, 0, 0, 0, 0, '2026-03-19 11:14:37', '2026-03-19 11:14:37', 0),
(2450, 4, 58, 1, 1, 1, 1, '2026-03-19 11:14:37', '2026-03-19 11:14:37', 0),
(2451, 4, 27, 0, 0, 0, 0, '2026-03-19 11:14:37', '2026-03-19 11:14:37', 0),
(2452, 4, 63, 0, 0, 0, 0, '2026-03-19 11:14:37', '2026-03-19 11:14:37', 0),
(2453, 4, 65, 0, 0, 0, 0, '2026-03-19 11:14:38', '2026-03-19 11:14:38', 0),
(2454, 4, 59, 0, 0, 0, 0, '2026-03-19 11:14:38', '2026-03-19 11:14:38', 0),
(2455, 4, 69, 1, 1, 1, 1, '2026-03-19 11:14:38', '2026-03-19 11:14:38', 0),
(2456, 4, 70, 0, 0, 0, 0, '2026-03-19 11:14:39', '2026-03-19 11:14:39', 0),
(2457, 4, 60, 0, 0, 0, 0, '2026-03-19 11:14:39', '2026-03-19 11:14:39', 0),
(2461, 4, 36, 0, 0, 0, 0, '2026-03-19 11:14:40', '2026-03-19 11:14:40', 0),
(2462, 4, 49, 0, 0, 0, 0, '2026-03-19 11:14:41', '2026-03-19 11:14:41', 0),
(2463, 4, 50, 0, 0, 0, 0, '2026-03-19 11:14:41', '2026-03-19 11:14:41', 0),
(2464, 4, 51, 0, 0, 0, 0, '2026-03-19 11:14:41', '2026-03-19 11:14:41', 0),
(2465, 4, 52, 0, 0, 0, 0, '2026-03-19 11:14:42', '2026-03-19 11:14:42', 0),
(2790, 1, 10, 1, 1, 1, 1, '2026-04-08 04:11:06', '2026-04-08 04:11:06', 0),
(2791, 1, 32, 1, 1, 1, 1, '2026-04-08 04:11:06', '2026-04-08 04:11:06', 0),
(2792, 1, 33, 1, 1, 1, 1, '2026-04-08 04:11:06', '2026-04-08 04:11:06', 0),
(2793, 1, 34, 1, 1, 1, 1, '2026-04-08 04:11:07', '2026-04-08 04:11:07', 0),
(2794, 1, 38, 1, 1, 1, 1, '2026-04-08 04:11:07', '2026-04-08 04:11:07', 0),
(2795, 1, 71, 1, 1, 1, 1, '2026-04-08 04:11:07', '2026-04-08 04:11:07', 0),
(2796, 1, 7, 1, 1, 1, 1, '2026-04-08 04:11:08', '2026-04-08 04:11:08', 0),
(2797, 1, 1, 1, 1, 1, 1, '2026-04-08 04:11:08', '2026-04-08 04:11:08', 0),
(2798, 1, 11, 1, 1, 1, 1, '2026-04-08 04:11:08', '2026-04-08 04:11:08', 0),
(2799, 1, 5, 1, 1, 1, 1, '2026-04-08 04:11:08', '2026-04-08 04:11:08', 0),
(2800, 1, 12, 1, 1, 1, 1, '2026-04-08 04:11:09', '2026-04-08 04:11:09', 0),
(2801, 1, 13, 1, 1, 1, 1, '2026-04-08 04:11:09', '2026-04-08 04:11:09', 0),
(2802, 1, 14, 1, 1, 1, 1, '2026-04-08 04:11:09', '2026-04-08 04:11:09', 0),
(2803, 1, 16, 1, 1, 1, 1, '2026-04-08 04:11:09', '2026-04-08 04:11:09', 0),
(2804, 1, 17, 1, 1, 1, 1, '2026-04-08 04:11:10', '2026-04-08 04:11:10', 0),
(2805, 1, 18, 1, 1, 1, 1, '2026-04-08 04:11:10', '2026-04-08 04:11:10', 0),
(2806, 1, 19, 1, 1, 1, 1, '2026-04-08 04:11:10', '2026-04-08 04:11:10', 0),
(2807, 1, 20, 1, 1, 1, 1, '2026-04-08 04:11:11', '2026-04-08 04:11:11', 0),
(2808, 1, 48, 1, 1, 1, 1, '2026-04-08 04:11:11', '2026-04-08 04:11:11', 0),
(2809, 1, 6, 1, 1, 1, 1, '2026-04-08 04:11:11', '2026-04-08 04:11:11', 0),
(2810, 1, 46, 1, 1, 1, 1, '2026-04-08 04:11:11', '2026-04-08 04:11:11', 0),
(2811, 1, 47, 1, 1, 1, 1, '2026-04-08 04:11:12', '2026-04-08 04:11:12', 0),
(2812, 1, 55, 1, 1, 1, 1, '2026-04-08 04:11:12', '2026-04-08 04:11:12', 0),
(2813, 1, 39, 1, 1, 1, 1, '2026-04-08 04:11:12', '2026-04-08 04:11:12', 0),
(2814, 1, 40, 1, 1, 1, 1, '2026-04-08 04:11:13', '2026-04-08 04:11:13', 0),
(2815, 1, 41, 1, 1, 1, 1, '2026-04-08 04:11:13', '2026-04-08 04:11:13', 0),
(2816, 1, 42, 1, 1, 1, 1, '2026-04-08 04:11:13', '2026-04-08 04:11:13', 0),
(2817, 1, 43, 1, 1, 1, 1, '2026-04-08 04:11:13', '2026-04-08 04:11:13', 0),
(2818, 1, 44, 1, 1, 1, 1, '2026-04-08 04:11:14', '2026-04-08 04:11:14', 0),
(2819, 1, 45, 1, 1, 1, 1, '2026-04-08 04:11:14', '2026-04-08 04:11:14', 0),
(2820, 1, 56, 1, 1, 1, 1, '2026-04-08 04:11:14', '2026-04-08 04:11:14', 0),
(2821, 1, 57, 1, 1, 1, 1, '2026-04-08 04:11:15', '2026-04-08 04:11:15', 0),
(2822, 1, 77, 1, 1, 1, 1, '2026-04-08 04:11:15', '2026-04-08 04:11:15', 0),
(2823, 1, 22, 1, 1, 1, 1, '2026-04-08 04:11:15', '2026-04-08 04:11:15', 0),
(2824, 1, 23, 1, 1, 1, 1, '2026-04-08 04:11:15', '2026-04-08 04:11:15', 0),
(2825, 1, 25, 1, 1, 1, 1, '2026-04-08 04:11:16', '2026-04-08 04:11:16', 0),
(2826, 1, 26, 1, 1, 1, 1, '2026-04-08 04:11:16', '2026-04-08 04:11:16', 0),
(2827, 1, 58, 1, 1, 1, 1, '2026-04-08 04:11:16', '2026-04-08 04:11:16', 0),
(2828, 1, 27, 1, 1, 1, 1, '2026-04-08 04:11:16', '2026-04-08 04:11:16', 0),
(2829, 1, 63, 1, 1, 1, 1, '2026-04-08 04:11:17', '2026-04-08 04:11:17', 0),
(2830, 1, 65, 1, 1, 1, 1, '2026-04-08 04:11:17', '2026-04-08 04:11:17', 0),
(2831, 1, 59, 1, 1, 1, 1, '2026-04-08 04:11:17', '2026-04-08 04:11:17', 0),
(2832, 1, 69, 1, 1, 1, 1, '2026-04-08 04:11:18', '2026-04-08 04:11:18', 0),
(2833, 1, 70, 0, 0, 0, 0, '2026-04-08 04:11:18', '2026-04-08 04:11:18', 0),
(2834, 1, 73, 0, 0, 0, 0, '2026-04-08 04:11:18', '2026-04-08 04:11:18', 0),
(2835, 1, 60, 1, 1, 1, 1, '2026-04-08 04:11:18', '2026-04-08 04:11:18', 0),
(2836, 1, 74, 0, 0, 0, 0, '2026-04-08 04:11:19', '2026-04-08 04:11:19', 0),
(2840, 1, 36, 0, 0, 0, 0, '2026-04-08 04:11:20', '2026-04-08 04:11:20', 0),
(2841, 1, 49, 1, 1, 1, 1, '2026-04-08 04:11:20', '2026-04-08 04:11:20', 0),
(2842, 1, 50, 1, 1, 1, 1, '2026-04-08 04:11:20', '2026-04-08 04:11:20', 0),
(2843, 1, 51, 0, 0, 0, 0, '2026-04-08 04:11:21', '2026-04-08 04:11:21', 0),
(2844, 1, 52, 1, 1, 1, 1, '2026-04-08 04:11:21', '2026-04-08 04:11:21', 0),
(2845, 1, 78, 1, 1, 1, 1, '2026-04-08 07:59:08', '2026-04-08 07:59:08', 0),
(2846, 1, 79, 1, 1, 1, 1, '2026-04-08 08:02:40', '2026-04-08 08:02:40', 0),
(2847, 1, 80, 1, 1, 1, 1, '2026-04-08 08:04:47', '2026-04-08 08:04:47', 0),
(2848, 2, 10, 0, 0, 0, 0, '2026-04-08 08:05:31', '2026-04-08 08:05:31', 0),
(2849, 2, 32, 1, 1, 1, 1, '2026-04-08 08:05:31', '2026-04-08 08:05:31', 0),
(2850, 2, 33, 0, 0, 0, 0, '2026-04-08 08:05:32', '2026-04-08 08:05:32', 0),
(2851, 2, 34, 0, 0, 0, 0, '2026-04-08 08:05:32', '2026-04-08 08:05:32', 0),
(2852, 2, 38, 0, 0, 0, 0, '2026-04-08 08:05:32', '2026-04-08 08:05:32', 0),
(2853, 2, 71, 0, 0, 0, 0, '2026-04-08 08:05:33', '2026-04-08 08:05:33', 0),
(2854, 2, 7, 0, 0, 0, 0, '2026-04-08 08:05:33', '2026-04-08 08:05:33', 0),
(2855, 2, 1, 0, 0, 0, 0, '2026-04-08 08:05:33', '2026-04-08 08:05:33', 0),
(2856, 2, 11, 0, 0, 0, 0, '2026-04-08 08:05:33', '2026-04-08 08:05:33', 0),
(2857, 2, 5, 0, 0, 0, 0, '2026-04-08 08:05:34', '2026-04-08 08:05:34', 0),
(2858, 2, 12, 1, 1, 1, 1, '2026-04-08 08:05:34', '2026-04-08 08:05:34', 0),
(2859, 2, 13, 1, 1, 1, 1, '2026-04-08 08:05:34', '2026-04-08 08:05:34', 0),
(2860, 2, 14, 1, 1, 1, 1, '2026-04-08 08:05:34', '2026-04-08 08:05:34', 0),
(2861, 2, 16, 1, 1, 1, 1, '2026-04-08 08:05:35', '2026-04-08 08:05:35', 0),
(2862, 2, 17, 1, 1, 1, 1, '2026-04-08 08:05:35', '2026-04-08 08:05:35', 0),
(2863, 2, 18, 1, 1, 1, 1, '2026-04-08 08:05:35', '2026-04-08 08:05:35', 0),
(2864, 2, 19, 0, 0, 0, 0, '2026-04-08 08:05:36', '2026-04-08 08:05:36', 0),
(2865, 2, 20, 1, 1, 1, 1, '2026-04-08 08:05:36', '2026-04-08 08:05:36', 0),
(2866, 2, 48, 1, 1, 1, 1, '2026-04-08 08:05:36', '2026-04-08 08:05:36', 0),
(2867, 2, 6, 0, 0, 0, 0, '2026-04-08 08:05:36', '2026-04-08 08:05:36', 0),
(2868, 2, 46, 1, 1, 1, 1, '2026-04-08 08:05:37', '2026-04-08 08:05:37', 0),
(2869, 2, 47, 1, 1, 1, 1, '2026-04-08 08:05:37', '2026-04-08 08:05:37', 0),
(2870, 2, 55, 1, 1, 1, 1, '2026-04-08 08:05:37', '2026-04-08 08:05:37', 0),
(2871, 2, 39, 1, 1, 1, 1, '2026-04-08 08:05:38', '2026-04-08 08:05:38', 0),
(2872, 2, 40, 1, 1, 1, 1, '2026-04-08 08:05:38', '2026-04-08 08:05:38', 0),
(2873, 2, 41, 1, 1, 1, 1, '2026-04-08 08:05:38', '2026-04-08 08:05:38', 0),
(2874, 2, 42, 1, 1, 1, 1, '2026-04-08 08:05:38', '2026-04-08 08:05:38', 0),
(2875, 2, 43, 1, 1, 1, 1, '2026-04-08 08:05:39', '2026-04-08 08:05:39', 0),
(2876, 2, 44, 1, 1, 1, 1, '2026-04-08 08:05:39', '2026-04-08 08:05:39', 0),
(2877, 2, 45, 1, 1, 1, 1, '2026-04-08 08:05:39', '2026-04-08 08:05:39', 0),
(2878, 2, 56, 1, 1, 1, 1, '2026-04-08 08:05:40', '2026-04-08 08:05:40', 0),
(2879, 2, 57, 1, 1, 1, 1, '2026-04-08 08:05:40', '2026-04-08 08:05:40', 0),
(2880, 2, 77, 0, 0, 0, 0, '2026-04-08 08:05:40', '2026-04-08 08:05:40', 0),
(2881, 2, 22, 1, 1, 1, 1, '2026-04-08 08:05:40', '2026-04-08 08:05:40', 0),
(2882, 2, 23, 1, 1, 1, 1, '2026-04-08 08:05:41', '2026-04-08 08:05:41', 0),
(2883, 2, 25, 1, 1, 1, 1, '2026-04-08 08:05:41', '2026-04-08 08:05:41', 0),
(2884, 2, 26, 0, 0, 0, 0, '2026-04-08 08:05:41', '2026-04-08 08:05:41', 0),
(2885, 2, 58, 0, 0, 0, 0, '2026-04-08 08:05:41', '2026-04-08 08:05:41', 0),
(2886, 2, 27, 0, 0, 0, 0, '2026-04-08 08:05:42', '2026-04-08 08:05:42', 0),
(2887, 2, 63, 0, 0, 0, 0, '2026-04-08 08:05:42', '2026-04-08 08:05:42', 0),
(2888, 2, 65, 0, 0, 0, 0, '2026-04-08 08:05:42', '2026-04-08 08:05:42', 0),
(2889, 2, 59, 1, 1, 1, 1, '2026-04-08 08:05:43', '2026-04-08 08:05:43', 0),
(2890, 2, 69, 0, 0, 0, 0, '2026-04-08 08:05:43', '2026-04-08 08:05:43', 0),
(2891, 2, 70, 0, 0, 0, 0, '2026-04-08 08:05:43', '2026-04-08 08:05:43', 0),
(2892, 2, 73, 0, 0, 0, 0, '2026-04-08 08:05:43', '2026-04-08 08:05:43', 0),
(2893, 2, 60, 1, 1, 1, 1, '2026-04-08 08:05:44', '2026-04-08 08:05:44', 0),
(2894, 2, 74, 0, 0, 0, 0, '2026-04-08 08:05:44', '2026-04-08 08:05:44', 0),
(2895, 2, 78, 1, 1, 1, 1, '2026-04-08 08:05:44', '2026-04-08 08:05:44', 0),
(2896, 2, 79, 1, 1, 1, 1, '2026-04-08 08:05:45', '2026-04-08 08:05:45', 0),
(2897, 2, 80, 1, 1, 1, 1, '2026-04-08 08:05:45', '2026-04-08 08:05:45', 0),
(2898, 2, 36, 0, 0, 0, 0, '2026-04-08 08:05:45', '2026-04-08 08:05:45', 0),
(2899, 2, 49, 0, 0, 0, 0, '2026-04-08 08:05:45', '2026-04-08 08:05:45', 0),
(2900, 2, 50, 0, 0, 0, 0, '2026-04-08 08:05:46', '2026-04-08 08:05:46', 0),
(2901, 2, 51, 1, 1, 1, 1, '2026-04-08 08:05:46', '2026-04-08 08:05:46', 0),
(2902, 2, 52, 0, 0, 0, 0, '2026-04-08 08:05:46', '2026-04-08 08:05:46', 0),
(2903, 3, 10, 0, 0, 0, 0, '2026-04-08 10:52:54', '2026-04-08 10:52:54', 0),
(2904, 3, 32, 0, 0, 0, 0, '2026-04-08 10:52:55', '2026-04-08 10:52:55', 0),
(2905, 3, 33, 1, 1, 1, 1, '2026-04-08 10:52:55', '2026-04-08 10:52:55', 0),
(2906, 3, 34, 0, 0, 0, 0, '2026-04-08 10:52:55', '2026-04-08 10:52:55', 0),
(2907, 3, 38, 0, 0, 0, 0, '2026-04-08 10:52:56', '2026-04-08 10:52:56', 0),
(2908, 3, 71, 0, 0, 0, 0, '2026-04-08 10:52:56', '2026-04-08 10:52:56', 0),
(2909, 3, 7, 0, 0, 0, 0, '2026-04-08 10:52:56', '2026-04-08 10:52:56', 0),
(2910, 3, 1, 0, 0, 0, 0, '2026-04-08 10:52:57', '2026-04-08 10:52:57', 0),
(2911, 3, 11, 0, 0, 0, 0, '2026-04-08 10:52:57', '2026-04-08 10:52:57', 0),
(2912, 3, 5, 0, 0, 0, 0, '2026-04-08 10:52:58', '2026-04-08 10:52:58', 0),
(2913, 3, 12, 1, 1, 1, 1, '2026-04-08 10:52:58', '2026-04-08 10:52:58', 0),
(2914, 3, 13, 1, 1, 1, 1, '2026-04-08 10:52:58', '2026-04-08 10:52:58', 0),
(2915, 3, 14, 0, 0, 0, 0, '2026-04-08 10:52:58', '2026-04-08 10:52:58', 0),
(2916, 3, 16, 1, 1, 1, 1, '2026-04-08 10:52:59', '2026-04-08 10:52:59', 0),
(2917, 3, 17, 1, 1, 1, 1, '2026-04-08 10:52:59', '2026-04-08 10:52:59', 0),
(2918, 3, 18, 1, 1, 1, 1, '2026-04-08 10:52:59', '2026-04-08 10:52:59', 0),
(2919, 3, 19, 1, 1, 1, 1, '2026-04-08 10:53:00', '2026-04-08 10:53:00', 0),
(2920, 3, 20, 1, 1, 1, 1, '2026-04-08 10:53:00', '2026-04-08 10:53:00', 0),
(2921, 3, 48, 1, 1, 1, 1, '2026-04-08 10:53:00', '2026-04-08 10:53:00', 0),
(2922, 3, 6, 0, 0, 0, 0, '2026-04-08 10:53:01', '2026-04-08 10:53:01', 0),
(2923, 3, 46, 1, 1, 1, 1, '2026-04-08 10:53:01', '2026-04-08 10:53:01', 0),
(2924, 3, 47, 1, 1, 1, 1, '2026-04-08 10:53:01', '2026-04-08 10:53:01', 0),
(2925, 3, 55, 1, 1, 1, 1, '2026-04-08 10:53:02', '2026-04-08 10:53:02', 0),
(2926, 3, 39, 0, 0, 0, 0, '2026-04-08 10:53:02', '2026-04-08 10:53:02', 0),
(2927, 3, 40, 1, 0, 0, 0, '2026-04-08 10:53:02', '2026-04-08 10:53:02', 0),
(2928, 3, 41, 1, 1, 1, 1, '2026-04-08 10:53:02', '2026-04-08 10:53:02', 0),
(2929, 3, 42, 0, 0, 0, 0, '2026-04-08 10:53:03', '2026-04-08 10:53:03', 0),
(2930, 3, 43, 0, 0, 0, 0, '2026-04-08 10:53:03', '2026-04-08 10:53:03', 0),
(2931, 3, 44, 0, 0, 0, 0, '2026-04-08 10:53:03', '2026-04-08 10:53:03', 0),
(2932, 3, 45, 0, 0, 0, 0, '2026-04-08 10:53:04', '2026-04-08 10:53:04', 0),
(2933, 3, 56, 0, 0, 0, 0, '2026-04-08 10:53:04', '2026-04-08 10:53:04', 0),
(2934, 3, 57, 0, 0, 0, 0, '2026-04-08 10:53:05', '2026-04-08 10:53:05', 0),
(2935, 3, 77, 1, 1, 1, 1, '2026-04-08 10:53:05', '2026-04-08 10:53:05', 0),
(2936, 3, 22, 0, 0, 0, 0, '2026-04-08 10:53:05', '2026-04-08 10:53:05', 0),
(2937, 3, 23, 1, 1, 1, 1, '2026-04-08 10:53:05', '2026-04-08 10:53:05', 0),
(2938, 3, 25, 0, 0, 0, 0, '2026-04-08 10:53:06', '2026-04-08 10:53:06', 0),
(2939, 3, 26, 0, 0, 0, 0, '2026-04-08 10:53:06', '2026-04-08 10:53:06', 0),
(2940, 3, 58, 0, 0, 0, 0, '2026-04-08 10:53:07', '2026-04-08 10:53:07', 0),
(2941, 3, 27, 0, 0, 0, 0, '2026-04-08 10:53:07', '2026-04-08 10:53:07', 0),
(2942, 3, 63, 1, 1, 1, 1, '2026-04-08 10:53:07', '2026-04-08 10:53:07', 0),
(2943, 3, 65, 1, 1, 1, 1, '2026-04-08 10:53:08', '2026-04-08 10:53:08', 0),
(2944, 3, 59, 1, 1, 1, 1, '2026-04-08 10:53:08', '2026-04-08 10:53:08', 0),
(2945, 3, 69, 0, 0, 0, 0, '2026-04-08 10:53:08', '2026-04-08 10:53:08', 0),
(2946, 3, 70, 1, 1, 1, 1, '2026-04-08 10:53:09', '2026-04-08 10:53:09', 0),
(2947, 3, 73, 0, 0, 0, 0, '2026-04-08 10:53:09', '2026-04-08 10:53:09', 0),
(2948, 3, 60, 1, 1, 1, 1, '2026-04-08 10:53:09', '2026-04-08 10:53:09', 0),
(2949, 3, 74, 0, 0, 0, 0, '2026-04-08 10:53:09', '2026-04-08 10:53:09', 0),
(2950, 3, 78, 1, 1, 1, 1, '2026-04-08 10:53:10', '2026-04-08 10:53:10', 0),
(2951, 3, 79, 1, 1, 1, 1, '2026-04-08 10:53:10', '2026-04-08 10:53:10', 0),
(2952, 3, 80, 1, 1, 1, 1, '2026-04-08 10:53:11', '2026-04-08 10:53:11', 0),
(2953, 3, 36, 0, 0, 0, 0, '2026-04-08 10:53:11', '2026-04-08 10:53:11', 0),
(2954, 3, 49, 1, 1, 1, 1, '2026-04-08 10:53:11', '2026-04-08 10:53:11', 0),
(2955, 3, 50, 1, 1, 1, 1, '2026-04-08 10:53:12', '2026-04-08 10:53:12', 0),
(2956, 3, 51, 1, 1, 1, 1, '2026-04-08 10:53:12', '2026-04-08 10:53:12', 0),
(2957, 3, 52, 1, 1, 1, 1, '2026-04-08 10:53:12', '2026-04-08 10:53:12', 0),
(2958, 6, 10, 0, 0, 0, 0, '2026-04-10 12:49:45', '2026-04-10 12:49:45', 0),
(2959, 6, 32, 0, 0, 0, 0, '2026-04-10 12:49:45', '2026-04-10 12:49:45', 0),
(2960, 6, 33, 0, 0, 0, 0, '2026-04-10 12:49:46', '2026-04-10 12:49:46', 0),
(2961, 6, 34, 0, 0, 0, 0, '2026-04-10 12:49:46', '2026-04-10 12:49:46', 0),
(2962, 6, 38, 0, 0, 0, 0, '2026-04-10 12:49:46', '2026-04-10 12:49:46', 0),
(2963, 6, 71, 0, 0, 0, 0, '2026-04-10 12:49:46', '2026-04-10 12:49:46', 0),
(2964, 6, 7, 0, 0, 0, 0, '2026-04-10 12:49:47', '2026-04-10 12:49:47', 0),
(2965, 6, 1, 0, 0, 0, 0, '2026-04-10 12:49:47', '2026-04-10 12:49:47', 0),
(2966, 6, 11, 0, 0, 0, 0, '2026-04-10 12:49:47', '2026-04-10 12:49:47', 0),
(2967, 6, 5, 0, 0, 0, 0, '2026-04-10 12:49:47', '2026-04-10 12:49:47', 0),
(2968, 6, 12, 1, 1, 1, 1, '2026-04-10 12:49:48', '2026-04-10 12:49:48', 0),
(2969, 6, 13, 0, 0, 0, 0, '2026-04-10 12:49:48', '2026-04-10 12:49:48', 0),
(2970, 6, 14, 1, 0, 0, 0, '2026-04-10 12:49:48', '2026-04-10 12:49:48', 0),
(2971, 6, 16, 0, 0, 0, 0, '2026-04-10 12:49:48', '2026-04-10 12:49:48', 0),
(2972, 6, 17, 0, 0, 0, 0, '2026-04-10 12:49:49', '2026-04-10 12:49:49', 0),
(2973, 6, 18, 0, 0, 0, 0, '2026-04-10 12:49:49', '2026-04-10 12:49:49', 0),
(2974, 6, 19, 0, 0, 0, 0, '2026-04-10 12:49:49', '2026-04-10 12:49:49', 0),
(2975, 6, 20, 1, 1, 1, 1, '2026-04-10 12:49:50', '2026-04-10 12:49:50', 0),
(2976, 6, 48, 1, 1, 1, 1, '2026-04-10 12:49:50', '2026-04-10 12:49:50', 0),
(2977, 6, 6, 0, 0, 0, 0, '2026-04-10 12:49:50', '2026-04-10 12:49:50', 0),
(2978, 6, 46, 1, 1, 1, 1, '2026-04-10 12:49:50', '2026-04-10 12:49:50', 0),
(2979, 6, 47, 1, 1, 1, 1, '2026-04-10 12:49:51', '2026-04-10 12:49:51', 0),
(2980, 6, 55, 1, 1, 1, 1, '2026-04-10 12:49:51', '2026-04-10 12:49:51', 0),
(2981, 6, 39, 0, 0, 0, 0, '2026-04-10 12:49:51', '2026-04-10 12:49:51', 0),
(2982, 6, 40, 0, 0, 0, 0, '2026-04-10 12:49:51', '2026-04-10 12:49:51', 0),
(2983, 6, 41, 0, 0, 0, 0, '2026-04-10 12:49:52', '2026-04-10 12:49:52', 0),
(2984, 6, 42, 0, 0, 0, 0, '2026-04-10 12:49:52', '2026-04-10 12:49:52', 0),
(2985, 6, 43, 0, 0, 0, 0, '2026-04-10 12:49:52', '2026-04-10 12:49:52', 0),
(2986, 6, 44, 0, 0, 0, 0, '2026-04-10 12:49:52', '2026-04-10 12:49:52', 0),
(2987, 6, 45, 1, 1, 1, 1, '2026-04-10 12:49:53', '2026-04-10 12:49:53', 0),
(2988, 6, 56, 0, 0, 0, 0, '2026-04-10 12:49:53', '2026-04-10 12:49:53', 0),
(2989, 6, 57, 0, 0, 0, 0, '2026-04-10 12:49:53', '2026-04-10 12:49:53', 0),
(2990, 6, 77, 0, 0, 0, 0, '2026-04-10 12:49:53', '2026-04-10 12:49:53', 0),
(2991, 6, 22, 0, 0, 0, 0, '2026-04-10 12:49:54', '2026-04-10 12:49:54', 0),
(2992, 6, 23, 0, 0, 0, 0, '2026-04-10 12:49:54', '2026-04-10 12:49:54', 0),
(2993, 6, 25, 0, 0, 0, 0, '2026-04-10 12:49:54', '2026-04-10 12:49:54', 0),
(2994, 6, 26, 0, 0, 0, 0, '2026-04-10 12:49:54', '2026-04-10 12:49:54', 0),
(2995, 6, 58, 0, 0, 0, 0, '2026-04-10 12:49:55', '2026-04-10 12:49:55', 0),
(2996, 6, 27, 0, 0, 0, 0, '2026-04-10 12:49:55', '2026-04-10 12:49:55', 0),
(2997, 6, 63, 0, 0, 0, 0, '2026-04-10 12:49:55', '2026-04-10 12:49:55', 0),
(2998, 6, 65, 0, 0, 0, 0, '2026-04-10 12:49:55', '2026-04-10 12:49:55', 0),
(2999, 6, 59, 0, 0, 0, 0, '2026-04-10 12:49:56', '2026-04-10 12:49:56', 0),
(3000, 6, 69, 0, 0, 0, 0, '2026-04-10 12:49:56', '2026-04-10 12:49:56', 0),
(3001, 6, 70, 0, 0, 0, 0, '2026-04-10 12:49:56', '2026-04-10 12:49:56', 0),
(3002, 6, 73, 0, 0, 0, 0, '2026-04-10 12:49:57', '2026-04-10 12:49:57', 0),
(3003, 6, 60, 0, 0, 0, 0, '2026-04-10 12:49:57', '2026-04-10 12:49:57', 0),
(3004, 6, 74, 0, 0, 0, 0, '2026-04-10 12:49:57', '2026-04-10 12:49:57', 0),
(3005, 6, 78, 1, 1, 1, 1, '2026-04-10 12:49:57', '2026-04-10 12:49:57', 0),
(3006, 6, 79, 1, 1, 1, 1, '2026-04-10 12:49:58', '2026-04-10 12:49:58', 0),
(3007, 6, 80, 1, 1, 1, 1, '2026-04-10 12:49:58', '2026-04-10 12:49:58', 0),
(3008, 6, 36, 0, 0, 0, 0, '2026-04-10 12:49:58', '2026-04-10 12:49:58', 0),
(3009, 6, 49, 0, 0, 0, 0, '2026-04-10 12:49:58', '2026-04-10 12:49:58', 0),
(3010, 6, 50, 0, 0, 0, 0, '2026-04-10 12:49:59', '2026-04-10 12:49:59', 0),
(3011, 6, 51, 1, 1, 1, 1, '2026-04-10 12:49:59', '2026-04-10 12:49:59', 0),
(3012, 6, 52, 0, 0, 0, 0, '2026-04-10 12:49:59', '2026-04-10 12:49:59', 0);

-- --------------------------------------------------------

--
-- Table structure for table `student_hr_interviews`
--

CREATE TABLE `student_hr_interviews` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student_hr_interviews`
--

INSERT INTO `student_hr_interviews` (`id`, `registration_id`, `branch_id`, `staff_user_id`, `sent_to_hr_by`, `sent_to_hr_at`, `company_name`, `interview_date`, `interview_status`, `rejection_reason`, `hr_updated_by`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 8, 8, '2026-03-31 07:53:07', 'ATS', '2026-04-07', 'scheduled', NULL, 3, '2026-03-31 07:52:24', '2026-04-06 04:24:09');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `assigned_to` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('Pending','Completed') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
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
  `reset_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `branch_id`, `role_id`, `name`, `email`, `phone`, `password`, `must_change_password`, `last_login`, `last_login_ip`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`, `ip_address`, `user_agent`, `remember_selector`, `remember_token_hash`, `remember_expires`, `reset_token_hash`, `reset_expires`) VALUES
(1, 1, 1, 'admin', 'admin@gmail.com', NULL, '$2y$10$XSJvOcrYzm1Si2hMt4XBAO8woxn0B2kN1/7sQAFN5GXTqgWZJx6r6', 0, '2026-04-11 09:04:48', '::1', 1, '2026-02-24 14:43:56', '2026-04-11 09:04:49', NULL, NULL, NULL, NULL, 'ef95668cda3d3984ea6f7073', '$2y$10$5pd2LJNLrEJj4w26Vm435u/IdqFhbiFEWIl4YAlT4pIbASVg3jOFW', '2026-05-11 09:04:48', NULL, NULL),
(2, 1, 2, 'John', 'webdeveloper05.ats@gmail.com', '9942133944', '$2y$10$RndVVNRzU5.otdBKdQ0GrucyKFszBSxuf1eBKo2IdNi7mKpDckkM.', 0, '2026-04-11 10:06:02', '157.51.61.143', 1, '2026-02-27 09:40:22', '2026-04-11 10:06:02', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(3, 1, 3, 'Michael', 'michael@gmail.com', '9942133944', '$2y$10$1v.h78Rz.VEfLfzF4mzUIOfYt/ucjkL0qpq4i.57N7AkUHuWyBKF.', 0, '2026-04-11 09:13:32', '::1', 1, '2026-02-27 09:55:00', '2026-04-11 09:13:32', 1, 1, '2401:4900:8825:644e:4140:caba:bff:f0c0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(4, 1, 4, 'Fredrick', 'fredrick@gmail.com', '7896541236', '$2y$10$NyGE/HNP0cteOIu13cNBrulKKUxPjbgJ1igsjcY8nSiuEuJlL/EiS', 0, '2026-04-10 12:43:24', '::1', 1, '2026-02-27 09:55:31', '2026-04-10 12:43:24', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(5, 1, 6, 'Fredrick John', 'fredrickjohn@gmail.com', '9942133944', '$2y$10$8xOZr5zGQCXQbah699/iv.scVJCDTAFOBaYSU3rT8TlfjvF2f1BSy', 0, '2026-04-10 12:53:25', '::1', 1, '2026-02-27 11:48:43', '2026-04-10 12:53:25', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(6, 1, 7, 'Test', 'test@gmail.com', '9874563210', '$2y$10$CYWliVwyZj8aAXljmNjrke8QM/jhkktGlOw46obUIp1N8buTUb4/q', 0, '2026-02-27 13:30:01', '::1', 1, '2026-02-27 13:12:51', '2026-02-27 13:30:01', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(7, 1, 2, 'Suresh', 'suresh@gmail.com', '9874563214', '$2y$10$.Chm89m3LfxsgYaKMzd47OCMBAp5nVfXpwHFWK7R7DrLKcev3jIm6', 0, '2026-04-10 03:13:31', '::1', 1, '2026-03-09 14:38:38', '2026-04-10 03:13:31', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(8, 1, 4, 'Sridharshan', 'webdeveloper005.ats@gmail.com', '9876543210', '$2y$10$.Chm89m3LfxsgYaKMzd47OCMBAp5nVfXpwHFWK7R7DrLKcev3jIm6', 0, '2026-04-08 03:49:44', '::1', 1, '2026-03-11 06:51:11', '2026-04-11 09:22:39', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'd88f4c85bdfe96a1dad64e8a', '$2y$10$J01vTYtWN.e6g4fc6isLLeq9Ol0neLhFE8VPBrlZrB9goQqIDxWbO', '2026-05-11 09:22:39', NULL, NULL),
(9, 1, 8, 'Sample', 'sample@gmail.com', '234567890', '$2y$10$St4Untsr9wTc1f7FRT.8aeFB1cwGHn2KDVeXGuh0acJBmsCqK2iX.', 0, '2026-03-11 13:04:41', '2401:4900:8825:644e:c116:fd72:e0c1:8291', 1, '2026-03-11 12:19:43', '2026-03-11 13:04:41', 1, 1, '2401:4900:8825:644e:44a5:d376:2e7f:954b', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(10, 1, 2, 'pramo', 'pramo@gmail.com', '7896541236', '$2y$10$JPP0QaZrwfnWsoMDW.9YxuPnl8Ul1/Co96o0DIFhelHMJY9VZClUm', 0, '2026-03-30 09:26:31', '::1', 1, '2026-03-24 06:08:43', '2026-03-30 09:26:31', 1, 1, '2401:4900:8825:644e:85f7:385b:a1f4:35a1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(11, 1, 2, 'Theju', 'theju@gmail.com', '874596521', '$2y$10$5oyokvxQZ5W.mBIGlF7Huetm0O8bO7suhyUG0FGlpFi13S.Yay.l.', 0, '2026-03-30 09:14:30', '2401:4900:8825:644e:5c49:878b:2a0:8236', 1, '2026-03-24 06:09:24', '2026-03-30 09:14:30', 1, 1, '2401:4900:8825:644e:85f7:385b:a1f4:35a1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(12, 1, 4, 'Raghul', 'raghul@gmail.com', '789654136', '$2y$10$9qKZb8lz8OHNcU2bWNN.Nueo/fVfMS3Nnw20dA6JE.U4OLF2N/gJm', 0, '2026-03-31 07:45:58', '223.185.27.31', 1, '2026-03-24 06:10:59', '2026-03-31 07:45:58', 1, 1, '2405:201:e015:50bd:8df4:ad31:f677:3c13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', NULL, NULL, NULL, NULL, NULL),
(13, 1, 1, 'Admin User Test1', 'admin@atscrm.com', '9000000001', '$2y$10$dneLsIcGaIjJQsGXakz.N.jkDj/y8tujl1D8Rs6ir90KJlQraxuTW', 0, NULL, NULL, 1, '2026-03-24 07:01:47', '2026-03-24 07:59:10', 1, 1, '2405:201:e015:50bd:f51b:db:6227:915c', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(14, 1, 3, 'Krishnan', 'krishnan.hr@gmail.com', '9000000002', '$2y$10$Gf9RVnpdYshfeEj0cyzIgOKqqcMLNJtfZvL0IiUabsC.lm7fhmboC', 0, NULL, NULL, 1, '2026-03-24 07:02:57', '2026-03-24 07:59:14', 1, 1, '2405:201:e015:50bd:f51b:db:6227:915c', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(15, 1, 4, 'Arunkumar', 'arun@gmail.com', '8148903261', '$2y$10$2NcAq6j3kSS/csXBji0d0uIts2Vb.QaN6cTa1D9PeBe3vM2KI5S3W', 0, '2026-03-30 07:31:02', '2401:4900:8825:644e:84a2:27d2:1df3:b321', 1, '2026-03-24 07:19:24', '2026-03-30 07:31:02', 1, 1, '2401:4900:8825:644e:21e1:1230:a94a:f720', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(16, 1, 2, 'Testuser', 'testuser@gmail.com', '7896541236', '$2y$10$4.EDVgwBicCbIWECnGcElenLTgmqqi56JnCYRfngKvFmB1Z050hmS', 0, NULL, NULL, 1, '2026-03-25 04:11:25', '2026-03-25 04:11:25', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(17, 1, 6, 'Saravana', 'saravanan@gmail.com', '9874563210', '$2y$10$ftHfb6DRliKMnY9uJ2pgROR6pkL6LnJKc3aE1MOEwQgv658A50IB6', 0, '2026-04-11 09:16:10', '::1', 1, '2026-04-10 13:03:20', '2026-04-11 09:16:10', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assessment`
--
ALTER TABLE `assessment`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_assessment_registration` (`registration_id`),
  ADD KEY `idx_assessment_branch` (`branch_id`),
  ADD KEY `idx_assessment_staff` (`staff_user_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`user_id`,`course_id`,`attendance_date`),
  ADD UNIQUE KEY `unique_attendance_per_day` (`user_id`,`course_id`,`attendance_date`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `attendance_ibfk_registration` (`registration_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `batches`
--
ALTER TABLE `batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `trainer_id` (`trainer_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `certificate_number` (`certificate_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `colleges_master`
--
ALTER TABLE `colleges_master`
  ADD PRIMARY KEY (`id`),
  ADD KEY `college_name` (`college_name`),
  ADD KEY `phone` (`phone`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contacts_master`
--
ALTER TABLE `contacts_master`
  ADD PRIMARY KEY (`id`),
  ADD KEY `phone` (`phone`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `course_code` (`course_code`),
  ADD KEY `idx_branch` (`branch_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `dailyreport_frontoffice_activity`
--
ALTER TABLE `dailyreport_frontoffice_activity`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_drf_activity_master` (`master_id`),
  ADD KEY `idx_drfo_activity_master` (`master_id`);

--
-- Indexes for table `dailyreport_frontoffice_college_followup_rows`
--
ALTER TABLE `dailyreport_frontoffice_college_followup_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_drf_college_master` (`master_id`),
  ADD KEY `idx_drfo_college_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_frontoffice_college_followup_status`
--
ALTER TABLE `dailyreport_frontoffice_college_followup_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_drf_college_status` (`followup_row_id`,`status_date`),
  ADD KEY `idx_drfo_college_status_row` (`followup_row_id`);

--
-- Indexes for table `dailyreport_frontoffice_database_followup_rows`
--
ALTER TABLE `dailyreport_frontoffice_database_followup_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_drf_db_master` (`master_id`),
  ADD KEY `idx_drfo_db_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_frontoffice_database_followup_status`
--
ALTER TABLE `dailyreport_frontoffice_database_followup_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_drf_db_status` (`database_row_id`,`status_date`),
  ADD KEY `idx_drfo_db_status_row` (`database_row_id`);

--
-- Indexes for table `dailyreport_frontoffice_hourly_rows`
--
ALTER TABLE `dailyreport_frontoffice_hourly_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_drf_hourly_master` (`master_id`),
  ADD KEY `idx_drfo_hourly_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_frontoffice_planner_rows`
--
ALTER TABLE `dailyreport_frontoffice_planner_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_drf_plan_master` (`master_id`),
  ADD KEY `idx_drfo_planner_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_frontoffice_registration_rows`
--
ALTER TABLE `dailyreport_frontoffice_registration_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_drf_reg_master` (`master_id`),
  ADD KEY `idx_drfo_reg_master` (`master_id`);

--
-- Indexes for table `dailyreport_hr_activity`
--
ALTER TABLE `dailyreport_hr_activity`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_dailyreport_hr_activity_master` (`master_id`),
  ADD KEY `idx_drhr_activity_master` (`master_id`);

--
-- Indexes for table `dailyreport_hr_college_data_rows`
--
ALTER TABLE `dailyreport_hr_college_data_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dailyreport_hr_college_data_master` (`master_id`),
  ADD KEY `idx_drhr_cd_master` (`master_id`);

--
-- Indexes for table `dailyreport_hr_college_followup_rows`
--
ALTER TABLE `dailyreport_hr_college_followup_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dailyreport_hr_college_followup_master` (`master_id`),
  ADD KEY `idx_drhr_cf_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_hr_hourly_rows`
--
ALTER TABLE `dailyreport_hr_hourly_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dailyreport_hr_hourly_master` (`master_id`),
  ADD KEY `idx_dailyreport_hr_hourly_sort` (`master_id`,`sort_order`),
  ADD KEY `idx_drhr_hourly_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_hr_internship_rows`
--
ALTER TABLE `dailyreport_hr_internship_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dailyreport_hr_internship_master` (`master_id`),
  ADD KEY `idx_drhr_intern_master` (`master_id`);

--
-- Indexes for table `dailyreport_hr_interview_rows`
--
ALTER TABLE `dailyreport_hr_interview_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dailyreport_hr_interview_master` (`master_id`),
  ADD KEY `idx_dailyreport_hr_interview_date` (`master_id`,`interview_date`),
  ADD KEY `idx_drhr_interview_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_hr_new_client_rows`
--
ALTER TABLE `dailyreport_hr_new_client_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dailyreport_hr_new_client_master` (`master_id`),
  ADD KEY `idx_drhr_new_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_hr_old_client_rows`
--
ALTER TABLE `dailyreport_hr_old_client_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dailyreport_hr_old_client_master` (`master_id`),
  ADD KEY `idx_drhr_old_master` (`master_id`);

--
-- Indexes for table `dailyreport_hr_placement_call_rows`
--
ALTER TABLE `dailyreport_hr_placement_call_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dailyreport_hr_placement_master` (`master_id`),
  ADD KEY `idx_drhr_placement_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_marketing_activity`
--
ALTER TABLE `dailyreport_marketing_activity`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_marketing_activity_master` (`master_id`),
  ADD KEY `idx_marketing_activity_master` (`master_id`),
  ADD KEY `idx_drmk_activity_master` (`master_id`);

--
-- Indexes for table `dailyreport_marketing_act_report_rows`
--
ALTER TABLE `dailyreport_marketing_act_report_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_marketing_act_master` (`master_id`,`sort_order`),
  ADD KEY `idx_drmk_act_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_marketing_amount_rows`
--
ALTER TABLE `dailyreport_marketing_amount_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_marketing_amount_master` (`master_id`,`sort_order`),
  ADD KEY `idx_drmk_amount_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_marketing_arts_college_rows`
--
ALTER TABLE `dailyreport_marketing_arts_college_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_marketing_arts_college_master` (`master_id`,`sort_order`),
  ADD KEY `idx_drmk_arts_college_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_marketing_arts_pc_rows`
--
ALTER TABLE `dailyreport_marketing_arts_pc_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_marketing_arts_pc_master` (`master_id`,`sort_order`),
  ADD KEY `idx_drmk_arts_pc_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_marketing_colleges_rows`
--
ALTER TABLE `dailyreport_marketing_colleges_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_marketing_colleges_master` (`master_id`,`sort_order`),
  ADD KEY `idx_drmk_college_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_marketing_engg_college_rows`
--
ALTER TABLE `dailyreport_marketing_engg_college_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_marketing_engg_college_master` (`master_id`,`sort_order`),
  ADD KEY `idx_drmk_engg_college_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_marketing_engg_pc_rows`
--
ALTER TABLE `dailyreport_marketing_engg_pc_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_marketing_engg_pc_master` (`master_id`,`sort_order`),
  ADD KEY `idx_drmk_engg_pc_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_marketing_hourly_rows`
--
ALTER TABLE `dailyreport_marketing_hourly_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_marketing_hourly_master` (`master_id`,`sort_order`),
  ADD KEY `idx_drmk_hourly_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_marketing_polytech_college_rows`
--
ALTER TABLE `dailyreport_marketing_polytech_college_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_marketing_polytech_master` (`master_id`,`sort_order`),
  ADD KEY `idx_drmk_polytech_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_marketing_program_rows`
--
ALTER TABLE `dailyreport_marketing_program_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_marketing_program_master` (`master_id`,`sort_order`),
  ADD KEY `idx_drmk_program_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_marketing_prospect_rows`
--
ALTER TABLE `dailyreport_marketing_prospect_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_marketing_prospect_master` (`master_id`,`sort_order`),
  ADD KEY `idx_drmk_prospect_master_sort` (`master_id`,`sort_order`);

--
-- Indexes for table `dailyreport_marketing_prospect_status_rows`
--
ALTER TABLE `dailyreport_marketing_prospect_status_rows`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mk_prospect_status_row` (`prospect_row_id`,`sort_order`),
  ADD KEY `idx_drmk_prospect_status_row_sort` (`prospect_row_id`,`sort_order`);

--
-- Indexes for table `dailyreport_master`
--
ALTER TABLE `dailyreport_master`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dailyreport_master` (`report_date`,`user_id`,`report_type`),
  ADD KEY `idx_dailyreport_master_branch` (`branch_id`),
  ADD KEY `idx_dailyreport_master_role` (`role_id`),
  ADD KEY `idx_dailyreport_master_user` (`user_id`),
  ADD KEY `idx_dm_reportdate_user_branch_type` (`report_date`,`user_id`,`branch_id`,`report_type`),
  ADD KEY `idx_dm_user_branch_date` (`user_id`,`branch_id`,`report_date`),
  ADD KEY `idx_dm_status` (`status`);

--
-- Indexes for table `enquiries`
--
ALTER TABLE `enquiries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_enquiry_no` (`enquiry_no`),
  ADD KEY `idx_enquiries_branch` (`branch_id`),
  ADD KEY `idx_enquiries_status` (`status`),
  ADD KEY `idx_enquiries_handled_by` (`handled_by`),
  ADD KEY `idx_enquiries_date` (`enquiry_date`);

--
-- Indexes for table `enquiry_followups`
--
ALTER TABLE `enquiry_followups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `enquiry_id` (`enquiry_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `followup_date` (`followup_date`),
  ADD KEY `status` (`status`),
  ADD KEY `verification_status` (`verification_status`),
  ADD KEY `idx_ef_date_user_branch` (`followup_date`,`created_by`,`branch_id`);

--
-- Indexes for table `enquiry_followup_files`
--
ALTER TABLE `enquiry_followup_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `followup_id` (`followup_id`),
  ADD KEY `enquiry_id` (`enquiry_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `file_type` (`file_type`);

--
-- Indexes for table `enquiry_sequence`
--
ALTER TABLE `enquiry_sequence`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `fee_structures`
--
ALTER TABLE `fee_structures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `followups`
--
ALTER TABLE `followups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`),
  ADD KEY `followup_date` (`followup_date`);

--
-- Indexes for table `interviews`
--
ALTER TABLE `interviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `leads`
--
ALTER TABLE `leads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `idx_leads_import_batch_id` (`import_batch_id`),
  ADD KEY `idx_leads_branch` (`branch_id`),
  ADD KEY `idx_leads_assigned` (`assigned_to`),
  ADD KEY `idx_leads_status` (`status`),
  ADD KEY `idx_leads_created` (`created_by`),
  ADD KEY `idx_leads_phone` (`phone`);

--
-- Indexes for table `lead_import_batches`
--
ALTER TABLE `lead_import_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lib_branch` (`branch_id`),
  ADD KEY `idx_lib_created_by` (`created_by`);

--
-- Indexes for table `marketing_amount`
--
ALTER TABLE `marketing_amount`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `menus`
--
ALTER TABLE `menus`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mock_interviews`
--
ALTER TABLE `mock_interviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_mock_interview_registration` (`registration_id`),
  ADD KEY `idx_mock_interviews_branch` (`branch_id`),
  ADD KEY `idx_mock_interviews_staff` (`staff_user_id`);

--
-- Indexes for table `monthly_targets`
--
ALTER TABLE `monthly_targets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_target_user_month_branch` (`branch_id`,`user_id`,`target_year`,`target_month`),
  ADD KEY `idx_target_branch` (`branch_id`),
  ADD KEY `idx_target_user` (`user_id`),
  ADD KEY `idx_target_role` (`role_id`),
  ADD KEY `idx_target_period` (`target_year`,`target_month`),
  ADD KEY `idx_target_assigned_by` (`assigned_by`);

--
-- Indexes for table `monthly_target_results`
--
ALTER TABLE `monthly_target_results`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_result_user_month_branch` (`branch_id`,`user_id`,`target_year`,`target_month`),
  ADD KEY `idx_result_branch` (`branch_id`),
  ADD KEY `idx_result_user` (`user_id`),
  ADD KEY `idx_result_role` (`role_id`),
  ADD KEY `idx_result_period` (`target_year`,`target_month`),
  ADD KEY `idx_result_target_id` (`target_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `placements`
--
ALTER TABLE `placements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `placement_interviews`
--
ALTER TABLE `placement_interviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pi_registration` (`registration_id`),
  ADD KEY `idx_pi_branch` (`branch_id`),
  ADD KEY `idx_pi_workflow` (`hr_workflow_id`),
  ADD KEY `idx_pi_status` (`status`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_registration_no` (`registration_no`),
  ADD KEY `enquiry_id` (`enquiry_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `status` (`registration_status`),
  ADD KEY `idx_enquiry_id` (`enquiry_id`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_reg_type` (`reg_type`),
  ADD KEY `idx_assigned_to` (`assigned_to`),
  ADD KEY `idx_registration_status` (`registration_status`);

--
-- Indexes for table `registration_courses`
--
ALTER TABLE `registration_courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_registration_courses_registration` (`registration_id`),
  ADD KEY `idx_registration_courses_guide_staff` (`guide_staff_id`),
  ADD KEY `idx_registration_courses_assigned_by` (`assigned_by`);

--
-- Indexes for table `registration_internships`
--
ALTER TABLE `registration_internships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_registration_internships_registration` (`registration_id`),
  ADD KEY `idx_registration_internships_guide_staff` (`guide_staff_id`),
  ADD KEY `idx_registration_internships_assigned_by` (`assigned_by`);

--
-- Indexes for table `registration_payments`
--
ALTER TABLE `registration_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_registration_id` (`registration_id`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_staff_id` (`staff_id`),
  ADD KEY `idx_payment_date` (`payment_date`),
  ADD KEY `idx_approval_status` (`approval_status`),
  ADD KEY `idx_target_report` (`branch_id`,`collected_by`,`approval_status`,`payment_date`);

--
-- Indexes for table `registration_profiles`
--
ALTER TABLE `registration_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_registration_profile` (`registration_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `menu_id` (`menu_id`);

--
-- Indexes for table `student_hr_interviews`
--
ALTER TABLE `student_hr_interviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_student_hr_interviews_registration` (`registration_id`),
  ADD KEY `idx_student_hr_interviews_branch` (`branch_id`),
  ADD KEY `idx_student_hr_interviews_staff` (`staff_user_id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_to` (`assigned_to`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `idx_remember_selector` (`remember_selector`),
  ADD KEY `idx_users_name` (`name`),
  ADD KEY `idx_users_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assessment`
--
ALTER TABLE `assessment`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `batches`
--
ALTER TABLE `batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `colleges_master`
--
ALTER TABLE `colleges_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contacts_master`
--
ALTER TABLE `contacts_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dailyreport_frontoffice_activity`
--
ALTER TABLE `dailyreport_frontoffice_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `dailyreport_frontoffice_college_followup_rows`
--
ALTER TABLE `dailyreport_frontoffice_college_followup_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `dailyreport_frontoffice_college_followup_status`
--
ALTER TABLE `dailyreport_frontoffice_college_followup_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `dailyreport_frontoffice_database_followup_rows`
--
ALTER TABLE `dailyreport_frontoffice_database_followup_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `dailyreport_frontoffice_database_followup_status`
--
ALTER TABLE `dailyreport_frontoffice_database_followup_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `dailyreport_frontoffice_hourly_rows`
--
ALTER TABLE `dailyreport_frontoffice_hourly_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `dailyreport_frontoffice_planner_rows`
--
ALTER TABLE `dailyreport_frontoffice_planner_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `dailyreport_frontoffice_registration_rows`
--
ALTER TABLE `dailyreport_frontoffice_registration_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `dailyreport_hr_activity`
--
ALTER TABLE `dailyreport_hr_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dailyreport_hr_college_data_rows`
--
ALTER TABLE `dailyreport_hr_college_data_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dailyreport_hr_college_followup_rows`
--
ALTER TABLE `dailyreport_hr_college_followup_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dailyreport_hr_hourly_rows`
--
ALTER TABLE `dailyreport_hr_hourly_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `dailyreport_hr_internship_rows`
--
ALTER TABLE `dailyreport_hr_internship_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dailyreport_hr_interview_rows`
--
ALTER TABLE `dailyreport_hr_interview_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dailyreport_hr_new_client_rows`
--
ALTER TABLE `dailyreport_hr_new_client_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dailyreport_hr_old_client_rows`
--
ALTER TABLE `dailyreport_hr_old_client_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dailyreport_hr_placement_call_rows`
--
ALTER TABLE `dailyreport_hr_placement_call_rows`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `dailyreport_marketing_activity`
--
ALTER TABLE `dailyreport_marketing_activity`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dailyreport_marketing_act_report_rows`
--
ALTER TABLE `dailyreport_marketing_act_report_rows`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dailyreport_marketing_amount_rows`
--
ALTER TABLE `dailyreport_marketing_amount_rows`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dailyreport_marketing_arts_college_rows`
--
ALTER TABLE `dailyreport_marketing_arts_college_rows`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dailyreport_marketing_arts_pc_rows`
--
ALTER TABLE `dailyreport_marketing_arts_pc_rows`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dailyreport_marketing_colleges_rows`
--
ALTER TABLE `dailyreport_marketing_colleges_rows`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dailyreport_marketing_engg_college_rows`
--
ALTER TABLE `dailyreport_marketing_engg_college_rows`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dailyreport_marketing_engg_pc_rows`
--
ALTER TABLE `dailyreport_marketing_engg_pc_rows`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dailyreport_marketing_hourly_rows`
--
ALTER TABLE `dailyreport_marketing_hourly_rows`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dailyreport_marketing_polytech_college_rows`
--
ALTER TABLE `dailyreport_marketing_polytech_college_rows`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dailyreport_marketing_program_rows`
--
ALTER TABLE `dailyreport_marketing_program_rows`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dailyreport_marketing_prospect_rows`
--
ALTER TABLE `dailyreport_marketing_prospect_rows`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dailyreport_marketing_prospect_status_rows`
--
ALTER TABLE `dailyreport_marketing_prospect_status_rows`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dailyreport_master`
--
ALTER TABLE `dailyreport_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `enquiries`
--
ALTER TABLE `enquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `enquiry_followups`
--
ALTER TABLE `enquiry_followups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `enquiry_followup_files`
--
ALTER TABLE `enquiry_followup_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enquiry_sequence`
--
ALTER TABLE `enquiry_sequence`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=242;

--
-- AUTO_INCREMENT for table `fee_structures`
--
ALTER TABLE `fee_structures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `followups`
--
ALTER TABLE `followups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `interviews`
--
ALTER TABLE `interviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leads`
--
ALTER TABLE `leads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `lead_import_batches`
--
ALTER TABLE `lead_import_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `marketing_amount`
--
ALTER TABLE `marketing_amount`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menus`
--
ALTER TABLE `menus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `mock_interviews`
--
ALTER TABLE `mock_interviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `monthly_targets`
--
ALTER TABLE `monthly_targets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `monthly_target_results`
--
ALTER TABLE `monthly_target_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `placements`
--
ALTER TABLE `placements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `placement_interviews`
--
ALTER TABLE `placement_interviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `registration_courses`
--
ALTER TABLE `registration_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `registration_internships`
--
ALTER TABLE `registration_internships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `registration_payments`
--
ALTER TABLE `registration_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `registration_profiles`
--
ALTER TABLE `registration_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3013;

--
-- AUTO_INCREMENT for table `student_hr_interviews`
--
ALTER TABLE `student_hr_interviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `batches`
--
ALTER TABLE `batches`
  ADD CONSTRAINT `batches_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `batches_ibfk_2` FOREIGN KEY (`trainer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `certificates_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dailyreport_frontoffice_activity`
--
ALTER TABLE `dailyreport_frontoffice_activity`
  ADD CONSTRAINT `fk_drf_activity_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dailyreport_frontoffice_college_followup_rows`
--
ALTER TABLE `dailyreport_frontoffice_college_followup_rows`
  ADD CONSTRAINT `fk_drf_college_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dailyreport_frontoffice_college_followup_status`
--
ALTER TABLE `dailyreport_frontoffice_college_followup_status`
  ADD CONSTRAINT `fk_drf_college_status_row` FOREIGN KEY (`followup_row_id`) REFERENCES `dailyreport_frontoffice_college_followup_rows` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dailyreport_frontoffice_database_followup_rows`
--
ALTER TABLE `dailyreport_frontoffice_database_followup_rows`
  ADD CONSTRAINT `fk_drf_db_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dailyreport_frontoffice_database_followup_status`
--
ALTER TABLE `dailyreport_frontoffice_database_followup_status`
  ADD CONSTRAINT `fk_drf_db_status_row` FOREIGN KEY (`database_row_id`) REFERENCES `dailyreport_frontoffice_database_followup_rows` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dailyreport_frontoffice_hourly_rows`
--
ALTER TABLE `dailyreport_frontoffice_hourly_rows`
  ADD CONSTRAINT `fk_drf_hourly_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dailyreport_frontoffice_planner_rows`
--
ALTER TABLE `dailyreport_frontoffice_planner_rows`
  ADD CONSTRAINT `fk_drf_plan_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dailyreport_frontoffice_registration_rows`
--
ALTER TABLE `dailyreport_frontoffice_registration_rows`
  ADD CONSTRAINT `fk_drf_reg_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dailyreport_hr_activity`
--
ALTER TABLE `dailyreport_hr_activity`
  ADD CONSTRAINT `fk_dailyreport_hr_activity_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dailyreport_hr_college_data_rows`
--
ALTER TABLE `dailyreport_hr_college_data_rows`
  ADD CONSTRAINT `fk_dailyreport_hr_college_data_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dailyreport_hr_college_followup_rows`
--
ALTER TABLE `dailyreport_hr_college_followup_rows`
  ADD CONSTRAINT `fk_dailyreport_hr_college_followup_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dailyreport_hr_hourly_rows`
--
ALTER TABLE `dailyreport_hr_hourly_rows`
  ADD CONSTRAINT `fk_dailyreport_hr_hourly_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dailyreport_hr_internship_rows`
--
ALTER TABLE `dailyreport_hr_internship_rows`
  ADD CONSTRAINT `fk_dailyreport_hr_internship_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dailyreport_hr_interview_rows`
--
ALTER TABLE `dailyreport_hr_interview_rows`
  ADD CONSTRAINT `fk_dailyreport_hr_interview_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dailyreport_hr_new_client_rows`
--
ALTER TABLE `dailyreport_hr_new_client_rows`
  ADD CONSTRAINT `fk_dailyreport_hr_new_client_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dailyreport_hr_old_client_rows`
--
ALTER TABLE `dailyreport_hr_old_client_rows`
  ADD CONSTRAINT `fk_dailyreport_hr_old_client_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dailyreport_hr_placement_call_rows`
--
ALTER TABLE `dailyreport_hr_placement_call_rows`
  ADD CONSTRAINT `fk_dailyreport_hr_placement_master` FOREIGN KEY (`master_id`) REFERENCES `dailyreport_master` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dailyreport_marketing_prospect_status_rows`
--
ALTER TABLE `dailyreport_marketing_prospect_status_rows`
  ADD CONSTRAINT `fk_mk_prospect_status_row` FOREIGN KEY (`prospect_row_id`) REFERENCES `dailyreport_marketing_prospect_rows` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enquiries`
--
ALTER TABLE `enquiries`
  ADD CONSTRAINT `enquiries_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enquiry_followups`
--
ALTER TABLE `enquiry_followups`
  ADD CONSTRAINT `fk_enq_followup_enquiry` FOREIGN KEY (`enquiry_id`) REFERENCES `enquiries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `enquiry_followup_files`
--
ALTER TABLE `enquiry_followup_files`
  ADD CONSTRAINT `fk_followup_files_followup` FOREIGN KEY (`followup_id`) REFERENCES `enquiry_followups` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `fee_structures`
--
ALTER TABLE `fee_structures`
  ADD CONSTRAINT `fee_structures_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `interviews`
--
ALTER TABLE `interviews`
  ADD CONSTRAINT `interviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `leads`
--
ALTER TABLE `leads`
  ADD CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `monthly_targets`
--
ALTER TABLE `monthly_targets`
  ADD CONSTRAINT `fk_monthly_targets_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_monthly_targets_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_monthly_targets_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `fk_monthly_targets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `monthly_target_results`
--
ALTER TABLE `monthly_target_results`
  ADD CONSTRAINT `fk_monthly_target_results_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_monthly_target_results_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `fk_monthly_target_results_target` FOREIGN KEY (`target_id`) REFERENCES `monthly_targets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_monthly_target_results_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `placements`
--
ALTER TABLE `placements`
  ADD CONSTRAINT `placements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `placement_interviews`
--
ALTER TABLE `placement_interviews`
  ADD CONSTRAINT `fk_pi_hr_workflow` FOREIGN KEY (`hr_workflow_id`) REFERENCES `student_hr_interviews` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pi_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `fk_reg_enquiry` FOREIGN KEY (`enquiry_id`) REFERENCES `enquiries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registration_courses`
--
ALTER TABLE `registration_courses`
  ADD CONSTRAINT `fk_registration_courses_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_registration_courses_guide_staff` FOREIGN KEY (`guide_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_registration_courses_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registration_internships`
--
ALTER TABLE `registration_internships`
  ADD CONSTRAINT `fk_registration_internships_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_registration_internships_guide_staff` FOREIGN KEY (`guide_staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_registration_internships_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registration_payments`
--
ALTER TABLE `registration_payments`
  ADD CONSTRAINT `fk_registration_payments_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registration_profiles`
--
ALTER TABLE `registration_profiles`
  ADD CONSTRAINT `fk_registration_profiles_registration` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
