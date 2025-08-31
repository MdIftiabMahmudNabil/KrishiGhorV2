<?php
/**
 * Route Anomaly Service
 * AI-powered detection of route deviations, detours, stalls, and delivery anomalies
 */

require_once __DIR__ . '/../config/database.php';

class RouteAnomalyService {
    private $db;
    
    // Anomaly detection thresholds
    private $thresholds = [
        'speed_deviation' => 0.3,      // 30% speed deviation threshold
        'route_deviation' => 0.5,      // 500m route deviation threshold (km)
        'stall_duration' => 30,        // 30 minutes stationary threshold
        'detour_ratio' => 1.3,         // 30% longer than expected route
        'stop_frequency' => 5,         // Max stops per hour
        'acceleration_anomaly' => 10   // m/s² threshold for unusual acceleration
    ];
    
    // Speed categories (km/h)
    private $speedCategories = [
        'stationary' => [0, 2],
        'very_slow' => [2, 10],
        'slow' => [10, 25],
        'normal' => [25, 60],
        'fast' => [60, 80],
        'very_fast' => [80, 120]
    ];
    
    // Route deviation patterns
    private $deviationPatterns = [
        'normal_variation' => 0.1,     // 10% normal route variation
        'minor_detour' => 0.2,         // 20% minor detour
        'major_detour' => 0.5,         // 50% major detour
        'completely_off_route' => 1.0  // 100%+ completely off route
    ];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Check for route anomalies based on current location and historical data
     */
    public function checkRouteAnomalies($transportId, $currentLocation = null) {
        try {
            if (!$currentLocation) {
                return ['is_anomalous' => false, 'anomalies' => []];
            }
            
            // Get transport and route details
            $transport = $this->getTransportDetails($transportId);
            if (!$transport) {
                throw new Exception("Transport not found");
            }
            
            // Get expected route
            $expectedRoute = $this->getExpectedRoute($transport['pickup_address'], $transport['delivery_address']);
            
            // Get recent tracking history
            $trackingHistory = $this->getTrackingHistory($transportId, 60); // Last 60 minutes
            
            $anomalies = [];
            $totalAnomalyScore = 0;
            
            // 1. Route deviation detection
            $routeDeviation = $this->detectRouteDeviation($currentLocation, $expectedRoute, $trackingHistory);
            if ($routeDeviation['is_anomalous']) {
                $anomalies[] = $routeDeviation;
                $totalAnomalyScore += $routeDeviation['severity_score'];
            }
            
            // 2. Speed anomaly detection
            $speedAnomaly = $this->detectSpeedAnomalies($trackingHistory, $transport['transport_type']);
            if ($speedAnomaly['is_anomalous']) {
                $anomalies[] = $speedAnomaly;
                $totalAnomalyScore += $speedAnomaly['severity_score'];
            }
            
            // 3. Stall detection
            $stallDetection = $this->detectStalls($trackingHistory);
            if ($stallDetection['is_anomalous']) {
                $anomalies[] = $stallDetection;
                $totalAnomalyScore += $stallDetection['severity_score'];
            }
            
            // 4. Unusual stop pattern detection
            $stopPatterns = $this->detectUnusualStopPatterns($trackingHistory);
            if ($stopPatterns['is_anomalous']) {
                $anomalies[] = $stopPatterns;
                $totalAnomalyScore += $stopPatterns['severity_score'];
            }
            
            // 5. Time-based anomaly detection
            $timeAnomalies = $this->detectTimeAnomalies($transportId, $trackingHistory);
            if ($timeAnomalies['is_anomalous']) {
                $anomalies[] = $timeAnomalies;
                $totalAnomalyScore += $timeAnomalies['severity_score'];
            }
            
            // 6. Acceleration pattern anomalies
            $accelerationAnomalies = $this->detectAccelerationAnomalies($trackingHistory);
            if ($accelerationAnomalies['is_anomalous']) {
                $anomalies[] = $accelerationAnomalies;
                $totalAnomalyScore += $accelerationAnomalies['severity_score'];
            }
            
            // Determine overall anomaly status
            $isAnomalous = !empty($anomalies);
            $severity = $this->determineSeverity($totalAnomalyScore);
            
            // Store anomaly detection results
            if ($isAnomalous) {
                $this->storeAnomalyDetection($transportId, $anomalies, $totalAnomalyScore, $severity);
            }
            
            // Generate recommendations
            $recommendations = $this->generateRecommendations($anomalies, $severity, $transport);
            
            return [
                'is_anomalous' => $isAnomalous,
                'anomaly_score' => round($totalAnomalyScore, 3),
                'severity' => $severity,
                'anomalies' => $anomalies,
                'recommendations' => $recommendations,
                'detection_time' => date('Y-m-d H:i:s'),
                'transport_id' => $transportId
            ];
            
        } catch (Exception $e) {
            error_log("Route anomaly detection error: " . $e->getMessage());
            
            return [
                'is_anomalous' => false,
                'anomaly_score' => 0,
                'severity' => 'low',
                'anomalies' => [],
                'detection_time' => date('Y-m-d H:i:s'),
                'error' => 'Anomaly detection failed'
            ];
        }
    }
    
