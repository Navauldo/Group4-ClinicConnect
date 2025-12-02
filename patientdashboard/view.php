<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in or has guest reference
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    if (isset($_GET['ref'])) {
        // Guest access with booking reference
        $_SESSION['guest_ref'] = $_GET['ref'];
    } else {
        header('Location: ../login.php?role=patient');
        exit;
    }
}

include '../includes/config.php';

$is_guest = isset($_SESSION['guest_ref']);
$booking_ref = $is_guest ? $_SESSION['guest_ref'] : ($_GET['ref'] ?? '');

if (!$booking_ref) {
    header('Location: index.php');
    exit;
}

// Get appointment details with security check
if ($is_guest) {
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE booking_reference = ?");
    $stmt->execute([$booking_ref]);
    $appointment = $stmt->fetch();
} else {
    $user_email = $_SESSION['user']['email'];
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE booking_reference = ? AND patient_email = ?");
    $stmt->execute([$booking_ref, $user_email]);
    $appointment = $stmt->fetch();
}

if (!$appointment) {
    header('Location: index.php?error=appointment_not_found');
    exit;
}

// Get reminder logs if any
$stmt = $pdo->prepare("SELECT * FROM reminder_logs WHERE appointment_id = ? ORDER BY sent_at DESC");
$stmt->execute([$appointment['id']]);
$reminder_logs = $stmt->fetchAll();

// Format appointment data
$appointment_date = date('l, F j, Y', strtotime($appointment['appointment_date']));
$appointment_time = date('g:i A', strtotime($appointment['appointment_time']));
$created_date = date('F j, Y', strtotime($appointment['created_at']));
$created_time = date('g:i A', strtotime($appointment['created_at']));

