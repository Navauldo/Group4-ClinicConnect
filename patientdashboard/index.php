<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    // If not logged in but has booking reference, allow limited access
    if (isset($_GET['ref'])) {
        // Store reference in session for limited access
        $_SESSION['guest_ref'] = $_GET['ref'];
        header('Location: patientdashboard/guest_view.php?ref=' . $_GET['ref']);
        exit;
    }
    
    // Redirect to login if no reference and not logged in
    header('Location: ../login.php?role=patient');
    exit;
}

include '../includes/config.php';

$user = $_SESSION['user'];
$patient_email = $user['email'];

// Get upcoming appointments (today and future)
$current_date = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT * FROM appointments 
    WHERE patient_email = ? 
    AND appointment_date >= ? 
    AND status = 'booked'
    ORDER BY appointment_date ASC, appointment_time ASC
    LIMIT 10
");
$stmt->execute([$patient_email, $current_date]);
$upcoming_appointments = $stmt->fetchAll();

// Get past appointments
$stmt = $pdo->prepare("
    SELECT * FROM appointments 
    WHERE patient_email = ? 
    AND (appointment_date < ? OR status IN ('cancelled', 'completed', 'no-show'))
    ORDER BY appointment_date DESC, appointment_time DESC
    LIMIT 10
");
$stmt->execute([$patient_email, $current_date]);
$past_appointments = $stmt->fetchAll();

// Get appointment counts for stats
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE patient_email = ? AND status = 'booked' AND appointment_date >= ?");
$stmt->execute([$patient_email, $current_date]);
$upcoming_count = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE patient_email = ? AND status = 'cancelled'");
$stmt->execute([$patient_email]);
$cancelled_count = $stmt->fetch()['count'];

$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE patient_email = ? AND status = 'no-show'");
$stmt->execute([$patient_email]);
$no_show_count = $stmt->fetch()['count'];

// Get unread notifications count for the badge
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM patient_notifications WHERE patient_id = ? AND is_read = 0");
$stmt->execute([$user['id']]);
$unread_notifications = $stmt->fetch()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .dashboard-card {
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            height: 100%;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .appointment-card {
            border-left: 4px solid;
            transition: all 0.3s;
        }
        .appointment-card.booked {
            border-left-color: #007bff;
        }
        .appointment-card.upcoming {
            border-left-color: #28a745;
        }
        .appointment-card.past {
            border-left-color: #6c757d;
        }
        .appointment-card.cancelled {
            border-left-color: #dc3545;
        }
        .stat-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            color: white;
            margin-bottom: 15px;
        }
        .stat-upcoming {
            background: linear-gradient(135deg, #007bff, #0056b3);
        }
        .stat-cancelled {
            background: linear-gradient(135deg, #dc3545, #a71d2a);
        }
        .stat-noshow {
            background: linear-gradient(135deg, #ffc107, #d39e00);
        }
        .quick-action-btn {
            padding: 15px;
            margin: 5px;
            border-radius: 10px;
            text-align: center;
            color: white;
            font-weight: bold;
            display: block;
            text-decoration: none;
            transition: all 0.3s;
            position: relative;
        }
        .quick-action-btn:hover {
            opacity: 0.9;
            text-decoration: none;
            color: white;
            transform: scale(1.05);
        }
        .btn-book {
            background: linear-gradient(135deg, #28a745, #1e7e34);
        }
        .btn-reschedule {
            background: linear-gradient(135deg, #17a2b8, #138496);
        }
        .btn-history {
            background: linear-gradient(135deg, #6f42c1, #563d7c);
        }
        .btn-profile {
            background: linear-gradient(135deg, #fd7e14, #e8590c);
        }
        .btn-messages {
            background: linear-gradient(135deg, #9c27b0, #7b1fa2);
        }
        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
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
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-hospital"></i> ClinicConnect Patient Portal
            </a>
            <div class="navbar-nav ms-auto">
                <a href="messages.php" class="nav-item nav-link text-light position-relative me-3">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_notifications > 0): ?>
                        <span class="notification-badge"><?= $unread_notifications ?></span>
                    <?php endif; ?>
                </a>
                <span class="nav-item nav-link text-light">
                    <i class="fas fa-user-circle"></i> <?= htmlspecialchars($user['name']) ?>
                </span>
                <a class="nav-item nav-link text-light" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h1><i class="fas fa-heartbeat"></i> Welcome, <?= htmlspecialchars($user['name']) ?>!</h1>
            <p class="lead">Manage your medical appointments easily and efficiently</p>
            <div class="row mt-4">
                <div class="col-md-3 col-6">
                    <div class="stat-card stat-upcoming">
                        <h3><?= $upcoming_count ?></h3>
                        <p>Upcoming Appointments</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card stat-cancelled">
                        <h3><?= $cancelled_count ?></h3>
                        <p>Cancelled</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card stat-noshow">
                        <h3><?= $no_show_count ?></h3>
                        <p>No Shows</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card" style="background: linear-gradient(135deg, #20c997, #199d76);">
                        <h3><?= count($past_appointments) ?></h3>
                        <p>Past Visits</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-md-3 col-6 mb-3">
                <a href="../booking/index.php" class="quick-action-btn btn-book">
                    <i class="fas fa-calendar-plus fa-2x mb-2"></i><br>
                    Book New Appointment
                </a>
            </div>
            <div class="col-md-3 col-6 mb-3">
                  <a href="messages.php" class="quick-action-btn btn-messages position-relative">
                    <i class="fas fa-comments fa-2x mb-2"></i><br>
                    Messages & Notifications
                    <?php if ($unread_notifications > 0): ?>
                        <span class="notification-badge"><?= $unread_notifications ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <div class="col-md-3 col-6 mb-3">
                <a href="history.php" class="quick-action-btn btn-history">
                    <i class="fas fa-history fa-2x mb-2"></i><br>
                    View Full History
                </a>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <a href="profile.php" class="quick-action-btn btn-profile">
                    <i class="fas fa-user-cog fa-2x mb-2"></i><br>
                    My Profile
                </a>
            </div>

            <!-- Messages & Notifications Quick Action -->
       
        </div>

        

        <!-- Upcoming Appointments -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card dashboard-card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar-check"></i> Upcoming Appointments
                            <span class="badge bg-light text-dark float-end"><?= count($upcoming_appointments) ?> appointments</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($upcoming_appointments)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> You have no upcoming appointments.
                                <a href="../booking/index.php" class="alert-link">Book your first appointment now!</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Reference</th>
                                            <th>Reason</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($upcoming_appointments as $appointment): ?>
                                        <tr class="appointment-card upcoming">
                                            <td>
                                                <strong><?= date('l, F j, Y', strtotime($appointment['appointment_date'])) ?></strong><br>
                                                <small class="text-muted"><?= date('g:i A', strtotime($appointment['appointment_time'])) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary"><?= $appointment['booking_reference'] ?></span>
                                            </td>
                                            <td><?= htmlspecialchars($appointment['reason'] ?: 'Checkup') ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="reschedule.php?ref=<?= $appointment['booking_reference'] ?>" 
                                                       class="btn btn-warning btn-sm" title="Reschedule">
                                                        <i class="fas fa-calendar-alt"></i>
                                                    </a>
                                                    <a href="cancel.php?ref=<?= $appointment['booking_reference'] ?>" 
                                                       class="btn btn-danger btn-sm" title="Cancel">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                    <a href="view.php?ref=<?= $appointment['booking_reference'] ?>" 
                                                       class="btn btn-info btn-sm" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (count($upcoming_appointments) >= 10): ?>
                                <div class="text-center mt-3">
                                    <a href="history.php?filter=upcoming" class="btn btn-outline-primary">
                                        View All Upcoming Appointments <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent History Sidebar -->
            <div class="col-lg-4">
                <div class="card dashboard-card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-history"></i> Recent History
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($past_appointments)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No appointment history yet.
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach (array_slice($past_appointments, 0, 5) as $appointment): ?>
                                <div class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">
                                            <?= date('M j, Y', strtotime($appointment['appointment_date'])) ?>
                                            at <?= date('g:i A', strtotime($appointment['appointment_time'])) ?>
                                        </h6>
                                        <span class="badge bg-<?= $appointment['status'] == 'cancelled' ? 'danger' : ($appointment['status'] == 'completed' ? 'success' : 'warning') ?>">
                                            <?= ucfirst($appointment['status']) ?>
                                        </span>
                                    </div>
                                    <p class="mb-1"><?= htmlspecialchars($appointment['reason'] ?: 'Checkup') ?></p>
                                    <small class="text-muted">Ref: <?= $appointment['booking_reference'] ?></small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($past_appointments) > 5): ?>
                                <div class="text-center mt-3">
                                    <a href="history.php" class="btn btn-outline-secondary btn-sm">
                                        View Full History <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="card dashboard-card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-link"></i> Quick Links
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <a href="../booking/index.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-calendar-plus text-primary"></i> Book New Appointment
                            </a>
                            <!-- FIX: Show upcoming appointments page for reschedule -->
                            <a href="history.php?filter=upcoming" class="list-group-item list-group-item-action">
                                <i class="fas fa-calendar-alt text-warning"></i> Reschedule Appointment
                            </a>
                            <a href="history.php?filter=upcoming" class="list-group-item list-group-item-action">
                                <i class="fas fa-times-circle text-danger"></i> Cancel Appointment
                            </a>
                            <a href="history.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-history text-secondary"></i> View Appointment History
                            </a>
                            <a href="profile.php" class="list-group-item list-group-item-action">
                                <i class="fas fa-user-edit text-success"></i> Update My Profile
                            </a>
                            <a href="messages.php" class="list-group-item list-group-item-action position-relative">
                                <i class="fas fa-comments text-purple"></i> Messages & Notifications
                                <?php if ($unread_notifications > 0): ?>
                                    <span class="badge bg-danger position-absolute top-50 end-0 translate-middle-y me-2">
                                        <?= $unread_notifications ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white mt-5 py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><i class="fas fa-hospital"></i> ClinicConnect</h5>
                    <p>Your health, our priority. Managing appointments made easy.</p>
                </div>
                <div class="col-md-6 text-end">
                    <p>&copy; 2025 ClinicConnect. All rights reserved.</p>
                    <p>Logged in as: <?= htmlspecialchars($user['email']) ?></p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh dashboard every 60 seconds
        setTimeout(function() {
            window.location.reload();
        }, 60000);
        
        // Confirm before cancelling
        document.addEventListener('DOMContentLoaded', function() {
            const cancelLinks = document.querySelectorAll('a[href*="cancel.php"]');
            cancelLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to cancel this appointment?')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>