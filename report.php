<?php
session_start();
// DB connection setup
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "telesol crm";
$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

function esc($str) {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Retrieve filters with defaults
$search_name = trim($_GET['search_name'] ?? '');
$report_type = $_GET['report_type'] ?? 'full';
$issue_type = $_GET['issue_type'] ?? 'all';
$date_filter = $_GET['date_filter'] ?? 'all';
$custom_start = $_GET['custom_start'] ?? '';
$custom_end = $_GET['custom_end'] ?? '';
$status_filter = $_GET['status_filter'] ?? 'all';

// Build dynamic WHERE clauses
$where = [];
$params = [];
$param_types = "";

if ($search_name !== '') {
    $where[] = "(customer_name LIKE CONCAT('%', ?, '%') OR logged_by LIKE CONCAT('%', ?, '%'))";
    $param_types .= 'ss';
    array_push($params, $search_name, $search_name);
}
if ($issue_type !== 'all' && $issue_type !== '') {
    $where[] = "issue_type = ?";
    $param_types .= 's';
    $params[] = $issue_type;
}
if ($status_filter !== 'all' && $status_filter !== '') {
    $where[] = "issue_status = ?";
    $param_types .= 's';
    $params[] = $status_filter;
}

$date_col = 'created_at';
switch ($date_filter) {
    case 'day':
        $where[] = "DATE($date_col) = CURDATE()";
        break;
    case 'week':
        $where[] = "YEARWEEK($date_col, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'month':
        $where[] = "YEAR($date_col) = YEAR(CURDATE()) AND MONTH($date_col) = MONTH(CURDATE())";
        break;
    case 'year':
        $where[] = "YEAR($date_col) = YEAR(CURDATE())";
        break;
    case 'custom':
        if ($custom_start && $custom_end) {
            $where[] = "$date_col BETWEEN ? AND ?";
            $param_types .= 'ss';
            $params[] = $custom_start . " 00:00:00";
            $params[] = $custom_end . " 23:59:59";
        }
        break;
}

$where_sql = count($where) ? "WHERE " . implode(' AND ', $where) : "";

// Fetch distinct issue types for filters
$issue_types_query = $conn->query("SELECT DISTINCT issue_type FROM tickets ORDER BY issue_type");
$issue_types = [];
while($row = $issue_types_query->fetch_assoc()) {
    if ($row['issue_type']) $issue_types[] = $row['issue_type'];
}

// Fetch distinct statuses for filters
$statuses_query = $conn->query("SELECT DISTINCT issue_status FROM tickets ORDER BY issue_status");
$statuses = [];
while($row = $statuses_query->fetch_assoc()) {
    if ($row['issue_status']) $statuses[] = $row['issue_status'];
}

// Fetch report data
$complaints = [];
$summary = [];

if ($search_name || $issue_type !== 'all' || $date_filter !== 'all' || $status_filter !== 'all') {
    if ($report_type === 'summary') {
        $sql = "SELECT 
                    customer_name,
                    COUNT(*) AS complaint_count,
                    GROUP_CONCAT(DISTINCT comments ORDER BY created_at SEPARATOR '\n---\n') AS combined_comments,
                    MIN(created_at) AS first_logged,
                    MAX(resolved_by_deadline) AS last_resolved,
                    GROUP_CONCAT(DISTINCT assigned_persons ORDER BY assigned_persons SEPARATOR ', ') AS assigned_engineers,
                    GROUP_CONCAT(DISTINCT issue_status ORDER BY issue_status SEPARATOR ', ') AS statuses
                FROM tickets
                $where_sql
                GROUP BY customer_name
                ORDER BY first_logged DESC";
        $stmt = $conn->prepare($sql);
        if($param_types) $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()) $summary[] = $row;
        $stmt->close();

        $chart_labels = array_column($summary, 'customer_name');
        $chart_data = array_map(fn($i) => (int)$i['complaint_count'], $summary);
    } else {
        $sql = "SELECT * FROM tickets $where_sql ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
        if($param_types) $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        while($row = $res->fetch_assoc()) $complaints[] = $row;
        $stmt->close();

        $issue_counts = [];
        foreach($complaints as $c) {
            $t = $c['issue_type'] ?? 'Unknown';
            $issue_counts[$t] = ($issue_counts[$t] ?? 0) + 1;
        }
        $chart_labels = array_keys($issue_counts);
        $chart_data = array_values($issue_counts);
    }
} else {
    $chart_labels = $chart_data = [];
}

