<?php
/**
 * Health Check Endpoint for Render
 * This file helps Render monitor if the application is running properly
 */

header('Content-Type: application/json');

try {
    // Check if we can load the configuration
    require_once __DIR__ . '/../src/config/env.php';
    
    // Check if we can connect to the database
    require_once __DIR__ . '/../src/config/database.php';
    $db = Database::getInstance();
    $connection = $db->getConnection();
    
    // Test a simple query
    $stmt = $connection->query('SELECT 1 as test');
    $result = $stmt->fetch();
    
    if ($result && $result['test'] == 1) {
        $status = 'healthy';
        $message = 'Application is running normally';
        $httpCode = 200;
    } else {
        $status = 'degraded';
        $message = 'Database connection test failed';
        $httpCode = 503;
    }
    
} catch (Exception $e) {
    $status = 'unhealthy';
    $message = 'Application error: ' . $e->getMessage();
    $httpCode = 500;
}

http_response_code($httpCode);

echo json_encode([
    'status' => $status,
    'message' => $message,
    'timestamp' => date('Y-m-d H:i:s'),
    'version' => '1.0.0',
    'environment' => 'production'
]);
