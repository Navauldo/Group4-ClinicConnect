-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 28, 2025 at 10:08 AM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `clinic_connect`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_name` varchar(255) NOT NULL,
  `patient_email` varchar(255) DEFAULT NULL,
  `patient_phone` varchar(20) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `reason` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'booked',
  `booking_reference` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reminder_sent` tinyint(1) DEFAULT 0,
  `reminder_sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_name`, `patient_email`, `patient_phone`, `appointment_date`, `appointment_time`, `reason`, `status`, `booking_reference`, `created_at`, `reminder_sent`, `reminder_sent_at`) VALUES
(1, 'John Doe', 'johndoe@gmail.com', '123456789', '2026-01-15', '10:00:00', 'I have the flu.', 'booked', 'CC202511271529', '2025-11-27 22:55:29', 0, NULL),
(2, 'John Doe', 'johndoe@gmail.com', '123456789', '2025-12-12', '09:00:00', 'Flu', 'cancelled', 'CC202511287375', '2025-11-27 23:39:26', 0, NULL),
(3, 'Dora', 'dora@gmail.com', '1234567789', '2025-12-02', '14:30:00', 'Pain', 'booked', 'CC202511283171', '2025-11-28 00:10:01', 0, NULL),
(4, 'Test', 'test@gmail.com', '123456789', '2026-03-04', '09:30:00', 'test', 'booked', 'CC202511283365', '2025-11-28 02:23:12', 0, NULL),
(5, 'Test Patient One', 'test1@email.com', '876-555-0101', '2025-12-04', '09:00:00', 'Checkup', 'booked', 'TEST001', '2025-11-28 04:28:26', 0, NULL),
(6, 'Test Patient Two', 'test2@email.com', '876-555-0102', '2025-12-04', '09:00:00', 'Consultation', 'booked', 'TEST002', '2025-11-28 04:28:26', 0, NULL),
(7, 'Test Patient Three', 'test3@email.com', '876-555-0103', '2025-11-29', '09:00:00', 'Follow-up', 'booked', 'TEST003', '2025-11-28 04:28:26', 1, NULL),
(8, 'Sendgrid Test', 'jonesvivian930@gmail.com', '123456789', '2025-12-02', '09:30:00', 'Check up', 'booked', 'CC202511283010', '2025-11-28 05:28:11', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `clinics`
--

CREATE TABLE `clinics` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinics`
--

INSERT INTO `clinics` (`id`, `name`, `address`, `phone`, `email`) VALUES
(1, 'Main Medical Clinic', '123 Health Street', '876-555-0123', 'contact@clinicconnect.com');

-- --------------------------------------------------------

--
-- Table structure for table `clinic_closures`
--

CREATE TABLE `clinic_closures` (
  `id` int(11) NOT NULL,
  `closure_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clinic_schedules`
--

CREATE TABLE `clinic_schedules` (
  `id` int(11) NOT NULL,
  `clinic_id` int(11) DEFAULT NULL,
  `day_of_week` int(11) DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinic_schedules`
--

INSERT INTO `clinic_schedules` (`id`, `clinic_id`, `day_of_week`, `start_time`, `end_time`, `is_active`) VALUES
(3, 1, 3, '09:00:00', '17:00:00', 1),
(4, 1, 4, '09:00:00', '17:00:00', 1),
(5, 1, 5, '09:00:00', '17:00:00', 1),
(8, 1, 2, '09:00:00', '17:00:00', 1),
(18, 1, 1, '09:00:00', '17:00:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `reminder_logs`
--

CREATE TABLE `reminder_logs` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `reminder_type` enum('email','sms') NOT NULL,
  `sent_at` datetime NOT NULL,
  `status` enum('sent','failed') NOT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clinics`
--
ALTER TABLE `clinics`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clinic_closures`
--
ALTER TABLE `clinic_closures`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `clinic_schedules`
--
ALTER TABLE `clinic_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_clinic_day` (`clinic_id`,`day_of_week`);

--
-- Indexes for table `reminder_logs`
--
ALTER TABLE `reminder_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `clinics`
--
ALTER TABLE `clinics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `clinic_closures`
--
ALTER TABLE `clinic_closures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clinic_schedules`
--
ALTER TABLE `clinic_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `reminder_logs`
--
ALTER TABLE `reminder_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `reminder_logs`
--
ALTER TABLE `reminder_logs`
  ADD CONSTRAINT `reminder_logs_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
