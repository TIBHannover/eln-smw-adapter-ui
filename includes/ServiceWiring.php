<?php

declare( strict_types=1 );

use ELNSMWAdapterUI\AdapterServiceClient;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
	'ELNSMWAdapterUI.AdapterServiceClient' => static function ( MediaWikiServices $services ): AdapterServiceClient {
		return new AdapterServiceClient(
			$services->getHttpRequestFactory(),
			LoggerFactory::getInstance( 'ELNSMWAdapterUI' ),
			(string)$services->getMainConfig()->get( 'ELNSMWAdapterUIServiceURL' )
		);
	},
];
