<?php

/**
 * Concrete subclass used to exercise the abstract DatatableController
 *
 * The base class keeps its query-building helpers protected; this fixture
 * exposes them so they can be asserted on directly.
 *
 * @author Mike Street <mike@liquidlight.co.uk>
 * @copyright Liquid Light Ltd.
 * @package TYPO3
 * @subpackage module_data_listing
 */

namespace LiquidLight\ModuleDataListing\Tests\Unit\Controller\Fixtures;

use LiquidLight\ModuleDataListing\Controller\DatatableController;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

class TestDatatableController extends DatatableController
{
	protected string $configurationName = 'test';

	protected string $table = 'fe_users';

	protected string $templateName = 'Test/Index';

	public function callGetNewQueryBuilder(?string $table = null): QueryBuilder
	{
		return $this->getNewQueryBuilder($table);
	}

	public function callPrepareQuery(array $params): QueryBuilder
	{
		return $this->prepareQuery($params);
	}

	public function callApplySearch(QueryBuilder $query, array $params): DatatableController
	{
		return $this->applySearch($query, $params);
	}

	public function callApplyJoins(QueryBuilder $query): DatatableController
	{
		return $this->applyJoins($query);
	}

	public function callApplyFilters(QueryBuilder $query, array $params): DatatableController
	{
		return $this->applyFilters($query, $params);
	}

	public function callApplyDeleteFilter(QueryBuilder $query, string $table, string $alias): DatatableController
	{
		return $this->applyDeleteFilter($query, $table, $alias);
	}

	public function exposeHeaders(): array
	{
		return $this->headers;
	}
}
