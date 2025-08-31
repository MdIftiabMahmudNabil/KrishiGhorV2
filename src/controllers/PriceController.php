<?php
/**
 * Price Controller
 * Handles market pricing data, analytics, and forecasting
 */

require_once __DIR__ . '/../models/Price.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../services/AIService.php';
require_once __DIR__ . '/../services/ForecastingService.php';
require_once __DIR__ . '/../services/TransportCostService.php';

class PriceController {
    private $priceModel;
    private $productModel;
    private $aiService;
    private $forecastingService;
    private $transportService;
    
    public function __construct() {
        $this->priceModel = new Price();
        $this->productModel = new Product();
        $this->aiService = new AIService();
        $this->forecastingService = new ForecastingService();
        $this->transportService = new TransportCostService();
    }
    
    /**
     * Get current market prices
     */
    public function getCurrentPrices() {
        try {
            $location = $_GET['location'] ?? null;
            $category = $_GET['category'] ?? null;
            
            $prices = $this->priceModel->getCurrentMarketPrices($location);
            
            // Filter by category if specified
            if ($category) {
                $prices = array_filter($prices, function($price) use ($category) {
                    return $price['category'] === $category;
                });
            }
            
            $this->sendResponse(200, [
                'current_prices' => array_values($prices),
                'location' => $location,
                'category' => $category,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            error_log("Get current prices error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Failed to fetch current prices']);
        }
    }
    
    /**
     * Get price trends and analytics
     */
    public function getTrends() {
        try {
            $category = $_GET['category'] ?? 'rice';
            $days = intval($_GET['days'] ?? 30);
            $location = $_GET['location'] ?? null;
            
            $trends = $this->priceModel->getMarketTrends($category, $days);
            $analytics = $this->priceModel->getPriceAnalytics($category, $days);
            
            $this->sendResponse(200, [
                'trends' => $trends,
                'analytics' => $analytics,
                'category' => $category,
                'days' => $days,
                'location' => $location
            ]);
            
        } catch (Exception $e) {
            error_log("Get price trends error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Failed to fetch price trends']);
        }
    }
    
    /**
     * Get price forecasts with confidence bands
     */
    public function getForecast() {
        try {
            $category = $_GET['category'] ?? 'rice';
            $location = $_GET['location'] ?? 'Dhaka';
            $days = intval($_GET['days'] ?? 7);
            
            // Get historical data for forecasting
            $historicalPrices = $this->priceModel->getByCategory($category, 
                date('Y-m-d', strtotime('-90 days')), 
                date('Y-m-d'), 
                $location
            );
            
            // Generate advanced forecast
            $forecast = $this->forecastingService->generateForecast($historicalPrices, $days);
            
            // Add market insights
            $marketAnalysis = $this->aiService->analyzeMarketTrends($category);
            
            $this->sendResponse(200, [
                'forecast' => $forecast,
                'market_analysis' => $marketAnalysis,
                'category' => $category,
                'location' => $location,
                'forecast_horizon' => $days,
                'generated_at' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            error_log("Get price forecast error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Failed to generate price forecast']);
        }
    }
    
    /**
     * Detect price anomalies
     */
    public function detectAnomalies() {
        try {
            $category = $_GET['category'] ?? null;
            $location = $_GET['location'] ?? null;
            $days = intval($_GET['days'] ?? 30);
            
            if (!$category) {
                $this->sendResponse(400, ['error' => 'Category parameter is required']);
                return;
            }
            
            // Get recent price data
            $priceData = $this->priceModel->getByCategory($category, 
                date('Y-m-d', strtotime("-{$days} days")), 
                date('Y-m-d'), 
                $location
            );
            
            // Detect anomalies using AI service
            $anomalies = $this->aiService->detectAnomalies($priceData);
            
            // If high-severity anomalies found, trigger alerts
            $highSeverityAnomalies = array_filter($anomalies['anomalies'], function($anomaly) {
                return $anomaly['severity'] === 'high';
            });
            
            if (!empty($highSeverityAnomalies)) {
                $this->triggerAnomalyAlerts($category, $location, $highSeverityAnomalies);
            }
            
            $this->sendResponse(200, [
                'anomalies' => $anomalies,
                'category' => $category,
                'location' => $location,
                'alert_triggered' => !empty($highSeverityAnomalies)
            ]);
            
        } catch (Exception $e) {
            error_log("Detect anomalies error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Failed to detect price anomalies']);
        }
    }
    
    /**
     * Get "where to sell" recommendations
     */
    public function getWhereToSellRecommendations() {
        try {
            $category = $_GET['category'] ?? null;
            $farmerLocation = $_GET['farmer_location'] ?? null;
            $quantity = floatval($_GET['quantity'] ?? 100);
            
            if (!$category || !$farmerLocation) {
                $this->sendResponse(400, ['error' => 'Category and farmer_location parameters are required']);
                return;
            }
            
            // Get current prices across all locations
            $allPrices = $this->priceModel->getCurrentMarketPrices();
            $categoryPrices = array_filter($allPrices, function($price) use ($category) {
                return $price['category'] === $category;
            });
            
            $recommendations = [];
            
            foreach ($categoryPrices as $price) {
                $marketLocation = $price['market_location'];
                
                // Calculate transport cost
                $transportCost = $this->transportService->calculateCost(
                    $farmerLocation, 
                    $marketLocation, 
                    $quantity
                );
                
                // Get price forecast for this location
                $forecast = $this->forecastingService->predictNextDayPrice(
                    $category, 
                    $marketLocation
                );
                
                $grossRevenue = $forecast['predicted_price'] * $quantity;
                $netProfit = $grossRevenue - $transportCost['total_cost'];
                $profitMargin = ($grossRevenue > 0) ? ($netProfit / $grossRevenue) * 100 : 0;
                
                $recommendations[] = [
                    'market_location' => $marketLocation,
                    'current_price' => $price['price_per_unit'],
                    'forecasted_price' => $forecast['predicted_price'],
                    'confidence' => $forecast['confidence'],
                    'transport_cost' => $transportCost['total_cost'],
                    'transport_distance' => $transportCost['distance_km'],
                    'transport_time' => $transportCost['estimated_hours'],
                    'gross_revenue' => round($grossRevenue, 2),
                    'net_profit' => round($netProfit, 2),
                    'profit_margin' => round($profitMargin, 2),
                    'recommendation_score' => round($netProfit * $forecast['confidence'], 2)
                ];
            }
            
            // Sort by recommendation score (net profit * confidence)
            usort($recommendations, function($a, $b) {
                return $b['recommendation_score'] <=> $a['recommendation_score'];
            });
            
            $this->sendResponse(200, [
                'recommendations' => array_slice($recommendations, 0, 5),
                'category' => $category,
                'farmer_location' => $farmerLocation,
                'quantity' => $quantity,
                'analysis_date' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            error_log("Get where to sell recommendations error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Failed to generate selling recommendations']);
        }
    }
    
    /**
     * Add new price data
     */
    public function addPrice() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        $user = $this->getCurrentUser();
        if (!$user) {
            $this->sendResponse(401, ['error' => 'Unauthorized']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $required = ['product_id', 'market_location', 'price_per_unit'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->sendResponse(400, ['error' => "Field '{$field}' is required"]);
                return;
            }
        }
        
        try {
            $data['source'] = 'user_report';
            $priceId = $this->priceModel->create($data);
            
            if ($priceId) {
                // Check for price anomalies after adding new data
                $product = $this->productModel->findById($data['product_id']);
                if ($product) {
                    $this->checkPriceAnomaliesAsync($product['category'], $data['market_location']);
                }
                
                $this->sendResponse(201, [
                    'message' => 'Price data added successfully',
                    'price_id' => $priceId
                ]);
            } else {
                $this->sendResponse(500, ['error' => 'Failed to add price data']);
            }
            
        } catch (Exception $e) {
            error_log("Add price error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    /**
     * Get regional price comparison
     */
    public function getRegionalComparison() {
        try {
            $category = $_GET['category'] ?? 'rice';
            
            $prices = $this->priceModel->getCurrentMarketPrices();
            $categoryPrices = array_filter($prices, function($price) use ($category) {
                return $price['category'] === $category;
            });
            
            // Group by location and calculate statistics
            $locationStats = [];
            foreach ($categoryPrices as $price) {
                $location = $price['market_location'];
                if (!isset($locationStats[$location])) {
                    $locationStats[$location] = [
                        'location' => $location,
                        'prices' => [],
                        'avg_price' => 0,
                        'min_price' => PHP_FLOAT_MAX,
                        'max_price' => 0,
                        'sample_count' => 0
                    ];
                }
                
                $locationStats[$location]['prices'][] = $price['price_per_unit'];
                $locationStats[$location]['min_price'] = min($locationStats[$location]['min_price'], $price['price_per_unit']);
                $locationStats[$location]['max_price'] = max($locationStats[$location]['max_price'], $price['price_per_unit']);
                $locationStats[$location]['sample_count']++;
            }
            
            // Calculate averages
            foreach ($locationStats as &$stats) {
                if (!empty($stats['prices'])) {
                    $stats['avg_price'] = round(array_sum($stats['prices']) / count($stats['prices']), 2);
                }
                unset($stats['prices']); // Remove detailed prices from response
            }
            
            $this->sendResponse(200, [
                'regional_comparison' => array_values($locationStats),
                'category' => $category,
                'comparison_date' => date('Y-m-d')
            ]);
            
        } catch (Exception $e) {
            error_log("Get regional comparison error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Failed to get regional price comparison']);
        }
    }
    
    /**
     * Trigger anomaly alerts to admins
     */
    private function triggerAnomalyAlerts($category, $location, $anomalies) {
        // This would integrate with notification service
        // For now, log the alert
        $alertData = [
            'type' => 'price_anomaly',
            'category' => $category,
            'location' => $location,
            'anomalies' => $anomalies,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        error_log("PRICE ANOMALY ALERT: " . json_encode($alertData));
        
        // TODO: Integrate with email/SMS notification service
        // TODO: Store alert in database for admin dashboard
    }
    
    /**
     * Check price anomalies asynchronously
     */
    private function checkPriceAnomaliesAsync($category, $location) {
        // In a real implementation, this would be queued for background processing
        // For now, perform synchronous check
        try {
            $priceData = $this->priceModel->getByCategory($category, 
                date('Y-m-d', strtotime('-30 days')), 
                date('Y-m-d'), 
                $location
            );
            
            $anomalies = $this->aiService->detectAnomalies($priceData);
            
            $highSeverityAnomalies = array_filter($anomalies['anomalies'], function($anomaly) {
                return $anomaly['severity'] === 'high';
            });
            
            if (!empty($highSeverityAnomalies)) {
                $this->triggerAnomalyAlerts($category, $location, $highSeverityAnomalies);
            }
            
        } catch (Exception $e) {
            error_log("Async anomaly check error: " . $e->getMessage());
        }
    }
    
    /**
     * Get current user from JWT token
     */
    private function getCurrentUser() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }
        
        $token = $matches[1];
        return $this->verifyJWT($token);
    }
    
    /**
     * Verify JWT token
     */
    private function verifyJWT($token) {
        // Basic JWT verification - in production use firebase/php-jwt library
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        
        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
        
        $payload = json_decode(base64_decode($payloadEncoded), true);
        
        if (!$payload || $payload['exp'] < time()) {
            return null;
        }
        
        return $payload;
    }
    
    /**
     * Send JSON response
     */
    private function sendResponse($statusCode, $data) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
