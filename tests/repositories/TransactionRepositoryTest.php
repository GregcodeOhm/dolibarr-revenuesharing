<?php
/**
 * TransactionRepositoryTest.php
 * Tests unitaires pour TransactionRepository
 *
 * @package    RevenueSharing
 * @subpackage Tests
 */

use PHPUnit\Framework\TestCase;

/**
 * Test class for TransactionRepository
 */
class TransactionRepositoryTest extends TestCase
{
    /** @var DoliDB Mock database instance */
    private $db;

    /** @var TransactionRepository Repository instance */
    private $repository;

    /**
     * Set up test environment before each test
     */
    protected function setUp(): void
    {
        $this->db = new DoliDB();
        $this->repository = new TransactionRepository($this->db);
    }

    /**
     * Test findByCollaborator returns paginated results
     */
    public function testFindByCollaboratorReturnsPaginatedResults()
    {
        // Arrange: Create mock transaction data
        $mockTransactions = [
            (object)[
                'rowid' => 1,
                'fk_collaborator' => 123,
                'amount' => 1000.00,
                'transaction_type' => 'invoice_payment',
                'transaction_date' => '2024-01-15'
            ],
            (object)[
                'rowid' => 2,
                'fk_collaborator' => 123,
                'amount' => -500.00,
                'transaction_type' => 'salary_deduction',
                'transaction_date' => '2024-01-20'
            ]
        ];

        // Mock both count and data queries
        $this->db->setMockResult('count_transactions_123', 25);
        $this->db->setMockResult('transactions_123_page1', $mockTransactions);

        // Act: Get first page
        $result = $this->repository->findByCollaborator(123, [
            'page' => 1,
            'limit' => 50
        ]);

        // Assert: Verify paginated structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('transactions', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('pages', $result);
        $this->assertArrayHasKey('current_page', $result);
        $this->assertArrayHasKey('per_page', $result);
    }

    /**
     * Test findByCollaborator with year filter
     */
    public function testFindByCollaboratorWithYearFilter()
    {
        // Arrange
        $mockTransactions = [
            (object)[
                'rowid' => 1,
                'amount' => 2000.00,
                'transaction_date' => '2024-03-15'
            ]
        ];

        $this->db->setMockResult('count_transactions_123_2024', 1);
        $this->db->setMockResult('transactions_123_2024_page1', $mockTransactions);

        // Act
        $result = $this->repository->findByCollaborator(123, [
            'year' => 2024,
            'page' => 1,
            'limit' => 50
        ]);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('transactions', $result);
    }

    /**
     * Test findByCollaborator with type filter
     */
    public function testFindByCollaboratorWithTypeFilter()
    {
        // Arrange
        $mockTransactions = [
            (object)[
                'rowid' => 1,
                'transaction_type' => 'invoice_payment',
                'amount' => 1500.00
            ]
        ];

        $this->db->setMockResult('count_transactions_123_invoice', 5);
        $this->db->setMockResult('transactions_123_invoice_page1', $mockTransactions);

        // Act
        $result = $this->repository->findByCollaborator(123, [
            'type' => 'invoice_payment',
            'page' => 1,
            'limit' => 50
        ]);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('transactions', $result);
    }

    /**
     * Test findByCollaborator excludes previsionnels when disabled
     */
    public function testFindByCollaboratorExcludesPrevisionnels()
    {
        // Arrange
        $mockTransactions = [
            (object)[
                'rowid' => 1,
                'type_contrat' => 'reel',
                'amount' => 1000.00
            ]
        ];

        $this->db->setMockResult('count_transactions_123_no_prev', 3);
        $this->db->setMockResult('transactions_123_no_prev_page1', $mockTransactions);

        // Act
        $result = $this->repository->findByCollaborator(123, [
            'show_previsionnel' => false,
            'page' => 1,
            'limit' => 50
        ]);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('transactions', $result);
    }

