<?php
/**
 * Payment Service
 * Handles payment processing with bKash/Nagad/Card mock and COD support
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/PaymentRiskService.php';

class PaymentService {
    private $db;
    private $riskService;
    
    // Payment gateway configurations
    private $gateways = [
        'bkash' => [
            'name' => 'bKash',
            'api_endpoint' => 'https://checkout.sandbox.bka.sh/v1.2.0-beta',
            'merchant_id' => 'sandbox_merchant_bkash',
            'app_key' => 'sandbox_app_key_123',
            'app_secret' => 'sandbox_app_secret_456',
            'username' => 'sandboxTokenizedUser02',
            'password' => 'sandboxTokenizedUser02@12345',
            'enabled' => true
        ],
        'nagad' => [
            'name' => 'Nagad',
            'api_endpoint' => 'https://api.mynagad.com/api/dfs',
            'merchant_id' => 'sandbox_merchant_nagad',
            'public_key' => 'sandbox_public_key_nagad',
            'private_key' => 'sandbox_private_key_nagad',
            'enabled' => true
        ],
        'card' => [
            'name' => 'Credit/Debit Card',
            'gateway' => 'sslcommerz', // or 'stripe', 'paypal'
            'store_id' => 'testbox',
            'store_password' => 'qwerty',
            'enabled' => true
        ],
        'rocket' => [
            'name' => 'Rocket',
            'api_endpoint' => 'https://sandbox-rocket.com/api',
            'merchant_number' => '01700000000',
            'pin' => '12345',
            'enabled' => true
        ],
        'bank_transfer' => [
            'name' => 'Bank Transfer',
            'enabled' => true
        ],
        'cod' => [
            'name' => 'Cash on Delivery',
            'enabled' => true
        ]
    ];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->riskService = new PaymentRiskService();
    }
    
    /**
     * Initiate payment process
     */
    public function initiatePayment($orderId, $amount, $paymentMethod, $additionalData = []) {
        try {
            // Validate payment method
            if (!isset($this->gateways[$paymentMethod])) {
                throw new Exception("Unsupported payment method: {$paymentMethod}");
            }
            
            if (!$this->gateways[$paymentMethod]['enabled']) {
                throw new Exception("Payment method {$paymentMethod} is currently disabled");
            }
            
            // Get order details
            $order = $this->getOrderDetails($orderId);
            if (!$order) {
                throw new Exception("Order not found");
            }
            
            // Perform risk assessment
            $riskAssessment = $this->riskService->assessPaymentRisk($orderId, $paymentMethod, $order);
            
            // Create payment record
            $paymentId = $this->createPaymentRecord($orderId, $amount, $paymentMethod, $riskAssessment);
            
            $result = [
                'payment_id' => $paymentId,
                'order_id' => $orderId,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'risk_score' => $riskAssessment['risk_score'],
                'requires_payment' => $paymentMethod !== 'cod'
            ];
            
            // Handle different payment methods
            switch ($paymentMethod) {
                case 'bkash':
                    $result = array_merge($result, $this->initiateBkashPayment($paymentId, $amount, $order));
                    break;
                    
                case 'nagad':
                    $result = array_merge($result, $this->initiateNagadPayment($paymentId, $amount, $order));
                    break;
                    
                case 'card':
                    $result = array_merge($result, $this->initiateCardPayment($paymentId, $amount, $order));
                    break;
                    
                case 'rocket':
                    $result = array_merge($result, $this->initiateRocketPayment($paymentId, $amount, $order));
                    break;
                    
                case 'bank_transfer':
                    $result = array_merge($result, $this->initiateBankTransfer($paymentId, $amount, $order));
                    break;
                    
                case 'cod':
                    $result = array_merge($result, $this->initiateCOD($paymentId, $amount, $order, $riskAssessment));
                    break;
                    
                default:
                    throw new Exception("Payment method not implemented: {$paymentMethod}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Payment initiation error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process payment (for webhook/callback handling)
     */
    public function processPayment($orderId, $paymentData) {
        try {
            $payment = $this->getPaymentByOrderId($orderId);
            if (!$payment) {
                throw new Exception("Payment record not found for order");
            }
            
            $result = [
                'success' => false,
                'payment_id' => $payment['id'],
                'order_id' => $orderId
            ];
            
            switch ($payment['payment_method']) {
                case 'bkash':
                    $result = array_merge($result, $this->processBkashPayment($payment, $paymentData));
                    break;
                    
                case 'nagad':
                    $result = array_merge($result, $this->processNagadPayment($payment, $paymentData));
                    break;
                    
                case 'card':
                    $result = array_merge($result, $this->processCardPayment($payment, $paymentData));
                    break;
                    
                case 'rocket':
                    $result = array_merge($result, $this->processRocketPayment($payment, $paymentData));
                    break;
                    
                case 'bank_transfer':
                    $result = array_merge($result, $this->processBankTransfer($payment, $paymentData));
                    break;
                    
                case 'cod':
                    $result = array_merge($result, $this->processCODPayment($payment, $paymentData));
                    break;
                    
                default:
                    throw new Exception("Unsupported payment method");
            }
            
            // Update payment status
            if ($result['success']) {
                $this->updatePaymentStatus($payment['id'], 'completed', $result);
                
                // Update risk assessment
                $this->riskService->recordPaymentSuccess($orderId, $payment['payment_method']);
            } else {
                $this->updatePaymentStatus($payment['id'], 'failed', $result);
                
                // Update risk assessment
                $this->riskService->recordPaymentFailure($orderId, $payment['payment_method'], $result['error'] ?? '');
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Payment processing error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Initiate bKash payment (mock implementation)
     */
    private function initiateBkashPayment($paymentId, $amount, $order) {
        // Mock bKash payment initiation
        $transactionId = 'TXN' . time() . rand(1000, 9999);
        
        // In real implementation, this would call bKash API
        $mockResponse = [
            'payment_url' => "https://checkout.sandbox.bka.sh/{$transactionId}",
            'transaction_id' => $transactionId,
            'payment_reference' => 'BK' . $paymentId,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
            'instructions' => 'Complete payment using your bKash account'
        ];
        
        // Store transaction details
        $this->updatePaymentDetails($paymentId, $mockResponse);
        
        return $mockResponse;
    }
    
    /**
     * Initiate Nagad payment (mock implementation)
     */
    private function initiateNagadPayment($paymentId, $amount, $order) {
        // Mock Nagad payment initiation
        $transactionId = 'NGD' . time() . rand(1000, 9999);
        
        $mockResponse = [
            'payment_url' => "https://api.mynagad.com/payment/{$transactionId}",
            'transaction_id' => $transactionId,
            'payment_reference' => 'NG' . $paymentId,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
            'instructions' => 'Complete payment using your Nagad account'
        ];
        
        $this->updatePaymentDetails($paymentId, $mockResponse);
        
        return $mockResponse;
    }
    
    /**
     * Initiate card payment (mock implementation)
     */
    private function initiateCardPayment($paymentId, $amount, $order) {
        // Mock card payment initiation
        $sessionId = 'CARD' . time() . rand(1000, 9999);
        
        $mockResponse = [
            'payment_url' => "https://securepay.sslcommerz.com/gwprocess/v4/api.php?Q=pay&SESSIONKEY={$sessionId}",
            'session_id' => $sessionId,
            'payment_reference' => 'CD' . $paymentId,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'instructions' => 'Complete payment using your credit/debit card'
        ];
        
        $this->updatePaymentDetails($paymentId, $mockResponse);
        
        return $mockResponse;
    }
    
    /**
     * Initiate Rocket payment (mock implementation)
     */
    private function initiateRocketPayment($paymentId, $amount, $order) {
        $transactionId = 'RKT' . time() . rand(1000, 9999);
        
        $mockResponse = [
            'payment_url' => "https://rocket.com.bd/pay/{$transactionId}",
            'transaction_id' => $transactionId,
            'payment_reference' => 'RK' . $paymentId,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
            'instructions' => 'Complete payment using your Rocket account'
        ];
        
        $this->updatePaymentDetails($paymentId, $mockResponse);
        
        return $mockResponse;
    }
    
    /**
     * Initiate bank transfer
     */
    private function initiateBankTransfer($paymentId, $amount, $order) {
        $reference = 'BT' . $paymentId . time();
        
        $response = [
            'payment_reference' => $reference,
            'bank_details' => [
                'bank_name' => 'Dutch-Bangla Bank Limited',
                'account_name' => 'KrishiGhor Ltd',
                'account_number' => '1234567890',
                'routing_number' => '090271234',
                'branch' => 'Dhaka Main Branch'
            ],
            'instructions' => 'Transfer the exact amount to the provided bank account and use the payment reference in the description'
        ];
        
        $this->updatePaymentDetails($paymentId, $response);
        
        return $response;
    }
    
    /**
     * Initiate Cash on Delivery (COD)
     */
    private function initiateCOD($paymentId, $amount, $order, $riskAssessment) {
        // COD risk check
        if ($riskAssessment['risk_score'] > 0.7) {
            return [
                'success' => false,
                'error' => 'COD not available due to high risk assessment',
                'risk_score' => $riskAssessment['risk_score'],
                'risk_factors' => $riskAssessment['risk_factors']
            ];
        }
        
        $response = [
            'payment_reference' => 'COD' . $paymentId,
            'amount_to_collect' => $amount,
            'instructions' => 'Payment will be collected upon delivery',
            'delivery_instructions' => 'Please keep exact cash amount ready for delivery person'
        ];
        
        $this->updatePaymentDetails($paymentId, $response);
        
        return $response;
    }
    
    /**
     * Process bKash payment callback
     */
    private function processBkashPayment($payment, $data) {
        // Mock bKash payment verification
        // In real implementation, this would verify with bKash API
        
        $success = isset($data['paymentID']) && isset($data['status']) && $data['status'] === 'Completed';
        
        return [
            'success' => $success,
            'transaction_id' => $data['paymentID'] ?? null,
            'amount_paid' => $data['amount'] ?? null,
            'payment_time' => date('Y-m-d H:i:s'),
            'gateway_response' => $data
        ];
    }
    
    /**
     * Process Nagad payment callback
     */
    private function processNagadPayment($payment, $data) {
        // Mock Nagad payment verification
        $success = isset($data['payment_ref_id']) && isset($data['status']) && $data['status'] === 'Success';
        
        return [
            'success' => $success,
            'transaction_id' => $data['payment_ref_id'] ?? null,
            'amount_paid' => $data['amount'] ?? null,
            'payment_time' => date('Y-m-d H:i:s'),
            'gateway_response' => $data
        ];
    }
    
    /**
     * Process card payment callback
     */
    private function processCardPayment($payment, $data) {
        // Mock card payment verification
        $success = isset($data['val_id']) && isset($data['status']) && $data['status'] === 'VALID';
        
        return [
            'success' => $success,
            'transaction_id' => $data['val_id'] ?? null,
            'amount_paid' => $data['amount'] ?? null,
            'payment_time' => date('Y-m-d H:i:s'),
            'card_type' => $data['card_type'] ?? null,
            'gateway_response' => $data
        ];
    }
    
    /**
     * Process Rocket payment
     */
    private function processRocketPayment($payment, $data) {
        $success = isset($data['trxId']) && isset($data['status']) && $data['status'] === 'completed';
        
        return [
            'success' => $success,
            'transaction_id' => $data['trxId'] ?? null,
            'amount_paid' => $data['amount'] ?? null,
            'payment_time' => date('Y-m-d H:i:s'),
            'gateway_response' => $data
        ];
    }
    
    /**
     * Process bank transfer
     */
    private function processBankTransfer($payment, $data) {
        // Manual verification required for bank transfers
        $success = isset($data['manual_verification']) && $data['manual_verification'] === true;
        
        return [
            'success' => $success,
            'transaction_id' => $data['bank_reference'] ?? null,
            'amount_paid' => $data['amount'] ?? null,
            'payment_time' => date('Y-m-d H:i:s'),
            'verification_notes' => $data['notes'] ?? '',
            'manual_verification' => true
        ];
    }
    
    /**
     * Process COD payment
     */
    private function processCODPayment($payment, $data) {
        // COD is marked as paid when delivery is confirmed
        $success = isset($data['delivery_confirmed']) && $data['delivery_confirmed'] === true;
        
        return [
            'success' => $success,
            'amount_paid' => $data['amount_collected'] ?? null,
            'payment_time' => date('Y-m-d H:i:s'),
            'delivery_person' => $data['delivery_person'] ?? null,
            'collection_notes' => $data['notes'] ?? ''
        ];
    }
    
    /**
     * Process refund
     */
    public function processRefund($orderId, $reason = null) {
        try {
            $payment = $this->getPaymentByOrderId($orderId);
            if (!$payment) {
                throw new Exception("Payment record not found");
            }
            
            if ($payment['status'] !== 'completed') {
                throw new Exception("Cannot refund incomplete payment");
            }
            
            // Create refund record
            $refundId = $this->createRefundRecord($payment['id'], $payment['amount'], $reason);
            
            $result = [
                'success' => false,
                'refund_id' => $refundId,
                'payment_id' => $payment['id'],
                'amount' => $payment['amount']
            ];
            
            // Process refund based on payment method
            switch ($payment['payment_method']) {
                case 'cod':
                    // COD refunds handled manually
                    $result['success'] = true;
                    $result['refund_method'] = 'manual';
                    $result['instructions'] = 'Refund will be processed manually within 3-5 business days';
                    break;
                    
                case 'bkash':
                case 'nagad':
                case 'rocket':
                    // Mobile payment refunds (mock)
                    $result['success'] = true;
                    $result['refund_method'] = 'mobile_wallet';
                    $result['estimated_time'] = '24-48 hours';
                    break;
                    
                case 'card':
                    // Card refunds (mock)
                    $result['success'] = true;
                    $result['refund_method'] = 'card_reversal';
                    $result['estimated_time'] = '5-7 business days';
                    break;
                    
                case 'bank_transfer':
                    // Bank transfer refunds
                    $result['success'] = true;
                    $result['refund_method'] = 'bank_transfer';
                    $result['estimated_time'] = '3-5 business days';
                    break;
            }
            
            if ($result['success']) {
                $this->updateRefundStatus($refundId, 'processing', $result);
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Refund processing error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get payment information for an order
     */
    public function getPaymentInfo($orderId) {
        try {
            $sql = "SELECT p.*, r.id as refund_id, r.status as refund_status, r.amount as refund_amount
                    FROM payments p
                    LEFT JOIN refunds r ON p.id = r.payment_id
                    WHERE p.order_id = :order_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':order_id' => $orderId]);
            
            $payment = $stmt->fetch();
            
            if ($payment && $payment['payment_details']) {
                $payment['payment_details'] = json_decode($payment['payment_details'], true);
            }
            
            return $payment;
            
        } catch (Exception $e) {
            error_log("Get payment info error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get available payment methods
     */
    public function getAvailablePaymentMethods() {
        $methods = [];
        
        foreach ($this->gateways as $key => $gateway) {
            if ($gateway['enabled']) {
                $methods[] = [
                    'code' => $key,
                    'name' => $gateway['name'],
                    'type' => $this->getPaymentMethodType($key)
                ];
            }
        }
        
        return $methods;
    }
    
    /**
     * Get payment method type
     */
    private function getPaymentMethodType($method) {
        $types = [
            'bkash' => 'mobile_banking',
            'nagad' => 'mobile_banking',
            'rocket' => 'mobile_banking',
            'card' => 'credit_card',
            'bank_transfer' => 'bank_transfer',
            'cod' => 'cash_on_delivery'
        ];
        
        return $types[$method] ?? 'other';
    }
    
    // Database helper methods
    
    private function createPaymentRecord($orderId, $amount, $method, $riskAssessment) {
        $sql = "INSERT INTO payments (order_id, amount, payment_method, status, risk_score, created_at, updated_at)
                VALUES (:order_id, :amount, :method, 'pending', :risk_score, NOW(), NOW())
                RETURNING id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':order_id' => $orderId,
            ':amount' => $amount,
            ':method' => $method,
            ':risk_score' => $riskAssessment['risk_score']
        ]);
        
        return $stmt->fetch()['id'];
    }
    
    private function updatePaymentDetails($paymentId, $details) {
        $sql = "UPDATE payments SET payment_details = :details, updated_at = NOW() WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $paymentId,
            ':details' => json_encode($details)
        ]);
    }
    
    private function updatePaymentStatus($paymentId, $status, $response = null) {
        $sql = "UPDATE payments SET status = :status, gateway_response = :response, updated_at = NOW() WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $paymentId,
            ':status' => $status,
            ':response' => $response ? json_encode($response) : null
        ]);
    }
    
    private function getOrderDetails($orderId) {
        $sql = "SELECT o.*, u.region, u.email, u.phone FROM orders o 
                LEFT JOIN users u ON o.buyer_id = u.id 
                WHERE o.id = :order_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        
        return $stmt->fetch();
    }
    
    private function getPaymentByOrderId($orderId) {
        $sql = "SELECT * FROM payments WHERE order_id = :order_id ORDER BY created_at DESC LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        
        return $stmt->fetch();
    }
    
    private function createRefundRecord($paymentId, $amount, $reason) {
        $sql = "INSERT INTO refunds (payment_id, amount, reason, status, created_at, updated_at)
                VALUES (:payment_id, :amount, :reason, 'pending', NOW(), NOW())
                RETURNING id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':payment_id' => $paymentId,
            ':amount' => $amount,
            ':reason' => $reason
        ]);
        
        return $stmt->fetch()['id'];
    }
    
    private function updateRefundStatus($refundId, $status, $details = null) {
        $sql = "UPDATE refunds SET status = :status, refund_details = :details, updated_at = NOW() WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $refundId,
            ':status' => $status,
            ':details' => $details ? json_encode($details) : null
        ]);
    }
}
