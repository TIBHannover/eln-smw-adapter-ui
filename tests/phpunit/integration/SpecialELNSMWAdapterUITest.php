<?php

namespace ELNSMWAdapterUI\Tests;

use ELNSMWAdapterUI\SpecialELNSMWAdapterUI;
use FauxRequest;
use MockHttpTrait;
use PermissionsError;
use RequestContext;
use SpecialPageTestBase;

/**
 * @covers \ELNSMWAdapterUI\SpecialELNSMWAdapterUI
 * @group Database
 */
class SpecialELNSMWAdapterUITest extends SpecialPageTestBase {

	use MockHttpTrait;

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

	public function testGetGroupNameReturnsOther() {
		$page = $this->newSpecialPage();

		$this->assertSame( 'other', $page->getGroupName() );
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

	public function testDefaultViewShowsSelectionFormWhenServiceUnreachable() {
		$this->setUserLang( 'qqx' );
		$this->installMockHttp( $this->makeFakeHttpRequest( '', 0 ) );

		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [] ),
			null,
			$this->newAuthorizedPerformer()
		);

		$this->assertStringContainsString( '(elnsmwadapterui-status-disconnected)', $html );
		$this->assertStringNotContainsString( '<form', $html );
	}

	public function testDefaultViewShowsServiceStatusWhenReachable() {
		$this->setUserLang( 'qqx' );
		$status = [
			'version' => '1.2.3',
			'smw_connection' => 'ok',
			'enabled_plugins' => [ 'eLabFTW' ],
			'plugins' => [
				'eLabFTW' => [ 'type' => 'url' ],
				'excel-upload' => [ 'type' => 'upload' ],
			],
		];
		$this->installMockHttp( $this->makeFakeHttpRequest( json_encode( $status ), 200 ) );

		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [] ),
			null,
			$this->newAuthorizedPerformer()
		);

		$this->assertStringContainsString( '(elnsmwadapterui-status-connected: 1.2.3)', $html );
		$this->assertStringContainsString( '(elnsmwadapterui-status-smw-connection: ok)', $html );
		$this->assertStringContainsString( '(elnsmwadapterui-form-select-legend)', $html );
		$this->assertStringContainsString( "name='wpeln-type'", $html );
		$this->assertStringContainsString( '(elnsmwadapterui-option-url-import)', $html );
		$this->assertStringContainsString( '(elnsmwadapterui-option-upload: excel-upload)', $html );
		$this->assertStringNotContainsString( '<h3', $html );
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
		$this->assertStringContainsString( "name='wpeln-url'", $html );
		$this->assertStringContainsString( '(elnsmwadapterui-form-url-help)', $html );
		$this->assertStringNotContainsString( '<h3', $html );
	}

	public function testResultsActionWithoutJobIdOrDataShowsNoResultsError() {
		$this->setUserLang( 'qqx' );

		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [ 'action' => 'results' ] ),
			null,
			$this->newAuthorizedPerformer()
		);

		$this->assertStringContainsString( '(elnsmwadapterui-error-no-results)', $html );
	}

	public function testProcessingActionWithoutJobIdShowsMissingJobIdError() {
		$this->setUserLang( 'qqx' );

		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [ 'action' => 'processing' ] ),
			null,
			$this->newAuthorizedPerformer()
		);

		$this->assertStringContainsString( '(elnsmwadapterui-error-no-job-id)', $html );
	}

	public function testProcessingActionUsesConfiguredJobStatusPath() {
		$this->overrideConfigValues( [
			'Server' => 'https://wiki.example.org',
			'ELNSMWAdapterUIJobStatusPath' => '/adapter/job/',
		] );
		$page = $this->newContextualizedSpecialPage(
			new FauxRequest( [ 'action' => 'processing', 'job_id' => 'abc' ] )
		);

		$page->execute( '' );

		$this->assertSame(
			'https://wiki.example.org/adapter/job/abc',
			$page->getOutput()->getJsConfigVars()['elnsmwadapteruiJobStatusUrl']
		);
	}

	public function testUnknownMethodShowsUnknownMethodError() {
		$this->setUserLang( 'qqx' );
		$this->installMockHttp( $this->makeFakeHttpRequest( '', 0 ) );

		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [ 'method' => 'does-not-exist' ] ),
			null,
			$this->newAuthorizedPerformer()
		);

		$this->assertStringContainsString( '(elnsmwadapterui-error-unknown-method: does-not-exist)', $html );
	}

	public function testProcessFormRejectsMalformedUrl() {
		$page = $this->newContextualizedSpecialPage( new FauxRequest( [] ) );

		$result = $page->processForm( [ 'eln-url' => 'not-a-url' ] );

		$this->assertTrue( $result->hasMessage( 'elnsmwadapterui-error-invalid-url' ) );
	}

	public function testProcessFormRejectsEmptyUrl() {
		$page = $this->newContextualizedSpecialPage( new FauxRequest( [] ) );

		$result = $page->processForm( [] );

		$this->assertTrue( $result->hasMessage( 'elnsmwadapterui-error-invalid-url' ) );
	}

	public function testProcessFormRejectsUnsupportedElnHost() {
		$page = $this->newContextualizedSpecialPage( new FauxRequest( [] ) );

		$result = $page->processForm( [ 'eln-url' => 'https://unsupported-eln.example/view?id=1' ] );

		$this->assertTrue( $result->hasMessage( 'elnsmwadapterui-error-unsupported-eln' ) );
	}

	public function testProcessFormAcceptsHostFromConfiguredAllowedHosts() {
		$this->overrideConfigValue( 'ELNSMWAdapterUIAllowedELabFTWHosts', [ 'Elab.Example.org' ] );
		$this->installMockHttp( $this->makeFakeHttpRequest( json_encode( [ 'job_id' => 'job-123' ] ), 200 ) );
		$page = $this->newContextualizedSpecialPage( new FauxRequest( [] ) );

		$result = $page->processForm( [ 'eln-url' => 'https://elab.example.org/experiments.php?mode=view&id=42' ] );

		$this->assertTrue( $result );
	}

	public function testProcessFormRejectsAllHostsByDefault() {
		$this->overrideConfigValue( 'ELNSMWAdapterUIAllowedELabFTWHosts', [] );
		$page = $this->newContextualizedSpecialPage( new FauxRequest( [] ) );

		$result = $page->processForm( [ 'eln-url' => 'https://elab.example.org/experiments.php?mode=view&id=42' ] );

		$this->assertTrue( $result->hasMessage( 'elnsmwadapterui-error-unsupported-eln' ) );
	}

	public function testProcessFormRejectsDefaultHostWhenNotInConfiguredAllowedHosts() {
		$this->overrideConfigValue( 'ELNSMWAdapterUIAllowedELabFTWHosts', [ 'elab.example.org' ] );
		$page = $this->newContextualizedSpecialPage( new FauxRequest( [] ) );

		$result = $page->processForm( [ 'eln-url' => 'https://elab.tu-clausthal.de/experiments.php?mode=view&id=42' ] );

		$this->assertTrue( $result->hasMessage( 'elnsmwadapterui-error-unsupported-eln' ) );
	}

	public function testProcessFormRejectsElabftwUrlWithoutQuery() {
		$this->overrideConfigValue( 'ELNSMWAdapterUIAllowedELabFTWHosts', [ 'elab.tu-clausthal.de' ] );
		$page = $this->newContextualizedSpecialPage( new FauxRequest( [] ) );

		$result = $page->processForm( [ 'eln-url' => 'https://elab.tu-clausthal.de/experiments.php' ] );

		$this->assertTrue( $result->hasMessage( 'elnsmwadapterui-error-missing-query' ) );
	}

	public function testProcessFormRejectsElabftwUrlWithoutId() {
		$this->overrideConfigValue( 'ELNSMWAdapterUIAllowedELabFTWHosts', [ 'elab.tu-clausthal.de' ] );
		$page = $this->newContextualizedSpecialPage( new FauxRequest( [] ) );

		$result = $page->processForm( [ 'eln-url' => 'https://elab.tu-clausthal.de/experiments.php?mode=view' ] );

		$this->assertTrue( $result->hasMessage( 'elnsmwadapterui-error-missing-id' ) );
	}

	public function testProcessFormSucceedsForValidElabftwUrlAndRedirectsToProcessing() {
		$this->overrideConfigValue( 'ELNSMWAdapterUIAllowedELabFTWHosts', [ 'elab.example.org' ] );
		$this->installMockHttp( $this->makeFakeHttpRequest( json_encode( [ 'job_id' => 'job-123' ] ), 200 ) );
		$page = $this->newContextualizedSpecialPage( new FauxRequest( [] ) );

		$result = $page->processForm( [ 'eln-url' => 'https://elab.example.org/experiments.php?mode=view&id=42' ] );

		$this->assertTrue( $result );
		$this->assertStringContainsString(
			'action=processing',
			$page->getOutput()->getRedirect()
		);
		$this->assertStringContainsString(
			'job_id=job-123',
			$page->getOutput()->getRedirect()
		);
	}

	public function testProcessFormReturnsServiceErrorMessageOnNon200Response() {
		$this->overrideConfigValue( 'ELNSMWAdapterUIAllowedELabFTWHosts', [ 'elab.tu-clausthal.de' ] );
		$this->installMockHttp( $this->makeFakeHttpRequest( 'Internal Server Error', 500 ) );
		$page = $this->newContextualizedSpecialPage( new FauxRequest( [] ) );

		$result = $page->processForm( [ 'eln-url' => 'https://elab.tu-clausthal.de/experiments.php?mode=view&id=42' ] );

		$this->assertTrue( $result->hasMessage( 'elnsmwadapterui-error-service-offline' ) );
	}

	public function testProcessFileUploadFormRejectsMissingUpload() {
		$page = $this->newContextualizedSpecialPage( new FauxRequest( [] ) );

		$result = $page->processFileUploadForm( [ 'method' => 'some-upload-plugin' ] );

		$this->assertTrue( $result->hasMessage( 'elnsmwadapterui-error-no-file' ) );
	}

	public function testProcessFileUploadFormRejectsWhenUploadDirNotConfigured() {
		// checkServiceStatus() returns a status without 'upload_path'.
		$this->installMockHttp( $this->makeFakeHttpRequest( json_encode( [ 'version' => '1.0' ] ), 200 ) );

		$tempName = tempnam( sys_get_temp_dir(), 'upload' );
		try {
			$request = new FauxRequest( [] );
			$request->setUploadData( [
				'wpupload-file' => [
					'name' => 'data.xlsx',
					'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
					'tmp_name' => $tempName,
					'size' => 123,
					'error' => UPLOAD_ERR_OK,
				],
			] );
			$page = $this->newContextualizedSpecialPage( $request );

			$result = $page->processFileUploadForm( [ 'method' => 'excel-local' ] );

			$this->assertTrue( $result->hasMessage( 'elnsmwadapterui-error-upload-dir' ) );
		} finally {
			if ( file_exists( $tempName ) ) {
				unlink( $tempName );
			}
		}
	}

	public function testMethodExcelUploadShowsFileUploadFormWithDynamicFields() {
		$this->setUserLang( 'qqx' );
		$status = [
			'plugins' => [
				'excel-local' => [ 'type' => 'upload' ],
			],
		];
		$pluginInfo = [
			'fields' => [
				[ 'name' => 'project', 'label' => 'Project', 'type' => 'text', 'required' => true ],
				[ 'name' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => [ 'A', 'B' ] ],
			],
		];
		$this->installMockHttp( [
			$this->makeFakeHttpRequest( json_encode( $status ), 200 ),
			$this->makeFakeHttpRequest( json_encode( $pluginInfo ), 200 ),
		] );

		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( [ 'method' => 'excel-local' ] ),
			null,
			$this->newAuthorizedPerformer()
		);

		$this->assertStringContainsString( '(elnsmwadapterui-form-upload-legend)', $html );
		$this->assertStringContainsString( '(elnsmwadapterui-form-file-help)', $html );
		$this->assertStringContainsString( "name='wpupload-file'", $html );
		$this->assertStringContainsString( "name='wpfield_project'", $html );
		$this->assertStringContainsString( "name='wpfield_category'", $html );
		$this->assertStringContainsString( "value='A'", $html );
		$this->assertStringNotContainsString( '<h3', $html );
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
		$this->assertStringContainsString( 'elnsmwadapterui-log__item--error', $html );
		$this->assertStringContainsString( 'Something failed', $html );
		$this->assertStringContainsString( 'elnsmwadapterui-log__item--warning', $html );
		$this->assertStringContainsString( 'Something is off', $html );
		$this->assertStringContainsString( 'elnsmwadapterui-log__item--notice', $html );
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
		$this->assertStringContainsString( '(elnsmwadapterui-results-no-log)', $html );
	}
}
