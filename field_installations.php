<?php
session_start();
$username = $_SESSION['username'] ?? 'User';

// Database connection info
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'CRM');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

// Secure count retrieval by validating input column name
function getCounts($conn, string $column): array {
    $allowedColumns = ['feedback_type', 'rate_technician', 'satisfaction'];
    if (!in_array($column, $allowedColumns)) return [];

    $stmt = $conn->prepare("SELECT `$column`, COUNT(*) as count FROM entries GROUP BY `$column`");
    $stmt->execute();
    $result = $stmt->get_result();
    $counts = [];
    while ($row = $result->fetch_assoc()) {
        $counts[$row[$column]] = (int)$row['count'];
    }
    $stmt->close();
    return $counts;
}

// Total entries count
$totalEntries = 0;
if ($res = $conn->query("SELECT COUNT(*) AS total FROM entries")) {
    $totalEntries = (int)$res->fetch_assoc()['total'];
    $res->close();
}

$typeDist = getCounts($conn, 'feedback_type');
$profDist = getCounts($conn, 'rate_technician');
$satDist = getCounts($conn, 'satisfaction');

// Recent entries with limit (most recent 15)
$entries = [];
if ($res = $conn->query("SELECT id, feedback_type, satisfaction, rate_technician, timestamp FROM entries ORDER BY timestamp DESC LIMIT 15")) {
    $entries = $res->fetch_all(MYSQLI_ASSOC);
    $res->close();
}

// Time-based data for trends (last 7 days)
$timeData = [];
if ($res = $conn->query("SELECT DATE(timestamp) as day, COUNT(*) as count FROM entries GROUP BY DATE(timestamp) ORDER BY day DESC LIMIT 7")) {
    $timeData = $res->fetch_all(MYSQLI_ASSOC);
    $res->close();
}

$conn->close();

$feedbackTypes = [
    'type1' => 'Installation',
    'type2' => 'Maintenance'
];

