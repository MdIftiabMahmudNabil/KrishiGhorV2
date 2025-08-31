<?php
/**
 * Security Service
 * Handles anomalous login detection, 2FA, and duplicate account detection
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';

class SecurityService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Log login attempt and detect anomalies
     */
    public function logLoginAttempt($userId, $ipAddress, $userAgent, $success = true) {
        $deviceFingerprint = $this->generateDeviceFingerprint($ipAddress, $userAgent);
        $locationData = $this->getLocationFromIP($ipAddress);
        $anomalyScore = $this->calculateAnomalyScore($userId, $ipAddress, $deviceFingerprint, $locationData);
        
        $sql = "INSERT INTO login_logs (
                    user_id, ip_address, user_agent, device_fingerprint, 
                    location_data, success, anomaly_score, is_suspicious
                ) VALUES (
                    :user_id, :ip_address, :user_agent, :device_fingerprint, 
                    :location_data, :success, :anomaly_score, :is_suspicious
                )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent,
            ':device_fingerprint' => $deviceFingerprint,
            ':location_data' => json_encode($locationData),
            ':success' => $success,
            ':anomaly_score' => $anomalyScore,
            ':is_suspicious' => $anomalyScore > 0.7
        ]);
        
        return [
            'anomaly_score' => $anomalyScore,
            'is_suspicious' => $anomalyScore > 0.7,
            'requires_2fa' => $anomalyScore > 0.5,
            'location' => $locationData
        ];
    }
    
    /**
     * Calculate anomaly score based on historical patterns
     */
    private function calculateAnomalyScore($userId, $ipAddress, $deviceFingerprint, $locationData) {
        $score = 0.0;
        
        // Check if this is a new IP address
        $sql = "SELECT COUNT(*) as count FROM login_logs 
                WHERE user_id = :user_id AND ip_address = :ip_address AND success = TRUE";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':ip_address' => $ipAddress]);
        $ipHistory = $stmt->fetch();
        
        if ($ipHistory['count'] == 0) {
            $score += 0.3; // New IP address
        }
        
        // Check if this is a new device
        $sql = "SELECT COUNT(*) as count FROM login_logs 
                WHERE user_id = :user_id AND device_fingerprint = :fingerprint AND success = TRUE";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId, ':fingerprint' => $deviceFingerprint]);
        $deviceHistory = $stmt->fetch();
        
        if ($deviceHistory['count'] == 0) {
            $score += 0.4; // New device
        }
        
        // Check geographic distance from previous logins
        $sql = "SELECT location_data FROM login_logs 
                WHERE user_id = :user_id AND success = TRUE AND location_data IS NOT NULL
                ORDER BY login_time DESC LIMIT 5";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $recentLocations = $stmt->fetchAll();
        
        if (!empty($recentLocations) && isset($locationData['lat'], $locationData['lng'])) {
            $currentLat = $locationData['lat'];
            $currentLng = $locationData['lng'];
            $minDistance = PHP_INT_MAX;
            
            foreach ($recentLocations as $location) {
                $locData = json_decode($location['location_data'], true);
                if (isset($locData['lat'], $locData['lng'])) {
                    $distance = $this->calculateDistance(
                        $currentLat, $currentLng, 
                        $locData['lat'], $locData['lng']
                    );
                    $minDistance = min($minDistance, $distance);
                }
            }
            
            // If more than 500km from usual locations
            if ($minDistance > 500) {
                $score += 0.3;
            }
        }
        
        // Check login frequency patterns
        $sql = "SELECT COUNT(*) as count FROM login_logs 
                WHERE user_id = :user_id AND login_time >= NOW() - INTERVAL '1 hour'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $recentLogins = $stmt->fetch();
        
        if ($recentLogins['count'] > 5) {
            $score += 0.2; // Too many recent attempts
        }
        
        return min(1.0, $score); // Cap at 1.0
    }
    
    /**
     * Generate device fingerprint
     */
    private function generateDeviceFingerprint($ipAddress, $userAgent) {
        return hash('sha256', $ipAddress . '|' . $userAgent);
    }
    
    /**
     * Get location from IP address (mock implementation)
     */
    private function getLocationFromIP($ipAddress) {
        // In production, use a service like MaxMind GeoIP2
        // For now, return mock data for Bangladesh
        return [
            'country' => 'Bangladesh',
            'region' => 'Dhaka',
            'city' => 'Dhaka',
            'lat' => 23.8103,
            'lng' => 90.4125
        ];
    }
    
    /**
     * Calculate distance between two geographic points
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2) {
        $earthRadius = 6371; // Earth radius in kilometers
        
        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);
        
        $deltaLat = $lat2Rad - $lat1Rad;
        $deltaLon = $lon2Rad - $lon1Rad;
        
        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLon / 2) * sin($deltaLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
    
    /**
     * Check for duplicate accounts
     */
    public function checkDuplicateAccounts($email, $phone, $firstName, $lastName) {
        $duplicates = [];
        
        // Check for similar emails
        $emailSimilarity = $this->findSimilarEmails($email);
        if (!empty($emailSimilarity)) {
            $duplicates = array_merge($duplicates, $emailSimilarity);
        }
        
        // Check for same phone number
        if ($phone) {
            $phoneDuplicates = $this->findSamePhone($phone);
            if (!empty($phoneDuplicates)) {
                $duplicates = array_merge($duplicates, $phoneDuplicates);
            }
        }
        
        // Check for similar names
        $nameSimilarity = $this->findSimilarNames($firstName, $lastName);
        if (!empty($nameSimilarity)) {
            $duplicates = array_merge($duplicates, $nameSimilarity);
        }
        
        return array_unique($duplicates, SORT_REGULAR);
    }
    
    /**
     * Find similar email addresses
     */
    private function findSimilarEmails($email) {
        $emailParts = explode('@', $email);
        $localPart = $emailParts[0];
        $domain = $emailParts[1] ?? '';
        
        // Remove common variations
        $cleanLocal = str_replace(['.', '_', '-', '+'], '', $localPart);
        
        $sql = "SELECT id, email, first_name, last_name FROM users 
                WHERE REPLACE(REPLACE(REPLACE(REPLACE(SPLIT_PART(email, '@', 1), '.', ''), '_', ''), '-', ''), '+', '') 
                ILIKE :clean_local AND email != :original_email";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':clean_local' => '%' . $cleanLocal . '%',
            ':original_email' => $email
        ]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Find accounts with same phone number
     */
    private function findSamePhone($phone) {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        
        $sql = "SELECT id, email, first_name, last_name, phone FROM users 
                WHERE REGEXP_REPLACE(phone, '[^0-9]', '', 'g') = :clean_phone";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':clean_phone' => $cleanPhone]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Find accounts with similar names
     */
    private function findSimilarNames($firstName, $lastName) {
        $sql = "SELECT id, email, first_name, last_name FROM users 
                WHERE (LOWER(first_name) = LOWER(:first_name) AND LOWER(last_name) = LOWER(:last_name))
                OR (SOUNDEX(first_name) = SOUNDEX(:first_name) AND SOUNDEX(last_name) = SOUNDEX(:last_name))";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':first_name' => $firstName,
            ':last_name' => $lastName
        ]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Log duplicate account detection
     */
    public function logDuplicateCheck($email1, $email2, $phone1, $phone2, $similarityScore, $type, $userId1, $userId2) {
        $sql = "INSERT INTO duplicate_account_checks (
                    email_1, email_2, phone_1, phone_2, similarity_score, 
                    similarity_type, user_id_1, user_id_2
                ) VALUES (
                    :email_1, :email_2, :phone_1, :phone_2, :similarity_score, 
                    :similarity_type, :user_id_1, :user_id_2
                )";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':email_1' => $email1,
            ':email_2' => $email2,
            ':phone_1' => $phone1,
            ':phone_2' => $phone2,
            ':similarity_score' => $similarityScore,
            ':similarity_type' => $type,
            ':user_id_1' => $userId1,
            ':user_id_2' => $userId2
        ]);
    }
    
    /**
     * Enable 2FA for user
     */
    public function enable2FA($userId) {
        $secret = $this->generate2FASecret();
        
        $sql = "INSERT INTO user_security_settings (user_id, two_factor_enabled, two_factor_secret)
                VALUES (:user_id, TRUE, :secret)
                ON CONFLICT (user_id) 
                DO UPDATE SET two_factor_enabled = TRUE, two_factor_secret = :secret, updated_at = NOW()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':secret' => $secret
        ]);
        
        return $secret;
    }
    
    /**
     * Generate 2FA secret
     */
    private function generate2FASecret() {
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Get user security settings
     */
    public function getUserSecuritySettings($userId) {
        $sql = "SELECT * FROM user_security_settings WHERE user_id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        
        return $stmt->fetch();
    }
}
