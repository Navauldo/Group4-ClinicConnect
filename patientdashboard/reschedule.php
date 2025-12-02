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

$success_message = "";
$error_message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reschedule_appointment'])) {
    $new_date = $_POST['appointment_date'];
    $new_time = $_POST['appointment_time'];
    
    // Check if clinic is closed on the new date
    $stmt = $pdo->prepare("SELECT COUNT(*) as is_closed FROM clinic_closures WHERE closure_date = ?");
    $stmt->execute([$new_date]);
    $closure_check = $stmt->fetch();
    
    if ($closure_check['is_closed'] > 0) {
        $error_message = "<div class='alert alert-danger'><strong>❌ Clinic Closed:</strong> The clinic is closed on $new_date. Please choose another date.</div>";
    } else {
        // Check if new time slot is available
        $stmt = $pdo->prepare("SELECT COUNT(*) as conflict FROM appointments WHERE appointment_date = ? AND appointment_time = ? AND status = 'booked' AND booking_reference != ?");
        $stmt->execute([$new_date, $new_time, $booking_ref]);
        $conflict_check = $stmt->fetch();
        
        if ($conflict_check['conflict'] > 0) {
            $error_message = "<div class='alert alert-danger'><strong>❌ Time Slot Taken:</strong> The selected time slot is no longer available. Please choose another time.</div>";
        } else {
            try {
                // Update the appointment
                $stmt = $pdo->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ? WHERE booking_reference = ?");
                $stmt->execute([$new_date, $new_time, $booking_ref]);
                
                // Log the rescheduling
                $stmt = $pdo->prepare("INSERT INTO appointment_logs (appointment_id, action, details, performed_by) VALUES (?, 'rescheduled', ?, ?)");
                $stmt->execute([$appointment['id'], "Rescheduled from {$appointment['appointment_date']} {$appointment['appointment_time']} to $new_date $new_time", 'patient']);
                
                $success_message = "<div class='alert alert-success'><strong>✅ Appointment Rescheduled Successfully!</strong><br>" .
                                 "<strong>New Date:</strong> " . date('l, F j, Y', strtotime($new_date)) . "<br>" .
                                 "<strong>New Time:</strong> " . date('g:i A', strtotime($new_time)) . "<br>" .
                                 "<strong>Booking Reference:</strong> $booking_ref</div>";
                
                // Refresh appointment data
                $stmt = $pdo->prepare("SELECT * FROM appointments WHERE booking_reference = ?");
                $stmt->execute([$booking_ref]);
                $appointment = $stmt->fetch();
                
            } catch(PDOException $e) {
                $error_message = "<div class='alert alert-danger'><strong>❌ Error:</strong> " . $e->getMessage() . "</div>";
            }
        }
    }
}

// Generate time slots (9:00 AM to 4:30 PM)
$available_times = [];
for ($hour = 9; $hour <= 16; $hour++) {
    if ($hour < 12) {
        $display_hour = $hour;
        $period = 'AM';
    } elseif ($hour == 12) {
        $display_hour = 12;
        $period = 'PM';
    } else {
        $display_hour = $hour - 12;
        $period = 'PM';
    }
    
    // Add :00 slot
    $available_times[] = [
        'value' => sprintf('%02d:00:00', $hour),
        'display' => sprintf('%d:00 %s', $display_hour, $period)
    ];
    
    // Add :30 slot (except for 4:30 PM which we'll add separately)
    if ($hour < 16) {
        $available_times[] = [
            'value' => sprintf('%02d:30:00', $hour),
            'display' => sprintf('%d:30 %s', $display_hour, $period)
        ];
    }
}

