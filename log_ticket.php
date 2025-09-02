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
    --bg: #818fa4ff;
    --background: #f0f0f0ff;
    --deep-bg: #425779ff;
    --white: #ffffff;
    --gray: #e9ecef;
    --sidebar: #2c4b61ff;
    --dark: #395b74ff;
    --subtitle: #364253ff;
    --border-line: #cccccc;
    --deep-blue: #0a234bff;
    --success: #28a745;
    --error: #dc3545;
    --light-green: #37ad13ff;
    /* --light-green: #3ad809ff; */
  }

     /* Reset */
    *,*::before,*::after {
        box-sizing: border-box;
    }
    
    body {
        margin: 0; 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #333;
        min-height: 100vh;
        display: flex;
        flex-direction: row;
        user-select: text;
    }

    /* Sidebar */
    .sidebar {
        width: 260px;
        background-color: var(--sidebar);
        font-size: 16px;
        font-weight: 500;
        font-family: inherit;
        color: white;
        padding: 1rem;
        height: 100vh;
        position: fixed;
        display: flex;
        flex-direction: column;
        box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
        z-index: 100;
    }

    .sidebar-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .company-logo {
        width: 140px;
        height: 70px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.1rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .company-logo img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .company-slogan {
        font-size: 1rem;
        color: rgba(255, 255, 255, 0.7);
        margin-top: 0.25rem;
        text-align: center;
    }

    .nav-menu {
        flex-grow: 1;
    }

    .nav-link {
        display: flex;
        align-items: center;
        padding: 0.5rem 1rem;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        border-radius: 6px;
        margin-bottom: 0.5rem;
        transition: all 0.3s ease;
    }

    .nav-link i {
        margin-right: 0.75rem;
        font-size: 1.3rem;
        color: var(--light-green);
        margin-bottom: 0.010rem;
    }

    .nav-link:hover,
    .nav-link.active {
        background-color: rgba(255, 255, 255, 0.1);
        color: white;
    }

    .nav-link.active {
        font-weight: 600;
    }

    .sidebar-footer {
        margin-top: auto;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.75rem 1.5rem;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
    }

    .btn-back {
        background: white;
        color: var(--dark);
        width: 100%;
        margin-bottom: 1rem;
    }

    .btn-back:hover {
        background: var(--gray);
    }

    .btn-logout {
        background: var(--error);
        color: white;
        width: 100%;
    }

    .btn-logout:hover {
        background: #c82333;
    }

    /* === MAIN CONTENT AREA === */
    .main-content {
        margin-left: 240px;
        padding: 2.5rem 2rem;
        flex: 1;
        transition: margin 0.3s;
        background-color: var(--background);
    }

    .main-content-header h1{
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--dark);
        margin-top: -20px;
        margin-bottom: 1rem;
        margin-left: 25px;
    }

    .card {
        background: var(--white);
        border-radius: 5px;
        box-shadow: 0 4px 20px rgba(44, 62, 80, 0.08);
        padding: 2rem 2rem;
        max-width: 1100px;
        margin-top: 5px;
        margin-left: 25px;
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
        color: #495057;
        letter-spacing: 0.01em;
    }

    .form-control {
        width: 100%;
        height: 2.2rem;
        padding: 0.5rem 0.8rem;
        font-size: 1rem;
        border: 1px solid var(--gray);
        border-radius: 7px;
        background-color: #f8f9fa;
        color: #333;
        transition: border 0.3s, box-shadow 0.3s;
        box-sizing: border-box;
        font-family: inherit;
    }

    .form-control:focus {
        outline: none;
        font-family: inherit;
        border-color: var(--primary);
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
        border: 2px dashed var(--gray);
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
        color: var(--primary);
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
        transform: translateY(-2px) scale(1.01);
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
    <div class="d-flex">
        <aside class="sidebar" role="complementary" aria-label="Sidebar navigation">
            <div class="sidebar-header">
                <div class="company-logo" aria-hidden="true">
                    <img src="/images/logo/Telesol_logo.jpeg" alt="Company Logo" />
                </div>
                <div class="company-slogan">Customer Relationship Management</div>
            </div>
            <nav class="nav-menu" role="navigation" aria-label="Main menu">
                <a href="dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="bi bi-list" aria-hidden="true"></i> Menu
                </a>
                <a href="log_ticket.php" class="nav-link active">
                    <i class="bi bi-journal-plus" aria-hidden="true"></i> Log Ticket
                </a>
                <a href="view_tickets.php" class="nav-link">
                    <i class="bi bi-hdd-network" aria-hidden="true"></i> View Tickets
                </a>
                <a href="log_installations.php" class="nav-link">
                    <i class="bi bi-journal-plus" aria-hidden="true"></i> Log Installation
                </a>
                <a href="view_installations.php" class="nav-link">
                    <i class="bi bi-hdd-network" aria-hidden="true"></i> View Installations
                </a>
                <a href="customer_experience_dashboard.php" class="nav-link">
                    <i class="bi bi-speedometer2" aria-hidden="true"></i> Customer Experience
                </a>
                <a href="field_installations.php" class="nav-link">
                    <i class="bi bi-hdd-network" aria-hidden="true"></i> Field Installations
                </a>
            </nav>
            <div class="sidebar-footer">
                <button class="btn btn-back" type="button" onclick="window.history.back()" aria-label="Go back">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Back
                </button>
                <form action="logout.php" method="POST">
                    <button type="submit" class="btn btn-logout">
                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Logout
                    </button>
                </form>
            </div>
        </aside>
    </div>

    <!-- Main Content -->
    <main class="main-content" role="main" aria-label="Log New Issue Form">
        <div class="main-content-header">
            <h1>Log Ticket</h1>
        </div>

        <div class="card">
            <header class="card-header">
                <!-- <h1 class="card-title">Log Ticket</h1> -->
                <p class="card-subtitle">Please fill in the details below</p>
            </header>

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
    </main>

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
