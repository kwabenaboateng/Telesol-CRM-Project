<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "telesol crm";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$technology_engineers = ['John Hagan', 'Isaac Ofosu-Afful', 'Sylvester Horsu', 'Innocent Odikro', 'Joshua Avinu'];

function fetchData($conn, $query) {
    $result = $conn->query($query);
    $data = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

$engineersList = "'" . implode("','", $technology_engineers) . "'";
$tasksQuery = "SELECT
    id,
    customer_name,
    issue_type,
    location,
    assigned_persons,
    issue_status,
    scheduled_datetime,
    created_at,
    resolved_by_deadline,
    comments
    FROM tickets 
    WHERE assigned_persons IN ($engineersList)
    ORDER BY created_at DESC";

$allTasks = fetchData($conn, $tasksQuery);

$statusCounts = [
    'open_issues' => 0,
    'pending_installations' => 0,
    'completed_today' => 0,
    'overdue_tasks' => 0
];

foreach ($allTasks as $task) {
    if ($task['issue_status'] != 'Resolved') {
        $statusCounts['open_issues']++;
    }
    
    if ($task['issue_type'] == 'Installation' && $task['issue_status'] != 'Resolved') {
        $statusCounts['pending_installations']++;
    }
    
    if ($task['issue_status'] == 'Resolved' && date('Y-m-d', strtotime($task['created_at'])) == date('Y-m-d')) {
        $statusCounts['completed_today']++;
    }
    
    if ($task['resolved_by_deadline'] && strtotime($task['resolved_by_deadline']) < time() && $task['issue_status'] != 'Resolved') {
        $statusCounts['overdue_tasks']++;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard - Telesol CRM</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>

        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --gray: #95a5a6;
            --sidebar-width: 250px;
            --background: #f5f7fa;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            display: flex;
            min-height: 100vh;
        }
        
        /* Fixed Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--primary);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .sidebar-header {
            padding: 20px;
            background: var(--dark);
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 101;
        }
        
        .sidebar-menu {
            padding: 10px 0;
        }
        
        .sidebar-menu ul {
            list-style: none;
        }
        
        .sidebar-menu li {
            margin: 5px 0;
        }
        
        .sidebar-menu a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 12px 20px;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: var(--secondary);
            border-left: 4px solid white;
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
            background: white;
            padding: 15px 30px;
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
            display: flex;
            align-items: center;
            gap: 8px;
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
            margin-bottom: 30px;
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
            position: sticky;
            top: 0;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
            text-align: center;
            min-width: 90px;
        }
        
        .status-new {
            background: #e3f2fd;
            color: var(--secondary);
        }
        
        .status-in-progress {
            background: #fff8e1;
            color: var(--warning);
        }
        
        .status-resolved {
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
            transition: transform 0.3s ease;
        }
        
        .engineer-card:hover {
            transform: translateY(-5px);
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
            max-height: 300px;
            overflow-y: auto;
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
            display: flex;
            justify-content: space-between;
        }
        
        .task-type {
            font-size: 12px;
            color: var(--gray);
            background: #f1f1f1;
            padding: 2px 8px;
            border-radius: 10px;
        }
        
        .task-details {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--gray);
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
        .db-status {
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
        }
        
        .section-title {
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--light);
            color: var(--dark);
        }
        
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
    
        /* <?php include 'styles.css'; /* Extracted CSS below can be saved in styles.css */ ?> */
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h2>Telesol CRM</h2></div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php" data-page="dashboard"><i class="fas fa-tachometer-alt"></i> Main Menu</a></li>
                <li><a href="dashboard_overview.php" class="active" data-page="Overview"><i class="fas fa-tachometer-alt"></i> Overview</a></li>
                <li><a href="all_tasks.php" data-page="issues"><i class="fas fa-tasks"></i> All Tasks</a></li>
                <li><a href="engineers.php" data-page="engineers"><i class="fas fa-users"></i> Engineer Assignments</a></li>
                <li><a href="#"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </div>
    <div class="main-content">
        <div class="header">
            <div><h2>Dashboard</h2></div>
            <div class="user-info">
                <span class="db-status db-connected">DB Connected</span>
                <img src="https://ui-avatars.com/api/?name=Admin+User&background=3498db&color=fff" alt="User" />
                <span>Admin User</span>
            </div>
        </div>
        <div class="content">
            <h2>Tasks Overview</h2>
            <p class="card-text">Summary of all tasks assigned to technology department engineers</p>
            <div class="card-grid">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-exclamation-circle"></i> Open Issues</div>
                        <div class="card-icon bg-primary"><i class="fas fa-tasks"></i></div>
                    </div>
                    <div class="card-value"><?php echo $statusCounts['open_issues']; ?></div>
                    <div class="card-text">Unresolved issues assigned to technology team</div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-server"></i> Pending Installations</div>
                        <div class="card-icon bg-warning"><i class="fas fa-cogs"></i></div>
                    </div>
                    <div class="card-value"><?php echo $statusCounts['pending_installations']; ?></div>
                    <div class="card-text">Installation tasks not yet completed</div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-check-circle"></i> Completed Today</div>
                        <div class="card-icon bg-success"><i class="fas fa-calendar-check"></i></div>
                    </div>
                    <div class="card-value"><?php echo $statusCounts['completed_today']; ?></div>
                    <div class="card-text">Tasks resolved today</div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fas fa-clock"></i> Overdue Tasks</div>
                        <div class="card-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></div>
                    </div>
                    <div class="card-value"><?php echo $statusCounts['overdue_tasks']; ?></div>
                    <div class="card-text">Tasks past their deadline</div>
                </div>
            </div>

            <h2 class="section-title">Recent Technology Department Tasks</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Customer</th>
                            <th>Location</th>
                            <th>Assigned To</th>
                            <th>Status</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $recentTasks = array_slice($allTasks, 0, 10);
                        foreach ($recentTasks as $task):
                            $statusClass = 'status-new';
                            if ($task['issue_status'] == 'Resolved') $statusClass = 'status-resolved';
                            elseif ($task['issue_status'] == 'In Progress') $statusClass = 'status-in-progress';
                            elseif ($task['issue_status'] == 'Pending') $statusClass = 'status-pending';
                            $dueDate = $task['resolved_by_deadline'] ? date('M j, Y', strtotime($task['resolved_by_deadline'])) : 'Not set';
                        ?>
                        <tr>
                            <td>#<?php echo $task['id']; ?></td>
                            <td><?php echo $task['issue_type']; ?></td>
                            <td><?php echo $task['customer_name']; ?></td>
                            <td><?php echo $task['location']; ?></td>
                            <td><?php echo $task['assigned_persons']; ?></td>
                            <td><span class="status <?php echo $statusClass; ?>"><?php echo $task['issue_status']; ?></span></td>
                            <td><?php echo $dueDate; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</body>
</html>
