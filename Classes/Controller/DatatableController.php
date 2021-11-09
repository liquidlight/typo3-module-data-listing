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
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

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

		$extPath = PathUtility::getAbsoluteWebPath('ext/module_data_listing');

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
	protected function usergroupLookup(int $uid): string
	{
		static $cache = [];

		if (isset($cache[$uid])) {
			return $cache[$uid];
		}

		$connection = $this->getConnection('fe_groups');
		$queryBuilder = $connection->createQueryBuilder();

		$usergroup = $queryBuilder
			->select('title')
			->from('fe_groups')
			->where(
				$queryBuilder->expr()->eq('uid', $uid)
			)
			->execute()
			->fetchAll()
		;

		if (!$usergroup) {
			return false;
		}

		$cache[$uid] = $usergroup[0]['title'];

		return $cache[$uid];
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

		if ($settings = $setup['module.']['tx_moduledatalisting.']['settings.']) {
			return $settings;
		}

		return null;
	}
}
