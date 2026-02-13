<?php
session_start();

$username = $_SESSION['username'] ?? 'User';

//Database connection params
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "telesol crm";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_installation'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }
    
    $id = $_POST['id'] ?? '';
    if ($id && is_numeric($id)) {
        $stmt = $conn->prepare("DELETE FROM installations WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Installation deleted successfully";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting installation: " . $conn->error;
            $_SESSION['message_type'] = "error";
        }
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Handle edit action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_installation'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed");
    }
    
    $id = $_POST['id'] ?? '';
    $customer_name = $_POST['customer_name'] ?? '';
    $transaction_id = $_POST['transaction_id'] ?? '';
    $amount_paid = $_POST['amount_paid'] ?? '';
    $mode_of_payment = $_POST['mode_of_payment'] ?? '';
    $location = $_POST['location'] ?? '';
    $email = $_POST['email'] ?? '';
    $installation_status = $_POST['installation_status'] ?? '';
    $scheduled_datetime = $_POST['scheduled_datetime'] ?? '';
    $assigned_engineer = $_POST['assigned_engineer'] ?? '';
    $comment = $_POST['comment'] ?? '';
    
    if ($id && is_numeric($id)) {
        $stmt = $conn->prepare("UPDATE installations SET customer_name=?, transaction_id=?, amount_paid=?, mode_of_payment=?, location=?, email=?, installation_status=?, scheduled_datetime=?, assigned_engineer=?, comment=? WHERE id=?");
        $stmt->bind_param("ssdsssssssi", $customer_name, $transaction_id, $amount_paid, $mode_of_payment, $location, $email, $installation_status, $scheduled_datetime, $assigned_engineer, $comment, $id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Installation updated successfully";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error updating installation: " . $conn->error;
            $_SESSION['message_type'] = "error";
        }
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Pagination & filtering setup
$status_filter = $_GET['status'] ?? 'All';
$search_term = trim($_GET['search'] ?? '');
$valid_statuses = ['All', 'Resolved', 'Unresolved'];
if (!in_array($status_filter, $valid_statuses, true)) {
    $status_filter = 'All';
}
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where_clauses = [];
$params = [];
$types = '';

if ($status_filter !== 'All') {
    $where_clauses[] = "installation_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search_term)) {
    $where_clauses[] = "(customer_name LIKE ? OR transaction_id LIKE ? OR location LIKE ? OR assigned_engineer LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Count total results for pagination
$count_sql = "SELECT COUNT(*) FROM installations $where_sql";
$stmt_count = $conn->prepare($count_sql);
if ($params) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$stmt_count->bind_result($total_results);
$stmt_count->fetch();
$stmt_count->close();

// Fetch installations data for current page/filter
$sql = "SELECT * FROM installations $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$total_pages = max(ceil($total_results / $limit), 1);

// Sample engineers list - ideally from DB
$engineers = ['John Hagan', 'Isaac Ofosu-Afful', 'Sylvester Horsu', 'Innocent Odikro', 'Joshua Avinu'];

// Helper: escape output for HTML safety
function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Installations Management - CRM</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" />
<style>
  :root {
    --primary: #083b6e;
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
    --background: #f5f7fa;
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

    /* Quick action buttons */
    .quick-actions {
      display: flex;
      gap: 0.7rem;
      flex-wrap: wrap;
      padding: 0.6rem 1rem 0;
    }

    .quick-action-btn {
      display: flex;
      align-items: center;
      gap: 0.3rem;
      padding: 0.3rem 0.6rem;
      background-color: var(--dark);
      border-radius: 1px;
      color: var(--white);
      font-weight: 500;
      text-decoration: none;
      transition: background-color var(--transition), box-shadow var(--transition);
      cursor: pointer;
      user-select: none;
    }

    .quick-action-btn i {
      font-size: 0.7rem;
    }

    .quick-action-btn:hover {
      color: var(--white);
      background-color: var(--secondary);
      box-shadow: 0 3px 7px rgba(0,0,0,0.1);
    }

    .quick-action-btn.active {
      color: var(--white);
      background-color: var(--secondary);
    }

    .quick-action-btn.active:hover {
      background-color: var(--success);
      color: var(--white);
    }

    /* Table container */
    .table-container {
      margin: 0.6rem;
      margin-bottom: 0.2rem;
      background-color: var(--white);
      border-radius: 2px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.05);
      overflow-x: auto;
      height: 500px;
    }

    table {
      width: 100%;
      height: 100%;
      font-weight: 500;
      font-size: 0.8rem;
      color: var(--dark);
      border-collapse: collapse;
      min-width: 900px;
    }

    thead tr {
      background-color: var(--primary);
      color: var(--white);
      position: sticky;
      top: 0;
      bottom: 5rem;
      z-index: 5;
    }

    th {
      padding: 0.5rem;
      text-align: center;
      font-weight: 500;
      font-size: 0.8rem;
      text-transform: initial;
      color: var(--white);
    }

    td {
      padding: 0.05rem;
      border-bottom: 1px solid var(--light-gray);
      text-align: left;
      vertical-align: middle;
    }

    tbody tr:hover:not(.no-data) {
      background-color: #f0f4f8;
    }

    tbody tr.no-data td {
      text-align: center;
      font-style: italic;
      color: var(--gray);
    }

    tbody tr.overdue {
      background-color: #f8d7da;
    }

    /* Status badges */
    .status-badge {
      font-size: 0.7rem;
      text-align: center;
      align-items: center;
      border-radius: 50px;
      display: inline-block;
      padding: 0.2rem 0.2rem;
      text-transform: uppercase;
      min-width: 80px;
    }

    .status-badge.resolved {
      color: white;
      align-items: center;
      padding-top: 0.15rem;
      padding-bottom: 0.25rem;
      background-color: var(--success);
    }

    .status-badge.pending {
      background-color: #fff3cd;
      color: #856404;
    }

    .status-badge.overdue {
      background-color: #f8d7da;
      color: #dd0016ff;
    }

    /* Action buttons */
    .actions {
      white-space: nowrap;
      text-align: center;
      gap: 0.01rem;
    }

    .btn-icon-action {
      background: none;
      border: none;
      font-size: 1rem;
      color: var(--primary);
      cursor: pointer;
      border-radius: var(--border-radius);
      padding: 0.1rem 0.1rem;
      margin-right: 0.10rem;
      transition: color var(--transition), background-color var(--transition);
    }

    .btn-icon-action:hover,
    .btn-icon-action:focus {
      color: #0013a5;
      background-color: #eef6ff;
      outline: none;
    }

    /* Pagination */
    .pagination {
      display: flex;
      justify-content: center;
      gap: 0.25rem;
      padding: 0.3rem;
      flex-wrap: wrap;
      padding-top: 0.7rem;
    }

    .pagination a,
    .pagination span {
      display: inline-block;
      padding: 0.2rem 0.2rem;
      border-radius: var(--border-radius);
      border: 1px solid var(--light-gray);
      color: var(--dark);
      text-decoration: none;
      min-width: 40px;
      text-align: center;
      font-weight: 500;
      transition: background-color var(--transition), color var(--transition);
    }

    .pagination a:hover {
      background-color: var(--primary-dark);
      color: white;
      border-color: var(--primary-dark);
    }

    .pagination a.active {
      background-color: var(--secondary);
      color: white;
      border-color: var(--secondary);
      pointer-events: none;
    }

    .pagination span {
      color: var(--gray);
      pointer-events: none;
      user-select: none;
    }

    .user-session {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .user-session i {
      font-size: 1.3rem;
      color: var(--dark);
    }

    /* Page Content */
    .page-content {
      padding: 1.5rem;
      flex: 1;
    }

    .page-header {
      margin-bottom: 1.5rem;
    }

    .page-title {
      font-size: 1rem;
      font-family: inherit;
      font-weight: 400;
      color: var(--primary-dark);
      margin-top: -1.2rem;
      margin-bottom: -0.8rem;
    }

    .page-subtitle {
      color: var(--gray);
      font-size: 0.95rem;
      margin-bottom: -1rem;
    }

    /* Message alerts */
    .alert {
      padding: 0.75rem 1.25rem;
      margin-bottom: 1.5rem;
      border: 1px solid transparent;
      border-radius: var(--border-radius);
    }

    .alert-success {
      color: #155724;
      background-color: #d4edda;
      border-color: #c3e6cb;
    }

    .alert-error {
      color: #721c24;
      background-color: #f8d7da;
      border-color: #f5c6cb;
    }

    /* Filters */
    .filters-container {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      margin-left: -0.3rem;
      margin-top: -0.9rem;
      margin-bottom: 1rem;
      align-items: center;
    }

    .filter-tabs {
      display: flex;
      gap: 0.3rem;
      flex-wrap: wrap;
    }

    .filter-tab {
      padding: 0.5rem 1rem;
      border-radius: 4px;
      background: var(--white);
      color: var(--dark);
      font-weight: 500;
      text-decoration: none;
      transition: all var(--transition);
      border: 1px solid var(--light-gray);
    }

    .filter-tab:hover {
      background-color: var(--secondary);
      color: var(--white);
      border-color: var(--secondary);
      transform: translateY(-2px);
    }

    .filter-tab.active {
      background-color: var(--primary);
      color: var(--white);
      border-color: var(--primary);
    }

    .new-installation-btn {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      background-color: var(--success);
      color: white;
      border-radius: 4px;
      text-decoration: none;
      font-weight: 600;
      transition: all var(--transition);
    }

    .new-installation-btn:hover {
      background-color: #27ae60;
      transform: translateY(-2px);
    }

    /* Table */
    .table-container {
      margin-top: -0.3rem;
      margin-left: -0.3rem;
      margin-right: -0.3rem;
      background-color: var(--white);
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
    
    .no-data {
      text-align: center;
      padding: 2rem;
      color: var(--gray);
      font-style: italic;
    }
    
    .no-data i {
      font-size: 2.5rem;
      display: block;
      margin-bottom: 0.5rem;
    }

    /* Status badges */
    .status-badge {
      display: inline-block;
      padding: 0.2rem 0.6rem;
      border-radius: 50px;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
    }
    
    .status-completed {
      background-color: var(--success);
      color: white;
    }
    
    .status-uncompleted {
      background-color: #fff3cd;
      color: #856404;
    }

    /* Action buttons */
    .actions {
      display: flex;
      gap: 0.1rem;
    }
    
    .btn-icon-action {
      background: none;
      border: none;
      font-size: 1.05rem;
      color: var(--dark);
      cursor: pointer;
      border-radius: var(--border-radius);
      padding: 0.2rem;
      transition: all var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .btn-icon-action:hover {
      background-color: #eef6ff;
      color: var(--secondary);
      transform: scale(1.1);
    }
    
    .btn-view:hover {
      color: var(--secondary);
    }
    
    .btn-edit:hover {
      color: var(--warning);
    }
    
    .btn-delete:hover {
      color: var(--danger);
    }

    /* Pagination */
    .pagination {
      display: flex;
      justify-content: center;
      gap: 0.5rem;
      flex-wrap: wrap;
      margin-top: 0.2rem;
      margin-bottom: -4rem;
    }
    
    .pagination a,
    .pagination span {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.3rem 0.7rem;
      border-radius: var(--border-radius);
      border: 1px solid var(--light-gray);
      color: var(--dark);
      text-decoration: none;
      min-width: 40px;
      font-weight: 500;
      transition: all var(--transition);
    }
    
    .pagination a:hover {
      background-color: var(--primary);
      color: white;
      border-color: var(--primary);
    }
    
    .pagination a.active {
      background-color: var(--primary);
      color: white;
      border-color: var(--primary);
      pointer-events: none;
    }
    
    .pagination span {
      color: var(--gray);
      pointer-events: none;
    }

    /* Modal */
    .modal-backdrop {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 2000;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }
    
    .modal {
      background: var(--white);
      border-radius: var(--border-radius);
      width: 100%;
      max-width: 600px;
      max-height: 80vh;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
      animation: modalFadeIn 0.3s ease;
    }
    
    @keyframes modalFadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    
    .modal-header {
      padding-left: 1rem;
      border-bottom: 1px solid var(--light-gray);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .modal-title {
      font-size: 1rem;
      font-weight: 600;
      color: var(--primary-dark);
    }
    
    .modal-close {
      padding-right: 2rem;
      background: none;
      border: none;
      font-size: 2rem;
      font-weight: bolder;
      color: var(--danger);
      cursor: pointer;
      transition: color var(--transition);
    }
    
    .modal-close:hover {
      color: #ea0000;
    }
    
    .modal-body {
      padding: 1rem;
      overflow-y: auto;
      flex: 1;
    }
    
    .detail-section {
      margin-bottom: 0.8rem;
      padding-bottom: 0.9rem;
      border-bottom: 1px solid var(--light-gray);
    }
    
    .detail-section:last-child {
      border-bottom: none;
      margin-bottom: 0;
    }
    
    .section-title {
      font-size: 0.9rem;
      font-weight: 600;
      margin-top: -0.1rem;
      margin-bottom: 0.2rem;
      color: var(--primary);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    
    .section-title i {
      font-size: 0.9rem;
    }
    
    .detail-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 1.2rem;
    }
    
    .detail-item {
      display: flex;
      flex-direction: column;
      gap: 0.3rem;
    }
    
    .detail-label {
      font-weight: 600;
      color: var(--dark);
      font-size: 0.9rem;
    }
    
    .detail-value {
      color: var(--dark);
      padding: 0.3rem;
      background-color: #f8f9fa;
      border-radius: 4px;
      min-height: 2.1rem;
      display: flex;
      align-items: center;
    }
    
    .form-group {
      margin-bottom: -0.5rem;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 0.3rem;
      font-weight: 500;
      color: var(--dark);
    }
    
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 90%;
      padding: 0.4rem;
      border: 1px solid var(--light-gray);
      border-radius: var(--border-radius);
      font-size: 0.9rem;
      transition: border-color var(--transition), box-shadow var(--transition);
    }
    
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--secondary);
      box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
    }
    
    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 0.8rem;
      margin-top: 1.2rem;
    }
    
    .btn {
      padding: 0.5rem 1rem;
      border-radius: var(--border-radius);
      font-weight: 600;
      cursor: pointer;
      transition: all var(--transition);
      border: none;
    }
    
    .btn-primary {
      background-color: var(--primary);
      color: white;
    }
    
    .btn-primary:hover {
      background-color: var(--primary-dark);
    }
    
    .btn-secondary {
      background-color: var(--secondary);
      color: white;
    }
    
    .btn-secondary:hover {
      background-color: #2980b9;
    }
    
    .btn-success {
      background-color: var(--success);
      color: white;
    }
    
    .btn-success:hover {
      background-color: #27ae60;
    }
    
    .btn-warning {
      background-color: var(--warning);
      color: white;
    }
    
    .btn-warning:hover {
      background-color: #e67e22;
    }
    
    .btn-danger {
      background-color: var(--danger);
      color: white;
    }
    
    .btn-danger:hover {
      background-color: #c0392b;
    }

    /* Edit Modal */
    .edit-modal {
      max-width: 600px;
    }
    
    .edit-form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0.8rem;
    }
    
    .form-full-width {
      grid-column: 1 / -1;
    }

    /* Delete confirmation modal */
    .delete-confirmation {
      text-align: center;
      padding: 1.8rem;
    }
    
    .delete-confirmation i {
      font-size: 3rem;
      color: var(--danger);
      margin-bottom: 0.8rem;
      display: block;
    }
    
    .delete-confirmation h3 {
      margin-bottom: 1rem;
      color: var(--dark);
    }
    
    .delete-confirmation p {
      margin-bottom: 2rem;
      color: var(--gray);
    }

    /* Toast notifications */
    #toast-container {
      position: fixed;
      top: 1.5rem;
      right: 1.5rem;
      z-index: 2100;
      display: flex;
      flex-direction: column;
      gap: 0.8rem;
      max-width: 350px;
    }
    
    .toast {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      background-color: var(--white);
      padding: 1rem 1.5rem;
      border-radius: var(--border-radius);
      box-shadow: var(--shadow-hover);
      font-weight: 500;
      color: var(--dark);
      animation: slideInRight 0.3s forwards, fadeOut 0.5s forwards 4s;
      border-left: 4px solid transparent;
    }
    
    .toast-success {
      border-left-color: var(--success);
    }
    
    .toast-error {
      border-left-color: var(--danger);
    }
    
    .toast i {
      font-size: 1.3rem;
    }
    
    @keyframes slideInRight {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes fadeOut {
      to { opacity: 0; transform: translateX(100%); }
    }

    /* Responsive */
    @media (max-width: 1024px) {
      .sidebar {
        transform: translateX(-100%);
        width: 280px;
      }
      
      .main-content {
        margin-left: 0;
      }
      
      .sidebar.active {
        transform: translateX(0);
      }
    }
    
    @media (max-width: 768px) {
      .header {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
      }
      
      .navbar-search {
        max-width: 100%;
        margin-right: 0;
      }
      
      .filters-container {
        flex-direction: column;
        align-items: stretch;
      }
      
      .filter-tabs {
        justify-content: center;
      }
      
      .modal {
        max-height: 85vh;
      }
      
      .detail-grid {
        grid-template-columns: 1fr;
      }
      
      .edit-form-grid {
        grid-template-columns: 1fr;
      }
    }
    
    @media (max-width: 480px) {
      .section-title {
        font-size: 1.1rem;
      }
      
      .modal-body {
        padding: 1rem;
      }
      
      .form-actions {
        flex-direction: column;
      }
      
      .btn {
        width: 100%;
      }
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
  <main class="main-content">
    <!-- Header -->
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

    <!-- Page Content -->
    <div class="page-content">
      <!-- Display messages -->
      <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?>">
          <?= $_SESSION['message'] ?>
          <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        </div>
      <?php endif; ?>

      <!-- Filters -->
      <div class="filters-container">
        <div class="filter-tabs">
          <a href="?status=All&search=<?= urlencode($search_term) ?>" class="filter-tab <?= $status_filter === 'All' ? 'active' : '' ?>">
            All Installations
          </a>
          <a href="?status=Uncompleted&search=<?= urlencode($search_term) ?>" class="filter-tab <?= $status_filter === 'Uncompleted' ? 'active' : '' ?>">
            Uncompleted
          </a>
          <a href="?status=Completed&search=<?= urlencode($search_term) ?>" class="filter-tab <?= $status_filter === 'Completed' ? 'active' : '' ?>">
            Completed
          </a>
        </div>

        <a href="new_installation.php" class="new-installation-btn">
          <i class="bi bi-plus-circle"></i> New Installation
        </a>
      </div>

      <!-- Installations Table -->
      <div class="table-container">
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Transaction ID</th>
                <th>Customer</th>
                <th>Package</th>
                <th>Location</th>
                <!-- <th>Scheduled</th> -->
                <!-- <th>Engineer</th> -->
                <th>Status</th>
                <th>Logged By</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($result->num_rows === 0): ?>
                <tr>
                  <td colspan="10" class="no-data">
                    <i class="bi bi-inbox"></i>
                    No installations found for the selected filter.
                  </td>
                </tr>
              <?php else:
                $row_num = $offset + 1;
                while ($row = $result->fetch_assoc()):
                  $scheduled = $row['scheduled_datetime'] ? date('M j, Y g:i A', strtotime($row['scheduled_datetime'])) : 'Not Set';
                  $statusClass = strtolower($row['installation_status']) === 'resolved' ? 'status-resolved' : 'status-unresolved';
              ?>
              <tr>
                <td><?= $row_num++ ?></td>
                <td><?= esc($row['transaction_id']) ?: 'n/a' ?></td>
                <td><?= esc($row['customer_name']) ?></td>
                <td>$<?= number_format((float)$row['amount_paid'], 2) ?></td>
                <td><?= esc($row['location']) ?></td>
                <!-- <td><?= esc($scheduled) ?></td> -->
                <!-- <td><?= esc($row['assigned_engineer']) ?: 'Unassigned' ?></td> -->
                <td><span class="status-badge <?= $statusClass ?>"><?= esc($row['installation_status']) ?></span></td>
                <td><?= esc($row['created_by']) ?></td>
                <td>
                  <div class="actions">
                    <button class="btn-icon-action btn-view" title="View Details"
                      data-id="<?= (int)$row['id'] ?>"
                      data-transaction-id="<?= esc($row['transaction_id']) ?>"
                      data-customer-name="<?= esc($row['customer_name']) ?>"
                      data-amount-paid="<?= esc($row['amount_paid']) ?>"
                      data-mode-of-payment="<?= esc($row['mode_of_payment']) ?>"
                      data-location="<?= esc($row['location']) ?>"
                      data-email="<?= esc($row['email']) ?>"
                      data-comment="<?= esc($row['comment']) ?>"
                      data-installation-status="<?= esc($row['installation_status']) ?>"
                      data-scheduled-datetime="<?= esc($row['scheduled_datetime']) ?>"
                      data-assigned-engineer="<?= esc($row['assigned_engineer']) ?>"
                      data-created-by="<?= esc($row['created_by']) ?>"
                      data-created-at="<?= esc(date('M j, Y g:i A', strtotime($row['created_at']))) ?>">
                      <i class="bi bi-eye"></i>
                    </button>
                    <!-- <button class="btn-icon-action btn-edit" title="Edit Installation"
                      data-id="<?= (int)$row['id'] ?>"
                      data-transaction-id="<?= esc($row['transaction_id']) ?>"
                      data-customer-name="<?= esc($row['customer_name']) ?>"
                      data-amount-paid="<?= esc($row['amount_paid']) ?>"
                      data-mode-of-payment="<?= esc($row['mode_of_payment']) ?>"
                      data-location="<?= esc($row['location']) ?>"
                      data-email="<?= esc($row['email']) ?>"
                      data-comment="<?= esc($row['comment']) ?>"
                      data-installation-status="<?= esc($row['installation_status']) ?>"
                      data-scheduled-datetime="<?= esc($row['scheduled_datetime']) ?>"
                      data-assigned-engineer="<?= esc($row['assigned_engineer']) ?>">
                      <i class="bi bi-pencil"></i>
                    </button> -->
                    <button class="btn-icon-action btn-delete" title="Delete Installation"
                      data-id="<?= (int)$row['id'] ?>"
                      data-customer-name="<?= esc($row['customer_name']) ?>">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endwhile; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
      <?php if ($total_pages > 1): ?>
        <nav class="pagination">
          <?php if ($page > 1): ?>
            <a href="?status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search_term) ?>&page=<?= $page - 1 ?>">
              &laquo; Prev
            </a>
          <?php endif; ?>

          <?php
          $max_links = 5;
          $start = max(1, $page - floor($max_links / 2));
          $end = min($total_pages, $start + $max_links - 1);
          
          if ($start > 1): ?>
            <a href="?status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search_term) ?>&page=1">1</a>
            <?php if ($start > 2): ?>
              <span>...</span>
            <?php endif; ?>
          <?php endif; ?>

          <?php for ($i = $start; $i <= $end; $i++): ?>
            <a href="?status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search_term) ?>&page=<?= $i ?>" 
               class="<?= $i === $page ? 'active' : '' ?>">
              <?= $i ?>
            </a>
          <?php endfor; ?>

          <?php if ($end < $total_pages): ?>
            <?php if ($end < $total_pages - 1): ?>
              <span>...</span>
            <?php endif; ?>
            <a href="?status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search_term) ?>&page=<?= $total_pages ?>">
              <?= $total_pages ?>
            </a>
          <?php endif; ?>

          <?php if ($page < $total_pages): ?>
            <a href="?status=<?= urlencode($status_filter) ?>&search=<?= urlencode($search_term) ?>&page=<?= $page + 1 ?>">
              Next &raquo;
            </a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>
    </div>
  </main>

  <!-- Detail Modal (Merged into one tab) -->
  <div id="detailModal" class="modal-backdrop">
    <div class="modal">
      <div class="modal-header">
        <h2 class="modal-title">Installation Details</h2>
        <button class="modal-close">&times;</button>
      </div>
      
      <div class="modal-body">
        <!-- Customer Information Section -->
        <div class="detail-section">
          <h3 class="section-title"><i class="bi bi-person"></i> Customer Information</h3>
          <div class="detail-grid">
            <div class="detail-item">
              <div class="detail-label">Customer Name</div>
              <div class="detail-value" id="detail-customer-name"></div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Transaction ID</div>
              <div class="detail-value" id="detail-transaction-id"></div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Email</div>
              <div class="detail-value" id="detail-email"></div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Location</div>
              <div class="detail-value" id="detail-location"></div>
            </div>
          </div>
        </div>

        <!-- Payment Information Section -->
        <div class="detail-section">
          <h3 class="section-title"><i class="bi bi-cash"></i> Payment Information</h3>
          <div class="detail-grid">
            <div class="detail-item">
              <div class="detail-label">Package Amount</div>
              <div class="detail-value" id="detail-amount-paid"></div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Payment Method</div>
              <div class="detail-value" id="detail-mode-of-payment"></div>
            </div>
          </div>
        </div>

        <!-- Installation Details Section -->
        <div class="detail-section">
          <h3 class="section-title"><i class="bi bi-wrench"></i> Installation Details</h3>
          <div class="detail-grid">
            <div class="detail-item">
              <div class="detail-label">Status</div>
              <div class="detail-value" id="detail-installation-status"></div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Assigned Engineer</div>
              <div class="detail-value" id="detail-assigned-engineer"></div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Scheduled Date/Time</div>
              <div class="detail-value" id="detail-scheduled-datetime"></div>
            </div>
          </div>
        </div>

        <!-- Assignment & Comments Section -->
        <div class="detail-section">
          <h3 class="section-title"><i class="bi bi-calendar-check"></i> Assignment & Comments</h3>
          <div class="detail-grid">
            <div class="detail-item" style="grid-column: 1 / -1;">
              <div class="detail-label">Comments</div>
              <div class="detail-value" id="detail-comment"></div>
            </div>
          </div>
        </div>

        <!-- System Information Section -->
        <div class="detail-section">
          <h3 class="section-title"><i class="bi bi-info-circle"></i> System Information</h3>
          <div class="detail-grid">
            <div class="detail-item">
              <div class="detail-label">Logged By</div>
              <div class="detail-value" id="detail-created-by"></div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Logged At</div>
              <div class="detail-value" id="detail-created-at"></div>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="detail-section">
          <h3 class="section-title"><i class="bi bi-lightning"></i> Quick Actions</h3>
          <div class="form-actions">
            <button type="button" class="btn btn-secondary" id="edit-installation-btn">Edit Installation</button>
            <button type="button" class="btn btn-success" id="mark-completed-btn">Mark as Completed</button>
            <button type="button" class="btn btn-warning" id="mark-uncompleted-btn">Mark as Uncompleted</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div id="editModal" class="modal-backdrop">
    <div class="modal edit-modal">
      <div class="modal-header">
        <h2 class="modal-title">Edit Installation</h2>
        <button class="modal-close">&times;</button>
      </div>
      
      <div class="modal-body">
        <form id="editForm" method="POST">
          <input type="hidden" name="edit_installation" value="1">
          <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
          <input type="hidden" id="edit-id" name="id">
          
          <div class="edit-form-grid">
            <div class="form-group">
              <label for="edit-customer-name">Customer Name *</label>
              <input type="text" id="edit-customer-name" name="customer_name" required>
            </div>
            
            <div class="form-group">
              <label for="edit-transaction-id">Transaction ID</label>
              <input type="text" id="edit-transaction-id" name="transaction_id">
            </div>
            
            <div class="form-group">
              <label for="edit-amount-paid">Amount Paid (GHS) *</label>
              <input type="number" id="edit-amount-paid" name="amount_paid" step="0.01" min="0" required>
            </div>
            
            <div class="form-group">
              <label for="edit-mode-of-payment">Payment Method</label>
              <select id="edit-mode-of-payment" name="mode_of_payment">
                <option value="Cash">Cash</option>
                <option value="Credit Card">Bank Card</option>
                <option value="Bank Transfer">Bank Transfer</option>
                <option value="Mobile Money">Mobile Money</option>
                <option value="Other">Other</option>
              </select>
            </div>
            
            <div class="form-group">
              <label for="edit-location">Location *</label>
              <input type="text" id="edit-location" name="location" required>
            </div>
            
            <div class="form-group">
              <label for="edit-email">Email</label>
              <input type="email" id="edit-email" name="email">
            </div>
            
            <div class="form-group">
              <label for="edit-installation-status">Status *</label>
              <select id="edit-installation-status" name="installation_status" required>
                <option value="Completed">Completed</option>
                <option value="Uncompleted">Uncompleted</option>
              </select>
            </div>
            
            <div class="form-group">
              <label for="edit-scheduled-datetime">Scheduled Date/Time</label>
              <input type="datetime-local" id="edit-scheduled-datetime" name="scheduled_datetime">
            </div>
            
            <div class="form-group">
              <label for="edit-assigned-engineer">Assigned Engineer</label>
              <select id="edit-assigned-engineer" name="assigned_engineer">
                <option value="">Select Engineer</option>
                <?php foreach ($engineers as $eng): ?>
                  <option value="<?= esc($eng) ?>"><?= esc($eng) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <div class="form-group form-full-width">
              <label for="edit-comment">Comments</label>
              <textarea id="edit-comment" name="comment" rows="3"></textarea>
            </div>
          </div>
          
          <div class="form-actions">
            <button type="button" class="btn btn-secondary" id="edit-cancel">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div id="deleteModal" class="modal-backdrop">
    <div class="modal" style="max-width: 500px;">
      <div class="modal-header">
        <h2 class="modal-title">Confirm Deletion</h2>
        <button class="modal-close">&times;</button>
      </div>
      
      <div class="modal-body">
        <div class="delete-confirmation">
          <i class="bi bi-exclamation-triangle"></i>
          <h3>Are you sure?</h3>
          <p>You are about to delete the installation for <span id="delete-customer-name" style="font-weight: bold;"></span>. This action cannot be undone.</p>
          
          <form id="deleteForm" method="POST">
            <input type="hidden" name="delete_installation" value="1">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" id="delete-id" name="id">
            
            <div class="form-actions">
              <button type="button" class="btn btn-secondary" id="delete-cancel">Cancel</button>
              <button type="submit" class="btn btn-danger">Delete Installation</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Toast Container -->
  <div id="toast-container"></div>

  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const detailModal = document.getElementById('detailModal');
    const editModal = document.getElementById('editModal');
    const deleteModal = document.getElementById('deleteModal');
    let currentInstallationId = null;
    let currentInstallationData = null;
    
    // View button click handler
    document.querySelectorAll('.btn-view').forEach(button => {
      button.addEventListener('click', function() {
        const data = this.dataset;
        currentInstallationId = data.id;
        currentInstallationData = data;
        
        // Populate details
        document.getElementById('detail-transaction-id').textContent = data.transactionId || 'N/A';
        document.getElementById('detail-customer-name').textContent = data.customerName || '';
        document.getElementById('detail-amount-paid').textContent = data.amountPaid ? `$${parseFloat(data.amountPaid).toFixed(2)}` : '';
        document.getElementById('detail-mode-of-payment').textContent = data.modeOfPayment || '';
        document.getElementById('detail-location').textContent = data.location || '';
        document.getElementById('detail-email').textContent = data.email || '';
        
        const statusElement = document.getElementById('detail-installation-status');
        statusElement.textContent = data.installationStatus || '';
        statusElement.className = 'detail-value ' + 
          (data.installationStatus === 'Completed' ? 'status-completed' : 'status-uncompleted');
        
        document.getElementById('detail-assigned-engineer').textContent = data.assignedEngineer || 'Unassigned';
        document.getElementById('detail-scheduled-datetime').textContent = data.scheduledDatetime ? 
          new Date(data.scheduledDatetime).toLocaleString() : 'Not set';
        document.getElementById('detail-comment').textContent = data.comment || 'No comments';
        document.getElementById('detail-created-by').textContent = data.createdBy || '';
        document.getElementById('detail-created-at').textContent = data.createdAt || '';
        
        // Show modal
        showModal(detailModal);
      });
    });
    
    // Edit button click handler (from table)
    document.querySelectorAll('.btn-edit').forEach(button => {
      button.addEventListener('click', function() {
        const data = this.dataset;
        populateEditForm(data);
      });
    });
    
    // Edit button in detail modal
    document.getElementById('edit-installation-btn').addEventListener('click', function() {
      if (currentInstallationData) {
        populateEditForm(currentInstallationData);
        closeModal(detailModal);
      }
    });
    
    function populateEditForm(data) {
      document.getElementById('edit-id').value = data.id;
      document.getElementById('edit-customer-name').value = data.customerName || '';
      document.getElementById('edit-transaction-id').value = data.transactionId || '';
      document.getElementById('edit-amount-paid').value = data.amountPaid || '';
      document.getElementById('edit-mode-of-payment').value = data.modeOfPayment || '';
      document.getElementById('edit-location').value = data.location || '';
      document.getElementById('edit-email').value = data.email || '';
      document.getElementById('edit-installation-status').value = data.installationStatus || '';
      document.getElementById('edit-scheduled-datetime').value = data.scheduledDatetime ? 
        new Date(data.scheduledDatetime).toISOString().slice(0, 16) : '';
      document.getElementById('edit-assigned-engineer').value = data.assignedEngineer || '';
      document.getElementById('edit-comment').value = data.comment || '';
      
      showModal(editModal);
    }
    
    // Mark as completed button
    document.getElementById('mark-completed-btn').addEventListener('click', function() {
      updateStatus('Completed');
    });
    
    // Mark as uncompleted button
    document.getElementById('mark-uncompleted-btn').addEventListener('click', function() {
      updateStatus('Uncompleted');
    });
    
    function updateStatus(status) {
      if (!currentInstallationId) {
        showToast('No installation selected', 'error');
        return;
      }
      
      // Create a hidden form and submit it
      const form = document.createElement('form');
      form.method = 'POST';
      form.style.display = 'none';
      
      const csrfInput = document.createElement('input');
      csrfInput.type = 'hidden';
      csrfInput.name = 'csrf_token';
      csrfInput.value = '<?= $csrf_token ?>';
      
      const editInput = document.createElement('input');
      editInput.type = 'hidden';
      editInput.name = 'edit_installation';
      editInput.value = '1';
      
      const idInput = document.createElement('input');
      idInput.type = 'hidden';
      idInput.name = 'id';
      idInput.value = currentInstallationId;
      
      const statusInput = document.createElement('input');
      statusInput.type = 'hidden';
      statusInput.name = 'installation_status';
      statusInput.value = status;
      
      // Preserve other data
      const customerInput = document.createElement('input');
      customerInput.type = 'hidden';
      customerInput.name = 'customer_name';
      customerInput.value = currentInstallationData.customerName || '';
      
      const transactionInput = document.createElement('input');
      transactionInput.type = 'hidden';
      transactionInput.name = 'transaction_id';
      transactionInput.value = currentInstallationData.transactionId || '';
      
      const amountInput = document.createElement('input');
      amountInput.type = 'hidden';
      amountInput.name = 'amount_paid';
      amountInput.value = currentInstallationData.amountPaid || '';
      
      const paymentInput = document.createElement('input');
      paymentInput.type = 'hidden';
      paymentInput.name = 'mode_of_payment';
      paymentInput.value = currentInstallationData.modeOfPayment || '';
      
      const locationInput = document.createElement('input');
      locationInput.type = 'hidden';
      locationInput.name = 'location';
      locationInput.value = currentInstallationData.location || '';
      
      const emailInput = document.createElement('input');
      emailInput.type = 'hidden';
      emailInput.name = 'email';
      emailInput.value = currentInstallationData.email || '';
      
      const datetimeInput = document.createElement('input');
      datetimeInput.type = 'hidden';
      datetimeInput.name = 'scheduled_datetime';
      datetimeInput.value = currentInstallationData.scheduledDatetime || '';
      
      const engineerInput = document.createElement('input');
      engineerInput.type = 'hidden';
      engineerInput.name = 'assigned_engineer';
      engineerInput.value = currentInstallationData.assignedEngineer || '';
      
      const commentInput = document.createElement('input');
      commentInput.type = 'hidden';
      commentInput.name = 'comment';
      commentInput.value = currentInstallationData.comment || '';
      
      form.appendChild(csrfInput);
      form.appendChild(editInput);
      form.appendChild(idInput);
      form.appendChild(statusInput);
      form.appendChild(customerInput);
      form.appendChild(transactionInput);
      form.appendChild(amountInput);
      form.appendChild(paymentInput);
      form.appendChild(locationInput);
      form.appendChild(emailInput);
      form.appendChild(datetimeInput);
      form.appendChild(engineerInput);
      form.appendChild(commentInput);
      
      document.body.appendChild(form);
      form.submit();
    }
    
    // Delete button click handler
    document.querySelectorAll('.btn-delete').forEach(button => {
      button.addEventListener('click', function() {
        const data = this.dataset;
        
        document.getElementById('delete-id').value = data.id;
        document.getElementById('delete-customer-name').textContent = data.customerName || 'this installation';
        
        showModal(deleteModal);
      });
    });
    
    // Close modals
    document.querySelectorAll('.modal-close, #edit-cancel, #delete-cancel').forEach(button => {
      button.addEventListener('click', function() {
        const modal = this.closest('.modal-backdrop');
        closeModal(modal);
      });
    });
    
    // Close modal when clicking outside
    document.querySelectorAll('.modal-backdrop').forEach(modal => {
      modal.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this);
      });
    });
    
    // Escape key to close modal
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        const openModal = document.querySelector('.modal-backdrop[style="display: flex;"]');
        if (openModal) closeModal(openModal);
      }
    });
    
    function showModal(modal) {
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }
    
    function closeModal(modal) {
      modal.style.display = 'none';
      document.body.style.overflow = 'auto';
      currentInstallationId = null;
      currentInstallationData = null;
    }
    
    // Toast notification function
    function showToast(message, type = 'success') {
      const toastContainer = document.getElementById('toast-container');
      const toast = document.createElement('div');
      toast.className = `toast toast-${type}`;
      toast.innerHTML = `
        <i class="bi ${type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle'}"></i>
        <span>${message}</span>
      `;
      toastContainer.appendChild(toast);
      
      setTimeout(() => {
        toast.style.animation = 'fadeOut 0.5s forwards';
        setTimeout(() => toast.remove(), 500);
      }, 4000);
    }
    
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => {
        const searchValue = this.value.trim();
        const url = new URL(window.location);
        
        if (searchValue) {
          url.searchParams.set('search', searchValue);
        } else {
          url.searchParams.delete('search');
        }
        
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
      }, 500);
    });
  });
  </script>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>