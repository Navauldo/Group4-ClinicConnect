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
        $login_success = false;
        
        // FIRST: Try password_verify() for hashed passwords (new accounts and demo accounts)
        if (password_verify($password, $user['password'])) {
            $login_success = true;
        }
        // SECOND: Check if this is a DEMO account trying to use 'password'
        // Only allow 'password' for demo accounts (email ends with @demo.com)
        elseif (strpos($user['email'], '@demo.com') !== false && $password === 'password') {
            $login_success = true;
        }
        // THIRD: Direct comparison for accounts that might have plain text passwords
        elseif ($password === $user['password']) {
            $login_success = true;
        }
        
        if ($login_success) {
            $_SESSION['user'] = $user;
            
            // Redirect based on role
            switch ($user['role']) {
                case 'patient':
                    header('Location: patientdashboard/index.php');
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
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            z-index: 10;
        }
        .input-group {
            position: relative;
        }
        .demo-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
            font-size: 0.9rem;
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
                        
                        <form method="POST" id="loginForm">
                            <input type="hidden" name="role" value="<?= $role ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input type="email" name="email" class="form-control" required 
                                           placeholder="Enter your email address"
                                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                                           id="emailInput">
                                </div>
                            </div>
                            
                            <div class="mb-3 position-relative">
                                <label class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" name="password" class="form-control" required 
                                           placeholder="Enter your password"
                                           id="passwordInput">
                                    <span class="password-toggle" onclick="togglePassword()">
                                        <i class="fas fa-eye" id="toggleIcon"></i>
                                    </span>
                                </div>
                                
                                <!-- Dynamic demo notice -->
                                <div id="demoNotice" class="demo-notice" style="display: none;">
                                    <i class="fas fa-info-circle text-warning"></i>
                                    <strong>Demo Account:</strong> Use "password" as your password
                                </div>
                                
                                <!-- Regular password hint -->
                                <div id="regularNotice" class="demo-notice" style="display: none; background: #e8f5e8; border-color: #c3e6cb;">
                                    <i class="fas fa-key text-success"></i>
                                    <strong>Regular Account:</strong> Use the password you created
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                                <label class="form-check-label" for="rememberMe">Remember me</label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100 btn-lg">
                                <i class="fas fa-sign-in-alt"></i> Login as <?= $role_display ?>
                            </button>
                        </form>
                        
                        <?php if ($role == 'patient'): ?>
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                Don't have an account? 
                                <a href="index.php#register" class="text-decoration-none">Create one now</a>
                            </small>
                        </div>
                        <?php endif; ?>
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
                
                <!-- Help Section -->
                <div class="card mt-4">
                    <div class="card-body text-center">
                        <h6><i class="fas fa-question-circle"></i> Need Help?</h6>
                        <p class="mb-0">
                            <small class="text-muted">
                                Forgot password? Contact clinic administration at <strong>support@clinicconnect.com</strong>
                            </small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Toggle password visibility
    function togglePassword() {
        const passwordInput = document.getElementById('passwordInput');
        const toggleIcon = document.getElementById('toggleIcon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }
    
    // Check if email is a demo account
    function checkAccountType() {
        const email = document.getElementById('emailInput').value;
        const demoNotice = document.getElementById('demoNotice');
        const regularNotice = document.getElementById('regularNotice');
        
        if (email.includes('@demo.com')) {
            demoNotice.style.display = 'block';
            regularNotice.style.display = 'none';
        } else if (email.includes('@')) {
            demoNotice.style.display = 'none';
            regularNotice.style.display = 'block';
        } else {
            demoNotice.style.display = 'none';
            regularNotice.style.display = 'none';
        }
    }
    
    // Form validation
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('loginForm');
        const emailInput = document.getElementById('emailInput');
        
        // Check account type when email changes
        emailInput.addEventListener('input', checkAccountType);
        emailInput.addEventListener('blur', checkAccountType);
        
        form.addEventListener('submit', function(event) {
            const email = form.querySelector('input[name="email"]').value;
            const password = form.querySelector('input[name="password"]').value;
            
            // Basic validation
            if (!email.includes('@')) {
                event.preventDefault();
                alert('Please enter a valid email address.');
                return;
            }
            
            if (password.length < 1) {
                event.preventDefault();
                alert('Please enter your password.');
                return;
            }
            
            // Special check for demo accounts
            if (email.includes('@demo.com') && password === 'password') {
                if (!confirm('You are using the demo password. Click OK to continue or Cancel to enter a different password.')) {
                    event.preventDefault();
                    return;
                }
            }
        });
        
        // Auto-focus email field
        if (emailInput.value === '') {
            emailInput.focus();
        }
        
        // Check if there's a remembered email
        const rememberedEmail = localStorage.getItem('clinicconnect_remembered_email');
        if (rememberedEmail && emailInput.value === '') {
            emailInput.value = rememberedEmail;
            checkAccountType();
        }
        
        // Handle remember me
        const rememberCheckbox = document.getElementById('rememberMe');
        rememberCheckbox.addEventListener('change', function() {
            if (this.checked && emailInput.value) {
                localStorage.setItem('clinicconnect_remembered_email', emailInput.value);
            } else {
                localStorage.removeItem('clinicconnect_remembered_email');
            }
        });
        
        // Initial check
        checkAccountType();
    });
    </script>
</body>
</html>