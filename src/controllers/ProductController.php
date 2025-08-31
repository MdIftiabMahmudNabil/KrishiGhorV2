<?php
/**
 * Product Controller
 * Handles product management operations
 */

require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/ProductSearchService.php';
require_once __DIR__ . '/../services/ImageContentService.php';

class ProductController {
    private $productModel;
    private $userModel;
    private $searchService;
    private $imageContentService;
    
    public function __construct() {
        $this->productModel = new Product();
        $this->userModel = new User();
        $this->searchService = new ProductSearchService();
        $this->imageContentService = new ImageContentService();
    }
    
    public function index() {
        try {
            $filters = [];
            $limit = intval($_GET['limit'] ?? 20);
            $offset = intval($_GET['offset'] ?? 0);
            
            // Build filters from query parameters
            if (!empty($_GET['category'])) {
                $filters['category'] = $_GET['category'];
            }
            if (!empty($_GET['location'])) {
                $filters['location'] = $_GET['location'];
            }
            if (!empty($_GET['search'])) {
                $filters['search'] = $_GET['search'];
            }
            if (!empty($_GET['min_price'])) {
                $filters['min_price'] = floatval($_GET['min_price']);
            }
            if (!empty($_GET['max_price'])) {
                $filters['max_price'] = floatval($_GET['max_price']);
            }
            if (isset($_GET['organic_only']) && $_GET['organic_only'] === 'true') {
                $filters['organic_only'] = true;
            }
            if (isset($_GET['available_only']) && $_GET['available_only'] === 'true') {
                $filters['available_only'] = true;
            }
            if (!empty($_GET['region'])) {
                $filters['region'] = $_GET['region'];
            }
            
            // Use advanced search if search query provided
            if (!empty($filters['search'])) {
                $products = $this->searchService->searchProducts($filters['search'], $filters, $limit, $offset);
            } else {
                $products = $this->productModel->search($filters, $limit, $offset);
            }
            
            $this->sendResponse(200, [
                'products' => $products,
                'filters' => $filters,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'count' => count($products)
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Get products error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    public function show($id) {
        try {
            $product = $this->productModel->findById($id);
            
            if (!$product) {
                $this->sendResponse(404, ['error' => 'Product not found']);
                return;
            }
            
            $this->sendResponse(200, ['product' => $product]);
            
        } catch (Exception $e) {
            error_log("Get product error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    public function create() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        $user = $this->getCurrentUser();
        if (!$user || $user['role'] !== 'farmer') {
            $this->sendResponse(403, ['error' => 'Only farmers can create products']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $required = ['name', 'category', 'quantity', 'unit', 'price_per_unit'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->sendResponse(400, ['error' => "Field '{$field}' is required"]);
                return;
            }
        }
        
        // Add farmer ID
        $data['farmer_id'] = $user['user_id'];
        
        try {
            $productId = $this->productModel->create($data);
            
            if ($productId) {
                $product = $this->productModel->findById($productId);
                $this->sendResponse(201, [
                    'message' => 'Product created successfully',
                    'product' => $product
                ]);
            } else {
                $this->sendResponse(500, ['error' => 'Failed to create product']);
            }
            
        } catch (Exception $e) {
            error_log("Create product error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    public function update($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        $user = $this->getCurrentUser();
        if (!$user) {
            $this->sendResponse(401, ['error' => 'Unauthorized']);
            return;
        }
        
        try {
            $product = $this->productModel->findById($id);
            
            if (!$product) {
                $this->sendResponse(404, ['error' => 'Product not found']);
                return;
            }
            
            // Check if user owns the product or is admin
            if ($user['role'] !== 'admin' && $product['farmer_id'] != $user['user_id']) {
                $this->sendResponse(403, ['error' => 'Permission denied']);
                return;
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            $updated = $this->productModel->update($id, $data);
            
            if ($updated) {
                $product = $this->productModel->findById($id);
                $this->sendResponse(200, [
                    'message' => 'Product updated successfully',
                    'product' => $product
                ]);
            } else {
                $this->sendResponse(500, ['error' => 'Failed to update product']);
            }
            
        } catch (Exception $e) {
            error_log("Update product error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    public function delete($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        $user = $this->getCurrentUser();
        if (!$user) {
            $this->sendResponse(401, ['error' => 'Unauthorized']);
            return;
        }
        
        try {
            $product = $this->productModel->findById($id);
            
            if (!$product) {
                $this->sendResponse(404, ['error' => 'Product not found']);
                return;
            }
            
            // Check if user owns the product or is admin
            if ($user['role'] !== 'admin' && $product['farmer_id'] != $user['user_id']) {
                $this->sendResponse(403, ['error' => 'Permission denied']);
                return;
            }
            
            $deleted = $this->productModel->softDelete($id);
            
            if ($deleted) {
                $this->sendResponse(200, ['message' => 'Product deleted successfully']);
            } else {
                $this->sendResponse(500, ['error' => 'Failed to delete product']);
            }
            
        } catch (Exception $e) {
            error_log("Delete product error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    public function getByFarmer($farmerId) {
        try {
            $limit = intval($_GET['limit'] ?? 20);
            $offset = intval($_GET['offset'] ?? 0);
            
            $products = $this->productModel->getByFarmer($farmerId, $limit, $offset);
            
            $this->sendResponse(200, [
                'products' => $products,
                'farmer_id' => $farmerId,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'count' => count($products)
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Get farmer products error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    public function getCategories() {
        try {
            $categories = $this->productModel->getCategories();
            $this->sendResponse(200, ['categories' => $categories]);
            
        } catch (Exception $e) {
            error_log("Get categories error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    public function getMarketStats() {
        try {
            $category = $_GET['category'] ?? null;
            $stats = $this->productModel->getMarketStats($category);
            
            $this->sendResponse(200, [
                'market_stats' => $stats,
                'category' => $category
            ]);
            
        } catch (Exception $e) {
            error_log("Get market stats error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    public function uploadImages($id) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        $user = $this->getCurrentUser();
        if (!$user) {
            $this->sendResponse(401, ['error' => 'Unauthorized']);
            return;
        }
        
        try {
            $product = $this->productModel->findById($id);
            
            if (!$product) {
                $this->sendResponse(404, ['error' => 'Product not found']);
                return;
            }
            
            // Check if user owns the product
            if ($user['role'] !== 'admin' && $product['farmer_id'] != $user['user_id']) {
                $this->sendResponse(403, ['error' => 'Permission denied']);
                return;
            }
            
            if (empty($_FILES['images'])) {
                $this->sendResponse(400, ['error' => 'No images uploaded']);
                return;
            }
            
            $uploadedImages = [];
            $uploadDir = 'uploads/products/' . $id . '/';
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $files = $_FILES['images'];
            $fileCount = count($files['name']);
            
            for ($i = 0; $i < $fileCount; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $fileInfo = pathinfo($files['name'][$i]);
                    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                    
                    if (in_array(strtolower($fileInfo['extension']), $allowedTypes)) {
                        // Validate image content for safety
                        $validationResult = $this->imageContentService->validateImage([
                            'tmp_name' => $files['tmp_name'][$i],
                            'size' => $files['size'][$i],
                            'type' => $files['type'][$i],
                            'name' => $files['name'][$i]
                        ], $files['name'][$i]);
                        
                        if (!$validationResult['valid']) {
                            error_log("Image validation failed: " . implode(', ', $validationResult['errors']));
                            continue; // Skip invalid images
                        }
                        
                        if (!$validationResult['safe']) {
                            error_log("Image safety check failed: " . implode(', ', $validationResult['warnings']));
                            // Continue but log warnings - could be made configurable
                        }
                        
                        $fileName = uniqid() . '.' . $fileInfo['extension'];
                        $filePath = $uploadDir . $fileName;
                        
                        if (move_uploaded_file($files['tmp_name'][$i], $filePath)) {
                            $uploadedImages[] = $filePath;
                        }
                    }
                }
            }
            
            if (!empty($uploadedImages)) {
                // Update product with new images
                $currentImages = $product['images'] ?? [];
                $allImages = array_merge($currentImages, $uploadedImages);
                
                $this->productModel->update($id, ['images' => $allImages]);
                
                $this->sendResponse(200, [
                    'message' => 'Images uploaded successfully',
                    'uploaded_images' => $uploadedImages
                ]);
            } else {
                $this->sendResponse(400, ['error' => 'No valid images uploaded']);
            }
            
        } catch (Exception $e) {
            error_log("Upload images error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    public function searchSuggestions() {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        $query = $_GET['q'] ?? '';
        $limit = intval($_GET['limit'] ?? 10);
        
        if (strlen($query) < 2) {
            $this->sendResponse(200, ['suggestions' => []]);
            return;
        }
        
        try {
            $suggestions = $this->searchService->getSearchSuggestions($query, $limit);
            
            $this->sendResponse(200, [
                'suggestions' => $suggestions,
                'query' => $query
            ]);
            
        } catch (Exception $e) {
            error_log("Search suggestions error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Failed to get search suggestions']);
        }
    }
    
    private function getCurrentUser() {
        // Get user from JWT token (implement JWT verification)
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }
        
        $token = $matches[1];
        return $this->verifyJWT($token);
    }
    
    private function verifyJWT($token) {
        // Implement JWT verification (similar to AuthController)
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        
        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
        
        $signature = base64url_decode($signatureEncoded);
        $expectedSignature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, AppConfig::get('auth.jwt_secret'), true);
        
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }
        
        $payload = json_decode(base64url_decode($payloadEncoded), true);
        
        if (!$payload || $payload['exp'] < time()) {
            return null;
        }
        
        return $payload;
    }
    
    private function sendResponse($statusCode, $data) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
