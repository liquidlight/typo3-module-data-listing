<?php

/**
 * Tests for the shared datatable query building
 *
 * The query assertions run a genuine TYPO3 QueryBuilder and assert on the SQL
 * it generates, so a change in how a clause is built is caught rather than a
 * change in how a mock was called. Most run over in-memory SQLite, which
 * quotes identifiers with double quotes; FIND_IN_SET has no SQLite equivalent
 * that accepts a placeholder, so that one case uses a MySQL platform instead.
 *
 * @author Mike Street <mike@liquidlight.co.uk>
 * @copyright Liquid Light Ltd.
 * @package TYPO3
 * @subpackage module_data_listing
 */

namespace LiquidLight\ModuleDataListing\Tests\Unit\Controller;

use Doctrine\DBAL\DriverManager;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use LiquidLight\ModuleDataListing\Tests\Unit\Controller\Fixtures\TestDatatableController;
use RuntimeException;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class DatatableControllerTest extends UnitTestCase
{
	protected bool $resetSingletonInstances = true;

	private Connection $connection;

	protected function setUp(): void
	{
		parent::setUp();

		$this->connection = DriverManager::getConnection([
			'driver' => 'pdo_sqlite',
			'memory' => true,
			'wrapperClass' => Connection::class,
		]);

		// Without a delete column in the TCA, applyDeleteFilter is a no-op and
		// the delete assertions would pass for the wrong reason
		$GLOBALS['TCA']['fe_users']['ctrl']['delete'] = 'deleted';
		$GLOBALS['TCA']['fe_groups']['ctrl']['delete'] = 'deleted';
	}

	/**
	 * Build the controller against a TypoScript-shaped configuration array
	 */
	private function buildController(array $configuration, ?Connection $connection = null): TestDatatableController
	{
		$configurationManager = $this->createMock(ConfigurationManagerInterface::class);
		$configurationManager
			->method('getConfiguration')
			->willReturn([
				'module.' => [
					'tx_moduledatalisting.' => [
						'configuration.' => [
							'test.' => $configuration,
						],
					],
				],
			])
		;

		$connectionPool = $this->createMock(ConnectionPool::class);
		$connectionPool
			->method('getConnectionForTable')
			->willReturn($connection ?? $this->connection)
		;

		return new TestDatatableController(
			$configurationManager,
			$connectionPool,
			$this->moduleTemplateFactory(),
			$this->createMock(PageRenderer::class),
		);
	}

	/**
	 * ModuleTemplateFactory is final, so it cannot be doubled. None of these
	 * tests render anything, so an uninitialised real instance is enough.
	 */
	private function moduleTemplateFactory(): ModuleTemplateFactory
	{
		return (new ReflectionClass(ModuleTemplateFactory::class))->newInstanceWithoutConstructor();
	}

	/**
	 * A MySQL platform resolved without ever connecting
	 *
	 * Pinning serverVersion lets Doctrine pick the platform without asking a
	 * server for it, which is enough as long as the expression under test does
	 * not quote a literal (ExpressionBuilder::like() does, and would connect).
	 */
	private function mysqlConnection(): Connection
	{
		return DriverManager::getConnection([
			'driver' => 'pdo_mysql',
			'host' => '127.0.0.1',
			'dbname' => 'unused',
			'user' => 'unused',
			'password' => '',
			'serverVersion' => '8.0.30',
			'wrapperClass' => Connection::class,
		]);
	}

	/**
	 * A bare SELECT to hang clauses off
	 *
	 * The default restriction container would otherwise contribute a deleted
	 * restriction of its own, which is indistinguishable in the SQL from the
	 * one the controller builds.
	 */
	private function baseQuery(TestDatatableController $controller): QueryBuilder
	{
		$query = $controller->callGetNewQueryBuilder();
		$query->getRestrictions()->removeAll();
		$query->select('uid')->from('fe_users');

		return $query;
	}

	/**
	 * The minimum configuration the constructor will accept
	 */
	private static function minimalConfiguration(array $overrides = []): array
	{
		return array_merge([
			'table' => 'fe_users',
			'searchableColumns' => '',
			'headers.' => [
				'fe_users.uid' => 'ID',
				'fe_users.username' => 'Username',
			],
		], $overrides);
	}

	public function testConstructorThrowsWhenTheConfigurationBranchIsMissing(): void
	{
		$configurationManager = $this->createMock(ConfigurationManagerInterface::class);
		$configurationManager
			->method('getConfiguration')
			->willReturn(['module.' => []])
		;

		$this->expectException(Exception::class);
		$this->expectExceptionCode(1790069492);

		new TestDatatableController(
			$configurationManager,
			$this->createMock(ConnectionPool::class),
			$this->moduleTemplateFactory(),
			$this->createMock(PageRenderer::class),
		);
	}

	public function testConstructorAppendsAdditionalColumnsToTheHeaders(): void
	{
		// TypoScript leaves the trailing dot on the table key, which is what
		// turns `fe_users.` + `crdate` into the `fe_users.crdate` header key
		$controller = $this->buildController($this->minimalConfiguration([
			'additionalColumns.' => [
				'fe_users.' => [
					'crdate' => 'Created',
					'lastlogin' => 'Last Login',
				],
			],
		]));

		self::assertSame(
			[
				'fe_users.uid' => 'ID',
				'fe_users.username' => 'Username',
				'fe_users.crdate' => 'Created',
				'fe_users.lastlogin' => 'Last Login',
			],
			$controller->exposeHeaders(),
		);
	}

	public function testConstructorDoesNotLetAnAdditionalColumnOverwriteAnExistingHeader(): void
	{
		$controller = $this->buildController($this->minimalConfiguration([
			'additionalColumns.' => [
				'fe_users.' => [
					'username' => 'Overwritten Label',
				],
			],
		]));

		self::assertSame('Username', $controller->exposeHeaders()['fe_users.username']);
	}

	public function testApplySearchBuildsALikeExpressionPerSearchableColumn(): void
	{
		$controller = $this->buildController($this->minimalConfiguration([
			'searchableColumns' => 'fe_users.username, fe_users.email',
		]));

		$query = $this->baseQuery($controller);
		$controller->callApplySearch($query, ['search' => ['value' => 'bob']]);

		$sql = $query->getSQL();

		self::assertStringContainsString('"fe_users"."username" LIKE :dcValue1', $sql);
		self::assertStringContainsString('"fe_users"."email" LIKE :dcValue2', $sql);
		self::assertStringContainsString(' OR ', $sql);
		self::assertSame(['dcValue1' => '%bob%', 'dcValue2' => '%bob%'], $query->getParameters());
	}

	public function testApplySearchFiltersOnTheOverrideExpressionRatherThanTheAlias(): void
	{
		$controller = $this->buildController($this->minimalConfiguration([
			'searchableColumns' => 'fe_users.name',
			'columnSelectOverrides.' => [
				'fe_users.name' => 'CONCAT(fe_users.first_name, fe_users.last_name)',
			],
		]));

		$query = $this->baseQuery($controller);
		$controller->callApplySearch($query, ['search' => ['value' => 'bob']]);

		$sql = $query->getSQL();

		self::assertStringContainsString('CONCAT(fe_users.first_name, fe_users.last_name) LIKE :dcValue1', $sql);
		self::assertStringNotContainsString('"fe_users"."name" LIKE', $sql);
	}

	public function testApplySearchEscapesLikeWildcardsInTheSearchTerm(): void
	{
		$controller = $this->buildController($this->minimalConfiguration([
			'searchableColumns' => 'fe_users.username',
		]));

		$query = $this->baseQuery($controller);
		$controller->callApplySearch($query, ['search' => ['value' => '100%_off']]);

		self::assertSame(['dcValue1' => '%100\%\_off%'], $query->getParameters());
	}

	public function testApplySearchAddsNoConditionWhenNoColumnsAreSearchable(): void
	{
		$controller = $this->buildController($this->minimalConfiguration([
			'searchableColumns' => '',
		]));

		$query = $this->baseQuery($controller);
		$controller->callApplySearch($query, ['search' => ['value' => 'bob']]);

		// An empty composite expression would otherwise render a bare WHERE
		self::assertStringNotContainsString('WHERE', $query->getSQL());
	}

	public function testApplySearchAddsNoConditionWhenTheSearchTermIsEmpty(): void
	{
		$controller = $this->buildController($this->minimalConfiguration([
			'searchableColumns' => 'fe_users.username',
		]));

		$query = $this->baseQuery($controller);
		$controller->callApplySearch($query, ['search' => ['value' => '']]);

		self::assertStringNotContainsString('WHERE', $query->getSQL());
	}

	public function testApplyJoinsStripsTheTypoScriptTrailingDotFromTheAlias(): void
	{
		$controller = $this->buildController($this->minimalConfiguration([
			'joins.' => [
				'groups.' => [
					'table' => 'fe_groups',
					'type' => 'leftJoin',
					'on' => 'groups.uid = fe_users.usergroup',
				],
			],
		]));

		$query = $this->baseQuery($controller);
		$controller->callApplyJoins($query);

		$sql = $query->getSQL();

		self::assertStringContainsString('LEFT JOIN "fe_groups" "groups" ON groups.uid = fe_users.usergroup', $sql);
		self::assertStringNotContainsString('"groups."', $sql);
	}

	#[DataProvider('incompleteJoinDefinitionProvider')]
	public function testApplyJoinsThrowsWhenAJoinDefinitionIsIncomplete(array $join): void
	{
		$controller = $this->buildController($this->minimalConfiguration([
			'joins.' => ['groups.' => $join],
		]));

		$this->expectException(RuntimeException::class);
		$this->expectExceptionCode(1790069493);

		$controller->callApplyJoins($this->baseQuery($controller));
	}

	public static function incompleteJoinDefinitionProvider(): array
	{
		return [
			'missing table' => [['type' => 'leftJoin', 'on' => 'a = b']],
			'missing type' => [['table' => 'fe_groups', 'on' => 'a = b']],
			'missing on' => [['table' => 'fe_groups', 'type' => 'leftJoin']],
		];
	}

	public function testApplyJoinsRejectsAJoinTypeOutsideTheAllowList(): void
	{
		$controller = $this->buildController($this->minimalConfiguration([
			'joins.' => [
				'groups.' => [
					'table' => 'fe_groups',
					// Anything not on the allow list would otherwise be called
					// as a method on the query builder
					'type' => 'executeQuery',
					'on' => 'groups.uid = fe_users.usergroup',
				],
			],
		]));

		$this->expectException(RuntimeException::class);
		$this->expectExceptionCode(1790069494);

		$controller->callApplyJoins($this->baseQuery($controller));
	}

	public function testApplyFiltersUsesFindInSetForUsergroupMembership(): void
	{
		$controller = $this->buildController($this->minimalConfiguration(), $this->mysqlConnection());

		$query = $this->baseQuery($controller);
		$controller->callApplyFilters($query, ['filters' => ['usergroup' => '3']]);

		// usergroup holds a comma separated list, so equality would only ever
		// match a user in exactly one group
		self::assertStringContainsString('FIND_IN_SET', $query->getSQL());
		self::assertSame(['dcValue1' => '3'], $query->getParameters());
	}

	public function testApplyFiltersUsesEqualityForEveryOtherField(): void
	{
		$controller = $this->buildController($this->minimalConfiguration());

		$query = $this->baseQuery($controller);
		$controller->callApplyFilters($query, ['filters' => ['pid' => '12']]);

		$sql = $query->getSQL();

		self::assertStringContainsString('"pid" = :dcValue1', $sql);
		self::assertStringNotContainsString('FIND_IN_SET', $sql);
	}

	public function testApplyFiltersTreatsSeveralValuesForOneFieldAsAnyOfThem(): void
	{
		$controller = $this->buildController($this->minimalConfiguration());

		$query = $this->baseQuery($controller);
		$controller->callApplyFilters($query, ['filters' => ['pid' => ['12', '13']]]);

		$sql = $query->getSQL();

		self::assertStringContainsString('"pid" = :dcValue1', $sql);
		self::assertStringContainsString('"pid" = :dcValue2', $sql);
		self::assertStringContainsString(' OR ', $sql);
	}

	public function testApplyFiltersSkipsEmptyValuesButKeepsAZero(): void
	{
		$controller = $this->buildController($this->minimalConfiguration());

		$query = $this->baseQuery($controller);
		$controller->callApplyFilters($query, ['filters' => [
			'pid' => ['', '0'],
			'username' => '',
		]]);

		$sql = $query->getSQL();

		self::assertStringContainsString('"pid" = :dcValue1', $sql);
		self::assertSame(['dcValue1' => '0'], $query->getParameters());
		self::assertStringNotContainsString('"username"', $sql);
	}

	public function testApplyFiltersAddsNoConditionWhenNoFiltersAreSet(): void
	{
		$controller = $this->buildController($this->minimalConfiguration());

		$query = $this->baseQuery($controller);
		$controller->callApplyFilters($query, []);

		self::assertStringNotContainsString('WHERE', $query->getSQL());
	}

	public function testApplyOrderUsesAOneBasedColumnIndex(): void
	{
		$controller = $this->buildController($this->minimalConfiguration());

		$query = $this->baseQuery($controller);
		$controller->applyOrder($query, ['order' => [['column' => '1', 'dir' => 'desc']]]);

		self::assertStringEndsWith('ORDER BY 2 DESC', $query->getSQL());
	}

	public function testApplyOrderAppliesEveryValidOrderInTurn(): void
	{
		$controller = $this->buildController($this->minimalConfiguration());

		$query = $this->baseQuery($controller);
		$controller->applyOrder($query, ['order' => [
			['column' => '0', 'dir' => 'asc'],
			['column' => '1', 'dir' => 'desc'],
		]]);

		self::assertStringEndsWith('ORDER BY 1 ASC, 2 DESC', $query->getSQL());
	}

	public function testApplyOrderDefaultsToAscendingWhenNoDirectionIsGiven(): void
	{
		$controller = $this->buildController($this->minimalConfiguration());

		$query = $this->baseQuery($controller);
		$controller->applyOrder($query, ['order' => [['column' => '0']]]);

		self::assertStringEndsWith('ORDER BY 1 ASC', $query->getSQL());
	}

	#[DataProvider('rejectedOrderProvider')]
	public function testApplyOrderIgnoresAnOrderItCannotTrust(array $order): void
	{
		$controller = $this->buildController($this->minimalConfiguration());

		$query = $this->baseQuery($controller);
		$controller->applyOrder($query, ['order' => [$order]]);

		// Both the direction and the column index reach the SQL unquoted, so
		// anything unexpected has to be dropped rather than passed through
		self::assertStringNotContainsString('ORDER BY', $query->getSQL());
	}

	public static function rejectedOrderProvider(): array
	{
		return [
			'injected direction' => [['column' => '0', 'dir' => 'ASC; DROP TABLE fe_users']],
			'unknown direction' => [['column' => '0', 'dir' => 'sideways']],
			'non numeric column' => [['column' => 'username', 'dir' => 'asc']],
			'injected column' => [['column' => '1 UNION SELECT password', 'dir' => 'asc']],
			'negative column' => [['column' => '-1', 'dir' => 'asc']],
			'column past the last header' => [['column' => '2', 'dir' => 'asc']],
			'no column' => [['dir' => 'asc']],
		];
	}

	public function testApplyDeleteFilterExcludesRowsFlaggedAsDeleted(): void
	{
		$controller = $this->buildController($this->minimalConfiguration());

		$query = $this->baseQuery($controller);
		$controller->callApplyDeleteFilter($query, 'fe_users', 'fe_users');

		$sql = $query->getSQL();

		self::assertStringContainsString('"fe_users"."deleted" = 0', $sql);
		self::assertStringContainsString('"fe_users"."deleted" IS NULL', $sql);
	}

	public function testApplyDeleteFilterQualifiesTheColumnWithTheJoinAlias(): void
	{
		$controller = $this->buildController($this->minimalConfiguration());

		$query = $this->baseQuery($controller);
		$controller->callApplyDeleteFilter($query, 'fe_groups', 'groups');

		self::assertStringContainsString('"groups"."deleted"', $query->getSQL());
	}

	public function testApplyDeleteFilterAddsNothingWhenTheTableHasNoDeleteColumn(): void
	{
		unset($GLOBALS['TCA']['sys_log']);

		$controller = $this->buildController($this->minimalConfiguration());

		$query = $this->baseQuery($controller);
		$controller->callApplyDeleteFilter($query, 'sys_log', 'sys_log');

		self::assertStringNotContainsString('WHERE', $query->getSQL());
	}

	public function testPrepareQueryKeepsTheBaseTableDeleteFilterWhenAJoinIsApplied(): void
	{
		$controller = $this->buildController($this->minimalConfiguration([
			'joins.' => [
				'groups.' => [
					'table' => 'fe_groups',
					'type' => 'leftJoin',
					'on' => 'groups.uid = fe_users.usergroup',
				],
			],
		]));

		$sql = $controller->callPrepareQuery(['search' => ['value' => '']])->getSQL();

		// IS NULL is the discriminator: only applyDeleteFilter emits it, so
		// asserting on "deleted" alone would pass on the DeletedRestriction's
		// own contribution and never see the dropped clause
		self::assertStringContainsString('"fe_users"."deleted" IS NULL', $sql);
		self::assertStringContainsString('"groups"."deleted" IS NULL', $sql);
	}

	public function testConstructorToleratesTypoScriptThatOmitsSearchableColumns(): void
	{
		$controller = $this->buildController([
			'table' => 'fe_users',
			'headers.' => ['fe_users.uid' => 'ID'],
		]);

		$query = $this->baseQuery($controller);
		$controller->callApplySearch($query, ['search' => ['value' => 'bob']]);

		self::assertStringNotContainsString('WHERE', $query->getSQL());
	}
}