// Prepare charts labels and data for JS
$timeLabels = array_reverse(array_column($timeData, 'day'));
$timeValues = array_reverse(array_column($timeData, 'count'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Telesol Feedback Analytics | Dashboard</title>

    <!-- Favicon -->
    <link rel="icon" href="Telesol_logo.jpeg" type="image/jpeg" />

    <!-- Fonts: Inter for readability -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Bootstrap CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet" />

    <!-- ApexCharts JS library -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <style>
        :root {
            --bg: #818fa4ff;
            --background: #f0f0f0ff;
            --deep-bg: #425779ff;
            --white: #ffffff;
            --gray: #e9ecef;
            --sidebar: #2c4b61ff;
            --dark: #395b74ff;
            --subtitle: #364253ff;
            --border-line: #cccccc;
            --deep-blue: #0a234bff;
            --success: #28a745;
            --error: #dc3545;
            --light-green: #37ad13ff;
            /* --light-green: #3ad809ff; */
        }

         /* Reset */
    *,*::before,*::after {
        box-sizing: border-box;
    }
    
    body {
        margin: 0; 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: #333;
        min-height: 100vh;
        display: flex;
        flex-direction: row;
        user-select: text;
    }

    /* Sidebar */
    .sidebar {
        width: 260px;
        background-color: var(--sidebar);
        font-size: 16px;
        font-weight: 500;
        font-family: inherit;
        color: white;
        padding: 1rem;
        height: 100vh;
        position: fixed;
        display: flex;
        flex-direction: column;
        box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
        z-index: 100;
    }

    .sidebar-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 1rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .company-logo {
        width: 140px;
        height: 70px;
        background: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.1rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .company-logo img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    .company-slogan {
        font-size: 1rem;
        color: rgba(255, 255, 255, 0.7);
        margin-top: 0.25rem;
        text-align: center;
    }

    .nav-menu {
        flex-grow: 1;
    }

    .nav-link {
        display: flex;
        align-items: center;
        padding: 0.5rem 1rem;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        border-radius: 6px;
        margin-bottom: 0.5rem;
        transition: all 0.3s ease;
    }

    .nav-link i {
        margin-right: 0.75rem;
        font-size: 1.3rem;
        color: var(--light-green);
        margin-bottom: 0.010rem;
    }

    .nav-link:hover,
    .nav-link.active {
        background-color: rgba(255, 255, 255, 0.1);
        color: white;
    }

    .nav-link.active {
        font-weight: 600;
    }

    .sidebar-footer {
        margin-top: auto;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.75rem 1.5rem;
        border-radius: 6px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
    }

    .btn-back {
        background: white;
        color: var(--dark);
        width: 100%;
        margin-bottom: 1rem;
    }

    .btn-back:hover {
        background: var(--gray);
    }

    .btn-logout {
        background: var(--error);
        color: white;
        width: 100%;
    }

    .btn-logout:hover {
        background: #c82333;
    }

    .main-content {
        margin-left: 240px;
        padding: 2.5rem 2rem;
        flex: 1;
        transition: margin 0.3s;
        background-color: var(--background);
    }

    .main-content-header h1{
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--dark);
        margin-top: -20px;
        margin-bottom: 1rem;
        margin-left: 25px;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2.5rem;
        gap: 1rem;
        flex-wrap: wrap;
    }

        .page-title {
            font-weight: 700;
            font-size: 1.8rem;
            color: var(--dark);
            white-space: nowrap;
        }

        .btn-primary {
            background-color: var(--primary);
            border: none;
        }

        .btn-primary:hover {
            background-color: #3954d1;
        }

        /* Stats Cards */
        .stats-card {
            background: #fff;
            border-radius: 14px;
            box-shadow: var(--card-shadow);
            padding: 1.6rem 1.8rem;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            cursor: default;
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            border-left: 5px solid var(--primary);
            user-select: none;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 30px rgba(0, 0, 0, 0.1);
        }

        .stats-card .icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 1rem;
            background: rgba(74, 107, 255, 0.15);
            padding: 0.5rem;
            border-radius: 8px;
        }

        .stats-card .title {
            font-size: 0.95rem;
            color: var(--gray);
            margin-bottom: 0.4rem;
        }

        .stats-card .value {
            font-weight: 700;
            font-size: 2rem;
            color: var(--dark);
            margin-bottom: 0.4rem;
        }

        .trend {
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 600;
        }

        .trend.up {
            color: var(--success);
        }

        .trend.down {
            color: var(--error);
        }

        /* Chart Cards */
        .chart-card {
            background: #fff;
            border-radius: 14px;
            padding: 1.5rem 1.8rem;
            box-shadow: var(--card-shadow);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
            flex-wrap: wrap;
        }

        .chart-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--dark);
            flex-grow: 1;
        }

        .dropdown-toggle {
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            padding: 0.4rem 0.9rem;
            border-radius: 6px;
        }

        /* Data Table */
        .data-table {
            background: #fff;
            border-radius: 14px;
            box-shadow: var(--card-shadow);
            overflow-x: auto !important;
            user-select: text;
        }

        .data-table table {
            border-collapse: separate !important;
            border-spacing: 0 12px !important;
            width: 100%;
            font-size: 0.95rem;
        }

        .data-table thead th {
            background-color: var(--primary) !important;
            color: #fff !important;
            padding: 1rem 1.2rem !important;
            border: none !important;
            border-radius: 12px 12px 0 0;
            text-align: left;
            user-select: none;
        }

        .data-table tbody tr {
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-radius: 10px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .data-table tbody tr:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.12);
            background-color: #f0f4ff;
            cursor: pointer;
        }

        .data-table tbody td {
            padding: 1rem 1.2rem !important;
            border: none !important;
            vertical-align: middle;
            color: var(--dark);
            font-weight: 600;
            user-select: text;
        }

        /* Zebra Striping */
        .data-table tbody tr:nth-child(even) {
            background-color: #f9fbff;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                position: fixed;
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            main.main-content {
                margin-left: 0;
                padding: 1.5rem 1rem;
            }
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 7px;
            height: 7px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-light);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }
    </style>
</head>

