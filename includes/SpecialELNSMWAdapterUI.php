<?php

declare( strict_types=1 );

namespace ELNSMWAdapterUI;

use Html;
use HTMLForm;
use MediaWiki\Logger\LoggerFactory;
use OutputPage;
use Psr\Log\LoggerInterface;
use SpecialPage;
use Status;
use stdClass;
use WebRequest;

/**
 * Special page for ELN SMW Adapter UI
 *
 * Provides a user interface for importing protocols from eLabFTW experiments
 * into MediaWiki with Semantic MediaWiki integration.
 */
class SpecialELNSMWAdapterUI extends SpecialPage {

	private const DYNAMIC_FIELD_PREFIX = 'field_';

	/**
	 * No native type hint: on MW 1.39 (the minimum version required by extension.json)
	 * Config is in the global namespace; MediaWiki\Config\Config (used on MW 1.43) is a
	 * different class from PHP's point of view.
	 * @var \Config|\MediaWiki\Config\Config
	 */
	private $config;

	/** @var LoggerInterface */
	private $logger;

	/** @var AdapterServiceClient */
	private $adapterServiceClient;

	/** @var AdapterPageRenderer|null */
	private $renderer;

	/**
	 * No native type hint on $configFactory: on MW 1.39 (the minimum version required by
	 * extension.json) ConfigFactory is still in the global namespace; MediaWiki\Config\ConfigFactory
	 * (used on MW 1.43) rejects a global-namespace instance and vice versa, so a native type
	 * hint here would break one of the two supported MW versions.
	 * @param \MediaWiki\Config\ConfigFactory|\ConfigFactory $configFactory
	 * @param AdapterServiceClient $adapterServiceClient
	 */
	public function __construct( $configFactory, AdapterServiceClient $adapterServiceClient ) {
		parent::__construct( 'ELNSMWAdapterUI', 'elnsmwadapterui-use' );
		$this->config = $configFactory->makeConfig( 'main' );
		$this->adapterServiceClient = $adapterServiceClient;
		$this->logger = LoggerFactory::getInstance( 'ELNSMWAdapterUI' );
	}

	/**
	 * Initialize the special page with required services
	 * @param string|null $par
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$this->checkPermissions();

		$out = $this->getOutput();
		$request = $this->getRequest();

		$out->enableOOUI();
		$out->addModuleStyles( 'ext.elnsmwadapterui.styles' );

		// Check if we should display results or form
		$action = $request->getVal( 'action', '' );
		$method = $request->getVal( 'method', '' );

		if ( $action === 'results' ) {
			$this->displayResultsPage( $out, $request );
		} elseif ( $action === 'processing' ) {
			$this->displayProcessingPage( $out, $request );
		} elseif ( !empty( $method ) ) {
			// Skip dropdown if method is specified
			$this->displayMethodForm( $out, $method );
		} else {
			$this->displaySelectionForm( $out );
		}
	}

	/**
	 * Validate the provided URL
	 */
	private function isValidUrl( string $url ): bool {
		if ( empty( $url ) ) {
			$this->logger->debug( 'URL validation failed: empty URL' );
			return false;
		}

		$parsedUrl = parse_url( $url );
		$this->logger->debug( 'URL validation', [
			'url' => $url,
			'parsed' => $parsedUrl
		] );

		$isValid = isset( $parsedUrl['scheme'] ) && isset( $parsedUrl['host'] );
		$this->logger->debug( 'URL validation result', [ 'is_valid' => $isValid ] );

		return $isValid;
	}

	/**
	 * @param array<string, array<string, mixed>> $descriptor
	 */
	private function newForm( array $descriptor, string $legendMessageKey, string $helpMessageKey ): HTMLForm {
		$form = HTMLForm::factory( 'ooui', $descriptor, $this->getContext() );
		// The wrapper legend makes HTMLForm draw the standard framed fieldset around the fields
		$form->setWrapperLegendMsg( $legendMessageKey );
		$form->addHeaderHtml( $this->msg( $helpMessageKey )->parseAsBlock() );
		return $form;
	}

