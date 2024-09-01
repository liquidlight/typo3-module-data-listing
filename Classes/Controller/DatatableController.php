<?php

/**
 * Shared functionality used by the module views
 *
 * @author Zaq Mughal <zaq@liquidlight.co.uk>
 * @copyright Liquid Light Ltd.
 * @package TYPO3
 * @subpackage module_data_listing
 */

namespace LiquidLight\ModuleDataListing\Controller;

use Exception;
use RuntimeException;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

abstract class DatatableController extends ActionController
{
	protected string $configurationName;

	protected string $table;

	protected array $headers;

	protected array $columnSelectOverrides;

	protected string $searchableColumns;

	protected array $joins;

	protected $defaultViewObjectName = BackendTemplateView::class;

	protected ConnectionPool $connectionPool;

	public function __construct(ConfigurationManagerInterface $configurationManagerInterface, ConnectionPool $connectionPool)
	{
		$this->connectionPool = $connectionPool;

		$setup = $configurationManagerInterface->getConfiguration(
			ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
		);

		if (!$configuration = $setup['module.']['tx_moduledatalisting.']['configuration.'][$this->configurationName . '.'] ?? false) {
			throw new Exception(sprintf(
				'Missing expected SetupTS definition for module.tx_moduledatalisting.configuration.%s',
				$this->configurationName,
			));
		}

		$this->table = $configuration['table'] ?? $this->table;
		$this->headers = $configuration['headers.'] ?? $this->headers ?? [];
		$this->columnSelectOverrides = $configuration['columnSelectOverrides.'] ?? $this->columnSelectOverrides ?? [];
		$this->joins = $configuration['joins.'] ?? $this->joins ?? [];
		$this->searchableColumns = $configuration['searchableColumns'] ?? $this->searchableColumns ?? [];

		foreach ($configuration['additionalColumns.'] ?? [] as $table => $columns) {
			foreach ($columns as $column => $label) {
				if (array_key_exists($table . $column, $this->headers)) {
					continue;
				}
				$this->headers[$table . $column] = $label;
			}
		}

	}

	/**
	 * Init view and load JS
	 */
	public function initializeView(ViewInterface $view): void
	{
		/** @var BackendTemplateView $view */
		parent::initializeView($view);

		$extPath = PathUtility::getAbsoluteWebPath('../typo3conf/ext/module_data_listing');

		if ($view instanceof BackendTemplateView) {
			$view->getModuleTemplate()->getPageRenderer()->addRequireJsConfiguration([
				'paths' => [
					'datatables.net' => $extPath . '/Resources/Public/JavaScript/DataTables/jquery.dataTables.min',
					'datatables.net-buttons' => $extPath . '/Resources/Public/JavaScript/DataTables/dataTables.buttons.min',
					'datatables.net-buttons-print' => $extPath . '/Resources/Public/JavaScript/DataTables/buttons.print.min',
					'datatables.net-buttons-html5' => $extPath . '/Resources/Public/JavaScript/DataTables/buttons.html5.min',
				],
				'shim' => [
					'datatables.net' => ['jquery'],
					'datatables.net-buttons' => ['datatables.net'],
					'datatables.net-buttons-print' => ['datatables.net-buttons'],
					'datatables.net-buttons-html5' => ['datatables.net-buttons'],
				],
			]);
		}
	}

	/**
	 * Return query builder connection by table
	 */
	protected function getNewQueryBuilder(?string $table = null): QueryBuilder
	{
		return $this->connectionPool
			->getConnectionForTable($table ?? $this->table)
			->createQueryBuilder()
		;
	}

	protected function prepareQuery(array $params): QueryBuilder
	{
		$query = $this->getNewQueryBuilder();

		$query
			->getRestrictions()
			->removeAll()
			->add(GeneralUtility::makeInstance(DeletedRestriction::class))
		;

		// Re-apply restrictions
		$query
			->from($this->table)
			->where(
				$query->expr()->eq(
					$this->table . '.deleted',
					0
				),
			)
		;

		$query = $this->applyJoins($query, $query);

		// Apply filters
		if ($params['filters'] ?? false) {
			$query = $this->applyFilters($query, $params);
		}

		// Apply search
		$query = $this->applySearch($query, $params);

		return $query;
	}

	/**
	 * Get the table data
	 */
	protected function getTableData(array $params): array
	{
		$query = $this->prepareQuery($params);

		$selectFields = array_keys($this->headers);
		foreach ($selectFields as $field) {
			if (isset($this->columnSelectOverrides[$field])) {
				$query->addSelectLiteral(sprintf(
					'%s as `%s`',
					$this->columnSelectOverrides[$field],
					$field,
				));
			} else {
				$query->addSelect($field);
			}
		}

		// Page
		if ($params['start'] ?? false) {
			$query = $query->setFirstResult($params['start']);
		}

		// Order
		$this->applyOrder($query, $params);

		// Page size
		if ($params['length'] > 0) {
			$query = $query
				->setMaxResults($params['length'])
			;
		}

		$data = $query
			->execute()
			->fetchAll()
		;

		return $data;
	}

