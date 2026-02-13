<?php
session_start();
$username = $_SESSION['username'] ?? 'User';

$servername = "localhost";
$db_username = "root";
$password_db = "";
$dbname = "telesol crm";

$conn = new mysqli($servername, $db_username, $password_db, $dbname);
if ($conn->connect_error) die("DB Error: ".htmlspecialchars($conn->connect_error));

function esc($str){ return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

// Create tables if not exists
$conn->query("
CREATE TABLE IF NOT EXISTS internal_request (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department ENUM('Administration','Customer Service','Operations','Sales','Systems and IT','Technology','Transport') DEFAULT 'Administration',
    requested_by VARCHAR(100) NOT NULL,
    priority ENUM('Normal','High','Critical') DEFAULT 'Normal',
    status ENUM('Pending','Approved','Rejected') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("
CREATE TABLE IF NOT EXISTS internal_request_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    quantity INT NOT NULL,
    FOREIGN KEY (request_id) REFERENCES internal_request(id) ON DELETE CASCADE
)");
$errors=[]; $success=false;

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_request'])){
    $department = $_POST['department'] ?? 'Administration';
    $priority = $_POST['priority'] ?? 'Normal';
    $items = $_POST['items'] ?? [];

    if(empty($items)) $errors[]="At least one item is required.";
    else {
        $all_valid = true;
        foreach($items as $i){
            if(trim($i['item_name'])==='' || intval($i['quantity'])<=0){
                $all_valid=false; break;
            }
        }
        if(!$all_valid) $errors[]="All items must have a name and quantity > 0.";
    }

    if(empty($errors)){
        $stmt = $conn->prepare("INSERT INTO internal_request (department, requested_by, priority) VALUES (?,?,?)");
        if($stmt){
            $stmt->bind_param("sss",$department,$username,$priority);
            if($stmt->execute()){
                $request_id = $stmt->insert_id;
                foreach($items as $i){
                    $stmt2 = $conn->prepare("INSERT INTO internal_request_items (request_id, item_name, quantity) VALUES (?,?,?)");
                    $stmt2->bind_param("ssi",$request_id, $i['item_name'], $i['quantity']);
                    $stmt2->execute();
                    $stmt2->close();
                }
                $success=true;
            } else $errors[]="Failed to create request: ".htmlspecialchars($stmt->error);
            $stmt->close();
        } else $errors[]="DB error: ".htmlspecialchars($conn->error);
    }
}

// Fetch requests
$req_result = $conn->query("
SELECT r.id,r.department,r.requested_by,r.priority,r.status,r.created_at,
       GROUP_CONCAT(CONCAT(i.item_name,' (',i.quantity,')') SEPARATOR ', ') as items
FROM internal_request r
LEFT JOIN internal_request_items i ON r.id=i.request_id
GROUP BY r.id
ORDER BY r.id DESC
");

$conn->close();
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Administration Tasks - Telesol CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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










/* body { background:#f5f7fa; }
.sidebar { width:220px; height:100vh; background:#2c3e50; position:fixed; color:white; }
.sidebar a { color:white; display:block; padding:0.8rem; text-decoration:none; }
.sidebar a.active, .sidebar a:hover { background:#3498db; }
.main-content { margin-left:220px; padding:20px; } */
.status-Pending { color:#e74c3c; font-weight:bold; }
.status-InProgress { color:#f39c12; font-weight:bold; }
.status-Completed { color:#00b44b; font-weight:bold; }
.card { transition:0.3s; }
.card:hover { transform:translateY(-3px); box-shadow:0 5px 15px rgba(0,0,0,0.1);}
</style>
</head>
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
                <li><a href="task_overview.php"><i class="bi bi-ticket-detailed"></i> Task Overview</a></li>
                <li><a href="internal_request.php" class="active"><i class="bi bi-wrench"></i> Internal Requisitions</a></li>
                <li><a href="customer_experience_dashboard.php"><i class="bi bi-people"></i> Customer Experience</a></li>
                <li><a href="report.php"><i class="bi bi-bar-chart"></i> Reports</a></li>
                <li><a href="#"><i class="bi bi-gear"></i> Settings</a></li>
                <li><a href="#"><i class="bi bi-arrow-left-circle"></i> Back</a></li>
                <li><a href="login.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>


<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Administration Department</h2>
        <span>Logged in as <strong><?= esc($username) ?></strong></span>
    </div>


<div class="card mb-4 p-4">
    <h4>Request Materials</h4>
    <form method="post" id="requisitionForm">
        <div class="row g-3 mb-2">
            <div class="col-md-4">
                <label>Department</label>
                <select class="form-select" name="department">
                    <option>Administration</option>
                    <option>Customer Service</option>
                    <option>Operations</option>
                    <option>Sales</option>
                    <option>Systems and IT</option>
                    <option>Technology</option>
                    <option>Transport</option>
                </select>
            </div>
            <div class="col-md-4">
                <label>Priority</label>
                <select class="form-select" name="priority">
                    <option selected>Normal</option>
                    <option>High</option>
                    <option>Critical</option>
                </select>
            </div>
        </div>

        <div id="itemsContainer">
            <div class="row g-3 mb-2 itemRow">
                <div class="col-md-6"><input type="text" name="items[0][item_name]" class="form-control" placeholder="Item Name" required></div>
                <div class="col-md-3"><input type="number" name="items[0][quantity]" class="form-control" placeholder="Quantity" min="1" required></div>
                <div class="col-md-3"><button type="button" class="btn btn-danger removeItem">Remove</button></div>
            </div>
        </div>

        <button type="button" class="btn btn-secondary mb-3" id="addItemBtn">Add Another Item</button>
        <div><button type="submit" name="create_request" class="btn btn-primary">Submit Request</button></div>
    </form>
</div>

<script>
let itemIndex = 1;
document.getElementById('addItemBtn').addEventListener('click', function(){
    const container = document.getElementById('itemsContainer');
    const div = document.createElement('div');
    div.classList.add('row','g-3','mb-2','itemRow');
    div.innerHTML = `
        <div class="col-md-6"><input type="text" name="items[${itemIndex}][item_name]" class="form-control" placeholder="Item Name" required></div>
        <div class="col-md-3"><input type="number" name="items[${itemIndex}][quantity]" class="form-control" placeholder="Quantity" min="1" required></div>
        <div class="col-md-3"><button type="button" class="btn btn-danger removeItem">Remove</button></div>
    `;
    container.appendChild(div);
    itemIndex++;
});

// Remove item row
document.addEventListener('click', function(e){
    if(e.target.classList.contains('removeItem')){
        e.target.closest('.itemRow').remove();
    }
});
</script>
