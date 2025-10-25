<?php
/**
 * SalaryDeclarationRepositoryTest.php
 * Tests unitaires pour SalaryDeclarationRepository
 *
 * @package    RevenueSharing
 * @subpackage Tests
 */

use PHPUnit\Framework\TestCase;

/**
 * Test class for SalaryDeclarationRepository
 */
class SalaryDeclarationRepositoryTest extends TestCase
{
    /** @var DoliDB Mock database instance */
    private $db;

    /** @var SalaryDeclarationRepository Repository instance */
    private $repository;

    /**
     * Set up test environment before each test
     */
    protected function setUp(): void
    {
        $this->db = new DoliDB();
        $this->repository = new SalaryDeclarationRepository($this->db);
    }

    /**
     * Test getSalaryStatistics returns correct statistics
     */
    public function testGetSalaryStatisticsReturnsValidData()
    {
        // Arrange: Create mock salary statistics
        $mockStats = (object)[
            'nb_brouillons' => 3,
            'nb_valides' => 5,
            'nb_payes' => 10,
            'montant_brouillons' => 1500.00,
            'montant_valides' => 2500.00,
            'montant_payes' => 5000.00,
            'montant_previsionnel' => 4000.00,
            'jours_brouillons' => 15.0,
            'jours_valides' => 25.0,
            'jours_payes' => 50.0
        ];

        $this->db->setMockResult(
            $this->buildStatisticsQuery(123, 2024),
            $mockStats
        );

        // Act: Get salary statistics
        $result = $this->repository->getSalaryStatistics(123, ['year' => 2024]);

        // Assert: Verify all statistics
        $this->assertIsObject($result);
        $this->assertEquals(3, $result->nb_brouillons);
        $this->assertEquals(5, $result->nb_valides);
        $this->assertEquals(10, $result->nb_payes);
        $this->assertEquals(1500.00, $result->montant_brouillons);
        $this->assertEquals(2500.00, $result->montant_valides);
        $this->assertEquals(5000.00, $result->montant_payes);
        $this->assertEquals(4000.00, $result->montant_previsionnel);
        $this->assertEquals(15.0, $result->jours_brouillons);
        $this->assertEquals(25.0, $result->jours_valides);
        $this->assertEquals(50.0, $result->jours_payes);
    }

    /**
     * Test getSalaryStatistics with no year filter
     */
    public function testGetSalaryStatisticsWithoutYearFilter()
    {
        // Arrange
        $mockStats = (object)[
            'nb_brouillons' => 5,
            'nb_valides' => 8,
            'nb_payes' => 15,
            'montant_brouillons' => 2000.00,
            'montant_valides' => 3000.00,
            'montant_payes' => 7000.00,
            'montant_previsionnel' => 5000.00,
            'jours_brouillons' => 20.0,
            'jours_valides' => 30.0,
            'jours_payes' => 70.0
        ];

        $this->db->setMockResult(
            $this->buildStatisticsQuery(123, 0),
            $mockStats
        );

        // Act
        $result = $this->repository->getSalaryStatistics(123);

        // Assert
        $this->assertIsObject($result);
        $this->assertEquals(5, $result->nb_brouillons);
        $this->assertEquals(5000.00, $result->montant_previsionnel);
    }

    /**
     * Test getSalaryStatistics with zero values
     */
    public function testGetSalaryStatisticsWithZeroValues()
    {
        // Arrange: No declarations exist
        $mockStats = (object)[
            'nb_brouillons' => 0,
            'nb_valides' => 0,
            'nb_payes' => 0,
            'montant_brouillons' => 0.00,
            'montant_valides' => 0.00,
            'montant_payes' => 0.00,
            'montant_previsionnel' => 0.00,
            'jours_brouillons' => 0.0,
            'jours_valides' => 0.0,
            'jours_payes' => 0.0
        ];

        $this->db->setMockResult(
            $this->buildStatisticsQuery(123, 2024),
            $mockStats
        );

        // Act
        $result = $this->repository->getSalaryStatistics(123, ['year' => 2024]);

        // Assert
        $this->assertIsObject($result);
        $this->assertEquals(0, $result->nb_brouillons);
        $this->assertEquals(0.00, $result->montant_previsionnel);
    }

