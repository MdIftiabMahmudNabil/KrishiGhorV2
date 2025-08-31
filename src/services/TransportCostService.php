<?php
/**
 * Transport Cost Service
 * Calculates transport costs and logistics for "where to sell" optimization
 */

require_once __DIR__ . '/../config/database.php';

class TransportCostService {
    private $db;
    
    // Transport rates per km (in BDT)
    private $transportRates = [
        'truck' => 3.5,      // Heavy truck
        'pickup' => 2.8,     // Light pickup
        'van' => 2.2,        // Small van
        'motorcycle' => 1.5,  // Motorcycle
        'rickshaw' => 1.0    // Manual rickshaw
    ];
    
    // Regional coordinates (approximate center points)
    private $regionCoordinates = [
        'dhaka' => ['lat' => 23.8103, 'lng' => 90.4125],
        'chittagong' => ['lat' => 22.3475, 'lng' => 91.8123],
        'sylhet' => ['lat' => 24.8949, 'lng' => 91.8687],
        'rajshahi' => ['lat' => 24.3636, 'lng' => 88.6241],
        'khulna' => ['lat' => 22.8456, 'lng' => 89.5403],
        'barisal' => ['lat' => 22.7010, 'lng' => 90.3535],
        'rangpur' => ['lat' => 25.7439, 'lng' => 89.2752],
        'mymensingh' => ['lat' => 24.7471, 'lng' => 90.4203],
        'comilla' => ['lat' => 23.4682, 'lng' => 91.1788],
        'cox\'s bazar' => ['lat' => 21.4272, 'lng' => 92.0058]
    ];
    
