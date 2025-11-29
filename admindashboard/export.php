<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include '../includes/config.php';

// Helper functions for preview
function getFilteredAppointments($start_date, $end_date, $status_filter, $limit = 10) {
    global $pdo;
    
    $query = "SELECT * FROM appointments WHERE 1=1";
    $params = [];
    
    if (!empty($start_date)) {
        $query .= " AND appointment_date >= ?";
        $params[] = $start_date;
    }
    
    if (!empty($end_date)) {
        $query .= " AND appointment_date <= ?";
        $params[] = $end_date;
    }
    
    if ($status_filter !== 'all') {
        $query .= " AND status = ?";
        $params[] = $status_filter;
    }
    
    $query .= " ORDER BY appointment_date DESC, appointment_time DESC";
    
    // Add LIMIT without parameter binding (direct value)
    if ($limit > 0) {
        $query .= " LIMIT " . (int)$limit;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getAppointmentCount($start_date, $end_date, $status_filter) {
    global $pdo;
    
    $query = "SELECT COUNT(*) as total FROM appointments WHERE 1=1";
    $params = [];
    
    if (!empty($start_date)) {
        $query .= " AND appointment_date >= ?";
        $params[] = $start_date;
    }
    
    if (!empty($end_date)) {
        $query .= " AND appointment_date <= ?";
        $params[] = $end_date;
    }
    
    if ($status_filter !== 'all') {
        $query .= " AND status = ?";
        $params[] = $status_filter;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch();
    return $result['total'];
}

function generatePreviewHTML($appointments, $total_count) {
    if (count($appointments) > 0) {
        $html = '
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Patient</th>
                            <th>Status</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($appointments as $appt) {
            $html .= '
                <tr>
                    <td>' . $appt['appointment_date'] . '</td>
                    <td>' . date('g:i A', strtotime($appt['appointment_time'])) . '</td>
                    <td>' . htmlspecialchars($appt['patient_name']) . '</td>
                    <td><span class="badge bg-' . getStatusBadgeColor($appt['status']) . '">' . ucfirst($appt['status']) . '</span></td>
                    <td><small>' . $appt['booking_reference'] . '</small></td>
                </tr>';
        }
        
        $html .= '
                    </tbody>
                </table>
            </div>';
        
        if ($total_count > 10) {
            $html .= '<p class="text-muted"><small>Showing first 10 of ' . $total_count . ' appointments</small></p>';
        } else {
            $html .= '<p class="text-muted"><small>Showing all ' . $total_count . ' appointments</small></p>';
        }
    } else {
        $html = '<p class="text-muted">No appointments match the selected filters.</p>';
    }
    
    return $html;
}

function getStatusBadgeColor($status) {
    switch ($status) {
        case 'booked': return 'primary';
        case 'cancelled': return 'danger';
        case 'completed': return 'success';
        case 'no-show': return 'warning';
        default: return 'secondary';
    }
}

// Handle AJAX preview requests - MUST BE AT THE VERY TOP
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    // Clear any previous output
    if (ob_get_length()) ob_clean();
    
    $start_date = $_GET['start_date'] ?? '';
    $end_date = $_GET['end_date'] ?? '';
    $status_filter = $_GET['status_filter'] ?? 'all';
    
    try {
        $appointments = getFilteredAppointments($start_date, $end_date, $status_filter, 10);
        $total_count = getAppointmentCount($start_date, $end_date, $status_filter);
        $preview_html = generatePreviewHTML($appointments, $total_count);
        
        header('Content-Type: application/json');
        echo json_encode([
            'count' => $total_count,
            'preview' => $preview_html
        ]);
        exit;
        
    } catch(PDOException $e) {
        // Clear any output before sending error
        if (ob_get_length()) ob_clean();
        
        header('Content-Type: application/json');
        echo json_encode([
            'count' => 0,
            'preview' => '<div class="alert alert-danger">Error loading preview: ' . $e->getMessage() . '</div>'
        ]);
        exit;
    }
}

$success_message = "";
$error_message = "";

// Handle export request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['export_appointments'])) {
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $status_filter = $_POST['status_filter'] ?? 'all';
    
    try {
        // Clear any existing output completely before CSV generation
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Build the query based on filters
        $query = "SELECT * FROM appointments WHERE 1=1";
        $params = [];
        
        // Date range filter
        if (!empty($start_date)) {
            $query .= " AND appointment_date >= ?";
            $params[] = $start_date;
        }
        
        if (!empty($end_date)) {
            $query .= " AND appointment_date <= ?";
            $params[] = $end_date;
        }
        
        // Status filter
        if ($status_filter !== 'all') {
            $query .= " AND status = ?";
            $params[] = $status_filter;
        }
        
        $query .= " ORDER BY appointment_date DESC, appointment_time DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $appointments = $stmt->fetchAll();
        
        if (count($appointments) > 0) {
            // Generate CSV content
            $csv_data = generateCSV($appointments);
            
            // Set headers for download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="appointments_' . date('Y-m-d') . '.csv"');
            
            // Output CSV data
            echo $csv_data;
            exit;
            
        } else {
            $error_message = "<div class='alert alert-warning'>No appointments found matching your criteria.</div>";
        }
        
    } catch(PDOException $e) {
        $error_message = "<div class='alert alert-danger'><strong> Export Error:</strong> " . $e->getMessage() . "</div>";
    }
}

// Function to generate CSV data
function generateCSV($appointments) {
    // Clear any existing output buffers completely
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Start fresh output buffering
    ob_start();
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8 to help Excel with special characters
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    
    // CSV headers
    $headers = [
        'Booking Reference',
        'Patient Name', 
        'Patient Email',
        'Patient Phone',
        'Appointment Date',
        'Appointment Time',
        'Reason for Visit',
        'Status',
        'Created Date',
        'Reminder Sent'
    ];
    
    fputcsv($output, $headers);
    
    // Add data rows
    foreach ($appointments as $appt) {
        $row = [
            $appt['booking_reference'],
            $appt['patient_name'],
            $appt['patient_email'] ?? 'N/A',
            $appt['patient_phone'] ?? 'N/A',
            $appt['appointment_date'],
            date('g:i A', strtotime($appt['appointment_time'])),
            $appt['reason'] ?? 'N/A',
            ucfirst($appt['status']),
            date('Y-m-d H:i', strtotime($appt['created_at'])),
            $appt['reminder_sent'] ? 'Yes' : 'No'
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
    return ob_get_clean();
}


// Get filter values for form persistence and initial preview
$start_date_val = $_POST['start_date'] ?? '';
$end_date_val = $_POST['end_date'] ?? '';
$status_val = $_POST['status_filter'] ?? 'all';

// Get initial preview data
$initial_appointments = getFilteredAppointments($start_date_val, $end_date_val, $status_val, 10);
$initial_count = getAppointmentCount($start_date_val, $end_date_val, $status_val);
$initial_preview = generatePreviewHTML($initial_appointments, $initial_count);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Appointment Data - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .export-card {
            border-left: 4px solid #28a745;
            transition: all 0.3s;
        }
        .export-card:hover {
            background-color: #f8f9fa;
        }
        .stats-card {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 8px;
            padding: 15px;
        }
        .flatpickr-calendar {
            background: white !important;
            border: 2px solid #28a745 !important;
            border-radius: 10px !important;
        }
        .flatpickr-day.selected {
            background: #28a745 !important;
        }
        .preview-loading {
            opacity: 0.6;
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
        <div class="row">
            <div class="col-md-8">
                <h2>📊 Export Appointment Data</h2>
                <p class="text-muted">Export filtered appointment data to CSV format for analysis in Excel</p>
                
                <?php echo $error_message; ?>
                <?php echo $success_message; ?>
                
                <div class="card export-card">
                    <div class="card-header bg-success text-white">
                        <h5>Export Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="exportForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Start Date</label>
                                        <input type="text" 
                                               name="start_date" 
                                               class="form-control" 
                                               id="startDate"
                                               value="<?= htmlspecialchars($start_date_val); ?>"
                                               placeholder="Select start date">
                                        <small class="text-muted">Leave empty for all dates</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">End Date</label>
                                        <input type="text" 
                                               name="end_date" 
                                               class="form-control" 
                                               id="endDate"
                                               value="<?= htmlspecialchars($end_date_val); ?>"
                                               placeholder="Select end date">
                                        <small class="text-muted">Leave empty for all dates</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Appointment Status</label>
                                        <select name="status_filter" class="form-control" id="statusFilter">
                                            <option value="all" <?= $status_val === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                            <option value="booked" <?= $status_val === 'booked' ? 'selected' : ''; ?>>Booked</option>
                                            <option value="cancelled" <?= $status_val === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                            <option value="completed" <?= $status_val === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="no-show" <?= $status_val === 'no-show' ? 'selected' : ''; ?>>No-Show</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="stats-card mt-4">
                                        <h6>Export Ready</h6>
                                        <div id="recordCount"><?= $initial_count ?></div>
                                        <small>appointments match filters</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" name="export_appointments" value="1" class="btn btn-success btn-lg">
                                    Export to CSV
                                </button>
                                <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Preview Section -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5>Data Preview</h5>
                    </div>
                    <div class="card-body">
                        <div id="previewContent">
                            <?= $initial_preview ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5>About Export Feature</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Export includes:</strong></p>
                        <ul>
                            <li>Booking reference numbers</li>
                            <li>Patient contact information</li>
                            <li>Appointment dates and times</li>
                            <li>Visit reasons and status</li>
                            <li>Creation timestamps</li>
                            <li>Reminder status</li>
                        </ul>
                        
                        <div class="alert alert-success">
                            <small>
                                <strong>Perfect for:</strong>
                                <ul class="mt-2">
                                    <li>Monthly reports</li>
                                    <li>Attendance analysis</li>
                                    <li>No-show tracking</li>
                                    <li>Performance metrics</li>
                                </ul>
                            </small>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-body">
                        <h6>Export Tips</h6>
                        <ul class="small">
                            <li>Use date ranges for monthly reports</li>
                            <li>Filter by status to analyze no-shows</li>
                            <li>Export opens directly in Excel</li>
                            <li>File downloads within 3 seconds</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <script>
    // Initialize date pickers
    flatpickr("#startDate", {
        dateFormat: "Y-m-d",
        maxDate: "today"
    });
    
    flatpickr("#endDate", {
        dateFormat: "Y-m-d",
        maxDate: "today"
    });

    // Function to update record count and preview
    function updateExportPreview() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const statusFilter = document.getElementById('statusFilter').value;
        
        // Create URL parameters
        const params = new URLSearchParams();
        if (startDate) params.append('start_date', startDate);
        if (endDate) params.append('end_date', endDate);
        params.append('status_filter', statusFilter);
        params.append('ajax', '1');
        
        // Show loading state
        document.getElementById('recordCount').textContent = 'Loading...';
        document.getElementById('previewContent').classList.add('preview-loading');
        
        // Use GET request
        fetch('export.php?' + params.toString())
        .then(response => {
            // First check if response is JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                return response.text().then(text => {
                    throw new Error('Expected JSON but got: ' + text.substring(0, 100));
                });
            }
            return response.json();
        })
        .then(data => {
            document.getElementById('recordCount').textContent = data.count;
            document.getElementById('previewContent').innerHTML = data.preview;
            document.getElementById('previewContent').classList.remove('preview-loading');
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('recordCount').textContent = 'Error';
            document.getElementById('previewContent').innerHTML = 
                '<div class="alert alert-danger">Error: ' + error.message + '</div>';
            document.getElementById('previewContent').classList.remove('preview-loading');
        });
    }

    // Add event listeners for filter changes
    document.getElementById('startDate').addEventListener('change', updateExportPreview);
    document.getElementById('endDate').addEventListener('change', updateExportPreview);
    document.getElementById('statusFilter').addEventListener('change', updateExportPreview);

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Export page loaded successfully');
    });
    </script>
</body>
</html>