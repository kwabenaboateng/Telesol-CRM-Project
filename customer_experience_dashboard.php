<?php
declare(strict_types=1);
session_start();

/* ===================== SESSION ===================== */
$username = $_SESSION['username'] ?? 'User';

/* ===================== DATABASE CONFIG ===================== */
$dbConfig = [
    'host'     => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'customer_feedback'
];

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        $dbConfig['host'],
        $dbConfig['username'],
        $dbConfig['password'],
        $dbConfig['database']
    );
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    exit('Database connection failed.');
}

/* ===================== HELPERS ===================== */
function esc(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/* ===================== FILTER INPUT ===================== */
$filters = [
    'recommend'     => $_GET['recommend'] ?? '',
    'team_helpful'  => $_GET['team_helpful'] ?? '',
    'date_filter'   => $_GET['date_filter'] ?? '',
    'date_value'    => $_GET['date_value'] ?? '',
    'year'          => $_GET['year'] ?? ''
];

$hasActiveFilters = !empty(array_filter($filters));

/* ===================== QUERY BUILD ===================== */
$whereClauses = [];
$params = [];
$paramTypes = '';

if ($filters['recommend'] !== '') {
    $whereClauses[] = "recommend = ?";
    $params[] = $filters['recommend'];
    $paramTypes .= 's';
}

if ($filters['team_helpful'] !== '') {
    $whereClauses[] = "team_helpful = ?";
    $params[] = $filters['team_helpful'];
    $paramTypes .= 's';
}

if ($filters['date_filter'] && $filters['date_value']) {
    switch ($filters['date_filter']) {
        case 'day':
            $whereClauses[] = "DATE(timestamp) = ?";
            $params[] = $filters['date_value'];
            $paramTypes .= 's';
            break;

        case 'week':
            $whereClauses[] = "YEAR(timestamp) = ? AND WEEK(timestamp,3) = ?";
            $params[] = (int)($filters['year'] ?: date('Y'));
            $params[] = (int)$filters['date_value'];
            $paramTypes .= 'ii';
            break;

        case 'month':
            $whereClauses[] = "YEAR(timestamp) = ? AND MONTH(timestamp) = ?";
            $params[] = (int)($filters['year'] ?: date('Y'));
            $params[] = (int)$filters['date_value'];
            $paramTypes .= 'ii';
            break;

        case 'year':
            $whereClauses[] = "YEAR(timestamp) = ?";
            $params[] = (int)$filters['date_value'];
            $paramTypes .= 'i';
            break;
    }
}

$whereSQL = $whereClauses ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

/* ===================== FETCH FEEDBACK ===================== */
$sql = "
    SELECT 
        id, name, email, ratings, team_helpful, recommend, suggestions,
        DATE_FORMAT(timestamp,'%Y-%m-%d %H:%i') AS formatted_time
    FROM feedback
    $whereSQL
    ORDER BY timestamp DESC
";

$stmt = $conn->prepare($sql);
if ($paramTypes) {
    $stmt->bind_param($paramTypes, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$feedbackEntries = $result->fetch_all(MYSQLI_ASSOC);
$totalEntries = count($feedbackEntries);

/* ===================== AGGREGATES ===================== */
function getCounts(mysqli $conn, string $whereSQL, string $paramTypes, array $params, string $column): array {
    $sql = "SELECT $column, COUNT(*) total FROM feedback $whereSQL GROUP BY $column";
    $stmt = $conn->prepare($sql);
    if ($paramTypes) {
        $stmt->bind_param($paramTypes, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $data = [];
    while ($row = $res->fetch_assoc()) {
        $data[$row[$column]] = (int)$row['total'];
    }
    return $data;
}

$recommendCounts    = getCounts($conn, $whereSQL, $paramTypes, $params, 'recommend');
$teamHelpfulCounts  = getCounts($conn, $whereSQL, $paramTypes, $params, 'team_helpful');
$ratingsCounts      = getCounts($conn, $whereSQL, $paramTypes, $params, 'ratings');

/* ===================== STATIC OPTIONS ===================== */
$recommendOptions = ['Yes', 'No', 'Maybe'];
$teamHelpfulOptions = ['Very Helpful', 'Helpful', 'Neutral', 'Not Helpful'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Telesol CRM | Customer Experience Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Customer feedback analytics dashboard">
    <meta name="keywords" content="CRM, Customer Feedback, Analytics">

    <!-- CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #2c3e50;
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
            --background: #d7d7d7;
            --purple: #00e5ffff;
            --white: #ffffff;
            --border-radius: 8px;
            --transition: 0.3s ease;
        }

        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
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
            padding: 0.6rem 0.6rem;
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

        /* Main content and header */
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
        }

        .header h1{
            font-family: inherit;
            font-size: 1.2rem;
            color: var(--primary);
            text-align: center;
        }

        .navbar-search {
            position: relative;
            flex: 1;
            max-width: 320px;
            margin-right: 1rem;
        }

        .navbar-search input[type="search"] {
            width: 100%;
            height: 23px;
            font-size: 0.9rem;
            border-radius: 3px;
            outline-offset: 2px;
            border: 1px solid var(--dark);
            padding: 0.5rem 0.5rem 0.5rem 1.9rem;
        }

        .navbar-search input::placeholder {
            opacity: 1;
            font-size: 12.5px;
            padding-top: -5px;
            color: var(--dark);
            padding-bottom: -3px;
        }

        .navbar-search svg {
            position: absolute;
            top: 50%;
            left: 0.4rem;
            idth: 14px;
            height: 14px;
            fill: var(--dark);
            pointer-events: none;
            transform: translateY(-50%);
        }

        .user-session {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-session i {
            font-size: 1.2rem;
            color: var(--dark);
        }

        /* Content Area */
        .content-area {
            padding: 1.1rem;
            padding-bottom: 5rem;
            flex: 1;
        }

        .content-area p{
            font-family: inherit;
            font-size: 0.9rem;
            color: var(--primary);
            padding-top: -5px;
            font-weight: 500;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
            margin-bottom: 2rem;

        } .stats-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: transform var(--transition),
            box-shadow var(--transition);
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .stats-card h3 {
            font-size: 0.9rem;
            text-transform: uppercase;
            color: var(--gray);
            margin-bottom: 0.75rem;
            font-weight: 600;
        }

        .stats-card .value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
            margin-bottom: 0.5rem;
        }

        .stats-card .chart-container {
            height: 120px;
            margin-top: 1rem;
        }

        /* Filter Badges */
        .filter-badges {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .filter-badge {
            display: inline-flex;
            align-items: center;
            background: linear-gradient(135deg, var(--secondary), #4a6bff);
            color: white;
            padding: 0.4rem 0.9rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .filter-badge .remove {
            margin-left: 0.5rem;
            cursor: pointer;
            opacity: 0.8;
            font-size: 1.1rem;
            line-height: 1;
        }

        .filter-badge .remove:hover {
            opacity: 1;
        }

        /* Table */
        .table-responsive {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            /* padding: 1rem; */
        }

        .table {
            margin: 0;
        }

        .table thead th {
            padding: 0.5rem;
            text-align: center;
            font-weight: 500;
            font-size: 0.8rem;
            text-transform: initial;
            color: var(--white);
            background-color: var(--primary);
        }

        .table tbody tr {
            transition: background-color var(--transition);
        }

        .table tbody tr:hover {
            background-color: var(--light-gray);
            cursor: pointer;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-color: var(--light-gray);
        }

        .rating-stars {
            color: #05649f;
            font-size: 1.1rem;
        }

        .badge {
            padding: 0.35em 0.65em;
            font-weight: 500;
        }

        /* Responsive */
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

            .menu-toggle {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--light-gray);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--gray);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }

        /* Utilities */
        .cursor-pointer {
            cursor: pointer;
        }

        .text-truncate-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Demo/Error States */
        .demo-mode {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
        } 

        .error-state {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 1rem;
            border-radius: var(--border-radius);
            text-align: center;
            margin-bottom: 1.5rem;
        }
    </style>
</head>

<body>
<!-- ===================== SIDEBAR ===================== -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h1>Telesol CRM</h1>
    </div>
    <nav class="sidebar-menu">
        <ul>
            <li><a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="ticket_mgt.php"><i class="bi bi-ticket-detailed"></i> Ticket Management</a></li>
            <li><a href="installation_mgt.php"><i class="bi bi-wrench"></i> Installations</a></li>
            <li><a href="customer_experience_dashboard.php" class="active"><i class="bi bi-people"></i> Customer Experience</a></li>
            <li><a href="report.php"><i class="bi bi-bar-chart"></i> Reports</a></li>
            <li><a href="#"><i class="bi bi-gear"></i> Settings</a></li>
            <li><a href="login.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
    </nav>
</aside>

<!-- ===================== MAIN ===================== -->
<main class="main-content">

<header class="header">
    <div>
        <h1>Customer Experience Dashboard</h1>
        <p>View and analyze customer feedback</p>
    </div>
    <div class="user-info">
        <i class="bi bi-person-circle"></i> <?= esc($username) ?>
    </div>
</header>

<div class="content-area">

    <div class="stats-grid">
        <div class="stats-card">
            <h3>Total Feedback</h3>
            <div class="value"><?= number_format($totalEntries) ?></div>
        </div>

        <div class="stats-card">
            <h3>Recommendation Rate</h3>
            <div class="chart-container">
                <canvas id="recommendChart"></canvas>
            </div>
        </div>

        <div class="stats-card">
            <h3>Customer Ratings</h3>
            <div class="chart-container">
                <canvas id="ratingsChart"></canvas>
            </div>
        </div>
    </div>
    
    <div class="d-flex align-items-right gap-3"> 
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#filterModal"> 
                <i class="bi bi-funnel me-2"></i>Filter Data 
            </button> 
            
            <?php if ($hasActiveFilters): ?> 
                <a href="customer_experience_dashboard.php" class="btn btn-outline-danger"> <i class="bi bi-x-circle me-2"></i>Clear All </a> 
                <?php endif; ?> 
        </div> 
        
        <!-- Active Filters --> 
         <?php if ($hasActiveFilters): ?> 
            <div class="filter-badges"> 
                <?php if ($filters['recommend']): 
                ?> 
                <span class="filter-badge"> Recommend: <?= esc($filters['recommend']) ?> 
                <span class="remove" onclick="removeFilter('recommend')">&times;</span> 
                </span> <?php endif; 
                ?> 
                
                <?php if ($filters['team_helpful']): ?> 
                    <span class="filter-badge"> Team Helpful: <?= esc($filters['team_helpful']) ?> 
                        <span class="remove" onclick="removeFilter('team_helpful')">&times;</span> 
                    </span> 
                <?php endif; ?> 
                    
                <?php if ($filters['date_filter'] && $filters['date_value']): ?> 
                    <span class="filter-badge"> <?= ucfirst(esc($filters['date_filter'])) ?>: <?= esc($filters['date_value']) ?> 
                    <?php if (in_array($filters['date_filter'], ['week', 'month'])): ?> (Year: <?= esc($filters['year']) ?>) 
                        <?php endif; ?> <span class="remove" onclick="removeFilter('date_filter')">&times;</span> </span> <?php endif; ?> </div> <?php endif; ?>





    <div class="table-responsive">
        <table class="table table-hover" id="feedbackTable">
            <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Rating</th>
                <th>Team Helpful</th>
                <th>Recommend</th>
                <th>Suggestions</th>
                <th>Date</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($feedbackEntries as $row): ?>
                <tr>
                    <td>#<?= esc((string)$row['id']) ?></td>
                    <td><?= esc($row['name']) ?></td>
                    <td><a href="mailto:<?= esc($row['email']) ?>"><?= esc($row['email']) ?></a></td>
                    <td><?= str_repeat('★', (int)$row['ratings']) ?></td>
                    <td><?= esc($row['team_helpful']) ?></td>
                    <td><?= esc($row['recommend']) ?></td>
                    <td><?= esc($row['suggestions']) ?></td>
                    <td><?= esc($row['formatted_time']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>
</main>

<!-- ===================== JS ===================== -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(function () {
    if ($('#feedbackTable tbody tr').length) {
        $('#feedbackTable').DataTable({
            pageLength: 25,
            order: [[0, 'desc']]
        });
    }

    <?php if ($recommendCounts): ?>
    new Chart(document.getElementById('recommendChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($recommendCounts)) ?>,
            datasets: [{ data: <?= json_encode(array_values($recommendCounts)) ?> }]
        }
    });
    <?php endif; ?>

    <?php if ($ratingsCounts): ?>
    new Chart(document.getElementById('ratingsChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_keys($ratingsCounts)) ?>,
            datasets: [{ data: <?= json_encode(array_values($ratingsCounts)) ?> }]
        }
    });
    <?php endif; ?>
});

</script>

</body>
</html>
