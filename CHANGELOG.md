# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Set up docker-compose-ci (DCI) based GitHub Actions CI, covering
  MediaWiki 1.39/PHP 8.1 and 1.43/PHP 8.3 (with Codecov coverage
  reporting).

### Changed

- Raise minimum required MediaWiki version from 1.35.0 to 1.39.0.
- Fix all MediaWiki-CodeSniffer violations to make `composer test` pass.

### Fixed

- Avoid calling the adapter service's `/status` endpoint twice per
  request in `displaySelectionForm()`.
- Log cURL transport errors (not just non-200 HTTP responses) when
  fetching service status, plugin info, or job results from the
  adapter service.
- Register special page aliases for `ELNSMWAdapterUI` via
  `ELNSMWAdapterUI.alias.php`. Without them, `SpecialPageFactory`
  cannot resolve a local name for the page, which breaks any code path
  relying on `getLocalNameFor()` (including `getPageTitle()->getLocalURL()`
  calls the special page itself makes when rendering form actions).

### Changed

- Extract the duplicated cURL GET/JSON-decode logic in
  `checkServiceStatus()`, `getPluginInfo()`, and `displayResultsPage()`
  into a shared `httpGetJson()` helper.
- Inject `ConfigFactory` into `SpecialELNSMWAdapterUI`'s constructor
  instead of resolving config lazily via `getConfig()` in `execute()`.
  Makes the `services` declaration in `extension.json` actually take
  effect and allows constructing the special page without a full
  request context.

### Removed

- Remove the deprecated, unreferenced `ELNSMWAdapterUISpecialPage.php`
  compatibility shim.
- Remove the unused `validateCSRFToken()` and `handleFormSubmission()`
  methods from `SpecialELNSMWAdapterUI`.