// Get stats for dashboard
$total_complaints = $conn->query("SELECT COUNT(*) as total FROM tickets")->fetch_assoc()['total'];
$resolved_complaints = $conn->query("SELECT COUNT(*) as resolved FROM tickets WHERE issue_status LIKE '%resolved%'")->fetch_assoc()['resolved'];
$pending_complaints = $conn->query("SELECT COUNT(*) as pending FROM tickets WHERE issue_status NOT LIKE '%resolved%'")->fetch_assoc()['pending'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>CRM Complaints Dashboard & Reports</title>
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
        --background: #d7d7d7;
        --white: #ffffff;
        --light-green: #3ad809ff;
        --green: #43b920ff;
        --orange: #f9e50aff;
        --deep-orange: #c0b107ff;
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
            margin-left: var(--sidebar-width);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .header {
            background-color: var(--white);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 0.45rem 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            color: var(--dark);
        }  z-index: 100;

        
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






























/* Layout */
.app-container {
    display: flex;
    min-height: 100vh;
}


    /* Main Content */
    /* .main-content {
        flex: 1;
        margin-left: 240px;
        display: flex;
        flex-direction: column;
    } */

/* Navbar */
.navbar {
    background-color: white;
    color: var(--dark);
    padding: 0.8rem 2rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    position: sticky;
    top: 0;
    z-index: 90;
}

.page-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.page-title i {
    font-size: 1.5rem;
    color: var(--primary);
}

.page-title h1 {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--dark);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user-info img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--light-gray);
}

.user-info span {
    font-weight: 500;
}

/* Stats Cards */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.2rem;
    padding: 1.5rem 2rem 0;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.2rem;
    box-shadow: var(--card-shadow);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    color: white;
}

.stat-icon.total { background: var(--primary); }
.stat-icon.resolved { background: var(--success); }
.stat-icon.pending { background: var(--warning); }

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 1.7rem;
    font-weight: 700;
    line-height: 1;
}

.stat-label {
    font-size: 0.85rem;
    color: var(--gray);
    margin-top: 0.25rem;
}

/* Main Container */
.container {
    flex: 1;
    display: flex;
    padding: 1.5rem 2rem;
    gap: 1.5rem;
    overflow: hidden;
}

/* Left panel - Filters */
.filters-panel {
    width: 280px;
    background: white;
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    padding: 1.2rem;
    display: flex;
    flex-direction: column;
    gap: 1.2rem;
    height: fit-content;
    position: sticky;
    top: 0px;
}

.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.panel-header h2 {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--dark);
    font-family: inherit;
    margin-bottom: -7px;
}

.panel-header button {
    background: none;
    border: none;
    color: var(--dark);
    cursor: pointer;
    font-size: 0.9rem;
    font-family: inherit;
    font-weight: 600;
}

.panel-header button:hover {
    text-decoration: underline;
}

.filter-group {
    margin-bottom: 0.1rem;
}

.filter-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
    color: var(--dark);
}

.filter-group input[type="text"],
.filter-group select,
.filter-group input[type="date"] {
    width: 100%;
    height: 35px;
    border-radius: 8px;
    font-size: 0.95rem;
    color: var(--dark);
    font-family: inherit;
    padding: 0.7rem 0.7rem;
    border: 1px solid var(--light-gray);
    transition: var(--transition);
}

.filter-group input[type="text"]:focus,
.filter-group select:focus,
.filter-group input[type="date"]:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(17, 90, 142, 0.15);
}

.radio-group {
    display: flex;
    gap: 1.5rem;
}

.radio-group label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    font-size: 0.95rem;
}

.radio-group input[type="radio"] {
    cursor: pointer;
    accent-color: var(--primary);
}

.action-buttons {
    display: flex;
    gap: 0.75rem;
    margin-top: 0.5rem;
}

.btn {
    padding: 0.65rem 0.65rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 0.9rem;
}

.btn-primary {
    background: var(--primary);
    color: white;
    flex: 2;
}

.btn-primary:hover {
    background: var(--primary-dark);
}

.btn-secondary {
    background: var(--light-gray);
    color: var(--dark);
    flex: 1;
}

.btn-secondary:hover {
    background: #dde1e7;
}

/* Right panel: charts + reports */
.report-section {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    gap: 1.5rem;
}