	private function displaySelectionForm( OutputPage $out ): void {
		$status = $this->adapterServiceClient->getStatus();
		$out->addHTML( $this->getRenderer()->statusBox( $status ) );

		$options = $this->getMethodOptions( $status );
		if ( $options === [] ) {
			return;
		}

		$this->newForm( [
			'eln-type' => [
				'type' => 'select',
				'label-message' => 'elnsmwadapterui-form-eln-type-label',
				'options' => $options,
				'default' => reset( $options ),
			],
		], 'elnsmwadapterui-form-select-legend', 'elnsmwadapterui-form-eln-type-help' )
			->setMethod( 'get' )
			->setFormIdentifier( 'selection' )
			->setSubmitTextMsg( 'elnsmwadapterui-form-continue' )
			->setSubmitCallback( function ( array $data ) {
				$this->getOutput()->redirect(
					$this->getPageTitle()->getLocalURL( [ 'method' => $data['eln-type'] ] )
				);
				return true;
			} )
			->show();
	}

	/**
	 * Import methods offered by the service, as label => value pairs for a select field
	 * @param array<string, mixed>|null $status Service status, or null if the service is unreachable
	 * @return array<string, string>
	 */
	private function getMethodOptions( ?array $status ): array {
		$hasUrlPlugins = false;
		$uploadPlugins = [];

		foreach ( $status['plugins'] ?? [] as $pluginName => $pluginInfo ) {
			if ( $pluginInfo['type'] === 'url' ) {
				$hasUrlPlugins = true;
			} elseif ( $pluginInfo['type'] === 'upload' ) {
				$uploadPlugins[] = (string)$pluginName;
			}
		}

		$options = [];
		if ( $hasUrlPlugins ) {
			$options[$this->msg( 'elnsmwadapterui-option-url-import' )->text()] = 'url';
		}
		foreach ( $uploadPlugins as $pluginName ) {
			$options[$this->msg( 'elnsmwadapterui-option-upload', $pluginName )->text()] = $pluginName;
		}

		return $options;
	}

	private function displayMethodForm( OutputPage $out, string $method ): void {
		if ( $method === 'url' ) {
			$this->displayUrlForm( $out );
			return;
		}

		// Check if method is an upload-type plugin
		$status = $this->adapterServiceClient->getStatus();
		if ( $status && isset( $status['plugins'][$method] ) ) {
			$pluginInfo = $status['plugins'][$method];
			if ( $pluginInfo['type'] === 'upload' ) {
				$this->displayFileUploadForm( $out, $method );
				return;
			}
		}

		// Fallback for unknown methods
		$out->addHTML( $this->getRenderer()->errorBox(
			$this->msg( 'elnsmwadapterui-error-unknown-method', $method )->text()
		) );
	}

	private function displayUrlForm( OutputPage $out ): void {
		$this->newForm( [
			'eln-url' => [
				'type' => 'url',
				'label-message' => 'elnsmwadapterui-form-url-label',
				'placeholder-message' => 'elnsmwadapterui-form-url-placeholder',
				'required' => true,
			],
		], 'elnsmwadapterui-form-legend', 'elnsmwadapterui-form-url-help' )
			->setAction( $this->getPageTitle()->getLocalURL( [ 'method' => 'url' ] ) )
			->setSubmitTextMsg( 'elnsmwadapterui-form-submit' )
			->setSubmitCallback( [ $this, 'processForm' ] )
			->show();

		$this->addBackToSelectionButton( $out );
	}

	private function displayFileUploadForm( OutputPage $out, string $method ): void {
		$descriptor = [
			'upload-file' => [
				'type' => 'file',
				'label-message' => 'elnsmwadapterui-form-file-label',
				'required' => true,
			],
		];

		// Additional fields as reported by the adapter service for this plugin
		$pluginInfo = $this->adapterServiceClient->getPluginInfo( $method );
		foreach ( $pluginInfo['fields'] ?? [] as $field ) {
			if ( is_array( $field ) && isset( $field['name'] ) ) {
				$descriptor[self::DYNAMIC_FIELD_PREFIX . $field['name']] = $this->getDynamicFieldDescriptor( $field );
			}
		}

		$this->newForm( $descriptor, 'elnsmwadapterui-form-upload-legend', 'elnsmwadapterui-form-file-help' )
			->setAction( $this->getPageTitle()->getLocalURL( [ 'method' => $method ] ) )
			->setSubmitTextMsg( 'elnsmwadapterui-form-upload' )
			->setSubmitCallback( fn ( array $data ) => $this->processFileUploadForm( $data + [ 'method' => $method ] ) )
			->show();

		$this->addBackToSelectionButton( $out );
	}

