<?php
/**
 * Payment Risk Service
 * AI-powered payment risk scoring for COD failures and high-risk patterns
 */

require_once __DIR__ . '/../config/database.php';

class PaymentRiskService {
    private $db;
    
    // Risk scoring weights
    private $riskWeights = [
        'payment_history' => 0.3,
        'order_patterns' => 0.25,
        'geographic_risk' => 0.15,
        'account_age' => 0.1,
        'order_frequency' => 0.1,
        'amount_anomaly' => 0.1
    ];
    
    // Risk thresholds
    private $riskThresholds = [
        'low' => 0.3,
        'medium' => 0.6,
        'high' => 0.8
    ];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Assess payment risk for an order
     */
    public function assessPaymentRisk($orderId, $paymentMethod, $orderData) {
        try {
            $riskFactors = [];
            $totalRiskScore = 0;
            
            // 1. Payment history analysis
            $paymentHistoryRisk = $this->analyzePaymentHistory($orderData['buyer_id'], $paymentMethod);
            $riskFactors['payment_history'] = $paymentHistoryRisk;
            $totalRiskScore += $paymentHistoryRisk['score'] * $this->riskWeights['payment_history'];
            
            // 2. Order pattern analysis
            $orderPatternRisk = $this->analyzeOrderPatterns($orderData['buyer_id'], $orderData);
            $riskFactors['order_patterns'] = $orderPatternRisk;
            $totalRiskScore += $orderPatternRisk['score'] * $this->riskWeights['order_patterns'];
            
            // 3. Geographic risk analysis
            $geographicRisk = $this->analyzeGeographicRisk($orderData['region'] ?? null, $orderData);
            $riskFactors['geographic_risk'] = $geographicRisk;
            $totalRiskScore += $geographicRisk['score'] * $this->riskWeights['geographic_risk'];
            
            // 4. Account age analysis
            $accountAgeRisk = $this->analyzeAccountAge($orderData['buyer_id']);
            $riskFactors['account_age'] = $accountAgeRisk;
            $totalRiskScore += $accountAgeRisk['score'] * $this->riskWeights['account_age'];
            
            // 5. Order frequency analysis
            $frequencyRisk = $this->analyzeOrderFrequency($orderData['buyer_id']);
            $riskFactors['order_frequency'] = $frequencyRisk;
            $totalRiskScore += $frequencyRisk['score'] * $this->riskWeights['order_frequency'];
            
            // 6. Amount anomaly analysis
            $amountRisk = $this->analyzeAmountAnomaly($orderData['buyer_id'], $orderData['total_amount']);
            $riskFactors['amount_anomaly'] = $amountRisk;
            $totalRiskScore += $amountRisk['score'] * $this->riskWeights['amount_anomaly'];
            
            // Determine risk level
            $riskLevel = $this->determineRiskLevel($totalRiskScore);
            
            // Generate recommendations
            $recommendations = $this->generateRiskRecommendations($riskLevel, $riskFactors, $paymentMethod);
            
            // Store risk assessment
            $this->storeRiskAssessment($orderId, $totalRiskScore, $riskLevel, $riskFactors, $recommendations);
            
            return [
                'risk_score' => round($totalRiskScore, 3),
                'risk_level' => $riskLevel,
                'risk_factors' => $riskFactors,
                'recommendations' => $recommendations,
                'assessment_date' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Payment risk assessment error: " . $e->getMessage());
            
            // Return default medium risk on error
            return [
                'risk_score' => 0.5,
                'risk_level' => 'medium',
                'risk_factors' => [],
                'recommendations' => ['Manual review recommended due to assessment error'],
                'assessment_date' => date('Y-m-d H:i:s'),
                'error' => 'Risk assessment failed'
            ];
        }
    }
    
    /**
     * Analyze payment history
     */
    private function analyzePaymentHistory($buyerId, $paymentMethod) {
        try {
            // Get payment history for this buyer
            $sql = "SELECT 
                        COUNT(*) as total_payments,
                        COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as successful_payments,
                        COUNT(CASE WHEN p.status = 'failed' THEN 1 END) as failed_payments,
                        COUNT(CASE WHEN p.payment_method = 'cod' AND p.status = 'failed' THEN 1 END) as cod_failures,
                        AVG(CASE WHEN p.status = 'completed' THEN 1.0 ELSE 0.0 END) as success_rate
                    FROM payments p
                    JOIN orders o ON p.order_id = o.id
                    WHERE o.buyer_id = :buyer_id
                    AND p.created_at >= NOW() - INTERVAL '90 days'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':buyer_id' => $buyerId]);
            $history = $stmt->fetch();
            
            $score = 0;
            $reasons = [];
            
            if ($history['total_payments'] == 0) {
                // New customer - medium risk
                $score = 0.5;
                $reasons[] = 'No payment history (new customer)';
            } else {
                // Calculate score based on success rate
                $successRate = floatval($history['success_rate']);
                
                if ($successRate >= 0.9) {
                    $score = 0.1; // Very low risk
                } elseif ($successRate >= 0.7) {
                    $score = 0.3; // Low risk
                } elseif ($successRate >= 0.5) {
                    $score = 0.6; // Medium risk
                } else {
                    $score = 0.9; // High risk
                }
                
                // Additional penalties for COD failures
                if ($paymentMethod === 'cod' && $history['cod_failures'] > 0) {
                    $codFailureRate = $history['cod_failures'] / max(1, $history['total_payments']);
                    $score += $codFailureRate * 0.3;
                    $reasons[] = "COD failure rate: " . round($codFailureRate * 100, 1) . "%";
                }
                
                $reasons[] = "Payment success rate: " . round($successRate * 100, 1) . "%";
                $reasons[] = "Total payments: " . $history['total_payments'];
            }
            
            return [
                'score' => min(1.0, $score),
                'reasons' => $reasons,
                'data' => $history
            ];
            
        } catch (Exception $e) {
            error_log("Payment history analysis error: " . $e->getMessage());
            return [
                'score' => 0.5,
                'reasons' => ['Payment history analysis failed'],
                'data' => []
            ];
        }
    }
    
    /**
     * Analyze order patterns
     */
    private function analyzeOrderPatterns($buyerId, $orderData) {
        try {
            // Get recent order patterns
            $sql = "SELECT 
                        COUNT(*) as total_orders,
                        COUNT(CASE WHEN order_status = 'cancelled' THEN 1 END) as cancelled_orders,
                        COUNT(CASE WHEN order_status = 'delivered' THEN 1 END) as delivered_orders,
                        AVG(total_amount) as avg_order_value,
                        STDDEV(total_amount) as order_value_stddev,
                        MAX(total_amount) as max_order_value,
                        COUNT(DISTINCT farmer_id) as unique_farmers
                    FROM orders 
                    WHERE buyer_id = :buyer_id
                    AND created_at >= NOW() - INTERVAL '30 days'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':buyer_id' => $buyerId]);
            $patterns = $stmt->fetch();
            
            $score = 0;
            $reasons = [];
            
            if ($patterns['total_orders'] == 0) {
                // New customer
                $score = 0.4;
                $reasons[] = 'No recent order history';
            } else {
                // High cancellation rate is risky
                $cancellationRate = $patterns['cancelled_orders'] / $patterns['total_orders'];
                if ($cancellationRate > 0.3) {
                    $score += 0.4;
                    $reasons[] = "High cancellation rate: " . round($cancellationRate * 100, 1) . "%";
                }
                
                // Very high order value compared to history is suspicious
                $avgOrderValue = floatval($patterns['avg_order_value']) ?: 1000;
                $currentOrderValue = floatval($orderData['total_amount']);
                
                if ($currentOrderValue > $avgOrderValue * 3) {
                    $score += 0.3;
                    $reasons[] = "Order value significantly higher than average";
                }
                
                // Too many different farmers might indicate suspicious behavior
                if ($patterns['unique_farmers'] > 10 && $patterns['total_orders'] > 0) {
                    $farmerRatio = $patterns['unique_farmers'] / $patterns['total_orders'];
                    if ($farmerRatio > 0.8) {
                        $score += 0.2;
                        $reasons[] = "High farmer diversity ratio";
                    }
                }
                
                // Successful delivery rate
                $deliveryRate = $patterns['delivered_orders'] / max(1, $patterns['total_orders']);
                if ($deliveryRate < 0.5) {
                    $score += 0.3;
                    $reasons[] = "Low delivery completion rate: " . round($deliveryRate * 100, 1) . "%";
                }
            }
            
            return [
                'score' => min(1.0, $score),
                'reasons' => $reasons,
                'data' => $patterns
            ];
            
        } catch (Exception $e) {
            error_log("Order pattern analysis error: " . $e->getMessage());
            return [
                'score' => 0.3,
                'reasons' => ['Order pattern analysis failed'],
                'data' => []
            ];
        }
    }
    
    /**
     * Analyze geographic risk
     */
    private function analyzeGeographicRisk($buyerRegion, $orderData) {
        try {
            // Regional risk scores based on historical data
            $regionalRiskScores = [
                'Dhaka' => 0.2,
                'Chittagong' => 0.3,
                'Sylhet' => 0.2,
                'Rajshahi' => 0.4,
                'Khulna' => 0.3,
                'Barisal' => 0.5,
                'Rangpur' => 0.4,
                'Mymensingh' => 0.3
            ];
            
            $baseScore = $regionalRiskScores[$buyerRegion] ?? 0.5;
            $reasons = ["Regional risk for {$buyerRegion}"];
            
            // Check for cross-regional orders (higher risk)
            if (isset($orderData['farmer_region']) && $orderData['farmer_region'] !== $buyerRegion) {
                $baseScore += 0.2;
                $reasons[] = "Cross-regional order (buyer: {$buyerRegion}, farmer: {$orderData['farmer_region']})";
            }
            
            // Check regional COD failure rates
            $sql = "SELECT 
                        COUNT(*) as total_cod_orders,
                        COUNT(CASE WHEN p.status = 'failed' THEN 1 END) as failed_cod_orders
                    FROM payments p
                    JOIN orders o ON p.order_id = o.id
                    JOIN users u ON o.buyer_id = u.id
                    WHERE u.region = :region
                    AND p.payment_method = 'cod'
                    AND p.created_at >= NOW() - INTERVAL '90 days'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':region' => $buyerRegion]);
            $regionData = $stmt->fetch();
            
            if ($regionData['total_cod_orders'] > 10) {
                $regionalCodFailureRate = $regionData['failed_cod_orders'] / $regionData['total_cod_orders'];
                if ($regionalCodFailureRate > 0.3) {
                    $baseScore += 0.2;
                    $reasons[] = "High regional COD failure rate: " . round($regionalCodFailureRate * 100, 1) . "%";
                }
            }
            
            return [
                'score' => min(1.0, $baseScore),
                'reasons' => $reasons,
                'data' => [
                    'buyer_region' => $buyerRegion,
                    'regional_cod_stats' => $regionData
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Geographic risk analysis error: " . $e->getMessage());
            return [
                'score' => 0.4,
                'reasons' => ['Geographic risk analysis failed'],
                'data' => []
            ];
        }
    }
    
    /**
     * Analyze account age
     */
    private function analyzeAccountAge($buyerId) {
        try {
            $sql = "SELECT 
                        EXTRACT(EPOCH FROM (NOW() - created_at))/86400 as account_age_days,
                        created_at
                    FROM users 
                    WHERE id = :buyer_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':buyer_id' => $buyerId]);
            $userData = $stmt->fetch();
            
            $accountAgeDays = floatval($userData['account_age_days']);
            $score = 0;
            $reasons = [];
            
            if ($accountAgeDays < 1) {
                $score = 0.8; // Very new account - high risk
                $reasons[] = "Account created today";
            } elseif ($accountAgeDays < 7) {
                $score = 0.6; // New account - medium-high risk
                $reasons[] = "Account less than 1 week old";
            } elseif ($accountAgeDays < 30) {
                $score = 0.4; // Recent account - medium risk
                $reasons[] = "Account less than 1 month old";
            } elseif ($accountAgeDays < 90) {
                $score = 0.2; // Established account - low risk
                $reasons[] = "Account less than 3 months old";
            } else {
                $score = 0.1; // Mature account - very low risk
                $reasons[] = "Established account (" . round($accountAgeDays) . " days old)";
            }
            
            return [
                'score' => $score,
                'reasons' => $reasons,
                'data' => [
                    'account_age_days' => round($accountAgeDays, 1),
                    'created_at' => $userData['created_at']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Account age analysis error: " . $e->getMessage());
            return [
                'score' => 0.5,
                'reasons' => ['Account age analysis failed'],
                'data' => []
            ];
        }
    }
    
    /**
     * Analyze order frequency
     */
    private function analyzeOrderFrequency($buyerId) {
        try {
            $sql = "SELECT 
                        COUNT(*) as orders_last_24h,
                        COUNT(CASE WHEN created_at >= NOW() - INTERVAL '7 days' THEN 1 END) as orders_last_7d,
                        COUNT(CASE WHEN created_at >= NOW() - INTERVAL '30 days' THEN 1 END) as orders_last_30d
                    FROM orders 
                    WHERE buyer_id = :buyer_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':buyer_id' => $buyerId]);
            $frequency = $stmt->fetch();
            
            $score = 0;
            $reasons = [];
            
            // Unusually high frequency might indicate fraud
            if ($frequency['orders_last_24h'] > 5) {
                $score += 0.5;
                $reasons[] = "High order frequency: {$frequency['orders_last_24h']} orders in 24h";
            } elseif ($frequency['orders_last_24h'] > 3) {
                $score += 0.3;
                $reasons[] = "Moderate order frequency: {$frequency['orders_last_24h']} orders in 24h";
            }
            
            if ($frequency['orders_last_7d'] > 15) {
                $score += 0.3;
                $reasons[] = "High weekly frequency: {$frequency['orders_last_7d']} orders in 7 days";
            }
            
            // Very low frequency for established accounts might also be risky
            if ($frequency['orders_last_30d'] == 1) {
                // First order in 30 days - check account age
                $score += 0.1;
                $reasons[] = "First order in 30 days";
            }
            
            if (empty($reasons)) {
                $reasons[] = "Normal order frequency";
            }
            
            return [
                'score' => min(1.0, $score),
                'reasons' => $reasons,
                'data' => $frequency
            ];
            
        } catch (Exception $e) {
            error_log("Order frequency analysis error: " . $e->getMessage());
            return [
                'score' => 0.2,
                'reasons' => ['Order frequency analysis failed'],
                'data' => []
            ];
        }
    }
    
    /**
     * Analyze amount anomaly
     */
    private function analyzeAmountAnomaly($buyerId, $orderAmount) {
        try {
            $sql = "SELECT 
                        AVG(total_amount) as avg_amount,
                        STDDEV(total_amount) as stddev_amount,
                        MAX(total_amount) as max_amount,
                        COUNT(*) as order_count
                    FROM orders 
                    WHERE buyer_id = :buyer_id
                    AND created_at >= NOW() - INTERVAL '90 days'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':buyer_id' => $buyerId]);
            $stats = $stmt->fetch();
            
            $score = 0;
            $reasons = [];
            
            if ($stats['order_count'] < 2) {
                // Not enough data for anomaly detection
                $score = 0.2;
                $reasons[] = "Insufficient order history for amount analysis";
            } else {
                $avgAmount = floatval($stats['avg_amount']);
                $stddevAmount = floatval($stats['stddev_amount']);
                $maxAmount = floatval($stats['max_amount']);
                
                // Calculate z-score
                if ($stddevAmount > 0) {
                    $zScore = abs(($orderAmount - $avgAmount) / $stddevAmount);
                    
                    if ($zScore > 3) {
                        $score = 0.8; // Very unusual amount
                        $reasons[] = "Order amount highly unusual (z-score: " . round($zScore, 2) . ")";
                    } elseif ($zScore > 2) {
                        $score = 0.5; // Somewhat unusual
                        $reasons[] = "Order amount unusual (z-score: " . round($zScore, 2) . ")";
                    } elseif ($zScore > 1.5) {
                        $score = 0.2; // Slightly unusual
                        $reasons[] = "Order amount slightly above normal";
                    } else {
                        $reasons[] = "Order amount within normal range";
                    }
                } else {
                    // No variation in previous orders - constant amount pattern
                    if ($orderAmount != $avgAmount) {
                        $score = 0.3;
                        $reasons[] = "Amount differs from consistent pattern";
                    } else {
                        $reasons[] = "Amount matches consistent pattern";
                    }
                }
                
                // Very large orders are always riskier
                if ($orderAmount > 50000) { // 50,000 BDT threshold
                    $score += 0.3;
                    $reasons[] = "Large order amount (৳" . number_format($orderAmount) . ")";
                }
            }
            
            return [
                'score' => min(1.0, $score),
                'reasons' => $reasons,
                'data' => [
                    'current_amount' => $orderAmount,
                    'avg_amount' => round(floatval($stats['avg_amount'] ?? 0), 2),
                    'max_amount' => floatval($stats['max_amount'] ?? 0),
                    'order_count' => intval($stats['order_count'])
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Amount anomaly analysis error: " . $e->getMessage());
            return [
                'score' => 0.3,
                'reasons' => ['Amount anomaly analysis failed'],
                'data' => []
            ];
        }
    }
    
    /**
     * Determine risk level based on score
     */
    private function determineRiskLevel($score) {
        if ($score <= $this->riskThresholds['low']) {
            return 'low';
        } elseif ($score <= $this->riskThresholds['medium']) {
            return 'medium';
        } elseif ($score <= $this->riskThresholds['high']) {
            return 'high';
        } else {
            return 'critical';
        }
    }
    
    /**
     * Generate risk recommendations
     */
    private function generateRiskRecommendations($riskLevel, $riskFactors, $paymentMethod) {
        $recommendations = [];
        
        switch ($riskLevel) {
            case 'low':
                $recommendations[] = "Low risk - proceed with normal processing";
                break;
                
            case 'medium':
                $recommendations[] = "Medium risk - consider additional verification";
                if ($paymentMethod === 'cod') {
                    $recommendations[] = "For COD: Consider requiring phone verification";
                }
                break;
                
            case 'high':
                $recommendations[] = "High risk - manual review recommended";
                if ($paymentMethod === 'cod') {
                    $recommendations[] = "COD not recommended - suggest prepaid payment";
                }
                $recommendations[] = "Consider requiring ID verification";
                break;
                
            case 'critical':
                $recommendations[] = "Critical risk - block or require extensive verification";
                $recommendations[] = "COD should be blocked";
                $recommendations[] = "Require manual approval from admin";
                break;
        }
        
        // Specific recommendations based on risk factors
        foreach ($riskFactors as $factor => $data) {
            if ($data['score'] > 0.7) {
                switch ($factor) {
                    case 'payment_history':
                        $recommendations[] = "Poor payment history - consider credit limit";
                        break;
                    case 'order_patterns':
                        $recommendations[] = "Unusual order patterns - review order details";
                        break;
                    case 'geographic_risk':
                        $recommendations[] = "High geographic risk - verify delivery address";
                        break;
                    case 'account_age':
                        $recommendations[] = "New account - require additional verification";
                        break;
                    case 'order_frequency':
                        $recommendations[] = "Unusual frequency - check for automation/fraud";
                        break;
                    case 'amount_anomaly':
                        $recommendations[] = "Unusual amount - verify buyer's purchase intent";
                        break;
                }
            }
        }
        
        return array_unique($recommendations);
    }
    
    /**
     * Store risk assessment in database
     */
    private function storeRiskAssessment($orderId, $riskScore, $riskLevel, $riskFactors, $recommendations) {
        try {
            $sql = "INSERT INTO payment_risk_assessments (
                        order_id, risk_score, risk_level, risk_factors, 
                        recommendations, created_at
                    ) VALUES (
                        :order_id, :risk_score, :risk_level, :risk_factors, 
                        :recommendations, NOW()
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':order_id' => $orderId,
                ':risk_score' => $riskScore,
                ':risk_level' => $riskLevel,
                ':risk_factors' => json_encode($riskFactors),
                ':recommendations' => json_encode($recommendations)
            ]);
            
        } catch (Exception $e) {
            error_log("Store risk assessment error: " . $e->getMessage());
        }
    }
    
    /**
     * Record payment success (for learning)
     */
    public function recordPaymentSuccess($orderId, $paymentMethod) {
        try {
            $sql = "UPDATE payment_risk_assessments 
                    SET outcome = 'success', outcome_date = NOW() 
                    WHERE order_id = :order_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':order_id' => $orderId]);
            
        } catch (Exception $e) {
            error_log("Record payment success error: " . $e->getMessage());
        }
    }
    
    /**
     * Record payment failure (for learning)
     */
    public function recordPaymentFailure($orderId, $paymentMethod, $failureReason) {
        try {
            $sql = "UPDATE payment_risk_assessments 
                    SET outcome = 'failure', outcome_date = NOW(), failure_reason = :reason
                    WHERE order_id = :order_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':order_id' => $orderId,
                ':reason' => $failureReason
            ]);
            
        } catch (Exception $e) {
            error_log("Record payment failure error: " . $e->getMessage());
        }
    }
    
    /**
     * Get risk statistics for admin dashboard
     */
    public function getRiskStatistics($days = 30) {
        try {
            $sql = "SELECT 
                        risk_level,
                        COUNT(*) as count,
                        AVG(risk_score) as avg_score,
                        COUNT(CASE WHEN outcome = 'success' THEN 1 END) as successful_payments,
                        COUNT(CASE WHEN outcome = 'failure' THEN 1 END) as failed_payments
                    FROM payment_risk_assessments 
                    WHERE created_at >= NOW() - INTERVAL ':days days'
                    GROUP BY risk_level
                    ORDER BY 
                        CASE risk_level 
                            WHEN 'low' THEN 1 
                            WHEN 'medium' THEN 2 
                            WHEN 'high' THEN 3 
                            WHEN 'critical' THEN 4 
                        END";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':days' => $days]);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Get risk statistics error: " . $e->getMessage());
            return [];
        }
    }
}
