<?php
session_start();
// Get logged-in username or default
$username = $_SESSION['username'] ?? 'User';
// CSRF token generation for AJAX
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
// Database connection details
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "telesol crm";
// Create DB connection with error handling
$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}
// Department-wise engineers
$department_persons = [
    'Technology' => ['John Hagan', 'Isaac Ofosu-Afful'],
    'System' => ['Sylvester Horsu', 'Innocent Odikro', 'Joshua Avinu'],
];
// Flatten all engineers for validation if needed
$all_engineers = array_merge(...array_values($department_persons));

// ------ AJAX: Add Comment --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'add_comment') {
    header('Content-Type: application/json');
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $issue_id = $_POST['issue_id'] ?? null;
    $comment = trim($_POST['comment'] ?? '');
    $author = $_SESSION['username'] ?? 'Unknown';
    
    // Basic validations
    if (!$issue_id || !is_numeric($issue_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid issue ID.']);
        exit;
    }
    
    if (empty($comment)) {
        echo json_encode(['success' => false, 'message' => 'Comment cannot be empty.']);
        exit;
    }
    
    // Insert comment into database
    $stmt = $conn->prepare("INSERT INTO comments (ticket_id, author, comment, created_at) VALUES (?, ?, ?, NOW())");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
        exit;
    }
    
    $stmt->bind_param('iss', $issue_id, $author, $comment);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Comment added successfully.',
            'comment' => [
                'author' => $author,
                'comment' => $comment,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add comment.']);
    }
    
    $stmt->close();
    $conn->close();
    exit;
}

// ------ AJAX: Assign/Schedule (multi-assignment & department) --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'assign_schedule') {
    header('Content-Type: application/json');
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    $issue_id = $_POST['issue_id'] ?? null;
    $assigned_department = trim($_POST['assigned_department'] ?? '');
    $assigned_persons = $_POST['assigned_persons'] ?? [];
    $scheduled_datetime = $_POST['scheduled_datetime'] ?? null;
    $comments = trim($_POST['comments'] ?? '');
    $resolved_by_deadline = $_POST['resolved_by_deadline'] ?? null;
    // Basic validations
    if (!$issue_id || !is_numeric($issue_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid issue ID.']);
        exit;
    }
    if (!array_key_exists($assigned_department, $department_persons)) {
        echo json_encode(['success' => false, 'message' => 'Invalid department selected.']);
        exit;
    }
    if (!is_array($assigned_persons) || count($assigned_persons) === 0) {
        echo json_encode(['success' => false, 'message' => 'Please select at least one assigned person.']);
        exit;
    }
    // Validate assigned persons belong to selected department
    $valid_persons = array_intersect($assigned_persons, $department_persons[$assigned_department]);
    if (count($valid_persons) === 0) {
        echo json_encode(['success' => false, 'message' => 'Assigned persons do not match selected department.']);
        exit;
    }
    // Validate scheduled datetime
    if (!$scheduled_datetime || !strtotime($scheduled_datetime)) {
        echo json_encode(['success' => false, 'message' => 'Invalid scheduled date/time.']);
        exit;
    }
    // Validate resolved_by_deadline (optional)
    if ($resolved_by_deadline && !strtotime($resolved_by_deadline)) {
        echo json_encode(['success' => false, 'message' => 'Invalid resolve by deadline datetime.']);
        exit;
    }
    // Store assigned_persons as comma-separated string
    $assigned_persons_str = implode(', ', $valid_persons);
    // Prepare and execute update statement
    $stmt = $conn->prepare("UPDATE tickets SET assigned_department = ?, assigned_persons = ?, scheduled_datetime = ?, comments = ?, resolved_by_deadline = ? WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
        exit;
    }
    $stmt->bind_param(
        'sssssi',
        $assigned_department,
        $assigned_persons_str,
        $scheduled_datetime,
        $comments,
        $resolved_by_deadline,
        $issue_id
    );
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Assignment, schedule, comments, and timeline updated successfully.',
            'assigned_department' => $assigned_department,
            'assigned_persons' => $assigned_persons_str,
            'scheduled_datetime' => $scheduled_datetime,
            'comments' => $comments,
            'resolved_by_deadline' => $resolved_by_deadline,
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update assignment.']);
    }
    $stmt->close();
    $conn->close();
    exit;
}
// ------ AJAX: Toggle issue status (Resolved/Unresolved) --------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'toggle_status') {
    header('Content-Type: application/json');
    // Validate CSRF token
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
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
        exit;
    }
    $stmt->bind_param('si', $new_status, $issue_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully.', 'new_status' => $new_status]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
    }
    $stmt->close();
    $conn->close();
    exit;
}
// -- Filters and Pagination (including search)
$valid_filters = ['all', 'pending', 'assigned', 'resolved'];
$filter = strtolower($_GET['filter'] ?? 'all');
if (!in_array($filter, $valid_filters, true)) {
    $filter = 'all';
}
$search_term = trim($_GET['search'] ?? '');
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$where_clauses = [];
switch ($filter) {
    case 'pending':
        $where_clauses[] = "(assigned_persons IS NULL OR assigned_persons = '')";
        $where_clauses[] = "(scheduled_datetime IS NULL OR scheduled_datetime = '0000-00-00 00:00:00')";
        $where_clauses[] = "(issue_status IS NULL OR issue_status != 'Resolved')";
        break;
    case 'assigned':
        $where_clauses[] = "((assigned_persons IS NOT NULL AND assigned_persons != '') OR (scheduled_datetime IS NOT NULL AND scheduled_datetime != '0000-00-00 00:00:00'))";
        $where_clauses[] = "(issue_status IS NULL OR issue_status != 'Resolved')";
        break;
    case 'resolved':
        $where_clauses[] = "issue_status = 'Resolved'";
        break;
}
if ($search_term !== '') {
    $escaped_search = $conn->real_escape_string($search_term);
    $where_clauses[] = "(customer_name LIKE '%$escaped_search%' OR contact_number LIKE '%$escaped_search%' OR email LIKE '%$escaped_search%' OR location LIKE '%$escaped_search%' OR issue_type LIKE '%$escaped_search%')";
}
$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
// Get total results count for pagination
$count_sql = "SELECT COUNT(*) FROM tickets $where_sql";
$stmt_count = $conn->prepare($count_sql);
if ($stmt_count === false) {
    die('Failed to prepare count query.');
}
$stmt_count->execute();
$stmt_count->bind_result($total_results);
$stmt_count->fetch();
$stmt_count->close();
// Fetch paginated ticket data, including comments
$data_sql = "SELECT id, customer_name, contact_number, email, location, issue_type, scheduled_datetime, assigned_department, assigned_persons, issue_status, comments, created_at, logged_by, resolved_by_deadline, service_type
             FROM tickets $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($data_sql);
