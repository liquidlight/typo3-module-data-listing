<?php

$EM_CONF[$_EXTKEY] = [
	'title' => 'Data listing for the TYPO3 backend',
	'description' => 'Interactive datatables for the TYPO3 backend',
	'category' => 'be',
	'author' => 'Zaq Mughal',
	'author_company' => 'Liquid Light Ltd',
	'author_email' => 'zaq@liquidlight.co.uk',
	'state' => 'beta',
	'version' => '0.1.0',
	'constraints' => [
		'depends' => [
			'typo3' => '9.5.0-9.5.99'
		],
		'conflicts' => [
		],
		'suggests' => [
		],
	],
];
