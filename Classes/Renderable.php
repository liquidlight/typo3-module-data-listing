<?php

/**
 * Backend module top level interface
 *
 * This defines the required functions needed to render a view
 *
 * @author Zaq Mughal <zaq@liquidlight.co.uk>
 * @copyright Liquid Light Ltd.
 * @package TYPO3
 * @subpackage backend_modules_datatables
 */

namespace LiquidLight\BackendModulesDatatables;

use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface Renderable
{
	/**
	 * Init view
	 */
	public function initializeView(ViewInterface $view): void;

	/**
	 * Default action: index
	 */
	public function indexAction(): void;
}
