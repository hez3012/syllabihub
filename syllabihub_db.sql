-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 15, 2026 at 05:40 AM
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
-- Database: `syllabihub_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('laravel-cache-bfc6e5952642c47003ff07af11aef691', 'i:3;', 1786896335),
('laravel-cache-bfc6e5952642c47003ff07af11aef691:timer', 'i:1786896335;', 1786896335),
('laravel-cache-e5658125770099e562e4be808417cf14', 'i:1;', 1786871126),
('laravel-cache-e5658125770099e562e4be808417cf14:timer', 'i:1786871126;', 1786871126);

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `program_id` bigint(20) UNSIGNED NOT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `course_code` varchar(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `title_active` varchar(255) GENERATED ALWAYS AS (case when `deleted_at` is null then `title` end) VIRTUAL,
  `year_level` tinyint(3) UNSIGNED DEFAULT NULL,
  `semester` enum('1st','2nd','summer') DEFAULT NULL,
  `prerequisite` varchar(255) DEFAULT NULL,
  `corequisite` varchar(255) DEFAULT NULL,
  `lecture_hours` decimal(4,1) DEFAULT NULL,
  `lab_hours` decimal(4,1) DEFAULT NULL,
  `credited_units` decimal(3,1) DEFAULT NULL,
  `tuition_hours` decimal(4,1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `course_code_active` varchar(20) GENERATED ALWAYS AS (case when `deleted_at` is null then `course_code` end) VIRTUAL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `program_id`, `created_by`, `course_code`, `title`, `year_level`, `semester`, `prerequisite`, `corequisite`, `lecture_hours`, `lab_hours`, `credited_units`, `tuition_hours`, `created_at`, `updated_at`, `deleted_at`) VALUES
(3239, 3244, NULL, 'COMP 001', 'Introduction to Computing', 1, '1st', NULL, NULL, 2.0, 3.0, 3.0, NULL, '2026-08-14 08:04:57', '2026-08-14 08:04:57', NULL),
(3240, 3244, NULL, 'COMP 002', 'Fundamentals of Programming', 1, '1st', NULL, NULL, 2.0, 3.0, 3.0, NULL, '2026-08-14 08:04:57', '2026-08-14 08:04:57', NULL),
(3241, 3244, NULL, 'COMP 003', 'Intermediate Programming', 1, '2nd', NULL, NULL, 2.0, 3.0, 3.0, NULL, '2026-08-14 08:04:57', '2026-08-14 08:04:57', NULL),
(3242, 3244, NULL, 'COMP 008', 'Data Structures and Algorithms', 2, '1st', NULL, NULL, 2.0, 3.0, 3.0, NULL, '2026-08-14 08:04:57', '2026-08-14 08:04:57', NULL),
(3243, 3244, NULL, 'COMP 011', 'Object Oriented Programming', 2, '1st', NULL, NULL, 2.0, 3.0, 3.0, NULL, '2026-08-14 08:04:57', '2026-08-14 08:04:57', NULL),
(3244, 3244, NULL, 'COMP 016', 'Web Development', 2, '2nd', NULL, NULL, 2.0, 3.0, 3.0, NULL, '2026-08-14 08:04:57', '2026-08-14 08:04:57', NULL),
(3245, 3244, NULL, 'COMP 020', 'Database Management Systems', 2, '2nd', NULL, NULL, 2.0, 3.0, 3.0, NULL, '2026-08-14 08:04:57', '2026-08-14 08:04:57', NULL),
(3246, 3244, NULL, 'COMP 025', 'Information Management', 3, '1st', NULL, NULL, 3.0, 0.0, 3.0, NULL, '2026-08-14 08:04:57', '2026-08-14 08:04:57', NULL),
(3247, 3244, NULL, 'COMP 030', 'Systems Integration and Architecture', 3, '1st', NULL, NULL, 2.0, 3.0, 3.0, NULL, '2026-08-14 08:04:57', '2026-08-14 08:04:57', NULL),
(3248, 3244, NULL, 'COMP 035', 'Networking 1', 3, '2nd', NULL, NULL, 2.0, 3.0, 3.0, NULL, '2026-08-14 08:04:57', '2026-08-14 08:04:57', NULL),
(3249, 3244, NULL, 'COMP 040', 'Software Engineering', 3, '2nd', NULL, NULL, 3.0, 0.0, 3.0, NULL, '2026-08-14 08:04:57', '2026-08-14 08:04:57', NULL),
(3250, 3244, NULL, 'COMP 045', 'Capstone Project 1', 4, '1st', NULL, NULL, 0.0, 3.0, 3.0, NULL, '2026-08-14 08:04:57', '2026-08-14 08:04:57', NULL),
(3251, 3244, NULL, 'COMP 050', 'Capstone Project 2', 4, '2nd', NULL, NULL, 0.0, 3.0, 3.0, NULL, '2026-08-14 08:04:57', '2026-08-14 08:04:57', NULL),
(3252, 3245, NULL, 'DIT 101', 'Advanced Database Systems', 1, '1st', NULL, NULL, 3.0, 0.0, 3.0, NULL, '2026-08-14 08:04:57', '2026-08-14 08:04:57', NULL),
(3253, 3245, NULL, 'DIT 102', 'Data Mining and Analytics', 1, '2nd', NULL, NULL, 3.0, 0.0, 3.0, NULL, '2026-08-14 08:04:57', '2026-08-14 08:04:57', NULL),
(3254, 3245, NULL, 'DIT 103', 'Advanced Web and Mobile Development', 2, '1st', NULL, NULL, 2.0, 3.0, 3.0, NULL, '2026-08-14 08:04:57', '2026-08-14 08:04:57', NULL),
(3255, 3244, 6778, 'COMP 067', 'Advanced System Architecture', 2, '1st', NULL, NULL, 4.0, 3.0, 2.0, 7.0, '2026-08-15 21:14:10', '2026-08-15 21:14:10', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `course_change_requests`
--

CREATE TABLE `course_change_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `requested_by` bigint(20) UNSIGNED NOT NULL,
  `action` enum('update','delete') NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Proposed field changes for update actions; null for delete' CHECK (json_valid(`payload`)),
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(150) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`id`, `code`, `name`, `created_at`, `updated_at`) VALUES
(3244, 'BSIT', 'Bachelor of Science in Information Technology', '2026-08-14 07:50:48', '2026-08-14 07:50:48'),
(3245, 'DIT', 'Diploma in Information Technology', '2026-08-14 07:50:48', '2026-08-14 07:50:48');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `syllabi`
--

