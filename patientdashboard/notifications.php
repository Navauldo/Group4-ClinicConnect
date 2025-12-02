<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    header('Location: ../login.php?role=patient');
    exit;
}

$user_id = $_SESSION['user']['id'];

// Mark all as read when visiting this page
$stmt = $pdo->prepare("UPDATE patient_notifications SET is_read = 1 WHERE patient_id = ?");
$stmt->execute([$user_id]);

header('Location: messages.php#notifications');
exit;
?>