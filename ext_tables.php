<?php

defined('TYPO3_MODE') or die();

if (TYPO3_MODE === 'BE') {
	// Create a module section "LiquidLight"
	$paymentsBillsModuleConfiguration = [
		'access' => 'user,group',
		'name' => 'llbackend',
		'labels' => 'LLL:EXT:backend_modules_datatables/Resources/Private/Language/locallang_mod_llbackend.xlf',
	];
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addModule(
		'llbackend', // main module key
		'',             // submodule key
		'',             // position
		'',
		$paymentsBillsModuleConfiguration
	);

	// Display our custom module sections underneath the Web section
	$temp_TBE_MODULES = [];
	foreach ($GLOBALS['TBE_MODULES'] as $key => $val) {
		if ($key === 'web') {
			$temp_TBE_MODULES[$key] = $val;
			$temp_TBE_MODULES['llbackend'] = '';
		} else {
			$temp_TBE_MODULES[$key] = $val;
		}
	}
	$GLOBALS['TBE_MODULES'] = $temp_TBE_MODULES;


	\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
		'LiquidLight.BackendModulesDatatables',
		'llbackend',
		'tx_backend_modules_datatables_feusers',
		'bottom',
		[
			FeUsers::class => 'index',
		],
		[
			'access' => 'user,group',
			'icon' => 'EXT:backend_modules_datatables/Resources/Public/Icons/FeUsers.svg',
			'labels' => 'LLL:EXT:backend_modules_datatables/Resources/Private/Language/locallang_feusers.xlf',
			'navigationComponentId' => '',
        	'inheritNavigationComponentFromMainModule' => false,
		]
	);
}