if ($stmt === false) {
    die('Failed to prepare data query.');
}
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$total_pages = $total_results > 0 ? ceil($total_results / $limit) : 1;
// Helper to escape output safely
function esc($str)
{
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
// Helper for checking overdue (not resolved and past resolved_by_deadline)
function isOverdue(array $row): bool
{
    if (strtolower($row['issue_status']) === 'resolved') return false;
    if (empty($row['resolved_by_deadline']) || $row['resolved_by_deadline'] === '0000-00-00 00:00:00') return false;
    return strtotime($row['resolved_by_deadline']) < time();
}
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
<meta charset="UTF-8" />
<title>Telesol CRM - View Tickets</title>
<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1" />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" />
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
    --light-green: #3ad809ff;
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
/* Main Content */
.main-content {
 flex: 1;
 margin-left: 250px;
 padding: 2rem 1rem 1rem 2rem;
 min-height: 100vh;
 overflow-x: auto;
 position: relative;
 user-select: text;
}
@media (max-width: 900px) {
 .main-content {
   margin-left: 0;
   padding: 1.3rem 1.6rem;
 }
}
/* User Session Display - fixed at top right */
.user-session {
 position: fixed;
 top: 0.7rem;
 right: 1rem;
 background-color: var(--dark);
 color: var(--white);
 padding: 0.5rem 0.8rem;
 border-radius: 5px;
 font-weight: 400;
 font-size: 1rem;
 z-index: 1100;
 user-select: none;
 text-align: center;
 align-items: center;
}
/* Header */
h1 {
 font-weight: 700;
 font-family: inherit;
 margin-top: -0.6rem;
 font-size: 1.8rem;
 user-select: text;
 color: var(--dark);
 margin-bottom: 0.7rem;
}
/* Filters */
.filters {
 display: flex;
 flex-wrap: wrap;
 gap: 1rem;
 margin-bottom: 1rem;
}
.filters a {
 padding: 0.65rem 2.4rem;
 border-radius: 6px;
 background: var(--dark);
 color: white;
 font-size: 16px;
 font-weight: 600;
 font-family: inherit;
 text-decoration: none;
 box-shadow: 0 2px 6px rgba(52, 73, 94, 0.2);
 transition: all var(--transition);
 user-select: none;
 letter-spacing: 0.05em;
 border: none;
}
.filters a:hover,
.filters a:focus-visible {
 background: var(--dark);
 outline-offset: 4px;
 box-shadow: 0 4px 12px var(--accent);
}
.filters a.active {
 background: var(--light-green);
 box-shadow: 0 5px 16px var(--accent);
}
/* Search Bar */
.search-bar {
 margin-bottom: 1rem;
 max-width: 300px;
 position: relative;
}
.search-bar input {
 width: 100%;
 height: 35px;
 padding: 0.75rem 1rem 0.75rem 3rem;
 font-size: 0.95rem;
 border-radius: 6px;
 border: 1.2px solid var(--sidebar);
 font-family: inherit;
 box-shadow: inset 0 0 4px rgba(0,0,0,0.03);
 color: var(--dark);
}
.search-bar input::placeholder {
 color: var(--sidebar);
 font-weight: 400;
}
.search-bar input:focus {
 outline: none;
 border-color: var(--sidebar);
}
.search-bar svg {
 position: absolute;
 top: 50%;
 left: 0.6rem;
 transform: translateY(-50%);
 width: 20px;
 height: 20px;
 fill: var(--sidebar);
 pointer-events: none;
 user-select: none;
}
/* Table */
.table-wrapper {
 background: white;
 border-radius: 12px;
 box-shadow: 0 10px 30px rgba(52, 73, 94, 0.1);
 overflow-x: auto;
 max-height: 500px;
 overflow-y: auto;
}
table {
 width: 100%;
 border-collapse: separate;
 border-spacing: 0 6px;
 font-size: 1rem;
 color: var(--sidebar);
 min-width: 940px;
}
thead tr {
 background-color: var(--dark);
 color: white;
 font-weight: 500;
 font-size: 1.1rem;
 font-family: inherit;
 border-radius: 10px;
}
th,
td {
 padding: 0.6rem 0.6rem;
 text-align: left;
 vertical-align: middle;
 position: relative;
 overflow-wrap: anywhere;
 font-size: 14px;
 font-family: inherit;
}
thead tr th:first-child {
 border-top-left-radius: 12px;
}
thead tr th:last-child {
 border-top-right-radius: 12px;
}
tbody tr {
 background-color: white;
 box-shadow: 0 2px 20px rgba(0,0,0,0.06);
 border-radius: 12px;
 cursor: pointer;
 user-select: none;
}
tbody tr:hover,
tbody tr.main-row:focus-within {
 background-color: var(--gray);
 box-shadow: 0 8px 24px rgba(42, 133, 255, 0.18);
 outline-offset: -4px;
 outline: 3px solid var(--primary);
}
tbody tr.main-row:focus {
 background-color: var(--gray);
}
tbody tr.main-row:focus td {
 outline: none;
}
tbody tr td:first-child {
 color: var(--primary);
 font-weight: 700;
 width: 3.5rem;
 user-select: text;
}
/* Status as icons */
.status-icon {
 font-size: 1.3rem;
 vertical-align: middle;
}
/* Buttons */
.btn-icon-action {
 background: none;
 border: none;
 padding: 0.4rem 0.6rem;
 font-size: 1.55rem;
 color: var(--sidebar);
 cursor: pointer;
 border-radius: 50%;
 transition: background 0.3s, color 0.3s;
 display: flex;
 align-items: center;
 justify-content: center;
}
.btn-icon-action:hover,
.btn-icon-action:focus-visible {
 background-color: var(--sidebar);
 color: white;
 outline-offset: 3px;
 outline: 4px solid var(--accent);
}
.pagination {
 margin-top: 2rem;
 display: flex;
 justify-content: center;
 gap: 0.5rem;
 flex-wrap: wrap;
 font-weight: 500;
}
.pagination a,
.pagination span {
 padding: 0.5rem 0.8rem;
 border-radius: 5px;
 width: 42px;
 text-align: center;
 font-size: 0.9rem;
 text-decoration: none;
 color: var(--sidebar);
 cursor: pointer;
 transition: all 0.3s ease;
 user-select: none;
}
.pagination span {
 color: #999;
 font-size: 1.1rem;
 pointer-events: none;
}
.pagination a.active,
.pagination a:hover,
.pagination a:focus-visible {
 background-color: var(--sidebar);
 color: white;
 outline-offset: 4px;
 outline: 4px solid var(--accent);
 cursor: default;
}
/* Responsive table */
@media (max-width: 900px) {
 table, thead, tbody, th, td, tr {
   display: block;
 }
 thead tr {
   display: none;
 }
 tr.main-row {
   margin-bottom: 1.5rem;
   padding: 1.2rem 1.6rem;
   box-shadow: 0 8px 28px rgba(0,0,0,0.1);
   border-radius: 18px;
   background: white;
 }
 td {
   padding-left: 58%;
   text-align: right;
   font-size: 1rem;
   font-weight: 600;
   position: relative;
   border: none;
 }
 td::before {
   content: attr(data-label);
   position: absolute;
   left: 1.5rem;
   top: 50%;
   transform: translateY(-50%);
   font-weight: 700;
   color: var(--primary);
   white-space: nowrap;
 }
 .details-row td {
   padding-left: 2rem !important;
   text-align: left !important;
 }
}
/* Toast container and animations */
#toast-container {
 position: fixed;
 top: 1.2rem;
 right: 1.2rem;
 z-index: 9999;
 display: flex;
 flex-direction: column;
 gap: 0.8rem;
 max-width: 310px;
}
.toast {
 background: white;
 padding: 1rem 1.3rem;
 border-radius: 12px;
 box-shadow: 0 6px 18px rgba(0,0,0,0.15);
 color: var(--sidebar);
 font-weight: 600;
 font-size: 1rem;
 display: flex;
 align-items: center;
 gap: 1rem;
 animation: slideInRight 0.35s ease forwards, fadeOut 0.5s ease forwards 4.2s;
 position: relative;
 user-select: none;
 box-sizing: border-box;
}
.toast.toast-success {
 border-left: 6px solid #28a745;
}
.toast.toast-error {
 border-left: 6px solid #dc3545;
}
.toast .bi {
 font-size: 1.4rem;
}
@keyframes slideInRight {
 from {
   transform: translateX(150%);
   opacity: 0;
 }
 to {
   transform: translateX(0);
   opacity: 1;
 }
}
@keyframes fadeOut {
 to {
   opacity: 0;
   transform: translateX(150%);
 }
}
tr.overdue {
 background-color: #ffdddd !important;
 border-left: 5px solid #dc3545;
}
/* Modal styles */
.modal {
 display: none;
 position: fixed;
 z-index: 1000;
 left: 0;
 top: 0;
 width: 100%;
 height: 100%;
 overflow: auto;
 background-color: rgba(0,0,0,0.5);
}
.modal-content {
 background-color: #fefefe;
 margin: 5% auto;
 padding: 20px;
 border: 1px solid #888;
 width: 80%;
 max-width: 800px;
 border-radius: 10px;
 box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2);
 animation: modalFadeIn 0.3s;
 position: fixed;
 top: 50%;
 left: 50%;
 transform: translate(-50%, -50%);
 max-height: 80vh;
 overflow-y: auto;
}
@keyframes modalFadeIn {
 from {opacity: 0; transform: translate(-50%, -60%);}
 to {opacity: 1; transform: translate(-50%, -50%);}
}
.close {
 color: #aaa;
 float: right;
 font-size: 28px;
 font-weight: bold;
 cursor: pointer;
}
.close:hover,
.close:focus {
 color: black;
 text-decoration: none;
}
.modal-header {
 padding: 10px 0;
 border-bottom: 1px solid #eee;
 margin-bottom: 20px;
}
.modal-body {
 padding: 10px 0;
}
.detail-row {
 display: flex;
 margin-bottom: 10px;
 border-bottom: 1px solid #f0f0f0;
 padding-bottom: 10px;
}
.detail-label {
 font-weight: bold;
 width: 200px;
 flex-shrink: 0;
}
.detail-value {
 flex-grow: 1;
 white-space: pre-wrap;
}
/* Comment section styles */
.comments-section {
 margin-top: 20px;
 border-top: 1px solid #eee;
 padding-top: 20px;
}
.comment {
 background-color: #f9f9f9;
 border-radius: 8px;
 padding: 12px;
 margin-bottom: 12px;
 border-left: 4px solid var(--deep-blue);
}
.comment-header {
 display: flex;
 justify-content: space-between;
 margin-bottom: 8px;
 font-weight: bold;
 color: var(--deep-blue);
}
.comment-date {
 color: #777;
 font-size: 0.9em;
}
.comment-text {
 white-space: pre-wrap;
}
.add-comment-form {
 margin-top: 20px;
}
.add-comment-form textarea {
 width: 100%;
 padding: 10px;
 border: 1px solid #ddd;
 border-radius: 4px;
 resize: vertical;
 min-height: 80px;
 margin-bottom: 10px;
}
.add-comment-form button {
 background-color: var(--deep-blue);
 color: white;
 border: none;
 padding: 8px 16px;
 border-radius: 4px;
 cursor: pointer;
}
.add-comment-form button:hover {
 background-color: var(--sidebar);
}
/* Assignment form styles */
.assignment-form {
 margin-top: 20px;
 border-top: 1px solid #eee;
 padding-top: 20px;
}
.form-group {
 margin-bottom: 15px;
}
.form-group label {
 display: block;
 margin-bottom: 5px;
 font-weight: bold;
}
.form-group select,
.form-group input {
 width: 100%;
 padding: 8px;
 border: 1px solid #ddd;
 border-radius: 4px;
}
.form-group select[multiple] {
 height: 120px;
}
.form-actions {
 margin-top: 20px;
 display: flex;
 gap: 10px;
}
.btn-primary {
 background-color: var(--deep-blue);
 color: white;
 border: none;
 padding: 10px 20px;
 border-radius: 4px;
 cursor: pointer;
}
.btn-primary:hover {
 background-color: var(--sidebar);
}
.btn-secondary {
 background-color: #6c757d;
 color: white;
 border: none;
 padding: 10px 20px;
 border-radius: 4px;
 cursor: pointer;
}
.btn-secondary:hover {
 background-color: #5a6268;
}
/* Tabs for modal */
.modal-tabs {
 display: flex;
 border-bottom: 1px solid #ddd;
 margin-bottom: 20px;
}
.modal-tab {
 padding: 10px 20px;
 cursor: pointer;
 border-bottom: 3px solid transparent;
}
.modal-tab.active {
 border-bottom-color: var(--deep-blue);
 font-weight: bold;
}
.modal-tab-content {
 display: none;
}
.modal-tab-content.active {
 display: block;
}
/* Screenreader only helper */
.sr-only {
 position: absolute !important;
 width: 1px; height: 1px;
 padding: 0; margin: -1px;
 overflow: hidden;
 clip: rect(0,0,0,0);
 border: 0;
}
</style>
</head>
<body>
 <!-- Sidebar -->
 <div class="d-flex">
   <aside class="sidebar" role="complementary" aria-label="Sidebar navigation">
     <div class="sidebar-header">
       <div class="company-logo" aria-hidden="true">
         <img src="/images/logo/Telesol_logo.jpeg" alt="Company Logo" />
       </div>
       <div class="company-slogan">Customer Relationship Management</div>
     </div>
     <nav class="nav-menu" role="navigation" aria-label="Main menu">
       <a href="dashboard.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
         <i class="bi bi-list" aria-hidden="true"></i> Menu
       </a>
       <a href="log_ticket.php" class="nav-link">
         <i class="bi bi-journal-plus" aria-hidden="true"></i> Log Ticket
       </a>
       <a href="view_tickets.php" class="nav-link active">
         <i class="bi bi-hdd-network" aria-hidden="true"></i> View Tickets
       </a>
       <a href="log_installations.php" class="nav-link">
         <i class="bi bi-journal-plus" aria-hidden="true"></i> Log Installation
       </a>
       <a href="view_installations.php" class="nav-link">
         <i class="bi bi-hdd-network" aria-hidden="true"></i> View Installations
       </a>
       <a href="customer_experience_dashboard.php" class="nav-link">
         <i class="bi bi-speedometer2" aria-hidden="true"></i> Customer Experience
       </a>
       <a href="field_installations.php" class="nav-link">
         <i class="bi bi-hdd-network" aria-hidden="true"></i> Field Installations
       </a>
     </nav>
     <div class="sidebar-footer">
       <button class="btn btn-back" type="button" onclick="window.history.back()" aria-label="Go back">
         <i class="bi bi-arrow-left" aria-hidden="true"></i> Back
       </button>
       <form action="logout.php" method="POST">
         <button type="submit" class="btn btn-logout">
           <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Logout
         </button>
       </form>
     </div>
   </aside>
 </div>
 <!-- User session display -->
 <div class="user-session" aria-live="polite" aria-atomic="true" aria-label="Currently logged in user">
   Logged in as: <strong><?= esc($username) ?></strong>
 </div>
 <main class="main-content" role="main" aria-label="Tickets Management Dashboard">
   <h1 tabindex="0">View Tickets</h1>
   <nav class="filters" role="tablist" aria-label="Filters by ticket status">
     <?php
     $tabs = ['all' => 'All Issues', 'pending' => 'Pending', 'assigned' => 'Assigned', 'resolved' => 'Resolved'];
     foreach ($tabs as $key => $label):
       $active = $filter === $key ? 'active' : '';
       $ariaSelected = $filter === $key ? 'true' : 'false';
     ?>
       <a href="?filter=<?= esc($key) ?>" role="tab" class="<?= $active ?>" aria-selected="<?= $ariaSelected ?>" tabindex="0"><?= $label ?></a>
     <?php endforeach; ?>
   </nav>
   <!-- Search -->
   <div class="search-bar" role="search">
     <label for="search-input" class="sr-only">Search tickets</label>
     <input id="search-input" type="search" name="search" placeholder="Search tickets by customer, location, issue..." value="<?= esc($search_term) ?>" aria-label="Search tickets" autocomplete="off" />
     <svg aria-hidden="true" focusable="false" viewBox="0 0 24 24"><path d="M21.53 20.47l-4.96-4.96A7.44 7.44 0 0 0 18 10.5 7.5 7.5 0 1 0 10.5 18a7.44 7.44 0 0 0 5.01-1.43l4.96 4.96a.75.75 0 0 0 1.06-1.06zM10.5 16.5a6 6 0 1 1 6-6 6 6 0 0 1-6 6z"/></svg>
   </div>
   <div class="table-wrapper" role="region" aria-label="Tickets table" tabindex="0">
     <table>
       <thead>
         <tr>
           <th scope="col">ID</th>
           <th scope="col">Customer Name</th>
           <th scope="col">Contact Number</th>
           <th scope="col">Location</th>
           <th scope="col">Issue Type</th>
           <th scope="col">Service Type</th>
           <th scope="col">Scheduled Date</th>
           <th scope="col">Assigned Engineer</th>
           <th scope="col">Status</th>
           <th scope="col" aria-label="View Details">Details</th>
         </tr>
       </thead>
       <tbody>
         <?php if ($result->num_rows === 0): ?>
           <tr><td colspan="10" class="no-issues" style="text-align:center; padding: 2rem; font-style: italic; color: #777;">No tickets found.</td></tr>
         <?php else:
           $count = $offset + 1;
           while ($row = $result->fetch_assoc()):
             $isResolved = strtolower($row['issue_status']) === 'resolved';
             $scheduled = ($row['scheduled_datetime'] && $row['scheduled_datetime'] !== '0000-00-00 00:00:00') ? date('Y-m-d H:i', strtotime($row['scheduled_datetime'])) : '–';
             $assigned_persons = $row['assigned_persons'] ?: '–';
             $overdue_class = isOverdue($row) ? 'overdue' : '';
             $comments = $row['comments'] ?: '–';
         ?>
         <tr tabindex="0" class="main-row <?= $overdue_class ?>" data-issue-id="<?= (int)$row['id'] ?>" aria-label="Ticket #<?= (int)$row['id'] ?> for <?= esc($row['customer_name']) ?>">
           <td data-label="ID" scope="row"><?= (int)$row['id'] ?></td>
           <td data-label="Customer Name"><?= esc($row['customer_name']) ?></td>
           <td data-label="Contact Number"><?= esc($row['contact_number']) ?></td>
           <td data-label="Location"><?= esc($row['location']) ?></td>
           <td data-label="Issue Type"><?= esc($row['issue_type']) ?></td>
           <td data-label="Service Type"><?= esc($row['service_type'] ?? '–') ?></td>
           <td data-label="Scheduled Date"><?= esc($scheduled) ?></td>
           <td data-label="Assigned Engineer"><?= esc($assigned_persons) ?></td>
           <td data-label="Status" style="text-align:center;">
             <?php if ($isResolved): ?>
               <i class="bi bi-check-circle-fill status-icon" style="color:#28a745;" title="Resolved" aria-label="Resolved"></i>
             <?php else: ?>
               <i class="bi bi-exclamation-circle-fill status-icon" style="color:#dc3545;" title="Not Resolved" aria-label="Not Resolved"></i>
             <?php endif; ?>
           </td>
           <td data-label="Details">
             <button type="button" class="btn-icon-action btn-view-details" aria-label="View details for ticket #<?= (int)$row['id'] ?>" data-issue-id="<?= (int)$row['id'] ?>"
             data-customer-name="<?= esc($row['customer_name'], ENT_QUOTES) ?>"
             data-contact-number="<?= esc($row['contact_number'], ENT_QUOTES) ?>"
             data-email="<?= esc($row['email'], ENT_QUOTES) ?>"
             data-location="<?= esc($row['location'], ENT_QUOTES) ?>"
             data-issue-type="<?= esc($row['issue_type'], ENT_QUOTES) ?>"
             data-service-type="<?= esc($row['service_type'] ?? '–', ENT_QUOTES) ?>"
             data-scheduled-date="<?= esc($scheduled, ENT_QUOTES) ?>"
             data-assigned-engineer="<?= esc($assigned_persons, ENT_QUOTES) ?>"
             data-status="<?= $isResolved ? 'Resolved' : 'Not Resolved' ?>"
             data-comments="<?= esc($comments, ENT_QUOTES) ?>"
             >
               <i class="bi bi-eye"></i>
             </button>
           </td>
         </tr>
         <?php endwhile; endif; ?>
       </tbody>
     </table>
   </div>
   <?php if ($total_pages > 1): ?>
     <nav role="navigation" aria-label="Pagination navigation" class="pagination" tabindex="0">
       <?php if ($page > 1): ?>
         <a href="?filter=<?= esc($filter) ?>&search=<?= urlencode($search_term) ?>&page=<?= $page-1 ?>" aria-label="Previous page">&laquo; Prev</a>
       <?php endif; ?>
       <?php
       $max_links = 5;
       $start = max(1, $page - intval($max_links / 2));
       $end = min($total_pages, $start + $max_links - 1);
       if ($start > 1) echo '<a href="?filter='.esc($filter).'&search='.urlencode($search_term).'&page=1" aria-label="Page 1">1</a>' . ($start > 2 ? '<span aria-hidden="true">…</span>' : '');
       for ($i = $start; $i <= $end; $i++):
         $active = ($i === $page) ? 'active' : '';
         $aria_current = ($i === $page) ? 'page' : 'false';
       ?>
         <a href="?filter=<?= esc($filter) ?>&search=<?= urlencode($search_term) ?>&page=<?= $i ?>" class="<?= $active ?>" aria-current="<?= $aria_current ?>" aria-label="Page <?= $i ?>"><?= $i ?></a>
       <?php endfor;
       if ($end < $total_pages) {
         if ($end < $total_pages - 1) echo '<span aria-hidden="true">…</span>';
         echo '<a href="?filter='.esc($filter).'&search='.urlencode($search_term).'&page='.$total_pages.'" aria-label="Page '.$total_pages.'">'.$total_pages.'</a>';
       }
       ?>
       <?php if ($page < $total_pages): ?>
         <a href="?filter=<?= esc($filter) ?>&search=<?= urlencode($search_term) ?>&page=<?= $page+1 ?>" aria-label="Next page">Next &raquo;</a>
       <?php endif; ?>
     </nav>
   <?php endif; ?>
 </main>
 <!-- Modal for displaying full details -->
 <div id="detailsModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" tabindex="-1">
   <div class="modal-content">
     <button class="close" aria-label="Close details modal">&times;</button>
     <div class="modal-header">
       <h2 id="modalTitle">Ticket Details</h2>
     </div>
     <div class="modal-body" id="modalDetails">
       <!-- Tabs for different sections -->
       <div class="modal-tabs">
         <div class="modal-tab active" data-tab="details">Details</div>
         <div class="modal-tab" data-tab="comments">Comments</div>
         <div class="modal-tab" data-tab="assignment">Assignment</div>
       </div>
       
       <!-- Details Tab -->
       <div class="modal-tab-content active" id="details-tab">
         <!-- Details will be populated here by JavaScript -->
       </div>
       
       <!-- Comments Tab -->
       <div class="modal-tab-content" id="comments-tab">
         <div class="comments-section">
           <div id="comments-list">
             <!-- Comments will be populated here by JavaScript -->
           </div>
           <div class="add-comment-form">
             <h3>Add Comment</h3>
             <textarea id="new-comment" placeholder="Enter your comment here..."></textarea>
             <button type="button" id="submit-comment">Submit Comment</button>
           </div>
         </div>
       </div>
       
       <!-- Assignment Tab -->
       <div class="modal-tab-content" id="assignment-tab">
         <div class="assignment-form">
           <form id="assignment-form">
             <input type="hidden" id="assign-issue-id" name="issue_id">
             <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
             
             <div class="form-group">
               <label for="assigned_department">Department:</label>
               <select id="assigned_department" name="assigned_department" required>
                 <option value="">Select Department</option>
                 <?php foreach ($department_persons as $dept => $persons): ?>
                   <option value="<?= esc($dept) ?>"><?= esc($dept) ?></option>
                 <?php endforeach; ?>
               </select>
             </div>
             
             <div class="form-group">
               <label for="assigned_persons">Assign Engineers:</label>
               <select id="assigned_persons" name="assigned_persons[]" multiple required>
                 <!-- Options will be populated based on department selection -->
               </select>
             </div>
             
             <div class="form-group">
               <label for="scheduled_datetime">Schedule Date/Time:</label>
               <input type="datetime-local" id="scheduled_datetime" name="scheduled_datetime" required>
             </div>
             
             <div class="form-group">
               <label for="resolved_by_deadline">Resolve By Deadline (Optional):</label>
               <input type="datetime-local" id="resolved_by_deadline" name="resolved_by_deadline">
             </div>
             
             <div class="form-group">
               <label for="comments">Comments:</label>
               <textarea id="comments" name="comments" rows="4"></textarea>
             </div>
             
             <div class="form-actions">
               <button type="submit" class="btn-primary">Save Assignment</button>
               <button type="button" class="btn-secondary" id="toggle-status-btn">Toggle Status</button>
             </div>
           </form>
         </div>
       </div>
     </div>
   </div>
 </div>
 <!-- Toast notifications container -->
 <div id="toast-container" aria-live="polite" aria-atomic="true" aria-relevant="additions"></div>
