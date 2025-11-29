<?php
// TURN ON ERROR REPORTING
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'includes/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClinicConnect - Medical Appointments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-hospital"></i> ClinicConnect
            </a>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 mx-auto text-center">
                <h1>Welcome to ClinicConnect! 🏥</h1>
                <p class="lead">Manage your medical appointments with ease</p>
                
                <div class="mt-4">
                    <a href="login.php?role=patient" class="btn btn-primary btn-lg me-3">
                        <i class="fas fa-calendar-plus"></i> Book Appointment
                    </a>
                    <a href="login.php?role=staff" class="btn btn-success btn-lg me-3">
                        <i class="fas fa-user-nurse"></i> Staff Dashboard
                    </a>
                    <a href="login.php?role=admin" class="btn btn-warning btn-lg">
                        <i class="fas fa-user-shield"></i> Admin Dashboard
                    </a>
                </div>
                
                <?php
                echo "<div class='alert alert-success mt-4'>";
                echo "✅ System Status: Active<br>";
                echo "🕒 Server Time: " . date('Y-m-d H:i:s');
                echo "</div>";
                ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>