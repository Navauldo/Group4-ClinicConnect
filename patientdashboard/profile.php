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
$user_id = $user['id'];
$success_message = "";
$error_message = "";

// Handle profile update (name and email only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "<div class='alert alert-danger'>Please enter a valid email address.</div>";
    } else {
        // Check if email is already taken by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $error_message = "<div class='alert alert-danger'>This email is already registered to another account.</div>";
        } else {
            try {
                // Update basic info
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $email, $user_id]);
                
                // Update appointments with new email
                $stmt = $pdo->prepare("UPDATE appointments SET patient_email = ? WHERE patient_email = ?");
                $stmt->execute([$email, $user['email']]);
                
                // Update session
                $_SESSION['user']['name'] = $name;
                $_SESSION['user']['email'] = $email;
                
                $success_message = "<div class='alert alert-success'><strong>✅ Profile Updated Successfully!</strong></div>";
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $_SESSION['user'] = $stmt->fetch();
                
            } catch (Exception $e) {
                $error_message = "<div class='alert alert-danger'><strong>❌ Error:</strong> " . $e->getMessage() . "</div>";
            }
        }
    }
}

// Handle password change (separate form) - FIXED VERSION
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // PREVENT PASSWORD CHANGES FOR DEMO ACCOUNTS
    if (strpos($user['email'], '@demo.com') !== false) {
        $error_message = "<div class='alert alert-warning'><strong>⚠️ Demo Account Restriction:</strong> Password changes are not allowed for demo accounts.</div>";
    } else {
        // Validate
        if (empty($current_password)) {
            $error_message = "<div class='alert alert-danger'>Please enter your current password.</div>";
        } elseif (empty($new_password)) {
            $error_message = "<div class='alert alert-danger'>Please enter a new password.</div>";
        } elseif (strlen($new_password) < 6) {
            $error_message = "<div class='alert alert-danger'>New password must be at least 6 characters.</div>";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "<div class='alert alert-danger'>New passwords do not match.</div>";
        } else {
            try {
                // 1. Get the CURRENT hashed password from database
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $db_user = $stmt->fetch();
                
                if (!$db_user) {
                    throw new Exception("User not found.");
                }
                
                $db_hashed_password = $db_user['password'];
                
                // 2. VERIFY current password against database hash
                if (!password_verify($current_password, $db_hashed_password)) {
                    throw new Exception("Current password is incorrect.");
                }
                
                // 3. Hash the NEW password
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // 4. Update password in database
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$new_hashed_password, $user_id]);
                
                $success_message = "<div class='alert alert-success'><strong>✅ Password Changed Successfully!</strong></div>";
                
            } catch (Exception $e) {
                $error_message = "<div class='alert alert-danger'><strong>❌ Error:</strong> " . $e->getMessage() . "</div>";
            }
        }
    }
}

// Get user's appointment statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_appointments,
        SUM(CASE WHEN appointment_date >= CURDATE() AND status = 'booked' THEN 1 ELSE 0 END) as upcoming,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM appointments 
    WHERE patient_email = ?
");
$stmt->execute([$user['email']]);
$stats = $stmt->fetch();

