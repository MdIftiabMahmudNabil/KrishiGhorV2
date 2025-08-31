<?php
/**
 * Simple routing test for KrishiGhor
 * This file helps debug routing and file access issues
 */

echo "<h1>KrishiGhor Routing Test</h1>";

// Current request info
echo "<h2>Request Information</h2>";
echo "<p><strong>REQUEST_URI:</strong> " . htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'Not set') . "</p>";
echo "<p><strong>SCRIPT_NAME:</strong> " . htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? 'Not set') . "</p>";
echo "<p><strong>PATH_INFO:</strong> " . htmlspecialchars($_SERVER['PATH_INFO'] ?? 'Not set') . "</p>";
echo "<p><strong>Current Directory:</strong> " . __DIR__ . "</p>";

// Check file existence
$testFiles = [
    '/dashboard/admin/analytics.html',
    '/dashboard/admin/users.html',
    '/dashboard/admin/products.html',
    '/dashboard/farmer/analytics.html',
    '/dashboard/buyer/browse.html'
];

echo "<h2>File Existence Check</h2>";
foreach ($testFiles as $file) {
    $fullPath = __DIR__ . $file;
    $exists = file_exists($fullPath);
    $readable = is_readable($fullPath);
    
    echo "<p><strong>$file:</strong> ";
    echo $exists ? "✓ EXISTS" : "✗ NOT FOUND";
    echo " | ";
    echo $readable ? "✓ READABLE" : "✗ NOT READABLE";
    echo " | Full path: " . htmlspecialchars($fullPath);
    echo "</p>";
}

// Test links
echo "<h2>Test Links</h2>";
echo "<p><a href='/dashboard/admin/analytics.html'>Admin Analytics</a></p>";
echo "<p><a href='/dashboard/admin/users.html'>Admin Users</a></p>";
echo "<p><a href='/dashboard/farmer/analytics.html'>Farmer Analytics</a></p>";
echo "<p><a href='/dashboard/buyer/browse.html'>Buyer Browse</a></p>";

// Server info
echo "<h2>Server Information</h2>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>Server Software:</strong> " . htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</p>";
echo "<p><strong>Document Root:</strong> " . htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? 'Not set') . "</p>";

// .htaccess check
$htaccessPath = __DIR__ . '/.htaccess';
echo "<p><strong>.htaccess file:</strong> " . (file_exists($htaccessPath) ? "✓ EXISTS" : "✗ NOT FOUND") . "</p>";

if (file_exists($htaccessPath)) {
    echo "<h3>.htaccess content:</h3>";
    echo "<pre>" . htmlspecialchars(file_get_contents($htaccessPath)) . "</pre>";
}
?>
