<?php
/**
 * Order Model
 * Handles order management and transaction data
 */

require_once __DIR__ . '/../config/database.php';

class Order {
    private $db;
    private $table = 'orders';
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function create($data) {
        $sql = "INSERT INTO {$this->table} (
                    buyer_id, farmer_id, product_id, quantity, unit_price, 
                    total_amount, delivery_address, delivery_date, payment_method, 
                    payment_status, order_status, notes, created_at, updated_at
                ) VALUES (
                    :buyer_id, :farmer_id, :product_id, :quantity, :unit_price, 
                    :total_amount, :delivery_address, :delivery_date, :payment_method, 
                    :payment_status, :order_status, :notes, NOW(), NOW()
                ) RETURNING id";
        
        $stmt = $this->db->prepare($sql);
        
        $params = [
            ':buyer_id' => $data['buyer_id'],
            ':farmer_id' => $data['farmer_id'],
            ':product_id' => $data['product_id'],
            ':quantity' => $data['quantity'],
            ':unit_price' => $data['unit_price'],
            ':total_amount' => $data['quantity'] * $data['unit_price'],
            ':delivery_address' => $data['delivery_address'],
            ':delivery_date' => $data['delivery_date'] ?? null,
            ':payment_method' => $data['payment_method'] ?? 'cash',
            ':payment_status' => 'pending',
            ':order_status' => 'pending',
            ':notes' => $data['notes'] ?? null,
        ];
        
        if ($stmt->execute($params)) {
            return $stmt->fetch()['id'];
        }
        
