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

// Technology department engineers
$technology_engineers = ['John Hagan', 'Isaac Ofosu-Afful', 'Sylvester Horsu', 'Innocent Odikro', 'Joshua Avinu'];

// Function to fetch data from database
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

// Fetch all tickets and installations assigned to technology department engineers
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

// Count tasks by status
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

// Group tasks by engineer
$engineerTasks = [];
foreach ($technology_engineers as $engineer) {
    $engineerTasks[$engineer] = [
        'active' => 0,
        'completed' => 0,
        'overdue' => 0,
        'tasks' => []
    ];
}

foreach ($allTasks as $task) {
    $engineer = $task['assigned_persons'];
    if (in_array($engineer, $technology_engineers)) {
        $engineerTasks[$engineer]['tasks'][] = $task;
        
        if ($task['issue_status'] != 'Resolved') {
            $engineerTasks[$engineer]['active']++;
        } else {
            $engineerTasks[$engineer]['completed']++;
        }
        
        if ($task['resolved_by_deadline'] && strtotime($task['resolved_by_deadline']) < time() && $task['issue_status'] != 'Resolved') {
            $engineerTasks[$engineer]['overdue']++;
        }
    }
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM - Technology Department Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            padding: 15px;
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
    </style>
</head>
<body>
    <!-- Fixed Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Telesol CRM</h2>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php" ><i class="fas fa-tachometer-alt"></i> Menu</a></li>
                <li><a href="tech_dashboard.php" class="active" data-page="overview"><i class="fas fa-tachometer-alt"></i> Tasks Overview</a></li>
                <li><a href="alltaskpage.php" data-page="issues"><i class="fas fa-tasks"></i> All Tasks</a></li>
                <li><a href="#" data-page="engineers"><i class="fas fa-users"></i> Engineer Assignments</a></li>
                <li><a href="#"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div>
                <button class="mobile-menu-btn">
                    <i class="fas fa-bars"></i>
                </button>
                <!-- <h2>Tasks Overview</h2> -->
            </div>
            <div class="user-info">
                <!-- <span id="db-status-indicator" class="db-status db-connected">DB Connected</span> -->
                <img src="https://ui-avatars.com/api/?name=Admin+User&background=3498db&color=fff" alt="User">
                <span>Admin User</span>
            </div>
        </div>
        
        <!-- Content Area -->
        <div class="content">
            <!-- Dashboard Page -->
            <div class="page active" id="dashboard">
                <h2>Tasks Overview</h2>
                <p class="card-text">Summary of all tasks assigned to technology department engineers</p>
                
                <div class="card-grid">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-exclamation-circle"></i>
                                <span>Open Issues</span>
                            </div>
                            <div class="card-icon bg-primary">
                                <i class="fas fa-tasks"></i>
                            </div>
                        </div>
                        <div id="open-issues-count" class="card-value"><?php echo $statusCounts['open_issues']; ?></div>
                        <div class="card-text">Unresolved issues assigned to technology team</div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-server"></i>
                                <span>Pending Installations</span>
                            </div>
                            <div class="card-icon bg-warning">
                                <i class="fas fa-cogs"></i>
                            </div>
                        </div>
                        <div id="pending-installations-count" class="card-value"><?php echo $statusCounts['pending_installations']; ?></div>
                        <div class="card-text">Installation tasks not yet completed</div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-check-circle"></i>
                                <span>Completed Today</span>
                            </div>
                            <div class="card-icon bg-success">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                        </div>
                        <div id="completed-today-count" class="card-value"><?php echo $statusCounts['completed_today']; ?></div>
                        <div class="card-text">Tasks resolved today</div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="fas fa-clock"></i>
                                <span>Overdue Tasks</span>
                            </div>
                            <div class="card-icon bg-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                        <div id="overdue-tasks-count" class="card-value"><?php echo $statusCounts['overdue_tasks']; ?></div>
                        <div class="card-text">Tasks past their deadline</div>
                    </div>
                </div>
                
                <h2 class="section-title">Recent Technology Department Tasks</h2>
                <div class="table-container">
                    <table id="recent-activities-table">
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
                        <tbody id="recent-activities-body">
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
            
            <!-- All Tasks Page -->
            <div class="page" id="issues">
                <h2>All Technology Department Tasks</h2>
                <p class="card-text">Complete list of all tasks assigned to technology department engineers</p>
                
                <div class="filter-section">
                    <div class="filter-group">
                        <label for="type-filter">Type</label>
                        <select id="type-filter">
                            <option value="all">All Types</option>
                            <option value="Issue">Issues</option>
                            <option value="Installation">Installations</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status-filter">Status</label>
                        <select id="status-filter">
                            <option value="all">All Statuses</option>
                            <option value="New">New</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Resolved">Resolved</option>
                            <option value="Pending">Pending</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="engineer-filter">Engineer</label>
                        <select id="engineer-filter">
                            <option value="all">All Engineers</option>
                            <?php foreach ($technology_engineers as $engineer): ?>
                            <option value="<?php echo str_replace(' ', '-', strtolower($engineer)); ?>"><?php echo $engineer; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" placeholder="Search tasks...">
                    </div>
                </div>
                
                <div class="table-container">
                    <table id="all-tasks-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Customer</th>
                                <th>Location</th>
                                <th>Assigned To</th>
                                <th>Status</th>
                                <th>Due Date</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody id="all-tasks-body">
                            <?php foreach ($allTasks as $task): 
                                $statusClass = 'status-new';
                                if ($task['issue_status'] == 'Resolved') $statusClass = 'status-resolved';
                                elseif ($task['issue_status'] == 'In Progress') $statusClass = 'status-in-progress';
                                elseif ($task['issue_status'] == 'Pending') $statusClass = 'status-pending';
                                
                                $dueDate = $task['resolved_by_deadline'] ? date('M j, Y', strtotime($task['resolved_by_deadline'])) : 'Not set';
                                $createdDate = date('M j, Y', strtotime($task['created_at']));
                            ?>
                            <tr>
                                <td>#<?php echo $task['id']; ?></td>
                                <td><?php echo $task['issue_type']; ?></td>
                                <td><?php echo $task['customer_name']; ?></td>
                                <td><?php echo $task['location']; ?></td>
                                <td><?php echo $task['assigned_persons']; ?></td>
                                <td><span class="status <?php echo $statusClass; ?>"><?php echo $task['issue_status']; ?></span></td>
                                <td><?php echo $dueDate; ?></td>
                                <td><?php echo $createdDate; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Engineer Assignments Page -->
            <div class="page" id="engineers">
                <h2>Technology Engineer Assignments</h2>
                <p class="card-text">Tasks assigned to individual technology engineers</p>
                
                <div id="engineer-grid" class="engineer-grid">
                    <?php foreach ($engineerTasks as $engineerName => $engineerData): 
                        if (count($engineerData['tasks']) > 0): 
                            $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($engineerName) . "&background=3498db&color=fff";
                    ?>
                    <div class="engineer-card">
                        <div class="engineer-header">
                            <img src="<?php echo $avatarUrl; ?>" alt="<?php echo $engineerName; ?>" class="engineer-avatar">
                            <div class="engineer-info">
                                <h3><?php echo $engineerName; ?></h3>
                                <p>Technology Engineer</p>
                            </div>
                        </div>
                        <div class="engineer-stats">
                            <div class="stat">
                                <div class="stat-value"><?php echo $engineerData['active']; ?></div>
                                <div class="stat-label">Active</div>
                            </div>
                            <div class="stat">
                                <div class="stat-value"><?php echo $engineerData['completed']; ?></div>
                                <div class="stat-label">Completed</div>
                            </div>
                            <div class="stat">
                                <div class="stat-value"><?php echo $engineerData['overdue']; ?></div>
                                <div class="stat-label">Overdue</div>
                            </div>
                        </div>
                        <div class="engineer-tasks">
                            <?php foreach (array_slice($engineerData['tasks'], 0, 5) as $task): 
                                $statusClass = 'status-new';
                                if ($task['issue_status'] == 'Resolved') $statusClass = 'status-resolved';
                                elseif ($task['issue_status'] == 'In Progress') $statusClass = 'status-in-progress';
                                elseif ($task['issue_status'] == 'Pending') $statusClass = 'status-pending';
                            ?>
                            <div class="task-item">
                                <div class="task-title">
                                    <span><?php echo $task['customer_name']; ?></span>
                                    <span class="task-type"><?php echo $task['issue_type']; ?></span>
                                </div>
                                <div class="task-details">
                                    <span>#<?php echo $task['id']; ?></span>
                                    <span class="status <?php echo $statusClass; ?>"><?php echo $task['issue_status']; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Page navigation
            const pageLinks = document.querySelectorAll('.sidebar-menu a[data-page]');
            const pages = document.querySelectorAll('.page');
            
            pageLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const targetPage = this.getAttribute('data-page');
                    
                    // Hide all pages
                    pages.forEach(page => {
                        page.classList.remove('active');
                    });
                    
                    // Show target page
                    document.getElementById(targetPage).classList.add('active');
                    
                    // Update active link
                    pageLinks.forEach(link => link.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Close sidebar on mobile after selection
                    if (window.innerWidth <= 992) {
                        document.querySelector('.sidebar').classList.remove('active');
                    }
                });
            });
            
            // Mobile menu toggle
            const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
            const sidebar = document.querySelector('.sidebar');
            
            mobileMenuBtn.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth <= 992 && 
                    !sidebar.contains(event.target) && 
                    !mobileMenuBtn.contains(event.target) &&
                    sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            });
            
            // Filter functionality
            const typeFilter = document.getElementById('type-filter');
            const statusFilter = document.getElementById('status-filter');
            const engineerFilter = document.getElementById('engineer-filter');
            const searchInput = document.getElementById('search');
            
            [typeFilter, statusFilter, engineerFilter, searchInput].forEach(filter => {
                filter.addEventListener('change', applyFilters);
                if (filter !== typeFilter && filter !== statusFilter && filter !== engineerFilter) {
                    filter.addEventListener('keyup', applyFilters);
                }
            });
            
            function applyFilters() {
                const typeValue = typeFilter.value;
                const statusValue = statusFilter.value;
                const engineerValue = engineerFilter.value;
                const searchValue = searchInput.value.toLowerCase();
                
                const rows = document.querySelectorAll('#all-tasks-body tr');
                
                rows.forEach(row => {
                    const type = row.cells[1].textContent;
                    const status = row.cells[5].textContent;
                    const engineer = row.cells[4].textContent.toLowerCase().replace(/\s+/g, '-');
                    const customer = row.cells[2].textContent.toLowerCase();
                    const location = row.cells[3].textContent.toLowerCase();
                    
                    const typeMatch = typeValue === 'all' || type === typeValue;
                    const statusMatch = statusValue === 'all' || status === statusValue;
                    const engineerMatch = engineerValue === 'all' || engineer === engineerValue;
                    const searchMatch = customer.includes(searchValue) || location.includes(searchValue);
                    
                    if (typeMatch && statusMatch && engineerMatch && searchMatch) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>