# Backend Modules Datatables

## Overview

This package provides you with a backend datatable view of fe_users allowing you to sort, search and export your fe_users as well as filter by usergroup.

By default, the following columns are shown:

1. ID
2. Username
3. Usergroup
4. Title
5. First name
6. Last name
7. Email

By default, the following columns are searchable:

1. ID
2. Username
3. First name
4. Last name
5. Email

## Settings

There are a few different settings that can be set on a site-by-site basis via typoscript.

### Joins

It is possible to bolt on data from related tables by making use of the `module.tx_moduledatalisting.settings.joins` object where:
1. `type` can be leftJoin, rightJoin or innerJoin
2. `table` is the name of the related table
3. `localIdentifier` is the unique identifier of the related table
4. `foreignIdentifier` is the matching field in the fe_users table

**Setup**
```
module.tx_moduledatalisting {
	settings {
		joins {
			1 {
				type = leftJoin
				table = related_table
				localIdentifier = uid
				foreignIdentifier = related_table_uid
			}
		}
	}
}
```

### Additional columns

It is possible to pull in additional columns from the fe_users table as well as columns from any join tables by making use of the `module.tx_moduledatalisting.settings.additionalColumns` object where:
1. `table` is the name of the table you wish to pull the additional column from (this can be fe_users or any joined tables)
2. `column` is the name of the column you wish to pull in
3. `label` is the label that is used in the datatable header

**`setup`**

```
module.tx_moduledatalisting {
	settings {
		additionalColumns {
			table {
				column = label
			}
		}
	}
}
```

### Searchable columns

The default searchable columns are specified above however it is possible to add and/or remove columns from this list by making use of the `module.tx_moduledatalisting.settings.searchableColumns` object where:
1. `table` is the name of the table you wish to pull the searchable column from (this can be fe_users or any joined tables)
2. `column` is the name of the column you wish to make searchable

**`setup`**

```
module.tx_moduledatalisting {
	settings {
		searchableColumns := addToList(table.column1,table.column2)
		searchableColumns := removeFromList(table.column3)
	}
}
```

It is also possible to completely reset the searchable columns:
```
module.tx_moduledatalisting {
	settings {
		searchableColumns = table.column1,table.column2
	}
}
```

## Icons

The Module Data Listing package comes with several pre-packaged icons you can use for your custom modules. We always welcome more additions, so if you create an icon and would like it included as standard, please create an issue or submit a PR.

### Usage

To use an icon, use the `iconIdentifier` from the table below when using the `registerModule` method.

The `module-listing-users` icon is used with the default `tx_module_data_listing_feusers` module.

### Available Icons

The icons currently available are:

| iconIdentifier | preview |
|---|---|
| `module-listing-company` | ![module-listing-company](./Resources/Public/Icons/Company.svg) |
| `module-listing-map` | ![module-listing-mao](./Resources/Public/Icons/Map.svg) |
| `module-listing-tools` | ![module-listing-tools](./Resources/Public/Icons/Tools.svg) |
| `module-listing-users` | ![module-listing-users](./Resources/Public/Icons/Users.svg) |

## Upgrading to 1.2.0

Version `1.2.0` comes with DataTables 2.x, which uses the new layout property.

If you have a local JavaScript file for you custom DataTables, you can remove the `dom` property and replace with `layout`.

```diff
-	'dom': '<\'form-inline form-inline-spaced\'lf>prtipB',
+	'layout': {
+		bottom2: 'buttons',
+	},
```
