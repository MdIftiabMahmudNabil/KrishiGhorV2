<?php
/**
 * Forecasting Service
 * Advanced price forecasting with Prophet/MA-style models and confidence bands
 */

require_once __DIR__ . '/../config/database.php';

class ForecastingService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Generate advanced price forecast with confidence bands
     * Implements simplified version of Prophet-like algorithm
     */
    public function generateForecast($historicalData, $days = 7) {
        if (empty($historicalData) || count($historicalData) < 7) {
            return $this->getFallbackForecast($days);
        }
        
        // Prepare time series data
        $timeSeries = $this->prepareTimeSeries($historicalData);
        
        // Decompose the time series
        $decomposition = $this->decomposeTimeSeries($timeSeries);
        
        // Fit trend and seasonal components
        $trendModel = $this->fitTrendModel($decomposition['trend']);
        $seasonalModel = $this->fitSeasonalModel($decomposition['seasonal']);
        
        // Generate forecasts
        $forecasts = [];
        $lastDate = end($timeSeries)['date'];
        
        for ($i = 1; $i <= $days; $i++) {
            $forecastDate = date('Y-m-d', strtotime($lastDate . " +{$i} days"));
            
            // Calculate trend component
            $trendValue = $this->predictTrend($trendModel, $i);
            
            // Calculate seasonal component
            $seasonalValue = $this->predictSeasonal($seasonalModel, $forecastDate);
            
            // Calculate noise/uncertainty
            $uncertainty = $this->calculateUncertainty($decomposition['residuals'], $i);
            
            // Combine components
            $pointForecast = $trendValue + $seasonalValue;
            
            $forecasts[] = [
                'date' => $forecastDate,
                'predicted_price' => round($pointForecast, 2),
                'lower_bound' => round($pointForecast - (1.96 * $uncertainty), 2), // 95% CI lower
                'upper_bound' => round($pointForecast + (1.96 * $uncertainty), 2), // 95% CI upper
                'confidence' => max(0.4, 0.9 - ($i * 0.08)), // Decreasing confidence
                'trend_component' => round($trendValue, 2),
                'seasonal_component' => round($seasonalValue, 2),
                'uncertainty' => round($uncertainty, 2)
            ];
        }
        
        return [
            'forecasts' => $forecasts,
            'model_info' => [
                'type' => 'decomposition_based',
                'data_points' => count($historicalData),
                'trend_strength' => $this->calculateTrendStrength($trendModel),
                'seasonal_strength' => $this->calculateSeasonalStrength($seasonalModel),
                'model_accuracy' => $this->calculateModelAccuracy($decomposition)
            ],
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Predict next day price (optimized for "where to sell" recommendations)
     */
    public function predictNextDayPrice($category, $location) {
        // Get recent price data
        $sql = "SELECT pr.price_per_unit, pr.date 
                FROM prices pr
                LEFT JOIN products p ON pr.product_id = p.id
                WHERE p.category = :category 
                AND pr.market_location ILIKE :location
                AND pr.date >= :start_date
                ORDER BY pr.date DESC
                LIMIT 30";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':category' => $category,
            ':location' => '%' . $location . '%',
            ':start_date' => date('Y-m-d', strtotime('-30 days'))
        ]);
        
        $historicalData = $stmt->fetchAll();
        
        if (empty($historicalData)) {
            return $this->getDefaultPricePrediction($category);
        }
        
        // Generate 1-day forecast
        $forecast = $this->generateForecast($historicalData, 1);
        
        if (!empty($forecast['forecasts'])) {
            return $forecast['forecasts'][0];
        }
        
        return $this->getDefaultPricePrediction($category);
    }
    
    /**
     * Prepare time series data for analysis
     */
    private function prepareTimeSeries($historicalData) {
        $timeSeries = [];
        
        // Group by date and calculate average price per day
        $dailyPrices = [];
        foreach ($historicalData as $record) {
            $date = $record['date'];
            if (!isset($dailyPrices[$date])) {
                $dailyPrices[$date] = [];
            }
            $dailyPrices[$date][] = floatval($record['price_per_unit'] ?? $record['avg_price']);
        }
        
        // Calculate daily averages and sort by date
        foreach ($dailyPrices as $date => $prices) {
            $timeSeries[] = [
                'date' => $date,
                'price' => array_sum($prices) / count($prices),
                'day_of_week' => date('w', strtotime($date)),
                'day_of_year' => date('z', strtotime($date))
            ];
        }
        
        // Sort by date
        usort($timeSeries, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        return $timeSeries;
    }
    
    /**
     * Decompose time series into trend, seasonal, and residual components
     */
    private function decomposeTimeSeries($timeSeries) {
        $n = count($timeSeries);
        $prices = array_column($timeSeries, 'price');
        
        // Calculate trend using moving average
        $trendWindow = min(7, $n); // Weekly moving average
        $trend = [];
        
        for ($i = 0; $i < $n; $i++) {
            $windowStart = max(0, $i - floor($trendWindow / 2));
            $windowEnd = min($n - 1, $i + floor($trendWindow / 2));
            
            $windowPrices = array_slice($prices, $windowStart, $windowEnd - $windowStart + 1);
            $trend[] = array_sum($windowPrices) / count($windowPrices);
        }
        
        // Calculate seasonal component (weekly pattern)
        $seasonal = [];
        $weeklyAverage = [];
        
        // Calculate average for each day of week
        for ($dow = 0; $dow < 7; $dow++) {
            $dowPrices = [];
            foreach ($timeSeries as $point) {
                if ($point['day_of_week'] == $dow) {
                    $dowPrices[] = $point['price'];
                }
            }
            $weeklyAverage[$dow] = !empty($dowPrices) ? array_sum($dowPrices) / count($dowPrices) : 0;
        }
        
        $globalAverage = array_sum($prices) / $n;
        
        // Calculate seasonal component for each point
        foreach ($timeSeries as $point) {
            $seasonal[] = $weeklyAverage[$point['day_of_week']] - $globalAverage;
        }
        
        // Calculate residuals
        $residuals = [];
        for ($i = 0; $i < $n; $i++) {
            $residuals[] = $prices[$i] - $trend[$i] - $seasonal[$i];
        }
        
        return [
            'trend' => $trend,
            'seasonal' => $seasonal,
            'residuals' => $residuals,
            'original' => $prices
        ];
    }
    
    /**
     * Fit trend model using linear regression
     */
    private function fitTrendModel($trendData) {
        $n = count($trendData);
        if ($n < 2) return ['slope' => 0, 'intercept' => 0];
        
        $sumX = $sumY = $sumXY = $sumX2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $x = $i;
            $y = $trendData[$i];
            $sumX += $x;
            $sumY += $y;
            $sumXY += ($x * $y);
            $sumX2 += ($x * $x);
        }
        
        $denominator = ($n * $sumX2) - ($sumX * $sumX);
        
        if ($denominator != 0) {
            $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
            $intercept = ($sumY - ($slope * $sumX)) / $n;
        } else {
            $slope = 0;
            $intercept = $sumY / $n;
        }
        
        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'r_squared' => $this->calculateRSquared($trendData, $slope, $intercept)
        ];
    }
    
    /**
     * Fit seasonal model
     */
    private function fitSeasonalModel($seasonalData) {
        // Calculate seasonal pattern
        $seasonalPattern = [];
        for ($i = 0; $i < 7; $i++) {
            $dayValues = [];
            for ($j = $i; $j < count($seasonalData); $j += 7) {
                $dayValues[] = $seasonalData[$j];
            }
            $seasonalPattern[$i] = !empty($dayValues) ? array_sum($dayValues) / count($dayValues) : 0;
        }
        
        return [
            'weekly_pattern' => $seasonalPattern,
            'amplitude' => max($seasonalPattern) - min($seasonalPattern)
        ];
    }
    
    /**
     * Predict trend component
     */
    private function predictTrend($trendModel, $periodsAhead) {
        $lastIndex = $periodsAhead - 1; // 0-based indexing
        return $trendModel['slope'] * $lastIndex + $trendModel['intercept'];
    }
    
    /**
     * Predict seasonal component
     */
    private function predictSeasonal($seasonalModel, $date) {
        $dayOfWeek = date('w', strtotime($date));
        return $seasonalModel['weekly_pattern'][$dayOfWeek] ?? 0;
    }
    
    /**
     * Calculate uncertainty/confidence interval
     */
    private function calculateUncertainty($residuals, $periodsAhead) {
        if (empty($residuals)) return 1.0;
        
        // Calculate residual standard deviation
        $mean = array_sum($residuals) / count($residuals);
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $residuals)) / count($residuals);
        
        $standardError = sqrt($variance);
        
        // Increase uncertainty with forecast horizon
        $horizonMultiplier = 1 + ($periodsAhead * 0.1);
        
        return $standardError * $horizonMultiplier;
    }
    
    /**
     * Calculate R-squared for trend model
     */
    private function calculateRSquared($actual, $slope, $intercept) {
        $n = count($actual);
        $mean = array_sum($actual) / $n;
        
        $ssTotal = $ssRes = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $predicted = $slope * $i + $intercept;
            $ssRes += pow($actual[$i] - $predicted, 2);
            $ssTotal += pow($actual[$i] - $mean, 2);
        }
        
        return $ssTotal > 0 ? 1 - ($ssRes / $ssTotal) : 0;
    }
    
    /**
     * Calculate trend strength
     */
    private function calculateTrendStrength($trendModel) {
        return min(1.0, abs($trendModel['slope']) * 10); // Normalize slope
    }
    
    /**
     * Calculate seasonal strength
     */
    private function calculateSeasonalStrength($seasonalModel) {
        return min(1.0, $seasonalModel['amplitude'] / 10); // Normalize amplitude
    }
    
    /**
     * Calculate model accuracy
     */
    private function calculateModelAccuracy($decomposition) {
        $residuals = $decomposition['residuals'];
        $original = $decomposition['original'];
        
        if (empty($residuals) || empty($original)) return 0.5;
        
        $mape = 0; // Mean Absolute Percentage Error
        $count = 0;
        
        for ($i = 0; $i < count($residuals); $i++) {
            if ($original[$i] != 0) {
                $mape += abs($residuals[$i] / $original[$i]);
                $count++;
            }
        }
        
        $averageMape = $count > 0 ? $mape / $count : 1.0;
        
        // Convert MAPE to accuracy (lower MAPE = higher accuracy)
        return max(0.1, 1 - min(1.0, $averageMape));
    }
    
    /**
     * Get fallback forecast when insufficient data
     */
    private function getFallbackForecast($days) {
        $forecasts = [];
        
        for ($i = 1; $i <= $days; $i++) {
            $forecasts[] = [
                'date' => date('Y-m-d', strtotime("+{$i} days")),
                'predicted_price' => 50.0, // Default price
                'lower_bound' => 45.0,
                'upper_bound' => 55.0,
                'confidence' => 0.3, // Low confidence
                'trend_component' => 0,
                'seasonal_component' => 0,
                'uncertainty' => 5.0
            ];
        }
        
        return [
            'forecasts' => $forecasts,
            'model_info' => [
                'type' => 'fallback',
                'data_points' => 0,
                'trend_strength' => 0,
                'seasonal_strength' => 0,
                'model_accuracy' => 0.3
            ],
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get default price prediction for unknown categories
     */
    private function getDefaultPricePrediction($category) {
        $defaultPrices = [
            'rice' => 55.0,
            'wheat' => 48.0,
            'potato' => 32.0,
            'onion' => 42.0,
            'tomato' => 38.0
        ];
        
        $basePrice = $defaultPrices[$category] ?? 40.0;
        
        return [
            'date' => date('Y-m-d', strtotime('+1 day')),
            'predicted_price' => $basePrice,
            'lower_bound' => $basePrice * 0.9,
            'upper_bound' => $basePrice * 1.1,
            'confidence' => 0.4,
            'trend_component' => 0,
            'seasonal_component' => 0,
            'uncertainty' => $basePrice * 0.1
        ];
    }
}
