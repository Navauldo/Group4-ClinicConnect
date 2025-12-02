<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session and check if staff is logged in
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'staff' && $_SESSION['user']['role'] !== 'admin')) {
    header('Location: ../login.php?role=staff');
    exit;
}

include '../includes/config.php';

// Get today's AND tomorrow's appointments (so we can test reschedule)
$stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_date >= CURDATE() AND status = 'booked' ORDER BY appointment_date, appointment_time");
$stmt->execute();
$appointments = $stmt->fetchAll();

// Get all appointments for the table
$stmt_all = $pdo->prepare("SELECT * FROM appointments ORDER BY appointment_date DESC, appointment_time DESC");
$stmt_all->execute();
$all_appointments = $stmt_all->fetchAll();

// Get unread patient messages count
$msg_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM patient_messages WHERE status = 'new'");
$msg_stmt->execute();
$unread_messages = $msg_stmt->fetch()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .dashboard-stat {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            text-align: center;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">← ClinicConnect Admin</a>
            <div class="navbar-nav ms-auto">
                <a href="patient_messages.php" class="nav-item nav-link text-light position-relative me-3">
                    <i class="fas fa-comments"></i>
                    <?php if ($unread_messages > 0): ?>
                        <span class="notification-badge"><?= $unread_messages ?></span>
                    <?php endif; ?>
                </a>
                <span class="nav-item nav-link text-light">
                    <i class="fas fa-user-shield"></i> Admin
                </span>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h2><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h2>
        
        <!-- Statistics Row -->
        <div class="row mb-4">
            <div class="col-md-3 col-6">
                <div class="dashboard-stat">
                    <div class="stat-number"><?= count($appointments) ?></div>
                    <div class="stat-label">Today's Appointments</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="dashboard-stat" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <div class="stat-number"><?= count($all_appointments) ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="dashboard-stat" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                    <div class="stat-number"><?= $unread_messages ?></div>
                    <div class="stat-label">New Messages</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="dashboard-stat" style="background: linear-gradient(135deg, #6f42c1, #563d7c);">
                    <div class="stat-number">24/7</div>
                    <div class="stat-label">Availability</div>
                </div>
            </div>
        </div>
        
        <!-- Today's Appointments Card -->
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
        
        <!-- Action Buttons -->
        <div class="mt-4">
            <div class="row">
                <div class="col-md-3 mb-2">
                    <a href="../booking/" class="btn btn-primary w-100">
                        <i class="fas fa-plus"></i> Add New Appointment
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="../index.php" class="btn btn-secondary w-100">
                        <i class="fas fa-home"></i> Back to Home
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="schedule.php" class="btn btn-outline-info w-100">
                        <i class="fas fa-calendar"></i> Manage Schedule
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="reminders.php" class="btn btn-outline-warning w-100">
                        <i class="fas fa-bell"></i> Send Reminders
                    </a>
                </div>
            </div>
            
            <div class="row mt-2">
                <div class="col-md-3 mb-2">
                    <a href="closures.php" class="btn btn-outline-danger w-100">
                        <i class="fas fa-times-circle"></i> Manage Closures
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="patient_management.php" class="btn btn-outline-info w-100">
                        <i class="fas fa-users"></i> Manage Patients
                    </a>
                </div>
                <div class="col-md-3 mb-2">
                    <a href="manage_status.php" class="btn btn-outline-success w-100">
                        <i class="fas fa-edit"></i> Manage Status
                    </a>
                </div>

                <div class="col-md-3 mb-2">
                    <a href="export.php" class="btn btn-outline-success w-100">
                        <i class="fas fa-edit"></i> Export Data
                    </a>
                </div>


                
                <div class="col-md-3 mb-2">
                    <a href="patient_messages.php" class="btn btn-outline-primary w-100 position-relative">
                        <i class="fas fa-comments"></i> Patient Messages
                        <?php if ($unread_messages > 0): ?>
                            <span class="badge bg-danger position-absolute top-0 start-100 translate-middle">
                                <?= $unread_messages ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>