        return false;
    }
    
    public function findById($id) {
        $sql = "SELECT o.*, 
                       p.name as product_name, p.name_bn as product_name_bn, p.unit, p.category,
                       b.first_name as buyer_first_name, b.last_name as buyer_last_name, 
                       b.email as buyer_email, b.phone as buyer_phone,
                       f.first_name as farmer_first_name, f.last_name as farmer_last_name, 
                       f.email as farmer_email, f.phone as farmer_phone
                FROM {$this->table} o
                LEFT JOIN products p ON o.product_id = p.id
                LEFT JOIN users b ON o.buyer_id = b.id
                LEFT JOIN users f ON o.farmer_id = f.id
                WHERE o.id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch();
    }
    
    public function getByBuyer($buyerId, $limit = null, $offset = 0) {
        $sql = "SELECT o.*, 
                       p.name as product_name, p.name_bn as product_name_bn, p.unit, p.category,
                       f.first_name as farmer_first_name, f.last_name as farmer_last_name, f.phone as farmer_phone
                FROM {$this->table} o
                LEFT JOIN products p ON o.product_id = p.id
                LEFT JOIN users f ON o.farmer_id = f.id
                WHERE o.buyer_id = :buyer_id
                ORDER BY o.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }
        
        $stmt = $this->db->prepare($sql);
        $params = [':buyer_id' => $buyerId];
        
        if ($limit) {
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;
        }
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getByFarmer($farmerId, $limit = null, $offset = 0) {
        $sql = "SELECT o.*, 
                       p.name as product_name, p.name_bn as product_name_bn, p.unit, p.category,
                       b.first_name as buyer_first_name, b.last_name as buyer_last_name, b.phone as buyer_phone
                FROM {$this->table} o
                LEFT JOIN products p ON o.product_id = p.id
                LEFT JOIN users b ON o.buyer_id = b.id
                WHERE o.farmer_id = :farmer_id
                ORDER BY o.created_at DESC";
        
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
        return $stmt->fetchAll();
    }
    
    public function updateStatus($id, $status, $notes = null) {
        $allowedStatuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
        
        if (!in_array($status, $allowedStatuses)) {
            return false;
        }
        
        $sql = "UPDATE {$this->table} SET order_status = :status, updated_at = NOW()";
        $params = [':id' => $id, ':status' => $status];
        
        if ($notes !== null) {
            $sql .= ", notes = :notes";
            $params[':notes'] = $notes;
        }
        
        $sql .= " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function updatePaymentStatus($id, $paymentStatus) {
        $allowedStatuses = ['pending', 'processing', 'completed', 'failed', 'refunded'];
        
        if (!in_array($paymentStatus, $allowedStatuses)) {
            return false;
        }
        
        $sql = "UPDATE {$this->table} SET payment_status = :payment_status, updated_at = NOW() WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id, ':payment_status' => $paymentStatus]);
    }
    
    public function getOrderStats($userId = null, $role = null) {
        $conditions = ["1=1"];
        $params = [];
        
        if ($userId && $role) {
            if ($role === 'buyer') {
                $conditions[] = "buyer_id = :user_id";
            } elseif ($role === 'farmer') {
                $conditions[] = "farmer_id = :user_id";
            }
            $params[':user_id'] = $userId;
        }
        
        $sql = "SELECT 
                    order_status,
                    payment_status,
                    COUNT(*) as count,
                    SUM(total_amount) as total_value,
                    AVG(total_amount) as avg_order_value
                FROM {$this->table}
                WHERE " . implode(' AND ', $conditions) . "
                GROUP BY order_status, payment_status
                ORDER BY order_status, payment_status";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getRecentOrders($limit = 10) {
        $sql = "SELECT o.*, 
                       p.name as product_name, p.category,
                       b.first_name as buyer_first_name, b.last_name as buyer_last_name,
                       f.first_name as farmer_first_name, f.last_name as farmer_last_name
                FROM {$this->table} o
                LEFT JOIN products p ON o.product_id = p.id
                LEFT JOIN users b ON o.buyer_id = b.id
                LEFT JOIN users f ON o.farmer_id = f.id
                ORDER BY o.created_at DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':limit' => $limit]);
        
        return $stmt->fetchAll();
    }
    
    public function getSalesReport($farmerId, $startDate = null, $endDate = null) {
        $conditions = ["farmer_id = :farmer_id"];
        $params = [':farmer_id' => $farmerId];
        
        if ($startDate) {
            $conditions[] = "created_at >= :start_date";
            $params[':start_date'] = $startDate;
        }
        
        if ($endDate) {
            $conditions[] = "created_at <= :end_date";
            $params[':end_date'] = $endDate;
        }
        
        $sql = "SELECT 
                    DATE(created_at) as sale_date,
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN order_status = 'delivered' THEN total_amount ELSE 0 END) as completed_sales,
                    SUM(CASE WHEN order_status IN ('pending', 'confirmed', 'processing', 'shipped') THEN total_amount ELSE 0 END) as pending_sales,
                    SUM(total_amount) as total_value
                FROM {$this->table}
                WHERE " . implode(' AND ', $conditions) . "
                GROUP BY DATE(created_at)
                ORDER BY sale_date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getPurchaseReport($buyerId, $startDate = null, $endDate = null) {
        $conditions = ["buyer_id = :buyer_id"];
        $params = [':buyer_id' => $buyerId];
        
        if ($startDate) {
            $conditions[] = "created_at >= :start_date";
            $params[':start_date'] = $startDate;
        }
        
        if ($endDate) {
            $conditions[] = "created_at <= :end_date";
            $params[':end_date'] = $endDate;
        }
        
        $sql = "SELECT 
                    DATE(created_at) as purchase_date,
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN payment_status = 'completed' THEN total_amount ELSE 0 END) as paid_amount,
                    SUM(CASE WHEN payment_status = 'pending' THEN total_amount ELSE 0 END) as pending_amount,
                    SUM(total_amount) as total_value
                FROM {$this->table}
                WHERE " . implode(' AND ', $conditions) . "
                GROUP BY DATE(created_at)
                ORDER BY purchase_date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function cancel($id, $reason = null) {
        $sql = "UPDATE {$this->table} SET 
                    order_status = 'cancelled', 
                    notes = CASE WHEN notes IS NULL THEN :reason ELSE CONCAT(notes, '; Cancelled: ', :reason) END,
                    updated_at = NOW() 
                WHERE id = :id AND order_status IN ('pending', 'confirmed')";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id, ':reason' => $reason ?? 'Cancelled by user']);
    }
}
