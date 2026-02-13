<?php
session_start();

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$username = $_SESSION['username'] ?? 'User';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$servername = "localhost";
$db_username = "root";
$password_db = "";
$dbname = "telesol crm";

$conn = new mysqli($servername, $db_username, $password_db, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

function esc($str) {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// --- Helper function for safe queries ---
function safeQuery($conn, $sql) {
    $result = $conn->query($sql);
    if ($result === false) {
        error_log("SQL Error: " . $conn->error . " in query: " . $sql);
        return false;
    }
    return $result;
}

// --- Fetch team members for dropdown ---
$members = [];
$members_result = safeQuery($conn, "SELECT name FROM team_members WHERE department='Administration' ORDER BY name ASC");
if ($members_result) {
    while ($m = $members_result->fetch_assoc()) {
        $members[] = $m['name'];
    }
}

// --- HANDLE POST REQUESTS ---
$errors = [];
$success = false;

// Create Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $task_title = trim($_POST['task_title'] ?? '');
        $category = $_POST['task_category'] ?? null;
        $priority = $_POST['priority'] ?? 'Medium';
        $assigned_to = $_POST['assigned_to'] ?? '';  // single value
        $start_date = $_POST['start_date'] ?? null;      // YYYY-MM-DD
        $deadline_date = $_POST['deadline_date'] ?? null; // YYYY-MM-DD
        $task_description = trim($_POST['task_description'] ?? '');

        // File upload
        $attachment_path = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/tasks/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $file_name = time() . '_' . basename($_FILES['attachment']['name']);
            $target_file = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_file)) {
                $attachment_path = $target_file;
            } else {
                $errors[] = "Failed to upload attachment.";
            }
        }

        if ($task_title === '' || $assigned_to === '') {
            $errors[] = "Task title and assignee are required.";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("
                INSERT INTO tasks (task_title, task_description, category, department, assigned_by, assigned_to, priority, start_date, deadline_date, attachment)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$stmt) {
                $errors[] = "Prepare failed: " . $conn->error;
            } else {
                $department = 'Administration';
                $stmt->bind_param(
                    "ssssssssss",
                    $task_title,
                    $task_description,
                    $category,
                    $department,
                    $username,
                    $assigned_to,
                    $priority,
                    $start_date,      // store only the date part
                    $deadline_date,   // store only the date part
                    $attachment_path
                );
                if ($stmt->execute()) {
                    $success = true;
                } else {
                    $errors[] = "Insert failed: " . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// Update Task (AJAX) – now includes start_date and deadline_date
if (isset($_POST['update_task'])) {
    $task_id = intval($_POST['task_id']);
    $task_title = trim($_POST['task_title']);
    $task_description = trim($_POST['task_description']);
    $assigned_to = $_POST['assigned_to'];
    $priority = $_POST['priority'];
    $status = $_POST['status'];
    $start_date = $_POST['start_date'] ?? null;
    $deadline_date = $_POST['deadline_date'] ?? null;

    $stmt = $conn->prepare("UPDATE tasks SET task_title=?, task_description=?, assigned_to=?, priority=?, status=?, start_date=?, deadline_date=? WHERE id=?");
    $stmt->bind_param("sssssssi", $task_title, $task_description, $assigned_to, $priority, $status, $start_date, $deadline_date, $task_id);
    $stmt->execute();
    $stmt->close();

    echo "success";
    exit;
}

// Delete Task (AJAX)
if (isset($_POST['delete_task'])) {
    $task_id = intval($_POST['task_id']);
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id=?");
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $stmt->close();
    echo "success";
    exit;
}

// Get single task data for edit modal (AJAX)
if (isset($_GET['get_task'])) {
    $task_id = intval($_GET['task_id']);
    $result = $conn->query("SELECT * FROM tasks WHERE id=$task_id");
    if ($row = $result->fetch_assoc()) {
        echo json_encode($row);
    }
    exit;
}

// ---- METRICS QUERIES ----
$total_tasks = 0;
$pending = 0;
$in_progress = 0;
$completed = 0;
$high_critical = 0;

$count_result = safeQuery($conn, "SELECT COUNT(*) as count FROM tasks");
if ($count_result) $total_tasks = $count_result->fetch_assoc()['count'];

$status_result = safeQuery($conn, "SELECT status, COUNT(*) as count FROM tasks GROUP BY status");
if ($status_result) {
    $status_counts = [];
    while ($row = $status_result->fetch_assoc()) {
        $status_counts[$row['status']] = $row['count'];
    }
    $pending = $status_counts['Pending'] ?? 0;
    $in_progress = $status_counts['In Progress'] ?? 0;
    $completed = $status_counts['Completed'] ?? 0;
}

$priority_result = safeQuery($conn, "SELECT priority, COUNT(*) as count FROM tasks GROUP BY priority");
if ($priority_result) {
    $priority_counts = [];
    while ($row = $priority_result->fetch_assoc()) {
        $priority_counts[$row['priority']] = $row['count'];
    }
    $high_critical = ($priority_counts['High'] ?? 0) + ($priority_counts['Critical'] ?? 0);
}

// Tasks per assigned person
$assigned_data = [];
$person_result = safeQuery($conn, "
    SELECT assigned_to, COUNT(*) as count 
    FROM tasks 
    WHERE assigned_to IS NOT NULL AND assigned_to != ''
    GROUP BY assigned_to 
    ORDER BY count DESC 
    LIMIT 10
");
if ($person_result) {
    while ($row = $person_result->fetch_assoc()) {
        $assigned_data[$row['assigned_to']] = $row['count'];
    }
}
$assigned_labels = json_encode(array_keys($assigned_data));
$assigned_counts = json_encode(array_values($assigned_data));

// Status distribution
$status_labels = json_encode(['Pending', 'In Progress', 'Completed']);
$status_data = json_encode([$pending, $in_progress, $completed]);

// Distinct persons for filter dropdown
$persons_filter = [];
$persons_result = safeQuery($conn, "SELECT DISTINCT assigned_to FROM tasks WHERE assigned_to IS NOT NULL AND assigned_to != '' ORDER BY assigned_to");
if ($persons_result) {
    while ($row = $persons_result->fetch_assoc()) {
        $persons_filter[] = $row['assigned_to'];
    }
}

// Fetch all tasks for the table
$tasks_result = safeQuery($conn, "SELECT * FROM tasks ORDER BY id DESC");
if (!$tasks_result) {
    $tasks_result = null;
    $errors[] = "Could not fetch tasks. Please ensure the database tables are set up correctly.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administration Tasks - Telesol CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- Quill CSS -->
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        --white: #ffffff;
        --border-radius: 8px;
        --transition: 0.3s ease;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
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
        padding: 1rem;
        text-align: center;
        font-weight: 300;
        font-size: 0.9rem;
        letter-spacing: 0.5px;
    }

    .sidebar-header img {
        max-width: 80%;
        height: auto;
        margin-bottom: 0.5rem;
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
        padding: 0.5rem 0.5rem;
        display: block;
        font-size: 14px;
        font-weight: 400;
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

    .main-content {
        margin-left: 220px;
        padding: 20px;
        width: calc(100% - 220px);
    }

    .header {
        background: var(--primary);
        color: var(--white);
        padding: 10px 20px;
        margin-bottom: 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        position: sticky;
        top: 0;
        z-index: 100;
        border-radius: var(--border-radius);
    }

    .user-info {
        display: flex;
        align-items: center;
        background: rgba(255,255,255,0.2);
        padding: 0.3rem 1rem;
        border-radius: 30px;
    }

    .user-info i {
        margin-right: 8px;
    }

    .metric-card {
        background: var(--white);
        border-radius: var(--border-radius);
        padding: 1.2rem;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        transition: var(--transition);
        border-left: 4px solid transparent;
    }
    .metric-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    }
    .metric-card.primary { border-left-color: var(--primary); }
    .metric-card.warning { border-left-color: var(--warning); }
    .metric-card.success { border-left-color: var(--success); }
    .metric-card.danger { border-left-color: var(--danger); }
    .metric-card .title {
        font-size: 0.9rem;
        color: var(--gray);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .metric-card .value {
        font-size: 2rem;
        font-weight: 600;
        color: var(--dark);
    }

    .chart-container {
        background: var(--white);
        border-radius: var(--border-radius);
        padding: 1rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .chart-container canvas {
        max-height: 250px;
    }

    .badge-low { background: #6c757d; color: white; }
    .badge-medium { background: #3498db; color: white; }
    .badge-high { background: #f39c12; color: white; }
    .badge-critical { background: #e74c3c; color: white; }
    .badge-pending { background: #dc3545; color: white; }
    .badge-inprogress { background: #ffc107; color: #212529; }
    .badge-completed { background: #28a745; color: white; }

    .table-hover tbody tr:hover {
        background: #f1f1f1;
    }
    .action-btn {
        margin-right: 5px;
        cursor: pointer;
    }
    .assignee-badge {
        background-color: #e9ecef;
        color: #495057;
        padding: 0.2rem 0.5rem;
        border-radius: 20px;
        font-size: 0.8rem;
        display: inline-block;
        margin: 2px;
    }
    .priority-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-left: 5px;
    }
</style>
<body>

<!-- Sidebar (unchanged) -->
<aside class="sidebar" aria-label="Main navigation">
    <div class="sidebar-header">
        <img src="/images/logo/Telesol_logo.jpeg" alt="Telesol Logo" style="max-width: 120px;">
        <h5>Telesol CRM</h5>
    </div>
    <nav class="sidebar-menu">
        <ul>
            <li><a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="task_overview.php" class="active"><i class="bi bi-ticket-detailed"></i> Task Overview</a></li>
            <li><a href="internal_request.php"><i class="bi bi-wrench"></i> Internal Requisitions</a></li>
            <li><a href="customer_experience_dashboard.php"><i class="bi bi-people"></i> Customer Experience</a></li>
            <li><a href="report.php"><i class="bi bi-bar-chart"></i> Reports</a></li>
            <li><a href="#"><i class="bi bi-gear"></i> Settings</a></li>
            <li><a href="#"><i class="bi bi-arrow-left-circle"></i> Back</a></li>
            <li><a href="login.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
    </nav>
</aside>

<div class="main-content">
    <!-- Header with user -->
    <div class="header">
        <h4>Task Management</h4>
        <div class="user-info">
            <i class="bi bi-person-circle"></i> <?= esc($username) ?>
        </div>
    </div>

    <!-- Alert messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><?php foreach ($errors as $e) echo "<div>" . esc($e) . "</div>"; ?></div>
    <?php elseif ($success): ?>
        <div class="alert alert-success">Task created successfully!</div>
    <?php endif; ?>

    <!-- Create Task Button -->
    <div class="mb-3">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createTaskModal">
            <i class="bi bi-plus-circle"></i> Create New Task
        </button>
    </div>

    <!-- Create Task Modal (single assignee) -->
    <div class="modal fade" id="createTaskModal" tabindex="-1" aria-labelledby="createTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data" id="taskForm">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createTaskModalLabel">Create New Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label>Task Title *</label>
                                <input type="text" name="task_title" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label>Category</label>
                                <select name="task_category" class="form-select">
                                    <option value="">Select category</option>
                                    <option>Procurement</option>
                                    <option>Maintenance</option>
                                    <option>Installations</option>
                                    <option>Internal Request</option>
                                    <option>Documentation</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>Priority <span class="priority-dot" id="priorityDot"></span></label>
                                <select name="priority" id="prioritySelect" class="form-select">
                                    <option value="">Select Priority</option>
                                    <option>Low</option>
                                    <option>Medium</option>
                                    <option>High</option>
                                    <option>Critical</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label>Assign To *</label>
                                <select name="assigned_to" class="form-select select2" required>
                                    <option value="">Select team member</option>
                                    <?php foreach ($members as $m): ?>
                                        <option value="<?= esc($m) ?>"><?= esc($m) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label>Start Date</label>
                                <input type="date" name="start_date" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label>Deadline Date</label>
                                <input type="date" name="deadline_date" class="form-control">
                            </div>
                            <div class="col-12">
                                <label>Description</label>
                                <div id="editor" style="height:150px;"></div>
                                <textarea name="task_description" id="hiddenDesc" hidden></textarea>
                            </div>
                            <div class="col-12">
                                <label>Attachment</label>
                                <input type="file" name="attachment" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="reset" class="btn btn-outline-secondary">Reset</button>
                        <button type="submit" name="create_task" class="btn btn-primary">Create Task</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Modal (single assignee) – simplified, only date fields -->
    <div class="modal fade" id="editTaskModal" tabindex="-1" aria-labelledby="editTaskModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTaskModalLabel">Edit Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="edit_task_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label>Task Title *</label>
                            <input type="text" id="edit_task_title" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label>Category</label>
                            <select id="edit_category" class="form-select">
                                <option value="">Select category</option>
                                <option>Procurement</option>
                                <option>Maintenance</option>
                                <option>Installations</option>
                                <option>Internal Request</option>
                                <option>Documentation</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Priority</label>
                            <select id="edit_priority" class="form-select">
                                <option>Low</option>
                                <option>Medium</option>
                                <option>High</option>
                                <option>Critical</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Assign To *</label>
                            <select id="edit_assigned_to" class="form-select select2" required>
                                <option value="">Select team member</option>
                                <?php foreach ($members as $m): ?>
                                    <option value="<?= esc($m) ?>"><?= esc($m) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Start Date</label>
                            <input type="date" id="edit_start_date" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label>Deadline Date</label>
                            <input type="date" id="edit_deadline_date" class="form-control">
                        </div>
                        <div class="col-12">
                            <label>Description</label>
                            <div id="edit_editor" style="height:150px;"></div>
                            <textarea id="edit_hiddenDesc" hidden></textarea>
                        </div>
                        <div class="col-12">
                            <label>Status</label>
                            <select id="edit_status" class="form-select">
                                <option>Pending</option>
                                <option>In Progress</option>
                                <option>Completed</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveEditBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="metric-card primary">
                <div class="title">Total Tasks</div>
                <div class="value"><?= $total_tasks ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card warning">
                <div class="title">Pending</div>
                <div class="value"><?= $pending ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card primary">
                <div class="title">In Progress</div>
                <div class="value"><?= $in_progress ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card success">
                <div class="title">Completed</div>
                <div class="value"><?= $completed ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card danger">
                <div class="title">High/Critical</div>
                <div class="value"><?= $high_critical ?></div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <div class="col-md-6">
            <div class="chart-container">
                <h5>Tasks per Assigned Person</h5>
                <canvas id="assignedChart"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-container">
                <h5>Task Status Distribution</h5>
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Task List (with Category and static Status badge) -->
    <div class="card p-4">
        <h4>All Administration Tasks</h4>
        <div class="row mb-3">
            <div class="col-md-3">
                <input class="form-control" id="taskSearch" type="text" placeholder="Search tasks...">
            </div>
            <div class="col-md-2">
                <select id="priorityFilter" class="form-select">
                    <option value="">All Priorities</option>
                    <option>Low</option>
                    <option>Medium</option>
                    <option>High</option>
                    <option>Critical</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="statusFilter" class="form-select">
                    <option value="">All Status</option>
                    <option>Pending</option>
                    <option>In Progress</option>
                    <option>Completed</option>
                </select>
            </div>
            <div class="col-md-2">
                <select id="personFilter" class="form-select">
                    <option value="">All Persons</option>
                    <?php foreach ($persons_filter as $person): ?>
                        <option value="<?= esc($person) ?>"><?= esc($person) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-outline-secondary" id="clearFilters" type="button">Clear</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle" id="taskTable">
                <thead class="table-light">
                    <tr>
                        <th>Task</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Assigned To</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Start Date</th>
                        <th>Deadline</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($tasks_result): while ($row = $tasks_result->fetch_assoc()): 
                    $start_date_display = $row['start_date'] ?? '';
                    $deadline_display = $row['deadline_date'] ?? '';
                    $status_class = '';
                    $status_text = $row['status'] ?? 'Pending';
                    if ($status_text == 'Pending') $status_class = 'badge-pending';
                    elseif ($status_text == 'In Progress') $status_class = 'badge-inprogress';
                    elseif ($status_text == 'Completed') $status_class = 'badge-completed';
                ?>
                    <tr data-id="<?= $row['id'] ?>">
                        <td><?= esc($row['task_title']) ?></td>
                        <td><?= esc(strip_tags($row['task_description'])) ?></td> <!-- Strip HTML tags for clean display -->
                        <td><?= esc($row['category'] ?? '') ?></td>
                        <td><?= esc($row['assigned_to']) ?></td>
                        <td>
                            <span class="badge 
                                <?php 
                                $p = strtolower($row['priority']); 
                                echo $p === 'low' ? 'badge-low' : ($p === 'medium' ? 'badge-medium' : ($p === 'high' ? 'badge-high' : 'badge-critical')); 
                                ?>">
                                <?= esc($row['priority']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= $status_class ?>"><?= esc($status_text) ?></span>
                        </td>
                        <td><?= esc($start_date_display) ?></td>
                        <td><?= esc($deadline_display) ?></td>
                        <td>
                            <i class="bi bi-pencil text-primary action-btn" onclick="editTask(<?= $row['id'] ?>)" title="Edit"></i>
                            <i class="bi bi-trash text-danger action-btn" onclick="deleteTask(<?= $row['id'] ?>)" title="Delete"></i>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- JavaScript libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Quill JS -->
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

<script>
    // Initialize Select2 (single select)
    $('.select2').select2({
        dropdownParent: $('#createTaskModal'),
        placeholder: 'Select a team member',
        width: '100%'
    });

    // Initialize Quill for create modal
    var quill = new Quill('#editor', {
        theme: 'snow',
        placeholder: 'Write task description...'
    });
    // Sync Quill content to hidden textarea before submit
    document.getElementById('taskForm').addEventListener('submit', function() {
        document.getElementById('hiddenDesc').value = quill.root.innerHTML;
    });

    // Priority dot color change
    document.getElementById('prioritySelect').addEventListener('change', function() {
        var dot = document.getElementById('priorityDot');
        var priority = this.value.toLowerCase();
        var color = '';
        if (priority === 'low') color = '#6c757d';
        else if (priority === 'medium') color = '#3498db';
        else if (priority === 'high') color = '#f39c12';
        else if (priority === 'critical') color = '#e74c3c';
        else color = 'transparent';
        dot.style.backgroundColor = color;
    });

    // Table filtering (column indices: Category at index 2, Assigned To at 3, Priority at 4, Status at 5)
    const taskTableRows = document.querySelectorAll('#taskTable tbody tr');

    function filterTable() {
        const searchVal = document.getElementById('taskSearch').value.toLowerCase();
        const priorityVal = document.getElementById('priorityFilter').value;
        const statusVal = document.getElementById('statusFilter').value;
        const personVal = document.getElementById('personFilter').value;

        taskTableRows.forEach(r => {
            const text = r.textContent.toLowerCase();
            const priority = r.children[4].textContent.trim(); // Priority column (badge)
            const status = r.children[5].textContent.trim();   // Status column (badge)
            const assignedTo = r.children[3].textContent.trim(); // Assigned To column

            const matchesSearch = searchVal === '' || text.includes(searchVal);
            const matchesPriority = priorityVal === '' || priority === priorityVal;
            const matchesStatus = statusVal === '' || status === statusVal;
            const matchesPerson = personVal === '' || assignedTo === personVal;

            r.style.display = (matchesSearch && matchesPriority && matchesStatus && matchesPerson) ? '' : 'none';
        });
    }

    document.getElementById('taskSearch').addEventListener('keyup', filterTable);
    document.getElementById('priorityFilter').addEventListener('change', filterTable);
    document.getElementById('statusFilter').addEventListener('change', filterTable);
    document.getElementById('personFilter').addEventListener('change', filterTable);
    document.getElementById('clearFilters').addEventListener('click', function() {
        document.getElementById('taskSearch').value = '';
        document.getElementById('priorityFilter').value = '';
        document.getElementById('statusFilter').value = '';
        document.getElementById('personFilter').value = '';
        filterTable();
    });

    // Edit Task – populate modal with current data (using start_date/deadline_date)
    function editTask(taskId) {
        fetch('?get_task=1&task_id=' + taskId)
            .then(response => response.json())
            .then(data => {
                document.getElementById('edit_task_id').value = data.id;
                document.getElementById('edit_task_title').value = data.task_title;
                document.getElementById('edit_category').value = data.category || '';
                document.getElementById('edit_priority').value = data.priority;

                // Set dates directly (no time splitting)
                document.getElementById('edit_start_date').value = data.start_date || '';
                document.getElementById('edit_deadline_date').value = data.deadline_date || '';

                // Set single assignee
                $('#edit_assigned_to').val(data.assigned_to).trigger('change');

                // Set description in Quill
                if (editQuill) {
                    editQuill.root.innerHTML = data.task_description || '';
                }

                document.getElementById('edit_status').value = data.status;

                // Show modal
                new bootstrap.Modal(document.getElementById('editTaskModal')).show();
            });
    }

    // Initialize Quill for edit modal
    var editQuill = new Quill('#edit_editor', {
        theme: 'snow',
        placeholder: 'Write task description...'
    });

    // Save Edit – now includes start_date and deadline_date
    document.getElementById('saveEditBtn').addEventListener('click', function() {
        const taskId = document.getElementById('edit_task_id').value;
        const taskTitle = document.getElementById('edit_task_title').value;
        const category = document.getElementById('edit_category').value;
        const priority = document.getElementById('edit_priority').value;
        const assignedTo = document.getElementById('edit_assigned_to').value;
        const startDate = document.getElementById('edit_start_date').value;
        const deadlineDate = document.getElementById('edit_deadline_date').value;
        const description = editQuill.root.innerHTML;
        const status = document.getElementById('edit_status').value;

        if (!taskTitle || !assignedTo) {
            alert('Task title and assignee are required.');
            return;
        }

        const params = new URLSearchParams({
            update_task: 1,
            task_id: taskId,
            task_title: taskTitle,
            task_description: description,
            assigned_to: assignedTo,
            priority: priority,
            status: status,
            start_date: startDate,
            deadline_date: deadlineDate
        });

        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: params
        })
        .then(response => response.text())
        .then(result => {
            if (result === 'success') {
                location.reload();
            } else {
                alert('Update failed');
            }
        });
    });

    // Delete Task
    function deleteTask(taskId) {
        if (confirm('Are you sure you want to delete this task?')) {
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'delete_task=1&task_id=' + taskId
            })
            .then(response => response.text())
            .then(result => {
                if (result === 'success') {
                    location.reload();
                } else {
                    alert('Delete failed');
                }
            });
        }
    }

    // Charts
    document.addEventListener('DOMContentLoaded', function() {
        const ctx1 = document.getElementById('assignedChart').getContext('2d');
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: <?= $assigned_labels ?>,
                datasets: [{
                    label: 'Tasks',
                    data: <?= $assigned_counts ?>,
                    backgroundColor: '#3498db',
                    borderColor: '#2980b9',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } }
                }
            }
        });

        const ctx2 = document.getElementById('statusChart').getContext('2d');
        new Chart(ctx2, {
            type: 'pie',
            data: {
                labels: <?= $status_labels ?>,
                datasets: [{
                    data: <?= $status_data ?>,
                    backgroundColor: ['#e74c3c', '#f39c12', '#28a745'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    });

    // Auto-hide alerts
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(a => a.style.display = 'none');
    }, 5000);
</script>
</body>
</html>