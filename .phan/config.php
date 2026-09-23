<?php

declare( strict_types=1 );

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

// Findings differ by MediaWiki core version (e.g. MediaWiki\Config\Config and
// Html::encodeJsVar() are undeclared on MW 1.39 but not on MW >= 1.41), so each
// CI matrix leg needs its own baseline. MW_VERSION is a build-time Docker arg, not a
// runtime env var, so read the actual installed core version straight from its
// defining constant instead (same $IP resolution mediawiki-phan-config itself uses).
$mwCoreIP = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : '../..';
$mwDefinesFile = "$mwCoreIP/includes/Defines.php";
$mwMinorVersion = 'default';
if ( is_readable( $mwDefinesFile ) &&
	preg_match( "/define\\(\\s*'MW_VERSION',\\s*'(\\d+\\.\\d+)/", file_get_contents( $mwDefinesFile ), $matches )
) {
	$mwMinorVersion = $matches[1];
}
$cfg['baseline_path'] = __DIR__ . "/baseline-$mwMinorVersion.php";

return $cfg;
