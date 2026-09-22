# Major

#### Feature

- BREAKING CHANGE: Support TYPO3 v12 and drop v11 (see README.md)
- BREAKING CHANGE: Register modules in `Configuration/Backend/Modules.php` rather than `ext_tables.php` (see README.md)
- BREAKING CHANGE: `indexAction()` returns a `ResponseInterface` (see README.md)
- BREAKING CHANGE: Override templates with page TSconfig rather than setup TypoScript (see README.md)
- BREAKING CHANGE: `$jsNamespace` is an ES module specifier and listings need a `Configuration/JavaScriptModules.php` (see README.md)
- New `$templateName` property declares the template a listing renders
- Load JavaScript as ES modules, using the jQuery provided by EXT:core

#### Fix

- Match usergroup membership with `FIND_IN_SET`, so users in more than one group are found
- Treat several checked values for one filter as "any of these"
- Search no longer builds an empty condition when `searchableColumns` is unset
- Keep the delete filter on the base table when a join is configured, rather than replacing it
- Fall back to an empty string when `searchableColumns` is unset, rather than raising a `TypeError`
- Render a single document, with the stylesheet in the head
