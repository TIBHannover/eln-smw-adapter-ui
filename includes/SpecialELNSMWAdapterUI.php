<?php

declare( strict_types=1 );

namespace ELNSMWAdapterUI;

use Html;
use MediaWiki\Logger\LoggerFactory;
use OutputPage;
use Psr\Log\LoggerInterface;
use SpecialPage;
use stdClass;
use WebRequest;

/**
 * Special page for ELN SMW Adapter UI
 *
 * Provides a user interface for importing protocols from eLabFTW experiments
 * into MediaWiki with Semantic MediaWiki integration.
 */
class SpecialELNSMWAdapterUI extends SpecialPage {

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

	/** @var array<int, array{type: string, message: string}> */
	private $messages = [];

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

		// Add CSS for styling
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
			$this->displaySelectionForm( $out, $request );
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

	private function displaySelectionForm( OutputPage $out, WebRequest $request ): void {
		// Check if form was submitted - HTMLForm prefixes with 'wp'
		$elnType = $request->getVal( 'wpeln-type', '' );
		if ( empty( $elnType ) ) {
			$elnType = $request->getVal( 'eln-type', '' );
		}

		if ( !empty( $elnType ) ) {
			// Redirect to method-specific form
			$out->redirect( $this->getPageTitle()->getLocalURL( [ 'method' => $elnType ] ) );
			return;
		}

		$status = $this->adapterServiceClient->getStatus();

		// Get plugins dynamically from service status
		$hasUrlPlugins = false;
		$uploadPlugins = [];

		if ( $status && isset( $status['plugins'] ) ) {
			foreach ( $status['plugins'] as $pluginName => $pluginInfo ) {
				if ( $pluginInfo['type'] === 'url' ) {
					$hasUrlPlugins = true;
				} elseif ( $pluginInfo['type'] === 'upload' ) {
					$uploadPlugins[] = $pluginName;
				}
			}
		}

		$options = [];
		// Add URL option if there are URL-based plugins (preselected)
		if ( $hasUrlPlugins ) {
			$options[] = [ 'value' => 'url', 'text' => 'URL (default)', 'selected' => true ];
		}
		// Add upload options for each upload plugin
		foreach ( $uploadPlugins as $pluginName ) {
			$options[] = [ 'value' => $pluginName, 'text' => 'Upload file (' . $pluginName . ')', 'selected' => false ];
		}

		$hasEnabledPlugins = $status && isset( $status['enabled_plugins'] ) && is_array( $status['enabled_plugins'] );

		$out->addHTML( $this->renderTemplate( 'selection-form', [
			'connected' => (bool)$status,
			'version' => (string)( $status['version'] ?? 'unknown' ),
			'hasSmwConnection' => $status && isset( $status['smw_connection'] ),
			'smwConnection' => (string)( $status['smw_connection'] ?? '' ),
			'hasEnabledPlugins' => $hasEnabledPlugins,
			'enabledPlugins' => $hasEnabledPlugins ? implode( ', ', $status['enabled_plugins'] ) : '',
			'legend' => $this->msg( 'elnsmwadapterui-form-select-legend' )->text(),
			'help' => $this->msg( 'elnsmwadapterui-form-eln-type-help' )->text(),
			'action' => $this->getPageTitle()->getLocalURL(),
			'label' => $this->msg( 'elnsmwadapterui-form-eln-type-label' )->text(),
			'options' => $options,
			'submitLabel' => $this->msg( 'elnsmwadapterui-form-continue' )->text(),
		] ) );

		// Display any messages
		$this->displayMessages( $out );
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
		$out->addHTML( $this->errorBox( 'Unknown method: ' . $method ) );
	}

	/**
	 * Display URL form (original form)
	 */
	private function displayUrlForm( OutputPage $out, string $elnUrl = '' ): void {
		$request = $this->getRequest();

		// Handle form submission
		if ( $request->wasPosted() ) {
			if ( !$this->getUser()->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
				$out->addHTML( $this->errorBox( 'Invalid form submission. Please try again.' ) );
			} else {
				$urlValue = $request->getVal( 'eln-url', '' );
				$result = $this->processForm( [ 'eln-url' => $urlValue ] );
				if ( $result !== true && is_string( $result ) ) {
					$out->addHTML( $this->errorBox( $result ) );
				} elseif ( $result === true ) {
					// Redirect happened in processForm
					return;
				}
				// Keep the entered value
				$elnUrl = $urlValue;
			}
		}

		$out->addHTML( $this->renderTemplate( 'url-form', [
			'legend' => $this->msg( 'elnsmwadapterui-form-legend' )->text(),
			'help' => $this->msg( 'elnsmwadapterui-form-url-help' )->text(),
			'action' => $this->getPageTitle()->getLocalURL( [ 'method' => 'url' ] ),
			'token' => $this->getUser()->getEditToken(),
			'label' => $this->msg( 'elnsmwadapterui-form-url-label' )->text(),
			'placeholder' => 'https://elab.tu-clausthal.de/experiments.php?mode=view&id=0000',
			'elnUrl' => $elnUrl,
			'submitLabel' => $this->msg( 'elnsmwadapterui-form-submit' )->text(),
		] ) );

		// Display any messages
		$this->displayMessages( $out );

		// Add back button
		$this->addBackToSelectionButton( $out );
	}

