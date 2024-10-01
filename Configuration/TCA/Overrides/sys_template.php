<?php

defined('TYPO3') || die();

call_user_func(function () {
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
		'module_data_listing',
		'Configuration/TypoScript',
		'Module Data Listing'
	);
});
