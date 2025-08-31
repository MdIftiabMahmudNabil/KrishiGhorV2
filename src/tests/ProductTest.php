<?php

use PHPUnit\Framework\TestCase;

/**
 * Product Model Test Cases
 */
class ProductTest extends TestCase
{
    private $productModel;
    private $userModel;
    private $testFarmerId;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Load configuration and dependencies
        require_once __DIR__ . '/../src/config/app.php';
        require_once __DIR__ . '/../src/config/database.php';
        require_once __DIR__ . '/../src/models/User.php';
        require_once __DIR__ . '/../src/models/Product.php';
        
        $this->userModel = new User();
        $this->productModel = new Product();
        
        // Create a test farmer
        $farmerData = [
            'email' => 'testfarmer@example.com',
            'password' => 'testpassword123',
            'first_name' => 'Test',
            'last_name' => 'Farmer',
            'role' => 'farmer'
        ];
        
        $this->testFarmerId = $this->userModel->create($farmerData);
    }
    
    protected function tearDown(): void
    {
        // Clean up test farmer
        if ($this->testFarmerId) {
            $this->userModel->softDelete($this->testFarmerId);
        }
        
        parent::tearDown();
    }
    
    public function testCreateProduct()
    {
        $productData = [
            'farmer_id' => $this->testFarmerId,
            'name' => 'Test Rice',
            'name_bn' => 'পরীক্ষার চাল',
            'category' => 'rice',
            'variety' => 'Basmati',
            'quantity' => 100.00,
            'unit' => 'kg',
            'price_per_unit' => 60.00,
            'description' => 'High quality test rice',
            'description_bn' => 'উচ্চমানের পরীক্ষার চাল',
            'location' => 'Test District',
            'quality_grade' => 'A',
            'organic_certified' => true
        ];
        
        $productId = $this->productModel->create($productData);
        
        $this->assertIsNumeric($productId);
        $this->assertGreaterThan(0, $productId);
        
        // Verify the product was created correctly
        $product = $this->productModel->findById($productId);
        $this->assertNotFalse($product);
        $this->assertEquals('Test Rice', $product['name']);
        $this->assertEquals('rice', $product['category']);
        $this->assertEquals(100.00, $product['quantity']);
        $this->assertEquals(60.00, $product['price_per_unit']);
        $this->assertTrue($product['organic_certified']);
        
        // Clean up
        $this->productModel->softDelete($productId);
    }
    
    public function testFindProductById()
    {
        // Create a test product
        $productData = [
            'farmer_id' => $this->testFarmerId,
            'name' => 'Find Test Product',
            'category' => 'tomato',
            'quantity' => 50.00,
            'unit' => 'kg',
            'price_per_unit' => 45.00,
            'location' => 'Test Location'
        ];
        
        $productId = $this->productModel->create($productData);
        
        // Find the product
        $product = $this->productModel->findById($productId);
        
        $this->assertNotFalse($product);
        $this->assertEquals('Find Test Product', $product['name']);
        $this->assertEquals('tomato', $product['category']);
        $this->assertEquals($this->testFarmerId, $product['farmer_id']);
        
        // Check if farmer information is included
        $this->assertArrayHasKey('first_name', $product);
        $this->assertEquals('Test', $product['first_name']);
        
        // Clean up
        $this->productModel->softDelete($productId);
    }
    
    public function testGetProductsByFarmer()
    {
        // Create multiple test products for the farmer
        $products = [];
        for ($i = 0; $i < 3; $i++) {
            $productData = [
                'farmer_id' => $this->testFarmerId,
                'name' => "Test Product {$i}",
                'category' => 'vegetable',
                'quantity' => 50.00 + $i,
                'unit' => 'kg',
                'price_per_unit' => 30.00 + $i,
                'location' => 'Test Location'
            ];
            $products[] = $this->productModel->create($productData);
        }
        
        // Get products by farmer
        $farmerProducts = $this->productModel->getByFarmer($this->testFarmerId);
        
        $this->assertGreaterThanOrEqual(3, count($farmerProducts));
        
        // Verify all products belong to the test farmer
        foreach ($farmerProducts as $product) {
            $this->assertEquals($this->testFarmerId, $product['farmer_id']);
        }
        
        // Clean up
        foreach ($products as $productId) {
            $this->productModel->softDelete($productId);
        }
    }
    
    public function testSearchProducts()
    {
        // Create test products with different attributes
        $products = [
            [
                'farmer_id' => $this->testFarmerId,
                'name' => 'Organic Tomato',
                'category' => 'tomato',
                'quantity' => 100.00,
                'unit' => 'kg',
                'price_per_unit' => 50.00,
                'location' => 'Dhaka',
                'organic_certified' => true
            ],
            [
                'farmer_id' => $this->testFarmerId,
                'name' => 'Regular Potato',
                'category' => 'potato',
                'quantity' => 200.00,
                'unit' => 'kg',
                'price_per_unit' => 30.00,
                'location' => 'Chittagong',
                'organic_certified' => false
            ]
        ];
        
        $productIds = [];
        foreach ($products as $productData) {
            $productIds[] = $this->productModel->create($productData);
        }
        
        // Test search by category
        $tomatoProducts = $this->productModel->search(['category' => 'tomato']);
        $this->assertGreaterThanOrEqual(1, count($tomatoProducts));
        
        // Test search by location
        $dhakaProducts = $this->productModel->search(['location' => 'Dhaka']);
        $this->assertGreaterThanOrEqual(1, count($dhakaProducts));
        
        // Test search by price range
        $expensiveProducts = $this->productModel->search(['min_price' => 40.00]);
        $this->assertGreaterThanOrEqual(1, count($expensiveProducts));
        
        // Test search for organic products only
        $organicProducts = $this->productModel->search(['organic_only' => true]);
        $this->assertGreaterThanOrEqual(1, count($organicProducts));
        
        // Clean up
        foreach ($productIds as $productId) {
            $this->productModel->softDelete($productId);
        }
    }
    
    public function testUpdateProduct()
    {
        // Create a test product
        $productData = [
            'farmer_id' => $this->testFarmerId,
            'name' => 'Update Test Product',
            'category' => 'rice',
            'quantity' => 100.00,
            'unit' => 'kg',
            'price_per_unit' => 55.00,
            'location' => 'Original Location'
        ];
        
        $productId = $this->productModel->create($productData);
        
        // Update the product
        $updateData = [
            'name' => 'Updated Test Product',
            'price_per_unit' => 65.00,
            'location' => 'Updated Location',
            'organic_certified' => true
        ];
        
        $updated = $this->productModel->update($productId, $updateData);
        $this->assertTrue($updated);
        
        // Verify the updates
        $product = $this->productModel->findById($productId);
        $this->assertEquals('Updated Test Product', $product['name']);
        $this->assertEquals(65.00, $product['price_per_unit']);
        $this->assertEquals('Updated Location', $product['location']);
        $this->assertTrue($product['organic_certified']);
        
        // Clean up
        $this->productModel->softDelete($productId);
    }
    
    public function testUpdateQuantity()
    {
        // Create a test product
        $productData = [
            'farmer_id' => $this->testFarmerId,
            'name' => 'Quantity Test Product',
            'category' => 'potato',
            'quantity' => 100.00,
            'unit' => 'kg',
            'price_per_unit' => 35.00
        ];
        
        $productId = $this->productModel->create($productData);
        
        // Update quantity
        $updated = $this->productModel->updateQuantity($productId, 75.00);
        $this->assertTrue($updated);
        
        // Verify the quantity update
        $product = $this->productModel->findById($productId);
        $this->assertEquals(75.00, $product['quantity']);
        
        // Clean up
        $this->productModel->softDelete($productId);
    }
    
    public function testGetCategories()
    {
        // Create products in different categories
        $categories = ['rice', 'tomato', 'potato', 'onion'];
        $productIds = [];
        
        foreach ($categories as $category) {
            $productData = [
                'farmer_id' => $this->testFarmerId,
                'name' => "Test {$category}",
                'category' => $category,
                'quantity' => 100.00,
                'unit' => 'kg',
                'price_per_unit' => 40.00,
                'status' => 'available'
            ];
            $productIds[] = $this->productModel->create($productData);
        }
        
        // Get categories
        $categoryStats = $this->productModel->getCategories();
        
        $this->assertIsArray($categoryStats);
        $this->assertGreaterThan(0, count($categoryStats));
        
        // Verify structure
        foreach ($categoryStats as $stat) {
            $this->assertArrayHasKey('category', $stat);
            $this->assertArrayHasKey('count', $stat);
        }
        
        // Clean up
        foreach ($productIds as $productId) {
            $this->productModel->softDelete($productId);
        }
    }
    
    public function testSoftDelete()
    {
        // Create a test product
        $productData = [
            'farmer_id' => $this->testFarmerId,
            'name' => 'Delete Test Product',
            'category' => 'test',
            'quantity' => 50.00,
            'unit' => 'kg',
            'price_per_unit' => 25.00
        ];
        
        $productId = $this->productModel->create($productData);
        
        // Soft delete the product
        $deleted = $this->productModel->softDelete($productId);
        $this->assertTrue($deleted);
        
        // Verify product is not found (because status is 'deleted')
        $product = $this->productModel->findById($productId);
        $this->assertFalse($product);
    }
}
