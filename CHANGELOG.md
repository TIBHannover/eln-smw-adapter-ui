# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Set up docker-compose-ci (DCI) based GitHub Actions CI, covering
  MediaWiki 1.39/PHP 8.1 and 1.43/PHP 8.3 (with Codecov coverage
  reporting).
- Add Phan static analysis (`composer phan`), wired into `composer
  analyze`/CI, with an initial baseline for pre-existing findings.

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
- Fix double-escaped output in `displaySelectionForm()` and
  `renderDynamicField()` where values were run through
  `htmlspecialchars()` before also being passed to `Html::element()`.
- Encode values embedded in the polling `<script>` added by
  `displayProcessingPage()` with `Html::encodeJsVar()` (falling back to
  the deprecated `Xml::encodeJsVar()` on MediaWiki &lt; 1.41) instead of
  `json_encode()`, per Phan's `SecurityCheck-XSS` finding.

### Changed

- Extract the duplicated cURL GET/JSON-decode logic in
  `checkServiceStatus()`, `getPluginInfo()`, and `displayResultsPage()`
  into a shared `httpGetJson()` helper.
- Inject `ConfigFactory` into `SpecialELNSMWAdapterUI`'s constructor
  instead of resolving config lazily via `getConfig()` in `execute()`.
  Makes the `services` declaration in `extension.json` actually take
  effect and allows constructing the special page without a full
  request context.
- Replace raw `curl_*` calls in `httpGetJson()` and
  `callAdapterService()` with MediaWiki's `HttpRequestFactory`
  (injected via the constructor). No change to the requests sent to
  the adapter service (same timeouts, SSL verification, redirect
  behaviour, and headers); this only makes the HTTP layer mockable in
  PHPUnit, which was previously impossible without a running adapter
  service.
- Add native PHP type declarations and `declare(strict_types=1)` to
  `SpecialELNSMWAdapterUI`, following this project's coding
  conventions. `checkServiceStatus()` and `getPluginInfo()` now
  explicitly return `null` if the adapter service responds with
  well-formed but non-object/array JSON, instead of passing that value
  through to callers that assume array access will work on it.

### Removed

- Remove the deprecated, unreferenced `ELNSMWAdapterUISpecialPage.php`
  compatibility shim.
- Remove the unused `validateCSRFToken()` and `handleFormSubmission()`
  methods from `SpecialELNSMWAdapterUI`.
