<?php

namespace ELNSMWAdapterUI\Tests;

use ELNSMWAdapterUI\AdapterPageRenderer;
use MediaWikiIntegrationTestCase;

/**
 * @covers \ELNSMWAdapterUI\AdapterPageRenderer
 */
class AdapterPageRendererTest extends MediaWikiIntegrationTestCase {

	private function newRenderer(): AdapterPageRenderer {
		$context = new \RequestContext();
		$context->setLanguage( 'qqx' );
		return new AdapterPageRenderer( $context );
	}

	public function testErrorBoxEscapesText() {
		$html = $this->newRenderer()->errorBox( '<img src=x onerror=alert()>' );

		$this->assertStringContainsString( '&lt;img src=x onerror=alert()>', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringContainsString( 'class="errorbox"', $html );
	}

	public function testMessageBoxesMapTypeToCssClassAndEscapeText() {
		$html = $this->newRenderer()->messageBoxes( [
			[ 'type' => 'error', 'message' => '<b>bad</b>' ],
			[ 'type' => 'warning', 'message' => 'careful' ],
			[ 'type' => 'other', 'message' => 'fyi' ],
		] );

		$this->assertStringContainsString( 'mw-message-box-error', $html );
		$this->assertStringContainsString( 'mw-message-box-warning', $html );
		$this->assertStringContainsString( 'mw-message-box-notice', $html );
		$this->assertStringContainsString( '&lt;b>bad&lt;/b>', $html );
		$this->assertStringNotContainsString( '<b>', $html );
	}

	public function testMessageBoxesRenderNothingWithoutMessages() {
		$this->assertSame( '', $this->newRenderer()->messageBoxes( [] ) );
	}

	public function testBackToSelectionButtonLinksToGivenUrl() {
		$html = $this->newRenderer()->backToSelectionButton( '/wiki/Special:ELNSMWAdapterUI' );

		$this->assertStringContainsString( 'href="/wiki/Special:ELNSMWAdapterUI"', $html );
		$this->assertStringContainsString( '(elnsmwadapterui-back-to-selection)', $html );
	}

	public function testSelectionFormShowsOfflineStateWithoutStatus() {
		$html = $this->newRenderer()->selectionForm( null, '/action' );

		$this->assertStringContainsString( 'Service unavailable or not responding', $html );
		$this->assertStringNotContainsString( 'value="url"', $html );
	}

	public function testSelectionFormListsUrlAndUploadPlugins() {
		$html = $this->newRenderer()->selectionForm( [
			'version' => '1.2.3',
			'plugins' => [
				'eLabFTW' => [ 'type' => 'url' ],
				'excel-upload' => [ 'type' => 'upload' ],
			],
		], '/action' );

		$this->assertStringContainsString( 'value="url" selected', $html );
		$this->assertStringContainsString( 'Upload file (excel-upload)', $html );
		$this->assertStringContainsString( 'action="/action"', $html );
	}

	public function testUrlFormKeepsEnteredUrlAndToken() {
		$html = $this->newRenderer()->urlForm( '/action', 'tok+\\', 'https://elab.example/?id=1' );

		$this->assertStringContainsString( 'name="eln-url"', $html );
		$this->assertStringContainsString( 'value="https://elab.example/?id=1"', $html );
		$this->assertStringContainsString( 'tok+\\', $html );
	}

	public function testUploadFormRendersDynamicFields() {
		$html = $this->newRenderer()->uploadForm( 'excel-local', '/action', 'tok', [
			[ 'name' => 'project', 'label' => 'Project', 'type' => 'text', 'required' => true ],
			[ 'name' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => [ 'A', 'B' ] ],
		] );

		$this->assertStringContainsString( 'name="upload-file"', $html );
		$this->assertStringContainsString( 'name="field_project"', $html );
		$this->assertStringContainsString( '<option value="B">B</option>', $html );
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
		$this->assertStringContainsString( 'href="/back"', $html );
	}
}
