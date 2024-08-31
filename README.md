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

## Upgrading from v1 to v2

There number of critical differences between v1 and v2.

### Overview of Changes

#### Classes `LiquidLight\ModuleDataListing\Controller\DatatableController`

* Changed property `$table` to `protected string $table`
* Changed property `$moduleName` to `protected string $configurationName`
* Changed property `$headers` to `protected array $headers`
* New property `protected array $columnSelectOverrides` mapls fields to complex SQL; useful for handling computed values.
* Method `protected function getConnection(string $table): Connection` changed to `protected function getConnection(?string $table = null): Connection`. Calling without an argument uses `$this->table`.
* Method `protected function getHeaders(array $default): array` changed to `protected function getHeaders(): array`. Uses `$this->headers` internally, which was otherwise always passed-in.
* Method `indexAction(): void` implemented as per the old sub-class instructions. As a result you no longer need to define `indexAction()` to have default behaviour, you can alternatively call `parent::index()` to expand on the default behaviour.

> [!Note]
> Previously the `$table`, `$moduleName` and `$headers` properties where not _actually_ defined in the previous version but where expected to be defined in sub-classes. They are now explicitly defined in this class. If you have previously extended `DatatableController` you will likely need to change your defitions to match.

#### Class`LiquidLight\ModuleDataListing\Controller\FeUsersController`

* Property `protected $table` changed to `protected string $table`
* Property `protected $moduleName` changed to `protected string $configurationName`. The value of this property has also been changed to `fe_users`.

#### Setup TS

Previously the configuration of a datatable listing was stored in `module.[ext].settings`; essentially this would limit how many listings could be setup per-extension and stifled extensibility and clutter a key reserved for module-level settings. The values previously defined there (`joins`, `additionalColumns`, etc) have been moved to `module.tx_moduledatalisting.configuration.[configuration_name]`. This coincides with a change to `LiquidLight\ModuleDataListing\Controller\DatatableController` which has had its `$moduleName` property changes to `$configurationName`, which is used to determine which configuration to use from the ones defined in TS.

The following is now the recommended SetupTS when defining your own listing.

```
module.tx_moduledatalisting {
    configuration{
        [configuration_name] < .default
        [configuration_name] {
    		searchableColumns = [tablename].uid, [tablename].[field], ...
        }
    }
}

module.[tx_myextension] {
    view < module.tx_moduledatalisting.view
	view {
		templateRootPaths.1725047881 = EXT:[my_extension]/Resources/Private/Backend/Templates/
		layoutRootPaths.1725047881 = EXT:[my_extension]/Resources/Private/Backend/Layouts/
		partialRootPaths.1725047881 = EXT:[my_extension]/Resources/Private/Backend/Partials/
	}
}
```
