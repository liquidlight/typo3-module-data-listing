# Backend Modules Datatables

## Overview

This package provides you with the tools to quickly build backend views of tables allowing you to sort, search and export your data as well as filter by related data.

Also provided is an example datatable listing for fe_users (`FeUsersController`) that can be filter by usergroup. `FeUsersController` by default will display the following columns; `ID`, `Username`, `Usergroup`, `Title`, `First name`, `Last name`, `Email`. The following columns are searchable: `ID`, `Username`, `First name`, `Last name`, `Email`.

## Settings

There are a few different settings that can be set on a site-by-site basis via typoscript. These are stored in the following typoscript path `module.tx_moduledatalisting.configuration.[configuration_name]` where *`configuration_name`* is derived from a classes `$configurationName` property.

### Headers

The `headers` key is a simple way to define what data is selected from the database and the associated column headers on the front-end. It contains key-value pairs where the key is a non-ambiguous field name and the value is the label of the column header.

Example headers;

```
module.tx_moduledatalisting.configuration.my_configuration {
    headers {
        my_table\.uid => The UID of this row
        my_table\.title => The title
    }
}
```

> [!Note]
> The dots (`.`) must be escaped as the full value is needed to perform the SQL select, i.e. `SELECT my_table.uid, my_table.title FROM ...".

### Joins

It is possible to add additional tables to join by making use of the `joins` object where:

* The key is an alias for the table to use (this can, for the most part, be the name of the table being joined)
* Values defined as follows;
    * `type` one of `join`, `leftJoin`, `rightJoin` or `innerJoin`
    * `table` the table to join
    * `on` the `ON` part of the join (remember to use alias names if they differ from the table's name)

Example joins;

```
module.tx_moduledatalisting.configuration.my_configuration {
    table = tx_from
    joins {
        tx_to_alias {
            table = tx_to
            type = leftJoin
            on = tx_from.this_id = tx_to_alias.that_id
        }
    }
}
```

### Searchable columns

Datatable listings provide a search box which can be configured to search only a specific set of field. Which fields can be searched is controlled by the `searchableColumns` property, which contains a comma delimited list of fields. You can mutate this value using the typoscript native list functions (`addToList`, .`removeFromList`, etc).

Example searchableColumns;
```
module.tx_moduledatalisting.configuration.my_configuration {
    # Explicitly set the columns
    searchableColumns = table.column1,table.column2
    # Append additional columns
    searchableColumns = addToList(table.column3,table.column4)
    # Remove columns
    searchableColumns = addToList(table.column2,table.column3)
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
* New property `protected array $columnSelectOverrides` maps fields to complex SQL; useful for handling computed values.
* Method `protected function getConnection(string $table): Connection` changed to `protected function getConnection(?string $table = null): Connection`. Calling without an argument uses `$this->table`.
* Method `protected function getHeaders(array $default): array` changed to `protected function getHeaders(): array`. Uses `$this->headers` internally, which was otherwise always passed-in.
* Method `indexAction(): void` implemented as per the old sub-class instructions. As a result you no longer need to define `indexAction()` to have default behaviour, you can alternatively call `parent::index()` to expand on the default behaviour.

> [!Note]
> Previously the `$table`, `$moduleName` and `$headers` properties where not _explicitly_ defined, but where expected to be defined in sub-classes. They are now explicitly defined in this class. If you have previously extended `DatatableController` you will likely need to change your definitions to match.

#### Class`LiquidLight\ModuleDataListing\Controller\FeUsersController`

* Property `protected $table` changed to `protected string $table`
* Property `protected $moduleName` changed to `protected string $configurationName`. The value of this property has also been changed to "*fe_users_*".

#### Setup TS

Previously the configuration of a datatable listing was stored in `module.[ext].settings`; essentially this would limit how many listings could be setup per-extension and stifled extensibility and clutter a key reserved for module-level settings. The values previously defined there (`joins`, `additionalColumns`, etc) have been moved to `module.tx_moduledatalisting.configuration.[configuration_name]`. This coincides with a change to `LiquidLight\ModuleDataListing\Controller\DatatableController` which has had its `$moduleName` property changes to `$configurationName`, which is used to determine which configuration to use from the ones defined in TS.

The following is now the recommended SetupTS when defining your own listing.

```
module.tx_moduledatalisting {
    configuration{
        [configuration_name] < .default
        [configuration_name] {
    		...
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

> [!Note]
> The `FeUsersController` class is no longer tied to the "default" configuration, but rather its own. located in `module.tx_moduledatalisting.configuration.fe_users` it still inherits from the `module.tx_moduledatalisting.configuration.default`, as is recommended in the block above.

Joins are defined and processed differently in version 2. The previous numerical index for joins has been replaced with a string key, that represents the alias of the joined table. The previous `localIdentifier` and `foreignIdentifier` keys have been simplified into a single `on` key, which defined the entire on statement.

```
module.tx_moduledatalisting {
	configuration {
		fe_user_groups < .fe_users
		fe_user_groups {
			joins {
				fe_groups {
					type = join
					table = fe_groups
					on = FIND_IN_SET(fe_groups.uid, fe_users.usergroup)
				}
			}
		}
	}
}
```

#### Extensibility changes

In version 1 there was a number of esoteric configurations when extending `DatatableController`: the `$table` class property set the table to use, while joins where defined in typoscript; SQL select and HTML column headers were computed from `$headers` and typoscript); Fields to search where entirely handled in TS. To make these options more consistent they can now all be set in _either_ typoscript or on an extending class, and have priority respectively.

The following in a breakdown of the class properties and their respective typoscript. Note that class properties are all defined in `DatatableListing` while the typoscript keys are relative to `module.tx_moduledatalisting.configuration.[configuration_name]`, where *`configuration_name`* is derived from a classes `$configurationName` property. This is the *only* place you can set the *`configuration_name`* for a class.

| Class Property                 | Typoscript Key                                  | Description |
|--------------------------------|-------------------------------------------------|-------------|
| `string $table`                | `table = <table_name>`                          | The SQL table name. Should be present in the TCA either as a table or as part of a column's relationship. |
| `string $searchableColumns`    | `searchableColumns = [List of searchable columns]`| Comma delimited list of table columns to perform searches using |
| `array $headers`               | `headers.[table\.column] = [Column Header]`     | Key-value pair mapping a table's column to it's display header; the same as  where the key is a non-ambiguious `$header` property used to require. You will need to escape dots (`.`), i.e. `fe_users\.username = Username`. |
| `array $columnSelectOverrides` | `columnSelectOverrides.[table\.column] = [SQL]` | Key-value pair mapping a table's column to a complex SQL expression, i.e.  where the key is a non-ambiguious `fe_users\.last_name = CONCAT(fe_users.last_name, ', ', fe_users.firstname)`. When set the key `headers` and `columnSelectOverrides` values become sorting hints, in the previous example sorting would be by `last_name` |
| `array $joins`                 | `joins.[alias] { ... }`                         | As explained above in the "Setup TS" section |

> [!Note]
> When joining tables you should use the alias in place of the table name for the purposes of `searchableColumns`, `headers`, and `columnSelectOverrides`.
