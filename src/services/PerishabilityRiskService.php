<?php
/**
 * Perishability Risk Service
 * AI-powered delay risk assessment based on product perishability and delivery conditions
 */

require_once __DIR__ . '/../config/database.php';

class PerishabilityRiskService {
    private $db;
    
    // Product perishability profiles (shelf life in hours under normal conditions)
    private $perishabilityProfiles = [
        'vegetables' => [
            'leafy_greens' => ['shelf_life' => 24, 'temp_sensitive' => true, 'humidity_sensitive' => true],
            'tomatoes' => ['shelf_life' => 72, 'temp_sensitive' => true, 'humidity_sensitive' => false],
            'potatoes' => ['shelf_life' => 720, 'temp_sensitive' => false, 'humidity_sensitive' => false], // 30 days
            'onions' => ['shelf_life' => 480, 'temp_sensitive' => false, 'humidity_sensitive' => false], // 20 days
            'carrots' => ['shelf_life' => 168, 'temp_sensitive' => false, 'humidity_sensitive' => true], // 7 days
            'cabbage' => ['shelf_life' => 240, 'temp_sensitive' => false, 'humidity_sensitive' => true] // 10 days
        ],
        'fruits' => [
            'bananas' => ['shelf_life' => 120, 'temp_sensitive' => true, 'humidity_sensitive' => false], // 5 days
            'mangoes' => ['shelf_life' => 96, 'temp_sensitive' => true, 'humidity_sensitive' => false], // 4 days
            'apples' => ['shelf_life' => 336, 'temp_sensitive' => false, 'humidity_sensitive' => false], // 14 days
            'oranges' => ['shelf_life' => 240, 'temp_sensitive' => false, 'humidity_sensitive' => false], // 10 days
            'grapes' => ['shelf_life' => 168, 'temp_sensitive' => true, 'humidity_sensitive' => false] // 7 days
        ],
        'dairy' => [
            'milk' => ['shelf_life' => 72, 'temp_sensitive' => true, 'humidity_sensitive' => false], // 3 days
            'cheese' => ['shelf_life' => 240, 'temp_sensitive' => true, 'humidity_sensitive' => false], // 10 days
            'yogurt' => ['shelf_life' => 168, 'temp_sensitive' => true, 'humidity_sensitive' => false] // 7 days
        ],
        'meat' => [
            'fish' => ['shelf_life' => 48, 'temp_sensitive' => true, 'humidity_sensitive' => false], // 2 days
            'chicken' => ['shelf_life' => 72, 'temp_sensitive' => true, 'humidity_sensitive' => false], // 3 days
            'beef' => ['shelf_life' => 120, 'temp_sensitive' => true, 'humidity_sensitive' => false] // 5 days
        ],
        'grains' => [
            'rice' => ['shelf_life' => 8760, 'temp_sensitive' => false, 'humidity_sensitive' => true], // 1 year
            'wheat' => ['shelf_life' => 8760, 'temp_sensitive' => false, 'humidity_sensitive' => true], // 1 year
            'lentils' => ['shelf_life' => 4380, 'temp_sensitive' => false, 'humidity_sensitive' => true] // 6 months
        ],
        'spices' => [
            'fresh_herbs' => ['shelf_life' => 72, 'temp_sensitive' => true, 'humidity_sensitive' => true], // 3 days
            'dried_spices' => ['shelf_life' => 4380, 'temp_sensitive' => false, 'humidity_sensitive' => true] // 6 months
        ]
    ];
    
    // Environmental factors that affect perishability
    private $environmentalFactors = [
        'temperature' => [
            'very_hot' => 2.5,    // 35°C+ - accelerates spoilage 2.5x
            'hot' => 1.8,         // 30-35°C - accelerates spoilage 1.8x
            'warm' => 1.3,        // 25-30°C - accelerates spoilage 1.3x
            'normal' => 1.0,      // 20-25°C - normal rate
            'cool' => 0.7,        // 15-20°C - slows spoilage
            'cold' => 0.5         // <15°C - significantly slows spoilage
        ],
        'humidity' => [
            'very_high' => 1.5,   // >80% humidity
            'high' => 1.2,        // 65-80% humidity
            'normal' => 1.0,      // 45-65% humidity
            'low' => 0.9,         // 30-45% humidity
            'very_low' => 1.1     // <30% humidity (dehydration risk)
        ],
        'season' => [
            'summer' => 1.4,      // Hot, humid season
            'monsoon' => 1.6,     // Very humid season
            'winter' => 0.8,      // Cool, dry season
            'spring' => 1.0,      // Moderate conditions
            'autumn' => 0.9       // Moderate conditions
        ]
    ];
    
