<?php
session_start();

$username = $_SESSION['username'] ?? 'User';

// DB connection - same as above
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "telesol crm";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

$department = 'Technology';

// Fetch counts for stats
function fetchCount(mysqli $conn, string $sql): int {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count ?? 0;
}

$totalTickets = fetchCount($conn, "SELECT COUNT(*) FROM tickets WHERE assigned_department = ?");
$resolvedTickets = fetchCount($conn, "SELECT COUNT(*) FROM tickets WHERE assigned_department = ? AND issue_status = 'Resolved'");
$pendingTickets = fetchCount($conn, "SELECT COUNT(*) FROM tickets WHERE assigned_department = ? AND (issue_status != 'Resolved' OR issue_status IS NULL)");


// Fetch tickets escalated to Technology department (assuming is_escalated flag exists)
$escalatedTicketsSql = "SELECT id, customer_name, issue_type, assigned_persons, scheduled_datetime, issue_status, resolved_by_deadline FROM tickets WHERE assigned_department = ? AND is_escalated = 1 ORDER BY created_at DESC";
$stmt = $conn->prepare($escalatedTicketsSql);
$stmt->bind_param('s', $department);
$stmt->execute();
$escalatedTickets = $stmt->get_result();

function esc($str) {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Check overdue helper reused
function isOverdueTech=array($row) use (){
    if (strtolower($row['issue_status']) === 'resolved') return false;
    if (empty($row['resolved_by_deadline']) || $row['resolved_by_deadline'] === '0000-00-00 00:00:00') return false;
    return strtotime($row['resolved_by_deadline']) < time();
};
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
<meta charset="UTF-8" />
<title>Technology Department Dashboard - Telesol CRM</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" />
<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 2rem; background: #f5f7fa; color: #333; }
    h1 { margin-bottom: 1rem; }
    .stats { display: flex; gap: 2rem; margin-bottom: 2rem; }
    .stat-card { background: white; padding: 1.5rem 2rem; border-radius: 12px; box-shadow: 0 4px 20px rgb(0 0 0 / 0.05); flex: 1; text-align: center; }
    .stat-card .number { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; }
    .stat-card .label { font-weight: 600; color: #555; }
    table { width: 100%; border-collapse: collapse; box-shadow: 0 0 20px rgb(0 0 0 / 0.05); background: white; border-radius: 12px; overflow: hidden; }
    thead { background: #0a234b; color: white; font-weight: 600; }
    th, td { padding: 0.85rem 1rem; text-align: left; font-size: 0.9rem; }
    tbody tr:nth-child(even) { background: #f3f6f8; }
    tbody tr.overdue { background: #ffe5e5 !important; border-left: 5px solid #dc3545;}
    tbody tr:hover { background: #dae9f7; cursor: pointer; }
    .status-icon { font-size: 1.4rem; vertical-align: middle; }
    .resolved { color: #28a745; }
    .unresolved { color: #dc3545; }
</style>
</head>
<body>

<h1>Technology Department Dashboard</h1>

<div class="stats" role="region" aria-label="Department ticket statistics">
    <div class="stat-card">
        <div class="number"><?= $totalTickets ?></div>
        <div class="label">Total Tickets Assigned</div>
    </div>
    <div class="stat-card">
        <div class="number"><?= $pendingTickets ?></div>
        <div class="label">Pending Tickets</div>
    </div>
    <div class="stat-card">
        <div class="number"><?= $resolvedTickets ?></div>
        <div class="label">Resolved Tickets</div>
    </div>
</div>

<h2>Escalated Issues</h2>
<?php if ($escalatedTickets->num_rows === 0): ?>
    <p>No escalated issues for Technology department.</p>
<?php else: ?>
<table role="table" aria-label="Escalated tickets to Technology department">
    <thead>
        <tr>
            <th scope="col">#</th>
            <th scope="col">Customer Name</th>
            <th scope="col">Issue Type</th>
            <th scope="col">Assigned Persons</th>
            <th scope="col">Scheduled Date</th>
            <th scope="col">Resolve By</th>
            <th scope="col">Status</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $idx = 1;
        while ($row = $escalatedTickets->fetch_assoc()): 
            $isResolved = strtolower($row['issue_status']) === 'resolved';
            $scheduled = ($row['scheduled_datetime'] && $row['scheduled_datetime'] !== '0000-00-00 00:00:00') ? date('Y-m-d H:i', strtotime($row['scheduled_datetime'])) : '–';
            $resolveBy = ($row['resolved_by_deadline'] && $row['resolved_by_deadline'] !== '0000-00-00 00:00:00') ? date('Y-m-d H:i', strtotime($row['resolved_by_deadline'])) : '–';
            $isOverdue = isOverdueTech($row);
        ?>
            <tr class="<?= $isOverdue ? 'overdue' : '' ?>">
                <th scope="row"><?= $idx++ ?></th>
                <td><?= esc($row['customer_name']) ?></td>
                <td><?= esc($row['issue_type']) ?></td>
                <td><?= esc($row['assigned_persons']) ?></td>
                <td><?= esc($scheduled) ?></td>
                <td><?= esc($resolveBy) ?></td>
                <td title="<?= $isResolved ? 'Resolved' : 'Not Resolved' ?>" aria-label="Status: <?= $isResolved ? 'Resolved' : 'Not Resolved' ?>">
                    <?php if ($isResolved): ?>
                        <i class="bi bi-check-circle-fill status-icon resolved"></i>
                    <?php else: ?>
                        <i class="bi bi-exclamation-circle-fill status-icon unresolved"></i>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php endif; ?>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
