<?php

return [
	'dependencies' => [
		'core',
		'backend',
	],
	'imports' => [
		'@liquidlight/module-data-listing/' => [
			'path' => 'EXT:module_data_listing/Resources/Public/JavaScript/',
			'exclude' => [
				'EXT:module_data_listing/Resources/Public/JavaScript/DataTables/',
			],
		],
		// jquery itself is provided by EXT:core
		'datatables.net' => 'EXT:module_data_listing/Resources/Public/JavaScript/DataTables/dataTables.min.mjs',
		'datatables.net-buttons' => 'EXT:module_data_listing/Resources/Public/JavaScript/DataTables/dataTables.buttons.min.mjs',
		'datatables.net-buttons-html5' => 'EXT:module_data_listing/Resources/Public/JavaScript/DataTables/buttons.html5.min.mjs',
		'datatables.net-buttons-print' => 'EXT:module_data_listing/Resources/Public/JavaScript/DataTables/buttons.print.min.mjs',
	],
];
