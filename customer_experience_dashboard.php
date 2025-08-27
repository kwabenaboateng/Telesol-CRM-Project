<?php
session_start();

$username = $_SESSION['username'] ?? 'User';

// Database connection
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "telesol crm";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

// Retrieve filters from GET
$filters = [
  'recommend' => $_GET['recommend'] ?? '',
  'team_helpful' => $_GET['team_helpful'] ?? '',
  'date_filter' => $_GET['date_filter'] ?? '',
  'date_value' => $_GET['date_value'] ?? '',
  'year' => $_GET['year'] ?? ''
];

// Build WHERE clauses dynamically
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
            $week = intval($filters['date_value']);
            $year = intval($filters['year']) ?: date('Y');
            $whereClauses[] = "YEAR(timestamp) = ? AND WEEK(timestamp, 3) = ?";
            $params[] = $year;
            $params[] = $week;
            $paramTypes .= 'ii';
            break;
        case 'month':
            $month = intval($filters['date_value']);
            $year = intval($filters['year']) ?: date('Y');
            $whereClauses[] = "YEAR(timestamp) = ? AND MONTH(timestamp) = ?";
            $params[] = $year;
            $params[] = $month;
            $paramTypes .= 'ii';
            break;
        case 'year':
            $year = intval($filters['date_value']);
            $whereClauses[] = "YEAR(timestamp) = ?";
            $params[] = $year;
            $paramTypes .= 'i';
            break;
    }
}

$whereSQL = '';
if (count($whereClauses) > 0) {
    $whereSQL = 'WHERE ' . implode(' AND ', $whereClauses);
}

// Fetch feedback entries with filters
$sql = "SELECT id, name, email, ratings, team_helpful, recommend, suggestions, timestamp FROM customer_feedback $whereSQL ORDER BY id DESC";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}

