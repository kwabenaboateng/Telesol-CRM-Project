<?php
session_start();

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'telesol crm';

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
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

$valid_statuses = ['All', 'Completed', 'Uncompleted'];
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
$count_sql = "SELECT COUNT(*) as total FROM installations $where_sql";
$stmt_count = $conn->prepare($count_sql);

if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}

$stmt_count->execute();
$count_result = $stmt_count->get_result();
$total_results = $count_result->fetch_assoc()['total'];
$stmt_count->close();

// Fetch installations data for current page/filter
$sql = "SELECT * FROM installations $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

// Add limit and offset to params
$fetch_params = $params;
$fetch_params[] = $limit;
$fetch_params[] = $offset;
$fetch_types = $types . 'ii';

if (!empty($fetch_params)) {
    $stmt->bind_param($fetch_types, ...$fetch_params);
}

$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$total_pages = max(ceil($total_results / $limit), 1);

// Sample engineers list - ideally from DB
$engineers = ['John Hagan', 'Isaac Ofosu-Afful', 'Sylvester Horsu', 'Innocent Odikro', 'Joshua Avinu'];

// Helper: escape output for HTML safety
function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telesol CRM - Installations</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            color: #2c3e50;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .nav {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .nav ul {
            list-style: none;
            display: flex;
            gap: 2rem;
        }
        
        .nav a {
            color: #2c3e50;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .nav a:hover {
            color: #3498db;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem 2rem;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-header h2 {
            font-size: 2rem;
            color: #2c3e50;
        }
        
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .btn-sm {
            padding: 0.35rem 0.75rem;
            font-size: 0.85rem;
        }
        
        .search-box {
            flex: 1;
            max-width: 400px;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.6rem 1rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: #34495e;
            color: white;
        }
        
        th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #ecf0f1;
        }
        
        tbody tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-completed {
            background: #d5f4e6;
            color: #27ae60;
        }
        
        .status-uncompleted {
            background: #fadbd8;
            color: #e74c3c;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
            padding: 1rem;
        }
        
        .pagination a,
        .pagination span {
            padding: 0.5rem 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #2c3e50;
        }
        
        .pagination a:hover {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .pagination .active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            font-size: 1.5rem;
            color: #2c3e50;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #95a5a6;
        }
        
        .close-btn:hover {
            color: #2c3e50;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #ecf0f1;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #2c3e50;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.9rem;
            font-family: inherit;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .info-section {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        
        .info-section h4 {
            margin-bottom: 0.75rem;
            color: #2c3e50;
            font-size: 1.1rem;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 0.5rem;
        }
        
        .info-label {
            font-weight: 600;
            min-width: 150px;
            color: #555;
        }
        
        .info-value {
            color: #2c3e50;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d5f4e6;
            color: #27ae60;
            border: 1px solid #27ae60;
        }
        
        .alert-error {
            background: #fadbd8;
            color: #e74c3c;
            border: 1px solid #e74c3c;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #95a5a6;
        }
        
        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Telesol CRM</h1>
        <div class="user-info">
            <span>Logged in as: <?php echo esc($_SESSION['username'] ?? 'Admin'); ?></span>
            <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
        </div>
    </div>
    
    <nav class="nav">
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="tickets.php">Ticket Management</a></li>
            <li><a href="installations.php" style="color: #3498db;">Installations</a></li>
            <li><a href="customer.php">Customer Experience</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="settings.php">Settings</a></li>
        </ul>
    </nav>
    
    <div class="container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo esc($_SESSION['message_type']); ?>">
                <?php 
                echo esc($_SESSION['message']);
                unset($_SESSION['message']);
                unset($_SESSION['message_type']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="page-header">
            <h2>Installation Management</h2>
            <a href="new_installation.php" class="btn btn-primary">+ New Installation</a>
        </div>
        
        <div class="filters">
            <div class="filter-group">
                <a href="?status=All" class="btn <?php echo $status_filter === 'All' ? 'btn-primary' : 'btn-secondary'; ?>">All Installations</a>
                <a href="?status=Uncompleted" class="btn <?php echo $status_filter === 'Uncompleted' ? 'btn-primary' : 'btn-secondary'; ?>">Uncompleted</a>
                <a href="?status=Completed" class="btn <?php echo $status_filter === 'Completed' ? 'btn-primary' : 'btn-secondary'; ?>">Completed</a>
            </div>
            
            <div class="search-box">
                <form method="GET" action="">
                    <input type="hidden" name="status" value="<?php echo esc($status_filter); ?>">
                    <input type="search" name="search" placeholder="Search by customer, transaction ID, location..." value="<?php echo esc($search_term); ?>">
                </form>
            </div>
        </div>
        
        <div class="table-container">
            <?php if ($result->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Transaction ID</th>
                            <th>Customer</th>
                            <th>Package</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th>Logged By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = $offset + 1;
                        while ($row = $result->fetch_assoc()): 
                            $statusClass = strtolower($row['installation_status']) === 'completed' ? 'status-completed' : 'status-uncompleted';
                        ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo esc($row['transaction_id']); ?></td>
                                <td><?php echo esc($row['customer_name']); ?></td>
                                <td>GHS <?php echo number_format($row['amount_paid'], 2); ?></td>
                                <td><?php echo esc($row['location']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo esc($row['installation_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo esc($row['logged_by'] ?? 'System'); ?></td>
                                <td>
                                    <div class="actions">
                                        <button class="btn btn-primary btn-sm" onclick="viewInstallation(<?php echo $row['id']; ?>)">View</button>
                                        <button class="btn btn-success btn-sm" onclick="editInstallation(<?php echo $row['id']; ?>)">Edit</button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteInstallation(<?php echo $row['id']; ?>, '<?php echo esc($row['customer_name']); ?>')">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p style="font-size: 1.2rem; margin-bottom: 0.5rem;">No installations found</p>
                    <p>Try adjusting your filters or search terms</p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search_term); ?>&page=<?php echo $page - 1; ?>">« Prev</a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                    <a href="?status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search_term); ?>&page=1">1</a>
                    <?php if ($start_page > 2): ?>
                        <span>...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search_term); ?>&page=<?php echo $i; ?>" 
                       class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span>...</span>
                    <?php endif; ?>
                    <a href="?status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search_term); ?>&page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search_term); ?>&page=<?php echo $page + 1; ?>">Next »</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- View Installation Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Installation Details</h3>
                <button class="close-btn" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>
    
    <!-- Edit Installation Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Installation</h3>
                <button class="close-btn" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo esc($csrf_token); ?>">
                <input type="hidden" name="edit_installation" value="1">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label>Customer Name *</label>
                        <input type="text" name="customer_name" id="edit_customer_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Transaction ID</label>
                        <input type="text" name="transaction_id" id="edit_transaction_id">
                    </div>
                    
                    <div class="form-group">
                        <label>Amount Paid (GHS) *</label>
                        <input type="number" step="0.01" name="amount_paid" id="edit_amount_paid" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select name="mode_of_payment" id="edit_mode_of_payment">
                            <option value="Cash">Cash</option>
                            <option value="Bank Card">Bank Card</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Mobile Money">Mobile Money</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Location *</label>
                        <input type="text" name="location" id="edit_location" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="edit_email">
                    </div>
                    
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="installation_status" id="edit_installation_status" required>
                            <option value="Completed">Completed</option>
                            <option value="Uncompleted">Uncompleted</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Scheduled Date/Time</label>
                        <input type="datetime-local" name="scheduled_datetime" id="edit_scheduled_datetime">
                    </div>
                    
                    <div class="form-group">
                        <label>Assigned Engineer</label>
                        <select name="assigned_engineer" id="edit_assigned_engineer">
                            <option value="">Select Engineer</option>
                            <?php foreach ($engineers as $engineer): ?>
                                <option value="<?php echo esc($engineer); ?>"><?php echo esc($engineer); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Comments</label>
                        <textarea name="comment" id="edit_comment"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Confirm Deletion</h3>
                <button class="close-btn" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo esc($csrf_token); ?>">
                <input type="hidden" name="delete_installation" value="1">
                <input type="hidden" name="id" id="delete_id">
                
                <div class="modal-body">
                    <p style="margin-bottom: 1rem;"><strong>Are you sure?</strong></p>
                    <p>You are about to delete the installation for <strong id="delete_customer_name"></strong>. This action cannot be undone.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Installation</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Installation data for JavaScript access
        const installations = <?php 
            // Reset result pointer
            $result->data_seek(0);
            $installations_data = [];
            while ($row = $result->fetch_assoc()) {
                $installations_data[] = $row;
            }
            echo json_encode($installations_data);
        ?>;
        
        function viewInstallation(id) {
            const installation = installations.find(i => i.id == id);
            if (!installation) return;
            
            const scheduled = installation.scheduled_datetime 
                ? new Date(installation.scheduled_datetime).toLocaleString() 
                : 'Not Set';
            
            const statusClass = installation.installation_status.toLowerCase() === 'completed' 
                ? 'status-completed' 
                : 'status-uncompleted';
            
            const html = `
                <div class="info-section">
                    <h4>Customer Information</h4>
                    <div class="info-row">
                        <span class="info-label">Customer Name:</span>
                        <span class="info-value">${escapeHtml(installation.customer_name)}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Transaction ID:</span>
                        <span class="info-value">${escapeHtml(installation.transaction_id || 'N/A')}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value">${escapeHtml(installation.email || 'N/A')}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Location:</span>
                        <span class="info-value">${escapeHtml(installation.location)}</span>
                    </div>
                </div>
                
                <div class="info-section">
                    <h4>Payment Information</h4>
                    <div class="info-row">
                        <span class="info-label">Package Amount:</span>
                        <span class="info-value">GHS ${parseFloat(installation.amount_paid).toFixed(2)}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment Method:</span>
                        <span class="info-value">${escapeHtml(installation.mode_of_payment || 'N/A')}</span>
                    </div>
                </div>
                
                <div class="info-section">
                    <h4>Installation Details</h4>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value">
                            <span class="status-badge ${statusClass}">
                                ${escapeHtml(installation.installation_status)}
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Assigned Engineer:</span>
                        <span class="info-value">${escapeHtml(installation.assigned_engineer || 'Not assigned')}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Scheduled Date/Time:</span>
                        <span class="info-value">${scheduled}</span>
                    </div>
                </div>
                
                <div class="info-section">
                    <h4>Comments</h4>
                    <p>${escapeHtml(installation.comment || 'No comments')}</p>
                </div>
                
                <div class="info-section">
                    <h4>System Information</h4>
                    <div class="info-row">
                        <span class="info-label">Logged By:</span>
                        <span class="info-value">${escapeHtml(installation.logged_by || 'System')}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Logged At:</span>
                        <span class="info-value">${new Date(installation.created_at).toLocaleString()}</span>
                    </div>
                </div>
            `;
            
            document.getElementById('viewModalBody').innerHTML = html;
            openModal('viewModal');
        }
        
        function editInstallation(id) {
            const installation = installations.find(i => i.id == id);
            if (!installation) return;
            
            document.getElementById('edit_id').value = installation.id;
            document.getElementById('edit_customer_name').value = installation.customer_name;
            document.getElementById('edit_transaction_id').value = installation.transaction_id || '';
            document.getElementById('edit_amount_paid').value = installation.amount_paid;
            document.getElementById('edit_mode_of_payment').value = installation.mode_of_payment || 'Cash';
            document.getElementById('edit_location').value = installation.location;
            document.getElementById('edit_email').value = installation.email || '';
            document.getElementById('edit_installation_status').value = installation.installation_status;
            document.getElementById('edit_assigned_engineer').value = installation.assigned_engineer || '';
            document.getElementById('edit_comment').value = installation.comment || '';
            
            // Format datetime for input
            if (installation.scheduled_datetime) {
                const dt = new Date(installation.scheduled_datetime);
                const formatted = dt.getFullYear() + '-' + 
                    String(dt.getMonth() + 1).padStart(2, '0') + '-' + 
                    String(dt.getDate()).padStart(2, '0') + 'T' + 
                    String(dt.getHours()).padStart(2, '0') + ':' + 
                    String(dt.getMinutes()).padStart(2, '0');
                document.getElementById('edit_scheduled_datetime').value = formatted;
            } else {
                document.getElementById('edit_scheduled_datetime').value = '';
            }
            
            openModal('editModal');
        }
        
        function deleteInstallation(id, customerName) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_customer_name').textContent = customerName;
            openModal('deleteModal');
        }
        
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>