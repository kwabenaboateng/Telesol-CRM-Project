<?php
include 'db_config.php';

$username = 'admin';
$password = 'admin123'; // Choose a strong password
$role = 'admin';           // Must be one of: user, admin, supervisor, manager

// Check if role is valid
$valid_roles = ['user', 'admin', 'supervisor', 'manager'];
if (!in_array($role, $valid_roles)) {
    die('Invalid role specified.');
}

// Hash the password securely
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare('INSERT INTO users (name, password, role) VALUES (?, ?, ?)');
if (!$stmt) {
    die('Prepare failed: ' . $conn->error);
}
$stmt->bind_param('sss', $username, $hashed_password, $role);

if ($stmt->execute()) {
    echo "User '$username' created successfully with role '$role'.\n";
} else {
    echo "Error creating user: " . $stmt->error . "\n";
}

$stmt->close();
$conn->close();
?>
