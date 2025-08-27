<?php
// No session start — public access

// Database credentials (consider moving to environment/config file for production)
$servername = "localhost";
$db_username = "root";
$db_password = "";
$dbname = "CRM";

// Establish a secure database connection with error handling
$conn = new mysqli($servername, $db_username, $db_password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

// === Handle AJAX schedule update request ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'schedule') {
    header('Content-Type: application/json');

    // Input sanitization & validation with filter_input for better security
    $installation_id = filter_input(INPUT_POST, 'installation_id', FILTER_VALIDATE_INT);
    $engineer = filter_input(INPUT_POST, 'engineer', FILTER_SANITIZE_STRING);
    $scheduled_datetime_raw = $_POST['scheduled_datetime'] ?? '';

    // Validate scheduled date/time format (ISO 8601 / strtotime)
    $scheduled_datetime = false;
    if (!empty($scheduled_datetime_raw)) {
        $dt = DateTime::createFromFormat('Y-m-d\TH:i', $scheduled_datetime_raw);
        if ($dt !== false) {
            $scheduled_datetime = $dt->format('Y-m-d H:i:s');
        }
    }

    // Static valid engineers list (Replace with DB fetch in prod)
    $engineers = ['John Smith', 'Alice Johnson', 'Mohamed Ali', 'Sophia Lee'];

    // Validate inputs and return JSON response on error
    if (!$installation_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid Installation ID.']);
        exit;
    }
    if (empty($engineer) || !in_array($engineer, $engineers, true)) {
        echo json_encode(['success' => false, 'message' => 'Selected engineer is not valid.']);
        exit;
    }
    if (!$scheduled_datetime) {
        echo json_encode(['success' => false, 'message' => 'Invalid or missing scheduled date/time.']);
        exit;
    }

    // Update the installation record securely with prepared statements
    $stmt_update = $conn->prepare("UPDATE installations SET assigned_engineer = ?, scheduled_datetime = ? WHERE id = ?");
    if (!$stmt_update) {
        echo json_encode(['success' => false, 'message' => 'Database error: Unable to prepare statement.']);
        exit;
    }
    $stmt_update->bind_param('ssi', $engineer, $scheduled_datetime, $installation_id);
    if ($stmt_update->execute()) {
        echo json_encode(['success' => true, 'message' => 'Schedule updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update schedule.']);
    }
    $stmt_update->close();
    $conn->close();
    exit;
}

// === View Installations Listing Logic ===

// Sanitize and validate inputs
$status_filter = $_GET['status'] ?? 'All'; // Valid: All, Resolved, Unresolved
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$limit = 10;
$offset = ($page - 1) * $limit;

// Validate filter values
$valid_statuses = ['All', 'Resolved', 'Unresolved'];
if (!in_array($status_filter, $valid_statuses, true)) {
    $status_filter = 'All';
}

// Count total results with or without status filter for pagination
if ($status_filter === 'All') {
    $count_sql = "SELECT COUNT(*) FROM installations";
    $stmt_count = $conn->prepare($count_sql);
} else {
    $count_sql = "SELECT COUNT(*) FROM installations WHERE installation_status = ?";
    $stmt_count = $conn->prepare($count_sql);
    $stmt_count->bind_param('s', $status_filter);
}
$stmt_count->execute();
$stmt_count->bind_result($total_results);
$stmt_count->fetch();
$stmt_count->close();

