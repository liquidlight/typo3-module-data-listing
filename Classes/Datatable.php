<?php

/**
 * Backend module datatable interface
 *
 * This defines the required functions needed to render a datatable
 *
 * @author Zaq Mughal <zaq@liquidlight.co.uk>
 * @copyright Liquid Light Ltd.
 * @package TYPO3
 * @subpackage backend_modules_datatables
 */

namespace LiquidLight\BackendModulesDatatables;

use LiquidLight\BackendModulesDatatables\Renderable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface Datatable extends Renderable
{
	/**
	 * Render DataTables ajax call
	 */
	public function renderAjax(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface;
}