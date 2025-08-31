<?php
/**
 * PostgreSQL Connection Test for KrishiGhor
 * Tests the Supabase PostgreSQL connection and displays connection information
 */

// Load configuration
require_once __DIR__ . '/../src/config/env.php';
require_once __DIR__ . '/../src/config/database.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>PostgreSQL Connection Test - KrishiGhor</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #10b981, #3b82f6); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .section { margin: 20px 0; padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px; }
        .success { background: #d1fae5; border-color: #10b981; }
        .error { background: #fee2e2; border-color: #ef4444; }
        .info { background: #dbeafe; border-color: #3b82f6; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .card { background: white; padding: 15px; border-radius: 6px; border: 1px solid #e5e7eb; }
        .status { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status.success { background: #10b981; color: white; }
        .status.error { background: #ef4444; color: white; }
        .status.warning { background: #f59e0b; color: white; }
        pre { background: #f3f4f6; padding: 15px; border-radius: 6px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: bold; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🚀 KrishiGhor PostgreSQL Connection Test</h1>
            <p>Testing Supabase PostgreSQL connection and configuration</p>
        </div>";

try {
    // Test 1: Environment Variables
    echo "<div class='section info'>
        <h2>📋 Environment Configuration</h2>
        <div class='grid'>";
    
    $envVars = [
        'DB_HOST' => DB_HOST,
        'DB_PORT' => DB_PORT,
        'DB_NAME' => DB_NAME,
        'DB_USER' => DB_USER,
        'DB_SSL_MODE' => DB_SSL_MODE,
        'APP_NAME' => APP_NAME,
        'APP_TIMEZONE' => APP_TIMEZONE
    ];
    
    foreach ($envVars as $key => $value) {
        $status = $value ? 'success' : 'error';
        echo "<div class='card'>
            <strong>$key:</strong> <span class='status $status'>" . ($value ?: 'Not Set') . "</span>
        </div>";
    }
    
    echo "</div></div>";
    
    // Test 2: Database Connection
    echo "<div class='section'>";
    echo "<h2>🔌 Database Connection Test</h2>";
    
    $startTime = microtime(true);
    $db = Database::getInstance();
    $connection = $db->getConnection();
    $endTime = microtime(true);
    
    $connectionTime = round(($endTime - $startTime) * 1000, 2);
    
    echo "<div class='card success'>
        <h3>✅ Connection Successful!</h3>
        <p><strong>Connection Time:</strong> {$connectionTime}ms</p>
        <p><strong>Status:</strong> <span class='status success'>Connected</span></p>
    </div>";
    
    // Test 3: Connection Information
    echo "<h3>📊 Connection Details</h3>";
    $connectionInfo = $db->getConnectionInfo();
    
    echo "<table>
        <tr><th>Property</th><th>Value</th></tr>
        <tr><td>Host</td><td>{$connectionInfo['host']}</td></tr>
        <tr><td>Port</td><td>{$connectionInfo['port']}</td></tr>
        <tr><td>Database</td><td>{$connectionInfo['database']}</td></tr>
        <tr><td>Username</td><td>{$connectionInfo['username']}</td></tr>
        <tr><td>Connected</td><td><span class='status " . ($connectionInfo['connected'] ? 'success' : 'error') . "'>" . ($connectionInfo['connected'] ? 'Yes' : 'No') . "</span></td></tr>
        <tr><td>Server Version</td><td>{$connectionInfo['server_version']}</td></tr>
    </table>";
    
    // Test 4: Database Operations
    echo "<h3>🧪 Database Operations Test</h3>";
    
    // Test basic query
    $stmt = $connection->query('SELECT version() as version, current_timestamp as current_time, current_database() as current_db');
    $result = $stmt->fetch();
    
    echo "<div class='card info'>
        <h4>Database Information</h4>
        <p><strong>Version:</strong> {$result['version']}</p>
        <p><strong>Current Time:</strong> {$result['current_time']}</p>
        <p><strong>Current Database:</strong> {$result['current_db']}</p>
    </div>";
    
    // Test timezone
    $stmt = $connection->query("SHOW timezone");
    $timezone = $stmt->fetchColumn();
    
    echo "<div class='card info'>
        <h4>Timezone Configuration</h4>
        <p><strong>Database Timezone:</strong> $timezone</p>
        <p><strong>PHP Timezone:</strong> " . date_default_timezone_get() . "</p>
        <p><strong>Current Time:</strong> " . date('Y-m-d H:i:s T') . "</p>
    </div>";
    
    // Test 5: Table Check
    echo "<h3>📋 Database Schema Check</h3>";
    
    $stmt = $connection->query("SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
    $tables = $stmt->fetchAll();
    
    if (count($tables) > 0) {
        echo "<div class='card success'>
            <h4>Found " . count($tables) . " Tables</h4>
            <table>
                <tr><th>Table Name</th><th>Type</th></tr>";
        
        foreach ($tables as $table) {
            echo "<tr><td>{$table['table_name']}</td><td>{$table['table_type']}</td></tr>";
        }
        
        echo "</table></div>";
    } else {
        echo "<div class='card warning'>
            <h4>No Tables Found</h4>
            <p>This is normal for a new database. Tables will be created when you run the migrations.</p>
        </div>";
    }
    
    // Test 6: SSL Connection
    echo "<h3>🔒 SSL Connection Test</h3>";
    
    $stmt = $connection->query("SELECT name, setting FROM pg_settings WHERE name IN ('ssl', 'ssl_ciphers')");
    $sslSettings = $stmt->fetchAll();
    
    echo "<div class='card info'>
        <h4>SSL Configuration</h4>";
    
    foreach ($sslSettings as $setting) {
        echo "<p><strong>{$setting['name']}:</strong> {$setting['setting']}</p>";
    }
    
    echo "</div>";
    
    echo "</div>";
    
    // Test 7: Performance Test
    echo "<div class='section info'>
        <h2>⚡ Performance Test</h2>";
    
    $iterations = 100;
    $startTime = microtime(true);
    
    for ($i = 0; $i < $iterations; $i++) {
        $stmt = $connection->query('SELECT 1');
        $stmt->fetch();
    }
    
    $endTime = microtime(true);
    $totalTime = round(($endTime - $startTime) * 1000, 2);
    $avgTime = round($totalTime / $iterations, 3);
    
    echo "<div class='card'>
        <h4>Query Performance</h4>
        <p><strong>Total Queries:</strong> $iterations</p>
        <p><strong>Total Time:</strong> {$totalTime}ms</p>
        <p><strong>Average Time:</strong> {$avgTime}ms per query</p>
        <p><strong>Queries per Second:</strong> " . round(1000 / $avgTime, 0) . "</p>
    </div>";
    
    echo "</div>";
    
    // Test 8: Connection String Test
    echo "<div class='section success'>
        <h2>🎯 Connection String Test</h2>
        <div class='card'>
            <h4>Your DATABASE_URL</h4>
            <pre>" . DATABASE_URL . "</pre>
            <p><strong>Status:</strong> <span class='status success'>Valid PostgreSQL Connection String</span></p>
            <p>This connection string can be used with other tools like:</p>
            <ul>
                <li>pgAdmin</li>
                <li>DBeaver</li>
                <li>Command line: <code>psql \"" . DATABASE_URL . "\"</code></li>
                <li>Python: <code>psycopg2.connect(\"" . DATABASE_URL . "\")</code></li>
            </ul>
        </div>
    </div>";
    
} catch (Exception $e) {
    echo "<div class='section error'>
        <h2>❌ Connection Failed</h2>
        <div class='card error'>
            <h4>Error Details</h4>
            <p><strong>Error:</strong> " . $e->getMessage() . "</p>
            <p><strong>File:</strong> " . $e->getFile() . "</p>
            <p><strong>Line:</strong> " . $e->getLine() . "</p>
        </div>
        
        <h3>🔧 Troubleshooting Steps</h3>
        <div class='card info'>
            <ol>
                <li>Verify your Supabase credentials are correct</li>
                <li>Check if your IP is whitelisted in Supabase</li>
                <li>Ensure the database is running and accessible</li>
                <li>Verify SSL requirements are met</li>
                <li>Check firewall settings</li>
            </ol>
        </div>
    </div>";
}

echo "</div></body></html>";
?>
