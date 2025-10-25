<?php
/**
 * BalanceRepositoryTest.php
 * Tests unitaires pour BalanceRepository
 *
 * @package    RevenueSharing
 * @subpackage Tests
 */

use PHPUnit\Framework\TestCase;

/**
 * Test class for BalanceRepository
 */
class BalanceRepositoryTest extends TestCase
{
    /** @var DoliDB Mock database instance */
    private $db;

    /** @var BalanceRepository Repository instance */
    private $repository;

    /**
     * Set up test environment before each test
     */
    protected function setUp(): void
    {
        $this->db = new DoliDB();
        $this->repository = new BalanceRepository($this->db);
    }

    /**
     * Test getBalance returns correct balance data
     */
    public function testGetBalanceReturnsValidData()
    {
        // Arrange: Create mock balance data
        $mockBalance = (object)[
            'previous_balance' => 1000.00,
            'year_credits' => 5000.00,
            'year_debits' => 3000.00,
            'year_balance' => 2000.00
        ];

        $this->db->setMockResult(
            $this->buildBalanceQuery(123, 2024),
            $mockBalance
        );

        // Act: Get balance
        $result = $this->repository->getBalance(123, ['year' => 2024]);

        // Assert: Verify results
        $this->assertIsObject($result);
        $this->assertEquals(1000.00, $result->previous_balance);
        $this->assertEquals(5000.00, $result->year_credits);
        $this->assertEquals(3000.00, $result->year_debits);
        $this->assertEquals(2000.00, $result->year_balance);
    }

    /**
     * Test getBalance with no year filter
     */
    public function testGetBalanceWithoutYearFilter()
    {
        // Arrange
        $mockBalance = (object)[
            'previous_balance' => 0.00,
            'year_credits' => 10000.00,
            'year_debits' => 5000.00,
            'year_balance' => 5000.00
        ];

        $this->db->setMockResult(
            $this->buildBalanceQuery(123, 0),
            $mockBalance
        );

        // Act
        $result = $this->repository->getBalance(123);

        // Assert
        $this->assertIsObject($result);
        $this->assertEquals(0.00, $result->previous_balance);
        $this->assertEquals(10000.00, $result->year_credits);
    }

    /**
     * Test getTurnover returns correct turnover data
     */
    public function testGetTurnoverReturnsValidData()
    {
        // Arrange
        $mockTurnover = (object)[
            'ca_reel_ht' => 50000.00,
            'ca_previsionnel_ht' => 20000.00,
            'ca_total_ht' => 70000.00,
            'collaborator_total_ht' => 35000.00,
            'studio_total_ht' => 35000.00,
            'avg_percentage' => 50.00,
            'nb_contrats_reels' => 5,
            'nb_contrats_previsionnel' => 2,
            'nb_factures_clients' => 5
        ];

        $this->db->setMockResult(
            $this->buildTurnoverQuery(123, 2024, true),
            $mockTurnover
        );

        // Act
        $result = $this->repository->getTurnover(123, [
            'year' => 2024,
            'show_previsionnel' => true
        ]);

        // Assert
        $this->assertIsObject($result);
        $this->assertEquals(50000.00, $result->ca_reel_ht);
        $this->assertEquals(20000.00, $result->ca_previsionnel_ht);
        $this->assertEquals(70000.00, $result->ca_total_ht);
        $this->assertEquals(35000.00, $result->collaborator_total_ht);
        $this->assertEquals(50.00, $result->avg_percentage);
    }

    /**
     * Test getTurnover excludes previsionnels when disabled
     */
    public function testGetTurnoverExcludesPrevisionnelsWhenDisabled()
    {
        // Arrange
        $mockTurnover = (object)[
            'ca_reel_ht' => 50000.00,
            'ca_previsionnel_ht' => 0.00,
            'ca_total_ht' => 50000.00,
            'collaborator_total_ht' => 25000.00,
            'studio_total_ht' => 25000.00,
            'avg_percentage' => 50.00,
            'nb_contrats_reels' => 5,
            'nb_contrats_previsionnel' => 0,
            'nb_factures_clients' => 5
        ];

        $this->db->setMockResult(
            $this->buildTurnoverQuery(123, 2024, false),
            $mockTurnover
        );

        // Act
        $result = $this->repository->getTurnover(123, [
            'year' => 2024,
            'show_previsionnel' => false
        ]);

        // Assert
        $this->assertEquals(0.00, $result->ca_previsionnel_ht);
        $this->assertEquals(0, $result->nb_contrats_previsionnel);
    }

    /**
     * Test getStatisticsByType returns array of statistics
     */
    public function testGetStatisticsByTypeReturnsArray()
    {
        // Arrange
        $mockStats = [
            (object)[
                'transaction_type' => 'invoice_payment',
                'total_amount' => 5000.00,
                'count' => 10
            ],
            (object)[
                'transaction_type' => 'salary_deduction',
                'total_amount' => -3000.00,
                'count' => 5
            ]
        ];

        // Mock the query to return an array result
        $sql = $this->buildStatisticsQuery(123, 2024);
        $this->db->setMockResult($sql, $mockStats);

        // Act
        $result = $this->repository->getStatisticsByType(123, ['year' => 2024]);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('invoice_payment', $result[0]->transaction_type);
        $this->assertEquals(5000.00, $result[0]->total_amount);
    }

    /**
     * Test getBalance handles database errors gracefully
     */
    public function testGetBalanceReturnsFalseOnError()
    {
        // Arrange: Set mock to return false (simulate DB error)
        $this->db->setMockResult(
            $this->buildBalanceQuery(999, 2024),
            false
        );

        // Act
        $result = $this->repository->getBalance(999, ['year' => 2024]);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Helper method to build balance query signature
     */
    private function buildBalanceQuery($collaboratorId, $year)
    {
        // Simplified query signature for mocking
        return "balance_query_{$collaboratorId}_{$year}";
    }

    /**
     * Helper method to build turnover query signature
     */
    private function buildTurnoverQuery($collaboratorId, $year, $showPrevisionnel)
    {
        $prev = $showPrevisionnel ? '1' : '0';
        return "turnover_query_{$collaboratorId}_{$year}_{$prev}";
    }

    /**
     * Helper method to build statistics query signature
     */
    private function buildStatisticsQuery($collaboratorId, $year)
    {
        return "statistics_query_{$collaboratorId}_{$year}";
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
