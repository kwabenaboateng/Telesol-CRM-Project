<?php
session_start();
$username = $_SESSION['username'] ?? 'User';

$conn = new mysqli("localhost","root","","telesol crm");
if($conn->connect_error) die("DB Error: ".$conn->connect_error);

function esc($str){ return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

// Fetch team members
$members_result = $conn->query("SELECT * FROM team_members ORDER BY name ASC");
$members = $members_result->fetch_all(MYSQLI_ASSOC);

// Handle task creation
$errors=[]; $success=false;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_task'])){
    $title = trim($_POST['task_title']);
    $desc = trim($_POST['task_description']);
    $priority = $_POST['priority'];
    $start_date = $_POST['start_date'] ?: null;
    $due_date = $_POST['due_date'] ?: null;
    $assigned_users = $_POST['assigned_to'] ?? [];

    if($title==='' || empty($assigned_users)) $errors[] = "Task title and assigned users are required.";

    if(empty($errors)){
        $stmt = $conn->prepare("INSERT INTO tasks (task_title, task_description, priority, start_date, due_date) VALUES (?,?,?,?,?)");
        $stmt->bind_param("sssss",$title,$desc,$priority,$start_date,$due_date);
        if($stmt->execute()){
            $task_id = $stmt->insert_id;
            foreach($assigned_users as $uid){
                $stmt2 = $conn->prepare("INSERT INTO task_assignments (task_id,user_id) VALUES (?,?)");
                $stmt2->bind_param("ii",$task_id,$uid);
                $stmt2->execute();
                $stmt2->close();
            }
            $success = true;
        } else $errors[] = "Failed: ".$stmt->error;
        $stmt->close();
    }
}

$conn->close();
?>