<body>
    <div class="d-flex">
    <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="company-logo">
                    <img src="/images/logo/Telesol_logo.jpeg" alt="Company Logo" />
                </div>
                <div class="company-slogan">Customer Relationship Management</div>
            </div>

            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'main-menu.php' ? 'active' : '' ?>">
                    <i class="bi bi-list"></i> Menu
                </a>
                <a href="log_ticket.php" class="nav-link">
                    <i class="bi bi-journal-plus"></i> Log Ticket
                </a>
                <a href="view_tickets.php" class="nav-link">
                    <i class="bi bi-hdd-network"></i> View Tickets
                </a>
                <a href="log_installations.php" class="nav-link">
                    <i class="bi bi-journal-plus"></i> Log Installation
                </a>
                <a href="view_installations.php" class="nav-link">
                    <i class="bi bi-hdd-network"></i> View Installations
                </a>
                <a href="customer_experience_dashboard.php" class="nav-link active">
                    <i class="bi bi-speedometer2"></i> Customer Experience
                </a>
                <a href="field_installations.php" class="nav-link">
                    <i class="bi bi-hdd-network"></i> Field Installations
                </a>
            </nav>

            <div class="sidebar-footer">
                <button class="btn btn-back" onclick="window.history.back()">
                    <i class="bi bi-arrow-left"></i> Back
                </button>
                <form action="logout.php" method="POST">
                    <button type="submit" class="btn btn-logout">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </button>
                </form>
            </div>
        </aside>
    </div>

    <!-- Main Content -->
    <main class="main-content" role="main" aria-labelledby="pageTitle">
        <section class="page-header d-flex flex-wrap justify-content-between align-items-center mb-5">
            <h1 id="pageTitle" class="page-title">Feedback Analytics Dashboard</h1>
            <button type="button" class="btn btn-primary d-flex align-items-center gap-2" aria-label="Export feedback analytics data">
                <i class="ri-download-line"></i> Export
            </button>
        </section>

        <!-- Stats Overview -->
        <section class="row g-4 mb-5">
            <article class="col-md-6 col-lg-3">
                <div class="stats-card" aria-label="Total feedback received">
                    <div class="icon"><i class="ri-feedback-line" aria-hidden="true"></i></div>
                    <h3 class="title">Total Feedback</h3>
                    <div class="value"><?= number_format($totalEntries) ?></div>
                    <div class="trend up" aria-live="polite">
                        <i class="ri-arrow-up-line"></i> 12% from last week
                    </div>
                </div>
            </article>

            <article class="col-md-6 col-lg-3">
                <div class="stats-card" aria-label="Average customer satisfaction rating">
                    <div class="icon"><i class="ri-star-line" aria-hidden="true"></i></div>
                    <h3 class="title">Avg. Satisfaction</h3>
                    <div class="value">4.2<span class="text-muted" style="font-size: 1rem;">/5</span></div>
                    <div class="trend up" aria-live="polite">
                        <i class="ri-arrow-up-line"></i> 8% from last week
                    </div>
                </div>
            </article>

            <article class="col-md-6 col-lg-3">
                <div class="stats-card" aria-label="Average response time">
                    <div class="icon"><i class="ri-time-line" aria-hidden="true"></i></div>
                    <h3 class="title">Avg. Response Time</h3>
                    <div class="value">2.4<span class="text-muted" style="font-size: 1rem;">hrs</span></div>
                    <div class="trend down" aria-live="polite">
                        <i class="ri-arrow-down-line"></i> 3% from last week
                    </div>
                </div>
            </article>
        </section>

        <!-- Charts Row -->
        <section class="row g-4 mb-5">
            <section class="col-lg-8">
                <div class="chart-card" aria-label="Feedback trends in the last 7 days">
                    <header class="chart-header">
                        <h3 class="chart-title">Feedback Trends (Last 7 Days)</h3>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="filterTimeDropdown" data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true" aria-controls="timeFilterMenu">
                                This Week
                            </button>
                            <ul class="dropdown-menu" id="timeFilterMenu" aria-labelledby="filterTimeDropdown">
                                <li><a class="dropdown-item" href="#">This Week</a></li>
                                <li><a class="dropdown-item" href="#">Last Week</a></li>
                                <li><a class="dropdown-item" href="#">This Month</a></li>
                            </ul>
                        </div>
                    </header>
                    <div id="trendChart" style="height: 200px;" role="img" aria-label="Line chart showing feedback count over the last 7 days"></div>
                </div>
            </section>

            <section class="col-lg-4">
                <div class="chart-card" aria-label="Feedback satisfaction distribution">
                    <header class="chart-header">
                        <h3 class="chart-title">Feedback Distribution</h3>
                    </header>
                    <div id="distributionChart" style="height: 200px;" role="img" aria-label="Donut chart showing feedback satisfaction distribution"></div>
                </div>
            </section>
        </section>

        <!-- Feedback Breakdown -->
        <section class="row g-4 mb-5">
            <section class="col-md-6">
                <div class="chart-card" aria-label="Breakdown of feedback types">
                    <header class="chart-header">
                        <h3 class="chart-title">Feedback Types</h3>
                    </header>
                    <div id="typeChart" style="height: 150px;" role="img" aria-label="Horizontal bar chart showing types of feedback"></div>
                </div>
            </section>

            <section class="col-md-6">
                <div class="chart-card" aria-label="Ratings of technician professionalism">
                    <header class="chart-header">
                        <h3 class="chart-title">Professionalism Ratings</h3>
                    </header>
                    <div id="profChart" style="height: 150px;" role="img" aria-label="Radial bar chart showing professionalism ratings"></div>
                </div>
            </section>
        </section>

        <!-- Recent Feedback Entries -->
        <section class="chart-card" aria-labelledby="recentFeedbackTitle">
            <header class="chart-header mb-3 d-flex justify-content-between align-items-center">
                <h3 id="recentFeedbackTitle" class="chart-title">Recent Feedback Entries</h3>
                <a href="#" class="btn btn-sm btn-outline-primary" aria-label="View all feedback entries">View All</a>
            </header>

            <div class="data-table table-responsive">
                <table class="table table-hover" aria-describedby="recentFeedbackTitle">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Type</th>
                            <th scope="col">Satisfaction</th>
                            <th scope="col">Professionalism</th>
                            <th scope="col">Date</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($entries)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 fs-5 text-muted">No feedback entries found</td>
                        </tr>
                        <?php else: foreach ($entries as $entry): ?>
                        <tr>
                            <td>#<?= htmlspecialchars($entry['id']) ?></td>
                            <td>
                                <span class="badge badge-primary" title="<?= htmlspecialchars($feedbackTypes[$entry['feedback_type']] ?? $entry['feedback_type']) ?>">
                                    <?= htmlspecialchars($feedbackTypes[$entry['feedback_type']] ?? $entry['feedback_type']) ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $satisfaction = htmlspecialchars($entry['satisfaction']);
                                if (in_array($satisfaction, ['Excellent', 'Good'])) :
                                ?>
                                <span class="badge badge-success"><?= $satisfaction ?></span>
                                <?php else: ?>
                                <span class="badge badge-warning"><?= $satisfaction ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($entry['rate_technician']) ?></td>
                            <td><?= date('M j, Y', strtotime($entry['timestamp'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-secondary" aria-label="View details of feedback #<?= htmlspecialchars($entry['id']) ?>" title="View">
                                    <i class="ri-eye-line" aria-hidden="true"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Bootstrap JS bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- ApexCharts JS -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const rootStyles = getComputedStyle(document.documentElement);
            const primaryColor = rootStyles.getPropertyValue('--primary').trim();
            const grayColor = rootStyles.getPropertyValue('--gray').trim();
            const darkColor = rootStyles.getPropertyValue('--dark').trim();

            // Feedback Trend Chart (Area)
            const trendOptions = {
                series: [{ name: 'Feedback Count', data: <?= json_encode($timeValues) ?> }],
                chart: { type: 'area', height: '100%', toolbar: { show: false }, zoom: { enabled: false } },
                colors: [primaryColor],
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 3 },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.75, opacityTo: 0.3, stops: [0, 90, 100]} },
                xaxis: { categories: <?= json_encode($timeLabels) ?>, labels: { style: { colors: grayColor } } },
                yaxis: { labels: { style: { colors: grayColor } } },
                tooltip: { y: { formatter: val => `${val} feedback${val !== 1 ? 's' : ''}`} }
            };
            new ApexCharts(document.querySelector("#trendChart"), trendOptions).render();

            // Distribution Chart
            const distributionOptions = {
                series: <?= json_encode(array_values($satDist)) ?>,
                chart: { type: 'donut', height: '100%' },
                labels: <?= json_encode(array_keys($satDist)) ?>,
                colors: ['#4361ee', '#3f37c9', '#4cc9f0', '#f72585', '#7209b7'],
                legend: { position: 'bottom' },
                plotOptions: { pie: { donut: { size: '65%', labels: { show: true, total: { show: true, label: 'Total', color: darkColor } } } } },
                dataLabels: { enabled: false }
            };
            new ApexCharts(document.querySelector("#distributionChart"), distributionOptions).render();

            // Feedback Types Bar Chart
            const typeOptions = {
                series: [{ name: 'Count', data: <?= json_encode(array_values($typeDist)) ?> }],
                chart: { type: 'bar', height: '100%', toolbar: { show: false } },
                plotOptions: { bar: { borderRadius: 6, horizontal: true } },
                dataLabels: { enabled: false },
                colors: [primaryColor],
                xaxis: { categories: <?= json_encode(array_values($feedbackTypes)) ?>, labels: { style: { colors: grayColor } } },
                yaxis: { labels: { style: { colors: grayColor } } }
            };
            new ApexCharts(document.querySelector("#typeChart"), typeOptions).render();

            // Professionalism Ratings Radial Bar Chart
            const profOptions = {
                series: <?= json_encode(array_values($profDist)) ?>,
                chart: { type: 'radialBar', height: '100%' },
                plotOptions: {
                    radialBar: {
                        dataLabels: {
                            name: { fontSize: '14px', color: darkColor },
                            value: { fontSize: '20px', color: darkColor, fontWeight: 700 },
                            total: { show: true, label: 'Average', formatter: () => '4.5/5' }
                        }
                    }
                },
                labels: <?= json_encode(array_keys($profDist)) ?>,
                colors: ['#4361ee', '#3f37c9', '#4cc9f0', '#f72585']
            };
            new ApexCharts(document.querySelector("#profChart"), profOptions).render();
        });
    </script>
</body>

</html>
