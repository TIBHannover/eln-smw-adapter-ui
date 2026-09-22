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

### Removed

- Remove the deprecated, unreferenced `ELNSMWAdapterUISpecialPage.php`
  compatibility shim.
- Remove the unused `validateCSRFToken()` and `handleFormSubmission()`
  methods from `SpecialELNSMWAdapterUI`.
