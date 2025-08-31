<?php
/**
 * AI Service
 * Handles ML endpoints and AI integrations
 */

require_once __DIR__ . '/../config/app.php';

class AIService {
    private $apiEndpoint;
    private $apiKey;
    
    public function __construct() {
        $this->apiEndpoint = AppConfig::get('ai.api_endpoint');
        $this->apiKey = AppConfig::get('ai.api_key');
    }
    
    public function detectTypos($text, $language = 'bn') {
        // Simulate AI typo detection for agricultural terms
        $commonTypos = [
            'bn' => [
                'ধান' => ['ধন', 'ধা', 'ধানন'],
                'গম' => ['গমম', 'গাম'],
                'আলু' => ['আল', 'আলূ'],
                'পেঁয়াজ' => ['পেয়াজ', 'পেঁয়ায'],
                'টমেটো' => ['টমাটো', 'টমেটু']
            ],
            'en' => [
                'rice' => ['rce', 'ricee'],
                'wheat' => ['whea', 'wheatt'],
                'potato' => ['potata', 'potatoe'],
                'onion' => ['onon', 'onionn'],
                'tomato' => ['tomaoto', 'tomatto']
            ]
        ];
        
        $suggestions = [];
        $words = explode(' ', $text);
        
        foreach ($words as $word) {
            $word = trim($word);
            if (isset($commonTypos[$language])) {
                foreach ($commonTypos[$language] as $correct => $typos) {
                    if (in_array($word, $typos)) {
                        $suggestions[] = [
                            'original' => $word,
                            'suggestion' => $correct,
                            'confidence' => 0.8
                        ];
                    }
                }
            }
        }
        
        return [
            'text' => $text,
            'language' => $language,
            'suggestions' => $suggestions,
            'processed_at' => date('Y-m-d H:i:s')
        ];
    }
    
