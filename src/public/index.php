<?php
/**
 * Front Controller / Entry Point
 * Handles routing for the KrishiGhor application
 */

// Start session
session_start();

// Load configuration
require_once __DIR__ . '/../src/config/app.php';
require_once __DIR__ . '/../src/config/database.php';

// Load controllers
require_once __DIR__ . '/../src/controllers/AuthController.php';
require_once __DIR__ . '/../src/controllers/ProductController.php';

// Set CORS headers for API requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get request URI and method
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove base path if needed
$basePath = '';
if ($basePath && strpos($requestUri, $basePath) === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}

// Route the request
try {
    switch (true) {
        // API Routes
        case preg_match('/^\/api\/auth\/register$/', $requestUri):
            $controller = new AuthController();
            $controller->register();
            break;
            
        case preg_match('/^\/api\/auth\/login$/', $requestUri):
            $controller = new AuthController();
            $controller->login();
            break;
            
        case preg_match('/^\/api\/auth\/logout$/', $requestUri):
            $controller = new AuthController();
            $controller->logout();
            break;
            
        case preg_match('/^\/api\/auth\/profile$/', $requestUri):
            $controller = new AuthController();
            if ($requestMethod === 'GET') {
                $controller->getProfile();
            } elseif ($requestMethod === 'PUT') {
                $controller->updateProfile();
            }
            break;
            
        case preg_match('/^\/api\/auth\/change-password$/', $requestUri):
            $controller = new AuthController();
            $controller->changePassword();
            break;
            
        case preg_match('/^\/api\/products$/', $requestUri):
            $controller = new ProductController();
            if ($requestMethod === 'GET') {
                $controller->index();
            } elseif ($requestMethod === 'POST') {
                $controller->create();
            }
            break;
            
        case preg_match('/^\/api\/products\/(\d+)$/', $requestUri, $matches):
            $controller = new ProductController();
            $id = $matches[1];
            if ($requestMethod === 'GET') {
                $controller->show($id);
            } elseif ($requestMethod === 'PUT') {
                $controller->update($id);
            } elseif ($requestMethod === 'DELETE') {
                $controller->delete($id);
            }
            break;
            
        case preg_match('/^\/api\/products\/categories$/', $requestUri):
            $controller = new ProductController();
            $controller->getCategories();
            break;
            
        case preg_match('/^\/api\/products\/farmer\/(\d+)$/', $requestUri, $matches):
            $controller = new ProductController();
            $controller->getByFarmer($matches[1]);
            break;
            
        case preg_match('/^\/api\/products\/search-suggestions$/', $requestUri):
            $controller = new ProductController();
            $controller->searchSuggestions();
            break;
            
        // Price API Routes
        case preg_match('/^\/api\/prices\/current$/', $requestUri):
            require_once __DIR__ . '/../src/controllers/PriceController.php';
            $controller = new PriceController();
            $controller->getCurrentPrices();
            break;
            
        case preg_match('/^\/api\/prices\/trends$/', $requestUri):
            require_once __DIR__ . '/../src/controllers/PriceController.php';
            $controller = new PriceController();
            $controller->getTrends();
            break;
            
        case preg_match('/^\/api\/prices\/forecast$/', $requestUri):
            require_once __DIR__ . '/../src/controllers/PriceController.php';
            $controller = new PriceController();
            $controller->getForecast();
            break;
            
        case preg_match('/^\/api\/prices\/anomalies$/', $requestUri):
            require_once __DIR__ . '/../src/controllers/PriceController.php';
            $controller = new PriceController();
            $controller->detectAnomalies();
            break;
            
        case preg_match('/^\/api\/prices\/where-to-sell$/', $requestUri):
            require_once __DIR__ . '/../src/controllers/PriceController.php';
            $controller = new PriceController();
            $controller->getWhereToSellRecommendations();
            break;
            
        case preg_match('/^\/api\/prices\/regional-comparison$/', $requestUri):
            require_once __DIR__ . '/../src/controllers/PriceController.php';
            $controller = new PriceController();
            $controller->getRegionalComparison();
            break;
            
        case preg_match('/^\/api\/prices$/', $requestUri):
            require_once __DIR__ . '/../src/controllers/PriceController.php';
            $controller = new PriceController();
            if ($requestMethod === 'POST') {
                $controller->addPrice();
            } else {
                $controller->getCurrentPrices();
            }
            break;
            
        // Order API Routes
        case preg_match('/^\/api\/orders$/', $requestUri):
            require_once __DIR__ . '/../src/controllers/OrderController.php';
            $controller = new OrderController();
            if ($requestMethod === 'POST') {
                $controller->placeOrder();
            } else {
                $controller->getOrders();
            }
            break;
            
        case preg_match('/^\/api\/orders\/(\d+)$/', $requestUri, $matches):
            require_once __DIR__ . '/../src/controllers/OrderController.php';
            $controller = new OrderController();
            $orderId = $matches[1];
            
            if ($requestMethod === 'GET') {
                $controller->getOrderDetails($orderId);
            } elseif ($requestMethod === 'POST') {
                $controller->updateOrderStatus($orderId);
            } elseif ($requestMethod === 'DELETE') {
                $controller->cancelOrder($orderId);
            }
            break;
            
        case preg_match('/^\/api\/orders\/(\d+)\/respond$/', $requestUri, $matches):
            require_once __DIR__ . '/../src/controllers/OrderController.php';
            $controller = new OrderController();
            $controller->respondToOrder($matches[1]);
            break;
            
        case preg_match('/^\/api\/orders\/(\d+)\/payment$/', $requestUri, $matches):
            require_once __DIR__ . '/../src/controllers/OrderController.php';
            $controller = new OrderController();
            $controller->processPayment($matches[1]);
            break;
            
        // Transport API Routes
        case preg_match('/^\/api\/transport\/request$/', $requestUri):
            require_once __DIR__ . '/../src/controllers/TransportController.php';
            $controller = new TransportController();
            $controller->requestTransport();
            break;
            
        case preg_match('/^\/api\/transport$/', $requestUri):
            require_once __DIR__ . '/../src/controllers/TransportController.php';
            $controller = new TransportController();
            $controller->getTransportRequests();
            break;
            
        case preg_match('/^\/api\/transport\/(\d+)\/tracking$/', $requestUri, $matches):
            require_once __DIR__ . '/../src/controllers/TransportController.php';
            $controller = new TransportController();
            $controller->getTrackingInfo($matches[1]);
            break;
            
        case preg_match('/^\/api\/transport\/(\d+)\/update$/', $requestUri, $matches):
            require_once __DIR__ . '/../src/controllers/TransportController.php';
            $controller = new TransportController();
            $controller->updateTransportStatus($matches[1]);
            break;
            
        case preg_match('/^\/api\/transport\/(\d+)\/confirm-delivery$/', $requestUri, $matches):
            require_once __DIR__ . '/../src/controllers/TransportController.php';
            $controller = new TransportController();
            $controller->confirmDelivery($matches[1]);
            break;
            
        case preg_match('/^\/api\/transport\/providers$/', $requestUri):
            require_once __DIR__ . '/../src/controllers/TransportController.php';
            $controller = new TransportController();
            $controller->getTransportProviders();
            break;
            
        case preg_match('/^\/api\/transport\/analytics$/', $requestUri):
            require_once __DIR__ . '/../src/controllers/TransportController.php';
            $controller = new TransportController();
            $controller->getAnalytics();
            break;
            
        // Frontend Routes
        case $requestUri === '/' || $requestUri === '/index.html':
            include __DIR__ . '/home.html';
            break;
            
        case $requestUri === '/home':
        case $requestUri === '/home.html':
            include __DIR__ . '/home.html';
            break;
            
        case $requestUri === '/register.php':
        case $requestUri === '/register':
        case $requestUri === '/register.html':
            include __DIR__ . '/register.html';
            break;
            
        case $requestUri === '/login':
        case $requestUri === '/login.html':
            include __DIR__ . '/login.html';
            break;
            
        // Auth API Routes
        case $requestUri === '/api/auth/login' && $requestMethod === 'POST':
            require_once __DIR__ . '/../src/controllers/AuthController.php';
            $controller = new AuthController();
            $controller->login();
            break;
            
        case $requestUri === '/api/auth/register' && $requestMethod === 'POST':
            require_once __DIR__ . '/../src/controllers/AuthController.php';
            $controller = new AuthController();
            $controller->register();
            break;
            
        case $requestUri === '/register' || $requestUri === '/register.html':
            include __DIR__ . '/register.html';
            break;
            
        // Dashboard Routes
        case $requestUri === '/dashboard/farmer':
        case $requestUri === '/dashboard/farmer.html':
            include __DIR__ . '/dashboard/farmer.html';
            break;
            
        case $requestUri === '/dashboard/buyer':
        case $requestUri === '/dashboard/buyer.html':
            include __DIR__ . '/dashboard/buyer.html';
            break;
            
        case $requestUri === '/dashboard/admin':
        case $requestUri === '/dashboard/admin.html':
            include __DIR__ . '/dashboard/admin.html';
            break;
            
        // Admin Dashboard Section Routes
        case $requestUri === '/dashboard/admin/users.html':
            include __DIR__ . '/dashboard/admin/users.html';
            break;
            
        case $requestUri === '/dashboard/admin/products.html':
            include __DIR__ . '/dashboard/admin/products.html';
            break;
            
        case $requestUri === '/dashboard/admin/orders.html':
            include __DIR__ . '/dashboard/admin/orders.html';
            break;
            
        case $requestUri === '/dashboard/admin/pricing.html':
            include __DIR__ . '/dashboard/admin/pricing.html';
            break;
            
        case $requestUri === '/dashboard/admin/transport.html':
            include __DIR__ . '/dashboard/admin/transport.html';
            break;
            
        case $requestUri === '/dashboard/admin/payments.html':
            include __DIR__ . '/dashboard/admin/payments.html';
            break;
            
        case $requestUri === '/dashboard/admin/analytics.html':
            $filePath = __DIR__ . '/dashboard/admin/analytics.html';
            if (file_exists($filePath)) {
                include $filePath;
            } else {
                http_response_code(404);
                echo '<h1>File Not Found</h1>';
                echo '<p>The file ' . htmlspecialchars($filePath) . ' does not exist.</p>';
                echo '<p>Current directory: ' . __DIR__ . '</p>';
                echo '<p>Request URI: ' . htmlspecialchars($requestUri) . '</p>';
            }
            break;
            
        case $requestUri === '/dashboard/admin/settings.html':
            include __DIR__ . '/dashboard/admin/settings.html';
            break;
            
        // Farmer Dashboard Section Routes
        case $requestUri === '/dashboard/farmer/products.html':
            include __DIR__ . '/dashboard/farmer/products.html';
            break;
            
        case $requestUri === '/dashboard/farmer/orders.html':
            include __DIR__ . '/dashboard/farmer/orders.html';
            break;
            
        case $requestUri === '/dashboard/farmer/pricing.html':
            include __DIR__ . '/dashboard/farmer/pricing.html';
            break;
            
        case $requestUri === '/dashboard/farmer/transport.html':
            include __DIR__ . '/dashboard/farmer/transport.html';
            break;
            
        case $requestUri === '/dashboard/farmer/analytics.html':
            include __DIR__ . '/dashboard/farmer/analytics.html';
            break;
            
        case $requestUri === '/dashboard/farmer/profile.html':
            include __DIR__ . '/dashboard/farmer/profile.html';
            break;
            
        // Buyer Dashboard Section Routes
        case $requestUri === '/dashboard/buyer/browse.html':
            include __DIR__ . '/dashboard/buyer/browse.html';
            break;
            
        case $requestUri === '/dashboard/buyer/orders.html':
            include __DIR__ . '/dashboard/buyer/orders.html';
            break;
            
        case $requestUri === '/dashboard/buyer/deliveries.html':
            include __DIR__ . '/dashboard/buyer/deliveries.html';
            break;
            
        case $requestUri === '/dashboard/buyer/wishlist.html':
            include __DIR__ . '/dashboard/buyer/wishlist.html';
            break;
            
        case $requestUri === '/dashboard/buyer/prices.html':
            include __DIR__ . '/dashboard/buyer/prices.html';
            break;
            
        case $requestUri === '/dashboard/buyer/suppliers.html':
            include __DIR__ . '/dashboard/buyer/suppliers.html';
            break;
            
        // Static assets
        case preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$/', $requestUri):
            // Let the web server handle static files
            return false;
            
        default:
            // 404 - Not Found
            http_response_code(404);
            if (strpos($requestUri, '/api/') === 0) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Endpoint not found']);
            } else {
                echo '<h1>404 - Page Not Found</h1>';
            }
            break;
    }
    
} catch (Exception $e) {
    error_log("Routing error: " . $e->getMessage());
    http_response_code(500);
    
    if (strpos($requestUri, '/api/') === 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Internal server error']);
    } else {
        echo '<h1>500 - Internal Server Error</h1>';
    }
}
