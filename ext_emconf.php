<?php

$EM_CONF[$_EXTKEY] = [
	'title' => 'Data listing for the TYPO3 backend',
	'description' => 'Filterable, searchable and sortable datatables for the TYPO3 backend. Fe_users comes as default.',
	'category' => 'be',
	'author' => 'Liquid Light',
	'author_company' => 'Liquid Light Ltd',
	'author_email' => 'developers@liquidlight.co.uk',
	'state' => 'stable',
	'version' => '1.2.1',
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
