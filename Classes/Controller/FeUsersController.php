<?php

/**
 * Render the fe_users table
 *
 * @author Zaq Mughal <zaq@liquidlight.co.uk>
 * @copyright Liquid Light Ltd.
 * @package TYPO3
 * @subpackage module_data_listing
 */

namespace LiquidLight\ModuleDataListing\Controller;

use LiquidLight\ModuleDataListing\Controller\DatatableController;

use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\Response;

class FeUsersController extends DatatableController
{
	protected $defaultViewObjectName = BackendTemplateView::class;

	/**
	 * Bills table headers
	 *
	 * @var array
	 * @access protected
	 */
	protected $headers = [
		'fe_users.uid' => 'ID',
		'fe_users.username' => 'Username',
		'fe_users.usergroup' => 'Group',
		'fe_users.title' => 'Title',
		'fe_users.first_name' => 'First Name',
		'fe_users.last_name' => 'Last Name',
		'fe_users.email' => 'Email',
	];

	/**
	 * Init view
	 */
	public function initializeView(ViewInterface $view): void
	{
		/** @var BackendTemplateView $view */
		parent::initializeView($view);

		// Load the JS
		if ($view instanceof BackendTemplateView) {
			$view->getModuleTemplate()->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/ModuleDataListing/FeUsersDataTable');
		}
	}

	/**
	 * Render DataTables ajax call
	 */
	public function renderAjax(ServerRequestInterface $request): Response
	{
		$params = $request->getQueryParams();
		$uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

		// Get the bills
		$tableData = $this->getTableData($params);

		// Get the bill count for pagination
		$count = $this->getCount($params);

		// Format payment data
		$data = [];
		foreach ($tableData as $row) {
			// Build the edit link
			$returnUrl = $uriBuilder->buildUriFromRoute('datalisting_ModuleDataListingTxModuleDataListingFeusers', []);

			$uriParameters = [
				'edit' => [
					'fe_users' => [
						$row['uid'] => 'edit',
					],
				],
				'returnUrl' => $returnUrl->getPath() . '?' . $returnUrl->getQuery(),
			];
			$editLink = $uriBuilder->buildUriFromRoute('record_edit', $uriParameters);

			// Wrap the uid in the edit link
			$row['uid'] = '<a href="' . $editLink . '" title="Edit record">' . $row['uid'] . '</a>';

			// Lookup the usergroups and replace IDs with titles
			$usergroups = [];
			foreach (explode(',', $row['usergroup']) as $usergroupUid) {
				$usergroups[] = parent::usergroupLookup($usergroupUid);
			}
			$row['usergroup'] = implode(', ', $usergroups);

			$data[] = array_values($row);
		}

		$return = [
			"draw" => $params['draw'],
			"recordsTotal" => $count,
			"recordsFiltered" => $count,
			"data" => $data,
		];

		$response = new Response();

		$response->getBody()->write(json_encode($return));

		return $response;
	}

	/**
	 * Default action: index
	 */
	public function indexAction(): void
	{
		$this->view->assignMultiple([
			'headers' => array_values(parent::getHeaders($this->headers)),
			'groups' => $this->getUsergroups(),
		]);
	}

