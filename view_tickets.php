<?php
session_start();

$username = $_SESSION['username'] ?? 'User';

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "telesol crm";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

$engineers = ['John Hagan', 'Isaac Ofosu-Afful', 'Sylvester Horsu', 'Innocent Odikro', 'Joshua Avinu'];

// AJAX: Assign/Schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'assign_schedule') {
    header('Content-Type: application/json');
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    $issue_id = $_POST['issue_id'] ?? null;
    $engineer = trim($_POST['engineer'] ?? '');
    $scheduled_datetime = $_POST['scheduled_datetime'] ?? null;

    if (!$issue_id || !is_numeric($issue_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid issue ID.']);
        exit;
    }
    if ($engineer === '' || !in_array($engineer, $engineers, true)) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid engineer.']);
        exit;
    }
    if (!$scheduled_datetime || !strtotime($scheduled_datetime)) {
        echo json_encode(['success' => false, 'message' => 'Invalid scheduled date/time.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE tickets SET assigned_engineer = ?, scheduled_datetime = ? WHERE id = ?");
    $stmt->bind_param('ssi', $engineer, $scheduled_datetime, $issue_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Engineer and schedule updated.',
            'assigned_engineer' => $engineer,
            'scheduled_datetime' => $scheduled_datetime
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update assignment.']);
    }
    $stmt->close();
    $conn->close();
    exit;
}

// AJAX: Toggle status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'toggle_status') {
    header('Content-Type: application/json');
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    $issue_id = $_POST['issue_id'] ?? null;
    $new_status = $_POST['new_status'] ?? '';
    if (!$issue_id || !is_numeric($issue_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid issue ID.']);
        exit;
    }
    if (!in_array($new_status, ['Resolved', 'Unresolved'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
        exit;
    }
    $stmt = $conn->prepare("UPDATE tickets SET issue_status = ? WHERE id = ?");
    $stmt->bind_param('si', $new_status, $issue_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Status updated.', 'new_status' => $new_status]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
    }
    $stmt->close();
    $conn->close();
    exit;
}

// Filters and Pagination
$valid_filters = ['all', 'pending', 'assigned', 'resolved'];
$filter = strtolower($_GET['filter'] ?? 'all');
if (!in_array($filter, $valid_filters, true)) $filter = 'all';

$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where_clauses = [];
switch ($filter) {
    case 'pending':
        $where_clauses[] = "(assigned_engineer IS NULL OR assigned_engineer = '')";
        $where_clauses[] = "(scheduled_datetime IS NULL OR scheduled_datetime = '0000-00-00 00:00:00')";
        $where_clauses[] = "(issue_status IS NULL OR issue_status != 'Resolved')";
        break;
    case 'assigned':
        $where_clauses[] = "((assigned_engineer IS NOT NULL AND assigned_engineer != '') OR (scheduled_datetime IS NOT NULL AND scheduled_datetime != '0000-00-00 00:00:00'))";
        $where_clauses[] = "(issue_status IS NULL OR issue_status != 'Resolved')";
        break;
    case 'resolved':
        $where_clauses[] = "issue_status = 'Resolved'";
        break;
    case 'all':
    default:
        break;
}
$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$count_sql = "SELECT COUNT(*) FROM tickets $where_sql";
$stmt_count = $conn->prepare($count_sql);
$stmt_count->execute();
$stmt_count->bind_result($total_results);
$stmt_count->fetch();
$stmt_count->close();

$data_sql = "SELECT id, customer_name, contact_number, email, location, issue_type, scheduled_datetime, assigned_engineer, issue_status, comments, created_at
             FROM tickets $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";

$stmt = $conn->prepare($data_sql);
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$total_pages = $total_results > 0 ? ceil($total_results / $limit) : 1;

function esc($str) {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>View Tickets</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" />
<style>
:root {
  --primary: #4a6bff;
  --accent: #ff4a6b;
  --dark: #2c3e50;
  --light: #f8f9fa;
  --gray: #e9ecef;
  --success: #28a745;
  --error: #dc3545;
  --warning: #ffc107;
}
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: var(--light);
    color: #333;
    min-height: 100vh;
    display: flex;
    margin: 0;
}

/* === SIDEBAR - DO NOT EDIT === */
.sidebar {
    width: 240px;
    background: var(--dark);
    color: white;
    padding: 1rem;
    height: 100vh;
    position: fixed;
    display: flex;
    flex-direction: column;
    box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
}

.sidebar-header {
    margin-bottom: 1rem;
    text-align: center;
}

.sidebar-header img {
    max-width: 140px;
    border-radius: 50%;
}

.sidebar-slogan {
    color: rgba(255, 255, 255, 0.7);
    margin-top: 0.25rem;
    font-size: 1rem;
}

.nav-menu {
    flex-grow: 1;
}

.nav-link {
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    padding: 0.7rem 1rem;
    display: block;
    border-radius: 6px;
    margin-bottom: 0.5rem;
    transition: background-color 0.3s, color 0.3s;
}

.nav-link:hover,
.nav-link.active {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    font-weight: 600;
}

.sidebar-footer {
    margin-top: auto;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1.1rem;
    width: 100%;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: background 0.3s, color 0.3s;
}

.btn-back {
    background: white;
    color: var(--dark);
     margin-bottom: 1rem;
}

.btn-back:hover {
    background: var(--gray);
}

.btn-logout {
    background: var(--error);
    color: white;
}

.btn-logout:hover {
    background: #c82333;
}

.main-content { 
    margin-left: 260px; 
    padding: 2rem 2rem; 
    overflow-x: auto; 
    min-height: 100vh; 
    background: var(--light);
}
h1 { 
    color: var(--dark); 
    margin-top: -0.9rem;
    margin-bottom: 1.5rem; 
    font-weight: 700; 
    font-size: 1.6rem;
}
.filters { 
    display: flex; 
    gap: 1rem; 
    margin-bottom: 1.5rem;
}
.filters a { 
    padding: 0.6rem 1.2rem; 
    border-radius: 6px; 
    text-decoration: none; 
    font-weight: 600; 
    border: 2px solid var(--primary); 
    color: var(--primary); 
    transition: background 0.3s, color 0.3s;
}
.filters a.active, 
.filters a:hover { 
    background: var(--primary); 
    color: #fff;
}
.table-wrapper { 
    overflow-x: auto; 
    background: white; 
    border-radius: 12px; 
    box-shadow: 0 6px 20px rgba(0,0,0,0.07);
}
table { 
    width: 100%; 
    border-collapse: collapse; 
    font-size: 0.9rem; 
    background: white;
}
thead { 
    background: var(--primary); 
    color: white; 
    font-weight: 600;
}
th, td { 
    padding: 0.75rem 1rem; 
    text-align: left; 
    border-bottom: 1px solid var(--gray); 
    vertical-align: middle;
}
.status-badge { 
    padding: 0.3em 0.75em; 
    border-radius: 12px; 
    font-weight: 600; 
    font-size: 0.85rem; 
    color: white; 
    user-select: none; 
    display: inline-block; 
    min-width: 90px;
}
.status-resolved { 
    background: var(--success);
}
.status-unresolved { 
    background: var(--warning);
}
.btn-icon-action { 
    background: none; 
    border: none; 
    padding: 0.3rem 0.5rem; 
    font-size: 1.40rem; 
    color: var(--primary); 
    cursor: pointer; 
    border-radius: 50%; 
    transition: background 0.2s;
}
.btn-icon-action:hover { 
    background: rgba(74, 107, 255, 0.09); 
    color: var(--accent);
}
.pagination { 
    margin-top: 1.5rem; 
    display: flex; 
    justify-content: center; 
    gap: 0.5rem; 
    flex-wrap: wrap;
}
.pagination a { 
    padding: 0.4rem 0.8rem; 
    color: var(--primary); 
    border: 1.5px solid var(--primary); 
    border-radius: 5px; 
    text-decoration: none; 
    font-weight: 600;
}
.pagination a:hover, 
.pagination a.active { 
    background: var(--primary); 
    color: #fff;
}
.details-row td {
    background: #f9f9f9;
}
.details-form fieldset {
    border: 1px solid var(--gray);
    border-radius: 6px;
    padding: 1rem;
    background: white;
}
.details-form legend {
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: var(--dark);
}
.details-form label {
    display: block;
    margin-top: 1rem;
    font-weight: 600;
    color: #333;
}
.details-form select, 
.details-form input[type="datetime-local"] {
    margin-top: 0.4rem; 
    padding: 0.5rem 0.75rem; 
    font-size: 1rem; 
    border: 1.5px solid var(--gray); 
    border-radius: 6px; 
    width: 100%; 
    box-sizing: border-box;
}
.details-form select:focus, 
.details-form input[type="datetime-local"]:focus {
    outline: none; 
    border-color: var(--primary); 
    box-shadow: 0 0 6px rgba(74,107,255,0.3);
}
.details-form div > label {
    font-weight: normal;
}
.details-form button {
    cursor: pointer;
    font-weight: 600;
    padding: 0.55rem 1.5rem;
    border: none;
    border-radius: 6px;
    font-size: 1rem;
    transition: background-color 0.2s;
}
.details-form .btn-primary {
    background: var(--primary);
    color: white;
}
.details-form .btn-primary:hover {
    background: var(--accent);
}
.details-form .btn-cancel {
    background: #e0e0e0;
    color: #333;
}
.details-form .btn-cancel:hover {
    background: #c2c2c2;
}
@media (max-width: 900px) {
.main-content { 
    margin-left: 0; 
    padding: 1rem 1.5rem;
}
table, thead, tbody, th, td, tr { 
    display: block;
}
thead tr { 
    display: none;
}
tr { 
    background: white; 
    margin-bottom: 1.25rem; 
    border-radius: 12px; 
    box-shadow: 0 6px 20px rgba(0,0,0,0.07); 
    padding: 1rem 1.2rem;
}
td { 
    padding-left: 50%; 
    position: relative; 
    text-align: right;
}
td::before { 
    content: attr(data-label); 
    position: absolute; 
    left: 1.25rem; 
    width: 45%; 
    font-weight: 600; 
    text-align: left; 
    color: var(--primary);
}
.details-row td {
    padding-left: 1.25rem !important;
    text-align: left !important;
}
}
</style>
</head>
<body>
<!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="company-logo">
                <img src="Telesol_logo.jpeg" alt="Company Logo" />
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
            <a href="view_tickets.php" class="nav-link active">
                <i class="bi bi-hdd-network"></i> View Tickets
            </a>
            <a href="log_installations.php" class="nav-link">
                <i class="bi bi-journal-plus"></i> Log Installation
            </a>
            <a href="view_installations.php" class="nav-link">
                <i class="bi bi-hdd-network"></i> View Installations
            </a>
            <a href="customer_experience_dashboard.php" class="nav-link">
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

<main class="main-content" role="main" aria-label="Logged Issues List">
    <h1>View Tickets</h1>
    <nav class="filters" role="tablist" aria-label="Issue Filters">
        <?php
        $tabs = ['all' => 'All Issues', 'pending' => 'Pending', 'assigned' => 'Assigned', 'resolved' => 'Resolved'];
        foreach ($tabs as $key => $label):
            $active = $filter === $key ? 'active' : '';
            $ariaSelected = $filter === $key ? 'true' : 'false';
        ?>
        <a href="?filter=<?= esc($key) ?>" role="tab" class="<?= $active ?>" aria-selected="<?= $ariaSelected ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="table-wrapper" role="region" aria-label="Logged Issues Table">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer Name</th>
                    <th>Contact Number</th>
                    <th>Email</th>
                    <th>Location</th>
                    <th>Issue Type</th>
                    <th>Scheduled Date/Time</th>
                    <th>Assigned Engineer</th>
                    <!-- <th>Comments</th> -->
                    <th>Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows === 0): ?>
                    <tr><td colspan="8" style="text-align:center; padding:1rem;">No issues found.</td></tr>
                <?php else:
                    $count = $offset + 1;
                    while ($row = $result->fetch_assoc()):
                        $isResolved = strtolower($row['issue_status']) === 'resolved';
                        $scheduled = ($row['scheduled_datetime'] && $row['scheduled_datetime'] !== '0000-00-00 00:00:00') ? date('Y-m-d H:i', strtotime($row['scheduled_datetime'])) : '–';
                ?>
                    <tr data-issue-id="<?= (int)$row['id'] ?>" class="main-row" tabindex="0" aria-expanded="false" aria-controls="details-<?= (int)$row['id'] ?>">
                        <td data-label="#" scope="row"><?= $count++ ?></td>
                        <td data-label="Customer Name"><?= esc($row['customer_name']) ?></td>
                        <td data-label="Contact Number"><?= esc($row['contact_number']) ?></td>
                        <td data-label="Email"><?= esc($row['email']) ?></td>
                        <td data-label="Location"><?= esc($row['location']) ?></td>
                        <td data-label="Issue Type"><?= esc($row['issue_type']) ?></td>
                        <td data-label="Scheduled Date/Time" class="scheduled-datetime"><?= esc($scheduled) ?></td>
                        <td data-label="Assigned Engineer" class="assigned-engineer"><?= esc($row['assigned_engineer'] ?: '–') ?></td>
                        <!-- <td data-label="Comments" title="<?= esc($row['comments']) ?>"><?= $row['comments'] ? esc($row['comments']) : '<em>None</em>' ?></td> -->
                        <td data-label="Status">
                            <span class="status-badge status-<?= $isResolved ? 'resolved' : 'unresolved' ?>"><?= esc($row['issue_status']) ?></span>
                        </td>
                        <td data-label="Details">
                            <button type="button" class="btn-icon-action btn-toggle-details" aria-label="Toggle details for ticket #<?= (int)$row['id'] ?>" aria-expanded="false">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                        </td>
                    </tr>
                    <tr class="details-row" id="details-<?= (int)$row['id'] ?>" aria-hidden="true" style="display:none;">
                        <td colspan="8" style="padding: 1rem;">
                            <form class="details-form" data-issue-id="<?= (int)$row['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= esc($csrf_token) ?>">
                                <input type="hidden" name="issue_id" value="<?= (int)$row['id'] ?>">
                                <fieldset>
                                    <legend>Ticket Details</legend>
                                    <p><strong>Customer Name:</strong> <?= esc($row['customer_name']) ?></p>
                                    <p><strong>Issue Type:</strong> <?= esc($row['issue_type']) ?></p>
                                    <p><strong>Comments:</strong> <?= $row['comments'] ? esc($row['comments']) : '<em>None</em>' ?></p>
                                    <label for="engineer-<?= (int)$row['id'] ?>">Assign Engineer<span aria-hidden="true">*</span>:</label>
                                    <select name="engineer" id="engineer-<?= (int)$row['id'] ?>" required>
                                        <option value="" disabled>Select Engineer</option>
                                        <?php foreach ($engineers as $eng): ?>
                                            <option value="<?= esc($eng) ?>" <?= $row['assigned_engineer'] === $eng ? 'selected' : '' ?>><?= esc($eng) ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <label for="scheduled-<?= (int)$row['id'] ?>">Schedule Date & Time<span aria-hidden="true">*</span>:</label>
                                    <input type="datetime-local" name="scheduled_datetime" id="scheduled-<?= (int)$row['id'] ?>" required 
                                    value="<?= ($row['scheduled_datetime'] && $row['scheduled_datetime'] !== '0000-00-00 00:00:00') ? date('Y-m-d\TH:i', strtotime($row['scheduled_datetime'])) : '' ?>">

                                    <label>Status<span aria-hidden="true">*</span>:</label>
                                    <div>
                                        <label>
                                            <input type="radio" name="issue_status_<?= (int)$row['id'] ?>" value="Resolved" <?= $isResolved ? 'checked' : '' ?>>
                                            Resolved
                                        </label>
                                        <label style="margin-left: 1rem;">
                                            <input type="radio" name="issue_status_<?= (int)$row['id'] ?>" value="Unresolved" <?= !$isResolved ? 'checked' : '' ?>>
                                            Unresolved
                                        </label>
                                    </div>

                                    <div style="margin-top:1rem; display:flex; justify-content:flex-end; gap:1rem;">
                                        <button type="submit" class="btn btn-primary">Save</button>
                                        <button type="button" class="btn btn-cancel">Cancel</button>
                                    </div>
                                </fieldset>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <nav role="navigation" aria-label="Pagination Navigation" class="pagination">
        <?php if ($page > 1): ?>
            <a href="?filter=<?= esc($filter) ?>&page=<?= $page - 1 ?>" aria-label="Previous page">&laquo; Prev</a>
        <?php endif; ?>
        <?php
        $max_links = 5;
        $start = max(1, $page - intval($max_links / 2));
        $end = min($total_pages, $start + $max_links - 1);
        if ($start > 1) echo '<a href="?filter=' . esc($filter) . '&page=1">1</a>' . ($start > 2 ? '<span>…</span>' : '');
        for ($i = $start; $i <= $end; $i++) {
            $active = ($i === $page) ? 'active' : '';
            $aria_current = $active ? 'page' : 'false';
            echo "<a href=\"?filter=" . esc($filter) . "&page=$i\" class=\"$active\" aria-current=\"$aria_current\">$i</a>";
        }
        if ($end < $total_pages) {
            if ($end < $total_pages - 1) echo '<span>…</span>';
            echo "<a href=\"?filter=" . esc($filter) . "&page=$total_pages\">$total_pages</a>";
        }
        ?>
        <?php if ($page < $total_pages): ?>
            <a href="?filter=<?= esc($filter) ?>&page=<?= $page + 1 ?>" aria-label="Next page">Next &raquo;</a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</main>

<script>
(() => {
    const csrfToken = '<?= $csrf_token ?>';

    // Toggle the expandable details row
    document.querySelectorAll('.btn-toggle-details').forEach(btn => {
        btn.addEventListener('click', () => {
            const mainRow = btn.closest('tr.main-row');
            const issueId = mainRow.getAttribute('data-issue-id');
            const detailsRow = document.getElementById('details-' + issueId);
            const expanded = mainRow.getAttribute('aria-expanded') === 'true';

            if (expanded) {
                detailsRow.style.display = 'none';
                mainRow.setAttribute('aria-expanded', 'false');
                btn.setAttribute('aria-expanded', 'false');
                btn.querySelector('i').classList.remove('bi-chevron-up');
                btn.querySelector('i').classList.add('bi-chevron-down');
            } else {
                detailsRow.style.display = 'table-row';
                mainRow.setAttribute('aria-expanded', 'true');
                btn.setAttribute('aria-expanded', 'true');
                btn.querySelector('i').classList.remove('bi-chevron-down');
                btn.querySelector('i').classList.add('bi-chevron-up');
            }
        });
    });

    // Cancel button inside details forms closes the details row
    document.querySelectorAll('.details-form .btn-cancel').forEach(btn => {
        btn.addEventListener('click', () => {
            const form = btn.closest('.details-form');
            const issueId = form.getAttribute('data-issue-id');
            const mainRow = document.querySelector(`tr.main-row[data-issue-id='${issueId}']`);
            const detailsRow = document.getElementById('details-' + issueId);
            const toggleBtn = mainRow.querySelector('.btn-toggle-details');

            detailsRow.style.display = 'none';
            mainRow.setAttribute('aria-expanded', 'false');
            toggleBtn.setAttribute('aria-expanded', 'false');
            toggleBtn.querySelector('i').classList.remove('bi-chevron-up');
            toggleBtn.querySelector('i').classList.add('bi-chevron-down');
        });
    });

    // Handle form submission inside the details popup for assign/schedule and set status
    document.querySelectorAll('.details-form').forEach(form => {
        form.addEventListener('submit', e => {
            e.preventDefault();
            const issueId = form.getAttribute('data-issue-id');
            const formData = new FormData(form);

            fetch('?action=assign_schedule', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    // Update row cells for assigned engineer and scheduled datetime
                    const mainRow = document.querySelector(`tr.main-row[data-issue-id='${issueId}']`);
                    if (mainRow) {
                        mainRow.querySelector('.assigned-engineer').textContent = data.assigned_engineer || '–';
                        const sdCell = mainRow.querySelector('.scheduled-datetime');
                        if (data.scheduled_datetime) {
                            sdCell.textContent = (new Date(data.scheduled_datetime)).toLocaleString();
                        } else {
                            sdCell.textContent = '–';
                        }
                    }

                    // Now handle status update separately because the assign_schedule POST updates engineer and schedule but not status.
                    // So we do a second request to update status if it changed.
                    const selectedStatus = form.querySelector(`input[name="issue_status_${issueId}"]:checked`).value;
                    const currentStatusBadge = mainRow.querySelector('.status-badge').textContent;

                    if (selectedStatus !== currentStatusBadge) {
                        const statusData = new URLSearchParams();
                        statusData.append('issue_id', issueId);
                        statusData.append('new_status', selectedStatus);
                        statusData.append('csrf_token', csrfToken);

                        fetch('?action=toggle_status', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: statusData.toString()
                        })
                        .then(r => r.json())
                        .then(statusResp => {
                            alert(statusResp.message);
                            if (statusResp.success) {
                                const badge = mainRow.querySelector('.status-badge');
                                badge.textContent = statusResp.new_status;
                                badge.classList.toggle('status-resolved', statusResp.new_status.toLowerCase() === 'resolved');
                                badge.classList.toggle('status-unresolved', statusResp.new_status.toLowerCase() !== 'resolved');
                            }
                        })
                        .catch(() => alert('Failed to update status.'));
                    }

                    // Optionally collapse details after save
                    const detailsRow = document.getElementById('details-' + issueId);
                    const toggleBtn = mainRow.querySelector('.btn-toggle-details');
                    detailsRow.style.display = 'none';
                    mainRow.setAttribute('aria-expanded', 'false');
                    toggleBtn.setAttribute('aria-expanded', 'false');
                    toggleBtn.querySelector('i').classList.remove('bi-chevron-up');
                    toggleBtn.querySelector('i').classList.add('bi-chevron-down');
                }
            })
            .catch(() => alert('Failed to update assignment.'));
        });
    });
})();
</script>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>
