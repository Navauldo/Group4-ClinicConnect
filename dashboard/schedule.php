<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/../includes/config.php';

$success_message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_hours'])) {
        $clinic_id = 1; // Default clinic
        $day_of_week = $_POST['day_of_week'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Update or insert schedule
        $stmt = $pdo->prepare("REPLACE INTO clinic_schedules (clinic_id, day_of_week, start_time, end_time, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$clinic_id, $day_of_week, $start_time, $end_time, $is_active]);
        
        $success_message = "<div class='alert alert-success'>Schedule updated successfully!</div>";
    }
}

// Get current schedules
$stmt = $pdo->prepare("SELECT * FROM clinic_schedules WHERE clinic_id = 1 ORDER BY day_of_week");
$stmt->execute();
$schedules = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Schedule - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">← ClinicConnect Staff</a>
        </div>
    </nav>

    <div class="container mt-4">
        <h2>🕒 Clinic Schedule Management</h2>
        
        <?php echo $success_message; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Weekly Schedule</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Day of Week</label>
                                    <select name="day_of_week" class="form-control" required>
                                        <option value="1">Monday</option>
                                        <option value="2">Tuesday</option>
                                        <option value="3">Wednesday</option>
                                        <option value="4">Thursday</option>
                                        <option value="5">Friday</option>
                                        <option value="6">Saturday</option>
                                        <option value="0">Sunday</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Start Time</label>
                                    <input type="time" name="start_time" class="form-control" value="09:00" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">End Time</label>
                                    <input type="time" name="end_time" class="form-control" value="17:00" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Active</label>
                                    <div class="form-check">
                                        <input type="checkbox" name="is_active" class="form-check-input" checked>
                                        <label class="form-check-label">Open</label>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" name="update_hours" class="btn btn-primary">Update Schedule</button>
                        </form>
                    </div>
                </div>

                <!-- Current Schedule Table -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>Current Schedule</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Day</th>
                                        <th>Opening Time</th>
                                        <th>Closing Time</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                    foreach($schedules as $schedule): 
                                    ?>
                                    <tr>
                                        <td><?= $days[$schedule['day_of_week']]; ?></td>
                                        <td><?= date('g:i A', strtotime($schedule['start_time'])); ?></td>
                                        <td><?= date('g:i A', strtotime($schedule['end_time'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?= $schedule['is_active'] ? 'success' : 'danger'; ?>">
                                                <?= $schedule['is_active'] ? 'Open' : 'Closed'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5>About Schedule Management</h5>
                        <p>Set your clinic's operating hours to control when patients can book appointments.</p>
                        <ul>
                            <li>Mark days as closed for holidays</li>
                            <li>Set different hours for weekends</li>
                            <li>Prevent bookings outside clinic hours</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-3">
            <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>