// Determine status badge
$status_badges = [
    'booked' => ['class' => 'primary', 'icon' => 'calendar-check'],
    'cancelled' => ['class' => 'danger', 'icon' => 'times-circle'],
    'completed' => ['class' => 'success', 'icon' => 'check-circle'],
    'no-show' => ['class' => 'warning', 'icon' => 'exclamation-triangle']
];
$status_info = $status_badges[$appointment['status']] ?? ['class' => 'secondary', 'icon' => 'question-circle'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Details - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .detail-card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .header-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
        }
        .info-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-icon {
            width: 40px;
            text-align: center;
            margin-right: 15px;
            font-size: 1.2rem;
        }
        .qr-code {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            margin: 20px 0;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .reminder-log {
            padding: 10px;
            margin: 5px 0;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 3px solid;
        }
        .log-email { border-left-color: #007bff; }
        .log-sms { border-left-color: #28a745; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <?php if (!$is_guest): ?>
            <div class="navbar-nav ms-auto">
                <span class="nav-item nav-link text-light">
                    <i class="fas fa-user-circle"></i> <?= htmlspecialchars($_SESSION['user']['name']) ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="header-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-calendar-alt"></i> Appointment Details</h1>
                    <p class="lead mb-0">Booking Reference: <strong><?= $appointment['booking_reference'] ?></strong></p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-<?= $status_info['class'] ?> fs-6 p-3">
                        <i class="fas fa-<?= $status_info['icon'] ?>"></i> 
                        <?= strtoupper($appointment['status']) ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="row mt-4">
            <div class="col-lg-8">
                <!-- Appointment Information -->
                <div class="card detail-card">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">
                            <i class="fas fa-info-circle text-primary"></i> Appointment Information
                        </h4>
                    </div>
                    <div class="card-body p-0">
                        <div class="info-item">
                            <div class="info-icon text-primary">
                                <i class="fas fa-calendar-day"></i>
                            </div>
                            <div>
                                <strong>Appointment Date & Time</strong><br>
                                <?= $appointment_date ?> at <?= $appointment_time ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon text-primary">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <strong>Patient Information</strong><br>
                                <?= htmlspecialchars($appointment['patient_name']) ?><br>
                                <small class="text-muted">
                                    <?= htmlspecialchars($appointment['patient_email']) ?> | 
                                    <?= htmlspecialchars($appointment['patient_phone']) ?>
                                </small>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon text-primary">
                                <i class="fas fa-stethoscope"></i>
                            </div>
                            <div>
                                <strong>Reason for Visit</strong><br>
                                <?= htmlspecialchars($appointment['reason'] ?: 'General Checkup') ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-icon text-primary">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <strong>Booking Information</strong><br>
                                Booked on <?= $created_date ?> at <?= $created_time ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Reminder Logs -->
                <?php if (!empty($reminder_logs)): ?>
                <div class="card detail-card mt-4">
                    <div class="card-header bg-white">
                        <h4 class="mb-0">
                            <i class="fas fa-bell text-warning"></i> Reminder History
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php foreach ($reminder_logs as $log): ?>
                        <div class="reminder-log log-<?= $log['reminder_type'] ?>">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <i class="fas fa-<?= $log['reminder_type'] == 'email' ? 'envelope' : 'sms' ?>"></i>
                                    <strong><?= strtoupper($log['reminder_type']) ?> Reminder</strong>
                                </div>
                                <div class="text-muted">
                                    <?= date('M j, Y g:i A', strtotime($log['sent_at'])) ?>
                                </div>
                            </div>
                            <div class="mt-1">
                                <span class="badge bg-<?= $log['status'] == 'sent' ? 'success' : 'danger' ?>">
                                    <?= ucfirst($log['status']) ?>
                                </span>
                                <?php if ($log['error_message']): ?>
                                    <small class="text-danger">Error: <?= htmlspecialchars($log['error_message']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar Actions -->
            <div class="col-lg-4">
                <!-- QR Code -->
                <div class="card detail-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-qrcode text-success"></i> Quick Check-in Code
                        </h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="qr-code">
                            <div id="qrcode"></div>
                            <p class="mt-3 mb-0">
                                <small class="text-muted">
                                    Show this at reception for quick check-in<br>
                                    Reference: <?= $appointment['booking_reference'] ?>
                                </small>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="card detail-card mt-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-cogs text-info"></i> Appointment Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="action-buttons">
                            <?php if ($appointment['status'] == 'booked' && strtotime($appointment['appointment_date']) >= time()): ?>
                            <a href="reschedule.php?ref=<?= $booking_ref ?>" class="btn btn-warning flex-fill">
                                <i class="fas fa-calendar-alt"></i> Reschedule
                            </a>
                            <a href="cancel.php?ref=<?= $booking_ref ?>" class="btn btn-danger flex-fill">
                                <i class="fas fa-times-circle"></i> Cancel
                            </a>
                            <?php endif; ?>
                            
                            <a href="javascript:window.print()" class="btn btn-secondary flex-fill">
                                <i class="fas fa-print"></i> Print Details
                            </a>
                            
                            <button onclick="shareAppointment()" class="btn btn-success flex-fill">
                                <i class="fas fa-share-alt"></i> Share
                            </button>
                            
                            <?php if ($appointment['status'] == 'cancelled'): ?>
                            <a href="../booking/index.php" class="btn btn-primary flex-fill">
                                <i class="fas fa-redo"></i> Book Again
                            </a>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Status-specific tips -->
                        <div class="mt-4">
                            <?php if ($appointment['status'] == 'booked'): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-lightbulb"></i>
                                <strong>Tip:</strong> Arrive 15 minutes before your appointment time.
                            </div>
                            <?php elseif ($appointment['status'] == 'cancelled'): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Note:</strong> This appointment was cancelled.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Clinic Information -->
                <div class="card detail-card mt-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fas fa-hospital text-primary"></i> Clinic Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $pdo->prepare("SELECT * FROM clinics WHERE id = 1");
                        $stmt->execute();
                        $clinic = $stmt->fetch();
                        ?>
                        <p class="mb-1">
                            <i class="fas fa-map-marker-alt"></i> 
                            <strong>Address:</strong><br>
                            <?= htmlspecialchars($clinic['address'] ?? '123 Health Street') ?>
                        </p>
                        <p class="mb-1">
                            <i class="fas fa-phone"></i> 
                            <strong>Phone:</strong><br>
                            <?= htmlspecialchars($clinic['phone'] ?? '(876) 555-0123') ?>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-envelope"></i> 
                            <strong>Email:</strong><br>
                            <?= htmlspecialchars($clinic['email'] ?? 'contact@clinicconnect.com') ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Code Library -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Generate QR Code
    document.addEventListener('DOMContentLoaded', function() {
        const qrData = JSON.stringify({
            ref: "<?= $appointment['booking_reference'] ?>",
            name: "<?= addslashes($appointment['patient_name']) ?>",
            date: "<?= $appointment['appointment_date'] ?>",
            time: "<?= $appointment['appointment_time'] ?>"
        });
        
        QRCode.toCanvas(document.getElementById('qrcode'), qrData, {
            width: 200,
            margin: 1,
            color: {
                dark: '#000000',
                light: '#FFFFFF'
            }
        }, function(error) {
            if (error) {
                console.error(error);
                document.getElementById('qrcode').innerHTML = 
                    '<div class="alert alert-danger">QR Code could not be generated</div>';
            }
        });
    });
    
    function shareAppointment() {
        const shareData = {
            title: 'My ClinicConnect Appointment',
            text: `Appointment for ${$appointment['patient_name']} on ${$appointment_date} at ${$appointment_time}`,
            url: window.location.href
        };
        
        if (navigator.share) {
            navigator.share(shareData)
                .then(() => console.log('Shared successfully'))
                .catch(error => console.log('Error sharing:', error));
        } else {
            // Fallback: Copy to clipboard
            const textArea = document.createElement('textarea');
            textArea.value = `Appointment Details:\nPatient: ${$appointment['patient_name']}\nDate: ${$appointment_date}\nTime: ${$appointment_time}\nReference: ${$appointment['booking_reference']}\nURL: ${window.location.href}`;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            alert('Appointment details copied to clipboard!');
        }
    }
    
    // Auto-refresh if appointment is upcoming
    <?php if ($appointment['status'] == 'booked'): ?>
    setTimeout(function() {
        window.location.reload();
    }, 60000); // Refresh every minute for real-time updates
    <?php endif; ?>
    </script>
</body>
</html>