<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../includes/config.php';

// Get today's AND tomorrow's appointments (so we can test reschedule)
$stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_date >= CURDATE() AND status = 'booked' ORDER BY appointment_date, appointment_time");
$stmt->execute();
$appointments = $stmt->fetchAll();

// Get all appointments for the table
$stmt_all = $pdo->prepare("SELECT * FROM appointments ORDER BY appointment_date DESC, appointment_time DESC");
$stmt_all->execute();
$all_appointments = $stmt_all->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">← ClinicConnect Staff</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>Staff Dashboard</h2>
        
        <!-- Today's Appointments Card -->

        <!-- DEBUG: Total appointments today: <?= count($appointments); ?> -->
<?php foreach ($appointments as $index => $appt): ?>
<!-- DEBUG Appointment <?= $index; ?>: 
     ID: <?= $appt['id']; ?>
     Status: <?= $appt['status']; ?>
     Reference: <?= $appt['booking_reference']; ?>
-->
<?php endforeach; ?>


        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                <h5>📅 Today's Appointments (<?= date('F j, Y'); ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (count($appointments) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Patient Name</th>
                                    <th>Contact</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appt): ?>
                                <tr>
                                    <td><?= date('g:i A', strtotime($appt['appointment_time'])); ?></td>
                                    <td><?= htmlspecialchars($appt['patient_name']); ?></td>
                                    <td>
                                        📞 <?= htmlspecialchars($appt['patient_phone']); ?><br>
                                        📧 <?= htmlspecialchars($appt['patient_email']); ?>
                                    </td>
                                    <td><?= htmlspecialchars($appt['reason']); ?></td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $appt['status'] == 'booked' ? 'primary' : 
                                            ($appt['status'] == 'cancelled' ? 'danger' : 'success')
                                        ?>">
                                            <?= ucfirst($appt['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($appt['status'] == 'booked'): ?>
                                            <a href="cancel.php?id=<?= $appt['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancel this appointment?')">Cancel</a>
                                            <a href="../booking/index.php?reschedule=<?= $appt['booking_reference']; ?>" class="btn btn-sm btn-outline-warning">Reschedule</a>
                                        <?php else: ?>
                                            <span class="text-muted">No actions</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No appointments scheduled for today.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- All Appointments Table -->
        <div class="card mt-4">
            <div class="card-header">
                <h5>All Appointments</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_appointments as $appt): ?>
                            <tr>
                                <td><?= $appt['appointment_date']; ?></td>
                                <td><?= date('g:i A', strtotime($appt['appointment_time'])); ?></td>
                                <td><?= htmlspecialchars($appt['patient_name']); ?></td>
                                <td><?= htmlspecialchars($appt['patient_phone']); ?></td>
                                <td>
                                    <span class="badge bg-<?= 
                                        $appt['status'] == 'booked' ? 'primary' : 
                                        ($appt['status'] == 'cancelled' ? 'danger' : 'success')
                                    ?>">
                                        <?= ucfirst($appt['status']); ?>
                                    </span>
                                </td>
                                <td><small><?= $appt['booking_reference']; ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="mt-3">
            <a href="../booking/" class="btn btn-primary">➕ Add New Appointment</a>
            <a href="../index.php" class="btn btn-secondary">← Back to Home</a>
            <a href="schedule.php" class="btn btn-outline-info"> Manage Schedule</a>
            <a href="reminders.php" class="btn btn-outline-warning">📧 Send Reminders</a>
            <a href="closures.php" class="btn btn-outline-danger"> Manage Closures</a>
                        <!-- TEST: Can you see this comment? -->
                        
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>