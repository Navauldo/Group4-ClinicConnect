<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/../includes/config.php';

$success_message = "";
$error_message = "";

// Get upcoming appointments (next 24-48 hours)
$stmt = $pdo->prepare("
    SELECT a.*, 
           CONCAT(a.appointment_date, ' ', a.appointment_time) as appointment_datetime
    FROM appointments a 
    WHERE a.status = 'booked' 
    AND CONCAT(a.appointment_date, ' ', a.appointment_time) BETWEEN 
        DATE_ADD(NOW(), INTERVAL 24 HOUR) AND DATE_ADD(NOW(), INTERVAL 48 HOUR)
    ORDER BY a.appointment_date, a.appointment_time
");
$stmt->execute();
$upcoming_appointments = $stmt->fetchAll();

// Handle reminder sending
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_reminders'])) {
    $selected_appointments = $_POST['selected_appointments'] ?? [];
    $reminder_type = $_POST['reminder_type']; // 'email' or 'sms'
    
    if (empty($selected_appointments)) {
        $error_message = "<div class='alert alert-warning'>Please select at least one appointment to send reminders.</div>";
    } else {
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($selected_appointments as $appointment_id) {
            // Get appointment details
            $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
            $stmt->execute([$appointment_id]);
            $appointment = $stmt->fetch();
            
            if ($appointment) {
                // Simulate sending reminder (we'll add real API integration later)
                $reminder_sent = sendReminder($appointment, $reminder_type);
                
                if ($reminder_sent) {
                    $sent_count++;
                    
                    // Mark as reminder sent in database
                    $update_stmt = $pdo->prepare("UPDATE appointments SET reminder_sent = 1 WHERE id = ?");
                    $update_stmt->execute([$appointment_id]);
                } else {
                    $failed_count++;
                }
            }
        }
        
        if ($sent_count > 0) {
            $success_message = "<div class='alert alert-success'><strong>✅ Reminders Sent Successfully!</strong><br>";
            $success_message .= "📧 Sent: $sent_count reminders<br>";
            if ($failed_count > 0) {
                $success_message .= "❌ Failed: $failed_count reminders";
            }
            $success_message .= "</div>";
        } else {
            $error_message = "<div class='alert alert-danger'>❌ No reminders were sent. Please check your configuration.</div>";
        }
    }
}

// Simulated reminder function (will be replaced with real API calls)
function sendReminder($appointment, $type) {
    // For now, just simulate success
    // Later we'll integrate with SendGrid (email) and Twilio (SMS)
    
    $patient_name = $appointment['patient_name'];
    $appointment_date = date('l, F j, Y', strtotime($appointment['appointment_date']));
    $appointment_time = date('g:i A', strtotime($appointment['appointment_time']));
    $booking_ref = $appointment['booking_reference'];
    
    if ($type == 'email') {
        // Email reminder content
        $subject = "Appointment Reminder - ClinicConnect";
        $message = "Hello $patient_name,\n\nThis is a reminder for your appointment on $appointment_date at $appointment_time.\n\nBooking Reference: $booking_ref\n\nPlease arrive 10 minutes early. To reschedule or cancel, please contact the clinic.\n\nThank you!";
        
        // Simulate email send (return true for now)
        return true;
        
    } else if ($type == 'sms') {
        // SMS reminder content (shorter)
        $message = "Hi $patient_name, reminder: Appt on $appointment_date at $appointment_time. Ref: $booking_ref";
        
        // Simulate SMS send (return true for now)
        return true;
    }
    
    return false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Reminders - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .reminder-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s;
        }
        .reminder-card:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        .appointment-time {
            font-weight: bold;
            color: #007bff;
        }
        .patient-info {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .reminder-stats {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border-radius: 8px;
            padding: 15px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">← ClinicConnect Staff</a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <h2>📧 Send Appointment Reminders</h2>
                <p class="text-muted">Send bulk reminders to patients with upcoming appointments (next 24-48 hours)</p>
                
                <?php echo $error_message; ?>
                <?php echo $success_message; ?>
                
                <form method="POST" id="remindersForm">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Upcoming Appointments</h5>
                                <div>
                                    <span class="badge bg-light text-dark">
                                        <?= count($upcoming_appointments); ?> appointments
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (count($upcoming_appointments) > 0): ?>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAll">
                                        <label class="form-check-label fw-bold" for="selectAll">
                                            Select All Appointments
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <?php foreach ($upcoming_appointments as $appointment): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card reminder-card">
                                            <div class="card-body">
                                                <div class="form-check">
                                                    <input class="form-check-input appointment-checkbox" 
                                                           type="checkbox" 
                                                           name="selected_appointments[]" 
                                                           value="<?= $appointment['id']; ?>" 
                                                           id="appointment_<?= $appointment['id']; ?>">
                                                    <label class="form-check-label w-100" for="appointment_<?= $appointment['id']; ?>">
                                                        <div class="appointment-time">
                                                            <?= date('D, M j', strtotime($appointment['appointment_date'])); ?> 
                                                            at <?= date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                                        </div>
                                                        <div class="patient-info">
                                                            <strong><?= htmlspecialchars($appointment['patient_name']); ?></strong><br>
                                                            📞 <?= htmlspecialchars($appointment['patient_phone']); ?><br>
                                                            📧 <?= htmlspecialchars($appointment['patient_email']); ?>
                                                        </div>
                                                        <small class="text-muted">
                                                            Ref: <?= $appointment['booking_reference']; ?>
                                                        </small>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <div class="text-muted">
                                        <h5>No upcoming appointments</h5>
                                        <p>There are no appointments scheduled for the next 24-48 hours.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (count($upcoming_appointments) > 0): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5>Reminder Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Reminder Type</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="reminder_type" value="email" id="emailReminders" checked>
                                            <label class="form-check-label" for="emailReminders">
                                                📧 Email Reminders
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="reminder_type" value="sms" id="smsReminders">
                                            <label class="form-check-label" for="smsReminders">
                                                📱 SMS Reminders
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="reminder-stats">
                                        <h6>Ready to Send</h6>
                                        <div id="selectedCount">0</div>
                                        <small>appointments selected</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" name="send_reminders" value="1" class="btn btn-success btn-lg">
                            🚀 Send Reminders to Selected Patients
                        </button>
                        <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
                    </div>
                    <?php else: ?>
                    <div class="mt-4">
                        <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5>About Reminders</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>How it works:</strong></p>
                        <ul>
                            <li>Select appointments from the next 24-48 hours</li>
                            <li>Choose Email or SMS reminders</li>
                            <li>Send bulk reminders with one click</li>
                            <li>Track sent reminders in the system</li>
                        </ul>
                        
                        <div class="alert alert-warning">
                            <small>
                                <strong>Note:</strong> Currently using simulated sending. 
                                Connect SendGrid (email) and Twilio (SMS) APIs for real notifications.
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-body">
                        <h6>Reminder Benefits</h6>
                        <ul class="small">
                            <li>Reduces no-show rates by up to 70%</li>
                            <li>Improves patient satisfaction</li>
                            <li>Saves staff time on phone calls</li>
                            <li>Automates communication process</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Select all functionality
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.appointment-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateSelectedCount();
    });

    // Update selected count
    function updateSelectedCount() {
        const selectedCount = document.querySelectorAll('.appointment-checkbox:checked').length;
        document.getElementById('selectedCount').textContent = selectedCount;
    }

    // Add event listeners to all checkboxes
    document.querySelectorAll('.appointment-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });

    // Initialize count on page load
    document.addEventListener('DOMContentLoaded', updateSelectedCount);
    </script>
</body>
</html>