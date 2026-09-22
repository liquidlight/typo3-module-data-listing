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
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

abstract class DatatableController extends ActionController
{
	/**
	 * ES module specifier of the JavaScript for this listing
	 *
	 * @var ?string
	 */
	protected $jsNamespace = null;

	protected string $configurationName;

	/**
	 * Template to render, relative to Resources/Private/Templates
	 */
	protected string $templateName;

	protected ?ModuleTemplate $moduleTemplate = null;

	protected string $table;

	protected array $headers;

	protected array $columnSelectOverrides;

	protected string $searchableColumns;

	protected array $joins;

	public function __construct(
		ConfigurationManagerInterface $configurationManagerInterface,
		protected ConnectionPool $connectionPool,
		protected ModuleTemplateFactory $moduleTemplateFactory,
		protected PageRenderer $pageRenderer
	) {
		$setup = $configurationManagerInterface->getConfiguration(
			ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
		);

		if (!$configuration = $setup['module.']['tx_moduledatalisting.']['configuration.'][$this->configurationName . '.'] ?? false) {
			throw new Exception(sprintf(
				'Missing expected SetupTS definition for module.tx_moduledatalisting.configuration.%s',
				$this->configurationName,
			), 1790069492);
		}

		$this->table = $configuration['table'] ?? $this->table;
		$this->headers = $configuration['headers.'] ?? $this->headers ?? [];
		$this->columnSelectOverrides = $configuration['columnSelectOverrides.'] ?? $this->columnSelectOverrides ?? [];
		$this->joins = $configuration['joins.'] ?? $this->joins ?? [];
		$this->searchableColumns = $configuration['searchableColumns'] ?? $this->searchableColumns ?? '';

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

		$query->from($this->table);

		$query
			->getRestrictions()
			->removeAll()
			->add(GeneralUtility::makeInstance(DeletedRestriction::class))
		;

		// Re-apply restrictions
		$this
			->applyDeleteFilter($query, $this->table, $this->table)
			->applyJoins($query)
			->applyFilters($query, $params)
			->applySearch($query, $params)
		;

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
			$query->setFirstResult($params['start']);
		}

		// Order
		$this->applyOrder($query, $params);

		// Page size
		if ($params['length'] > 0) {
			$query
				->setMaxResults($params['length'])
			;
		}

		$data = $query
			->executeQuery()
			->fetchAllAssociative()
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

		$count = $query->executeQuery()->fetchOne();

		return (int)$count;
	}

	/**
	 * Apply search to query
	 */
	protected function applySearch(QueryBuilder $query, array $params): self
	{
		if ($params['search']['value']) {
			$searchableColumns = GeneralUtility::trimExplode(',', $this->searchableColumns, true);

			$expressions = [];

			foreach ($searchableColumns as $field) {
				$param = $query->createNamedParameter('%' . $query->escapeLikeWildcards($params['search']['value']) . '%');

				$expressions[] = isset($this->columnSelectOverrides[$field]) ?
					// If we have a column override we need to filter on that
					// override and not the field (alias) itself
					sprintf('%s LIKE %s', $this->columnSelectOverrides[$field], $param) :
					// Otherwise we can filter directly off the field itself
					$query->expr()->like($field, $param);
			}

			// An empty `searchableColumns` would otherwise build a composite
			// expression with no parts, which renders as an empty WHERE
			if ($expressions) {
				$query->andWhere($query->expr()->or(...$expressions));
			}
		}

		return $this;
	}

	/**
	 * Apply joins to query
	 */
	protected function applyJoins(QueryBuilder $query): self
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
					), 1790069493);
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
				), 1790069494);
			}

			// Perform the join
			$query->$type($this->table, $table, $alias, $on);

			$this->applyDeleteFilter($query, $table, $alias);
		}

		return $this;
	}

	protected function applyDeleteFilter(QueryBuilder $query, string $table, string $alias, bool $restrict = true): self
	{
		// Exclude anything that is deleted
		if ($deleteFiled = $GLOBALS['TCA'][$table]['ctrl']['delete'] ?? false) {
			$deleteFiled = $alias . '.' . $deleteFiled;
			$query->andWhere(
				$query->expr()->or($query->expr()->eq($deleteFiled, 0), $query->expr()->isNull($deleteFiled)),
			);
		}

		return $this;
	}

	/**
	 * Apply filters to query
	 */
	protected function applyFilters(QueryBuilder $query, array $params): self
	{
		foreach ($params['filters'] ?? [] as $field => $filter) {
			$values = array_filter(
				is_array($filter) ? $filter : [$filter],
				static fn ($value): bool => (string)$value !== '',
			);

			if (!$values) {
				continue;
			}

			$expressions = [];

			foreach ($values as $value) {
				$expressions[] = $field === 'usergroup' ?
					// `usergroup` holds a comma separated list of uids, so membership
					// has to be tested with FIND_IN_SET rather than equality
					$query->expr()->inSet($field, $query->createNamedParameter($value)) :
					$query->expr()->eq($field, $query->createNamedParameter($value));
			}

			// Several checked values for one filter mean "any of these"
			$query->andWhere($query->expr()->or(...$expressions));
		}

		return $this;
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
	public function indexAction(): ResponseInterface
	{
		if ($this->jsNamespace) {
			$this->pageRenderer->loadJavaScriptModule($this->jsNamespace);
		}

		$this->getModuleTemplate()->assignMultiple([
			'headers' => array_values($this->headers),
		]);

		return $this->renderHtml();
	}

	/**
	 * The module template doubles as the view
	 *
	 * Assign to it from an action, then call renderHtml() to render.
	 */
	protected function getModuleTemplate(): ModuleTemplate
	{
		return $this->moduleTemplate ??= $this->moduleTemplateFactory->create($this->request);
	}

	/**
	 * Render the view
	 */
	protected function renderHtml(): ResponseInterface
	{
		return $this->getModuleTemplate()->renderResponse($this->templateName);
	}
}