if ($paramTypes !== '') {
    $bind_names[] = $paramTypes;
    for ($i = 0; $i < count($params); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $params[$i];
        $bind_names[] = &$$bind_name;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

$stmt->execute();
$result = $stmt->get_result();
$feedbackEntries = $result->fetch_all(MYSQLI_ASSOC);
$totalEntries = count($feedbackEntries);
$stmt->close();

// Helper function to get counts for charts with filters
function getCounts($conn, $whereSQL, $paramTypes, $params, $column) {
    $sql = "SELECT `$column`, COUNT(*) as count FROM customer_feedback $whereSQL GROUP BY `$column`";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("Prepare failed: " . htmlspecialchars($conn->error));
    }
    if ($paramTypes !== '') {
        $bind_names = [];
        $bind_names[] = $paramTypes;
        for ($i = 0; $i < count($params); $i++) {
            $bind_name = 'bind' . $i;
            $$bind_name = $params[$i];
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }
    if (!$stmt->execute()) {
        die("Execute failed: " . htmlspecialchars($stmt->error));
    }
    $res = $stmt->get_result();
    $counts = [];
    while ($row = $res->fetch_assoc()) {
        $counts[$row[$column]] = (int)$row['count'];
    }
    $stmt->close();
    return $counts;
}

$recommendCounts = getCounts($conn, $whereSQL, $paramTypes, $params, 'recommend');
$teamHelpfulCounts = getCounts($conn, $whereSQL, $paramTypes, $params, 'team_helpful');
$ratingsCounts = getCounts($conn, $whereSQL, $paramTypes, $params, 'ratings');

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Telesol | Customer Feedback Dashboard</title>

<!-- Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet" />

<style>
:root {
  --primary: #4a6bff;
  --accent: #ff4a6b;
  --dark: #2c3e50;
  --light: #f8f9fa;
  --gray: #e9ecef;
  --success: #28a745;
  --error: #dc3545;
}

* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  background-color: #f8f9fa;
  color: #333;
  line-height: 1.6;
  min-height: 100vh;
  display: flex;
  flex-direction: row;
}

.sidebar {
  width: 260px;
  /* background: var(--dark); */
  background: #185e73ff;
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
  padding-bottom: 1rem;
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
  padding: 0.75rem 1rem;
  color: rgba(255, 255, 255, 0.8);
  text-decoration: none;
  border-radius: 6px;
  margin-bottom: 0.5rem;
  transition: all 0.3s ease;
}

.nav-link i {
  margin-right: 0.75rem;
  font-size: 1rem;
  color: var(--accent);
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

/* Main Content */
.main-content {
  margin-left: 270px;
  padding: 1.5rem;
  height: 100vh;
  display: flex;
  flex-direction: column;
}

/* Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1.5rem;
}

.page-title {
  font-size: 1.4rem;
  font-weight: 600;
  color: var(--dark);
  margin: 0;
  margin-top: -30px;
  margin-bottom: -25px;
}

/* Cards */
.stats-cards-row {
  display: flex;
  gap: 1rem;
  margin-top: -20px;
  margin-bottom: 1.5rem;
}

.stats-card {
  background: white;
  border-radius: 10px;
  padding: 1.5rem;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  flex: 1;
  /* height: 140px; */
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
}

.stats-card .card-title {
  font-size: 1rem;
  color: #6c757d;
  font-weight: 500;
  margin-bottom: 0.5rem;
}

.stats-card .card-value {
  font-size: 2.5rem;
  font-weight: 700;
  color: var(--primary);
}

.stats-card .card-description {
  color: #6c757d;
  font-size: 0.9rem;
  margin-top: auto;
}

/* Chart containers */
.chart-container {
  position: relative;
  width: 100%;
  height: 150px;
}

/* Table Container */
.table-container {
  flex-grow: 1;
  overflow-y: auto;
  background: white;
  border-radius: 10px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
  padding: 1rem;
  max-height: calc(100vh - 390px);
  width: 1200px;
}

/* Sticky table header */
.data-table {
  width: 100%;
  border-collapse: collapse;
}

.data-table thead th {
  position: sticky;
  top: 0;
  background-color: white;
  padding: 0.75rem;
  font-weight: 600;
  color: var(--dark);
  border-bottom: 2px solid #f1f3f9;
  z-index: 10;
}

.data-table tbody tr:hover {
  background-color: #f8f9fa;
}

.data-table tbody td {
  padding: 0.75rem;
  border-bottom: 1px solid #f1f3f9;
  vertical-align: middle;
}

/* Responsive */
@media (max-width: 992px) {
  .sidebar {
    width: 240px;
  }
  .main-content {
    margin-left: 240px;
  }
}
@media (max-width: 768px) {
  .sidebar {
    width: 100%;
    height: auto;
    position: relative;
    padding: 1rem;
  }
  .main-content {
    margin-left: 0;
    padding: 1.5rem;
    height: auto;
  }
  .table-container {
    max-height: none;
  }
}

</style>

</head>
<body>
  <div class="d-flex">
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="company-logo">
          <img src="Telesol_logo.jpeg" alt="Company Logo" />
        </div>
        <div class="company-slogan">Customer Relationship Management</div>
      </div>
      <nav class="nav-menu">
        <a href="dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'main-menu.php' ? 'active' : '' ?>">
          <i class="bi bi-list"></i> Menu
        </a>
        <a href="log_ticket.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'log_ticket.php' ? 'active' : '' ?>">
          <i class="bi bi-journal-plus"></i> Log Issue
        </a>
        <a href="log_installations.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'log_installations.php' ? 'active' : '' ?>">
          <i class="bi bi-journal-plus"></i> Log Installation
        </a>
        <a href="customer_experience_dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'customer_experience_dashboard.php' ? 'active' : '' ?>">
          <i class="bi bi-speedometer2"></i> Customer Experience
        </a>
        <a href="field_installations.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'field_installations.php' ? 'active' : '' ?>">
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

    <!-- Main Content -->
    <div class="main-content">
      <div class="page-header">
        <h1 class="page-title">Customer Feedback Dashboard</h1>
        <div>
          <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#filterModal">
            <i class="bi bi-funnel"></i> Filter
          </button>
        </div>
      </div>

      <div class="stats-cards-row">
        <div class="stats-card">
          <div class="card-title">Total Feedback Entries</div>
          <div class="card-value"><?= $totalEntries ?></div>
          <div class="card-description">All time customer feedback</div>
        </div>

        <div class="stats-card">
          <div class="card-title">Recommendation Rate</div>
          <div class="chart-container">
            <canvas id="recommendChart"></canvas>
          </div>
        </div>

        <div class="stats-card">
          <div class="card-title">Customer Ratings</div>
          <div class="chart-container">
            <canvas id="ratingsChart"></canvas>
          </div>
        </div>
      </div>

      <div class="table-container">
      <?php if ($totalEntries > 0): ?>
        <table class="data-table">
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
            <tr data-bs-toggle="modal" data-bs-target="#detailModal" 
                onclick="showDetails(
                  '<?= htmlspecialchars($row['id']) ?>',
                  '<?= htmlspecialchars($row['name']) ?>',
                  '<?= htmlspecialchars($row['email']) ?>',
                  '<?= htmlspecialchars($row['ratings']) ?>',
                  '<?= htmlspecialchars($row['team_helpful']) ?>',
                  '<?= htmlspecialchars($row['recommend']) ?>',
                  `<?= htmlspecialchars(str_replace("`", "\\`", $row['suggestions'])) ?>`,
                  '<?= htmlspecialchars($row['timestamp']) ?>'
                )">
              <td><?= htmlspecialchars($row['id']) ?></td>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><a href="mailto:<?= htmlspecialchars($row['email']) ?>"><?= htmlspecialchars($row['email']) ?></a></td>
              <td><?= htmlspecialchars($row['ratings']) ?></td>
              <td><?= htmlspecialchars($row['team_helpful']) ?></td>
              <td><?= htmlspecialchars($row['recommend']) ?></td>
              <td><?= htmlspecialchars($row['suggestions']) ?></td>
              <td><?= htmlspecialchars($row['timestamp']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="text-center py-5">
          <i class="bi bi-exclamation-circle text-muted" style="font-size: 3rem;"></i>
          <h4 class="mt-3">No feedback found</h4>
          <p class="text-muted">No feedback entries match your current filters</p>
          <a href="customer_experience.php" class="btn btn-primary mt-2">Reset Filters</a>
        </div>
      <?php endif; ?>
      </div>
    </div>
  </div>

 
  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <script>
    // Show details in modal function left the same

    // Initialize charts
    document.addEventListener('DOMContentLoaded', function() {
      // Recommendation Chart (Pie chart)
      const recommendCtx = document.getElementById('recommendChart').getContext('2d');
      new Chart(recommendCtx, {
        type: 'pie',
        data: {
          labels: <?= json_encode(array_keys($recommendCounts)) ?>,
          datasets: [{
            data: <?= json_encode(array_values($recommendCounts)) ?>,
            backgroundColor: [
              '#4a6bff',
              '#6c757d',
              '#17a2b8',
              '#28a745',
              '#ffc107'
            ],
            borderWidth: 0
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'right'
            }
          },
          cutout: '30%'
        }
      });

      // Ratings Chart (Horizontal Bar) - better visualization for categorical data
      const ratingsCtx = document.getElementById('ratingsChart').getContext('2d');
      new Chart(ratingsCtx, {
        type: 'bar',
        data: {
          labels: <?= json_encode(array_keys($ratingsCounts)) ?>,
          datasets: [{
            label: 'Number of Ratings',
            data: <?= json_encode(array_values($ratingsCounts)) ?>,
            backgroundColor: '#4a6bff',
            borderRadius: 6,
            borderWidth: 0
          }]
        },
        options: {
          indexAxis: 'y',  // horizontal bar
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false }
          },
          scales: {
            x: { beginAtZero: true, ticks: { precision: 0 } }
          }
        }
      });
    });
  </script>
</body>
</html>
