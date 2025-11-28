<?php
// TURN ON ERROR REPORTING
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'clinic_connect');
define('DB_USER', 'root');
define('DB_PASS', ''); // Default XAMPP password is empty

echo "<div class='alert alert-info'>Testing database connection...</div>";

// Create database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<div class='alert alert-success'>✅ Database connected successfully!</div>";
} catch(PDOException $e) {
    echo "<div class='alert alert-danger'>❌ Database connection failed: " . $e->getMessage() . "</div>";
    echo "<div class='alert alert-warning'>Troubleshooting tips:";
    echo "<ul>";
    echo "<li>Make sure MySQL is running in XAMPP</li>";
    echo "<li>Make sure database 'clinicconnect' exists</li>";
    echo "<li>Check if password is needed (try 'root' as password)</li>";
    echo "</ul></div>";
    // Don't die yet, let's see the error
}
?>