    // Transport type preservation capabilities
    private $transportTypes = [
        'truck' => ['refrigerated' => false, 'insulated' => false, 'ventilated' => true],
        'refrigerated_truck' => ['refrigerated' => true, 'insulated' => true, 'ventilated' => true],
        'van' => ['refrigerated' => false, 'insulated' => false, 'ventilated' => true],
        'pickup' => ['refrigerated' => false, 'insulated' => false, 'ventilated' => false],
        'motorbike' => ['refrigerated' => false, 'insulated' => false, 'ventilated' => false]
    ];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Assess delivery risk based on product perishability and conditions
     */
    public function assessDeliveryRisk($productCategory, $pickupLocation, $deliveryLocation, $plannedPickupTime) {
        try {
            // Get product perishability profile
            $productProfile = $this->getProductProfile($productCategory);
            
            // Calculate estimated delivery duration
            $estimatedDuration = $this->estimateDeliveryDuration($pickupLocation, $deliveryLocation);
            
            // Get environmental conditions
            $environmentalConditions = $this->getEnvironmentalConditions($pickupLocation, $deliveryLocation, $plannedPickupTime);
            
            // Calculate base spoilage risk
            $baseSpoilageRisk = $this->calculateBaseSpoilageRisk($productProfile, $estimatedDuration);
            
            // Apply environmental factors
            $environmentalRisk = $this->calculateEnvironmentalRisk($productProfile, $environmentalConditions);
            
            // Calculate transport risk
            $transportRisk = $this->calculateTransportRisk($productProfile, $estimatedDuration);
            
            // Calculate delay sensitivity
            $delaySensitivity = $this->calculateDelaySensitivity($productProfile, $estimatedDuration);
            
            // Combine all risk factors
            $totalRiskScore = $this->combineRiskFactors([
                'base_spoilage' => $baseSpoilageRisk,
                'environmental' => $environmentalRisk,
                'transport' => $transportRisk,
                'delay_sensitivity' => $delaySensitivity
            ]);
            
            // Determine risk level and generate recommendations
            $riskLevel = $this->determineRiskLevel($totalRiskScore);
            $recommendations = $this->generateRecommendations($riskLevel, $productProfile, $environmentalConditions);
            
            // Calculate acceptable delay threshold
            $acceptableDelayThreshold = $this->calculateAcceptableDelay($productProfile, $totalRiskScore);
            
            return [
                'risk_score' => round($totalRiskScore, 3),
                'risk_level' => $riskLevel,
                'product_category' => $productCategory,
                'estimated_duration_hours' => $estimatedDuration,
                'acceptable_delay_hours' => $acceptableDelayThreshold,
                'shelf_life_remaining_percent' => $this->calculateRemainingShelfLife($productProfile, $estimatedDuration, $environmentalConditions),
                'risk_factors' => [
                    'base_spoilage_risk' => $baseSpoilageRisk,
                    'environmental_risk' => $environmentalRisk,
                    'transport_risk' => $transportRisk,
                    'delay_sensitivity' => $delaySensitivity
                ],
                'environmental_conditions' => $environmentalConditions,
                'recommendations' => $recommendations,
                'critical_checkpoints' => $this->generateCriticalCheckpoints($productProfile, $estimatedDuration),
                'assessment_time' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Delivery risk assessment error: " . $e->getMessage());
            
            return [
                'risk_score' => 0.5,
                'risk_level' => 'medium',
                'product_category' => $productCategory,
                'estimated_duration_hours' => 24,
                'acceptable_delay_hours' => 6,
                'error' => 'Risk assessment failed'
            ];
        }
    }
    
    /**
     * Assess delay risk for ongoing delivery
     */
    public function assessDelayRisk($productCategory, $originalPickupTime, $currentTime, $additionalDelayHours = 0) {
        try {
            $productProfile = $this->getProductProfile($productCategory);
            
            // Calculate elapsed time
            $elapsedHours = (strtotime($currentTime) - strtotime($originalPickupTime)) / 3600;
            $totalDelayHours = $elapsedHours + $additionalDelayHours;
            
            // Calculate remaining shelf life
            $remainingShelfLifePercent = max(0, (($productProfile['shelf_life'] - $totalDelayHours) / $productProfile['shelf_life']) * 100);
            
            // Determine risk based on remaining shelf life
            $riskScore = 1 - ($remainingShelfLifePercent / 100);
            
            // Apply urgency factors
            if ($remainingShelfLifePercent < 10) {
                $riskLevel = 'critical';
                $urgency = 'immediate_action_required';
            } elseif ($remainingShelfLifePercent < 25) {
                $riskLevel = 'high';
                $urgency = 'urgent';
            } elseif ($remainingShelfLifePercent < 50) {
                $riskLevel = 'medium';
                $urgency = 'moderate';
            } else {
                $riskLevel = 'low';
                $urgency = 'low';
            }
            
            // Generate delay-specific recommendations
            $recommendations = $this->generateDelayRecommendations($riskLevel, $remainingShelfLifePercent, $productProfile);
            
            return [
                'risk_score' => round($riskScore, 3),
                'risk_level' => $riskLevel,
                'urgency' => $urgency,
                'elapsed_hours' => round($elapsedHours, 2),
                'additional_delay_hours' => $additionalDelayHours,
                'remaining_shelf_life_percent' => round($remainingShelfLifePercent, 1),
                'recommendations' => $recommendations,
                'quality_impact' => $this->assessQualityImpact($remainingShelfLifePercent),
                'assessment_time' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Delay risk assessment error: " . $e->getMessage());
            
            return [
                'risk_score' => 0.8,
                'risk_level' => 'high',
                'urgency' => 'urgent',
                'error' => 'Delay risk assessment failed'
            ];
        }
    }
    
    /**
     * Schedule monitoring for high-risk deliveries
     */
    public function scheduleMonitoring($transportId, $riskAssessment) {
        try {
            // Calculate monitoring intervals based on risk level
            $monitoringIntervals = $this->getMonitoringIntervals($riskAssessment['risk_level']);
            
            // Schedule checkpoints
            foreach ($monitoringIntervals as $interval) {
                $checkTime = date('Y-m-d H:i:s', strtotime("+{$interval} minutes"));
                
                $this->scheduleRiskCheckpoint($transportId, $checkTime, $riskAssessment);
            }
            
            return [
                'success' => true,
                'monitoring_points' => count($monitoringIntervals),
                'next_checkpoint' => $monitoringIntervals[0] ?? null
            ];
            
        } catch (Exception $e) {
            error_log("Schedule monitoring error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get analytics for perishability risk management
     */
    public function getRiskAnalytics($filters = []) {
        try {
            $sql = "SELECT 
                        product_category,
                        COUNT(*) as total_deliveries,
                        AVG(risk_score) as avg_risk_score,
                        COUNT(CASE WHEN risk_level = 'high' OR risk_level = 'critical' THEN 1 END) as high_risk_deliveries,
                        COUNT(CASE WHEN quality_impact = 'spoiled' THEN 1 END) as spoiled_deliveries,
                        AVG(delivery_duration_hours) as avg_delivery_duration
                    FROM perishability_assessments
                    WHERE created_at >= NOW() - INTERVAL '30 days'";
            
            if (!empty($filters['farmer_id'])) {
                $sql .= " AND farmer_id = :farmer_id";
            }
            
            $sql .= " GROUP BY product_category ORDER BY avg_risk_score DESC";
            
            $stmt = $this->db->prepare($sql);
            
            if (!empty($filters['farmer_id'])) {
                $stmt->execute([':farmer_id' => $filters['farmer_id']]);
            } else {
                $stmt->execute();
            }
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Get risk analytics error: " . $e->getMessage());
            return [];
        }
    }
    
    // Private helper methods
    
    private function getProductProfile($productCategory) {
        // Determine category and subcategory
        $normalizedCategory = strtolower($productCategory);
        
        // Try to find exact match first
        foreach ($this->perishabilityProfiles as $mainCategory => $subCategories) {
            if (isset($subCategories[$normalizedCategory])) {
                return array_merge(
                    $subCategories[$normalizedCategory],
                    ['main_category' => $mainCategory, 'sub_category' => $normalizedCategory]
                );
            }
        }
        
        // Try to find main category match
        if (isset($this->perishabilityProfiles[$normalizedCategory])) {
            // Use average values for the main category
            $subCategories = $this->perishabilityProfiles[$normalizedCategory];
            $avgShelfLife = array_sum(array_column($subCategories, 'shelf_life')) / count($subCategories);
            
            return [
                'shelf_life' => $avgShelfLife,
                'temp_sensitive' => true, // Conservative default
                'humidity_sensitive' => true, // Conservative default
                'main_category' => $normalizedCategory,
                'sub_category' => 'generic'
            ];
        }
        
        // Default profile for unknown products
        return [
            'shelf_life' => 72, // 3 days
            'temp_sensitive' => true,
            'humidity_sensitive' => true,
            'main_category' => 'unknown',
            'sub_category' => $normalizedCategory
        ];
    }
    
    private function estimateDeliveryDuration($pickupLocation, $deliveryLocation) {
        // Simplified duration estimation - in production use routing API
        $distances = [
            'Dhaka-Chittagong' => 6,    // 6 hours
            'Dhaka-Sylhet' => 5,        // 5 hours
            'Dhaka-Rajshahi' => 6,      // 6 hours
            'Dhaka-Khulna' => 8,        // 8 hours
            'within_city' => 2,         // 2 hours for intra-city
            'nearby_districts' => 4,    // 4 hours for nearby
            'distant_districts' => 8    // 8 hours for distant
        ];
        
        $key = $pickupLocation . '-' . $deliveryLocation;
        $reverseKey = $deliveryLocation . '-' . $pickupLocation;
        
        return $distances[$key] ?? $distances[$reverseKey] ?? $distances['nearby_districts'];
    }
    
    private function getEnvironmentalConditions($pickupLocation, $deliveryLocation, $plannedPickupTime) {
        // Simplified environmental condition estimation
        // In production, integrate with weather API
        
        $hour = intval(date('H', strtotime($plannedPickupTime)));
        $month = intval(date('n', strtotime($plannedPickupTime)));
        
        // Determine season
        if (in_array($month, [12, 1, 2])) {
            $season = 'winter';
        } elseif (in_array($month, [3, 4, 5])) {
            $season = 'spring';
        } elseif (in_array($month, [6, 7, 8])) {
            $season = 'summer';
        } elseif (in_array($month, [9, 10, 11])) {
            $season = 'autumn';
        } else {
            $season = 'spring';
        }
        
        // Determine temperature based on time and season
        if ($season === 'summer') {
            $temperature = $hour >= 10 && $hour <= 16 ? 'very_hot' : 'hot';
        } elseif ($season === 'winter') {
            $temperature = $hour >= 12 && $hour <= 15 ? 'warm' : 'cool';
        } else {
            $temperature = $hour >= 11 && $hour <= 15 ? 'warm' : 'normal';
        }
        
        // Estimate humidity
        $humidity = $season === 'monsoon' ? 'very_high' : ($season === 'summer' ? 'high' : 'normal');
        
        return [
            'temperature' => $temperature,
            'humidity' => $humidity,
            'season' => $season,
            'pickup_time_hour' => $hour,
            'month' => $month
        ];
    }
    
    private function calculateBaseSpoilageRisk($productProfile, $estimatedDuration) {
        // Calculate risk based on delivery duration vs shelf life
        $durationRatio = $estimatedDuration / $productProfile['shelf_life'];
        
        // Risk increases exponentially as we approach shelf life
        $baseRisk = min(1.0, pow($durationRatio, 1.5));
        
        return $baseRisk;
    }
    
    private function calculateEnvironmentalRisk($productProfile, $environmentalConditions) {
        $environmentalMultiplier = 1.0;
        
        // Apply temperature factor
        if ($productProfile['temp_sensitive']) {
            $environmentalMultiplier *= $this->environmentalFactors['temperature'][$environmentalConditions['temperature']];
        }
        
        // Apply humidity factor
        if ($productProfile['humidity_sensitive']) {
            $environmentalMultiplier *= $this->environmentalFactors['humidity'][$environmentalConditions['humidity']];
        }
        
        // Apply seasonal factor
        $environmentalMultiplier *= $this->environmentalFactors['season'][$environmentalConditions['season']];
        
        // Convert multiplier to risk score (1.0 = no additional risk, >1.0 = increased risk)
        return min(1.0, ($environmentalMultiplier - 1.0) * 2);
    }
    
    private function calculateTransportRisk($productProfile, $estimatedDuration) {
        // Risk increases with longer transport duration
        // Higher risk for very perishable items
        
        $transportRisk = 0;
        
        if ($productProfile['shelf_life'] < 48) { // Very perishable (< 2 days)
            $transportRisk = min(0.5, $estimatedDuration / 24);
        } elseif ($productProfile['shelf_life'] < 168) { // Perishable (< 1 week)
            $transportRisk = min(0.3, $estimatedDuration / 48);
        } else { // Less perishable
            $transportRisk = min(0.1, $estimatedDuration / 168);
        }
        
        return $transportRisk;
    }
    
    private function calculateDelaySensitivity($productProfile, $estimatedDuration) {
        // How sensitive is this product to delays?
        $baseDelaySensitivity = 1 / max(1, $productProfile['shelf_life'] / 24); // Daily sensitivity
        
        // Adjust based on current transport duration
        $durationFactor = $estimatedDuration / 24; // Convert to days
        
        return min(1.0, $baseDelaySensitivity * $durationFactor);
    }
    
    private function combineRiskFactors($riskFactors) {
        // Weighted combination of risk factors
        $weights = [
            'base_spoilage' => 0.4,
            'environmental' => 0.3,
            'transport' => 0.2,
            'delay_sensitivity' => 0.1
        ];
        
        $totalRisk = 0;
        foreach ($riskFactors as $factor => $value) {
            $totalRisk += $value * ($weights[$factor] ?? 0);
        }
        
        return min(1.0, $totalRisk);
    }
    
    private function determineRiskLevel($riskScore) {
        if ($riskScore >= 0.8) return 'critical';
        if ($riskScore >= 0.6) return 'high';
        if ($riskScore >= 0.3) return 'medium';
        return 'low';
    }
    
    private function calculateAcceptableDelay($productProfile, $riskScore) {
        // Calculate how much delay is acceptable before quality is significantly impacted
        $baseAcceptableDelay = $productProfile['shelf_life'] * 0.1; // 10% of shelf life
        
        // Adjust based on current risk
        $riskAdjustment = 1.0 - $riskScore;
        
        return max(1, $baseAcceptableDelay * $riskAdjustment); // Minimum 1 hour
    }
    
    private function calculateRemainingShelfLife($productProfile, $duration, $environmentalConditions) {
        $effectiveShelfLife = $productProfile['shelf_life'];
        
        // Apply environmental factors
        if ($productProfile['temp_sensitive']) {
            $tempFactor = $this->environmentalFactors['temperature'][$environmentalConditions['temperature']];
            $effectiveShelfLife /= $tempFactor;
        }
        
        if ($productProfile['humidity_sensitive']) {
            $humidityFactor = $this->environmentalFactors['humidity'][$environmentalConditions['humidity']];
            $effectiveShelfLife /= $humidityFactor;
        }
        
        $remainingPercent = max(0, (($effectiveShelfLife - $duration) / $effectiveShelfLife) * 100);
        
        return round($remainingPercent, 1);
    }
    
    private function generateRecommendations($riskLevel, $productProfile, $environmentalConditions) {
        $recommendations = [];
        
        switch ($riskLevel) {
            case 'critical':
                $recommendations[] = "URGENT: Use refrigerated transport if available";
                $recommendations[] = "Prioritize this delivery - avoid any delays";
                $recommendations[] = "Consider breaking into smaller, faster deliveries";
                $recommendations[] = "Notify buyer about potential quality impact";
                break;
                
            case 'high':
                $recommendations[] = "Use insulated transport if possible";
                $recommendations[] = "Minimize stops and delays during transport";
                $recommendations[] = "Monitor delivery progress closely";
                $recommendations[] = "Prepare buyer for slightly reduced quality";
                break;
                
            case 'medium':
                $recommendations[] = "Ensure proper ventilation during transport";
                $recommendations[] = "Avoid peak heat hours if temperature sensitive";
                $recommendations[] = "Regular progress updates recommended";
                break;
                
            case 'low':
                $recommendations[] = "Standard transport procedures apply";
                $recommendations[] = "Normal delivery schedule acceptable";
                break;
        }
        
        // Add specific recommendations based on product characteristics
        if ($productProfile['temp_sensitive'] && in_array($environmentalConditions['temperature'], ['hot', 'very_hot'])) {
            $recommendations[] = "Avoid direct sunlight exposure during loading/unloading";
        }
        
        if ($productProfile['humidity_sensitive'] && $environmentalConditions['humidity'] === 'very_high') {
            $recommendations[] = "Ensure adequate ventilation to prevent moisture buildup";
        }
        
        return array_unique($recommendations);
    }
    
    private function generateDelayRecommendations($riskLevel, $remainingShelfLife, $productProfile) {
        $recommendations = [];
        
        if ($remainingShelfLife < 10) {
            $recommendations[] = "CRITICAL: Immediate delivery required";
            $recommendations[] = "Consider emergency transport options";
            $recommendations[] = "Notify buyer of potential spoilage";
            $recommendations[] = "Document condition for insurance/liability";
        } elseif ($remainingShelfLife < 25) {
            $recommendations[] = "URGENT: Expedite delivery";
            $recommendations[] = "Use fastest available route";
            $recommendations[] = "Inform buyer of quality concerns";
        } elseif ($remainingShelfLife < 50) {
            $recommendations[] = "Accelerate delivery if possible";
            $recommendations[] = "Monitor product condition";
            $recommendations[] = "Keep buyer informed of progress";
        }
        
        return $recommendations;
    }
    
    private function generateCriticalCheckpoints($productProfile, $estimatedDuration) {
        $checkpoints = [];
        
        // Generate checkpoints at key intervals
        $intervals = [0.25, 0.5, 0.75]; // 25%, 50%, 75% of journey
        
        foreach ($intervals as $interval) {
            $checkTime = $estimatedDuration * $interval;
            $remainingShelfLife = (($productProfile['shelf_life'] - $checkTime) / $productProfile['shelf_life']) * 100;
            
            $checkpoints[] = [
                'checkpoint_time_hours' => round($checkTime, 1),
                'expected_shelf_life_remaining' => round($remainingShelfLife, 1),
                'action_required' => $remainingShelfLife < 50 ? 'quality_check' : 'progress_update'
            ];
        }
        
        return $checkpoints;
    }
    
    private function assessQualityImpact($remainingShelfLifePercent) {
        if ($remainingShelfLifePercent < 5) {
            return 'spoiled';
        } elseif ($remainingShelfLifePercent < 20) {
            return 'severely_degraded';
        } elseif ($remainingShelfLifePercent < 40) {
            return 'moderately_degraded';
        } elseif ($remainingShelfLifePercent < 70) {
            return 'slightly_degraded';
        } else {
            return 'good_quality';
        }
    }
    
    private function getMonitoringIntervals($riskLevel) {
        switch ($riskLevel) {
            case 'critical':
                return [30, 60, 120, 180]; // Every 30 min, 1h, 2h, 3h
            case 'high':
                return [60, 180, 360]; // Every 1h, 3h, 6h
            case 'medium':
                return [180, 480]; // Every 3h, 8h
            case 'low':
            default:
                return [480]; // Every 8h
        }
    }
    
    private function scheduleRiskCheckpoint($transportId, $checkTime, $riskAssessment) {
        try {
            $sql = "INSERT INTO perishability_checkpoints (
                        transport_id, checkpoint_time, risk_level, risk_score, created_at
                    ) VALUES (
                        :transport_id, :checkpoint_time, :risk_level, :risk_score, NOW()
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':transport_id' => $transportId,
                ':checkpoint_time' => $checkTime,
                ':risk_level' => $riskAssessment['risk_level'],
                ':risk_score' => $riskAssessment['risk_score']
            ]);
            
        } catch (Exception $e) {
            error_log("Schedule risk checkpoint error: " . $e->getMessage());
        }
    }
}