    /**
     * Test findById returns single transaction
     */
    public function testFindByIdReturnsTransaction()
    {
        // Arrange
        $mockTransaction = (object)[
            'rowid' => 42,
            'fk_collaborator' => 123,
            'amount' => 2500.00,
            'transaction_type' => 'invoice_payment',
            'description' => 'Test payment',
            'transaction_date' => '2024-02-15',
            'status' => 1
        ];

        $this->db->setMockResult('transaction_42', $mockTransaction);

        // Act
        $result = $this->repository->findById(42);

        // Assert
        $this->assertIsObject($result);
        $this->assertEquals(42, $result->rowid);
        $this->assertEquals(2500.00, $result->amount);
        $this->assertEquals('invoice_payment', $result->transaction_type);
    }

    /**
     * Test findById returns false when not found
     */
    public function testFindByIdReturnsFalseWhenNotFound()
    {
        // Arrange
        $this->db->setMockResult('transaction_999', false);

        // Act
        $result = $this->repository->findById(999);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test create method validates required fields
     */
    public function testCreateValidatesRequiredFields()
    {
        // Act: Try to create with missing required fields
        $result = $this->repository->create([]);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test create method with valid data
     */
    public function testCreateWithValidData()
    {
        // Arrange: Mock successful insert returning ID
        $this->db->setMockResult('insert_transaction', 100);

        // Act
        $result = $this->repository->create([
            'fk_collaborator' => 123,
            'transaction_date' => '2024-03-01',
            'amount' => 1500.00,
            'transaction_type' => 'invoice_payment',
            'description' => 'Test transaction',
            'status' => 1
        ]);

        // Assert: Should return the new transaction ID
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    /**
     * Test update method with valid data
     */
    public function testUpdateWithValidData()
    {
        // Arrange
        $this->db->setMockResult('update_transaction_42', true);

        // Act
        $result = $this->repository->update(42, [
            'amount' => 2000.00,
            'description' => 'Updated description'
        ]);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test delete method
     */
    public function testDeleteTransaction()
    {
        // Arrange
        $this->db->setMockResult('delete_transaction_42', true);

        // Act
        $result = $this->repository->delete(42);

        // Assert
        $this->assertTrue($result);
    }

    /**
     * Test pagination calculates pages correctly
     */
    public function testPaginationCalculatesCorrectly()
    {
        // Arrange: 125 total transactions, 50 per page = 3 pages
        $mockTransactions = [];
        $this->db->setMockResult('count_transactions_123', 125);
        $this->db->setMockResult('transactions_123_page1', $mockTransactions);

        // Act
        $result = $this->repository->findByCollaborator(123, [
            'page' => 1,
            'limit' => 50
        ]);

        // Assert
        $this->assertEquals(125, $result['total']);
        $this->assertEquals(3, $result['pages']); // ceil(125/50) = 3
        $this->assertEquals(1, $result['current_page']);
        $this->assertEquals(50, $result['per_page']);
    }

    /**
     * Test multiple filters combined
     */
    public function testMultipleFiltersCombined()
    {
        // Arrange
        $mockTransactions = [
            (object)[
                'rowid' => 1,
                'transaction_type' => 'invoice_payment',
                'amount' => 1000.00,
                'transaction_date' => '2024-05-15'
            ]
        ];

        $this->db->setMockResult('count_transactions_123_multi', 1);
        $this->db->setMockResult('transactions_123_multi_page1', $mockTransactions);

        // Act: Combine year, type, and previsionnel filters
        $result = $this->repository->findByCollaborator(123, [
            'year' => 2024,
            'type' => 'invoice_payment',
            'show_previsionnel' => false,
            'page' => 1,
            'limit' => 50
        ]);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('transactions', $result);
    }

    /**
     * Tear down test environment after each test
     */
    protected function tearDown(): void
    {
        $this->db = null;
        $this->repository = null;
    }
}