<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administration Tasks - Telesol CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
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
        padding: 0.5rem 0.5rem;
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

    .sidebar{
        width:var(--sidebar-width);
        background-color: var(--primary);
        height:100vh;
        position:fixed;
        background: var(--primary);
        color:white;
        padding:1rem;
    }

    .sidebar a{
        color:white;
        display:block;
        padding:.8rem;
        text-decoration:none;
        border-radius:4px;
    }

    .sidebar a.active,
    .sidebar a:hover{
        background:#3498db;
    }

    .main-content{
        margin-left:220px;
        padding:20px;
    }

    /* Header Styles */
    .header {
        background: var(--primary);
        color: var(--white);
        padding: 10px 20px;
        margin-bottom: 5rem;
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








    .card{
        transition:.3s;
        margin-bottom:20px;
    }

    .card:hover{
        transform:translateY(-3px);
        box-shadow:0 5px 15px rgba(0,0,0,.1);
    }

    .badge-low{
        background:#6c757d;
    }

    .badge-medium{
        background:#3498db;
    }

    .badge-high{
        background:#f39c12;
    }

    .badge-critical{
        background:#e74c3c;
    }

    .badge-pending{
        background:#dc3545;
    }

    .badge-inprogress{
        background:#ffc107;
        color:#212529;
    }

    .badge-completed{
        background:#28a745;
    }

    .table-hover tbody tr:hover{
        background:#f1f1f1;
    }

    .alert{
        transition:.5s;
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
                <li><a href="dashboard.php"><i class="bi bi-speedometer2"></i> Menu</a></li>
                <li><a href="admin.php" class="active"><i class="bi bi-speedometer2"></i> Create Task</a></li>
                <li><a href="task_overview.php"><i class="bi bi-ticket-detailed"></i> Task Overview</a></li>
                <li><a href="internal_request.php"><i class="bi bi-wrench"></i> Internal Requisition</a></li>
                <li><a href="internal_request.php"><i class="bi bi-wrench"></i> Installations</a></li>
                <li><a href="admin_report.php"><i class="bi bi-people"></i> Report</a></li>
                <li><a href="#"><i class="bi bi-arrow-left-circle"></i> Back</a></li>
                <li><a href="login.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div>
                <!-- <button class="mobile-menu-btn"><i class="fas fa-bars"></i></button> -->
                <h2>Administration Department</h2>
            </div>

            <div class="user-session">
                <i class="bi bi-person-circle"></i>
                    <span>Logged in as: <strong><?= esc($username) ?></strong></span>
            </div>
        </header>

        <div class="content">

            <?php if(!empty($errors)): ?>
                <div class="alert alert-danger"><?php foreach($errors as $e) echo "<div>".esc($e)."</div>"; ?></div>
                <?php elseif($success): ?>
                <div class="alert alert-success">Task assigned successfully!</div>
            <?php endif; ?>

            <div class="card p-4">
                <h4>Assign New Task</h4>
                <form method="post" class="row g-3">
            
                    <div class="col-md-3">
                        <select name="taske_type" class="form-select" placeholder="Task Type" required>
                            <option value="">Select Task Type</option>
                            <?php foreach($members as $m) echo "<option>".esc($m)."</option>"; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <input type="text" class="form-control" name="task_title" placeholder="Task Title" required>
                    </div>

                    <div class="col-md-4">
                        <input type="text" class="form-control" name="task_title" placeholder="Task Title" required>
                    </div>

                    <div class="col-md-4">
                        <select name="assigned_to" class="form-select" required>
                            <option value="">Select Team Member</option>
                            <?php foreach($members as $m) echo "<option>".esc($m)."</option>"; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <select name="priority" class="form-select">
                            <option>Low</option>
                            <option selected>Medium</option>
                            <option>High</option>
                            <option>Critical</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <input type="date" class="form-control" name="due_date">
                    </div>

                    <div class="col-12">
                        <textarea class="form-control" name="task_description" placeholder="Task Description"></textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" name="create_task" class="btn btn-primary"><i class="bi bi-send-fill"></i> Assign Task</button>
                    </div>
                </form>
            </div>


















            <div class="container mt-4">
                <h2>Create New Task</h2>

                <?php if(!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach($errors as $e) echo "<div>".esc($e)."</div>"; ?>
                </div>
                <?php elseif($success): ?>
                <div class="alert alert-success">Task created successfully!</div>
                <?php endif; ?>

                <form method="post" class="row g-3">
                    <div class="col-md-6"><input type="text" name="task_title" class="form-control" placeholder="Task Title" required></div>
                        <div class="col-md-6">
                            <select name="assigned_to[]" class="form-select" multiple required>
                                <?php foreach($members as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= esc($m['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
    
                            <small class="text-muted">Hold Ctrl (Cmd) to select multiple</small>
                        </div>
    
                        <div class="col-md-3">
                            <select name="priority" class="form-select">
                                <option>Low</option>
                                <option selected>Medium</option>
                                <option>High</option>
                                <option>Critical</option>
                            </select></div>
            <div class="col-md-3"><input type="date" name="start_date" class="form-control"></div>
    <div class="col-md-3"><input type="date" name="due_date" class="form-control"></div>
    <div class="col-12"><textarea name="task_description" class="form-control" placeholder="Task Description"></textarea></div>
    <div class="col-12"><button name="create_task" class="btn btn-primary">Create Task</button></div>
    </form>
    </div>
        </div>
    </div>


<script>
const taskTableRows=document.querySelectorAll('#taskTable tbody tr');
document.getElementById('taskSearch').addEventListener('keyup',function(){
let f=this.value.toLowerCase();
taskTableRows.forEach(r=>r.style.display=r.textContent.toLowerCase().includes(f)?'':'none');});
document.getElementById('priorityFilter').addEventListener('change',function(){
let v=this.value; taskTableRows.forEach(r=>r.style.display=(v===''||r.children[4].textContent===v)?'':'none');});
document.getElementById('statusFilter').addEventListener('change',function(){
let v=this.value; taskTableRows.forEach(r=>r.style.display=(v===''||r.querySelector('.statusSelect').value===v)?'':'none');});

// Inline status update (AJAX)
document.querySelectorAll('.statusSelect').forEach(sel=>{
sel.addEventListener('change',function(){
let tr=this.closest('tr');
let id=tr.dataset.id;
let val=this.value;
fetch('',{
method:'POST',
headers:{'Content-Type':'application/x-www-form-urlencoded'},
body:'update_status=1&task_id='+id+'&status='+encodeURIComponent(val)
});
});
});

// Fade out alerts
setTimeout(()=>{document.querySelectorAll('.alert').forEach(a=>a.style.display='none');},5000);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


































<!-- 
<?php
session_start();
$username = $_SESSION['username'] ?? 'User';

$conn = new mysqli("localhost","root","","telesol crm");
if($conn->connect_error) die("DB Error: ".$conn->connect_error);

function esc($str){ return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

// Fetch team members
$members_result = $conn->query("SELECT * FROM team_members ORDER BY name ASC");
$members = $members_result->fetch_all(MYSQLI_ASSOC);

// Handle task creation
$errors=[]; $success=false;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_task'])){
    $title = trim($_POST['task_title']);
    $desc = trim($_POST['task_description']);
    $priority = $_POST['priority'];
    $start_date = $_POST['start_date'] ?: null;
    $due_date = $_POST['due_date'] ?: null;
    $assigned_users = $_POST['assigned_to'] ?? [];

    if($title==='' || empty($assigned_users)) $errors[] = "Task title and assigned users are required.";

    if(empty($errors)){
        $stmt = $conn->prepare("INSERT INTO tasks (task_title, task_description, priority, start_date, due_date) VALUES (?,?,?,?,?)");
        $stmt->bind_param("sssss",$title,$desc,$priority,$start_date,$due_date);
        if($stmt->execute()){
            $task_id = $stmt->insert_id;
            foreach($assigned_users as $uid){
                $stmt2 = $conn->prepare("INSERT INTO task_assignments (task_id,user_id) VALUES (?,?)");
                $stmt2->bind_param("ii",$task_id,$uid);
                $stmt2->execute();
                $stmt2->close();
            }
            $success = true;
        } else $errors[] = "Failed: ".$stmt->error;
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Create Task - Telesol CRM</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<style>
body{background:#f5f7fa;font-family:'Segoe UI',sans-serif;}
.sidebar{width:220px;height:100vh;position:fixed;background:#2c3e50;color:white;padding:1rem;}
.sidebar a{color:white;display:block;padding:.8rem;text-decoration:none;border-radius:4px;}
.sidebar a.active,.sidebar a:hover{background:#3498db;}
.main-content{margin-left:220px;padding:20px;}
.card{transition:.3s;margin-bottom:20px;}
.card:hover{transform:translateY(-3px);box-shadow:0 5px 15px rgba(0,0,0,.1);}
.badge-low{background:#6c757d;}
.badge-medium{background:#3498db;}
.badge-high{background:#f39c12;}
.badge-critical{background:#e74c3c;}
.badge-pending{background:#dc3545;}
.badge-inprogress{background:#ffc107;color:#212529;}
.badge-completed{background:#28a745;}
.table-hover tbody tr:hover{background:#f1f1f1;}
.alert{transition:.5s;}
</style>
<body class="bg-light">
<div class="container mt-4">
<h2>Create New Task</h2>

<?php if(!empty($errors)): ?>
<div class="alert alert-danger"><?php foreach($errors as $e) echo "<div>".esc($e)."</div>"; ?></div>
<?php elseif($success): ?>
<div class="alert alert-success">Task created successfully!</div>
<?php endif; ?>

<form method="post" class="row g-3">
    <div class="col-md-6"><input type="text" name="task_title" class="form-control" placeholder="Task Title" required></div>
        <div class="col-md-6">
            <select name="assigned_to[]" class="form-select" multiple required>
                <?php foreach($members as $m): ?>
                    <option value="<?= $m['id'] ?>"><?= esc($m['name']) ?></option>
                <?php endforeach; ?>
            </select>
    
            <small class="text-muted">Hold Ctrl (Cmd) to select multiple</small>
        </div>
    
        <div class="col-md-3"><select name="priority" class="form-select">
            <option>Low</option>
            <option selected>Medium</option>
            <option>High</option>
            <option>Critical</option>
            </select></div>
            <div class="col-md-3"><input type="date" name="start_date" class="form-control"></div>
    <div class="col-md-3"><input type="date" name="due_date" class="form-control"></div>
    <div class="col-12"><textarea name="task_description" class="form-control" placeholder="Task Description"></textarea></div>
    <div class="col-12"><button name="create_task" class="btn btn-primary">Create Task</button></div>
    </form>
    </div>
</body>
</html> -->
