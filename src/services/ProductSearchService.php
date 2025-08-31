<?php
/**
 * Product Search Service
 * Handles advanced product search with fuzzy matching and canonicalization
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Product.php';

class ProductSearchService {
    private $db;
    private $productModel;
    
    // Canonicalization mapping for agricultural terms
    private $canonicalMapping = [
        // Rice mappings
        'ধান' => 'rice',
        'চাল' => 'rice', 
        'rice' => 'rice',
        'paddy' => 'rice',
        'dhan' => 'rice',
        'chal' => 'rice',
        
        // Wheat mappings
        'গম' => 'wheat',
        'wheat' => 'wheat',
        'gom' => 'wheat',
        
        // Potato mappings
        'আলু' => 'potato',
        'potato' => 'potato',
        'alu' => 'potato',
        'aloo' => 'potato',
        
        // Onion mappings
        'পেঁয়াজ' => 'onion',
        'পেয়াজ' => 'onion',
        'onion' => 'onion',
        'peyaj' => 'onion',
        'piaj' => 'onion',
        
        // Tomato mappings
        'টমেটো' => 'tomato',
        'টমাটো' => 'tomato',
        'tomato' => 'tomato',
        'tometo' => 'tomato',
        
        // Common variations
        'ব্রিঞ্জাল' => 'eggplant',
        'বেগুন' => 'eggplant',
        'eggplant' => 'eggplant',
        'brinjal' => 'eggplant',
        
        'গাজর' => 'carrot',
        'carrot' => 'carrot',
        'gajor' => 'carrot',
        
        'বাঁধাকপি' => 'cabbage',
        'cabbage' => 'cabbage',
        'bandhakopi' => 'cabbage',
        
        'ফুলকপি' => 'cauliflower',
        'cauliflower' => 'cauliflower',
        'phulkopi' => 'cauliflower',
        
        'লাউ' => 'bottle_gourd',
        'bottle gourd' => 'bottle_gourd',
        'lau' => 'bottle_gourd',
        
        'করলা' => 'bitter_gourd',
        'bitter gourd' => 'bitter_gourd',
        'korola' => 'bitter_gourd',
        
        'ঢেঁড়স' => 'okra',
        'okra' => 'okra',
        'dheros' => 'okra',
        'lady finger' => 'okra',
        
        'পালং শাক' => 'spinach',
        'spinach' => 'spinach',
        'palong shak' => 'spinach',
        
        'লেটুস' => 'lettuce',
        'lettuce' => 'lettuce',
        
        'শসা' => 'cucumber',
        'cucumber' => 'cucumber',
        'shasha' => 'cucumber',
        
        'মিষ্টি কুমড়া' => 'pumpkin',
        'pumpkin' => 'pumpkin',
        'mishti kumra' => 'pumpkin',
        
        'মরিচ' => 'chili',
        'chili' => 'chili',
        'morich' => 'chili',
        'pepper' => 'chili',
        
        'আদা' => 'ginger',
        'ginger' => 'ginger',
        'ada' => 'ginger',
        
        'রসুন' => 'garlic',
        'garlic' => 'garlic',
        'roshun' => 'garlic',
        
        'ধনে পাতা' => 'coriander',
        'coriander' => 'coriander',
        'dhone pata' => 'coriander',
        'cilantro' => 'coriander'
    ];
    
    // Common typos and variations
    private $fuzzyMapping = [
        'bn' => [
            'ধানন' => 'ধান',
            'ধন' => 'ধান', 
            'ধা' => 'ধান',
            'গমম' => 'গম',
            'গাম' => 'গম',
            'আলূ' => 'আলু',
            'আল' => 'আলু',
            'পেয়াজ' => 'পেঁয়াজ',
            'পেঁয়ায' => 'পেঁয়াজ',
            'টমাটো' => 'টমেটো',
            'টমেটু' => 'টমেটো',
            'বেগূন' => 'বেগুন',
            'গাজোর' => 'গাজর',
            'বাধাকপি' => 'বাঁধাকপি',
            'ফূলকপি' => 'ফুলকপি',
            'লাও' => 'লাউ',
            'করোলা' => 'করলা',
            'ঢেড়স' => 'ঢেঁড়স'
        ],
        'en' => [
            'rce' => 'rice',
            'ricee' => 'rice',
            'padddy' => 'paddy',
            'whea' => 'wheat',
            'wheatt' => 'wheat',
            'potata' => 'potato',
            'potatoe' => 'potato',
            'onon' => 'onion',
            'onionn' => 'onion',
            'tomaoto' => 'tomato',
            'tomatto' => 'tomato',
            'eggplnt' => 'eggplant',
            'carott' => 'carrot',
            'cabbage' => 'cabbage',
            'caulifower' => 'cauliflower',
            'okraa' => 'okra',
            'spinach' => 'spinach',
            'lettuce' => 'lettuce',
            'cucmber' => 'cucumber',
            'pumpkn' => 'pumpkin',
            'chilli' => 'chili',
            'gingerr' => 'ginger',
            'garlik' => 'garlic',
            'coriader' => 'coriander'
        ]
    ];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->productModel = new Product();
    }
    
    /**
     * Advanced product search with fuzzy matching and canonicalization
     */
    public function searchProducts($query, $filters = [], $limit = 20, $offset = 0) {
        if (empty($query)) {
            return $this->productModel->search($filters, $limit, $offset);
        }
        
        // Step 1: Canonicalize and fix typos in search query
        $processedQueries = $this->processSearchQuery($query);
        
        // Step 2: Build SQL query with fuzzy matching
        $results = $this->performFuzzySearch($processedQueries, $filters, $limit, $offset);
        
        // Step 3: Rank and sort results by relevance
        return $this->rankSearchResults($results, $query);
    }
    
    /**
     * Process search query - fix typos and canonicalize terms
     */
    private function processSearchQuery($query) {
        $language = $this->detectLanguage($query);
        $terms = $this->tokenizeQuery($query);
        $processedTerms = [];
        
        foreach ($terms as $term) {
            $term = trim(strtolower($term));
            if (empty($term)) continue;
            
            // Step 1: Fix common typos
            $correctedTerm = $this->fixTypos($term, $language);
            
            // Step 2: Canonicalize term
            $canonicalTerm = $this->canonicalizeTerm($correctedTerm);
            
            // Add both original and processed versions
            $processedTerms[] = [
                'original' => $term,
                'corrected' => $correctedTerm,
                'canonical' => $canonicalTerm,
                'language' => $language
            ];
        }
        
        return $processedTerms;
    }
    
    /**
     * Detect language (Bengali vs English)
     */
    private function detectLanguage($text) {
        // Simple language detection based on Unicode ranges
        $bengaliPattern = '/[\x{0980}-\x{09FF}]/u';
        if (preg_match($bengaliPattern, $text)) {
            return 'bn';
        }
        return 'en';
    }
    
    /**
     * Tokenize search query into individual terms
     */
    private function tokenizeQuery($query) {
        // Split on whitespace, commas, and common separators
        return preg_split('/[\s,\-_]+/', $query, -1, PREG_SPLIT_NO_EMPTY);
    }
    
    /**
     * Fix common typos using fuzzy mapping
     */
    private function fixTypos($term, $language) {
        if (isset($this->fuzzyMapping[$language][$term])) {
            return $this->fuzzyMapping[$language][$term];
        }
        
        // Levenshtein distance for close matches
        $minDistance = 3;
        $bestMatch = $term;
        
        foreach ($this->fuzzyMapping[$language] as $typo => $correct) {
            $distance = levenshtein($term, $typo);
            if ($distance <= 2 && $distance < $minDistance) {
                $minDistance = $distance;
                $bestMatch = $correct;
            }
        }
        
        return $bestMatch;
    }
    
    /**
     * Canonicalize term to standard form
     */
    private function canonicalizeTerm($term) {
        if (isset($this->canonicalMapping[$term])) {
            return $this->canonicalMapping[$term];
        }
        
        // Try partial matching for compound terms
        foreach ($this->canonicalMapping as $alias => $canonical) {
            if (strpos($term, $alias) !== false || strpos($alias, $term) !== false) {
                return $canonical;
            }
        }
        
        return $term;
    }
    
    /**
     * Perform fuzzy search in database
     */
    private function performFuzzySearch($processedQueries, $filters, $limit, $offset) {
        $searchConditions = [];
        $params = [];
        $paramIndex = 0;
        
        foreach ($processedQueries as $queryData) {
            $searchTerms = [
                $queryData['original'],
                $queryData['corrected'], 
                $queryData['canonical']
            ];
            
            $termConditions = [];
            foreach ($searchTerms as $term) {
                if (empty($term)) continue;
                
                $paramName = ':search_' . $paramIndex++;
                $params[$paramName] = '%' . $term . '%';
                
                $termConditions[] = "(
                    p.name ILIKE $paramName OR 
                    p.name_bn ILIKE $paramName OR 
                    p.category ILIKE $paramName OR
                    p.variety ILIKE $paramName OR
                    p.description ILIKE $paramName OR
                    p.description_bn ILIKE $paramName
                )";
            }
            
            if (!empty($termConditions)) {
                $searchConditions[] = '(' . implode(' OR ', $termConditions) . ')';
            }
        }
        
        // Base conditions
        $conditions = ["p.status != 'deleted'"];
        if (!empty($searchConditions)) {
            $conditions[] = '(' . implode(' AND ', $searchConditions) . ')';
        }
        
        // Add other filters
        if (!empty($filters['category'])) {
            $conditions[] = "p.category = :category";
            $params[':category'] = $filters['category'];
        }
        
        if (!empty($filters['location'])) {
            $conditions[] = "p.location ILIKE :location";
            $params[':location'] = '%' . $filters['location'] . '%';
        }
        
        if (!empty($filters['min_price'])) {
            $conditions[] = "p.price_per_unit >= :min_price";
            $params[':min_price'] = $filters['min_price'];
        }
        
        if (!empty($filters['max_price'])) {
            $conditions[] = "p.price_per_unit <= :max_price";
            $params[':max_price'] = $filters['max_price'];
        }
        
        if (!empty($filters['organic_only'])) {
            $conditions[] = "p.organic_certified = true";
        }
        
        if (!empty($filters['available_only'])) {
            $conditions[] = "p.status = 'available'";
        }
        
        if (!empty($filters['region'])) {
            $conditions[] = "u.region = :region";
            $params[':region'] = $filters['region'];
        }
        
        $sql = "SELECT p.*, u.first_name, u.last_name, u.phone, u.region as farmer_region,
                       ts_rank(to_tsvector('english', p.name || ' ' || COALESCE(p.description, '')), plainto_tsquery('english', :rank_query)) as rank_score
                FROM products p
                LEFT JOIN users u ON p.farmer_id = u.id
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY rank_score DESC, p.created_at DESC
                LIMIT :limit OFFSET :offset";
        
        // Add ranking query parameter
        $rankTerms = array_column($processedQueries, 'canonical');
        $params[':rank_query'] = implode(' ', array_filter($rankTerms));
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        // Decode images for each product
        foreach ($products as &$product) {
            if ($product['images']) {
                $product['images'] = json_decode($product['images'], true);
            }
        }
        
        return $products;
    }
    
    /**
     * Rank search results by relevance
     */
    private function rankSearchResults($results, $originalQuery) {
        foreach ($results as &$result) {
            $relevanceScore = 0;
            
            // Exact match in name (highest priority)
            if (stripos($result['name'], $originalQuery) !== false) {
                $relevanceScore += 100;
            }
            
            // Exact match in Bengali name
            if (stripos($result['name_bn'], $originalQuery) !== false) {
                $relevanceScore += 90;
            }
            
            // Category match
            if (stripos($result['category'], $originalQuery) !== false) {
                $relevanceScore += 50;
            }
            
            // Description match
            if (stripos($result['description'], $originalQuery) !== false) {
                $relevanceScore += 30;
            }
            
            // Available products get bonus
            if ($result['status'] === 'available') {
                $relevanceScore += 20;
            }
            
            // Organic products get small bonus
            if ($result['organic_certified']) {
                $relevanceScore += 10;
            }
            
            $result['relevance_score'] = $relevanceScore;
        }
        
        // Sort by relevance score and then by database rank
        usort($results, function($a, $b) {
            if ($a['relevance_score'] !== $b['relevance_score']) {
                return $b['relevance_score'] - $a['relevance_score'];
            }
            return $b['rank_score'] <=> $a['rank_score'];
        });
        
        return $results;
    }
    
    /**
     * Get search suggestions for autocomplete
     */
    public function getSearchSuggestions($query, $limit = 10) {
        $processedQueries = $this->processSearchQuery($query);
        $suggestions = [];
        
        foreach ($processedQueries as $queryData) {
            // Add corrected version if different from original
            if ($queryData['corrected'] !== $queryData['original']) {
                $suggestions[] = [
                    'text' => $queryData['corrected'],
                    'type' => 'typo_correction',
                    'confidence' => 0.8
                ];
            }
            
            // Add canonical form if different
            if ($queryData['canonical'] !== $queryData['corrected']) {
                $suggestions[] = [
                    'text' => $queryData['canonical'],
                    'type' => 'canonical_form',
                    'confidence' => 0.9
                ];
            }
        }
        
        // Get popular search terms from database
        $sql = "SELECT DISTINCT category, COUNT(*) as count
                FROM products 
                WHERE status = 'available' AND category ILIKE :query
                GROUP BY category
                ORDER BY count DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':query' => '%' . $query . '%',
            ':limit' => $limit - count($suggestions)
        ]);
        
        $categories = $stmt->fetchAll();
        foreach ($categories as $category) {
            $suggestions[] = [
                'text' => $category['category'],
                'type' => 'category',
                'confidence' => 0.7,
                'count' => $category['count']
            ];
        }
        
        return array_slice($suggestions, 0, $limit);
    }
}
