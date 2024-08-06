<?php

/**
 * Backend module top level interface
 *
 * This defines the required functions needed to render a view
 *
 * @author Zaq Mughal <zaq@liquidlight.co.uk>
 * @copyright Liquid Light Ltd.
 * @package TYPO3
 * @subpackage module_data_listing
 */

namespace LiquidLight\ModuleDataListing;

use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

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
