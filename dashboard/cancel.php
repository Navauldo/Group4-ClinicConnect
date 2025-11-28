<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../includes/config.php';

if (isset($_GET['id'])) {
    $appointment_id = $_GET['id'];
    
    try {
        // Update status to cancelled
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$appointment_id]);
        
        // Redirect back to dashboard with success message
        header("Location: index.php?message=Appointment+cancelled+successfully");
        exit;
    } catch(PDOException $e) {
        die("<div class='alert alert-danger'>Error cancelling appointment: " . $e->getMessage() . "</div>");
    }
} else {
    header("Location: index.php");
    exit;
}
?>