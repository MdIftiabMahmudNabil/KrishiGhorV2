<?php
/**
 * Delivery Tracking Service
 * Real-time GPS tracking, status updates, and delivery analytics
 */

require_once __DIR__ . '/../config/database.php';

class DeliveryTrackingService {
    private $db;
    
    // Tracking update thresholds
    private $updateThresholds = [
        'min_distance_meters' => 100,    // Minimum distance to record new position
        'min_time_seconds' => 30,        // Minimum time between updates
        'max_time_seconds' => 300,       // Maximum time without update before alert
        'geofence_radius_meters' => 200  // Geofence radius for pickup/delivery locations
    ];
    
    // Status progression rules
    private $statusProgression = [
        'requested' => ['assigned', 'cancelled'],
        'assigned' => ['pickup_pending', 'cancelled'],
        'pickup_pending' => ['picked_up', 'delayed', 'cancelled'],
        'picked_up' => ['in_transit', 'delayed'],
        'in_transit' => ['delivered', 'delayed', 'failed'],
        'delayed' => ['in_transit', 'delivered', 'failed'],
        'delivered' => [], // Terminal state
        'failed' => ['pickup_pending'], // Can retry
        'cancelled' => [] // Terminal state
    ];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Initialize tracking for a new transport
     */
    public function initializeTracking($transportId, $etaPrediction) {
        try {
            // Create initial tracking record
            $sql = "INSERT INTO transport_tracking (
                        transport_id, status, current_location, speed, 
                        bearing, accuracy, battery_level, estimated_arrival,
                        created_at, updated_at
                    ) VALUES (
                        :transport_id, 'assigned', NULL, 0,
                        NULL, NULL, 100, :estimated_arrival,
                        NOW(), NOW()
                    ) RETURNING id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':transport_id' => $transportId,
                ':estimated_arrival' => $etaPrediction['estimated_arrival'] ?? null
            ]);
            
            $trackingId = $stmt->fetch()['id'];
            
            // Initialize geofences for pickup and delivery locations
            $this->setupGeofences($transportId);
            
            // Create initial analytics record
            $this->initializeAnalytics($transportId);
            
            return [
                'success' => true,
                'tracking_id' => $trackingId,
                'status' => 'initialized'
            ];
            
        } catch (Exception $e) {
            error_log("Initialize tracking error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update vehicle location and status
     */
    public function updateLocation($transportId, $locationData) {
        try {
            // Validate location data
            if (!$this->validateLocationData($locationData)) {
                throw new Exception("Invalid location data");
            }
            
            // Get last known location
            $lastLocation = $this->getLastKnownLocation($transportId);
            
            // Check if update is significant enough
            if ($lastLocation && !$this->shouldUpdateLocation($lastLocation, $locationData)) {
                return [
                    'success' => true,
                    'action' => 'skipped',
                    'reason' => 'Insignificant change'
                ];
            }
            
            // Calculate derived metrics
            $derivedMetrics = $this->calculateDerivedMetrics($lastLocation, $locationData);
            
            // Insert new tracking record
            $trackingRecordId = $this->insertTrackingRecord($transportId, $locationData, $derivedMetrics);
            
            // Check geofences
            $geofenceEvents = $this->checkGeofences($transportId, $locationData);
            
            // Detect potential issues
            $issueDetection = $this->detectPotentialIssues($transportId, $locationData, $derivedMetrics);
            
            // Update transport status if needed
            $statusUpdate = $this->updateTransportStatus($transportId, $geofenceEvents, $issueDetection);
            
            // Trigger notifications if necessary
            $notifications = $this->triggerNotifications($transportId, $geofenceEvents, $issueDetection);
            
            return [
                'success' => true,
                'tracking_record_id' => $trackingRecordId,
                'geofence_events' => $geofenceEvents,
                'issues_detected' => $issueDetection,
                'status_updated' => $statusUpdate,
                'notifications_sent' => $notifications,
                'location_updated_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Update location error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get real-time tracking data
     */
    public function getTrackingData($transportId) {
        try {
            // Get current transport status
            $transport = $this->getTransportDetails($transportId);
            
            // Get latest tracking record
            $latestTracking = $this->getLatestTrackingRecord($transportId);
            
            // Get tracking history (last 24 hours)
            $trackingHistory = $this->getTrackingHistory($transportId, 24);
            
            // Calculate journey statistics
            $journeyStats = $this->calculateJourneyStatistics($trackingHistory);
            
            // Get geofence status
            $geofenceStatus = $this->getGeofenceStatus($transportId);
            
            // Get real-time metrics
            $realTimeMetrics = $this->getRealTimeMetrics($transportId, $latestTracking);
            
            // Check for alerts
            $activeAlerts = $this->getActiveAlerts($transportId);
            
            return [
                'transport_id' => $transportId,
                'current_status' => $transport['status'] ?? 'unknown',
                'current_location' => $latestTracking ? [
                    'latitude' => $latestTracking['latitude'],
                    'longitude' => $latestTracking['longitude'],
                    'accuracy' => $latestTracking['accuracy'],
                    'updated_at' => $latestTracking['updated_at']
                ] : null,
                'current_speed' => $latestTracking['speed'] ?? 0,
                'bearing' => $latestTracking['bearing'] ?? null,
                'journey_statistics' => $journeyStats,
                'geofence_status' => $geofenceStatus,
                'real_time_metrics' => $realTimeMetrics,
                'active_alerts' => $activeAlerts,
                'tracking_quality' => $this->assessTrackingQuality($trackingHistory),
                'last_updated' => $latestTracking['updated_at'] ?? null
            ];
            
        } catch (Exception $e) {
            error_log("Get tracking data error: " . $e->getMessage());
            return [
                'transport_id' => $transportId,
                'error' => 'Failed to retrieve tracking data'
            ];
        }
    }
    
    /**
     * Complete delivery and finalize tracking
     */
    public function completeDelivery($transportId, $deliveryData) {
        try {
            // Update final delivery status
            $this->updateFinalStatus($transportId, 'delivered', $deliveryData);
            
            // Calculate final analytics
            $finalAnalytics = $this->calculateFinalAnalytics($transportId);
            
            // Generate delivery report
            $deliveryReport = $this->generateDeliveryReport($transportId, $finalAnalytics);
            
            // Archive tracking data
            $this->archiveTrackingData($transportId);
            
            return [
                'success' => true,
                'delivery_completed_at' => date('Y-m-d H:i:s'),
                'final_analytics' => $finalAnalytics,
                'delivery_report' => $deliveryReport
            ];
            
        } catch (Exception $e) {
            error_log("Complete delivery error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get performance metrics for analytics
     */
    public function getPerformanceMetrics($filters = []) {
        try {
            $whereClause = "WHERE t.status = 'delivered'";
            $params = [];
            
            if (!empty($filters['farmer_id'])) {
                $whereClause .= " AND o.farmer_id = :farmer_id";
                $params[':farmer_id'] = $filters['farmer_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $whereClause .= " AND t.created_at >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereClause .= " AND t.created_at <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }
            
            $sql = "SELECT 
                        COUNT(*) as total_deliveries,
                        AVG(ta.actual_duration_hours) as avg_delivery_time,
                        AVG(ta.route_efficiency) as avg_route_efficiency,
                        AVG(ta.eta_accuracy) as avg_eta_accuracy,
                        COUNT(CASE WHEN ta.delivery_status = 'on_time' THEN 1 END) as on_time_deliveries,
                        COUNT(CASE WHEN ta.delivery_status = 'delayed' THEN 1 END) as delayed_deliveries,
                        AVG(ta.customer_satisfaction_score) as avg_customer_satisfaction,
                        AVG(ta.total_distance_km) as avg_distance,
                        COUNT(CASE WHEN ta.issues_count > 0 THEN 1 END) as deliveries_with_issues
                    FROM transport t
                    JOIN orders o ON t.order_id = o.id
                    LEFT JOIN transport_analytics ta ON t.id = ta.transport_id
                    {$whereClause}";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $metrics = $stmt->fetch();
            
            // Calculate additional KPIs
            $metrics['on_time_percentage'] = $metrics['total_deliveries'] > 0 ? 
                ($metrics['on_time_deliveries'] / $metrics['total_deliveries']) * 100 : 0;
            
            $metrics['issue_rate'] = $metrics['total_deliveries'] > 0 ? 
                ($metrics['deliveries_with_issues'] / $metrics['total_deliveries']) * 100 : 0;
            
            return $metrics;
            
        } catch (Exception $e) {
            error_log("Get performance metrics error: " . $e->getMessage());
            return [];
        }
    }
    
    // Private helper methods
    
    private function validateLocationData($locationData) {
        $required = ['latitude', 'longitude', 'timestamp'];
        
        foreach ($required as $field) {
            if (!isset($locationData[$field])) {
                return false;
            }
        }
        
        // Validate coordinate ranges
        $lat = floatval($locationData['latitude']);
        $lng = floatval($locationData['longitude']);
        
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return false;
        }
        
        return true;
    }
    
    private function getLastKnownLocation($transportId) {
        try {
            $sql = "SELECT * FROM transport_tracking 
                    WHERE transport_id = :transport_id 
                    ORDER BY created_at DESC LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':transport_id' => $transportId]);
            
            return $stmt->fetch();
            
        } catch (Exception $e) {
            error_log("Get last known location error: " . $e->getMessage());
            return null;
        }
    }
    
    private function shouldUpdateLocation($lastLocation, $newLocation) {
        // Check time threshold
        $timeDiff = strtotime($newLocation['timestamp']) - strtotime($lastLocation['updated_at']);
        if ($timeDiff < $this->updateThresholds['min_time_seconds']) {
            return false;
        }
        
        // Check distance threshold
        $distance = $this->calculateDistance(
            $lastLocation['latitude'], $lastLocation['longitude'],
            $newLocation['latitude'], $newLocation['longitude']
        );
        
        if ($distance < $this->updateThresholds['min_distance_meters']) {
            return false;
        }
        
        return true;
    }
    
    private function calculateDerivedMetrics($lastLocation, $newLocation) {
        $metrics = [
            'speed' => 0,
            'bearing' => null,
            'distance_traveled' => 0,
            'acceleration' => 0
        ];
        
        if ($lastLocation) {
            // Calculate distance
            $distance = $this->calculateDistance(
                $lastLocation['latitude'], $lastLocation['longitude'],
                $newLocation['latitude'], $newLocation['longitude']
            );
            
            // Calculate time difference
            $timeDiff = strtotime($newLocation['timestamp']) - strtotime($lastLocation['updated_at']);
            
            if ($timeDiff > 0) {
                // Calculate speed (km/h)
                $metrics['speed'] = ($distance / 1000) / ($timeDiff / 3600);
                
                // Calculate acceleration
                $speedDiff = $metrics['speed'] - ($lastLocation['speed'] ?? 0);
                $metrics['acceleration'] = $speedDiff / ($timeDiff / 3600); // km/h²
            }
            
            // Calculate bearing
            $metrics['bearing'] = $this->calculateBearing(
                $lastLocation['latitude'], $lastLocation['longitude'],
                $newLocation['latitude'], $newLocation['longitude']
            );
            
            $metrics['distance_traveled'] = $distance;
        }
        
        return $metrics;
    }
    
    private function insertTrackingRecord($transportId, $locationData, $derivedMetrics) {
        try {
            $sql = "INSERT INTO transport_tracking (
                        transport_id, latitude, longitude, altitude, accuracy,
                        speed, bearing, battery_level, signal_strength,
                        distance_traveled, acceleration, timestamp, created_at, updated_at
                    ) VALUES (
                        :transport_id, :latitude, :longitude, :altitude, :accuracy,
                        :speed, :bearing, :battery_level, :signal_strength,
                        :distance_traveled, :acceleration, :timestamp, NOW(), NOW()
                    ) RETURNING id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':transport_id' => $transportId,
                ':latitude' => $locationData['latitude'],
                ':longitude' => $locationData['longitude'],
                ':altitude' => $locationData['altitude'] ?? null,
                ':accuracy' => $locationData['accuracy'] ?? null,
                ':speed' => $derivedMetrics['speed'],
                ':bearing' => $derivedMetrics['bearing'],
                ':battery_level' => $locationData['battery_level'] ?? null,
                ':signal_strength' => $locationData['signal_strength'] ?? null,
                ':distance_traveled' => $derivedMetrics['distance_traveled'],
                ':acceleration' => $derivedMetrics['acceleration'],
                ':timestamp' => $locationData['timestamp']
            ]);
            
            return $stmt->fetch()['id'];
            
        } catch (Exception $e) {
            error_log("Insert tracking record error: " . $e->getMessage());
            return null;
        }
    }
    
    private function checkGeofences($transportId, $locationData) {
        try {
            $events = [];
            
            // Get active geofences for this transport
            $geofences = $this->getActiveGeofences($transportId);
            
            foreach ($geofences as $geofence) {
                $distance = $this->calculateDistance(
                    $locationData['latitude'], $locationData['longitude'],
                    $geofence['center_latitude'], $geofence['center_longitude']
                );
                
                $isInside = $distance <= $geofence['radius_meters'];
                $wasInside = $geofence['current_status'] === 'inside';
                
                if ($isInside && !$wasInside) {
                    // Entered geofence
                    $events[] = [
                        'type' => 'geofence_enter',
                        'geofence_id' => $geofence['id'],
                        'geofence_name' => $geofence['name'],
                        'timestamp' => $locationData['timestamp']
                    ];
                    
                    $this->updateGeofenceStatus($geofence['id'], 'inside');
                    
                } elseif (!$isInside && $wasInside) {
                    // Exited geofence
                    $events[] = [
                        'type' => 'geofence_exit',
                        'geofence_id' => $geofence['id'],
                        'geofence_name' => $geofence['name'],
                        'timestamp' => $locationData['timestamp']
                    ];
                    
                    $this->updateGeofenceStatus($geofence['id'], 'outside');
                }
            }
            
            return $events;
            
        } catch (Exception $e) {
            error_log("Check geofences error: " . $e->getMessage());
            return [];
        }
    }
    
    private function detectPotentialIssues($transportId, $locationData, $derivedMetrics) {
        $issues = [];
        
        // Speed anomalies
        if ($derivedMetrics['speed'] > 100) { // Over 100 km/h
            $issues[] = [
                'type' => 'excessive_speed',
                'severity' => 'high',
                'description' => "Vehicle traveling at {$derivedMetrics['speed']} km/h"
            ];
        }
        
        // Stationary for too long
        if ($derivedMetrics['speed'] < 2 && $this->getStationaryDuration($transportId) > 30) {
            $issues[] = [
                'type' => 'prolonged_stop',
                'severity' => 'medium',
                'description' => 'Vehicle stationary for over 30 minutes'
            ];
        }
        
        // Poor GPS accuracy
        if (isset($locationData['accuracy']) && $locationData['accuracy'] > 100) {
            $issues[] = [
                'type' => 'poor_gps_accuracy',
                'severity' => 'low',
                'description' => "GPS accuracy: {$locationData['accuracy']}m"
            ];
        }
        
        // Low battery
        if (isset($locationData['battery_level']) && $locationData['battery_level'] < 20) {
            $issues[] = [
                'type' => 'low_battery',
                'severity' => 'medium',
                'description' => "Device battery: {$locationData['battery_level']}%"
            ];
        }
        
        return $issues;
    }
    
    private function updateTransportStatus($transportId, $geofenceEvents, $issues) {
        // Update status based on geofence events
        foreach ($geofenceEvents as $event) {
            if ($event['type'] === 'geofence_enter') {
                if ($event['geofence_name'] === 'pickup_location') {
                    $this->updateTransportStatusInDB($transportId, 'pickup_pending');
                } elseif ($event['geofence_name'] === 'delivery_location') {
                    $this->updateTransportStatusInDB($transportId, 'arrived');
                }
            }
        }
        
        // Update status based on issues
        foreach ($issues as $issue) {
            if ($issue['severity'] === 'high') {
                $this->createAlert($transportId, $issue);
            }
        }
        
        return true;
    }
    
    private function triggerNotifications($transportId, $geofenceEvents, $issues) {
        $notifications = [];
        
        foreach ($geofenceEvents as $event) {
            if ($event['type'] === 'geofence_enter' && $event['geofence_name'] === 'delivery_location') {
                $notifications[] = $this->notifyDeliveryArrival($transportId);
            }
        }
        
        foreach ($issues as $issue) {
            if ($issue['severity'] === 'high') {
                $notifications[] = $this->notifyIssue($transportId, $issue);
            }
        }
        
        return $notifications;
    }
    
    // Utility methods
    
    private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        // Haversine formula for calculating distance between two points
        $earthRadius = 6371000; // Earth radius in meters
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }
    
    private function calculateBearing($lat1, $lon1, $lat2, $lon2) {
        $dLon = deg2rad($lon2 - $lon1);
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        
        $y = sin($dLon) * cos($lat2Rad);
        $x = cos($lat1Rad) * sin($lat2Rad) - sin($lat1Rad) * cos($lat2Rad) * cos($dLon);
        
        $bearing = rad2deg(atan2($y, $x));
        return ($bearing + 360) % 360; // Normalize to 0-360
    }
    
    // Database helper methods (simplified implementations)
    
    private function setupGeofences($transportId) {
        // Implementation would create geofences for pickup and delivery locations
        error_log("Setting up geofences for transport #{$transportId}");
    }
    
    private function initializeAnalytics($transportId) {
        // Implementation would create initial analytics record
        error_log("Initializing analytics for transport #{$transportId}");
    }
    
    private function getTransportDetails($transportId) {
        // Implementation would fetch transport details
        return ['status' => 'in_transit'];
    }
    
    private function getLatestTrackingRecord($transportId) {
        // Implementation would fetch latest tracking record
        return null;
    }
    
    private function getTrackingHistory($transportId, $hours) {
        // Implementation would fetch tracking history
        return [];
    }
    
    private function calculateJourneyStatistics($trackingHistory) {
        // Implementation would calculate journey stats
        return [
            'total_distance' => 0,
            'average_speed' => 0,
            'stops_count' => 0
        ];
    }
    
    private function getGeofenceStatus($transportId) {
        // Implementation would get geofence status
        return ['current_geofence' => null];
    }
    
    private function getRealTimeMetrics($transportId, $latestTracking) {
        // Implementation would calculate real-time metrics
        return [
            'eta_updated' => null,
            'progress_percentage' => 0
        ];
    }
    
    private function getActiveAlerts($transportId) {
        // Implementation would fetch active alerts
        return [];
    }
    
    private function assessTrackingQuality($trackingHistory) {
        // Implementation would assess tracking quality
        return 'good';
    }
    
    private function updateFinalStatus($transportId, $status, $deliveryData) {
        // Implementation would update final status
        error_log("Updating final status for transport #{$transportId} to {$status}");
    }
    
    private function calculateFinalAnalytics($transportId) {
        // Implementation would calculate final analytics
        return ['delivery_time' => '2 hours', 'efficiency' => '95%'];
    }
    
    private function generateDeliveryReport($transportId, $analytics) {
        // Implementation would generate delivery report
        return ['report_id' => 'RPT_' . $transportId, 'summary' => 'Delivery completed successfully'];
    }
    
    private function archiveTrackingData($transportId) {
        // Implementation would archive tracking data
        error_log("Archiving tracking data for transport #{$transportId}");
    }
    
    private function getActiveGeofences($transportId) {
        // Implementation would fetch active geofences
        return [];
    }
    
    private function updateGeofenceStatus($geofenceId, $status) {
        // Implementation would update geofence status
        error_log("Updating geofence #{$geofenceId} status to {$status}");
    }
    
    private function getStationaryDuration($transportId) {
        // Implementation would calculate stationary duration
        return 0;
    }
    
    private function updateTransportStatusInDB($transportId, $status) {
        // Implementation would update transport status in database
        error_log("Updating transport #{$transportId} status to {$status}");
    }
    
    private function createAlert($transportId, $issue) {
        // Implementation would create alert
        error_log("Creating alert for transport #{$transportId}: {$issue['description']}");
    }
    
    private function notifyDeliveryArrival($transportId) {
        // Implementation would send arrival notification
        return "Delivery arrival notification sent for transport #{$transportId}";
    }
    
    private function notifyIssue($transportId, $issue) {
        // Implementation would send issue notification
        return "Issue notification sent for transport #{$transportId}: {$issue['description']}";
    }
}
