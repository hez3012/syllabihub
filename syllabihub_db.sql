-- MySQL dump 10.13  Distrib 8.0.46, for Win64 (x86_64)
--
-- Host: localhost    Database: syllabihub_db
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,'Test Admin','admin@test.syllabihub','logout','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-21 11:34:02'),(2,'Test Admin','admin@test.syllabihub','login','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-21 11:39:47'),(3,'Test Admin','admin@test.syllabihub','logout','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-21 11:40:32'),(4,'Test Admin','admin@test.syllabihub','login','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-21 11:40:57'),(5,'Test Admin','admin@test.syllabihub','logout','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-21 11:41:01'),(6,'Test Admin','admin@test.syllabihub','login','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-21 11:58:39'),(7,'Test Admin','admin@test.syllabihub','logout','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-21 11:58:45'),(8,'Test Admin','admin@test.syllabihub','login','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-21 12:32:54'),(9,'Test Admin','admin@test.syllabihub','logout','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-21 12:33:02'),(10,'Test Admin','admin@test.syllabihub','login','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-21 12:46:54'),(11,'Test Admin','admin@test.syllabihub','logout','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-21 12:47:18'),(12,'Test Admin','admin@test.syllabihub','login','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-21 12:50:50'),(13,'Test Admin','admin@test.syllabihub','logout','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-21 13:19:43'),(14,'Test Admin','admin@test.syllabihub','login','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-21 13:20:15'),(15,'Test Admin','admin@test.syllabihub','login','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-24 02:03:59'),(16,'Test Admin','admin@test.syllabihub','logout','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-24 02:04:14'),(17,'Test Admin','admin@test.syllabihub','login','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-24 02:08:50'),(18,'Test Admin','admin@test.syllabihub','logout','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-24 02:08:55'),(19,'Test Admin','admin@test.syllabihub','login','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36','2026-09-25 08:59:33'),(20,'Test Admin','admin@test.syllabihub','login','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-09-28 03:17:34'),(21,'Test Admin','admin@test.syllabihub','logout','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-09-28 03:21:50'),(22,'Test Admin','admin@test.syllabihub','login','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-09-28 03:22:11'),(23,'Test Admin','admin@test.syllabihub','logout','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-09-28 03:22:59'),(24,'Test Admin','admin@test.syllabihub','login','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-09-28 03:23:15'),(25,'Test Admin','admin@test.syllabihub','logout','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-09-28 03:32:52'),(26,'Test Admin','admin@test.syllabihub','login','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-09-28 03:33:16'),(27,'Test Admin','admin@test.syllabihub','logout','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-09-28 04:31:25'),(28,'Test Admin','admin@test.syllabihub','login','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-09-28 04:32:24'),(29,'Test Admin','admin@test.syllabihub','logout','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-09-28 05:21:14'),(30,'Test Admin','admin@test.syllabihub','login','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-09-28 05:21:35');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_trails`
--

DROP TABLE IF EXISTS `audit_trails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_trails` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` bigint unsigned NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_trails`
--

LOCK TABLES `audit_trails` WRITE;
/*!40000 ALTER TABLE `audit_trails` DISABLE KEYS */;
INSERT INTO `audit_trails` VALUES (1,'Test Admin','admin@test.syllabihub','updated','App\\Models\\Course',3239,'Uploaded syllabus for: COMP 001 — Introduction to Computing',NULL,'{\"syllabus_files\": [\"PDF #1793: Processed\", \"DOCX #1794: Processed\"]}','2026-09-21 13:02:56'),(2,'Test Admin','admin@test.syllabihub','created','App\\Models\\Course',3686,'Created course: COMP 101 — Olala',NULL,'{\"id\": 3686, \"title\": \"Olala\", \"programs\": {\"DIT\": {\"semester\": \"2nd\", \"year_level\": 2}, \"BSIT\": {\"semester\": \"2nd\", \"year_level\": 3}}, \"lab_hours\": \"3\", \"created_at\": \"2026-09-25T09:23:47.000000Z\", \"created_by\": 6778, \"updated_at\": \"2026-09-25T09:23:47.000000Z\", \"corequisite\": null, \"course_code\": \"COMP 101\", \"prerequisite\": null, \"lecture_hours\": \"1\", \"tuition_hours\": null, \"credited_units\": \"4\"}','2026-09-25 09:23:47'),(3,'Test Admin','admin@test.syllabihub','deleted','App\\Models\\Course',3686,'Deleted course: COMP 101 — Olala','{\"id\": 3686, \"title\": \"Olala\", \"lab_hours\": \"3.0\", \"created_at\": \"2026-09-25T09:23:47.000000Z\", \"created_by\": 6778, \"deleted_at\": null, \"updated_at\": \"2026-09-25T09:23:47.000000Z\", \"corequisite\": null, \"course_code\": \"COMP 101\", \"prerequisite\": null, \"title_active\": \"Olala\", \"lecture_hours\": \"1.0\", \"tuition_hours\": null, \"credited_units\": \"4.0\", \"course_code_active\": \"COMP 101\"}',NULL,'2026-09-25 09:24:17'),(4,'Test Admin','admin@test.syllabihub','updated','App\\Models\\Course',3239,'Updated course: COMP 001 — Introduction to Computing',NULL,NULL,'2026-09-25 09:24:27'),(5,'Test Admin','admin@test.syllabihub','created','App\\Models\\Course',3687,'Created course: COMP 101 — Olala',NULL,'{\"id\": 3687, \"title\": \"Olala\", \"programs\": {\"DIT\": {\"semester\": \"2nd\", \"year_level\": 1}, \"BSIT\": {\"semester\": \"1st\", \"year_level\": 1}}, \"lab_hours\": \"3\", \"created_at\": \"2026-09-25T09:25:23.000000Z\", \"created_by\": 6778, \"updated_at\": \"2026-09-25T09:25:23.000000Z\", \"corequisite\": null, \"course_code\": \"COMP 101\", \"prerequisite\": null, \"lecture_hours\": \"3\", \"tuition_hours\": null, \"credited_units\": \"3\"}','2026-09-25 09:25:23'),(6,'Test Admin','admin@test.syllabihub','deleted','App\\Models\\Course',3687,'Deleted course: COMP 101 — Olala','{\"id\": 3687, \"title\": \"Olala\", \"lab_hours\": \"3.0\", \"created_at\": \"2026-09-25T09:25:23.000000Z\", \"created_by\": 6778, \"deleted_at\": null, \"updated_at\": \"2026-09-25T09:25:23.000000Z\", \"corequisite\": null, \"course_code\": \"COMP 101\", \"prerequisite\": null, \"title_active\": \"Olala\", \"lecture_hours\": \"3.0\", \"tuition_hours\": null, \"credited_units\": \"3.0\", \"course_code_active\": \"COMP 101\"}',NULL,'2026-09-25 09:26:16');
/*!40000 ALTER TABLE `audit_trails` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_general_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('laravel-cache-bfc6e5952642c47003ff07af11aef691','i:1;',1790651661),('laravel-cache-bfc6e5952642c47003ff07af11aef691:timer','i:1790651661;',1790651661),('laravel-cache-e5658125770099e562e4be808417cf14','i:1;',1790565321),('laravel-cache-e5658125770099e562e4be808417cf14:timer','i:1790565321;',1790565321);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_change_requests`
--

