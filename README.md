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
$wgELNSMWAdapterUIWikiURL = "https://your-wiki.com";     // Target wiki URL
```

## Usage

1. Navigate to `Special:ELNSMWAdapterUI`
2. Enter an eLabFTW experiment URL
3. Click "Import Protocols"

## Requirements

- MediaWiki >= 1.35.0
- Backend adapter service running
- `elnsmwadapterui-use` permission for users
