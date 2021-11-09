<?php

use LiquidLight\ModuleDataListing\Controller\FeUsersController;

/**
 * Definitions for AJAX routes provided by EXT:module_data_listing
 */
return [
	'module_data_listing_get_fe_users' => [
		'path' => '/ll-module-data-listing/get-fe-users',
		'target' => FeUsersController::class . '::renderAjax',
	],
];
