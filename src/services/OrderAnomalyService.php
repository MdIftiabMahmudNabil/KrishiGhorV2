<?php
/**
 * Order Anomaly Service
 * AI-powered detection of unusual order patterns (quantity, region, time)
 */

require_once __DIR__ . '/../config/database.php';

class OrderAnomalyService {
    private $db;
    
    // Anomaly detection thresholds
    private $thresholds = [
        'quantity_zscore' => 2.5,     // Z-score threshold for quantity anomalies
        'amount_zscore' => 2.0,       // Z-score threshold for amount anomalies
        'frequency_24h' => 10,        // Max orders per 24h before flagging
        'frequency_1h' => 5,          // Max orders per hour before flagging
        'cross_region_ratio' => 0.8,  // Ratio of cross-region orders that's suspicious
        'unusual_time_score' => 0.7   // Threshold for unusual timing patterns
    ];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Check for order anomalies
     */
    public function checkOrderAnomalies($buyerId, $productId, $quantity, $buyerRegion) {
        try {
            $anomalies = [];
            $totalAnomalyScore = 0;
            
            // 1. Quantity anomaly detection
            $quantityAnomaly = $this->detectQuantityAnomaly($buyerId, $productId, $quantity);
            if ($quantityAnomaly['is_anomalous']) {
                $anomalies[] = $quantityAnomaly;
                $totalAnomalyScore += $quantityAnomaly['severity_score'];
            }
            
            // 2. Geographic anomaly detection
            $geoAnomaly = $this->detectGeographicAnomaly($buyerId, $productId, $buyerRegion);
            if ($geoAnomaly['is_anomalous']) {
                $anomalies[] = $geoAnomaly;
                $totalAnomalyScore += $geoAnomaly['severity_score'];
            }
            
            // 3. Temporal anomaly detection
            $timeAnomaly = $this->detectTemporalAnomaly($buyerId);
            if ($timeAnomaly['is_anomalous']) {
                $anomalies[] = $timeAnomaly;
                $totalAnomalyScore += $timeAnomaly['severity_score'];
            }
            
            // 4. Order frequency anomaly
            $frequencyAnomaly = $this->detectFrequencyAnomaly($buyerId);
            if ($frequencyAnomaly['is_anomalous']) {
                $anomalies[] = $frequencyAnomaly;
                $totalAnomalyScore += $frequencyAnomaly['severity_score'];
            }
            
            // 5. Product pattern anomaly
            $patternAnomaly = $this->detectProductPatternAnomaly($buyerId, $productId);
            if ($patternAnomaly['is_anomalous']) {
                $anomalies[] = $patternAnomaly;
                $totalAnomalyScore += $patternAnomaly['severity_score'];
            }
            
            // Determine overall anomaly status
            $isAnomalous = !empty($anomalies);
            $severityLevel = $this->determineAnomalySeverity($totalAnomalyScore);
            
            // Store anomaly detection results
            if ($isAnomalous) {
                $this->storeAnomalyDetection($buyerId, $productId, $anomalies, $totalAnomalyScore, $severityLevel);
            }
            
            return [
                'is_anomalous' => $isAnomalous,
                'anomaly_score' => round($totalAnomalyScore, 3),
                'severity_level' => $severityLevel,
                'anomalies' => $anomalies,
                'detection_time' => date('Y-m-d H:i:s'),
                'recommendations' => $this->generateAnomalyRecommendations($anomalies, $severityLevel)
            ];
            
        } catch (Exception $e) {
            error_log("Order anomaly detection error: " . $e->getMessage());
            
            return [
                'is_anomalous' => false,
                'anomaly_score' => 0,
                'severity_level' => 'low',
                'anomalies' => [],
                'detection_time' => date('Y-m-d H:i:s'),
                'error' => 'Anomaly detection failed'
            ];
        }
    }
    
    /**
     * Detect quantity anomalies
     */
    private function detectQuantityAnomaly($buyerId, $productId, $quantity) {
        try {
            // Get historical quantity data for this buyer
            $sql = "SELECT 
                        AVG(quantity) as avg_quantity,
                        STDDEV(quantity) as stddev_quantity,
                        MAX(quantity) as max_quantity,
                        COUNT(*) as order_count
                    FROM orders 
                    WHERE buyer_id = :buyer_id
                    AND created_at >= NOW() - INTERVAL '90 days'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':buyer_id' => $buyerId]);
            $stats = $stmt->fetch();
            
