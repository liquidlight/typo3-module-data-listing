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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsController]

class FeUsersController extends DatatableController
{
	/**
	 * Table
	 *
	 * @access protected
	 */
	protected string $table = 'fe_users';

	/**
	 * Table headers
	 *
	 * @var array<string, string>
	 */
	protected array $headers = [
		'fe_users.uid' => 'ID',
		'fe_users.username' => 'Username',
		'fe_users.usergroup' => 'Group',
		'fe_users.title' => 'Title',
		'fe_users.first_name' => 'First Name',
		'fe_users.last_name' => 'Last Name',
		'fe_users.email' => 'Email',
	];

	/**
	 * Unix timestamp columns to be processed
	 *
	 * @var array<string>
	 */
	protected array $dateColumns = [
		'tstamp',
		'starttime',
		'endtime',
		'crdate',
		'lastlogin',
		'is_online',
	];

	/**
	 * The modules name
	 *
	 * @var string
	 */
	protected string $configurationName = 'fe_users';

	/**
	 * JS file namespace
	 *
	 * @var ?string
	 */
	protected $jsNamespace = 'TYPO3/CMS/Cpd/AnnualSubmissionsDataTable';

	/**
	 * Render DataTables ajax call
	 */
	public function renderAjax(ServerRequestInterface $request): Response
	{
		$params = $request->getQueryParams();
		$uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

		// Get the data
		$tableData = parent::getTableData($params);

		// Get the data count for pagination
		$count = parent::getCount($params);

		// Format data
		$data = [];
		foreach ($tableData as $row) {
			// Build the edit link
			$returnUrl = $uriBuilder->buildUriFromRoutePath('/module/datalisting/feusers');

			$uriParameters = [
				'edit' => [
					$this->table => [
						$row['uid'] => 'edit',
					],
				],
				'returnUrl' => $returnUrl->getPath() . '?' . $returnUrl->getQuery(),
			];
			$editLink = $uriBuilder->buildUriFromRoute('record_edit', $uriParameters);

			// Wrap the uid in the edit link
			$row['uid'] = '<a href="' . $editLink . '" title="Edit record">' . $row['uid'] . '</a>';

			// Lookup the usergroups and replace IDs with titles
			if (isset($row['usergroup'])) {
				$usergroups = array_filter(
					array_map(
						function ($usergroupUid): ?string {
							$usergroupUid = (int)$usergroupUid;

							return $usergroupUid ? $this->getUsergroupNameByUid($usergroupUid) : null;
						},
						GeneralUtility::trimExplode(',', $row['usergroup'] ?? '')
					)
				);

				$row['usergroup'] = implode(', ', $usergroups);
			}

			// Format unix timestamp fields
			foreach ($this->dateColumns as $dateColumn) {
				if (!isset($row[$dateColumn])) {
					continue;
				}

				$row[$dateColumn] = $row[$dateColumn] ? date('d/m/Y H:i:s', $row[$dateColumn]) : 'N/A';
			}

			$data[] = array_values($row);
		}

		$return = [
			'draw' => $params['draw'],
			'recordsTotal' => $count,
			'recordsFiltered' => $count,
			'data' => $data,
		];

		return $this->jsonResponse(json_encode($return));
	}

	/**
	 * Get the usergroups to filter by
	 */
	private function getUsergroups(): array
	{
		$usergroups = $this->getNewQueryBuilder('fe_groups')
			->select('title', 'uid')->from('fe_groups')->executeQuery()->fetchAllAssociative()
		;

		return $usergroups;
	}

	/**
	 * Lookup a usergroup
	 */
	private function getUsergroupNameByUid(int $usergroupUid): ?string
	{
		static $cache = [];

		if (isset($cache[$usergroupUid])) {
			return $cache[$usergroupUid];
		}

		$queryBuilder = $this->getNewQueryBuilder();

		$usergroup = $queryBuilder
			->select('title')
			->from('fe_groups')->where($queryBuilder->expr()->eq('uid', $usergroupUid))->executeQuery()->fetchAllAssociative()
		;

		if (!$usergroup) {
			return null;
		}

		$cache[$usergroupUid] = $usergroup[0]['title'];

		return $cache[$usergroupUid];
	}
}