    public function predictPrice($category, $location, $season, $historicalData = []) {
        // Simulate AI price prediction
        $basePrices = [
            'rice' => 50,
            'wheat' => 45,
            'potato' => 30,
            'onion' => 40,
            'tomato' => 35,
            'carrot' => 25,
            'cabbage' => 20
        ];
        
        $basePrice = $basePrices[$category] ?? 35;
        
        // Apply seasonal adjustments
        $seasonalMultipliers = [
            'spring' => 1.1,
            'summer' => 1.3,
            'monsoon' => 1.2,
            'autumn' => 0.9,
            'winter' => 0.8
        ];
        
        $seasonalPrice = $basePrice * ($seasonalMultipliers[$season] ?? 1.0);
        
        // Apply location adjustments
        $locationMultipliers = [
            'dhaka' => 1.2,
            'chittagong' => 1.1,
            'sylhet' => 1.0,
            'rajshahi' => 0.9,
            'khulna' => 0.95,
            'barisal' => 0.85,
            'rangpur' => 0.8,
            'mymensingh' => 0.9
        ];
        
        $locationKey = strtolower($location);
        $finalPrice = $seasonalPrice * ($locationMultipliers[$locationKey] ?? 1.0);
        
        // Add some randomness for volatility
        $volatility = 0.1; // 10% volatility
        $randomFactor = 1 + (rand(-100, 100) / 1000) * $volatility;
        $predictedPrice = $finalPrice * $randomFactor;
        
        // Generate forecast for next 7 days
        $forecast = [];
        for ($i = 1; $i <= 7; $i++) {
            $dayVolatility = 1 + (rand(-50, 50) / 1000);
            $forecast[] = [
                'date' => date('Y-m-d', strtotime("+{$i} days")),
                'predicted_price' => round($predictedPrice * $dayVolatility, 2),
                'confidence' => max(0.6, 0.9 - ($i * 0.05))
            ];
        }
        
        return [
            'category' => $category,
            'location' => $location,
            'season' => $season,
            'current_predicted_price' => round($predictedPrice, 2),
            'forecast' => $forecast,
            'factors' => [
                'base_price' => $basePrice,
                'seasonal_factor' => $seasonalMultipliers[$season] ?? 1.0,
                'location_factor' => $locationMultipliers[$locationKey] ?? 1.0,
                'volatility_factor' => $randomFactor
            ],
            'prediction_confidence' => 0.75,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    public function detectAnomalies($priceData) {
        if (empty($priceData)) {
            return ['anomalies' => [], 'status' => 'insufficient_data'];
        }
        
        $prices = array_column($priceData, 'price');
        $mean = array_sum($prices) / count($prices);
        $variance = array_sum(array_map(function($x) use ($mean) { return pow($x - $mean, 2); }, $prices)) / count($prices);
        $stdDev = sqrt($variance);
        
        $anomalies = [];
        foreach ($priceData as $index => $data) {
            $zScore = abs(($data['price'] - $mean) / $stdDev);
            if ($zScore > 2) { // More than 2 standard deviations
                $anomalies[] = [
                    'date' => $data['date'] ?? date('Y-m-d'),
                    'price' => $data['price'],
                    'z_score' => round($zScore, 3),
                    'deviation_percentage' => round((($data['price'] - $mean) / $mean) * 100, 2),
                    'severity' => $zScore > 3 ? 'high' : 'medium'
                ];
            }
        }
        
        return [
            'anomalies' => $anomalies,
            'statistics' => [
                'mean_price' => round($mean, 2),
                'std_deviation' => round($stdDev, 2),
                'variance' => round($variance, 2),
                'data_points' => count($priceData)
            ],
            'analysis_date' => date('Y-m-d H:i:s')
        ];
    }
    
    public function recommendCrops($location, $season, $soilType = null, $farmerExperience = 'intermediate') {
        $recommendations = [
            'spring' => [
                'dhaka' => ['rice', 'jute', 'sesame'],
                'chittagong' => ['rice', 'chili', 'turmeric'],
                'sylhet' => ['tea', 'rice', 'citrus'],
                'rajshahi' => ['mango', 'rice', 'sugarcane'],
                'default' => ['rice', 'vegetables', 'pulses']
            ],
            'summer' => [
                'dhaka' => ['jute', 'okra', 'bottle_gourd'],
                'chittagong' => ['chili', 'brinjal', 'bitter_gourd'],
                'sylhet' => ['pineapple', 'jackfruit', 'rice'],
                'rangpur' => ['tobacco', 'potato', 'maize'],
                'default' => ['summer_vegetables', 'fodder_crops']
            ],
            'monsoon' => [
                'dhaka' => ['rice', 'water_spinach', 'fish_cultivation'],
                'sylhet' => ['rice', 'fish_cultivation', 'duck_rearing'],
                'barisal' => ['rice', 'fish_cultivation', 'coconut'],
                'default' => ['rice', 'water_tolerant_crops']
            ],
            'autumn' => [
                'dhaka' => ['potato', 'cabbage', 'cauliflower'],
                'rajshahi' => ['wheat', 'mustard', 'potato'],
                'rangpur' => ['potato', 'onion', 'garlic'],
                'default' => ['winter_vegetables', 'mustard']
            ],
            'winter' => [
                'dhaka' => ['tomato', 'cabbage', 'carrot'],
                'chittagong' => ['winter_vegetables', 'strawberry'],
                'rajshahi' => ['wheat', 'mustard', 'lentils'],
                'default' => ['winter_crops', 'vegetables']
            ]
        ];
        
        $locationKey = strtolower($location);
        $crops = $recommendations[$season][$locationKey] ?? $recommendations[$season]['default'] ?? [];
        
        $detailedRecommendations = [];
        foreach ($crops as $crop) {
            $detailedRecommendations[] = [
                'crop' => $crop,
                'suitability_score' => rand(75, 95),
                'expected_yield' => $this->getExpectedYield($crop, $location, $season),
                'market_demand' => rand(70, 90),
                'profit_potential' => rand(60, 85),
                'difficulty_level' => $this->getDifficultyLevel($crop, $farmerExperience),
                'growing_tips' => $this->getGrowingTips($crop, $season)
            ];
        }
        
        // Sort by suitability score
        usort($detailedRecommendations, function($a, $b) {
            return $b['suitability_score'] - $a['suitability_score'];
        });
        
        return [
            'location' => $location,
            'season' => $season,
            'soil_type' => $soilType,
            'farmer_experience' => $farmerExperience,
            'recommendations' => array_slice($detailedRecommendations, 0, 5),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }
    
    public function analyzeMarketTrends($category, $timeframe = '30_days') {
        // Simulate market trend analysis
        $trends = [
            'price_trend' => rand(-15, 25), // percentage change
            'demand_trend' => rand(-10, 30),
            'supply_trend' => rand(-20, 20),
            'seasonal_impact' => rand(-25, 25),
            'market_volatility' => rand(5, 40)
        ];
        
        $insights = [];
        
        if ($trends['price_trend'] > 10) {
            $insights[] = 'Prices are showing strong upward trend - good time to sell';
        } elseif ($trends['price_trend'] < -10) {
            $insights[] = 'Prices are declining - consider waiting or value-addition';
        }
        
        if ($trends['demand_trend'] > 15) {
            $insights[] = 'High demand detected - market opportunity available';
        }
        
        if ($trends['market_volatility'] > 30) {
            $insights[] = 'High market volatility - consider price hedging strategies';
        }
        
        return [
            'category' => $category,
            'timeframe' => $timeframe,
            'trends' => $trends,
            'insights' => $insights,
            'recommendation' => $this->getMarketRecommendation($trends),
            'analysis_date' => date('Y-m-d H:i:s')
        ];
    }
    
    private function getExpectedYield($crop, $location, $season) {
        $baseYields = [
            'rice' => '4-6 tons/hectare',
            'wheat' => '3-4 tons/hectare',
            'potato' => '15-25 tons/hectare',
            'onion' => '12-18 tons/hectare',
            'tomato' => '20-40 tons/hectare'
        ];
        
        return $baseYields[$crop] ?? '5-10 tons/hectare';
    }
    
    private function getDifficultyLevel($crop, $experience) {
        $baseDifficulty = [
            'rice' => 'medium',
            'wheat' => 'easy',
            'potato' => 'medium',
            'tomato' => 'hard',
            'onion' => 'medium'
        ];
        
        return $baseDifficulty[$crop] ?? 'medium';
    }
    
    private function getGrowingTips($crop, $season) {
        $tips = [
            'rice' => 'Ensure proper water management and use quality seeds',
            'wheat' => 'Plant at optimal time and ensure good drainage',
            'potato' => 'Use disease-free seeds and maintain proper spacing',
            'tomato' => 'Provide support structures and regular pest monitoring',
            'onion' => 'Ensure proper curing and storage after harvest'
        ];
        
        return $tips[$crop] ?? 'Follow standard agricultural practices for best results';
    }
    
    private function getMarketRecommendation($trends) {
        if ($trends['price_trend'] > 15 && $trends['demand_trend'] > 10) {
            return 'STRONG SELL - Optimal market conditions';
        } elseif ($trends['price_trend'] < -15) {
            return 'HOLD - Wait for better prices';
        } elseif ($trends['demand_trend'] > 20) {
            return 'SELL - High demand phase';
        } else {
            return 'MONITOR - Watch market developments';
        }
    }
}
