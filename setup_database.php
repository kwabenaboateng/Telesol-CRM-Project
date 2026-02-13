<?php
/**
 * Database setup script - Run this once to initialize the database
 */

require_once 'config/database.php';

try {
    $conn = getDB();
    
    // Read and execute SQL file
    $sql = file_get_contents('database.sql');
    
    // Split SQL into individual queries
    $queries = array_filter(array_map('trim', explode(';', $sql)));
    
    $success = true;
    $errors = [];
    
    // Execute each query
    foreach ($queries as $query) {
        if (!empty($query)) {
            try {
                $conn->query($query);
            } catch (mysqli_sql_exception $e) {
                $errors[] = "Error executing query: " . $e->getMessage();
                $success = false;
            }
        }
    }
    
    if ($success) {
        echo "<div style='background: #d4edda; color: #155724; padding: 20px; margin: 20px; border-radius: 5px;'>";
        echo "<h2>✅ Database setup completed successfully!</h2>";
        echo "<p>All tables have been created and initialized.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; margin: 20px; border-radius: 5px;'>";
        echo "<h2>❌ Database setup completed with errors</h2>";
        echo "<ul>";
        foreach ($errors as $error) {
            echo "<li>" . htmlspecialchars($error) . "</li>";
        }
        echo "</ul>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 20px; margin: 20px; border-radius: 5px;'>";
    echo "<h2>❌ Database setup failed</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

// Display connection info
echo "<div style='background: #e2e3e5; color: #383d41; padding: 15px; margin: 20px; border-radius: 5px;'>";
echo "<h3>Database Information:</h3>";
echo "<ul>";
echo "<li><strong>Host:</strong> " . DB_HOST . "</li>";
echo "<li><strong>Database:</strong> " . DB_NAME . "</li>";
echo "<li><strong>Username:</strong> " . DB_USERNAME . "</li>";
echo "<li><strong>Charset:</strong> " . DB_CHARSET . "</li>";
echo "</ul>";
echo "</div>";
?>