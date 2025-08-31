<?php
/**
 * Invoice Service
 * Handles invoice generation and management
 */

require_once __DIR__ . '/../config/database.php';

class InvoiceService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Generate invoice for an order
     */
    public function generateInvoice($orderId) {
        try {
            // Get order details
            $order = $this->getOrderDetails($orderId);
            if (!$order) {
                throw new Exception("Order not found");
            }
            
            // Generate invoice number
            $invoiceNumber = $this->generateInvoiceNumber();
            
            // Calculate totals
            $subtotal = $order['quantity'] * $order['unit_price'];
            $taxRate = 0.15; // 15% VAT
            $taxAmount = $subtotal * $taxRate;
            $totalAmount = $subtotal + $taxAmount;
            
            // Create invoice record
            $invoiceId = $this->createInvoiceRecord($orderId, $invoiceNumber, $subtotal, $taxAmount, $totalAmount);
            
            // Generate invoice data
            $invoiceData = [
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'order_id' => $orderId,
                'issue_date' => date('Y-m-d'),
                'due_date' => date('Y-m-d', strtotime('+7 days')),
                'buyer' => [
                    'name' => $order['buyer_first_name'] . ' ' . $order['buyer_last_name'],
                    'email' => $order['buyer_email'],
                    'phone' => $order['buyer_phone'],
                    'address' => $order['delivery_address']
                ],
                'seller' => [
                    'name' => $order['farmer_first_name'] . ' ' . $order['farmer_last_name'],
                    'email' => $order['farmer_email'],
                    'phone' => $order['farmer_phone']
                ],
                'items' => [
                    [
                        'description' => $order['product_name'] . ($order['product_name_bn'] ? ' (' . $order['product_name_bn'] . ')' : ''),
                        'quantity' => $order['quantity'],
                        'unit' => $order['unit'],
                        'unit_price' => $order['unit_price'],
                        'total' => $subtotal
                    ]
                ],
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'payment_method' => $order['payment_method'],
                'payment_status' => $order['payment_status'],
                'notes' => $order['notes']
            ];
            
            // Store invoice data
            $this->updateInvoiceData($invoiceId, $invoiceData);
            
            return $invoiceData;
            
        } catch (Exception $e) {
            error_log("Generate invoice error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get invoice by order ID
     */
    public function getInvoice($orderId) {
        try {
            $sql = "SELECT * FROM invoices WHERE order_id = :order_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':order_id' => $orderId]);
            
            $invoice = $stmt->fetch();
            
            if ($invoice && $invoice['invoice_data']) {
                $invoiceData = json_decode($invoice['invoice_data'], true);
                $invoiceData['created_at'] = $invoice['created_at'];
                $invoiceData['updated_at'] = $invoice['updated_at'];
                return $invoiceData;
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Get invoice error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get invoice by invoice number
     */
    public function getInvoiceByNumber($invoiceNumber) {
        try {
            $sql = "SELECT * FROM invoices WHERE invoice_number = :invoice_number";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':invoice_number' => $invoiceNumber]);
            
            $invoice = $stmt->fetch();
            
            if ($invoice && $invoice['invoice_data']) {
                return json_decode($invoice['invoice_data'], true);
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Get invoice by number error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate PDF invoice (placeholder)
     */
    public function generateInvoicePDF($orderId) {
        try {
            $invoiceData = $this->getInvoice($orderId);
            if (!$invoiceData) {
                throw new Exception("Invoice not found");
            }
            
            // In a real implementation, this would use a PDF library like TCPDF or mPDF
            // For now, return HTML that can be converted to PDF
            $html = $this->generateInvoiceHTML($invoiceData);
            
            return [
                'success' => true,
                'html' => $html,
                'filename' => 'invoice_' . $invoiceData['invoice_number'] . '.pdf'
            ];
            
        } catch (Exception $e) {
            error_log("Generate invoice PDF error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send invoice via email (placeholder)
     */
    public function sendInvoiceEmail($orderId, $emailAddress = null) {
        try {
            $invoiceData = $this->getInvoice($orderId);
            if (!$invoiceData) {
                throw new Exception("Invoice not found");
            }
            
            $recipientEmail = $emailAddress ?: $invoiceData['buyer']['email'];
            
            // In a real implementation, this would integrate with an email service
            // For now, just log the action
            error_log("SEND INVOICE EMAIL: Invoice #{$invoiceData['invoice_number']} to {$recipientEmail}");
            
            return [
                'success' => true,
                'message' => 'Invoice sent successfully',
                'recipient' => $recipientEmail
            ];
            
        } catch (Exception $e) {
            error_log("Send invoice email error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get overdue invoices
     */
    public function getOverdueInvoices($days = 30) {
        try {
            $sql = "SELECT i.*, o.buyer_id, o.farmer_id
                    FROM invoices i
                    JOIN orders o ON i.order_id = o.id
                    WHERE i.due_date < CURRENT_DATE
                    AND o.payment_status != 'completed'
                    AND i.created_at >= NOW() - INTERVAL ':days days'
                    ORDER BY i.due_date ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':days' => $days]);
            
            $overdueInvoices = $stmt->fetchAll();
            
            // Decode invoice data for each invoice
            foreach ($overdueInvoices as &$invoice) {
                if ($invoice['invoice_data']) {
                    $invoice['data'] = json_decode($invoice['invoice_data'], true);
                }
            }
            
            return $overdueInvoices;
            
        } catch (Exception $e) {
            error_log("Get overdue invoices error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update invoice status
     */
    public function updateInvoiceStatus($invoiceId, $status) {
        try {
            $allowedStatuses = ['draft', 'sent', 'paid', 'overdue', 'cancelled'];
            
            if (!in_array($status, $allowedStatuses)) {
                throw new Exception("Invalid invoice status");
            }
            
            $sql = "UPDATE invoices SET status = :status, updated_at = NOW() WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            
            return $stmt->execute([
                ':id' => $invoiceId,
                ':status' => $status
            ]);
            
        } catch (Exception $e) {
            error_log("Update invoice status error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get invoice statistics
     */
    public function getInvoiceStatistics($days = 30) {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_invoices,
                        COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_invoices,
                        COUNT(CASE WHEN status = 'overdue' THEN 1 END) as overdue_invoices,
                        COUNT(CASE WHEN due_date < CURRENT_DATE AND status != 'paid' THEN 1 END) as past_due,
                        SUM(total_amount) as total_amount,
                        SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as paid_amount,
                        AVG(total_amount) as avg_amount
                    FROM invoices 
                    WHERE created_at >= NOW() - INTERVAL ':days days'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':days' => $days]);
            
            return $stmt->fetch();
            
        } catch (Exception $e) {
            error_log("Get invoice statistics error: " . $e->getMessage());
            return [];
        }
    }
    
    // Private helper methods
    
    private function generateInvoiceNumber() {
        $prefix = 'INV';
        $date = date('Ymd');
        
        // Get the next sequence number for today
        $sql = "SELECT COUNT(*) + 1 as next_seq FROM invoices WHERE DATE(created_at) = CURRENT_DATE";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        
        $sequence = str_pad($result['next_seq'], 4, '0', STR_PAD_LEFT);
        
        return $prefix . $date . $sequence;
    }
    
    private function getOrderDetails($orderId) {
        $sql = "SELECT o.*, 
                       p.name as product_name, p.name_bn as product_name_bn, p.unit, p.category,
                       b.first_name as buyer_first_name, b.last_name as buyer_last_name, 
                       b.email as buyer_email, b.phone as buyer_phone,
                       f.first_name as farmer_first_name, f.last_name as farmer_last_name, 
                       f.email as farmer_email, f.phone as farmer_phone
                FROM orders o
                LEFT JOIN products p ON o.product_id = p.id
                LEFT JOIN users b ON o.buyer_id = b.id
                LEFT JOIN users f ON o.farmer_id = f.id
                WHERE o.id = :id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $orderId]);
        
        return $stmt->fetch();
    }
    
    private function createInvoiceRecord($orderId, $invoiceNumber, $subtotal, $taxAmount, $totalAmount) {
        $sql = "INSERT INTO invoices (
                    order_id, invoice_number, subtotal, tax_amount, total_amount, 
                    due_date, status, created_at, updated_at
                ) VALUES (
                    :order_id, :invoice_number, :subtotal, :tax_amount, :total_amount,
                    :due_date, 'draft', NOW(), NOW()
                ) RETURNING id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':order_id' => $orderId,
            ':invoice_number' => $invoiceNumber,
            ':subtotal' => $subtotal,
            ':tax_amount' => $taxAmount,
            ':total_amount' => $totalAmount,
            ':due_date' => date('Y-m-d', strtotime('+7 days'))
        ]);
        
        return $stmt->fetch()['id'];
    }
    
    private function updateInvoiceData($invoiceId, $invoiceData) {
        $sql = "UPDATE invoices SET invoice_data = :data, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        $stmt->execute([
            ':id' => $invoiceId,
            ':data' => json_encode($invoiceData)
        ]);
    }
    
    private function generateInvoiceHTML($invoiceData) {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Invoice ' . $invoiceData['invoice_number'] . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .invoice-details { margin-bottom: 20px; }
                .parties { display: flex; justify-content: space-between; margin-bottom: 30px; }
                .party { width: 45%; }
                .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .items-table th { background-color: #f2f2f2; }
                .totals { text-align: right; margin-top: 20px; }
                .total-row { margin: 5px 0; }
                .total-amount { font-weight: bold; font-size: 18px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>KrishiGhor</h1>
                <h2>Invoice</h2>
            </div>
            
            <div class="invoice-details">
                <p><strong>Invoice Number:</strong> ' . $invoiceData['invoice_number'] . '</p>
                <p><strong>Issue Date:</strong> ' . $invoiceData['issue_date'] . '</p>
                <p><strong>Due Date:</strong> ' . $invoiceData['due_date'] . '</p>
            </div>
            
            <div class="parties">
                <div class="party">
                    <h3>Bill To:</h3>
                    <p><strong>' . $invoiceData['buyer']['name'] . '</strong></p>
                    <p>' . $invoiceData['buyer']['email'] . '</p>
                    <p>' . $invoiceData['buyer']['phone'] . '</p>
                    <p>' . $invoiceData['buyer']['address'] . '</p>
                </div>
                <div class="party">
                    <h3>Sold By:</h3>
                    <p><strong>' . $invoiceData['seller']['name'] . '</strong></p>
                    <p>' . $invoiceData['seller']['email'] . '</p>
                    <p>' . $invoiceData['seller']['phone'] . '</p>
                </div>
            </div>
            
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($invoiceData['items'] as $item) {
            $html .= '
                    <tr>
                        <td>' . $item['description'] . '</td>
                        <td>' . $item['quantity'] . ' ' . $item['unit'] . '</td>
                        <td>৳' . number_format($item['unit_price'], 2) . '</td>
                        <td>৳' . number_format($item['total'], 2) . '</td>
                    </tr>';
        }
        
        $html .= '
                </tbody>
            </table>
            
            <div class="totals">
                <div class="total-row">Subtotal: ৳' . number_format($invoiceData['subtotal'], 2) . '</div>
                <div class="total-row">Tax (' . ($invoiceData['tax_rate'] * 100) . '%): ৳' . number_format($invoiceData['tax_amount'], 2) . '</div>
                <div class="total-row total-amount">Total Amount: ৳' . number_format($invoiceData['total_amount'], 2) . '</div>
            </div>
            
            <div style="margin-top: 30px;">
                <p><strong>Payment Method:</strong> ' . ucfirst($invoiceData['payment_method']) . '</p>
                <p><strong>Payment Status:</strong> ' . ucfirst($invoiceData['payment_status']) . '</p>
            </div>
            
            <div style="margin-top: 30px; text-align: center; color: #666;">
                <p>Thank you for using KrishiGhor!</p>
            </div>
        </body>
        </html>';
        
        return $html;
    }
}
