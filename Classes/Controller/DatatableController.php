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

use LiquidLight\ModuleDataListing\Datatable;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;

abstract class DatatableController extends ActionController implements Datatable
{
	protected $defaultViewObjectName = BackendTemplateView::class;

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
	protected function getConnection(string $table): Connection
	{
		return GeneralUtility::makeInstance(ConnectionPool::class)
			->getConnectionForTable($table)
		;
	}

	/**
	 * Lookup a usergroup
	 */
	protected function getHeaders(array $default): array
	{
		static $headers = [];

		if (count($headers)) {
			return $headers;
		}

		if (!$additional = $this->getModuleSettings()['additionalColumns.']) {
			return $default;
		}

		$headers = $default;

		// Apply additional headers
		foreach ($additional as $table => $columns) {
			foreach ($columns as $column => $label) {
				if (array_key_exists($table . $column, $headers)) {
					continue;
				}
				$headers[$table . $column] = $label;
			}
		}

		return $headers;
	}

	protected function getModuleSettings(): ?array
	{
		static $settings = [];

		if (count($settings)) {
			return $settings;
		}

		// Load module settings
		$setup = GeneralUtility::makeInstance(ObjectManager::class)
			->get(ConfigurationManagerInterface::class)
			->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT)
		;

		if ($settings = $setup['module.'][$this->moduleName . '.']['settings.']) {
			return $settings;
		}

