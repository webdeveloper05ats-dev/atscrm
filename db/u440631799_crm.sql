-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 11, 2026 at 04:45 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

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
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('Present','Absent','Late') NOT NULL,
  `marked_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

INSERT INTO `enquiries` (`id`, `enquiry_date`, `enquiry_no`, `branch_id`, `name`, `dob`, `profession`, `gender`, `address`, `instagram_id`, `phone`, `email`, `course_interest`, `qualification`, `year_of_passout`, `college`, `percentage_marks`, `father_name`, `father_occupation`, `father_contact_no`, `software_languages_known`, `technologies`, `interested_in`, `placements_required`, `know_about`, `know_about_other`, `candidate_signature_path`, `counselor_signature_path`, `status`, `handled_by`, `converted_registration_id`, `remarks`, `created_at`, `updated_at`, `created_by`, `updated_by`, `ip_address`, `user_agent`, `platform`, `app_version`, `device_type`, `created_ip`) VALUES
(1, '2026-03-06', 'ENQ-20260306-0001', 1, 'Arun', '1987-07-21', 'Student', 'male', 'Ram Nagar', 'Sample', '7896541236', 'arun@gmail.com', 'Flutter Development', 'MCA', 2010, 'Anna Univercity', 75.00, 'Ram', 'Sample', '796541230114', 'Java,HTML', 'Web Designing', 'Internship,Placement Assistance', 1, 'Walk-in', 'NO', NULL, NULL, 'converted', 2, NULL, 'I need internship', '2026-03-06 11:57:44', '2026-03-06 17:07:31', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(2, '2026-03-06', 'ENQ-20260306-0002', 1, 'Raghul', '1987-05-22', 'Student', 'male', 'Ram Nagar', 'Sample', '9942133944', 'raghul@gmail.com', 'Data Analytics', 'MCA', 2010, 'Anna Univercity', 75.00, 'Ram', 'Sample', '796541230114', 'HTML,PYTHON', 'Data Science', 'Technology Training', 1, 'Other', 'NO', NULL, NULL, 'converted', 2, NULL, NULL, '2026-03-06 12:50:00', '2026-03-07 11:36:00', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(3, '2026-03-07', 'ENQ-20260307-0001', 1, 'Grandway Properties', NULL, 'Owner', 'male', 'Ram Nagar', 'Sample', '98745632145', 'grandway@gmail.com', 'Website Making', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Other', 'Reference', NULL, NULL, 'converted', 2, NULL, NULL, '2026-03-07 14:48:25', '2026-03-11 09:00:06', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL);

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
(1, 1, 1, '2026-03-06', '16:45:00', 'call', 'done', 'This Student interested in join Flutter internship', '2026-03-07', '16:45:00', '2026-03-06 17:07:31', 'pending', NULL, NULL, NULL, 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 12:45:29', '2026-03-06 17:07:31'),
(2, 2, 1, '2026-03-06', '16:55:00', 'call', 'done', 'This student need Course', '2026-03-07', '17:50:00', '2026-03-07 11:36:00', 'pending', NULL, NULL, NULL, 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 12:50:45', '2026-03-07 11:36:00'),
(3, 3, 1, '2026-03-07', '17:48:00', 'call', 'done', 'Sample', '2026-03-09', '17:48:00', '2026-03-11 09:00:06', 'pending', NULL, NULL, NULL, 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 14:49:09', '2026-03-11 09:00:06');

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
(1, 1, 'Grandway Properties', '98745632145', 'grandway@gmail.com', 'Reference', 'Website Making', 'Grandway properties parent', 'FSWD', '2026', 'converted', NULL, '2026-03-07 14:48:25', NULL, 2, NULL, 3, NULL, 'If we follow him sure he will give website work to us', '2026-03-07 14:17:02', '2026-03-10 10:22:46', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(2, 1, 'Altitudes', '7896541230', 'altitudes@gmail.com', 'Walk-in', 'Full Stack Webdevelopment', 'Altitudes parent company', 'FSWD', '2026', 'new', NULL, NULL, NULL, 2, NULL, NULL, NULL, 'test', '2026-03-09 14:34:47', '2026-03-10 10:22:38', 3, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(3, 1, 'Arun Kumar', '9876543210', 'arun.kumar@example.com', 'Website', 'Full Stack Development', 'ABC Engineering College', 'CSE', '2026', 'new', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'Interested in weekend batch', '2026-03-10 11:48:14', '2026-03-10 11:48:14', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(4, 1, 'Priya Sharma', '9123456780', 'priya.sharma@example.com', 'Instagram', 'UI UX Design', 'XYZ Arts and Science College', 'Visual Communication', '2025', 'new', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'Wants placement support', '2026-03-10 11:48:14', '2026-03-10 11:48:14', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(5, 1, 'Rahul Verma', '9988776655', 'rahul.verma@example.com', 'Google Ads', 'Data Analytics', 'National Institute of Technology', 'EEE', 'Final Year', 'new', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'Asked for fee details', '2026-03-10 11:48:14', '2026-03-10 11:48:14', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(6, 1, 'Sneha R', '9001122334', 'sneha.r@example.com', 'Reference', 'Python Development', 'Metro College of Engineering', 'IT', '2024', 'new', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'Can join immediately', '2026-03-10 11:48:14', '2026-03-10 11:48:14', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(7, 1, 'Karthik S', '9090909090', 'karthik.s@example.com', 'Walk-in', 'Digital Marketing', 'Sunrise Business School', 'MBA', '2025', 'new', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'Needs weekday batch', '2026-03-10 11:48:14', '2026-03-10 11:48:14', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(8, 1, 'Meena Joseph', '9345678901', 'meena.joseph@example.com', 'Facebook', 'Testing', 'St. Thomas College', 'BCA', '2026', 'new', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'Requested callback tomorrow', '2026-03-10 11:48:14', '2026-03-10 11:48:14', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(9, 1, 'Vikram Patel', '9785612345', 'vikram.patel@example.com', 'Website', 'Java Development', 'Global Tech College', 'CSE', '2023', 'new', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'Working professional', '2026-03-10 11:48:14', '2026-03-10 11:48:14', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(10, 1, 'Anjali Nair', '9654321870', 'anjali.nair@example.com', 'Instagram', 'Graphic Design', 'Creative Media Institute', 'Design', '2026', 'new', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'Interested in portfolio guidance', '2026-03-10 11:48:14', '2026-03-10 11:48:14', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(11, 1, 'Mohammed Irfan', '9445566677', 'mohd.irfan@example.com', 'Reference', 'Cloud Computing', 'Alpha Polytechnic', 'Computer Engineering', '2024', 'new', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'Asked about certification', '2026-03-10 11:48:14', '2026-03-10 11:48:14', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(12, 1, 'Divya K', '9556677889', 'divya.k@example.com', 'Google Ads', 'MS Office', 'City Women\'s College', 'B.Com', '2025', 'new', NULL, NULL, NULL, NULL, 1, NULL, NULL, 'Beginner level student', '2026-03-10 11:48:14', '2026-03-10 11:48:14', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36');

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
(1, 1, 2, 'leads_20260310_114814_6723.xlsx', 10, 10, 0, 'completed', '2026-03-10 11:48:14');

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
(15, 'Programs', 'programs', NULL, 'fas fa-graduation-cap', 9, 1, '2026-02-26 13:33:56', '2026-02-26 15:49:13'),
(16, 'Students', 'students', NULL, 'fas fa-user-graduate', 10, 1, '2026-02-26 13:35:03', '2026-02-26 13:52:16'),
(17, 'Payments', 'payments', NULL, 'fas fa-rupee-sign', 11, 1, '2026-02-26 13:36:05', '2026-02-26 15:49:45'),
(18, 'Staff Panel', 'staffpanel', NULL, 'fas fa-chalkboard-teacher', 12, 1, '2026-02-26 13:37:16', '2026-02-26 15:49:59'),
(19, 'HR Panel', 'hr_panel', NULL, 'fas fa-briefcase', 13, 1, '2026-02-26 13:38:11', '2026-02-26 15:50:14'),
(20, 'Reports', 'reports', NULL, 'fas fa-chart-line', 14, 1, '2026-02-26 13:39:05', '2026-02-26 15:50:25'),
(22, 'Student Allocation', 'student_allocation', 18, 'fas fa-user-tag', 1, 1, '2026-02-26 14:00:47', '2026-02-26 14:00:47'),
(23, 'Attendance', 'attendance', 18, 'fas fa-calendar-check', 2, 1, '2026-02-26 14:01:39', '2026-02-26 14:01:39'),
(24, 'Classes', 'classes', 18, 'fas fa-book-open', 3, 1, '2026-02-26 14:02:11', '2026-02-26 14:02:11'),
(25, 'Assessment', 'assessment', 18, 'fas fa-tasks', 4, 1, '2026-02-26 14:02:35', '2026-02-26 14:02:35'),
(26, 'Internship Report', 'internship_report', 18, 'fas fa-file-alt', 5, 1, '2026-02-26 14:02:58', '2026-02-26 14:02:58'),
(27, 'Certificate Status', 'certificate_status', 18, 'fas fa-certificate', 6, 1, '2026-02-26 14:03:20', '2026-02-26 14:03:20'),
(28, 'Interview Schedule', 'interviews', 19, 'fas fa-calendar-alt', 1, 1, '2026-02-26 14:04:37', '2026-02-26 14:04:37'),
(29, 'Placement', 'placements', 19, 'fas fa-building', 2, 1, '2026-02-26 14:04:59', '2026-02-26 14:04:59'),
(32, 'Dashboard', 'dashboard/frontoffice', NULL, 'fas fa-home', 1, 1, '2026-02-27 10:25:41', '2026-02-27 10:25:41'),
(33, 'Dashboard', 'dashboard/hr', NULL, 'fas fa-home', 1, 1, '2026-02-27 10:26:33', '2026-02-27 10:26:33'),
(34, 'Dashboard', 'dashboard/staff', NULL, 'fas fa-home', 1, 1, '2026-02-27 10:27:04', '2026-02-27 10:27:04'),
(35, 'Marketing Panel', 'marketing_panel', NULL, 'fas fa-bullhorn', 15, 1, '2026-02-27 11:43:41', '2026-02-27 11:43:41'),
(36, 'Dashboard', 'dashboard/marketing', 35, 'fas fa-home', 1, 1, '2026-02-27 11:44:41', '2026-02-27 11:44:41'),
(37, 'Lead Management', 'lead_management', 35, 'fas fa-user-tie', 2, 1, '2026-02-27 11:45:34', '2026-02-27 11:45:34'),
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
(53, 'Registered Students', 'students/registered', 16, 'fas fa-user-graduate', 1, 1, '2026-03-10 09:53:54', '2026-03-10 09:53:54'),
(54, 'Assign Students', 'students/assign', 16, 'fas fa-user-tag', 2, 1, '2026-03-10 09:53:54', '2026-03-10 09:53:54'),
(55, 'Excel Upload', 'leads/import', 12, 'fas fa-file-excel', 3, 1, '2026-03-10 11:20:41', '2026-03-10 11:20:41');

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
(1, 1, 2, 2, 2026, 3, 50000.00, 10.00, 'sample', 'active', 3, '2026-03-07 16:38:46', '2026-03-07 16:38:46'),
(2, 1, 3, 3, 2026, 3, 75000.00, 10.00, 'Do Well', 'active', 3, '2026-03-09 10:16:10', '2026-03-09 10:16:10'),
(3, 1, 5, 6, 2026, 3, 50000.00, 10.00, NULL, 'active', 3, '2026-03-09 10:30:08', '2026-03-09 10:30:08');

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

INSERT INTO `registrations` (`id`, `registration_no`, `enquiry_id`, `branch_id`, `reg_type`, `source_type`, `joined_on`, `enquiry_snapshot_name`, `enquiry_snapshot_phone`, `enquiry_snapshot_email`, `program_name`, `batch_name`, `total_fee`, `discount_amount`, `final_fee`, `paid_amount`, `balance_amount`, `payment_status`, `notes`, `registration_status`, `assigned_to`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'REG-202603-0002', 1, 1, 'internship', 'lead', '2026-03-06', 'Arun', '7896541236', 'arun@gmail.com', 'Flutter Development', NULL, 1500.00, 0.00, 1500.00, 1500.00, 0.00, 'paid', 'This is sample one', 'active', 2, 2, '2026-03-06 17:07:31', '2026-03-07 11:09:46'),
(5, 'REG-202603-0003', 2, 1, 'course', 'lead', '2026-03-07', 'Raghul', '9942133944', 'raghul@gmail.com', 'Data Analytics', 'Morning Batch', 15000.00, 0.00, 15000.00, 0.00, 15000.00, 'unpaid', 'This stident will join soon', 'draft', 2, 2, '2026-03-07 11:36:00', '2026-03-07 11:36:49'),
(7, 'REG-202603-0004', NULL, 1, 'course', 'direct', '2026-03-07', 'Ranjith', '7896541236', 'ranjith@gmail.com', 'AWS', 'Morning Batch', 15000.00, 0.00, 15000.00, 0.00, 15000.00, 'unpaid', 'He will be willing to join from monday', 'active', 2, 2, '2026-03-07 13:21:18', '2026-03-07 13:21:18'),
(8, NULL, 3, 1, 'course', 'direct', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid', NULL, 'draft', 2, 2, '2026-03-11 09:00:06', '2026-03-11 09:00:06');

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
(1, 1, 1, 2, 2, 2, 500.00, '2026-03-07', 'cash', 'partial', '11234567890', 'RCPT-202603-0001', 'approved', 'this is sample', '2026-03-07 09:58:58', '2026-03-07 09:58:58'),
(2, 1, 1, 2, 2, 2, 1000.00, '2026-03-07', 'cash', 'partial', '11234567891', 'RCPT-202603-0002', 'approved', 'He paid full amount', '2026-03-07 11:09:46', '2026-03-07 11:09:46');

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

INSERT INTO `registration_profiles` (`id`, `registration_id`, `student_name`, `gender`, `dob`, `address`, `qualification`, `college_name`, `year_of_passout`, `parent_name`, `parent_phone`, `parent_occupation`, `emergency_contact`, `aadhaar_no`, `photo_path`, `signature_path`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, 'Arun', 'male', '1987-07-21', 'Ram Nagar', 'MCA', NULL, '2010', 'Ram', '796541230114', 'Sample', '987452361', '1221322344325353', NULL, NULL, 'He will come to class by monday', '2026-03-06 17:14:52', '2026-03-07 10:34:59'),
(2, 5, 'Raghul', 'male', '1987-05-22', 'Ram Nagar', 'MCA', 'Anna Univercity', '2010', 'Ram', '796541230114', 'Sample', '987452361', '1221322344325353', NULL, NULL, 'Want to follow him', '2026-03-07 11:36:49', '2026-03-07 11:36:49'),
(3, 7, 'Ranjith', 'male', '1997-01-21', 'Ram Nagar', 'MCA', 'Anna Univercity', '2010', 'Ram', '796541230114', 'Sample', '987452361', '1221322344325353', NULL, NULL, 'This is sample', '2026-03-07 13:21:18', '2026-03-07 13:21:18');

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
(1, 'Super Admin', 'dashboard/superadmin', 1, 0, 1, '2026-02-24 14:43:56', '2026-02-27 13:02:24'),
(2, 'Front Office', 'dashboard/frontoffice', 0, 1, 1, '2026-02-26 11:54:51', '2026-03-07 16:14:41'),
(3, 'HR', 'dashboard/hr', 0, 1, 1, '2026-02-26 11:55:14', '2026-03-07 16:14:59'),
(4, 'Staff', 'dashboard/staff', 0, 1, 1, '2026-02-26 11:55:20', '2026-03-07 16:14:54'),
(6, 'Marketing', 'dashboard/marketing', 0, 1, 1, '2026-02-27 11:35:47', '2026-03-07 16:14:46'),
(7, 'Test', 'dashboard/test', 0, 0, 1, '2026-02-27 13:11:38', '2026-03-09 18:05:34');

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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role_id`, `menu_id`, `can_view`, `can_add`, `can_edit`, `can_delete`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 0, 0, 0, '2026-02-25 11:07:53', '2026-02-25 11:07:53'),
(4, 1, 5, 1, 1, 1, 1, '2026-03-09 14:10:31', '2026-03-09 14:10:31'),
(5, 1, 6, 1, 1, 1, 1, '2026-02-26 11:25:51', '2026-02-26 11:25:51'),
(6, 1, 7, 1, 1, 1, 1, '2026-02-26 11:54:10', '2026-02-26 11:54:10'),
(9, 1, 10, 1, 1, 1, 1, '2026-02-26 13:03:48', '2026-02-26 13:03:48'),
(10, 1, 11, 1, 1, 1, 1, '2026-02-26 13:05:42', '2026-02-26 13:05:42'),
(11, 1, 12, 1, 1, 1, 1, '2026-02-26 13:29:10', '2026-02-26 13:29:10'),
(12, 1, 13, 1, 1, 1, 1, '2026-02-26 13:30:54', '2026-02-26 13:30:54'),
(13, 1, 14, 1, 1, 1, 1, '2026-02-26 13:33:00', '2026-02-26 13:33:00'),
(14, 1, 15, 1, 1, 1, 1, '2026-02-26 13:33:56', '2026-02-26 13:33:56'),
(15, 1, 16, 1, 1, 1, 1, '2026-02-26 13:35:03', '2026-02-26 13:35:03'),
(16, 1, 17, 1, 1, 1, 1, '2026-02-26 13:36:05', '2026-02-26 13:36:05'),
(17, 1, 18, 1, 1, 1, 1, '2026-02-26 13:37:16', '2026-02-26 13:37:16'),
(18, 1, 19, 1, 1, 1, 1, '2026-02-26 13:38:11', '2026-02-26 13:38:11'),
(19, 1, 20, 1, 1, 1, 1, '2026-02-26 13:39:05', '2026-02-26 13:39:05'),
(21, 1, 22, 1, 1, 1, 1, '2026-02-26 14:00:47', '2026-02-26 14:00:47'),
(22, 1, 23, 1, 1, 1, 1, '2026-02-26 14:01:39', '2026-02-26 14:01:39'),
(23, 1, 24, 1, 1, 1, 1, '2026-02-26 14:02:11', '2026-02-26 14:02:11'),
(24, 1, 25, 1, 1, 1, 1, '2026-02-26 14:02:35', '2026-02-26 14:02:35'),
(25, 1, 26, 1, 1, 1, 1, '2026-02-26 14:02:58', '2026-02-26 14:02:58'),
(26, 1, 27, 1, 1, 1, 1, '2026-02-26 14:03:20', '2026-02-26 14:03:20'),
(27, 1, 28, 1, 1, 1, 1, '2026-02-26 14:04:37', '2026-02-26 14:04:37'),
(28, 1, 29, 1, 1, 1, 1, '2026-02-26 14:04:59', '2026-02-26 14:04:59'),
(103, 1, 32, 1, 1, 1, 1, '2026-02-27 10:25:41', '2026-02-27 10:25:41'),
(104, 1, 33, 1, 1, 1, 1, '2026-02-27 10:26:33', '2026-02-27 10:26:33'),
(105, 1, 34, 1, 1, 1, 1, '2026-02-27 10:27:04', '2026-02-27 10:27:04'),
(160, 4, 10, 0, 0, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(161, 4, 32, 0, 0, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(162, 4, 33, 0, 0, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(163, 4, 34, 1, 1, 1, 1, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(164, 4, 7, 0, 0, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(165, 4, 1, 0, 0, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(166, 4, 11, 0, 0, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(168, 4, 12, 0, 0, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(169, 4, 13, 0, 0, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(170, 4, 14, 0, 0, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(171, 4, 15, 0, 0, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(172, 4, 16, 0, 0, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(173, 4, 17, 0, 0, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(174, 4, 18, 1, 1, 1, 1, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(175, 4, 19, 0, 0, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(176, 4, 20, 1, 1, 1, 1, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(177, 4, 6, 0, 0, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(179, 4, 22, 1, 0, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(180, 4, 23, 1, 1, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(181, 4, 24, 1, 1, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(182, 4, 25, 1, 1, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(183, 4, 26, 1, 1, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(184, 4, 27, 1, 0, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(185, 4, 28, 0, 0, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(186, 4, 29, 0, 0, 0, 0, '2026-02-27 10:28:20', '2026-02-27 10:28:20'),
(187, 1, 35, 1, 1, 1, 1, '2026-02-27 11:43:41', '2026-02-27 11:43:41'),
(188, 1, 36, 1, 1, 1, 1, '2026-02-27 11:44:41', '2026-02-27 11:44:41'),
(189, 1, 37, 1, 1, 1, 1, '2026-02-27 11:45:34', '2026-02-27 11:45:34'),
(250, 1, 38, 1, 1, 1, 1, '2026-02-27 13:20:11', '2026-02-27 13:20:11'),
(251, 7, 10, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(252, 7, 32, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(253, 7, 33, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(254, 7, 34, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(255, 7, 38, 1, 1, 1, 1, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(256, 7, 7, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(257, 7, 1, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(258, 7, 11, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(260, 7, 12, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(261, 7, 13, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(262, 7, 14, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(263, 7, 15, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(264, 7, 16, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(265, 7, 17, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(266, 7, 18, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(267, 7, 19, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(268, 7, 20, 1, 1, 1, 1, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(269, 7, 35, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(270, 7, 6, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(272, 7, 22, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(273, 7, 23, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(274, 7, 24, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(275, 7, 25, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(276, 7, 26, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(277, 7, 27, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(278, 7, 28, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(279, 7, 29, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(280, 7, 36, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(281, 7, 37, 0, 0, 0, 0, '2026-02-27 13:29:45', '2026-02-27 13:29:45'),
(282, 1, 39, 1, 1, 1, 1, '2026-02-27 13:52:12', '2026-02-27 13:52:12'),
(283, 1, 40, 1, 1, 1, 1, '2026-02-27 13:52:55', '2026-02-27 13:52:55'),
(284, 1, 41, 1, 1, 1, 1, '2026-02-27 13:53:35', '2026-02-27 13:53:35'),
(548, 1, 48, 1, 1, 1, 1, '2026-03-07 15:47:17', '2026-03-07 15:47:17'),
(549, 1, 49, 1, 1, 1, 1, '2026-03-07 15:48:57', '2026-03-07 15:48:57'),
(550, 1, 50, 1, 1, 1, 1, '2026-03-07 15:50:10', '2026-03-07 15:50:10'),
(551, 1, 51, 1, 1, 1, 1, '2026-03-07 15:50:46', '2026-03-07 15:50:46'),
(552, 1, 52, 1, 1, 1, 1, '2026-03-07 15:51:19', '2026-03-07 15:51:19'),
(553, 3, 10, 0, 0, 0, 0, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(554, 3, 32, 0, 0, 0, 0, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(555, 3, 33, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(556, 3, 34, 0, 0, 0, 0, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(557, 3, 38, 0, 0, 0, 0, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(558, 3, 7, 0, 0, 0, 0, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(559, 3, 1, 0, 0, 0, 0, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(560, 3, 11, 0, 0, 0, 0, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(562, 3, 12, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(563, 3, 13, 1, 0, 0, 0, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(564, 3, 14, 0, 0, 0, 0, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(565, 3, 15, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(566, 3, 16, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(567, 3, 17, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(568, 3, 18, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(569, 3, 19, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(570, 3, 20, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(571, 3, 35, 0, 0, 0, 0, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(572, 3, 48, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(573, 3, 6, 0, 0, 0, 0, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(575, 3, 46, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(576, 3, 47, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(577, 3, 39, 0, 0, 0, 0, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(578, 3, 40, 1, 0, 0, 0, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(579, 3, 41, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(580, 3, 42, 0, 0, 0, 0, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(581, 3, 43, 0, 0, 0, 0, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(582, 3, 44, 0, 0, 0, 0, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(583, 3, 45, 0, 0, 0, 0, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(584, 3, 22, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(585, 3, 23, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(586, 3, 24, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(587, 3, 25, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(588, 3, 26, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(589, 3, 27, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(590, 3, 28, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(591, 3, 29, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(592, 3, 36, 0, 0, 0, 0, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(593, 3, 37, 0, 0, 0, 0, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(594, 3, 49, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(595, 3, 50, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(596, 3, 51, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(597, 3, 52, 1, 1, 1, 1, '2026-03-07 15:55:50', '2026-03-07 15:55:50'),
(598, 2, 10, 0, 0, 0, 0, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(599, 2, 32, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(600, 2, 33, 0, 0, 0, 0, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(601, 2, 34, 0, 0, 0, 0, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(602, 2, 38, 0, 0, 0, 0, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(603, 2, 7, 0, 0, 0, 0, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(604, 2, 1, 0, 0, 0, 0, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(605, 2, 11, 0, 0, 0, 0, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(607, 2, 12, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(608, 2, 13, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(609, 2, 14, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(610, 2, 15, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(611, 2, 16, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(612, 2, 17, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(613, 2, 18, 1, 0, 0, 0, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(614, 2, 19, 0, 0, 0, 0, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(615, 2, 20, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(616, 2, 35, 0, 0, 0, 0, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(617, 2, 48, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(618, 2, 6, 0, 0, 0, 0, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(620, 2, 46, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(621, 2, 47, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(622, 2, 39, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(623, 2, 40, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(624, 2, 41, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(625, 2, 42, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(626, 2, 43, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(627, 2, 44, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(628, 2, 45, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(629, 2, 22, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(630, 2, 23, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(631, 2, 24, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(632, 2, 25, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(633, 2, 26, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(634, 2, 27, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(635, 2, 28, 0, 0, 0, 0, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(636, 2, 29, 0, 0, 0, 0, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(637, 2, 36, 0, 0, 0, 0, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(638, 2, 37, 0, 0, 0, 0, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(639, 2, 49, 0, 0, 0, 0, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(640, 2, 50, 0, 0, 0, 0, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(641, 2, 51, 1, 1, 1, 1, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(642, 2, 52, 0, 0, 0, 0, '2026-03-07 15:56:54', '2026-03-07 15:56:54'),
(643, 6, 10, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(644, 6, 32, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(645, 6, 33, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(646, 6, 34, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(647, 6, 38, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(648, 6, 7, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(649, 6, 1, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(650, 6, 11, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(652, 6, 12, 1, 1, 1, 1, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(653, 6, 13, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(654, 6, 14, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(655, 6, 15, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(656, 6, 16, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(657, 6, 17, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(658, 6, 18, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(659, 6, 19, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(660, 6, 20, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(661, 6, 35, 1, 1, 1, 1, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(662, 6, 48, 1, 1, 1, 1, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(663, 6, 6, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(665, 6, 46, 1, 1, 1, 1, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(666, 6, 47, 1, 1, 1, 1, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(667, 6, 39, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(668, 6, 40, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(669, 6, 41, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(670, 6, 42, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(671, 6, 43, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(672, 6, 44, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(673, 6, 45, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(674, 6, 22, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(675, 6, 23, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(676, 6, 24, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(677, 6, 25, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(678, 6, 26, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(679, 6, 27, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(680, 6, 28, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(681, 6, 29, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(682, 6, 36, 1, 1, 1, 1, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(683, 6, 37, 1, 1, 1, 1, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(684, 6, 49, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(685, 6, 50, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(686, 6, 51, 1, 1, 1, 1, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(687, 6, 52, 0, 0, 0, 0, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(688, 1, 43, 1, 1, 1, 1, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(689, 1, 42, 1, 1, 1, 1, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(690, 1, 44, 1, 1, 1, 1, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(691, 6, 45, 1, 1, 1, 1, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(692, 1, 46, 1, 1, 1, 1, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(693, 1, 47, 1, 1, 1, 1, '2026-03-07 15:57:12', '2026-03-07 15:57:12'),
(694, 1, 53, 1, 1, 1, 0, '2026-03-10 09:53:54', '2026-03-10 09:53:54'),
(695, 2, 53, 1, 1, 1, 0, '2026-03-10 09:53:54', '2026-03-10 09:53:54'),
(696, 1, 54, 1, 1, 1, 0, '2026-03-10 09:53:54', '2026-03-10 09:53:54'),
(697, 2, 54, 1, 1, 1, 0, '2026-03-10 09:53:54', '2026-03-10 09:53:54'),
(701, 3, 55, 1, 1, 1, 1, '2026-03-10 11:20:41', '2026-03-10 11:20:41'),
(702, 2, 55, 1, 1, 1, 1, '2026-03-10 11:20:41', '2026-03-10 11:20:41'),
(703, 6, 55, 1, 1, 1, 1, '2026-03-10 11:20:41', '2026-03-10 11:20:41'),
(704, 1, 55, 1, 1, 1, 1, '2026-03-10 11:20:41', '2026-03-10 11:20:41');

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
(1, 1, 1, 'admin', 'admin@gmail.com', NULL, '$2y$10$XSJvOcrYzm1Si2hMt4XBAO8woxn0B2kN1/7sQAFN5GXTqgWZJx6r6', 0, '2026-03-11 08:55:16', '::1', 1, '2026-02-24 14:43:56', '2026-03-11 08:55:16', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 1, 2, 'John', 'webdeveloper05.ats@gmail.com', '9942133944', '$2y$10$RndVVNRzU5.otdBKdQ0GrucyKFszBSxuf1eBKo2IdNi7mKpDckkM.', 0, '2026-03-11 08:56:19', '::1', 1, '2026-02-27 09:40:22', '2026-03-11 08:56:19', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '0093ac1c5b8d78fa1f5199e2', '$2y$10$zGpL9ayEYjB2.LiOVbRPIOFS0y0v8FUjwnyyuycoHjduFELGqHtxC', '2026-03-25 08:56:19', NULL, NULL),
(3, 1, 3, 'Michael', 'michael@gmail.com', '9942133944', '$2y$10$1v.h78Rz.VEfLfzF4mzUIOfYt/ucjkL0qpq4i.57N7AkUHuWyBKF.', 0, '2026-03-10 08:36:01', '::1', 1, '2026-02-27 09:55:00', '2026-03-10 08:36:01', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(4, 1, 4, 'Fredrick', 'fredrick@gmail.com', '7896541236', '$2y$10$NyGE/HNP0cteOIu13cNBrulKKUxPjbgJ1igsjcY8nSiuEuJlL/EiS', 0, '2026-03-11 08:36:33', '::1', 1, '2026-02-27 09:55:31', '2026-03-11 08:36:33', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(5, 1, 6, 'Fredrick John', 'fredrickjohn@gmail.com', '9942133944', '$2y$10$8xOZr5zGQCXQbah699/iv.scVJCDTAFOBaYSU3rT8TlfjvF2f1BSy', 0, '2026-03-09 14:37:01', '::1', 1, '2026-02-27 11:48:43', '2026-03-09 14:37:01', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(6, 1, 7, 'Test', 'test@gmail.com', '9874563210', '$2y$10$CYWliVwyZj8aAXljmNjrke8QM/jhkktGlOw46obUIp1N8buTUb4/q', 0, '2026-02-27 13:30:01', '::1', 1, '2026-02-27 13:12:51', '2026-02-27 13:30:01', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(7, 1, 2, 'Suresh', 'suresh@gmail.com', '9874563214', '$2y$10$.Chm89m3LfxsgYaKMzd47OCMBAp5nVfXpwHFWK7R7DrLKcev3jIm6', 0, '2026-03-09 14:39:05', '::1', 1, '2026-03-09 14:38:38', '2026-03-09 14:39:05', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`user_id`,`course_id`,`attendance_date`),
  ADD KEY `course_id` (`course_id`);

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
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `course_code` (`course_code`),
  ADD KEY `idx_branch` (`branch_id`),
  ADD KEY `idx_status` (`status`);

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
  ADD KEY `verification_status` (`verification_status`);

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
-- Indexes for table `fee_structures`
--
ALTER TABLE `fee_structures`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`);

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
  ADD KEY `idx_leads_import_batch_id` (`import_batch_id`);

--
-- Indexes for table `lead_import_batches`
--
ALTER TABLE `lead_import_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lib_branch` (`branch_id`),
  ADD KEY `idx_lib_created_by` (`created_by`);

--
-- Indexes for table `menus`
--
ALTER TABLE `menus`
  ADD PRIMARY KEY (`id`);

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
  ADD KEY `idx_remember_selector` (`remember_selector`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enquiries`
--
ALTER TABLE `enquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `enquiry_followups`
--
ALTER TABLE `enquiry_followups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `enquiry_followup_files`
--
ALTER TABLE `enquiry_followup_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fee_structures`
--
ALTER TABLE `fee_structures`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `lead_import_batches`
--
ALTER TABLE `lead_import_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `menus`
--
ALTER TABLE `menus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT for table `monthly_targets`
--
ALTER TABLE `monthly_targets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `registration_payments`
--
ALTER TABLE `registration_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `registration_profiles`
--
ALTER TABLE `registration_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=708;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `fk_reg_enquiry` FOREIGN KEY (`enquiry_id`) REFERENCES `enquiries` (`id`) ON DELETE CASCADE;

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
