<?php
session_start();
$username = $_SESSION['username'] ?? 'User';

$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "telesol crm";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

$errors = [];
$success = false;

// Initialize empty form values to avoid undefined variable notices
$customer_name = $contact_number = $email = $location = $issue_type = $service_type = $comments = $escalate = $escalated_department = '';

// Allowed values for selects
$valid_issue_types = ['Data Issue', 'Top up Issue', 'Connection Issue', 'System-Related Issue', 'Manual Top up Request', 'Other'];
$valid_service_types = ['4G', 'FTTH', 'FTTP'];
$valid_departments = ['Admin', 'Customer Service', 'Finance', 'Systems', 'Technical', 'Other'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize inputs
    $customer_name = trim($_POST['customer_name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $issue_type = $_POST['issue_type'] ?? '';
    $service_type = $_POST['service_type'] ?? '';
    $comments = trim($_POST['comments'] ?? '');
    $escalate = $_POST['escalate'] ?? '';
    $escalated_department = $_POST['escalated_department'] ?? '';

    // Validate required fields
    if ($customer_name === '') {
        $errors[] = "Customer name is required.";
    }
    if ($contact_number === '') {
        $errors[] = "Contact number is required.";
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please provide a valid email address.";
    }
    if ($location === '') {
        $errors[] = "Location is required.";
    }
    if (!in_array($issue_type, $valid_issue_types, true)) {
        $errors[] = "Please select a valid issue type.";
    }
    if (!in_array($service_type, $valid_service_types, true)) {
        $errors[] = "Please select a valid service type.";
    }
    if (!in_array($escalate, ['Yes', 'No'], true)) {
        $errors[] = "Please indicate if the issue should be escalated.";
    }
    if ($escalate === 'Yes' && !in_array($escalated_department, $valid_departments, true)) {
        $errors[] = "Please select a valid department for escalation.";
    }

    // Handle file upload if provided
    $upload_path = null;
    if (!empty($_FILES['attachment']['name'])) {
        $allowed_types = [
            'image/jpeg', 'image/png', 'image/gif',
            'video/mp4', 'video/avi', 'video/quicktime', 'video/mov'
        ];
        $file_type = $_FILES['attachment']['type'] ?? '';
        $file_size = $_FILES['attachment']['size'] ?? 0;

        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Invalid file type. Allowed: JPG, PNG, GIF, MP4, AVI, MOV.";
        } elseif ($file_size > 10 * 1024 * 1024) { // 10MB limit
            $errors[] = "File size must be under 10MB.";
        } else {
            $ext = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $unique_name = uniqid('issue_', true) . '.' . $ext;
            $upload_path = 'uploads/' . $unique_name;

            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $unique_name)) {
                $errors[] = "Failed to upload file.";
            }
        }
    }

    // Insert into database if no validation errors
    if (empty($errors)) {
        $stmt = $conn->prepare(
            "INSERT INTO tickets
            (customer_name, contact_number, email, location, issue_type, service_type, attachment_path, comments, escalate, escalated_department, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param(
            'sssssssssss',
            $customer_name,
            $contact_number,
            $email,
            $location,
            $issue_type,
            $service_type,
            $upload_path,
            $comments,
            $escalate,
            $escalated_department,
            $username
        );

        if ($stmt->execute()) {
            $success = true;
            // Clear form fields after success to display fresh blank form
            $customer_name = $contact_number = $email = $location = '';
            $issue_type = $service_type = $comments = '';
            $escalate = $escalated_department = '';
            $upload_path = null;
        } else {
            $errors[] = "Failed to log issue. Please try again.";
        }

        $stmt->close();
    }
}

$conn->close();

// Escape function for output safety
function esc($str) {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Telesol CRM - Log Ticket</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
</head>

<style>
    :root {
        --primary: #083b6e;
        --primary-dark: #1e2d3b;
        --secondary: #3498db;
        --success: #00b44bff;
        --warning: #f39c12;
        --danger: #e74c3c;
        --light: #ecf0f1;
        --dark: #2c3e50;
        --gray: #95a5a6;
        --light-gray: #ddd;
        --sidebar-width: 220px;
        --background: #f5f7fa;
        --purple: #00e5ffff;
        --white: #ffffff;
        --border-radius: 8px;
        --transition: 0.3s ease;
    }

    /* Reset and base styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    body {
        background-color: var(--background);
        color: var(--dark);
        display: flex;
        min-height: 100vh;
        font-size: 14px;
    }

    /* Sidebar */
    .sidebar {
        width: var(--sidebar-width);
        background-color: var(--primary);
        color: var(--white);
        position: fixed;
        height: 100vh;
        overflow-y: auto;
        z-index: 1000;
        transition: transform var(--transition);
    }
    
    .sidebar-header {
        background: var(--primary);
        padding: 0.5rem;
        text-align: center;
        font-weight: 300;
        font-size: 0.8rem;
        font-family: inherit;
        letter-spacing: 0.5px;
    }
    
    .sidebar-menu ul {
        list-style: none;
        padding: 1rem 0;
    }

    .sidebar-menu li {
        margin: 0.2rem 0;
    }
    
    .sidebar-menu a {
        color: var(--white);
        text-decoration: none;
        padding: 0.6rem 0.6rem;
        display: block;
        font-size: 14px;
        font-weight: 400;
        font-family: inherit;
        transition: background-color var(--transition);
    }

    .sidebar-menu a:hover,
    .sidebar-menu a.active {
        background-color: var(--secondary);
        border-left: 3px solid var(--white);
    }

    .sidebar-menu i {
        margin-right: 10px;
        width: 20px;
        text-align: center;
    }

    /* Main Content Styles */
    .main-content {
        flex: 1;
        margin-left: var(--sidebar-width);
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }
        
    /* Header Styles */
    .header {
        background: var(--white);
        color: var(--primary-dark);
        padding: 10px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        position: sticky;
        top: 0;
        z-index: 100;
    }
        
    .user-info {
        display: flex;
        align-items: center;
    }

    .user-info img {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-right: 10px;
    }

    /* Content Styles */
    .content {
        padding: 20px;
        flex: 1;
        overflow-y: auto;
    }

    .page {
        display: none;
    }

    .page.active {
        display: block;
    }

    /* Dashboard Cards */
    .card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
        
    .card {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
        
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
        
    .card-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--dark);
    }
    
    .card-icon {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }
       
    .bg-primary { background: var(--primary); }
    .bg-success { background: var(--success); }
    .bg-warning { background: var(--warning); }
    .bg-danger { background: var(--danger); }
        
    .card-value {
        font-size: 28px;
        font-weight: 700;
        margin: 10px 0;
    }
        
    .card-text {
        color: var(--gray);
        font-size: 14px;
    }
        
    /* Table Styles */
    .table-container {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
        
    table {
        width: 100%;
        border-collapse: collapse;
    }
        
    th, td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid #eee;
    }
        
    th {
        background: #f8f9fa;
        font-weight: 600;
        color: var(--dark);
    }
        
    tr:hover {
        background: #f8f9fa;
    }
        
    .status {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }
        
    .status-new {
        background: #e3f2fd;
        color: var(--secondary);
    }
        
    .status-in-progress {
        background: #fff8e1;
        color: var(--warning);
    }
        
    .status-completed {
        background: #e8f5e9;
        color: var(--success);
    }
        
    .status-pending {
        background: #ffebee;
        color: var(--danger);
    }
        
    .btn {
        padding: 8px 15px;
        border-radius: 4px;
        border: none;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
    }
        
    .btn-primary {
        background: var(--secondary);
        color: white;
    }
        
    .btn-primary:hover {
        background: #2980b9;
    }
        
    .btn-sm {
        padding: 5px 10px;
        font-size: 12px;
    }
        
    /* Filter Section */
    .filter-section {
        background: white;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: center;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
        
    .filter-group {
        display: flex;
        flex-direction: column;
    }
        
    .filter-group label {
        font-size: 12px;
        margin-bottom: 5px;
        color: var(--gray);
    }
        
    select, input {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
        
    /* Engineer Cards */
    .engineer-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }
        
    .engineer-card {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .engineer-header {
        padding: 20px;
        display: flex;
        align-items: center;
        background: var(--primary);
        color: white;
    }

    .engineer-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        margin-right: 15px;
        border: 3px solid rgba(255,255,255,0.3);
    }

    .engineer-info h3 {
        margin-bottom: 5px;
    }

    .engineer-info p {
        font-size: 14px;
        opacity: 0.8;
    }

    .engineer-stats {
        display: flex;
        background: #f8f9fa;
        padding: 10px;
        justify-content: space-around;
    }
        
    .stat {
        text-align: center;
    }
        
    .stat-value {
        font-size: 18px;
        font-weight: 700;
    }

    .stat-label {
        font-size: 12px;
        color: var(--gray);
    }
        
    .engineer-tasks {
        padding: 15px;
    }
        
    .task-item {
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }
        
        .task-item:last-child {
            border-bottom: none;
        }
        
        .task-title {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .task-details {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--gray);
        }
        
        /* Loading indicator */
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
            flex-direction: column;
        }
        
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-left-color: var(--secondary);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin-bottom: 15px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Mobile menu button */
        .mobile-menu-btn {
            display: none;
            background: var(--secondary);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
            margin-right: 15px;
        }
        
        /* Database connection indicator */
        /* .db-status {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .db-connected {
            background: var(--success);
            color: white;
        }
        
        .db-disconnected {
            background: var(--danger);
            color: white;
        } */
        
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .mobile-menu-btn {
                display: block;
            }
            
            .card-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .card-grid {
                grid-template-columns: 1fr;
            }
            
            .engineer-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-section {
                flex-direction: column;
                align-items: flex-start;
            }
        }



    .card {
        background: var(--white);
        border-radius: 5px;
        box-shadow: 0 4px 20px rgba(44, 62, 80, 0.08);
        padding: 2rem 2rem;
        max-width: 1200px;
        margin-top: -6px;
        margin-left: 4px;
        transition: box-shadow 0.3s;
    }

    .card-header {
        margin-bottom: 0.5rem;
        color: var(--dark);
    }

    .card-title {
        font-size: 1.4em;
        font-weight: 700;
        color: var(--dark);
        margin-top: -10px;
        margin-bottom: -0.2rem;
        margin-left: 0;
    }

    .card-subtitle {
        color: var(--subtitle);
        font-size: 1.1rem;
        margin-top: -0.9rem;
        margin-bottom: 0.8rem;
    }

    /* Grid Layout for Forms */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem 1.2rem;
    }

    .form-group {
        margin-top: -0.02rem;
        margin-bottom: -0.01rem;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.2rem;
        color: var(--dark) /* #495057 */;
        letter-spacing: 0.01em;
    }

    .form-control {
        width: 100%;
        height: 2.2rem;
        padding: 0.5rem 0.8rem;
        font-size: 1rem;
        border: 1px solid var(--dark);
        border-radius: 7px;
        background-color: #f8f9fa;
        color: var(--dark);
        transition: border 0.3s, box-shadow 0.3s;
        box-sizing: border-box;
        font-family: inherit;
    }

    .form-control:focus {
        outline: none;
        font-family: inherit;
        border-color: var(--dark);
        background-color: #fff;
        box-shadow: 0 0 0 3px rgba(74, 107, 255, 0.12);
    }

    textarea.form-control {
        min-height: 100px;
        resize: vertical;
        margin-top: -0.02rem;
        padding-top: 0.4rem;
    }

    .form-file {
        border: 2px dashed var(--dark);
        padding: 1rem 0.75rem;
        border-radius: 8px;
        background-color: #f8f9fa;
        text-align: center;
    }

    .form-file input {
        display: none;
    }

    .form-file-label {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 1rem;
        color: var(--dark);
        gap: 0.2rem;
    }

    .form-file-label i {
        font-size: 1.6rem;
        margin-top: 0.1rem;
        margin-bottom: 0.1rem;
    }

    /* Submit Button */
    .btn-submit {
        /* background: linear-gradient(135deg, var(--primary), var(--accent)); */
        background: var(--dark);
        color: white;
        font-weight: 600;
        font-size: 1.05rem;
        padding: 0.6rem 0;
        border-radius: 8px;
        margin-top: 0.4rem;
        grid-column: 1 / -1;
        border: none;
        cursor: pointer;
        box-shadow: 0 2px 12px rgba(74, 107, 255, 0.10);
        transition: opacity 0.2s, transform 0.2s;
    }

    .btn-submit:hover {
        opacity: 0.92;
        transform: translateY(-2px) scale(1);
    }

    /* Feedback Messages */
    .alert {
        padding: 1.15rem 1.2rem;
        border-radius: 7px;
        margin-bottom: 2rem;
        font-weight: 500;
        font-size: 1.04rem;
        line-height: 1.4;
        box-shadow: 0 1px 6px rgba(44, 62, 80, 0.07);
    }

    .alert-error {
        background-color: #f8d7da;
        color: var(--error);
        border-left: 5px solid var(--error);
    }

    .alert-success {
        background-color: #d4edda;
        color: var(--success);
        border-left: 5px solid var(--success);
    }

    /* Required star styling */
    .required {
        color: var(--error);
        font-weight: 700;
    }

    /* Responsive Enhancements */
    @media (max-width: 1100px) {
        .card {
            padding: 1.2rem 1rem;
            max-width: 98vw;
        }

        .main-content {
            padding: 1.2rem 0.5rem;
        }

        .form-grid {
            gap: 1.1rem;
        }
    }

    @media (max-width: 900px) {
        .form-grid {
            grid-template-columns: 1fr 1fr;
        }
    }

    @media (max-width: 768px) {
        body {
            flex-direction: column;
        }

        .sidebar {
            width: 100%;
            position: relative;
            height: auto;
            box-shadow: none;
        }

        .main-content {
            margin-left: 0;
            padding: 1.2rem 0.5rem;
        }

        .card {
            margin: 1rem 0;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 520px) {
        .card {
            padding: 0.7rem 0.3rem;
        }

        .card-title {
            font-size: 1.2rem;
        }
    }
</style>

<body>

    <!-- Sidebar -->
    <aside class="sidebar" aria-label="Main navigation">
        <div class="sidebar-header">
            <div class="sidebar-header">
                <div class="company-logo" aria-hidden="true">
                    <img src="/images/logo/Telesol_logo.jpeg" alt="Company Logo" />
            </div>
        </div>
            <h1>Telesol CRM</h1>
        </div>
        <nav class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li><a href="ticket_mgt.php" class="active"><i class="bi bi-ticket-detailed"></i> Tickets</a></li>
                <li><a href="installation_mgt.php"><i class="bi bi-wrench"></i> Installations</a></li>
                <li><a href="customer_experience_dashboard.php"><i class="bi bi-people"></i> Customer Experience</a></li>
                <li><a href="report.php"><i class="bi bi-bar-chart"></i> Reports</a></li>
                <li><a href="#"><i class="bi bi-gear"></i> Settings</a></li>
                <li><a href="#"><i class="bi bi-arrow-left-circle"></i> Back</a></li>
                <li><a href="login.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
    <header class="header">
        <div>
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
                <h2>New Ticket</h2>
        </div>

      <div class="user-session">
        <i class="bi bi-person-circle"></i>
        <span>Logged in as: <strong><?= esc($username) ?></strong></span>
      </div>
    </header>

    <div class="content" role="main" aria-label="Log New Issue Form">
        <div class="card">
            <?php if (!empty($errors)) : ?>
                <div class="alert alert-error" role="alert" aria-live="assertive">
                    <?php foreach ($errors as $error) : ?>
                        <div><?= esc($error) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php elseif ($success) : ?>
                <div class="alert alert-success" role="status" aria-live="polite">
                    Issue logged successfully!
                </div>
                <?php endif; ?>

                <form method="post" class="form-grid" enctype="multipart/form-data" novalidate>
                    <div class="form-group">
                        <label for="customer_name">Customer Name <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" id="customer_name" name="customer_name" required class="form-control" value="<?= esc($customer_name) ?>" aria-required="true" />
                    </div>

                    <div class="form-group">
                        <label for="contact_number">Contact Number <span class="required" aria-hidden="true">*</span></label>
                        <input type="tel" id="contact_number" name="contact_number" required class="form-control" value="<?= esc($contact_number) ?>" aria-required="true" />
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address <span class="required" aria-hidden="true">*</span></label>
                        <input type="email" id="email" name="email" required class="form-control" value="<?= esc($email) ?>" aria-required="true" />
                    </div>

                    <div class="form-group">
                        <label for="location">Location <span class="required" aria-hidden="true">*</span></label>
                        <input type="text" id="location" name="location" required class="form-control" value="<?= esc($location) ?>" aria-required="true" />
                    </div>

                    <div class="form-group">
                        <label for="service_type">Service Type <span class="required" aria-hidden="true">*</span></label>
                        <select id="service_type" name="service_type" required class="form-control" aria-required="true">
                            <option value="" disabled <?= empty($service_type) ? 'selected' : '' ?>>Select Service Type</option>
                            <?php foreach ($valid_service_types as $st) : ?>
                                <option value="<?= esc($st) ?>" <?= ($service_type === $st) ? 'selected' : '' ?>><?= esc($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="issue_type">Issue Type <span class="required" aria-hidden="true">*</span></label>
                        <select id="issue_type" name="issue_type" required class="form-control" aria-required="true">
                            <option value="" disabled <?= empty($issue_type) ? 'selected' : '' ?>>Select Issue Type</option>
                            <?php foreach ($valid_issue_types as $it) : ?>
                                <option value="<?= esc($it) ?>" <?= ($issue_type === $it) ? 'selected' : '' ?>><?= esc($it) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="escalate">Should Issue Be Escalated? <span class="required" aria-hidden="true">*</span></label>
                        <select id="escalate" name="escalate" required class="form-control" aria-required="true">
                            <option value="" disabled <?= empty($escalate) ? 'selected' : '' ?>>Select Option</option>
                            <option value="Yes" <?= ($escalate === 'Yes') ? 'selected' : '' ?>>Yes</option>
                            <option value="No" <?= ($escalate === 'No') ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="escalated_department">Escalated To Which Department?</label>
                        <select id="escalated_department" name="escalated_department" class="form-control" <?= ($escalate !== 'Yes') ? 'disabled' : '' ?>>
                            <option value="" disabled <?= empty($escalated_department) ? 'selected' : '' ?>>Select Department</option>
                            <?php foreach ($valid_departments as $dept) : ?>
                                <option value="<?= esc($dept) ?>" <?= ($escalated_department === $dept) ? 'selected' : '' ?>><?= esc($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="attachment">Upload Image or Video (optional)</label>
                        <div class="form-file">
                            <label for="attachment" class="form-file-label" tabindex="0">
                                <i class="bi bi-cloud-arrow-up"></i>
                                <span>Click to upload file</span>
                                <small class="text-muted">Max size: 10MB (JPG, PNG, GIF, MP4, AVI, MOV)</small>
                            </label>
                            <input type="file" id="attachment" name="attachment" accept="image/*,video/*" />
                        </div>
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="comments">Comments</label>
                        <textarea id="comments" name="comments" class="form-control" rows="5"><?= esc($comments) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-submit" aria-label="Submit Issue">
                        <i class="bi bi-send-fill"></i> Submit Issue
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const escalateSelect = document.getElementById('escalate');
            const escalatedDeptSelect = document.getElementById('escalated_department');
            const fileInput = document.getElementById('attachment');
            const fileLabelSpan = document.querySelector('.form-file-label span');

            function toggleEscalatedDept() {
                if (escalateSelect.value === 'Yes') {
                    escalatedDeptSelect.disabled = false;
                } else {
                    escalatedDeptSelect.disabled = true;
                    escalatedDeptSelect.value = "";
                }
            }

            escalateSelect.addEventListener('change', toggleEscalatedDept);
            toggleEscalatedDept();

            fileInput.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    fileLabelSpan.textContent = fileInput.files[0].name;
                } else {
                    fileLabelSpan.textContent = 'Click to upload file';
                }
            });
        });
    </script>
</body>

</html>
