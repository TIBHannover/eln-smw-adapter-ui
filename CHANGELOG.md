# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-09-28

First tagged release. Adds asynchronous job processing with a polling UI, a configurable list of allowed eLabFTW hosts and a cleaned-up, lintable front end. Requires MediaWiki 1.39 or newer.

### Added
- Add asynchronous import processing with a status-polling page and plugin-specific form fields [`b4ca2c7`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/b4ca2c7)
- Add `$wgELNSMWAdapterUIAllowedELabFTWHosts` to configure which eLabFTW instances may be imported from (default: `elab.tu-clausthal.de`) [`7c27a2d`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/7c27a2d)
- Add ESLint and Stylelint checks; the file upload and processing scripts are now regular ResourceLoader modules [`48e5ac1`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/48e5ac1)

### Changed
- Raise the minimum required MediaWiki version from 1.35.0 to 1.39.0 [`736fd01`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/736fd01)
- Report malformed responses from the adapter service as an invalid response instead of failing with an error [`aecad2c`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/aecad2c)
- Escape all values rendered on the special page consistently; markup is unchanged [`da87b83`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/da87b83)
- Style the special page with BEM CSS classes instead of inline styles [`475de68`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/475de68)
- Log transport errors when contacting the adapter service [`d673e80`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/d673e80)
- Remove the unused compatibility shim `ELNSMWAdapterUISpecialPage.php` [`3eae767`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/3eae767)

### Deprecated
- Deprecate the flat CSS class names (e.g. `elnsmwadapterui-form-container`) in favour of BEM names (e.g. `elnsmwadapterui-form`). Both are still emitted, but the old ones carry no styles and will be removed in a future release [`475de68`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/475de68)

### Fixed
- Fix the special page not resolving its local name, which broke form action URLs [`a46fd34`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/a46fd34)
- Fix double-escaped text on the selection form and in dynamic fields [`ee38a16`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/ee38a16)
- Fix a potential XSS via values embedded in the processing page script [`ee38a16`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/ee38a16)
- Fix fatal error on MediaWiki 1.39 caused by a type hint [`c5c55ab`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/c5c55ab)
- Fix the selection form requesting the service status twice [`21134da`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/21134da)
- Fix the template parser failing to load [`a7bd47e`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/a7bd47e)

[Unreleased]: https://github.com/TIBHannover/eln-smw-adapter-ui/compare/1.0.0...HEAD
[1.0.0]: https://github.com/TIBHannover/eln-smw-adapter-ui/releases/tag/1.0.0