	/**
	 * HTMLForm field descriptor for a field reported by the adapter service
	 * @param array{name: string, label?: string, type?: string, required?: bool, options?: string[]} $field
	 * @return array<string, mixed>
	 */
	private function getDynamicFieldDescriptor( array $field ): array {
		$descriptor = [
			'label' => (string)( $field['label'] ?? $field['name'] ),
			'required' => !empty( $field['required'] ),
		];

		if ( ( $field['type'] ?? '' ) === 'select' ) {
			$options = array_map( 'strval', array_values( (array)( $field['options'] ?? [] ) ) );
			$descriptor['type'] = 'select';
			$descriptor['options'] = [
				$this->msg( 'elnsmwadapterui-form-select-placeholder' )->text() => '',
			] + array_combine( $options, $options );
			$descriptor['default'] = '';
		} else {
			$descriptor['type'] = 'text';
		}

		return $descriptor;
	}

	private function addBackToSelectionButton( OutputPage $out ): void {
		$out->addHTML( Html::rawElement( 'p', [], $this->getRenderer()->backToSelectionButton(
			$this->getPageTitle()->getLocalURL()
		) ) );
	}

	/**
	 * The renderer depends on this request's context (for localized messages), so it is
	 * created per special page instance instead of being injected as a shared service.
	 */
	private function getRenderer(): AdapterPageRenderer {
		$this->renderer ??= new AdapterPageRenderer( $this );
		return $this->renderer;
	}

	/**
	 * Submit callback of the URL form
	 * @param array $data Expected key: 'eln-url' (string)
	 * @return bool|Status True after redirecting to the processing page, else a fatal Status
	 */
	public function processForm( array $data ): bool|Status {
		$elnUrl = isset( $data['eln-url'] ) ? (string)$data['eln-url'] : '';

		if ( !$this->isValidUrl( $elnUrl ) ) {
			return Status::newFatal( 'elnsmwadapterui-error-invalid-url' );
		}

		return $this->redirectIfStarted( $this->adaptProtocols( $elnUrl ) );
	}

	/**
	 * Submit callback of the file upload form
	 * @param array $data Expected keys: 'method' (string), 'field_*' (plugin specific fields)
	 * @return bool|Status True after redirecting to the processing page, else a fatal Status
	 */
	public function processFileUploadForm( array $data ): bool|Status {
		$method = isset( $data['method'] ) ? (string)$data['method'] : '';

		// Handle file upload; the HTMLForm file field is named 'wpupload-file'
		$uploadedFile = $this->getRequest()->getUpload( 'wpupload-file' );

		if ( !$uploadedFile->exists() ) {
			return Status::newFatal( 'elnsmwadapterui-error-no-file' );
		}

		// Get file info
		$fileName = $uploadedFile->getName();
		$fileSize = $uploadedFile->getSize();

		// Create upload directory if it doesn't exist
		$uploadDir = $this->getUploadDirectory();
		if ( !$uploadDir ) {
			return Status::newFatal( 'elnsmwadapterui-error-upload-dir' );
		}

		// Use original filename and delete existing file if it exists
		$uploadPath = $uploadDir . '/' . $fileName;

		// Delete existing file if it exists
		if ( file_exists( $uploadPath ) ) {
			if ( !unlink( $uploadPath ) ) {
				return Status::newFatal( 'elnsmwadapterui-error-remove-existing' );
			}
			$this->logger->info( 'Existing file deleted before upload', [
				'file_path' => $uploadPath
			] );
		}

		// Move uploaded file from temp location
		$tempPath = $uploadedFile->getTempName();
		if ( !$tempPath || !move_uploaded_file( $tempPath, $uploadPath ) ) {
			return Status::newFatal( 'elnsmwadapterui-error-save-upload' );
		}

		$this->logger->info( 'File uploaded successfully', [
			'original_name' => $fileName,
			'upload_path' => $uploadPath,
			'method' => $method,
			'size' => $fileSize
		] );

		// Collect dynamic field values
		$dynamicFields = [];
		foreach ( $data as $key => $value ) {
			if ( str_starts_with( $key, self::DYNAMIC_FIELD_PREFIX ) ) {
				$dynamicFields[substr( $key, strlen( self::DYNAMIC_FIELD_PREFIX ) )] = $value;
			}
		}

		// Process with adapter service using file path as ID
		return $this->redirectIfStarted( $this->adaptProtocols( $uploadPath, $method, $dynamicFields ) );
	}

