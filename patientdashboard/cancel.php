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

// Get appointment details with security check
if ($is_guest) {
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE booking_reference = ? AND status = 'booked'");
    $stmt->execute([$booking_ref]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        unset($_SESSION['guest_ref']);
        header('Location: ../login.php?role=patient&error=appointment_not_found');
        exit;
    }
} else {
    $user_email = $_SESSION['user']['email'];
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE booking_reference = ? AND patient_email = ? AND status = 'booked'");
    $stmt->execute([$booking_ref, $user_email]);
    $appointment = $stmt->fetch();
}

if (!$appointment && !$is_guest) {
    header('Location: index.php?error=appointment_not_found');
    exit;
}

// Common cancellation reasons
$cancellation_reasons = [
    'schedule_conflict' => 'Schedule Conflict',
    'feeling_better' => 'Feeling Better/No Longer Needed',
    'financial_concerns' => 'Financial Concerns/Cost',
    'transportation' => 'Transportation Issues',
    'weather' => 'Weather Conditions',
    'family_emergency' => 'Family Emergency',
    'doctor_preference' => 'Want to See Different Doctor',
    'clinic_location' => 'Clinic Location Inconvenient',
    'other' => 'Other Reason'
];

$success_message = "";
$error_message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel_appointment'])) {
    $cancellation_reason = $_POST['cancellation_reason'];
    $custom_reason = trim($_POST['custom_reason'] ?? '');
    
    // Validate if appointment can be cancelled (at least 2 hours before)
    $appointment_datetime = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
    $current_datetime = time();
    $hours_before = ($appointment_datetime - $current_datetime) / 3600;
    
    if ($hours_before < 2) {
        $error_message = "<div class='alert alert-danger'><strong>❌ Cannot Cancel:</strong> Appointments can only be cancelled at least 2 hours before the scheduled time. Please call the clinic directly for assistance.</div>";
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update appointment status
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled', updated_at = NOW() WHERE booking_reference = ?");
            $stmt->execute([$booking_ref]);
            
            // Store cancellation reason (you might want to create a cancellation_logs table)
            // For now, we'll update the reason field
            $final_reason = $cancellation_reason == 'other' ? $custom_reason : $cancellation_reasons[$cancellation_reason];
            if (!empty($final_reason)) {
                $stmt = $pdo->prepare("UPDATE appointments SET reason = CONCAT(reason, ' [Cancelled: ', ?, ']') WHERE booking_reference = ?");
                $stmt->execute([$final_reason, $booking_ref]);
            }
            
            // Log the cancellation
            $stmt = $pdo->prepare("INSERT INTO appointment_logs (appointment_id, action, details, performed_by) VALUES (?, 'cancelled', ?, ?)");
            $stmt->execute([$appointment['id'], "Cancelled with reason: $final_reason", 'patient']);
            
            $pdo->commit();
            
            $success_message = "<div class='alert alert-success'><strong>✅ Appointment Cancelled Successfully!</strong><br>" .
                             "Your appointment has been cancelled and the time slot has been made available for other patients.<br>" .
                             "<strong>Cancellation Reason:</strong> " . ($final_reason ?: 'Not specified') . "<br>" .
                             "<strong>Booking Reference:</strong> $booking_ref</div>";
            
            // Clear appointment data since it's now cancelled
            $appointment = null;
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error_message = "<div class='alert alert-danger'><strong>❌ Error:</strong> " . $e->getMessage() . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Appointment - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .current-appointment-card {
            border-left: 4px solid #dc3545;
            background: #f8f9fa;
        }
        .warning-card {
            border-left: 4px solid #ffc107;
            background: #fff3cd;
        }
        .reason-option {
            padding: 12px;
            margin: 8px 0;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .reason-option:hover {
            background: #f8f9fa;
            border-color: #6c757d;
        }
        .reason-option.selected {
            background: #dc3545 !important;
            color: white !important;
            border-color: #c82333;
        }
        .custom-reason-box {
            display: none;
            margin-top: 10px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 3px solid #6c757d;
        }
        .cancellation-steps {
            counter-reset: step-counter;
            list-style: none;
            padding-left: 0;
        }
        .cancellation-steps li {
            counter-increment: step-counter;
            margin-bottom: 20px;
            padding-left: 40px;
            position: relative;
        }
        .cancellation-steps li:before {
            content: counter(step-counter);
            background: #dc3545;
            color: white;
            font-weight: bold;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            left: 0;
            top: 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
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
        <h2><i class="fas fa-times-circle"></i> Cancel Appointment</h2>
        
        <?php echo $error_message; ?>
        <?php echo $success_message; ?>
        
        <?php if ($appointment): ?>
        <!-- Current Appointment Details -->
        <div class="card current-appointment-card mb-4">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-times"></i> Appointment to Cancel
                    <?php if ($is_guest): ?>
                        <span class="badge bg-warning float-end">Guest Access</span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Patient Name:</strong> <?= htmlspecialchars($appointment['patient_name']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($appointment['patient_email']) ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($appointment['patient_phone']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Appointment Date:</strong> <?= date('l, F j, Y', strtotime($appointment['appointment_date'])) ?></p>
                        <p><strong>Appointment Time:</strong> <?= date('g:i A', strtotime($appointment['appointment_time'])) ?></p>
                        <p><strong>Reason for Visit:</strong> <?= htmlspecialchars($appointment['reason']) ?></p>
                        <p><strong>Booking Reference:</strong> <span class="badge bg-primary"><?= $appointment['booking_reference'] ?></span></p>
                    </div>
                </div>
                
                <?php 
                $appointment_datetime = strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']);
                $current_datetime = time();
                $hours_before = ($appointment_datetime - $current_datetime) / 3600;
                ?>
                
                <div class="alert <?= $hours_before < 2 ? 'alert-danger' : 'alert-info' ?> mt-3">
                    <i class="fas fa-clock"></i>
                    <strong>Time Until Appointment:</strong> 
                    <?php if ($hours_before < 0): ?>
                        This appointment is in the past.
                    <?php elseif ($hours_before < 2): ?>
                        <span class="text-danger">Less than 2 hours - Please call the clinic to cancel.</span>
                    <?php else: ?>
                        Approximately <?= floor($hours_before) ?> hours and <?= round(($hours_before - floor($hours_before)) * 60) ?> minutes
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Cancellation Process -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-comment-medical"></i> Cancellation Process
                        </h5>
                    </div>
                    <div class="card-body">
                        <ol class="cancellation-steps">
                            <li>
                                <strong>Select Cancellation Reason</strong>
                                <p class="text-muted">Help us improve our services by telling us why you're cancelling.</p>
                            </li>
                            <li>
                                <strong>Review Cancellation Policy</strong>
                                <p class="text-muted">Appointments must be cancelled at least 2 hours in advance.</p>
                            </li>
                            <li>
                                <strong>Confirm Cancellation</strong>
                                <p class="text-muted">Your time slot will be released for other patients.</p>
                            </li>
                        </ol>
                    </div>
                </div>

                <!-- Cancellation Form -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-edit"></i> Step 1: Tell Us Why You're Cancelling
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="cancellationForm">
                            <input type="hidden" name="booking_reference" value="<?= $booking_ref ?>">
                            
                            <div class="mb-4">
                                <label class="form-label"><strong>Select Cancellation Reason *</strong></label>
                                <small class="text-muted d-block mb-2">Choose the primary reason for cancelling this appointment:</small>
                                
                                <?php foreach ($cancellation_reasons as $key => $reason): ?>
                                <div class="reason-option" onclick="selectReason('<?= $key ?>')">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" 
                                               name="cancellation_reason" 
                                               value="<?= $key ?>" 
                                               id="reason_<?= $key ?>"
                                               required>
                                        <label class="form-check-label w-100" for="reason_<?= $key ?>">
                                            <?= $reason ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                
                                <div id="customReasonBox" class="custom-reason-box">
                                    <label class="form-label"><strong>Please specify your reason:</strong></label>
                                    <textarea name="custom_reason" class="form-control" rows="3" 
                                              placeholder="Please provide more details about why you're cancelling..."></textarea>
                                    <small class="text-muted">Your feedback helps us improve our services.</small>
                                </div>
                            </div>
                            
                            <!-- Cancellation Policy -->
                            <div class="card warning-card mb-4">
                                <div class="card-body">
                                    <h6><i class="fas fa-exclamation-triangle"></i> Cancellation Policy</h6>
                                    <ul class="mb-0">
                                        <li>Appointments must be cancelled at least <strong>2 hours</strong> before the scheduled time</li>
                                        <li>Repeated cancellations may affect your ability to book future appointments</li>
                                        <li>The time slot will be immediately released for other patients</li>
                                        <li>You will receive a confirmation email/SMS after cancellation</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" name="cancel_appointment" value="1" 
                                        class="btn btn-danger btn-lg" 
                                        id="cancelBtn"
                                        <?= $hours_before < 2 ? 'disabled' : '' ?>>
                                    <i class="fas fa-times-circle"></i> Confirm Cancellation
                                </button>
                                <a href="index.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-arrow-left"></i> Go Back
                                </a>
                            </div>
                            
                            <?php if ($hours_before < 2): ?>
                            <div class="alert alert-danger mt-3">
                                <i class="fas fa-ban"></i>
                                <strong>Cancellation Not Available Online:</strong> 
                                This appointment is within 2 hours. Please call the clinic directly at <strong>(876) 555-0123</strong> to cancel.
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar Information -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle"></i> Important Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-calendar-times"></i> What happens when you cancel?</h6>
                            <ul class="mb-0">
                                <li>Your appointment slot becomes available immediately</li>
                                <li>You can book a new appointment at any time</li>
                                <li>Your cancellation reason helps us improve</li>
                            </ul>
                        </div>
                        
                        <div class="alert alert-success">
                            <h6><i class="fas fa-lightbulb"></i> Consider Rescheduling Instead</h6>
                            <p>If your schedule has changed, consider <strong>rescheduling</strong> instead of cancelling.</p>
                            <a href="reschedule.php?ref=<?= $booking_ref ?>" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-calendar-alt"></i> Reschedule Instead
                            </a>
                        </div>
                        
                        <div class="alert alert-primary">
                            <h6><i class="fas fa-phone"></i> Need Help?</h6>
                            <p>If you have questions or need assistance:</p>
                            <p class="mb-1"><strong>Phone:</strong> (876) 555-0123</p>
                            <p class="mb-0"><strong>Email:</strong> support@clinicconnect.com</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php elseif (!isset($success_message)): ?>
        <!-- No appointment found -->
        <div class="alert alert-danger">
            <h4><i class="fas fa-exclamation-triangle"></i> Appointment Not Found</h4>
            <p>We couldn't find the appointment you're trying to cancel. This could be because:</p>
            <ul>
                <li>The appointment has already been cancelled</li>
                <li>The appointment has already occurred</li>
                <li>The booking reference is incorrect</li>
            </ul>
            <div class="mt-3">
                <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
                <?php if (!$is_guest): ?>
                <a href="../booking/index.php" class="btn btn-success">Book New Appointment</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Success Message Actions -->
        <?php if (isset($success_message) && !empty($success_message)): ?>
        <div class="card mt-4 border-success">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-check-circle"></i> Cancellation Complete
                </h5>
            </div>
            <div class="card-body">
                <div class="text-center">
                    <div class="mb-4">
                        <i class="fas fa-calendar-times fa-5x text-success"></i>
                    </div>
                    <h4>Appointment Successfully Cancelled</h4>
                    <p class="lead">Your time slot has been released for other patients.</p>
                    
                    <div class="row mt-4">
                        <div class="col-md-4 mb-3">
                            <a href="index.php" class="btn btn-primary w-100">
                                <i class="fas fa-tachometer-alt"></i> Back to Dashboard
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="../booking/index.php" class="btn btn-success w-100">
                                <i class="fas fa-calendar-plus"></i> Book New Appointment
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="history.php" class="btn btn-info w-100">
                                <i class="fas fa-history"></i> View History
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    function selectReason(reasonKey) {
        // Remove selected class from all reason options
        document.querySelectorAll('.reason-option').forEach(div => {
            div.classList.remove('selected');
        });
        
        // Add selected class to clicked element
        const clickedElement = document.querySelector(`#reason_${reasonKey}`).parentElement.parentElement;
        clickedElement.classList.add('selected');
        
        // Update the radio button
        const radio = document.querySelector(`#reason_${reasonKey}`);
        radio.checked = true;
        
        // Show/hide custom reason box
        const customBox = document.getElementById('customReasonBox');
        if (reasonKey === 'other') {
            customBox.style.display = 'block';
            // Make custom reason required
            const textarea = customBox.querySelector('textarea[name="custom_reason"]');
            textarea.required = true;
        } else {
            customBox.style.display = 'none';
            // Remove required from custom reason
            const textarea = customBox.querySelector('textarea[name="custom_reason"]');
            textarea.required = false;
            textarea.value = '';
        }
        
        // Enable/disable cancel button based on reason selection
        updateCancelButton();
    }

    function updateCancelButton() {
        const cancelBtn = document.getElementById('cancelBtn');
        const selectedReason = document.querySelector('input[name="cancellation_reason"]:checked');
        const customReason = document.querySelector('textarea[name="custom_reason"]');
        
        // If "other" is selected, check if custom reason is filled
        if (selectedReason && selectedReason.value === 'other') {
            cancelBtn.disabled = !customReason || customReason.value.trim() === '';
        } else {
            cancelBtn.disabled = !selectedReason;
        }
    }

    // Form validation
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('cancellationForm');
        
        form.addEventListener('submit', function(event) {
            // Check if reason is selected
            const selectedReason = document.querySelector('input[name="cancellation_reason"]:checked');
            if (!selectedReason) {
                event.preventDefault();
                alert('Please select a cancellation reason.');
                return;
            }
            
            // If "other" is selected, check custom reason
            if (selectedReason.value === 'other') {
                const customReason = document.querySelector('textarea[name="custom_reason"]');
                if (!customReason || customReason.value.trim() === '') {
                    event.preventDefault();
                    alert('Please provide a reason for cancellation.');
                    customReason.focus();
                    return;
                }
            }
            
            // Final confirmation
            if (!confirm('Are you sure you want to cancel this appointment? This action cannot be undone.')) {
                event.preventDefault();
                return;
            }
        });
        
        // Listen for changes in custom reason textarea
        const customReasonTextarea = document.querySelector('textarea[name="custom_reason"]');
        if (customReasonTextarea) {
            customReasonTextarea.addEventListener('input', updateCancelButton);
        }
    });

    // Make function global
    window.selectReason = selectReason;
    </script>
</body>
</html>