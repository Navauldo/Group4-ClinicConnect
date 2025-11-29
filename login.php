<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'includes/config.php';

// Get role from URL parameter
$role = $_GET['role'] ?? 'patient';
$role_display = ucfirst($role);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $user_role = $_POST['role'];
    
    // Validate credentials
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
    $stmt->execute([$email, $user_role]);
    $user = $stmt->fetch();
    
    if ($user) {
        // For demo purposes, all passwords are "password"
        // In a real system, you would use password_verify()
        if ($password === 'password') {
            $_SESSION['user'] = $user;
            
            // Redirect based on role
            switch ($user['role']) {
                case 'patient':
                    header('Location: booking/index.php');
                    break;
                case 'staff':
                    header('Location: dashboard/index.php');
                    break;
                case 'admin':
                    header('Location: admindashboard/index.php');
                    break;
                default:
                    header('Location: index.php');
            }
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "Invalid email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .login-card {
            border: 2px solid #007bff;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,123,255,0.2);
        }
        .role-badge {
            font-size: 0.9rem;
            padding: 8px 15px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-arrow-left"></i> Back to ClinicConnect
            </a>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card login-card">
                    <div class="card-header bg-primary text-white text-center">
                        <h4>
                            <?php 
                            $icons = [
                                'patient' => 'fas fa-user',
                                'staff' => 'fas fa-user-nurse', 
                                'admin' => 'fas fa-user-shield'
                            ];
                            ?>
                            <i class="<?= $icons[$role] ?>"></i> 
                            <?= $role_display ?> Login
                        </h4>
                        <span class="badge role-badge bg-light text-dark">
                            <?= $role_display ?> Access
                        </span>
                    </div>
                    <div class="card-body p-4">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <input type="hidden" name="role" value="<?= $role ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" name="email" class="form-control" required 
                                           placeholder="Enter your email address">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" name="password" class="form-control" required 
                                           placeholder="Enter your password">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 btn-lg">
                                <i class="fas fa-sign-in-alt"></i> Login as <?= $role_display ?>
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Role Selection -->
                <div class="text-center mt-4">
                    <p>Need a different role?</p>
                    <div class="btn-group">
                        <a href="login.php?role=patient" class="btn btn-outline-primary <?= $role == 'patient' ? 'active' : '' ?>">
                            Patient
                        </a>
                        <a href="login.php?role=staff" class="btn btn-outline-success <?= $role == 'staff' ? 'active' : '' ?>">
                            Staff
                        </a>
                        <a href="login.php?role=admin" class="btn btn-outline-warning <?= $role == 'admin' ? 'active' : '' ?>">
                            Admin
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>