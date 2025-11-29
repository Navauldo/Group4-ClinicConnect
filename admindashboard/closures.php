<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../includes/config.php';

$success_message = "";
$error_message = "";

// Handle closure creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_closure'])) {
    $closure_date = $_POST['closure_date'];
    $reason = $_POST['reason'];
    
    try {
        // Check if there are existing appointments on this date
        $stmt = $pdo->prepare("SELECT COUNT(*) as appointment_count FROM appointments WHERE appointment_date = ? AND status = 'booked'");
        $stmt->execute([$closure_date]);
        $result = $stmt->fetch();
        
        if ($result['appointment_count'] > 0) {
            $error_message = "<div class='alert alert-danger'><strong>❌ Cannot Close Clinic:</strong> There are {$result['appointment_count']} booked appointments on this date. Please reschedule them first.</div>";
        } else {
            // Add closure
            $stmt = $pdo->prepare("INSERT INTO clinic_closures (closure_date, reason, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$closure_date, $reason]);
            
            $success_message = "<div class='alert alert-success'><strong>✅ Clinic Closed:</strong> $closure_date has been marked as closed.</div>";
        }
    } catch(PDOException $e) {
        $error_message = "<div class='alert alert-danger'><strong>❌ Error:</strong> " . $e->getMessage() . "</div>";
    }
}

// Handle closure deletion
if (isset($_GET['delete_closure'])) {
    $closure_id = $_GET['delete_closure'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM clinic_closures WHERE id = ?");
        $stmt->execute([$closure_id]);
        
        $success_message = "<div class='alert alert-success'><strong>✅ Closure Removed:</strong> The clinic date has been reopened.</div>";
    } catch(PDOException $e) {
        $error_message = "<div class='alert alert-danger'><strong>❌ Error:</strong> " . $e->getMessage() . "</div>";
    }
}

// Get upcoming closures
$stmt = $pdo->prepare("SELECT * FROM clinic_closures WHERE closure_date >= CURDATE() ORDER BY closure_date");
$stmt->execute();
$upcoming_closures = $stmt->fetchAll();

// Get appointments that need rescheduling (conflicts with closures)
$stmt = $pdo->prepare("
    SELECT a.*, c.closure_date 
    FROM appointments a 
    INNER JOIN clinic_closures c ON a.appointment_date = c.closure_date 
    WHERE a.status = 'booked'
    ORDER BY a.appointment_date
");
$stmt->execute();
$conflicting_appointments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Closures - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .closure-card {
            border-left: 4px solid #dc3545;
            transition: all 0.3s;
        }
        .closure-card:hover {
            background-color: #f8f9fa;
        }
        .conflict-card {
            border-left: 4px solid #ffc107;
            background: #fff9e6;
        }
        .flatpickr-calendar {
            background: white !important;
            border: 2px solid #007bff !important;
            border-radius: 10px !important;
        }
        .flatpickr-day.selected {
            background: #007bff !important;
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
        <h2>🚫 Clinic Closure Management</h2>
        
        <?php echo $error_message; ?>
        <?php echo $success_message; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5>Add New Closure</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Closure Date *</label>
                                <input type="text" name="closure_date" class="form-control" required placeholder="Select date" readonly id="closureDate">
                                <small class="text-muted">Select a weekday (Monday-Friday)</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reason for Closure *</label>
                                <textarea name="reason" class="form-control" rows="3" placeholder="e.g., Public holiday, Staff training, Emergency closure..." required></textarea>
                            </div>
                            <button type="submit" name="add_closure" class="btn btn-danger">Add Closure</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Upcoming Closures</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($upcoming_closures) > 0): ?>
                            <?php foreach ($upcoming_closures as $closure): ?>
                            <div class="card closure-card mb-2">
                                <div class="card-body py-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?= $closure['closure_date']; ?></strong>
                                            <br><small><?= htmlspecialchars($closure['reason']); ?></small>
                                        </div>
                                        <a href="?delete_closure=<?= $closure['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this closure?')">Remove</a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No upcoming closures scheduled.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Conflicting Appointments Section -->
        <?php if (count($conflicting_appointments) > 0): ?>
        <div class="card mt-4">
            <div class="card-header bg-warning">
                <h5>⚠️ Appointments Needing Rescheduling</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">The following appointments conflict with closure dates and need to be rescheduled:</p>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Phone</th>
                                <th>Closure Reason</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($conflicting_appointments as $appt): ?>
                            <tr>
                                <td><?= $appt['appointment_date']; ?></td>
                                <td><?= date('g:i A', strtotime($appt['appointment_time'])); ?></td>
                                <td><?= htmlspecialchars($appt['patient_name']); ?></td>
                                <td><?= htmlspecialchars($appt['patient_phone']); ?></td>
                                <td><small><?= htmlspecialchars($appt['reason']); ?></small></td>
                                <td>
                                    <a href="../booking/index.php?reschedule=<?= $appt['booking_reference']; ?>" class="btn btn-sm btn-warning">Reschedule</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="mt-3">
            <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
    // Initialize closure calendar (weekdays only)
    flatpickr("#closureDate", {
        minDate: "today",
        dateFormat: "Y-m-d",
        disable: [
            function(date) {
                // Disable weekends
                return (date.getDay() === 0 || date.getDay() === 6);
            }
        ]
    });
    </script>
</body>
</html>