<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session and check if staff is logged in
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] !== 'staff' && $_SESSION['user']['role'] !== 'admin')) {
    header('Location: ../login.php?role=staff');
    exit;
}

include '../includes/config.php';

// Check if database connection is successful
if (!isset($pdo)) {
    die("Database connection failed. Please check your config.php file.");
}

$user = $_SESSION['user'];
$success_message = "";
$error_message = "";

// Check if required tables exist
try {
    $check_messages = $pdo->query("SHOW TABLES LIKE 'patient_messages'")->rowCount();
    $check_notifications = $pdo->query("SHOW TABLES LIKE 'patient_notifications'")->rowCount();
    $check_users = $pdo->query("SHOW TABLES LIKE 'users'")->rowCount();
} catch (Exception $e) {
    die("Error checking database tables: " . $e->getMessage());
}

// Handle message reply
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reply_message'])) {
    if ($check_messages == 0) {
        $error_message = "<div class='alert alert-danger'>Messages table not set up. Please run the SQL queries.</div>";
    } else {
        $message_id = intval($_POST['message_id']);
        $reply = trim($_POST['reply'] ?? '');
        
        if (empty($reply)) {
            $error_message = "<div class='alert alert-danger'>Please enter a reply.</div>";
        } else {
            try {
                // Update message with reply
                $stmt = $pdo->prepare("
                    UPDATE patient_messages 
                    SET reply = ?, status = 'replied', replied_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$reply, $message_id]);
                
                // Get message details to create notification
                $msg_stmt = $pdo->prepare("SELECT * FROM patient_messages WHERE id = ?");
                $msg_stmt->execute([$message_id]);
                $message = $msg_stmt->fetch();
                
                if ($message && $check_notifications > 0 && $check_users > 0) {
                    // Create notification for patient
                    $patient_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $patient_stmt->execute([$message['patient_email']]);
                    $patient = $patient_stmt->fetch();
                    
                    if ($patient) {
                        $notification_stmt = $pdo->prepare("
                            INSERT INTO patient_notifications 
                            (patient_id, patient_email, notification_type, title, message) 
                            VALUES (?, ?, 'message_reply', ?, ?)
                        ");
                        
                        $title = "Reply to your message: " . $message['subject'];
                        $notification_msg = "The clinic has responded to your message. Reply: " . substr($reply, 0, 100) . "...";
                        
                        $notification_stmt->execute([
                            $patient['id'],
                            $message['patient_email'],
                            $title,
                            $notification_msg
                        ]);
                    }
                }
                
                $success_message = "<div class='alert alert-success'>✅ Reply sent successfully!</div>";
                
            } catch (Exception $e) {
                $error_message = "<div class='alert alert-danger'><strong>❌ Error:</strong> " . $e->getMessage() . "</div>";
            }
        }
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    if ($check_messages == 0) {
        $error_message = "<div class='alert alert-danger'>Messages table not set up. Please run the SQL queries.</div>";
    } else {
        $message_id = intval($_POST['message_id']);
        $new_status = $_POST['status'];
        
        try {
            $stmt = $pdo->prepare("UPDATE patient_messages SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $message_id]);
            
            $success_message = "<div class='alert alert-success'>✅ Status updated successfully!</div>";
        } catch (Exception $e) {
            $error_message = "<div class='alert alert-danger'><strong>❌ Error:</strong> " . $e->getMessage() . "</div>";
        }
    }
}

// Get all patient messages
if ($check_messages > 0) {
    $status_filter = $_GET['status'] ?? 'all';
    $search_query = $_GET['search'] ?? '';
    
    $query = "SELECT * FROM patient_messages WHERE 1=1";
    $params = [];
    
    if ($status_filter != 'all') {
        $query .= " AND status = ?";
        $params[] = $status_filter;
    }
    
    if (!empty($search_query)) {
        $query .= " AND (patient_name LIKE ? OR patient_email LIKE ? OR subject LIKE ? OR message LIKE ?)";
        $search_term = "%$search_query%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $query .= " ORDER BY 
        CASE WHEN status = 'new' THEN 1 
             WHEN status = 'read' THEN 2 
             WHEN status = 'replied' THEN 3 
             ELSE 4 END,
        created_at DESC";
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $messages = $stmt->fetchAll();
    } catch (Exception $e) {
        $messages = [];
        $error_message = "<div class='alert alert-danger'>Error loading messages: " . $e->getMessage() . "</div>";
    }
    
    // Get message counts
    try {
        $count_stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM patient_messages GROUP BY status");
        $count_stmt->execute();
        $status_counts = [];
        $total_count = 0;
        while ($row = $count_stmt->fetch()) {
            $status_counts[$row['status']] = $row['count'];
            $total_count += $row['count'];
        }
    } catch (Exception $e) {
        $status_counts = [];
        $total_count = 0;
    }
} else {
    $messages = [];
    $status_counts = [];
    $total_count = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Messages - ClinicConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .message-card {
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
            transition: all 0.3s;
        }
        .message-card:hover {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .message-new {
            border-left: 5px solid #007bff;
            background-color: #f8f9fa;
        }
        .message-read {
            border-left: 5px solid #28a745;
        }
        .message-replied {
            border-left: 5px solid #17a2b8;
        }
        .urgent-message {
            background: #fff8e1;
            border-color: #ffc107;
        }
        .chat-bubble {
            max-width: 70%;
            padding: 12px 15px;
            border-radius: 18px;
            margin-bottom: 10px;
        }
        .patient-message {
            background: #e3f2fd;
            margin-right: auto;
        }
        .staff-reply {
            background: #f8f9fa;
            margin-left: auto;
            border: 1px solid #dee2e6;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
        }
        .message-actions {
            opacity: 0;
            transition: opacity 0.3s;
        }
        .message-card:hover .message-actions {
            opacity: 1;
        }
        .database-warning {
            border-left: 5px solid #dc3545;
            background: #f8d7da;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-arrow-left"></i> Staff Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-item nav-link text-light">
                    <i class="fas fa-user-shield"></i> <?= htmlspecialchars($user['name']) ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1><i class="fas fa-comments"></i> Patient Messages</h1>
        <p class="lead">Communicate with patients and manage inquiries</p>
        
        <!-- Database Setup Warning -->
        <?php if ($check_messages == 0): ?>
        <div class="database-warning">
            <h5><i class="fas fa-database"></i> Database Setup Required</h5>
            <p>The <code>patient_messages</code> table is not set up. Please run this SQL query in phpMyAdmin:</p>
            <pre class="bg-dark text-white p-3 rounded">
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
            </pre>
            <p>After running the query, <a href="patient_messages.php" class="alert-link">refresh this page</a>.</p>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <?= $error_message ?>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <?= $success_message ?>
        <?php endif; ?>
        
        <?php if ($check_messages > 0): ?>
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-2 col-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?= $total_count ?></h3>
                        <p class="mb-0">Total Messages</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card text-center bg-primary text-white">
                    <div class="card-body">
                        <h3><?= $status_counts['new'] ?? 0 ?></h3>
                        <p class="mb-0">New</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card text-center bg-success text-white">
                    <div class="card-body">
                        <h3><?= $status_counts['read'] ?? 0 ?></h3>
                        <p class="mb-0">Read</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card text-center bg-info text-white">
                    <div class="card-body">
                        <h3><?= $status_counts['replied'] ?? 0 ?></h3>
                        <p class="mb-0">Replied</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card text-center bg-warning text-white">
                    <div class="card-body">
                        <h3><?= $status_counts['resolved'] ?? 0 ?></h3>
                        <p class="mb-0">Resolved</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?= count($messages) ?></h3>
                        <p class="mb-0">Filtered</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Filter by Status</label>
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?= ($status_filter ?? 'all') == 'all' ? 'selected' : '' ?>>All Messages</option>
                            <option value="new" <?= ($status_filter ?? 'all') == 'new' ? 'selected' : '' ?>>New Only</option>
                            <option value="read" <?= ($status_filter ?? 'all') == 'read' ? 'selected' : '' ?>>Read Only</option>
                            <option value="replied" <?= ($status_filter ?? 'all') == 'replied' ? 'selected' : '' ?>>Replied Only</option>
                            <option value="resolved" <?= ($status_filter ?? 'all') == 'resolved' ? 'selected' : '' ?>>Resolved Only</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search by patient name, email, or subject" value="<?= htmlspecialchars($search_query ?? '') ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <a href="patient_messages.php" class="btn btn-secondary w-100">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Messages List -->
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="fas fa-inbox"></i> Patient Inquiries
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($messages)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-comment-slash fa-3x text-muted mb-3"></i>
                        <h5>No Messages Found</h5>
                        <p class="text-muted">There are no messages matching your criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="accordion" id="messagesAccordion">
                        <?php foreach ($messages as $index => $msg): 
                            $msg_class = "message-" . $msg['status'];
                            if ($msg['is_urgent']) {
                                $msg_class .= " urgent-message";
                            }
                        ?>
                        <div class="message-card <?= $msg_class ?>">
                            <div class="card-header" id="heading<?= $index ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <button class="btn btn-link text-decoration-none text-dark p-0" 
                                            type="button" data-bs-toggle="collapse" 
                                            data-bs-target="#collapse<?= $index ?>" 
                                            aria-expanded="false" 
                                            aria-controls="collapse<?= $index ?>">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user-circle fa-2x me-3"></i>
                                            <div class="text-start">
                                                <h6 class="mb-0">
                                                    <?= htmlspecialchars($msg['patient_name']) ?>
                                                    <?php if ($msg['is_urgent']): ?>
                                                        <span class="badge bg-danger ms-2">URGENT</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($msg['patient_email']) ?>
                                                    • 
                                                    <?= date('M j, Y g:i A', strtotime($msg['created_at'])) ?>
                                                    • 
                                                    <span class="badge bg-<?= 
                                                        $msg['status'] == 'new' ? 'primary' : 
                                                        ($msg['status'] == 'read' ? 'success' : 
                                                        ($msg['status'] == 'replied' ? 'info' : 'warning'))
                                                    ?>">
                                                        <?= ucfirst($msg['status']) ?>
                                                    </span>
                                                </small>
                                            </div>
                                        </div>
                                    </button>
                                    <div class="message-actions">
                                        <span class="badge bg-secondary">
                                            <?= ucfirst(str_replace('_', ' ', $msg['message_type'])) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="collapse<?= $index ?>" class="collapse" aria-labelledby="heading<?= $index ?>" data-bs-parent="#messagesAccordion">
                                <div class="card-body">
                                    <h5><?= htmlspecialchars($msg['subject']) ?></h5>
                                    
                                    <!-- Patient's Message -->
                                    <div class="chat-bubble patient-message mb-3">
                                        <div class="d-flex justify-content-between">
                                            <small class="text-primary fw-bold">
                                                <?= htmlspecialchars($msg['patient_name']) ?>:
                                            </small>
                                            <small class="text-muted">
                                                <?= date('M j, g:i A', strtotime($msg['created_at'])) ?>
                                            </small>
                                        </div>
                                        <p class="mb-0"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                    </div>
                                    
                                    <!-- Staff Reply (if exists) -->
                                    <?php if (!empty($msg['reply'])): ?>
                                        <div class="chat-bubble staff-reply mb-3">
                                            <div class="d-flex justify-content-between">
                                                <small class="text-success fw-bold">Staff Reply:</small>
                                                <small class="text-muted">
                                                    <?= date('M j, g:i A', strtotime($msg['replied_at'])) ?>
                                                </small>
                                            </div>
                                            <p class="mb-0"><?= nl2br(htmlspecialchars($msg['reply'])) ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Reply Form -->
                                    <form method="POST" class="mt-4">
                                        <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Staff Reply</label>
                                            <textarea name="reply" class="form-control" rows="4" 
                                                      placeholder="Type your response here..."><?= htmlspecialchars($msg['reply'] ?? '') ?></textarea>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Update Status</label>
                                                    <select name="status" class="form-select" onchange="this.form.update_status.click()">
                                                        <option value="new" <?= $msg['status'] == 'new' ? 'selected' : '' ?>>New</option>
                                                        <option value="read" <?= $msg['status'] == 'read' ? 'selected' : '' ?>>Read</option>
                                                        <option value="replied" <?= $msg['status'] == 'replied' ? 'selected' : '' ?>>Replied</option>
                                                        <option value="resolved" <?= $msg['status'] == 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="d-flex gap-2 mt-4">
                                                    <button type="submit" name="reply_message" class="btn btn-primary flex-fill">
                                                        <i class="fas fa-reply"></i> Send Reply
                                                    </button>
                                                    <button type="submit" name="update_status" class="btn btn-secondary">
                                                        <i class="fas fa-save"></i> Update Status
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Auto-expand if there are new messages
    document.addEventListener('DOMContentLoaded', function() {
        const newMessages = document.querySelectorAll('.message-new');
        if (newMessages.length > 0) {
            const firstNew = newMessages[0];
            const collapseId = firstNew.querySelector('[data-bs-target]').getAttribute('data-bs-target');
            const collapseElement = document.querySelector(collapseId);
            if (collapseElement) {
                new bootstrap.Collapse(collapseElement, { toggle: true });
            }
        }
    });
    
    // Auto-refresh every 30 seconds
    setTimeout(function() {
        window.location.reload();
    }, 30000);
    </script>
</body>
</html>