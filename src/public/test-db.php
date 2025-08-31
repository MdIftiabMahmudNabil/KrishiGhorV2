<?php
/**
 * Database Connection Test
 * Test file to verify Supabase PostgreSQL connection
 */

// Include database configuration
require_once __DIR__ . '/../src/config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Test database connection
    $db = Database::getInstance();
    $connection = $db->getConnection();
    
    // Test basic query
    $stmt = $connection->query("SELECT version() as db_version, current_database() as db_name, current_user as db_user");
    $result = $stmt->fetch();
    
    // Test timezone setting
    $timezoneStmt = $connection->query("SHOW timezone");
    $timezone = $timezoneStmt->fetch();
    
    // Get database statistics
    $tableCountStmt = $connection->query("
        SELECT COUNT(*) as table_count 
        FROM information_schema.tables 
        WHERE table_schema = 'public'
    ");
    $tableCount = $tableCountStmt->fetch();
    
    // Test if our tables exist
    $tables = ['users', 'products', 'orders', 'prices', 'transport'];
    $existingTables = [];
    
    foreach ($tables as $table) {
        $checkStmt = $connection->query("
            SELECT EXISTS (
                SELECT FROM information_schema.tables 
                WHERE table_schema = 'public' 
                AND table_name = '$table'
            ) as exists
        ");
        $exists = $checkStmt->fetch();
        $existingTables[$table] = $exists['exists'] === 't';
    }
    
    // Success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Database connection successful',
        'database' => [
            'name' => $result['db_name'],
            'user' => $result['db_user'],
            'version' => $result['db_version'],
            'timezone' => $timezone['TimeZone'] ?? 'Not set',
            'tables_count' => $tableCount['table_count'],
            'existing_tables' => $existingTables
        ],
        'connection_info' => [
            'host' => 'db.moozvhfbkhbepmjadijj.supabase.co',
            'port' => '5432',
            'database' => 'postgres',
            'ssl_mode' => 'require'
        ],
        'timestamp' => date('Y-m-d H:i:s T')
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Error response
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed',
        'error' => $e->getMessage(),
        'error_type' => get_class($e),
        'timestamp' => date('Y-m-d H:i:s T')
    ], JSON_PRETTY_PRINT);
}
?>
