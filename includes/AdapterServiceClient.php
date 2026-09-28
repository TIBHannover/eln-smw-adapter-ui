<?php

declare( strict_types=1 );

namespace ELNSMWAdapterUI;

use MediaWiki\Http\HttpRequestFactory;
use Psr\Log\LoggerInterface;
use stdClass;

/**
 * HTTP client for the ELN SMW adapter service.
 */
class AdapterServiceClient {

	public function __construct(
		private HttpRequestFactory $httpRequestFactory,
		private LoggerInterface $logger,
		private string $serviceUrl
	) {
	}

	/**
	 * Check the status of the adapter service
	 * @return array<string, mixed>|null Status information or null if unreachable, or malformed
	 */
	public function getStatus(): ?array {
		$status = $this->httpGetJson( $this->buildUrl( '/status' ) );
		return is_array( $status ) ? $status : null;
	}

	/**
	 * Get plugin info including dynamic form fields
	 * @return array<string, mixed>|null Plugin info or null if unreachable, or malformed
	 */
	public function getPluginInfo( string $pluginName ): ?array {
		$pluginInfo = $this->httpGetJson( $this->buildUrl( '/plugin-info/' . urlencode( $pluginName ) ) );
		return is_array( $pluginInfo ) ? $pluginInfo : null;
	}

	/**
	 * Fetch the result of a finished import job
	 * @return stdClass|null The job result, or null if unavailable or malformed
	 */
	public function getJobResult( string $jobId ): ?stdClass {
		$jobData = $this->httpGetJson( $this->buildUrl( '/job/' . urlencode( $jobId ) ), false, 10 );
		if ( $jobData instanceof stdClass && ( $jobData->result ?? null ) instanceof stdClass ) {
			return $jobData->result;
		}
		return null;
	}

	/**
	 * Start an asynchronous import job on the adapter service
	 * @param string $eln Name of the ELN / plugin
	 * @param string $id Experiment ID or uploaded file name
	 * @param string $userName Name of the importing user
	 * @param array<string, mixed> $additionalData Additional data such as dynamic field values
	 * @return string ID of the created job
	 * @throws AdapterServiceException
	 */
	public function submitJob( string $eln, string $id, string $userName, array $additionalData = [] ): string {
		$url = $this->buildUrl( '/adapt-async' );

		$payload = [
			'eln' => $eln,
			'id' => $id,
			'data' => array_merge(
				[ 'user' => $userName ],
				$additionalData
			)
		];

		$this->logger->info( 'Calling adapter service', [
			'url' => $url,
			'eln' => $eln,
			'id' => $id
		] );

		$request = $this->httpRequestFactory->create( $url, [
			'method' => 'POST',
			'postData' => json_encode( $payload ),
			'timeout' => 120,
			'connectTimeout' => 10,
			'sslVerifyHost' => true,
			'sslVerifyCert' => true,
			'followRedirects' => false,
			'userAgent' => 'MediaWiki-ELNSMWAdapterUI/0.2.0',
		], __METHOD__ );
		$request->setHeader( 'Content-Type', 'application/json' );

		$status = $request->execute();
		$response = $request->getContent();

		if ( !$status->isOK() ) {
			$this->logger->error( 'HTTP error when calling adapter service', [
				'error' => (string)$status,
				'url' => $url
			] );
			throw new AdapterServiceException( 'elnsmwadapterui-error-service-offline' );
		}

		$httpCode = $request->getStatus();
		if ( $httpCode !== 200 ) {
			$this->logger->warning( 'HTTP error from adapter service', [
				'http_code' => $httpCode,
				'response' => $response
			] );
			throw new AdapterServiceException( 'elnsmwadapterui-error-service-error', [ $httpCode ] );
		}

		$jobResponse = json_decode( $response );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->logger->error( 'Invalid JSON response from adapter service', [
				'response' => $response,
				'json_error' => json_last_error_msg()
			] );
			throw new AdapterServiceException( 'elnsmwadapterui-error-invalid-response' );
		}

		$this->logger->info( 'Got job response', [ 'job_response' => $response ] );

		if ( !( $jobResponse instanceof stdClass ) || !is_scalar( $jobResponse->job_id ?? null ) ) {
			$this->logger->error( 'No job_id in response', [ 'response' => $response ] );
			throw new AdapterServiceException( 'elnsmwadapterui-error-invalid-response' );
		}

		$jobId = (string)$jobResponse->job_id;
		$this->logger->info( 'Returning job_id to client', [ 'job_id' => $jobId ] );

		return $jobId;
	}

	private function buildUrl( string $path ): string {
		return rtrim( $this->serviceUrl, '/' ) . $path;
	}

	/**
	 * Perform a GET request against the adapter service and decode the JSON response.
	 * No native return type hint: json_decode() can return a scalar if the adapter
	 * service ever responds with non-object/array JSON, which the callers of this
	 * method are not designed to receive; they check the shape before use.
	 * @param string $url
	 * @param bool $associative Decode JSON objects as associative arrays instead of stdClass
	 * @return mixed Decoded response, or null on failure
	 */
	private function httpGetJson( string $url, bool $associative = true, int $timeout = 5, int $connectTimeout = 3 ) {
		$request = $this->httpRequestFactory->create( $url, [
			'timeout' => $timeout,
			'connectTimeout' => $connectTimeout,
			'sslVerifyHost' => true,
			'sslVerifyCert' => true,
			'followRedirects' => false,
		], __METHOD__ );
		$request->setHeader( 'Content-Type', 'application/json' );

		$status = $request->execute();
		if ( !$status->isOK() ) {
			$this->logger->error( 'HTTP error when calling adapter service', [
				'error' => (string)$status,
				'url' => $url
			] );
			return null;
		}

		$httpCode = $request->getStatus();
		if ( $httpCode !== 200 ) {
			$this->logger->warning( 'HTTP error from adapter service', [
				'http_code' => $httpCode,
				'url' => $url
			] );
			return null;
		}

		return json_decode( $request->getContent(), $associative );
	}
}
