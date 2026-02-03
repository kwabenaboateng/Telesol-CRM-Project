<?php
session_start();

// Database connection
try {
    $conn = new mysqli('localhost', 'root', '', 'telesol crm');
    if ($conn->connect_errno) throw new Exception("Connection failed: " . $conn->connect_error);
} catch (Exception $ex) {
    die("<div style='color:red; font-weight:bold;'>Database error: " . $ex->getMessage() . "</div>");
}

$technology_engineers = ['John Hagan', 'Isaac Ofosu-Afful', 'Sylvester Horsu', 'Innocent Odikro', 'Joshua Avinu'];
$engineersList = "'" . implode("','", $technology_engineers) . "'";

$tasksQuery = "SELECT id, customer_name, issue_type, location, assigned_persons, issue_status, scheduled_datetime, created_at, resolved_by_deadline, comments
               FROM tickets WHERE assigned_persons IN ($engineersList) ORDER BY created_at DESC";

$allTasks = [];
if ($result = $conn->query($tasksQuery)) {
    while ($row = $result->fetch_assoc()) {
        $allTasks[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>All Tasks - Telesol CRM</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
    body { font-family: Arial, sans-serif; margin:0; background:#f5f7fa; color:#333; display:flex; min-height: 100vh; }
    .sidebar { background:#2c3e50; color:#fff; width:250px; height:100vh; position:fixed; overflow-y:auto; }
    .sidebar-header { padding:20px; text-align:center; background:#1c2b3a; font-size:1.5em; font-weight:bold; }
    .sidebar-menu ul { list-style:none; padding:0; }
    .sidebar-menu li { margin:5px 0; }
    .sidebar-menu a { color:#fff; text-decoration:none; display:block; padding:12px 20px; transition: background 0.3s ease; }
    .sidebar-menu a.active, .sidebar-menu a:hover { background:#3498db; }
    .main-content { margin-left:250px; flex:1; display:flex; flex-direction:column; }
    .header { background:#fff; padding:15px 30px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 5px rgba(0,0,0,0.1); position:sticky; top:0; z-index:100; }
    .user-info { display:flex; align-items:center; gap:10px; }
    .user-info img { border-radius:50%; width:40px; height:40px; }
    .db-status { font-size:0.9em; background:#2ecc71; padding:5px 10px; border-radius:5px; color:#fff; display:flex; align-items:center; gap:5px; }
    .content { padding:20px; flex:1; overflow-y:auto; }
    .filter-section { background:#fff; border-radius:8px; padding:15px; margin-bottom:20px; display:flex; flex-wrap:wrap; gap:15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
    .filter-group { display:flex; flex-direction:column; min-width:150px; }
    .filter-group label { font-size:0.9em; color:#555; margin-bottom:6px; }
    select, input[type=text] { padding:8px; border:1px solid #ccc; border-radius:4px; font-size:1em; }
    table { width:100%; border-collapse:collapse; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
    th, td { padding:12px 15px; text-align:left; border-bottom:1px solid #eee; }
    th { background:#f8f9fa; color:#2c3e50; font-weight:700; position:sticky; top:0; }
    tr:hover { background:#f9f9f9; }
    .status { padding:3px 8px; border-radius:15px; font-size:0.8em; color:#fff; font-weight:600; display:inline-block; }
    .status-new { background:#3498db; }
    .status-resolved { background:#2ecc71; }
    .status-in-progress { background:#f1c40f; color:#333; }
    .status-pending { background:#e74c3c; }
    .btn { padding:8px 12px; border:none; background:#3498db; color:#fff; border-radius:4px; cursor:pointer; transition: background-color 0.3s ease; }
    .btn:hover { background:#2980b9; }
    .bulk-actions { margin-bottom:10px; }
    .hidden { display:none; }
</style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-header">Telesol CRM</div>
    <div class="sidebar-menu">
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="tech_dashboard.php" class="active"><i class="fas fa-tasks"></i> All Tasks</a></li>
            <li><a href="engineers.php"><i class="fas fa-users"></i> Engineer Assignments</a></li>
            <li><a href="#"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
</div>

<div class="main-content">
    <div class="header">
        <h2>All Tasks</h2>
        <div class="user-info">
            <span class="db-status" title="Database active"><i class="fas fa-check-circle"></i> DB Connected</span>
            <img src="https://ui-avatars.com/api/?name=Admin+User&background=3498db&color=fff" alt="User" />
            <span>Admin User</span>
        </div>
    </div>

    <div class="content">
        <div class="filter-section" role="region" aria-label="Filters to search tasks">
            <div class="filter-group">
                <label for="type-filter">Type</label>
                <select id="type-filter" aria-controls="all-tasks-table">
                    <option value="all">All Types</option>
                    <option value="Issue">Issues</option>
                    <option value="Installation">Installations</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="status-filter">Status</label>
                <select id="status-filter" aria-controls="all-tasks-table">
                    <option value="all">All Statuses</option>
                    <option value="New">New</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Resolved">Resolved</option>
                    <option value="Pending">Pending</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="engineer-filter">Engineer</label>
                <select id="engineer-filter" aria-controls="all-tasks-table">
                    <option value="all">All Engineers</option>
                    <?php foreach ($technology_engineers as $engineer): ?>
                    <option value="<?php echo str_replace(' ', '-', strtolower($engineer)); ?>"><?php echo $engineer; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group" style="flex-grow:1;">
                <label for="search">Search</label>
                <input type="text" id="search" placeholder="Search by customer or location" aria-controls="all-tasks-table" />
            </div>
        </div>

        <div class="bulk-actions hidden" aria-live="polite" id="bulk-actions-bar">
            <button class="btn" id="bulk-resolve-btn">Mark Selected as Resolved</button>
            <button class="btn" id="bulk-pending-btn">Mark Selected as Pending</button>
            <button class="btn" id="bulk-clear-btn">Clear Selection</button>
        </div>

        <table aria-describedby="tasks-desc" id="all-tasks-table">
            <caption id="tasks-desc">Table showing all tasks with filters applied</caption>
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"/></th>
                    <th>ID</th>
                    <th>Type</th>
                    <th>Customer</th>
                    <th>Location</th>
                    <th>Assigned To</th>
                    <th>Status</th>
                    <th>Due Date</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody id="tasks-body">
                <?php foreach ($allTasks as $task):
                    $statusClass = 'status-new';
                    if ($task['issue_status'] == 'Resolved') $statusClass = 'status-resolved';
                    elseif ($task['issue_status'] == 'In Progress') $statusClass = 'status-in-progress';
                    elseif ($task['issue_status'] == 'Pending') $statusClass = 'status-pending';
                    $dueDate = $task['resolved_by_deadline'] ? date('M j, Y', strtotime($task['resolved_by_deadline'])) : 'Not set';
                    $createdDate = date('M j, Y', strtotime($task['created_at']));
                ?>
                <tr data-type="<?php echo htmlspecialchars($task['issue_type']); ?>"
                    data-status="<?php echo htmlspecialchars($task['issue_status']); ?>"
                    data-engineer="<?php echo str_replace(' ', '-', strtolower($task['assigned_persons'])); ?>"
                    data-customer="<?php echo strtolower(htmlspecialchars($task['customer_name'])); ?>"
                    data-location="<?php echo strtolower(htmlspecialchars($task['location'])); ?>">
                    <td><input type="checkbox" class="task-checkbox" data-task-id="<?php echo $task['id']; ?>" /></td>
                    <td>#<?php echo $task['id']; ?></td>
                    <td><?php echo htmlspecialchars($task['issue_type']); ?></td>
                    <td><?php echo htmlspecialchars($task['customer_name']); ?></td>
                    <td><?php echo htmlspecialchars($task['location']); ?></td>
                    <td><?php echo htmlspecialchars($task['assigned_persons']); ?></td>
                    <td><span class="status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($task['issue_status']); ?></span></td>
                    <td><?php echo $dueDate; ?></td>
                    <td><?php echo $createdDate; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const filters = {
        type: document.getElementById('type-filter'),
        status: document.getElementById('status-filter'),
        engineer: document.getElementById('engineer-filter'),
        search: document.getElementById('search')
    };

    const table = document.getElementById('all-tasks-table');
    const tbody = document.getElementById('tasks-body');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    const bulkActionsBar = document.getElementById('bulk-actions-bar');
    const selectAllCheckbox = document.getElementById('select-all');
    const taskCheckboxes = tbody.querySelectorAll('.task-checkbox');

    function filterTable() {
        const typeValue = filters.type.value;
        const statusValue = filters.status.value;
        const engineerValue = filters.engineer.value;
        const searchValue = filters.search.value.trim().toLowerCase();

        let visibleCount = 0;
        rows.forEach(row => {
            const rowType = row.getAttribute('data-type');
            const rowStatus = row.getAttribute('data-status');
            const rowEngineer = row.getAttribute('data-engineer');
            const rowCustomer = row.getAttribute('data-customer');
            const rowLocation = row.getAttribute('data-location');

            const matchesType = (typeValue === 'all') || (rowType === typeValue);
            const matchesStatus = (statusValue === 'all') || (rowStatus === statusValue);
            const matchesEngineer = (engineerValue === 'all') || (rowEngineer === engineerValue);
            const matchesSearch = rowCustomer.includes(searchValue) || rowLocation.includes(searchValue);

            if (matchesType && matchesStatus && matchesEngineer && matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Reset select all checkbox
        selectAllCheckbox.checked = false;
        updateBulkActions();
    }

    // Event listeners for filters
    filters.type.addEventListener('change', filterTable);
    filters.status.addEventListener('change', filterTable);
    filters.engineer.addEventListener('change', filterTable);
    filters.search.addEventListener('input', filterTable);

    // Select all checkbox event
    selectAllCheckbox.addEventListener('change', () => {
        const filteredRows = rows.filter(row => row.style.display !== 'none');
        filteredRows.forEach(row => {
            const checkbox = row.querySelector('.task-checkbox');
            checkbox.checked = selectAllCheckbox.checked;
        });
        updateBulkActions();
    });

    // Individual checkbox event
    taskCheckboxes.forEach(chk => {
        chk.addEventListener('change', () => {
            updateBulkActions();
        });
    });

    function updateBulkActions() {
        const checkedBoxes = Array.from(taskCheckboxes).filter(chk => chk.checked && chk.closest('tr').style.display !== 'none');
        bulkActionsBar.classList.toggle('hidden', checkedBoxes.length === 0);
    }

    // Bulk action buttons
    document.getElementById('bulk-resolve-btn').addEventListener('click', () => {
        alert('Mark selected tasks as Resolved (implement AJAX backend update)');
    });

    document.getElementById('bulk-pending-btn').addEventListener('click', () => {
        alert('Mark selected tasks as Pending (implement AJAX backend update)');
    });

    document.getElementById('bulk-clear-btn').addEventListener('click', () => {
        taskCheckboxes.forEach(chk => chk.checked = false);
        updateBulkActions();
        selectAllCheckbox.checked = false;
    });

    // Initial filter call
    filterTable();
});
</script>
</body>
</html>
