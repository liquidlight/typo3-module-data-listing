<?php

return [
	'datalisting' => [
		'access' => 'user',
		'path' => '/module/datalisting',
		'iconIdentifier' => 'modulegroup-datalisting',
		'labels' => 'LLL:EXT:module_data_listing/Resources/Private/Language/locallang_mod_datalisting.xlf',
		'position' => [
			'after' => 'file',
		],
	],
	'datalisting_feusers' => [
		'parent' => 'datalisting',
		'access' => 'user',
		'iconIdentifier' => 'module-listing-users',
		'navigationComponent' => '',
		'labels' => 'LLL:EXT:module_data_listing/Resources/Private/Language/locallang_feusers.xlf',
		'extensionName' => 'ModuleDataListing',
		'controllerActions' => [
			'LiquidLight\ModuleDataListing\Controller\FeUsersController' => [
				'index',
			],
		],
	],
];
