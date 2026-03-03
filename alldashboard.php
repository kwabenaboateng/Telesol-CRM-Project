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
            --sidebar-width: 240px;
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            color: var(--primary);
            background-color: var(--background);
            color: #333;
            display: flex;
            min-height: 100vh;
        }

        /* Header Styles */
        .header {
            color: var(--white);
            background-color: var(---primary);
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
            color: var(--primary);
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
