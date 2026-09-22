/* global TYPO3 */

import ModuleDataListing from '@liquidlight/module-data-listing/ModuleDataListing.js';

ModuleDataListing.config({
	storageKey: 'FeUsers',
	ajaxUrl: TYPO3.settings.ajaxUrls['module_data_listing_get_fe_users']
});

ModuleDataListing.dataTable.init();
ModuleDataListing.filters.init();
