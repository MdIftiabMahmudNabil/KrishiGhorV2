<?php
/**
 * Product Model
 * Handles agricultural product data operations
 */

require_once __DIR__ . '/../config/database.php';

class Product {
    private $db;
    private $table = 'products';
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function create($data) {
        $sql = "INSERT INTO {$this->table} (
                    farmer_id, name, name_bn, category, variety, quantity, 
                    unit, price_per_unit, description, description_bn, 
                    harvest_date, expiry_date, location, quality_grade, 
                    organic_certified, images, status, created_at, updated_at
                ) VALUES (
                    :farmer_id, :name, :name_bn, :category, :variety, :quantity, 
                    :unit, :price_per_unit, :description, :description_bn, 
                    :harvest_date, :expiry_date, :location, :quality_grade, 
                    :organic_certified, :images, :status, NOW(), NOW()
                ) RETURNING id";
        
        $stmt = $this->db->prepare($sql);
        
        $params = [
            ':farmer_id' => $data['farmer_id'],
            ':name' => $data['name'],
            ':name_bn' => $data['name_bn'] ?? null,
            ':category' => $data['category'],
            ':variety' => $data['variety'] ?? null,
            ':quantity' => $data['quantity'],
            ':unit' => $data['unit'],
            ':price_per_unit' => $data['price_per_unit'],
            ':description' => $data['description'] ?? null,
            ':description_bn' => $data['description_bn'] ?? null,
            ':harvest_date' => $data['harvest_date'] ?? null,
            ':expiry_date' => $data['expiry_date'] ?? null,
            ':location' => $data['location'] ?? null,
            ':quality_grade' => $data['quality_grade'] ?? 'A',
            ':organic_certified' => $data['organic_certified'] ?? false,
            ':images' => json_encode($data['images'] ?? []),
            ':status' => $data['status'] ?? 'available',
        ];
        
        if ($stmt->execute($params)) {
            return $stmt->fetch()['id'];
        }
        
        return false;
    }
    
    public function findById($id) {
        $sql = "SELECT p.*, u.first_name, u.last_name, u.phone, u.email as farmer_email
                FROM {$this->table} p
                LEFT JOIN users u ON p.farmer_id = u.id
                WHERE p.id = :id AND p.status != 'deleted'";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        $product = $stmt->fetch();
        if ($product && $product['images']) {
            $product['images'] = json_decode($product['images'], true);
        }
        
        return $product;
    }
    
    public function getByFarmer($farmerId, $limit = null, $offset = 0) {
        $sql = "SELECT * FROM {$this->table} 
                WHERE farmer_id = :farmer_id AND status != 'deleted' 
                ORDER BY created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }
        
        $stmt = $this->db->prepare($sql);
        $params = [':farmer_id' => $farmerId];
        
        if ($limit) {
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;
        }
        
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
    
    public function search($filters = [], $limit = 20, $offset = 0) {
        $conditions = ["p.status != 'deleted'"];
        $params = [];
        
        if (!empty($filters['category'])) {
            $conditions[] = "p.category = :category";
            $params[':category'] = $filters['category'];
        }
        
        if (!empty($filters['location'])) {
            $conditions[] = "p.location ILIKE :location";
            $params[':location'] = '%' . $filters['location'] . '%';
        }
        
        if (!empty($filters['search'])) {
            $conditions[] = "(p.name ILIKE :search OR p.name_bn ILIKE :search OR p.description ILIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
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
        
        $sql = "SELECT p.*, u.first_name, u.last_name, u.phone
                FROM {$this->table} p
                LEFT JOIN users u ON p.farmer_id = u.id
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY p.created_at DESC
                LIMIT :limit OFFSET :offset";
        
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
    
    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        
        $allowedFields = [
            'name', 'name_bn', 'category', 'variety', 'quantity', 'unit',
            'price_per_unit', 'description', 'description_bn', 'harvest_date',
            'expiry_date', 'location', 'quality_grade', 'organic_certified', 'status'
        ];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            } elseif ($key === 'images') {
                $fields[] = "images = :images";
                $params[':images'] = json_encode($value);
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function getCategories() {
        $sql = "SELECT category, COUNT(*) as count 
                FROM {$this->table} 
                WHERE status = 'available' 
                GROUP BY category 
                ORDER BY count DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function getMarketStats($category = null) {
        $conditions = ["status = 'available'"];
        $params = [];
        
        if ($category) {
            $conditions[] = "category = :category";
            $params[':category'] = $category;
        }
        
        $sql = "SELECT 
                    category,
                    COUNT(*) as total_products,
                    SUM(quantity) as total_quantity,
                    AVG(price_per_unit) as avg_price,
                    MIN(price_per_unit) as min_price,
                    MAX(price_per_unit) as max_price
                FROM {$this->table} 
                WHERE " . implode(' AND ', $conditions) . "
                GROUP BY category
                ORDER BY total_products DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function softDelete($id) {
        $sql = "UPDATE {$this->table} SET status = 'deleted', updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    public function updateQuantity($id, $newQuantity) {
        $sql = "UPDATE {$this->table} SET quantity = :quantity, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id, ':quantity' => $newQuantity]);
    }
}
