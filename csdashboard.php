<?php
session_start();

// Check if user is logged in
// if (!isset($_SESSION['username'])) {
//     header('Location: login.php');
//     exit;
// }

$username = $_SESSION['username'];

// Database connection (adjust credentials)
$host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'telesol crm';

$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . htmlspecialchars($conn->connect_error));
}

// ================== SAMPLE DATA QUERIES ==================
// Replace these with your actual database queries

// 1. Total tickets
$totalTickets = 245; // example value
// 2. Open tickets (status = 'Open' or 'Pending')
$openTickets = 78;
// 3. Avg response time in hours (example)
$avgResponseTime = 4.5; // hours
// 4. Satisfaction score (out of 5)
$satisfaction = 4.2;

// 5. Ticket trends (last 7 days)
$trendLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$trendData = [65, 72, 80, 78, 90, 85, 95];

// 6. Status distribution
$statusCounts = [
    'Open' => 78,
    'In Progress' => 45,
    'Resolved' => 102,
    'Closed' => 20
];

// 7. Recent tickets
$recentTickets = [
    ['id' => 'T1234', 'customer' => 'Acme Corp', 'subject' => 'Login issue', 'status' => 'Open', 'priority' => 'High', 'agent' => 'John Doe', 'created' => '2023-06-15'],
    ['id' => 'T1235', 'customer' => 'Beta Ltd', 'subject' => 'Billing question', 'status' => 'In Progress', 'priority' => 'Medium', 'agent' => 'Jane Smith', 'created' => '2023-06-15'],
    ['id' => 'T1236', 'customer' => 'Gamma Inc', 'subject' => 'Feature request', 'status' => 'Resolved', 'priority' => 'Low', 'agent' => 'Mike Lee', 'created' => '2023-06-14'],
    ['id' => 'T1237', 'customer' => 'Delta Co', 'subject' => 'Cannot access account', 'status' => 'Open', 'priority' => 'Critical', 'agent' => 'Sarah Kim', 'created' => '2023-06-14'],
    ['id' => 'T1238', 'customer' => 'Epsilon', 'subject' => 'Email not sending', 'status' => 'Closed', 'priority' => 'Medium', 'agent' => 'Tom Brown', 'created' => '2023-06-13'],
];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Customer Service Dashboard - Telesol CRM</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #083b6e;
            --primary-dark: #1e2d3b;
            --secondary: #3498db;
            --success: #00b44b;
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

        [data-theme="dark"] {
            --primary: #58a6ff;
            --primary-dark: #206ab6;
            --secondary: #58a6ff;
            --background: #121212;
            --white: #1e1e1e;
            --dark: #f2f2f2;
            --light: #2d2d2d;
            --light-gray: #333;
            --gray: #8899a6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--background);
            color: var(--dark);
            display: flex;
            min-height: 100vh;
            font-size: 14px;
            transition: background-color var(--transition), color var(--transition);
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--primary);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: background-color var(--transition);
        }

        .sidebar-header {
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h1 {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .sidebar-menu ul {
            list-style: none;
            padding: 1rem 0;
        }

        .sidebar-menu li {
            margin: 0.2rem 0;
        }

        .sidebar-menu a {
            color: white;
            text-decoration: none;
            padding: 0.6rem 1rem;
            display: block;
            font-size: 0.9rem;
            transition: all var(--transition);
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background-color: var(--secondary);
            border-left-color: white;
        }

        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Header */
        .header {
            background-color: var(--white);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 0.75rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            border-bottom: 1px solid var(--light-gray);
        }

        .page-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .dark-mode-toggle {
            cursor: pointer;
            background: var(--secondary);
            border: none;
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 30px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color var(--transition);
        }

        .dark-mode-toggle:hover {
            background: var(--primary-dark);
        }

        .logout-link {
            color: var(--dark);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            font-weight: 500;
        }

        .logout-link:hover {
            color: var(--danger);
        }

        /* Page Content */
        .page-content {
            padding: 1.5rem;
        }

        /* Metric Cards */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.2rem;
            margin-bottom: 2rem;
        }

        .metric-card {
            background: var(--white);
            padding: 1.2rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid var(--light-gray);
            transition: transform var(--transition), box-shadow var(--transition);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .metric-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(52,152,219,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary);
            font-size: 1.8rem;
        }

        .metric-content h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
        }

        .metric-content p {
            margin: 0;
            color: var(--gray);
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* Charts Row */
        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: var(--white);
            padding: 1.2rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid var(--light-gray);
        }

        .chart-title {
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .chart-container {
            position: relative;
            height: 250px;
        }

        /* Recent Tickets Table */
        .table-card {
            background: var(--white);
            padding: 1.2rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid var(--light-gray);
            margin-bottom: 2rem;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .table-title {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .view-all-link {
            color: var(--secondary);
            text-decoration: none;
            font-weight: 500;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            text-align: left;
            padding: 0.75rem;
            font-weight: 600;
            color: var(--gray);
            border-bottom: 2px solid var(--light-gray);
        }

        .table td {
            padding: 0.75rem;
            border-bottom: 1px solid var(--light-gray);
        }

        .status-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-open { background: #f39c12; color: #fff; }
        .status-inprogress { background: #3498db; color: #fff; }
        .status-resolved { background: #00b44b; color: #fff; }
        .status-closed { background: #95a5a6; color: #fff; }

        .priority-badge {
            display: inline-block;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
            background: #ecf0f1;
        }
        .priority-high { background: #e74c3c; color: #fff; }
        .priority-medium { background: #f39c12; color: #fff; }
        .priority-low { background: #3498db; color: #fff; }

        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .quick-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            background: var(--secondary);
            color: white;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: background var(--transition);
        }

        .quick-btn:hover {
            background: var(--primary-dark);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .charts-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body data-theme="light">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h1>Telesol CRM</h1>
        </div>
        <nav class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li><a href="ticket_mgt.php"><i class="bi bi-ticket-detailed"></i> Ticket Management</a></li>
                <li><a href="installation_mgt.php"><i class="bi bi-wrench"></i> Installations</a></li>
                <li><a href="customer_service_dashboard.php" class="active"><i class="bi bi-headset"></i> Customer Service</a></li>
                <li><a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a></li>
                <li><a href="settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="page-title">
                <i class="bi bi-headset me-2"></i>Customer Service Dashboard
            </div>
            <div class="user-info">
                <span><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($username) ?></span>
                <button id="darkModeToggle" class="dark-mode-toggle">
                    <i class="bi bi-moon-fill" id="darkModeIcon"></i> Dark
                </button>
                <a href="?logout=1" class="logout-link"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </header>

        <!-- Page Content -->
        <div class="page-content">
            <!-- Metrics Cards -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon"><i class="bi bi-ticket-detailed"></i></div>
                    <div class="metric-content">
                        <h3><?= $totalTickets ?></h3>
                        <p>Total Tickets</p>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon"><i class="bi bi-envelope-open"></i></div>
                    <div class="metric-content">
                        <h3><?= $openTickets ?></h3>
                        <p>Open Tickets</p>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon"><i class="bi bi-clock-history"></i></div>
                    <div class="metric-content">
                        <h3><?= $avgResponseTime ?>h</h3>
                        <p>Avg Response Time</p>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon"><i class="bi bi-star"></i></div>
                    <div class="metric-content">
                        <h3><?= $satisfaction ?>/5</h3>
                        <p>Satisfaction Score</p>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="charts-row">
                <div class="chart-card">
                    <div class="chart-title"><i class="bi bi-graph-up"></i> Ticket Trends (Last 7 Days)</div>
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-title"><i class="bi bi-pie-chart"></i> Status Distribution</div>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Tickets Table -->
            <div class="table-card">
                <div class="table-header">
                    <span class="table-title"><i class="bi bi-clock me-2"></i>Recent Tickets</span>
                    <a href="view_tickets.php" class="view-all-link">View All <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Ticket ID</th>
                                <th>Customer</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Assigned To</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTickets as $ticket): 
                                $statusClass = '';
                                switch(strtolower($ticket['status'])) {
                                    case 'open': $statusClass = 'status-open'; break;
                                    case 'in progress': $statusClass = 'status-inprogress'; break;
                                    case 'resolved': $statusClass = 'status-resolved'; break;
                                    case 'closed': $statusClass = 'status-closed'; break;
                                }
                                $priorityClass = '';
                                switch(strtolower($ticket['priority'])) {
                                    case 'critical':
                                    case 'high': $priorityClass = 'priority-high'; break;
                                    case 'medium': $priorityClass = 'priority-medium'; break;
                                    case 'low': $priorityClass = 'priority-low'; break;
                                }
                            ?>
                            <tr>
                                <td><a href="ticket_detail.php?id=<?= $ticket['id'] ?>"><?= $ticket['id'] ?></a></td>
                                <td><?= htmlspecialchars($ticket['customer']) ?></td>
                                <td><?= htmlspecialchars($ticket['subject']) ?></td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= $ticket['status'] ?></span></td>
                                <td><span class="priority-badge <?= $priorityClass ?>"><?= $ticket['priority'] ?></span></td>
                                <td><?= htmlspecialchars($ticket['agent']) ?></td>
                                <td><?= $ticket['created'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="new_ticket.php" class="quick-btn"><i class="bi bi-plus-circle"></i> New Ticket</a>
                <a href="search.php" class="quick-btn"><i class="bi bi-search"></i> Advanced Search</a>
            </div>
        </div>
    </main>

    <!-- Chart.js Scripts -->
    <script>
        (function() {
            // Dark mode toggle
            const toggle = document.getElementById('darkModeToggle');
            const body = document.body;
            const icon = document.getElementById('darkModeIcon');

            function setDarkMode(enabled) {
                if (enabled) {
                    body.setAttribute('data-theme', 'dark');
                    icon.classList.replace('bi-moon-fill', 'bi-sun-fill');
                    toggle.innerHTML = '<i class="bi bi-sun-fill" id="darkModeIcon"></i> Light';
                    localStorage.setItem('crmDarkMode', 'enabled');
                } else {
                    body.setAttribute('data-theme', 'light');
                    icon.classList.replace('bi-sun-fill', 'bi-moon-fill');
                    toggle.innerHTML = '<i class="bi bi-moon-fill" id="darkModeIcon"></i> Dark';
                    localStorage.setItem('crmDarkMode', 'disabled');
                }
            }

            // Load saved preference
            const saved = localStorage.getItem('crmDarkMode');
            if (saved === 'enabled') setDarkMode(true);
            else setDarkMode(false);

            toggle.addEventListener('click', () => {
                const isDark = body.getAttribute('data-theme') === 'dark';
                setDarkMode(!isDark);
            });

            // Charts
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($trendLabels) ?>,
                    datasets: [{
                        label: 'Tickets',
                        data: <?= json_encode($trendData) ?>,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52,152,219,0.1)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });

            const statusCtx = document.getElementById('statusChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'pie',
                data: {
                    labels: <?= json_encode(array_keys($statusCounts)) ?>,
                    datasets: [{
                        data: <?= json_encode(array_values($statusCounts)) ?>,
                        backgroundColor: ['#e74c3c', '#f39c12', '#00b44b', '#95a5a6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        })();
    </script>
</body>
</html>