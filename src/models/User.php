<?php
/**
 * User Model
 * Handles user data operations and authentication
 */

require_once __DIR__ . '/../config/database.php';

class User {
    private $db;
    private $table = 'users';
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function create($data) {
        $sql = "INSERT INTO {$this->table} (
                    email, password, first_name, last_name, phone, 
                    role, status, language, region, created_at, updated_at
                ) VALUES (
                    :email, :password, :first_name, :last_name, :phone, 
                    :role, :status, :language, :region, NOW(), NOW()
                ) RETURNING id";
        
        $stmt = $this->db->prepare($sql);
        
        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $params = [
            ':email' => $data['email'],
            ':password' => $hashedPassword,
            ':first_name' => $data['first_name'],
            ':last_name' => $data['last_name'],
            ':phone' => $data['phone'] ?? null,
            ':role' => $data['role'] ?? 'farmer',
            ':status' => $data['status'] ?? 'active',
            ':language' => $data['language'] ?? 'bn',
            ':region' => $data['region'] ?? 'Dhaka',
        ];
        
        if ($stmt->execute($params)) {
            return $stmt->fetch()['id'];
        }
        
        return false;
    }
    
    public function findByEmail($email) {
        $sql = "SELECT * FROM {$this->table} WHERE email = :email AND status = 'active'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        
        return $stmt->fetch();
    }
    
    public function findById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id AND status = 'active'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        return $stmt->fetch();
    }
    
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        
        foreach ($data as $key => $value) {
            if ($key === 'password') {
                $fields[] = "password = :password";
                $params[':password'] = password_hash($value, PASSWORD_DEFAULT);
            } elseif (in_array($key, ['first_name', 'last_name', 'phone', 'language', 'status', 'region'])) {
                $fields[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
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
    
    public function updateLastLogin($id) {
        $sql = "UPDATE {$this->table} SET last_login = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
    
    public function emailExists($email, $excludeId = null) {
        $sql = "SELECT id FROM {$this->table} WHERE email = :email";
        $params = [':email' => $email];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch() !== false;
    }
    
    public function getByRole($role, $limit = null, $offset = 0) {
        $sql = "SELECT id, email, first_name, last_name, phone, role, status, language, created_at, last_login 
                FROM {$this->table} 
                WHERE role = :role AND status = 'active' 
                ORDER BY created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT :limit OFFSET :offset";
        }
        
        $stmt = $this->db->prepare($sql);
        $params = [':role' => $role];
        
        if ($limit) {
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;
        }
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getStats() {
        $sql = "SELECT 
                    role,
                    COUNT(*) as count,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count,
                    COUNT(CASE WHEN last_login >= NOW() - INTERVAL '30 days' THEN 1 END) as recent_active
                FROM {$this->table} 
                GROUP BY role";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function softDelete($id) {
        $sql = "UPDATE {$this->table} SET status = 'deleted', updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}