    /**
     * Test findByCollaborator returns list of declarations
     */
    public function testFindByCollaboratorReturnsDeclarations()
    {
        // Arrange
        $mockDeclarations = [
            (object)[
                'rowid' => 1,
                'fk_collaborator' => 123,
                'declaration_year' => 2024,
                'declaration_month' => 3,
                'status' => 1,
                'total_days' => 15.0,
                'solde_utilise' => 1500.00
            ],
            (object)[
                'rowid' => 2,
                'fk_collaborator' => 123,
                'declaration_year' => 2024,
                'declaration_month' => 2,
                'status' => 2,
                'total_days' => 20.0,
                'solde_utilise' => 2000.00
            ]
        ];

        $this->db->setMockResult(
            $this->buildDeclarationsQuery(123, 2024),
            $mockDeclarations
        );

        // Act
        $result = $this->repository->findByCollaborator(123, ['year' => 2024]);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(1, $result[0]->rowid);
        $this->assertEquals(2024, $result[0]->declaration_year);
    }

    /**
     * Test findByCollaborator with status filter
     */
    public function testFindByCollaboratorWithStatusFilter()
    {
        // Arrange: Only brouillons (status = 1)
        $mockDeclarations = [
            (object)[
                'rowid' => 1,
                'status' => 1,
                'total_days' => 15.0
            ]
        ];

        $this->db->setMockResult(
            $this->buildDeclarationsQuery(123, 0, 1),
            $mockDeclarations
        );

        // Act
        $result = $this->repository->findByCollaborator(123, ['status' => 1]);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals(1, $result[0]->status);
    }

    /**
     * Test findByCollaborator with custom sorting
     */
    public function testFindByCollaboratorWithCustomSorting()
    {
        // Arrange
        $mockDeclarations = [
            (object)['rowid' => 2, 'total_days' => 25.0],
            (object)['rowid' => 1, 'total_days' => 15.0]
        ];

        $this->db->setMockResult(
            $this->buildDeclarationsQuery(123, 0, 0, 'total_days', 'DESC'),
            $mockDeclarations
        );

        // Act
        $result = $this->repository->findByCollaborator(123, [
            'order_by' => 'total_days',
            'order_dir' => 'DESC'
        ]);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        // First result should have more days
        $this->assertEquals(25.0, $result[0]->total_days);
    }

    /**
     * Test findByCollaborator prevents SQL injection in order_by
     */
    public function testFindByCollaboratorSanitizesOrderBy()
    {
        // Arrange: Try to inject malicious SQL
        $mockDeclarations = [];

        $this->db->setMockResult(
            $this->buildDeclarationsQuery(123, 0, 0, 'declaration_year', 'DESC'),
            $mockDeclarations
        );

        // Act: Try to inject SQL in order_by
        $result = $this->repository->findByCollaborator(123, [
            'order_by' => 'malicious_field; DROP TABLE',
            'order_dir' => 'DESC; DELETE FROM'
        ]);

        // Assert: Should use default safe values
        $this->assertIsArray($result);
    }

    /**
     * Test findById returns single declaration
     */
    public function testFindByIdReturnsDeclaration()
    {
        // Arrange
        $mockDeclaration = (object)[
            'rowid' => 42,
            'fk_collaborator' => 123,
            'declaration_year' => 2024,
            'declaration_month' => 5,
            'status' => 2,
            'total_days' => 18.0,
            'solde_utilise' => 1800.00,
            'note_private' => 'Test note'
        ];

        $this->db->setMockResult('declaration_42', $mockDeclaration);

        // Act
        $result = $this->repository->findById(42);

        // Assert
        $this->assertIsObject($result);
        $this->assertEquals(42, $result->rowid);
        $this->assertEquals(2024, $result->declaration_year);
        $this->assertEquals(18.0, $result->total_days);
    }

