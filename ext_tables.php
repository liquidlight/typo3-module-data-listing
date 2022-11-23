<?php

defined('TYPO3_MODE') or die();

if (TYPO3_MODE === 'BE') {
	// Create a module section
	$paymentsBillsModuleConfiguration = [
		'access' => 'user,group',
		'name' => 'datalisting',
		'labels' => 'LLL:EXT:module_data_listing/Resources/Private/Language/locallang_mod_datalisting.xlf',
	];
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
		'datalisting',
		'',
		'',
		'',
		$paymentsBillsModuleConfiguration
	);

	// Display our custom module sections underneath the Web section
	$temp_TBE_MODULES = [];
	foreach ($GLOBALS['TBE_MODULES'] as $key => $val) {
		if ($key === 'web') {
			$temp_TBE_MODULES[$key] = $val;
			$temp_TBE_MODULES['datalisting'] = '';
		} else {
			$temp_TBE_MODULES[$key] = $val;
		}
	}
	$GLOBALS['TBE_MODULES'] = $temp_TBE_MODULES;


	\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
		'LiquidLight.ModuleDataListing',
		'datalisting',
		'tx_module_data_listing_feusers',
		'bottom',
		[
			FeUsers::class => 'index',
		],
		[
			'access' => 'user,group',
			'icon' => 'EXT:module_data_listing/Resources/Public/Icons/FeUsers.svg',
			'labels' => 'LLL:EXT:module_data_listing/Resources/Private/Language/locallang_feusers.xlf',
			'navigationComponentId' => '',
			'inheritNavigationComponentFromMainModule' => false,
		]
	);
}
