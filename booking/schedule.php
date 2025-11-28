<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../includes/config.php';

// Generate proper time slots (9:00 AM to 4:30 PM only) - FIXED VERSION
$available_times = [];

// Morning slots: 9:00 AM to 11:30 AM
for ($hour = 9; $hour <= 11; $hour++) {
    // Add :00 slot (9:00, 10:00, 11:00)
    $available_times[] = [
        'value' => sprintf('%02d:00:00', $hour),
        'display' => date('g:i A', strtotime(sprintf('%02d:00:00', $hour)))
    ];
    
    // Add :30 slot (9:30, 10:30, 11:30)
    $available_times[] = [
        'value' => sprintf('%02d:30:00', $hour),
        'display' => date('g:i A', strtotime(sprintf('%02d:30:00', $hour)))
    ];
}

// Afternoon slots: 12:00 PM to 4:30 PM
for ($hour = 12; $hour <= 16; $hour++) {
    // Add :00 slot (12:00, 1:00, 2:00, 3:00, 4:00)
    $available_times[] = [
        'value' => sprintf('%02d:00:00', $hour),
        'display' => date('g:i A', strtotime(sprintf('%02d:00:00', $hour)))
    ];
    
    // Add :30 slot (12:30, 1:30, 2:30, 3:30) - but NOT 4:30 if hour is 16
    if ($hour < 16) {
        $available_times[] = [
            'value' => sprintf('%02d:30:00', $hour),
            'display' => date('g:i A', strtotime(sprintf('%02d:30:00', $hour)))
        ];
    }
}

// Add 4:30 PM specifically
$available_times[] = [
    'value' => '16:30:00',
    'display' => '4:30 PM'
];

$error_message = "";
$success_message = "";
$appointment = null;

// Check if booking reference is provided
if (isset($_GET['ref'])) {
    $booking_ref = $_GET['ref'];
    
    // Get appointment details
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE booking_reference = ? AND status = 'booked'");
    $stmt->execute([$booking_ref]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        $error_message = "<div class='alert alert-danger'>Appointment not found or cannot be rescheduled.</div>";
    }
} else {
    $error_message = "<div class='alert alert-danger'>No booking reference provided.</div>";
}

// Handle reschedule form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reschedule_appointment'])) {
    $new_date = $_POST['appointment_date'];
    $new_time = $_POST['appointment_time'];
    $booking_ref = $_POST['booking_reference'];
    
    // Check if clinic is closed on the new date
    $stmt = $pdo->prepare("SELECT COUNT(*) as is_closed FROM clinic_closures WHERE closure_date = ?");
    $stmt->execute([$new_date]);
    $closure_check = $stmt->fetch();
    
    if ($closure_check['is_closed'] > 0) {
        $error_message = "<div class='alert alert-danger'><strong>❌ Clinic Closed:</strong> The clinic is closed on $new_date. Please choose another date.</div>";
    } else {
        try {
            // Update the appointment
            $stmt = $pdo->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ? WHERE booking_reference = ?");
            $stmt->execute([$new_date, $new_time, $booking_ref]);
            
            $success_message = "<div class='alert alert-success'><strong>✅ Appointment Rescheduled!</strong><br>Your new appointment is on " . date('l, F j, Y', strtotime($new_date)) . " at " . date('g:i A', strtotime($new_time)) . "</div>";
            
            // Refresh appointment data
            $stmt = $pdo->prepare("SELECT * FROM appointments WHERE booking_reference = ?");
            $stmt->execute([$booking_ref]);
            $appointment = $stmt->fetch();
            
        } catch(PDOException $e) {
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
    <title>Reschedule Appointment - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">← Back to ClinicConnect</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Reschedule Appointment</h2>
        
        <?php echo $error_message; ?>
        <?php echo $success_message; ?>
        
        <?php if ($appointment): ?>
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5>Current Appointment Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Patient:</strong> <?= htmlspecialchars($appointment['patient_name']); ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($appointment['patient_phone']); ?></p>
                        <p><strong>Reference:</strong> <?= $appointment['booking_reference']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Current Date:</strong> <?= $appointment['appointment_date']; ?></p>
                        <p><strong>Current Time:</strong> <?= date('g:i A', strtotime($appointment['appointment_time'])); ?></p>
                        <p><strong>Reason:</strong> <?= htmlspecialchars($appointment['reason']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5>Select New Appointment Time</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="booking_reference" value="<?= $appointment['booking_reference']; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">New Date *</label>
                                <input type="text" 
                                       name="appointment_date" 
                                       class="form-control calendar-input" 
                                       required 
                                       id="appointmentDate"
                                       placeholder="Select date (Monday-Friday only)"
                                       readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">New Time *</label>
                                <select name="appointment_time" class="form-control" required>
                                    <option value="">Select time</option>
                                    <?php foreach ($available_times as $time): ?>
                                        <option value="<?= $time['value']; ?>"><?= $time['display']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Available: 9:00 AM - 4:30 PM</small>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="reschedule_appointment" value="1" class="btn btn-warning btn-lg">Reschedule Appointment</button>
                    <a href="../index.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!$appointment && !$error_message): ?>
        <div class="alert alert-warning">
            <h5>Need to Reschedule?</h5>
            <p>If you have a booking reference, please provide it to reschedule your appointment.</p>
            <form method="GET" class="mt-3">
                <div class="row">
                    <div class="col-md-8">
                        <input type="text" name="ref" class="form-control" placeholder="Enter your booking reference (e.g., CC202412011234)">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">Find Appointment</button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
    // Initialize Flatpickr with weekend disabling for reschedule page
    flatpickr("#appointmentDate", {
        minDate: "today",
        dateFormat: "Y-m-d",
        disable: [
            function(date) {
                // Disable weekends (Sunday = 0, Saturday = 6)
                return (date.getDay() === 0 || date.getDay() === 6);
            }
        ]
    });
    </script>
</body>
</html>