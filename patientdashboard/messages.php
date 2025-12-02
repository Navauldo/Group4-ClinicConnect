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

// Check if database connection is successful
if (!isset($pdo)) {
    die("Database connection failed. Please check your config.php file.");
}

$user = $_SESSION['user'];
$user_id = $user['id'];
$patient_email = $user['email'];
$patient_name = $user['name'];

$success_message = "";
$error_message = "";

// Handle new message submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $message_type = $_POST['message_type'] ?? 'general';
    $is_urgent = isset($_POST['is_urgent']) ? 1 : 0;
    
    if (empty($subject)) {
        $error_message = "<div class='alert alert-danger'>Please enter a subject.</div>";
    } elseif (empty($message)) {
        $error_message = "<div class='alert alert-danger'>Please enter your message.</div>";
    } else {
        try {
            // First, check if the patient_messages table exists
            $check_table = $pdo->query("SHOW TABLES LIKE 'patient_messages'");
            if ($check_table->rowCount() == 0) {
                throw new Exception("Database table 'patient_messages' doesn't exist. Please run the SQL queries first.");
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO patient_messages 
                (patient_id, patient_email, patient_name, subject, message, message_type, is_urgent, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'new', NOW())
            ");
            $stmt->execute([$user_id, $patient_email, $patient_name, $subject, $message, $message_type, $is_urgent]);
            
            $success_message = "<div class='alert alert-success'><strong>✅ Message Sent Successfully!</strong><br>Our staff will respond within 24 hours.</div>";
            
        } catch (Exception $e) {
            $error_message = "<div class='alert alert-danger'><strong>❌ Error:</strong> " . $e->getMessage() . "</div>";
        }
    }
}

