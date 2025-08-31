<?php
/**
 * Transport Controller
 * Handles transport requests, real-time tracking, and delivery management
 */

require_once __DIR__ . '/../models/Transport.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../services/ETAPredictionService.php';
require_once __DIR__ . '/../services/RouteAnomalyService.php';
require_once __DIR__ . '/../services/PerishabilityRiskService.php';
require_once __DIR__ . '/../services/DeliveryTrackingService.php';

class TransportController {
    private $transportModel;
    private $orderModel;
    private $productModel;
    private $etaService;
    private $routeAnomalyService;
    private $perishabilityService;
    private $trackingService;
    
    public function __construct() {
        $this->transportModel = new Transport();
        $this->orderModel = new Order();
        $this->productModel = new Product();
        $this->etaService = new ETAPredictionService();
        $this->routeAnomalyService = new RouteAnomalyService();
        $this->perishabilityService = new PerishabilityRiskService();
        $this->trackingService = new DeliveryTrackingService();
    }
    
    /**
     * Farmer requests transport for an order
     */
    public function requestTransport() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        $user = $this->getCurrentUser();
        if (!$user || $user['role'] !== 'farmer') {
            $this->sendResponse(403, ['error' => 'Only farmers can request transport']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $required = ['order_id', 'pickup_address', 'preferred_date', 'transport_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->sendResponse(400, ['error' => "Field '{$field}' is required"]);
                return;
            }
        }
        
        try {
            // Get and validate order
            $order = $this->orderModel->findById($data['order_id']);
            if (!$order) {
                $this->sendResponse(404, ['error' => 'Order not found']);
                return;
            }
            
            // Check if farmer owns the order
            if ($order['farmer_id'] != $user['id']) {
                $this->sendResponse(403, ['error' => 'You can only request transport for your own orders']);
                return;
            }
            
            // Check order status
            if (!in_array($order['order_status'], ['confirmed', 'processing'])) {
                $this->sendResponse(400, ['error' => 'Order must be confirmed or processing to request transport']);
                return;
            }
            
            // Get product details for perishability assessment
            $product = $this->productModel->findById($order['product_id']);
            
            // Assess delivery risk based on product perishability
            $riskAssessment = $this->perishabilityService->assessDeliveryRisk(
                $product['category'],
                $data['pickup_address'],
                $order['delivery_address'],
                $data['preferred_date']
            );
            
            // Prepare transport request data
            $transportData = [
                'order_id' => $data['order_id'],
                'transport_type' => $data['transport_type'],
                'pickup_address' => $data['pickup_address'],
                'delivery_address' => $order['delivery_address'],
                'pickup_date' => $data['preferred_date'],
                'delivery_date' => $data['delivery_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'requested'
            ];
            
            // Predict ETA and optimize route
            $etaPrediction = $this->etaService->predictETA(
                $data['pickup_address'],
                $order['delivery_address'],
                $data['transport_type'],
                $data['preferred_date']
            );
            
            // Find and assign best transport provider
            $assignmentResult = $this->assignTransportProvider($transportData, $etaPrediction, $riskAssessment);
            
            if ($assignmentResult['success']) {
                $transportId = $this->transportModel->create(array_merge(
                    $transportData,
                    $assignmentResult['provider_data']
                ));
                
                if ($transportId) {
                    // Initialize tracking
                    $this->trackingService->initializeTracking($transportId, $etaPrediction);
                    
                    // Update order status
                    $this->orderModel->updateStatus($data['order_id'], 'processing', 'Transport requested');
                    
                    // Schedule perishability monitoring if high risk
                    if ($riskAssessment['risk_level'] === 'high') {
                        $this->perishabilityService->scheduleMonitoring($transportId, $riskAssessment);
                    }
                    
                    $transport = $this->transportModel->findById($transportId);
                    
                    $this->sendResponse(201, [
                        'message' => 'Transport requested successfully',
                        'transport' => $transport,
                        'eta_prediction' => $etaPrediction,
                        'risk_assessment' => $riskAssessment,
                        'provider_assignment' => $assignmentResult
                    ]);
                } else {
                    $this->sendResponse(500, ['error' => 'Failed to create transport request']);
                }
            } else {
                $this->sendResponse(400, [
                    'error' => 'Transport assignment failed',
                    'reason' => $assignmentResult['error']
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Request transport error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    /**
     * Get real-time transport tracking information
     */
    public function getTrackingInfo($transportId) {
        $user = $this->getCurrentUser();
        if (!$user) {
            $this->sendResponse(401, ['error' => 'Unauthorized']);
            return;
        }
        
        try {
            $transport = $this->transportModel->findById($transportId);
            if (!$transport) {
                $this->sendResponse(404, ['error' => 'Transport not found']);
                return;
            }
            
            // Check permissions
            $order = $this->orderModel->findById($transport['order_id']);
            if ($user['role'] !== 'admin' && 
                $order['buyer_id'] != $user['id'] && 
                $order['farmer_id'] != $user['id']) {
                $this->sendResponse(403, ['error' => 'Access denied']);
                return;
            }
            
            // Get real-time tracking data
            $trackingData = $this->trackingService->getTrackingData($transportId);
            
            // Check for route anomalies
            $anomalyCheck = $this->routeAnomalyService->checkRouteAnomalies(
                $transportId,
                $trackingData['current_location'] ?? null
            );
            
            // Update ETA if needed
            if (!empty($trackingData['current_location'])) {
                $updatedETA = $this->etaService->updateETA(
                    $transportId,
                    $trackingData['current_location'],
                    $trackingData['current_speed'] ?? 0
                );
            } else {
                $updatedETA = null;
            }
            
            $response = [
                'transport' => $transport,
                'tracking' => $trackingData,
                'eta_prediction' => $updatedETA,
                'route_anomalies' => $anomalyCheck,
                'last_updated' => date('Y-m-d H:i:s')
            ];
            
            $this->sendResponse(200, $response);
            
        } catch (Exception $e) {
            error_log("Get tracking info error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    /**
     * Update transport status and location (for transport providers)
     */
    public function updateTransportStatus($transportId) {
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
        
        try {
            $transport = $this->transportModel->findById($transportId);
            if (!$transport) {
                $this->sendResponse(404, ['error' => 'Transport not found']);
                return;
            }
            
            // Update transport status
            if (!empty($data['status'])) {
                $this->transportModel->updateStatus($transportId, $data['status'], $data['notes'] ?? null);
                
                // Handle status-specific actions
                $this->handleStatusChange($transportId, $data['status'], $transport);
            }
            
            // Update location if provided
            if (!empty($data['location'])) {
                $this->trackingService->updateLocation($transportId, $data['location']);
                
                // Check for route anomalies
                $anomalyCheck = $this->routeAnomalyService->checkRouteAnomalies($transportId, $data['location']);
                
                if ($anomalyCheck['is_anomalous']) {
                    $this->handleRouteAnomaly($transportId, $anomalyCheck);
                }
            }
            
            // Update delivery info
            $updateData = [];
            if (isset($data['driver_info'])) {
                $updateData['driver_info'] = $data['driver_info'];
            }
            if (isset($data['vehicle_info'])) {
                $updateData['vehicle_info'] = $data['vehicle_info'];
            }
            if (isset($data['estimated_delivery'])) {
                $updateData['delivery_date'] = $data['estimated_delivery'];
            }
            
            if (!empty($updateData)) {
                $this->transportModel->updateTrackingInfo($transportId, $updateData);
            }
            
            $updatedTransport = $this->transportModel->findById($transportId);
            
            $this->sendResponse(200, [
                'message' => 'Transport updated successfully',
                'transport' => $updatedTransport
            ]);
            
        } catch (Exception $e) {
            error_log("Update transport status error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    /**
     * Confirm delivery
     */
    public function confirmDelivery($transportId) {
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
        
        try {
            $transport = $this->transportModel->findById($transportId);
            if (!$transport) {
                $this->sendResponse(404, ['error' => 'Transport not found']);
                return;
            }
            
            $order = $this->orderModel->findById($transport['order_id']);
            
            // Check permissions - buyer or admin can confirm delivery
            if ($user['role'] !== 'admin' && $order['buyer_id'] != $user['id']) {
                $this->sendResponse(403, ['error' => 'Only the buyer or admin can confirm delivery']);
                return;
            }
            
            // Update transport status
            $this->transportModel->updateStatus($transportId, 'delivered', $data['notes'] ?? 'Delivery confirmed by buyer');
            
            // Update order status
            $this->orderModel->updateStatus($transport['order_id'], 'delivered', 'Delivery confirmed');
            
            // Complete tracking
            $this->trackingService->completeDelivery($transportId, $data);
            
            // Generate delivery analytics
            $analytics = $this->generateDeliveryAnalytics($transportId, $transport);
            
            $this->sendResponse(200, [
                'message' => 'Delivery confirmed successfully',
                'transport_id' => $transportId,
                'order_id' => $transport['order_id'],
                'analytics' => $analytics
            ]);
            
        } catch (Exception $e) {
            error_log("Confirm delivery error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    /**
     * Get transport analytics and reporting
     */
    public function getAnalytics() {
        $user = $this->getCurrentUser();
        if (!$user || !in_array($user['role'], ['farmer', 'admin'])) {
            $this->sendResponse(403, ['error' => 'Access denied']);
            return;
        }
        
        try {
            $filters = [];
            if ($user['role'] === 'farmer') {
                $filters['farmer_id'] = $user['id'];
            }
            
            // Get transport statistics
            $stats = $this->transportModel->getTransportStats(
                $_GET['start_date'] ?? null,
                $_GET['end_date'] ?? null,
                $filters
            );
            
            // Get delivery performance metrics
            $performance = $this->trackingService->getPerformanceMetrics($filters);
            
            // Get route efficiency analytics
            $routeAnalytics = $this->routeAnomalyService->getRouteAnalytics($filters);
            
            // Get perishability risk analytics
            $riskAnalytics = $this->perishabilityService->getRiskAnalytics($filters);
            
            $this->sendResponse(200, [
                'transport_stats' => $stats,
                'delivery_performance' => $performance,
                'route_analytics' => $routeAnalytics,
                'risk_analytics' => $riskAnalytics,
                'generated_at' => date('Y-m-d H:i:s')
            ]);
            
        } catch (Exception $e) {
            error_log("Get transport analytics error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    /**
     * Get available transport providers
     */
    public function getTransportProviders() {
        try {
            $location = $_GET['location'] ?? null;
            $transportType = $_GET['transport_type'] ?? null;
            
            $providers = $this->getAvailableProviders($location, $transportType);
            
            $this->sendResponse(200, [
                'providers' => $providers,
                'location' => $location,
                'transport_type' => $transportType
            ]);
            
        } catch (Exception $e) {
            error_log("Get transport providers error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    /**
     * Get transport requests for current user
     */
    public function getTransportRequests() {
        $user = $this->getCurrentUser();
        if (!$user) {
            $this->sendResponse(401, ['error' => 'Unauthorized']);
            return;
        }
        
        try {
            $limit = intval($_GET['limit'] ?? 20);
            $offset = intval($_GET['offset'] ?? 0);
            $status = $_GET['status'] ?? null;
            
            $filters = [];
            if ($user['role'] === 'farmer') {
                $filters['farmer_id'] = $user['id'];
            } elseif ($user['role'] === 'buyer') {
                $filters['buyer_id'] = $user['id'];
            }
            
            $transports = $this->transportModel->getTransports($filters, $limit, $offset);
            
            // Filter by status if provided
            if ($status) {
                $transports = array_filter($transports, function($transport) use ($status) {
                    return $transport['status'] === $status;
                });
            }
            
            // Add tracking data for each transport
            foreach ($transports as &$transport) {
                $transport['tracking'] = $this->trackingService->getTrackingData($transport['id']);
                $transport['eta'] = $this->etaService->getCurrentETA($transport['id']);
            }
            
            $this->sendResponse(200, [
                'transports' => array_values($transports),
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'count' => count($transports)
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Get transport requests error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    // Private helper methods
    
    private function assignTransportProvider($transportData, $etaPrediction, $riskAssessment) {
        try {
            // Get available providers for the route
            $providers = $this->getAvailableProviders(
                $transportData['pickup_address'],
                $transportData['transport_type']
            );
            
            if (empty($providers)) {
                return [
                    'success' => false,
                    'error' => 'No available transport providers for this route'
                ];
            }
            
            // Score providers based on multiple factors
            $scoredProviders = [];
            foreach ($providers as $provider) {
                $score = $this->calculateProviderScore($provider, $etaPrediction, $riskAssessment);
                $scoredProviders[] = array_merge($provider, ['score' => $score]);
            }
            
            // Sort by score (highest first)
            usort($scoredProviders, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });
            
            $bestProvider = $scoredProviders[0];
            
            return [
                'success' => true,
                'provider_data' => [
                    'provider_name' => $bestProvider['name'],
                    'provider_contact' => $bestProvider['phone'],
                    'cost' => $this->calculateTransportCost($transportData, $bestProvider),
                    'tracking_number' => $this->generateTrackingNumber()
                ],
                'provider_info' => $bestProvider,
                'alternatives' => array_slice($scoredProviders, 1, 2) // Top 2 alternatives
            ];
            
        } catch (Exception $e) {
            error_log("Provider assignment error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Provider assignment failed'
            ];
        }
    }
    
    private function getAvailableProviders($location = null, $transportType = null) {
        $sql = "SELECT * FROM transport_providers WHERE status = 'active'";
        $params = [];
        
        if ($location) {
            $sql .= " AND (service_areas @> ?::jsonb OR service_areas = '[]')";
            $params[] = json_encode([$location]);
        }
        
        if ($transportType) {
            $sql .= " AND (vehicle_types @> ?::jsonb OR vehicle_types = '[]')";
            $params[] = json_encode([$transportType]);
        }
        
        $sql .= " ORDER BY rating DESC, successful_deliveries DESC";
        
        $stmt = Database::getInstance()->getConnection()->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    private function calculateProviderScore($provider, $etaPrediction, $riskAssessment) {
        $score = 0;
        
        // Rating factor (40%)
        $score += ($provider['rating'] / 5.0) * 0.4;
        
        // Success rate factor (30%)
        $successRate = $provider['total_deliveries'] > 0 ? 
            $provider['successful_deliveries'] / $provider['total_deliveries'] : 0;
        $score += $successRate * 0.3;
        
        // Experience factor (20%)
        $experienceScore = min(1.0, $provider['total_deliveries'] / 100);
        $score += $experienceScore * 0.2;
        
        // Risk compatibility factor (10%)
        if ($riskAssessment['risk_level'] === 'high' && $provider['rating'] >= 4.0) {
            $score += 0.1;
        }
        
        return $score;
    }
    
    private function calculateTransportCost($transportData, $provider) {
        // Base cost calculation - this would integrate with TransportCostService
        $baseCost = 500; // Base fee
        
        // Distance-based cost (simplified)
        $estimatedDistance = 50; // This would be calculated based on actual addresses
        $distanceCost = $estimatedDistance * 3; // 3 BDT per km
        
        // Provider-specific multiplier
        $providerMultiplier = $provider['rating'] >= 4.5 ? 1.1 : 1.0;
        
        return ($baseCost + $distanceCost) * $providerMultiplier;
    }
    
    private function generateTrackingNumber() {
        return 'KG' . date('Ymd') . rand(1000, 9999);
    }
    
    private function handleStatusChange($transportId, $status, $transport) {
        switch ($status) {
            case 'pickup_pending':
                $this->notifyFarmer($transport['order_id'], 'Transport scheduled for pickup');
                break;
                
            case 'in_transit':
                $this->notifyBuyer($transport['order_id'], 'Order is in transit');
                $this->orderModel->updateStatus($transport['order_id'], 'shipped');
                break;
                
            case 'delivered':
                $this->notifyBuyer($transport['order_id'], 'Order delivered');
                break;
                
            case 'delayed':
                $this->handleDelayedDelivery($transportId, $transport);
                break;
        }
    }
    
    private function handleRouteAnomaly($transportId, $anomalyCheck) {
        // Log the anomaly
        error_log("Route anomaly detected for transport #{$transportId}: " . json_encode($anomalyCheck));
        
        // Notify relevant parties
        $transport = $this->transportModel->findById($transportId);
        $order = $this->orderModel->findById($transport['order_id']);
        
        if ($anomalyCheck['severity'] === 'high') {
            $this->notifyFarmer($transport['order_id'], 'Route anomaly detected - possible delay');
            $this->notifyBuyer($transport['order_id'], 'Your delivery may be delayed due to route issues');
        }
        
        // Update ETA if needed
        $this->etaService->recalculateETA($transportId, $anomalyCheck);
    }
    
    private function handleDelayedDelivery($transportId, $transport) {
        $order = $this->orderModel->findById($transport['order_id']);
        $product = $this->productModel->findById($order['product_id']);
        
        // Check perishability risk
        $delayRisk = $this->perishabilityService->assessDelayRisk(
            $product['category'],
            $transport['pickup_date'],
            new DateTime()
        );
        
        if ($delayRisk['risk_level'] === 'critical') {
            $this->notifyFarmer($transport['order_id'], 'URGENT: Perishable product delivery delayed');
            $this->notifyBuyer($transport['order_id'], 'Your perishable order is delayed - quality may be affected');
        }
    }
    
    private function generateDeliveryAnalytics($transportId, $transport) {
        $trackingData = $this->trackingService->getTrackingData($transportId);
        $etaData = $this->etaService->getCurrentETA($transportId);
        
        return [
            'delivery_time' => date('Y-m-d H:i:s'),
            'total_duration' => $this->calculateDuration($transport['pickup_date'], date('Y-m-d H:i:s')),
            'eta_accuracy' => $etaData ? $this->calculateETAAccuracy($etaData, date('Y-m-d H:i:s')) : null,
            'route_efficiency' => $trackingData['route_efficiency'] ?? null,
            'distance_traveled' => $trackingData['total_distance'] ?? null
        ];
    }
    
    private function calculateDuration($startTime, $endTime) {
        $start = new DateTime($startTime);
        $end = new DateTime($endTime);
        return $end->diff($start)->format('%d days %h hours %i minutes');
    }
    
    private function calculateETAAccuracy($etaData, $actualDeliveryTime) {
        $eta = new DateTime($etaData['estimated_delivery']);
        $actual = new DateTime($actualDeliveryTime);
        $diff = abs($eta->getTimestamp() - $actual->getTimestamp()) / 3600; // Hours
        
        // Accuracy percentage (100% if within 1 hour, decreasing linearly)
        return max(0, 100 - ($diff * 10));
    }
    
    private function notifyFarmer($orderId, $message) {
        error_log("NOTIFY FARMER: Order #{$orderId} - {$message}");
    }
    
    private function notifyBuyer($orderId, $message) {
        error_log("NOTIFY BUYER: Order #{$orderId} - {$message}");
    }
    
    // Authentication helpers
    
    private function getCurrentUser() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }
        
        $token = $matches[1];
        return $this->verifyJWT($token);
    }
    
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
    
    private function sendResponse($statusCode, $data) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
