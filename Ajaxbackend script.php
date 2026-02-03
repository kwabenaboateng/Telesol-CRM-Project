<?php
session_start();
header('Content-Type: application/json');

$conn = new mysqli('localhost', 'root', '', 'telesol crm');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'DB connection failed']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'bulk_update_status':
        $taskIdsRaw = $_POST['task_ids'] ?? '';
        $taskIds = array_filter(array_map('intval', explode(',', $taskIdsRaw)));
        $newStatus = $_POST['new_status'] ?? '';
        $allowedStatuses = ['New', 'In Progress', 'Resolved', 'Pending'];

        if (empty($taskIds) || !in_array($newStatus, $allowedStatuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit;
        }

        $idsList = implode(',', $taskIds);
        $stmt = $conn->prepare("UPDATE tickets SET issue_status=? WHERE id IN ($idsList)");
        $stmt->bind_param('s', $newStatus);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed']);
        }
        break;

    case 'get_task_details':
        $taskId = intval($_POST['task_id'] ?? 0);
        if (!$taskId) {
            echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
            exit;
        }
        $result = $conn->query("SELECT * FROM tickets WHERE id=$taskId LIMIT 1");
        if ($result && $task = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'task' => $task]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Task not found']);
        }
        break;

    case 'save_task':
        $taskId = intval($_POST['task_id'] ?? 0);
        $status = $_POST['issue_status'] ?? '';
        $comments = $_POST['comments'] ?? '';
        $allowedStatuses = ['New', 'In Progress', 'Resolved', 'Pending'];

        if (!$taskId || !in_array($status, $allowedStatuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid data']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE tickets SET issue_status=?, comments=? WHERE id=?");
        $stmt->bind_param('ssi', $status, $comments, $taskId);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Save failed']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$conn->close();
