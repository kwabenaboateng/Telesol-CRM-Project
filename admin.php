<?php
session_start();
$username = $_SESSION['username'] ?? 'User';
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

$servername = "localhost";
$db_username = "root";
$password_db = "";
$dbname = "telesol crm";

$conn = new mysqli($servername, $db_username, $password_db, $dbname);
if($conn->connect_error) die("DB connection failed: ".htmlspecialchars($conn->connect_error));

function esc($str){ return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

// Fetch team members with additional details
$members_result = $conn->query("SELECT id, name, avatar, position FROM team_members WHERE department='Administration' ORDER BY name ASC");
$members = [];
while($m = $members_result->fetch_assoc()) $members[] = $m;

// Fetch task categories
$categories_result = $conn->query("SELECT * FROM task_categories ORDER BY name ASC");
$categories = [];
while($c = $categories_result->fetch_assoc()) $categories[] = $c;

// Handle task creation
$errors=[]; $success=false;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_task'])){
    // CSRF verification
    if(!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')){
        $errors[] = "Invalid security token.";
    } else {
        $task_title = trim($_POST['task_title'] ?? '');
        $task_description = trim($_POST['task_description'] ?? '');
        $assigned_to = $_POST['assigned_to'] ?? [];
        $priority = $_POST['priority'] ?? 'Medium';
        $category = $_POST['task_category'] ?? '';
        $start_date = $_POST['start_date'] ?? null;
        $deadline_date = $_POST['deadline_date'] ?? null;
        $estimated_hours = $_POST['estimated_hours'] ?? null;

        if($task_title==='' || empty($assigned_to)) $errors[]="Task title and assigned to are required.";
        
        // Validate dates
        if($start_date && $deadline_date && strtotime($deadline_date) < strtotime($start_date)){
            $errors[] = "Deadline cannot be earlier than start date.";
        }

        if(empty($errors)){
            $assigned_to_str = implode(',', $assigned_to);
            $stmt = $conn->prepare("
                INSERT INTO tasks (
                    task_title, task_description, department, assigned_to, assigned_by, 
                    priority, category, start_date, due_date, estimated_hours, status, created_at
                ) VALUES (?,?,?,?,?,?,?,?,?,?,'Pending', NOW())
            ");
            if($stmt){
                $stmt->bind_param(
                    "sssssssssd",
                    $task_title, $task_description, 'Administration', $assigned_to_str, $username,
                    $priority, $category, $start_date, $deadline_date, $estimated_hours
                );
                if($stmt->execute()) {
                    $success = true;
                    // Clear POST data on success
                    $_POST = [];
                }
                else $errors[] = "Failed: ".htmlspecialchars($stmt->error);
                $stmt->close();
            } else $errors[] = "DB error: ".htmlspecialchars($conn->error);
        }
    }
}

// Fetch dashboard statistics
$stats = [];
$stats_query = $conn->query("
    SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pending_tasks,
        SUM(CASE WHEN status='In Progress' THEN 1 ELSE 0 END) as in_progress_tasks,
        SUM(CASE WHEN status='Completed' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN priority='Critical' AND status != 'Completed' THEN 1 ELSE 0 END) as critical_tasks
    FROM tasks 
    WHERE department='Administration'
");
$stats = $stats_query->fetch_assoc();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Administration Tasks · Telesol CRM</title>
    
    <!-- Modern Bootstrap 5 + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Advanced UI Components -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #0a4b7a;
            --primary-light: #e6f3ff;
            --primary-dark: #083b6e;
            --secondary: #2ecc71;
            --accent: #3498db;
            --danger: #e74c3c;
            --warning: #f39c12;
            --success: #27ae60;
            --dark: #2c3e50;
            --gray: #95a5a6;
            --light: #f8f9fa;
            --sidebar-width: 260px;
            --border-radius: 12px;
            --box-shadow: 0 8px 20px rgba(0,0,0,0.05);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f0f2f5;
            color: var(--dark);
            display: flex;
            line-height: 1.6;
        }

        /* Modern Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: var(--transition);
            box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 2rem 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .company-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .company-logo img {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.2);
        }

        .company-logo h1 {
            font-size: 1.4rem;
            font-weight: 600;
            letter-spacing: -0.5px;
            margin: 0;
            color: white;
        }

        .sidebar-menu {
            padding: 1.5rem 1rem;
        }

        .sidebar-menu ul {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 0.3rem;
        }

        .sidebar-menu a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 0.8rem 1rem;
            display: flex;
            align-items: center;
            border-radius: 10px;
            font-weight: 500;
            transition: var(--transition);
            gap: 12px;
        }

        .sidebar-menu a i {
            font-size: 1.2rem;
            width: 24px;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-menu a.active {
            background: var(--secondary);
            box-shadow: 0 4px 10px rgba(46, 204, 113, 0.3);
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 30px;
            min-height: 100vh;
        }

        /* Modern Header */
        .header {
            background: white;
            border-radius: 16px;
            padding: 1.2rem 1.8rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--box-shadow);
            border: 1px solid rgba(0,0,0,0.02);
        }

        .header h2 {
            font-size: 1.6rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            letter-spacing: -0.5px;
        }

        .user-session {
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--light);
            padding: 0.6rem 1.2rem;
            border-radius: 40px;
            font-weight: 500;
        }

        .user-session i {
            font-size: 1.4rem;
            color: var(--primary);
        }

        /* Dashboard Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
            border: 1px solid rgba(0,0,0,0.02);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }

        .stat-info h3 {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-number {
            font-size: 1.9rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1;
        }

        /* Modern Form Card */
        .form-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--box-shadow);
            border: 1px solid rgba(0,0,0,0.02);
        }

        .form-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 0.8rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .form-control, .form-select {
            border: 2px solid #eef2f6;
            border-radius: 12px;
            padding: 0.7rem 1rem;
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(10, 75, 122, 0.1);
        }

        /* Priority Indicators */
        .priority-dot {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-left: 8px;
            transition: var(--transition);
        }

        .priority-badge {
            padding: 0.3rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .priority-low { background: #6c757d; color: white; }
        .priority-medium { background: #3498db; color: white; }
        .priority-high { background: #f39c12; color: white; }
        .priority-critical { background: #e74c3c; color: white; }

        /* Quill Editor Customization */
        .ql-toolbar {
            border-top-left-radius: 12px;
            border-top-right-radius: 12px;
            border: 2px solid #eef2f6 !important;
            background: white;
        }

        .ql-container {
            border-bottom-left-radius: 12px;
            border-bottom-right-radius: 12px;
            border: 2px solid #eef2f6 !important;
            border-top: none !important;
            min-height: 150px;
            font-size: 0.95rem;
        }

        /* Select2 Customization */
        .select2-container--bootstrap-5 .select2-selection {
            border: 2px solid #eef2f6 !important;
            border-radius: 12px !important;
            min-height: 46px;
            padding: 0.2rem 0.5rem;
        }

        /* Buttons */
        .btn {
            padding: 0.7rem 1.8rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: var(--transition);
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(10, 75, 122, 0.3);
        }

        .btn-outline-secondary {
            background: white;
            border: 2px solid #eef2f6;
            color: var(--dark);
        }

        .btn-outline-secondary:hover {
            background: #eef2f6;
            border-color: #d0d9e0;
            transform: translateY(-2px);
        }

        /* Alerts */
        .alert {
            border-radius: 16px;
            padding: 1rem 1.5rem;
            border: none;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 6px solid var(--success);
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 6px solid var(--danger);
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
        }

        /* Loading Skeleton */
        .skeleton {
            animation: skeleton-loading 1s linear infinite alternate;
        }

        @keyframes skeleton-loading {
            0% { opacity: 0.6; }
            100% { opacity: 1; }
        }

        /* Tooltips */
        .tooltip-icon {
            color: var(--gray);
            cursor: help;
            margin-left: 5px;
            font-size: 0.9rem;
        }

        /* Attachment Preview */
        .attachment-preview {
            border: 2px dashed #eef2f6;
            border-radius: 12px;
            padding: 0.8rem;
            display: none;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .attachment-preview.active {
            display: flex;
        }
    </style>
</head>

<body>
    <!-- Modern Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="company-logo">
                <img src="/images/logo/Telesol_logo.jpeg" alt="Telesol CRM">
                <h1>Telesol CRM</h1>
            </div>
        </div>
        
        <nav class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
                <li><a href="admin.php" class="active"><i class="bi bi-plus-circle-fill"></i> Create Task</a></li>
                <li><a href="admin_task_overview.php"><i class="bi bi-list-task"></i> Task Overview</a></li>
                <li><a href="internal_request.php"><i class="bi bi-tools"></i> Internal Requisition</a></li>
                <li><a href="installations.php"><i class="bi bi-gear-fill"></i> Installations</a></li>
                <li><a href="admin_report.php"><i class="bi bi-file-earmark-bar-graph-fill"></i> Reports</a></li>
                <li style="margin-top: 30px;"><a href="#"><i class="bi bi-arrow-left-circle"></i> Back</a></li>
                <li><a href="login.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header with User Info -->
        <header class="header">
            <div>
                <h2>Task Management</h2>
                <p style="color: var(--gray); margin-top: 5px; margin-bottom: 0;">
                    <i class="bi bi-calendar-check"></i> <?= date('l, F j, Y') ?>
                </p>
            </div>
            <div class="user-session">
                <i class="bi bi-person-circle"></i>
                <div>
                    <span style="display: block; font-size: 0.8rem; color: var(--gray);">Logged in as</span>
                    <strong><?= esc($username) ?></strong>
                </div>
            </div>
        </header>

        <!-- Dashboard Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--primary-light); color: var(--primary);">
                    <i class="bi bi-list-check"></i>
                </div>
                <div class="stat-info">
                    <h3>Total Tasks</h3>
                    <span class="stat-number"><?= number_format($stats['total_tasks'] ?? 0) ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #fff3cd; color: #856404;">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="stat-info">
                    <h3>In Progress</h3>
                    <span class="stat-number"><?= number_format($stats['in_progress_tasks'] ?? 0) ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #d4edda; color: #155724;">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3>Completed</h3>
                    <span class="stat-number"><?= number_format($stats['completed_tasks'] ?? 0) ?></span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: #f8d7da; color: #721c24;">
                    <i class="bi bi-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <h3>Critical</h3>
                    <span class="stat-number"><?= number_format($stats['critical_tasks'] ?? 0) ?></span>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if(!empty($errors)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle-fill" style="margin-right: 10px;"></i>
                <strong>Please fix the following errors:</strong>
                <ul style="margin-top: 10px; margin-bottom: 0;">
                    <?php foreach($errors as $e): ?>
                        <li><?= esc($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill" style="margin-right: 10px;"></i>
                <strong>Success!</strong> Task has been assigned successfully.
            </div>
        <?php endif; ?>

        <!-- Create Task Form Card -->
        <div class="form-card">
            <div class="form-section-title">
                <i class="bi bi-pencil-square"></i>
                Create New Task
                <span class="badge bg-primary" style="margin-left: 15px; font-size: 0.8rem; padding: 0.3rem 0.8rem;">
                    Administration Department
                </span>
            </div>

            <form method="POST" enctype="multipart/form-data" id="taskForm">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <input type="hidden" name="create_task" value="1">
                <input type="hidden" name="task_description" id="taskDescription">

                <div class="row g-4">
                    <!-- Task Title -->
                    <div class="col-md-8">
                        <label class="form-label">
                            <i class="bi bi-card-heading"></i>
                            Task Title <span class="text-danger">*</span>
                            <i class="bi bi-question-circle tooltip-icon" data-bs-toggle="tooltip" title="Enter a clear, concise title for the task"></i>
                        </label>
                        <input type="text" 
                               name="task_title" 
                               class="form-control" 
                               placeholder="e.g., Install new security cameras at North Wing"
                               value="<?= esc($_POST['task_title'] ?? '') ?>"
                               required
                               maxlength="200"
                               autocomplete="off">
                        <div class="form-text text-muted">
                            <span id="titleCharCount">0</span>/200 characters
                        </div>
                    </div>

                    <!-- Estimated Hours -->
                    <div class="col-md-4">
                        <label class="form-label">
                            <i class="bi bi-hourglass-split"></i>
                            Est. Hours
                        </label>
                        <input type="number" 
                               name="estimated_hours" 
                               class="form-control" 
                               placeholder="2.5"
                               step="0.5"
                               min="0.5"
                               max="168"
                               value="<?= esc($_POST['estimated_hours'] ?? '') ?>">
                    </div>

                    <!-- Category -->
                    <div class="col-md-4">
                        <label class="form-label">
                            <i class="bi bi-tag"></i>
                            Category
                        </label>
                        <select name="task_category" class="form-select">
                            <option value="">Select category</option>
                            <option value="Procurement" <?= ($_POST['task_category'] ?? '') == 'Procurement' ? 'selected' : '' ?>>Procurement</option>
                            <option value="Maintenance" <?= ($_POST['task_category'] ?? '') == 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                            <option value="Installations" <?= ($_POST['task_category'] ?? '') == 'Installations' ? 'selected' : '' ?>>Installations</option>
                            <option value="Internal Request" <?= ($_POST['task_category'] ?? '') == 'Internal Request' ? 'selected' : '' ?>>Internal Request</option>
                            <option value="Documentation" <?= ($_POST['task_category'] ?? '') == 'Documentation' ? 'selected' : '' ?>>Documentation</option>
                        </select>
                    </div>

                    <!-- Priority -->
                    <div class="col-md-4">
                        <label class="form-label">
                            <i class="bi bi-flag"></i>
                            Priority
                            <span class="priority-dot" id="priorityDot"></span>
                        </label>
                        <select name="priority" id="prioritySelect" class="form-select">
                            <option value="Low" <?= ($_POST['priority'] ?? '') == 'Low' ? 'selected' : '' ?>>Low</option>
                            <option value="Medium" <?= ($_POST['priority'] ?? '') == 'Medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="High" <?= ($_POST['priority'] ?? '') == 'High' ? 'selected' : '' ?>>High</option>
                            <option value="Critical" <?= ($_POST['priority'] ?? '') == 'Critical' ? 'selected' : '' ?>>Critical</option>
                        </select>
                        <div id="priorityPreview" class="mt-2" style="display: none;">
                            <span class="priority-badge">Preview</span>
                        </div>
                    </div>

                    <!-- Assign To (Multiple) -->
                    <div class="col-md-4">
                        <label class="form-label">
                            <i class="bi bi-people"></i>
                            Assign To <span class="text-danger">*</span>
                        </label>
                        <select name="assigned_to[]" class="form-select select2" multiple required>
                            <?php foreach($members as $member): ?>
                                <option value="<?= esc($member['name']) ?>" 
                                        <?= in_array($member['name'], ($_POST['assigned_to'] ?? [])) ? 'selected' : '' ?>>
                                    <?= esc($member['name']) ?> - <?= esc($member['position'] ?? 'Team Member') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted">
                            You can select multiple team members
                        </div>
                    </div>

                    <!-- Date Range -->
                    <div class="col-md-3">
                        <label class="form-label">
                            <i class="bi bi-calendar-plus"></i>
                            Start Date
                        </label>
                        <input type="date" 
                               name="start_date" 
                               id="startDate" 
                               class="form-control"
                               value="<?= esc($_POST['start_date'] ?? '') ?>">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">
                            <i class="bi bi-calendar-check"></i>
                            Deadline Date
                        </label>
                        <input type="date" 
                               name="deadline_date" 
                               id="deadlineDate" 
                               class="form-control"
                               value="<?= esc($_POST['deadline_date'] ?? '') ?>">
                    </div>

                    <!-- Description with Rich Text Editor -->
                    <div class="col-12">
                        <label class="form-label">
                            <i class="bi bi-file-text"></i>
                            Description
                            <i class="bi bi-question-circle tooltip-icon" data-bs-toggle="tooltip" title="Add detailed description, checklists, or requirements"></i>
                        </label>
                        <div id="editor" style="height: 200px;"><?= $_POST['task_description'] ?? '' ?></div>
                    </div>

                    <!-- Attachment -->
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="bi bi-paperclip"></i>
                            Attachment
                        </label>
                        <input type="file" 
                               name="attachment" 
                               id="attachment" 
                               class="form-control"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip">
                        <div class="form-text text-muted">
                            Max file size: 10MB. Supported: PDF, DOC, XLS, Images, ZIP
                        </div>
                        
                        <!-- Attachment Preview -->
                        <div id="attachmentPreview" class="attachment-preview">
                            <i class="bi bi-file-earmark"></i>
                            <span id="fileName"></span>
                            <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="clearAttachment()">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Tags -->
                    <div class="col-md-6">
                        <label class="form-label">
                            <i class="bi bi-tags"></i>
                            Tags
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="tags" 
                               placeholder="Enter tags separated by comma"
                               value="<?= esc($_POST['tags'] ?? '') ?>">
                        <div class="form-text text-muted">
                            e.g., urgent, maintenance, high-priority
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="col-12 mt-4 d-flex justify-content-end gap-3">
                        <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                            <i class="bi bi-arrow-counterclockwise"></i>
                            Reset
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="bi bi-send-fill"></i>
                            Create Task
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        // Initialize all components when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Initialize Select2 with advanced options
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Select team members',
                allowClear: true,
                closeOnSelect: false
            });

            // Initialize Quill Rich Text Editor
            var quill = new Quill('#editor', {
                theme: 'snow',
                placeholder: 'Write task description in detail...',
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline', 'strike'],
                        ['blockquote', 'code-block'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'script': 'sub'}, { 'script': 'super' }],
                        [{ 'indent': '-1'}, { 'indent': '+1' }],
                        [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                        [{ 'color': [] }, { 'background': [] }],
                        ['link', 'image'],
                        ['clean']
                    ]
                }
            });

            // Set initial editor content
            const initialContent = `<?= addslashes($_POST['task_description'] ?? '') ?>`;
            if (initialContent) {
                quill.root.innerHTML = initialContent;
            }

            // Update hidden field before form submit
            document.getElementById('taskForm').onsubmit = function() {
                document.getElementById('taskDescription').value = quill.root.innerHTML;
                return true;
            };

            // Initialize date pickers with constraints
            flatpickr("#startDate", {
                minDate: "today",
                dateFormat: "Y-m-d",
                allowInput: true
            });

            flatpickr("#deadlineDate", {
                minDate: "today",
                dateFormat: "Y-m-d",
                allowInput: true
            });

            // Priority color indicator
            const prioritySelect = document.getElementById('prioritySelect');
            const priorityDot = document.getElementById('priorityDot');
            const priorityPreview = document.getElementById('priorityPreview');

            const priorityColors = {
                'Low': '#6c757d',
                'Medium': '#3498db',
                'High': '#f39c12',
                'Critical': '#e74c3c'
            };

            function updatePriorityIndicator() {
                const priority = prioritySelect.value;
                priorityDot.style.backgroundColor = priorityColors[priority] || '#6c757d';
                
                // Show preview badge
                if (priority) {
                    const previewBadge = priorityPreview.querySelector('.priority-badge');
                    previewBadge.textContent = priority;
                    previewBadge.className = `priority-badge priority-${priority.toLowerCase()}`;
                    priorityPreview.style.display = 'block';
                } else {
                    priorityPreview.style.display = 'none';
                }
            }

            prioritySelect.addEventListener('change', updatePriorityIndicator);
            updatePriorityIndicator();

            // Date validation
            function validateDates() {
                const startDate = document.getElementById('startDate').value;
                const deadlineDate = document.getElementById('deadlineDate').value;
                
                if (startDate && deadlineDate) {
                    if (new Date(deadlineDate) < new Date(startDate)) {
                        alert('Deadline cannot be earlier than start date.');
                        document.getElementById('deadlineDate').value = '';
                    }
                }
            }

            document.getElementById('startDate').addEventListener('change', validateDates);
            document.getElementById('deadlineDate').addEventListener('change', validateDates);

            // Character counter for title
            const titleInput = document.querySelector('input[name="task_title"]');
            const charCount = document.getElementById('titleCharCount');

            function updateCharCount() {
                charCount.textContent = titleInput.value.length;
                if (titleInput.value.length > 180) {
                    charCount.style.color = '#e74c3c';
                } else {
                    charCount.style.color = '#95a5a6';
                }
            }

            titleInput.addEventListener('input', updateCharCount);
            updateCharCount();

            // File attachment preview
            const attachment = document.getElementById('attachment');
            const preview = document.getElementById('attachmentPreview');
            const fileName = document.getElementById('fileName');

            attachment.addEventListener('change', function(e) {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    fileName.textContent = `${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
                    preview.classList.add('active');
                } else {
                    clearAttachment();
                }
            });

            // Tags input enhancement
            $('#tags').on('keypress', function(e) {
                if (e.which === 13 || e.which === 44) { // Enter or comma
                    e.preventDefault();
                    const tags = $(this).val().split(',').map(t => t.trim()).filter(t => t);
                    if (tags.length > 0) {
                        // You can implement tag chips here
                        console.log('Tags:', tags);
                    }
                }
            });

            // Loading state for submit button
            const submitBtn = document.getElementById('submitBtn');
            const form = document.getElementById('taskForm');

            form.addEventListener('submit', function() {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Creating...';
            });
        });

        // Reset form function
        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                document.getElementById('taskForm').reset();
                window.location.href = window.location.pathname;
            }
        }

        // Clear attachment function
        function clearAttachment() {
            document.getElementById('attachment').value = '';
            document.getElementById('attachmentPreview').classList.remove('active');
        }

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + Enter to submit
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('taskForm').submit();
            }
            
            // Esc to clear form
            if (e.key === 'Escape') {
                resetForm();
            }
        });

        // Unsaved changes warning
        let formChanged = false;
        document.querySelectorAll('#taskForm input, #taskForm select, #taskForm textarea').forEach(element => {
            element.addEventListener('change', () => formChanged = true);
        });

        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
    </script>

    <!-- Optional: Add Inter font for better typography -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</body>
</html>