		return null;
	}

	/**
	 * Get the table data
	 */
	protected function getTableData(array $params): array
	{
		$connection = $this->getConnection($this->table);
		$queryBuilder = $connection->createQueryBuilder();

		/**
		 * Users without attached fees were not returned in the count due to null values
		 * Removing restrictions and re-apply to fe_users only solves this
		 * @todo TYPO3 v10+ has a cleaner way of doing this: https://docs.typo3.org/m/typo3/reference-coreapi/10.4/en-us/ApiOverview/Database/RestrictionBuilder/Index.html#limitrestrictionstotables
		*/
		$queryBuilder
			->getRestrictions()
			->removeAll()
			->add(GeneralUtility::makeInstance(DeletedRestriction::class))
		;

		// Re-apply restrictions
		$query = $queryBuilder
			->select(...array_keys($this->getHeaders($this->headers)))
			->from($this->table)
			->where(
				$queryBuilder->expr()->eq(
					$this->table . '.deleted',
					0
				),
			)
		;

		// Apply joins
		$query = $this->applyJoins($queryBuilder, $query);

		// Apply filters
		if ($params['filters']) {
			$query = $this->applyFilters($queryBuilder, $query, $params);
		}

		// Page
		if ($params['start']) {
			$query = $query->setFirstResult($params['start']);
		}

		// Order
		$order = $params['order'][0];

		if (isset($order['column']) && $order['dir']) {
			$headerKeys = array_keys($this->getHeaders($this->headers));

			// Get column to order by and use alias if present
			if (strpos($headerKeys[$order['column']], ' as ') !== false) {
				$column = explode(' as ', $headerKeys[$order['column']])[1];
			} else {
				$column = $headerKeys[$order['column']];
			}

			$query = $query->orderBy($column, $order['dir']);
		} else {
			$query = $query->orderBy($this->table . '.uid', 'DESC');
		}

		// Apply search
		$query = $this->applySearch($queryBuilder, $query, $params);

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
		$connection = $this->getConnection($this->table);
		$queryBuilder = $connection->createQueryBuilder();

		/**
		 * Users without attached fees were not returned in the count due to null values
		 * Removing restrictions and re-apply to table only solves this
		 * @todo TYPO3 v10+ has a cleaner way of doing this: https://docs.typo3.org/m/typo3/reference-coreapi/10.4/en-us/ApiOverview/Database/RestrictionBuilder/Index.html#limitrestrictionstotables
		*/
		$queryBuilder
		->getRestrictions()
		->removeAll()
		;

		$query = $queryBuilder
			->count($this->table . '.uid')
			->from($this->table)
			->where(
				$queryBuilder->expr()->eq(
					$this->table . '.deleted',
					0
				),
			)
		;

		// Apply joins
		$query = $this->applyJoins($queryBuilder, $query, $this->table);

		// Apply filters
		if ($params['filters']) {
			$query = $this->applyFilters($queryBuilder, $query, $params);
		}

		// Apply search
		$query = $this->applySearch($queryBuilder, $query, $params);

		$count = $query
			->execute()
			->fetchColumn(0)
		;

		return (int)$count;
	}

	/**
	 * Apply search to query
	 */
	protected function applySearch(QueryBuilder $queryBuilder, QueryBuilder $query, array $params): QueryBuilder
	{
		if ($params['search']['value']) {
			$columnStr = $this->getModuleSettings()['searchableColumns'];
			$searchableColumns = GeneralUtility::trimExplode(',', $columnStr);

			$searchQuery = $queryBuilder->expr()->orX();
			foreach ($searchableColumns as $field) {
				$searchQuery->add(
					$queryBuilder->expr()->like(
						$field,
						$queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($params['search']['value']) . '%')
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
	protected function applyJoins(QueryBuilder $queryBuilder, QueryBuilder $query): QueryBuilder
	{
		$joins = $this->getModuleSettings()['joins.'];

		// Apply joins from settings
		if ($joins) {
			foreach ($joins as $join) {
				// Should we be using an alias for join?
				if (array_key_exists('as', $join)) {
					$joinTable = $join['as'];
				} else {
					$joinTable = $join['table'];
				}

				switch ($join['type']) {
					case 'leftJoin':
						$query = $query
							->leftJoin(
								$this->table,
								$join['table'],
								$joinTable,
								$queryBuilder->expr()->eq($joinTable . '.' . $join['localIdentifier'], $queryBuilder->quoteIdentifier($this->table . '.' . $join['foreignIdentifier']))
							)
					;
						break;
					case 'rightJoin':
						$query = $query
							->rightJoin(
								$this->table,
								$join['table'],
								$joinTable,
								$queryBuilder->expr()->eq($joinTable . '.' . $join['localIdentifier'], $queryBuilder->quoteIdentifier($this->table . '.' . $join['foreignIdentifier']))
							)
					;
						break;
					case 'innerJoin':
						// Apply many-to-many joins
						if (substr($join['table'], -3) === '_mm') {
							// Should we be using an alias for secondary?
							if (array_key_exists('secondaryTableAs', $join)) {
								$secondaryJoinTable = $join['secondaryTableAs'];
							} else {
								$secondaryJoinTable = $join['secondaryTable'];
							}

							// Do we need to apply an additional where clause to join table?
							if (array_key_exists('secondaryWhereField', $join) && array_key_exists('secondaryWhereValue', $join)) {
								$query = $query
									->innerJoin(
										$this->table,
										$join['table'],
										$joinTable,
										$queryBuilder->expr()->eq($joinTable . '.' . $join['localIdentifier'], $queryBuilder->quoteIdentifier($this->table . '.uid'))
									)
									->innerJoin(
										$joinTable,
										$join['secondaryTable'],
										$secondaryJoinTable,
										$queryBuilder->expr()->andX(
											$queryBuilder->expr()->eq($secondaryJoinTable . '.' . $join['secondaryLocalIdentifier'], $queryBuilder->quoteIdentifier($joinTable . '.' . $join['secondaryForeignIdentifier'])),
											$queryBuilder->expr()->eq($secondaryJoinTable . '.' . $join['secondaryWhereField'], $queryBuilder->createNamedParameter($join['secondaryWhereValue']))
										)
									)
								;
								break;
							}

							// Apply mm join without additional where clause
							$query = $query
								->innerJoin(
									$this->table,
									$join['table'],
									$joinTable,
									$queryBuilder->expr()->eq($joinTable . '.' . $join['localIdentifier'], $queryBuilder->quoteIdentifier($this->table . '.uid'))
								)
								->innerJoin(
									$joinTable,
									$join['secondaryTable'],
									$secondaryJoinTable,
									$queryBuilder->expr()->eq($secondaryJoinTable . '.' . $join['secondaryLocalIdentifier'], $queryBuilder->quoteIdentifier($join['table'] . '.' . $join['secondaryForeignIdentifier']))
								)
							;
							break;
						}

						// Apply standard join
						$query = $query
							->innerJoin(
								$this->table,
								$join['table'],
								$join['table'],
								$queryBuilder->expr()->eq($join['table'] . '.' . $join['localIdentifier'], $queryBuilder->quoteIdentifier($this->table . '.' . $join['foreignIdentifier']))
							)
						;
						break;
				}
			}
		}

		// Apply restrictions for joins from settings
		if (!$joins) {
			return $query;
		}

		foreach ($joins as $join) {
			// Don't check the mm tables
			if (substr($join['table'], -3) === '_mm') {
				continue;
			}

			$query = $query
				->where(
					$queryBuilder->expr()->orX(
						$queryBuilder->expr()->eq(
							$joinTable . '.deleted',
							0
						),
						$queryBuilder->expr()->isNull(
							$joinTable . '.deleted'
						),
					),
				)
			;
		}

		return $query;
	}

	/**
	 * Apply filters to query
	 */
	protected function applyFilters(QueryBuilder $queryBuilder, QueryBuilder $query, array $params): QueryBuilder
	{
		foreach ($params['filters'] as $field => $filter) {
			// If filtering by usergroup
			// then use an IN query
			// else use equals
			if ((is_array($filter) && (count($filter) > 1))) {
				foreach ($filter as $value) {
					if ($field == 'usergroup') {
						$query = $query
							->andWhere(
								$queryBuilder->expr()->orX(
									$queryBuilder->expr()->like(
										$field,
										$queryBuilder->createNamedParameter($queryBuilder->escapeLikeWildcards($value) . ',%')
									),
									$queryBuilder->expr()->like(
										$field,
										$queryBuilder->createNamedParameter('%,' . $queryBuilder->escapeLikeWildcards($value) . ',%')
									),
									$queryBuilder->expr()->like(
										$field,
										$queryBuilder->createNamedParameter('%,' . $queryBuilder->escapeLikeWildcards($value))
									)
								),
							)
						;
					} else {
						$query = $query
							->andWhere(
								$queryBuilder->expr()->eq(
									$field,
									$queryBuilder->createNamedParameter(
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
						$queryBuilder->expr()->eq(
							$field,
							$queryBuilder->createNamedParameter(
								is_array($filter) ? $filter[0] : $filter
							)
						)
					)
				;
			}
		}
		return $query;
	}
}
