-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Mar 30, 2026 at 03:40 AM
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
(1, 12, 1, 8, 55.00, 64.00, 75.00, 64.67, '2026-03-14 09:46:55', '2026-03-14 09:47:12'),
(3, 15, 1, 4, 50.00, 50.00, 50.00, 50.00, '2026-03-17 06:42:22', '2026-03-24 06:44:24'),
(4, 18, 1, 4, 100.00, 100.00, 100.00, 100.00, '2026-03-19 10:31:40', '2026-03-19 10:31:40');

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
(8, 15, 0, 0, 1, '2026-03-17', 'Present', 'Basic python', 'Arithemetic operations', NULL, NULL, NULL, 4, '2026-03-17 06:40:16', '2026-03-17 06:40:16'),
(9, 15, 0, 0, 1, '2026-03-18', 'Absent', NULL, NULL, 'no', NULL, NULL, 4, '2026-03-19 10:20:47', '2026-03-19 10:20:47'),
(10, 15, 0, 0, 1, '2026-03-19', 'Absent', NULL, NULL, 'yes', 'wertyuiop', 'parent', 4, '2026-03-19 10:21:13', '2026-03-19 10:21:13'),
(11, 12, 0, 0, 1, '2026-03-24', 'Present', 'loops', 'mapping', NULL, NULL, NULL, 8, '2026-03-24 06:21:33', '2026-03-24 10:13:52'),
(18, 18, 0, 0, 1, '2026-03-21', 'Present', 'jhgdfb', 'gbdfvsda', NULL, NULL, NULL, 4, '2026-03-24 10:31:15', '2026-03-24 10:31:15'),
(23, 39, 0, 0, 1, '2026-03-27', 'Present', 'Html', 'Welcome page', NULL, NULL, NULL, 15, '2026-03-27 07:19:45', '2026-03-27 07:19:45'),
(28, 39, 0, 0, 1, '2026-03-28', 'Present', 'jiuqsdun', 'Jaiudn', NULL, NULL, NULL, 15, '2026-03-28 04:33:26', '2026-03-28 04:33:26');

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
(1, '2026-03-06', 'ENQ-20260306-0001', 1, 'Arun', '1987-07-21', 'Student', 'male', 'Ram Nagar', 'Sample', '7896541236', 'arun@gmail.com', 'Flutter Development', 'MCA', 2010, 'Anna Univercity', 75.00, 'Ram', 'Sample', '796541230114', NULL, 'Java,HTML', 'Web Designing', 'Internship,Placement Assistance', 1, 'Walk-in', 'NO', NULL, NULL, 'converted', 2, NULL, 'I need internship', '2026-03-06 11:57:44', '2026-03-06 17:07:31', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(2, '2026-03-06', 'ENQ-20260306-0002', 1, 'Raghul', '1987-05-22', 'Student', 'male', 'Ram Nagar', 'Sample', '9942133944', 'raghul@gmail.com', 'Data Analytics', 'MCA', 2010, 'Anna Univercity', 75.00, 'Ram', 'Sample', '796541230114', NULL, 'HTML,PYTHON', 'Data Science', 'Technology Training', 1, 'Other', 'NO', NULL, NULL, 'converted', 2, NULL, NULL, '2026-03-06 12:50:00', '2026-03-07 11:36:00', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(3, '2026-03-07', 'ENQ-20260307-0001', 1, 'Grandway Properties', NULL, 'Owner', 'male', 'Ram Nagar', 'Sample', '98745632145', 'grandway@gmail.com', 'Website Making', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'Other', 'Reference', NULL, NULL, 'converted', 2, NULL, NULL, '2026-03-07 14:48:25', '2026-03-11 09:00:06', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(4, '2026-03-11', 'ENQ-20260311-0001', 1, 'Ram', '1987-01-21', 'Student', 'male', 'Ram Nagar', 'Sample', '9874563210', 'ram@gmail.com', 'Full Stack Webdevelopment', 'MCA', 2010, 'Anna Univercity', 75.00, 'John', 'Sample', '796541230114', NULL, 'HTML,CSS', 'Artificial Intelligence,Data Science', 'Technology Training,Internship', 1, 'Google Search', NULL, NULL, NULL, 'new', 2, NULL, 'Sample', '2026-03-11 05:55:14', '2026-03-11 05:55:14', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(6, '2026-03-11', 'ENQ-20260311-0003', 1, 'Wednesday', '1987-01-21', 'Student', 'male', 'hgduqhdoiwj', 'wertyuio', '9987654321', 'wed@gmail.com', 'Course', 'MCA', 2010, 'asdfghjk', 76.00, 'sample', 'ertyuio', '9876543210', NULL, 'HTML', 'Artificial Intelligence,Full Stack Web Development', 'Placement Assistance', 1, 'Google Search,Other', 'Reference', NULL, NULL, 'converted', 2, NULL, 'dfghbjkl', '2026-03-11 12:31:22', '2026-03-11 12:33:18', 2, 2, '2401:4900:8825:644e:c116:fd72:e0c1:8291', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(7, '2026-03-12', 'ENQ-20260312-0001', 1, 'Ramesh', '2001-01-01', 'Student', 'male', 'Ram nagar masjid, Nehru street', 'qwertyui', '9876543210', 'ramesh@gmail.com', 'MERN', 'BE', 2021, 'ATS', 89.00, 'wertyuiop', 'qwertyuiop', '9876543210', NULL, 'PHP', 'Artificial Intelligence,Data Science', 'Technology Training,Internship', 1, 'Google Search', NULL, NULL, NULL, 'converted', 7, NULL, 'wertyuiop[', '2026-03-12 05:16:00', '2026-03-17 03:36:19', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(8, '2026-03-14', 'ENQ-20260314-0001', 1, 'Mohammed Irfan', '1996-01-14', 'Student', 'male', 'Ram Nagar', 'Sample', '9445566677', 'mohd.irfan@example.com', 'Cloud Computing', 'MCA', 2010, 'Anna Univercity', 75.00, 'rg', 'Sample', '796541230114', NULL, 'fgreh', 'Java', 'Placement Assistance', 1, 'Friends/Reference', 'Reference', NULL, NULL, 'converted', 2, NULL, NULL, '2026-03-14 09:26:29', '2026-03-17 03:29:30', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(9, '2026-03-14', 'ENQ-20260314-0002', 1, 'Anjali Nair', '2000-05-12', 'Student', 'female', 'Ram Nagar', 'Sample', '9654321870', 'anjali.nair@example.com', 'Graphic Design', NULL, 2010, 'Anna Univercity', 75.00, 'dad', 'Sample', '796541230114', NULL, 'sddj', 'PHP & MySQL', 'Placement Assistance', 1, 'Facebook', 'Instagram', NULL, NULL, 'converted', 2, NULL, 'erdfrthrthr', '2026-03-14 09:34:49', '2026-03-17 05:00:18', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(10, '2026-03-14', 'ENQ-20260314-0003', 1, 'Divya K', '0986-11-22', 'Student', 'female', 'Ram Nagar', 'Sample', '9556677889', 'divya.k@example.com', 'MS Office', 'MCA', 2010, 'Anna Univercity', 75.00, 'asdcsafvsdg', 'sfcsdfsdfwsf', '2132536344364', NULL, 'xffgbnfgxfdbfr', 'Full Stack Web Development', 'Placement Assistance', 0, 'Instagram', 'Google Ads', NULL, NULL, 'converted', 2, NULL, NULL, '2026-03-14 12:11:48', '2026-03-17 03:30:26', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(11, '2026-03-16', 'ENQ-20260316-0001', 1, 'Hema', NULL, 'Student', 'female', 'Ram Nagar', 'Sample', '78965412301', 'hema@gmail.com', 'Full Stack Webdevelopment', 'MCA', NULL, 'Anna Univercity', 75.00, 'ffer', 'ferferfer', '796541230114', NULL, 'weferferferfrerefe', 'PHP & MySQL', 'Project Development', 0, 'Other', 'Reference', NULL, NULL, 'converted', 2, NULL, 'dwedfew', '2026-03-16 12:30:04', '2026-03-17 04:16:32', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(12, '2026-03-17', 'ENQ-20260317-0001', 1, 'Meena Joseph', NULL, 'Student', 'male', 'rtgrtgrtrtrtrtbh', 'Sample', '9345678901', 'meena.joseph@example.com', 'Testing', 'rger', 2010, 'Anna Univercity', 75.00, 'dad', 'Sample', '32435344567', NULL, 'tg45tg45tg4g', 'Data Science,Full Stack Web Development,Web Designing', 'Internship', 1, 'Other', 'Facebook', NULL, NULL, 'converted', 7, NULL, 'dfvdfvrfg', '2026-03-17 04:49:25', '2026-03-17 07:02:17', 7, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(13, '2026-03-18', 'ENQ-20260317-0002', 1, 'Joseph Glyzen', '2004-05-17', 'Student', 'male', 'Coimbatore', NULL, '1234567890', 'glyzen@gmail.com', 'AI, Python, ML and Dl', 'cs', 2026, 'Karpgam', 79.00, 'Lorence', 'Driver', '7418529635', NULL, 'python , cpp', NULL, NULL, 1, NULL, NULL, NULL, NULL, 'converted', 1, NULL, NULL, '2026-03-17 06:27:12', '2026-03-25 04:56:03', 1, NULL, '157.51.60.39', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(21, '2026-03-17', 'ENQ-20260317-0004', 1, 'jeevi', '1985-01-21', 'Student', 'female', 'Ram Nagar', 'Sample', '987654345', 'jeevi@gmail.com', 'Full stack development', 'MCA', 2010, 'Anna Univercity', 75.00, 'rg', 'sfcsdfsdfwsf', '2132536344364', NULL, 'wsddwef', 'Java', 'Internship', 0, 'Other', 'Website', NULL, NULL, 'converted', 2, NULL, 'edfwewe', '2026-03-17 07:08:55', '2026-03-17 07:15:57', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(22, '2026-03-17', 'ENQ-20260317-0006', 1, 'Ezekil jackson', '2026-03-13', 'Student', 'male', 'Sulur, Coimbatore', NULL, '1234567890', 'jack@gmail.com', 'Data Analytics', 'bsc cs', 2026, 'Karpgam', 79.00, 'Kamarasu', NULL, NULL, NULL, 'Cpp, C, java, python', NULL, NULL, 1, 'Other', 'Reference', NULL, NULL, 'converted', 2, NULL, NULL, '2026-03-17 07:10:55', '2026-03-17 07:16:10', 2, NULL, '157.51.60.39', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(23, '2026-03-17', 'ENQ-20260317-0009', 1, 'ishu', '2025-03-12', 'sdfvc', 'female', 'weujyhgb', '45678', '876543455', 'ishu@gmail.com', 'Full stack development', 'final year', 2026, 'NPRCET', 76.00, 'sadha', 'shop', '098765432', NULL, 'sdfgyt', 'Artificial Intelligence', 'Technology Training', 0, 'Friends/Reference', NULL, NULL, NULL, 'converted', 2, NULL, NULL, '2026-03-17 07:14:18', '2026-03-24 06:17:29', 2, NULL, '2405:201:e015:50bd:e0e7:fd8a:fb71:1f50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(24, '2026-03-19', 'ENQ-20260319-0016', 1, 'Ezekil jackson', '1995-06-13', 'Student', 'male', 'Ram Nagar', 'Sample', '1234567890', 'jack@gmail.com', 'Regular', 'MCA', NULL, 'Anna Univercity', 75.00, 'dad', 'Sample', '32435344567', NULL, 'ggrtgerg', 'Full Stack Web Development', 'Placement Assistance', 0, 'Instagram', 'Reference', NULL, NULL, 'new', 2, NULL, 'thrth', '2026-03-19 10:59:08', '2026-03-19 10:59:08', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(25, '2026-03-19', 'ENQ-20260319-0019', 1, 'sdass', '2026-03-19', 'student', 'male', 'cbe', 'sgdfdhdf', '1234567890', 'nsdndnd@gmail.com', 'php', 'msc', 2026, 'snmv', 87.00, 'not interested', 'not winning', '123456789', NULL, 'php .net sql', 'Web Designing', 'Internship', 1, NULL, 'friends', NULL, NULL, 'new', 2, NULL, 'next week join 12th may', '2026-03-19 11:29:42', '2026-03-19 11:29:42', 2, NULL, '2401:4900:8825:644e:4cb9:1154:e1bf:f296', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(26, '2026-03-19', 'ENQ-20260319-0023', 1, 'errftghbbn', '2026-03-05', 'student', 'female', 'efjhyywfhkqkqkqkqkqkqij', '3hyf3ru21ikf', '123456789', 'w2fd5gthn@gmail.com', 'python', 'bsc cs', 2028, 'nirmala', 98.00, 'kannan', 'businesso', '636335467', NULL, 'php', 'Full Stack Web Development', 'Internship', 1, 'Instagram', 'frieds', NULL, NULL, 'new', 2, NULL, '4jr3frrrrrrrrfyifyifyifyifyifyi', '2026-03-19 12:21:03', '2026-03-19 12:21:03', 2, NULL, '2401:4900:8825:644e:fc9c:4ffe:7ea6:2286', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(27, '2026-03-25', 'ENQ-20260325-0082', 1, 'Monday', '2000-02-21', 'Student', 'male', 'Ram Nagar', 'erfererferg', '987456321145', 'john@gmail.com', 'UI/UX', 'MCA', 2010, 'Anna Univercity', 75.00, 'rg', 'Sample', '32435344567', NULL, 'htgfh', 'Artificial Intelligence', 'Technology Training', 0, 'Website', NULL, NULL, NULL, 'new', 2, NULL, 'dsf', '2026-03-25 04:44:16', '2026-03-25 04:44:16', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(28, '2026-03-25', 'ENQ-20260325-0087', 1, 'Priya Sharma', '2000-01-12', 'Student', 'female', 'Ram Nagar', 'erfererferg', '9123456780', 'priya.sharma@example.com', 'UI UX Design', 'MCA', 2010, 'Anna Univercity', 75.00, 'asdcsafvsdg', 'Sample', '32435344567', NULL, 'jhgbf', 'Artificial Intelligence', 'Technology Training', 0, 'Other', 'Instagram', NULL, NULL, 'converted', 7, NULL, 'dcsdc', '2026-03-25 05:16:52', '2026-03-25 05:17:52', 7, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(29, '2026-03-25', 'ENQ-20260325-0105', 1, 'Sundar', '1999-06-08', 'Student', NULL, 'Ram Nagar', 'erfererferg', '9876543210', 'sundar@gmail.com', 'Full Stack', 'MCA', 2010, 'Anna Univercity', 75.00, 'John', 'Sample', '3243534456', 'sampl@gmail.com', 'rfd', 'Artificial Intelligence', 'Technology Training', 0, 'Instagram,Other', 'Website', NULL, NULL, 'converted', 2, NULL, NULL, '2026-03-25 10:05:58', '2026-03-25 10:06:59', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(30, '2026-03-25', 'ENQ-20260325-0108', 1, 'Johnson', '2000-06-15', 'Owner', 'male', 'Ram Nagar', 'erfererferg', '9876543210', 'johnson@gmail.com', 'Full Stack Webdevelopment', 'MCA', 2010, 'Anna Univercity', 75.00, 'Ram', 'Sample', '7965412301', 'sampl@gmail.com', 'fghjkl;', 'Web Designing', NULL, 0, 'Other', 'Website', NULL, NULL, 'converted', 2, NULL, NULL, '2026-03-25 10:09:28', '2026-03-25 10:10:33', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(31, '2026-03-26', 'ENQ-20260326-0111', 1, 'Pavan', '2001-01-01', 'Student', 'male', 'Ram nagar masjid, Nehru street', 'qwertyu', '9876543210', 'pavan@gmail.com', 'FSWD', 'BE', 2021, 'ATS', 89.00, 'wertyuiop', 'qwertyuiop', '9876543210', NULL, 'qwertyui', 'Java', 'Technology Training', 1, 'Walk-in', 'Walk-in', NULL, NULL, 'converted', 2, NULL, 'qwerh', '2026-03-26 03:39:45', '2026-03-26 03:40:47', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(32, '2026-03-26', 'ENQ-20260326-0114', 1, 'Thanish', '2001-01-01', 'Student', 'male', 'Ram nagar masjid, Nehru street', 'qwertyui', '9876543210', 'thanish@gmail.com', 'MERN', 'BE', 2021, 'ATS', 89.00, 'wertyuiop', 'qwertyuiop', '9876543210', NULL, 'wertyu', 'Web Designing', 'Placement Assistance', 1, 'Walk-in', 'Walk-in', NULL, NULL, 'converted', 2, NULL, 'wertyu', '2026-03-26 03:43:31', '2026-03-26 03:44:19', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(33, '2026-03-26', 'ENQ-20260326-0118', 1, 'Anu', '2001-01-01', 'Student', 'female', 'Ram nagar masjid, Nehru street', 'qwertyui', '9876543210', 'anu@gmail.com', 'MERN', 'BE', 2021, 'ATS', 89.00, 'wertyuiop', 'qwertyuiop', '9876543210', 'webdeveloper005.ats@gmail.com', 'qwertyuiop', 'PHP & MySQL', 'Placement Assistance', 0, 'Walk-in', 'Walk-in', NULL, NULL, 'converted', 2, NULL, 'qwertyuiop[', '2026-03-26 05:46:46', '2026-03-26 05:47:54', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(34, '2026-03-27', 'ENQ-20260327-0122', 1, 'Sakthivel', '2004-09-02', 'Student', 'male', 'Dindigul', NULL, '6381814891', 'sakthivelpcseb@gmail.com', 'Full Stack Development', 'B.E', 2026, 'NPR', 80.00, 'periyasamy', 'Bussiness', '9790222576', 'sakthivelpcseb@gmail.com', 'Javascript,Java,Python', 'Full Stack Web Development,Web Designing', 'Project Development', 1, 'Other', 'Reference', NULL, NULL, 'converted', 10, NULL, 'Test case 2', '2026-03-27 07:11:41', '2026-03-27 07:16:38', 10, NULL, '2401:4900:8825:644e:2835:4470:a4cc:1971', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(35, '2026-03-27', 'ENQ-20260327-0125', 1, 'Arjun Kumar', '2000-10-22', 'Student', 'male', 'Ram Nagar', 'erfererferg', '9876543210', 'arjun.kumar@gmail.com', 'Full Stack Development', 'MCA', 2010, 'Anna Univercity', 75.00, 'Ram', 'Sample', '3243534456', 'sampl@gmail.com', 'ewf', 'Artificial Intelligence', 'Technology Training', 0, 'Instagram,Other', 'Website', NULL, NULL, 'converted', 2, NULL, NULL, '2026-03-27 07:31:52', '2026-03-27 07:32:45', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(36, '2026-03-27', 'ENQ-20260327-0127', 1, 'Thowfig narayanan', '2002-06-11', 'Student', 'male', '!4/A , RVS Nagar, Sulur New Bus stand,\r\nSulur, Coimbatore, 641402', 'omniversal_handsome', '9952235465', 'glyzenats345@gmail.com', 'Python with AI', 'bsc cs', 2026, 'Karpagam College of Engineering', 79.00, 'Ameer', 'Driver', '9952235465', 'ats.pythondeveloper08@gmail.com', 'C,C++', 'Python,Digital Marketing', 'Internship,Project Development', 1, 'Walk-in', 'Website', NULL, NULL, 'converted', 7, NULL, NULL, '2026-03-27 07:49:47', '2026-03-27 07:54:28', 7, NULL, '2409:40f4:2157:1908:69b4:aa59:b64e:4b3d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(37, '2026-03-27', 'ENQ-20260327-0130', 1, 'Abraham lingan', NULL, 'Student', NULL, '3rd appartment, behind new busstand, sulur, Coimbatore', 'omniversal_handsome', '9952235465', 'ats.pythondeveloper08@gmail.com', 'UiUX', 'BCA', 2026, 'Kathir College of arts and science', 88.00, 'Kalaivani', 'Auditor', '9952235465', 'glyzenj@gmail.com', '-', 'Full Stack Web Development', 'Technology Training,Internship', 0, 'Instagram', 'Instagram', 'uploads/enquiries/1774602247_2947.jpeg', NULL, 'converted', 7, NULL, NULL, '2026-03-27 09:04:07', '2026-03-27 09:05:57', 7, NULL, '2409:40f4:215f:a1cf:218e:29ba:19c0:f622', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(38, '2026-03-27', 'ENQ-20260327-0133', 1, 'Rakshasthira', NULL, 'Student', 'female', 'NH street , Siruvani tank, Sulur , coimbatore', 'omniversal_handsome', '9952235465', 'glyzenats345@gmail.com', 'AWS', 'BCA', 2026, 'Hindustan college of arts and science', 84.00, 'Raghavan', 'Dojo Coach', '9952235465', 'ats.pythondeveloper08@gmail.com', 'java', 'Python', NULL, 1, 'Other', 'Reference', NULL, NULL, 'converted', 7, NULL, NULL, '2026-03-27 09:14:02', '2026-03-27 09:15:54', 7, NULL, '2409:40f4:215f:a1cf:218e:29ba:19c0:f622', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(39, '2026-03-27', 'ENQ-20260327-0136', 1, 'Thrishna madhavi', '2002-03-20', 'Student', 'female', 'Theppakadu forest,Valparai, Coimbatore', 'omniversal_handsome', '9952235465', 'glyzenats345@gmail.com', 'UiUX', 'BCom', 2026, 'Kathir College of arts and science', 97.00, 'Ranbeer Paul', 'Car Mechanic', '9952235465', 'ats.pythondeveloper08@gmail.com', 'Java, Python', 'Web Designing', 'Technology Training', 1, 'Walk-in,Other', 'Facebook', NULL, NULL, 'converted', 7, NULL, NULL, '2026-03-27 09:23:07', '2026-03-27 09:25:24', 7, NULL, '2409:40f4:215f:a1cf:218e:29ba:19c0:f622', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(40, '2026-03-27', 'ENQ-20260327-0139', 1, 'Paralogam', '2005-10-21', 'Student', 'male', '14/a, nh strret,\r\nGandhipuram, Coimbatore', 'omniversal_handsome', '9952235465', 'ats.pythondeveloper08@gmail.com', 'Data Science', 'BBA', 2026, 'Kathir College of arts and science', 74.00, 'Raja sima reddy', 'businsess', '9952235465', 'ats.pythondeveloper08@gmail.com', NULL, 'Java', 'Technology Training', 0, 'Other', 'Website', NULL, NULL, 'converted', 7, NULL, NULL, '2026-03-27 09:30:42', '2026-03-27 09:32:00', 7, NULL, '2409:40f4:215f:a1cf:218e:29ba:19c0:f622', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(41, '2026-03-27', 'ENQ-20260327-0142', 1, 'Divyarani', '2005-08-25', 'Student', 'female', '58/jh,Bypass road, Dindugul', 'omniversal_handsome', '9952235465', 'glyzenats345@gmail.com', 'Power BI', 'bsc cs', 2026, 'Karpagam College of Engineering', 88.00, 'Raghavan', 'Dojo Coach', '9952235465', 'ats.pythondeveloper08@gmail.com', NULL, 'Full Stack Web Development,Web Designing,PHP & MySQL', 'Technology Training', 1, 'Other', 'Instagram', NULL, NULL, 'converted', 7, NULL, NULL, '2026-03-27 09:37:01', '2026-03-27 09:37:41', 7, NULL, '2409:40f4:215f:a1cf:218e:29ba:19c0:f622', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(42, '2026-03-27', 'ENQ-20260327-0145', 1, 'Aadhavan', '2001-06-27', 'Student', 'male', 'Aachi mess,Trichy rOAD, PALLADAM', 'omniversal_handsome', '9952235465', 'ats.pythondeveloper08@gmail.com', 'AWS', 'bsc cs', 2026, 'Karpagam College of Engineering', NULL, 'Ameer', 'Driver', '9952235465', 'ats.pythondeveloper08@gmail.com', 'C', 'Tally', 'Placement Assistance', 0, 'Instagram,Other', 'Walk-in', NULL, NULL, 'converted', 7, NULL, NULL, '2026-03-27 09:47:16', '2026-03-27 09:49:19', 7, NULL, '2409:40f4:215f:a1cf:218e:29ba:19c0:f622', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(43, '2026-03-27', 'ENQ-20260327-0148', 1, 'Agilandeshwari', '2005-02-01', 'Student', NULL, 'c', 'omniversal_handsome', '9952235465', 'glyzenats345@gmail.com', 'Data Science', 'bsc cs', 2026, 'Kathir College of arts and science', NULL, 'Raghavan', 'Driver', '9952235465', 'ats.pythondeveloper08@gmail.com', NULL, NULL, NULL, 0, 'Other', 'Facebook', NULL, NULL, 'converted', 7, NULL, NULL, '2026-03-27 10:03:57', '2026-03-27 10:06:04', 7, NULL, '2409:40f4:215f:a1cf:218e:29ba:19c0:f622', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(44, '2026-03-27', 'ENQ-20260327-0151', 1, 'Zeekashami', NULL, 'Student', 'male', 'Sulur, Coimbatore', 'omniversal_handsome', '9952235465', 'ats.pythondeveloper08@gmail.com', 'Data Analytics', 'BCA', 2026, 'Kathir College of arts and science', 88.00, 'Kamarasu', 'Auditor', '9952235465', 'ats.pythondeveloper08@gmail.com', NULL, 'PHP & MySQL', NULL, 1, 'Other', 'Walk-in', NULL, NULL, 'converted', 7, NULL, NULL, '2026-03-27 10:11:17', '2026-03-27 10:12:18', 7, NULL, '2409:40f4:215f:a1cf:218e:29ba:19c0:f622', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL),
(45, '2026-03-27', 'ENQ-20260327-0154', 1, 'Vadakupattiramasamy', '2001-05-11', 'Student', 'male', 'NH Strret, Gandhipuram,\r\nCoimbatore', 'omniversal_handsome', '9952235465', 'ats.pythondeveloper08@gmail.com', 'Python with AI', 'BCA', 2026, 'Hindustan college of arts and science', NULL, 'Lorence', 'Car Mechanic', '9952235465', 'ats.pythondeveloper08@gmail.com', 'java', NULL, NULL, 1, 'Other', 'Walk-in', NULL, NULL, 'converted', 7, NULL, NULL, '2026-03-27 10:17:18', '2026-03-27 10:18:36', 7, NULL, '2409:40f4:215f:a1cf:218e:29ba:19c0:f622', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'web', NULL, NULL, NULL);

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
(3, 3, 1, '2026-03-07', '17:48:00', 'call', 'done', 'Sample', '2026-03-09', '17:48:00', '2026-03-11 09:00:06', 'pending', NULL, NULL, NULL, 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 14:49:09', '2026-03-11 09:00:06'),
(5, 6, 1, '2026-03-11', '20:02:00', 'call', 'done', 'wertyuio', '2026-03-12', '18:02:00', '2026-03-11 12:33:18', 'pending', NULL, NULL, NULL, 2, 2, '2401:4900:8825:644e:c116:fd72:e0c1:8291', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 12:32:42', '2026-03-11 12:33:18'),
(6, 7, 1, '2026-03-13', '10:48:00', 'call', 'done', 'qwertyuiop[]', '2026-03-15', '10:48:00', '2026-03-17 03:36:19', 'pending', NULL, NULL, NULL, 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-12 05:18:54', '2026-03-17 03:36:19'),
(7, 9, 1, '2026-03-14', '17:33:00', 'call', 'done', 'Today follow up', '2026-03-16', '15:33:00', '2026-03-17 05:00:17', 'pending', NULL, NULL, NULL, 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 10:03:53', '2026-03-17 05:00:17'),
(8, 8, 1, '2026-03-14', '19:35:00', 'call', 'done', 'fyfgdg', '2026-03-16', '17:36:00', '2026-03-17 03:29:29', 'pending', NULL, NULL, NULL, 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 12:06:22', '2026-03-17 03:29:29'),
(9, 10, 1, '2026-03-14', '17:44:00', 'call', 'done', 'dadcsfs', '2026-03-16', '17:44:00', '2026-03-17 03:30:25', 'pending', NULL, NULL, NULL, 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-14 12:14:24', '2026-03-17 03:30:25'),
(10, 11, 1, '2026-03-16', '18:00:00', 'call', 'done', 'eferf', '2026-03-17', '18:00:00', '2026-03-17 04:16:31', 'pending', NULL, NULL, NULL, 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-16 12:30:40', '2026-03-17 04:16:31'),
(11, 12, 1, '2026-03-17', '11:19:00', 'call', 'done', 'tghrthrt', '2026-03-18', '10:19:00', '2026-03-17 07:02:17', 'pending', NULL, NULL, NULL, 7, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-17 04:50:08', '2026-03-17 07:02:17'),
(12, 13, 1, '2026-03-17', '16:00:00', 'walkin', 'done', 'hhhhhh', '2026-03-31', '15:00:00', '2026-03-17 06:31:24', 'pending', NULL, NULL, NULL, 1, 1, '157.51.60.39', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-17 06:30:52', '2026-03-17 06:31:24'),
(13, 13, 1, '2026-03-17', '16:26:00', 'whatsapp', 'done', 'lllllll', '2026-04-15', '12:22:00', '2026-03-25 04:56:03', 'pending', NULL, NULL, NULL, 2, 2, '157.51.60.39', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-17 06:53:14', '2026-03-25 04:56:03'),
(14, 21, 1, '2026-03-17', NULL, 'call', 'done', 'asdfkjh', NULL, NULL, '2026-03-17 07:15:57', 'pending', NULL, NULL, NULL, 2, 2, '2405:201:e015:50bd:e0e7:fd8a:fb71:1f50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-17 07:10:40', '2026-03-17 07:15:57'),
(15, 22, 1, '2026-03-17', '14:45:00', 'call', 'done', 'one two three', '2026-03-20', '17:44:00', '2026-03-17 07:16:10', 'pending', NULL, NULL, NULL, 2, 2, '157.51.60.39', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-17 07:14:43', '2026-03-17 07:16:10'),
(16, 23, 1, '2026-03-19', '19:55:00', 'call', 'done', 'dfghfghjfghddfg', '2026-03-23', '19:56:00', '2026-03-24 06:17:29', 'pending', NULL, NULL, NULL, 2, 2, '2401:4900:8825:644e:fc9c:4ffe:7ea6:2286', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 12:25:18', '2026-03-24 06:17:29'),
(17, 28, 1, '2026-03-25', '10:47:00', 'call', 'done', 'wsdw', '2026-03-26', '10:47:00', '2026-03-25 05:17:51', 'pending', NULL, NULL, NULL, 7, 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 05:17:29', '2026-03-25 05:17:51'),
(18, 29, 1, '2026-03-25', '15:36:00', 'call', 'done', 'sad', '2026-03-26', '15:36:00', '2026-03-25 10:06:58', 'pending', NULL, NULL, NULL, 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 10:06:35', '2026-03-25 10:06:58'),
(19, 30, 1, '2026-03-25', '15:39:00', 'call', 'done', 'hjkl', '2026-03-26', '15:39:00', '2026-03-25 10:10:33', 'pending', NULL, NULL, NULL, 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 10:10:08', '2026-03-25 10:10:33'),
(20, 31, 1, '2026-03-26', '10:10:00', 'call', 'done', 'wstyukl;', '2026-03-27', '10:10:00', '2026-03-26 03:40:46', 'pending', NULL, NULL, NULL, 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 03:40:25', '2026-03-26 03:40:46'),
(21, 32, 1, '2026-03-26', '09:13:00', 'call', 'done', 'qwertyuio', '2026-03-27', '09:13:00', '2026-03-26 03:44:19', 'pending', NULL, NULL, NULL, 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 03:44:02', '2026-03-26 03:44:19'),
(22, 33, 1, '2026-03-26', '11:17:00', 'call', 'done', 'wertyuiop', '2026-03-27', '11:17:00', '2026-03-26 05:47:54', 'pending', NULL, NULL, NULL, 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 05:47:31', '2026-03-26 05:47:54'),
(23, 34, 1, '2026-03-27', NULL, 'call', 'done', 'Summa Try', '2026-03-31', '12:30:00', '2026-03-27 07:16:38', 'pending', NULL, NULL, NULL, 10, 10, '2401:4900:8825:644e:2835:4470:a4cc:1971', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 07:15:14', '2026-03-27 07:16:38'),
(24, 35, 1, '2026-03-27', '13:02:00', 'call', 'done', 'rhbh', '2026-03-28', '13:02:00', '2026-03-27 07:32:44', 'pending', NULL, NULL, NULL, 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 07:32:25', '2026-03-27 07:32:44'),
(25, 36, 1, '2026-03-27', '16:25:00', 'walkin', 'done', 'Needs the explanation of the course', '2026-03-30', '15:25:00', '2026-03-27 07:54:28', 'pending', NULL, NULL, NULL, 7, 7, '2409:40f4:2157:1908:69b4:aa59:b64e:4b3d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 07:53:07', '2026-03-27 07:54:28'),
(26, 37, 1, '2026-03-27', '12:35:00', 'call', 'done', 'gooooooooood', '2026-03-30', '16:13:00', '2026-03-27 09:05:57', 'pending', NULL, NULL, NULL, 7, 7, '2409:40f4:215f:a1cf:218e:29ba:19c0:f622', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 09:05:41', '2026-03-27 09:05:57'),
(27, 38, 1, '2026-03-27', '10:10:00', 'email', 'done', 'need more task', '2026-08-26', '17:28:00', '2026-03-27 09:15:54', 'pending', NULL, NULL, NULL, 7, 7, '2409:40f4:215f:a1cf:218e:29ba:19c0:f622', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 09:15:40', '2026-03-27 09:15:54'),
(28, 39, 1, '2026-03-27', '09:35:00', 'walkin', 'done', 'needs extra session in practical', '2026-04-01', '12:30:00', '2026-03-27 09:25:24', 'pending', NULL, NULL, NULL, 7, 7, '2409:40f4:215f:a1cf:218e:29ba:19c0:f622', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 09:25:04', '2026-03-27 09:25:24'),
(29, 40, 1, '2026-03-27', '10:04:00', 'call', 'done', 'on next year', '2026-12-31', '15:04:00', '2026-03-27 09:32:00', 'pending', NULL, NULL, NULL, 7, 7, '2409:40f4:215f:a1cf:218e:29ba:19c0:f622', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 09:31:46', '2026-03-27 09:32:00'),
(30, 41, 1, '2026-03-27', NULL, 'email', 'done', 'hhhhh', '2026-03-31', '13:10:00', '2026-03-27 09:37:41', 'pending', NULL, NULL, NULL, 7, 7, '2409:40f4:215f:a1cf:218e:29ba:19c0:f622', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 09:37:30', '2026-03-27 09:37:41'),
(31, 42, 1, '2026-03-27', '16:50:00', 'sms', 'done', 'I WILL BE BACK', '2026-04-16', '15:22:00', '2026-03-27 09:49:19', 'pending', NULL, NULL, NULL, 7, 7, '2409:40f4:215f:a1cf:218e:29ba:19c0:f622', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 09:48:17', '2026-03-27 09:49:19'),
(32, 43, 1, '2026-03-27', '18:00:00', 'call', 'done', 'plll', '2026-05-21', '15:35:00', '2026-03-27 10:06:04', 'pending', NULL, NULL, NULL, 7, 7, '2409:40f4:215f:a1cf:218e:29ba:19c0:f622', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 10:05:36', '2026-03-27 10:06:04'),
(33, 44, 1, '2026-03-27', '17:41:00', 'call', 'done', 'great', '2026-03-31', '16:20:00', '2026-03-27 10:12:18', 'pending', NULL, NULL, NULL, 7, 7, '2409:40f4:215f:a1cf:218e:29ba:19c0:f622', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 10:12:00', '2026-03-27 10:12:18'),
(34, 45, 1, '2026-03-27', '17:21:00', 'whatsapp', 'done', 'nice', '2026-05-05', '12:50:00', '2026-03-27 10:18:36', 'pending', NULL, NULL, NULL, 7, 7, '2409:40f4:215f:a1cf:218e:29ba:19c0:f622', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 10:18:27', '2026-03-27 10:18:36');

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
(177, '2026-03-28 11:29:13');

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
(1, 1, 'Grandway Properties', '98745632145', 'grandway@gmail.com', 'Reference', 'Website Making', 'Grandway properties parent', 'FSWD', '2026', 'converted', NULL, '2026-03-07 14:48:25', NULL, 2, NULL, 3, NULL, 'If we follow him sure he will give website work to us', '2026-03-07 14:17:02', '2026-03-10 10:22:46', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(2, 1, 'Altitudes', '7896541230', 'altitudes@gmail.com', 'Walk-in', 'Full Stack Webdevelopment', 'Altitudes parent company', 'FSWD', '2026', 'new', NULL, NULL, NULL, 2, NULL, NULL, NULL, 'test', '2026-03-09 14:34:47', '2026-03-10 10:22:38', 3, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(13, 1, 'Arun Kumar', '9876543210', 'arun.kumar@example.com', 'Website', 'Full Stack Development', 'ABC Engineering College', 'CSE', '2026', 'new', NULL, NULL, NULL, NULL, 2, NULL, NULL, 'Interested in weekend batch', '2026-03-11 06:16:01', '2026-03-11 06:16:01', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(14, 1, 'Priya Sharma', '9123456780', 'priya.sharma@example.com', 'Instagram', 'UI UX Design', 'XYZ Arts and Science College', 'Visual Communication', '2025', 'new', NULL, NULL, NULL, 7, 2, NULL, NULL, 'Wants placement support', '2026-03-11 06:16:02', '2026-03-25 05:13:34', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(15, 1, 'Rahul Verma', '9988776655', 'rahul.verma@example.com', 'Google Ads', 'Data Analytics', 'National Institute of Technology', 'EEE', 'Final Year', 'new', NULL, NULL, NULL, NULL, 2, NULL, NULL, 'Asked for fee details', '2026-03-11 06:16:02', '2026-03-11 06:16:02', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(16, 1, 'Sneha R', '9001122334', 'sneha.r@example.com', 'Reference', 'Python Development', 'Metro College of Engineering', 'IT', '2024', 'new', NULL, NULL, NULL, 7, 2, NULL, NULL, 'Can join immediately', '2026-03-11 06:16:03', '2026-03-17 04:37:03', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(17, 1, 'Karthik S', '9090909090', 'karthik.s@example.com', 'Walk-in', 'Digital Marketing', 'Sunrise Business School', 'MBA', '2025', 'new', NULL, NULL, NULL, 7, 2, NULL, NULL, 'Needs weekday batch', '2026-03-11 06:16:04', '2026-03-17 04:37:03', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(18, 1, 'Meena Joseph', '9345678901', 'meena.joseph@example.com', 'Facebook', 'Testing', 'St. Thomas College', 'BCA', '2026', 'converted', NULL, '2026-03-17 04:49:25', NULL, 7, 2, 12, NULL, 'Requested callback tomorrow', '2026-03-11 06:16:04', '2026-03-17 04:49:25', 2, 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(19, 1, 'Vikram Patel', '9785612345', 'vikram.patel@example.com', 'Website', 'Java Development', 'Global Tech College', 'CSE', '2023', 'new', NULL, NULL, NULL, 7, 2, NULL, NULL, 'Working professional', '2026-03-11 06:16:05', '2026-03-17 04:37:03', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(20, 1, 'Anjali Nair', '9654321870', 'anjali.nair@example.com', 'Instagram', 'Graphic Design', 'Creative Media Institute', 'Design', '2026', 'converted', NULL, '2026-03-14 09:34:50', NULL, 2, 2, 9, NULL, 'Interested in portfolio guidance', '2026-03-11 06:16:05', '2026-03-14 09:34:50', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(21, 1, 'Mohammed Irfan', '9445566677', 'mohd.irfan@example.com', NULL, 'Cloud Computing', 'Alpha Polytechnic', 'Computer Engineering', '2024', 'converted', NULL, '2026-03-14 09:26:30', NULL, 2, 2, 8, NULL, 'Asked about certification', '2026-03-11 06:16:06', '2026-03-19 12:13:45', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(22, 1, 'Divya Kumar', '9556677889', 'divya.k@example.com', NULL, 'MS Office', 'City Women\'s College', 'B.Com', '2025', 'converted', NULL, '2026-03-14 12:11:48', NULL, 2, 2, 10, NULL, 'Beginner level student', '2026-03-11 06:16:07', '2026-03-19 12:14:09', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(23, 1, 'Wednesday', '34567890-', 'wed@gmail.com', 'Reference', 'Course', 'Accent Techno Soft', 'MCA', '2024', 'converted', NULL, '2026-03-11 12:31:22', NULL, 2, NULL, 6, NULL, 'ghj', '2026-03-11 12:29:12', '2026-03-11 12:31:22', 2, 2, '2401:4900:8825:644e:c116:fd72:e0c1:8291', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(24, 1, 'Ram', '78965412302', 'ram@gmail.com', 'Reference', 'UI/UX', 'Sample', 'BCA', '2010', 'new', NULL, NULL, NULL, 7, NULL, NULL, NULL, 'this is sample', '2026-03-16 09:34:06', '2026-03-16 09:34:06', 3, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(25, 1, 'Uma', '7896541236', 'uma@gmail.com', 'Reference', 'React', 'Test', 'BCA', '2024', 'new', NULL, NULL, NULL, 2, NULL, NULL, NULL, 'bdfebdfjkerngjkner', '2026-03-16 12:02:01', '2026-03-16 12:02:01', 3, NULL, '2405:201:e015:50bd:c4d2:33ce:ceab:eda6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(26, 1, 'Hema', '78965412301', 'hema@gmail.com', 'Reference', 'Full Stack Webdevelopment', 'Sample', 'BCA', '2010', 'converted', NULL, '2026-03-16 12:30:04', NULL, 2, NULL, 11, NULL, 'efewrfger', '2026-03-16 12:19:17', '2026-03-16 12:30:04', 3, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(27, 1, 'Joseph Glyzen', '1234567890', 'glyzen@gmail.com', 'Walk-in', 'AI, Python, ML and Dl', 'Karpagam', 'Python', '2026', 'new', NULL, NULL, NULL, 2, NULL, NULL, NULL, NULL, '2026-03-17 06:07:47', '2026-03-17 06:07:47', 1, NULL, '157.51.60.39', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(28, 1, 'jeevi', '8679678978', 'jeevi@gmail.com', 'Website', 'Full stack development', 'ATS', 'js', '2026', 'new', NULL, NULL, NULL, 2, NULL, NULL, NULL, 'sdfkjhbvczxcghasdh', '2026-03-17 06:08:41', '2026-03-17 06:13:27', 1, 1, '2405:201:e015:50bd:e0e7:fd8a:fb71:1f50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(29, 1, 'jeevi', '987654345', 'jeevi@gmail.com', 'Website', 'Full stack development', 'ATS', 'js', '2026', 'new', NULL, NULL, NULL, 2, NULL, NULL, NULL, 'wertyuopdfghjklvbnmsdfghjk', '2026-03-17 06:29:42', '2026-03-17 06:29:42', 3, NULL, '2405:201:e015:50bd:e0e7:fd8a:fb71:1f50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(30, 1, 'maha', '8765434567', 'maha@gmail.com', 'Website', 'Full stack development', 'ATS', 'js', '2026', 'new', NULL, NULL, NULL, 2, NULL, NULL, NULL, 'wertyu', '2026-03-17 06:32:05', '2026-03-17 06:32:05', 3, NULL, '2405:201:e015:50bd:e0e7:fd8a:fb71:1f50', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(31, 1, 'Arun Kumar', '9876543210', 'arun.kumar@example.com', 'Website', 'Full Stack Development', 'ABC Engineering College', 'CSE', '2026', 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, 'Interested in weekend batch', '2026-03-17 06:35:00', '2026-03-17 06:35:00', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(32, 1, 'Priya Sharma', '9123456780', 'priya.sharma@example.com', 'Instagram', 'UI UX Design', 'XYZ Arts and Science College', 'Visual Communication', '2025', 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, 'Wants placement support', '2026-03-17 06:35:01', '2026-03-17 06:35:01', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(33, 1, 'Rahul Verma', '9988776655', 'rahul.verma@example.com', 'Google Ads', 'Data Analytics', 'National Institute of Technology', 'EEE', 'Final Year', 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, 'Asked for fee details', '2026-03-17 06:35:01', '2026-03-17 06:35:01', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(34, 1, 'Sneha R', '9001122334', 'sneha.r@example.com', 'Reference', 'Python Development', 'Metro College of Engineering', 'IT', '2024', 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, 'Can join immediately', '2026-03-17 06:35:02', '2026-03-17 06:35:02', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(36, 1, 'Meena Joseph', '9345678901', 'meena.joseph@example.com', 'Facebook', 'Testing', 'St. Thomas College', 'BCA', '2026', 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, 'Requested callback tomorrow', '2026-03-17 06:35:03', '2026-03-17 06:35:03', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(37, 1, 'Vikram Patel', '9785612345', 'vikram.patel@example.com', 'Website', 'Java Development', 'Global Tech College', 'CSE', '2023', 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, 'Working professional', '2026-03-17 06:35:04', '2026-03-17 06:35:04', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(38, 1, 'Anjali Nair', '9654321870', 'anjali.nair@example.com', 'Instagram', 'Graphic Design', 'Creative Media Institute', 'Design', '2026', 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, 'Interested in portfolio guidance', '2026-03-17 06:35:05', '2026-03-17 06:35:05', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(39, 1, 'Mohammed Irfan', '9445566677', 'mohd.irfan@example.com', 'Reference', 'Cloud Computing', 'Alpha Polytechnic', 'Computer Engineering', '2024', 'new', NULL, NULL, NULL, NULL, 3, NULL, NULL, 'Asked about certification', '2026-03-17 06:35:05', '2026-03-17 06:35:05', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(40, 1, 'Divya K', '9556677889', 'divya.k@example.com', 'Google Ads', 'MS Office', 'City Women\'s College', 'B.Com', '2025', 'new', NULL, NULL, NULL, 2, 3, NULL, NULL, 'Beginner level student', '2026-03-17 06:35:06', '2026-03-27 07:25:43', 2, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(43, 1, 'Ezekil jackson', '1234567890', 'jack@gmail.com', 'Reference', 'Regular', 'Karpagam', 'Bsc cs', '2026', 'new', NULL, NULL, NULL, 2, NULL, NULL, NULL, NULL, '2026-03-17 07:06:03', '2026-03-17 07:06:03', 1, NULL, '157.51.60.39', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(44, 1, 'pramodhini', '7402740298', 'pramodhninganesan@gmai.com', 'Reference', 'python with ai', 'Nirmala College for Women', 'BCA', '2027', 'new', NULL, NULL, NULL, 3, NULL, NULL, NULL, NULL, '2026-03-17 12:18:24', '2026-03-17 12:18:24', 2, NULL, '2401:4900:8825:644e:d837:e4ba:cc08:1dd6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(45, 1, 'Thejaswini', '9600332324', 'atshrmarketing@gmail.com', 'Reference', 'Python with Django', 'Nirmala College for Women', 'MCA', '2027', 'new', NULL, NULL, NULL, 8, NULL, NULL, NULL, 'She told like discuss and get back for Django 2 weeks internship', '2026-03-17 12:21:36', '2026-03-17 12:21:36', 2, NULL, '2401:4900:8825:644e:d837:e4ba:cc08:1dd6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(46, 1, 'pramodhini', '7402740298', 'pramodhninganesan@gmai.com', 'Reference', 'python with ai', 'Nirmala College for Women', 'BCA', '2027', 'new', NULL, NULL, NULL, 3, NULL, NULL, NULL, NULL, '2026-03-17 12:26:26', '2026-03-17 12:26:26', 2, NULL, '2401:4900:8825:644e:d837:e4ba:cc08:1dd6', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36'),
(49, 1, 'rajesh', 'fhfjhfjfjk', 'hdshvfjdyhiu@gmail.com', 'Instagram', 'aws', 'SMIT', 'mca', '2028', 'new', NULL, NULL, NULL, 8, NULL, NULL, NULL, 'ghjghjkdfghjfghjkghjkghjkfghjk', '2026-03-19 12:09:40', '2026-03-19 12:09:40', 2, NULL, '2401:4900:8825:644e:fc9c:4ffe:7ea6:2286', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(50, 1, 'ani', 'vhjkftislkjg', 'ani@gmail.com', 'Google Ads', 'fghjdfghjghj', 'SMIT', 'MBA', '2028', 'new', NULL, NULL, NULL, 4, NULL, NULL, NULL, 'dfghjbjcvfgbhjvbncjkjikx', '2026-03-19 12:11:54', '2026-03-19 12:11:54', 2, NULL, '2401:4900:8825:644e:fc9c:4ffe:7ea6:2286', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(53, 1, 'Arjun Kumar', '9876543210', 'arjun.kumar@gmail.com', 'Website', 'Full Stack Development', 'PSG College of Technology', 'Computer Science', 'Final Year', 'converted', NULL, NULL, NULL, 2, NULL, NULL, NULL, 'Asked for demo class', '2026-03-24 06:36:57', '2026-03-27 07:31:52', 1, 2, '2405:201:e015:50bd:f51b:db:6227:915c', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(57, 1, 'smith', '9876583459', 'smith@gmail.com', 'Website', 'java', 'ATS', 'computer science', '2026', 'new', NULL, NULL, NULL, 2, NULL, NULL, NULL, 'sdfghjkdfghjkl', '2026-03-24 07:34:04', '2026-03-24 07:35:52', 1, 1, '2405:201:e015:50bd:e588:45ee:d41b:53ca', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(59, 1, 'ATS WEB DEVELOPER', '7896541236', 'ats.banu@gmail.com', 'Walk-in', 'Full Stack Webdevelopment', 'Sample', 'BCA', '2010', 'new', NULL, NULL, NULL, 2, NULL, NULL, NULL, 'fcgh', '2026-03-24 07:45:47', '2026-03-24 07:45:47', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(61, 1, 'Tuesday', '8765432190', 'tuesday@gmail.com', 'Walk-in', 'AI', 'EFG college', 'CSE', 'Final Year', 'new', NULL, NULL, NULL, 2, NULL, NULL, NULL, 'sdfsdfsefesf', '2026-03-24 07:49:16', '2026-03-24 07:49:16', 2, NULL, '2401:4900:8825:644e:21e1:1230:a94a:f720', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(63, 1, 'Friday', '7654321234', 'friday@gmail.com', 'Walk-in', 'AI', 'EFG college', 'CSE', 'Final Year', 'new', NULL, NULL, NULL, 2, NULL, NULL, NULL, 'vdbdfbdfbdfbdf', '2026-03-24 07:51:56', '2026-03-24 07:51:56', 7, NULL, '2401:4900:8825:644e:21e1:1230:a94a:f720', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(66, 1, 'Test4', '1234567890', 'test4@gmail.com', 'Website', 'Full Stack Development', 'PSG College of Technology', 'Computer Science', '2024', 'new', NULL, NULL, NULL, 0, NULL, NULL, NULL, 'Test case1', '2026-03-24 08:40:58', '2026-03-24 08:40:58', 1, NULL, '2405:201:e015:50bd:f51b:db:6227:915c', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(67, 1, 'Sanjay', '7251634890', 'sanjay@gmail.com', 'Walk-in', 'CYBER', 'IJK college', 'AI & DS', 'Final Year', 'new', NULL, NULL, NULL, 5, NULL, NULL, NULL, 'test sakthivel', '2026-03-24 08:49:40', '2026-03-24 08:49:40', 7, NULL, '2401:4900:8825:644e:21e1:1230:a94a:f720', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(68, 1, 'Mary', '3737383566', 'mary@gmail.com', 'Website', 'MS Office', 'SMIT', 'MBA', '2024', 'new', NULL, NULL, NULL, 11, NULL, NULL, NULL, 'QW2ERWERTYUIWERTYUQYUIOYTUGJHKSDRFTHYUIOOSWEDRFGTHYUJIO', '2026-03-24 09:41:21', '2026-03-24 09:41:21', 3, NULL, '2401:4900:8825:644e:5981:a6d:c7c4:3965', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(69, 1, 'Mary', '5464738394', 'Mary@gmail.com', 'Website', 'MS Office', 'SMIT', 'MBA', '2024', 'new', NULL, NULL, NULL, 11, NULL, NULL, NULL, 'we are verutggjork', '2026-03-24 09:42:43', '2026-03-24 09:42:43', 3, NULL, '2401:4900:8825:644e:5981:a6d:c7c4:3965', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(70, 1, 'rakesh', '1111111111', 'rakesh@gamil.com', 'Walk-in', 'CYBER', 'IJK college', 'CSE', NULL, 'new', NULL, NULL, NULL, 15, NULL, NULL, NULL, 'test sakthi 2', '2026-03-24 12:14:52', '2026-03-24 12:14:52', 3, NULL, '2401:4900:8825:644e:21e1:1230:a94a:f720', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(71, 1, 'ajay', '1111111111', 'ajay@gmail.com', 'Walk-in', 'AI', 'EFG college', 'AI & DS', 'Final Year', 'new', NULL, NULL, NULL, 10, NULL, NULL, NULL, 'jasnjSB', '2026-03-25 04:23:56', '2026-03-25 04:23:56', 10, NULL, '2405:201:e015:50bd:80b9:34c9:b1dc:fce2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(72, 1, 'rithick', '9790729124', 'rithick@gmail.com', 'Walk-in', 'CYBER', 'EFG college', 'AI & DS', NULL, 'new', NULL, NULL, NULL, 11, NULL, NULL, NULL, 'ghghvjh', '2026-03-25 08:58:40', '2026-03-25 08:58:40', 3, NULL, '2401:4900:8825:644e:7885:f029:1fb4:b27d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(74, 1, 'Sundar', '9876543210', 'sundar@gmail.com', 'Website', 'Full Stack', 'XYZ College', 'CSE', '2024', 'new', NULL, NULL, NULL, 2, 6, NULL, NULL, 'Interested in weekend batch', '2026-03-25 10:03:31', '2026-03-25 10:03:50', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(75, 1, 'Johnson', '9876543210', 'johnson@gmail.com', 'Website', 'Full Stack', 'XYZ College', 'CSE', '2025', 'converted', NULL, NULL, NULL, 2, 6, NULL, NULL, 'Interested in weekend batch', '2026-03-25 10:03:31', '2026-03-26 04:24:29', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(76, 1, 'Pavan', '9876543210', 'pavan@gmail.com', 'Walk-in', 'FSWD', 'KEC', 'CHEM', '2023', 'new', NULL, NULL, NULL, 2, NULL, NULL, NULL, 'qwertyuio', '2026-03-26 03:36:19', '2026-03-26 03:36:19', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(77, 1, 'Thanish', '9876543210', 'thanish@gmail.com', 'Walk-in', 'MERN', 'KEC', 'CHEM', '2023', 'new', NULL, NULL, NULL, 2, NULL, NULL, NULL, 'qwertyuio', '2026-03-26 03:37:13', '2026-03-26 03:37:13', 1, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(78, 1, 'Anu', '9876543210', 'anu@gmail.com', 'Walk-in', 'MERN', 'ATS', 'MCA', '2021', 'converted', NULL, NULL, NULL, 2, NULL, NULL, NULL, 'qwertyuiop', '2026-03-26 05:45:05', '2026-03-26 05:46:46', 2, 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(79, 1, 'Zeekashami', '9952235465', 'ats.pythondeveloper08@gmail.com', 'Walk-in', 'Data Analytics', 'Kathir College of Arts and Science', 'Computer Science', '2026', 'converted', NULL, NULL, NULL, 7, NULL, NULL, NULL, 'With high practical task', '2026-03-27 06:18:21', '2026-03-27 10:11:17', 1, 7, '2409:40f4:2157:1908:69b4:aa59:b64e:4b3d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(80, 1, 'Thowfig narayanan', '9952235465', 'glyzenats345@gmail.com', 'Website', 'Python with AI', 'Karpagam College of Engineering', 'Computer Science', '2026', 'converted', NULL, NULL, NULL, 7, NULL, NULL, NULL, NULL, '2026-03-27 06:21:22', '2026-03-27 07:49:47', 1, 7, '2409:40f4:2157:1908:69b4:aa59:b64e:4b3d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(81, 1, 'Abraham lingan', '9952235465', 'ats.pythondeveloper08@gmail.com', 'Instagram', 'UiUX', 'Kathir College of Arts and Science', 'BCA', '2026', 'converted', NULL, NULL, NULL, 7, NULL, NULL, NULL, NULL, '2026-03-27 06:23:03', '2026-03-27 09:04:07', 1, 7, '2409:40f4:2157:1908:69b4:aa59:b64e:4b3d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(82, 1, 'Rakshasthira', '9952235465', 'glyzenats345@gmail.com', 'Reference', 'AWS', 'Hindustan College of Arts and Science', 'BBA', '2026', 'converted', NULL, NULL, NULL, 7, NULL, NULL, NULL, NULL, '2026-03-27 06:24:33', '2026-03-27 09:14:02', 1, 7, '2409:40f4:2157:1908:69b4:aa59:b64e:4b3d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(83, 1, 'Thrishna madhavi', '9952235465', 'glyzenats345@gmail.com', 'Facebook', 'UiUX', 'Kathir College of Arts and Science', 'Bcom', '2026', 'converted', NULL, NULL, NULL, 7, NULL, NULL, NULL, NULL, '2026-03-27 06:25:48', '2026-03-27 09:23:07', 1, 7, '2409:40f4:2157:1908:69b4:aa59:b64e:4b3d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(84, 1, 'Paralogam', '9952235465', 'ats.pythondeveloper08@gmail.com', 'Website', 'Data Science', 'NGP College of Arts and Science', 'Micro Bioology', '2026', 'converted', NULL, NULL, NULL, 7, 7, NULL, NULL, NULL, '2026-03-27 06:48:13', '2026-03-27 09:30:42', 1, 7, '2409:40f4:2157:1908:69b4:aa59:b64e:4b3d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(85, 1, 'Divyarani', '9952235465', 'glyzenats345@gmail.com', 'Instagram', 'Power BI', 'SNS College of Arts and Science', 'BBA', '2026', 'converted', NULL, NULL, NULL, 7, 7, NULL, NULL, NULL, '2026-03-27 06:48:13', '2026-03-27 09:37:01', 1, 7, '2409:40f4:2157:1908:69b4:aa59:b64e:4b3d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(86, 1, 'Aadhavan', '9952235465', 'ats.pythondeveloper08@gmail.com', 'Walk-in', 'AWS', 'Kathir College of Arts and Science', 'Bcom CA', '2026', 'converted', NULL, NULL, NULL, 7, 7, NULL, NULL, 'Expecting more tasks', '2026-03-27 06:48:13', '2026-03-27 09:47:16', 1, 7, '2409:40f4:2157:1908:69b4:aa59:b64e:4b3d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(87, 1, 'Agilandeshwari', '9952235465', 'glyzenats345@gmail.com', 'Facebook', 'Data Science', 'Kathir College of Arts and Science', 'Bio Tech', '2026', 'converted', NULL, NULL, NULL, 7, 7, NULL, NULL, NULL, '2026-03-27 06:48:13', '2026-03-27 10:03:57', 1, 7, '2409:40f4:2157:1908:69b4:aa59:b64e:4b3d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(88, 1, 'Vadakupattiramasamy', '9952235465', 'ats.pythondeveloper08@gmail.com', 'Walk-in', 'Python with AI', 'KG College', 'ECE', '2026', 'converted', NULL, NULL, NULL, 7, 7, NULL, NULL, NULL, '2026-03-27 06:48:13', '2026-03-27 10:17:18', 1, 7, '2409:40f4:2157:1908:69b4:aa59:b64e:4b3d', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36'),
(89, 1, 'Sakthivel', '6381814891', 'sakthivelpcseb@gmail.com', 'Reference', 'Full Stack Development', 'NPR CET', 'Computer Science', 'Final Year', 'converted', NULL, NULL, NULL, 10, NULL, NULL, NULL, 'Test case', '2026-03-27 07:04:56', '2026-03-27 07:11:41', 1, 10, '2401:4900:8825:644e:2835:4470:a4cc:1971', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36');

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
(2, 1, 2, 'leads_20260311_114600_2605.xlsx', 10, 10, 0, 'completed', '2026-03-11 06:16:00'),
(3, 1, 2, 'leads_20260317_120458_9360.xlsx', 10, 10, 0, 'completed', '2026-03-17 06:34:59'),
(4, 1, 2, 'leads_20260319_174554_5645.xlsx', 13, 0, 13, 'failed', '2026-03-19 12:15:54'),
(6, 1, 1, 'leads_20260325_153329_6656.csv', 2, 2, 0, 'completed', '2026-03-25 10:03:30'),
(7, 1, 1, 'leads_20260327_121813_6305.xlsx', 5, 5, 0, 'completed', '2026-03-27 06:48:13');

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
(66, 'Daily Report', 'reports/create', 20, 'fa-file-pen', 3, 1, '2026-03-19 06:59:33', '2026-03-19 06:59:33'),
(67, 'Reports List', 'reports/list', 20, 'fa-list-check', 4, 1, '2026-03-19 07:00:23', '2026-03-19 07:00:23'),
(68, 'Export', 'reports/export', 20, 'fa-file-export', 5, 1, '2026-03-19 07:01:07', '2026-03-19 07:01:07'),
(69, 'Student Report', 'reports/student_schedule', 20, 'fas fa-calendar-day', 1, 1, '2026-03-19 11:06:50', '2026-03-26 12:46:24'),
(70, 'Student Overall', 'reports/student_overall', 20, 'fas fa-clipboard', 1, 1, '2026-03-19 11:10:20', '2026-03-19 11:10:20'),
(71, 'Dashboard test 1', 'dashboard/superadmin', NULL, 'fas fa-chart-line', 1, 1, '2026-03-24 06:47:33', '2026-03-24 06:47:33'),
(73, 'Daily Report Test1', 'reports/daily', 20, 'fas fa-calendar-day', 1, 1, '2026-03-24 06:58:34', '2026-03-24 06:58:34'),
(74, 'Monthly Report', 'reports/monthly', 20, 'fas fa-calendar-alt', 2, 1, '2026-03-24 06:59:08', '2026-03-24 06:59:08');

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
(1, 15, 1, 4, 88.00, 90.00, 89.00, 'done', '2026-03-24 06:46:56', 4, '2026-03-17 06:43:00', '2026-03-24 06:46:56'),
(2, 12, 1, 8, 87.00, 87.00, 87.00, 'done', '2026-03-25 04:27:42', 8, '2026-03-18 11:08:17', '2026-03-25 04:27:42'),
(3, 18, 1, 4, 100.00, 100.00, 100.00, 'done', '2026-03-24 06:44:58', 4, '2026-03-19 10:31:02', '2026-03-24 06:44:58');

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
(2, 1, 3, 3, 2026, 3, 75000.00, 10.00, 'Do Well', 'active', 3, '2026-03-09 10:16:10', '2026-03-24 07:34:11'),
(3, 1, 5, 6, 2026, 3, 50000.00, 10.00, NULL, 'active', 3, '2026-03-09 10:30:08', '2026-03-09 10:30:08'),
(5, 1, 10, 2, 2026, 3, 50000.00, 20.00, NULL, 'active', 3, '2026-03-24 09:27:35', '2026-03-24 09:28:18'),
(7, 1, 7, 2, 2026, 3, 500000.00, 5.00, 'fgkdjhfgh', 'active', 3, '2026-03-25 07:36:48', '2026-03-25 07:36:48');

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
(1, 15, 1, 1, 'Mechpro', '2026-03-17', '10:35:00', 'Offline', 'rejected', 'qwertyuiop', 3, 3, '2026-03-19 05:05:21', '2026-03-19 05:25:18'),
(2, 15, 1, 1, 'Nettel', '2026-03-19', '11:08:00', 'Offline', 'selected', 'qwertyuiop', 3, 3, '2026-03-19 05:38:43', '2026-03-19 05:38:43'),
(3, 15, 1, 1, 'scoto', '2026-03-25', '21:23:00', 'Online', 'scheduled', 'asdfghjwertyui', 3, 3, '2026-03-24 09:48:11', '2026-03-24 09:48:11'),
(4, 12, 1, 2, 'IQ general', '2026-03-29', '09:44:00', 'Offline', 'scheduled', 'qwertyu;gfd', 3, 3, '2026-03-25 07:28:51', '2026-03-25 07:28:51');

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
(1, 'REG-202603-0002', 1, 1, 'internship', 'lead', '2026-03-06', 'Arun', '7896541236', 'arun@gmail.com', 'Flutter Development', 'Morning', 7, 'Morning', '2026-03-11', '2026-03-16', 'completed', 'given', NULL, 'provided', NULL, 7, 1500.00, 0.00, 1500.00, 1500.00, 0.00, 'paid', 'This is sample one', 'active', 2, 2, '2026-03-06 17:07:31', '2026-03-11 10:59:52'),
(5, 'REG-202603-0003', 2, 1, 'course', 'lead', '2026-03-07', 'Raghul', '9942133944', 'raghul@gmail.com', 'Data Analytics', 'Morning Batch', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 15000.00, 0.00, 15000.00, 0.00, 15000.00, 'unpaid', 'This stident will join soon', 'draft', 2, 2, '2026-03-07 11:36:00', '2026-03-07 11:36:49'),
(7, 'REG-202603-0004', NULL, 1, 'course', 'direct', '2026-03-07', 'Ranjith', '7896541236', 'ranjith@gmail.com', 'AWS', 'Morning Batch', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 15000.00, 0.00, 15000.00, 0.00, 15000.00, 'unpaid', 'He will be willing to join from monday', 'active', 2, 2, '2026-03-07 13:21:18', '2026-03-07 13:21:18'),
(8, NULL, 3, 1, 'course', 'direct', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid', NULL, 'draft', 2, 2, '2026-03-11 09:00:06', '2026-03-11 09:00:06'),
(10, 'REG-202603-0007', 6, 1, 'course', 'direct', '2026-03-11', 'Wednesday', '9987654321', 'wed@gmail.com', 'Course', 'dfghbjkm', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 15000.00, 0.00, 15000.00, 1000.00, 14000.00, 'partial', 'ertyuio', 'active', 2, 2, '2026-03-11 12:33:18', '2026-03-26 05:02:07'),
(11, 'REG-202603-0008', 7, 1, 'internship', 'direct', '2026-03-12', 'Ramesh', '9876543210', 'ramesh@gmail.com', 'MERN', 'Morning', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 5000.00, 0.00, 5000.00, 0.00, 5000.00, 'unpaid', 'qwertyuiopsdfghjk', 'active', 7, 2, '2026-03-12 05:24:58', '2026-03-24 06:52:55'),
(12, 'REG-202603-0009', NULL, 1, 'course', 'direct', '2026-03-14', 'Pranesh', '9876543210', 'pranesh@gmail.com', 'MERN', '2023', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 15000.00, 0.00, 15000.00, 0.00, 15000.00, 'unpaid', 'ghj', 'active', 8, 2, '2026-03-14 06:49:03', '2026-03-14 06:50:06'),
(13, 'REG-202603-0010', 11, 1, 'internship', 'direct', '2026-03-17', 'Hema', '78965412301', 'hema@gmail.com', 'Full Stack Webdevelopment', NULL, NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid', NULL, 'draft', 2, 2, '2026-03-17 04:16:33', '2026-03-17 04:33:05'),
(14, 'REG-202603-0011', 9, 1, 'internship', 'direct', '2026-03-17', 'Anjali Nair', '9654321870', 'anjali.nair@example.com', 'Graphic Design', NULL, NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid', NULL, 'active', 2, 2, '2026-03-17 04:34:15', '2026-03-17 07:40:52'),
(15, 'REG-202603-0012', 13, 1, 'course', 'direct', '2026-03-17', 'Joseph Glyzen', '1234567890', 'glyzen@gmail.com', 'AI, Python, ML and Dl', NULL, NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 10000.00, 2000.00, 8000.00, 0.00, 8000.00, 'unpaid', NULL, 'draft', 4, 1, '2026-03-17 06:31:24', '2026-03-25 04:56:03'),
(16, 'REG-202603-0013', 13, 1, 'internship', 'direct', '2026-03-17', 'Joseph Glyzen', '1234567890', 'glyzen@gmail.com', 'AI, Python, ML and Dl', NULL, NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 25000.00, 1000.00, 24000.00, 0.00, 24000.00, 'unpaid', NULL, 'active', 1, 2, '2026-03-17 06:57:22', '2026-03-17 06:58:37'),
(17, 'REG-202603-0014', 12, 1, 'internship', 'direct', '2026-03-17', 'Meena Joseph', '9345678901', 'meena.joseph@example.com', 'Testing', NULL, NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid', 'werty', 'active', 7, 2, '2026-03-17 07:02:17', '2026-03-17 07:03:28'),
(18, 'REG-202603-0015', 21, 1, 'course', 'direct', '2026-03-17', 'jeevi', '987654345', 'jeevi@gmail.com', 'Full stack development', NULL, NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid', NULL, 'active', 4, 2, '2026-03-17 07:15:57', '2026-03-17 07:29:20'),
(19, 'REG-202603-0016', 22, 1, 'internship', 'direct', '2026-03-17', 'Ezekil jackson', '1234567890', 'jack@gmail.com', 'Data Analytics', 'jack6789', 15, 'Evening', '2026-03-17', NULL, 'pending', 'not_given', '1970-01-01 05:30:00', 'not_provided', '1970-01-01 05:30:00', NULL, 25000.00, 1200.00, 23800.00, 9000.00, 14800.00, 'partial', 'rehrejjeg', 'active', 4, 2, '2026-03-17 07:16:10', '2026-03-17 07:53:38'),
(20, 'REG-202603-0017', NULL, 1, 'internship', 'direct', '2026-03-20', 'sdfg', '123456788', '1bwghsyjd@gmail.co', 'python', '2000 batch', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 1500.00, 5.00, 1495.00, 0.00, 1495.00, 'unpaid', 'kiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiii', 'active', 2, 2, '2026-03-19 12:36:05', '2026-03-19 12:36:05'),
(21, 'REG-202603-0018', NULL, 1, 'course', 'direct', '2026-03-20', 'Ats Web Developer', '7896541236', 'john@gmail.com', 'Flutter Development', 'Morning Batch', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 15000.00, 0.00, 15000.00, 7500.00, 7500.00, 'partial', 'dwewewe', 'active', 2, 2, '2026-03-20 06:52:09', '2026-03-25 04:57:53'),
(22, 'REG-202603-0019', 23, 1, 'internship', 'direct', '2026-03-24', 'ishu', '876543455', 'ishu@gmail.com', 'Full stack development', NULL, NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid', NULL, 'active', 2, 2, '2026-03-24 06:17:29', '2026-03-24 06:17:29'),
(23, 'REG-202603-0020', NULL, 1, 'internship', 'direct', '2026-03-24', 'Test 1', '7402740298', 'ats.pythondeveloper05@gmail.com', 'Python Data Science', '2022', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 6000.00, 0.00, 6000.00, 0.00, 6000.00, 'unpaid', NULL, 'active', 7, 1, '2026-03-24 07:14:04', '2026-03-24 07:14:04'),
(24, 'REG-202603-0021', NULL, 1, 'internship', 'direct', '2026-03-24', 'Ats Designer', '9486333699', 'ats.designer05@gmail.com', NULL, NULL, 30, 'Morning', NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 5000.00, 1000.00, 4000.00, 0.00, 4000.00, 'unpaid', NULL, 'active', 15, 1, '2026-03-24 07:19:46', '2026-03-24 07:24:33'),
(25, 'REG-202603-0022', NULL, 1, 'internship', 'direct', '2026-03-24', 'Raju', '8148903261', 'raju@gmail.com', NULL, NULL, 30, 'Afternoon', NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid', 'test sakthivel add registartion', 'active', 15, 7, '2026-03-24 09:00:38', '2026-03-24 09:06:16'),
(26, 'REG-202603-0023', NULL, 1, 'course', 'direct', '2026-03-24', 'Nish', '6712345678', 'nish@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid', 'testing sakthi', 'active', 7, 7, '2026-03-24 09:46:42', '2026-03-24 09:46:42'),
(27, 'REG-202603-0024', NULL, 1, 'course', 'direct', '2026-03-24', 'Ravi', '8912345678', 'ravi@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 8000.00, 3000.00, 5000.00, 0.00, 5000.00, 'unpaid', 'sakthi test for amount', 'active', 12, 7, '2026-03-24 09:56:06', '2026-03-24 10:06:12'),
(28, 'REG-202603-0025', NULL, 1, 'course', 'direct', '2026-03-25', 'Vicky', '8148903261', 'vicky@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 5000.00, 0.00, 5000.00, 0.00, 5000.00, 'unpaid', NULL, 'draft', 7, 7, '2026-03-25 04:56:58', '2026-03-25 04:56:58'),
(29, 'REG-202603-0026', NULL, 1, 'internship', 'direct', '2026-03-25', 'Ak', '9790729124', 'ak@gmail.com', 'Fullstack', NULL, 21, 'Evening', NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 7500.00, 0.00, 7500.00, 0.00, 7500.00, 'unpaid', NULL, 'active', 15, 2, '2026-03-25 05:08:16', '2026-03-25 05:09:14'),
(30, 'REG-202603-0027', 28, 1, 'internship', 'direct', '2026-03-25', 'Priya Sharma', '9123456780', 'priya.sharma@example.com', 'UI UX Design', 'Morning Batch', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 1500.00, 0.00, 1500.00, 1500.00, 0.00, 'paid', 'dfw', 'active', 7, 7, '2026-03-25 05:17:56', '2026-03-25 05:41:47'),
(31, 'REG-202603-0028', NULL, 1, 'internship', 'direct', '2026-03-25', 'Chandru', '9790729124', 'chandru@gmail.com', 'Fullstack', NULL, NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 8000.00, 0.00, 8000.00, 3798.00, 4202.00, 'partial', NULL, 'active', 7, 7, '2026-03-25 05:44:48', '2026-03-25 05:45:46'),
(32, 'REG-202603-0029', NULL, 1, 'internship', 'direct', '2026-03-25', 'Sunil', '9874563210', 'sunil@gmail.com', 'Flutter Development', 'Morning Batch', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 150000.00, 0.00, 150000.00, 0.00, 150000.00, 'unpaid', 'wd', 'active', 2, 1, '2026-03-25 06:47:07', '2026-03-25 06:47:07'),
(33, 'REG-202603-0030', NULL, 1, 'internship', 'direct', '2026-03-25', 'Wersid', '9876543212', 'wesi@gmail.com', '3465678', 'r4t5yu', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, 'unpaid', 'dfghjk', 'active', 2, 2, '2026-03-25 07:43:58', '2026-03-25 07:43:58'),
(34, 'REG-202603-0031', 29, 1, 'course', 'direct', '2026-03-25', 'Sundar', '9876543210', 'sundar@gmail.com', 'Full Stack', 'Morning Batch', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 1500.00, 0.00, 1500.00, 0.00, 1500.00, 'unpaid', NULL, 'active', 2, 2, '2026-03-25 10:07:03', '2026-03-25 10:07:52'),
(35, 'REG-202603-0032', 30, 1, 'internship', 'direct', '2026-03-25', 'Johnson', '9876543210', 'johnson@gmail.com', 'Full Stack Webdevelopment', 'Morning Batch', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 1000.00, 0.00, 1000.00, 0.00, 1000.00, 'unpaid', NULL, 'active', 2, 2, '2026-03-25 10:10:37', '2026-03-25 10:26:11'),
(36, 'REG-202603-0033', 31, 1, 'internship', 'direct', '2026-03-26', 'Pavan', '9876543210', 'pavan@gmail.com', 'FSWD', 'Morning', 15, 'Morning', NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 5000.00, 0.00, 5000.00, 0.00, 5000.00, 'unpaid', 'wertyui', 'active', 4, 2, '2026-03-26 03:40:51', '2026-03-26 03:46:05'),
(37, 'REG-202603-0034', 32, 1, 'course', 'direct', '2026-03-26', 'Thanish', '9876543210', 'thanish@gmail.com', 'MERN', 'Morning', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 15000.00, 0.00, 15000.00, 0.00, 15000.00, 'unpaid', 'wertyuio', 'active', 2, 2, '2026-03-26 03:44:24', '2026-03-26 03:45:16'),
(38, 'REG-202603-0035', 33, 1, 'internship', 'direct', '2026-03-26', 'Anu', '9876543210', 'anu@gmail.com', 'MERN', 'Morning', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 2000.00, 0.00, 2000.00, 1000.00, 1000.00, 'partial', 'qwertyu', 'active', 2, 2, '2026-03-26 05:47:59', '2026-03-26 05:53:48'),
(39, 'REG-202603-0036', 34, 1, 'course', 'direct', '2026-03-27', 'Sakthivel', '6381814891', 'sakthivelpcseb@gmail.com', 'Full Stack Development', '3', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 12000.00, 0.00, 12000.00, 0.00, 12000.00, 'unpaid', 'Paid', 'active', 10, 10, '2026-03-27 07:16:38', '2026-03-27 07:17:52'),
(40, 'REG-202603-0037', 35, 1, 'course', 'direct', '2026-03-27', 'Arjun Kumar', '9876543210', 'arjun.kumar@gmail.com', 'Full Stack Development', 'Morning Batch', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 15000.00, 0.00, 15000.00, 0.00, 15000.00, 'unpaid', NULL, 'active', 2, 2, '2026-03-27 07:32:50', '2026-03-27 07:33:34'),
(41, 'REG-202603-0038', 36, 1, 'course', 'direct', '2026-03-27', 'Thowfig narayanan', '9952235465', 'glyzenats345@gmail.com', 'Python with AI', 'Evening batch', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 25000.00, 1000.00, 24000.00, 0.00, 24000.00, 'unpaid', NULL, 'active', 7, 7, '2026-03-27 07:54:28', '2026-03-27 07:56:37'),
(42, 'REG-202603-0039', 37, 1, 'internship', 'direct', '2026-03-27', 'Abraham lingan', '9952235465', 'ats.pythondeveloper08@gmail.com', 'UiUX', 'Evening batch', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 25000.00, 1500.00, 23500.00, 23500.00, 0.00, 'paid', NULL, 'active', 7, 7, '2026-03-27 09:05:57', '2026-03-27 10:58:19'),
(43, 'REG-202603-0040', 38, 1, 'course', 'direct', '2026-03-27', 'Rakshasthira', '9952235465', 'glyzenats345@gmail.com', 'AWS', 'morning batch', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 25000.00, 0.00, 25000.00, 0.00, 25000.00, 'unpaid', NULL, 'active', 7, 7, '2026-03-27 09:15:54', '2026-03-27 09:17:31'),
(44, 'REG-202603-0041', 39, 1, 'internship', 'direct', '2026-03-27', 'Thrishna madhavi', '9952235465', 'glyzenats345@gmail.com', 'UiUX', 'morning batch', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 25000.00, 2000.00, 23000.00, 23000.00, 0.00, 'paid', NULL, 'active', 7, 7, '2026-03-27 09:25:24', '2026-03-27 10:54:08'),
(45, 'REG-202603-0042', 40, 1, 'course', 'direct', '2026-03-27', 'Paralogam', '9952235465', 'ats.pythondeveloper08@gmail.com', 'Data Science', 'Evening batch', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 25000.00, 0.00, 25000.00, 0.00, 25000.00, 'unpaid', NULL, 'active', 7, 7, '2026-03-27 09:32:00', '2026-03-27 09:32:44'),
(46, 'REG-202603-0043', 41, 1, 'internship', 'direct', '2026-03-27', 'Divyarani', '9952235465', 'glyzenats345@gmail.com', 'Power BI', 'Evening batch', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 30000.00, 4000.00, 26000.00, 16567.00, 9433.00, 'partial', NULL, 'active', 7, 7, '2026-03-27 09:37:41', '2026-03-27 10:51:14'),
(47, 'REG-202603-0044', 42, 1, 'course', 'direct', '2026-03-27', 'Aadhavan', '9952235465', 'ats.pythondeveloper08@gmail.com', 'AWS', 'Evening batch', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 25000.00, 4000.00, 21000.00, 0.00, 21000.00, 'unpaid', NULL, 'active', 7, 7, '2026-03-27 09:49:19', '2026-03-27 09:58:28'),
(48, 'REG-202603-0045', 43, 1, 'internship', 'direct', '2026-03-27', 'Agilandeshwari', '9952235465', 'glyzenats345@gmail.com', 'Data Science', 'Evening batch', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 20000.00, 0.00, 20000.00, 20000.00, 0.00, 'paid', NULL, 'active', 7, 7, '2026-03-27 10:06:04', '2026-03-27 10:49:27'),
(49, 'REG-202603-0046', 44, 1, 'course', 'direct', '2026-03-27', 'Zeekashami', '9952235465', 'ats.pythondeveloper08@gmail.com', 'Data Analytics', 'Evening batch', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 25000.00, 0.00, 25000.00, 20000.00, 5000.00, 'partial', NULL, 'active', 7, 7, '2026-03-27 10:12:18', '2026-03-27 11:34:42'),
(50, 'REG-202603-0047', 45, 1, 'internship', 'direct', '2026-03-27', 'Vadakupattiramasamy', '9952235465', 'ats.pythondeveloper08@gmail.com', 'Python with AI', 'morning batch', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, 25000.00, 1000.00, 24000.00, 20000.00, 4000.00, 'partial', NULL, 'active', 7, 7, '2026-03-27 10:18:36', '2026-03-27 10:46:27');

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
(1, 5, 2, 2, '2026-03-07 11:36:49', 'draft', '2026-03-07 11:36:00', '2026-03-07 11:36:49'),
(2, 7, 2, 2, '2026-03-07 13:21:18', 'active', '2026-03-07 13:21:18', '2026-03-07 13:21:18'),
(3, 8, 2, 2, '2026-03-11 09:00:06', 'draft', '2026-03-11 09:00:06', '2026-03-11 09:00:06'),
(4, 10, 2, 2, '2026-03-26 05:02:07', 'active', '2026-03-11 12:33:18', '2026-03-26 05:02:07'),
(5, 12, 8, 2, '2026-03-14 06:50:06', 'active', '2026-03-14 06:49:03', '2026-03-14 06:50:06'),
(6, 15, 4, 1, '2026-03-25 04:56:03', 'draft', '2026-03-17 06:31:24', '2026-03-25 04:56:03'),
(7, 18, 4, 2, '2026-03-17 07:29:20', 'active', '2026-03-17 07:15:57', '2026-03-17 07:29:20'),
(8, 21, 2, 2, '2026-03-25 04:57:53', 'active', '2026-03-20 06:52:09', '2026-03-25 04:57:53'),
(9, 26, 7, 7, '2026-03-24 09:46:42', 'active', '2026-03-24 09:46:42', '2026-03-24 09:46:42'),
(10, 27, 12, 7, '2026-03-24 10:06:12', 'active', '2026-03-24 09:56:06', '2026-03-24 10:06:12'),
(11, 28, 7, 7, '2026-03-25 04:56:58', 'draft', '2026-03-25 04:56:58', '2026-03-25 04:56:58'),
(12, 34, 2, 2, '2026-03-25 10:07:52', 'active', '2026-03-25 10:07:03', '2026-03-25 10:07:52'),
(13, 37, 8, 2, '2026-03-26 05:43:28', 'active', '2026-03-26 03:44:24', '2026-03-26 05:43:28'),
(18, 39, 15, 10, '2026-03-27 07:18:24', 'active', '2026-03-27 07:18:24', '2026-03-27 07:18:24'),
(19, 49, 15, 7, '2026-03-27 10:38:17', 'active', '2026-03-27 10:38:17', '2026-03-27 10:38:17'),
(20, 47, 15, 7, '2026-03-27 10:38:25', 'active', '2026-03-27 10:38:25', '2026-03-27 10:38:25'),
(21, 45, 15, 7, '2026-03-27 10:38:34', 'active', '2026-03-27 10:38:34', '2026-03-27 10:38:34'),
(22, 43, 8, 7, '2026-03-27 10:38:48', 'active', '2026-03-27 10:38:48', '2026-03-27 10:38:48'),
(23, 41, 8, 7, '2026-03-27 10:38:57', 'active', '2026-03-27 10:38:57', '2026-03-27 10:38:57');

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
(1, 1, 2, 2, '2026-03-11 10:59:52', 7, 'Morning', '2026-03-11', '2026-03-16', 'completed', 'given', NULL, 'provided', NULL, 7, '2026-03-06 17:07:31', '2026-03-11 10:59:52'),
(2, 11, 7, 2, '2026-03-24 06:52:55', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-12 05:24:58', '2026-03-24 06:52:55'),
(3, 13, 2, 2, '2026-03-17 04:33:05', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-17 04:16:33', '2026-03-17 04:33:05'),
(4, 14, 2, 2, '2026-03-17 07:40:52', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-17 04:34:15', '2026-03-17 07:40:52'),
(5, 16, 1, 2, '2026-03-17 06:58:37', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-17 06:57:22', '2026-03-17 06:58:37'),
(6, 17, 7, 2, '2026-03-17 07:03:28', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-17 07:02:17', '2026-03-17 07:03:28'),
(7, 19, 4, 2, '2026-03-17 07:53:38', 15, 'Evening', '2026-03-17', NULL, 'pending', 'not_given', '1970-01-01 05:30:00', 'not_provided', '1970-01-01 05:30:00', NULL, '2026-03-17 07:16:10', '2026-03-17 07:53:38'),
(8, 20, 2, 2, '2026-03-19 12:36:05', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-19 12:36:05', '2026-03-19 12:36:05'),
(9, 22, 2, 2, '2026-03-24 06:17:29', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-24 06:17:29', '2026-03-24 06:17:29'),
(10, 23, 7, 1, '2026-03-24 07:14:04', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-24 07:14:04', '2026-03-24 07:14:04'),
(11, 24, 15, 1, '2026-03-24 07:24:33', 30, 'Morning', NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-24 07:19:46', '2026-03-24 07:24:33'),
(12, 25, 15, 7, '2026-03-24 09:06:16', 30, 'Afternoon', NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-24 09:00:38', '2026-03-24 09:06:16'),
(13, 29, 15, 2, '2026-03-25 05:09:14', 21, 'Evening', NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-25 05:08:16', '2026-03-25 05:09:14'),
(14, 30, 7, 7, '2026-03-25 05:41:47', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-25 05:17:56', '2026-03-25 05:41:47'),
(15, 31, 7, 7, '2026-03-25 05:45:46', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-25 05:44:48', '2026-03-25 05:45:46'),
(16, 32, 2, 1, '2026-03-25 06:47:07', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-25 06:47:07', '2026-03-25 06:47:07'),
(17, 33, 2, 2, '2026-03-25 07:43:58', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-25 07:43:58', '2026-03-25 07:43:58'),
(18, 35, 2, 2, '2026-03-25 10:26:11', NULL, NULL, NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-25 10:10:37', '2026-03-25 10:26:11'),
(19, 36, 4, 2, '2026-03-26 03:46:05', 15, 'Morning', NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-26 03:40:51', '2026-03-26 03:46:05'),
(33, 38, 8, 2, '2026-03-26 05:50:14', 7, 'Morning', NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-26 05:50:14', '2026-03-26 05:50:14'),
(34, 50, 4, 7, '2026-03-27 10:36:02', 15, 'Morning', '2026-03-01', '2026-03-15', 'completed', 'given', '2026-03-27 16:31:00', 'provided', '2026-03-27 16:31:00', 15, '2026-03-27 10:36:02', '2026-03-27 11:01:52'),
(35, 48, 4, 7, '2026-03-27 10:36:26', 30, 'Evening', '2026-03-01', '2026-03-30', 'in_progress', 'not_given', '1970-01-01 05:30:00', 'not_provided', '1970-01-01 05:30:00', NULL, '2026-03-27 10:36:26', '2026-03-27 11:05:18'),
(36, 46, 4, 7, '2026-03-27 10:36:42', 21, 'Evening', NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-27 10:36:42', '2026-03-27 10:36:42'),
(37, 44, 4, 7, '2026-03-27 10:37:17', 15, 'Morning', NULL, NULL, 'pending', 'not_given', NULL, 'not_provided', NULL, NULL, '2026-03-27 10:37:17', '2026-03-27 10:37:17'),
(38, 42, 4, 7, '2026-03-27 10:37:59', 15, 'Evening', '2026-03-01', '2026-03-15', 'completed', 'given', '2026-03-27 16:37:00', 'provided', '2026-03-27 16:37:00', 15, '2026-03-27 10:37:59', '2026-03-27 11:07:25');

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
(2, 1, 1, 2, 2, 2, 1000.00, '2026-03-07', 'cash', 'partial', '11234567891', 'RCPT-202603-0002', 'approved', 'He paid full amount', '2026-03-07 11:09:46', '2026-03-07 11:09:46'),
(3, 10, 1, 2, 2, 2, 1000.00, '2026-03-11', 'cash', 'partial', '1234567890-', 'RCPT-202603-0003', 'approved', 'tyhujikohvjk fxcgvhbjnkmlfgvhbjkl', '2026-03-11 12:35:00', '2026-03-11 12:35:00'),
(4, 19, 1, 2, 2, 2, 4000.00, '2026-03-17', 'cash', 'partial', '77543bt4389t57', 'RCPT-202603-0004', 'approved', 'hyuyfgyfegfyegyegyegyefygfygf', '2026-03-17 07:22:26', '2026-03-17 07:22:26'),
(5, 19, 1, 4, 2, 2, 5000.00, '2026-03-17', 'cash', 'partial', '1234', 'RCPT-202603-0005', 'approved', 'dfghyjhgf', '2026-03-17 07:53:38', '2026-03-17 07:53:38'),
(6, 21, 1, 2, 2, 2, 7500.00, '2026-03-25', 'upi', 'partial', '11234567890', 'RCPT-202603-0006', 'approved', 'wdfwefewer', '2026-03-25 04:57:52', '2026-03-25 04:57:52'),
(7, 30, 1, 7, 2, 2, 750.00, '2026-03-25', 'cash', 'partial', '11234567890', 'RCPT-202603-0007', 'approved', 'wedw', '2026-03-25 05:34:47', '2026-03-25 05:34:47'),
(8, 30, 1, 7, 7, 7, 750.00, '2026-03-25', 'cash', 'full', 'fgergerge', 'RCPT-202603-0008', 'approved', 'ergerge', '2026-03-25 05:41:45', '2026-03-25 05:41:45'),
(9, 31, 1, 7, 7, 7, 3798.00, '2026-03-25', 'cash', 'partial', '11234567890', 'RCPT-202603-0009', 'approved', 'dwff', '2026-03-25 05:45:44', '2026-03-25 05:45:44'),
(10, 38, 1, 2, 2, 2, 1000.00, '2026-03-26', 'upi', 'partial', '123456789', 'RCPT-202603-0010', 'approved', 'qwertyuio', '2026-03-26 05:53:47', '2026-03-26 05:53:47'),
(11, 50, 1, 7, 7, 7, 20000.00, '2026-03-27', 'upi', 'partial', '77543bt4389t57', 'RCPT-202603-0011', 'approved', '4000 balance', '2026-03-27 10:46:27', '2026-03-27 10:46:27'),
(12, 48, 1, 7, 7, 7, 20000.00, '2026-03-27', 'cash', 'full', '16549486546fgr5+65', 'RCPT-202603-0012', 'approved', 'Full paiod', '2026-03-27 10:49:27', '2026-03-27 10:49:27'),
(13, 46, 1, 7, 7, 7, 16567.00, '2026-03-27', 'card', 'partial', '165145FT9800767', 'RCPT-202603-0013', 'approved', 'balance 10000 some thing', '2026-03-27 10:51:14', '2026-03-27 10:51:14'),
(14, 44, 1, 7, 7, 7, 9857.00, '2026-03-27', 'cheque', 'partial', '151984865577dg', 'RCPT-202603-0014', 'approved', 'balance 14000', '2026-03-27 10:52:29', '2026-03-27 10:52:29'),
(15, 44, 1, 7, 7, 7, 13143.00, '2026-03-27', 'cash', 'full', '56164846566jty1646', 'RCPT-202603-0015', 'approved', 'full paid', '2026-03-27 10:54:08', '2026-03-27 10:54:08'),
(16, 42, 1, 7, 7, 7, 10000.00, '2026-03-27', 'cash', 'partial', '65116811868TYFFty77', 'RCPT-202603-0016', 'approved', '10000 finished', '2026-03-27 10:55:50', '2026-03-27 10:55:50'),
(17, 42, 1, 7, 7, 7, 13000.00, '2026-03-27', 'bank_transfer', 'partial', '6151981989865(ETYFYD', 'RCPT-202603-0017', 'approved', 'balance 10500', '2026-03-27 10:56:41', '2026-03-27 10:56:41'),
(18, 42, 1, 7, 7, 7, 500.00, '2026-03-27', 'cash', 'full', '941981941878dggfge685', 'RCPT-202603-0018', 'approved', 'Paid fully', '2026-03-27 10:58:19', '2026-03-27 10:58:19'),
(19, 49, 1, 7, 15, 15, 20000.00, '2026-03-27', 'cheque', 'partial', '65191916916GY&09968796', 'RCPT-202603-0019', 'approved', NULL, '2026-03-27 11:34:42', '2026-03-27 11:34:42');

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
(1, 1, 'Arun', 'male', '1987-07-21', 'Ram Nagar', 'MCA', 'Anna Univercity', '2010', 'Ram', '796541230114', 'Sample', '987452361', '1221322344325353', NULL, NULL, 'He will come to class by monday', '2026-03-06 17:14:52', '2026-03-11 08:32:53'),
(2, 5, 'Raghul', 'male', '1987-05-22', 'Ram Nagar', 'MCA', 'Anna Univercity', '2010', 'Ram', '796541230114', 'Sample', '987452361', '1221322344325353', NULL, NULL, 'Want to follow him', '2026-03-07 11:36:49', '2026-03-07 11:36:49'),
(3, 7, 'Ranjith', 'male', '1997-01-21', 'Ram Nagar', 'MCA', 'Anna Univercity', '2010', 'Ram', '796541230114', 'Sample', '987452361', '1221322344325353', NULL, NULL, 'This is sample', '2026-03-07 13:21:18', '2026-03-07 13:21:18'),
(5, 10, 'Wednesday', 'male', '1987-01-21', 'hgduqhdoiwj', 'MCA', 'asdfghjk', '2010', 'sample', '9876543210', 'ertyuio', '987654321', '1234567890', NULL, NULL, 'dfghjk', '2026-03-11 12:34:13', '2026-03-11 12:34:13'),
(6, 11, 'Ramesh', 'male', '2001-01-01', 'Ram nagar masjid, Nehru street.', 'BE', 'ATS', '2021', 'wertyuiop', '9876543210', 'qwertyuiop', '9876543210', '656565656565', NULL, NULL, 'qwertyuiop[', '2026-03-12 05:26:11', '2026-03-24 06:52:55'),
(7, 12, 'Pranesh', 'male', '2001-01-01', 'wertyuiop', 'BE', 'qwet', '2023', 'Ramesh', '9876543210', 'qwertyui', '9876543210', '989898989898', NULL, NULL, 'sdfghjk', '2026-03-14 06:49:05', '2026-03-14 06:49:05'),
(8, 13, 'Hema', 'female', NULL, 'Ram Nagar', 'MCA', 'Anna Univercity', NULL, 'ffer', '796541230114', 'ferferfer', NULL, NULL, NULL, NULL, NULL, '2026-03-17 04:33:06', '2026-03-17 04:33:06'),
(9, 15, 'Joseph Glyzen', 'male', '2004-05-17', 'Coimbatore', 'cs', 'Karpgam', '2026', 'Lorence', '7418529635', 'Driver', NULL, NULL, NULL, NULL, NULL, '2026-03-17 06:33:28', '2026-03-17 06:33:28'),
(10, 16, 'Joseph Glyzen', 'male', '2004-05-17', 'Coimbatore', 'cs', 'Karpgam', '2026', 'Lorence', '7418529635', 'Driver', NULL, '46544668', NULL, NULL, NULL, '2026-03-17 06:58:37', '2026-03-17 06:58:37'),
(11, 17, 'Meena Joseph', 'male', NULL, 'rtgrtgrtrtrtrtbh', 'rger', 'Anna Univercity', '2010', 'mom', '32435344567', 'Sample', '9876543234', '2345678909876', NULL, NULL, 'sdfghj', '2026-03-17 07:03:28', '2026-03-17 07:03:28'),
(12, 19, 'Ezekil jackson', 'male', '2026-03-13', 'Sulur, Coimbatore', 'bsc cs', 'Karpgam', '2026', 'Kamarasu', '7418529635', 'Driver', '7845895623', '4654466845', NULL, NULL, 'fgghhgrgthjegjfdgdgehgfhgtyete', '2026-03-17 07:20:01', '2026-03-17 07:20:18'),
(13, 18, 'jeevi', 'female', '1985-01-21', 'Ram Nagar', 'MCA', 'Anna Univercity', '2010', 'rg', '2132536344364', 'sfcsdfsdfwsf', NULL, NULL, NULL, NULL, NULL, '2026-03-17 07:20:03', '2026-03-17 07:20:03'),
(14, 14, 'Anjali Nair', 'female', '2000-05-12', 'Ram Nagar', NULL, 'Anna Univercity', '2010', 'dad', '796541230114', 'Sample', NULL, NULL, NULL, NULL, NULL, '2026-03-17 07:40:52', '2026-03-17 07:40:52'),
(15, 20, 'sdfg', 'female', '2026-03-10', 'nthgj7yhycvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvv,', 'b.sc c', 'Nirmala college', '2033', 'j5htgk3iiiiiiiiiiiiiiii', '1234567', ',6kkkkkkkkkkkkkkkkkkkkkkk12', '12345678', '123456789', NULL, NULL, 'kwfyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyuh', '2026-03-19 12:36:05', '2026-03-19 12:36:05'),
(16, 21, 'Ats Web Developer', 'male', NULL, 'Ram nagar', 'Mca', 'Anna Univercity', '2010', 'Ram', '7965412301', 'Sample', '987452361', NULL, NULL, NULL, 'eweweew', '2026-03-20 06:52:10', '2026-03-20 06:52:10'),
(17, 23, 'Test 1', 'male', '2000-03-12', '7, nehru street, ramnagar coimbatore', 'Be In Textiles', 'Krishna College Of Arts And Science', '2025', 'Ranjith', '895623562', 'Teacher', '7418526303', '147852963012', NULL, NULL, NULL, '2026-03-24 07:14:04', '2026-03-24 07:14:04'),
(18, 24, 'Ats Designer', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-24 07:19:46', '2026-03-24 07:19:46'),
(19, 25, 'Raju', 'male', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-24 09:00:38', '2026-03-24 09:00:38'),
(20, 26, 'Nish', 'male', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-24 09:46:42', '2026-03-24 09:46:42'),
(21, 27, 'Ravi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-24 09:56:06', '2026-03-24 09:56:06'),
(22, 28, 'Vicky', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-25 04:56:58', '2026-03-25 04:56:58'),
(23, 29, 'Ak', 'male', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-25 05:08:16', '2026-03-25 05:08:16'),
(24, 30, 'Priya Sharma', 'female', '2000-01-12', 'Ram Nagar', 'MCA', 'Anna Univercity', '2010', 'asdcsafvsdg', '32435344567', 'Sample', NULL, '122132234432', NULL, NULL, 'wed', '2026-03-25 05:18:36', '2026-03-25 05:18:36'),
(25, 31, 'Chandru', 'male', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-25 05:44:48', '2026-03-25 05:44:48'),
(26, 32, 'Sunil', 'male', '2000-01-21', 'Ram nagar', 'Mca', 'Anna Univercity', '2010', 'Ram', '7965412301', 'Sample', '987452361', NULL, NULL, NULL, 'e', '2026-03-25 06:47:08', '2026-03-25 06:47:08'),
(27, 33, 'Wersid', 'male', '2003-02-18', 'Weyuqwertyutryu', 'Y6u7', 'Wertyu7', '2024', 'Fghdh', '2345678923', 'Dfrgtyuio', '5657453452', '334564565678', NULL, NULL, 'wsdfghjk', '2026-03-25 07:43:58', '2026-03-25 07:43:58'),
(28, 34, 'Sundar', 'male', '1999-06-08', 'Ram Nagar', 'MCA', 'Anna Univercity', '2010', 'John', '3243534456', 'Sample', '987452361', '122132234432', NULL, NULL, NULL, '2026-03-25 10:07:53', '2026-03-25 10:07:53'),
(29, 35, 'Johnson', 'male', '2000-06-15', 'Ram Nagar', 'MCA', 'Anna Univercity', '2010', 'Ram', '7965412301', 'Sample', '987452361', '122132234432', NULL, NULL, NULL, '2026-03-25 10:11:43', '2026-03-25 10:11:43'),
(30, 36, 'Pavan', 'male', '2001-01-01', 'Ram nagar masjid, Nehru street', 'BE', 'ATS', '2021', 'wertyuiop', '9876543210', 'qwertyuiop', '9876543210', '656565656565', NULL, NULL, 'wertyuio', '2026-03-26 03:42:05', '2026-03-26 03:42:05'),
(31, 37, 'Thanish', 'male', '2001-01-01', 'Ram nagar masjid, Nehru street', 'BE', 'ATS', '2021', 'wertyuiop', '9876543210', 'qwertyuiop', '9876543210', '656565656565', NULL, NULL, 'qwertyuio', '2026-03-26 03:45:18', '2026-03-26 03:45:18'),
(32, 38, 'Anu', 'female', '2001-01-01', 'Ram nagar masjid, Nehru street', 'BE', 'ATS', '2021', 'wertyuiop', '9876543210', 'qwertyuiop', '9876543210', '656565656565', NULL, NULL, 'wertyuio', '2026-03-26 05:48:48', '2026-03-26 05:48:48'),
(33, 39, 'Sakthivel', 'male', '2004-09-02', 'Dindigul', 'B.E', 'NPR', '2026', 'periyasamy', '9790222576', 'Bussiness', NULL, NULL, NULL, NULL, NULL, '2026-03-27 07:17:52', '2026-03-27 07:17:52'),
(34, 40, 'Arjun Kumar', 'male', '2000-10-22', 'Ram Nagar', 'MCA', 'Anna Univercity', '2010', 'Ram', '3243534456', 'Sample', NULL, '122132234432', NULL, NULL, NULL, '2026-03-27 07:33:35', '2026-03-27 07:33:35'),
(35, 41, 'Thowfig narayanan', 'male', '2002-06-11', '!4/A , RVS Nagar, Sulur New Bus stand,\r\nSulur, Coimbatore, 641402', 'bsc cs', 'Karpagam College of Engineering', '2026', 'Ameer', '9952235465', 'Driver', '9952235465', '457896123456', NULL, NULL, NULL, '2026-03-27 07:56:37', '2026-03-27 07:56:37'),
(36, 42, 'Abraham lingan', 'male', '2000-06-06', '3rd appartment, behind new busstand, sulur, Coimbatore', 'BCA', 'Kathir College of arts and science', '2026', 'Kalaivani', '9952235465', 'Auditor', '9952235465', '784512855226', NULL, NULL, NULL, '2026-03-27 09:07:41', '2026-03-27 09:07:41'),
(37, 43, 'Rakshasthira', 'female', '1996-05-15', 'NH street , Siruvani tank, Sulur , coimbatore', 'BCA', 'Hindustan college of arts and science', '2026', 'Raghavan', '9952235465', 'Dojo Coach', '9952235465', '784517896325', NULL, NULL, NULL, '2026-03-27 09:17:31', '2026-03-27 09:17:31'),
(38, 44, 'Thrishna madhavi', 'female', '2002-03-20', 'Theppakadu forest,Valparai, Coimbatore', 'BCom', 'Kathir College of arts and science', '2026', 'Ranbeer Paul', '9952235465', 'Car Mechanic', NULL, '365412874658', NULL, NULL, NULL, '2026-03-27 09:27:23', '2026-03-27 09:27:23'),
(39, 45, 'Paralogam', 'male', '2005-10-21', '14/a, nh strret,\r\nGandhipuram, Coimbatore', 'BBA', 'Kathir College of arts and science', '2026', 'Raja sima reddy', '9952235465', 'businsess', NULL, '745812369854', NULL, NULL, NULL, '2026-03-27 09:32:44', '2026-03-27 09:32:44'),
(40, 46, 'Divyarani', 'female', '2005-08-25', '58/jh,Bypass road, Dindugul', 'bsc cs', 'Karpagam College of Engineering', '2026', 'Raghavan', '9952235465', 'Dojo Coach', '9952235465', '145236975846', NULL, NULL, NULL, '2026-03-27 09:38:35', '2026-03-27 09:38:35'),
(41, 47, 'Aadhavan', 'male', '2001-06-27', 'Aachi mess,Trichy rOAD, PALLADAM', 'bsc cs', 'Karpagam College of Engineering', '2026', 'Ameer', '9952235465', 'Driver', '9952235465', '852369741521', NULL, NULL, NULL, '2026-03-27 09:58:28', '2026-03-27 09:58:28'),
(42, 48, 'Agilandeshwari', 'female', '2005-02-01', 'Sulur,Coimbatore', 'bsc cs', 'Kathir College of arts and science', '2026', 'Raghavan', '9952235465', 'Driver', '9952235465', '798465165481', NULL, NULL, NULL, '2026-03-27 10:07:21', '2026-03-27 10:07:21'),
(43, 49, 'Zeekashami', 'male', '2004-02-19', 'Sulur, Coimbatore', 'BCA', 'Kathir College of arts and science', '2026', 'Kamarasu', '9952235465', 'Auditor', '9952235465', '451265983265', NULL, NULL, NULL, '2026-03-27 10:13:12', '2026-03-27 10:13:12'),
(44, 50, 'Vadakupattiramasamy', 'male', '2001-05-11', 'NH Strret, Gandhipuram,\r\nCoimbatore', 'BCA', 'Hindustan college of arts and science', '2026', 'Lorence', '9952235465', 'Car Mechanic', '9952235465', '478013636589', NULL, NULL, NULL, '2026-03-27 10:19:17', '2026-03-27 10:19:17');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `report_date` date NOT NULL,
  `department` enum('front_office','hr','marketing') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`id`, `user_id`, `role_id`, `branch_id`, `report_date`, `department`, `created_at`, `updated_at`) VALUES
(1, 2, 2, 1, '2026-03-19', 'front_office', '2026-03-19 07:06:07', '2026-03-19 07:06:07'),
(2, 3, 3, 1, '2026-03-20', 'hr', '2026-03-20 10:40:32', '2026-03-20 10:40:32'),
(3, 1, 1, 1, '2026-03-06', 'front_office', '2026-03-24 07:22:22', '2026-03-24 07:22:22'),
(4, 1, 1, 1, '2026-03-23', 'front_office', '2026-03-24 07:31:06', '2026-03-24 07:31:06'),
(5, 1, 1, 1, '2026-03-24', 'front_office', '2026-03-24 07:31:36', '2026-03-24 07:31:36'),
(6, 3, 3, 1, '2026-03-24', 'hr', '2026-03-24 09:18:08', '2026-03-24 09:18:08'),
(7, 7, 2, 1, '2026-03-24', 'front_office', '2026-03-24 09:48:43', '2026-03-24 09:48:43'),
(8, 3, 3, 1, '2026-03-25', 'hr', '2026-03-25 07:32:38', '2026-03-25 07:32:38');

-- --------------------------------------------------------

--
-- Table structure for table `report_activity`
--

CREATE TABLE `report_activity` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `fresh_calls` int(11) DEFAULT 0,
  `follow_calls` int(11) DEFAULT 0,
  `msg_sent` int(11) DEFAULT 0,
  `mail_sent` int(11) DEFAULT 0,
  `total_calls` int(11) DEFAULT 0,
  `promo` int(11) DEFAULT 0,
  `reference` int(11) DEFAULT 0,
  `db_calls` int(11) DEFAULT 0,
  `total_reg` int(11) DEFAULT 0,
  `hods_met` int(11) DEFAULT 0,
  `asst_prof_met` int(11) DEFAULT 0,
  `colleges_visited` int(11) DEFAULT 0,
  `companies_visited` int(11) DEFAULT 0,
  `forum_posting` int(11) DEFAULT 0,
  `students_ref` int(11) DEFAULT 0,
  `workshop` int(11) DEFAULT 0,
  `oncampus_training` int(11) DEFAULT 0,
  `project_taken` int(11) DEFAULT 0,
  `billing` decimal(10,2) DEFAULT 0.00,
  `fresh_collection` decimal(10,2) DEFAULT 0.00,
  `old_collection` decimal(10,2) DEFAULT 0.00,
  `total_collection` decimal(10,2) DEFAULT 0.00,
  `registrations` int(11) DEFAULT 0,
  `walkins` int(11) DEFAULT 0,
  `conversion_ratio` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `report_activity`
--

INSERT INTO `report_activity` (`id`, `report_id`, `fresh_calls`, `follow_calls`, `msg_sent`, `mail_sent`, `total_calls`, `promo`, `reference`, `db_calls`, `total_reg`, `hods_met`, `asst_prof_met`, `colleges_visited`, `companies_visited`, `forum_posting`, `students_ref`, `workshop`, `oncampus_training`, `project_taken`, `billing`, `fresh_collection`, `old_collection`, `total_collection`, `registrations`, `walkins`, `conversion_ratio`, `created_at`) VALUES
(1, 1, 3, 3, 2, 3, 3, 3, 3, 3, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0, 3.00, 3.00, 3.00, 3.00, 3, 3, 3.00, '2026-03-19 08:48:41');

-- --------------------------------------------------------

--
-- Table structure for table `report_followups`
--

CREATE TABLE `report_followups` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `ref_id` int(11) NOT NULL,
  `status` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `follow_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_hourly`
--

CREATE TABLE `report_hourly` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `time_slot` varchar(50) DEFAULT NULL,
  `particulars` text DEFAULT NULL,
  `activities` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `report_hourly`
--

INSERT INTO `report_hourly` (`id`, `report_id`, `time_slot`, `particulars`, `activities`, `created_at`) VALUES
(2, 1, '9:30', '10:30', 'sdfffefref', '2026-03-19 12:53:15'),
(3, 1, 'bhjky', 'ghujhjhu', 'jhbjkiujh', '2026-03-19 12:53:15'),
(4, 1, 'hhik', 'jkhjk', 'ghjhkujh', '2026-03-19 12:53:15'),
(5, 1, 'ghjhi', 'hjhjkjuo', 'jkhjkujh', '2026-03-19 12:53:15'),
(6, 1, 'ujh', 'hujhik', 'jkhkujhol', '2026-03-19 12:53:15'),
(7, 2, 'eeeeeeeeeeeej', 'eeeeeeeeehgys', 'ssssssssssssssssssssssss', '2026-03-20 10:43:42'),
(8, 2, 'dddddddddddddddddddddddddddddddddddd', 'ddddddddddddddddddddddd', 'dddddddddddddddddddddddddddd', '2026-03-20 10:43:42');

-- --------------------------------------------------------

--
-- Table structure for table `report_interviews`
--

CREATE TABLE `report_interviews` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `candidate_name` varchar(150) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `interview_date` date DEFAULT NULL,
  `interview_status` varchar(100) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_registrations`
--

CREATE TABLE `report_registrations` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `college` varchar(255) DEFAULT NULL,
  `date_of_reg` date DEFAULT NULL,
  `course` varchar(150) DEFAULT NULL,
  `billing` decimal(10,2) DEFAULT NULL,
  `collection` decimal(10,2) DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT NULL,
  `payment_mode` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `report_registrations`
--

INSERT INTO `report_registrations` (`id`, `report_id`, `name`, `department`, `contact_no`, `college`, `date_of_reg`, `course`, `billing`, `collection`, `balance`, `payment_mode`, `created_at`) VALUES
(3, 1, 'mm', 'mmmkkhhh', 'jjkj', 'hhh', '0000-00-00', 'hhj', 10000.00, 10000.00, 5.00, 'cash', '2026-03-19 12:46:29');

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
(2164, 6, 10, 0, 0, 0, 0, '2026-03-19 10:28:31', '2026-03-19 10:28:31', 0),
(2165, 6, 32, 0, 0, 0, 0, '2026-03-19 10:28:32', '2026-03-19 10:28:32', 0),
(2166, 6, 33, 0, 0, 0, 0, '2026-03-19 10:28:32', '2026-03-19 10:28:32', 0),
(2167, 6, 34, 0, 0, 0, 0, '2026-03-19 10:28:32', '2026-03-19 10:28:32', 0),
(2168, 6, 38, 0, 0, 0, 0, '2026-03-19 10:28:33', '2026-03-19 10:28:33', 0),
(2169, 6, 7, 0, 0, 0, 0, '2026-03-19 10:28:33', '2026-03-19 10:28:33', 0),
(2170, 6, 1, 0, 0, 0, 0, '2026-03-19 10:28:33', '2026-03-19 10:28:33', 0),
(2171, 6, 11, 0, 0, 0, 0, '2026-03-19 10:28:34', '2026-03-19 10:28:34', 0),
(2172, 6, 5, 0, 0, 0, 0, '2026-03-19 10:28:34', '2026-03-19 10:28:34', 0),
(2173, 6, 12, 1, 1, 1, 1, '2026-03-19 10:28:34', '2026-03-19 10:28:34', 0),
(2174, 6, 13, 0, 0, 0, 0, '2026-03-19 10:28:34', '2026-03-19 10:28:34', 0),
(2175, 6, 14, 0, 0, 0, 0, '2026-03-19 10:28:35', '2026-03-19 10:28:35', 0),
(2176, 6, 16, 0, 0, 0, 0, '2026-03-19 10:28:35', '2026-03-19 10:28:35', 0),
(2177, 6, 17, 0, 0, 0, 0, '2026-03-19 10:28:35', '2026-03-19 10:28:35', 0),
(2178, 6, 18, 0, 0, 0, 0, '2026-03-19 10:28:36', '2026-03-19 10:28:36', 0),
(2179, 6, 19, 0, 0, 0, 0, '2026-03-19 10:28:36', '2026-03-19 10:28:36', 0),
(2180, 6, 20, 1, 1, 1, 1, '2026-03-19 10:28:36', '2026-03-19 10:28:36', 0),
(2182, 6, 48, 1, 1, 1, 1, '2026-03-19 10:28:37', '2026-03-19 10:28:37', 0),
(2183, 6, 6, 0, 0, 0, 0, '2026-03-19 10:28:37', '2026-03-19 10:28:37', 0),
(2184, 6, 46, 1, 1, 1, 1, '2026-03-19 10:28:38', '2026-03-19 10:28:38', 0),
(2185, 6, 47, 1, 1, 1, 1, '2026-03-19 10:28:38', '2026-03-19 10:28:38', 0),
(2186, 6, 55, 1, 1, 1, 1, '2026-03-19 10:28:38', '2026-03-19 10:28:38', 0),
(2187, 6, 39, 0, 0, 0, 0, '2026-03-19 10:28:38', '2026-03-19 10:28:38', 0),
(2188, 6, 40, 0, 0, 0, 0, '2026-03-19 10:28:39', '2026-03-19 10:28:39', 0),
(2189, 6, 41, 0, 0, 0, 0, '2026-03-19 10:28:39', '2026-03-19 10:28:39', 0),
(2190, 6, 42, 0, 0, 0, 0, '2026-03-19 10:28:39', '2026-03-19 10:28:39', 0),
(2191, 6, 43, 0, 0, 0, 0, '2026-03-19 10:28:40', '2026-03-19 10:28:40', 0),
(2192, 6, 44, 0, 0, 0, 0, '2026-03-19 10:28:40', '2026-03-19 10:28:40', 0),
(2193, 6, 45, 1, 1, 1, 1, '2026-03-19 10:28:40', '2026-03-19 10:28:40', 0),
(2194, 6, 56, 0, 0, 0, 0, '2026-03-19 10:28:41', '2026-03-19 10:28:41', 0),
(2195, 6, 57, 0, 0, 0, 0, '2026-03-19 10:28:41', '2026-03-19 10:28:41', 0),
(2196, 6, 22, 0, 0, 0, 0, '2026-03-19 10:28:41', '2026-03-19 10:28:41', 0),
(2197, 6, 23, 0, 0, 0, 0, '2026-03-19 10:28:41', '2026-03-19 10:28:41', 0),
(2198, 6, 25, 0, 0, 0, 0, '2026-03-19 10:28:42', '2026-03-19 10:28:42', 0),
(2199, 6, 26, 0, 0, 0, 0, '2026-03-19 10:28:42', '2026-03-19 10:28:42', 0),
(2200, 6, 58, 0, 0, 0, 0, '2026-03-19 10:28:42', '2026-03-19 10:28:42', 0),
(2201, 6, 27, 0, 0, 0, 0, '2026-03-19 10:28:43', '2026-03-19 10:28:43', 0),
(2202, 6, 63, 0, 0, 0, 0, '2026-03-19 10:28:43', '2026-03-19 10:28:43', 0),
(2203, 6, 65, 0, 0, 0, 0, '2026-03-19 10:28:43', '2026-03-19 10:28:43', 0),
(2204, 6, 59, 0, 0, 0, 0, '2026-03-19 10:28:44', '2026-03-19 10:28:44', 0),
(2205, 6, 60, 0, 0, 0, 0, '2026-03-19 10:28:44', '2026-03-19 10:28:44', 0),
(2206, 6, 66, 1, 1, 1, 1, '2026-03-19 10:28:44', '2026-03-19 10:28:44', 0),
(2207, 6, 67, 1, 1, 1, 1, '2026-03-19 10:28:44', '2026-03-19 10:28:44', 0),
(2208, 6, 68, 1, 1, 1, 1, '2026-03-19 10:28:45', '2026-03-19 10:28:45', 0),
(2209, 6, 36, 1, 1, 1, 1, '2026-03-19 10:28:45', '2026-03-19 10:28:45', 0),
(2211, 6, 49, 0, 0, 0, 0, '2026-03-19 10:28:46', '2026-03-19 10:28:46', 0),
(2212, 6, 50, 0, 0, 0, 0, '2026-03-19 10:28:46', '2026-03-19 10:28:46', 0),
(2213, 6, 51, 1, 1, 1, 1, '2026-03-19 10:28:46', '2026-03-19 10:28:46', 0),
(2214, 6, 52, 0, 0, 0, 0, '2026-03-19 10:28:46', '2026-03-19 10:28:46', 0),
(2313, 2, 10, 0, 0, 0, 0, '2026-03-19 10:40:34', '2026-03-19 10:40:34', 0),
(2314, 2, 32, 1, 1, 1, 1, '2026-03-19 10:40:34', '2026-03-19 10:40:34', 0),
(2315, 2, 33, 0, 0, 0, 0, '2026-03-19 10:40:35', '2026-03-19 10:40:35', 0),
(2316, 2, 34, 0, 0, 0, 0, '2026-03-19 10:40:35', '2026-03-19 10:40:35', 0),
(2317, 2, 38, 0, 0, 0, 0, '2026-03-19 10:40:35', '2026-03-19 10:40:35', 0),
(2318, 2, 7, 0, 0, 0, 0, '2026-03-19 10:40:35', '2026-03-19 10:40:35', 0),
(2319, 2, 1, 0, 0, 0, 0, '2026-03-19 10:40:36', '2026-03-19 10:40:36', 0),
(2320, 2, 11, 0, 0, 0, 0, '2026-03-19 10:40:36', '2026-03-19 10:40:36', 0),
(2321, 2, 5, 0, 0, 0, 0, '2026-03-19 10:40:36', '2026-03-19 10:40:36', 0),
(2322, 2, 12, 1, 1, 1, 1, '2026-03-19 10:40:37', '2026-03-19 10:40:37', 0),
(2323, 2, 13, 1, 1, 1, 1, '2026-03-19 10:40:37', '2026-03-19 10:40:37', 0),
(2324, 2, 14, 1, 1, 1, 1, '2026-03-19 10:40:37', '2026-03-19 10:40:37', 0),
(2325, 2, 16, 1, 1, 1, 1, '2026-03-19 10:40:37', '2026-03-19 10:40:37', 0),
(2326, 2, 17, 1, 1, 1, 1, '2026-03-19 10:40:38', '2026-03-19 10:40:38', 0),
(2327, 2, 18, 1, 0, 0, 0, '2026-03-19 10:40:38', '2026-03-19 10:40:38', 0),
(2328, 2, 19, 0, 0, 0, 0, '2026-03-19 10:40:38', '2026-03-19 10:40:38', 0),
(2329, 2, 20, 1, 1, 1, 1, '2026-03-19 10:40:39', '2026-03-19 10:40:39', 0),
(2330, 2, 48, 1, 1, 1, 1, '2026-03-19 10:40:39', '2026-03-19 10:40:39', 0),
(2331, 2, 6, 0, 0, 0, 0, '2026-03-19 10:40:39', '2026-03-19 10:40:39', 0),
(2332, 2, 46, 1, 1, 1, 1, '2026-03-19 10:40:39', '2026-03-19 10:40:39', 0),
(2333, 2, 47, 1, 1, 1, 1, '2026-03-19 10:40:40', '2026-03-19 10:40:40', 0),
(2334, 2, 55, 1, 1, 1, 1, '2026-03-19 10:40:40', '2026-03-19 10:40:40', 0),
(2335, 2, 39, 1, 1, 1, 1, '2026-03-19 10:40:40', '2026-03-19 10:40:40', 0),
(2336, 2, 40, 1, 1, 1, 1, '2026-03-19 10:40:41', '2026-03-19 10:40:41', 0),
(2337, 2, 41, 1, 1, 1, 1, '2026-03-19 10:40:41', '2026-03-19 10:40:41', 0),
(2338, 2, 42, 1, 1, 1, 1, '2026-03-19 10:40:41', '2026-03-19 10:40:41', 0),
(2339, 2, 43, 1, 1, 1, 1, '2026-03-19 10:40:42', '2026-03-19 10:40:42', 0),
(2340, 2, 44, 1, 1, 1, 1, '2026-03-19 10:40:42', '2026-03-19 10:40:42', 0),
(2341, 2, 45, 1, 1, 1, 1, '2026-03-19 10:40:42', '2026-03-19 10:40:42', 0),
(2342, 2, 56, 1, 1, 1, 1, '2026-03-19 10:40:43', '2026-03-19 10:40:43', 0),
(2343, 2, 57, 1, 1, 1, 1, '2026-03-19 10:40:43', '2026-03-19 10:40:43', 0),
(2344, 2, 22, 1, 1, 1, 1, '2026-03-19 10:40:43', '2026-03-19 10:40:43', 0),
(2345, 2, 23, 1, 1, 1, 1, '2026-03-19 10:40:44', '2026-03-19 10:40:44', 0),
(2346, 2, 25, 1, 1, 1, 1, '2026-03-19 10:40:44', '2026-03-19 10:40:44', 0),
(2347, 2, 26, 0, 0, 0, 0, '2026-03-19 10:40:44', '2026-03-19 10:40:44', 0),
(2348, 2, 58, 0, 0, 0, 0, '2026-03-19 10:40:45', '2026-03-19 10:40:45', 0),
(2349, 2, 27, 0, 0, 0, 0, '2026-03-19 10:40:45', '2026-03-19 10:40:45', 0),
(2350, 2, 63, 0, 0, 0, 0, '2026-03-19 10:40:45', '2026-03-19 10:40:45', 0),
(2351, 2, 65, 0, 0, 0, 0, '2026-03-19 10:40:46', '2026-03-19 10:40:46', 0),
(2352, 2, 59, 1, 1, 1, 1, '2026-03-19 10:40:46', '2026-03-19 10:40:46', 0),
(2353, 2, 60, 1, 1, 1, 1, '2026-03-19 10:40:46', '2026-03-19 10:40:46', 0),
(2354, 2, 66, 1, 1, 1, 1, '2026-03-19 10:40:46', '2026-03-19 10:40:46', 0),
(2355, 2, 67, 1, 1, 1, 1, '2026-03-19 10:40:47', '2026-03-19 10:40:47', 0),
(2356, 2, 68, 1, 1, 1, 1, '2026-03-19 10:40:47', '2026-03-19 10:40:47', 0),
(2357, 2, 36, 0, 0, 0, 0, '2026-03-19 10:40:47', '2026-03-19 10:40:47', 0),
(2358, 2, 49, 0, 0, 0, 0, '2026-03-19 10:40:48', '2026-03-19 10:40:48', 0),
(2359, 2, 50, 0, 0, 0, 0, '2026-03-19 10:40:48', '2026-03-19 10:40:48', 0),
(2360, 2, 51, 1, 1, 1, 1, '2026-03-19 10:40:48', '2026-03-19 10:40:48', 0),
(2361, 2, 52, 0, 0, 0, 0, '2026-03-19 10:40:49', '2026-03-19 10:40:49', 0),
(2364, 3, 10, 0, 0, 0, 0, '2026-03-19 11:13:38', '2026-03-19 11:13:38', 0),
(2365, 3, 32, 0, 0, 0, 0, '2026-03-19 11:13:39', '2026-03-19 11:13:39', 0),
(2366, 3, 33, 1, 1, 1, 1, '2026-03-19 11:13:39', '2026-03-19 11:13:39', 0),
(2367, 3, 34, 0, 0, 0, 0, '2026-03-19 11:13:39', '2026-03-19 11:13:39', 0),
(2368, 3, 38, 0, 0, 0, 0, '2026-03-19 11:13:40', '2026-03-19 11:13:40', 0),
(2369, 3, 7, 0, 0, 0, 0, '2026-03-19 11:13:40', '2026-03-19 11:13:40', 0),
(2370, 3, 1, 0, 0, 0, 0, '2026-03-19 11:13:40', '2026-03-19 11:13:40', 0),
(2371, 3, 11, 0, 0, 0, 0, '2026-03-19 11:13:41', '2026-03-19 11:13:41', 0),
(2372, 3, 5, 0, 0, 0, 0, '2026-03-19 11:13:41', '2026-03-19 11:13:41', 0),
(2373, 3, 12, 1, 1, 1, 1, '2026-03-19 11:13:41', '2026-03-19 11:13:41', 0),
(2374, 3, 13, 1, 0, 0, 0, '2026-03-19 11:13:42', '2026-03-19 11:13:42', 0),
(2375, 3, 14, 0, 0, 0, 0, '2026-03-19 11:13:42', '2026-03-19 11:13:42', 0),
(2376, 3, 16, 0, 0, 0, 0, '2026-03-19 11:13:42', '2026-03-19 11:13:42', 0),
(2377, 3, 17, 1, 1, 1, 1, '2026-03-19 11:13:43', '2026-03-19 11:13:43', 0),
(2378, 3, 18, 1, 1, 1, 1, '2026-03-19 11:13:43', '2026-03-19 11:13:43', 0),
(2379, 3, 19, 1, 1, 1, 1, '2026-03-19 11:13:43', '2026-03-19 11:13:43', 0),
(2380, 3, 20, 1, 1, 1, 1, '2026-03-19 11:13:44', '2026-03-19 11:13:44', 0),
(2381, 3, 48, 1, 1, 1, 1, '2026-03-19 11:13:44', '2026-03-19 11:13:44', 0),
(2382, 3, 6, 0, 0, 0, 0, '2026-03-19 11:13:44', '2026-03-19 11:13:44', 0),
(2383, 3, 46, 1, 1, 1, 1, '2026-03-19 11:13:45', '2026-03-19 11:13:45', 0),
(2384, 3, 47, 1, 1, 1, 1, '2026-03-19 11:13:45', '2026-03-19 11:13:45', 0),
(2385, 3, 55, 1, 1, 1, 1, '2026-03-19 11:13:45', '2026-03-19 11:13:45', 0),
(2386, 3, 39, 0, 0, 0, 0, '2026-03-19 11:13:46', '2026-03-19 11:13:46', 0),
(2387, 3, 40, 1, 0, 0, 0, '2026-03-19 11:13:46', '2026-03-19 11:13:46', 0),
(2388, 3, 41, 1, 1, 1, 1, '2026-03-19 11:13:46', '2026-03-19 11:13:46', 0),
(2389, 3, 42, 0, 0, 0, 0, '2026-03-19 11:13:46', '2026-03-19 11:13:46', 0),
(2390, 3, 43, 0, 0, 0, 0, '2026-03-19 11:13:47', '2026-03-19 11:13:47', 0),
(2391, 3, 44, 0, 0, 0, 0, '2026-03-19 11:13:47', '2026-03-19 11:13:47', 0),
(2392, 3, 45, 0, 0, 0, 0, '2026-03-19 11:13:47', '2026-03-19 11:13:47', 0),
(2393, 3, 56, 0, 0, 0, 0, '2026-03-19 11:13:48', '2026-03-19 11:13:48', 0),
(2394, 3, 57, 0, 0, 0, 0, '2026-03-19 11:13:48', '2026-03-19 11:13:48', 0),
(2395, 3, 22, 0, 0, 0, 0, '2026-03-19 11:13:48', '2026-03-19 11:13:48', 0),
(2396, 3, 23, 1, 1, 1, 1, '2026-03-19 11:13:49', '2026-03-19 11:13:49', 0),
(2397, 3, 25, 0, 0, 0, 0, '2026-03-19 11:13:49', '2026-03-19 11:13:49', 0),
(2398, 3, 26, 0, 0, 0, 0, '2026-03-19 11:13:49', '2026-03-19 11:13:49', 0),
(2399, 3, 58, 0, 0, 0, 0, '2026-03-19 11:13:50', '2026-03-19 11:13:50', 0),
(2400, 3, 27, 0, 0, 0, 0, '2026-03-19 11:13:50', '2026-03-19 11:13:50', 0),
(2401, 3, 63, 1, 1, 1, 1, '2026-03-19 11:13:50', '2026-03-19 11:13:50', 0),
(2402, 3, 65, 1, 1, 1, 1, '2026-03-19 11:13:50', '2026-03-19 11:13:50', 0),
(2403, 3, 59, 1, 1, 1, 1, '2026-03-19 11:13:51', '2026-03-19 11:13:51', 0),
(2404, 3, 69, 0, 0, 0, 0, '2026-03-19 11:13:51', '2026-03-19 11:13:51', 0),
(2405, 3, 70, 1, 1, 1, 1, '2026-03-19 11:13:51', '2026-03-19 11:13:51', 0),
(2406, 3, 60, 1, 1, 1, 1, '2026-03-19 11:13:52', '2026-03-19 11:13:52', 0),
(2407, 3, 66, 1, 1, 1, 1, '2026-03-19 11:13:52', '2026-03-19 11:13:52', 0),
(2408, 3, 67, 1, 1, 1, 1, '2026-03-19 11:13:52', '2026-03-19 11:13:52', 0),
(2409, 3, 68, 1, 1, 1, 1, '2026-03-19 11:13:53', '2026-03-19 11:13:53', 0),
(2410, 3, 36, 0, 0, 0, 0, '2026-03-19 11:13:53', '2026-03-19 11:13:53', 0),
(2411, 3, 49, 1, 1, 1, 1, '2026-03-19 11:13:53', '2026-03-19 11:13:53', 0),
(2412, 3, 50, 1, 1, 1, 1, '2026-03-19 11:13:54', '2026-03-19 11:13:54', 0),
(2413, 3, 51, 1, 1, 1, 1, '2026-03-19 11:13:54', '2026-03-19 11:13:54', 0),
(2414, 3, 52, 1, 1, 1, 1, '2026-03-19 11:13:54', '2026-03-19 11:13:54', 0),
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
(2458, 4, 66, 0, 0, 0, 0, '2026-03-19 11:14:39', '2026-03-19 11:14:39', 0),
(2459, 4, 67, 0, 0, 0, 0, '2026-03-19 11:14:40', '2026-03-19 11:14:40', 0),
(2460, 4, 68, 0, 0, 0, 0, '2026-03-19 11:14:40', '2026-03-19 11:14:40', 0),
(2461, 4, 36, 0, 0, 0, 0, '2026-03-19 11:14:40', '2026-03-19 11:14:40', 0),
(2462, 4, 49, 0, 0, 0, 0, '2026-03-19 11:14:41', '2026-03-19 11:14:41', 0),
(2463, 4, 50, 0, 0, 0, 0, '2026-03-19 11:14:41', '2026-03-19 11:14:41', 0),
(2464, 4, 51, 0, 0, 0, 0, '2026-03-19 11:14:41', '2026-03-19 11:14:41', 0),
(2465, 4, 52, 0, 0, 0, 0, '2026-03-19 11:14:42', '2026-03-19 11:14:42', 0),
(2568, 1, 10, 1, 1, 1, 1, '2026-03-23 12:28:01', '2026-03-23 12:28:01', 0),
(2569, 1, 32, 1, 1, 1, 1, '2026-03-23 12:28:01', '2026-03-23 12:28:01', 0),
(2570, 1, 33, 1, 1, 1, 1, '2026-03-23 12:28:01', '2026-03-23 12:28:01', 0),
(2571, 1, 34, 1, 1, 1, 1, '2026-03-23 12:28:02', '2026-03-23 12:28:02', 0),
(2572, 1, 38, 1, 1, 1, 1, '2026-03-23 12:28:02', '2026-03-23 12:28:02', 0),
(2573, 1, 7, 1, 1, 1, 1, '2026-03-23 12:28:02', '2026-03-23 12:28:02', 0),
(2574, 1, 1, 1, 0, 0, 0, '2026-03-23 12:28:03', '2026-03-23 12:28:03', 0),
(2575, 1, 11, 1, 1, 1, 1, '2026-03-23 12:28:03', '2026-03-23 12:28:03', 0),
(2576, 1, 5, 1, 1, 1, 1, '2026-03-23 12:28:03', '2026-03-23 12:28:03', 0),
(2577, 1, 12, 1, 1, 1, 1, '2026-03-23 12:28:04', '2026-03-23 12:28:04', 0),
(2578, 1, 13, 1, 1, 1, 1, '2026-03-23 12:28:04', '2026-03-23 12:28:04', 0),
(2579, 1, 14, 1, 1, 1, 1, '2026-03-23 12:28:04', '2026-03-23 12:28:04', 0),
(2580, 1, 16, 1, 1, 1, 1, '2026-03-23 12:28:05', '2026-03-23 12:28:05', 0),
(2581, 1, 17, 1, 1, 1, 1, '2026-03-23 12:28:05', '2026-03-23 12:28:05', 0),
(2582, 1, 18, 1, 1, 1, 1, '2026-03-23 12:28:05', '2026-03-23 12:28:05', 0),
(2583, 1, 19, 1, 1, 1, 1, '2026-03-23 12:28:05', '2026-03-23 12:28:05', 0),
(2584, 1, 20, 1, 1, 1, 1, '2026-03-23 12:28:06', '2026-03-23 12:28:06', 0),
(2585, 1, 48, 1, 1, 1, 1, '2026-03-23 12:28:06', '2026-03-23 12:28:06', 0),
(2586, 1, 6, 1, 1, 1, 1, '2026-03-23 12:28:06', '2026-03-23 12:28:06', 0),
(2587, 1, 46, 1, 1, 1, 1, '2026-03-23 12:28:07', '2026-03-23 12:28:07', 0),
(2588, 1, 47, 1, 1, 1, 1, '2026-03-23 12:28:07', '2026-03-23 12:28:07', 0),
(2589, 1, 55, 1, 1, 1, 1, '2026-03-23 12:28:07', '2026-03-23 12:28:07', 0),
(2590, 1, 39, 1, 1, 1, 1, '2026-03-23 12:28:07', '2026-03-23 12:28:07', 0),
(2591, 1, 40, 1, 1, 1, 1, '2026-03-23 12:28:08', '2026-03-23 12:28:08', 0),
(2592, 1, 41, 1, 1, 1, 1, '2026-03-23 12:28:08', '2026-03-23 12:28:08', 0),
(2593, 1, 42, 1, 1, 1, 1, '2026-03-23 12:28:08', '2026-03-23 12:28:08', 0),
(2594, 1, 43, 1, 1, 1, 1, '2026-03-23 12:28:09', '2026-03-23 12:28:09', 0),
(2595, 1, 44, 1, 1, 1, 1, '2026-03-23 12:28:09', '2026-03-23 12:28:09', 0),
(2596, 1, 45, 0, 0, 0, 0, '2026-03-23 12:28:09', '2026-03-23 12:28:09', 0),
(2597, 1, 56, 1, 1, 1, 1, '2026-03-23 12:28:09', '2026-03-23 12:28:09', 0),
(2598, 1, 57, 1, 1, 1, 1, '2026-03-23 12:28:10', '2026-03-23 12:28:10', 0),
(2599, 1, 22, 1, 1, 1, 1, '2026-03-23 12:28:10', '2026-03-23 12:28:10', 0),
(2600, 1, 23, 1, 1, 1, 1, '2026-03-23 12:28:10', '2026-03-23 12:28:10', 0),
(2601, 1, 25, 1, 1, 1, 1, '2026-03-23 12:28:11', '2026-03-23 12:28:11', 0),
(2602, 1, 26, 1, 1, 1, 1, '2026-03-23 12:28:11', '2026-03-23 12:28:11', 0),
(2603, 1, 58, 1, 1, 1, 1, '2026-03-23 12:28:11', '2026-03-23 12:28:11', 0),
(2604, 1, 27, 1, 1, 1, 1, '2026-03-23 12:28:11', '2026-03-23 12:28:11', 0),
(2605, 1, 63, 1, 1, 1, 1, '2026-03-23 12:28:12', '2026-03-23 12:28:12', 0),
(2606, 1, 65, 1, 1, 1, 1, '2026-03-23 12:28:12', '2026-03-23 12:28:12', 0),
(2607, 1, 59, 1, 1, 1, 1, '2026-03-23 12:28:12', '2026-03-23 12:28:12', 0),
(2608, 1, 69, 0, 0, 0, 0, '2026-03-23 12:28:13', '2026-03-23 12:28:13', 0),
(2609, 1, 70, 0, 0, 0, 0, '2026-03-23 12:28:13', '2026-03-23 12:28:13', 0),
(2610, 1, 60, 1, 1, 1, 1, '2026-03-23 12:28:13', '2026-03-23 12:28:13', 0),
(2611, 1, 66, 1, 1, 1, 1, '2026-03-23 12:28:13', '2026-03-23 12:28:13', 0),
(2612, 1, 67, 1, 1, 1, 1, '2026-03-23 12:28:14', '2026-03-23 12:28:14', 0),
(2613, 1, 68, 1, 1, 1, 1, '2026-03-23 12:28:14', '2026-03-23 12:28:14', 0),
(2614, 1, 36, 0, 0, 0, 0, '2026-03-23 12:28:14', '2026-03-23 12:28:14', 0),
(2615, 1, 49, 1, 1, 1, 1, '2026-03-23 12:28:15', '2026-03-23 12:28:15', 0),
(2616, 1, 50, 1, 1, 1, 1, '2026-03-23 12:28:15', '2026-03-23 12:28:15', 0),
(2617, 1, 51, 0, 0, 0, 0, '2026-03-23 12:28:15', '2026-03-23 12:28:15', 0),
(2618, 1, 52, 1, 1, 1, 1, '2026-03-23 12:28:15', '2026-03-23 12:28:15', 0),
(2619, 1, 71, 1, 1, 1, 1, '2026-03-24 06:47:33', '2026-03-24 06:47:33', 0),
(2621, 1, 73, 1, 1, 1, 1, '2026-03-24 06:58:34', '2026-03-24 06:58:34', 0),
(2622, 1, 74, 1, 1, 1, 1, '2026-03-24 06:59:08', '2026-03-24 06:59:08', 0);

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
(1, 15, 1, 4, 4, '2026-03-19 10:28:06', 'scoto', '2026-03-25', 'scheduled', NULL, 3, '2026-03-17 06:43:07', '2026-03-24 09:48:11'),
(2, 12, 1, 8, 8, '2026-03-18 11:08:34', 'IQ general', '2026-03-29', 'scheduled', NULL, 3, '2026-03-18 11:08:34', '2026-03-25 07:28:51');

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
(1, 1, 1, 'admin', 'admin@gmail.com', NULL, '$2y$10$XSJvOcrYzm1Si2hMt4XBAO8woxn0B2kN1/7sQAFN5GXTqgWZJx6r6', 0, '2026-03-28 04:32:06', '2401:4900:8825:644e:7979:edb5:aef3:2ba', 1, '2026-02-24 14:43:56', '2026-03-28 04:32:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 1, 2, 'John', 'webdeveloper05.ats@gmail.com', '9942133944', '$2y$10$RndVVNRzU5.otdBKdQ0GrucyKFszBSxuf1eBKo2IdNi7mKpDckkM.', 0, '2026-03-27 07:23:23', '::1', 1, '2026-02-27 09:40:22', '2026-03-27 07:23:23', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(3, 1, 3, 'Michael', 'michael@gmail.com', '9942133944', '$2y$10$1v.h78Rz.VEfLfzF4mzUIOfYt/ucjkL0qpq4i.57N7AkUHuWyBKF.', 0, '2026-03-28 11:53:57', '::1', 1, '2026-02-27 09:55:00', '2026-03-28 11:53:57', 1, 1, '2401:4900:8825:644e:4140:caba:bff:f0c0', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(4, 1, 4, 'Fredrick', 'fredrick@gmail.com', '7896541236', '$2y$10$NyGE/HNP0cteOIu13cNBrulKKUxPjbgJ1igsjcY8nSiuEuJlL/EiS', 0, '2026-03-26 09:42:53', '::1', 1, '2026-02-27 09:55:31', '2026-03-26 09:42:53', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(5, 1, 6, 'Fredrick John', 'fredrickjohn@gmail.com', '9942133944', '$2y$10$8xOZr5zGQCXQbah699/iv.scVJCDTAFOBaYSU3rT8TlfjvF2f1BSy', 0, '2026-03-27 12:22:06', '::1', 1, '2026-02-27 11:48:43', '2026-03-27 12:22:06', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(6, 1, 7, 'Test', 'test@gmail.com', '9874563210', '$2y$10$CYWliVwyZj8aAXljmNjrke8QM/jhkktGlOw46obUIp1N8buTUb4/q', 0, '2026-02-27 13:30:01', '::1', 1, '2026-02-27 13:12:51', '2026-02-27 13:30:01', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(7, 1, 2, 'Suresh', 'suresh@gmail.com', '9874563214', '$2y$10$.Chm89m3LfxsgYaKMzd47OCMBAp5nVfXpwHFWK7R7DrLKcev3jIm6', 0, '2026-03-27 12:34:12', '::1', 1, '2026-03-09 14:38:38', '2026-03-27 12:34:12', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(8, 1, 4, 'Sridharshan', 'webdeveloper005.ats@gmail.com', '9876543210', '$2y$10$zpUgOvsO3vdhnKJXvQX3G.dFGFTbBGINlk6bL0p2UcQSilmJP92Y.', 0, '2026-03-27 12:29:14', '::1', 1, '2026-03-11 06:51:11', '2026-03-27 12:29:14', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(9, 1, 8, 'Sample', 'sample@gmail.com', '234567890', '$2y$10$St4Untsr9wTc1f7FRT.8aeFB1cwGHn2KDVeXGuh0acJBmsCqK2iX.', 0, '2026-03-11 13:04:41', '2401:4900:8825:644e:c116:fd72:e0c1:8291', 1, '2026-03-11 12:19:43', '2026-03-11 13:04:41', 1, 1, '2401:4900:8825:644e:44a5:d376:2e7f:954b', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(10, 1, 2, 'pramo', 'pramo@gmail.com', '7896541236', '$2y$10$JPP0QaZrwfnWsoMDW.9YxuPnl8Ul1/Co96o0DIFhelHMJY9VZClUm', 0, '2026-03-27 07:06:17', '2401:4900:8825:644e:2835:4470:a4cc:1971', 1, '2026-03-24 06:08:43', '2026-03-27 07:06:17', 1, 1, '2401:4900:8825:644e:85f7:385b:a1f4:35a1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(11, 1, 2, 'Theju', 'theju@gmail.com', '874596521', '$2y$10$5oyokvxQZ5W.mBIGlF7Huetm0O8bO7suhyUG0FGlpFi13S.Yay.l.', 0, '2026-03-25 09:15:15', '2401:4900:8825:644e:7885:f029:1fb4:b27d', 1, '2026-03-24 06:09:24', '2026-03-25 09:15:15', 1, 1, '2401:4900:8825:644e:85f7:385b:a1f4:35a1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(12, 1, 4, 'Raghul', 'raghul@gmail.com', '789654136', '$2y$10$9qKZb8lz8OHNcU2bWNN.Nueo/fVfMS3Nnw20dA6JE.U4OLF2N/gJm', 0, '2026-03-24 10:06:24', '2405:201:e015:50bd:8df4:ad31:f677:3c13', 1, '2026-03-24 06:10:59', '2026-03-24 10:06:24', 1, 1, '2405:201:e015:50bd:8df4:ad31:f677:3c13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', NULL, NULL, NULL, NULL, NULL),
(13, 1, 1, 'Admin User Test1', 'admin@atscrm.com', '9000000001', '$2y$10$dneLsIcGaIjJQsGXakz.N.jkDj/y8tujl1D8Rs6ir90KJlQraxuTW', 0, NULL, NULL, 1, '2026-03-24 07:01:47', '2026-03-24 07:59:10', 1, 1, '2405:201:e015:50bd:f51b:db:6227:915c', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(14, 1, 3, 'Krishnan', 'krishnan.hr@gmail.com', '9000000002', '$2y$10$Gf9RVnpdYshfeEj0cyzIgOKqqcMLNJtfZvL0IiUabsC.lm7fhmboC', 0, NULL, NULL, 1, '2026-03-24 07:02:57', '2026-03-24 07:59:14', 1, 1, '2405:201:e015:50bd:f51b:db:6227:915c', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(15, 1, 4, 'Arunkumar', 'arun@gmail.com', '8148903261', '$2y$10$2NcAq6j3kSS/csXBji0d0uIts2Vb.QaN6cTa1D9PeBe3vM2KI5S3W', 0, '2026-03-28 04:32:56', '2401:4900:8825:644e:7979:edb5:aef3:2ba', 1, '2026-03-24 07:19:24', '2026-03-28 04:32:56', 1, 1, '2401:4900:8825:644e:21e1:1230:a94a:f720', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL),
(16, 1, 2, 'Testuser', 'testuser@gmail.com', '7896541236', '$2y$10$4.EDVgwBicCbIWECnGcElenLTgmqqi56JnCYRfngKvFmB1Z050hmS', 0, NULL, NULL, 1, '2026-03-25 04:11:25', '2026-03-25 04:11:25', 1, 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', NULL, NULL, NULL, NULL, NULL);

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
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_date` (`user_id`,`report_date`),
  ADD UNIQUE KEY `unique_report` (`user_id`,`report_date`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `report_date` (`report_date`),
  ADD KEY `department` (`department`);

--
-- Indexes for table `report_activity`
--
ALTER TABLE `report_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `report_followups`
--
ALTER TABLE `report_followups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `report_hourly`
--
ALTER TABLE `report_hourly`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `report_interviews`
--
ALTER TABLE `report_interviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`);

--
-- Indexes for table `report_registrations`
--
ALTER TABLE `report_registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`),
  ADD KEY `idx_report_id` (`report_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

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
-- AUTO_INCREMENT for table `enquiries`
--
ALTER TABLE `enquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `enquiry_followups`
--
ALTER TABLE `enquiry_followups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `enquiry_followup_files`
--
ALTER TABLE `enquiry_followup_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enquiry_sequence`
--
ALTER TABLE `enquiry_sequence`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=178;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `lead_import_batches`
--
ALTER TABLE `lead_import_batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `marketing_amount`
--
ALTER TABLE `marketing_amount`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menus`
--
ALTER TABLE `menus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `mock_interviews`
--
ALTER TABLE `mock_interviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `monthly_targets`
--
ALTER TABLE `monthly_targets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `registration_courses`
--
ALTER TABLE `registration_courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `registration_internships`
--
ALTER TABLE `registration_internships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `registration_payments`
--
ALTER TABLE `registration_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `registration_profiles`
--
ALTER TABLE `registration_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `report_activity`
--
ALTER TABLE `report_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `report_followups`
--
ALTER TABLE `report_followups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_hourly`
--
ALTER TABLE `report_hourly`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `report_interviews`
--
ALTER TABLE `report_interviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_registrations`
--
ALTER TABLE `report_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2625;

--
-- AUTO_INCREMENT for table `student_hr_interviews`
--
ALTER TABLE `student_hr_interviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

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
