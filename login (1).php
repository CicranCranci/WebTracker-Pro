<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

$servername = "localhost";
$username = "cicrancr_cicran1"; // Your DB username
$password = "Spike2005."; // Your DB password
$dbname = "cicrancr_visitor_info";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Retrieve login form data
$adminUsername = $_POST['username'];
$adminPassword = $_POST['password'];

// Find the user
$stmt = $conn->prepare("SELECT username, password FROM admin_users WHERE username = ?");
$stmt->bind_param("s", $adminUsername);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    if (password_verify($adminPassword, $user['password'])) {
        $_SESSION['user'] = $user['username']; // Set session variable
        header("Location: dashboard.php"); // Redirect to dashboard
        exit();
    }
}

// If login failed
header("Location: login.html?error=invalid");
exit();
?>


