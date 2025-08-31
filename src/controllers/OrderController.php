<?php
/**
 * Order Controller
 * Handles order placement, management, and workflow
 */

require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/PaymentService.php';
require_once __DIR__ . '/../services/OrderAnomalyService.php';
require_once __DIR__ . '/../services/InvoiceService.php';
require_once __DIR__ . '/../services/ReminderService.php';

class OrderController {
    private $orderModel;
    private $productModel;
    private $userModel;
    private $paymentService;
    private $anomalyService;
    private $invoiceService;
    private $reminderService;
    
    public function __construct() {
        $this->orderModel = new Order();
        $this->productModel = new Product();
        $this->userModel = new User();
        $this->paymentService = new PaymentService();
        $this->anomalyService = new OrderAnomalyService();
        $this->invoiceService = new InvoiceService();
        $this->reminderService = new ReminderService();
    }
    
    /**
     * Buyer places an order
     */
    public function placeOrder() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        $user = $this->getCurrentUser();
        if (!$user || $user['role'] !== 'buyer') {
            $this->sendResponse(403, ['error' => 'Only buyers can place orders']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $required = ['product_id', 'quantity', 'delivery_address', 'payment_method'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->sendResponse(400, ['error' => "Field '{$field}' is required"]);
                return;
            }
        }
        
        try {
            // Get product details
            $product = $this->productModel->findById($data['product_id']);
            if (!$product) {
                $this->sendResponse(404, ['error' => 'Product not found']);
                return;
            }
            
            // Check product availability
            if ($product['status'] !== 'available') {
                $this->sendResponse(400, ['error' => 'Product is not available']);
                return;
            }
            
            if ($product['quantity'] < $data['quantity']) {
                $this->sendResponse(400, ['error' => 'Insufficient stock available']);
                return;
            }
            
            // Check for order anomalies
            $anomalyCheck = $this->anomalyService->checkOrderAnomalies(
                $user['id'], 
                $data['product_id'], 
                $data['quantity'], 
                $user['region'] ?? null
            );
            
            if ($anomalyCheck['is_anomalous']) {
                // Log anomaly but don't block order (could be configurable)
                error_log("Order anomaly detected: " . json_encode($anomalyCheck));
            }
            
            // Prepare order data
            $orderData = [
                'buyer_id' => $user['id'],
                'farmer_id' => $product['farmer_id'],
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity'],
                'unit_price' => $product['price_per_unit'],
                'delivery_address' => $data['delivery_address'],
                'delivery_date' => $data['delivery_date'] ?? null,
                'payment_method' => $data['payment_method'],
                'notes' => $data['notes'] ?? null
            ];
            
            // Create order
            $orderId = $this->orderModel->create($orderData);
            
            if ($orderId) {
                // Create order timeline entry
                $this->createTimelineEntry($orderId, 'created', 'Order placed by buyer');
                
                // Generate invoice
                $invoice = $this->invoiceService->generateInvoice($orderId);
                
                // Setup payment if not COD
                $paymentData = null;
                if ($data['payment_method'] !== 'cod') {
                    $paymentData = $this->paymentService->initiatePayment(
                        $orderId, 
                        $orderData['quantity'] * $orderData['unit_price'],
                        $data['payment_method']
                    );
                }
                
                // Notify farmer
                $this->notifyFarmer($orderId, $product['farmer_id']);
                
                // Schedule reminder if needed
                if ($data['payment_method'] !== 'cod' && !empty($paymentData['requires_payment'])) {
                    $this->reminderService->schedulePaymentReminder($orderId, $user['id']);
                }
                
                $order = $this->orderModel->findById($orderId);
                
                $response = [
                    'message' => 'Order placed successfully',
                    'order' => $order,
                    'invoice' => $invoice,
                    'anomaly_detected' => $anomalyCheck['is_anomalous']
                ];
                
                if ($paymentData) {
                    $response['payment'] = $paymentData;
                }
                
                $this->sendResponse(201, $response);
                
            } else {
                $this->sendResponse(500, ['error' => 'Failed to create order']);
            }
            
        } catch (Exception $e) {
            error_log("Place order error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    /**
     * Farmer accepts or rejects an order
     */
    public function respondToOrder($orderId) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        $user = $this->getCurrentUser();
        if (!$user || $user['role'] !== 'farmer') {
            $this->sendResponse(403, ['error' => 'Only farmers can respond to orders']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['action']) || !in_array($data['action'], ['accept', 'reject'])) {
            $this->sendResponse(400, ['error' => 'Valid action (accept/reject) is required']);
            return;
        }
        
        try {
            $order = $this->orderModel->findById($orderId);
            
            if (!$order) {
                $this->sendResponse(404, ['error' => 'Order not found']);
                return;
            }
            
            // Check if farmer owns the product
            if ($order['farmer_id'] != $user['id']) {
                $this->sendResponse(403, ['error' => 'You can only respond to your own orders']);
                return;
            }
            
            // Check order status
            if ($order['order_status'] !== 'pending') {
                $this->sendResponse(400, ['error' => 'Order has already been processed']);
                return;
            }
            
            if ($data['action'] === 'accept') {
                // Accept order
                $this->orderModel->updateStatus($orderId, 'confirmed', $data['notes'] ?? null);
                $this->createTimelineEntry($orderId, 'confirmed', 'Order accepted by farmer');
                
                // Update product quantity
                $product = $this->productModel->findById($order['product_id']);
                $newQuantity = $product['quantity'] - $order['quantity'];
                $this->productModel->updateQuantity($order['product_id'], $newQuantity);
                
                // If quantity reaches zero, mark as sold
                if ($newQuantity <= 0) {
                    $this->productModel->update($order['product_id'], ['status' => 'sold']);
                }
                
                // Notify buyer
                $this->notifyBuyer($orderId, $order['buyer_id'], 'accepted');
                
                $message = 'Order accepted successfully';
                
            } else {
                // Reject order
                $this->orderModel->updateStatus($orderId, 'cancelled', $data['notes'] ?? 'Rejected by farmer');
                $this->createTimelineEntry($orderId, 'cancelled', 'Order rejected by farmer');
                
                // Notify buyer
                $this->notifyBuyer($orderId, $order['buyer_id'], 'rejected');
                
                $message = 'Order rejected successfully';
            }
            
            $updatedOrder = $this->orderModel->findById($orderId);
            
            $this->sendResponse(200, [
                'message' => $message,
                'order' => $updatedOrder
            ]);
            
        } catch (Exception $e) {
            error_log("Respond to order error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    /**
     * Get orders for current user
     */
    public function getOrders() {
        $user = $this->getCurrentUser();
        if (!$user) {
            $this->sendResponse(401, ['error' => 'Unauthorized']);
            return;
        }
        
        try {
            $limit = intval($_GET['limit'] ?? 20);
            $offset = intval($_GET['offset'] ?? 0);
            $status = $_GET['status'] ?? null;
            
            if ($user['role'] === 'buyer') {
                $orders = $this->orderModel->getByBuyer($user['id'], $limit, $offset);
            } elseif ($user['role'] === 'farmer') {
                $orders = $this->orderModel->getByFarmer($user['id'], $limit, $offset);
            } else {
                // Admin can see all orders
                $orders = $this->orderModel->getRecentOrders($limit);
            }
            
            // Filter by status if provided
            if ($status) {
                $orders = array_filter($orders, function($order) use ($status) {
                    return $order['order_status'] === $status;
                });
            }
            
            // Add timeline and payment info for each order
            foreach ($orders as &$order) {
                $order['timeline'] = $this->getOrderTimeline($order['id']);
                $order['payment_info'] = $this->paymentService->getPaymentInfo($order['id']);
            }
            
            $this->sendResponse(200, [
                'orders' => array_values($orders),
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'count' => count($orders)
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Get orders error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    /**
     * Get order details with timeline
     */
    public function getOrderDetails($orderId) {
        $user = $this->getCurrentUser();
        if (!$user) {
            $this->sendResponse(401, ['error' => 'Unauthorized']);
            return;
        }
        
        try {
            $order = $this->orderModel->findById($orderId);
            
            if (!$order) {
                $this->sendResponse(404, ['error' => 'Order not found']);
                return;
            }
            
            // Check permissions
            if ($user['role'] !== 'admin' && 
                $order['buyer_id'] != $user['id'] && 
                $order['farmer_id'] != $user['id']) {
                $this->sendResponse(403, ['error' => 'Access denied']);
                return;
            }
            
            // Get additional details
            $order['timeline'] = $this->getOrderTimeline($orderId);
            $order['payment_info'] = $this->paymentService->getPaymentInfo($orderId);
            $order['invoice'] = $this->invoiceService->getInvoice($orderId);
            
            $this->sendResponse(200, ['order' => $order]);
            
        } catch (Exception $e) {
            error_log("Get order details error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    /**
     * Update order status (for shipping, delivery confirmation)
     */
    public function updateOrderStatus($orderId) {
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
        
        if (empty($data['status'])) {
            $this->sendResponse(400, ['error' => 'Status is required']);
            return;
        }
        
        try {
            $order = $this->orderModel->findById($orderId);
            
            if (!$order) {
                $this->sendResponse(404, ['error' => 'Order not found']);
                return;
            }
            
            // Check permissions based on status change
            if (!$this->canUpdateStatus($user, $order, $data['status'])) {
                $this->sendResponse(403, ['error' => 'Not authorized to update this status']);
                return;
            }
            
            // Update status
            $this->orderModel->updateStatus($orderId, $data['status'], $data['notes'] ?? null);
            $this->createTimelineEntry($orderId, $data['status'], $data['notes'] ?? 'Status updated');
            
            // Handle status-specific actions
            $this->handleStatusChange($orderId, $data['status'], $order);
            
            $updatedOrder = $this->orderModel->findById($orderId);
            
            $this->sendResponse(200, [
                'message' => 'Order status updated successfully',
                'order' => $updatedOrder
            ]);
            
        } catch (Exception $e) {
            error_log("Update order status error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    /**
     * Process payment for an order
     */
    public function processPayment($orderId) {
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
            $order = $this->orderModel->findById($orderId);
            
            if (!$order) {
                $this->sendResponse(404, ['error' => 'Order not found']);
                return;
            }
            
            // Check if user is the buyer
            if ($order['buyer_id'] != $user['id']) {
                $this->sendResponse(403, ['error' => 'Only the buyer can process payment']);
                return;
            }
            
            // Process payment
            $paymentResult = $this->paymentService->processPayment($orderId, $data);
            
            if ($paymentResult['success']) {
                // Update payment status
                $this->orderModel->updatePaymentStatus($orderId, 'completed');
                $this->createTimelineEntry($orderId, 'payment_completed', 'Payment processed successfully');
                
                // Cancel reminder
                $this->reminderService->cancelReminder($orderId, 'payment');
                
                $this->sendResponse(200, [
                    'message' => 'Payment processed successfully',
                    'payment' => $paymentResult
                ]);
            } else {
                $this->sendResponse(400, [
                    'error' => 'Payment failed',
                    'details' => $paymentResult['error']
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Process payment error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    /**
     * Cancel an order
     */
    public function cancelOrder($orderId) {
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
            $order = $this->orderModel->findById($orderId);
            
            if (!$order) {
                $this->sendResponse(404, ['error' => 'Order not found']);
                return;
            }
            
            // Check permissions
            if ($order['buyer_id'] != $user['id'] && $order['farmer_id'] != $user['id'] && $user['role'] !== 'admin') {
                $this->sendResponse(403, ['error' => 'Not authorized to cancel this order']);
                return;
            }
            
            // Check if order can be cancelled
            if (!in_array($order['order_status'], ['pending', 'confirmed'])) {
                $this->sendResponse(400, ['error' => 'Order cannot be cancelled at this stage']);
                return;
            }
            
            // Cancel order
            $reason = $data['reason'] ?? 'Cancelled by user';
            $this->orderModel->cancel($orderId, $reason);
            $this->createTimelineEntry($orderId, 'cancelled', $reason);
            
            // Handle refund if payment was made
            if ($order['payment_status'] === 'completed') {
                $refundResult = $this->paymentService->processRefund($orderId);
                if ($refundResult['success']) {
                    $this->orderModel->updatePaymentStatus($orderId, 'refunded');
                    $this->createTimelineEntry($orderId, 'refunded', 'Payment refunded');
                }
            }
            
            // Restore product quantity if order was confirmed
            if ($order['order_status'] === 'confirmed') {
                $product = $this->productModel->findById($order['product_id']);
                $newQuantity = $product['quantity'] + $order['quantity'];
                $this->productModel->updateQuantity($order['product_id'], $newQuantity);
                
                // Update product status if needed
                if ($product['status'] === 'sold') {
                    $this->productModel->update($order['product_id'], ['status' => 'available']);
                }
            }
            
            $this->sendResponse(200, [
                'message' => 'Order cancelled successfully',
                'order_id' => $orderId
            ]);
            
        } catch (Exception $e) {
            error_log("Cancel order error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    /**
     * Create timeline entry
     */
    private function createTimelineEntry($orderId, $status, $notes = null) {
        try {
            $sql = "INSERT INTO order_timeline (order_id, status, notes, created_at) 
                    VALUES (:order_id, :status, :notes, NOW())";
            
            $stmt = Database::getInstance()->getConnection()->prepare($sql);
            $stmt->execute([
                ':order_id' => $orderId,
                ':status' => $status,
                ':notes' => $notes
            ]);
        } catch (Exception $e) {
            error_log("Create timeline entry error: " . $e->getMessage());
        }
    }
    
    /**
     * Get order timeline
     */
    private function getOrderTimeline($orderId) {
        try {
            $sql = "SELECT * FROM order_timeline 
                    WHERE order_id = :order_id 
                    ORDER BY created_at ASC";
            
            $stmt = Database::getInstance()->getConnection()->prepare($sql);
            $stmt->execute([':order_id' => $orderId]);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get order timeline error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if user can update order status
     */
    private function canUpdateStatus($user, $order, $newStatus) {
        switch ($user['role']) {
            case 'admin':
                return true;
                
            case 'farmer':
                // Farmers can update shipping status
                return in_array($newStatus, ['processing', 'shipped']) && 
                       $order['farmer_id'] == $user['id'];
                
            case 'buyer':
                // Buyers can confirm delivery
                return $newStatus === 'delivered' && $order['buyer_id'] == $user['id'];
                
            default:
                return false;
        }
    }
    
    /**
     * Handle status-specific actions
     */
    private function handleStatusChange($orderId, $status, $order) {
        switch ($status) {
            case 'shipped':
                // Notify buyer about shipment
                $this->notifyBuyer($orderId, $order['buyer_id'], 'shipped');
                break;
                
            case 'delivered':
                // Mark COD payment as completed if applicable
                if ($order['payment_method'] === 'cod' && $order['payment_status'] === 'pending') {
                    $this->orderModel->updatePaymentStatus($orderId, 'completed');
                    $this->createTimelineEntry($orderId, 'payment_completed', 'COD payment received');
                }
                break;
        }
    }
    
    /**
     * Notify farmer about new order
     */
    private function notifyFarmer($orderId, $farmerId) {
        // Implementation would integrate with notification service
        // For now, just log
        error_log("NOTIFY FARMER: New order #{$orderId} for farmer #{$farmerId}");
    }
    
    /**
     * Notify buyer about order status
     */
    private function notifyBuyer($orderId, $buyerId, $action) {
        // Implementation would integrate with notification service
        // For now, just log
        error_log("NOTIFY BUYER: Order #{$orderId} {$action} for buyer #{$buyerId}");
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
