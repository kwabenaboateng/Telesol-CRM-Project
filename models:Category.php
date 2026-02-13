<?php
/**
 * Category Model - Handles all category-related operations
 */

require_once __DIR__ . '/../config/database.php';

class Category {
    private $conn;
    private $table = 'task_categories';
    
    public function __construct() {
        $this->conn = getDB();
    }
    
    /**
     * Get all categories
     */
    public function getAll() {
        try {
            $query = "SELECT * FROM {$this->table} ORDER BY name ASC";
            $result = $this->conn->query($query);
            
            $categories = [];
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row;
            }
            
            return ['success' => true, 'data' => $categories];
            
        } catch (Exception $e) {
            error_log("Get categories error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Add a new category
     */
    public function add($data) {
        try {
            $name = $this->conn->real_escape_string($data['name']);
            $description = $this->conn->real_escape_string($data['description'] ?? '');
            $color = $this->conn->real_escape_string($data['color'] ?? '#3498db');
            
            $query = "INSERT INTO {$this->table} (name, description, color) VALUES ('$name', '$description', '$color')";
            
            if ($this->conn->query($query)) {
                return ['success' => true, 'id' => $this->conn->insert_id];
            } else {
                throw new Exception("Failed to add category");
            }
            
        } catch (Exception $e) {
            error_log("Add category error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>