	/**
	 * Get upload directory path
	 */
	private function getUploadDirectory(): string|false {
		// Get upload path from service status
		$status = $this->adapterServiceClient->getStatus();
		if ( $status && isset( $status['upload_path'] ) ) {
			return (string)$status['upload_path'];
		}

		$this->logger->error( 'Upload path not available from service' );
		return false;
	}

	/**
	 * Display results page
	 */
	private function displayResultsPage( OutputPage $out, WebRequest $request ): void {
		// Check if we have job_id (new method) or data (old method for backwards compat)
		$jobId = $request->getVal( 'job_id', '' );
		$data = $request->getVal( 'data', '' );

		$result = null;

		if ( !empty( $jobId ) ) {
			// Fetch result from backend via job_id
			$result = $this->adapterServiceClient->getJobResult( $jobId );
		} elseif ( !empty( $data ) ) {
			// Old method: decode from URL parameter
			$result = json_decode( base64_decode( $data ) );
		}

		if ( !$result ) {
			$out->addHTML( $this->getRenderer()->errorBox( $this->msg( 'elnsmwadapterui-error-no-results' )->text() ) );
			return;
		}

		$this->displayResults( $out, $result );
	}

	/**
	 * Display processing page with JavaScript polling
	 */
	private function displayProcessingPage( OutputPage $out, WebRequest $request ): void {
		$jobId = $request->getVal( 'job_id', '' );
		if ( empty( $jobId ) ) {
			$out->addHTML( $this->getRenderer()->errorBox( $this->msg( 'elnsmwadapterui-error-no-job-id' )->text() ) );
			return;
		}

		$out->setPageTitle( $this->msg( 'elnsmwadapterui-processing-title' )->text() );

		// Use a public job status URL that the browser can access (e.g. via a reverse proxy)
		$server = rtrim( (string)$this->config->get( 'Server' ), '/' );
		$jobPath = '/' . trim( (string)$this->config->get( 'ELNSMWAdapterUIJobStatusPath' ), '/' ) . '/';
		$jobStatusUrl = $server . $jobPath . urlencode( $jobId );
		$resultsUrl = $this->getPageTitle()->getLocalURL( [ 'action' => 'results', 'job_id' => '__JOBID__' ] );

		$out->addHTML( $this->getRenderer()->processing() );

		$out->addJsConfigVars( [
			'elnsmwadapteruiJobStatusUrl' => $jobStatusUrl,
			'elnsmwadapteruiResultsUrl' => $resultsUrl,
		] );
		$out->addModules( 'ext.elnsmwadapterui.processing' );
	}

	/**
	 * Display results after processing
	 */
	private function displayResults( OutputPage $out, stdClass $result ): void {
		$out->setPageTitle( $this->msg( 'elnsmwadapterui-results-title' )->text() );

		$out->addHTML( $this->getRenderer()->results(
			$result,
			$this->getWikiUrl(),
			$this->getPageTitle()->getLocalURL()
		) );
	}

	/**
	 * Base URL for links to imported pages: $wgELNSMWAdapterUIWikiURL, or derived from
	 * $wgServer and $wgArticlePath when unset.
	 */
	private function getWikiUrl(): string {
		$configured = (string)$this->config->get( 'ELNSMWAdapterUIWikiURL' );
		if ( $configured !== '' ) {
			return rtrim( $configured, '/' );
		}

		return rtrim(
			(string)$this->config->get( 'Server' ) .
			str_replace( '$1', '', (string)$this->config->get( 'ArticlePath' ) ),
			'/'
		);
	}

