<?php

/**
 * Render the fe_users table
 *
 * @author Zaq Mughal <zaq@liquidlight.co.uk>
 * @copyright Liquid Light Ltd.
 * @package TYPO3
 * @subpackage backend_modules_datatables
 */

namespace LiquidLight\BackendModulesDatatables\Controller;

use LiquidLight\BackendModulesDatatables\Controller\DatatableController;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

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
		'uid' => 'ID',
		'username' => 'Username',
		'usergroup' => 'Group',
		'title' => 'Title',
		'first_name' => 'First Name',
		'last_name' => 'Last Name',
		'email' => 'Email',
	];

	/**
	 * Init view
	 */
	public function initializeView(ViewInterface $view): void
	{
		/** @var BackendTemplateView $view */
		parent::initializeView($view);

		if ($view instanceof BackendTemplateView) {
			$view->getModuleTemplate()->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/BackendModulesDatatables/FeUsersDataTable');
		}
	}

	/**
	 * Render DataTables ajax call
	 */
	public function renderAjax(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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

		$response->getBody()->write(json_encode($return));

		return $response;
	}

	/**
	 * Default action: index
	 */
	public function indexAction(): void
	{
		$this->view->assignMultiple([
			'headers' => array_values($this->headers),
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
			->select(...array_keys($this->headers))
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
			$headerKeys = array_keys($this->headers);
			$query = $query->orderBy($headerKeys[$order['column']], $order['dir']);
		} else {
			$query = $query->orderBy('fe_users.uid', 'DESC');
		}

		// Search
		if ($params['search']['value']) {
			$query = $query
				->andWhere(
					$queryBuilder->expr()->like(
						'fe_users.name',
						$queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($params['search']['value']) . '%')
					)
				)
				->orWhere(
					$queryBuilder->expr()->like(
						'fe_users.username',
						$queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($params['search']['value']) . '%')
					)
				)
				->orWhere(
					$queryBuilder->expr()->like(
						'fe_users.email',
						$queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($params['search']['value']) . '%')
					)
				)
				->orWhere(
					$queryBuilder->expr()->like(
						'fe_users.uid',
						$queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($params['search']['value']) . '%')
					)
				)
			;
		}

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
	 * Apply joins to query
	 */
	private function applyJoins(QueryBuilder $queryBuilder, QueryBuilder $query): QueryBuilder
	{
		// Apply joins from settings
		if (isset($this->settings['joins'])) {
			foreach ($this->settings['joins'] as $i => $join) {
				switch ($join['type']) {
					case 'leftJoin':
						$query = $query
							->leftJoin(
								'fe_users',
								$join['table'],
								$i,
								$queryBuilder->expr()->eq($i . $join['localIdentifier'], $queryBuilder->quoteIdentifier('fe_users.' . $join['foreignIdentifier']))
							)
						;
						break;
					case 'rightJoin':
						$query = $query
							->rightJoin(
								'fe_users',
								$join['table'],
								$i,
								$queryBuilder->expr()->eq($i . $join['localIdentifier'], $queryBuilder->quoteIdentifier('fe_users.' . $join['foreignIdentifier']))
							)
						;
						break;
					case 'innerJoin':
						$query = $query
							->innerJoin(
								'fe_users',
								$join['table'],
								$i,
								$queryBuilder->expr()->eq($i . $join['localIdentifier'], $queryBuilder->quoteIdentifier('fe_users.' . $join['foreignIdentifier']))
							)
						;
						break;
				}
			}
		}

		// Apply restrictions for joins from settings
		if (!isset($this->settings['joins'])) {
			return $query;
		}

		for ($i = 1; $i <= count($this->settings['joins']); $i++) {
			$query = $query
				->where(
					$queryBuilder->expr()->orX(
						$queryBuilder->expr()->eq(
							$i . '.deleted',
							0
						),
						$queryBuilder->expr()->isNull(
							$i . '.deleted'
						),
					),
					$queryBuilder->expr()->orX(
						$queryBuilder->expr()->eq(
							$i . '.hidden',
							0
						),
						$queryBuilder->expr()->isNull(
							$i . '.hidden'
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