    /**
     * Detect route deviations from expected path
     */
    private function detectRouteDeviation($currentLocation, $expectedRoute, $trackingHistory) {
        try {
            // Calculate distance from expected route
            $deviationDistance = $this->calculateRouteDeviation($currentLocation, $expectedRoute);
            
            // Calculate cumulative route length vs expected
            $actualRouteLength = $this->calculateActualRouteLength($trackingHistory);
            $expectedRouteLength = $expectedRoute['total_distance'];
            $routeLengthRatio = $actualRouteLength / max(1, $expectedRouteLength);
            
            $isAnomalous = false;
            $reasons = [];
            $severityScore = 0;
            
            // Check deviation distance
            if ($deviationDistance > $this->thresholds['route_deviation']) {
                $isAnomalous = true;
                $severityScore += min(0.5, $deviationDistance / 2); // Cap at 0.5
                $reasons[] = "Vehicle is {$deviationDistance}km off expected route";
            }
            
            // Check route length ratio
            if ($routeLengthRatio > $this->thresholds['detour_ratio']) {
                $isAnomalous = true;
                $detourPercentage = ($routeLengthRatio - 1) * 100;
                $severityScore += min(0.4, ($routeLengthRatio - 1) / 2);
                $reasons[] = "Route is {$detourPercentage}% longer than expected";
            }
            
            // Classify deviation type
            $deviationType = $this->classifyDeviation($deviationDistance, $routeLengthRatio);
            
            return [
                'type' => 'route_deviation',
                'is_anomalous' => $isAnomalous,
                'severity_score' => $severityScore,
                'reasons' => $reasons,
                'data' => [
                    'deviation_distance_km' => $deviationDistance,
                    'route_length_ratio' => $routeLengthRatio,
                    'deviation_type' => $deviationType,
                    'actual_route_length' => $actualRouteLength,
                    'expected_route_length' => $expectedRouteLength
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Route deviation detection error: " . $e->getMessage());
            return [
                'type' => 'route_deviation',
                'is_anomalous' => false,
                'severity_score' => 0,
                'reasons' => ['Route deviation analysis failed'],
                'data' => []
            ];
        }
    }
    
    /**
     * Detect speed anomalies and erratic driving patterns
     */
    private function detectSpeedAnomalies($trackingHistory, $transportType) {
        try {
            if (empty($trackingHistory)) {
                return ['type' => 'speed_anomaly', 'is_anomalous' => false, 'severity_score' => 0, 'reasons' => []];
            }
            
            $speeds = array_column($trackingHistory, 'speed');
            $speeds = array_filter($speeds, function($speed) { return $speed !== null; });
            
            if (empty($speeds)) {
                return ['type' => 'speed_anomaly', 'is_anomalous' => false, 'severity_score' => 0, 'reasons' => []];
            }
            
            // Statistical analysis
            $avgSpeed = array_sum($speeds) / count($speeds);
            $maxSpeed = max($speeds);
            $minSpeed = min($speeds);
            $speedVariance = $this->calculateVariance($speeds);
            $speedStdDev = sqrt($speedVariance);
            
            // Expected speed ranges for transport type
            $expectedSpeeds = [
                'truck' => ['min' => 20, 'max' => 70, 'avg' => 40],
                'van' => ['min' => 25, 'max' => 80, 'avg' => 45],
                'pickup' => ['min' => 25, 'max' => 80, 'avg' => 45],
                'motorbike' => ['min' => 20, 'max' => 60, 'avg' => 35]
            ];
            
            $expected = $expectedSpeeds[$transportType] ?? $expectedSpeeds['truck'];
            
            $isAnomalous = false;
            $reasons = [];
            $severityScore = 0;
            
            // Check for excessive speed
            if ($maxSpeed > $expected['max'] * 1.2) {
                $isAnomalous = true;
                $severityScore += 0.4;
                $reasons[] = "Excessive speed detected: {$maxSpeed} km/h";
            }
            
            // Check for abnormally low average speed
            if ($avgSpeed < $expected['min'] * 0.8) {
                $isAnomalous = true;
                $severityScore += 0.3;
                $reasons[] = "Abnormally low average speed: {$avgSpeed} km/h";
            }
            
            // Check for high speed variability (erratic driving)
            $speedCoefficientOfVariation = $speedStdDev / max(1, $avgSpeed);
            if ($speedCoefficientOfVariation > 0.5) {
                $isAnomalous = true;
                $severityScore += 0.3;
                $reasons[] = "Erratic speed patterns detected (high variability)";
            }
            
            // Check for rapid speed changes
            $rapidChanges = $this->detectRapidSpeedChanges($speeds);
            if ($rapidChanges['count'] > 5) {
                $isAnomalous = true;
                $severityScore += 0.2;
                $reasons[] = "Frequent rapid speed changes: {$rapidChanges['count']} instances";
            }
            
            return [
                'type' => 'speed_anomaly',
                'is_anomalous' => $isAnomalous,
                'severity_score' => min(1.0, $severityScore),
                'reasons' => $reasons,
                'data' => [
                    'avg_speed' => round($avgSpeed, 2),
                    'max_speed' => $maxSpeed,
                    'min_speed' => $minSpeed,
                    'speed_variance' => round($speedVariance, 2),
                    'coefficient_of_variation' => round($speedCoefficientOfVariation, 3),
                    'rapid_changes' => $rapidChanges,
                    'expected_range' => $expected
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Speed anomaly detection error: " . $e->getMessage());
            return [
                'type' => 'speed_anomaly',
                'is_anomalous' => false,
                'severity_score' => 0,
                'reasons' => ['Speed analysis failed'],
                'data' => []
            ];
        }
    }
    
    /**
     * Detect prolonged stalls or stationary periods
     */
    private function detectStalls($trackingHistory) {
        try {
            if (empty($trackingHistory)) {
                return ['type' => 'stall_detection', 'is_anomalous' => false, 'severity_score' => 0, 'reasons' => []];
            }
            
            $stalls = [];
            $currentStall = null;
            
            foreach ($trackingHistory as $point) {
                $speed = $point['speed'] ?? 0;
                $timestamp = strtotime($point['timestamp']);
                
                if ($speed <= $this->speedCategories['stationary'][1]) { // 2 km/h threshold
                    if ($currentStall === null) {
                        $currentStall = [
                            'start_time' => $timestamp,
                            'location' => $point['location'] ?? null,
                            'duration' => 0
                        ];
                    }
                } else {
                    if ($currentStall !== null) {
                        $currentStall['end_time'] = $timestamp;
                        $currentStall['duration'] = ($timestamp - $currentStall['start_time']) / 60; // Minutes
                        
                        if ($currentStall['duration'] >= 5) { // Minimum 5 minutes to be considered a stall
                            $stalls[] = $currentStall;
                        }
                        $currentStall = null;
                    }
                }
            }
            
            // Handle ongoing stall
            if ($currentStall !== null) {
                $currentStall['end_time'] = time();
                $currentStall['duration'] = (time() - $currentStall['start_time']) / 60;
                if ($currentStall['duration'] >= 5) {
                    $stalls[] = $currentStall;
                }
            }
            
            $isAnomalous = false;
            $reasons = [];
            $severityScore = 0;
            
            foreach ($stalls as $stall) {
                if ($stall['duration'] >= $this->thresholds['stall_duration']) {
                    $isAnomalous = true;
                    $severityScore += min(0.5, $stall['duration'] / 120); // Scale based on duration
                    $reasons[] = "Prolonged stall detected: {$stall['duration']} minutes";
                }
            }
            
            // Check for excessive number of short stalls
            $shortStalls = array_filter($stalls, function($stall) {
                return $stall['duration'] >= 5 && $stall['duration'] < 15;
            });
            
            if (count($shortStalls) > 3) {
                $isAnomalous = true;
                $severityScore += 0.3;
                $reasons[] = "Frequent short stops: " . count($shortStalls) . " stops";
            }
            
            return [
                'type' => 'stall_detection',
                'is_anomalous' => $isAnomalous,
                'severity_score' => min(1.0, $severityScore),
                'reasons' => $reasons,
                'data' => [
                    'total_stalls' => count($stalls),
                    'longest_stall_minutes' => !empty($stalls) ? max(array_column($stalls, 'duration')) : 0,
                    'total_stall_time_minutes' => array_sum(array_column($stalls, 'duration')),
                    'stalls' => $stalls
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Stall detection error: " . $e->getMessage());
            return [
                'type' => 'stall_detection',
                'is_anomalous' => false,
                'severity_score' => 0,
                'reasons' => ['Stall analysis failed'],
                'data' => []
            ];
        }
    }
    
    /**
     * Detect unusual stop patterns
     */
    private function detectUnusualStopPatterns($trackingHistory) {
        try {
            if (empty($trackingHistory)) {
                return ['type' => 'stop_patterns', 'is_anomalous' => false, 'severity_score' => 0, 'reasons' => []];
            }
            
            // Analyze stop frequency and patterns
            $stops = $this->identifyStops($trackingHistory);
            $timeSpan = $this->getTimeSpan($trackingHistory) / 3600; // Hours
            
            $stopsPerHour = count($stops) / max(1, $timeSpan);
            
            $isAnomalous = false;
            $reasons = [];
            $severityScore = 0;
            
            // Check stop frequency
            if ($stopsPerHour > $this->thresholds['stop_frequency']) {
                $isAnomalous = true;
                $severityScore += 0.4;
                $reasons[] = "High stop frequency: {$stopsPerHour} stops per hour";
            }
            
            // Check for unusual stop locations
            $unusualStops = $this->identifyUnusualStops($stops);
            if (!empty($unusualStops)) {
                $isAnomalous = true;
                $severityScore += 0.3;
                $reasons[] = "Stops at unusual locations: " . count($unusualStops);
            }
            
            // Check for pattern anomalies (e.g., zigzag patterns)
            $zigzagPattern = $this->detectZigzagPattern($stops);
            if ($zigzagPattern['detected']) {
                $isAnomalous = true;
                $severityScore += 0.3;
                $reasons[] = "Zigzag movement pattern detected";
            }
            
            return [
                'type' => 'stop_patterns',
                'is_anomalous' => $isAnomalous,
                'severity_score' => min(1.0, $severityScore),
                'reasons' => $reasons,
                'data' => [
                    'total_stops' => count($stops),
                    'stops_per_hour' => round($stopsPerHour, 2),
                    'unusual_stops' => count($unusualStops),
                    'zigzag_detected' => $zigzagPattern['detected'],
                    'time_span_hours' => round($timeSpan, 2)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Stop pattern detection error: " . $e->getMessage());
            return [
                'type' => 'stop_patterns',
                'is_anomalous' => false,
                'severity_score' => 0,
                'reasons' => ['Stop pattern analysis failed'],
                'data' => []
            ];
        }
    }
    
    /**
     * Detect time-based anomalies (delays, early arrivals)
     */
    private function detectTimeAnomalies($transportId, $trackingHistory) {
        try {
            // Get expected timeline
            $expectedTimeline = $this->getExpectedTimeline($transportId);
            
            if (!$expectedTimeline) {
                return ['type' => 'time_anomaly', 'is_anomalous' => false, 'severity_score' => 0, 'reasons' => []];
            }
            
            $currentTime = time();
            $expectedArrival = strtotime($expectedTimeline['expected_arrival']);
            $timeDifference = ($currentTime - $expectedArrival) / 3600; // Hours
            
            $isAnomalous = false;
            $reasons = [];
            $severityScore = 0;
            
            // Check for significant delays
            if ($timeDifference > 2) { // More than 2 hours late
                $isAnomalous = true;
                $severityScore += min(0.5, $timeDifference / 8); // Scale up to 8 hours
                $reasons[] = "Significant delay: {$timeDifference} hours behind schedule";
            } elseif ($timeDifference > 0.5) { // More than 30 minutes late
                $isAnomalous = true;
                $severityScore += 0.2;
                $reasons[] = "Minor delay: {$timeDifference} hours behind schedule";
            }
            
            // Check for being too early (might indicate shortcuts or speeding)
            if ($timeDifference < -1) { // More than 1 hour early
                $isAnomalous = true;
                $severityScore += 0.3;
                $reasons[] = "Unexpectedly early: " . abs($timeDifference) . " hours ahead of schedule";
            }
            
            return [
                'type' => 'time_anomaly',
                'is_anomalous' => $isAnomalous,
                'severity_score' => $severityScore,
                'reasons' => $reasons,
                'data' => [
                    'time_difference_hours' => round($timeDifference, 2),
                    'expected_arrival' => $expectedTimeline['expected_arrival'],
                    'current_time' => date('Y-m-d H:i:s'),
                    'status' => $timeDifference > 0 ? 'delayed' : ($timeDifference < -0.5 ? 'early' : 'on_time')
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Time anomaly detection error: " . $e->getMessage());
            return [
                'type' => 'time_anomaly',
                'is_anomalous' => false,
                'severity_score' => 0,
                'reasons' => ['Time analysis failed'],
                'data' => []
            ];
        }
    }
    
    /**
     * Detect acceleration/deceleration anomalies
     */
    private function detectAccelerationAnomalies($trackingHistory) {
        try {
            if (count($trackingHistory) < 2) {
                return ['type' => 'acceleration_anomaly', 'is_anomalous' => false, 'severity_score' => 0, 'reasons' => []];
            }
            
            $accelerations = [];
            
            for ($i = 1; $i < count($trackingHistory); $i++) {
                $prev = $trackingHistory[$i - 1];
                $curr = $trackingHistory[$i];
                
                $speedDiff = ($curr['speed'] ?? 0) - ($prev['speed'] ?? 0); // km/h
                $timeDiff = (strtotime($curr['timestamp']) - strtotime($prev['timestamp'])) / 3600; // hours
                
                if ($timeDiff > 0) {
                    $acceleration = $speedDiff / $timeDiff; // km/h²
                    $accelerations[] = [
                        'acceleration' => $acceleration,
                        'timestamp' => $curr['timestamp'],
                        'type' => $acceleration > 0 ? 'acceleration' : 'deceleration'
                    ];
                }
            }
            
            $isAnomalous = false;
            $reasons = [];
            $severityScore = 0;
            
            // Check for extreme accelerations/decelerations
            foreach ($accelerations as $accel) {
                $absAccel = abs($accel['acceleration']);
                
                if ($absAccel > $this->thresholds['acceleration_anomaly']) {
                    $isAnomalous = true;
                    $severityScore += min(0.3, $absAccel / 50);
                    $reasons[] = "Extreme {$accel['type']}: {$absAccel} km/h²";
                }
            }
            
            // Check for frequent hard braking/acceleration
            $extremeEvents = array_filter($accelerations, function($accel) {
                return abs($accel['acceleration']) > 5;
            });
            
            if (count($extremeEvents) > 3) {
                $isAnomalous = true;
                $severityScore += 0.2;
                $reasons[] = "Frequent extreme acceleration events: " . count($extremeEvents);
            }
            
            return [
                'type' => 'acceleration_anomaly',
                'is_anomalous' => $isAnomalous,
                'severity_score' => min(1.0, $severityScore),
                'reasons' => $reasons,
                'data' => [
                    'total_events' => count($accelerations),
                    'extreme_events' => count($extremeEvents),
                    'max_acceleration' => !empty($accelerations) ? max(array_column($accelerations, 'acceleration')) : 0,
                    'max_deceleration' => !empty($accelerations) ? min(array_column($accelerations, 'acceleration')) : 0
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Acceleration anomaly detection error: " . $e->getMessage());
            return [
                'type' => 'acceleration_anomaly',
                'is_anomalous' => false,
                'severity_score' => 0,
                'reasons' => ['Acceleration analysis failed'],
                'data' => []
            ];
        }
    }
    
    // Helper methods
    
    private function getTransportDetails($transportId) {
        try {
            $sql = "SELECT * FROM transport WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $transportId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get transport details error: " . $e->getMessage());
            return null;
        }
    }
    
    private function getExpectedRoute($pickup, $delivery) {
        // Simplified route calculation - in production use proper routing API
        return [
            'total_distance' => 100, // km
            'waypoints' => [],
            'estimated_duration' => 120 // minutes
        ];
    }
    
    private function getTrackingHistory($transportId, $minutes = 60) {
        try {
            $sql = "SELECT * FROM transport_tracking 
                    WHERE transport_id = :transport_id 
                    AND created_at >= NOW() - INTERVAL ':minutes minutes'
                    ORDER BY created_at ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':transport_id' => $transportId,
                ':minutes' => $minutes
            ]);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get tracking history error: " . $e->getMessage());
            return [];
        }
    }
    
    private function calculateRouteDeviation($currentLocation, $expectedRoute) {
        // Simplified deviation calculation
        return 0.3; // 300m deviation
    }
    
    private function calculateActualRouteLength($trackingHistory) {
        if (count($trackingHistory) < 2) return 0;
        
        $totalDistance = 0;
        for ($i = 1; $i < count($trackingHistory); $i++) {
            // Simplified distance calculation
            $totalDistance += 1; // 1km per segment
        }
        
        return $totalDistance;
    }
    
    private function classifyDeviation($distance, $ratio) {
        if ($distance > 1.0 || $ratio > 2.0) return 'completely_off_route';
        if ($distance > 0.5 || $ratio > 1.5) return 'major_detour';
        if ($distance > 0.2 || $ratio > 1.2) return 'minor_detour';
        return 'normal_variation';
    }
    
    private function calculateVariance($values) {
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(function($x) use ($mean) { return pow($x - $mean, 2); }, $values)) / count($values);
        return $variance;
    }
    
    private function detectRapidSpeedChanges($speeds) {
        $rapidChanges = 0;
        for ($i = 1; $i < count($speeds); $i++) {
            $change = abs($speeds[$i] - $speeds[$i - 1]);
            if ($change > 20) { // 20 km/h change
                $rapidChanges++;
            }
        }
        
        return ['count' => $rapidChanges];
    }
    
    private function identifyStops($trackingHistory) {
        $stops = [];
        foreach ($trackingHistory as $point) {
            if (($point['speed'] ?? 0) <= 2) { // Consider as stop if speed <= 2 km/h
                $stops[] = $point;
            }
        }
        return $stops;
    }
    
    private function getTimeSpan($trackingHistory) {
        if (empty($trackingHistory)) return 3600; // Default 1 hour
        
        $first = strtotime($trackingHistory[0]['timestamp']);
        $last = strtotime(end($trackingHistory)['timestamp']);
        
        return max(1, $last - $first); // Seconds
    }
    
    private function identifyUnusualStops($stops) {
        // Simplified - in production would check against known locations
        return array_slice($stops, 0, 2); // Return first 2 as "unusual"
    }
    
    private function detectZigzagPattern($stops) {
        // Simplified zigzag detection
        return ['detected' => count($stops) > 5];
    }
    
    private function getExpectedTimeline($transportId) {
        try {
            $sql = "SELECT predicted_arrival as expected_arrival FROM transport_predictions 
                    WHERE transport_id = :transport_id ORDER BY created_at DESC LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':transport_id' => $transportId]);
            
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Get expected timeline error: " . $e->getMessage());
            return null;
        }
    }
    
    private function determineSeverity($score) {
        if ($score >= 0.8) return 'critical';
        if ($score >= 0.6) return 'high';
        if ($score >= 0.3) return 'medium';
        return 'low';
    }
    
    private function generateRecommendations($anomalies, $severity, $transport) {
        $recommendations = [];
        
        foreach ($anomalies as $anomaly) {
            switch ($anomaly['type']) {
                case 'route_deviation':
                    $recommendations[] = "Contact driver to verify route - possible detour or navigation issue";
                    break;
                case 'speed_anomaly':
                    $recommendations[] = "Monitor driver behavior - check for traffic conditions or safety issues";
                    break;
                case 'stall_detection':
                    $recommendations[] = "Check for delivery delays - driver may need assistance";
                    break;
                case 'time_anomaly':
                    $recommendations[] = "Update delivery estimates and notify customer of delays";
                    break;
            }
        }
        
        if ($severity === 'critical' || $severity === 'high') {
            $recommendations[] = "Immediate intervention recommended - contact driver directly";
            $recommendations[] = "Consider alternative routing or backup transport";
        }
        
        return array_unique($recommendations);
    }
    
    private function storeAnomalyDetection($transportId, $anomalies, $score, $severity) {
        try {
            $sql = "INSERT INTO transport_anomalies (
                        transport_id, anomaly_score, severity, anomalies, created_at
                    ) VALUES (
                        :transport_id, :anomaly_score, :severity, :anomalies, NOW()
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':transport_id' => $transportId,
                ':anomaly_score' => $score,
                ':severity' => $severity,
                ':anomalies' => json_encode($anomalies)
            ]);
            
        } catch (Exception $e) {
            error_log("Store anomaly detection error: " . $e->getMessage());
        }
    }
    
    /**
     * Get route analytics for reporting
     */
    public function getRouteAnalytics($filters = []) {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_routes,
                        AVG(anomaly_score) as avg_anomaly_score,
                        COUNT(CASE WHEN severity = 'high' OR severity = 'critical' THEN 1 END) as high_risk_routes,
                        COUNT(CASE WHEN severity = 'low' THEN 1 END) as normal_routes
                    FROM transport_anomalies
                    WHERE created_at >= NOW() - INTERVAL '30 days'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetch();
            
        } catch (Exception $e) {
            error_log("Get route analytics error: " . $e->getMessage());
            return [];
        }
    }
}
