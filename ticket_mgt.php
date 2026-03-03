<?php
session_start();

// Get logged-in username or default to 'User'
$username = $_SESSION['username'] ?? 'User';

// Generate CSRF token for AJAX requests if not already present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Database connection configuration
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "telesol crm";

// Create connection with error handling
$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

// Department-wise engineers mapping
$department_persons = [
    'Admin' => ['Patience', 'Ruth'],
    'System' => ['Nii Djan', 'Pierrette'],
    'Sales' => ['Florence', 'Kingsley', 'Ayivor', 'Vicentia', 'Joel', 'Jesse'],
    'Technology' => ['John Hagan', 'Isaac Ofosu-Afful', 'Sylvester Horsu', 'Innocent Odikro', 'Joshua Avinu'],
];
// Flatten all engineers for validation if needed
$all_engineers = array_merge(...array_values($department_persons));

// Utility functions
/**
 * Escape output for HTML
 */
function esc(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Determine if a ticket is overdue
 * @param array $ticket Row from tickets table
 * @return bool
 */
function isOverdue(array $ticket): bool {
    if (strtolower($ticket['issue_status']) === 'resolved') {
        return false;
    }
    if (empty($ticket['resolved_by_deadline']) || $ticket['resolved_by_deadline'] === '0000-00-00 00:00:00') {
        return false;
    }
    return strtotime($ticket['resolved_by_deadline']) < time();
}

// --- Handle AJAX Requests ---

// Content-Type header for JSON API responses
function sendJsonHeader(): void {
    header('Content-Type: application/json; charset=utf-8');
}

// Validate CSRF token posted
function checkCsrfToken(array $post_data, string $session_token): void {
    if (!isset($post_data['csrf_token']) || !hash_equals($session_token, $post_data['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

// AJAX: Update ticket details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'update_ticket') {
    sendJsonHeader();
    checkCsrfToken($_POST, $_SESSION['csrf_token']);

    $issue_id = $_POST['issue_id'] ?? null;
    $customer_name = trim($_POST['customer_name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $issue_type = trim($_POST['issue_type'] ?? '');
    $service_type = trim($_POST['service_type'] ?? '');
    $assigned_department = trim($_POST['assigned_department'] ?? '');
    $assigned_persons = $_POST['assigned_persons'] ?? [];
    $scheduled_datetime = $_POST['scheduled_datetime'] ?? null;
    $comments = trim($_POST['comments'] ?? '');
    $resolved_by_deadline = $_POST['resolved_by_deadline'] ?? null;
    $issue_status = trim($_POST['issue_status'] ?? '');

    if (!$issue_id || !is_numeric($issue_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid issue ID.']);
        exit;
    }
    if ($customer_name === '' || $contact_number === '' || $email === '' || $location === '' || $issue_type === '') {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
        exit;
    }

    // Convert array of assigned persons to comma-separated string
    $assigned_persons_str = '';
    if (is_array($assigned_persons) && count($assigned_persons) > 0) {
        $assigned_persons_str = implode(', ', $assigned_persons);
    }

    $stmt = $conn->prepare("UPDATE tickets SET 
        customer_name=?, 
        contact_number=?, 
        email=?, 
        location=?, 
        issue_type=?, 
        service_type=?,
        assigned_department=?,
        assigned_persons=?,
        scheduled_datetime=?,
        comments=?,
        resolved_by_deadline=?,
        issue_status=?
        WHERE id=?"
    );
    
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: could not prepare statement.']);
        exit;
    }

    $stmt->bind_param(
        'ssssssssssssi', 
        $customer_name, 
        $contact_number, 
        $email, 
        $location, 
        $issue_type, 
        $service_type,
        $assigned_department,
        $assigned_persons_str,
        $scheduled_datetime,
        $comments,
        $resolved_by_deadline,
        $issue_status,
        $issue_id
    );
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Ticket updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update ticket.']);
    }
    $stmt->close();
    $conn->close();
    exit;
}

// AJAX: Get ticket details for modal
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_ticket_details') {
    sendJsonHeader();

    $issue_id = $_GET['issue_id'] ?? null;
    if (!$issue_id || !is_numeric($issue_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid issue ID.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM tickets WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: could not prepare statement.']);
        exit;
    }
    $stmt->bind_param('i', $issue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($ticket = $result->fetch_assoc()) {
        // Format dates for display
        $ticket['scheduled_datetime_formatted'] = ($ticket['scheduled_datetime'] && $ticket['scheduled_datetime'] !== '0000-00-00 00:00:00') 
            ? date('Y-m-d\TH:i', strtotime($ticket['scheduled_datetime'])) 
            : '';
        
        $ticket['resolved_by_deadline_formatted'] = ($ticket['resolved_by_deadline'] && $ticket['resolved_by_deadline'] !== '0000-00-00 00:00:00')
            ? date('Y-m-d\TH:i', strtotime($ticket['resolved_by_deadline']))
            : '';
        
        $ticket['created_at_formatted'] = date('Y-m-d H:i', strtotime($ticket['created_at']));
        
        echo json_encode(['success' => true, 'ticket' => $ticket]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Ticket not found.']);
    }
    
    $stmt->close();
    $conn->close();
    exit;
}

// AJAX: Get comments for a ticket
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_comments') {
    sendJsonHeader();

    $issue_id = $_GET['issue_id'] ?? null;
    if (!$issue_id || !is_numeric($issue_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid issue ID.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT author, comment, created_at FROM comments WHERE ticket_id = ? ORDER BY created_at DESC");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: could not prepare statement.']);
        exit;
    }
    $stmt->bind_param('i', $issue_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }

    echo json_encode(['success' => true, 'comments' => $comments]);

    $stmt->close();
    $conn->close();
    exit;
}

// AJAX: Add a new comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'add_comment') {
    sendJsonHeader();
    checkCsrfToken($_POST, $_SESSION['csrf_token']);

    $issue_id = $_POST['issue_id'] ?? null;
    $comment = trim($_POST['comment'] ?? '');
    $author = $_SESSION['username'] ?? 'User';

    if (!$issue_id || !is_numeric($issue_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid issue ID.']);
        exit;
    }
    if ($comment === '') {
        echo json_encode(['success' => false, 'message' => 'Comment cannot be empty.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO comments (ticket_id, author, comment) VALUES (?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: could not prepare statement.']);
        exit;
    }

    $stmt->bind_param('iss', $issue_id, $author, $comment);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Comment added successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add comment.']);
    }
    $stmt->close();
    $conn->close();
    exit;
}

// AJAX: Toggle issue status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'toggle_status') {
    sendJsonHeader();
    checkCsrfToken($_POST, $_SESSION['csrf_token']);

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
        echo json_encode(['success' => false, 'message' => 'Database error: could not prepare statement.']);
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

// AJAX: Delete ticket and related comments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'delete_ticket') {
    sendJsonHeader();
    checkCsrfToken($_POST, $_SESSION['csrf_token']);

    $issue_id = $_POST['issue_id'] ?? null;

    if (!$issue_id || !is_numeric($issue_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid issue ID.']);
        exit;
    }

    // Delete ticket
    $stmt = $conn->prepare("DELETE FROM tickets WHERE id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: could not prepare delete statement.']);
        exit;
    }

    $stmt->bind_param('i', $issue_id);
    if ($stmt->execute()) {
        // Also delete associated comments
        $stmt->close();

        $stmtC = $conn->prepare("DELETE FROM comments WHERE ticket_id = ?");
        if ($stmtC) {
            $stmtC->bind_param('i', $issue_id);
            $stmtC->execute();
            $stmtC->close();
        }
        echo json_encode(['success' => true, 'message' => 'Ticket and associated comments deleted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete ticket.']);
    }
    $stmt->close();
    $conn->close();
    exit;
}

// --- Filters, search, pagination ---
// Valid filter options
$valid_filters = ['all', 'pending', 'assigned', 'resolved'];
$filter = strtolower($_GET['filter'] ?? 'all');
if (!in_array($filter, $valid_filters, true)) {
    $filter = 'all';
}

// Search term input, trimmed
$search_term = trim($_GET['search'] ?? '');

// Pagination parameters
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build WHERE clauses based on filter and search
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
    case 'all':
    default:
        // No additional filter clause for 'all'
        break;
}
if ($search_term !== '') {
    $escaped_search = $conn->real_escape_string($search_term);
    $where_clauses[] = "(customer_name LIKE '%$escaped_search%' OR contact_number LIKE '%$escaped_search%' OR email LIKE '%$escaped_search%' OR location LIKE '%$escaped_search%' OR issue_type LIKE '%$escaped_search%')";
}
$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Get total number of tickets for pagination
$count_sql = "SELECT COUNT(*) FROM tickets $where_sql";
$stmt_count = $conn->prepare($count_sql);
if ($stmt_count === false) {
    die('Failed to prepare count query.');
}
$stmt_count->execute();
$stmt_count->bind_result($total_results);
$stmt_count->fetch();
$stmt_count->close();

// Get tickets with limit and offset
$data_sql = "SELECT id, customer_name, contact_number, email, location, issue_type, scheduled_datetime, assigned_department, assigned_persons, issue_status, comments, created_at, logged_by, resolved_by_deadline, service_type FROM tickets $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($data_sql);
if ($stmt === false) {
    die('Failed to prepare select query.');
}
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();

$total_pages = $total_results > 0 ? ceil($total_results / $limit) : 1;

?>

<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
    <meta charset="UTF-8" />
    <title>Telesol CRM - View Tickets</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" />
</head>

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
            padding: 1rem 1rem;
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
            height: 35px;
            font-size: 0.9rem;
            border-radius: 3px;
            outline-offset: 2px;
            border: 1px solid var(--dark);
            padding: 0.5rem 0.5rem 0.5rem 1.9rem;
        }

        .navbar-search input::placeholder {
            opacity: 1;
            font-size: 14px;
            padding-top: -5px;
            color: var(--dark);
            padding-bottom: -3px;
        }

        .navbar-search svg {
            position: absolute;
            top: 50%;
            left: 0.4rem;
            width: 20px;
            height: 20px;
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
            gap: 0.4rem;
            flex-wrap: wrap;
            padding: 0.8rem 1rem 0;
            margin-left: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.7rem 1.6rem;
            background-color: var(--dark);
            border-radius: 3px;
            color: var(--white);
            font-weight: 500;
            text-decoration: none;
            transition: background-color var(--transition), box-shadow var(--transition);
            cursor: pointer;
            user-select: none;
        }

        .quick-action-btn i {
            font-size: 1.2rem;
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

        /* New Ticket button specific styling */
        .quick-action-btn.new-ticket {
            background-color: var(--success);
            color: var(--white);
        }

        .quick-action-btn.new-ticket:hover {
            background-color: #00a040;
            box-shadow: 0 3px 7px rgba(0, 180, 75, 0.2);
        }

        /* Table container */
        .table-container {
            margin: 0.6rem;
            margin-bottom: 1rem;
            margin-left: 1.5rem;
            margin-right: 1.5rem;
            background-color: var(--white);
            border-radius: 2px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
            overflow-x: auto;
            height: 600px;
        }

        table {
            width: 100%;
            height: 100%;
            font-weight: 500;
            font-size: 1rem;
            color: var(--dark);
            border-collapse: collapse;
            min-width: 900px;
        }

        thead tr {
            background-color: var(--primary);
            color: var(--white);
            position: sticky;
            top: 0;
            bottom: 8rem;
            z-index: 5;
        }

        th {
            padding: 0.8rem;
            text-align: center;
            font-weight: 500;
            font-size: 0.9rem;
            text-transform: initial;
            color: var(--white);
        }

        td {
            padding: 0.03rem;
            border-bottom: 1px solid var(--light-gray);
            text-align: left;
            vertical-align: middle;
            font-size: 0.9rem;
            font-weight: 500;
        }

        tbody tr:hover:not(.no-data) {
            background-color: #f0f4f8;
        }

        tbody tr.no-data td {
            text-align: center;
            font-style: italic;
            color: var(--dark);
        }

        tbody tr.overdue {
            background-color: #f8d7da;
        }

        /* Status badges */
        .status-badge {
            font-size: 0.9rem;
            text-align: center;
            align-items: center;
            border-radius: 50px;
            display: inline-block;
            padding: 0.4rem 0.4rem;
            text-transform: uppercase;
            min-width: 80px;
        }

        .status-badge.resolved {
            color: white;
            font-size: 0.8rem;
            font-weight: 500;
            background-color: var(--success);
        }

        .status-badge.pending {
            color: white;
            font-size: 0.8rem;
            font-weight: 500;
            background-color: var(--warning);
        }

        .status-badge.overdue {
            background-color: #f8d7da;
            color: #dd0016ff;
        }

        /* Action buttons (edit, view, delete) */
        .actions {
            white-space: nowrap;
            text-align: center;
            gap: 0.01rem;
        }

        .btn-icon-action {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--primary);
            cursor: pointer;
            border-radius: var(--border-radius);
            padding: 0.1rem 0.1rem;
            margin-right: 0.15rem;
            transition: color var(--transition), background-color var(--transition);
        }

        .btn-icon-action:hover,
        .btn-icon-action:focus {
            color: var(--primary);
            /* background-color: #eef6ff; */
            outline: none;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.4rem;
            flex-wrap: wrap;
            padding-top: 0.4rem;
        }

        .pagination a,
        .pagination span {
            display: inline-block;
            padding: 0.5rem 0.5rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--light-gray);
            color: var(--dark);
            text-decoration: none;
            min-width: 40px;
            text-align: center;
            font-weight: 400;
            transition: background-color var(--transition), color var(--transition);
        }

        .pagination a:hover {
            background-color: var(--primary);
            color: var(--white);
        }

        .pagination a.active {
            background-color: var(--primary);
            color: var(--white);
            pointer-events: none;
        }

        .pagination span {
            color: var(--primary);
            pointer-events: none;
            user-select: none;
        }

        /* Floating add button bottom right */
        .floating-action {
            position: fixed;
            top: 52rem;
            bottom: 0.4rem;
            right: 2rem;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--dark);
            color: var(--white);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            z-index: 900;
            transition: background-color var(--transition);
            user-select: none;
        }

        .floating-action:hover,
        .floating-action:focus {
            background-color: var(--secondary);
            outline: none;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1000;
            background-color: rgba(0,0,0,0.45);
            backdrop-filter: blur(5px);
            overflow-y: auto;
            padding: 1rem 1rem;
        }

        .modal-content {
            background: var(--white);
            border-radius: var(--border-radius);
            max-width: 900px;
            margin: auto;
            padding: 1.5rem 2rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            animation: modalFadeIn 0.3s ease forwards;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .close {
            position: absolute;
            top: 0.6rem;
            right: 1.5rem;
            font-size: 2rem;
            font-weight: 600;
            background-color: var(--white);
            cursor: pointer;
            user-select: none;
            transition: color var(--transition);
            border: none;
        }

        .close:hover,
        .close:focus {
            color: var(--danger);
            outline: none;
        }

        .modal-header {
            margin-bottom: 1rem;
            border-bottom: 1.4px solid var(--primary-dark);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-weight: 500;
            color: var(--primary-dark);
            font-size: 1rem;
            margin: 0;
        }

        .modal-edit-toggle {
            background: var(--secondary);
            color: white;
            border: none;
            padding: 0.4rem 1rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            transition: background-color var(--transition);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .modal-edit-toggle:hover {
            background-color: var(--primary);
        }

        /* Two-column layout for ticket details */
        .modal-columns {
            display: flex;
            gap: 1.2rem;
            margin-top: 1rem;
        }

        .details-column {
            flex: 1;
            min-width: 0;
        }

        .comments-column {
            flex: 1;
            min-width: 0;
            border-left: 1px solid var(--light-gray);
            padding-left: 1.5rem;
        }

        /* Detail rows inside modal */
        .detail-row {
            display: flex;
            margin-bottom: 0.5rem;
            align-items: flex-start;
        }

        .detail-label {
            font-weight: 500;
            width: 160px;
            color: var(--dark);
            flex-shrink: 0;
            padding-top: 0.2rem;
        }

        .detail-value {
            flex-grow: 1;
            white-space: pre-wrap;
            word-wrap: break-word;
            color: var(--dark);
            padding: 0.2rem;
            border-radius: 4px;
            background: #f8f9fa;
            min-height: 30px;
            display: flex;
            align-items: center;
        }

        /* Edit mode styles */
        .edit-mode .detail-value {
            display: none;
        }

        .edit-mode .detail-edit {
            display: block;
        }

        .detail-edit {
            display: none;
            flex-grow: 1;
        }

        .detail-edit input,
        .detail-edit select,
        .detail-edit textarea {
            width: 100%;
            padding: 0.3rem 0.3rem;
            border: 1px solid var(--light-gray);
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 0.9rem;
            background: white;
        }

        .detail-edit input:focus,
        .detail-edit select:focus,
        .detail-edit textarea:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .detail-edit textarea {
            min-height: 80px;
            resize: vertical;
        }

        .detail-edit select[multiple] {
            height: 120px;
        }

        .edit-actions {
            display: none;
            margin-top: 2rem;
            padding-top: 1.3rem;
            border-top: 1px solid var(--light-gray);
            justify-content: flex-end;
            gap: 0.8rem;
        }

        .edit-mode .edit-actions {
            display: flex;
        }

        .btn-save {
            background-color: var(--success);
            color: white;
            border: none;
            padding: 0.7rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: background-color var(--transition);
        }

        .btn-save:hover {
            background-color: #00a040;
        }

        .btn-cancel {
            background-color: var(--gray);
            color: white;
            border: none;
            padding: 0.7rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: background-color var(--transition);
        }

        .btn-cancel:hover {
            background-color: #7f8c8d;
        }

        /* Comments section */
        .comments-section {
            margin-top: 0.3rem;
        }

        .comments-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .comments-header h3 {
            color: var(--primary-dark);
            font-weight: 600;
            margin: 0;
        }

        .comment {
            background-color: #f1f5f9;
            border-left: 4px solid var(--primary);
            border-radius: var(--border-radius);
            padding: 0.75rem 1rem;
            margin-bottom: 0.75rem;
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            font-weight: 600;
            color: var(--primary-dark);
            font-size: 0.7rem;
            margin-top: -0.7rem;
            margin-bottom: 0.1rem;
        }

        .comment-date {
            color: var(--gray);
            font-size: 0.8rem;
            font-style: italic;
        }

        .comment-text {
            white-space: pre-wrap;
            font-size: 0.85rem;
            color: var(--dark);
            line-height: 1.4;
        }

        /* Add comment form */
        .add-comment-form {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--light-gray);
        }

        .add-comment-form h4 {
            margin-bottom: 0.5rem;
            color: var(--primary-dark);
            font-weight: 500;
        }

        .add-comment-form textarea {
            width: 100%;
            min-height: 80px;
            padding: 0.7rem 1rem;
            border-radius: var(--border-radius);
            border: 1px solid var(--light-gray);
            font-family: inherit;
            resize: vertical;
            transition: border-color var(--transition), box-shadow var(--transition);
            font-size: 0.9rem;
        }

        .add-comment-form textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 5px rgba(17, 90, 142, 0.3);
        }

        .add-comment-form button {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 600;
            transition: background-color var(--transition);
            display: flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }

        .add-comment-form button:hover,
        .add-comment-form button:focus {
            background-color: var(--primary-dark);
            outline: none;
        }

        /* Scrollable comments */
        .comments-container {
            max-height: 300px;
            overflow-y: auto;
            padding-right: 0.5rem;
        }

        /* Empty state for comments */
        .no-comments {
            text-align: center;
            padding: 2rem;
            color: var(--gray);
            font-style: italic;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .modal-columns {
                flex-direction: column;
                gap: 1.5rem;
            }
            
            .comments-column {
                border-left: none;
                border-top: 1px solid var(--light-gray);
                padding-left: 0;
                padding-top: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .detail-label {
                width: 100%;
                margin-bottom: 0.3rem;
                padding-top: 0;
            }
            
            .detail-value,
            .detail-edit {
                width: 100%;
            }
            
            .modal-content {
                padding: 1rem;
                max-width: 95%;
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
                <li><a href="ticket_mgt.php" class="active"><i class="bi bi-ticket-detailed"></i> Tickets</a></li>
                <li><a href="installation_mgt.php"><i class="bi bi-wrench"></i> Installations</a></li>
                <li><a href="customer_experience_dashboard.php"><i class="bi bi-people"></i> Customer Experience</a></li>
                <li><a href="cs_internal_request_form.php"><i class="bi bi-wrench"></i> Internal Requisition</a></li>
                <li><a href="report.php"><i class="bi bi-bar-chart"></i> Reports</a></li>
                <li><a href="#"><i class="bi bi-arrow-left-circle"></i> Back</a></li>
                <li><a href="login.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main class="main-content" role="main" aria-label="Ticket management content">
        <!-- Header Bar -->
        <header class="header" role="banner">
            <div class="navbar-search" role="search" aria-label="Search tickets">
                <input
                    type="search"
                    name="search"
                    id="searchInput"
                    placeholder="Search tickets by customer, location, issue..."
                    value="<?= esc($search_term) ?>"
                    aria-describedby="searchHelp"
                    autocomplete="off"
                    aria-autocomplete="list"
                    aria-controls="searchResults"
                />
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.53 20.47l-4.96-4.96A7.44 7.44 0 0 0 18 10.5 7.5 7.5 0 1 0 10.5 18a7.44 7.44 0 0 0 5.01-1.43l4.96 4.96a.75.75 0 0 0 1.06-1.06zM10.5 16.5a6 6 0 1 1 6-6 6 6 0 0 1-6 6z"/></svg>
            </div>
            <div class="user-session" aria-label="User session info">
                <i class="bi bi-person-circle" aria-hidden="true"></i>
                <span>Logged in as: <strong><?= esc($username) ?></strong></span>
            </div>
        </header>

        <!-- Quick action tabs -->
        <nav class="quick-actions" role="navigation" aria-label="Filter ticket status">
            <?php
            $tabs = ['all' => 'All Issues', 'pending' => 'Pending', 'assigned' => 'Assigned', 'resolved' => 'Resolved'];
            foreach ($tabs as $key => $label):
                $active = $filter === $key ? 'active' : '';
                $icon = match($key){
                    'all' => 'circle',
                    'pending' => 'clock',
                    'assigned' => 'person',
                    'resolved' => 'check-circle',
                    default => 'circle'
                };
            ?>
            <a href="?filter=<?= esc($key) ?>&search=<?= urlencode($search_term) ?>" class="quick-action-btn <?= $active ?>" role="link" aria-current="<?= $active ? 'page' : 'false' ?>">
                <i class="bi bi-<?= esc($icon) ?>" aria-hidden="true"></i> <?= esc($label) ?>
            </a>
            <?php endforeach; ?>
            
            <!-- NEW TICKET BUTTON ADDED HERE -->
            <a href="log_ticket.php" class="quick-action-btn new-ticket" role="link" aria-label="Create new ticket">
                <i class="bi bi-plus-circle" aria-hidden="true"></i> New Ticket
            </a>
        </nav>

        <!-- Tickets Table -->
        <section aria-label="Tickets list" class="table-container" tabindex="0">
            <table aria-describedby="ticketsSummary">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Contact</th>
                        <th scope="col">Location</th>
                        <th scope="col">Issue Type</th>
                        <th scope="col">Scheduled</th>
                        <th scope="col">Assigned To</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-center" aria-label="Actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result->num_rows === 0): ?>
                    <tr class="no-data">
                        <td colspan="9" style="text-align:center">
                            <i class="bi bi-inbox" aria-hidden="true" style="font-size:1.5rem; display:block; margin-bottom:0.5rem;"></i>
                            No tickets found
                        </td>
                    </tr>
                <?php else:
                    while ($row = $result->fetch_assoc()):
                        $isResolved = strtolower($row['issue_status']) === 'resolved';
                        $scheduled = ($row['scheduled_datetime'] && $row['scheduled_datetime'] !== '0000-00-00 00:00:00') ? date('Y-m-d H:i', strtotime($row['scheduled_datetime'])) : '–';
                        $assigned_persons = $row['assigned_persons'] ?: '–';
                        $overdue_class = isOverdue($row) ? 'overdue' : '';
                        $status_class = $isResolved ? 'resolved' : (isOverdue($row) ? 'overdue' : 'pending');
                    ?>
                    <tr class="<?= $overdue_class ?>">
                        <td><?= '#' . (int)$row['id'] ?></td>
                        <td><?= esc($row['customer_name']) ?></td>
                        <td><?= esc($row['contact_number']) ?></td>
                        <td><?= esc($row['location']) ?></td>
                        <td><?= esc($row['issue_type']) ?></td>
                        <td><?= esc($scheduled) ?></td>
                        <td><?= esc($assigned_persons) ?></td>
                        <td class="text-center">
                            <span class="status-badge <?= $status_class ?>" role="status" aria-label="<?= $isResolved ? 'Resolved' : (isOverdue($row) ? 'Overdue' : 'Pending') ?>">
                                <?= $isResolved ? 'Resolved' : (isOverdue($row) ? 'Overdue' : 'Pending') ?>
                            </span>
                        </td>
                        <td class="actions text-center" aria-label="Manage ticket #<?= (int)$row['id'] ?>">
                            <button type="button" class="btn-icon-action btn-view-edit" title="View/Edit Details"
                                data-issue-id="<?= (int)$row['id'] ?>"
                                aria-label="View/edit ticket #<?= (int)$row['id'] ?>">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="btn-icon-action btn-delete" title="Delete Ticket"
                                data-issue-id="<?= (int)$row['id'] ?>"
                                aria-label="Delete ticket #<?= (int)$row['id'] ?>">
                                <i class="bi bi-trash" aria-hidden="true"></i>
                            </button>
                        </td>
                    </tr>
                <?php endwhile; endif; ?>
                </tbody>
            </table>
        </section>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <nav class="pagination" role="navigation" aria-label="Ticket list pagination">
            <?php if ($page > 1): ?>
            <a href="?filter=<?= esc($filter) ?>&search=<?= urlencode($search_term) ?>&page=<?= $page - 1 ?>" aria-label="Go to previous page">← Prev</a>
            <?php endif;

            $max_links = 5;
            $start = max(1, $page - intval($max_links / 2));
            $end = min($total_pages, $start + $max_links - 1);

            if ($start > 1) {
                echo '<a href="?filter=' . esc($filter) . '&search=' . urlencode($search_term) . '&page=1">1</a>';
                if ($start > 2) echo '<span aria-hidden="true">…</span>';
            }

            for ($i = $start; $i <= $end; $i++):
                $active = ($i === $page) ? 'active' : '';
            ?>
            <a href="?filter=<?= esc($filter) ?>&search=<?= urlencode($search_term) ?>&page=<?= $i ?>" class="<?= $active ?>" aria-current="<?= $active ? 'page' : 'false' ?>"><?= $i ?></a>
            <?php endfor;

            if ($end < $total_pages) {
                if ($end < $total_pages - 1) echo '<span aria-hidden="true">…</span>';
                echo '<a href="?filter=' . esc($filter) . '&search=' . urlencode($search_term) . '&page=' . $total_pages . '">' . $total_pages . '</a>';
            }

            if ($page < $total_pages): ?>
            <a href="?filter=<?= esc($filter) ?>&search=<?= urlencode($search_term) ?>&page=<?= $page + 1 ?>" aria-label="Go to next page">Next →</a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>

        <!-- Floating action button for adding new ticket -->
        <a href="log_ticket.php" class="floating-action" title="Add New Ticket" aria-label="Add new ticket">
            <i class="bi bi-plus" aria-hidden="true"></i>
        </a>

        <!-- Unified View/Edit Modal with Comments -->
        <div id="unifiedModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="unifiedModalTitle" tabindex="-1" aria-hidden="true">
            <div class="modal-content">
                <button type="button" class="close" aria-label="Close modal">&times;</button>
                <header class="modal-header">
                    <h2 id="unifiedModalTitle">Ticket Details</h2>
                </header>
                
                <div class="modal-columns">
                    <!-- Left Column: Ticket Details -->
                    <div class="details-column">
                        <form id="ticket-edit-form">
                            <input type="hidden" id="edit-issue-id" name="issue_id" />
                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>" />
                            
                            <!-- Ticket ID - Always Read-Only -->
                            <div class="detail-row">
                                <div class="detail-label">Ticket ID:</div>
                                <div class="detail-value" id="detail-id"></div>
                                <!-- No edit field for Ticket ID - it's unchangeable -->
                            </div>
                            
                            <div class="detail-row">
                                <div class="detail-label">Customer Name:</div>
                                <div class="detail-value" id="detail-customer-name"></div>
                                <div class="detail-edit">
                                    <input type="text" id="edit-customer-name" name="customer_name" required>
                                </div>
                            </div>
                            
                            <div class="detail-row">
                                <div class="detail-label">Contact Number:</div>
                                <div class="detail-value" id="detail-contact-number"></div>
                                <div class="detail-edit">
                                    <input type="text" id="edit-contact-number" name="contact_number" required>
                                </div>
                            </div>
                            
                            <div class="detail-row">
                                <div class="detail-label">Email:</div>
                                <div class="detail-value" id="detail-email"></div>
                                <div class="detail-edit">
                                    <input type="email" id="edit-email" name="email" required>
                                </div>
                            </div>
                            
                            <div class="detail-row">
                                <div class="detail-label">Location:</div>
                                <div class="detail-value" id="detail-location"></div>
                                <div class="detail-edit">
                                    <input type="text" id="edit-location" name="location" required>
                                </div>
                            </div>
                            
                            <div class="detail-row">
                                <div class="detail-label">Issue Type:</div>
                                <div class="detail-value" id="detail-issue-type"></div>
                                <div class="detail-edit">
                                    <select id="edit-issue-type" name="issue_type" required>
                                        <option value="">Select Issue Type</option>
                                        <option value="Connection Issue">Connection Issue</option>
                                        <option value="System-Related">System Related</option>
                                        <option value="Manual Top-up Request">Manual Top-up Request</option>
                                        <option value="Service Inquiry">Service Inquiry</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="detail-row">
                                <div class="detail-label">Service Type:</div>
                                <div class="detail-value" id="detail-service-type"></div>
                                <div class="detail-edit">
                                    <select id="edit-service-type" name="service_type">
                                        <option value="">Select Service Type</option>
                                        <option value="4G">4G</option>
                                        <option value="FTTH">FTTH</option>
                                        <option value="VSAT">VSAT</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="detail-row">
                                <div class="detail-label">Scheduled Date/Time:</div>
                                <div class="detail-value" id="detail-scheduled-datetime"></div>
                                <div class="detail-edit">
                                    <input type="datetime-local" id="edit-scheduled-datetime" name="scheduled_datetime">
                                </div>
                            </div>
                            
                            <div class="detail-row">
                                <div class="detail-label">Assigned Department:</div>
                                <div class="detail-value" id="detail-assigned-department"></div>
                                <div class="detail-edit">
                                    <select id="edit-assigned-department" name="assigned_department">
                                        <option value="">Select Department</option>
                                        <?php foreach ($department_persons as $dept => $persons): ?>
                                            <option value="<?= esc($dept) ?>"><?= esc($dept) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="detail-row">
                                <div class="detail-label">Assigned Persons:</div>
                                <div class="detail-value" id="detail-assigned-persons"></div>
                                <div class="detail-edit">
                                    <select id="edit-assigned-persons" name="assigned_persons[]" multiple>
                                        <!-- Options populated dynamically -->
                                    </select>
                                </div>
                            </div>
                            
                            <div class="detail-row">
                                <div class="detail-label">Resolve By Deadline:</div>
                                <div class="detail-value" id="detail-resolved-by-deadline"></div>
                                <div class="detail-edit">
                                    <input type="datetime-local" id="edit-resolved-by-deadline" name="resolved_by_deadline">
                                </div>
                            </div>
                            
                            <div class="detail-row">
                                <div class="detail-label">Status:</div>
                                <div class="detail-value" id="detail-issue-status"></div>
                                <div class="detail-edit">
                                    <select id="edit-issue-status" name="issue_status">
                                        <option value="Unresolved">Unresolved</option>
                                        <option value="Resolved">Resolved</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Created At - Always Read-Only -->
                            <div class="detail-row">
                                <div class="detail-label">Created At:</div>
                                <div class="detail-value" id="detail-created-at"></div>
                                <!-- No edit field for Created At - it's unchangeable -->
                            </div>
                            
                            <!-- Logged By - Always Read-Only -->
                            <div class="detail-row">
                                <div class="detail-label">Logged By:</div>
                                <div class="detail-value" id="detail-logged-by"></div>
                                <!-- No edit field for Logged By - it's unchangeable -->
                            </div>

                            <button type="button" id="editToggleBtn" class="modal-edit-toggle" aria-label="Toggle edit mode">
                                <i class="bi bi-pencil-square" aria-hidden="true"></i> Edit
                            </button>

                            <div class="edit-actions">
                                <button type="button" id="cancelEditBtn" class="btn-cancel">Cancel</button>
                                <button type="submit" id="saveChangesBtn" class="btn-save">Save Changes</button>
                            </div>

                            
                        </form>
                    </div>
                    
                    <!-- Right Column: Comments -->
                    <div class="comments-column">
                        <div class="comments-section">
                            <div class="comments-header">
                                <h3>Activity Comments</h3>
                            </div>
                            
                            <div class="comments-container" id="comments-container">
                                <!-- Comments will be loaded here -->
                                <div class="no-comments" id="no-comments">No comments yet.</div>
                            </div>
                            
                            <form id="add-comment-form" class="add-comment-form" aria-label="Add comment form">
                                <h4>Add New Comment</h4>
                                <textarea id="new-comment" name="comment" placeholder="Enter your comment here..." required></textarea>
                                <button type="submit" aria-label="Submit comment">
                                    <i class="bi bi-chat-left-text"></i> Add Comment
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast container -->
        <div id="toast-container" aria-live="polite" aria-atomic="true"></div>

    </main>

    <script>
        (() => {
            'use strict';

            // CSRF token from PHP
            const csrfToken = '<?= $csrf_token ?>';

            // Department persons mapping
            const departmentPersons = <?= json_encode($department_persons) ?>;

            // Current issue ID for modal operations
            let currentIssueId = null;
            let isEditMode = false;
            let currentTicketData = null;

            // Modal elements
            const unifiedModal = document.getElementById('unifiedModal');
            const editToggleBtn = document.getElementById('editToggleBtn');
            const cancelEditBtn = document.getElementById('cancelEditBtn');
            const ticketEditForm = document.getElementById('ticket-edit-form');
            const assignedDepartmentSelect = document.getElementById('edit-assigned-department');
            const assignedPersonsSelect = document.getElementById('edit-assigned-persons');
            const commentsContainer = document.getElementById('comments-container');
            const noCommentsElement = document.getElementById('no-comments');

            // Toast notifications
            const toastContainer = document.getElementById('toast-container');
            function createToast(message, type = 'success') {
                const toast = document.createElement('div');
                toast.className = `toast toast-${type}`;
                toast.setAttribute('role', 'alert');
                toast.setAttribute('aria-live', 'assertive');
                toast.setAttribute('aria-atomic', 'true');
                toast.innerHTML = `
                    <i class="bi ${type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle'}" aria-hidden="true"></i>
                    <span>${message}</span>
                `;
                toastContainer.appendChild(toast);
                setTimeout(() => {
                    toast.style.animation = 'fadeOut 0.5s forwards';
                    setTimeout(() => toast.remove(), 500);
                }, 4000);
            }

            // Utility: escape HTML input for security
            function escapeHtml(text) {
                return text.replace(/[&<>"']/g, (m) => {
                    switch(m) {
                        case '&': return '&amp;';
                        case '<': return '&lt;';
                        case '>': return '&gt;';
                        case '"': return '&quot;';
                        case '\'': return '&#39;';
                        default: return m;
                    }
                });
            }

            // Debounce function for search
            function debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }

            // Modal helper functions
            function openModal() {
                unifiedModal.style.display = 'block';
                unifiedModal.setAttribute('aria-hidden', 'false');
                // Focus first focusable element for accessibility
                const focusable = unifiedModal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                if (focusable) focusable.focus();
            }
            
            function closeModal() {
                unifiedModal.style.display = 'none';
                unifiedModal.setAttribute('aria-hidden', 'true');
                exitEditMode();
            }

            // Close modal when clicking outside content or pressing ESC
            document.addEventListener('click', (e) => {
                if (e.target === unifiedModal) closeModal();
            });
            
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && unifiedModal.style.display === 'block') {
                    closeModal();
                }
            });

            // Close button for modal
            document.querySelector('#unifiedModal .close').addEventListener('click', () => {
                closeModal();
            });

            // Load ticket details
            function loadTicketDetails(issueId) {
                fetch(`?action=get_ticket_details&issue_id=${issueId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            currentTicketData = data.ticket;
                            displayTicketDetails(data.ticket);
                            loadComments(issueId);
                        } else {
                            createToast('Failed to load ticket details.', 'error');
                        }
                    })
                    .catch(() => {
                        createToast('Error loading ticket details.', 'error');
                    });
            }

            // Display ticket details in the modal - VIEW MODE
            function displayTicketDetails(ticket) {
                // Display values (read-only mode)
                document.getElementById('edit-issue-id').value = ticket.id;
                document.getElementById('detail-id').textContent = '#' + ticket.id;
                document.getElementById('detail-customer-name').textContent = ticket.customer_name || '–';
                document.getElementById('detail-contact-number').textContent = ticket.contact_number || '–';
                document.getElementById('detail-email').textContent = ticket.email || '–';
                document.getElementById('detail-location').textContent = ticket.location || '–';
                document.getElementById('detail-issue-type').textContent = ticket.issue_type || '–';
                document.getElementById('detail-service-type').textContent = ticket.service_type || '–';
                
                // Format dates for display
                const scheduledDate = ticket.scheduled_datetime_formatted 
                    ? new Date(ticket.scheduled_datetime_formatted).toLocaleString() 
                    : '–';
                document.getElementById('detail-scheduled-datetime').textContent = scheduledDate;
                
                document.getElementById('detail-assigned-department').textContent = ticket.assigned_department || '–';
                document.getElementById('detail-assigned-persons').textContent = ticket.assigned_persons || '–';
                
                const resolvedDate = ticket.resolved_by_deadline_formatted 
                    ? new Date(ticket.resolved_by_deadline_formatted).toLocaleString() 
                    : '–';
                document.getElementById('detail-resolved-by-deadline').textContent = resolvedDate;
                
                const statusClass = ticket.issue_status === 'Resolved' ? 'resolved' : 'pending';
                document.getElementById('detail-issue-status').innerHTML = 
                    `<span class="status-badge ${statusClass}">${ticket.issue_status || 'Unresolved'}</span>`;
                
                /* document.getElementById('detail-comments').textContent = ticket.comments || '–'; */
                document.getElementById('detail-created-at').textContent = ticket.created_at_formatted || '–';
                document.getElementById('detail-logged-by').textContent = ticket.logged_by || '–';
                
                // Set form values for edit mode (hidden initially)
                document.getElementById('edit-customer-name').value = ticket.customer_name || '';
                document.getElementById('edit-contact-number').value = ticket.contact_number || '';
                document.getElementById('edit-email').value = ticket.email || '';
                document.getElementById('edit-location').value = ticket.location || '';
                document.getElementById('edit-issue-type').value = ticket.issue_type || '';
                document.getElementById('edit-service-type').value = ticket.service_type || '';
                document.getElementById('edit-scheduled-datetime').value = ticket.scheduled_datetime_formatted || '';
                document.getElementById('edit-assigned-department').value = ticket.assigned_department || '';
                document.getElementById('edit-resolved-by-deadline').value = ticket.resolved_by_deadline_formatted || '';
                document.getElementById('edit-issue-status').value = ticket.issue_status || 'Unresolved';
                /* document.getElementById('edit-comments').value = ticket.comments || ''; */
                
                // Update assigned persons dropdown based on selected department
                updateAssignedPersonsDropdown(ticket.assigned_department, ticket.assigned_persons);
                
                // Exit edit mode initially (default is view mode)
                exitEditMode();
            }

            // Update assigned persons dropdown
            function updateAssignedPersonsDropdown(department, currentAssigned) {
                assignedPersonsSelect.innerHTML = '';
                
                if (department && departmentPersons[department]) {
                    const currentPersons = currentAssigned ? currentAssigned.split(',').map(p => p.trim()) : [];
                    
                    departmentPersons[department].forEach(person => {
                        const option = document.createElement('option');
                        option.value = person;
                        option.textContent = person;
                        option.selected = currentPersons.includes(person);
                        assignedPersonsSelect.appendChild(option);
                    });
                }
            }

            // Department change handler for assigned persons dropdown
            assignedDepartmentSelect.addEventListener('change', function() {
                updateAssignedPersonsDropdown(this.value, '');
            });

            // Toggle edit mode
            function enterEditMode() {
                isEditMode = true;
                document.querySelector('.details-column').classList.add('edit-mode');
                editToggleBtn.innerHTML = '<i class="bi bi-eye" aria-hidden="true"></i> View';
                editToggleBtn.setAttribute('aria-label', 'Toggle to view mode');
                document.getElementById('unifiedModalTitle').textContent = 'Edit Ticket';
                
                // Focus first editable field for better UX (skip Ticket ID since it's not editable)
                setTimeout(() => {
                    const firstEditable = document.querySelector('.detail-edit input, .detail-edit select, .detail-edit textarea');
                    if (firstEditable) firstEditable.focus();
                }, 100);
            }

            function exitEditMode() {
                isEditMode = false;
                document.querySelector('.details-column').classList.remove('edit-mode');
                editToggleBtn.innerHTML = '<i class="bi bi-pencil-square" aria-hidden="true"></i> Edit';
                editToggleBtn.setAttribute('aria-label', 'Toggle to edit mode');
                document.getElementById('unifiedModalTitle').textContent = 'Ticket Details';
            }

            // Edit toggle button
            editToggleBtn.addEventListener('click', () => {
                if (isEditMode) {
                    exitEditMode();
                } else {
                    enterEditMode();
                }
            });

            // Cancel edit button - restores original values
            cancelEditBtn.addEventListener('click', () => {
                exitEditMode();
                // Restore original data from currentTicketData
                if (currentTicketData) {
                    displayTicketDetails(currentTicketData);
                }
            });

            // Form submission - saves changes
            ticketEditForm.addEventListener('submit', (e) => {
                e.preventDefault();
                
                if (!currentIssueId) return;
                
                const formData = new FormData(ticketEditForm);
                
                // Get assigned persons as array
                const assignedPersons = Array.from(assignedPersonsSelect.selectedOptions).map(opt => opt.value);
                formData.delete('assigned_persons[]');
                assignedPersons.forEach(person => {
                    formData.append('assigned_persons[]', person);
                });
                
                fetch('?action=update_ticket', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        createToast(data.message, 'success');
                        exitEditMode();
                        
                        // Update currentTicketData with new values
                        if (currentTicketData) {
                            currentTicketData.customer_name = document.getElementById('edit-customer-name').value;
                            currentTicketData.contact_number = document.getElementById('edit-contact-number').value;
                            currentTicketData.email = document.getElementById('edit-email').value;
                            currentTicketData.location = document.getElementById('edit-location').value;
                            currentTicketData.issue_type = document.getElementById('edit-issue-type').value;
                            currentTicketData.service_type = document.getElementById('edit-service-type').value;
                            currentTicketData.assigned_department = document.getElementById('edit-assigned-department').value;
                            currentTicketData.assigned_persons = assignedPersons.join(', ');
                            currentTicketData.issue_status = document.getElementById('edit-issue-status').value;
                            /* currentTicketData.comments = document.getElementById('edit-comments').value; */
                            
                            // Update display with new values
                            displayTicketDetails(currentTicketData);
                        }
                        
                        // Update the table row without refreshing the page
                        updateTableRow(currentIssueId);
                        
                    } else {
                        createToast(data.message, 'error');
                    }
                })
                .catch(() => {
                    createToast('Error updating ticket.', 'error');
                });
            });

            // Update table row after edit
            function updateTableRow(issueId) {
                const row = document.querySelector(`.btn-view-edit[data-issue-id="${issueId}"]`)?.closest('tr');
                if (!row) return;
                
                // Update row cells with new data
                const cells = row.querySelectorAll('td');
                
                // Ticket ID remains the same (cell 0) - unchanged
                
                // Customer Name (cell 1)
                cells[1].textContent = document.getElementById('edit-customer-name').value;
                
                // Contact Number (cell 2)
                cells[2].textContent = document.getElementById('edit-contact-number').value;
                
                // Location (cell 3)
                cells[3].textContent = document.getElementById('edit-location').value;
                
                // Issue Type (cell 4)
                cells[4].textContent = document.getElementById('edit-issue-type').value;
                
                // Scheduled Date (cell 5)
                const scheduledDate = document.getElementById('edit-scheduled-datetime').value;
                cells[5].textContent = scheduledDate ? new Date(scheduledDate).toLocaleString() : '–';
                
                // Assigned Persons (cell 6)
                cells[6].textContent = Array.from(assignedPersonsSelect.selectedOptions)
                    .map(opt => opt.value)
                    .join(', ') || '–';
                
                // Status (cell 7)
                const status = document.getElementById('edit-issue-status').value;
                const statusClass = status === 'Resolved' ? 'resolved' : 'pending';
                cells[7].innerHTML = `<span class="status-badge ${statusClass}">${status}</span>`;
            }

            // Load and display comments
            function loadComments(issueId) {
                fetch(`?action=get_comments&issue_id=${issueId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            displayComments(data.comments);
                        } else {
                            createToast('Failed to load comments.', 'error');
                        }
                    })
                    .catch(() => {
                        createToast('Error loading comments.', 'error');
                    });
            }

            // Display comments in the comments container
            function displayComments(comments) {
                if (comments.length > 0) {
                    noCommentsElement.style.display = 'none';
                    
                    const commentsHtml = comments.map(comment => `
                        <div class="comment">
                            <div class="comment-header">
                                <span>${escapeHtml(comment.author)}</span>
                                <span class="comment-date">${new Date(comment.created_at).toLocaleString()}</span>
                            </div>
                            <div class="comment-text">${escapeHtml(comment.comment)}</div>
                        </div>
                    `).join('');
                    
                    commentsContainer.innerHTML = commentsHtml;
                    
                    // Scroll to top of comments
                    commentsContainer.scrollTop = 0;
                } else {
                    noCommentsElement.style.display = 'block';
                    commentsContainer.innerHTML = '';
                }
            }

            // View/Edit button click handler
            document.querySelectorAll('.btn-view-edit').forEach(btn => {
                btn.addEventListener('click', () => {
                    currentIssueId = btn.getAttribute('data-issue-id');
                    loadTicketDetails(currentIssueId);
                    openModal();
                });
            });

            // Add comment form submission
            document.getElementById('add-comment-form').addEventListener('submit', function(e) {
                e.preventDefault();
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
                .then(resp => resp.json())
                .then(data => {
                    if (data.success) {
                        createToast(data.message, 'success');
                        document.getElementById('new-comment').value = '';
                        // Reload comments to show the new one
                        loadComments(currentIssueId);
                    } else {
                        createToast(data.message, 'error');
                    }
                }).catch(() => createToast('Error adding comment.', 'error'));
            });

            // Delete ticket button handler
            document.querySelectorAll('.btn-delete').forEach(btn => {
                btn.addEventListener('click', () => {
                    const issueId = btn.getAttribute('data-issue-id');
                    if (!issueId) return;

                    if(!confirm(`Are you sure you want to delete ticket #${issueId}? This action cannot be undone.`)) return;

                    const formData = new FormData();
                    formData.append('issue_id', issueId);
                    formData.append('csrf_token', csrfToken);

                    fetch('?action=delete_ticket', {
                        method: 'POST',
                        body: formData
                    })
                    .then(resp => resp.json())
                    .then(data => {
                        if (data.success) {
                            createToast(data.message, 'success');
                            const row = btn.closest('tr');
                            if(row){
                                row.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                                row.style.opacity = '0';
                                row.style.transform = 'translateX(-100%)';
                                setTimeout(() => row.remove(), 400);
                            }
                        } else {
                            createToast(data.message, 'error');
                        }
                    })
                    .catch(() => createToast('Error deleting ticket.', 'error'));
                });
            });

            // Debounced live search redirect
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', debounce(() => {
                    const val = searchInput.value.trim();
                    const url = new URL(window.location);

                    if (val) {
                        url.searchParams.set('search', val);
                    } else {
                        url.searchParams.delete('search');
                    }

                    url.searchParams.set('page', '1');
                    window.location.href = url.toString();
                }, 500));
            }
        })();
    </script>
</body>
</html>

<?php
// Close DB connections etc.
$stmt->close();
$conn->close();
?>