            // Also get product-specific stats
            $sql2 = "SELECT 
                        AVG(quantity) as product_avg_quantity,
                        STDDEV(quantity) as product_stddev_quantity,
                        COUNT(*) as product_order_count
                    FROM orders 
                    WHERE product_id = :product_id
                    AND created_at >= NOW() - INTERVAL '90 days'";
            
            $stmt2 = $this->db->prepare($sql2);
            $stmt2->execute([':product_id' => $productId]);
            $productStats = $stmt2->fetch();
            
            $isAnomalous = false;
            $reasons = [];
            $severityScore = 0;
            
            // Check buyer's historical pattern
            if ($stats['order_count'] >= 3 && $stats['stddev_quantity'] > 0) {
                $zScore = abs(($quantity - $stats['avg_quantity']) / $stats['stddev_quantity']);
                
                if ($zScore > $this->thresholds['quantity_zscore']) {
                    $isAnomalous = true;
                    $severityScore += min(0.5, $zScore / 10); // Cap at 0.5
                    $reasons[] = "Quantity significantly different from buyer's history (z-score: " . round($zScore, 2) . ")";
                }
            }
            
            // Check against product's typical quantities
            if ($productStats['product_order_count'] >= 5 && $productStats['product_stddev_quantity'] > 0) {
                $productZScore = abs(($quantity - $productStats['product_avg_quantity']) / $productStats['product_stddev_quantity']);
                
                if ($productZScore > $this->thresholds['quantity_zscore']) {
                    $isAnomalous = true;
                    $severityScore += min(0.3, $productZScore / 15); // Cap at 0.3
                    $reasons[] = "Quantity unusual for this product (z-score: " . round($productZScore, 2) . ")";
                }
            }
            
            // Very large quantities are always suspicious
            if ($quantity > 1000) { // Threshold for large quantity
                $isAnomalous = true;
                $severityScore += 0.4;
                $reasons[] = "Very large quantity ordered ({$quantity} units)";
            }
            
            return [
                'type' => 'quantity_anomaly',
                'is_anomalous' => $isAnomalous,
                'severity_score' => $severityScore,
                'reasons' => $reasons,
                'data' => [
                    'ordered_quantity' => $quantity,
                    'buyer_avg_quantity' => round(floatval($stats['avg_quantity'] ?? 0), 2),
                    'product_avg_quantity' => round(floatval($productStats['product_avg_quantity'] ?? 0), 2),
                    'buyer_order_count' => intval($stats['order_count']),
                    'product_order_count' => intval($productStats['product_order_count'])
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Quantity anomaly detection error: " . $e->getMessage());
            return [
                'type' => 'quantity_anomaly',
                'is_anomalous' => false,
                'severity_score' => 0,
                'reasons' => ['Quantity analysis failed'],
                'data' => []
            ];
        }
    }
    