// Get recent appointments
$stmt = $pdo->prepare("
    SELECT * FROM appointments 
    WHERE patient_email = ? 
    ORDER BY appointment_date DESC 
    LIMIT 5
");
$stmt->execute([$user['email']]);
$recent_appointments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #667eea;
            margin: 0 auto 20px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 15px;
        }
        .stat-card h3 {
            color: #667eea;
            margin-bottom: 5px;
        }
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .info-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-icon {
            width: 40px;
            color: #667eea;
            font-size: 1.2rem;
        }
        .password-strength {
            height: 5px;
            background: #eee;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s;
        }
        .strength-weak { background: #dc3545; }
        .strength-medium { background: #ffc107; }
        .strength-strong { background: #28a745; }
        .demo-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .form-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #dee2e6;
        }
        .form-section h5 {
            color: #495057;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
        }
        .help-text {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
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
        <!-- Profile Header -->
        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-3 text-center">
                    <div class="profile-avatar">
                        <i class="fas fa-user-md"></i>
                    </div>
                </div>
                <div class="col-md-9">
                    <h1><?= htmlspecialchars($user['name']) ?></h1>
                    <p class="lead mb-0"><?= htmlspecialchars($user['email']) ?></p>
                    <p class="mb-0">Patient since <?= date('F Y', strtotime($user['created_at'])) ?></p>
                    <?php if (strpos($user['email'], '@demo.com') !== false): ?>
                    <span class="badge bg-warning mt-2">
                        <i class="fas fa-vial"></i> Demo Account
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Display Messages -->
        <?php if (!empty($error_message)): ?>
            <?= $error_message ?>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <?= $success_message ?>
        <?php endif; ?>
        
        <!-- Demo Account Warning -->
        <?php if (strpos($user['email'], '@demo.com') !== false): ?>
        <div class="demo-warning">
            <h5><i class="fas fa-exclamation-triangle"></i> Demo Account Notice</h5>
            <p class="mb-0">This is a demo account. Password changes are disabled. You can update your name and email, but the password will remain "password".</p>
        </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <h3><?= $stats['total_appointments'] ?? 0 ?></h3>
                    <p>Total Appointments</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <h3><?= $stats['upcoming'] ?? 0 ?></h3>
                    <p>Upcoming</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <h3><?= $stats['completed'] ?? 0 ?></h3>
                    <p>Completed</p>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <h3><?= $stats['cancelled'] ?? 0 ?></h3>
                    <p>Cancelled</p>
                </div>
            </div>
        </div>
        
        <!-- Profile Update Forms -->
        <div class="row">
            <div class="col-lg-8">
                <!-- Update Profile Form -->
                <div class="form-section">
                    <h5><i class="fas fa-user-edit"></i> Update Personal Information</h5>
                    <form method="POST" id="profileForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" name="name" class="form-control" 
                                           value="<?= htmlspecialchars($user['name']) ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Email Address *</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?= htmlspecialchars($user['email']) ?>" required>
                                    <small class="text-muted">We'll use this for appointment reminders</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" name="update_profile" value="1" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Change Password Form -->
                <div class="form-section">
                    <h5><i class="fas fa-key"></i> Change Password</h5>
                    <form method="POST" id="passwordForm">
                        <?php if (strpos($user['email'], '@demo.com') !== false): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-ban"></i>
                            <strong>Password changes disabled:</strong> Demo accounts cannot change passwords.
                        </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Current Password *</label>
                                    <input type="password" name="current_password" class="form-control" 
                                           placeholder="Enter current password"
                                           <?= strpos($user['email'], '@demo.com') !== false ? 'disabled' : '' ?>>
                                    <div class="help-text">
                                        Enter the exact password you used when registering
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">New Password *</label>
                                    <input type="password" name="new_password" id="newPassword" class="form-control" 
                                           placeholder="Enter new password" 
                                           oninput="checkPasswordStrength()"
                                           <?= strpos($user['email'], '@demo.com') !== false ? 'disabled' : '' ?>>
                                    <div class="password-strength">
                                        <div class="password-strength-bar" id="passwordStrengthBar"></div>
                                    </div>
                                    <small id="passwordStrengthText" class="form-text"></small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Confirm Password *</label>
                                    <input type="password" name="confirm_password" class="form-control" 
                                           placeholder="Confirm new password"
                                           <?= strpos($user['email'], '@demo.com') !== false ? 'disabled' : '' ?>>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-lightbulb"></i>
                            <strong>Password Requirements:</strong>
                            <ul class="mb-0">
                                <li>At least 6 characters long</li>
                                <li>Include uppercase and lowercase letters</li>
                                <li>Include at least one number</li>
                            </ul>
                        </div>
                        
                        <div class="mt-3">
                            <button type="submit" name="change_password" value="1" class="btn btn-primary"
                                    <?= strpos($user['email'], '@demo.com') !== false ? 'disabled' : '' ?>>
                                <i class="fas fa-key"></i> Change Password
                            </button>
                            <button type="reset" class="btn btn-secondary">Reset</button>
                            <?php if (strpos($user['email'], '@demo.com') !== false): ?>
                            <small class="text-muted ms-2">Password changes disabled for demo accounts</small>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Account Information Sidebar -->
            <div class="col-lg-4">
                <div class="info-card">
                    <h5><i class="fas fa-info-circle"></i> Account Information</h5>
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <div>
                            <strong>User ID</strong><br>
                            <?= $user['id'] ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div>
                            <strong>Member Since</strong><br>
                            <?= date('F j, Y', strtotime($user['created_at'])) ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-user-tag"></i>
                        </div>
                        <div>
                            <strong>Account Type</strong><br>
                            <span class="badge bg-primary">Patient</span>
                            <?php if (strpos($user['email'], '@demo.com') !== false): ?>
                            <span class="badge bg-warning ms-1">Demo</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <strong>Last Updated</strong><br>
                            <?= date('F j, Y g:i A', strtotime($user['updated_at'])) ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div>
                            <strong>Account Status</strong><br>
                            <span class="badge bg-success">Active</span>
                        </div>
                    </div>
                </div>
                
                <!-- Security Tips -->
                <div class="info-card mt-4">
                    <h5><i class="fas fa-shield-alt"></i> Security Tips</h5>
                    <ul class="mb-0">
                        <li>Use a unique password for this account</li>
                        <li>Never share your password with anyone</li>
                        <li>Log out after using public computers</li>
                        <li>Regularly update your password</li>
                        <li>Use a password manager for security</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-history"></i> Recent Appointments
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_appointments)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No appointment history found.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_appointments as $appt): ?>
                                <tr>
                                    <td><?= date('M j, Y', strtotime($appt['appointment_date'])) ?></td>
                                    <td><?= date('g:i A', strtotime($appt['appointment_time'])) ?></td>
                                    <td><?= htmlspecialchars($appt['reason'] ?: 'Checkup') ?></td>
                                    <td>
                                        <span class="badge bg-<?= 
                                            $appt['status'] == 'booked' ? 'primary' : 
                                            ($appt['status'] == 'cancelled' ? 'danger' : 
                                            ($appt['status'] == 'completed' ? 'success' : 'warning')) 
                                        ?>">
                                            <?= ucfirst($appt['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view.php?ref=<?= $appt['booking_reference'] ?>" class="text-decoration-none">
                                            <?= $appt['booking_reference'] ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center">
                        <a href="history.php" class="btn btn-outline-primary">
                            <i class="fas fa-history"></i> View Full History
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function checkPasswordStrength() {
        const password = document.getElementById('newPassword').value;
        const strengthBar = document.getElementById('passwordStrengthBar');
        const strengthText = document.getElementById('passwordStrengthText');
        
        let strength = 0;
        let text = '';
        let color = '';
        
        if (password.length >= 6) strength++;
        if (password.length >= 8) strength++;
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        if (strength === 0) {
            text = 'Very Weak';
            color = 'strength-weak';
            width = '25%';
        } else if (strength === 1) {
            text = 'Weak';
            color = 'strength-weak';
            width = '25%';
        } else if (strength === 2) {
            text = 'Medium';
            color = 'strength-medium';
            width = '50%';
        } else if (strength === 3) {
            text = 'Strong';
            color = 'strength-strong';
            width = '75%';
        } else {
            text = 'Very Strong';
            color = 'strength-strong';
            width = '100%';
        }
        
        strengthBar.className = `password-strength-bar ${color}`;
        strengthBar.style.width = width;
        strengthText.textContent = `Password Strength: ${text}`;
        strengthText.className = `form-text ${color === 'strength-weak' ? 'text-danger' : color === 'strength-medium' ? 'text-warning' : 'text-success'}`;
    }
    
    // Form validation
    document.addEventListener('DOMContentLoaded', function() {
        const passwordForm = document.getElementById('passwordForm');
        
        passwordForm.addEventListener('submit', function(event) {
            // Skip if disabled (demo account)
            if (document.querySelector('input[name="current_password"]').disabled) {
                event.preventDefault();
                return;
            }
            
            const currentPassword = document.querySelector('input[name="current_password"]').value;
            const newPassword = document.querySelector('input[name="new_password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            
            if (!currentPassword) {
                event.preventDefault();
                alert('Please enter your current password.');
                return;
            }
            
            if (newPassword.length < 6) {
                event.preventDefault();
                alert('New password must be at least 6 characters long.');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                event.preventDefault();
                alert('New passwords do not match.');
                return;
            }
            
            if (!confirm('Are you sure you want to change your password?')) {
                event.preventDefault();
                return;
            }
        });
        
        // Profile form validation
        const profileForm = document.getElementById('profileForm');
        profileForm.addEventListener('submit', function(event) {
            const email = document.querySelector('input[name="email"]').value;
            
            if (!email.includes('@')) {
                event.preventDefault();
                alert('Please enter a valid email address.');
                return;
            }
            
            if (!confirm('Are you sure you want to update your profile information?')) {
                event.preventDefault();
                return;
            }
        });
    });
    </script>
</body>
</html>