	/**
	 * Display file upload form
	 */
	private function displayFileUploadForm( OutputPage $out, string $method ): void {
		$request = $this->getRequest();

		// Handle form submission first
		if ( $request->wasPosted() && $request->getVal( 'wpmethod' ) === $method ) {
			if ( !$this->getUser()->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
				$out->addHTML( $this->errorBox( 'Invalid form submission. Please try again.' ) );
				return;
			}

			$result = $this->processFileUploadForm( [ 'method' => $method ] );
			if ( $result !== true && is_string( $result ) ) {
				$out->addHTML( $this->errorBox( $result ) );
			} elseif ( $result === true ) {
				// Redirect happened in processFileUploadForm
				return;
			}
		}

		// Fetch plugin info to get dynamic fields
		$pluginInfo = $this->adapterServiceClient->getPluginInfo( $method );

		$token = $this->getUser()->getEditToken();

		$fields = [];
		if ( $pluginInfo && isset( $pluginInfo['fields'] ) && is_array( $pluginInfo['fields'] ) ) {
			foreach ( $pluginInfo['fields'] as $field ) {
				$fields[] = $this->getDynamicFieldData( $field );
			}
		}

		$out->addHTML( $this->renderTemplate( 'upload-form', [
			'legend' => $this->msg( 'elnsmwadapterui-form-upload-legend' )->text(),
			'method' => $method,
			'help' => $this->msg( 'elnsmwadapterui-form-file-help' )->text(),
			'action' => $this->getPageTitle()->getLocalURL( [ 'method' => $method ] ),
			'token' => $token,
			'label' => $this->msg( 'elnsmwadapterui-form-file-label' )->text(),
			'dropText' => $this->msg( 'elnsmwadapterui-file-drop-text' )->text(),
			'fields' => $fields,
			'submitLabel' => $this->msg( 'elnsmwadapterui-form-upload' )->text(),
		] ) );

		// Add JavaScript for file name display and drag-and-drop
		$out->addModules( 'ext.elnsmwadapterui.fileupload' );

		// Display any messages
		$this->displayMessages( $out );

		// Add back button
		$this->addBackToSelectionButton( $out );
	}

	/**
	 * Add back to selection button
	 */
	private function addBackToSelectionButton( OutputPage $out ): void {
		$out->addHTML( Html::rawElement(
			'div',
			[ 'class' => 'elnsmwadapterui-back-selection' ],
			Html::element( 'a', [
				'href' => $this->getPageTitle()->getLocalURL(),
				'class' => 'mw-ui-button'
			], $this->msg( 'elnsmwadapterui-back-to-selection' )->text() )
		) );
	}

	/**
	 * Build the template data for a dynamic form field based on field metadata
	 * @param array{name: string, label: string, type: string, required?: bool, options?: string[]} $field
	 * @return array<string, mixed>
	 */
	private function getDynamicFieldData( array $field ): array {
		return [
			'label' => $field['label'],
			'name' => 'field_' . $field['name'],
			'required' => !empty( $field['required'] ),
			'isSelect' => $field['type'] === 'select',
			'options' => array_values( (array)( $field['options'] ?? [] ) ),
		];
	}

	/**
	 * @param string $text Plain text, will be escaped
	 */
	private function errorBox( string $text ): string {
		return Html::element( 'div', [ 'class' => 'errorbox' ], $text );
	}

	/**
	 * @param string $name Template file name without extension
	 * @param array<string, mixed> $data
	 */
	private function renderTemplate( string $name, array $data ): string {
		// TemplateParser moved into a namespace after MW 1.39 (the minimum required version).
		$class = class_exists( 'MediaWiki\\Html\\TemplateParser' )
			? 'MediaWiki\\Html\\TemplateParser'
			: 'TemplateParser';
		// Instantiated via reflection: only one of the two classes exists per MW version, so a
		// direct `new` would be reported by static analysis on the other one
		$parser = ( new \ReflectionClass( $class ) )->newInstance( __DIR__ . '/../templates' );
		return $parser->processTemplate( $name, $data );
	}

