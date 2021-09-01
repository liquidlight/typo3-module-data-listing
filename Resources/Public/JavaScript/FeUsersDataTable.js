define([
    "jquery", 
    "datatables.net", 
    "datatables.net-buttons", 
    "datatables.net-buttons-print", 
    "datatables.net-buttons-html5"
], function() {

    function initializeDataTable(filters) {
        return $("#feusers-table").DataTable({
            "processing": true,
            "serverSide": true,
            "order": [[ 0, "desc" ]],
            "dom": "B<'form-inline form-inline-spaced'<'form-group'l><'form-group'f>rtip>",
            "buttons": [
                "csv", "print"
            ],
            "ajax": {
                "url": TYPO3.settings.ajaxUrls["backend_modules_datatables_get_fe_users"],
                "data": {
                    "filters": filters,
                }
            },
            "language": {
                "emptyTable": "No data available in table"
            },
        });
    }

    $(document).ready(function() {
        var filters = {};

        // Initialize the view
        initializeDataTable(filters);

        // Destroy the table and reinit with year filter
        $(".searchUsergroups").click(function() {
            var groups = [];
            $('.usergroups input:checked').each(function() {
                groups.push($(this).val());
            });
            $("#feusers-table").DataTable().destroy();

            filters['usergroup'] = groups;

            initializeDataTable(filters);
        });
    });


});