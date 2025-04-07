<?php

$EM_CONF[$_EXTKEY] = [
	'title' => 'Data listing for the TYPO3 backend',
	'description' => 'Filterable, searchable and sortable datatables for the TYPO3 backend. Fe_users comes as default.',
	'category' => 'be',
	'author' => 'Zaq Mughal',
	'author_company' => 'Liquid Light Ltd',
	'author_email' => 'zaq@liquidlight.co.uk',
	'state' => 'stable',
	'version' => '1.1.0',
	'constraints' => [
		'depends' => [
			'typo3' => '12.4.0-12.4.99',
		],
		'conflicts' => [
		],
		'suggests' => [
		],
	],
];
