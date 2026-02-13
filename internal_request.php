<?php
session_start();
$username = $_SESSION['username'] ?? 'User';

$servername = "localhost";
$db_username = "root";
$password_db = "";
$dbname = "telesol crm";

$conn = new mysqli($servername, $db_username, $password_db, $dbname);
if ($conn->connect_error) die("DB Error: ".htmlspecialchars($conn->connect_error));

function esc($str){ return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_request'])) {
    $department = $_POST['department'] ?? 'Administration';
    $priority = $_POST['priority'] ?? 'Normal';
    $requestor_name = trim($_POST['requestor_name'] ?? $username); // fallback to session
    $purpose = trim($_POST['purpose'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $items = $_POST['items'] ?? [];

    if (empty($items)) {
        $errors[] = "At least one item is required.";
    } else {
        $all_valid = true;
        foreach ($items as $i) {
            if (trim($i['item_name']) === '' || intval($i['quantity']) <= 0) {
                $all_valid = false;
                break;
            }
        }
        if (!$all_valid) $errors[] = "All items must have a name and quantity > 0.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO internal_request 
            (department, requested_by, requestor_name, purpose, location, priority) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        if ($stmt) {
            $stmt->bind_param("ssssss", $department, $username, $requestor_name, $purpose, $location, $priority);
            if ($stmt->execute()) {
                $request_id = $stmt->insert_id;
                foreach ($items as $i) {
                    $stmt2 = $conn->prepare("INSERT INTO internal_request_items (request_id, item_name, quantity) VALUES (?, ?, ?)");
                    $stmt2->bind_param("isi", $request_id, $i['item_name'], $i['quantity']);
                    $stmt2->execute();
                    $stmt2->close();
                }
                $success = true;
            } else {
                $errors[] = "Failed to create request: " . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        } else {
            $errors[] = "DB error: " . htmlspecialchars($conn->error);
        }
    }
}

// Fetch requests with items concatenated
$req_result = $conn->query("
SELECT r.id, r.department, r.requested_by, r.requestor_name, r.purpose, r.location, 
       r.priority, r.status, r.created_at,
       GROUP_CONCAT(CONCAT(i.item_name, ' (', i.quantity, ')') SEPARATOR ', ') as items
FROM internal_request r
LEFT JOIN internal_request_items i ON r.id = i.request_id
GROUP BY r.id
ORDER BY r.id DESC
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Internal Requisitions - Telesol CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
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
            --white: #ffffff;
            --border-radius: 8px;
            --transition: 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--background);
            color: var(--dark);
            display: flex;
            min-height: 100vh;
            font-size: 14px;
        }

        /* Sidebar – exactly as provided */
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
            padding: 1rem;
            text-align: center;
            font-weight: 300;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        .sidebar-header img {
            max-width: 80%;
            height: auto;
            margin-bottom: 0.5rem;
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
            padding: 0.5rem 0.5rem;
            display: block;
            font-size: 14px;
            font-weight: 400;
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

        .main-content {
            margin-left: 220px;
            padding: 20px;
            width: calc(100% - 220px);
        }

        .header {
            background: var(--primary);
            color: var(--white);
            padding: 10px 20px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
            border-radius: var(--border-radius);
        }

        .user-info {
            display: flex;
            align-items: center;
            background: rgba(255,255,255,0.2);
            padding: 0.3rem 1rem;
            border-radius: 30px;
        }

        .user-info i {
            margin-right: 8px;
        }

        /* Card styling */
        .card {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: var(--transition);
            border: none;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        /* Status badges */
        .badge-pending { background: #dc3545; color: white; }
        .badge-approved { background: #28a745; color: white; }
        .badge-rejected { background: #6c757d; color: white; }
        .badge-normal { background: #6c757d; color: white; }
        .badge-high { background: #f39c12; color: white; }
        .badge-critical { background: #dc3545; color: white; }

        .table-hover tbody tr:hover {
            background: #f1f1f1;
        }

        .item-row {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 10px;
        }
        .remove-item {
            margin-top: 8px;
        }
    </style>
</head>
<body>

<!-- Sidebar (unchanged) -->
<aside class="sidebar" aria-label="Main navigation">
    <div class="sidebar-header">
        <img src="/images/logo/Telesol_logo.jpeg" alt="Telesol Logo" style="max-width: 120px;">
        <h5>Telesol CRM</h5>
    </div>
    <nav class="sidebar-menu">
        <ul>
            <li><a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="task_overview.php"><i class="bi bi-ticket-detailed"></i> Task Overview</a></li>
            <li><a href="internal_request.php" class="active"><i class="bi bi-wrench"></i> Internal Requisitions</a></li>
            <li><a href="report.php"><i class="bi bi-bar-chart"></i> Reports</a></li>
            <!-- <li><a href="#"><i class="bi bi-arrow-left-circle"></i> Back</a></li> -->
            <li><a href="login.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
        </ul>
    </nav>
</aside>

<div class="main-content">
    <!-- Header with user -->
    <div class="header">
        <h4>Internal Requisitions</h4>
        <div class="user-info">
            <i class="bi bi-person-circle"></i> <?= esc($username) ?>
        </div>
    </div>

    <!-- Alert messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php foreach ($errors as $e): ?>
                <div><?= esc($e) ?></div>
            <?php endforeach; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Requisition submitted successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Requisition Form Card with new fields -->
    <div class="card mb-4 p-4">
        <h4 class="mb-3">Request Materials</h4>
        <form method="post" id="requisitionForm">
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Department</label>
                    <select class="form-select" name="department">
                        <option>Administration</option>
                        <option>Customer Service</option>
                        <option>Operations</option>
                        <option>Sales</option>
                        <option>Systems and IT</option>
                        <option>Technology</option>
                        <option>Transport</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Priority</label>
                    <select class="form-select" name="priority">
                        <option>Normal</option>
                        <option>High</option>
                        <option>Critical</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Requestor Name</label>
                    <input type="text" name="requestor_name" class="form-control" value="<?= esc($username) ?>" placeholder="Full name">
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Purpose / Description</label>
                    <textarea name="purpose" class="form-control" rows="2" placeholder="Why is this needed?"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Location</label>
                    <input type="text" name="location" class="form-control" placeholder="e.g., Store, Office #">
                </div>
            </div>

            <div id="itemsContainer">
                <div class="item-row p-3 mb-3 border rounded">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Item Name</label>
                            <input type="text" name="items[0][item_name]" class="form-control" placeholder="e.g., Printer Paper" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Quantity</label>
                            <input type="number" name="items[0][quantity]" class="form-control" placeholder="Qty" min="1" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-outline-danger remove-item w-100">Remove</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <button type="button" class="btn btn-outline-secondary" id="addItemBtn">
                    <i class="bi bi-plus-circle"></i> Add Another Item
                </button>
                <button type="submit" name="create_request" class="btn btn-primary px-4">
                    <i class="bi bi-send"></i> Submit Request
                </button>
            </div>
        </form>
    </div>

    <!-- Requisitions List Card (now includes new columns) -->
    <div class="card p-4">
        <h4 class="mb-3">Requisition History</h4>
        <?php if ($req_result && $req_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Dept</th>
                            <th>Requestor</th>
                            <th>Purpose</th>
                            <th>Location</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Items</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $req_result->fetch_assoc()): 
                            $priorityClass = '';
                            if ($row['priority'] == 'High') $priorityClass = 'badge-high';
                            elseif ($row['priority'] == 'Critical') $priorityClass = 'badge-critical';
                            else $priorityClass = 'badge-normal';

                            $statusClass = '';
                            if ($row['status'] == 'Approved') $statusClass = 'badge-approved';
                            elseif ($row['status'] == 'Rejected') $statusClass = 'badge-rejected';
                            else $statusClass = 'badge-pending';
                        ?>
                        <tr>
                            <td>#<?= $row['id'] ?></td>
                            <td><?= esc($row['department']) ?></td>
                            <td><?= esc($row['requestor_name'] ?: $row['requested_by']) ?></td>
                            <td><?= esc($row['purpose'] ?: '-') ?></td>
                            <td><?= esc($row['location'] ?: '-') ?></td>
                            <td><span class="badge <?= $priorityClass ?>"><?= esc($row['priority']) ?></span></td>
                            <td><span class="badge <?= $statusClass ?>"><?= esc($row['status']) ?></span></td>
                            <td><?= esc($row['items']) ?></td>
                            <td><?= date('d M Y, H:i', strtotime($row['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted">No requisitions found.</p>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript for dynamic items and alerts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    let itemIndex = 1;
    const container = document.getElementById('itemsContainer');

    document.getElementById('addItemBtn').addEventListener('click', function() {
        const newItem = document.createElement('div');
        newItem.classList.add('item-row', 'p-3', 'mb-3', 'border', 'rounded');
        newItem.innerHTML = `
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Item Name</label>
                    <input type="text" name="items[${itemIndex}][item_name]" class="form-control" placeholder="e.g., Printer Paper" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Quantity</label>
                    <input type="number" name="items[${itemIndex}][quantity]" class="form-control" placeholder="Qty" min="1" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-danger remove-item w-100">Remove</button>
                </div>
            </div>
        `;
        container.appendChild(newItem);
        itemIndex++;
    });

    // Remove item row using event delegation
    container.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-item') || e.target.closest('.remove-item')) {
            const btn = e.target.closest('.remove-item');
            const row = btn.closest('.item-row');
            if (row) {
                row.remove();
            }
        }
    });

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
</script>
</body>
</html>