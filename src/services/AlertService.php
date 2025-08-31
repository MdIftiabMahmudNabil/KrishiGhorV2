<?php
/**
 * Alert Service
 * Handles price anomaly alerts and admin notifications
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';

class AlertService {
    private $db;
    private $userModel;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->userModel = new User();
    }
    
    /**
     * Create price anomaly alert
     */
    public function createPriceAnomalyAlert($category, $location, $anomalies, $severity = 'medium') {
        try {
            $sql = "INSERT INTO alerts (
                        type, category, location, severity, data, 
                        status, created_at, updated_at
                    ) VALUES (
                        'price_anomaly', :category, :location, :severity, :data,
                        'active', NOW(), NOW()
                    ) RETURNING id";
            
            $stmt = $this->db->prepare($sql);
            $alertData = [
                'anomalies' => $anomalies,
                'detection_time' => date('Y-m-d H:i:s'),
                'alert_triggered_by' => 'automated_system'
            ];
            
            $stmt->execute([
                ':category' => $category,
                ':location' => $location,
                ':severity' => $severity,
                ':data' => json_encode($alertData)
            ]);
            
            $alertId = $stmt->fetch()['id'];
            
            // Notify admins
            $this->notifyAdmins($alertId, 'price_anomaly', $category, $location, $anomalies);
            
            return $alertId;
            
        } catch (Exception $e) {
            error_log("Create price anomaly alert error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create price spike alert
     */
    public function createPriceSpikeAlert($category, $location, $currentPrice, $previousPrice, $percentageChange) {
        $severity = abs($percentageChange) > 50 ? 'high' : 'medium';
        $direction = $percentageChange > 0 ? 'spike' : 'drop';
        
        try {
            $sql = "INSERT INTO alerts (
                        type, category, location, severity, data, 
                        status, created_at, updated_at
                    ) VALUES (
                        'price_spike', :category, :location, :severity, :data,
                        'active', NOW(), NOW()
                    ) RETURNING id";
            
            $stmt = $this->db->prepare($sql);
            $alertData = [
                'direction' => $direction,
                'current_price' => $currentPrice,
                'previous_price' => $previousPrice,
                'percentage_change' => $percentageChange,
                'detection_time' => date('Y-m-d H:i:s')
            ];
            
            $stmt->execute([
                ':category' => $category,
                ':location' => $location,
                ':severity' => $severity,
                ':data' => json_encode($alertData)
            ]);
            
            $alertId = $stmt->fetch()['id'];
            
            // Notify admins and affected users
            $this->notifyAdmins($alertId, 'price_spike', $category, $location, $alertData);
            $this->notifyAffectedUsers($category, $location, $alertData);
            
            return $alertId;
            
        } catch (Exception $e) {
            error_log("Create price spike alert error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notify administrators
     */
    private function notifyAdmins($alertId, $alertType, $category, $location, $data) {
        try {
            // Get all admin users
            $admins = $this->userModel->getByRole('admin');
            
            foreach ($admins as $admin) {
                $this->sendNotification($admin['id'], $alertId, $alertType, $category, $location, $data);
            }
            
            // Also send email notifications if configured
            $this->sendEmailAlerts($admins, $alertType, $category, $location, $data);
            
        } catch (Exception $e) {
            error_log("Notify admins error: " . $e->getMessage());
        }
    }
    
    /**
     * Notify affected users (farmers/buyers with price alerts)
     */
    private function notifyAffectedUsers($category, $location, $data) {
        try {
            // Get users with price alerts for this category/location
            $sql = "SELECT DISTINCT pa.user_id, u.email, u.first_name, u.last_name
                    FROM price_alerts pa
                    JOIN users u ON pa.user_id = u.id
                    WHERE pa.product_category = :category
                    AND (pa.market_location ILIKE :location OR pa.market_location IS NULL)
                    AND pa.is_active = TRUE
                    AND u.status = 'active'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':category' => $category,
                ':location' => '%' . $location . '%'
            ]);
            
            $affectedUsers = $stmt->fetchAll();
            
            foreach ($affectedUsers as $user) {
                $this->sendUserNotification($user['user_id'], 'price_alert', $category, $location, $data);
            }
            
        } catch (Exception $e) {
            error_log("Notify affected users error: " . $e->getMessage());
        }
    }
    
    /**
     * Send notification to user
     */
    private function sendNotification($userId, $alertId, $alertType, $category, $location, $data) {
        try {
            $sql = "INSERT INTO notifications (
                        user_id, type, title, message, data, 
                        is_read, created_at, updated_at
                    ) VALUES (
                        :user_id, :type, :title, :message, :data,
                        FALSE, NOW(), NOW()
                    )";
            
            $stmt = $this->db->prepare($sql);
            
            $title = $this->generateNotificationTitle($alertType, $category, $location);
            $message = $this->generateNotificationMessage($alertType, $category, $location, $data);
            
            $notificationData = [
                'alert_id' => $alertId,
                'alert_type' => $alertType,
                'category' => $category,
                'location' => $location,
                'original_data' => $data
            ];
            
            $stmt->execute([
                ':user_id' => $userId,
                ':type' => $alertType,
                ':title' => $title,
                ':message' => $message,
                ':data' => json_encode($notificationData)
            ]);
            
        } catch (Exception $e) {
            error_log("Send notification error: " . $e->getMessage());
        }
    }
    
    /**
     * Send user notification (for price alerts)
     */
    private function sendUserNotification($userId, $notificationType, $category, $location, $data) {
        try {
            $sql = "INSERT INTO notifications (
                        user_id, type, title, message, data, 
                        is_read, created_at, updated_at
                    ) VALUES (
                        :user_id, :type, :title, :message, :data,
                        FALSE, NOW(), NOW()
                    )";
            
            $stmt = $this->db->prepare($sql);
            
            $title = $this->generateUserNotificationTitle($notificationType, $category, $location);
            $message = $this->generateUserNotificationMessage($notificationType, $category, $location, $data);
            
            $stmt->execute([
                ':user_id' => $userId,
                ':type' => $notificationType,
                ':title' => $title,
                ':message' => $message,
                ':data' => json_encode($data)
            ]);
            
        } catch (Exception $e) {
            error_log("Send user notification error: " . $e->getMessage());
        }
    }
    
    /**
     * Send email alerts to admins
     */
    private function sendEmailAlerts($admins, $alertType, $category, $location, $data) {
        // This would integrate with an email service (PHPMailer, SendGrid, etc.)
        // For now, just log the alert
        
        $emailData = [
            'recipients' => array_column($admins, 'email'),
            'subject' => $this->generateEmailSubject($alertType, $category, $location),
            'body' => $this->generateEmailBody($alertType, $category, $location, $data),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        error_log("EMAIL ALERT TO SEND: " . json_encode($emailData));
        
        // TODO: Implement actual email sending
        /*
        foreach ($admins as $admin) {
            $this->emailService->send(
                $admin['email'],
                $emailData['subject'],
                $emailData['body']
            );
        }
        */
    }
    
    /**
     * Generate notification title
     */
    private function generateNotificationTitle($alertType, $category, $location) {
        switch ($alertType) {
            case 'price_anomaly':
                return "Price Anomaly Detected: {$category} in {$location}";
            case 'price_spike':
                return "Price Spike Alert: {$category} in {$location}";
            default:
                return "Market Alert: {$category}";
        }
    }
    
    /**
     * Generate notification message
     */
    private function generateNotificationMessage($alertType, $category, $location, $data) {
        switch ($alertType) {
            case 'price_anomaly':
                $anomalyCount = count($data['anomalies'] ?? []);
                return "Detected {$anomalyCount} price anomalies for {$category} in {$location}. Immediate attention required.";
                
            case 'price_spike':
                $direction = $data['direction'] ?? 'change';
                $percentage = abs($data['percentage_change'] ?? 0);
                return "Significant price {$direction} detected for {$category} in {$location}. Price changed by {$percentage}%.";
                
            default:
                return "Market activity detected for {$category} in {$location}.";
        }
    }
    
    /**
     * Generate user notification title
     */
    private function generateUserNotificationTitle($notificationType, $category, $location) {
        switch ($notificationType) {
            case 'price_alert':
                return "Price Alert: {$category}";
            default:
                return "Market Update: {$category}";
        }
    }
    
    /**
     * Generate user notification message
     */
    private function generateUserNotificationMessage($notificationType, $category, $location, $data) {
        switch ($notificationType) {
            case 'price_alert':
                $direction = $data['direction'] ?? 'changed';
                $currentPrice = $data['current_price'] ?? 'N/A';
                return "Price {$direction} detected for {$category} in {$location}. Current price: ৳{$currentPrice}";
                
            default:
                return "Market update available for {$category} in {$location}.";
        }
    }
    
    /**
     * Generate email subject
     */
    private function generateEmailSubject($alertType, $category, $location) {
        return "[KrishiGhor Alert] " . $this->generateNotificationTitle($alertType, $category, $location);
    }
    
    /**
     * Generate email body
     */
    private function generateEmailBody($alertType, $category, $location, $data) {
        $body = "Dear Admin,\n\n";
        $body .= $this->generateNotificationMessage($alertType, $category, $location, $data) . "\n\n";
        $body .= "Alert Details:\n";
        $body .= "- Category: {$category}\n";
        $body .= "- Location: {$location}\n";
        $body .= "- Time: " . date('Y-m-d H:i:s') . "\n";
        
        if ($alertType === 'price_spike') {
            $body .= "- Current Price: ৳" . ($data['current_price'] ?? 'N/A') . "\n";
            $body .= "- Previous Price: ৳" . ($data['previous_price'] ?? 'N/A') . "\n";
            $body .= "- Change: " . ($data['percentage_change'] ?? 'N/A') . "%\n";
        }
        
        $body .= "\nPlease login to the admin dashboard for more details.\n\n";
        $body .= "Best regards,\n";
        $body .= "KrishiGhor Alert System";
        
        return $body;
    }
    
    /**
     * Get active alerts
     */
    public function getActiveAlerts($limit = 50) {
        try {
            $sql = "SELECT * FROM alerts 
                    WHERE status = 'active' 
                    ORDER BY created_at DESC 
                    LIMIT :limit";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':limit' => $limit]);
            
            $alerts = $stmt->fetchAll();
            
            // Decode JSON data
            foreach ($alerts as &$alert) {
                $alert['data'] = json_decode($alert['data'], true);
            }
            
            return $alerts;
            
        } catch (Exception $e) {
            error_log("Get active alerts error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark alert as resolved
     */
    public function resolveAlert($alertId, $resolvedBy, $resolution = null) {
        try {
            $sql = "UPDATE alerts 
                    SET status = 'resolved', 
                        resolved_by = :resolved_by,
                        resolution = :resolution,
                        resolved_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :alert_id";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':alert_id' => $alertId,
                ':resolved_by' => $resolvedBy,
                ':resolution' => $resolution
            ]);
            
        } catch (Exception $e) {
            error_log("Resolve alert error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get alert statistics
     */
    public function getAlertStatistics($days = 30) {
        try {
            $sql = "SELECT 
                        type,
                        severity,
                        COUNT(*) as count,
                        COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_count,
                        AVG(EXTRACT(EPOCH FROM (resolved_at - created_at))/3600) as avg_resolution_hours
                    FROM alerts 
                    WHERE created_at >= NOW() - INTERVAL ':days days'
                    GROUP BY type, severity
                    ORDER BY count DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':days' => $days]);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Get alert statistics error: " . $e->getMessage());
            return [];
        }
    }
}