// Fetch installations data with pagination and sorting
if ($status_filter === 'All') {
    $sql = "SELECT id, transaction_id, customer_name, amount_paid, mode_of_payment, location, email, comment,
                   installation_status, scheduled_datetime, assigned_engineer, created_by, created_at
            FROM installations
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $limit, $offset);
} else {
    $sql = "SELECT id, transaction_id, customer_name, amount_paid, mode_of_payment, location, email, comment,
                   installation_status, scheduled_datetime, assigned_engineer, created_by, created_at
            FROM installations
            WHERE installation_status = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sii', $status_filter, $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

// Calculate total pages for pagination
$total_pages = max(ceil($total_results / $limit), 1);

// Sample engineers list for scheduling (In production, replace with a DB query)
$engineers = ['John Smith', 'Alice Johnson', 'Mohamed Ali', 'Sophia Lee'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Installations Management - CRM</title>

<!-- Fonts & Icons -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" />

<style>
  :root {
    --primary: #4a6bff;
    --accent: #ff4a6b;
    --dark: #20306f;
    --light: #f8f9fa;
    --gray-light: #e9ecef;
    --gray-dark: #6c757d;
    --success: #28a745;
    --warning: #ffc107;
    --error: #dc3545;
    --font-family: 'Inter', sans-serif;
  }
  
  /* Reset & Base */
  * {
    box-sizing: border-box;
  }
  body, html {
    margin: 0;
    padding: 0;
    height: 100%;
    font-family: var(--font-family);
    background-color: var(--light);
    color: #333;
    overflow: hidden; /* prevent body scroll */
  }

  /* Layout */
  body {
    display: flex;
    height: 100vh;
  }

  /* Sidebar */
  .sidebar {
    width: 300px;
    background-color: var(--dark);
    height: 100vh;
    box-shadow: 2px 0 10px rgba(0,0,0,0.15);
    padding: 1.5rem 2rem;
    display: flex;
    flex-direction: column;
    overflow: auto;
  }
  .sidebar-logo {
    margin-bottom: 2rem;
  }
  .sidebar-logo img {
    max-height: 40px;
    display: block;
  }
  .sidebar-menu {
    flex-grow: 1;
  }
  .sidebar-menu ul {
    list-style: none;
    padding: 0;
    margin: 0;
  }
  .sidebar-menu li {
    margin-bottom: 1rem;
  }
  .sidebar-menu a {
    text-decoration: none;
    color: var(--gray-light);
    font-weight: 600;
    font-size: 1rem;
    display: flex;
    align-items: center;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    transition: background-color 0.3s, color 0.3s;
  }
  .sidebar-menu a:hover,
  .sidebar-menu a.active {
    background-color: rgba(74,107,255,0.15);
    color: var(--primary);
  }
  .sidebar-menu a i {
    margin-right: 12px;
    font-size: 1.3rem;
  }

  /* Main content */
  main.main-content {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    padding: 2.5rem 3rem;
    overflow: hidden; /* prevent main content scroll */
  }

  main.main-content h1 {
    font-weight: 700;
    margin: 0 0 1.5rem 0;
    color: var(--dark);
    letter-spacing: 0.03em;
    flex-shrink: 0;
  }

  /* Tabs */
  .tabs {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-shrink: 0;
  }
  .tabs a {
    display: inline-block;
    padding: 0.5rem 1.25rem;
    border: 2px solid var(--primary);
    border-radius: 8px;
    font-weight: 600;
    color: var(--primary);
    text-decoration: none;
    transition: all 0.3s;
    user-select: none;
  }
  .tabs a.active,
  .tabs a:hover {
    background-color: var(--primary);
    color: white;
  }

  /* Scrollable table container */
  .table-wrapper {
    flex-grow: 1;
    overflow-y: auto;
    box-shadow: 0 4px 12px rgb(0 0 0 / 0.05);
    border-radius: 10px;
    background: white;
  }

  /* Tables */
  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
    color: #333;
  }
  thead tr {
    background-color: var(--primary);
    color: white;
    font-weight: 600;
    letter-spacing: 0.02em;
    position: sticky;
    top: 0;
    z-index: 2;
  }
  th, td {
    padding: 12px 18px;
    border-bottom: 1px solid var(--gray-light);
  }
  th {
    text-align: left;
  }
  td.comment-cell {
    max-width: 220px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: help;
  }

  /* Status badges */
  .status-badge {
    padding: 0.3em 0.65em;
    border-radius: 12px;
    color: #fff;
    font-weight: 700;
    font-size: 0.85rem;
    text-align: center;
    min-width: 90px;
    user-select: none;
    display: inline-block;
  }
  .status-resolved {
    background-color: var(--success);
  }
  .status-unresolved {
    background-color: var(--warning);
    color: #212529;
  }

  /* Pagination */
  .pagination {
    margin-top: 1.75rem;
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    flex-wrap: wrap;
    flex-shrink: 0;
  }
  .pagination a {
    padding: 0.45rem 0.85rem;
    border: 2px solid var(--primary);
    border-radius: 6px;
    color: var(--primary);
    font-weight: 600;
    text-decoration: none;
    transition: all 0.25s ease;
    user-select: none;
  }
  .pagination a:hover {
    background-color: var(--primary);
    color: white;
  }
  .pagination a.active {
    background-color: var(--primary);
    color: white;
    pointer-events: none;
  }

  /* Schedule button styles */
  .schedule-btn {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--primary);
    font-size: 1.3rem;
    transition: color 0.3s ease;
  }
  .schedule-btn:hover,
  .schedule-btn:focus {
    color: var(--accent);
    outline: none;
  }

  /* Modal backdrop */
  .modal-backdrop {
    position: fixed;
    top: 0; left: 0;
    width: 100vw; height: 100vh;
    background: rgba(0,0,0,0.4);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    padding: 1rem;
  }
  .modal-backdrop.active {
    display: flex;
  }

  /* Modal content */
  .modal {
    background: white;
    padding: 2rem 2.5rem;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    max-width: 420px;
    width: 100%;
    font-family: var(--font-family);
    position: relative;
  }
  .modal h2 {
    margin-top: 0;
    font-weight: 700;
    font-size: 1.7rem;
    color: var(--dark);
  }
  .modal label {
    display: block;
    margin-top: 1.2rem;
    font-weight: 600;
    color: var(--gray-dark);
  }
  .modal input[type="datetime-local"],
  .modal select {
    margin-top: 0.4rem;
    width: 100%;
    padding: 0.55rem 0.85rem;
    font-size: 1rem;
    border: 2px solid var(--gray-light);
    border-radius: 8px;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
    background-color: #fff;
  }
  .modal input[type="datetime-local"]:focus,
  .modal select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 10px rgba(74,107,255,0.3);
    outline: none;
    background-color: white;
  }

  .modal .btn-group {
    margin-top: 2rem;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
  }
  .modal button {
    font-weight: 700;
    padding: 0.6rem 1.8rem;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-size: 1.1rem;
    transition: background-color 0.25s ease;
  }
  .modal button.submit-btn {
    background-color: var(--primary);
    color: white;
  }
  .modal button.submit-btn:hover,
  .modal button.submit-btn:focus {
    background-color: var(--accent);
    outline: none;
  }
  .modal button.cancel-btn {
    background-color: #e0e0e0;
    color: #444;
  }
  .modal button.cancel-btn:hover,
  .modal button.cancel-btn:focus {
    background-color: #c2c2c2;
    outline: none;
  }

  /* Responsive for smaller screens */
  @media (max-width: 768px) {
    body {
      flex-direction: column;
      overflow: visible;
    }
    .sidebar {
      width: 100%;
      height: auto;
      box-shadow: none;
      flex-direction: row;
      padding: 1rem 1.5rem;
      overflow-x: auto;
      scrollbar-width: thin;
      scrollbar-color: var(--primary) transparent;
    }
    .sidebar-menu ul {
      display: flex;
      gap: 1rem;
      margin: 0;
    }
    .sidebar-menu li {
      margin-bottom: 0;
    }
    main.main-content {
      padding: 1.5rem 1rem;
      height: calc(100vh - 112px); /* sidebar approx 56px + header/tabs approx 56px */
    }
    .table-wrapper {
      height: 100%;
      max-height: none;
    }
    table, thead, tbody, th, td, tr {
      display: block;
      width: 100% !important;
    }
    thead tr {
      display: none;
    }
    tr {
      margin-bottom: 1rem;
      background: white;
      border-radius: 10px;
      box-shadow: 0 4px 10px rgb(0 0 0 / 0.05);
      padding: 1.25rem;
    }
    td {
      position: relative;
      padding-left: 50%;
      white-space: normal;
      text-align: left;
      border: none;
    }
    td::before {
      content: attr(data-label);
      position: absolute;
      left: 1rem;
      top: 1.25rem;
      width: 45%;
      font-weight: 700;
      color: var(--gray-dark);
      white-space: nowrap;
    }
  }

  /* Visually hidden (for aria descriptions) */
  .sr-only {
    border: 0 !important;
    clip: rect(1px,1px,1px,1px) !important;
    -webkit-clip-path: inset(50%) !important;
    clip-path: inset(50%) !important;
    height: 1px !important;
    margin: -1px !important;
    overflow: hidden !important;
    padding: 0 !important;
    position: absolute !important;
    width: 1px !important;
    white-space: nowrap !important;
  }
