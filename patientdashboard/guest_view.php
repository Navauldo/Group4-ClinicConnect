<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if guest reference exists
if (!isset($_SESSION['guest_ref']) && !isset($_GET['ref'])) {
    header('Location: ../login.php?role=patient');
    exit;
}

$booking_ref = $_SESSION['guest_ref'] ?? $_GET['ref'];
include '../includes/config.php';

// Get appointment details
$stmt = $pdo->prepare("SELECT * FROM appointments WHERE booking_reference = ?");
$stmt->execute([$booking_ref]);
$appointment = $stmt->fetch();

if (!$appointment) {
    unset($_SESSION['guest_ref']);
    header('Location: ../login.php?role=patient&error=appointment_not_found');
    exit;
}

// Store reference in session if not already there
if (!isset($_SESSION['guest_ref'])) {
    $_SESSION['guest_ref'] = $booking_ref;
}

$appointment_date = date('l, F j, Y', strtotime($appointment['appointment_date']));
$appointment_time = date('g:i A', strtotime($appointment['appointment_time']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Access - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .guest-card {
            border: 3px dashed #007bff;
            border-radius: 15px;
            margin-top: 50px;
        }
        .guest-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
        }
        .appointment-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        .action-buttons .btn {
            margin: 5px;
            min-width: 150px;
        }
        .login-prompt {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card guest-card">
                    <div class="guest-header text-center">
                        <h1><i class="fas fa-user-clock"></i> Guest Access</h1>
                        <p class="lead mb-0">Viewing appointment as guest</p>
                    </div>
                    
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <div class="badge bg-warning fs-5 p-3">
                                <i class="fas fa-exclamation-triangle"></i> Limited Access Mode
                            </div>
                            <p class="text-muted mt-2">You're accessing this appointment with a booking reference only.</p>
                        </div>
                        
                        <!-- Appointment Info -->
                        <div class="appointment-info">
                            <h4 class="text-center mb-4">
                                <i class="fas fa-calendar-check"></i> Appointment Details
                            </h4>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Patient Name:</strong><br>
                                    <?= htmlspecialchars($appointment['patient_name']) ?></p>
                                    
                                    <p><strong>Email:</strong><br>
                                    <?= htmlspecialchars($appointment['patient_email']) ?></p>
                                    
                                    <p><strong>Phone:</strong><br>
                                    <?= htmlspecialchars($appointment['patient_phone']) ?></p>
                                </div>
                                
                                <div class="col-md-6">
                                    <p><strong>Appointment Date:</strong><br>
                                    <?= $appointment_date ?></p>
                                    
                                    <p><strong>Appointment Time:</strong><br>
                                    <?= $appointment_time ?></p>
                                    
                                    <p><strong>Reason:</strong><br>
                                    <?= htmlspecialchars($appointment['reason']) ?></p>
                                    
                                    <p><strong>Booking Reference:</strong><br>
                                    <span class="badge bg-dark fs-6"><?= $appointment['booking_reference'] ?></span></p>
                                </div>
                            </div>
                            
                            <div class="text-center mt-3">
                                <span class="badge bg-<?= 
                                    $appointment['status'] == 'booked' ? 'success' : 
                                    ($appointment['status'] == 'cancelled' ? 'danger' : 'warning')
                                ?> fs-6 p-2">
                                    Status: <?= strtoupper($appointment['status']) ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Available Actions -->
                        <div class="text-center action-buttons">
                            <h5 class="mb-3">Available Actions:</h5>
                            
                            <?php if ($appointment['status'] == 'booked'): ?>
                            <a href="reschedule.php" class="btn btn-warning btn-lg">
                                <i class="fas fa-calendar-alt"></i> Reschedule
                            </a>
                            
                            <a href="cancel.php" class="btn btn-danger btn-lg">
                                <i class="fas fa-times-circle"></i> Cancel
                            </a>
                            
                            <a href="view.php" class="btn btn-info btn-lg">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                This appointment cannot be modified because it's <?= $appointment['status'] ?>.
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Login Prompt -->
                        <div class="login-prompt">
                            <h5><i class="fas fa-user-plus"></i> Want Full Access?</h5>
                            <p>Create an account or log in to access all features:</p>
                            <ul>
                                <li>View complete appointment history</li>
                                <li>Manage multiple appointments</li>
                                <li>Update your profile information</li>
                                <li>Receive personalized reminders</li>
                            </ul>
                            <div class="text-center mt-3">
                                <a href="../login.php?role=patient" class="btn btn-success">
                                    <i class="fas fa-sign-in-alt"></i> Login or Create Account
                                </a>
                                <button onclick="continueAsGuest()" class="btn btn-outline-secondary">
                                    <i class="fas fa-user-clock"></i> Continue as Guest
                                </button>
                            </div>
                        </div>
                        
                        <!-- Navigation -->
                        <div class="text-center mt-4">
                            <a href="../index.php" class="btn btn-outline-primary">
                                <i class="fas fa-home"></i> Back to Home
                            </a>
                            <button onclick="printPage()" class="btn btn-outline-secondary">
                                <i class="fas fa-print"></i> Print Details
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Security Notice -->
                <div class="alert alert-warning mt-4">
                    <i class="fas fa-shield-alt"></i>
                    <strong>Security Notice:</strong> Guest access is limited to protect your privacy. 
                    For security, please do not share your booking reference with others.
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function continueAsGuest() {
        alert('You will continue with limited guest access. Some features may not be available.');
    }
    
    function printPage() {
        window.print();
    }
    
    // Auto-logout guest session after 30 minutes
    setTimeout(function() {
        if (confirm('Your guest session is about to expire. Would you like to continue?')) {
            // Refresh to extend session
            window.location.reload();
        } else {
            window.location.href = '../index.php';
        }
    }, 1800000); // 30 minutes
    </script>
</body>
</html>