<script>
(() => {
'use strict';
const csrfToken = '<?= $csrf_token ?>';
const departmentPersons = <?= json_encode($department_persons, JSON_UNESCAPED_UNICODE) ?>;
let currentIssueId = null;

// Toast notifications
const toastContainer = document.getElementById('toast-container');
function createToast(message, type = 'success') {
   const toast = document.createElement('div');
   toast.className = `toast toast-${type}`;
   toast.setAttribute('role', 'alert');
   toast.setAttribute('aria-live', 'assertive');
   toast.setAttribute('aria-atomic', 'true');
   toast.tabIndex = 0;
   const icon = document.createElement('i');
   icon.className = `bi ${type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle-fill'}`;
   icon.setAttribute('aria-hidden', 'true');
   toast.appendChild(icon);
   const text = document.createElement('span');
   text.textContent = message;
   toast.appendChild(text);
   toastContainer.appendChild(toast);
   toast.focus();
   setTimeout(() => {
     toast.style.animation = 'fadeOut 0.5s forwards';
     setTimeout(() => toast.remove(), 500);
   }, 4000);
}
// Debounce helper
function debounce(func, wait) {
   let timeout;
   return function(...args) {
     clearTimeout(timeout);
     timeout = setTimeout(() => func.apply(this, args), wait);
   };
}
// Modal functionality
const modal = document.getElementById('detailsModal');
const modalDetails = document.getElementById('modalDetails');
const closeBtn = modal.querySelector('.close');
// Close modal when clicking on X
closeBtn.addEventListener('click', () => {
   modal.style.display = 'none';
   modal.setAttribute('aria-hidden', 'true');
});
// Close modal when clicking outside
window.addEventListener('click', (event) => {
   if (event.target === modal) {
     modal.style.display = 'none';
     modal.setAttribute('aria-hidden', 'true');
   }
});
// Tab functionality
const tabs = document.querySelectorAll('.modal-tab');
tabs.forEach(tab => {
  tab.addEventListener('click', () => {
    // Remove active class from all tabs and contents
    document.querySelectorAll('.modal-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.modal-tab-content').forEach(c => c.classList.remove('active'));
    
    // Add active class to clicked tab and corresponding content
    tab.classList.add('active');
    const tabId = tab.getAttribute('data-tab');
    document.getElementById(`${tabId}-tab`).classList.add('active');
  });
});
// Load comments for an issue
function loadComments(issueId) {
  fetch(`get_comments.php?issue_id=${issueId}`)
    .then(response => response.json())
    .then(data => {
      const commentsList = document.getElementById('comments-list');
      commentsList.innerHTML = '';
      
      if (data.success && data.comments.length > 0) {
        data.comments.forEach(comment => {
          const commentEl = document.createElement('div');
          commentEl.className = 'comment';
          commentEl.innerHTML = `
            <div class="comment-header">
              <span class="comment-author">${escapeHtml(comment.author)}</span>
              <span class="comment-date">${formatDate(comment.created_at)}</span>
            </div>
            <div class="comment-text">${escapeHtml(comment.comment)}</div>
          `;
          commentsList.appendChild(commentEl);
        });
      } else {
        commentsList.innerHTML = '<p>No comments yet.</p>';
      }
    })
    .catch(error => {
      console.error('Error loading comments:', error);
      document.getElementById('comments-list').innerHTML = '<p>Error loading comments.</p>';
    });
}
// Format date for display
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleString();
}
// Escape HTML special chars
function escapeHtml(text) {
  const map = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  };
  return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}
