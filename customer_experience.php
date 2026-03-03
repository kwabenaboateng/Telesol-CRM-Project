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

$conn = new mysqli(
    $dbConfig['host'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['database']
);
$conn->set_charset('utf8mb4');

/* ===================== HELPERS ===================== */
function esc(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

/* ===================== FETCH DATA ===================== */
$sql = "
    SELECT 
        id, name, email, ratings, team_helpful, recommend, suggestions,
        DATE_FORMAT(timestamp,'%Y-%m-%d %H:%i') AS formatted_time
    FROM feedback
    ORDER BY timestamp DESC
";
$result = $conn->query($sql);
$rows = $result->fetch_all(MYSQLI_ASSOC);
$totalEntries = count($rows);

/* ===================== CHART DATA ===================== */
$recommendCounts = [];
$ratingsCounts   = [];

foreach ($rows as $r) {
    $recommendCounts[$r['recommend']] = ($recommendCounts[$r['recommend']] ?? 0) + 1;
    $ratingsCounts[$r['ratings']]     = ($ratingsCounts[$r['ratings']] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Telesol CRM | Customer Experience</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
</head>

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
        font-family:Inter,system-ui,sans-serif;
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
        padding: 0.55rem 1rem;
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
        width: 14px;
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
        transition: transform var(--transition), box-shadow var(--transition);
        height: 100%;
    }

    .stats-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-hover);
    }

    .stats-card h3 {
        font-size: 0.9rem;
        text-transform: uppercase;
        color: var(--primary);
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
        height: 80px;
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

    .table-container {
        margin-top: -0.3rem;
        margin-left: 0.6rem;
        margin-right: 0.6rem;
        background-color: var(--light-gray);
        border-radius: 3px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.05);
        overflow-x: auto;
        height: 460px;
    }

    .table-wrapper {
        overflow-x: auto;
        max-height: 500px;
    }

    table {
        width: 100%;
        height: 100%;
        font-weight: 400;
        font-size: 0.9rem;
        color: var(--dark);
        border-collapse: collapse;
        min-width: 1000px;
    }

    thead {
        position: sticky;
        top: 0;
        z-index: 10;
    }

    thead tr {
        background-color: var(--primary);
        color: white;
        font-size: 0.9rem;
        font-weight: 500;
        position: sticky;
        top: 0;
        z-index: 10;
    }

    th {
        padding: 0.6rem;
        font-weight: 500;
        font-size: 0.9rem;
        text-align: center;
        letter-spacing: 0.1px;
        text-transform: initial;
        background-color: var(--primary);
    }

    tbody tr {
        border-bottom: 1px solid var(--light-gray);
        transition: background-color var(--transition);
    }

    tbody tr:hover {
        background-color: #f8f9fa;
    }

    td {
        padding: 0.25rem;
        vertical-align: middle;
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
    /* .demo-mode {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.75rem;
        border-radius: var(--border-radius);
        margin-bottom: 1.5rem;
    }  */

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
    <!-- Sidebar -->
    <aside class="sidebar" aria-label="Main navigation">
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
                <li><a href="#"><i class="bi bi-arrow-left-circle"></i> Back</a></li>
                <li><a href="login.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content">
        <header class="header">
            <h5 class="mb-0">Customer Experience Dashboard</h5>

            <div class="user-session">
                <i class="bi bi-person-circle"></i>
                    <span>Logged in as: <strong><?= esc($username) ?></strong></span>
            </div>
        </header>

        <div class="container-fluid py-3">

            <!-- FILTER -->
            <div class="d-flex justify-content-end mb-2">
                <select id="chartFilter" class="form-select form-select-sm w-auto">
                    <option value="all">All Feedback</option>
                    <option value="Yes">Recommended</option>
                    <option value="No">Not Recommended</option>
                    <option value="Maybe">Maybe</option>
                </select>
            </div>

            <!-- STATS -->
            <div class="stats-grid">
                <div class="stats-card">
                    <h3>Total Feedback</h3>
                    <div class="value"><?= number_format($totalEntries) ?></div>
                </div>

                <div class="stats-card">
                    <h3>Recommendation Rate</h3>
                        <canvas id="recommendChart" height="80"></canvas>
                </div>

                <div class="stats-card">
                    <h3>Customer Ratings</h3>
                        <canvas id="ratingsChart" height="120"></canvas>
                </div>
            </div>

            <!-- TABLE -->
            <div class="table-container">
                <div class="table-responsive">
                    <table id="feedbackTable" class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th class="text-left">Name</th>
                                <th>Email</th>
                                <th>Rating</th>
                                <th>Team Helpful</th>
                                <th>Recommend</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($rows as $r): ?>
                            <tr
                                data-name="<?= esc($r['name']) ?>"
                                data-email="<?= esc($r['email']) ?>"
                                data-rating="<?= esc((string)$r['ratings']) ?>"
                                data-team="<?= esc($r['team_helpful']) ?>"
                                data-recommend="<?= esc($r['recommend']) ?>"
                                data-date="<?= esc($r['formatted_time']) ?>"
                                data-suggestions="<?= esc($r['suggestions']) ?>"
                                >

                                <td>#<?= esc((string)$r['id']) ?></td>
                                <td class="text-left"><?= esc($r['name']) ?></td>
                                <td class="text-left"><?= esc($r['email']) ?></td>
                                <td class="rating-stars"><?= str_repeat('★', (int)$r['ratings']) ?></td>
                                <td><?= esc($r['team_helpful']) ?></td>
                                <td><?= esc($r['recommend']) ?></td>
                                <td><?= esc($r['formatted_time']) ?></td>
                                <td>
                                    <i class="bi bi-eye-fill action-btn view-btn" title="View details"></i>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <!-- MODAL -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Customer Feedback Details</h6>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody"></div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
$(function () {

    $('.view-btn').on('click', function () {
        const d = $(this).closest('tr').data();
        $('#modalBody').html(`
            <div class="row g-3">
                <div class="col-md-4"><strong>Name:</strong> ${d.name}</div>
                <div class="col-md-2"><strong>Email:</strong> ${d.email}</div>
                <div class="col-md-2"><strong>Rating:</strong> ${'★'.repeat(d.rating)}</div>
                <div class="col-md-4"><strong>Team Helpful:</strong> ${d.team}</div>
                <div class="col-md-4"><strong>Recommend:</strong> ${d.recommend}</div>
                <div class="col-12"><strong>Date:</strong> ${d.date}</div>
                <div class="col-12 mt-2">
                    <strong>Suggestions:</strong>
                    <div class="border rounded p-2 bg-light">${d.suggestions || '—'}</div>
                </div>
            </div>
        `);
        new bootstrap.Modal('#detailModal').show();
    });

    new Chart(document.getElementById('recommendChart'), {
        type:'doughnut',
        data:{labels:Object.keys(<?= json_encode($recommendCounts) ?>),
              datasets:[{data:Object.values(<?= json_encode($recommendCounts) ?>)}]}
    });

    new Chart(document.getElementById('ratingsChart'), {
        type:'bar',
        data:{labels:Object.keys(<?= json_encode($ratingsCounts) ?>),
              datasets:[{data:Object.values(<?= json_encode($ratingsCounts) ?>)}]}
    });
});
</script>

</body>
</html>























