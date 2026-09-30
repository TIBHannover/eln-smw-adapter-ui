# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.1] - 2026-09-30

Modernises the import forms so they look and behave like standard MediaWiki forms.

### Changed
- Show the method selection, URL import and file upload forms as standard MediaWiki forms with a framed section and legend; file upload keeps drag and drop onto the file button [`b9fcdb8`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/b9fcdb8)
- Show the service status as a standard message box and the import progress with a progress bar [`b9fcdb8`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/b9fcdb8)
- Name the actual cause when a form submission fails (e.g. a host that is not allowed) instead of a generic failure message [`b9fcdb8`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/b9fcdb8)
- Remove the custom form styles and the separate file upload script, which are no longer needed [`b9fcdb8`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/b9fcdb8)

### Fixed
- Translate all remaining English texts on the import pages, including the progress and error messages, and add the missing permission messages [`b9fcdb8`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/b9fcdb8)
- Avoid a deprecation warning on MediaWiki 1.41 and later when setting the page title [`b9fcdb8`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/b9fcdb8)

## [2.0.0] - 2026-09-29

Makes the extension usable on wikis other than the SFB 1368 wiki. Existing installations must now set the values below explicitly.

### Breaking Changes
- `$wgELNSMWAdapterUIAllowedELabFTWHosts` no longer defaults to `elab.tu-clausthal.de`; it is empty, so URL imports are rejected until hosts are configured [`40145b0`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/40145b0) ([#10](https://github.com/TIBHannover/eln-smw-adapter-ui/issues/10))
- `$wgELNSMWAdapterUIWikiURL` no longer defaults to the SFB 1368 wiki; when empty, links to imported pages are derived from `$wgServer` and `$wgArticlePath` [`40145b0`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/40145b0) ([#10](https://github.com/TIBHannover/eln-smw-adapter-ui/issues/10))

### Added
- Add `$wgELNSMWAdapterUIJobStatusPath` to configure the public path used to poll job status (default: `/eln-smw-adapter/job/`, formerly hardcoded to `/sfb1368/eln-smw-adapter/job/`) [`40145b0`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/40145b0) ([#10](https://github.com/TIBHannover/eln-smw-adapter-ui/issues/10))

### Changed
- Correct the extension URL in `extension.json` and the minimum MediaWiki version in the README [`40145b0`](https://github.com/TIBHannover/eln-smw-adapter-ui/commit/40145b0)

[Unreleased]: https://github.com/TIBHannover/eln-smw-adapter-ui/compare/2.0.1...HEAD
[2.0.1]: https://github.com/TIBHannover/eln-smw-adapter-ui/compare/2.0.0...2.0.1
[2.0.0]: https://github.com/TIBHannover/eln-smw-adapter-ui/compare/1.0.0...2.0.0

Older releases: [1.x](CHANGELOG-1.x.md)