    /**
     * Test findById returns false when not found
     */
    public function testFindByIdReturnsFalseWhenNotFound()
    {
        // Arrange
        $this->db->setMockResult('declaration_999', false);

        // Act
        $result = $this->repository->findById(999);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Test getTotalPrevisionnel returns correct amount
     */
    public function testGetTotalPrevisionnelReturnsCorrectAmount()
    {
        // Arrange
        $mockStats = (object)[
            'montant_previsionnel' => 3500.00,
            'nb_brouillons' => 2,
            'nb_valides' => 3,
            'nb_payes' => 0,
            'montant_brouillons' => 1500.00,
            'montant_valides' => 2000.00,
            'montant_payes' => 0.00,
            'jours_brouillons' => 15.0,
            'jours_valides' => 20.0,
            'jours_payes' => 0.0
        ];

        $this->db->setMockResult(
            $this->buildStatisticsQuery(123, 2024),
            $mockStats
        );

        // Act
        $result = $this->repository->getTotalPrevisionnel(123, ['year' => 2024]);

        // Assert
        $this->assertEquals(3500.00, $result);
    }

    /**
     * Test getTotalPrevisionnel returns zero when no data
     */
    public function testGetTotalPrevisionnelReturnsZeroWhenNoData()
    {
        // Arrange
        $this->db->setMockResult(
            $this->buildStatisticsQuery(999, 2024),
            false
        );

        // Act
        $result = $this->repository->getTotalPrevisionnel(999, ['year' => 2024]);

        // Assert
        $this->assertEquals(0.0, $result);
    }

    /**
     * Test getTotalDays returns correct breakdown
     */
    public function testGetTotalDaysReturnsCorrectBreakdown()
    {
        // Arrange
        $mockStats = (object)[
            'jours_brouillons' => 10.0,
            'jours_valides' => 15.0,
            'jours_payes' => 25.0,
            'nb_brouillons' => 2,
            'nb_valides' => 3,
            'nb_payes' => 5,
            'montant_brouillons' => 1000.00,
            'montant_valides' => 1500.00,
            'montant_payes' => 2500.00,
            'montant_previsionnel' => 2500.00
        ];

        $this->db->setMockResult(
            $this->buildStatisticsQuery(123, 2024),
            $mockStats
        );

        // Act
        $result = $this->repository->getTotalDays(123, ['year' => 2024]);

        // Assert
        $this->assertIsObject($result);
        $this->assertEquals(10.0, $result->jours_brouillons);
        $this->assertEquals(15.0, $result->jours_valides);
        $this->assertEquals(25.0, $result->jours_payes);
        $this->assertEquals(50.0, $result->jours_total); // 10 + 15 + 25
    }

    /**
     * Test getSalaryStatistics handles database errors
     */
    public function testGetSalaryStatisticsReturnsFalseOnError()
    {
        // Arrange
        $this->db->setMockResult(
            $this->buildStatisticsQuery(999, 2024),
            false
        );

        // Act
        $result = $this->repository->getSalaryStatistics(999, ['year' => 2024]);

        // Assert
        $this->assertFalse($result);
    }

    /**
     * Helper method to build statistics query signature
     */
    private function buildStatisticsQuery($collaboratorId, $year)
    {
        return "salary_stats_{$collaboratorId}_{$year}";
    }

    /**
     * Helper method to build declarations query signature
     */
    private function buildDeclarationsQuery($collaboratorId, $year = 0, $status = 0, $orderBy = 'declaration_year', $orderDir = 'DESC')
    {
        return "declarations_{$collaboratorId}_{$year}_{$status}_{$orderBy}_{$orderDir}";
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
