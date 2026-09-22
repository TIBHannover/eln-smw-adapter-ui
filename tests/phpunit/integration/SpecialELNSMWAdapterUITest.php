<?php

namespace ELNSMWAdapterUI\Tests;

use FauxRequest;
use PermissionsError;
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
}
