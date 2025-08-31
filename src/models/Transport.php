<?php
/**
 * Transport Model
 * Handles logistics and transportation data
 */

require_once __DIR__ . '/../config/database.php';

class Transport {
    private $db;
    private $table = 'transport';
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function create($data) {
        $sql = "INSERT INTO {$this->table} (
                    order_id, transport_type, provider_name, provider_contact, 
                    pickup_address, delivery_address, pickup_date, delivery_date, 
                    cost, status, tracking_number, vehicle_info, driver_info, 
                    notes, created_at, updated_at
                ) VALUES (
                    :order_id, :transport_type, :provider_name, :provider_contact, 
                    :pickup_address, :delivery_address, :pickup_date, :delivery_date, 
                    :cost, :status, :tracking_number, :vehicle_info, :driver_info, 
                    :notes, NOW(), NOW()
                ) RETURNING id";
        
        $stmt = $this->db->prepare($sql);
        
        $params = [
            ':order_id' => $data['order_id'],
            ':transport_type' => $data['transport_type'] ?? 'truck',
            ':provider_name' => $data['provider_name'] ?? null,
            ':provider_contact' => $data['provider_contact'] ?? null,
            ':pickup_address' => $data['pickup_address'],
            ':delivery_address' => $data['delivery_address'],
            ':pickup_date' => $data['pickup_date'] ?? null,
            ':delivery_date' => $data['delivery_date'] ?? null,
            ':cost' => $data['cost'] ?? null,
            ':status' => 'scheduled',
            ':tracking_number' => $data['tracking_number'] ?? null,
            ':vehicle_info' => json_encode($data['vehicle_info'] ?? []),
            ':driver_info' => json_encode($data['driver_info'] ?? []),
            ':notes' => $data['notes'] ?? null,
        ];
        
        if ($stmt->execute($params)) {
            return $stmt->fetch()['id'];
        }
        