	/**
	 * Process URL form submission callback
	 * @param array $data Expected key: 'eln-url' (string)
	 */
	public function processForm( array $data ): bool|string {
		$elnUrl = isset( $data['eln-url'] ) ? (string)$data['eln-url'] : '';

		if ( !$this->isValidUrl( $elnUrl ) ) {
			return 'Please provide a valid URL.';
		}

		$jobId = $this->adaptProtocols( $elnUrl );
		if ( $jobId !== null ) {
			$this->redirectToProcessing( $jobId );
			return true;
		}

		return 'Failed to process the request.';
	}

	/**
	 * Process file upload form submission
	 * @param array $data Expected key: 'method' (string)
	 */
	public function processFileUploadForm( array $data ): bool|string {
		$method = isset( $data['method'] ) ? (string)$data['method'] : '';

		// Handle file upload
		$request = $this->getRequest();
		$uploadedFile = $request->getUpload( 'upload-file' );

		if ( !$uploadedFile || !$uploadedFile->exists() ) {
			return 'Please select a file to upload.';
		}

		// Get file info
		$fileName = $uploadedFile->getName();
		$fileSize = $uploadedFile->getSize();

		// Create upload directory if it doesn't exist
		$uploadDir = $this->getUploadDirectory();
		if ( !$uploadDir ) {
			return 'Upload directory is not configured.';
		}

		// Use original filename and delete existing file if it exists
		$uploadPath = $uploadDir . '/' . $fileName;

		// Delete existing file if it exists
		if ( file_exists( $uploadPath ) ) {
			if ( !unlink( $uploadPath ) ) {
				return 'Failed to remove existing file with the same name.';
			}
			$this->logger->info( 'Existing file deleted before upload', [
				'file_path' => $uploadPath
			] );
		}

		// Move uploaded file from temp location
		$tempPath = $uploadedFile->getTempName();
		if ( !$tempPath || !move_uploaded_file( $tempPath, $uploadPath ) ) {
			return 'Failed to save uploaded file.';
		}

		$this->logger->info( 'File uploaded successfully', [
			'original_name' => $fileName,
			'upload_path' => $uploadPath,
			'method' => $method,
			'size' => $fileSize
		] );

		// Collect dynamic field values
		$dynamicFields = [];
		foreach ( $request->getValues() as $key => $value ) {
			if ( strpos( $key, 'field_' ) === 0 ) {
				// Remove 'field_' prefix
				$fieldName = substr( $key, 6 );
				$dynamicFields[$fieldName] = $value;
			}
		}

		// Process with adapter service using file path as ID
		$jobId = $this->adaptProtocols( $uploadPath, $method, $dynamicFields );
		if ( $jobId !== null ) {
			$this->redirectToProcessing( $jobId );
			return true;
		}

		return 'Failed to process the uploaded file.';
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
			$out->addHTML( $this->errorBox( 'No results data found.' ) );
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
			$out->addHTML( $this->errorBox( 'No job ID provided.' ) );
			return;
		}

		$out->setPageTitle( 'Processing Import' );

		// Use public job status URL that browser can access (via nginx proxy)
		// Construct from $wgServer (https://service.tib.eu) + /sfb1368/eln-smw-adapter/job/
		$server = (string)$this->config->get( 'Server' );
		$jobStatusUrl = rtrim( $server, '/' ) . '/sfb1368/eln-smw-adapter/job/' . urlencode( $jobId );
		$resultsUrl = $this->getPageTitle()->getLocalURL( [ 'action' => 'results', 'job_id' => '__JOBID__' ] );

		$out->addHTML( $this->renderTemplate( 'processing', [] ) );