// Get user's messages
try {
    $stmt = $pdo->prepare("
        SELECT * FROM patient_messages 
        WHERE patient_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    $messages = $stmt->fetchAll();
} catch (Exception $e) {
    $messages = [];
    $error_message = "<div class='alert alert-warning'>Could not load messages: " . $e->getMessage() . "</div>";
}

// Get unread notifications count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM patient_notifications WHERE patient_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetch()['count'] ?? 0;
} catch (Exception $e) {
    $unread_notifications = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages & Notifications - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .message-card {
            border-radius: 10px;
            border: 1px solid #dee2e6;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .message-card:hover {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .message-status-new {
            border-left: 5px solid #007bff;
        }
        .message-status-read {
            border-left: 5px solid #28a745;
        }
        .message-status-replied {
            border-left: 5px solid #17a2b8;
        }
        .urgent-badge {
            background: #dc3545;
            color: white;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .chat-bubble {
            max-width: 70%;
            padding: 12px 15px;
            border-radius: 18px;
            margin-bottom: 10px;
            position: relative;
        }
        .patient-message {
            background: #e3f2fd;
            margin-left: auto;
        }
        .staff-reply {
            background: #f8f9fa;
            margin-right: auto;
            border: 1px solid #dee2e6;
        }
        .message-time {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 5px;
        }
        .tab-content {
            min-height: 400px;
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
                <a href="notifications.php" class="nav-item nav-link text-light position-relative">
                    <i class="fas fa-bell"></i>
                    <?php if ($unread_notifications > 0): ?>
                        <span class="notification-badge"><?= $unread_notifications ?></span>
                    <?php endif; ?>
                </a>
                <span class="nav-item nav-link text-light">
                    <i class="fas fa-user-circle"></i> <?= htmlspecialchars($user['name']) ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1><i class="fas fa-comments"></i> Messages & Notifications</h1>
        <p class="lead">Contact clinic staff and view notifications</p>
        
        <!-- Database Setup Warning -->
        <?php 
        // Check if required tables exist
        $check_messages = $pdo->query("SHOW TABLES LIKE 'patient_messages'")->rowCount();
        $check_notifications = $pdo->query("SHOW TABLES LIKE 'patient_notifications'")->rowCount();
        
        if ($check_messages == 0 || $check_notifications == 0): 
        ?>
        <div class="alert alert-danger">
            <h5><i class="fas fa-database"></i> Database Setup Required</h5>
            <p>Some database tables are missing. Please run these SQL queries in phpMyAdmin:</p>
            <pre class="bg-dark text-white p-3 rounded">
-- Table for patient-staff messages
CREATE TABLE IF NOT EXISTS patient_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    patient_email VARCHAR(100) NOT NULL,
    patient_name VARCHAR(100) NOT NULL,
    subject VARCHAR(200),
    message TEXT NOT NULL,
    reply TEXT,
    status ENUM('new', 'read', 'replied', 'resolved') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    replied_at TIMESTAMP NULL,
    is_urgent BOOLEAN DEFAULT FALSE,
    message_type ENUM('general', 'appointment_change', 'medical_query', 'billing') DEFAULT 'general',
    INDEX idx_patient (patient_id),
    INDEX idx_status (status)
);

-- Table for patient notifications
CREATE TABLE IF NOT EXISTS patient_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    patient_email VARCHAR(100) NOT NULL,
    notification_type ENUM('appointment_change', 'reminder', 'message_reply', 'general') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    related_appointment_id INT,
    related_reference VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_patient (patient_id),
    INDEX idx_unread (patient_id, is_read)
);
            </pre>
            <p>After running these queries, <a href="messages.php" class="alert-link">refresh this page</a>.</p>
        </div>
        <?php endif; ?>
        
        <!-- Display Messages -->
        <?php if (!empty($error_message)): ?>
            <?= $error_message ?>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <?= $success_message ?>
        <?php endif; ?>
        
        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" id="messagesTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="new-tab" data-bs-toggle="tab" data-bs-target="#new" type="button" role="tab">
                    <i class="fas fa-edit"></i> New Message
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">
                    <i class="fas fa-history"></i> Message History
                    <?php if ($messages): ?>
                        <span class="badge bg-primary ms-1"><?= count($messages) ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                    <i class="fas fa-bell"></i> Notifications
                    <?php if ($unread_notifications > 0): ?>
                        <span class="badge bg-danger ms-1"><?= $unread_notifications ?></span>
                    <?php endif; ?>
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="messagesTabContent">
            <!-- New Message Tab -->
            <div class="tab-pane fade show active" id="new" role="tabpanel">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-paper-plane"></i> Send Message to Clinic Staff</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($check_messages == 0): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                The messages database table is not set up yet. Please run the SQL queries shown above.
                            </div>
                        <?php else: ?>
                        <form method="POST" id="messageForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Subject *</label>
                                        <input type="text" name="subject" class="form-control" 
                                               placeholder="What is this regarding?" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Message Type</label>
                                        <select name="message_type" class="form-select">
                                            <option value="general">General Inquiry</option>
                                            <option value="appointment_change">Appointment Change Request</option>
                                            <option value="medical_query">Medical Question</option>
                                            <option value="billing">Billing/Insurance</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Your Message *</label>
                                <textarea name="message" class="form-control" rows="6" 
                                          placeholder="Please describe your question or concern in detail..." required></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_urgent" id="isUrgent">
                                    <label class="form-check-label text-danger" for="isUrgent">
                                        <i class="fas fa-exclamation-triangle"></i> Mark as Urgent
                                        <small class="text-muted d-block">(For time-sensitive matters only)</small>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Response Time:</strong> Our staff typically responds within 24 hours during business days.
                                For immediate assistance, please call the clinic directly.
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" name="send_message" value="1" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane"></i> Send Message
                                </button>
                                <button type="reset" class="btn btn-secondary">Clear Form</button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Message History Tab -->
            <div class="tab-pane fade" id="history" role="tabpanel">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Message History</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($check_messages == 0): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-database"></i> Database table not set up. Please run the SQL queries.
                            </div>
                        <?php elseif (empty($messages)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                                <h5>No Messages Yet</h5>
                                <p class="text-muted">You haven't sent any messages to the clinic.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($messages as $msg): 
                                    $status_class = 'message-status-' . $msg['status'];
                                ?>
                                <div class="message-card <?= $status_class ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-0">
                                                <?= htmlspecialchars($msg['subject']) ?>
                                                <?php if ($msg['is_urgent']): ?>
                                                    <span class="badge urgent-badge ms-2">URGENT</span>
                                                <?php endif; ?>
                                            </h6>
                                            <div>
                                                <span class="badge bg-<?= 
                                                    $msg['status'] == 'new' ? 'primary' : 
                                                    ($msg['status'] == 'replied' ? 'info' : 'success')
                                                ?>">
                                                    <?= ucfirst($msg['status']) ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <i class="fas fa-clock"></i> 
                                                <?= date('M j, Y g:i A', strtotime($msg['created_at'])) ?>
                                                • 
                                                <i class="fas fa-tag"></i> 
                                                <?= ucfirst(str_replace('_', ' ', $msg['message_type'])) ?>
                                            </small>
                                        </div>
                                        
                                        <!-- Patient's Message -->
                                        <div class="chat-bubble patient-message">
                                            <div class="d-flex justify-content-between">
                                                <small class="text-primary fw-bold">You:</small>
                                                <small class="text-muted">
                                                    <?= date('g:i A', strtotime($msg['created_at'])) ?>
                                                </small>
                                            </div>
                                            <p class="mb-0"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                        </div>
                                        
                                        <!-- Staff Reply (if exists) -->
                                        <?php if (!empty($msg['reply'])): ?>
                                            <div class="chat-bubble staff-reply">
                                                <div class="d-flex justify-content-between">
                                                    <small class="text-success fw-bold">Clinic Staff:</small>
                                                    <small class="text-muted">
                                                        <?= date('g:i A', strtotime($msg['replied_at'])) ?>
                                                    </small>
                                                </div>
                                                <p class="mb-0"><?= nl2br(htmlspecialchars($msg['reply'])) ?></p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($msg['status'] == 'new'): ?>
                                            <div class="mt-3">
                                                <small class="text-warning">
                                                    <i class="fas fa-clock"></i> 
                                                    Waiting for staff response
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Notifications Tab -->
            <div class="tab-pane fade" id="notifications" role="tabpanel">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-bell"></i> Notifications</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($check_notifications == 0): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-database"></i> Notifications table not set up. Please run the SQL queries.
                            </div>
                        <?php else: ?>
                            <?php 
                            try {
                                $stmt = $pdo->prepare("
                                    SELECT * FROM patient_notifications 
                                    WHERE patient_id = ? 
                                    ORDER BY created_at DESC
                                    LIMIT 50
                                ");
                                $stmt->execute([$user_id]);
                                $notifications = $stmt->fetchAll();
                            } catch (Exception $e) {
                                $notifications = [];
                                echo "<div class='alert alert-danger'>Error loading notifications: " . $e->getMessage() . "</div>";
                            }
                            ?>
                            
                            <?php if (empty($notifications)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                                    <h5>No Notifications</h5>
                                    <p class="text-muted">You don't have any notifications yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($notifications as $notification): 
                                        $bg_class = $notification['is_read'] ? 'bg-light' : 'bg-white';
                                        $icon = $notification['notification_type'] == 'appointment_change' ? 'fa-calendar-alt' : 
                                               ($notification['notification_type'] == 'reminder' ? 'fa-bell' : 
                                               ($notification['notification_type'] == 'message_reply' ? 'fa-reply' : 'fa-info-circle'));
                                    ?>
                                    <div class="list-group-item <?= $bg_class ?>" style="border-left: 4px solid #007bff;">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">
                                                <i class="fas <?= $icon ?> me-2"></i>
                                                <?= htmlspecialchars($notification['title']) ?>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="badge bg-danger ms-2">NEW</span>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-muted">
                                                <?= date('M j, g:i A', strtotime($notification['created_at'])) ?>
                                            </small>
                                        </div>
                                        <p class="mb-1"><?= nl2br(htmlspecialchars($notification['message'])) ?></p>
                                        <?php if ($notification['related_reference']): ?>
                                            <small class="text-primary">
                                                <i class="fas fa-hashtag"></i> Ref: <?= $notification['related_reference'] ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="mt-3">
                                    <a href="mark_all_notifications_read.php" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-check-double"></i> Mark All as Read
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Simple notification marking - removed AJAX for simplicity
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-refresh notifications every 60 seconds
        setTimeout(function() {
            const activeTab = document.querySelector('#messagesTab .nav-link.active');
            if (activeTab && activeTab.id === 'notifications-tab') {
                location.reload();
            }
        }, 60000);
    });
    </script>
</body>
</html>