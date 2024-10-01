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
			ajaxUrlKey: '',
			tableSelector: '#mdl-datatable',
			searchButtonSelector: '.mdl-search',

			dataTable: {
				processing: true,
				serverSide: true,
				order: [[0, 'desc']],
				layout: {
					bottom2: 'buttons',
				},
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

		config: function(options = {}) {
			ModuleDataListing.settings = Object.assign({}, ModuleDataListing.settings, options);
		}
	};

	ModuleDataListing.local = {
		get: function(key) {
			let storage = localStorage.getItem('ModuleDataListing');
			storage = JSON.parse(storage);

			return storage[key];
		},
		set: function(key, data) {
			let storage = localStorage.getItem('ModuleDataListing') ?? '';
			storage = JSON.parse(storage);

			storage[key] = data;

			localStorage.setItem('ModuleDataListing', JSON.stringify(storage));
		},
	}

	ModuleDataListing.dataTable = {
		init: function(filters) {
			const settings = {
				search: {
					search: ''
				},
				ajax: {
					url: TYPO3.settings.ajaxUrls[ModuleDataListing.settings.ajaxUrlKey],
					data: {
						filters: filters
					}
				},
			}
			const table = $(ModuleDataListing.settings.tableSelector)
				.DataTable(
					Object.assign({}, ModuleDataListing.settings.dataTable, settings)
				);

			table.on('search.dt', function () {
				// // Get the current URL
				// let url = new URL(window.location);
				// url.searchParams.set('search', table.search());
				// window.history.pushState({}, '', url);
			});

			return table;
		},

		destroy: function() {
			$(ModuleDataListing.settings.tableSelector).DataTable().destroy();
		},

		restart: function(filters) {
			ModuleDataListing.dataTable.destroy();
			ModuleDataListing.dataTable.init(filters);
		}
	};

	ModuleDataListing.filters = {
		init: function () {
			$(ModuleDataListing.settings.searchButtonSelector).click(function () {
				let filters = {}

				$('div[data-mdl-filter]').each(function () {
					let self = $(this);
					let filterData = [];

					self.find('input:checked').each(function () {
						filterData.push($(this).val());
					});

					filters[self.data('mdl-filter')] = filterData;
				});

				ModuleDataListing.dataTable.restart(filters);
			});
		}
	}

	return ModuleDataListing;
});