// Add 4:30 PM specifically
$available_times[] = [
    'value' => '16:30:00',
    'display' => '4:30 PM'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reschedule Appointment - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .flatpickr-calendar {
            background: white !important;
            border: 2px solid #007bff !important;
            border-radius: 10px !important;
            box-shadow: 0 5px 15px rgba(0,123,255,0.2) !important;
        }
        .flatpickr-day {
            color: #007bff !important;
            font-weight: 500 !important;
        }
        .flatpickr-day.selected {
            background: #007bff !important;
            color: white !important;
        }
        .flatpickr-day.flatpickr-disabled {
            color: #ccc !important;
            text-decoration: line-through !important;
        }
        .calendar-input {
            background: white !important;
            border: 2px solid #007bff !important;
            color: #007bff !important;
        }
        .current-appointment-card {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
        }
        .time-slot-option {
            padding: 10px;
            margin: 5px 0;
            border: 2px solid #dee2e6;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .time-slot-option:hover {
            background: #e9ecef;
            border-color: #007bff;
        }
        .time-slot-option.selected {
            background: #007bff !important;
            color: white !important;
            border-color: #0056b3;
        }
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
        <h2><i class="fas fa-calendar-alt"></i> Reschedule Appointment</h2>
        
        <?php echo $error_message; ?>
        <?php echo $success_message; ?>
        
        <?php if ($appointment): ?>
        <!-- Current Appointment Details -->
        <div class="card current-appointment-card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-day"></i> Current Appointment Details
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
                        <p><strong>Current Date:</strong> <?= date('l, F j, Y', strtotime($appointment['appointment_date'])) ?></p>
                        <p><strong>Current Time:</strong> <?= date('g:i A', strtotime($appointment['appointment_time'])) ?></p>
                        <p><strong>Reason:</strong> <?= htmlspecialchars($appointment['reason']) ?></p>
                        <p><strong>Booking Reference:</strong> <span class="badge bg-primary"><?= $appointment['booking_reference'] ?></span></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reschedule Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-calendar-plus"></i> Select New Appointment Time
                    <small class="text-muted float-end">Clinic hours: 9:00 AM - 4:30 PM, Monday to Friday</small>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="rescheduleForm">
                    <input type="hidden" name="booking_reference" value="<?= $booking_ref ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><strong>New Date *</strong></label>
                                <input type="text" 
                                       name="appointment_date" 
                                       class="form-control calendar-input" 
                                       required 
                                       id="appointmentDate"
                                       placeholder="Select date (Monday-Friday only)"
                                       readonly
                                       value="<?= $_POST['appointment_date'] ?? '' ?>">
                                <small class="text-muted">Weekends and holidays are automatically disabled</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label"><strong>Clinic Schedule</strong></label>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> 
                                    <strong>Clinic Hours:</strong> Monday to Friday, 9:00 AM - 4:30 PM<br>
                                    <strong>Time Slots:</strong> Available every 30 minutes
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label"><strong>New Time *</strong></label>
                                <div id="timeSlotsContainer">
                                    <?php foreach ($available_times as $time): 
                                        $is_selected = ($_POST['appointment_time'] ?? '') === $time['value'];
                                    ?>
                                    <div class="form-check time-slot-option <?= $is_selected ? 'selected' : '' ?>" 
                                         onclick="selectTimeSlot(this, '<?= $time['value'] ?>')">
                                        <input class="form-check-input" type="radio" 
                                               name="appointment_time" 
                                               value="<?= $time['value'] ?>" 
                                               id="time_<?= str_replace(':', '', $time['value']) ?>"
                                               <?= $is_selected ? 'checked' : '' ?>
                                               required>
                                        <label class="form-check-label w-100" for="time_<?= str_replace(':', '', $time['value']) ?>">
                                            <?= $time['display'] ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" id="selectedTimeInput" name="appointment_time" value="<?= $_POST['appointment_time'] ?? '' ?>" required>
                                <div class="invalid-feedback">Please select a time slot.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" name="reschedule_appointment" value="1" class="btn btn-warning btn-lg">
                            <i class="fas fa-calendar-check"></i> Confirm Reschedule
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <?php if ($is_guest): ?>
                        <a href="../booking/schedule.php?ref=<?= $booking_ref ?>" class="btn btn-outline-primary">
                            <i class="fas fa-external-link-alt"></i> Use Classic Rescheduler
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Note:</strong> Rescheduling is only available for future appointments. 
                        If you need to reschedule an appointment that is within 24 hours, please call the clinic directly.
                    </div>
                </form>
            </div>
        </div>
        <?php else: ?>
        <!-- No appointment found -->
        <div class="alert alert-danger">
            <h4><i class="fas fa-exclamation-triangle"></i> Appointment Not Found</h4>
            <p>We couldn't find the appointment you're trying to reschedule. This could be because:</p>
            <ul>
                <li>The appointment has already been cancelled</li>
                <li>The appointment reference is incorrect</li>
                <li>The appointment is too close to the scheduled time</li>
            </ul>
            <div class="mt-3">
                <a href="index.php" class="btn btn-primary">Back to Dashboard</a>
                <a href="../booking/index.php" class="btn btn-success">Book New Appointment</a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
    // Initialize Flatpickr with weekend disabling
    const datePicker = flatpickr("#appointmentDate", {
        minDate: "today",
        dateFormat: "Y-m-d",
        disable: [
            function(date) {
                // Disable weekends (Sunday = 0, Saturday = 6)
                return (date.getDay() === 0 || date.getDay() === 6);
            }
        ],
        locale: {
            firstDayOfWeek: 1 // Monday
        },
        onChange: function(selectedDates, dateStr) {
            // Clear selected time when date changes
            document.querySelectorAll('input[name="appointment_time"]').forEach(radio => {
                radio.checked = false;
            });
            document.querySelectorAll('.time-slot-option').forEach(div => {
                div.classList.remove('selected');
            });
            document.getElementById('selectedTimeInput').value = '';
        }
    });

    function selectTimeSlot(element, timeValue) {
        // Remove selected class from all time slots
        document.querySelectorAll('.time-slot-option').forEach(div => {
            div.classList.remove('selected');
        });
        
        // Add selected class to clicked element
        element.classList.add('selected');
        
        // Update the radio button
        const radio = element.querySelector('input[type="radio"]');
        radio.checked = true;
        
        // Update hidden input
        document.getElementById('selectedTimeInput').value = timeValue;
        
        // Trigger change event for validation
        radio.dispatchEvent(new Event('change'));
    }

    // Form validation
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('rescheduleForm');
        
        form.addEventListener('submit', function(event) {
            // Check if date is selected
            const dateInput = document.getElementById('appointmentDate');
            if (!dateInput.value) {
                event.preventDefault();
                alert('Please select a new date for your appointment.');
                dateInput.focus();
                return;
            }
            
            // Check if time is selected
            const timeSelected = document.querySelector('input[name="appointment_time"]:checked');
            if (!timeSelected) {
                event.preventDefault();
                alert('Please select a new time for your appointment.');
                return;
            }
            
            // Confirm rescheduling
            if (!confirm('Are you sure you want to reschedule this appointment?')) {
                event.preventDefault();
                return;
            }
        });
    });

    // Auto-select first time slot if only one is available
    function autoSelectTimeSlot() {
        const timeSlots = document.querySelectorAll('input[name="appointment_time"]');
        if (timeSlots.length === 1) {
            timeSlots[0].checked = true;
            timeSlots[0].parentElement.classList.add('selected');
            document.getElementById('selectedTimeInput').value = timeSlots[0].value;
        }
    }

    // Make function global
    window.selectTimeSlot = selectTimeSlot;
    window.autoSelectTimeSlot = autoSelectTimeSlot;
    </script>
</body>
</html>