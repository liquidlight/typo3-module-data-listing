# 3.0.0

**22nd September 2026**

#### Feature

- BREAKING CHANGE: Support TYPO3 v12 and drop v11 (see README.md)
- BREAKING CHANGE: Register modules in `Configuration/Backend/Modules.php` rather than `ext_tables.php` (see README.md)
- BREAKING CHANGE: `indexAction()` returns a `ResponseInterface` (see README.md)
- BREAKING CHANGE: Override templates with page TSconfig rather than setup TypoScript (see README.md)
- BREAKING CHANGE: `$jsNamespace` is an ES module specifier and listings need a `Configuration/JavaScriptModules.php` (see README.md)
- New `$templateName` property declares the template a listing renders
- Load JavaScript as ES modules, using the jQuery provided by EXT:core
- Add Document, Money and Report icons

#### Fix

- Match usergroup membership with `FIND_IN_SET`, so users in more than one group are found
- Treat several checked values for one filter as "any of these"
- Search no longer builds an empty condition when `searchableColumns` is unset
- Keep the delete filter on the base table when a join is configured, rather than replacing it
- Fall back to an empty string when `searchableColumns` is unset, rather than raising a `TypeError`
- Render a single document, with the stylesheet in the head

# 2.0.0

**21st September 2026**

#### Feature

- BREAKING CHANGE: Restructure SetupTS related to `module.tx_moduledatalisting` (see README.md)
- BREAKING CHANGE: `DatatableController` has various changes to properties and methods (see README.md)
- Enabled dependency injection auto-wiring in Services.yaml

# 1.2.1

**17th February 2025**

#### Fix

- Update group/filter HTML to not look like a toggle and show contents (see README for [upgrade steps](https://github.com/liquidlight/typo3-module-data-listing#upgrading-to-120))

# 1.2.0

**17th February 2025**

#### Feature

- Upgrade DataTables to 2.x (see README for [upgrade steps](https://github.com/liquidlight/typo3-module-data-listing#upgrading-to-120))

#### Fix

- Load the ext path correctly
- Check if user ID is in the group before processing

#### Refactor

- Lots of code tidy-up, refactoring and indenting correctly

# 1.1.0

**22nd November 2023**

#### Minor
* Fixed the styles the search filters #5
* Added the icons back to the pagination using Typo3 icon set #5


# 1.0.0

**12th June 2023**

#### Major

- Remove TYPO3 v9 & v10 compatibility
