<?php
require_once 'config.php';

try {
    $db = getDBConnection();
    
    // Read and execute the SQL file
    $sql = file_get_contents('create_tables.sql');
    
    // Split the SQL file into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    // Execute each statement
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $db->exec($statement);
        }
    }
    
    echo "Tables created successfully!";
} catch(PDOException $e) {
    die("Error creating tables: " . $e->getMessage());
}
?> 