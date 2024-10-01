/* global define, TYPO3 */
/* eslint no-unused-vars: "off" */

define([
	'jquery',
	'datatables.net',
	'datatables.net-buttons',
	'datatables.net-buttons-print',
	'datatables.net-buttons-html5'
], function () {
	var ModuleDataListing = {
		settings: {
			// The URL called when updating the datatable
			ajaxUrl: '',
			// If set, localStorage is used for rembering filters
			storageKey: null,
			// jQuery selector of your datatable
			tableSelector: '#mdl-datatable',
			// jQuery selector of button for filtering
			filterButtonSelector: '.mdl-filter-search',
			// jQuery selector of button for clearing filters
			clearButtonSelector: '.mdl-filter-clear',

			// DataTable options
			dataTable: {
				processing: true,
				serverSide: true,
				order: [[0, 'desc']],
				dom: '<\'form-inline form-inline-spaced\'lf>prtipB',
				buttons: [
					{
						extend: 'csv',
						text: 'Download as CSV',
						className: 'btn btn-primary'
					},
					{
						extend: 'copy',
						text: 'Copy to clipboard',
						className: 'btn btn-default'
					}
				],
				pagingType: 'full_numbers',
				language: {
					emptyTable: 'No data available in table'
				},
				lengthMenu: [
					[
						10,
						25,
						50,
						100,
						-1
					],
					[
						10,
						25,
						50,
						100,
						'All'
					]
				],
				classes: {
					sLength: 'form-group',
					sLengthSelect: 'form-control input-sm',
					sFilter: 'form-group',
					sFilterInput: 'form-control input-sm'
				}
			}
		},

		// Setting config - merges options
		config: function(options = {}) {
			ModuleDataListing.settings = Object.assign({}, ModuleDataListing.settings, options);
		}
	};

	/**
	 * Local Storage
	 *
	 * Only works if `storageKey` is set.
	 *
	 * Everything is stored in a single JSON object keyed by
	 * ModuleDataListing.${settings.storageKey}
	 */
	ModuleDataListing.storage = {

		/**
		 * Get an item from localStorage
		 */
		get: function(key) {
			if (!ModuleDataListing.settings.storageKey) {
				return null;
			}

			let storage = localStorage.getItem(
				'ModuleDataListing.' + ModuleDataListing.settings.storageKey
			);
			storage = storage ? JSON.parse(storage) : {};

			return storage.hasOwnProperty(key) ? storage[key] : null;
		},

		/**
		 * Add an item to localStorage
		 */
		set: function(key, data) {
			if (!ModuleDataListing.settings.storageKey) {
				return;
			}

			// Load existing storage
			let storage = localStorage.getItem(
				'ModuleDataListing.' + ModuleDataListing.settings.storageKey
			);

			storage = storage ? JSON.parse(storage) : {};

			// Add our new data
			storage[key] = data;

			// Store it
			localStorage.setItem(
				'ModuleDataListing.' + ModuleDataListing.settings.storageKey,
				JSON.stringify(storage)
			);
		},

		/**
		 * Empty the localsotrage for this storage key
		 */
		clear: function() {
			localStorage.removeItem(
				'ModuleDataListing.' + ModuleDataListing.settings.storageKey
			)
		}
	}

	/**
	 * DataTable functions
	 *
	 * Uses https://datatables.net/
	 */
	ModuleDataListing.dataTable = {
		init: function(filters = null) {

			// See if we can get filters from storage
			if(!filters) {
				filters = ModuleDataListing.storage.get('filters');
			}

			// Add the dynamic options
			const settings = {
				search: {
					search: ModuleDataListing.storage.get('keywords')
				},

				ajax: {
					url: ModuleDataListing.settings.ajaxUrl,
					data: {
						filters: filters
					}
				},
			}

			// Initialise the datatable
			const table = $(ModuleDataListing.settings.tableSelector)
				.DataTable(
					Object.assign({}, ModuleDataListing.settings.dataTable, settings)
				);

			// Add listener for search box to update storage
			table.on('search.dt', function () {
				ModuleDataListing.storage.set('keywords', table.search());
			});

			return table;
		},

		// Remove datatable config
		destroy: function() {
			$(ModuleDataListing.settings.tableSelector).DataTable().destroy();
		},

		restart: function(filters = null) {
			ModuleDataListing.dataTable.destroy();
			ModuleDataListing.dataTable.init(filters);
		}
	};

	/**
	 * Filter
	 */
	ModuleDataListing.filters = {
		// Filter setup
		init: function () {

			// Set any filters from storage
			ModuleDataListing.filters.set(
				ModuleDataListing.storage.get('filters')
			);

			// Add click listener to search button
			$(ModuleDataListing.settings.filterButtonSelector).click(function () {
				let filters = ModuleDataListing.filters.get();
				ModuleDataListing.storage.set('filters', filters);
				ModuleDataListing.dataTable.restart(filters);
			});

			// Remove all filters when cleared
			$(ModuleDataListing.settings.clearButtonSelector).click(function () {
				ModuleDataListing.storage.clear();
				ModuleDataListing.filters.clear();
				ModuleDataListing.dataTable.restart({});
			});
		},

		/**
		 * Get an object & array of checked items
		 */
		get: function () {
			let filters = {};

			$('div[data-mdl-filter]').each(function () {
				let self = $(this);
				let filterData = [];

				self.find('input:checked').each(function () {
					filterData.push($(this).val());
				});

				filters[self.data('mdl-filter')] = filterData;
			});

			return filters;
		},

		/**
		 * Check any checkbox which were in the filters object
		 */
		set: function (filters = {}) {
			for (let filter in filters) {
				let container = $(`div[data-mdl-filter="${filter}"]`);
				for (let value of filters[filter]) {
					container.find(`input[value="${value}"]`).prop('checked', true);
				}
			}
		},

		/**
		 * Uncheck all checkboxes
		 */
		clear: function () {
			$('div[data-mdl-filter] input:checked').each(function() {
				$(this).prop('checked', false);
			})
		},
	}

	return ModuleDataListing;
});
