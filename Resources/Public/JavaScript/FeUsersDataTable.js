define([
	'jquery',
	'datatables.net',
	'datatables.net-buttons',
	'datatables.net-buttons-print',
	'datatables.net-buttons-html5'
], function() {

	function initializeDataTable(filters) {
		return $('#feusers-table').DataTable({
			'processing': true,
			'serverSide': true,
			'order': [[0, 'desc']],
			'dom': '<\'form-inline form-inline-spaced\'lf>prtipB',
			'buttons': [
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
			'pagingType': 'full_numbers',
			'ajax': {
				'url': TYPO3.settings.ajaxUrls['module_data_listing_get_fe_users'],
				'data': {
					'filters': filters
				}
			},
			'language': {
				'emptyTable': 'No data available in table'
			},
			'lengthMenu': [
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
			'classes': {
				'sLength': 'form-group',
				'sLengthSelect': 'form-control input-sm',
				'sFilter': 'form-group',
				'sFilterInput': 'form-control input-sm'
			}
		});
	}

	$(document).ready(function() {
		var filters = {};

		// Initialize the view
		initializeDataTable(filters);

		// Destroy the table and reinit with year filter
		$('.searchUsergroups').click(function() {
			var groups = [];
			$('.usergroups input:checked').each(function() {
				groups.push($(this).val());
			});
			$('#feusers-table').DataTable().destroy();

			filters['usergroup'] = groups;

			initializeDataTable(filters);
		});
	});


});
