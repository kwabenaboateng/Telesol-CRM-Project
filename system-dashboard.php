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
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: var(--primary);
            color: white;
            transition: all 0.3s ease;
        }
        
        .sidebar-header {
            padding: 20px;
            background: var(--dark);
            text-align: center;
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
            display: flex;
            flex-direction: column;
        }
        
        /* Header Styles */
        .header {
            background: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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
        
        @media (max-width: 992px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
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
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Tech CRM</h2>
            </div>
            <div class="sidebar-menu">
                <ul>
                    <li><a href="#" class="active" data-page="dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="#" data-page="issues"><i class="fas fa-tasks"></i> All Issues & Installations</a></li>
                    <li><a href="#" data-page="engineers"><i class="fas fa-users"></i> Engineer Assignments</a></li>
                    <li><a href="#"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="#"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h2>Technology Department Dashboard</h2>
                <div class="user-info">
                    <img src="https://ui-avatars.com/api/?name=Admin+User&background=3498db&color=fff" alt="User">
                    <span>Admin User</span>
                </div>
            </div>
            
            <!-- Content Area -->
            <div class="content">
                <!-- Dashboard Page -->
                <div class="page active" id="dashboard">
                    <h2>Department Overview</h2>
                    <p class="card-text">Summary of all technology department activities</p>
                    
                    <div class="card-grid">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Open Issues</div>
                                <div class="card-icon bg-primary">
                                    <i class="fas fa-exclamation-circle"></i>
                                </div>
                            </div>
                            <div class="card-value">24</div>
                            <div class="card-text">5 more than yesterday</div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Pending Installations</div>
                                <div class="card-icon bg-warning">
                                    <i class="fas fa-server"></i>
                                </div>
                            </div>
                            <div class="card-value">18</div>
                            <div class="card-text">3 scheduled for today</div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Completed Today</div>
                                <div class="card-icon bg-success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                            <div class="card-value">12</div>
                            <div class="card-text">8 issues, 4 installations</div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">Overdue Tasks</div>
                                <div class="card-icon bg-danger">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                            <div class="card-value">7</div>
                            <div class="card-text">Needs immediate attention</div>
                        </div>
                    </div>
                    
                    <h2>Recent Activities</h2>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Assigned To</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>#TECH-1042</td>
                                    <td>Installation</td>
                                    <td>New server setup for Finance Dept</td>
                                    <td>Sarah Johnson</td>
                                    <td><span class="status status-in-progress">In Progress</span></td>
                                    <td>Today</td>
                                    <td><button class="btn btn-primary btn-sm">View</button></td>
                                </tr>
                                <tr>
                                    <td>#TECH-1038</td>
                                    <td>Issue</td>
                                    <td>CRM software crashing on login</td>
                                    <td>Michael Chen</td>
                                    <td><span class="status status-new">New</span></td>
                                    <td>Tomorrow</td>
                                    <td><button class="btn btn-primary btn-sm">View</button></td>
                                </tr>
                                <tr>
                                    <td>#TECH-1035</td>
                                    <td>Installation</td>
                                    <td>WiFi access points - 3rd Floor</td>
                                    <td>James Wilson</td>
                                    <td><span class="status status-completed">Completed</span></td>
                                    <td>Yesterday</td>
                                    <td><button class="btn btn-primary btn-sm">View</button></td>
                                </tr>
                                <tr>
                                    <td>#TECH-1033</td>
                                    <td>Issue</td>
                                    <td>Printer not working in Marketing</td>
                                    <td>Emma Thompson</td>
                                    <td><span class="status status-pending">Pending</span></td>
                                    <td>2 days ago</td>
                                    <td><button class="btn btn-primary btn-sm">View</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- All Issues & Installations Page -->
                <div class="page" id="issues">
                    <h2>All Issues & Installations</h2>
                    <p class="card-text">Complete list of all tasks assigned to the technology department</p>
                    
                    <div class="filter-section">
                        <div class="filter-group">
                            <label for="type-filter">Type</label>
                            <select id="type-filter">
                                <option value="all">All Types</option>
                                <option value="issue">Issues</option>
                                <option value="installation">Installations</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="status-filter">Status</label>
                            <select id="status-filter">
                                <option value="all">All Statuses</option>
                                <option value="new">New</option>
                                <option value="in-progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="engineer-filter">Engineer</label>
                            <select id="engineer-filter">
                                <option value="all">All Engineers</option>
                                <option value="sarah">Sarah Johnson</option>
                                <option value="michael">Michael Chen</option>
                                <option value="james">James Wilson</option>
                                <option value="emma">Emma Thompson</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="search">Search</label>
                            <input type="text" id="search" placeholder="Search tasks...">
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Client/Department</th>
                                    <th>Assigned To</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>#TECH-1042</td>
                                    <td>Installation</td>
                                    <td>New server setup for Finance Dept</td>
                                    <td>Finance Department</td>
                                    <td>Sarah Johnson</td>
                                    <td>High</td>
                                    <td><span class="status status-in-progress">In Progress</span></td>
                                    <td>Today</td>
                                    <td><button class="btn btn-primary btn-sm">View</button></td>
                                </tr>
                                <tr>
                                    <td>#TECH-1041</td>
                                    <td>Installation</td>
                                    <td>CRM mobile app deployment</td>
                                    <td>Sales Team</td>
                                    <td>Michael Chen</td>
                                    <td>Medium</td>
                                    <td><span class="status status-new">New</span></td>
                                    <td>Next Week</td>
                                    <td><button class="btn btn-primary btn-sm">View</button></td>
                                </tr>
                                <tr>
                                    <td>#TECH-1040</td>
                                    <td>Issue</td>
                                    <td>Email synchronization problem</td>
                                    <td>Customer Support</td>
                                    <td>Emma Thompson</td>
                                    <td>High</td>
                                    <td><span class="status status-in-progress">In Progress</span></td>
                                    <td>Tomorrow</td>
                                    <td><button class="btn btn-primary btn-sm">View</button></td>
                                </tr>
                                <tr>
                                    <td>#TECH-1039</td>
                                    <td>Issue</td>
                                    <td>Database backup failure alert</td>
                                    <td>IT Infrastructure</td>
                                    <td>James Wilson</td>
                                    <td>Critical</td>
                                    <td><span class="status status-new">New</span></td>
                                    <td>Immediate</td>
                                    <td><button class="btn btn-primary btn-sm">View</button></td>
                                </tr>
                                <tr>
                                    <td>#TECH-1038</td>
                                    <td>Issue</td>
                                    <td>CRM software crashing on login</td>
                                    <td>All Users</td>
                                    <td>Michael Chen</td>
                                    <td>High</td>
                                    <td><span class="status status-new">New</span></td>
                                    <td>Tomorrow</td>
                                    <td><button class="btn btn-primary btn-sm">View</button></td>
                                </tr>
                                <tr>
                                    <td>#TECH-1037</td>
                                    <td>Installation</td>
                                    <td>Security camera system - Parking lot</td>
                                    <td>Facilities</td>
                                    <td>Sarah Johnson</td>
                                    <td>Medium</td>
                                    <td><span class="status status-completed">Completed</span></td>
                                    <td>Yesterday</td>
                                    <td><button class="btn btn-primary btn-sm">View</button></td>
                                </tr>
                                <tr>
                                    <td>#TECH-1036</td>
                                    <td>Installation</td>
                                    <td>New workstations for interns</td>
                                    <td>HR Department</td>
                                    <td>Emma Thompson</td>
                                    <td>Low</td>
                                    <td><span class="status status-pending">Pending</span></td>
                                    <td>Next Month</td>
                                    <td><button class="btn btn-primary btn-sm">View</button></td>
                                </tr>
                                <tr>
                                    <td>#TECH-1035</td>
                                    <td>Installation</td>
                                    <td>WiFi access points - 3rd Floor</td>
                                    <td>All Departments</td>
                                    <td>James Wilson</td>
                                    <td>High</td>
                                    <td><span class="status status-completed">Completed</span></td>
                                    <td>Yesterday</td>
                                    <td><button class="btn btn-primary btn-sm">View</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Engineer Assignments Page -->
                <div class="page" id="engineers">
                    <h2>Engineer Assignments</h2>
                    <p class="card-text">Tasks assigned to individual engineers</p>
                    
                    <div class="engineer-grid">
                        <!-- Engineer 1 -->
                        <div class="engineer-card">
                            <div class="engineer-header">
                                <img src="https://ui-avatars.com/api/?name=Sarah+Johnson&background=3498db&color=fff" alt="Sarah Johnson" class="engineer-avatar">
                                <div class="engineer-info">
                                    <h3>Sarah Johnson</h3>
                                    <p>Senior Systems Engineer</p>
                                </div>
                            </div>
                            <div class="engineer-stats">
                                <div class="stat">
                                    <div class="stat-value">5</div>
                                    <div class="stat-label">Active</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value">3</div>
                                    <div class="stat-label">Completed</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value">1</div>
                                    <div class="stat-label">Overdue</div>
                                </div>
                            </div>
                            <div class="engineer-tasks">
                                <div class="task-item">
                                    <div class="task-title">New server setup for Finance Dept</div>
                                    <div class="task-details">
                                        <span>#TECH-1042</span>
                                        <span class="status status-in-progress">In Progress</span>
                                    </div>
                                </div>
                                <div class="task-item">
                                    <div class="task-title">Security camera system installation</div>
                                    <div class="task-details">
                                        <span>#TECH-1037</span>
                                        <span class="status status-completed">Completed</span>
                                    </div>
                                </div>
                                <div class="task-item">
                                    <div class="task-title">Network infrastructure upgrade</div>
                                    <div class="task-details">
                                        <span>#TECH-1030</span>
                                        <span class="status status-in-progress">In Progress</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Engineer 2 -->
                        <div class="engineer-card">
                            <div class="engineer-header">
                                <img src="https://ui-avatars.com/api/?name=Michael+Chen&background=2ecc71&color=fff" alt="Michael Chen" class="engineer-avatar">
                                <div class="engineer-info">
                                    <h3>Michael Chen</h3>
                                    <p>Software Specialist</p>
                                </div>
                            </div>
                            <div class="engineer-stats">
                                <div class="stat">
                                    <div class="stat-value">4</div>
                                    <div class="stat-label">Active</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value">2</div>
                                    <div class="stat-label">Completed</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value">0</div>
                                    <div class="stat-label">Overdue</div>
                                </div>
                            </div>
                            <div class="engineer-tasks">
                                <div class="task-item">
                                    <div class="task-title">CRM mobile app deployment</div>
                                    <div class="task-details">
                                        <span>#TECH-1041</span>
                                        <span class="status status-new">New</span>
                                    </div>
                                </div>
                                <div class="task-item">
                                    <div class="task-title">CRM software crashing on login</div>
                                    <div class="task-details">
                                        <span>#TECH-1038</span>
                                        <span class="status status-new">New</span>
                                    </div>
                                </div>
                                <div class="task-item">
                                    <div class="task-title">API integration with accounting software</div>
                                    <div class="task-details">
                                        <span>#TECH-1025</span>
                                        <span class="status status-in-progress">In Progress</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Engineer 3 -->
                        <div class="engineer-card">
                            <div class="engineer-header">
                                <img src="https://ui-avatars.com/api/?name=James+Wilson&background=f39c12&color=fff" alt="James Wilson" class="engineer-avatar">
                                <div class="engineer-info">
                                    <h3>James Wilson</h3>
                                    <p>Network Engineer</p>
                                </div>
                            </div>
                            <div class="engineer-stats">
                                <div class="stat">
                                    <div class="stat-value">3</div>
                                    <div class="stat-label">Active</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value">4</div>
                                    <div class="stat-label">Completed</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value">1</div>
                                    <div class="stat-label">Overdue</div>
                                </div>
                            </div>
                            <div class="engineer-tasks">
                                <div class="task-item">
                                    <div class="task-title">Database backup failure alert</div>
                                    <div class="task-details">
                                        <span>#TECH-1039</span>
                                        <span class="status status-new">New</span>
                                    </div>
                                </div>
                                <div class="task-item">
                                    <div class="task-title">WiFi access points - 3rd Floor</div>
                                    <div class="task-details">
                                        <span>#TECH-1035</span>
                                        <span class="status status-completed">Completed</span>
                                    </div>
                                </div>
                                <div class="task-item">
                                    <div class="task-title">VPN configuration for remote staff</div>
                                    <div class="task-details">
                                        <span>#TECH-1028</span>
                                        <span class="status status-in-progress">In Progress</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Engineer 4 -->
                        <div class="engineer-card">
                            <div class="engineer-header">
                                <img src="https://ui-avatars.com/api/?name=Emma+Thompson&background=9b59b6&color=fff" alt="Emma Thompson" class="engineer-avatar">
                                <div class="engineer-info">
                                    <h3>Emma Thompson</h3>
                                    <p>Support Engineer</p>
                                </div>
                            </div>
                            <div class="engineer-stats">
                                <div class="stat">
                                    <div class="stat-value">3</div>
                                    <div class="stat-label">Active</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value">3</div>
                                    <div class="stat-label">Completed</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value">2</div>
                                    <div class="stat-label">Overdue</div>
                                </div>
                            </div>
                            <div class="engineer-tasks">
                                <div class="task-item">
                                    <div class="task-title">Email synchronization problem</div>
                                    <div class="task-details">
                                        <span>#TECH-1040</span>
                                        <span class="status status-in-progress">In Progress</span>
                                    </div>
                                </div>
                                <div class="task-item">
                                    <div class="task-title">Printer not working in Marketing</div>
                                    <div class="task-details">
                                        <span>#TECH-1033</span>
                                        <span class="status status-pending">Pending</span>
                                    </div>
                                </div>
                                <div class="task-item">
                                    <div class="task-title">New workstations for interns</div>
                                    <div class="task-details">
                                        <span>#TECH-1036</span>
                                        <span class="status status-pending">Pending</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
                });
            });
            
            // Filter functionality (basic implementation)
            const typeFilter = document.getElementById('type-filter');
            const statusFilter = document.getElementById('status-filter');
            const engineerFilter = document.getElementById('engineer-filter');
            const searchInput = document.getElementById('search');
            
            [typeFilter, statusFilter, engineerFilter, searchInput].forEach(filter => {
                filter.addEventListener('change', applyFilters);
                filter.addEventListener('keyup', applyFilters);
            });
            
            function applyFilters() {
                // This is a simplified version - in a real application,
                // you would make an AJAX request to your PHP backend with the filter values
                console.log('Filters applied:');
                console.log('Type:', typeFilter.value);
                console.log('Status:', statusFilter.value);
                console.log('Engineer:', engineerFilter.value);
                console.log('Search:', searchInput.value);
                
                // In a real application, you would filter the table rows based on these values
                // For this example, we'll just show an alert
                alert('In a real application, this would filter the results. For this demo, check the console for filter values.');
            }
        });
    </script>
</body>
</html>