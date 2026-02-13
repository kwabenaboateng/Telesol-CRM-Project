<?php
/**
 * Task Model - Handles all task-related database operations
 */

require_once __DIR__ . '/../config/database.php';

class Task {
    private $conn;
    private $table = 'tasks';
    
    public function __construct() {
        $this->conn = getDB();
    }
    
    /**
     * Create a new task
     */
    public function create($data) {
        try {
            // Validate required fields
            if (empty($data['task_title']) || empty($data['assigned_to'])) {
                throw new Exception("Task title and assigned to are required.");
            }
            
            // Prepare data
            $task_title = $this->conn->real_escape_string($data['task_title']);
            $task_description = $this->conn->real_escape_string($data['task_description'] ?? '');
            $assigned_to = $this->conn->real_escape_string(implode(',', $data['assigned_to']));
            $assigned_by = $this->conn->real_escape_string($data['assigned_by'] ?? 'User');
            $priority = $this->conn->real_escape_string($data['priority'] ?? 'Medium');
            $category = $this->conn->real_escape_string($data['category'] ?? '');
            $start_date = !empty($data['start_date']) ? "'" . $this->conn->real_escape_string($data['start_date']) . "'" : "NULL";
            $due_date = !empty($data['due_date']) ? "'" . $this->conn->real_escape_string($data['due_date']) . "'" : "NULL";
            $estimated_hours = !empty($data['estimated_hours']) ? floatval($data['estimated_hours']) : "NULL";
            $status = "'Pending'";
            $department = "'Administration'";
            
            // Build query
            $query = "INSERT INTO {$this->table} (
                task_title, task_description, department, assigned_to, assigned_by, 
                priority, category, start_date, due_date, estimated_hours, status, created_at
            ) VALUES (
                '$task_title', '$task_description', $department, '$assigned_to', '$assigned_by',
                '$priority', '$category', $start_date, $due_date, $estimated_hours, $status, NOW()
            )";
            
            // Execute query
            if ($this->conn->query($query)) {
                return ['success' => true, 'id' => $this->conn->insert_id];
            } else {
                throw new Exception("Failed to create task: " . $this->conn->error);
            }
            
        } catch (Exception $e) {
            error_log("Task creation error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get all tasks with optional filters
     */
    public function getAll($filters = []) {
        try {
            $where = [];
            
            if (!empty($filters['department'])) {
                $dept = $this->conn->real_escape_string($filters['department']);
                $where[] = "department = '$dept'";
            }
            
            if (!empty($filters['status'])) {
                $status = $this->conn->real_escape_string($filters['status']);
                $where[] = "status = '$status'";
            }
            
            if (!empty($filters['priority'])) {
                $priority = $this->conn->real_escape_string($filters['priority']);
                $where[] = "priority = '$priority'";
            }
            
            $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
            
            $query = "SELECT * FROM {$this->table} $where_clause ORDER BY created_at DESC";
            $result = $this->conn->query($query);
            
            $tasks = [];
            while ($row = $result->fetch_assoc()) {
                $tasks[] = $row;
            }
            
            return ['success' => true, 'data' => $tasks];
            
        } catch (Exception $e) {
            error_log("Get tasks error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get task statistics
     */
    public function getStats($department = 'Administration') {
        try {
            $dept = $this->conn->real_escape_string($department);
            
            $query = "
                SELECT 
                    COUNT(*) as total_tasks,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_tasks,
                    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_tasks,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
                    SUM(CASE WHEN status = 'On Hold' THEN 1 ELSE 0 END) as on_hold_tasks,
                    SUM(CASE WHEN priority = 'Critical' AND status != 'Completed' THEN 1 ELSE 0 END) as critical_tasks,
                    SUM(CASE WHEN due_date < CURDATE() AND status != 'Completed' THEN 1 ELSE 0 END) as overdue_tasks
                FROM {$this->table} 
                WHERE department = '$dept'
            ";
            
            $result = $this->conn->query($query);
            $stats = $result->fetch_assoc();
            
            // Ensure all values are set
            $default_stats = [
                'total_tasks' => 0,
                'pending_tasks' => 0,
                'in_progress_tasks' => 0,
                'completed_tasks' => 0,
                'on_hold_tasks' => 0,
                'critical_tasks' => 0,
                'overdue_tasks' => 0
            ];
            
            return ['success' => true, 'data' => array_merge($default_stats, $stats)];
            
        } catch (Exception $e) {
            error_log("Get stats error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Update task status
     */
    public function updateStatus($task_id, $status) {
        try {
            $id = intval($task_id);
            $status = $this->conn->real_escape_string($status);
            
            $completed_at = ($status == 'Completed') ? ", completed_at = NOW()" : "";
            
            $query = "UPDATE {$this->table} SET status = '$status' $completed_at WHERE id = $id";
            
            if ($this->conn->query($query)) {
                return ['success' => true];
            } else {
                throw new Exception("Failed to update status");
            }
            
        } catch (Exception $e) {
            error_log("Update status error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get a single task by ID
     */
    public function getById($task_id) {
        try {
            $id = intval($task_id);
            $query = "SELECT * FROM {$this->table} WHERE id = $id";
            $result = $this->conn->query($query);
            
            if ($result && $result->num_rows > 0) {
                return ['success' => true, 'data' => $result->fetch_assoc()];
            } else {
                return ['success' => false, 'error' => 'Task not found'];
            }
            
        } catch (Exception $e) {
            error_log("Get task error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>