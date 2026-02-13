<?php
session_start();
$conn = new mysqli(
    "localhost",
    "root",
    "",
    "telesol crm"
);
function esc($str){ return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }

$tasks_result = $conn->query("
SELECT t.*, GROUP_CONCAT(tm.name SEPARATOR ', ') AS assigned_users
FROM tasks t
LEFT JOIN task_assignments ta ON t.id=ta.task_id
LEFT JOIN team_members tm ON ta.user_id=tm.id
GROUP BY t.id
ORDER BY t.created_at DESC
");

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Tasks - Telesol CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-4">
        <h2>All Tasks</h2>

        <div class="row">
        <?php while($row=$tasks_result->fetch_assoc()): ?>
<div class="col-md-4 mb-3">
<div class="card shadow-sm">
<div class="card-body">
<h5 class="card-title"><?= esc($row['task_title']) ?></h5>
<p class="card-text"><?= esc($row['task_description']) ?></p>
<p><strong>Assigned To:</strong> <?= esc($row['assigned_users']) ?></p>
<p><strong>Priority:</strong> <?= esc($row['priority']) ?></p>
<p><strong>Start:</strong> <?= esc($row['start_date']) ?> | <strong>Deadline:</strong> <?= esc($row['due_date']) ?></p>
<div class="d-flex justify-content-between">
<button class="btn btn-sm btn-success">Edit</button>
<button class="btn btn-sm btn-danger">Delete</button>
</div>
</div>
</div>
</div>
<?php endwhile; ?>
</div>
</div>
</body>
</html>