</style>
</head>
<body>

<!-- Sidebar Navigation -->
<aside class="sidebar" role="navigation" aria-label="Main navigation">
  <div class="sidebar-logo">
    <img src="Telesol_logo.jpeg" alt="Telesol Company Logo" />
  </div>
  <nav class="sidebar-menu" aria-label="Sidebar menu">
    <ul>
      <li><a href="main-menu.php" class="nav-link active" aria-current="page"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
      <li><a href="log_issue.php" class="nav-link"><i class="bi bi-journal-plus"></i> Log Issue</a></li>
      <li><a href="log_installations.php" class="nav-link"><i class="bi bi-hdd-network"></i> Log Installation</a></li>
      <li><a href="customer_experience.php" class="nav-link"><i class="bi bi-person-lines-fill"></i> Customer Experience</a></li>
      <li><a href="field_installations.php" class="nav-link"><i class="bi bi-tools"></i> Field Installations</a></li>
      <li><a href="#" class="nav-link"><i class="bi bi-funnel"></i> Filters</a></li>
      <li><a href="#" class="nav-link"><i class="bi bi-journal-text"></i> Reports</a></li>
    </ul>
  </nav>
</aside>

<!-- Main Content -->
<main class="main-content" role="main">

  <h1>Installations Overview</h1>

  <!-- Status Filter Tabs -->
  <nav class="tabs" aria-label="Filter installations by status">
    <a href="?status=All" class="<?= $status_filter === 'All' ? 'active' : '' ?>" role="tab" aria-selected="<?= $status_filter === 'All' ? 'true' : 'false' ?>" tabindex="0">All</a>
    <a href="?status=Unresolved" class="<?= $status_filter === 'Unresolved' ? 'active' : '' ?>" role="tab" aria-selected="<?= $status_filter === 'Unresolved' ? 'true' : 'false' ?>" tabindex="-1">Unresolved</a>
    <a href="?status=Resolved" class="<?= $status_filter === 'Resolved' ? 'active' : '' ?>" role="tab" aria-selected="<?= $status_filter === 'Resolved' ? 'true' : 'false' ?>" tabindex="-1">Resolved</a>
  </nav>

  <section aria-live="polite" aria-relevant="additions removals" class="table-wrapper" tabindex="0">
    <table aria-describedby="installationsDesc" role="grid">
      <caption id="installationsDesc" style="text-align:left; padding: 0 0 1rem 0; font-weight: 600; color: var(--gray-dark);">
        List of installations with status and scheduling details.
      </caption>
      <thead>
        <tr role="row">
          <th>#</th>
          <th>Transaction ID</th>
          <th>Customer Name</th>
          <th>Package</th>
          <th>Mode of Payment</th>
          <th>Location</th>
          <th>Email</th>
          <th>Scheduled Date/Time</th>
          <th>Assigned Engineer</th>
          <th>Schedule</th>
          <th>Comment</th>
          <th>Status</th>
          <th>Logged By</th>
          <th>Logged At</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result->num_rows === 0): ?>
          <tr>
            <td colspan="14" style="text-align:center; padding:2rem; font-style: italic; color: var(--gray-dark);">No installations found for the selected filter.</td>
          </tr>
        <?php else:
          $row_count = $offset + 1;
          while ($row = $result->fetch_assoc()):
            $scheduled = $row['scheduled_datetime'] ? date('Y-m-d H:i', strtotime($row['scheduled_datetime'])) : null;
            $assignedEng = $row['assigned_engineer'] ?? null;
        ?>
          <tr role="row">
            <td data-label="#" role="gridcell"><?= htmlspecialchars($row_count++) ?></td>
            <td data-label="Transaction ID" role="gridcell"><?= htmlspecialchars($row['transaction_id']) ?: '<em>n/a</em>'; ?></td>
            <td data-label="Customer Name" role="gridcell"><?= htmlspecialchars($row['customer_name']) ?></td>
            <td data-label="Package" role="gridcell"><?= '$' . number_format((float)$row['amount_paid'], 2) ?></td>
            <td data-label="Mode of Payment" role="gridcell"><?= htmlspecialchars($row['mode_of_payment']) ?></td>
            <td data-label="Location" role="gridcell"><?= htmlspecialchars($row['location']) ?></td>
            <td data-label="Email" role="gridcell"><a href="mailto:<?= htmlspecialchars($row['email']) ?>"><?= htmlspecialchars($row['email']) ?></a></td>
            <td data-label="Scheduled Date/Time" role="gridcell" title="<?= $scheduled ?? 'Not set'; ?>">
              <?= $scheduled ?? '<em>Not set</em>'; ?>
            </td>
            <td data-label="Assigned Engineer" role="gridcell">
              <?= $assignedEng ? htmlspecialchars($assignedEng) : '<em>Unassigned</em>'; ?>
            </td>
            <td data-label="Schedule" role="gridcell" style="text-align:center;">
              <button
                class="schedule-btn"
                aria-label="Schedule or reschedule installation ID <?= (int)$row['id'] ?>"
                title="Schedule or change engineer assignment"
                data-id="<?= (int)$row['id'] ?>"
                data-current-engineer="<?= htmlspecialchars($assignedEng ?? '') ?>"
                data-current-datetime="<?= htmlspecialchars($row['scheduled_datetime'] ?? '') ?>"
              >
                <i class="bi bi-calendar-event"></i>
              </button>
            </td>
            <td data-label="Comment" class="comment-cell" role="gridcell" title="<?= htmlspecialchars($row['comment']) ?>">
              <?= htmlspecialchars($row['comment']) ?: '<em>None</em>'; ?>
            </td>
            <td data-label="Status" role="gridcell">
              <span class="status-badge <?= strtolower($row['installation_status']) === 'resolved' ? 'status-resolved' : 'status-unresolved' ?>">
                <?= htmlspecialchars($row['installation_status']) ?>
              </span>
            </td>
            <td data-label="Logged By" role="gridcell"><?= htmlspecialchars($row['created_by']) ?></td>
            <td data-label="Logged At" role="gridcell"><?= htmlspecialchars(date("Y-m-d H:i", strtotime($row['created_at']))) ?></td>
          </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </section>

  <!-- Pagination -->
  <?php if ($total_pages > 1): ?>
  <nav aria-label="Pagination navigation" class="pagination" role="navigation">
    <?php if ($page > 1):
      $prev_page = $page - 1; ?>
      <a href="?status=<?= urlencode($status_filter) ?>&page=<?= $prev_page ?>" aria-label="Go to previous page">&laquo; Prev</a>
    <?php endif; ?>

    <?php for ($i = 1; $i <= $total_pages; $i++):
      $is_active = ($i === $page) ? 'active' : '';
    ?>
      <a href="?status=<?= urlencode($status_filter) ?>&page=<?= $i ?>" class="<?= $is_active ?>" aria-current="<?= $is_active ? 'page' : 'false' ?>">
        <?= $i ?>
      </a>
    <?php endfor; ?>

    <?php if ($page < $total_pages):
      $next_page = $page + 1; ?>
      <a href="?status=<?= urlencode($status_filter) ?>&page=<?= $next_page ?>" aria-label="Go to next page">Next &raquo;</a>
    <?php endif; ?>
  </nav>
  <?php endif; ?>