	/**
	 * Check the host against $wgELNSMWAdapterUIAllowedELabFTWHosts (case-insensitive).
	 */
	private function isAllowedELabFTWHost( string $host ): bool {
		$allowedHosts = array_map(
			'strtolower',
			(array)$this->config->get( 'ELNSMWAdapterUIAllowedELabFTWHosts' )
		);

		return in_array( strtolower( $host ), $allowedHosts, true );
	}

	/**
	 * Process protocols from ELN URL or file path
	 * @return Status Good Status whose value is the ID of the started job, or a fatal Status
	 */
	private function adaptProtocols(
		string $elnUrlOrPath, string $method = 'url', array $dynamicFields = []
	): Status {
		if ( $method === 'url' ) {
			// Handle URL-based processing (original logic)
			$parsedUrl = parse_url( $elnUrlOrPath );

			if ( !isset( $parsedUrl['host'] ) ) {
				return Status::newFatal( 'elnsmwadapterui-error-invalid-url' );
			}

			if ( !$this->isAllowedELabFTWHost( $parsedUrl['host'] ) ) {
				return Status::newFatal( 'elnsmwadapterui-error-unsupported-eln', $parsedUrl['host'] );
			}

			return $this->processELabFTWUrl( $parsedUrl, $dynamicFields );
		}

		// Handle file-based processing
		return $this->processUploadedFile( $elnUrlOrPath, $method, $dynamicFields );
	}

	/**
	 * Process uploaded file
	 */
	private function processUploadedFile(
		string $filePath, string $method, array $dynamicFields = []
	): Status {
		if ( !file_exists( $filePath ) ) {
			return Status::newFatal( 'elnsmwadapterui-error-file-not-found' );
		}

		// Use method name directly as plugin name
		// For file uploads, pass just the filename, not the full path
		$fileName = basename( $filePath );
		return $this->callAdapterService( $method, $fileName, $dynamicFields );
	}

	/**
	 * Process eLabFTW URL and extract experiment ID
	 * @param array $parsedUrl
	 * @param array $dynamicFields
	 */
	private function processELabFTWUrl( array $parsedUrl, array $dynamicFields = [] ): Status {
		if ( !isset( $parsedUrl['query'] ) ) {
			return Status::newFatal( 'elnsmwadapterui-error-missing-query' );
		}

		parse_str( $parsedUrl['query'], $query );

		if ( !isset( $query['id'] ) ) {
			return Status::newFatal( 'elnsmwadapterui-error-missing-id' );
		}

		return $this->callAdapterService( 'eLabFTW', (string)$query['id'], $dynamicFields );
	}

	/**
	 * Call the adapter service
	 * @param string $eln
	 * @param string $id
	 * @param array $additionalData Additional data including user, dynamic fields, etc.
	 * @return Status Good Status whose value is the ID of the started job, or a fatal Status
	 */
	private function callAdapterService( string $eln, string $id, array $additionalData = [] ): Status {
		// Get current user - prefer real name, fallback to username
		$currentUser = $this->getUser();
		$userName = $currentUser->getRealName();
		if ( empty( $userName ) ) {
			$userName = $currentUser->getName();
		}

		try {
			return Status::newGood(
				$this->adapterServiceClient->submitJob( $eln, $id, $userName, $additionalData )
			);
		} catch ( AdapterServiceException $e ) {
			return Status::newFatal( $e->getMessageKey(), ...$e->getMessageParams() );
		}
	}

	/**
	 * Redirect to the processing page of the started job
	 * @param Status $jobStatus Result of adaptProtocols()
	 * @return bool|Status True after redirecting, else the fatal Status
	 */
	private function redirectIfStarted( Status $jobStatus ): bool|Status {
		if ( !$jobStatus->isOK() ) {
			return $jobStatus;
		}

		$this->getOutput()->redirect(
			$this->getPageTitle()->getLocalURL( [ 'action' => 'processing', 'job_id' => $jobStatus->getValue() ] )
		);
		return true;
	}

	/**
	 * @return string
	 */
	public function getGroupName() {
		return 'other';
	}

	/**
	 * @return \Message
	 */
	public function getDescription() {
		return $this->msg( 'elnsmwadapterui-special-page-title' );
	}
}
