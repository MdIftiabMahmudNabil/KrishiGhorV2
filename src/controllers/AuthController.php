<?php
/**
 * Authentication Controller
 * Handles user registration, login, and password reset
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/SecurityService.php';

class AuthController {
    private $userModel;
    private $securityService;
    
    public function __construct() {
        $this->userModel = new User();
        $this->securityService = new SecurityService();
    }
    
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $required = ['email', 'password', 'first_name', 'last_name', 'role'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->sendResponse(400, ['error' => "Field '{$field}' is required"]);
                return;
            }
        }
        
        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $this->sendResponse(400, ['error' => 'Invalid email format']);
            return;
        }
        
        // Validate role
        $allowedRoles = ['farmer', 'buyer', 'admin'];
        if (!in_array($data['role'], $allowedRoles)) {
            $this->sendResponse(400, ['error' => 'Invalid role']);
            return;
        }
        
        // Check if email already exists
        if ($this->userModel->emailExists($data['email'])) {
            $this->sendResponse(409, ['error' => 'Email already registered']);
            return;
        }
        
        // Check for duplicate accounts
        $duplicates = $this->securityService->checkDuplicateAccounts(
            $data['email'], 
            $data['phone'] ?? null, 
            $data['first_name'], 
            $data['last_name']
        );
        
        if (!empty($duplicates)) {
            $this->sendResponse(409, [
                'error' => 'Similar account found',
                'potential_duplicates' => count($duplicates),
                'suggestion' => 'Please check if you already have an account'
            ]);
            return;
        }
        
        // Validate password strength
        if (strlen($data['password']) < 6) {
            $this->sendResponse(400, ['error' => 'Password must be at least 6 characters long']);
            return;
        }
        
        try {
            $userId = $this->userModel->create($data);
            
            if ($userId) {
                $user = $this->userModel->findById($userId);
                unset($user['password']); // Remove password from response
                
                $this->sendResponse(201, [
                    'message' => 'User registered successfully',
                    'user' => $user,
                    'token' => $this->generateJWT($user)
                ]);
            } else {
                $this->sendResponse(500, ['error' => 'Failed to create user']);
            }
        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['email']) || empty($data['password'])) {
            $this->sendResponse(400, ['error' => 'Email and password are required']);
            return;
        }
        
        try {
            $user = $this->userModel->findByEmail($data['email']);
            
            if (!$user || !$this->userModel->verifyPassword($data['password'], $user['password'])) {
                $this->sendResponse(401, ['error' => 'Invalid credentials']);
                return;
            }
            
            // Log login attempt and check for anomalies
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $securityCheck = $this->securityService->logLoginAttempt(
                $user['id'], $ipAddress, $userAgent, true
            );
            
            // Update last login
            $this->userModel->updateLastLogin($user['id']);
            
            unset($user['password']); // Remove password from response
            
            $response = [
                'message' => 'Login successful',
                'user' => $user,
                'token' => $this->generateJWT($user)
            ];
            
            // Add security warnings if needed
            if ($securityCheck['is_suspicious']) {
                $response['security_warning'] = [
                    'message' => 'Unusual login detected from new location/device',
                    'anomaly_score' => $securityCheck['anomaly_score'],
                    'location' => $securityCheck['location']
                ];
            }
            
            if ($securityCheck['requires_2fa']) {
                $response['requires_2fa'] = true;
                $response['message'] = 'Additional verification required';
            }
            
            $this->sendResponse(200, $response);
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    public function logout() {
        // For JWT, logout is handled client-side by removing the token
        // Here we could implement token blacklisting if needed
        
        $this->sendResponse(200, ['message' => 'Logout successful']);
    }
    
    public function resetPassword() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['email'])) {
            $this->sendResponse(400, ['error' => 'Email is required']);
            return;
        }
        
        try {
            $user = $this->userModel->findByEmail($data['email']);
            
            if (!$user) {
                // Don't reveal if email exists or not for security
                $this->sendResponse(200, ['message' => 'If email exists, reset instructions have been sent']);
                return;
            }
            
            // Generate reset token (in production, save this to database with expiry)
            $resetToken = bin2hex(random_bytes(32));
            
            // TODO: Send email with reset link
            // For now, return the token (in production, only send via email)
            
            $this->sendResponse(200, [
                'message' => 'Password reset instructions sent',
                'reset_token' => $resetToken // Remove this in production
            ]);
            
        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    public function changePassword() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        // Verify JWT token
        $user = $this->getCurrentUser();
        if (!$user) {
            $this->sendResponse(401, ['error' => 'Unauthorized']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['current_password']) || empty($data['new_password'])) {
            $this->sendResponse(400, ['error' => 'Current password and new password are required']);
            return;
        }
        
        try {
            $userRecord = $this->userModel->findById($user['id']);
            
            if (!$this->userModel->verifyPassword($data['current_password'], $userRecord['password'])) {
                $this->sendResponse(400, ['error' => 'Current password is incorrect']);
                return;
            }
            
            if (strlen($data['new_password']) < 6) {
                $this->sendResponse(400, ['error' => 'New password must be at least 6 characters long']);
                return;
            }
            
            $updated = $this->userModel->update($user['id'], ['password' => $data['new_password']]);
            
            if ($updated) {
                $this->sendResponse(200, ['message' => 'Password changed successfully']);
            } else {
                $this->sendResponse(500, ['error' => 'Failed to update password']);
            }
            
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    public function getProfile() {
        $user = $this->getCurrentUser();
        if (!$user) {
            $this->sendResponse(401, ['error' => 'Unauthorized']);
            return;
        }
        
        try {
            $userRecord = $this->userModel->findById($user['id']);
            unset($userRecord['password']);
            
            $this->sendResponse(200, ['user' => $userRecord]);
            
        } catch (Exception $e) {
            error_log("Get profile error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    public function updateProfile() {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->sendResponse(405, ['error' => 'Method not allowed']);
            return;
        }
        
        $user = $this->getCurrentUser();
        if (!$user) {
            $this->sendResponse(401, ['error' => 'Unauthorized']);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $allowedFields = ['first_name', 'last_name', 'phone', 'language', 'region'];
            $updateData = [];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }
            
            if (empty($updateData)) {
                $this->sendResponse(400, ['error' => 'No valid fields to update']);
                return;
            }
            
            $updated = $this->userModel->update($user['id'], $updateData);
            
            if ($updated) {
                $userRecord = $this->userModel->findById($user['id']);
                unset($userRecord['password']);
                
                $this->sendResponse(200, [
                    'message' => 'Profile updated successfully',
                    'user' => $userRecord
                ]);
            } else {
                $this->sendResponse(500, ['error' => 'Failed to update profile']);
            }
            
        } catch (Exception $e) {
            error_log("Update profile error: " . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }
    
    private function generateJWT($user) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + AppConfig::get('auth.jwt_expire', 3600)
        ]);
        
        $headerEncoded = base64url_encode($header);
        $payloadEncoded = base64url_encode($payload);
        
        $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, AppConfig::get('auth.jwt_secret'), true);
        $signatureEncoded = base64url_encode($signature);
        
        return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
    }
    
    private function getCurrentUser() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return null;
        }
        
        $token = $matches[1];
        return $this->verifyJWT($token);
    }
    
    private function verifyJWT($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        
        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
        
        $signature = base64url_decode($signatureEncoded);
        $expectedSignature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, AppConfig::get('auth.jwt_secret'), true);
        
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }
        
        $payload = json_decode(base64url_decode($payloadEncoded), true);
        
        if (!$payload || $payload['exp'] < time()) {
            return null;
        }
        
        return $payload;
    }
    
    private function sendResponse($statusCode, $data) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}

// Helper function for base64 URL encoding
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Helper function for base64 URL decoding
function base64url_decode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}
