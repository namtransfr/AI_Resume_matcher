<?php
require_once 'config.php';

$sql = file_get_contents('database_updates.sql');

// Split into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql)));

foreach ($statements as $statement) {
    if (!empty($statement)) {
        if ($conn->query($statement) === TRUE) {
            echo "Executed: " . substr($statement, 0, 50) . "...\n";
        } else {
            echo "Error executing: " . $conn->error . "\n";
        }
    }
}

echo "Database update completed.\n";
?>