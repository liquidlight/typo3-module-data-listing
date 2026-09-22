<?php

/**
 * Tests for the fe_users DataTables endpoint
 *
 * renderAjax reaches the base class through parent::, so the query cannot be
 * stubbed out; these tests run it against a seeded in-memory SQLite database
 * and assert on the JSON that comes back.
 *
 * @author Mike Street <mike@liquidlight.co.uk>
 * @copyright Liquid Light Ltd.
 * @package TYPO3
 * @subpackage module_data_listing
 */

namespace LiquidLight\ModuleDataListing\Tests\Unit\Controller;

use Doctrine\DBAL\DriverManager;
use LiquidLight\ModuleDataListing\Controller\FeUsersController;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class FeUsersControllerTest extends UnitTestCase
{
	protected bool $resetSingletonInstances = true;

	/**
	 * Parameters every buildUriFromRoute('record_edit', ...) call was given
	 *
	 * @var array<int, array>
	 */
	private array $editUriParameters = [];

	private Connection $connection;

	protected function setUp(): void
	{
		parent::setUp();

		$GLOBALS['TCA']['fe_users']['ctrl']['delete'] = 'deleted';

		$this->connection = DriverManager::getConnection([
			'driver' => 'pdo_sqlite',
			'memory' => true,
			'wrapperClass' => Connection::class,
		]);

		$this->connection->executeStatement(
			'CREATE TABLE fe_users (
				uid INTEGER PRIMARY KEY,
				username TEXT,
				usergroup TEXT,
				title TEXT,
				first_name TEXT,
				last_name TEXT,
				email TEXT,
				crdate INTEGER,
				lastlogin INTEGER,
				deleted INTEGER DEFAULT 0
			)'
		);
		$this->connection->executeStatement(
			'CREATE TABLE fe_groups (uid INTEGER PRIMARY KEY, title TEXT, deleted INTEGER DEFAULT 0)'
		);
	}

	/**
	 * Seed with plain SQL: Connection::insert() reads the table schema, which
	 * needs a cache the unit test bootstrap does not register
	 */
	private function insertUser(array $row): void
	{
		$row = array_merge([
			'username' => 'user',
			'usergroup' => '',
			'title' => '',
			'first_name' => '',
			'last_name' => '',
			'email' => '',
			'crdate' => 0,
			'lastlogin' => 0,
			'deleted' => 0,
		], $row);

		$this->connection->executeStatement(
			sprintf(
				'INSERT INTO fe_users (%s) VALUES (%s)',
				implode(', ', array_keys($row)),
				implode(', ', array_map(static fn (string $column): string => ':' . $column, array_keys($row))),
			),
			$row,
		);
	}

	private function insertGroup(int $uid, string $title): void
	{
		$this->connection->executeStatement(
			'INSERT INTO fe_groups (uid, title, deleted) VALUES (:uid, :title, 0)',
			['uid' => $uid, 'title' => $title],
		);
	}

	private function buildController(): FeUsersController
	{
		$configurationManager = $this->createMock(ConfigurationManagerInterface::class);
		$configurationManager
			->method('getConfiguration')
			->willReturn([
				'module.' => [
					'tx_moduledatalisting.' => [
						'configuration.' => [
							// No headers. key, so the controller's own header
							// list stands; the date columns are appended the
							// way an integrator would add them
							'fe_users.' => [
								'searchableColumns' => 'fe_users.username',
								'additionalColumns.' => [
									'fe_users.' => [
										'crdate' => 'Created',
										'lastlogin' => 'Last Login',
									],
								],
							],
						],
					],
				],
			])
		;

		$connectionPool = $this->createMock(ConnectionPool::class);
		$connectionPool
			->method('getConnectionForTable')
			->willReturn($this->connection)
		;

		$controller = new FeUsersController(
			$configurationManager,
			$connectionPool,
			(new ReflectionClass(ModuleTemplateFactory::class))->newInstanceWithoutConstructor(),
			$this->createMock(PageRenderer::class),
		);

		$controller->injectResponseFactory(new ResponseFactory());
		$controller->injectStreamFactory(new StreamFactory());

		// UriBuilder is a singleton, so makeInstance resolves it from the
		// singleton registry rather than the instance queue
		GeneralUtility::setSingletonInstance(UriBuilder::class, $this->buildUriBuilder());

		return $controller;
	}

	private function buildUriBuilder(): UriBuilder
	{
		$uriBuilder = $this->createMock(UriBuilder::class);
		$uriBuilder
			->method('buildUriFromRoute')
			->willReturnCallback(function (string $route, array $parameters = []): Uri {
				if ($route !== 'record_edit') {
					return new Uri('/typo3/module/web/datalisting?token=abc123');
				}

				$this->editUriParameters[] = $parameters;

				return new Uri('/typo3/record/edit?uid=' . array_key_first($parameters['edit']['fe_users']));
			})
		;

		return $uriBuilder;
	}

	/**
	 * Run renderAjax and decode the JSON it responds with
	 */
	private function renderAjax(array $params = []): array
	{
		$request = $this->createMock(ServerRequestInterface::class);
		$request
			->method('getQueryParams')
			->willReturn(array_merge([
				'draw' => 1,
				'length' => 10,
				'search' => ['value' => ''],
			], $params))
		;

		$response = $this->buildController()->renderAjax($request);

		return json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
	}

	public function testRenderAjaxWrapsTheUidInAnEditLink(): void
	{
		$this->insertUser(['uid' => 1, 'username' => 'ada']);

		$return = $this->renderAjax();

		self::assertSame(
			'<a href="/typo3/record/edit?uid=1" title="Edit record">1</a>',
			$return['data'][0][0],
		);
	}

	public function testRenderAjaxSendsTheModuleUrlAsTheEditReturnUrl(): void
	{
		$this->insertUser(['uid' => 2, 'username' => 'ada']);

		$this->renderAjax();

		self::assertSame(
			'/typo3/module/web/datalisting?token=abc123',
			$this->editUriParameters[0]['returnUrl'],
		);
		self::assertSame(['fe_users' => [2 => 'edit']], $this->editUriParameters[0]['edit']);
	}

	public function testRenderAjaxReplacesUsergroupUidsWithTheirTitles(): void
	{
		$this->insertGroup(11, 'Members');
		$this->insertGroup(12, 'Editors');
		$this->insertUser(['uid' => 3, 'username' => 'ada', 'usergroup' => '11,12']);

		$return = $this->renderAjax();

		self::assertSame('Members, Editors', $return['data'][0][2]);
	}

	public function testRenderAjaxDropsUsergroupUidsThatResolveToNothing(): void
	{
		$this->insertGroup(21, 'Members');
		$this->insertUser(['uid' => 4, 'username' => 'ada', 'usergroup' => '21,0,22']);

		$return = $this->renderAjax();

		self::assertSame('Members', $return['data'][0][2]);
	}

	public function testRenderAjaxRendersAnEmptyUsergroupListAsAnEmptyString(): void
	{
		$this->insertUser(['uid' => 5, 'username' => 'ada', 'usergroup' => '']);

		$return = $this->renderAjax();

		self::assertSame('', $return['data'][0][2]);
	}

	public function testRenderAjaxFormatsUnixTimestampColumnsAsDates(): void
	{
		$this->insertUser([
			'uid' => 6,
			'username' => 'ada',
			// 2021-03-04 05:06:07 UTC
			'crdate' => 1614834367,
			'lastlogin' => 1614834367,
		]);

		$return = $this->renderAjax();

		self::assertSame(date('d/m/Y H:i:s', 1614834367), $return['data'][0][7]);
		self::assertSame(date('d/m/Y H:i:s', 1614834367), $return['data'][0][8]);
	}

	public function testRenderAjaxRendersAnUnsetTimestampAsNotAvailable(): void
	{
		$this->insertUser(['uid' => 7, 'username' => 'ada', 'crdate' => 0]);

		$return = $this->renderAjax();

		self::assertSame('N/A', $return['data'][0][7]);
	}

	public function testRenderAjaxReturnsEachRowAsAPositionalArray(): void
	{
		$this->insertUser([
			'uid' => 8,
			'username' => 'ada',
			'title' => 'Dr',
			'first_name' => 'Ada',
			'last_name' => 'Lovelace',
			'email' => 'ada@example.com',
		]);

		$return = $this->renderAjax();

		self::assertSame(
			['Dr', 'Ada', 'Lovelace', 'ada@example.com'],
			array_slice($return['data'][0], 3, 4),
		);
	}

	public function testRenderAjaxEchoesTheDrawCounterAndCountsTheRows(): void
	{
		$this->insertUser(['uid' => 9, 'username' => 'ada']);
		$this->insertUser(['uid' => 10, 'username' => 'grace']);

		$return = $this->renderAjax(['draw' => 7]);

		self::assertSame(7, $return['draw']);
		self::assertSame(2, $return['recordsTotal']);
		self::assertSame(2, $return['recordsFiltered']);
	}

	public function testRenderAjaxOmitsDeletedUsers(): void
	{
		$this->insertUser(['uid' => 11, 'username' => 'ada']);
		$this->insertUser(['uid' => 12, 'username' => 'deleted-user', 'deleted' => 1]);

		$return = $this->renderAjax();

		self::assertCount(1, $return['data']);
		self::assertSame(1, $return['recordsTotal']);
	}

	public function testRenderAjaxLimitsTheRowsToThePageLength(): void
	{
		foreach ([13, 14, 15] as $uid) {
			$this->insertUser(['uid' => $uid, 'username' => 'user' . $uid]);
		}

		$return = $this->renderAjax(['length' => 2]);

		self::assertCount(2, $return['data']);
		self::assertSame(3, $return['recordsTotal']);
	}

	public function testRenderAjaxAppliesTheSearchTermToTheSearchableColumns(): void
	{
		$this->insertUser(['uid' => 16, 'username' => 'ada']);
		$this->insertUser(['uid' => 17, 'username' => 'grace']);

		$return = $this->renderAjax(['search' => ['value' => 'grac']]);

		self::assertSame(1, $return['recordsFiltered']);
		self::assertStringContainsString('>17</a>', $return['data'][0][0]);
	}

	public function testRenderAjaxRespondsAsJson(): void
	{
		$this->insertUser(['uid' => 18, 'username' => 'ada']);

		$request = $this->createMock(ServerRequestInterface::class);
		$request
			->method('getQueryParams')
			->willReturn(['draw' => 1, 'length' => 10, 'search' => ['value' => '']])
		;

		$response = $this->buildController()->renderAjax($request);

		self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
	}
}