    /**
     * Detect geographic anomalies
     */
    private function detectGeographicAnomaly($buyerId, $productId, $buyerRegion) {
        try {
            // Get buyer's ordering patterns by region
            $sql = "SELECT 
                        u_farmer.region as farmer_region,
                        COUNT(*) as order_count,
                        COUNT(*) * 1.0 / SUM(COUNT(*)) OVER() as region_ratio
                    FROM orders o
                    JOIN products p ON o.product_id = p.id
                    JOIN users u_farmer ON p.farmer_id = u_farmer.id
                    WHERE o.buyer_id = :buyer_id
                    AND o.created_at >= NOW() - INTERVAL '90 days'
                    GROUP BY u_farmer.region";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':buyer_id' => $buyerId]);
            $regionPatterns = $stmt->fetchAll();
            
            // Get the farmer's region for the current product
            $sql2 = "SELECT u.region as farmer_region
                     FROM products p
                     JOIN users u ON p.farmer_id = u.id
                     WHERE p.id = :product_id";
            
            $stmt2 = $this->db->prepare($sql2);
            $stmt2->execute([':product_id' => $productId]);
            $currentOrder = $stmt2->fetch();
            
            $isAnomalous = false;
            $reasons = [];
            $severityScore = 0;
            
            if ($currentOrder && count($regionPatterns) > 0) {
                $farmerRegion = $currentOrder['farmer_region'];
                
                // Check if buyer usually orders from their own region
                $sameRegionRatio = 0;
                $crossRegionCount = 0;
                $totalOrders = array_sum(array_column($regionPatterns, 'order_count'));
                
                foreach ($regionPatterns as $pattern) {
                    if ($pattern['farmer_region'] === $buyerRegion) {
                        $sameRegionRatio = $pattern['region_ratio'];
                    } else {
                        $crossRegionCount += $pattern['order_count'];
                    }
                }
                
                // If buyer usually orders from same region but now ordering from different region
                if ($sameRegionRatio > 0.7 && $farmerRegion !== $buyerRegion) {
                    $isAnomalous = true;
                    $severityScore += 0.3;
                    $reasons[] = "Buyer usually orders from same region ({$sameRegionRatio*100:.0f}% same-region), now ordering from {$farmerRegion}";
                }
                
                // If this creates an unusual cross-region pattern
                $crossRegionRatio = $crossRegionCount / max(1, $totalOrders);
                if ($crossRegionRatio > $this->thresholds['cross_region_ratio'] && $farmerRegion !== $buyerRegion) {
                    $isAnomalous = true;
                    $severityScore += 0.2;
                    $reasons[] = "High cross-region order ratio: " . round($crossRegionRatio * 100, 1) . "%";
                }
                
                // Check regional risk factors
                $highRiskRegionPairs = [
                    ['Dhaka', 'Barisal'],
                    ['Chittagong', 'Rangpur'],
                    ['Sylhet', 'Khulna']
                ];
                
                foreach ($highRiskRegionPairs as $pair) {
                    if (($buyerRegion === $pair[0] && $farmerRegion === $pair[1]) ||
                        ($buyerRegion === $pair[1] && $farmerRegion === $pair[0])) {
                        $isAnomalous = true;
                        $severityScore += 0.25;
                        $reasons[] = "High-risk region pair: {$buyerRegion} ↔ {$farmerRegion}";
                        break;
                    }
                }
            }
            
            return [
                'type' => 'geographic_anomaly',
                'is_anomalous' => $isAnomalous,
                'severity_score' => $severityScore,
                'reasons' => $reasons,
                'data' => [
                    'buyer_region' => $buyerRegion,
                    'farmer_region' => $currentOrder['farmer_region'] ?? null,
                    'region_patterns' => $regionPatterns
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Geographic anomaly detection error: " . $e->getMessage());
            return [
                'type' => 'geographic_anomaly',
                'is_anomalous' => false,
                'severity_score' => 0,
                'reasons' => ['Geographic analysis failed'],
                'data' => []
            ];
        }
    }
    
    /**
     * Detect temporal anomalies (unusual timing)
     */
    private function detectTemporalAnomaly($buyerId) {
        try {
            $currentHour = intval(date('H'));
            $currentDayOfWeek = intval(date('w')); // 0 = Sunday
            
            // Get buyer's historical ordering patterns by hour and day
            $sql = "SELECT 
                        EXTRACT(HOUR FROM created_at) as order_hour,
                        EXTRACT(DOW FROM created_at) as order_dow,
                        COUNT(*) as order_count
                    FROM orders 
                    WHERE buyer_id = :buyer_id
                    AND created_at >= NOW() - INTERVAL '90 days'
                    GROUP BY EXTRACT(HOUR FROM created_at), EXTRACT(DOW FROM created_at)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':buyer_id' => $buyerId]);
            $timePatterns = $stmt->fetchAll();
            
            $isAnomalous = false;
            $reasons = [];
            $severityScore = 0;
            
            if (count($timePatterns) >= 5) { // Enough data for pattern analysis
                // Calculate typical ordering hours
                $hourCounts = array_fill(0, 24, 0);
                $dowCounts = array_fill(0, 7, 0);
                $totalOrders = 0;
                
                foreach ($timePatterns as $pattern) {
                    $hour = intval($pattern['order_hour']);
                    $dow = intval($pattern['order_dow']);
                    $count = intval($pattern['order_count']);
                    
                    $hourCounts[$hour] += $count;
                    $dowCounts[$dow] += $count;
                    $totalOrders += $count;
                }
                
                // Check if current hour is unusual
                $currentHourRatio = $hourCounts[$currentHour] / max(1, $totalOrders);
                if ($currentHourRatio < 0.05 && $totalOrders > 10) { // Less than 5% of orders at this hour
                    $isAnomalous = true;
                    $severityScore += 0.3;
                    $reasons[] = "Unusual ordering hour: {$currentHour}:00 (only " . round($currentHourRatio * 100, 1) . "% of past orders)";
                }
                
                // Check if current day is unusual
                $currentDowRatio = $dowCounts[$currentDayOfWeek] / max(1, $totalOrders);
                if ($currentDowRatio < 0.05 && $totalOrders > 10) {
                    $isAnomalous = true;
                    $severityScore += 0.2;
                    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    $reasons[] = "Unusual ordering day: {$dayNames[$currentDayOfWeek]} (only " . round($currentDowRatio * 100, 1) . "% of past orders)";
                }
                
                // Very late night or very early morning orders are suspicious
                if ($currentHour >= 23 || $currentHour <= 5) {
                    $isAnomalous = true;
                    $severityScore += 0.25;
                    $reasons[] = "Order placed during unusual hours ({$currentHour}:00)";
                }
            }
            
            return [
                'type' => 'temporal_anomaly',
                'is_anomalous' => $isAnomalous,
                'severity_score' => $severityScore,
                'reasons' => $reasons,
                'data' => [
                    'current_hour' => $currentHour,
                    'current_day_of_week' => $currentDayOfWeek,
                    'historical_patterns' => $timePatterns
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Temporal anomaly detection error: " . $e->getMessage());
            return [
                'type' => 'temporal_anomaly',
                'is_anomalous' => false,
                'severity_score' => 0,
                'reasons' => ['Temporal analysis failed'],
                'data' => []
            ];
        }
    }
    
    /**
     * Detect frequency anomalies
     */
    private function detectFrequencyAnomaly($buyerId) {
        try {
            // Get recent order frequency
            $sql = "SELECT 
                        COUNT(CASE WHEN created_at >= NOW() - INTERVAL '1 hour' THEN 1 END) as orders_1h,
                        COUNT(CASE WHEN created_at >= NOW() - INTERVAL '24 hours' THEN 1 END) as orders_24h,
                        COUNT(CASE WHEN created_at >= NOW() - INTERVAL '7 days' THEN 1 END) as orders_7d,
                        COUNT(CASE WHEN created_at >= NOW() - INTERVAL '30 days' THEN 1 END) as orders_30d
                    FROM orders 
                    WHERE buyer_id = :buyer_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':buyer_id' => $buyerId]);
            $frequency = $stmt->fetch();
            
            $isAnomalous = false;
            $reasons = [];
            $severityScore = 0;
            
            // Check hourly frequency
            if ($frequency['orders_1h'] >= $this->thresholds['frequency_1h']) {
                $isAnomalous = true;
                $severityScore += 0.5;
                $reasons[] = "High frequency: {$frequency['orders_1h']} orders in last hour";
            }
            
            // Check daily frequency
            if ($frequency['orders_24h'] >= $this->thresholds['frequency_24h']) {
                $isAnomalous = true;
                $severityScore += 0.4;
                $reasons[] = "High frequency: {$frequency['orders_24h']} orders in last 24 hours";
            }
            
            // Check for burst patterns (many orders in short time)
            $sql2 = "SELECT 
                        DATE_TRUNC('minute', created_at) as minute_bucket,
                        COUNT(*) as orders_per_minute
                     FROM orders 
                     WHERE buyer_id = :buyer_id
                     AND created_at >= NOW() - INTERVAL '1 hour'
                     GROUP BY DATE_TRUNC('minute', created_at)
                     HAVING COUNT(*) > 1
                     ORDER BY orders_per_minute DESC
                     LIMIT 1";
            
            $stmt2 = $this->db->prepare($sql2);
            $stmt2->execute([':buyer_id' => $buyerId]);
            $burstData = $stmt2->fetch();
            
            if ($burstData && $burstData['orders_per_minute'] > 2) {
                $isAnomalous = true;
                $severityScore += 0.6;
                $reasons[] = "Burst pattern: {$burstData['orders_per_minute']} orders in same minute";
            }
            
            return [
                'type' => 'frequency_anomaly',
                'is_anomalous' => $isAnomalous,
                'severity_score' => $severityScore,
                'reasons' => $reasons,
                'data' => [
                    'orders_1h' => intval($frequency['orders_1h']),
                    'orders_24h' => intval($frequency['orders_24h']),
                    'orders_7d' => intval($frequency['orders_7d']),
                    'orders_30d' => intval($frequency['orders_30d']),
                    'burst_data' => $burstData
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Frequency anomaly detection error: " . $e->getMessage());
            return [
                'type' => 'frequency_anomaly',
                'is_anomalous' => false,
                'severity_score' => 0,
                'reasons' => ['Frequency analysis failed'],
                'data' => []
            ];
        }
    }
    
    /**
     * Detect product pattern anomalies
     */
    private function detectProductPatternAnomaly($buyerId, $productId) {
        try {
            // Get buyer's product category preferences
            $sql = "SELECT 
                        p.category,
                        COUNT(*) as order_count,
                        COUNT(*) * 1.0 / SUM(COUNT(*)) OVER() as category_ratio
                    FROM orders o
                    JOIN products p ON o.product_id = p.id
                    WHERE o.buyer_id = :buyer_id
                    AND o.created_at >= NOW() - INTERVAL '90 days'
                    GROUP BY p.category";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':buyer_id' => $buyerId]);
            $categoryPatterns = $stmt->fetchAll();
            
            // Get current product's category
            $sql2 = "SELECT category FROM products WHERE id = :product_id";
            $stmt2 = $this->db->prepare($sql2);
            $stmt2->execute([':product_id' => $productId]);
            $currentProduct = $stmt2->fetch();
            
            $isAnomalous = false;
            $reasons = [];
            $severityScore = 0;
            
            if ($currentProduct && count($categoryPatterns) > 0) {
                $currentCategory = $currentProduct['category'];
                
                // Check if this category is unusual for the buyer
                $categoryFound = false;
                foreach ($categoryPatterns as $pattern) {
                    if ($pattern['category'] === $currentCategory) {
                        $categoryFound = true;
                        // If this category is less than 5% of buyer's orders, it's unusual
                        if ($pattern['category_ratio'] < 0.05) {
                            $isAnomalous = true;
                            $severityScore += 0.3;
                            $reasons[] = "Unusual product category for buyer: {$currentCategory} (only " . round($pattern['category_ratio'] * 100, 1) . "% of past orders)";
                        }
                        break;
                    }
                }
                
                // If buyer has never ordered from this category before
                if (!$categoryFound && count($categoryPatterns) >= 5) {
                    $isAnomalous = true;
                    $severityScore += 0.4;
                    $reasons[] = "First time ordering from category: {$currentCategory}";
                }
                
                // Check for rapid category switching
                $sql3 = "SELECT p.category, o.created_at
                         FROM orders o
                         JOIN products p ON o.product_id = p.id
                         WHERE o.buyer_id = :buyer_id
                         ORDER BY o.created_at DESC
                         LIMIT 5";
                
                $stmt3 = $this->db->prepare($sql3);
                $stmt3->execute([':buyer_id' => $buyerId]);
                $recentOrders = $stmt3->fetchAll();
                
                $uniqueCategories = array_unique(array_column($recentOrders, 'category'));
                if (count($uniqueCategories) >= 4 && count($recentOrders) >= 4) {
                    $isAnomalous = true;
                    $severityScore += 0.2;
                    $reasons[] = "Rapid category switching: " . count($uniqueCategories) . " different categories in last " . count($recentOrders) . " orders";
                }
            }
            
            return [
                'type' => 'product_pattern_anomaly',
                'is_anomalous' => $isAnomalous,
                'severity_score' => $severityScore,
                'reasons' => $reasons,
                'data' => [
                    'current_category' => $currentProduct['category'] ?? null,
                    'category_patterns' => $categoryPatterns
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Product pattern anomaly detection error: " . $e->getMessage());
            return [
                'type' => 'product_pattern_anomaly',
                'is_anomalous' => false,
                'severity_score' => 0,
                'reasons' => ['Product pattern analysis failed'],
                'data' => []
            ];
        }
    }
    
    /**
     * Determine anomaly severity
     */
    private function determineAnomalySeverity($totalScore) {
        if ($totalScore <= 0.3) {
            return 'low';
        } elseif ($totalScore <= 0.6) {
            return 'medium';
        } elseif ($totalScore <= 0.9) {
            return 'high';
        } else {
            return 'critical';
        }
    }
    
    /**
     * Generate recommendations based on anomalies
     */
    private function generateAnomalyRecommendations($anomalies, $severityLevel) {
        $recommendations = [];
        
        switch ($severityLevel) {
            case 'low':
                $recommendations[] = "Low anomaly score - proceed with standard processing";
                break;
                
            case 'medium':
                $recommendations[] = "Medium anomaly detected - consider additional verification";
                $recommendations[] = "Monitor order completion and payment behavior";
                break;
                
            case 'high':
                $recommendations[] = "High anomaly detected - manual review recommended";
                $recommendations[] = "Consider requiring additional buyer verification";
                $recommendations[] = "Flag order for admin attention";
                break;
                
            case 'critical':
                $recommendations[] = "Critical anomaly detected - hold order for manual approval";
                $recommendations[] = "Require identity verification from buyer";
                $recommendations[] = "Contact buyer to confirm order legitimacy";
                break;
        }
        
        // Specific recommendations based on anomaly types
        foreach ($anomalies as $anomaly) {
            switch ($anomaly['type']) {
                case 'quantity_anomaly':
                    $recommendations[] = "Verify large quantity order with buyer";
                    break;
                case 'geographic_anomaly':
                    $recommendations[] = "Confirm delivery address for cross-region order";
                    break;
                case 'temporal_anomaly':
                    $recommendations[] = "Unusual timing - verify buyer authenticity";
                    break;
                case 'frequency_anomaly':
                    $recommendations[] = "High frequency detected - check for automation/fraud";
                    break;
                case 'product_pattern_anomaly':
                    $recommendations[] = "Unusual product choice - confirm buyer intent";
                    break;
            }
        }
        
        return array_unique($recommendations);
    }
    
    /**
     * Store anomaly detection results
     */
    private function storeAnomalyDetection($buyerId, $productId, $anomalies, $totalScore, $severityLevel) {
        try {
            $sql = "INSERT INTO order_anomaly_detections (
                        buyer_id, product_id, anomaly_score, severity_level, 
                        anomalies, created_at
                    ) VALUES (
                        :buyer_id, :product_id, :anomaly_score, :severity_level, 
                        :anomalies, NOW()
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':buyer_id' => $buyerId,
                ':product_id' => $productId,
                ':anomaly_score' => $totalScore,
                ':severity_level' => $severityLevel,
                ':anomalies' => json_encode($anomalies)
            ]);
            
        } catch (Exception $e) {
            error_log("Store anomaly detection error: " . $e->getMessage());
        }
    }
    
    /**
     * Get anomaly statistics for admin dashboard
     */
    public function getAnomalyStatistics($days = 30) {
        try {
            $sql = "SELECT 
                        severity_level,
                        COUNT(*) as count,
                        AVG(anomaly_score) as avg_score
                    FROM order_anomaly_detections 
                    WHERE created_at >= NOW() - INTERVAL ':days days'
                    GROUP BY severity_level
                    ORDER BY 
                        CASE severity_level 
                            WHEN 'low' THEN 1 
                            WHEN 'medium' THEN 2 
                            WHEN 'high' THEN 3 
                            WHEN 'critical' THEN 4 
                        END";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':days' => $days]);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Get anomaly statistics error: " . $e->getMessage());
            return [];
        }
    }
}
