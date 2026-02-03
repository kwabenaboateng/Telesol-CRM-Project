<?php
session_start();

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

$engineerTasks = [];
foreach ($technology_engineers as $engineer) {
    $engineerTasks[$engineer] = [
        'active' => 0,
        'completed' => 0,
        'overdue' => 0,
        'tasks' => []
    ];
}

foreach ($allTasks as $task) {
    $engineer = $task['assigned_persons'];
    if (in_array($engineer, $technology_engineers)) {
        $engineerTasks[$engineer]['tasks'][] = $task;
        if ($task['issue_status'] != 'Resolved') $engineerTasks[$engineer]['active']++;
        else $engineerTasks[$engineer]['completed']++;
        if ($task['resolved_by_deadline'] && strtotime($task['resolved_by_deadline']) < time() && $task['issue_status'] != 'Resolved') $engineerTasks[$engineer]['overdue']++;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Engineer Assignments - Telesol CRM</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
<style>
body { font-family: Arial, sans-serif; margin:0; background:#f5f7fa; color:#333; display:flex; min-height: 100vh; }
.sidebar { background:#2c3e50; color:#fff; width:250px; height:100vh; position:fixed; overflow-y:auto; }
.sidebar-header { padding:20px; text-align:center; font-size:1.5em; font-weight:bold; background:#1c2b3a; }
.sidebar-menu ul { list-style:none; padding:0; }
.sidebar-menu li { margin:5px 0; }
.sidebar-menu a { color:#fff; display:block; padding:12px 20px; text-decoration:none; transition: background 0.3s ease; }
.sidebar-menu a.active, .sidebar-menu a:hover { background:#3498db; }
.main-content { margin-left:250px; flex:1; display:flex; flex-direction:column; }
.header { background:#fff; padding:15px 30px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 5px rgba(0,0,0,.1); position:sticky; top:0; z-index:100; }
.user-info { display:flex; align-items:center; gap:10px; }
.user-info img { border-radius:50%; width:40px; height:40px; }
.db-status { font-size:0.9em; background:#2ecc71; padding:5px 10px; border-radius:5px; color:#fff; display:flex; align-items:center; gap:5px; }
.content { padding:20px; flex:1; overflow-y:auto; }
.engineer-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:20px; }
.engineer-card { background:#fff; border-radius:8px; box-shadow:0 2px 10px rgba(0,0,0,0.05); transition:transform 0.3s ease; cursor:pointer; }
.engineer-card:hover { transform: translateY(-5px); }
.engineer-header { background:#2c3e50; color:#fff; padding:15px; display:flex; align-items:center; border-top-left-radius:8px; border-top-right-radius:8px; }
.engineer-avatar { border-radius:50%; width:50px; height:50px; margin-right:15px; border:3px solid rgba(255,255,255,0.3); }
.engineer-info h3 { margin-bottom:5px; }
.engineer-info p { margin:0; font-size:0.9em; opacity:0.8; }
.engineer-stats { display:flex; justify-content:space-around; padding:10px; background:#f8f9fa; }
.stat { text-align:center; }
.stat-value { font-size:18px; font-weight:700; }
.stat-label { font-size:12px; color:#7f8c8d; }
.engineer-tasks { max-height:300px; overflow-y:auto; padding:15px; }
.task-item { padding:10px 0; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; }
.task-item:last-child { border-bottom:none; }
.task-title { font-weight:500; }
.status { padding:3px 8px; border-radius:15px; font-size:0.75em; font-weight:600; display:inline-block; }
.status-new { background:#3498db; color:#fff; }
.status-resolved { background:#2ecc71; color:#fff; }
.status-in-progress { background:#f1c40f; color:#333; }
.status-pending { background:#e74c3c; color:#fff; }
</style>
</head>
<body>
<div class="sidebar">
    <div class="sidebar-header">Telesol CRM</div>
    <div class="sidebar-menu">
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="tech_dashboard.php"><i class="fas fa-tasks"></i> All Tasks</a></li>
            <li><a href="engineers.php" class="active"><i class="fas fa-users"></i> Engineer Assignments</a></li>
            <li><a href="#"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
</div>

<div class="main-content">
    <div class="header">
        <h2>Engineer Assignments</h2>
        <div class="user-info">
            <span class="db-status" aria-label="Database connected"><i class="fas fa-check-circle"></i> DB Connected</span>
            <img src="https://ui-avatars.com/api/?name=Admin+User&background=3498db&color=fff" alt="User" />
            <span>Admin User</span>
        </div>
    </div>

    <div class="content">
        <div class="engineer-grid" role="list">
            <?php foreach ($engineerTasks as $engineerName => $engineerData):
                if (empty($engineerData['tasks'])) continue;
                $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($engineerName) . "&background=3498db&color=fff";
            ?>
            <div class="engineer-card" role="listitem" tabindex="0" aria-label="<?php echo $engineerName; ?>, assigned tasks">
                <div class="engineer-header">
                    <img src="<?php echo $avatarUrl; ?>" alt="Avatar of <?php echo $engineerName; ?>" class="engineer-avatar" />
                    <div class="engineer-info">
                        <h3><?php echo $engineerName; ?></h3>
                        <p>Technology Engineer</p>
                    </div>
                </div>
                <div class="engineer-stats" aria-label="Task summary for <?php echo $engineerName; ?>">
                    <div class="stat">
                        <div class="stat-value"><?php echo $engineerData['active']; ?></div>
                        <div class="stat-label">Active</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value"><?php echo $engineerData['completed']; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value"><?php echo $engineerData['overdue']; ?></div>
                        <div class="stat-label">Overdue</div>
                    </div>
                </div>
                <div class="engineer-tasks" aria-label="Tasks assigned to <?php echo $engineerName; ?>">
                    <?php foreach (array_slice($engineerData['tasks'], 0, 5) as $task):
                        $statusClass = 'status-new';
                        if ($task['issue_status'] == 'Resolved') $statusClass = 'status-resolved';
                        elseif ($task['issue_status'] == 'In Progress') $statusClass = 'status-in-progress';
                        elseif ($task['issue_status'] == 'Pending') $statusClass = 'status-pending';
                    ?>
                    <div class="task-item">
                        <div class="task-title"><?php echo htmlspecialchars($task['customer_name']); ?></div>
                        <div class="status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($task['issue_status']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
</body>
</html>
