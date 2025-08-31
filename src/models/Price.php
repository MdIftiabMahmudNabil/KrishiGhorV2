<?php
/**
 * Price Model
 * Handles pricing data and market analytics
 */

require_once __DIR__ . '/../config/database.php';

class Price {
    private $db;
    private $table = 'prices';
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function create($data) {
        $sql = "INSERT INTO {$this->table} (
                    product_id, market_location, price_per_unit, date, 
                    source, quality_grade, notes, created_at, updated_at
                ) VALUES (
                    :product_id, :market_location, :price_per_unit, :date, 
                    :source, :quality_grade, :notes, NOW(), NOW()
                ) RETURNING id";
        
        $stmt = $this->db->prepare($sql);
        
        $params = [
            ':product_id' => $data['product_id'],
            ':market_location' => $data['market_location'],
            ':price_per_unit' => $data['price_per_unit'],
            ':date' => $data['date'] ?? date('Y-m-d'),
            ':source' => $data['source'] ?? 'manual',
            ':quality_grade' => $data['quality_grade'] ?? 'A',
            ':notes' => $data['notes'] ?? null,
        ];
        
        if ($stmt->execute($params)) {
            return $stmt->fetch()['id'];
        }
        
        return false;
    }
    
    public function getByProduct($productId, $limit = 30) {
        $sql = "SELECT pr.*, p.name as product_name, p.category, p.unit
                FROM {$this->table} pr
                LEFT JOIN products p ON pr.product_id = p.id
                WHERE pr.product_id = :product_id
                ORDER BY pr.date DESC, pr.created_at DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':product_id' => $productId, ':limit' => $limit]);
        
        return $stmt->fetchAll();
    }
    
    public function getByCategory($category, $startDate = null, $endDate = null, $location = null) {
        $conditions = ["p.category = :category"];
        $params = [':category' => $category];
        
        if ($startDate) {
            $conditions[] = "pr.date >= :start_date";
            $params[':start_date'] = $startDate;
        }
        
        if ($endDate) {
            $conditions[] = "pr.date <= :end_date";
            $params[':end_date'] = $endDate;
        }
        
        if ($location) {
            $conditions[] = "pr.market_location ILIKE :location";
            $params[':location'] = '%' . $location . '%';
        }
        
        $sql = "SELECT pr.*, p.name as product_name, p.variety, p.unit
                FROM {$this->table} pr
                LEFT JOIN products p ON pr.product_id = p.id
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY pr.date DESC, pr.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getMarketTrends($category, $days = 30) {
        $sql = "SELECT 
                    DATE(pr.date) as price_date,
                    pr.market_location,
                    AVG(pr.price_per_unit) as avg_price,
                    MIN(pr.price_per_unit) as min_price,
                    MAX(pr.price_per_unit) as max_price,
                    COUNT(*) as sample_count
                FROM {$this->table} pr
                LEFT JOIN products p ON pr.product_id = p.id
                WHERE p.category = :category 
                AND pr.date >= CURRENT_DATE - INTERVAL ':days days'
                GROUP BY DATE(pr.date), pr.market_location
                ORDER BY price_date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':category' => $category, ':days' => $days]);
        
        return $stmt->fetchAll();
    }
    
    public function getCurrentMarketPrices($location = null) {
        $conditions = ["pr.date = CURRENT_DATE"];
        $params = [];
        
        if ($location) {
            $conditions[] = "pr.market_location ILIKE :location";
            $params[':location'] = '%' . $location . '%';
        }
        
        $sql = "SELECT 
                    p.category,
                    p.name as product_name,
                    p.variety,
                    p.unit,
                    pr.market_location,
                    pr.price_per_unit,
                    pr.quality_grade,
                    pr.created_at
                FROM {$this->table} pr
                LEFT JOIN products p ON pr.product_id = p.id
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY p.category, p.name, pr.price_per_unit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getPriceAnalytics($category, $days = 30) {
        $sql = "SELECT 
                    p.category,
                    COUNT(DISTINCT pr.product_id) as unique_products,
                    COUNT(*) as total_records,
                    AVG(pr.price_per_unit) as avg_price,
                    STDDEV(pr.price_per_unit) as price_volatility,
                    MIN(pr.price_per_unit) as min_price,
                    MAX(pr.price_per_unit) as max_price,
                    COUNT(DISTINCT pr.market_location) as market_locations
                FROM {$this->table} pr
                LEFT JOIN products p ON pr.product_id = p.id
                WHERE p.category = :category 
                AND pr.date >= CURRENT_DATE - INTERVAL ':days days'
                GROUP BY p.category";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':category' => $category, ':days' => $days]);
        
        return $stmt->fetch();
    }
    
    public function getPriceForecast($category, $location = null, $days = 7) {
        // This would integrate with AI service for actual forecasting
        // For now, return trend-based simple forecast
        
        $conditions = ["p.category = :category"];
        $params = [':category' => $category];
        
        if ($location) {
            $conditions[] = "pr.market_location ILIKE :location";
            $params[':location'] = '%' . $location . '%';
        }
        
        $sql = "SELECT 
                    DATE(pr.date) as price_date,
                    AVG(pr.price_per_unit) as avg_price
                FROM {$this->table} pr
                LEFT JOIN products p ON pr.product_id = p.id
                WHERE " . implode(' AND ', $conditions) . "
                AND pr.date >= CURRENT_DATE - INTERVAL '30 days'
                GROUP BY DATE(pr.date)
                ORDER BY price_date DESC
                LIMIT 30";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $historicalData = $stmt->fetchAll();
        
        // Simple linear trend calculation
        $forecast = [];
        if (count($historicalData) >= 7) {
            $prices = array_column($historicalData, 'avg_price');
            $trend = $this->calculateTrend($prices);
            
            $lastPrice = $prices[0];
            for ($i = 1; $i <= $days; $i++) {
                $forecastPrice = $lastPrice + ($trend * $i);
                $forecast[] = [
                    'date' => date('Y-m-d', strtotime("+{$i} days")),
                    'predicted_price' => round($forecastPrice, 2),
                    'confidence' => max(0.3, 0.9 - ($i * 0.1)) // Decreasing confidence
                ];
            }
        }
        
        return $forecast;
    }
    
    private function calculateTrend($prices) {
        $n = count($prices);
        if ($n < 2) return 0;
        
        $sumX = $sumY = $sumXY = $sumX2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $x = $i;
            $y = $prices[$i];
            $sumX += $x;
            $sumY += $y;
            $sumXY += ($x * $y);
            $sumX2 += ($x * $x);
        }
        
        $slope = (($n * $sumXY) - ($sumX * $sumY)) / (($n * $sumX2) - ($sumX * $sumX));
        return $slope;
    }
    
    public function getLocationStats() {
        $sql = "SELECT 
                    market_location,
                    COUNT(*) as total_records,
                    COUNT(DISTINCT product_id) as unique_products,
                    AVG(price_per_unit) as avg_price,
                    MIN(date) as first_record,
                    MAX(date) as last_record
                FROM {$this->table}
                GROUP BY market_location
                ORDER BY total_records DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
