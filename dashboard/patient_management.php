<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/../includes/config.php';

$success_message = "";
$error_message = "";
$search_results = [];
$search_performed = false;
$patient_to_edit = null;

// Handle patient search
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search_patient'])) {
    $search_name = trim($_POST['search_name']);
    
    if (!empty($search_name)) {
        $search_performed = true;
        
        try {
            // Search for patients by name in appointments table
            $stmt = $pdo->prepare("
                SELECT DISTINCT 
                    patient_name,
                    patient_phone,
                    patient_email,
                    COUNT(*) as appointment_count,
                    MAX(appointment_date) as last_appointment
                FROM appointments 
                WHERE patient_name LIKE ? 
                GROUP BY patient_name, patient_phone, patient_email
                ORDER BY patient_name
            ");
            $stmt->execute(["%$search_name%"]);
            $search_results = $stmt->fetchAll();
            
            if (count($search_results) === 0) {
                $error_message = "<div class='alert alert-warning'>No patients found matching '$search_name'.</div>";
            }
        } catch(PDOException $e) {
            $error_message = "<div class='alert alert-danger'><strong>❌ Search Error:</strong> " . $e->getMessage() . "</div>";
        }
    } else {
        $error_message = "<div class='alert alert-warning'>Please enter a patient name to search.</div>";
    }
}

// Handle patient editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_patient'])) {
    $patient_name = trim($_POST['patient_name']);
    $new_phone = trim($_POST['patient_phone']);
    $new_email = trim($_POST['patient_email']);
    
    // Validate phone number format
    $clean_phone = preg_replace('/[^0-9]/', '', $new_phone);
    if (strlen($clean_phone) < 7 || strlen($clean_phone) > 15) {
        $error_message = "<div class='alert alert-danger'><strong>❌ Validation Error:</strong> Please enter a valid phone number (7-15 digits).</div>";
    } else {
        // Validate email format if provided
        if (!empty($new_email) && !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "<div class='alert alert-danger'><strong>❌ Validation Error:</strong> Please enter a valid email address.</div>";
        } else {
            try {
                // Update all appointments for this patient with the new contact information
                $stmt = $pdo->prepare("
                    UPDATE appointments 
                    SET patient_phone = ?, patient_email = ?
                    WHERE patient_name = ?
                ");
                $stmt->execute([$new_phone, $new_email, $patient_name]);
                
                $affected_rows = $stmt->rowCount();
                
                $success_message = "<div class='alert alert-success'><strong>✅ Contact Information Updated!</strong><br>";
                $success_message .= "Updated contact information for patient: <strong>$patient_name</strong><br>";
                $success_message .= "<small>Affected $affected_rows appointment(s). Future reminders will use the updated contact information.</small></div>";
                
                // Clear search results to show updated data
                $search_results = [];
                $search_performed = false;
                
            } catch(PDOException $e) {
                $error_message = "<div class='alert alert-danger'><strong>❌ Update Error:</strong> " . $e->getMessage() . "</div>";
            }
        }
    }
}

