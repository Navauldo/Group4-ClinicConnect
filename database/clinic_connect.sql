-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 29, 2025 at 05:10 AM
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
(3, 'Dora', 'dora@gmail.com', '1234567789', '2025-12-10', '11:30:00', 'Pain', 'booked', 'CC202511283171', '2025-11-28 00:10:01', 0, NULL),
(4, 'Test', 'test@gmail.com', '123456789', '2026-03-04', '09:30:00', 'test', 'booked', 'CC202511283365', '2025-11-28 02:23:12', 0, NULL),
(5, 'Test Patient One', 'test1@email.com', '876-555-0101', '2025-12-15', '09:00:00', 'Checkup', 'booked', 'TEST001', '2025-11-28 04:28:26', 0, NULL),
(6, 'Test Patient Two', 'test2@email.com', '876-555-0102', '2025-12-04', '09:00:00', 'Consultation', 'booked', 'TEST002', '2025-11-28 04:28:26', 0, NULL),
(7, 'Test Patient Three', 'test3@email.com', '876-555-0103', '2025-12-11', '12:00:00', 'Follow-up', 'booked', 'TEST003', '2025-11-28 04:28:26', 1, NULL),
(8, 'Sendgrid Test', 'jonesvivian930@gmail.com', '123456789', '2025-12-01', '09:00:00', 'Check up', 'booked', 'CC202511283010', '2025-11-28 05:28:11', 1, '2025-11-28 21:06:55'),
(9, 'Test Case 4', 'test@gmail.com', '1234567789', '2025-12-03', '14:00:00', 'Check up', 'booked', 'CC202511294324', '2025-11-29 02:48:29', 0, NULL);

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

--
-- Dumping data for table `clinic_closures`
--

INSERT INTO `clinic_closures` (`id`, `closure_date`, `reason`, `created_at`) VALUES
(1, '2026-01-01', 'Holiday', '2025-11-28 15:52:17'),
(3, '2025-12-25', 'Christmas', '2025-11-28 20:49:06');

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
(5, 1, 5, '09:00:00', '17:00:00', 1),
(8, 1, 2, '09:00:00', '17:00:00', 1),
(24, 1, 1, '09:00:00', '17:00:00', 1),
(25, 1, 4, '09:00:00', '17:00:00', 1);

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
-- Dumping data for table `reminder_logs`
--

INSERT INTO `reminder_logs` (`id`, `appointment_id`, `reminder_type`, `sent_at`, `status`, `error_message`, `created_at`) VALUES
(1, 8, 'email', '2025-11-28 21:00:33', 'sent', NULL, '2025-11-29 02:00:33'),
(2, 8, 'email', '2025-11-28 21:06:55', 'sent', NULL, '2025-11-29 02:06:55');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `role` enum('patient','staff','admin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `name`, `role`, `created_at`, `updated_at`) VALUES
(1, 'john.patient@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Patient', 'patient', '2025-11-29 03:40:07', '2025-11-29 03:40:07'),
(2, 'sarah.jones@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Jones', 'patient', '2025-11-29 03:40:07', '2025-11-29 03:40:07'),
(3, 'mike.smith@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike Smith', 'patient', '2025-11-29 03:40:07', '2025-11-29 03:40:07'),
(4, 'lisa.wilson@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Lisa Wilson', 'patient', '2025-11-29 03:40:07', '2025-11-29 03:40:07'),
(5, 'nurse.sarah@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Sarah Nurse', 'staff', '2025-11-29 03:40:07', '2025-11-29 03:40:07'),
(6, 'dr.johnson@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Johnson', 'staff', '2025-11-29 03:40:07', '2025-11-29 03:40:07'),
(7, 'reception.mary@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mary Receptionist', 'staff', '2025-11-29 03:40:07', '2025-11-29 03:40:07'),
(8, 'admin.clinic@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Clinic Admin', 'admin', '2025-11-29 03:40:07', '2025-11-29 03:40:07'),
(9, 'super.admin@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Super Admin', 'admin', '2025-11-29 03:40:07', '2025-11-29 03:40:07');

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
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `clinics`
--
ALTER TABLE `clinics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `clinic_closures`
--
ALTER TABLE `clinic_closures`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `clinic_schedules`
--
ALTER TABLE `clinic_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `reminder_logs`
--
ALTER TABLE `reminder_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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
