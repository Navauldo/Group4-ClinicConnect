<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/../includes/config.php';

$success_message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_hours'])) {
        $clinic_id = 1;
        $day_of_week = $_POST['day_of_week'];
        $start_time = $_POST['start_time'] . ':00'; // Add seconds for database
        $end_time = $_POST['end_time'] . ':00'; // Add seconds for database
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            // Check if schedule already exists for this day
            $check_stmt = $pdo->prepare("SELECT id FROM clinic_schedules WHERE clinic_id = ? AND day_of_week = ?");
            $check_stmt->execute([$clinic_id, $day_of_week]);
            $existing = $check_stmt->fetch();
            
            if ($existing) {
                // Update existing schedule
                $stmt = $pdo->prepare("UPDATE clinic_schedules SET start_time = ?, end_time = ?, is_active = ? WHERE clinic_id = ? AND day_of_week = ?");
                $stmt->execute([$start_time, $end_time, $is_active, $clinic_id, $day_of_week]);
                $success_message = "<div class='alert alert-success'>✅ Schedule updated successfully!</div>";
            } else {
                // Insert new schedule
                $stmt = $pdo->prepare("INSERT INTO clinic_schedules (clinic_id, day_of_week, start_time, end_time, is_active) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$clinic_id, $day_of_week, $start_time, $end_time, $is_active]);
                $success_message = "<div class='alert alert-success'>✅ Schedule added successfully!</div>";
            }
        } catch(PDOException $e) {
            $success_message = "<div class='alert alert-danger'>❌ Error: " . $e->getMessage() . "</div>";
        }
    }
}

// Get current schedules
$stmt = $pdo->prepare("SELECT * FROM clinic_schedules WHERE clinic_id = 1 ORDER BY day_of_week");
$stmt->execute();
$schedules = $stmt->fetchAll();

// Create default schedules if none exist - FIXED to include all days
if (empty($schedules)) {
    $default_schedules = [
        [0, '09:00:00', '17:00:00', 0], // Sunday (closed by default)
        [1, '09:00:00', '17:00:00', 1], // Monday
        [2, '09:00:00', '17:00:00', 1], // Tuesday
        [3, '09:00:00', '17:00:00', 1], // Wednesday
        [4, '09:00:00', '17:00:00', 1], // Thursday
        [5, '09:00:00', '17:00:00', 1], // Friday
        [6, '09:00:00', '17:00:00', 0], // Saturday (closed by default)
    ];
    
    foreach ($default_schedules as $schedule) {
        $stmt = $pdo->prepare("INSERT INTO clinic_schedules (clinic_id, day_of_week, start_time, end_time, is_active) VALUES (1, ?, ?, ?, ?)");
        $stmt->execute($schedule);
    }
    
    // Reload schedules
    $stmt = $pdo->prepare("SELECT * FROM clinic_schedules WHERE clinic_id = 1 ORDER BY day_of_week");
    $stmt->execute();
    $schedules = $stmt->fetchAll();
}

// Create a schedule map for easier access
$schedule_map = [];
foreach ($schedules as $schedule) {
    $schedule_map[$schedule['day_of_week']] = $schedule;
}
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
                <!-- Weekly Schedule Card -->
                <div class="card">
                    <div class="card-header">
                        <h5>Weekly Operating Hours</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row mb-3">
                                <div class="col-md-3">
                                    <label class="form-label">Day of Week</label>
                                    <select name="day_of_week" class="form-control" required>
                          
                                        <option value="1">Monday</option>
                                        <option value="2">Tuesday</option>
                                        <option value="3">Wednesday</option>
                                        <option value="4">Thursday</option>
                                        <option value="5">Friday</option>
                                    
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Opening Time</label>
                                    <input type="time" name="start_time" class="form-control" value="09:00" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Closing Time</label>
                                    <input type="time" name="end_time" class="form-control" value="17:00" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <div class="form-check mt-2">
                                        <input type="checkbox" name="is_active" class="form-check-input" checked id="isActive">
                                        <label class="form-check-label" for="isActive">Clinic Open</label>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" name="update_hours" class="btn btn-primary">Update Schedule</button>
                        </form>
                    </div>
                </div>

                <!-- Current Schedule Table -->
                <div class="card mt-4">
                    <div class="card-header bg-success text-white">
                        <h5>Current Weekly Schedule</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Day</th>
                                        <th>Opening Time</th>
                                        <th>Closing Time</th>
                                        <th>Hours Open</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                    for($i = 0; $i <= 6; $i++):
                                        if (isset($schedule_map[$i])) {
                                            $schedule = $schedule_map[$i];
                                            $start_formatted = date('g:i A', strtotime($schedule['start_time']));
                                            $end_formatted = date('g:i A', strtotime($schedule['end_time']));
                                            $hours = $start_formatted . ' - ' . $end_formatted;
                                        } else {
                                            // Fallback if schedule doesn't exist
                                            $schedule = ['start_time' => '09:00:00', 'end_time' => '17:00:00', 'is_active' => 0];
                                            $start_formatted = '9:00 AM';
                                            $end_formatted = '5:00 PM';
                                            $hours = '9:00 AM - 5:00 PM';
                                        }
                                    ?>
                                    <tr>
                                        <td><strong><?= $days[$i]; ?></strong></td>
                                        <td><?= $start_formatted; ?></td>
                                        <td><?= $end_formatted; ?></td>
                                        <td><?= $hours; ?></td>
                                        <td>
                                            <span class="badge bg-<?= $schedule['is_active'] ? 'success' : 'danger'; ?>">
                                                <?= $schedule['is_active'] ? 'OPEN' : 'CLOSED'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Quick Stats -->
                <div class="card">
                    <div class="card-body">
                        <h5>Schedule Overview</h5>
                        <?php
                        $open_days = array_filter($schedules, function($s) { return $s['is_active']; });
                        $total_hours = 0;
                        foreach($open_days as $day) {
                            $total_hours += (strtotime($day['end_time']) - strtotime($day['start_time'])) / 3600;
                        }
                        ?>
                        <div class="mb-3">
                            <strong>Open Days:</strong> <?= count($open_days); ?>/7<br>
                            <strong>Weekly Hours:</strong> <?= number_format($total_hours, 1); ?> hours<br>
                            <strong>Next Open:</strong> 
                            <?php
                            $today = date('w'); // 0-6 (Sunday-Saturday)
                            $next_open_day = null;
                            for($i = 1; $i <= 7; $i++) {
                                $check_day = ($today + $i) % 7;
                                if (isset($schedule_map[$check_day]) && $schedule_map[$check_day]['is_active']) {
                                    $next_open_day = $days[$check_day];
                                    break;
                                }
                            }
                            echo $next_open_day ?: 'No open days found';
                            ?>
                        </div>
                    </div>
                </div>

                

                
            </div>
        </div>
        
        <div class="mt-4">
            <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
            
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>