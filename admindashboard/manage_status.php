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

$user = $_SESSION['user'];
$success_message = "";
$error_message = "";

// Get all appointments for status management
$stmt = $pdo->prepare("SELECT * FROM appointments ORDER BY appointment_date DESC, appointment_time DESC");
$stmt->execute();
$appointments = $stmt->fetchAll();

// Handle status change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_status'])) {
    $appointment_id = intval($_POST['appointment_id']);
    $new_status = $_POST['new_status'];
    $reason = trim($_POST['reason'] ?? '');
    
    // Get current appointment details
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch();
    
    if ($appointment) {
        $old_status = $appointment['status'];
        
        // Update appointment status
        $update_stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE id = ?");
        $update_stmt->execute([$new_status, $appointment_id]);
        
        // Log the status change
        $log_stmt = $pdo->prepare("
            INSERT INTO status_change_logs 
            (appointment_id, booking_reference, old_status, new_status, changed_by, changed_by_name, reason, changed_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $log_stmt->execute([
            $appointment_id,
            $appointment['booking_reference'],
            $old_status,
            $new_status,
            $user['id'],
            $user['name'],
            $reason
        ]);
        
        // If status is changed to cancelled or no-show, create notification for patient
        if (in_array($new_status, ['cancelled', 'no-show'])) {
            $notification_stmt = $pdo->prepare("
                INSERT INTO patient_notifications 
                (patient_id, patient_email, notification_type, title, message, related_appointment_id, related_reference) 
                VALUES (?, ?, 'appointment_change', ?, ?, ?, ?)
            ");
            
            $title = "Appointment Status Changed";
            $message = "Your appointment (Ref: {$appointment['booking_reference']}) has been marked as '{$new_status}'. ";
            if ($reason) {
                $message .= "Reason: {$reason}";
            }
            
            // Get patient ID from users table
            $patient_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $patient_stmt->execute([$appointment['patient_email']]);
            $patient = $patient_stmt->fetch();
            
            if ($patient) {
                $notification_stmt->execute([
                    $patient['id'],
                    $appointment['patient_email'],
                    $title,
                    $message,
                    $appointment_id,
                    $appointment['booking_reference']
                ]);
            }
        }
        
        $success_message = "<div class='alert alert-success'>✅ Status changed from '{$old_status}' to '{$new_status}' for appointment {$appointment['booking_reference']}</div>";
        
        // Refresh appointments list
        header("Location: manage_status.php?success=1");
        exit;
    } else {
        $error_message = "<div class='alert alert-danger'>Appointment not found.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointment Status - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-selector .btn-group {
            width: 100%;
        }
        .status-selector .btn {
            flex: 1;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        .appointment-row {
            transition: all 0.3s;
        }
        .appointment-row:hover {
            background-color: #f8f9fa;
        }
        .status-booked { color: #007bff; }
        .status-completed { color: #28a745; }
        .status-cancelled { color: #dc3545; }
        .status-noshow { color: #ffc107; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-arrow-left"></i> Staff Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-item nav-link text-light">
                    <i class="fas fa-user-shield"></i> <?= htmlspecialchars($user['name']) ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1><i class="fas fa-edit"></i> Manage Appointment Status</h1>
        <p class="lead">Update appointment status and track changes</p>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">✅ Status updated successfully!</div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <?= $error_message ?>
        <?php endif; ?>
        
        <!-- Status Legend -->
        <div class="row mb-4">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Status Legend</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <span class="badge bg-primary status-badge">Booked</span>
                                <small class="text-muted">Confirmed appointment</small>
                            </div>
                            <div class="col-md-3">
                                <span class="badge bg-success status-badge">Completed</span>
                                <small class="text-muted">Appointment finished</small>
                            </div>
                            <div class="col-md-3">
                                <span class="badge bg-danger status-badge">Cancelled</span>
                                <small class="text-muted">Cancelled by patient or clinic</small>
                            </div>
                            <div class="col-md-3">
                                <span class="badge bg-warning status-badge">No-Show</span>
                                <small class="text-muted">Patient didn't show up</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Appointments Table -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> All Appointments
                    <span class="badge bg-light text-dark float-end"><?= count($appointments) ?> total</span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($appointments)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                        <h5>No Appointments Found</h5>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Patient</th>
                                    <th>Reference</th>
                                    <th>Current Status</th>
                                    <th>Change To</th>
                                    <th>Reason</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $appointment): ?>
                                <form method="POST" class="appointment-row">
                                    <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">
                                    <tr>
                                        <td>
                                            <?= date('M j, Y', strtotime($appointment['appointment_date'])) ?><br>
                                            <small class="text-muted"><?= date('g:i A', strtotime($appointment['appointment_time'])) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($appointment['patient_name']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($appointment['patient_email']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-dark"><?= $appointment['booking_reference'] ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $status_classes = [
                                                'booked' => 'bg-primary',
                                                'completed' => 'bg-success',
                                                'cancelled' => 'bg-danger',
                                                'no-show' => 'bg-warning'
                                            ];
                                            $status_class = $status_classes[$appointment['status']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?= $status_class ?>">
                                                <?= ucfirst($appointment['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <select name="new_status" class="form-select form-select-sm" required>
                                                <option value="">Select Status</option>
                                                <option value="booked" <?= $appointment['status'] == 'booked' ? 'disabled' : '' ?>>Booked</option>
                                                <option value="completed">Completed</option>
                                                <option value="cancelled">Cancelled</option>
                                                <option value="no-show">No-Show</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" name="reason" class="form-control form-control-sm" 
                                                   placeholder="Optional reason for change">
                                        </td>
                                        <td>
                                            <button type="submit" name="change_status" value="1" class="btn btn-sm btn-primary">
                                                <i class="fas fa-save"></i> Update
                                            </button>
                                        </td>
                                    </tr>
                                </form>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Status Change Log -->
        <div class="card mt-4">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-history"></i> Status Change History</h5>
            </div>
            <div class="card-body">
                <?php 
                $stmt = $pdo->prepare("
                    SELECT * FROM status_change_logs 
                    ORDER BY changed_at DESC 
                    LIMIT 50
                ");
                $stmt->execute();
                $logs = $stmt->fetchAll();
                ?>
                
                <?php if (empty($logs)): ?>
                    <p class="text-muted">No status changes recorded yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Reference</th>
                                    <th>Change</th>
                                    <th>Changed By</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <small><?= date('M j, g:i A', strtotime($log['changed_at'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-dark"><?= $log['booking_reference'] ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= ucfirst($log['old_status']) ?></span>
                                        <i class="fas fa-arrow-right mx-2 text-muted"></i>
                                        <span class="badge bg-<?= 
                                            $log['new_status'] == 'completed' ? 'success' : 
                                            ($log['new_status'] == 'cancelled' ? 'danger' : 
                                            ($log['new_status'] == 'no-show' ? 'warning' : 'primary'))
                                        ?>">
                                            <?= ucfirst($log['new_status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($log['changed_by_name']) ?></td>
                                    <td>
                                        <small><?= htmlspecialchars($log['reason'] ?: '—') ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>