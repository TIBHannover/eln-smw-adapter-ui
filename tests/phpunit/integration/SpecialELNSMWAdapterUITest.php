<?php

namespace ELNSMWAdapterUI\Tests;

use ELNSMWAdapterUI\SpecialELNSMWAdapterUI;
use FauxRequest;
use PermissionsError;
use RequestContext;
use SpecialPageTestBase;

/**
 * @covers \ELNSMWAdapterUI\SpecialELNSMWAdapterUI
 * @group Database
 */
class SpecialELNSMWAdapterUITest extends SpecialPageTestBase {

	protected function newSpecialPage() {
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'ELNSMWAdapterUI' );
	}

	private function newAuthorizedPerformer() {
		return $this->getTestUser( [ 'user' ] )->getAuthority();
	}

	/**
	 * Build a special page instance with a context, for exercising public
	 * form-processing callbacks directly without going through execute().
	 * @param FauxRequest $request
	 * @return SpecialELNSMWAdapterUI
	 */
	private function newContextualizedSpecialPage( FauxRequest $request ): SpecialELNSMWAdapterUI {
		$context = new RequestContext();
		$context->setRequest( $request );
		$context->setUser( $this->getTestUser()->getUser() );
		$context->setLanguage( 'qqx' );

		/** @var SpecialELNSMWAdapterUI $page */
		$page = $this->newSpecialPage();
		$page->setContext( $context );

		return $page;
	}

	public function testExecuteRequiresElnSmwAdapterUiUsePermission() {
		$this->setGroupPermissions( 'user', 'elnsmwadapterui-use', false );
		$this->expectException( PermissionsError::class );

		$this->executeSpecialPage(
			'',
			new FauxRequest( [] ),
			null,
			$this->getTestUser()->getAuthority()
		);
	}

	public function testDefaultViewShowsSelectionForm() {
		$this->setUserLang( 'qqx' );

		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [] ),
			null,
			$this->newAuthorizedPerformer()
		);

		$this->assertStringContainsString( '(elnsmwadapterui-form-select-legend)', $html );
	}

	public function testMethodUrlShowsUrlImportForm() {
		$this->setUserLang( 'qqx' );

		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [ 'method' => 'url' ] ),
			null,
			$this->newAuthorizedPerformer()
		);

		$this->assertStringContainsString( '(elnsmwadapterui-form-legend)', $html );
		$this->assertStringContainsString( 'name="eln-url"', $html );
	}

	public function testResultsActionWithoutJobIdOrDataShowsNoResultsError() {
		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [ 'action' => 'results' ] ),
			null,
			$this->newAuthorizedPerformer()
		);

		$this->assertStringContainsString( 'No results data found.', $html );
	}

	public function testProcessingActionWithoutJobIdShowsMissingJobIdError() {
		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [ 'action' => 'processing' ] ),
			null,
			$this->newAuthorizedPerformer()
		);

		$this->assertStringContainsString( 'No job ID provided.', $html );
	}

	public function testUnknownMethodShowsUnknownMethodError() {
		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [ 'method' => 'does-not-exist' ] ),
			null,
			$this->newAuthorizedPerformer()
		);

		$this->assertStringContainsString( 'Unknown method: does-not-exist', $html );
	}

	public function testProcessFormRejectsMalformedUrl() {
		$page = $this->newContextualizedSpecialPage( new FauxRequest( [] ) );

		$result = $page->processForm( [ 'eln-url' => 'not-a-url' ] );

		$this->assertSame( 'Please provide a valid URL.', $result );
	}

	public function testProcessFormRejectsEmptyUrl() {
		$page = $this->newContextualizedSpecialPage( new FauxRequest( [] ) );

		$result = $page->processForm( [] );

		$this->assertSame( 'Please provide a valid URL.', $result );
	}

	public function testProcessFormRejectsUnsupportedElnHost() {
		$page = $this->newContextualizedSpecialPage( new FauxRequest( [] ) );

		$result = $page->processForm( [ 'eln-url' => 'https://unsupported-eln.example/view?id=1' ] );

		$this->assertSame( 'Failed to process the request.', $result );
	}

	public function testProcessFormRejectsElabftwUrlWithoutQuery() {
		$page = $this->newContextualizedSpecialPage( new FauxRequest( [] ) );

		$result = $page->processForm( [ 'eln-url' => 'https://elab.tu-clausthal.de/experiments.php' ] );

		$this->assertSame( 'Failed to process the request.', $result );
	}

	public function testProcessFormRejectsElabftwUrlWithoutId() {
		$page = $this->newContextualizedSpecialPage( new FauxRequest( [] ) );

		$result = $page->processForm( [ 'eln-url' => 'https://elab.tu-clausthal.de/experiments.php?mode=view' ] );

		$this->assertSame( 'Failed to process the request.', $result );
	}

	public function testProcessFileUploadFormRejectsMissingUpload() {
		$page = $this->newContextualizedSpecialPage( new FauxRequest( [] ) );

		$result = $page->processFileUploadForm( [ 'method' => 'some-upload-plugin' ] );

		$this->assertSame( 'Please select a file to upload.', $result );
	}

	public function testResultsActionWithDataParameterRendersProtocolsAndLogMessages() {
		$this->setUserLang( 'qqx' );

		$data = (object)[
			'smw_pages' => (object)[
				'P1' => (object)[ 'title' => 'Protocol 1' ],
			],
			'messages' => [
				(object)[ 'type' => 'error', 'text' => 'Something failed' ],
				(object)[ 'type' => 'warning', 'text' => 'Something is off' ],
				(object)[ 'type' => 'info', 'text' => 'Just so you know' ],
			],
		];
		$encoded = base64_encode( json_encode( $data ) );

		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [ 'action' => 'results', 'data' => $encoded ] ),
			null,
			$this->newAuthorizedPerformer()
		);

		$this->assertStringContainsString( '>P1<', $html );
		$this->assertStringContainsString( 'elnsmwadapterui-log-error', $html );
		$this->assertStringContainsString( 'Something failed', $html );
		$this->assertStringContainsString( 'elnsmwadapterui-log-warning', $html );
		$this->assertStringContainsString( 'Something is off', $html );
		$this->assertStringContainsString( 'elnsmwadapterui-log-notice', $html );
		$this->assertStringContainsString( 'Just so you know', $html );
	}

	public function testResultsActionWithDataParameterAndNoProtocolsShowsWarning() {
		$this->setUserLang( 'qqx' );

		$data = (object)[ 'smw_pages' => (object)[] ];
		$encoded = base64_encode( json_encode( $data ) );

		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [ 'action' => 'results', 'data' => $encoded ] ),
			null,
			$this->newAuthorizedPerformer()
		);

		$this->assertStringContainsString( '(elnsmwadapterui-warning-no-protocols)', $html );
		$this->assertStringContainsString( 'No log messages available.', $html );
	}
}