        return false;
    }
    
    public function findById($id) {
        $sql = "SELECT t.*, 
                       o.id as order_id, o.quantity, o.total_amount,
                       p.name as product_name, p.category,
                       b.first_name as buyer_first_name, b.last_name as buyer_last_name, b.phone as buyer_phone,
                       f.first_name as farmer_first_name, f.last_name as farmer_last_name, f.phone as farmer_phone
                FROM {$this->table} t
                LEFT JOIN orders o ON t.order_id = o.id
                LEFT JOIN products p ON o.product_id = p.id
                LEFT JOIN users b ON o.buyer_id = b.id
                LEFT JOIN users f ON o.farmer_id = f.id
                WHERE t.id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        $transport = $stmt->fetch();
        if ($transport) {
            if ($transport['vehicle_info']) {
                $transport['vehicle_info'] = json_decode($transport['vehicle_info'], true);
            }
            if ($transport['driver_info']) {
                $transport['driver_info'] = json_decode($transport['driver_info'], true);
            }
        }
        
        return $transport;
    }
    
    public function findByOrder($orderId) {
        $sql = "SELECT * FROM {$this->table} WHERE order_id = :order_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':order_id' => $orderId]);
        
        $transport = $stmt->fetch();
        if ($transport) {
            if ($transport['vehicle_info']) {
                $transport['vehicle_info'] = json_decode($transport['vehicle_info'], true);
            }
            if ($transport['driver_info']) {
                $transport['driver_info'] = json_decode($transport['driver_info'], true);
            }
        }
        
        return $transport;
    }
    
    public function getByProvider($providerName, $limit = null, $offset = 0) {
        $sql = "SELECT t.*, o.quantity, o.total_amount,
                       p.name as product_name, p.category
                FROM {$this->table} t
                LEFT JOIN orders o ON t.order_id = o.id
                LEFT JOIN products p ON o.product_id = p.id
                WHERE t.provider_name = :provider_name
                ORDER BY t.created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }
        
        $stmt = $this->db->prepare($sql);
        $params = [':provider_name' => $providerName];
        
        if ($limit) {
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;
        }
        
        $stmt->execute($params);
        $transports = $stmt->fetchAll();
        
        // Decode JSON fields
        foreach ($transports as &$transport) {
            if ($transport['vehicle_info']) {
                $transport['vehicle_info'] = json_decode($transport['vehicle_info'], true);
            }
            if ($transport['driver_info']) {
                $transport['driver_info'] = json_decode($transport['driver_info'], true);
            }
        }
        
        return $transports;
    }
    
    public function updateStatus($id, $status, $notes = null) {
        $allowedStatuses = ['scheduled', 'pickup_pending', 'in_transit', 'delivered', 'cancelled', 'delayed'];
        
        if (!in_array($status, $allowedStatuses)) {
            return false;
        }
        
        $sql = "UPDATE {$this->table} SET status = :status, updated_at = NOW()";
        $params = [':id' => $id, ':status' => $status];
        
        if ($notes !== null) {
            $sql .= ", notes = :notes";
            $params[':notes'] = $notes;
        }
        
        // Update delivery date if delivered
        if ($status === 'delivered') {
            $sql .= ", delivery_date = NOW()";
        }
        
        $sql .= " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function updateTrackingInfo($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        
        if (isset($data['tracking_number'])) {
            $fields[] = "tracking_number = :tracking_number";
            $params[':tracking_number'] = $data['tracking_number'];
        }
        
        if (isset($data['vehicle_info'])) {
            $fields[] = "vehicle_info = :vehicle_info";
            $params[':vehicle_info'] = json_encode($data['vehicle_info']);
        }
        
        if (isset($data['driver_info'])) {
            $fields[] = "driver_info = :driver_info";
            $params[':driver_info'] = json_encode($data['driver_info']);
        }
        
        if (isset($data['pickup_date'])) {
            $fields[] = "pickup_date = :pickup_date";
            $params[':pickup_date'] = $data['pickup_date'];
        }
        
        if (isset($data['delivery_date'])) {
            $fields[] = "delivery_date = :delivery_date";
            $params[':delivery_date'] = $data['delivery_date'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "updated_at = NOW()";
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function getTransportStats($startDate = null, $endDate = null) {
        $conditions = ["1=1"];
        $params = [];
        
        if ($startDate) {
            $conditions[] = "created_at >= :start_date";
            $params[':start_date'] = $startDate;
        }
        
        if ($endDate) {
            $conditions[] = "created_at <= :end_date";
            $params[':end_date'] = $endDate;
        }
        
        $sql = "SELECT 
                    transport_type,
                    status,
                    COUNT(*) as count,
                    AVG(cost) as avg_cost,
                    SUM(cost) as total_cost,
                    AVG(EXTRACT(EPOCH FROM (delivery_date - pickup_date))/3600) as avg_delivery_hours
                FROM {$this->table}
                WHERE " . implode(' AND ', $conditions) . "
                GROUP BY transport_type, status
                ORDER BY transport_type, status";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function getProviderPerformance() {
        $sql = "SELECT 
                    provider_name,
                    COUNT(*) as total_deliveries,
                    COUNT(CASE WHEN status = 'delivered' THEN 1 END) as successful_deliveries,
                    COUNT(CASE WHEN status = 'delivered' THEN 1 END) * 100.0 / COUNT(*) as success_rate,
                    AVG(cost) as avg_cost,
                    AVG(EXTRACT(EPOCH FROM (delivery_date - pickup_date))/3600) as avg_delivery_hours
                FROM {$this->table}
                WHERE provider_name IS NOT NULL
                GROUP BY provider_name
                HAVING COUNT(*) >= 5
                ORDER BY success_rate DESC, avg_delivery_hours ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function getActiveDeliveries() {
        $sql = "SELECT t.*, 
                       o.quantity, o.total_amount,
                       p.name as product_name, p.category,
                       b.first_name as buyer_first_name, b.last_name as buyer_last_name, b.phone as buyer_phone,
                       f.first_name as farmer_first_name, f.last_name as farmer_last_name, f.phone as farmer_phone
                FROM {$this->table} t
                LEFT JOIN orders o ON t.order_id = o.id
                LEFT JOIN products p ON o.product_id = p.id
                LEFT JOIN users b ON o.buyer_id = b.id
                LEFT JOIN users f ON o.farmer_id = f.id
                WHERE t.status IN ('scheduled', 'pickup_pending', 'in_transit')
                ORDER BY t.pickup_date ASC, t.created_at ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        $transports = $stmt->fetchAll();
        
        // Decode JSON fields
        foreach ($transports as &$transport) {
            if ($transport['vehicle_info']) {
                $transport['vehicle_info'] = json_decode($transport['vehicle_info'], true);
            }
            if ($transport['driver_info']) {
                $transport['driver_info'] = json_decode($transport['driver_info'], true);
            }
        }
        
        return $transports;
    }
    
    public function getDelayedDeliveries() {
        $sql = "SELECT t.*, 
                       o.quantity, o.total_amount,
                       p.name as product_name,
                       b.first_name as buyer_first_name, b.last_name as buyer_last_name, b.phone as buyer_phone
                FROM {$this->table} t
                LEFT JOIN orders o ON t.order_id = o.id
                LEFT JOIN products p ON o.product_id = p.id
                LEFT JOIN users b ON o.buyer_id = b.id
                WHERE t.delivery_date < NOW() 
                AND t.status NOT IN ('delivered', 'cancelled')
                ORDER BY t.delivery_date ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
