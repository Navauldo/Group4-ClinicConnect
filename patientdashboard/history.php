<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'patient') {
    header('Location: ../login.php?role=patient');
    exit;
}

include '../includes/config.php';

$user = $_SESSION['user'];
$patient_email = $user['email'];

// Get filter parameters
$filter = $_GET['filter'] ?? 'all'; // all, upcoming, past, cancelled, completed
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query based on filters
$query = "SELECT * FROM appointments WHERE patient_email = ?";
$params = [$patient_email];

// Apply filters
switch ($filter) {
    case 'upcoming':
        $query .= " AND appointment_date >= CURDATE() AND status = 'booked'";
        break;
    case 'past':
        $query .= " AND (appointment_date < CURDATE() OR status IN ('completed', 'no-show'))";
        break;
    case 'cancelled':
        $query .= " AND status = 'cancelled'";
        break;
    case 'completed':
        $query .= " AND status = 'completed'";
        break;
    case 'noshow':
        $query .= " AND status = 'no-show'";
        break;
    // 'all' shows everything
}

// Date range filter
if ($date_from) {
    $query .= " AND appointment_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $query .= " AND appointment_date <= ?";
    $params[] = $date_to;
}

// Search filter
if ($search) {
    $query .= " AND (booking_reference LIKE ? OR reason LIKE ? OR patient_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Order by
$query .= " ORDER BY appointment_date DESC, appointment_time DESC";

// Get total count for pagination
$count_query = str_replace("SELECT *", "SELECT COUNT(*) as total", $query);
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_count = $stmt->fetch()['total'];

// Pagination - FIXED VERSION
$per_page = 15;
$total_pages = ceil($total_count / $per_page);
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Add pagination to query WITHOUT parameters (concatenate directly)
$query .= " LIMIT $per_page OFFSET $offset";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Get statistics
$stats = [
    'total' => 0,
    'upcoming' => 0,
    'cancelled' => 0,
    'completed' => 0,
    'noshow' => 0
];

$stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM appointments WHERE patient_email = ? GROUP BY status");
$stmt->execute([$patient_email]);
$status_counts = $stmt->fetchAll();

foreach ($status_counts as $row) {
    $stats[$row['status']] = $row['count'];
    $stats['total'] += $row['count'];
}

// Get upcoming count separately
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE patient_email = ? AND appointment_date >= CURDATE() AND status = 'booked'");
$stmt->execute([$patient_email]);
$stats['upcoming'] = $stmt->fetch()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment History - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .stat-card {
            border-radius: 10px;
            padding: 15px;
            color: white;
            margin-bottom: 15px;
            text-align: center;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .stat-total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-upcoming { background: linear-gradient(135deg, #28a745, #1e7e34); }
        .stat-cancelled { background: linear-gradient(135deg, #dc3545, #a71d2a); }
        .stat-completed { background: linear-gradient(135deg, #17a2b8, #138496); }
        .stat-noshow { background: linear-gradient(135deg, #ffc107, #d39e00); }
        
        .appointment-row {
            transition: all 0.3s;
            border-left: 4px solid;
        }
        .appointment-row:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .status-booked { border-left-color: #007bff; }
        .status-upcoming { border-left-color: #28a745; }
        .status-cancelled { border-left-color: #dc3545; }
        .status-completed { border-left-color: #17a2b8; }
        .status-noshow { border-left-color: #ffc107; }
        
        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .export-btn {
            background: linear-gradient(135deg, #20c997, #199d76);
            color: white;
            border: none;
        }
        .export-btn:hover {
            background: linear-gradient(135deg, #199d76, #147a63);
            color: white;
        }
        .badge-status {
            font-size: 0.8em;
            padding: 5px 10px;
        }
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
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
            <div class="navbar-nav ms-auto">
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
        <h1><i class="fas fa-history"></i> Appointment History</h1>
        <p class="lead">View and manage all your past and upcoming appointments</p>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2 col-6">
                <div class="stat-card stat-total">
                    <h3><?= $stats['total'] ?></h3>
                    <p>Total</p>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card stat-upcoming">
                    <h3><?= $stats['upcoming'] ?></h3>
                    <p>Upcoming</p>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card stat-completed">
                    <h3><?= $stats['completed'] ?></h3>
                    <p>Completed</p>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card stat-cancelled">
                    <h3><?= $stats['cancelled'] ?></h3>
                    <p>Cancelled</p>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card stat-noshow">
                    <h3><?= $stats['noshow'] ?></h3>
                    <p>No Shows</p>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="stat-card" style="background: linear-gradient(135deg, #6f42c1, #563d7c);">
                    <h3><?= $per_page ?></h3>
                    <p>Per Page</p>
                </div>
            </div>
        </div>
        
        <!-- Filters Card -->
        <div class="filter-card">
            <h4><i class="fas fa-filter"></i> Filter Appointments</h4>
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Filter by Status</label>
                    <select name="filter" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $filter == 'all' ? 'selected' : '' ?>>All Appointments</option>
                        <option value="upcoming" <?= $filter == 'upcoming' ? 'selected' : '' ?>>Upcoming Only</option>
                        <option value="past" <?= $filter == 'past' ? 'selected' : '' ?>>Past Only</option>
                        <option value="completed" <?= $filter == 'completed' ? 'selected' : '' ?>>Completed Only</option>
                        <option value="cancelled" <?= $filter == 'cancelled' ? 'selected' : '' ?>>Cancelled Only</option>
                        <option value="noshow" <?= $filter == 'noshow' ? 'selected' : '' ?>>No Shows Only</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="text" name="date_from" class="form-control datepicker" 
                           placeholder="Start date" value="<?= htmlspecialchars($date_from) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="text" name="date_to" class="form-control datepicker" 
                           placeholder="End date" value="<?= htmlspecialchars($date_to) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" 
                               placeholder="Reference or reason..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="history.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                  
                </div>
            </form>
        </div>
        
        <!-- Appointments Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> Appointments
                    <span class="badge bg-primary float-end"><?= $total_count ?> total</span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($appointments)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Appointments Found</h3>
                    <p>No appointments match your current filters.</p>
                    <a href="../booking/index.php" class="btn btn-primary mt-2">
                        <i class="fas fa-calendar-plus"></i> Book Your First Appointment
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Reference</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): 
                                $is_upcoming = $appointment['status'] == 'booked' && strtotime($appointment['appointment_date']) >= time();
                                $status_class = $is_upcoming ? 'upcoming' : $appointment['status'];
                            ?>
                            <tr class="appointment-row status-<?= $status_class ?>">
                                <td>
                                    <strong><?= date('M j, Y', strtotime($appointment['appointment_date'])) ?></strong><br>
                                    <small class="text-muted"><?= date('g:i A', strtotime($appointment['appointment_time'])) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-dark"><?= $appointment['booking_reference'] ?></span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($appointment['reason'] ?: 'Checkup') ?>
                                    <?php if ($appointment['reminder_sent']): ?>
                                        <br><small class="text-success"><i class="fas fa-bell"></i> Reminder sent</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $status_badges = [
                                        'booked' => ['class' => 'bg-primary', 'text' => 'Booked'],
                                        'upcoming' => ['class' => 'bg-success', 'text' => 'Upcoming'],
                                        'cancelled' => ['class' => 'bg-danger', 'text' => 'Cancelled'],
                                        'completed' => ['class' => 'bg-info', 'text' => 'Completed'],
                                        'no-show' => ['class' => 'bg-warning', 'text' => 'No Show']
                                    ];
                                    $status_info = $status_badges[$status_class] ?? ['class' => 'bg-secondary', 'text' => ucfirst($status_class)];
                                    ?>
                                    <span class="badge badge-status <?= $status_info['class'] ?>">
                                        <?= $status_info['text'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?ref=<?= $appointment['booking_reference'] ?>" 
                                           class="btn btn-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($is_upcoming): ?>
                                        <a href="reschedule.php?ref=<?= $appointment['booking_reference'] ?>" 
                                           class="btn btn-warning" title="Reschedule">
                                            <i class="fas fa-calendar-alt"></i>
                                        </a>
                                        <a href="cancel.php?ref=<?= $appointment['booking_reference'] ?>" 
                                           class="btn btn-danger" title="Cancel">
                                            <i class="fas fa-times"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($appointment['status'] == 'cancelled'): ?>
                                        <a href="../booking/index.php" 
                                           class="btn btn-success" title="Book Again">
                                            <i class="fas fa-redo"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $current_page == 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page - 1])) ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        </li>
                        
                        <?php 
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                        <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $current_page == $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page + 1])) ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                    <p class="text-center text-muted">
                        Page <?= $current_page ?> of <?= $total_pages ?> • 
                        Showing <?= count($appointments) ?> of <?= $total_count ?> appointments
                    </p>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        
        
        <!-- Help Section -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-question-circle"></i> Understanding Your History
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-primary me-2">Booked</span>
                            <span>Confirmed appointment</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-success me-2">Upcoming</span>
                            <span>Future appointment</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-danger me-2">Cancelled</span>
                            <span>Cancelled by you or clinic</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-warning me-2">No Show</span>
                            <span>Missed appointment</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
    // Initialize date pickers
    flatpickr(".datepicker", {
        dateFormat: "Y-m-d",
        allowInput: true
    });
    
    function exportHistory() {
        // Get current filter parameters
        const params = new URLSearchParams(window.location.search);
        
        // Ask for export format
        const format = prompt("Enter export format (csv, excel, or pdf):", "csv");
        if (!format) return;
        
        // Build export URL
        let exportUrl = `export_history.php?format=${format}`;
        params.forEach((value, key) => {
            if (key !== 'page') { // Don't include page parameter
                exportUrl += `&${key}=${encodeURIComponent(value)}`;
            }
        });
        
        // Open export in new window
        window.open(exportUrl, '_blank');
    }
    
    function printHistory() {
        // Get current filter parameters
        const params = new URLSearchParams(window.location.search);
        
        // Build print URL
        let printUrl = `print_history.php?`;
        params.forEach((value, key) => {
            if (key !== 'page') { // Don't include page parameter
                printUrl += `${key}=${encodeURIComponent(value)}&`;
            }
        });
        
        // Open print view in new window
        const printWindow = window.open(printUrl, '_blank');
        printWindow.focus();
    }
    
    // Auto-refresh every 5 minutes
    setTimeout(function() {
        window.location.reload();
    }, 300000);
    
    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+F for filter focus
        if (e.ctrlKey && e.key === 'f') {
            e.preventDefault();
            document.querySelector('input[name="search"]').focus();
        }
        
        // Ctrl+P for print
        if (e.ctrlKey && e.key === 'p') {
            e.preventDefault();
            printHistory();
        }
        
        // Ctrl+E for export
        if (e.ctrlKey && e.key === 'e') {
            e.preventDefault();
            exportHistory();
        }
    });
    </script>
</body>
</html>