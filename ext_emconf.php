<?php

$EM_CONF[$_EXTKEY] = [
	'title' => 'Data listing for the TYPO3 backend',
	'description' => 'Filterable, searchable and sortable datatables for the TYPO3 backend. Fe_users comes as default.',
	'category' => 'be',
	'author' => 'Liquid Light',
	'author_email' => 'info@liquidlight.co.uk',
	'author_company' => 'Liquid Light Ltd',
	'state' => 'stable',
	'version' => '3.0.0',
	'constraints' => [
		'depends' => [
			'typo3' => '12.4.0-13.4.99',
		],
		'conflicts' => [
		],
		'suggests' => [
		],
	],
];
