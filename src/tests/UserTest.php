<?php

use PHPUnit\Framework\TestCase;

/**
 * User Model Test Cases
 */
class UserTest extends TestCase
{
    private $userModel;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Load configuration and dependencies
        require_once __DIR__ . '/../src/config/app.php';
        require_once __DIR__ . '/../src/config/database.php';
        require_once __DIR__ . '/../src/models/User.php';
        
        $this->userModel = new User();
    }
    
    public function testCreateUser()
    {
        $userData = [
            'email' => 'test@example.com',
            'password' => 'testpassword123',
            'first_name' => 'Test',
            'last_name' => 'User',
            'role' => 'farmer',
            'phone' => '+8801712345678',
            'language' => 'bn'
        ];
        
        $userId = $this->userModel->create($userData);
        
        $this->assertIsNumeric($userId);
        $this->assertGreaterThan(0, $userId);
        
        // Clean up
        $this->userModel->softDelete($userId);
    }
    
    public function testFindUserByEmail()
    {
        // Create a test user first
        $userData = [
            'email' => 'findtest@example.com',
            'password' => 'testpassword123',
            'first_name' => 'Find',
            'last_name' => 'Test',
            'role' => 'farmer'
        ];
        
        $userId = $this->userModel->create($userData);
        
        // Find the user by email
        $user = $this->userModel->findByEmail('findtest@example.com');
        
        $this->assertNotFalse($user);
        $this->assertEquals('findtest@example.com', $user['email']);
        $this->assertEquals('Find', $user['first_name']);
        $this->assertEquals('farmer', $user['role']);
        
        // Clean up
        $this->userModel->softDelete($userId);
    }
    
    public function testPasswordVerification()
    {
        $password = 'testpassword123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $isValid = $this->userModel->verifyPassword($password, $hashedPassword);
        
        $this->assertTrue($isValid);
        
        $isInvalid = $this->userModel->verifyPassword('wrongpassword', $hashedPassword);
        
        $this->assertFalse($isInvalid);
    }
    
    public function testEmailExists()
    {
        // Create a test user
        $userData = [
            'email' => 'exists@example.com',
            'password' => 'testpassword123',
            'first_name' => 'Exists',
            'last_name' => 'Test',
            'role' => 'farmer'
        ];
        
        $userId = $this->userModel->create($userData);
        
        // Test email exists
        $exists = $this->userModel->emailExists('exists@example.com');
        $this->assertTrue($exists);
        
        // Test email doesn't exist
        $notExists = $this->userModel->emailExists('notexists@example.com');
        $this->assertFalse($notExists);
        
        // Clean up
        $this->userModel->softDelete($userId);
    }
    
    public function testUpdateUser()
    {
        // Create a test user
        $userData = [
            'email' => 'update@example.com',
            'password' => 'testpassword123',
            'first_name' => 'Update',
            'last_name' => 'Test',
            'role' => 'farmer'
        ];
        
        $userId = $this->userModel->create($userData);
        
        // Update user data
        $updateData = [
            'first_name' => 'Updated',
            'phone' => '+8801912345678',
            'language' => 'en'
        ];
        
        $updated = $this->userModel->update($userId, $updateData);
        $this->assertTrue($updated);
        
        // Verify the update
        $user = $this->userModel->findById($userId);
        $this->assertEquals('Updated', $user['first_name']);
        $this->assertEquals('+8801912345678', $user['phone']);
        $this->assertEquals('en', $user['language']);
        
        // Clean up
        $this->userModel->softDelete($userId);
    }
    
    public function testGetUsersByRole()
    {
        // Create test users with different roles
        $farmers = [];
        for ($i = 0; $i < 3; $i++) {
            $userData = [
                'email' => "farmer{$i}@test.com",
                'password' => 'testpassword123',
                'first_name' => "Farmer{$i}",
                'last_name' => 'Test',
                'role' => 'farmer'
            ];
            $farmers[] = $this->userModel->create($userData);
        }
        
        $buyers = [];
        for ($i = 0; $i < 2; $i++) {
            $userData = [
                'email' => "buyer{$i}@test.com",
                'password' => 'testpassword123',
                'first_name' => "Buyer{$i}",
                'last_name' => 'Test',
                'role' => 'buyer'
            ];
            $buyers[] = $this->userModel->create($userData);
        }
        
        // Test getting farmers
        $farmerUsers = $this->userModel->getByRole('farmer', 10);
        $this->assertGreaterThanOrEqual(3, count($farmerUsers));
        
        // Test getting buyers
        $buyerUsers = $this->userModel->getByRole('buyer', 10);
        $this->assertGreaterThanOrEqual(2, count($buyerUsers));
        
        // Clean up
        foreach ($farmers as $userId) {
            $this->userModel->softDelete($userId);
        }
        foreach ($buyers as $userId) {
            $this->userModel->softDelete($userId);
        }
    }
    
    public function testInvalidUserCreation()
    {
        // Test with missing required fields
        $invalidData = [
            'email' => 'invalid@test.com',
            // Missing password, first_name, last_name
        ];
        
        $this->expectException(Exception::class);
        $this->userModel->create($invalidData);
    }
    
    public function testSoftDelete()
    {
        // Create a test user
        $userData = [
            'email' => 'delete@example.com',
            'password' => 'testpassword123',
            'first_name' => 'Delete',
            'last_name' => 'Test',
            'role' => 'farmer'
        ];
        
        $userId = $this->userModel->create($userData);
        
        // Soft delete the user
        $deleted = $this->userModel->softDelete($userId);
        $this->assertTrue($deleted);
        
        // Verify user cannot be found by email (because status is 'deleted')
        $user = $this->userModel->findByEmail('delete@example.com');
        $this->assertFalse($user);
        
        // But user still exists in database with 'deleted' status
        $userById = $this->userModel->findById($userId);
        $this->assertFalse($userById); // findById also filters by active status
    }
}
