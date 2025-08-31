<?php
/**
 * Reminder Service
 * Smart reminder system for unpaid invoices and order notifications
 */

require_once __DIR__ . '/../config/database.php';

class ReminderService {
    private $db;
    
    // Reminder configurations
    private $reminderConfig = [
        'payment' => [
            'first_reminder' => 24,  // hours after order placed
            'second_reminder' => 48, // hours after order placed
            'final_reminder' => 72,  // hours after order placed
            'auto_cancel' => 168     // hours after order placed (7 days)
        ],
        'delivery' => [
            'preparation_reminder' => 24, // hours before expected delivery
            'delivery_reminder' => 2      // hours before expected delivery
        ]
    ];
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Schedule payment reminder for an order
     */
    public function schedulePaymentReminder($orderId, $userId) {
        try {
            // Get order details
            $order = $this->getOrderDetails($orderId);
            if (!$order) {
                throw new Exception("Order not found");
            }
            
            // Only schedule reminders for non-COD orders
            if ($order['payment_method'] === 'cod') {
                return ['success' => true, 'message' => 'No reminder needed for COD orders'];
            }
            
            // Schedule multiple reminders
            $reminders = [];
            
            foreach ($this->reminderConfig['payment'] as $type => $hoursDelay) {
                $reminderTime = date('Y-m-d H:i:s', strtotime($order['created_at'] . " +{$hoursDelay} hours"));
                
                $reminderId = $this->createReminder([
                    'order_id' => $orderId,
                    'user_id' => $userId,
                    'reminder_type' => 'payment',
                    'reminder_subtype' => $type,
                    'scheduled_time' => $reminderTime,
                    'message' => $this->generatePaymentReminderMessage($type, $order),
                    'status' => 'scheduled'
                ]);
                
                $reminders[] = [
                    'id' => $reminderId,
                    'type' => $type,
                    'scheduled_time' => $reminderTime
                ];
            }
            
            return [
                'success' => true,
                'reminders_scheduled' => count($reminders),
                'reminders' => $reminders
            ];
            
        } catch (Exception $e) {
            error_log("Schedule payment reminder error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Schedule delivery reminders
     */
    public function scheduleDeliveryReminder($orderId, $deliveryDate) {
        try {
            $order = $this->getOrderDetails($orderId);
            if (!$order) {
                throw new Exception("Order not found");
            }
            
            $reminders = [];
            
            foreach ($this->reminderConfig['delivery'] as $type => $hoursBefore) {
                $reminderTime = date('Y-m-d H:i:s', strtotime($deliveryDate . " -{$hoursBefore} hours"));
                
                // Only schedule future reminders
                if (strtotime($reminderTime) > time()) {
                    $reminderId = $this->createReminder([
                        'order_id' => $orderId,
                        'user_id' => $order['buyer_id'],
                        'reminder_type' => 'delivery',
                        'reminder_subtype' => $type,
                        'scheduled_time' => $reminderTime,
                        'message' => $this->generateDeliveryReminderMessage($type, $order, $deliveryDate),
                        'status' => 'scheduled'
                    ]);
                    
                    $reminders[] = [
                        'id' => $reminderId,
                        'type' => $type,
                        'scheduled_time' => $reminderTime
                    ];
                }
            }
            
            return [
                'success' => true,
                'reminders_scheduled' => count($reminders),
                'reminders' => $reminders
            ];
            
        } catch (Exception $e) {
            error_log("Schedule delivery reminder error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process due reminders (called by cron job)
     */
    public function processDueReminders() {
        try {
            // Get all reminders that are due
            $sql = "SELECT r.*, o.buyer_id, o.farmer_id, o.order_status, o.payment_status
                    FROM reminders r
                    JOIN orders o ON r.order_id = o.id
                    WHERE r.status = 'scheduled'
                    AND r.scheduled_time <= NOW()
                    ORDER BY r.scheduled_time ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $dueReminders = $stmt->fetchAll();
            
            $processed = 0;
            $results = [];
            
            foreach ($dueReminders as $reminder) {
                $result = $this->processReminder($reminder);
                $results[] = $result;
                
                if ($result['success']) {
                    $processed++;
                }
            }
            
            return [
                'success' => true,
                'total_due' => count($dueReminders),
                'processed' => $processed,
                'results' => $results
            ];
            
        } catch (Exception $e) {
            error_log("Process due reminders error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process individual reminder
     */
    private function processReminder($reminder) {
        try {
            // Check if reminder is still relevant
            if (!$this->isReminderRelevant($reminder)) {
                $this->updateReminderStatus($reminder['id'], 'cancelled', 'No longer relevant');
                return [
                    'success' => true,
                    'reminder_id' => $reminder['id'],
                    'action' => 'cancelled',
                    'reason' => 'Not relevant'
                ];
            }
            
            // Send the reminder
            $sendResult = $this->sendReminder($reminder);
            
            if ($sendResult['success']) {
                $this->updateReminderStatus($reminder['id'], 'sent', 'Reminder sent successfully');
                
                // Handle special actions for certain reminder types
                $this->handleSpecialActions($reminder);
                
                return [
                    'success' => true,
                    'reminder_id' => $reminder['id'],
                    'action' => 'sent',
                    'details' => $sendResult
                ];
            } else {
                $this->updateReminderStatus($reminder['id'], 'failed', $sendResult['error']);
                return [
                    'success' => false,
                    'reminder_id' => $reminder['id'],
                    'action' => 'failed',
                    'error' => $sendResult['error']
                ];
            }
            
        } catch (Exception $e) {
            error_log("Process reminder error: " . $e->getMessage());
            $this->updateReminderStatus($reminder['id'], 'failed', $e->getMessage());
            
            return [
                'success' => false,
                'reminder_id' => $reminder['id'],
                'action' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check if reminder is still relevant
     */
    private function isReminderRelevant($reminder) {
        switch ($reminder['reminder_type']) {
            case 'payment':
                // Payment reminders are not relevant if payment is completed or order is cancelled
                return !in_array($reminder['payment_status'], ['completed', 'refunded']) && 
                       !in_array($reminder['order_status'], ['cancelled', 'delivered']);
                
            case 'delivery':
                // Delivery reminders are not relevant if order is cancelled or already delivered
                return !in_array($reminder['order_status'], ['cancelled', 'delivered']);
                
            default:
                return true;
        }
    }
    
    /**
     * Send reminder notification
     */
    private function sendReminder($reminder) {
        try {
            // Create notification record
            $notificationId = $this->createNotification([
                'user_id' => $reminder['user_id'],
                'type' => $reminder['reminder_type'] . '_reminder',
                'title' => $this->getReminderTitle($reminder),
                'message' => $reminder['message'],
                'data' => json_encode([
                    'order_id' => $reminder['order_id'],
                    'reminder_type' => $reminder['reminder_type'],
                    'reminder_subtype' => $reminder['reminder_subtype']
                ])
            ]);
            
            // Send email if user has email
            $emailResult = $this->sendReminderEmail($reminder);
            
            // Send SMS if user has phone (placeholder)
            $smsResult = $this->sendReminderSMS($reminder);
            
            return [
                'success' => true,
                'notification_id' => $notificationId,
                'email_sent' => $emailResult['success'],
                'sms_sent' => $smsResult['success']
            ];
            
        } catch (Exception $e) {
            error_log("Send reminder error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Handle special actions for certain reminder types
     */
    private function handleSpecialActions($reminder) {
        if ($reminder['reminder_type'] === 'payment' && $reminder['reminder_subtype'] === 'auto_cancel') {
            // Auto-cancel unpaid orders after final reminder
            $this->autoCancelOrder($reminder['order_id']);
        }
    }
    
    /**
     * Auto-cancel unpaid order
     */
    private function autoCancelOrder($orderId) {
        try {
            // Check current status
            $order = $this->getOrderDetails($orderId);
            
            if ($order && $order['payment_status'] === 'pending' && $order['order_status'] !== 'cancelled') {
                // Cancel the order
                $sql = "UPDATE orders SET 
                            order_status = 'cancelled',
                            notes = CASE WHEN notes IS NULL THEN 'Auto-cancelled due to non-payment' 
                                   ELSE CONCAT(notes, '; Auto-cancelled due to non-payment') END,
                            updated_at = NOW()
                        WHERE id = :order_id";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([':order_id' => $orderId]);
                
                // Create timeline entry
                $this->createTimelineEntry($orderId, 'auto_cancelled', 'Order automatically cancelled due to non-payment');
                
                // Notify both buyer and farmer
                $this->notifyOrderCancellation($orderId, 'auto_cancel');
                
                error_log("Auto-cancelled order #{$orderId} due to non-payment");
            }
            
        } catch (Exception $e) {
            error_log("Auto-cancel order error: " . $e->getMessage());
        }
    }
    
    /**
     * Cancel reminder
     */
    public function cancelReminder($orderId, $reminderType = null, $reminderSubtype = null) {
        try {
            $conditions = ["order_id = :order_id", "status = 'scheduled'"];
            $params = [':order_id' => $orderId];
            
            if ($reminderType) {
                $conditions[] = "reminder_type = :reminder_type";
                $params[':reminder_type'] = $reminderType;
            }
            
            if ($reminderSubtype) {
                $conditions[] = "reminder_subtype = :reminder_subtype";
                $params[':reminder_subtype'] = $reminderSubtype;
            }
            
            $sql = "UPDATE reminders SET 
                        status = 'cancelled', 
                        updated_at = NOW(),
                        notes = 'Cancelled due to status change'
                    WHERE " . implode(' AND ', $conditions);
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
            
        } catch (Exception $e) {
            error_log("Cancel reminder error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get reminder statistics
     */
    public function getReminderStatistics($days = 30) {
        try {
            $sql = "SELECT 
                        reminder_type,
                        status,
                        COUNT(*) as count,
                        AVG(EXTRACT(EPOCH FROM (sent_at - scheduled_time))/3600) as avg_delay_hours
                    FROM reminders 
                    WHERE created_at >= NOW() - INTERVAL ':days days'
                    GROUP BY reminder_type, status
                    ORDER BY reminder_type, status";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':days' => $days]);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Get reminder statistics error: " . $e->getMessage());
            return [];
        }
    }
    
    // Message generation methods
    
    private function generatePaymentReminderMessage($type, $order) {
        $orderTotal = $order['quantity'] * $order['unit_price'];
        
        switch ($type) {
            case 'first_reminder':
                return "আপনার অর্ডার #{$order['id']} এর পেমেন্ট এখনো পেন্ডিং আছে। মোট পরিমাণ: ৳" . number_format($orderTotal, 2) . "। অনুগ্রহ করে পেমেন্ট সম্পন্ন করুন।";
                
            case 'second_reminder':
                return "দ্বিতীয় রিমাইন্ডার: অর্ডার #{$order['id']} এর পেমেন্ট এখনো বাকি। পেমেন্ট না করলে আপনার অর্ডার বাতিল হয়ে যেতে পারে।";
                
            case 'final_reminder':
                return "সর্বশেষ রিমাইন্ডার: অর্ডার #{$order['id']} এর পেমেন্ট ২৪ ঘন্টার মধ্যে সম্পন্ন না করলে অর্ডারটি স্বয়ংক্রিয়ভাবে বাতিল হয়ে যাবে।";
                
            case 'auto_cancel':
                return "আপনার অর্ডার #{$order['id']} পেমেন্ট না করার কারণে স্বয়ংক্রিয়ভাবে বাতিল করা হয়েছে।";
                
            default:
                return "আপনার অর্ডার #{$order['id']} সম্পর্কে একটি রিমাইন্ডার।";
        }
    }
    
    private function generateDeliveryReminderMessage($type, $order, $deliveryDate) {
        switch ($type) {
            case 'preparation_reminder':
                return "আগামীকাল আপনার অর্ডার #{$order['id']} ডেলিভারি হবে। অনুগ্রহ করে প্রস্তুত থাকুন।";
                
            case 'delivery_reminder':
                return "আজ আপনার অর্ডার #{$order['id']} ডেলিভারি হবে। ডেলিভারি টাইম: {$deliveryDate}";
                
            default:
                return "আপনার অর্ডার #{$order['id']} ডেলিভারি সম্পর্কে রিমাইন্ডার।";
        }
    }
    
    private function getReminderTitle($reminder) {
        $titles = [
            'payment_first_reminder' => 'পেমেন্ট রিমাইন্ডার',
            'payment_second_reminder' => 'পেমেন্ট রিমাইন্ডার (২য়)',
            'payment_final_reminder' => 'চূড়ান্ত পেমেন্ট রিমাইন্ডার',
            'payment_auto_cancel' => 'অর্ডার বাতিল',
            'delivery_preparation_reminder' => 'ডেলিভারি প্রস্তুতি',
            'delivery_delivery_reminder' => 'ডেলিভারি রিমাইন্ডার'
        ];
        
        $key = $reminder['reminder_type'] . '_' . $reminder['reminder_subtype'];
        return $titles[$key] ?? 'KrishiGhor রিমাইন্ডার';
    }
    
    // Email and SMS methods (placeholders)
    
    private function sendReminderEmail($reminder) {
        // In real implementation, integrate with email service
        error_log("SEND REMINDER EMAIL: {$reminder['reminder_type']} for order #{$reminder['order_id']} to user #{$reminder['user_id']}");
        
        return ['success' => true, 'method' => 'email'];
    }
    
    private function sendReminderSMS($reminder) {
        // In real implementation, integrate with SMS service
        error_log("SEND REMINDER SMS: {$reminder['reminder_type']} for order #{$reminder['order_id']} to user #{$reminder['user_id']}");
        
        return ['success' => true, 'method' => 'sms'];
    }
    
    // Database helper methods
    
    private function createReminder($data) {
        $sql = "INSERT INTO reminders (
                    order_id, user_id, reminder_type, reminder_subtype, 
                    scheduled_time, message, status, created_at, updated_at
                ) VALUES (
                    :order_id, :user_id, :reminder_type, :reminder_subtype,
                    :scheduled_time, :message, :status, NOW(), NOW()
                ) RETURNING id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        
        return $stmt->fetch()['id'];
    }
    
    private function updateReminderStatus($reminderId, $status, $notes = null) {
        $sql = "UPDATE reminders SET 
                    status = :status, 
                    sent_at = CASE WHEN :status = 'sent' THEN NOW() ELSE sent_at END,
                    notes = :notes,
                    updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $reminderId,
            ':status' => $status,
            ':notes' => $notes
        ]);
    }
    
    private function createNotification($data) {
        $sql = "INSERT INTO notifications (
                    user_id, type, title, message, data, 
                    is_read, created_at, updated_at
                ) VALUES (
                    :user_id, :type, :title, :message, :data,
                    FALSE, NOW(), NOW()
                ) RETURNING id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        
        return $stmt->fetch()['id'];
    }
    
    private function getOrderDetails($orderId) {
        $sql = "SELECT o.*, 
                       p.name as product_name, p.unit,
                       b.first_name as buyer_first_name, b.last_name as buyer_last_name, 
                       b.email as buyer_email, b.phone as buyer_phone,
                       f.first_name as farmer_first_name, f.last_name as farmer_last_name
                FROM orders o
                LEFT JOIN products p ON o.product_id = p.id
                LEFT JOIN users b ON o.buyer_id = b.id
                LEFT JOIN users f ON o.farmer_id = f.id
                WHERE o.id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $orderId]);
        
        return $stmt->fetch();
    }
    
    private function createTimelineEntry($orderId, $status, $notes = null) {
        try {
            $sql = "INSERT INTO order_timeline (order_id, status, notes, created_at) 
                    VALUES (:order_id, :status, :notes, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':order_id' => $orderId,
                ':status' => $status,
                ':notes' => $notes
            ]);
        } catch (Exception $e) {
            error_log("Create timeline entry error: " . $e->getMessage());
        }
    }
    
    private function notifyOrderCancellation($orderId, $reason) {
        // Placeholder for order cancellation notifications
        error_log("NOTIFY ORDER CANCELLATION: Order #{$orderId} cancelled - {$reason}");
    }
}
