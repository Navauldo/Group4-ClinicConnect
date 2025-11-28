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
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">ClinicConnect</a>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 mx-auto text-center">
                <h1>Welcome to ClinicConnect! 🏥</h1>
                <div class="mt-4">
                    <a href="booking/" class="btn btn-primary btn-lg me-3">Book Appointment</a>
                    <a href="dashboard/" class="btn btn-outline-secondary btn-lg">Staff Dashboard</a>

                </div>
                
                <?php
                echo "<div class='alert alert-success mt-4'>";
                echo "<strong>✅ Homepage is working!</strong><br>";
                echo "PHP Version: " . phpversion() . "<br>";
                echo "Server Time: " . date('Y-m-d H:i:s');
                echo "</div>";
                ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>