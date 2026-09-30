<?php

namespace ELNSMWAdapterUI\Tests;

use ELNSMWAdapterUI\AdapterPageRenderer;
use MediaWikiIntegrationTestCase;
use OutputPage;

/**
 * @covers \ELNSMWAdapterUI\AdapterPageRenderer
 */
class AdapterPageRendererTest extends MediaWikiIntegrationTestCase {

	private function newRenderer(): AdapterPageRenderer {
		// Outside of a web request nothing else sets the OOUI theme the buttons and widgets need
		OutputPage::setupOOUI();
		$context = new \RequestContext();
		$context->setLanguage( 'qqx' );
		return new AdapterPageRenderer( $context );
	}

	public function testErrorBoxEscapesText() {
		$html = $this->newRenderer()->errorBox( '<img src=x onerror=alert()>' );

		$this->assertStringContainsString( '&lt;img src=x onerror=alert()&gt;', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	public function testBackToSelectionButtonLinksToGivenUrl() {
		$html = $this->newRenderer()->backToSelectionButton( '/wiki/Special:ELNSMWAdapterUI' );

		$this->assertStringContainsString( "href='/wiki/Special:ELNSMWAdapterUI'", $html );
		$this->assertStringContainsString( '(elnsmwadapterui-back-to-selection)', $html );
	}

	public function testStatusBoxShowsOfflineStateWithoutStatus() {
		$html = $this->newRenderer()->statusBox( null );

		$this->assertStringContainsString( '(elnsmwadapterui-status-disconnected)', $html );
	}

	public function testStatusBoxShowsVersionConnectionAndPlugins() {
		$html = $this->newRenderer()->statusBox( [
			'version' => '1.2.3',
			'smw_connection' => 'ok',
			'enabled_plugins' => [ 'eLabFTW', 'excel-upload' ],
		] );

		$this->assertStringContainsString( '(elnsmwadapterui-status-connected: 1.2.3)', $html );
		$this->assertStringContainsString( '(elnsmwadapterui-status-smw-connection: ok)', $html );
		$this->assertStringContainsString( '(elnsmwadapterui-status-plugins: eLabFTW, excel-upload)', $html );
	}

	public function testProcessingShowsProgressBarAndStatusText() {
		$html = $this->newRenderer()->processing();

		$this->assertStringContainsString( 'oo-ui-progressBarWidget', $html );
		$this->assertStringContainsString( '(elnsmwadapterui-processing-status)', $html );
	}

	public function testResultsListsOnlyProtocolPagesAndEscapesLogText() {
		$result = (object)[
			'smw_pages' => (object)[ 'P1' => (object)[], 'Other' => (object)[] ],
			'messages' => [ (object)[ 'type' => 'error', 'text' => '<script>x</script>' ] ],
		];

		$html = $this->newRenderer()->results( $result, 'https://wiki.example', '/back' );

		$this->assertStringContainsString( 'https://wiki.example/P1', $html );
		$this->assertStringNotContainsString( 'wiki.example/Other', $html );
		$this->assertStringContainsString( '&lt;script&gt;x&lt;/script&gt;', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( "href='/back'", $html );
	}
}
