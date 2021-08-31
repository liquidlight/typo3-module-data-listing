<?php

use LiquidLight\BackendModulesDatatables\Controller\FeUsersController;

/**
 * Definitions for AJAX routes provided by EXT:backend_modules_datatables
 */
return [
	'backend_modules_datatables_get_fe_users' => [
		'path' => '/ll-backend-modules-datatables/get-fe-users',
		'target' => FeUsersController::class . '::renderAjax',
	],
];