// Handle edit request
if (isset($_GET['edit'])) {
    $patient_name = urldecode($_GET['edit']);
    
    try {
        // Get the most recent contact info for this patient
        $stmt = $pdo->prepare("
            SELECT DISTINCT patient_name, patient_phone, patient_email
            FROM appointments 
            WHERE patient_name = ?
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$patient_name]);
        $patient_to_edit = $stmt->fetch();
        
        if (!$patient_to_edit) {
            $error_message = "<div class='alert alert-danger'>Patient not found.</div>";
        }
    } catch(PDOException $e) {
        $error_message = "<div class='alert alert-danger'><strong>❌ Error:</strong> " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Patient Contact Information - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .patient-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s;
        }
        .patient-card:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }
        .search-card {
            border-left: 4px solid #28a745;
        }
        .edit-form-card {
            border-left: 4px solid #ffc107;
        }
        .contact-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
            margin-right: 5px;
        }
        .stats-card {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .appointment-count {
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-arrow-left"></i> ClinicConnect Staff Dashboard
            </a>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h2><i class="fas fa-address-book"></i> Manage Patient Contact Information</h2>
                <p class="text-muted">Search for patients and update their contact information for appointment reminders</p>
                
                <?php echo $error_message; ?>
                <?php echo $success_message; ?>
                
                <!-- Search Card -->
                <div class="card search-card shadow-sm mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-search"></i> Search for Patient
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="searchForm">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Patient Name</label>
                                        <input type="text" 
                                               name="search_name" 
                                               class="form-control" 
                                               placeholder="Enter patient name (full or partial)"
                                               value="<?= isset($_POST['search_name']) ? htmlspecialchars($_POST['search_name']) : '' ?>"
                                               required>
                                        <small class="text-muted">Search by patient name to find and update contact information</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stats-card mt-4">
                                        <h6>Ready to Search</h6>
                                        <div class="display-6 fw-bold">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <small>Find patient records</small>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" name="search_patient" value="1" class="btn btn-success btn-lg">
                                <i class="fas fa-search"></i> Search Patients
                            </button>
                            <a href="index.php" class="btn btn-secondary">← Back to Dashboard</a>
                        </form>
                    </div>
                </div>

                <!-- Edit Form Card (shown when editing) -->
                <?php if ($patient_to_edit): ?>
                <div class="card edit-form-card shadow-sm mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-edit"></i> Edit Contact Information for <?= htmlspecialchars($patient_to_edit['patient_name']) ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="editForm">
                            <input type="hidden" name="patient_name" value="<?= htmlspecialchars($patient_to_edit['patient_name']) ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Patient Name</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($patient_to_edit['patient_name']) ?>" readonly>
                                        <small class="text-muted">Patient name cannot be changed</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Phone Number *</label>
                                        <input type="tel" 
                                               name="patient_phone" 
                                               class="form-control" 
                                               value="<?= htmlspecialchars($patient_to_edit['patient_phone']) ?>" 
                                               required
                                               pattern="[0-9+\-\s()]{7,15}">
                                        <small class="text-muted">Required for SMS reminders (7-15 digits)</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Email Address</label>
                                <input type="email" 
                                       name="patient_email" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($patient_to_edit['patient_email']) ?>"
                                       pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$">
                                <small class="text-muted">Optional - for email reminders</small>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Note:</strong> This will update contact information across all appointments for this patient. Future reminder notifications will use the updated information.
                            </div>
                            
                            <button type="submit" name="update_patient" value="1" class="btn btn-warning btn-lg">
                                <i class="fas fa-save"></i> Update Contact Information
                            </button>
                            <a href="patient_management.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Search Results -->
                <?php if ($search_performed && count($search_results) > 0): ?>
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-users"></i> Search Results
                            </h5>
                            <span class="badge bg-light text-dark fs-6">
                                <?= count($search_results) ?> patient(s) found
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($search_results as $patient): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card patient-card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas fa-user"></i>
                                            <?= htmlspecialchars($patient['patient_name']) ?>
                                        </h6>
                                        
                                        <div class="patient-info mb-3">
                                            <?php if (!empty($patient['patient_phone'])): ?>
                                                <span class="badge bg-primary contact-badge">
                                                    <i class="fas fa-phone"></i>
                                                    <?= htmlspecialchars($patient['patient_phone']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary contact-badge">
                                                    <i class="fas fa-phone-slash"></i>
                                                    No phone
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($patient['patient_email'])): ?>
                                                <span class="badge bg-info contact-badge">
                                                    <i class="fas fa-envelope"></i>
                                                    <?= htmlspecialchars($patient['patient_email']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary contact-badge">
                                                    <i class="fas fa-envelope"></i>
                                                    No email
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="appointment-count mb-3">
                                            <small>
                                                <i class="fas fa-calendar-check"></i>
                                                Total appointments: <?= $patient['appointment_count'] ?>
                                                <?php if (!empty($patient['last_appointment'])): ?>
                                                    <br><i class="fas fa-clock"></i>
                                                    Last appointment: <?= date('M j, Y', strtotime($patient['last_appointment'])) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                Click edit to update contact info
                                            </small>
                                            <a href="?edit=<?= urlencode($patient['patient_name']) ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php elseif ($search_performed): ?>
                <div class="card">
                    <div class="card-body text-center py-4">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5>No Patients Found</h5>
                        <p class="text-muted">No patients match your search criteria. Try a different name or check the spelling.</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Real-time phone number validation
    document.addEventListener('DOMContentLoaded', function() {
        const phoneInput = document.querySelector('input[name="patient_phone"]');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                // Remove any non-digit characters except +, -, (, ), and spaces
                this.value = this.value.replace(/[^0-9+\-\s()]/g, '');
            });
        }

        // Form validation for edit form
        const editForm = document.getElementById('editForm');
        if (editForm) {
            editForm.addEventListener('submit', function(event) {
                const phoneInput = document.querySelector('input[name="patient_phone"]');
                const cleanPhone = phoneInput.value.replace(/[^0-9]/g, '');
                
                if (cleanPhone.length < 7 || cleanPhone.length > 15) {
                    event.preventDefault();
                    alert('Please enter a valid phone number (7-15 digits).');
                    phoneInput.focus();
                    return;
                }
                
                const emailInput = document.querySelector('input[name="patient_email"]');
                if (emailInput.value && !isValidEmail(emailInput.value)) {
                    event.preventDefault();
                    alert('Please enter a valid email address or leave the field empty.');
                    emailInput.focus();
                    return;
                }
            });
        }

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
    });
    </script>
</body>
</html>