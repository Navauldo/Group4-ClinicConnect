<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../includes/config.php';

// Get clinic schedule
$stmt = $pdo->prepare("SELECT * FROM clinic_schedules WHERE clinic_id = 1 ORDER BY day_of_week");
$stmt->execute();
$clinic_schedule = $stmt->fetchAll();

// Create a schedule map for easy access
$schedule_map = [];
foreach ($clinic_schedule as $day) {
    $schedule_map[$day['day_of_week']] = $day;
}

// Check if rescheduling
$is_rescheduling = false;
$current_appointment = null;

if (isset($_GET['reschedule'])) {
    $is_rescheduling = true;
    $booking_ref = $_GET['reschedule'];
    
    // Get current appointment details
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE booking_reference = ? AND status = 'booked'");
    $stmt->execute([$booking_ref]);
    $current_appointment = $stmt->fetch();
    
    if (!$current_appointment) {
        $is_rescheduling = false;
        $success_message = "<div class='alert alert-danger'>Appointment not found or cannot be rescheduled.</div>";
    }
}

$success_message = "";
$form_submitted = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_appointment'])) {
    $patient_name = trim($_POST['patient_name']);
    $patient_email = trim($_POST['patient_email']);
    $patient_phone = trim($_POST['patient_phone']);
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $reason = trim($_POST['reason']);

    // Validation flags
    $is_valid = true;
    $validation_errors = [];

    // Validate email format if provided
    if (!empty($patient_email) && !filter_var($patient_email, FILTER_VALIDATE_EMAIL)) {
        $is_valid = false;
        $validation_errors[] = "Please enter a valid email address";
    }

    // Validate phone number (numbers only, 7-15 digits)
    $clean_phone = preg_replace('/[^0-9]/', '', $patient_phone);
    if (strlen($clean_phone) < 7 || strlen($clean_phone) > 15) {
        $is_valid = false;
        $validation_errors[] = "Please enter a valid phone number (7-15 digits)";
    }

    if (!$is_valid) {
        $success_message = "<div class='alert alert-danger'><strong>❌ Validation Error:</strong><br>" . implode('<br>', $validation_errors) . "</div>";
    } else {
        // Check if clinic is closed on this date
        $stmt = $pdo->prepare("SELECT COUNT(*) as is_closed FROM clinic_closures WHERE closure_date = ?");
        $stmt->execute([$appointment_date]);
        $closure_check = $stmt->fetch();
        
        if ($closure_check['is_closed'] > 0) {
            $success_message = "<div class='alert alert-danger'><strong>❌ Clinic Closed:</strong> The clinic is closed on $appointment_date. Please choose another date.</div>";
        } else {
            // Validate date is weekday and clinic is open
            $day_of_week = date('w', strtotime($appointment_date));
            $clinic_hours = $schedule_map[$day_of_week] ?? null;
            
            // BLOCK WEEKENDS - FIXED: Using proper day numbers (Sunday=0, Monday=1, Saturday=6)
            if ($day_of_week == 0 || $day_of_week == 6) {
                $success_message = "<div class='alert alert-danger'><strong>❌ Error:</strong> Clinic is closed on weekends</div>";
            } elseif (!$clinic_hours || !$clinic_hours['is_active']) {
                $day_name = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$day_of_week];
                $success_message = "<div class='alert alert-danger'><strong>❌ Error:</strong> Clinic is closed on $day_name (holiday/special closure)</div>";
            } else {
                // Validate time is between 9:00 AM and 4:30 PM
                $appointment_timestamp = strtotime($appointment_time);
                $min_time = strtotime('09:00:00');
                $max_time = strtotime('16:30:00');
                
                if ($appointment_timestamp < $min_time || $appointment_timestamp > $max_time) {
                    $success_message = "<div class='alert alert-danger'><strong>❌ Error:</strong> Selected time must be between 9:00 AM and 4:30 PM</div>";
                } else {
                    try {
                        if ($is_rescheduling && isset($_POST['booking_reference'])) {
                            // Reschedule existing appointment
                            $booking_ref = $_POST['booking_reference'];
                            
                            $stmt = $pdo->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, reason = ? WHERE booking_reference = ?");
                            $stmt->execute([$appointment_date, $appointment_time, $reason, $booking_ref]);
                            
                            $success_message = "<div class='alert alert-success'><strong>✅ Appointment Rescheduled!</strong><br>Your appointment has been moved to " . 
                                              date('l, F j, Y', strtotime($appointment_date)) . " at " . date('g:i A', strtotime($appointment_time)) . 
                                              "<br>Booking reference: <strong>$booking_ref</strong></div>";
                            
                        } else {
                            // New booking
                            $booking_ref = 'CC' . date('Ymd') . rand(1000, 9999);
                            
                            $stmt = $pdo->prepare("INSERT INTO appointments (patient_name, patient_email, patient_phone, appointment_date, appointment_time, reason, booking_reference) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$patient_name, $patient_email, $patient_phone, $appointment_date, $appointment_time, $reason, $booking_ref]);
                            
                            $success_message = "<div class='alert alert-success'><strong>✅ Appointment Booked!</strong><br>Your appointment is scheduled for " . 
                                              date('l, F j, Y', strtotime($appointment_date)) . " at " . date('g:i A', strtotime($appointment_time)) . 
                                              "<br>Booking reference: <strong>$booking_ref</strong></div>";
                            
                            // Set flag to clear form (only for new bookings, not rescheduling)
                            $form_submitted = true;
                        }
                    } catch(PDOException $e) {
                        $success_message = "<div class='alert alert-danger'><strong>❌ Error:</strong> " . $e->getMessage() . "</div>";
                    }
                }
            }
        }
    }
}