    // Fuel prices and other costs
    private $baseCosts = [
        'fuel_price_per_liter' => 114.0, // BDT per liter
        'driver_daily_wage' => 800.0,    // BDT per day
        'vehicle_maintenance_per_km' => 1.2, // BDT per km
        'toll_charges_per_100km' => 150.0,   // BDT per 100km
        'loading_unloading_fee' => 200.0     // BDT flat fee
    ];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Calculate transport cost between two locations
     */
    public function calculateCost($fromLocation, $toLocation, $quantity = 100, $transportType = 'auto') {
        try {
            // Calculate distance
            $distance = $this->calculateDistance($fromLocation, $toLocation);
            
            // Determine optimal transport type based on quantity
            if ($transportType === 'auto') {
                $transportType = $this->determineOptimalTransport($quantity, $distance);
            }
            
            // Calculate base transport cost
            $baseTransportCost = $distance * $this->transportRates[$transportType];
            
            // Calculate additional costs
            $fuelCost = $this->calculateFuelCost($distance, $transportType);
            $laborCost = $this->calculateLaborCost($distance);
            $maintenanceCost = $distance * $this->baseCosts['vehicle_maintenance_per_km'];
            $tollCharges = ($distance / 100) * $this->baseCosts['toll_charges_per_100km'];
            $loadingFee = $this->baseCosts['loading_unloading_fee'];
            
            // Apply quantity multiplier for larger loads
            $quantityMultiplier = $this->getQuantityMultiplier($quantity);
            
            $totalCost = ($baseTransportCost + $fuelCost + $laborCost + $maintenanceCost + $tollCharges + $loadingFee) * $quantityMultiplier;
            
            // Calculate estimated time
            $estimatedHours = $this->calculateTravelTime($distance, $transportType);
            
            return [
                'from_location' => $fromLocation,
                'to_location' => $toLocation,
                'distance_km' => round($distance, 1),
                'transport_type' => $transportType,
                'quantity' => $quantity,
                'cost_breakdown' => [
                    'base_transport' => round($baseTransportCost * $quantityMultiplier, 2),
                    'fuel_cost' => round($fuelCost * $quantityMultiplier, 2),
                    'labor_cost' => round($laborCost * $quantityMultiplier, 2),
                    'maintenance' => round($maintenanceCost * $quantityMultiplier, 2),
                    'toll_charges' => round($tollCharges, 2),
                    'loading_fee' => round($loadingFee, 2)
                ],
                'total_cost' => round($totalCost, 2),
                'cost_per_unit' => round($totalCost / $quantity, 2),
                'estimated_hours' => round($estimatedHours, 1),
                'quantity_multiplier' => $quantityMultiplier,
                'calculated_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            error_log("Transport cost calculation error: " . $e->getMessage());
            return $this->getDefaultCostEstimate($fromLocation, $toLocation, $quantity);
        }
    }
    
    /**
     * Calculate distance between two locations using Haversine formula
     */
    private function calculateDistance($location1, $location2) {
        $coord1 = $this->getLocationCoordinates($location1);
        $coord2 = $this->getLocationCoordinates($location2);
        
        if (!$coord1 || !$coord2) {
            // Fallback to average distance if coordinates not found
            return $this->getAverageDistanceBetweenRegions();
        }
        
        $earthRadius = 6371; // Earth radius in kilometers
        
        $lat1Rad = deg2rad($coord1['lat']);
        $lon1Rad = deg2rad($coord1['lng']);
        $lat2Rad = deg2rad($coord2['lat']);
        $lon2Rad = deg2rad($coord2['lng']);
        
        $deltaLat = $lat2Rad - $lat1Rad;
        $deltaLon = $lon2Rad - $lon1Rad;
        
        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLon / 2) * sin($deltaLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        $distance = $earthRadius * $c;
        
        // Add 20% for road routing (straight line vs actual roads)
        return $distance * 1.2;
    }
    
    /**
     * Get coordinates for a location
     */
    private function getLocationCoordinates($location) {
        $locationKey = strtolower(trim($location));
        
        // Direct match
        if (isset($this->regionCoordinates[$locationKey])) {
            return $this->regionCoordinates[$locationKey];
        }
        
        // Fuzzy match for market names
        foreach ($this->regionCoordinates as $region => $coords) {
            if (strpos($locationKey, $region) !== false || strpos($region, $locationKey) !== false) {
                return $coords;
            }
        }
        
        // Check database for custom locations
        try {
            $sql = "SELECT latitude, longitude FROM market_locations WHERE name ILIKE :location LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':location' => '%' . $location . '%']);
            $result = $stmt->fetch();
            
            if ($result) {
                return ['lat' => $result['latitude'], 'lng' => $result['longitude']];
            }
        } catch (Exception $e) {
            // Table might not exist, continue with fallback
        }
        
        return null;
    }
    
    /**
     * Determine optimal transport type based on quantity and distance
     */
    private function determineOptimalTransport($quantity, $distance) {
        // For small quantities and short distances
        if ($quantity <= 50 && $distance <= 20) {
            return 'motorcycle';
        }
        
        // For medium quantities and short distances
        if ($quantity <= 200 && $distance <= 50) {
            return 'van';
        }
        
        // For medium quantities and medium distances
        if ($quantity <= 500 && $distance <= 150) {
            return 'pickup';
        }
        
        // For large quantities or long distances
        return 'truck';
    }
    
    /**
     * Calculate fuel cost based on distance and transport type
     */
    private function calculateFuelCost($distance, $transportType) {
        $fuelEfficiency = [
            'truck' => 8,      // km per liter
            'pickup' => 12,    // km per liter
            'van' => 15,       // km per liter
            'motorcycle' => 35, // km per liter
            'rickshaw' => 0    // No fuel cost
        ];
        
        if (!isset($fuelEfficiency[$transportType]) || $fuelEfficiency[$transportType] == 0) {
            return 0;
        }
        
        $fuelNeeded = $distance / $fuelEfficiency[$transportType];
        return $fuelNeeded * $this->baseCosts['fuel_price_per_liter'];
    }
    
    /**
     * Calculate labor cost based on distance
     */
    private function calculateLaborCost($distance) {
        $hoursNeeded = $this->calculateTravelTime($distance, 'truck'); // Use truck as baseline
        $daysNeeded = ceil($hoursNeeded / 8); // 8-hour working day
        
        return $daysNeeded * $this->baseCosts['driver_daily_wage'];
    }
    
    /**
     * Calculate travel time based on distance and transport type
     */
    private function calculateTravelTime($distance, $transportType) {
        $averageSpeeds = [
            'truck' => 45,      // km/h
            'pickup' => 50,     // km/h
            'van' => 50,        // km/h
            'motorcycle' => 40, // km/h
            'rickshaw' => 15    // km/h
        ];
        
        $speed = $averageSpeeds[$transportType] ?? 45;
        $travelTime = $distance / $speed;
        
        // Add buffer time for loading/unloading and breaks
        $bufferTime = $distance > 100 ? 2 : 1; // hours
        
        return $travelTime + $bufferTime;
    }
    
    /**
     * Get quantity multiplier for larger loads
     */
    private function getQuantityMultiplier($quantity) {
        // Economy of scale for larger quantities
        if ($quantity >= 1000) {
            return 0.8; // 20% discount for large loads
        } elseif ($quantity >= 500) {
            return 0.9; // 10% discount
        } elseif ($quantity >= 200) {
            return 0.95; // 5% discount
        } elseif ($quantity <= 20) {
            return 1.3; // 30% premium for very small loads
        } elseif ($quantity <= 50) {
            return 1.2; // 20% premium for small loads
        }
        
        return 1.0; // No multiplier for standard quantities
    }
    
    /**
     * Get average distance between regions (fallback)
     */
    private function getAverageDistanceBetweenRegions() {
        return 150; // Average distance between major cities in Bangladesh
    }
    
    /**
     * Get default cost estimate when calculation fails
     */
    private function getDefaultCostEstimate($fromLocation, $toLocation, $quantity) {
        $defaultDistance = 150;
        $defaultRate = 3.0; // Average rate per km
        $defaultCost = $defaultDistance * $defaultRate * $this->getQuantityMultiplier($quantity);
        
        return [
            'from_location' => $fromLocation,
            'to_location' => $toLocation,
            'distance_km' => $defaultDistance,
            'transport_type' => 'truck',
            'quantity' => $quantity,
            'cost_breakdown' => [
                'base_transport' => $defaultCost,
                'fuel_cost' => 0,
                'labor_cost' => 0,
                'maintenance' => 0,
                'toll_charges' => 0,
                'loading_fee' => 0
            ],
            'total_cost' => round($defaultCost, 2),
            'cost_per_unit' => round($defaultCost / $quantity, 2),
            'estimated_hours' => 6.0,
            'quantity_multiplier' => $this->getQuantityMultiplier($quantity),
            'calculated_at' => date('Y-m-d H:i:s'),
            'note' => 'Default estimate - coordinates not found'
        ];
    }
    
    /**
     * Get transport options for a specific route
     */
    public function getTransportOptions($fromLocation, $toLocation, $quantity = 100) {
        $distance = $this->calculateDistance($fromLocation, $toLocation);
        $options = [];
        
        foreach ($this->transportRates as $transportType => $rate) {
            // Skip inappropriate transport types
            if (($transportType === 'rickshaw' && $distance > 30) ||
                ($transportType === 'motorcycle' && $quantity > 100)) {
                continue;
            }
            
            $cost = $this->calculateCost($fromLocation, $toLocation, $quantity, $transportType);
            $options[] = $cost;
        }
        
        // Sort by total cost
        usort($options, function($a, $b) {
            return $a['total_cost'] <=> $b['total_cost'];
        });
        
        return $options;
    }
    
    /**
     * Calculate route optimization for multiple destinations
     */
    public function optimizeRoute($startLocation, $destinations, $quantity = 100) {
        $optimizedRoute = [];
        $totalCost = 0;
        $totalDistance = 0;
        $currentLocation = $startLocation;
        
        // Simple nearest neighbor algorithm for route optimization
        $remainingDestinations = $destinations;
        
        while (!empty($remainingDestinations)) {
            $nearestDestination = null;
            $shortestDistance = PHP_FLOAT_MAX;
            $shortestDistanceIndex = -1;
            
            foreach ($remainingDestinations as $index => $destination) {
                $distance = $this->calculateDistance($currentLocation, $destination);
                if ($distance < $shortestDistance) {
                    $shortestDistance = $distance;
                    $nearestDestination = $destination;
                    $shortestDistanceIndex = $index;
                }
            }
            
            if ($nearestDestination) {
                $segmentCost = $this->calculateCost($currentLocation, $nearestDestination, $quantity);
                $optimizedRoute[] = [
                    'from' => $currentLocation,
                    'to' => $nearestDestination,
                    'cost' => $segmentCost,
                    'sequence' => count($optimizedRoute) + 1
                ];
                
                $totalCost += $segmentCost['total_cost'];
                $totalDistance += $segmentCost['distance_km'];
                $currentLocation = $nearestDestination;
                
                unset($remainingDestinations[$shortestDistanceIndex]);
                $remainingDestinations = array_values($remainingDestinations);
            }
        }
        
        return [
            'optimized_route' => $optimizedRoute,
            'total_cost' => round($totalCost, 2),
            'total_distance' => round($totalDistance, 1),
            'total_destinations' => count($destinations),
            'optimization_date' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get regional transport statistics
     */
    public function getRegionalTransportStats() {
        $stats = [];
        
        foreach ($this->regionCoordinates as $region => $coords) {
            $avgCostToOtherRegions = [];
            
            foreach ($this->regionCoordinates as $otherRegion => $otherCoords) {
                if ($region !== $otherRegion) {
                    $cost = $this->calculateCost($region, $otherRegion, 100);
                    $avgCostToOtherRegions[] = $cost['total_cost'];
                }
            }
            
            $stats[$region] = [
                'region' => ucfirst($region),
                'avg_transport_cost' => !empty($avgCostToOtherRegions) ? round(array_sum($avgCostToOtherRegions) / count($avgCostToOtherRegions), 2) : 0,
                'min_transport_cost' => !empty($avgCostToOtherRegions) ? round(min($avgCostToOtherRegions), 2) : 0,
                'max_transport_cost' => !empty($avgCostToOtherRegions) ? round(max($avgCostToOtherRegions), 2) : 0,
                'connectivity_score' => $this->calculateConnectivityScore($region)
            ];
        }
        
        return $stats;
    }
    
    /**
     * Calculate connectivity score for a region
     */
    private function calculateConnectivityScore($region) {
        // Higher score = better connectivity (lower average transport cost)
        $distances = [];
        
        foreach ($this->regionCoordinates as $otherRegion => $coords) {
            if ($region !== $otherRegion) {
                $distances[] = $this->calculateDistance($region, $otherRegion);
            }
        }
        
        $avgDistance = !empty($distances) ? array_sum($distances) / count($distances) : 200;
        
        // Convert to score (0-100, where 100 is best connectivity)
        return max(0, 100 - ($avgDistance / 5));
    }
}
