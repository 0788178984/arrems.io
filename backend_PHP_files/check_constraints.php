<?php
require_once 'config.php';

try {
    // Get all tables that have foreign keys referencing users table
    $sql = "
        SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM
            information_schema.KEY_COLUMN_USAGE
        WHERE
            REFERENCED_TABLE_SCHEMA = '" . DB_NAME . "' AND
            REFERENCED_TABLE_NAME = 'users'
    ";
    
    $stmt = $pdo->query($sql);
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($constraints) > 0) {
        echo "Found foreign key constraints referencing users table:\n\n";
        foreach ($constraints as $constraint) {
            echo "Table: {$constraint['TABLE_NAME']}\n";
            echo "Column: {$constraint['COLUMN_NAME']}\n";
            echo "Constraint Name: {$constraint['CONSTRAINT_NAME']}\n";
            echo "References: {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n\n";
        }
    } else {
        echo "No foreign key constraints found referencing users table.\n";
    }
    
} catch (PDOException $e) {
    die("ERROR: " . $e->getMessage() . "\n");
}
?> 