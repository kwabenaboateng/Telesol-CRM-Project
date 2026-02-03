<?php
// Include DB connection
include 'db_connect.php'; 

// Count overall issues assigned to tech department
$sql_issues = "SELECT COUNT(*) AS total_issues FROM issues WHERE department = 'technology'";
$result_issues = $conn->query($sql_issues);
$total_issues = $result_issues->fetch_assoc()['total_issues'] ?? 0;

// Count installations assigned to tech department
$sql_installations = "SELECT COUNT(*) AS total_installations FROM installations WHERE department = 'technology'";
$result_installations = $conn->query($sql_installations);
$total_installations = $result_installations->fetch_assoc()['total_installations'] ?? 0;

// Count engineers assigned to tech department
$sql_engineers = "SELECT COUNT(*) AS total_engineers FROM engineers WHERE department = 'technology'";
$result_engineers = $conn->query($sql_engineers);
$total_engineers = $result_engineers->fetch_assoc()['total_engineers'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Technology Department Dashboard</title>
<style>
  body { font-family: Arial, sans-serif; margin: 20px; background: #f5f7fa; }
  h1 { color: #333; }
  .card { background: #fff; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);
          padding: 20px; margin-bottom: 20px; width: 300px; display: inline-block; vertical-align: top; }
  .count { font-size: 3em; color: #007BFF; }
  .label { font-weight: bold; color: #555; }
  a.button { background: #007BFF; color: white; text-decoration: none; padding: 10px 15px; border-radius: 5px;}
  a.button:hover { background: #0056b3; }
</style>
</head>
<body>

<h1>Technology Department Dashboard</h1>

<div class="card">
  <div class="count"><?= $total_issues ?></div>
  <div class="label">Total Issues Assigned</div>
</div>

<div class="card">
  <div class="count"><?= $total_installations ?></div>
  <div class="label">Total Installations Assigned</div>
</div>

<div class="card">
  <div class="count"><?= $total_engineers ?></div>
  <div class="label">Engineers in Department</div>
</div>

<br>

<a href="tech_issues_installations.php" class="button">View Issues & Installations</a>

</body>
</html>