DROP TABLE IF EXISTS `course_change_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_change_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint unsigned NOT NULL,
  `requested_by` bigint unsigned NOT NULL,
  `action` enum('update','delete') COLLATE utf8mb4_general_ci NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'Proposed field changes for update actions; null for delete',
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_note` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `requested_by` (`requested_by`),
  KEY `reviewed_by` (`reviewed_by`),
  CONSTRAINT `course_change_requests_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `course_change_requests_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `course_change_requests_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `course_change_requests_chk_1` CHECK (json_valid(`payload`))
) ENGINE=InnoDB AUTO_INCREMENT=717 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_change_requests`
--

LOCK TABLES `course_change_requests` WRITE;
/*!40000 ALTER TABLE `course_change_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `course_change_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `course_program`
--

DROP TABLE IF EXISTS `course_program`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `course_program` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint unsigned NOT NULL,
  `program_id` bigint unsigned NOT NULL,
  `year_level` tinyint unsigned DEFAULT NULL,
  `semester` enum('1st','2nd','summer') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_program_course_id_program_id_unique` (`course_id`,`program_id`),
  KEY `course_program_program_id_index` (`program_id`),
  CONSTRAINT `course_program_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_program_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=779 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `course_program`
--

LOCK TABLES `course_program` WRITE;
/*!40000 ALTER TABLE `course_program` DISABLE KEYS */;
INSERT INTO `course_program` VALUES (1,3239,3244,1,'1st','2026-09-25 08:52:43','2026-09-25 09:24:27'),(2,3240,3244,1,'1st','2026-09-25 08:52:43','2026-09-25 08:52:43'),(3,3241,3244,1,'2nd','2026-09-25 08:52:43','2026-09-25 08:52:43'),(4,3242,3244,2,'1st','2026-09-25 08:52:43','2026-09-25 08:52:43'),(5,3243,3244,2,'1st','2026-09-25 08:52:43','2026-09-25 08:52:43'),(6,3244,3244,2,'2nd','2026-09-25 08:52:43','2026-09-25 08:52:43'),(7,3245,3244,2,'2nd','2026-09-25 08:52:43','2026-09-25 08:52:43'),(8,3246,3244,3,'1st','2026-09-25 08:52:43','2026-09-25 08:52:43'),(9,3247,3244,3,'1st','2026-09-25 08:52:43','2026-09-25 08:52:43'),(10,3248,3244,3,'2nd','2026-09-25 08:52:43','2026-09-25 08:52:43'),(11,3249,3244,3,'2nd','2026-09-25 08:52:43','2026-09-25 08:52:43'),(12,3250,3244,4,'1st','2026-09-25 08:52:43','2026-09-25 08:52:43'),(13,3251,3244,4,'2nd','2026-09-25 08:52:43','2026-09-25 08:52:43'),(14,3252,3245,1,'1st','2026-09-25 08:52:43','2026-09-25 08:52:43'),(15,3253,3245,1,'2nd','2026-09-25 08:52:43','2026-09-25 08:52:43'),(16,3254,3245,2,'1st','2026-09-25 08:52:43','2026-09-25 08:52:43'),(17,3255,3244,2,'1st','2026-09-25 08:52:43','2026-09-25 08:52:43'),(164,3686,3244,3,'2nd','2026-09-25 09:23:47','2026-09-25 09:23:47'),(165,3686,3245,2,'2nd','2026-09-25 09:23:47','2026-09-25 09:23:47'),(166,3687,3244,1,'1st','2026-09-25 09:25:23','2026-09-25 09:25:23'),(167,3687,3245,1,'2nd','2026-09-25 09:25:23','2026-09-25 09:25:23');
/*!40000 ALTER TABLE `course_program` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `courses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_by` bigint unsigned DEFAULT NULL,
  `course_code` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `title_active` varchar(255) COLLATE utf8mb4_general_ci GENERATED ALWAYS AS ((case when (`deleted_at` is null) then `title` end)) VIRTUAL,
  `prerequisite` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `corequisite` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `lecture_hours` decimal(4,1) DEFAULT NULL,
  `lab_hours` decimal(4,1) DEFAULT NULL,
  `credited_units` decimal(3,1) DEFAULT NULL,
  `tuition_hours` decimal(4,1) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `course_code_active` varchar(20) COLLATE utf8mb4_general_ci GENERATED ALWAYS AS ((case when (`deleted_at` is null) then `course_code` end)) VIRTUAL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_title_active` (`title_active`),
  UNIQUE KEY `uq_course_code_active` (`course_code_active`),
  KEY `fk_courses_created_by` (`created_by`),
  FULLTEXT KEY `ft_course_search` (`course_code`,`title`),
  CONSTRAINT `fk_courses_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4299 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` (`id`, `created_by`, `course_code`, `title`, `prerequisite`, `corequisite`, `lecture_hours`, `lab_hours`, `credited_units`, `tuition_hours`, `created_at`, `updated_at`, `deleted_at`) VALUES (3239,NULL,'COMP 001','Introduction to Computing',NULL,NULL,2.0,3.0,3.0,NULL,'2026-08-14 08:04:57','2026-08-14 08:04:57',NULL),(3240,NULL,'COMP 002','Fundamentals of Programming',NULL,NULL,2.0,3.0,3.0,NULL,'2026-08-14 08:04:57','2026-08-14 08:04:57',NULL),(3241,NULL,'COMP 003','Intermediate Programming',NULL,NULL,2.0,3.0,3.0,NULL,'2026-08-14 08:04:57','2026-08-14 08:04:57',NULL),(3242,NULL,'COMP 008','Data Structures and Algorithms',NULL,NULL,2.0,3.0,3.0,NULL,'2026-08-14 08:04:57','2026-08-14 08:04:57',NULL),(3243,NULL,'COMP 011','Object Oriented Programming',NULL,NULL,2.0,3.0,3.0,NULL,'2026-08-14 08:04:57','2026-08-14 08:04:57',NULL),(3244,NULL,'COMP 016','Web Development',NULL,NULL,2.0,3.0,3.0,NULL,'2026-08-14 08:04:57','2026-08-14 08:04:57',NULL),(3245,NULL,'COMP 020','Database Management Systems',NULL,NULL,2.0,3.0,3.0,NULL,'2026-08-14 08:04:57','2026-08-14 08:04:57',NULL),(3246,NULL,'COMP 025','Information Management',NULL,NULL,3.0,0.0,3.0,NULL,'2026-08-14 08:04:57','2026-08-14 08:04:57',NULL),(3247,NULL,'COMP 030','Systems Integration and Architecture',NULL,NULL,2.0,3.0,3.0,NULL,'2026-08-14 08:04:57','2026-08-14 08:04:57',NULL),(3248,NULL,'COMP 035','Networking 1',NULL,NULL,2.0,3.0,3.0,NULL,'2026-08-14 08:04:57','2026-08-14 08:04:57',NULL),(3249,NULL,'COMP 040','Software Engineering',NULL,NULL,3.0,0.0,3.0,NULL,'2026-08-14 08:04:57','2026-08-14 08:04:57',NULL),(3250,NULL,'COMP 045','Capstone Project 1',NULL,NULL,0.0,3.0,3.0,NULL,'2026-08-14 08:04:57','2026-08-14 08:04:57',NULL),(3251,NULL,'COMP 050','Capstone Project 2',NULL,NULL,0.0,3.0,3.0,NULL,'2026-08-14 08:04:57','2026-08-14 08:04:57',NULL),(3252,NULL,'DIT 101','Advanced Database Systems',NULL,NULL,3.0,0.0,3.0,NULL,'2026-08-14 08:04:57','2026-08-14 08:04:57',NULL),(3253,NULL,'DIT 102','Data Mining and Analytics',NULL,NULL,3.0,0.0,3.0,NULL,'2026-08-14 08:04:57','2026-08-14 08:04:57',NULL),(3254,NULL,'DIT 103','Advanced Web and Mobile Development',NULL,NULL,2.0,3.0,3.0,NULL,'2026-08-14 08:04:57','2026-08-14 08:04:57',NULL),(3255,6778,'COMP 067','Advanced System Architecture',NULL,NULL,4.0,3.0,2.0,7.0,'2026-08-15 21:14:10','2026-08-15 21:14:10',NULL),(3686,6778,'COMP 101','Olala',NULL,NULL,1.0,3.0,4.0,NULL,'2026-09-25 09:23:47','2026-09-25 09:24:17','2026-09-25 09:24:17'),(3687,6778,'COMP 101','Olala',NULL,NULL,3.0,3.0,3.0,NULL,'2026-09-25 09:25:23','2026-09-25 09:26:16','2026-09-25 09:26:16');
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `connection` text COLLATE utf8mb4_general_ci NOT NULL,
  `queue` text COLLATE utf8mb4_general_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_general_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_general_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_general_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_general_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_general_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_09_21_000001_create_audit_logs_table',2),(5,'2026_09_21_000002_create_audit_trails_table',2),(8,'2026_09_24_000001_create_course_program_table',3);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `programs`
--

DROP TABLE IF EXISTS `programs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `programs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4501 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `programs`
--

LOCK TABLES `programs` WRITE;
/*!40000 ALTER TABLE `programs` DISABLE KEYS */;
INSERT INTO `programs` VALUES (3244,'BSIT','Bachelor of Science in Information Technology','2026-08-14 07:50:48','2026-08-14 07:50:48'),(3245,'DIT','Diploma in Information Technology','2026-08-14 07:50:48','2026-08-14 07:50:48');
/*!40000 ALTER TABLE `programs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_general_ci,
  `payload` longtext COLLATE utf8mb4_general_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `syllabi`
--

DROP TABLE IF EXISTS `syllabi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `syllabi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint unsigned NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_general_ci NOT NULL,
  `file_type` enum('pdf','docx') COLLATE utf8mb4_general_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `raw_text` longtext COLLATE utf8mb4_general_ci,
  `curriculum_year` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('pending','processed','failed') COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `course_id` (`course_id`),
  KEY `uploaded_by` (`uploaded_by`),
  FULLTEXT KEY `ft_syllabus_search` (`raw_text`),
  CONSTRAINT `syllabi_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `syllabi_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2192 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `syllabi`
--

LOCK TABLES `syllabi` WRITE;
/*!40000 ALTER TABLE `syllabi` DISABLE KEYS */;
INSERT INTO `syllabi` VALUES (1792,3239,'syllabi/3239/yjD150xcusFPnLpg1mGzAkqw15pLTrhfEdScqvyW.pdf','pdf','Laravel PHP Setup.pdf','WEB DEV\nWeb Development Notes\nCore Terminology\nTerm Definition	Example\nFrameworkDirects flow and structure of applicationLaravel, Django, React\nLibrary Tool you call when needed jQuery, Lodash\nComposer PHP dependency manager Manages Laravel packages\nBefore Installing LaravelCore Concepts\nUnderstanding the difference between frameworks, libraries, and package managers is essential\nfor modern web development. Framework vs Library\nFramework: \"Don\'t call us, we\'ll call you\" (Inversion of Control)\nLibrary: You call it when you need specific functionality Prerequisites Check\nEnsure you have installed:\nPHP 8.0+ (check with php -v)\nComposer (check with composer -V)\nNode.js + npm (check with node -v and npm -v)\n\nProject Setup & Commands\nCreate New Laravel Project\nCommand	Description\ncomposer create-project laravel/laravel . Create in current directory\ncomposer create-project laravel/laravel folder-nameCreate in new folder\n\nRun Development Server\nComposer Notes\nQuick Fix: Illuminate\\Database\\QueryException\nProblem\nDatabase connection error when running Laravel.\nSolution\nFile Anatomy\nFile Purpose\n.env Sensitive config (database credentials, app keys)\nphp artisan serveAbout composer update\nOnly run composer update after manually editing composer.json. Avoid running it unnecessarily\nas it updates all packages. \n1. Open Laravel project root folder\n2. Edit .env file\n3. Find: SESSION_DRIVER=database\n4. Change to: SESSION_DRIVER=file\n# Before\nSESSION_DRIVER=database\n# After\nSESSION_DRIVER=fileOther Common .env Fixes\nEnsure DB_HOST=127.0.0.1 (not localhost)\nCheck DB_PORT=3306 (default MySQL port)\nVerify DB_DATABASE exists in MySQL\n\nFile Purpose\ncomposer.jsonLists installed packages and versions\nartisan Laravel CLI tool for commands\npackage.jsonLists npm packages (frontend)\nWeek 5: Bootstrap Integration\nStep-by-Step Setup\nRouting Basics\n# 1. Install Laravel UI package\ncomposer require laravel/ui\n# 2. Install Bootstrap via npm\nnpminstall bootstrap\n# 3. Fix PowerShell execution policy (if step 2 fails)\nSet-ExecutionPolicy RemoteSigned -Scope CurrentUser\n# 4. Scaffold Bootstrap UI\nphp artisan ui bootstrap\n# 5. Install Bootstrap Icons\nnpminstall bootstrap-icons\n# 6. Add to /resources/sass/app.scss\n@import \'bootstrap-icons/font/bootstrap-icons.css\';\n# 7. Install all dependencies\nnpminstall\n# 8. Build assets\nnpm run build\n# OR for development with watch:\nnpm run devPowerShell Execution Policy\nIf npm install fails, run:\nSet-ExecutionPolicy RemoteSigned -Scope CurrentUser\nThen retry npm install.\n\nSimple Route Example\nFallback Route (Catch-All)\nCommon Route Methods\nMethod	Purpose\nRoute::get() Display a page\nRoute::post() Submit form data\nRoute::put() Update resource\nRoute::delete() Delete resource\nRoute::resource()Full CRUD routes\nController Commands\nCreate Controllers\nCommand	Description\nphp artisan make:controller \nControllerName\nBasic controller\nphp artisan make:controller \nControllerName --resource\nCRUD controller (index, create, store, show, \nedit, update, destroy)\nphp artisan make:controller \nNewsController --invokable\nSingle-action controller\nResource Controller Methods\nMethod Route	Purpose\nindex() GET /resource	List all resources\ncreate()GET /resource/create Show create form\nstore() POST /resource Save new resource\nshow() GET /resource/{id} Show single resource\nRoute::get(\'page_name\',function(){\nreturn\'<h1>Page content</h1>\';\n})->name(\'pageRouteName\');\nRoute::fallback(function(){\nreturnredirect()->route(\'pageRouteName\');\n});\n\nMethod Route	Purpose\nedit() GET /resource/{id}/edit Show edit form\nupdate()PUT/PATCH /resource/{id}Update resource\ndestroy()DELETE /resource/{id} Delete resource\nCommon Artisan Commands\nCommand	Description\nphp artisan serve	Start development server\nphp artisan route:list	List all registered routes\nphp artisan make:model ModelName	Create Eloquent model\nphp artisan make:migration create_table_nameCreate migration\nphp artisan migrate	Run migrations\nphp artisan tinker	Interactive REPL for Laravel\nphp artisan cache:clear	Clear application cache\nQuick Reference: File Structure\nlaravel-project/\n├── app/\n│   ├── Http/Controllers/  # Controllers\n│   ├── Models/             # Eloquent models\n│   └── Providers/          # Service providers\n├── resources/\n│   ├── views/              # Blade templates\n│   ├── sass/               # SCSS files\n│   └── js/                 # JavaScript\n├── routes/\n│   └── web.php             # Web routes\n├── .env                    # Environment config\n└── composer.json           # PHP dependencies','2022-2023','processed',6778,'2026-09-15 04:58:25','2026-09-21 13:02:56','2026-09-21 13:02:56'),(1793,3239,'syllabi/3239/pHkzSCgfqO9KUs3ghBrcyCjBaTrZKvSJENcOorAl.pdf','pdf','Computer_Society_Outgoing_Officers_AY_2025-2026.pdf','Computer Society • Outgoing Officers A.Y. 2025–2026 • Page 1\nCOMPUTER SOCIETY\nOUTGOING OFFICERS\nAcademic Year 2025–2026\nOfficial list of officers whose service under the Computer Society concluded after A.Y. 2025–2026. Individuals who\ncontinued as officers in A.Y. 2026–2027 are excluded.\nI. CS CENTRAL BODY — 18 OUTGOING OFFICERS\nNo. Position Held (A.Y. 2025–2026)	Name\n1 CS President	Hon. Alec Godwin Almirañez\n2 VP for Internal Affairs	Hon. Daniel Victorioso\n3 VP for External Affairs	Hon. Kristine Kyle Israel\n4 VP for Records	Hon. Hezekiah R. Mejilla\n5 AVP for Records	Hon. Ramon Anthony V. Dela Fuente\n6 VP for Finance	Hon. Mikka Kette Esparagoza\n7 AVP for Finance	Hon. Shaina Cuevas\n8 VP for RnD	Hon. Jaira Isabel Ocariza\n9 AVP for RnD	Hon. Andrhea Louise Legaspi\n10 VP for Audit	Hon. Kristine Salazar\n11 AVP for Communications	Hon. Dale Martin Tubio\n12 Director for Academics	Hon. Kyle Mata\n13 Co-Director for Academics	Hon. Neil Jerald Linga\n14 Director for Creatives	Hon. Grace Anne Lim\n15 Co-Director for Creatives	Hon. Carmela Azarcon\n16 Director for Sports	Hon. James Ryan Arante\n17 Co-Director for Sports	Hon. Kurt Wenson Aldave\n18 CS Representative	Hon. Jhay Dominique VelascoII. APPRENTICES & COMMITTEES — 17 OUTGOING MEMBERS\nNo. Position Held (A.Y. 2025–2026)	Name\n19 Internal Affairs – Apprentice	Hon. Hannah Lorainne Genandoy\n20 External Affairs – Apprentice	Hon. Prince Dale Limosnero\n21 Finance – Apprentice	Hon. Janseth Vega\n22 VP for Records – Apprentice	Hon. Mary Elizabeth S. Salvador\n\nComputer Society • Outgoing Officers A.Y. 2025–2026 • Page 2\nNo. Position Held (A.Y. 2025–2026)	Name\n23 AVP for RnD – Apprentice	Hon. Lean Chad Masungsong\n24 Communications Committee	Hon. Jonathan Asuncion\n25 Communications Committee	Hon. Norjanah Macalatas\n26 Communications Committee	Hon. Aaron Pizarras\n27 Academics Committee	Hon. Zyruss Grospe\n28 Academics Committee	Hon. Christian Manlangit\n29 Academics Committee	Hon. Art Nathan Olegario\n30 Creatives Committee	Hon. Lorenz Samuel Y. Cudera\n31 Creatives Committee	Hon. Chelsea G. Roman\n32 Creatives Committee	Hon. Biana B. Tagyamon\n33 Sports Committee	Hon. John Mutia\n34 Sports Committee	Hon. Yeriel Gyan Pallada\n35 Sports Committee	Hon. Mariel Rubic\nTotal Outgoing Officers: 35\nPrepared for official organizational records and university submission.','2022-2023','processed',6778,'2026-09-21 13:02:56','2026-09-21 13:02:56',NULL),(1794,3239,'syllabi/3239/fSUzF2EqdtyxPxcyUSd4mfIQp88IsgD6FgznZulK.docx','docx','Activity-2-Angles.docx','COMP 017 – Multimedia\nActivity 2: Camera Angles\nName: Mejilla, Hezekiah R.\nProgram, Year, and Section: DIT 3-1\nHigh Angle\nLow Angle\nEye-Level Angle\nBird’s Eye View\nBug’s Eye View','2022-2023','processed',6778,'2026-09-21 13:02:56','2026-09-21 13:02:56',NULL);
/*!40000 ALTER TABLE `syllabi` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('admin','faculty','intern') COLLATE utf8mb4_general_ci DEFAULT 'intern',
  `remember_token` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=9223 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (6778,'Test Admin','admin@test.syllabihub',NULL,'$2y$12$rhgBXsvwTvEwP6UGV/PZmulPOfzZOg/yebRZG/Mx.dGvUOs339NNi','admin','GEjw7ULjjQxujG2BcqbFbxo9Lx3IQlpzeSyYGqyg46obSaVWk3CMeSnhCnJv','2026-08-14 08:04:30','2026-08-14 08:04:30'),(6779,'Test Faculty','faculty@test.syllabihub',NULL,'$2y$12$aQWUKJch5XxSFu/LaRYsPulULTGnYpm7hfLIdup2uJRc.Ax07mJH2','faculty',NULL,'2026-08-14 08:04:31','2026-08-14 08:04:31'),(6780,'Test Intern','intern@test.syllabihub',NULL,'$2y$12$/t.Vl625UoLlzGt6XosPT.HxQLtru8BRULn9fuzBmJNeAJdhwsG5C','intern',NULL,'2026-08-14 08:04:31','2026-08-14 08:04:31');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-28 14:23:45