		$out->addJsConfigVars( [
			'elnsmwadapteruiJobStatusUrl' => $jobStatusUrl,
			'elnsmwadapteruiResultsUrl' => $resultsUrl,
		] );
		$out->addModules( 'ext.elnsmwadapterui.processing' );
	}

	/**
	 * Display results after processing
	 */
	private function displayResults( OutputPage $out, ?stdClass $result ): void {
		if ( !$result ) {
			$out->addHTML( $this->errorBox( $this->msg( 'elnsmwadapterui-error-service-offline' )->text() ) );
			return;
		}

		// Page header
		$out->setPageTitle( $this->msg( 'elnsmwadapterui-results-title' ) );

		$wikiUrl = (string)$this->config->get( 'ELNSMWAdapterUIWikiURL' );
		$protocols = [];
		$hasPages = isset( $result->smw_pages ) && !empty( $result->smw_pages );
		if ( $hasPages ) {
			foreach ( $result->smw_pages as $pageId => $pageData ) {
				if ( strpos( (string)$pageId, 'P' ) === 0 ) {
					$protocols[] = [
						'url' => $wikiUrl . '/' . urlencode( (string)$pageId ),
						'pageId' => (string)$pageId,
					];
				}
			}
		}

		$logMessages = [];
		foreach ( $result->messages ?? [] as $message ) {
			$logMessages[] = [
				'cssClass' => $this->getMessageCssClass( $message->type ),
				'type' => strtoupper( $message->type ),
				'text' => $message->text,
			];
		}

		$out->addHTML( $this->renderTemplate( 'results', [
			'protocolsTitle' => $this->msg( 'elnsmwadapterui-results-protocols' )->text(),
			'hasPages' => $hasPages,
			'hasProtocols' => $protocols !== [],
			'protocols' => $protocols,
			'summary' => $this->msg( 'elnsmwadapterui-protocols-count', count( $protocols ) )->text(),
			'noProtocolsText' => $this->msg( 'elnsmwadapterui-warning-no-protocols' )->text(),
			'logTitle' => $this->msg( 'elnsmwadapterui-results-log' )->text(),
			'hasLogMessages' => $logMessages !== [],
			'logMessages' => $logMessages,
			'backUrl' => $this->getPageTitle()->getLocalURL(),
			'backText' => $this->msg( 'elnsmwadapterui-back-button' )->text(),
		] ) );
	}

	private function getMessageCssClass( string $type ): string {
		return match ( $type ) {
			'error' => 'error',
			'warning' => 'warning',
			default => 'notice',
		};
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
	 * @return string|null ID of the started job, or null on failure
	 */
	private function adaptProtocols(
		string $elnUrlOrPath, string $method = 'url', array $dynamicFields = []
	): ?string {
		if ( $method === 'url' ) {
			// Handle URL-based processing (original logic)
			$parsedUrl = parse_url( $elnUrlOrPath );

			if ( !isset( $parsedUrl['host'] ) ) {
				$this->addMessage( 'error', 'elnsmwadapterui-error-invalid-url' );
				return null;
			}

			if ( !$this->isAllowedELabFTWHost( $parsedUrl['host'] ) ) {
				$this->addMessage( 'error', 'elnsmwadapterui-error-unsupported-eln', [ $parsedUrl['host'] ] );
				return null;
			}

			return $this->processELabFTWUrl( $parsedUrl, $dynamicFields );
		} else {
			// Handle file-based processing
			return $this->processUploadedFile( $elnUrlOrPath, $method, $dynamicFields );
		}
	}

	/**
	 * Process uploaded file
	 */
	private function processUploadedFile(
		string $filePath, string $method, array $dynamicFields = []
	): ?string {
		if ( !file_exists( $filePath ) ) {
			$this->addMessage( 'error', 'elnsmwadapterui-error-file-not-found' );
			return null;
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
	private function processELabFTWUrl( array $parsedUrl, array $dynamicFields = [] ): ?string {
		if ( !isset( $parsedUrl['query'] ) ) {
			$this->addMessage( 'error', 'elnsmwadapterui-error-missing-query' );
			return null;
		}

		parse_str( $parsedUrl['query'], $query );

		if ( !isset( $query['id'] ) ) {
			$this->addMessage( 'error', 'elnsmwadapterui-error-missing-id' );
			return null;
		}

		return $this->callAdapterService( 'eLabFTW', (string)$query['id'], $dynamicFields );
	}

	/**
	 * Call the adapter service, recording a user-facing message on failure
	 * @param string $eln
	 * @param string $id
	 * @param array $additionalData Additional data including user, dynamic fields, etc.
	 * @return string|null ID of the started job, or null on failure
	 */
	private function callAdapterService( string $eln, string $id, array $additionalData = [] ): ?string {
		// Get current user - prefer real name, fallback to username
		$currentUser = $this->getUser();
		$userName = $currentUser->getRealName();
		if ( empty( $userName ) ) {
			$userName = $currentUser->getName();
		}

		try {
			return $this->adapterServiceClient->submitJob( $eln, $id, $userName, $additionalData );
		} catch ( AdapterServiceException $e ) {
			$this->addMessage( 'error', $e->getMessageKey(), $e->getMessageParams() );
			return null;
		}
	}

	private function redirectToProcessing( string $jobId ): void {
		$this->getOutput()->redirect(
			$this->getPageTitle()->getLocalURL( [ 'action' => 'processing', 'job_id' => $jobId ] )
		);
	}

	/**
	 * Add a message to be displayed to the user
	 */
	private function addMessage( string $type, string $messageKey, array $params = [] ): void {
		$this->messages[] = [
			'type' => $type,
			'message' => $this->msg( $messageKey, $params )->text()
		];
	}

	/**
	 * Display accumulated messages
	 */
	private function displayMessages( OutputPage $out ): void {
		foreach ( $this->messages as $message ) {
			$cssClass = $this->getMessageCssClass( $message['type'] );
			$out->addHTML( Html::element( 'div', [
				'class' => "mw-message-box mw-message-box-{$cssClass}"
			], $message['message'] ) );
		}
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
