<?php
/**
 * Migration script to update notifications table with missing columns
 * Run this script once to add the required columns for the enhanced notifications system
 */

require_once __DIR__ . '/includes/db.php';

echo "Starting notifications table migration...\n";

try {
    // Read the SQL migration file
    $sql_content = file_get_contents(__DIR__ . '/sql/update_notifications_table.sql');

    // Split the SQL into individual statements
    $statements = explode(';', $sql_content);

    $pdo->beginTransaction();

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue; // Skip empty lines and comments
        }

        echo "Executing: " . substr($statement, 0, 50) . "...\n";
        $pdo->exec($statement);
    }

    $pdo->commit();
    echo "✅ Migration completed successfully!\n";
    echo "The notifications table now has the following new columns:\n";
    echo "- target_audience (ENUM)\n";
    echo "- start_date (DATETIME)\n";
    echo "- end_date (DATETIME)\n";
    echo "- Added performance indexes\n";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Verify the migration by checking the table structure
try {
    echo "\n📋 Verifying table structure:\n";
    $result = $pdo->query("DESCRIBE notifications");

    while ($row = $result->fetch()) {
        echo "- {$row['Field']} ({$row['Type']}) {$row['Null']} {$row['Key']} {$row['Default']}\n";
    }

} catch (Exception $e) {
    echo "Warning: Could not verify table structure: " . $e->getMessage() . "\n";
}

echo "\n🎉 You can now use the enhanced notifications system!\n";
?>