// Get submitted values to preserve form data (only if form wasn't successfully submitted)
$submitted_name = $form_submitted ? '' : ($_POST['patient_name'] ?? '');
$submitted_email = $form_submitted ? '' : ($_POST['patient_email'] ?? '');
$submitted_phone = $form_submitted ? '' : ($_POST['patient_phone'] ?? '');
$submitted_date = $form_submitted ? '' : ($_POST['appointment_date'] ?? '');
$submitted_time = $form_submitted ? '' : ($_POST['appointment_time'] ?? '');
$submitted_reason = $form_submitted ? '' : ($_POST['reason'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        /* MODERN WHITE AND BLUE CALENDAR */
        .flatpickr-calendar {
            background: white !important;
            border: 2px solid #007bff !important;
            border-radius: 10px !important;
            box-shadow: 0 5px 15px rgba(0,123,255,0.2) !important;
        }
        .flatpickr-day {
            color: #007bff !important;
            font-weight: 500 !important;
            border-radius: 6px !important;
            margin: 2px !important;
        }
        .flatpickr-day:hover {
            background: #007bff !important;
            color: white !important;
            border-color: #007bff !important;
        }
        .flatpickr-day.selected {
            background: #007bff !important;
            color: white !important;
            border-color: #007bff !important;
        }
        .flatpickr-day.flatpickr-disabled {
            color: #ccc !important;
            text-decoration: line-through !important;
            background: #f8f9fa !important;
        }
        .flatpickr-weekday {
            color: #007bff !important;
            font-weight: bold !important;
        }
        .flatpickr-current-month {
            color: #007bff !important;
        }
        .flatpickr-months .flatpickr-month {
            color: #007bff !important;
            fill: #007bff !important;
        }
        
        /* TIME SLOTS STYLING */
        .time-slot {
            background: white;
            border: 2px solid #007bff;
            border-radius: 8px;
            padding: 12px 8px;
            margin: 4px;
            cursor: pointer;
            transition: all 0.3s;
            color: #007bff;
            font-weight: bold;
            text-align: center;
            flex: 1;
            min-width: 100px;
        }
        .time-slot:hover {
            background: #007bff;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,123,255,0.3);
        }
        .time-slot.selected {
            background: #007bff;
            color: white;
            border-color: #0056b3;
            transform: translateY(-2px);
        }
        .time-slot.disabled {
            background: #f8f9fa;
            color: #6c757d;
            border-color: #dee2e6;
            cursor: not-allowed;
        }

        /* STATUS MESSAGES */
        .day-status {
            font-size: 0.9rem;
            margin-top: 8px;
            padding: 10px;
            border-radius: 6px;
            border-left: 4px solid;
        }
        .day-open {
            background: #e8f5e8;
            color: #155724;
            border-left-color: #28a745;
        }
        .day-closed {
            background: #fde8e8;
            color: #721c24;
            border-left-color: #dc3545;
        }

        .time-slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
            gap: 8px;
            margin-top: 10px;
        }
        
        .calendar-input {
            background: white !important;
            border: 2px solid #007bff !important;
            color: #007bff !important;
            font-weight: 500 !important;
        }
        .calendar-input:focus {
            border-color: #0056b3 !important;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
        }

        /* Validation styles */
        .is-invalid {
            border-color: #dc3545 !important;
        }
        .invalid-feedback {
            display: none;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875em;
            color: #dc3545;
        }
        .was-validated .form-control:invalid ~ .invalid-feedback {
            display: block;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">← Back to ClinicConnect</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Book New Appointment</h2>
        
        <?php echo $success_message; ?>
        
        <form method="POST" class="mt-4 needs-validation" id="bookingForm" novalidate>
            <?php if ($is_rescheduling && $current_appointment): ?>
            <div class="alert alert-info">
                <strong>Rescheduling Appointment:</strong> Currently booked for 
                <?= date('l, F j, Y', strtotime($current_appointment['appointment_date'])); ?> at 
                <?= date('g:i A', strtotime($current_appointment['appointment_time'])); ?>
            </div>
            <input type="hidden" name="booking_reference" value="<?= $current_appointment['booking_reference']; ?>">
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Patient Name *</label>
                        <input type="text" name="patient_name" class="form-control" 
                            value="<?= $is_rescheduling ? htmlspecialchars($current_appointment['patient_name']) : $submitted_name; ?>" 
                            required <?= $is_rescheduling ? 'readonly' : ''; ?>>
                        <div class="invalid-feedback">
                            Please provide patient name.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="patient_email" class="form-control"
                            value="<?= $is_rescheduling ? htmlspecialchars($current_appointment['patient_email']) : $submitted_email; ?>"
                            <?= $is_rescheduling ? 'readonly' : ''; ?>
                            pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$">
                        <div class="invalid-feedback">
                            Please provide a valid email address.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone *</label>
                        <input type="tel" name="patient_phone" class="form-control"
                            value="<?= $is_rescheduling ? htmlspecialchars($current_appointment['patient_phone']) : $submitted_phone; ?>"
                            required <?= $is_rescheduling ? 'readonly' : ''; ?>
                            pattern="[0-9+\-\s()]{7,15}">
                        <div class="invalid-feedback">
                            Please provide a valid phone number (7-15 digits).
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Appointment Date *</label>
                        <input type="text" 
                               name="appointment_date" 
                               class="form-control calendar-input" 
                               required 
                               value="<?= $submitted_date ?>"
                               id="appointmentDate"
                               placeholder="Select a date (Weekdays only - Monday to Friday)"
                               readonly>
                        <div class="invalid-feedback">
                            Please select an appointment date.
                        </div>
                        <div id="dateStatus" class="day-status"></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Preferred Time *</label>
                        <div id="timeSlotsContainer">
                            <div class="alert alert-info">
                                <small>📅 Please select a date first to see available times (Monday-Friday only)</small>
                            </div>
                        </div>
                        <input type="hidden" name="appointment_time" id="selectedTime" value="<?= $submitted_time ?>" required>
                        <div class="invalid-feedback">
                            Please select an appointment time.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason for Visit</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Briefly describe why you're visiting..."><?= $is_rescheduling ? htmlspecialchars($current_appointment['reason']) : $submitted_reason; ?></textarea>
                    </div>
                </div>
            </div>
            
            <button type="submit" name="book_appointment" value="1" class="btn btn-primary btn-lg" id="submitBtn">
                <?= $is_rescheduling ? 'Reschedule Appointment' : 'Book Appointment'; ?>
            </button>
            <a href="../index.php" class="btn btn-secondary">Cancel</a>
        </form>
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
            firstDayOfWeek: 0
        },
        onChange: function(selectedDates, dateStr, instance) {
            validateSelectedDate();
        }
    });

    function validateSelectedDate() {
        const dateInput = document.getElementById('appointmentDate');
        const dateStatus = document.getElementById('dateStatus');
        const submitBtn = document.getElementById('submitBtn');
        const selectedDate = dateInput.value;
        
        if (!selectedDate) {
            dateStatus.innerHTML = '';
            submitBtn.disabled = false;
            updateTimeSlots();
            return;
        }
        
        // Use UTC methods to avoid timezone issues
        const dateObj = new Date(selectedDate + 'T00:00:00Z');
        const dayOfWeek = dateObj.getUTCDay();
        const dayName = dateObj.toLocaleDateString('en-US', { 
            weekday: 'long',
            timeZone: 'UTC'
        });
        
        // Block weekends
        if (dayOfWeek === 0 || dayOfWeek === 6) {
            dateStatus.innerHTML = `<div class="day-closed">❌ Clinic closed on ${dayName}. Please select a weekday (Monday-Friday).</div>`;
            submitBtn.disabled = true;
            document.getElementById('timeSlotsContainer').innerHTML = `
                <div class="alert alert-danger">
                    <small>❌ ${dayName} appointments are not available. Please select a weekday.</small>
                </div>
            `;
            document.getElementById('selectedTime').value = '';
            return;
        }
        
        dateStatus.innerHTML = `<div class="day-open">✅ ${dayName}: 9:00 AM - 4:30 PM</div>`;
        submitBtn.disabled = false;
        updateTimeSlots();
    }

    function updateTimeSlots() {
        const dateInput = document.getElementById('appointmentDate');
        const timeContainer = document.getElementById('timeSlotsContainer');
        const selectedTimeInput = document.getElementById('selectedTime');
        const selectedDate = dateInput.value;
        
        if (!selectedDate) {
            timeContainer.innerHTML = `
                <div class="alert alert-info">
                    <small>📅 Please select a date first to see available times (Monday-Friday only)</small>
                </div>
            `;
            selectedTimeInput.value = '';
            return;
        }
        
        // Generate time slots from 9:00 AM to 4:30 PM
        const timeSlots = [];
        
        // Morning slots: 9:00 AM to 11:30 AM
        for (let hour = 9; hour <= 11; hour++) {
            timeSlots.push({
                value: `${hour.toString().padStart(2, '0')}:00:00`,
                display: formatDisplayTime(hour, 0)
            });
            
            timeSlots.push({
                value: `${hour.toString().padStart(2, '0')}:30:00`,
                display: formatDisplayTime(hour, 30)
            });
        }
        
        // Afternoon slots: 12:00 PM to 4:30 PM
        for (let hour = 12; hour <= 16; hour++) {
            timeSlots.push({
                value: `${hour.toString().padStart(2, '0')}:00:00`,
                display: formatDisplayTime(hour, 0)
            });
            
            if (hour < 16) {
                timeSlots.push({
                    value: `${hour.toString().padStart(2, '0')}:30:00`,
                    display: formatDisplayTime(hour, 30)
                });
            }
        }
        
        // Add 4:30 PM specifically
        timeSlots.push({
            value: `16:30:00`,
            display: formatDisplayTime(16, 30)
        });
        
        // Create time slot buttons
        let timeSlotsHTML = `
            <div class="mb-2">
                <strong>Available Times (9:00 AM - 4:30 PM):</strong>
            </div>
            <div class="time-slots-grid">
        `;
        
        timeSlots.forEach(slot => {
            const isSelected = selectedTimeInput.value === slot.value;
            timeSlotsHTML += `
                <div class="time-slot ${isSelected ? 'selected' : ''}" 
                     onclick="selectTimeSlot(this, '${slot.value}')">
                    ${slot.display}
                </div>
            `;
        });
        
        timeSlotsHTML += `</div>`;
        timeContainer.innerHTML = timeSlotsHTML;
        
        // Auto-select first time slot if none selected
        if (!selectedTimeInput.value && timeSlots.length > 0) {
            selectTimeSlot(document.querySelector('.time-slot'), timeSlots[0].value);
        }
    }

    function selectTimeSlot(element, timeValue) {
        // Remove selected class from all time slots
        document.querySelectorAll('.time-slot').forEach(slot => {
            slot.classList.remove('selected');
        });
        
        // Add selected class to clicked slot
        if (element) {
            element.classList.add('selected');
        }
        
        // Update hidden input
        document.getElementById('selectedTime').value = timeValue;
    }

    function formatDisplayTime(hour, minute) {
        let displayHour = hour;
        let period = 'AM';
        
        if (hour === 12) {
            period = 'PM';
        } else if (hour > 12) {
            displayHour = hour - 12;
            period = 'PM';
        }
        
        const minuteStr = minute.toString().padStart(2, '0');
        return `${displayHour}:${minuteStr} ${period}`;
    }

    // Form validation
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('bookingForm');
        
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);

        validateSelectedDate();
    });

    // Real-time phone number validation
    document.querySelector('input[name="patient_phone"]').addEventListener('input', function(e) {
        // Remove any non-digit characters except +, -, (, ), and spaces
        this.value = this.value.replace(/[^0-9+\-\s()]/g, '');
    });

    // Make functions global for onclick events
    window.selectTimeSlot = selectTimeSlot;
    </script>
</body>
</html>