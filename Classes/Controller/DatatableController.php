<?php

/**
 * Shared functionality used by the module views
 *
 * @author Zaq Mughal <zaq@liquidlight.co.uk>
 * @copyright Liquid Light Ltd.
 * @package TYPO3
 * @subpackage backend_modules_datatables
 */

namespace LiquidLight\BackendModulesDatatables\Controller;

use LiquidLight\BackendModulesDatatables\Datatable;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Connection;

abstract class DatatableController extends ActionController implements Datatable
{
	protected $defaultViewObjectName = BackendTemplateView::class;

	protected $extPath = '/typo3conf/ext/backend_modules_datatables';

	/**
	 * Init view and load JS
	 */
	public function initializeView(ViewInterface $view): void
	{
		/** @var BackendTemplateView $view */
		parent::initializeView($view);

		if ($view instanceof BackendTemplateView) {
			$view->getModuleTemplate()->getPageRenderer()->addRequireJsConfiguration([
				'paths' => [
					'datatables.net' => $this->extPath . '/Resources/Public/JavaScript/DataTables/jquery.dataTables.min',
					'datatables.net-buttons' => $this->extPath . '/Resources/Public/JavaScript/DataTables/dataTables.buttons.min',
					'datatables.net-buttons-print' => $this->extPath . '/Resources/Public/JavaScript/DataTables/buttons.print.min',
					'datatables.net-buttons-html5' => $this->extPath . '/Resources/Public/JavaScript/DataTables/buttons.html5.min',
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
	public function getConnection(string $table): Connection
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
}
