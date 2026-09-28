<?php

namespace ELNSMWAdapterUI\Tests;

use ELNSMWAdapterUI\AdapterServiceClient;
use ELNSMWAdapterUI\AdapterServiceException;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;
use Psr\Log\NullLogger;

/**
 * @covers \ELNSMWAdapterUI\AdapterServiceClient
 * @covers \ELNSMWAdapterUI\AdapterServiceException
 */
class AdapterServiceClientTest extends MediaWikiIntegrationTestCase {

	use MockHttpTrait;

	/**
	 * Must be called after installMockHttp(), so the client picks up the mocked factory.
	 */
	private function newClient(): AdapterServiceClient {
		return new AdapterServiceClient(
			$this->getServiceContainer()->getHttpRequestFactory(),
			new NullLogger(),
			'http://adapter.example/'
		);
	}

	public function testGetStatusReturnsDecodedStatus() {
		$this->installMockHttp( $this->makeFakeHttpRequest( json_encode( [ 'version' => '1.2.3' ] ), 200 ) );

		$this->assertSame( [ 'version' => '1.2.3' ], $this->newClient()->getStatus() );
	}

	public function testGetStatusReturnsNullWhenServiceUnreachable() {
		$this->installMockHttp( $this->makeFakeHttpRequest( '', 0 ) );

		$this->assertNull( $this->newClient()->getStatus() );
	}

	public function testGetStatusReturnsNullOnNon200Response() {
		$this->installMockHttp( $this->makeFakeHttpRequest( '{}', 500 ) );

		$this->assertNull( $this->newClient()->getStatus() );
	}

	public function testGetStatusReturnsNullOnNonArrayJson() {
		$this->installMockHttp( $this->makeFakeHttpRequest( '"ok"', 200 ) );

		$this->assertNull( $this->newClient()->getStatus() );
	}

	public function testGetPluginInfoReturnsDecodedInfo() {
		$info = [ 'fields' => [ [ 'name' => 'project', 'label' => 'Project', 'type' => 'text' ] ] ];
		$this->installMockHttp( $this->makeFakeHttpRequest( json_encode( $info ), 200 ) );

		$this->assertSame( $info, $this->newClient()->getPluginInfo( 'excel-local' ) );
	}

	public function testGetPluginInfoReturnsNullWhenServiceUnreachable() {
		$this->installMockHttp( $this->makeFakeHttpRequest( '', 0 ) );

		$this->assertNull( $this->newClient()->getPluginInfo( 'excel-local' ) );
	}

	public function testGetJobResultReturnsResultObject() {
		$body = json_encode( [ 'result' => [ 'smw_pages' => [ 'P1' => [] ] ] ] );
		$this->installMockHttp( $this->makeFakeHttpRequest( $body, 200 ) );

		$result = $this->newClient()->getJobResult( 'job-1' );

		$this->assertIsObject( $result );
		$this->assertObjectHasProperty( 'smw_pages', $result );
	}

	public function testGetJobResultReturnsNullWithoutResult() {
		$this->installMockHttp( $this->makeFakeHttpRequest( json_encode( [ 'status' => 'running' ] ), 200 ) );

		$this->assertNull( $this->newClient()->getJobResult( 'job-1' ) );
	}

	public function testGetJobResultReturnsNullWhenResultIsNotAnObject() {
		$this->installMockHttp( $this->makeFakeHttpRequest( json_encode( [ 'result' => 'done' ] ), 200 ) );

		$this->assertNull( $this->newClient()->getJobResult( 'job-1' ) );
	}

	public function testGetJobResultReturnsNullWhenServiceUnreachable() {
		$this->installMockHttp( $this->makeFakeHttpRequest( '', 0 ) );

		$this->assertNull( $this->newClient()->getJobResult( 'job-1' ) );
	}

	public function testSubmitJobReturnsJobId() {
		$this->installMockHttp( $this->makeFakeHttpRequest( json_encode( [ 'job_id' => 'job-123' ] ), 200 ) );

		$this->assertSame( 'job-123', $this->newClient()->submitJob( 'eLabFTW', '42', 'Alice' ) );
	}

	public function testSubmitJobThrowsServiceOfflineWhenUnreachable() {
		$this->installMockHttp( $this->makeFakeHttpRequest( '', 0 ) );

		$this->assertSubmitJobFails( 'elnsmwadapterui-error-service-offline' );
	}

	public function testSubmitJobThrowsServiceOfflineOnHttpErrorStatus() {
		$this->installMockHttp( $this->makeFakeHttpRequest( 'Internal Server Error', 500 ) );

		$this->assertSubmitJobFails( 'elnsmwadapterui-error-service-offline' );
	}

	public function testSubmitJobThrowsServiceErrorWithHttpCodeOnSuccessStatusOtherThan200() {
		$this->installMockHttp( $this->makeFakeHttpRequest( '', 204 ) );

		$this->assertSubmitJobFails( 'elnsmwadapterui-error-service-error', [ 204 ] );
	}

	public function testSubmitJobThrowsInvalidResponseOnMalformedJson() {
		$this->installMockHttp( $this->makeFakeHttpRequest( '{not json', 200 ) );

		$this->assertSubmitJobFails( 'elnsmwadapterui-error-invalid-response' );
	}

	public function testSubmitJobThrowsInvalidResponseWithoutJobId() {
		$this->installMockHttp( $this->makeFakeHttpRequest( json_encode( [ 'status' => 'ok' ] ), 200 ) );

		$this->assertSubmitJobFails( 'elnsmwadapterui-error-invalid-response' );
	}

	public function testSubmitJobThrowsInvalidResponseOnNonObjectJson() {
		$this->installMockHttp( $this->makeFakeHttpRequest( '5', 200 ) );

		$this->assertSubmitJobFails( 'elnsmwadapterui-error-invalid-response' );
	}

	/**
	 * @param string $expectedKey
	 * @param array<int, mixed> $expectedParams
	 */
	private function assertSubmitJobFails( string $expectedKey, array $expectedParams = [] ): void {
		try {
			$this->newClient()->submitJob( 'eLabFTW', '42', 'Alice' );
			$this->fail( 'Expected AdapterServiceException' );
		} catch ( AdapterServiceException $e ) {
			$this->assertSame( $expectedKey, $e->getMessageKey() );
			$this->assertSame( $expectedParams, $e->getMessageParams() );
		}
	}
}