.chart-container {
    background: white;
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    padding: 1.5rem;
    position: relative;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.chart-header h3 {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--dark);
}

.chart-actions {
    display: flex;
    gap: 0.5rem;
}

.chart-actions button {
    background: white;
    border: 1px solid var(--light-gray);
    border-radius: 6px;
    padding: 0.5rem 0.75rem;
    font-size: 0.9rem;
    cursor: pointer;
    transition: var(--transition);
}

.chart-actions button.active,
.chart-actions button:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.chart-wrapper {
    height: 280px;
    position: relative;
}

.report-table-container {
    background: white;
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    overflow: hidden;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.table-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--light-gray);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.table-header h3 {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--dark);
}

.table-actions {
    display: flex;
    gap: 0.5rem;
}

.export-btn {
    background: white;
    border: 1px solid var(--light-gray);
    border-radius: 6px;
    padding: 0.5rem 0.75rem;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: var(--transition);
}

.export-btn:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.table-wrapper {
    overflow: auto;
    flex: 1;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
}

thead {
    position: sticky;
    top: 0;
    z-index: 10;
}

thead tr {
    background: var(--primary);
    color: white;
}

th, td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid var(--light-gray);
}

th {
    font-weight: 600;
    white-space: nowrap;
}

tbody tr {
    transition: var(--transition);
}

tbody tr:nth-child(even) {
    background: #fafbff;
}

tbody tr:hover {
    background: #edf1ff;
}

.status-badge {
    padding: 0.35rem 0.75rem;
    border-radius: 50px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-block;
    text-align: center;
    min-width: 100px;
}

.status-resolved {
    background: rgba(40, 167, 69, 0.15);
    color: var(--success);
}

.status-pending {
    background: rgba(220, 53, 69, 0.15);
    color: var(--danger);
}

.status-inprogress {
    background: rgba(255, 193, 7, 0.15);
    color: var(--warning);
}

.comment-preview {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
}

.tooltip {
    position: relative;
    display: inline-block;
}

.tooltip .tooltip-text {
    visibility: hidden;
    width: 280px;
    background: var(--dark);
    color: white;
    text-align: left;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    position: absolute;
    z-index: 100;
    bottom: 125%;
    left: 50%;
    transform: translateX(-50%);
    opacity: 0;
    transition: opacity 0.3s;
    font-size: 0.9rem;
    line-height: 1.5;
    white-space: pre-wrap;
}

.tooltip:hover .tooltip-text {
    visibility: visible;
    opacity: 1;
}

