define([
	'jquery',
	'TYPO3/CMS/ModuleDataListing/ModuleDataListing'
], function ($, ModuleDataListing) {

	ModuleDataListing.config({
		storageKey: 'FeUsers',
		ajaxUrl: TYPO3.settings.ajaxUrls['module_data_listing_get_fe_users']
	});

	$(document).ready(function () {
		// Initialize the view
		ModuleDataListing.dataTable.init();
		ModuleDataListing.filters.init();

	});
});
