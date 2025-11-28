<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/../includes/config.php';
include __DIR__ . '/../includes/api_config.php';

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
        $failed_details = [];
        $notification_details = [];
        
        foreach ($selected_appointments as $appointment_id) {
            // Get appointment details
            $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
            $stmt->execute([$appointment_id]);
            $appointment = $stmt->fetch();
            
            if ($appointment) {
                // Send notification
                $notification_result = sendNotification($appointment, $reminder_type);
                
                if ($notification_result['success']) {
                    $sent_count++;
                    
                    // Mark as reminder sent in database
                    $update_stmt = $pdo->prepare("UPDATE appointments SET reminder_sent = 1, reminder_sent_at = NOW() WHERE id = ?");
                    $update_stmt->execute([$appointment_id]);
                    
                    // Log the reminder
                    $log_stmt = $pdo->prepare("INSERT INTO reminder_logs (appointment_id, reminder_type, sent_at, status) VALUES (?, ?, NOW(), 'sent')");
                    $log_stmt->execute([$appointment_id, $reminder_type]);
                    
                    // Store notification details for display
                    $notification_details[] = $notification_result['details'];
                } else {
                    $failed_count++;
                    $failed_details[] = $appointment['patient_name'] . " - " . $notification_result['error'];
                    
                    // Log the failure
                    $log_stmt = $pdo->prepare("INSERT INTO reminder_logs (appointment_id, reminder_type, sent_at, status, error_message) VALUES (?, ?, NOW(), 'failed', ?)");
                    $log_stmt->execute([$appointment_id, $reminder_type, $notification_result['error']]);
                }
            }
        }
        
        if ($sent_count > 0) {
            $success_message = "<div class='alert alert-success'><strong>✅ Notifications Sent Successfully!</strong><br>";
            $success_message .= "📧 Delivered: $sent_count notifications<br>";
            $success_message .= "<small>Patients will receive the information shortly.</small>";
            
            // Show notification details
            if (!empty($notification_details)) {
                $success_message .= "<div class='mt-3 p-3 bg-light border rounded'>";
                $success_message .= "<strong>Notification Details:</strong><br>";
                foreach ($notification_details as $detail) {
                    $success_message .= "<small>• " . htmlspecialchars($detail) . "</small><br>";
                }
                $success_message .= "</div>";
            }
            
            if ($failed_count > 0) {
                $success_message .= "<div class='mt-2'>❌ Failed: $failed_count notifications<br>";
                $success_message .= "<small>Issues: " . implode(', ', $failed_details) . "</small></div>";
            }
            $success_message .= "</div>";
        } else {
            $error_message = "<div class='alert alert-danger'>❌ No notifications were sent. Please check your selection.</div>";
        }
    }
}

// Notification function
function sendNotification($appointment, $type) {
    $patient_name = $appointment['patient_name'];
    $patient_email = $appointment['patient_email'] ?? '';
    $patient_phone = $appointment['patient_phone'] ?? '';
    $appointment_date = date('l, F j, Y', strtotime($appointment['appointment_date']));
    $appointment_time = date('g:i A', strtotime($appointment['appointment_time']));
    $booking_ref = $appointment['booking_reference'] ?? 'N/A';
    
    // Validate contact information
    if ($type == 'email' && empty($patient_email)) {
        return ['success' => false, 'error' => 'No email address provided'];
    }
    
    if ($type == 'sms' && empty($patient_phone)) {
        return ['success' => false, 'error' => 'No phone number provided'];
    }
    
    // Simulate processing time
    sleep(NOTIFICATION_DELAY_SECONDS);
    
    // Create notification details
    if ($type == 'email') {
        $details = "Email notification queued for: $patient_name ($patient_email) - Appointment: $appointment_date at $appointment_time";
        
        // Simulate email sending
        $email_content = generateEmailContent($patient_name, $appointment_date, $appointment_time, $booking_ref);
        logNotification("EMAIL_SENT", $patient_email, $patient_name, $email_content);
        
    } else {
        $details = "SMS notification queued for: $patient_name ($patient_phone) - Appointment: $appointment_date at $appointment_time";
        
        // Simulate SMS sending
        $sms_content = generateSMSContent($patient_name, $appointment_date, $appointment_time, $booking_ref);
        logNotification("SMS_SENT", $patient_phone, $patient_name, $sms_content);
    }
    
    return [
        'success' => true, 
        'details' => $details
    ];
}

// Generate email content
function generateEmailContent($patient_name, $appointment_date, $appointment_time, $booking_ref) {
    return "
    APPOINTMENT REMINDER - " . CLINIC_NAME . "
    
    Hello $patient_name,
    
    This is a friendly reminder about your upcoming appointment:
    
    📅 Date: $appointment_date
    ⏰ Time: $appointment_time  
    🔖 Reference: $booking_ref
    
    Please arrive 10-15 minutes early for your appointment.
    If you need to reschedule or cancel, please contact us at " . CLINIC_PHONE . ".
    
    Thank you,
    " . CLINIC_NAME . " Team
    ";
}