.empty-state {
    padding: 3rem 1rem;
    text-align: center;
    color: var(--gray);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state p {
    font-size: 1.1rem;
    margin-bottom: 1.5rem;
}

/* Responsive */
@media (max-width: 1200px) {
    .container {
        flex-direction: column;
    }
    
    .filters-panel {
        width: 100%;
        position: static;
    }
    
    .stats-container {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 992px) {
    .sidebar {
        width: 70px;
        padding: 1rem 0.5rem;
    }
    
    .sidebar-header, .company-slogan, .nav-link span, .sidebar-footer .btn span {
        display: none;
    }
    
    .company-logo {
        width: 40px;
        height: 40px;
        border-radius: 6px;
    }
    
    .nav-link {
        justify-content: center;
        padding: 0.75rem;
    }
    
    .nav-link i {
        margin-right: 0;
        font-size: 1.2rem;
    }
    
    .main-content {
        margin-left: 70px;
    }
}

@media (max-width: 768px) {
    .stats-container {
        grid-template-columns: 1fr;
        padding: 1rem;
        gap: 1rem;
    }
    
    .table-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .table-actions {
        width: 100%;
        justify-content: space-between;
    }
    
    .radio-group {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .navbar {
        padding: 0.8rem 1rem;
    }
    
    .container {
        padding: 1rem;
    }
    
    .chart-wrapper {
        height: 250px;
    }
}

/* Animation */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.report-section > * {
    animation: fadeIn 0.5s ease-out;
}

/* Print styles */
@media print {
    .sidebar, .filters-panel, .chart-actions, .table-actions, .user-info {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0;
    }
    
    .container {
        display: block;
        padding: 0;
    }
    
    .report-section {
        display: block;
    }
    
    .chart-container, .report-table-container {
        box-shadow: none;
        border: 1px solid #ddd;
        margin-bottom: 1rem;
        page-break-inside: avoid;
    }
}
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Sidebar -->
  <aside class="sidebar" aria-label="Main navigation">
    <div class="sidebar-header">
      <h1>Telesol CRM</h1>
    </div>
    <nav class="sidebar-menu">
      <ul>
        <li><a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li><a href="ticket_mgt.php"><i class="bi bi-ticket-detailed"></i> Ticket Management</a></li>
        <li><a href="installation_mgt.php" class="active"><i class="bi bi-wrench"></i> Installations</a></li>
        <li><a href="customer_experience_dashboard.php"><i class="bi bi-people"></i> Customer Experience</a></li>
        <li><a href="report.php"><i class="bi bi-bar-chart"></i> Reports</a></li>
        <li><a href="#"><i class="bi bi-gear"></i> Settings</a></li>
        <li><a href="#"><i class="bi bi-arrow-left-circle"></i> Back</a></li>
        <li><a href="login.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
      </ul>
    </nav>
  </aside>

<!-- Main Content -->
<div class="main-content">
    <header class="header">
        <div class="navbar-search" role="search" aria-label="Search tickets">
            <input
                type="search"
                name="search"
                id="searchInput"
                placeholder="Search installations by customer name, phone no."
                value="<?= esc($search_term) ?>"
                aria-describedby="searchHelp"
                autocomplete="off"
                aria-autocomplete="list"
                aria-controls="searchResults"
            />
        
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.53 20.47l-4.96-4.96A7.44 7.44 0 0 0 18 10.5 7.5 7.5 0 1 0 10.5 18a7.44 7.44 0 0 0 5.01-1.43l4.96 4.96a.75.75 0 0 0 1.06-1.06zM10.5 16.5a6 6 0 1 1 6-6 6 6 0 0 1-6 6z"/></svg>
        </div>

        <div class="user-session">
            <i class="bi bi-person-circle"></i>
                <span>Logged in as: <strong><?= esc($username) ?></strong></span>
        </div>
    </header>
    <!-- <div class="header">
        <div>
            <button class="mobile-menu-btn"><i class="fas fa-bars"></i></button>
        </div>
        <div class="user-info">
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($username) ?>&background=3498db&color=fff" alt="User">
            <span><?= htmlspecialchars($username) ?></span>
        </div>
    </div> -->

    <!-- Stats Cards -->
    <div class="stats-container">
        <div class="stat-card">
            <div class="stat-icon total">
                <i class="fas fa-ticket-alt"></i>
            </div>
            
            <div class="stat-content">
                    <div class="stat-value"><?= $total_complaints ?></div>
                    <div class="stat-label">Total Complaints</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon resolved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $resolved_complaints ?></div>
                    <div class="stat-label">Resolved Complaints</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $pending_complaints ?></div>
                    <div class="stat-label">Pending Complaints</div>
                </div>
            </div>
        </div>

        <!-- Content Container -->
        <div class="container">
            <section class="filters-panel">
                <div class="panel-header">
                    <h2>Report Filters</h2>
                    <button type="button" onclick="clearFilters()">Clear All</button>
                </div>
                
                <form method="get" id="filterForm">
                    <div class="filter-group">
                        <label>Report Type</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="report_type" value="full" <?= $report_type === 'full' ? 'checked' : '' ?>>
                                <span>Detailed</span>
                            </label>
                            <label>
                                <input type="radio" name="report_type" value="summary" <?= $report_type === 'summary' ? 'checked' : '' ?>>
                                <span>Summary</span>
                            </label>
                        </div>
                    </div>

                    <div class="filter-group">
                        <label for="search_name">Customer or Logged By</label>
                        <input type="text" id="search_name" name="search_name" value="<?= esc($search_name) ?>" 
                               placeholder="Enter name" autocomplete="off">
                    </div>
                    
                    <div class="filter-group">
                        <label for="issue_type">Issue Type</label>
                        <select name="issue_type" id="issue_type">
                            <option value="all" <?= ($issue_type === 'all' ? 'selected' : '') ?>>All Types</option>
                            <?php foreach($issue_types as $it): ?>
                            <option value="<?= esc($it) ?>" <?= ($issue_type === $it ? 'selected' : '') ?>><?= esc($it) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status_filter">Status</label>
                        <select name="status_filter" id="status_filter">
                            <option value="all" <?= ($status_filter === 'all' ? 'selected' : '') ?>>All Statuses</option>
                            <?php foreach($statuses as $status): ?>
                            <option value="<?= esc($status) ?>" <?= ($status_filter === $status ? 'selected' : '') ?>><?= esc($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_filter">Date Range</label>
                        <select id="date_filter" name="date_filter">
                            <option value="all" <?= $date_filter === 'all' ? 'selected' : '' ?>>All Time</option>
                            <option value="day" <?= $date_filter === 'day' ? 'selected' : '' ?>>Today</option>
                            <option value="week" <?= $date_filter === 'week' ? 'selected' : '' ?>>This Week</option>
                            <option value="month" <?= $date_filter === 'month' ? 'selected' : '' ?>>This Month</option>
                            <option value="year" <?= $date_filter === 'year' ? 'selected' : '' ?>>This Year</option>
                            <option value="custom" <?= $date_filter === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                        </select>
                    </div>
                    
                    <div class="filter-group" id="custom-date-inputs" style="display: none;">
                        <label for="custom_start">Start Date</label>
                        <input type="date" id="custom_start" name="custom_start" value="<?= esc($custom_start) ?>">
                        
                        <label for="custom_end" style="margin-top: 0.8rem;">End Date</label>
                        <input type="date" id="custom_end" name="custom_end" value="<?= esc($custom_end) ?>">
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </form>
            </section>

            <section class="report-section">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3><?= $report_type === 'summary' ? "Complaints per Customer" : "Complaints by Issue Type" ?></h3>
                        <div class="chart-actions">
                            <button class="chart-toggle active" data-chart-type="bar">Bar</button>
                            <button class="chart-toggle" data-chart-type="line">Line</button>
                            <button class="chart-toggle" data-chart-type="pie">Pie</button>
                        </div>
                    </div>
                    <div class="chart-wrapper">
                        <canvas id="complaintsChart"></canvas>
                    </div>
                </div>

                <div class="report-table-container">
                    <div class="table-header">
                        <h3><?= $report_type === 'summary' ? "Complaints Summary" : "Complaints Details" ?></h3>
                        <div class="table-actions">
                            <button class="export-btn" onclick="exportTable('csv')">
                                <i class="fas fa-file-csv"></i> CSV
                            </button>
                            <button class="export-btn" onclick="exportTable('excel')">
                                <i class="fas fa-file-excel"></i> Excel
                            </button>
                            <button class="export-btn" onclick="exportTable('pdf')">
                                <i class="fas fa-file-pdf"></i> PDF
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-wrapper">
                        <?php if($report_type === 'summary'): ?>
                            <?php if(empty($summary)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No summarized complaints found matching your filters</p>
                                    <button class="btn btn-primary" onclick="clearFilters()">Clear Filters</button>
                                </div>
                            <?php else: ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Customer Name</th>
                                            <th>Total Complaints</th>
                                            <th>Combined Comments</th>
                                            <th>First Logged</th>
                                            <th>Last Resolved</th>
                                            <th>Assigned Engineers</th>
                                            <th>Statuses</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($summary as $row): ?>
                                        <tr>
                                            <td><strong><?= esc($row['customer_name']) ?></strong></td>
                                            <td><span class="stat-value"><?= (int)$row['complaint_count'] ?></span></td>
                                            <td>
                                                <div class="tooltip">
                                                    <div class="comment-preview">
                                                        <?= esc(mb_strimwidth($row['combined_comments'], 0, 60, '...')) ?>
                                                    </div>
                                                    <span class="tooltip-text"><?= esc($row['combined_comments']) ?></span>
                                                </div>
                                            </td>
                                            <td><?= esc($row['first_logged'] ? date('M j, Y', strtotime($row['first_logged'])) : '–') ?></td>
                                            <td><?= esc($row['last_resolved'] ? date('M j, Y', strtotime($row['last_resolved'])) : '–') ?></td>
                                            <td><?= esc($row['assigned_engineers'] ?: '–') ?></td>
                                            <td>
                                                <?php 
                                                $statuses = array_unique(array_map('trim', explode(',', $row['statuses'])));
                                                foreach($statuses as $status): 
                                                    $class = 'status-pending';
                                                    $lower = strtolower($status);
                                                    if (str_contains($lower, 'resolved')) $class = 'status-resolved';
                                                    else if (str_contains($lower, 'in progress')) $class = 'status-inprogress';
                                                ?>
                                                <span class="status-badge <?= esc($class) ?>"><?= esc(ucwords($status)) ?></span>
                                                <?php endforeach; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if(empty($complaints)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>No detailed complaints found matching your filters</p>
                                    <button class="btn btn-primary" onclick="clearFilters()">Clear Filters</button>
                                </div>
                            <?php else: ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Issue ID</th>
                                            <th>Customer Name</th>
                                            <th>Logged By</th>
                                            <th>Issue Type</th>
                                            <th>Date Logged</th>
                                            <th>Assigned To</th>
                                            <th>Status</th>
                                            <th>Comments</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($complaints as $c):
                                            $statusClass = 'status-pending';
                                            $stLower = strtolower($c['issue_status'] ?? '');
                                            if(str_contains($stLower, 'resolved')) $statusClass = 'status-resolved';
                                            else if(str_contains($stLower, 'in progress')) $statusClass = 'status-inprogress';
                                        ?>
                                        <tr>
                                            <td>#<?= esc($c['id']) ?></td>
                                            <td><strong><?= esc($c['customer_name']) ?></strong></td>
                                            <td><?= esc($c['logged_by']) ?></td>
                                            <td><?= esc($c['issue_type']) ?></td>
                                            <td><?= esc(date('M j, Y', strtotime($c['created_at']))) ?></td>
                                            <td><?= esc($c['assigned_persons'] ?: '–') ?></td>
                                            <td>
                                                <span class="status-badge <?= esc($statusClass) ?>">
                                                    <?= esc($c['issue_status'] ?: 'Pending') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if(!empty($c['comments'])): ?>
                                                <div class="tooltip">
                                                    <div class="comment-preview">
                                                        <?= esc(mb_strimwidth($c['comments'], 0, 60, '...')) ?>
                                                    </div>
                                                    <span class="tooltip-text"><?= esc($c['comments']) ?></span>
                                                </div>
                                                <?php else: ?>
                                                <span class="text-muted">No comments</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle custom date inputs
    function toggleCustomDate() {
        const dateFilter = document.getElementById('date_filter');
        const customDateInputs = document.getElementById('custom-date-inputs');
        customDateInputs.style.display = dateFilter.value === 'custom' ? 'block' : 'none';
    }
    
    // Initialize
    toggleCustomDate();
    document.getElementById('date_filter').addEventListener('change', toggleCustomDate);
    
    // Clear filters
    window.clearFilters = function() {
        document.getElementById('search_name').value = '';
        document.getElementById('issue_type').value = 'all';
        document.getElementById('status_filter').value = 'all';
        document.getElementById('date_filter').value = 'all';
        document.getElementById('custom_start').value = '';
        document.getElementById('custom_end').value = '';
        document.querySelector('input[name="report_type"][value="full"]').checked = true;
        toggleCustomDate();
        document.getElementById('filterForm').submit();
    }
    
    // Export functionality
    window.exportTable = function(format) {
        alert(`Exporting data as ${format.toUpperCase()} format. In a real application, this would generate and download a file.`);
        // Actual implementation would generate CSV/Excel/PDF here
    }
    
    // Initialize chart
    const ctx = document.getElementById('complaintsChart').getContext('2d');
    const labels = <?= json_encode($chart_labels) ?>;
    const data = <?= json_encode($chart_data) ?>;
    
    const chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels.length ? labels : ["No Data"],
            datasets: [{
                label: 'Number of Complaints',
                data: data.length ? data : [0],
                backgroundColor: 'rgba(17, 90, 142, 0.7)',
                borderColor: 'rgba(17, 90, 142, 1)',
                borderWidth: 1,
                borderRadius: 6,
                maxBarThickness: 40,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.7)',
                    titleFont: {
                        size: 14,
                        weight: 'bold'
                    },
                    bodyFont: {
                        size: 13
                    },
                    padding: 12,
                    cornerRadius: 6
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false
                    },
                    ticks: {
                        stepSize: 1
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    
    // Chart type toggle
    document.querySelectorAll('.chart-toggle').forEach(button => {
        button.addEventListener('click', function() {
            const type = this.dataset.chartType;
            chart.config.type = type;
            chart.update();
            
            // Update active state
            document.querySelectorAll('.chart-toggle').forEach(btn => {
                btn.classList.remove('active');
            });
            this.classList.add('active');
        });
    });
});
</script>
</body>
</html>