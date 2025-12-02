<?php
// TURN ON ERROR REPORTING
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'includes/config.php';

// Handle patient registration
$registration_success = "";
$registration_error = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_patient'])) {
    $name = trim($_POST['patient_name']);
    $email = trim($_POST['patient_email']);
    $password = $_POST['patient_password'];
    $confirm_password = $_POST['patient_confirm_password'];
    
    // Validation
    $errors = [];
    
    // Validate name
    if (empty($name)) {
        $errors[] = "Name is required.";
    } elseif (strlen($name) < 2) {
        $errors[] = "Name must be at least 2 characters.";
    }
    
    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    } elseif (!strpos($email, '@')) {
        $errors[] = "Email must contain '@' symbol.";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "This email is already registered.";
        }
    }
    
    // Validate password
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    // If no errors, create account
    if (empty($errors)) {
        try {
            // For demo, we'll store plain text password
            // In production, use: $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (email, password, name, role, created_at, updated_at) 
                                   VALUES (?, ?, ?, 'patient', NOW(), NOW())");
            $stmt->execute([$email, $password, $name]);
            
            $registration_success = "<div class='alert alert-success'>
                <strong>✅ Account Created Successfully!</strong><br>
                You can now login with your email and password.
            </div>";
            
            // Clear form
            $_POST = [];
            
        } catch (PDOException $e) {
            $registration_error = "<div class='alert alert-danger'>
                <strong>❌ Registration Error:</strong> " . $e->getMessage() . "
            </div>";
        }
    } else {
        $registration_error = "<div class='alert alert-danger'><strong>❌ Registration Errors:</strong><br>" . 
                            implode('<br>', $errors) . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClinicConnect - Medical Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        .registration-card {
            border: 2px solid #007bff;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,123,255,0.2);
            transition: transform 0.3s;
        }
        .registration-card:hover {
            transform: translateY(-5px);
        }
        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            color: #007bff;
        }
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
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
        .benefits-list {
            list-style: none;
            padding-left: 0;
        }
        .benefits-list li {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .benefits-list li:last-child {
            border-bottom: none;
        }
        .benefits-list i {
            color: #28a745;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-hospital"></i> ClinicConnect
            </a>
            <div class="navbar-nav ms-auto">
                <a href="login.php?role=patient" class="nav-link text-light">
                    <i class="fas fa-sign-in-alt"></i> Patient Login
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Hero Section -->
        <div class="hero-section text-center">
            <h1 class="display-4">Welcome to ClinicConnect! 🏥</h1>
            <p class="lead">Manage your medical appointments with ease and convenience</p>
            <div class="mt-4">
                <a href="#register" class="btn btn-light btn-lg me-3">
                    <i class="fas fa-user-plus"></i> Create Free Account
                </a>
                <a href="login.php?role=patient" class="btn btn-outline-light btn-lg me-3">
                    <i class="fas fa-sign-in-alt"></i> Patient Login
                </a>
                <a href="#features" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-info-circle"></i> Learn More
                </a>
            </div>
        </div>

        <!-- Registration and Login Section -->
        <div class="row mt-5" id="register">
            <!-- Registration Form -->
            <div class="col-lg-6 mb-4">
                <div class="card registration-card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-user-plus"></i> Create Patient Account
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php echo $registration_error; ?>
                        <?php echo $registration_success; ?>
                        
                        <form method="POST" id="registrationForm">
                            <div class="form-floating mb-3">
                                <input type="text" 
                                       class="form-control" 
                                       id="patient_name" 
                                       name="patient_name" 
                                       placeholder="John Doe"
                                       value="<?= htmlspecialchars($_POST['patient_name'] ?? '') ?>"
                                       required>
                                <label for="patient_name">
                                    <i class="fas fa-user"></i> Full Name *
                                </label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="email" 
                                       class="form-control" 
                                       id="patient_email" 
                                       name="patient_email" 
                                       placeholder="name@example.com"
                                       value="<?= htmlspecialchars($_POST['patient_email'] ?? '') ?>"
                                       required>
                                <label for="patient_email">
                                    <i class="fas fa-envelope"></i> Email Address *
                                </label>
                                <div class="invalid-feedback" id="emailFeedback">
                                    Please enter a valid email address.
                                </div>
                            </div>
                            
                            <div class="form-floating mb-3 position-relative">
                                <input type="password" 
                                       class="form-control" 
                                       id="patient_password" 
                                       name="patient_password" 
                                       placeholder="Password"
                                       required
                                       oninput="checkPasswordStrength()">
                                <label for="patient_password">
                                    <i class="fas fa-lock"></i> Password *
                                </label>
                                <span class="password-toggle" onclick="togglePassword('patient_password', 'toggleIcon1')">
                                    <i class="fas fa-eye" id="toggleIcon1"></i>
                                </span>
                                <div class="password-strength">
                                    <div class="password-strength-bar" id="passwordStrengthBar"></div>
                                </div>
                                <small id="passwordStrengthText" class="form-text"></small>
                                <div class="form-text">
                                    Password must be at least 6 characters long.
                                </div>
                            </div>
                            
                            <div class="form-floating mb-3 position-relative">
                                <input type="password" 
                                       class="form-control" 
                                       id="patient_confirm_password" 
                                       name="patient_confirm_password" 
                                       placeholder="Confirm Password"
                                       required>
                                <label for="patient_confirm_password">
                                    <i class="fas fa-lock"></i> Confirm Password *
                                </label>
                                <span class="password-toggle" onclick="togglePassword('patient_confirm_password', 'toggleIcon2')">
                                    <i class="fas fa-eye" id="toggleIcon2"></i>
                                </span>
                                <div class="invalid-feedback" id="confirmPasswordFeedback">
                                    Passwords do not match.
                                </div>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms of Service</a> and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                                </label>
                            </div>
                            
                            <button type="submit" name="register_patient" value="1" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-user-plus"></i> Create Account
                            </button>
                            
                            <div class="text-center mt-3">
                                <small class="text-muted">
                                    Already have an account? 
                                    <a href="login.php?role=patient" class="text-decoration-none">Login here</a>
                                </small>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Benefits & Quick Login -->
            <div class="col-lg-6">
                <!-- Benefits Card -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-star"></i> Benefits of Creating an Account
                        </h4>
                    </div>
                    <div class="card-body">
                        <ul class="benefits-list">
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <strong>24/7 Appointment Booking</strong> - Book anytime, anywhere
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <strong>Easy Rescheduling</strong> - Change appointments with one click
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <strong>Appointment History</strong> - Track all your past visits
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <strong>Reminder Notifications</strong> - Get SMS/Email reminders
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <strong>Quick Check-in</strong> - Use QR codes for faster service
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <strong>Secure & Private</strong> - Your health data is protected
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Quick Login -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-bolt"></i> Quick Access
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <a href="login.php?role=patient" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-user"></i> Patient Login
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="login.php?role=staff" class="btn btn-outline-success w-100">
                                    <i class="fas fa-user-nurse"></i> Staff Login
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="login.php?role=admin" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-user-shield"></i> Admin Login
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="booking/index.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-calendar"></i> Book as Guest
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Features Section -->
        <div class="row mt-5" id="features">
            <div class="col-12">
                <h2 class="text-center mb-4">
                    <i class="fas fa-cogs"></i> How ClinicConnect Works
                </h2>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <div class="mb-3">
                            <i class="fas fa-user-plus fa-3x text-primary"></i>
                        </div>
                        <h4>1. Create Account</h4>
                        <p>Sign up in seconds with your name and email. It's completely free!</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <div class="mb-3">
                            <i class="fas fa-calendar-plus fa-3x text-success"></i>
                        </div>
                        <h4>2. Book Appointments</h4>
                        <p>Choose your preferred date and time from available slots.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card text-center h-100">
                    <div class="card-body">
                        <div class="mb-3">
                            <i class="fas fa-bell fa-3x text-warning"></i>
                        </div>
                        <h4>3. Get Reminders</h4>
                        <p>Receive automatic reminders via SMS and email.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Terms of Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Acceptance of Terms</h6>
                    <p>By creating an account, you agree to these terms of service.</p>
                    
                    <h6>2. Account Responsibility</h6>
                    <p>You are responsible for maintaining the confidentiality of your account.</p>
                    
                    <h6>3. Appointment Policy</h6>
                    <p>Appointments must be cancelled at least 2 hours in advance.</p>
                    
                    <h6>4. Privacy</h6>
                    <p>Your personal information will be protected according to our privacy policy.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Privacy Modal -->
    <div class="modal fade" id="privacyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Privacy Policy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Information Collection</h6>
                    <p>We collect only necessary information for appointment management.</p>
                    
                    <h6>2. Data Usage</h6>
                    <p>Your data is used solely for clinic appointment purposes.</p>
                    
                    <h6>3. Data Protection</h6>
                    <p>We implement security measures to protect your information.</p>
                    
                    <h6>4. Communication</h6>
                    <p>We may contact you regarding your appointments via email or SMS.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Toggle password visibility
    function togglePassword(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    
    // Check password strength
    function checkPasswordStrength() {
        const password = document.getElementById('patient_password').value;
        const strengthBar = document.getElementById('passwordStrengthBar');
        const strengthText = document.getElementById('passwordStrengthText');
        const confirmPassword = document.getElementById('patient_confirm_password');
        
        let strength = 0;
        let text = '';
        let color = '';
        
        // Length check
        if (password.length >= 6) strength++;
        if (password.length >= 8) strength++;
        
        // Complexity checks
        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        // Determine strength level
        if (strength <= 1) {
            text = 'Weak';
            color = 'strength-weak';
            width = '25%';
        } else if (strength <= 3) {
            text = 'Medium';
            color = 'strength-medium';
            width = '50%';
        } else {
            text = 'Strong';
            color = 'strength-strong';
            width = '100%';
        }
        
        // Update UI
        strengthBar.className = `password-strength-bar ${color}`;
        strengthBar.style.width = width;
        strengthText.textContent = `Password Strength: ${text}`;
        strengthText.className = `form-text ${color === 'strength-weak' ? 'text-danger' : color === 'strength-medium' ? 'text-warning' : 'text-success'}`;
        
        // Real-time password confirmation check
        if (confirmPassword.value) {
            checkPasswordMatch();
        }
    }
    
    // Check if passwords match
    function checkPasswordMatch() {
        const password = document.getElementById('patient_password').value;
        const confirmPassword = document.getElementById('patient_confirm_password').value;
        const feedback = document.getElementById('confirmPasswordFeedback');
        const confirmInput = document.getElementById('patient_confirm_password');
        
        if (password !== confirmPassword && confirmPassword.length > 0) {
            confirmInput.classList.add('is-invalid');
            feedback.style.display = 'block';
        } else {
            confirmInput.classList.remove('is-invalid');
            feedback.style.display = 'none';
        }
    }
    
    // Email validation
    function validateEmail() {
        const email = document.getElementById('patient_email').value;
        const feedback = document.getElementById('emailFeedback');
        const emailInput = document.getElementById('patient_email');
        
        if (email && !email.includes('@')) {
            emailInput.classList.add('is-invalid');
            feedback.textContent = "Email must contain '@' symbol.";
            feedback.style.display = 'block';
            return false;
        } else if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            emailInput.classList.add('is-invalid');
            feedback.textContent = "Please enter a valid email address.";
            feedback.style.display = 'block';
            return false;
        } else {
            emailInput.classList.remove('is-invalid');
            feedback.style.display = 'none';
            return true;
        }
    }
    
    // Form validation
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('registrationForm');
        const passwordInput = document.getElementById('patient_password');
        const confirmInput = document.getElementById('patient_confirm_password');
        const emailInput = document.getElementById('patient_email');
        
        // Real-time validation
        passwordInput.addEventListener('input', checkPasswordStrength);
        confirmInput.addEventListener('input', checkPasswordMatch);
        emailInput.addEventListener('blur', validateEmail);
        
        // Form submission
        form.addEventListener('submit', function(event) {
            let isValid = true;
            
            // Validate email
            if (!validateEmail()) {
                isValid = false;
                emailInput.focus();
            }
            
            // Validate password length
            if (passwordInput.value.length < 6) {
                isValid = false;
                passwordInput.classList.add('is-invalid');
                alert('Password must be at least 6 characters long.');
                passwordInput.focus();
            }
            
            // Validate password match
            if (passwordInput.value !== confirmInput.value) {
                isValid = false;
                confirmInput.classList.add('is-invalid');
                alert('Passwords do not match.');
                confirmInput.focus();
            }
            
            // Check terms agreement
            const termsCheckbox = document.getElementById('terms');
            if (!termsCheckbox.checked) {
                isValid = false;
                alert('You must agree to the Terms of Service and Privacy Policy.');
                termsCheckbox.focus();
            }
            
            if (!isValid) {
                event.preventDefault();
            }
        });
    });
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            if (targetId !== '#') {
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            }
        });
    });
    </script>
</body>
</html>