</main>

<!-- Modal: Schedule Installation -->
<div id="scheduleModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-hidden="true" tabindex="-1">
  <div class="modal">
    <h2 id="modalTitle">Schedule Installation</h2>
    <form id="scheduleForm" novalidate>
      <input type="hidden" name="installation_id" id="installation_id" value="" />

      <label for="engineer">Assign Engineer <span aria-hidden="true" style="color: var(--error);">*</span></label>
      <select name="engineer" id="engineer" required aria-required="true" aria-describedby="engineerHelp">
        <option value="" disabled selected>Select Engineer</option>
        <?php foreach ($engineers as $eng): ?>
          <option value="<?= htmlspecialchars($eng) ?>"><?= htmlspecialchars($eng) ?></option>
        <?php endforeach; ?>
      </select>
      <span id="engineerHelp" class="sr-only">Choose an engineer to assign for the installation</span>

      <label for="scheduled_datetime">Scheduled Date and Time <span aria-hidden="true" style="color: var(--error);">*</span></label>
      <input type="datetime-local" id="scheduled_datetime" name="scheduled_datetime" required aria-required="true" aria-describedby="datetimeHelp" />
      <span id="datetimeHelp" class="sr-only">Select date and time for scheduling the installation</span>

      <div class="btn-group">
        <button type="button" class="cancel-btn" id="modalCancelBtn">Cancel</button>
        <button type="submit" class="submit-btn">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
  const scheduleModal = document.getElementById('scheduleModal');
  const scheduleForm = document.getElementById('scheduleForm');
  const installationIdInput = document.getElementById('installation_id');
  const engineerSelect = document.getElementById('engineer');
  const dtInput = document.getElementById('scheduled_datetime');
  const modalCancelBtn = document.getElementById('modalCancelBtn');

  function showMessage(message, isError = false) {
    alert(message);
  }

  // Open modal & fill form on schedule button click
  document.querySelectorAll('.schedule-btn').forEach(button => {
    button.addEventListener('click', () => {
      const instId = button.dataset.id;
      const currentEngineer = button.dataset.currentEngineer;
      const currentDatetime = button.dataset.currentDatetime;

      installationIdInput.value = instId;

      engineerSelect.value = (currentEngineer && [...engineerSelect.options].some(opt => opt.value === currentEngineer)) ? currentEngineer : "";

      if (currentDatetime && !isNaN(Date.parse(currentDatetime))) {
        dtInput.value = new Date(currentDatetime).toISOString().slice(0,16);
      } else {
        dtInput.value = "";
      }

      openModal();
    });
  });

  function openModal() {
    scheduleModal.classList.add('active');
    scheduleModal.setAttribute('aria-hidden', 'false');
    engineerSelect.focus();
  }

  function closeModal() {
    scheduleModal.classList.remove('active');
    scheduleModal.setAttribute('aria-hidden', 'true');
    scheduleForm.reset();
  }

  modalCancelBtn.addEventListener('click', closeModal);
  scheduleModal.addEventListener('click', e => {
    if (e.target === scheduleModal) closeModal();
  });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && scheduleModal.classList.contains('active')) {
      closeModal();
    }
  });

  function validateScheduleForm() {
    if (!engineerSelect.value) {
      showMessage('Please select an engineer.', true);
      engineerSelect.focus();
      return false;
    }
    if (!dtInput.value) {
      showMessage('Please select a scheduled date and time.', true);
      dtInput.focus();
      return false;
    }

    const selectedDate = new Date(dtInput.value);
    if (isNaN(selectedDate)) {
      showMessage('The scheduled date/time is invalid.', true);
      dtInput.focus();
      return false;
    }

    const now = new Date();
    if (selectedDate < now) {
      showMessage('Scheduled date/time cannot be in the past.', true);
      dtInput.focus();
      return false;
    }

    return true;
  }

  scheduleForm.addEventListener('submit', async e => {
    e.preventDefault();

    if (!validateScheduleForm()) return;

    const formData = new FormData(scheduleForm);

    try {
      const response = await fetch('?action=schedule', {
        method: 'POST',
        body: formData,
        headers: {
          'Accept': 'application/json'
        }
      });
      if (!response.ok) throw new Error('Network response error.');

      const data = await response.json();
      showMessage(data.message, !data.success);

      if (data.success) {
        closeModal();
        window.location.reload();
      }
    } catch (error) {
      showMessage('An error occurred while updating the schedule. Please try again.', true);
      console.error(error);
    }
  });
</script>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
