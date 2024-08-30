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
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
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
	protected string $table;

	protected string $configurationName;

	protected array $headers;
	protected array $columnSelectOverrides = [];

	protected $defaultViewObjectName = BackendTemplateView::class;


	protected ConnectionPool $connectionPool;

	public function injectConnectionPool(ConnectionPool $connectionPool) {
		$this->connectionPool = $connectionPool;
	}

	/**
	 * Init view and load JS
	 */
	public function initializeView(ViewInterface $view): void
	{
		/** @var BackendTemplateView $view */
		parent::initializeView($view);

		$extPath = '/' . trim(PathUtility::stripPathSitePrefix(ExtensionManagementUtility::extPath('module_data_listing')), '/');

		if ($view instanceof BackendTemplateView) {
			$view->getModuleTemplate()->getPageRenderer()->addRequireJsConfiguration([
				'paths' => [
					'datatables.net' => $extPath . '/Resources/Public/JavaScript/DataTables/jquery.dataTables.min',
					'datatables.net-buttons' => $extPath . '/Resources/Public/JavaScript/DataTables/dataTables.buttons.min',
					'datatables.net-buttons-print' => $extPath . '/Resources/Public/JavaScript/DataTables/buttons.print.min',
					'datatables.net-buttons-html5' => $extPath . '/Resources/Public/JavaScript/DataTables/buttons.html5.min',
				],
				'shim' => [
					'datatables.net' => ['jquery', 'exports' => 'datatables.net'],
					'datatables.net-buttons' => ['datatables.net', 'exports' => 'datatables.net-buttons'],
					'datatables.net-buttons-print' => ['datatables.net-buttons', 'exports' => 'datatables.net-buttons-print'],
					'datatables.net-buttons-html5' => ['datatables.net-buttons', 'exports' => 'datatables.net-buttons-html5'],
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
			->createQueryBuilder();
		;
	}

	/**
	 * Lookup a usergroup
	 */
	protected function getHeaders(): array
	{
		static $headers = [];

		if (count($headers)) {
			return $headers;
		}

		$headers = $this->headers;

		if (!$additional = $this->getModuleSettings()['additionalColumns.'] ?? false) {
			return $headers;
		}

		// Apply additional headers
		foreach ($additional as $table => $columns) {
			foreach ($columns as $column => $label) {
				if (!array_key_exists($table . $column, $headers)) {
					$headers[$table . $column] = $label;
				}
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

		if ($moduleSettings = $setup['module.']['tx_moduledatalisting.']['configuration.'][$this->configurationName . '.'] ?? false) {
			return $moduleSettings ?? [];
		} else {
			throw new Exception(sprintf(
				'Missing expected SetupTS definition for module.tx_moduledatalisting.configuration.%s',
				$this->configurationName,
			));
		}

		return null;
	}

	/**
	 * Get the table data
	 */
	protected function getTableData(array $params): array
	{
		$query = $this->getNewQueryBuilder();

		/**
		 * Users without attached fees were not returned in the count due to null values
		 * Removing restrictions and re-apply to fe_users only solves this
		 * @todo TYPO3 v10+ has a cleaner way of doing this: https://docs.typo3.org/m/typo3/reference-coreapi/10.4/en-us/ApiOverview/Database/RestrictionBuilder/Index.html#limitrestrictionstotables
		*/
		$query
			->getRestrictions()
			->removeAll()
			->add(GeneralUtility::makeInstance(DeletedRestriction::class))
		;

		$selectFields = array_keys($this->getHeaders());
		foreach($selectFields as $field){
			if(isset($this->columnSelectOverrides[$field])) {
				$query->addSelectLiteral(sprintf(
						'%s as `%s`',
						$this->columnSelectOverrides[$field],
						$field,
					));
			} else {
				$query->addSelect($field);
			}
		}

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

		// Apply joins
		$query = $this->applyJoins($query, $query);

		// Apply filters
		if ($params['filters'] ?? false) {
			$query = $this->applyFilters($query, $params);
		}

		// Page
		if ($params['start'] ?? false) {
			$query = $query->setFirstResult($params['start']);
		}

		// Order
		$order = $params['order'][0];

		if (isset($order['column']) && $order['dir']) {
			$headerKeys = array_keys($this->getHeaders());

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
		$query = $this->applySearch($query, $params);

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
		$query = $this->getNewQueryBuilder();

		/**
		 * Users without attached fees were not returned in the count due to null values
		 * Removing restrictions and re-apply to table only solves this
		 * @todo TYPO3 v10+ has a cleaner way of doing this: https://docs.typo3.org/m/typo3/reference-coreapi/10.4/en-us/ApiOverview/Database/RestrictionBuilder/Index.html#limitrestrictionstotables
		*/
		$query
			->getRestrictions()
			->removeAll()
		;

		$query = $query
			->count($this->table . '.uid')
			->from($this->table)
			->where(
				$query->expr()->eq(
					$this->table . '.deleted',
					0
				),
			)
		;

		// Apply joins
		$query = $this->applyJoins($query, $this->table);

		// Apply filters
		if ($params['filters']) {
			$query = $this->applyFilters($query, $params);
		}

		// Apply search
		$query = $this->applySearch($query, $params);

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
			$columnStr = $this->getModuleSettings()['searchableColumns'];
			$searchableColumns = GeneralUtility::trimExplode(',', $columnStr);

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
		$joins = $this->getModuleSettings()['joins.'];

		if (!$joins) {
			return $query;
		}

		// Apply joins from settings
		foreach ($joins as $join) {
			// Should we be using an alias for join?
			if (array_key_exists('as', $join)) {
				$joinTable = $join['as'];
			} else {
				$joinTable = $join['table'];
			}

			switch ($type = $join['type']) {
				case 'leftJoin':
				case 'rightJoin':
					$query = $query
						->$type(
							$this->table,
							$join['table'],
							$joinTable,
							$query->expr()->eq($joinTable . '.' . $join['localIdentifier'], $query->quoteIdentifier($this->table . '.' . $join['foreignIdentifier']))
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
									$query->expr()->eq($joinTable . '.' . $join['localIdentifier'], $query->quoteIdentifier($this->table . '.uid'))
								)
								->innerJoin(
									$joinTable,
									$join['secondaryTable'],
									$secondaryJoinTable,
									$query->expr()->andX(
										$query->expr()->eq($secondaryJoinTable . '.' . $join['secondaryLocalIdentifier'], $query->quoteIdentifier($joinTable . '.' . $join['secondaryForeignIdentifier'])),
										$query->expr()->eq($secondaryJoinTable . '.' . $join['secondaryWhereField'], $query->createNamedParameter($join['secondaryWhereValue']))
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
								$query->expr()->eq($joinTable . '.' . $join['localIdentifier'], $query->quoteIdentifier($this->table . '.uid'))
							)
							->innerJoin(
								$joinTable,
								$join['secondaryTable'],
								$secondaryJoinTable,
								$query->expr()->eq($secondaryJoinTable . '.' . $join['secondaryLocalIdentifier'], $query->quoteIdentifier($join['table'] . '.' . $join['secondaryForeignIdentifier']))
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
							$query->expr()->eq($join['table'] . '.' . $join['localIdentifier'], $query->quoteIdentifier($this->table . '.' . $join['foreignIdentifier']))
						)
					;
					break;
			}
		}

		foreach ($joins as $join) {
			// Don't check the mm tables
			if (substr($join['table'], -3) === '_mm') {
				continue;
			}

			$query = $query
				->where(
					$query->expr()->orX(
						$query->expr()->eq(
							$joinTable . '.deleted',
							0
						),
						$query->expr()->isNull(
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
	 * Default action: index
	 */
	public function indexAction(): void
	{
		$this->view->assignMultiple([
			'headers' => array_values($this->getHeaders()),
		]);
	}
}