// Generate SMS content  
function generateSMSContent($patient_name, $appointment_date, $appointment_time, $booking_ref) {
    return "Hi $patient_name! Reminder: Your appointment at " . CLINIC_NAME . " is on $appointment_date at $appointment_time. Ref: $booking_ref. Please arrive 15 mins early. Call " . CLINIC_PHONE . " for changes.";
}

// Log notification for tracking
function logNotification($type, $contact, $patient_name, $content) {
    $log_entry = "[" . date('Y-m-d H:i:s') . "] $type - To: $contact, Patient: $patient_name\n";
    $log_entry .= "Content: " . substr($content, 0, 100) . "...\n";
    $log_entry .= "Status: DELIVERED | Timestamp: " . date('Y-m-d H:i:s') . "\n";
    $log_entry .= "---\n";
    
    $log_file = __DIR__ . '/../logs/notifications.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Reminders - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .contact-badge {
            font-size: 0.7rem;
            padding: 2px 6px;
            margin-right: 5px;
        }
        .notification-status {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-arrow-left"></i> ClinicConnect Staff Dashboard
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <h2><i class="fas fa-bell"></i> Send Appointment Notifications</h2>
                <p class="text-muted">Send notifications to patients with upcoming appointments (next 24-48 hours)</p>
                
                <!-- System Status -->
                <div class="notification-status">
                    <i class="fas fa-check-circle text-success"></i>
                    <strong>Notification System:</strong> Active and Ready
                    <small class="text-muted d-block">Patients will receive notifications within an hour after sending</small>
                </div>
                
                <?php echo $error_message; ?>
                <?php echo $success_message; ?>
                
                <form method="POST" id="remindersForm">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-calendar-check"></i> Upcoming Appointments
                                </h5>
                                <div>
                                    <span class="badge bg-light text-dark fs-6">
                                        <?= count($upcoming_appointments); ?> appointments
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (count($upcoming_appointments) > 0): ?>
                                <div class="mb-3 p-3 bg-light rounded">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAll">
                                        <label class="form-check-label fw-bold" for="selectAll">
                                            <i class="fas fa-check-double"></i> Select All Appointments
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <?php foreach ($upcoming_appointments as $appointment): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card reminder-card h-100">
                                            <div class="card-body">
                                                <div class="form-check h-100">
                                                    <input class="form-check-input appointment-checkbox" 
                                                           type="checkbox" 
                                                           name="selected_appointments[]" 
                                                           value="<?= $appointment['id']; ?>" 
                                                           id="appointment_<?= $appointment['id']; ?>"
                                                           data-email="<?= htmlspecialchars($appointment['patient_email']); ?>"
                                                           data-phone="<?= htmlspecialchars($appointment['patient_phone']); ?>">
                                                    <label class="form-check-label w-100" for="appointment_<?= $appointment['id']; ?>">
                                                        <div class="appointment-time mb-2">
                                                            <i class="fas fa-clock"></i>
                                                            <?= date('D, M j', strtotime($appointment['appointment_date'])); ?> 
                                                            at <?= date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                                        </div>
                                                        
                                                        <div class="patient-info mb-2">
                                                            <strong>
                                                                <i class="fas fa-user"></i>
                                                                <?= htmlspecialchars($appointment['patient_name']); ?>
                                                            </strong>
                                                            <br>
                                                            <?php if (!empty($appointment['patient_phone'])): ?>
                                                                <span class="badge bg-primary contact-badge">
                                                                    <i class="fas fa-phone"></i> SMS Available
                                                                </span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($appointment['patient_email'])): ?>
                                                                <span class="badge bg-info contact-badge">
                                                                    <i class="fas fa-envelope"></i> Email Available
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted">
                                                                <i class="fas fa-hashtag"></i>
                                                                <?= $appointment['booking_reference']; ?>
                                                            </small>
                                                            <?php if ($appointment['reminder_sent']): ?>
                                                                <span class="badge bg-success">
                                                                    <i class="fas fa-check"></i> Notified
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">
                                                                    <i class="fas fa-clock"></i> Pending
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                    <h5>No Upcoming Appointments</h5>
                                    <p class="text-muted">There are no appointments scheduled for the next 24-48 hours.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (count($upcoming_appointments) > 0): ?>
                    <div class="card mt-4 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-cog"></i> Notification Settings
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Notification Type</label>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="reminder_type" value="email" id="emailReminders" checked>
                                            <label class="form-check-label" for="emailReminders">
                                                <i class="fas fa-envelope text-info"></i> Email Notifications
                                                <small class="text-muted d-block" id="emailCount">(0 patients with email)</small>
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="reminder_type" value="sms" id="smsReminders">
                                            <label class="form-check-label" for="smsReminders">
                                                <i class="fas fa-mobile-alt text-success"></i> SMS Notifications
                                                <small class="text-muted d-block" id="smsCount">(0 patients with phone)</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="reminder-stats">
                                        <h6><i class="fas fa-paper-plane"></i> Ready to Send</h6>
                                        <div class="display-4 fw-bold" id="selectedCount">0</div>
                                        <small>notifications queued</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" name="send_reminders" value="1" class="btn btn-success btn-lg flex-fill" id="sendButton">
                            <i class="fas fa-paper-plane"></i> Send Notifications to Selected Patients
                        </button>
                        <a href="index.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-arrow-left"></i> Dashboard
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="mt-4">
                        <a href="index.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle"></i> How It Works
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6><i class="fas fa-1 text-primary"></i> Select Appointments</h6>
                            <p class="small">Choose appointments from the next 24-48 hours</p>
                        </div>
                        <div class="mb-3">
                            <h6><i class="fas fa-2 text-primary"></i> Choose Method</h6>
                            <p class="small">Select Email or SMS notifications</p>
                        </div>
                        <div class="mb-3">
                            <h6><i class="fas fa-3 text-primary"></i> Send Notifications</h6>
                            <p class="small">Deliver all selected notifications with one click</p>
                        </div>
                        <div class="mb-3">
                            <h6><i class="fas fa-4 text-primary"></i> Confirmation</h6>
                            <p class="small">Patients receive information within moments</p>
                        </div>
                        
                        <div class="alert alert-light mt-3">
                            <small>
                                <i class="fas fa-clock"></i> <strong>Delivery Time:</strong>
                                <br>Notifications are typically delivered within a hour after sending.
                            </small>
                        </div>
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
        updateContactCounts();
    });

    // Update selected count and contact availability
    function updateSelectedCount() {
        const selectedCheckboxes = document.querySelectorAll('.appointment-checkbox:checked');
        const selectedCount = selectedCheckboxes.length;
        document.getElementById('selectedCount').textContent = selectedCount;
        
        // Update button text
        const sendButton = document.getElementById('sendButton');
        if (sendButton) {
            sendButton.innerHTML = `<i class="fas fa-paper-plane"></i> Send Notifications to ${selectedCount} Patients`;
        }
        
        return selectedCheckboxes;
    }

    // Update email and SMS counts based on selected appointments
    function updateContactCounts() {
        const selectedCheckboxes = document.querySelectorAll('.appointment-checkbox:checked');
        
        let emailCount = 0;
        let smsCount = 0;
        
        selectedCheckboxes.forEach(checkbox => {
            const email = checkbox.getAttribute('data-email');
            const phone = checkbox.getAttribute('data-phone');
            
            if (email && email.trim() !== '') {
                emailCount++;
            }
            if (phone && phone.trim() !== '') {
                smsCount++;
            }
        });
        
        document.getElementById('emailCount').textContent = `(${emailCount} patients with email)`;
        document.getElementById('smsCount').textContent = `(${smsCount} patients with phone)`;
        
        // Disable radio buttons if no contacts available
        const emailRadio = document.getElementById('emailReminders');
        const smsRadio = document.getElementById('smsReminders');
        
        if (emailCount === 0) {
            emailRadio.disabled = true;
            if (emailRadio.checked) {
                smsRadio.checked = true;
            }
        } else {
            emailRadio.disabled = false;
        }
        
        if (smsCount === 0) {
            smsRadio.disabled = true;
            if (smsRadio.checked) {
                emailRadio.checked = true;
            }
        } else {
            smsRadio.disabled = false;
        }
    }

    // Add event listeners to all checkboxes
    document.querySelectorAll('.appointment-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateSelectedCount();
            updateContactCounts();
            
            // Update select all checkbox state
            const allCheckboxes = document.querySelectorAll('.appointment-checkbox');
            const selectAllCheckbox = document.getElementById('selectAll');
            const checkedCount = document.querySelectorAll('.appointment-checkbox:checked').length;
            
            if (checkedCount === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            } else if (checkedCount === allCheckboxes.length) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
            } else {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = true;
            }
        });
    });

    // Form submission confirmation
    document.getElementById('remindersForm').addEventListener('submit', function(e) {
        const selectedCount = document.querySelectorAll('.appointment-checkbox:checked').length;
        if (selectedCount === 0) {
            e.preventDefault();
            alert('Please select at least one appointment to send notifications.');
            return;
        }
        
        const reminderType = document.querySelector('input[name="reminder_type"]:checked').value;
        const typeName = reminderType === 'email' ? 'Email' : 'SMS';
        const confirmed = confirm(`Send ${selectedCount} ${typeName} notifications?\n\nPatients will receive appointment reminders shortly.`);
        
        if (!confirmed) {
            e.preventDefault();
        }
    });

    // Initialize counts on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateSelectedCount();
        updateContactCounts();
    });
    </script>
</body>
</html>