	/**
	 * Get the count of rows
	 */
	private function getCount(array $params): int
	{
		$connection = parent::getConnection('fe_users');
		$queryBuilder = $connection->createQueryBuilder();

		/**
		 * Users without attached fees were not returned in the count due to null values
		 * Removing restrictions and re-apply to fe_users only solves this
		 * @todo TYPO3 v10+ has a cleaner way of doing this: https://docs.typo3.org/m/typo3/reference-coreapi/10.4/en-us/ApiOverview/Database/RestrictionBuilder/Index.html#limitrestrictionstotables
		*/
		$queryBuilder
		->getRestrictions()
		->removeAll()
		;

		$query = $queryBuilder
			->count('fe_users.uid')
			->from('fe_users')
			->where(
				$queryBuilder->expr()->eq(
					'fe_users.deleted',
					0
				),
				$queryBuilder->expr()->eq(
					'fe_users.disable',
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

		// Apply search
		$query = $this->applySearch($queryBuilder, $query, $params);

		$count = $query
			->execute()
			->fetchColumn(0)
		;

		return (int)$count;
	}

	/**
	 * Get the table data
	 */
	private function getTableData(array $params): array
	{
		$connection = parent::getConnection('fe_users');
		$queryBuilder = $connection->createQueryBuilder();

		/**
		 * Users without attached fees were not returned in the count due to null values
		 * Removing restrictions and re-apply to fe_users only solves this
		 * @todo TYPO3 v10+ has a cleaner way of doing this: https://docs.typo3.org/m/typo3/reference-coreapi/10.4/en-us/ApiOverview/Database/RestrictionBuilder/Index.html#limitrestrictionstotables
		*/
		$queryBuilder
			->getRestrictions()
			->removeAll()
		;

		// Re-apply restrictions
		$query = $queryBuilder
			->select(...array_keys(parent::getHeaders($this->headers)))
			->from('fe_users')
			->where(
				$queryBuilder->expr()->eq(
					'fe_users.deleted',
					0
				),
				$queryBuilder->expr()->eq(
					'fe_users.disable',
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
			$headerKeys = array_keys(parent::getHeaders($this->headers));
			$query = $query->orderBy($headerKeys[$order['column']], $order['dir']);
		} else {
			$query = $query->orderBy('fe_users.uid', 'DESC');
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
	 * Apply search to query
	 */
	private function applySearch(QueryBuilder $queryBuilder, QueryBuilder $query, array $params): QueryBuilder
	{
		if ($params['search']['value']) {
			$columnStr = parent::getModuleSettings()['searchableColumns'];
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
	private function applyJoins(QueryBuilder $queryBuilder, QueryBuilder $query): QueryBuilder
	{
		$joins = parent::getModuleSettings()['joins.'];

		// Apply joins from settings
		if ($joins) {
			foreach ($joins as $join) {
				switch ($join['type']) {
					case 'leftJoin':
						$query = $query
							->leftJoin(
								'fe_users',
								$join['table'],
								$join['table'],
								$queryBuilder->expr()->eq($join['table'] . '.' . $join['localIdentifier'], $queryBuilder->quoteIdentifier('fe_users.' . $join['foreignIdentifier']))
							)
						;
						break;
					case 'rightJoin':
						$query = $query
							->rightJoin(
								'fe_users',
								$join['table'],
								$join['table'],
								$queryBuilder->expr()->eq($join['table'] . '.' . $join['localIdentifier'], $queryBuilder->quoteIdentifier('fe_users.' . $join['foreignIdentifier']))
							)
						;
						break;
					case 'innerJoin':
						$query = $query
							->innerJoin(
								'fe_users',
								$join['table'],
								$join['table'],
								$queryBuilder->expr()->eq($join['table'] . '.' . $join['localIdentifier'], $queryBuilder->quoteIdentifier('fe_users.' . $join['foreignIdentifier']))
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
			$query = $query
				->where(
					$queryBuilder->expr()->orX(
						$queryBuilder->expr()->eq(
							$join['table'] . '.deleted',
							0
						),
						$queryBuilder->expr()->isNull(
							$join['table'] . '.deleted'
						),
					),
					$queryBuilder->expr()->orX(
						$queryBuilder->expr()->eq(
							$join['table'] . '.hidden',
							0
						),
						$queryBuilder->expr()->isNull(
							$join['table'] . '.hidden'
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
	private function applyFilters(QueryBuilder $queryBuilder, QueryBuilder $query, array $params): QueryBuilder
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

	/**
	 * Get the usergroups to filter by
	 */
	private function getUsergroups(): array
	{
		$connection = $this->getConnection('fe_groups');
		$queryBuilder = $connection->createQueryBuilder();

		$usergroups = $queryBuilder
			->select('title', 'uid')
			->from('fe_groups')
			->execute()
			->fetchAll()
		;

		return $usergroups;
	}
}