	/**
	 * Get the count of rows
	 */
	protected function getCount(array $params): int
	{
		$query = $this->prepareQuery($params);

		$query->count($this->table . '.uid');

		$count = $query
			->executeQuery()
			->fetchColumn(0)
		;

		return (int)$count;
	}

	/**
	 * Apply search to query
	 */
	protected function applySearch(QueryBuilder $query, array $params): QueryBuilder
	{
		if ($params['search']['value']) {
			$searchableColumns = GeneralUtility::trimExplode(',', $this->searchableColumns);

			$searchQuery = $query->expr()->orX();
			foreach ($searchableColumns as $field) {
				$searchQuery->add(
					$query->expr()->like(
						$field,
						$query->createNamedParameter('%' . $query->escapeLikeWildcards($params['search']['value']) . '%')
					)
				);
			}

			$query = $query->andWhere($searchQuery);
		}

		return $query;
	}

	/**
	 * Apply joins to query
	 */
	protected function applyJoins(QueryBuilder $query): QueryBuilder
	{
		$joins = $this->joins ?? [];

		$typesAllowed = ['join', 'leftJoin', 'rightJoin', 'innerJoin'];

		// Apply joins from settings
		foreach ($joins as $alias => $join) {

			foreach (['table', 'type', 'on'] as $property) {
				if (!isset($join[$property])) {
					throw new RuntimeException(sprintf(
						'Expected join definition %s to contain %s',
						$alias,
						$property
					));
				}
			}

			$alias = substr($alias, 0, -1); // Remove the trailing . from TS
			$table = $join['table'];
			$type = $join['type'];
			$on = $join['on'];

			if (!in_array($type, $typesAllowed, true)) {
				throw new RuntimeException(sprintf(
					'Unexpected join definition %s has type of %s',
					$alias,
					$type,
				));
			}

			// Perform the join
			$query->$type($this->table, $table, $alias, $on);

			// Exclude anything that is deleted
			if ($deleteFiled = $GLOBALS['TCA'][$table]['ctrl']['delete'] ?? false) {
				$deleteFiled = $alias . '.' . $deleteFiled;
				$query->where(
					$query->expr()->orX(
						$query->expr()->eq($deleteFiled, 0),
						$query->expr()->isNull($deleteFiled),
					),
				);
			}
		}

		return $query;
	}

	/**
	 * Apply filters to query
	 */
	protected function applyFilters(QueryBuilder $query, array $params): QueryBuilder
	{
		foreach ($params['filters'] ?? [] as $field => $filter) {
			// If filtering by usergroup
			// then use an IN query
			// else use equals
			if (is_array($filter) && (count($filter) > 1)) {
				foreach ($filter as $value) {
					if ($field === 'usergroup') {
						$query = $query
							->andWhere(
								$query->expr()->orX(
									$query->expr()->like(
										$field,
										$query->createNamedParameter($query->escapeLikeWildcards($value) . ',%')
									),
									$query->expr()->like(
										$field,
										$query->createNamedParameter('%,' . $query->escapeLikeWildcards($value) . ',%')
									),
									$query->expr()->like(
										$field,
										$query->createNamedParameter('%,' . $query->escapeLikeWildcards($value))
									)
								),
							)
						;
					} else {
						$query = $query
							->andWhere(
								$query->expr()->eq(
									$field,
									$query->createNamedParameter(
										$value
									)
								)
							)
						;
					}
				}
			} else {
				$query = $query
					->andWhere(
						$query->expr()->eq(
							$field,
							$query->createNamedParameter(
								is_array($filter) ? $filter[0] : $filter
							)
						)
					)
				;
			}
		}
		return $query;
	}

	/**
	 *
	 */
	public function applyOrder(QueryBuilder $query, array $params)
	{

		$orders = $params['order'] ?? [];
		$columnCount = count($this->headers);

		foreach ($orders as $order) {

			// Prepare the directions
			$dir = strtoupper($order['dir'] ?? 'ASC');

			if (!in_array($dir, ['ASC', 'DESC'], true)) {
				continue;
			}

			// Prepare the column index
			$column = $order['column'] ?? false;

			if (!is_numeric($column)) {
				continue;
			}

			$column = (int)$column;

			if (!is_int($column)) {
				continue;
			} elseif (0 > $column || $column >= $columnCount) {
				continue;
			}

			// Note: SQL order by column index is 1-base
			$query->getConcreteQueryBuilder()->addOrderBy($column + 1, $dir);
		}

		return $this;
	}

	/**
	 * Default action: index
	 */
	public function indexAction(): void
	{
		$this->view->assignMultiple([
			'headers' => array_values($this->headers),
		]);
	}
}