CREATE TABLE `syllabi` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `course_id` bigint(20) UNSIGNED NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` enum('pdf','docx') NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `raw_text` longtext DEFAULT NULL,
  `curriculum_year` varchar(20) DEFAULT NULL,
  `status` enum('pending','processed','failed') DEFAULT 'pending',
  `uploaded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','faculty','intern') DEFAULT 'intern',
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `role`, `remember_token`, `created_at`, `updated_at`) VALUES
(6778, 'Test Admin', 'admin@test.syllabihub', NULL, '$2y$12$rhgBXsvwTvEwP6UGV/PZmulPOfzZOg/yebRZG/Mx.dGvUOs339NNi', 'admin', 'RfkYxUDg4Ch8He1XnnYIRBTR3m8hol4CFCztyH6Gyn1Uv3f9eD3FRuq9CaAF', '2026-08-14 08:04:30', '2026-08-14 08:04:30'),
(6779, 'Test Faculty', 'faculty@test.syllabihub', NULL, '$2y$12$aQWUKJch5XxSFu/LaRYsPulULTGnYpm7hfLIdup2uJRc.Ax07mJH2', 'faculty', NULL, '2026-08-14 08:04:31', '2026-08-14 08:04:31'),
(6780, 'Test Intern', 'intern@test.syllabihub', NULL, '$2y$12$/t.Vl625UoLlzGt6XosPT.HxQLtru8BRULn9fuzBmJNeAJdhwsG5C', 'intern', NULL, '2026-08-14 08:04:31', '2026-08-14 08:04:31');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_title_active` (`title_active`),
  ADD UNIQUE KEY `uq_course_code_active` (`course_code_active`),
  ADD KEY `fk_courses_created_by` (`created_by`),
  ADD KEY `idx_courses_program_id` (`program_id`);
ALTER TABLE `courses` ADD FULLTEXT KEY `ft_course_search` (`course_code`,`title`);

--
-- Indexes for table `course_change_requests`
--
ALTER TABLE `course_change_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `syllabi`
--
ALTER TABLE `syllabi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);
ALTER TABLE `syllabi` ADD FULLTEXT KEY `ft_syllabus_search` (`raw_text`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3256;

--
-- AUTO_INCREMENT for table `course_change_requests`
--
ALTER TABLE `course_change_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=651;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3246;

--
-- AUTO_INCREMENT for table `syllabi`
--
ALTER TABLE `syllabi`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1792;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6781;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`),
  ADD CONSTRAINT `fk_courses_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `course_change_requests`
--
ALTER TABLE `course_change_requests`
  ADD CONSTRAINT `course_change_requests_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  ADD CONSTRAINT `course_change_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `course_change_requests_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `syllabi`
--
ALTER TABLE `syllabi`
  ADD CONSTRAINT `syllabi_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  ADD CONSTRAINT `syllabi_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
