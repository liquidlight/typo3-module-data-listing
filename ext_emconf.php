<?php

$EM_CONF[$_EXTKEY] = [
	'title' => 'Data listing for the TYPO3 backend',
	'description' => 'Filterable, searchable and sortable datatables for the TYPO3 backend. Fe_users comes as default.',
	'category' => 'be',
	'author' => 'Zaq Mughal',
	'author_company' => 'Liquid Light Ltd',
	'author_email' => 'zaq@liquidlight.co.uk',
	'state' => 'beta',
	'version' => '0.6.0',
	'constraints' => [
		'depends' => [
			'typo3' => '11.5.0-11.5.99',
		],
		'conflicts' => [
		],
		'suggests' => [
		],
	],
];