// View details button click handler
document.querySelectorAll('.btn-view-details').forEach(btn => {
   btn.addEventListener('click', () => {
     currentIssueId = btn.getAttribute('data-issue-id');
     // Gather all data attributes safely
     const customerName = btn.getAttribute('data-customer-name');
     const contactNumber = btn.getAttribute('data-contact-number');
     const email = btn.getAttribute('data-email');
     const location = btn.getAttribute('data-location');
     const issueType = btn.getAttribute('data-issue-type');
     const serviceType = btn.getAttribute('data-service-type');
     const scheduledDate = btn.getAttribute('data-scheduled-date');
     const assignedEngineer = btn.getAttribute('data-assigned-engineer');
     const status = btn.getAttribute('data-status');
     const comments = btn.getAttribute('data-comments') || '–';
     
     // Create HTML for modal content with comments included
     const detailsHTML = `
       <div class="detail-row">
         <div class="detail-label">ID:</div>
         <div class="detail-value">${escapeHtml(currentIssueId)}</div>
       </div>
       <div class="detail-row">
         <div class="detail-label">Customer Name:</div>
         <div class="detail-value">${escapeHtml(customerName)}</div>
       </div>
       <div class="detail-row">
         <div class="detail-label">Contact Number:</div>
         <div class="detail-value">${escapeHtml(contactNumber)}</div>
       </div>
       <div class="detail-row">
         <div class="detail-label">Email:</div>
         <div class="detail-value">${escapeHtml(email)}</div>
       </div>
       <div class="detail-row">
         <div class="detail-label">Location:</div>
         <div class="detail-value">${escapeHtml(location)}</div>
       </div>
       <div class="detail-row">
         <div class="detail-label">Issue Type:</div>
         <div class="detail-value">${escapeHtml(issueType)}</div>
       </div>
       <div class="detail-row">
         <div class="detail-label">Service Type:</div>
         <div class="detail-value">${escapeHtml(serviceType)}</div>
       </div>
       <div class="detail-row">
         <div class="detail-label">Scheduled Date:</div>
         <div class="detail-value">${escapeHtml(scheduledDate)}</div>
       </div>
       <div class="detail-row">
         <div class="detail-label">Assigned Engineer:</div>
         <div class="detail-value">${escapeHtml(assignedEngineer)}</div>
       </div>
       <div class="detail-row">
         <div class="detail-label">Status:</div>
         <div class="detail-value">${escapeHtml(status)}</div>
       </div>
       <div class="detail-row">
         <div class="detail-label">Comments:</div>
         <div class="detail-value" style="white-space: pre-wrap;">${escapeHtml(comments)}</div>
       </div>
     `;
     
     // Populate and show modal
     document.getElementById('details-tab').innerHTML = detailsHTML;
     document.getElementById('assign-issue-id').value = currentIssueId;
     
     // Load comments for this issue
     loadComments(currentIssueId);
     
     // Show modal
     modal.style.display = 'block';
     modal.removeAttribute('aria-hidden');
     modal.focus();
   });
});
// Department change handler
document.getElementById('assigned_department').addEventListener('change', function() {
  const department = this.value;
  const personsSelect = document.getElementById('assigned_persons');
  
  // Clear existing options
  personsSelect.innerHTML = '';
  
  if (department && departmentPersons[department]) {
    // Add options for persons in selected department
    departmentPersons[department].forEach(person => {
      const option = document.createElement('option');
      option.value = person;
      option.textContent = person;
      personsSelect.appendChild(option);
    });
  }
});
// Submit comment handler
document.getElementById('submit-comment').addEventListener('click', function() {
  const commentText = document.getElementById('new-comment').value.trim();
  
  if (!commentText) {
    createToast('Please enter a comment.', 'error');
    return;
  }
  
  const formData = new FormData();
  formData.append('issue_id', currentIssueId);
  formData.append('comment', commentText);
  formData.append('csrf_token', csrfToken);
  
  fetch('?action=add_comment', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      createToast(data.message, 'success');
      document.getElementById('new-comment').value = '';
      loadComments(currentIssueId); // Reload comments
    } else {
      createToast(data.message, 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    createToast('Error adding comment.', 'error');
  });
});
// Assignment form submission
document.getElementById('assignment-form').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const formData = new FormData(this);
  
  fetch('?action=assign_schedule', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      createToast(data.message, 'success');
      // Close modal and refresh page to see changes
      setTimeout(() => {
        modal.style.display = 'none';
        location.reload();
      }, 1500);
    } else {
      createToast(data.message, 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    createToast('Error updating assignment.', 'error');
  });
});
// Toggle status button
document.getElementById('toggle-status-btn').addEventListener('click', function() {
  if (!currentIssueId) return;
  
  const formData = new FormData();
  formData.append('issue_id', currentIssueId);
  formData.append('csrf_token', csrfToken);
  formData.append('new_status', 'Resolved'); // Toggle to resolved
  
  fetch('?action=toggle_status', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      createToast(data.message, 'success');
      // Close modal and refresh page to see changes
      setTimeout(() => {
        modal.style.display = 'none';
        location.reload();
      }, 1500);
    } else {
      createToast(data.message, 'error');
    }
  })
  .catch(error => {
    console.error('Error:', error);
    createToast('Error toggling status.', 'error');
  });
});
// Debounced live search redirect
const searchInput = document.getElementById('search-input');
if (searchInput) {
   searchInput.addEventListener('input', debounce(() => {
     const val = searchInput.value.trim();
     const url = new URL(window.location);
     if (val) url.searchParams.set('search', val);
     else url.searchParams.delete('search');
     url.searchParams.set('page', '1'); // reset page on search
     window.location.href = url.toString();
   }, 500));
}
})();
</script>
</body>
</html>
<?php
$stmt->close();
$conn->close();
?>