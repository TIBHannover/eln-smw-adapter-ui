# ELN SMW Adapter UI

A MediaWiki extension that provides a web interface for importing experiment data from eLabFTW into Semantic MediaWiki.

## Installation

1. Clone to your MediaWiki extensions directory
2. Add to `LocalSettings.php`:

```php
wfLoadExtension("ELNSMWAdapterUI");
```

## Configuration

```php
$wgELNSMWAdapterUIServiceURL = "http://localhost:5000";  // Backend service URL
$wgELNSMWAdapterUIWikiURL = "https://your-wiki.com/wiki"; // Base URL for links to imported pages (default: derived from $wgServer and $wgArticlePath)
$wgELNSMWAdapterUIJobStatusPath = "/eln-smw-adapter/job/"; // Public path (relative to $wgServer) where the browser polls job status
$wgELNSMWAdapterUIAllowedELabFTWHosts = [ "elab.example.org" ]; // Allowed eLabFTW hosts (default: none, so URL imports are rejected until set)
```

## Usage

1. Navigate to `Special:ELNSMWAdapterUI`
2. Enter an eLabFTW experiment URL
3. Click "Import Protocols"

## Requirements

- MediaWiki >= 1.39.0
- Backend adapter service running
- `elnsmwadapterui-use` permission for users
