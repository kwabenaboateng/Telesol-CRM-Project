<?php
/**
 * Team Member Model - Handles all team member-related operations
 */

require_once __DIR__ . '/../config/database.php';

class TeamMember {
    private $conn;
    private $table = 'team_members';
    
    public function __construct() {
        $this->conn = getDB();
    }
    
    /**
     * Get all team members with optional filters
     */
    public function getAll($filters = []) {
        try {
            $where = [];
            
            if (!empty($filters['department'])) {
                $dept = $this->conn->real_escape_string($filters['department']);
                $where[] = "department = '$dept'";
            }
            
            $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
            
            $query = "SELECT id, name, position, department, email, avatar FROM {$this->table} $where_clause ORDER BY name ASC";
            $result = $this->conn->query($query);
            
            $members = [];
            while ($row = $result->fetch_assoc()) {
                $members[] = $row;
            }
            
            return ['success' => true, 'data' => $members];
            
        } catch (Exception $e) {
            error_log("Get team members error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Add a new team member
     */
    public function add($data) {
        try {
            $name = $this->conn->real_escape_string($data['name']);
            $position = $this->conn->real_escape_string($data['position'] ?? '');
            $department = $this->conn->real_escape_string($data['department'] ?? '');
            $email = $this->conn->real_escape_string($data['email'] ?? '');
            $phone = $this->conn->real_escape_string($data['phone'] ?? '');
            
            $query = "INSERT INTO {$this->table} (name, position, department, email, phone) 
                      VALUES ('$name', '$position', '$department', '$email', '$phone')";
            
            if ($this->conn->query($query)) {
                return ['success' => true, 'id' => $this->conn->insert_id];
            } else {
                throw new Exception("Failed to add team member");
            }
            
        } catch (Exception $e) {
            error_log("Add team member error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>