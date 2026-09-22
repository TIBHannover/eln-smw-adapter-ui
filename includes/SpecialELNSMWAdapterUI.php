<?php

namespace ELNSMWAdapterUI;

use Html;
use MediaWiki\Config\Config;
use MediaWiki\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use SpecialPage;
use WebRequest;

/**
 * Special page for ELN SMW Adapter UI
 *
 * Provides a user interface for importing protocols from eLabFTW experiments
 * into MediaWiki with Semantic MediaWiki integration.
 */
class SpecialELNSMWAdapterUI extends SpecialPage {

	/** @var Config */
	private $config;

	/** @var LoggerInterface */
	private $logger;

	/** @var array */
	private $messages = [];

	public function __construct() {
		parent::__construct( 'ELNSMWAdapterUI', 'elnsmwadapterui-use' );
		$this->logger = LoggerFactory::getInstance( 'ELNSMWAdapterUI' );
	}

	/**
	 * Initialize the special page with required services
	 * @param string|null $par
	 */
	public function execute( $par ) {
		$this->config = $this->getConfig();
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
	 * @param string $url
	 * @return bool
	 */
	private function isValidUrl( $url ) {
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
	 * Display ELN selection form (step 1)
	 * @param OutputPage $out
	 * @param WebRequest $request
	 */
	private function displaySelectionForm( $out, $request ) {
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

		// Check service status and display
		$status = $this->checkServiceStatus();

		// Display compact dropdown selection
		$html = '<div class="elnsmwadapterui-form-container">';

		// Service status section
		$html .= '<div class="elnsmwadapterui-status-section">';
		$html .= '<h3>Service Status</h3>';
		if ( $status ) {
			$html .= '<div class="elnsmwadapterui-status-connected">';
			$html .= '<span style="color: green;">●</span> Service is running (v' .
				htmlspecialchars( $status['version'] ?? 'unknown' ) . ')';

			if ( isset( $status['smw_connection'] ) ) {
				$html .= '<br><small>SMW Connection: ' . htmlspecialchars( $status['smw_connection'] ) . '</small>';
			}
			if ( isset( $status['enabled_plugins'] ) && is_array( $status['enabled_plugins'] ) ) {
				$html .= '<br><small>Available plugins: ' .
					htmlspecialchars( implode( ', ', $status['enabled_plugins'] ) ) . '</small>';
			}
			$html .= '</div>';
		} else {
			$html .= '<div class="elnsmwadapterui-status-disconnected">';
			$html .= '<span style="color: red;">●</span> Service unavailable or not responding';
			$html .= '</div>';
		}
		$html .= '</div>';

		$html .= '<div class="elnsmwadapterui-section">';
		$html .= '<h2 class="elnsmwadapterui-section-title">' .
			$this->msg( 'elnsmwadapterui-form-select-legend' )->escaped() . '</h2>';

		$html .= '<div class="elnsmwadapterui-form-content">';
		$html .= '<p class="elnsmwadapterui-form-description">' .
			$this->msg( 'elnsmwadapterui-form-eln-type-help' )->escaped() . '</p>';

		$html .= Html::openElement( 'form', [
			'method' => 'get',
			'action' => $this->getPageTitle()->getLocalURL()
		] );

		$html .= '<div class="elnsmwadapterui-form-field">';
		$html .= '<label class="elnsmwadapterui-form-label">' .
			$this->msg( 'elnsmwadapterui-form-eln-type-label' )->escaped() . '</label>';
		$html .= Html::openElement( 'select', [
			'name' => 'eln-type',
			'class' => 'elnsmwadapterui-form-select',
			'required' => 'required'
		] );
		// Get plugins dynamically from service status
		$status = $this->checkServiceStatus();
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

		// Add URL option if there are URL-based plugins (preselected)
		if ( $hasUrlPlugins ) {
			$html .= Html::element( 'option', [ 'value' => 'url', 'selected' => 'selected' ], 'URL (default)' );
		}

		// Add upload options for each upload plugin
		foreach ( $uploadPlugins as $pluginName ) {
			$optionText = 'Upload file (' . htmlspecialchars( $pluginName ) . ')';
			$html .= Html::element( 'option', [ 'value' => $pluginName ], $optionText );
		}
		$html .= Html::closeElement( 'select' );
		$html .= '</div>';

		$html .= '<div class="elnsmwadapterui-form-actions">';
		$html .= Html::element( 'input', [
			'type' => 'submit',
			'value' => $this->msg( 'elnsmwadapterui-form-continue' )->text(),
			'class' => 'mw-ui-button mw-ui-progressive elnsmwadapterui-submit-button'
		] );
		$html .= '</div>';

		$html .= Html::closeElement( 'form' );
		// Close form-content
		$html .= '</div>';
		// Close section
		$html .= '</div>';
		// Close form-container
		$html .= '</div>';

		$out->addHTML( $html );

		// Display any messages
		$this->displayMessages( $out );
	}

	/**
	 * Display method-specific form (step 2)
	 * @param OutputPage $out
	 * @param string $method
	 */
	private function displayMethodForm( $out, $method ) {
		if ( $method === 'url' ) {
			$this->displayUrlForm( $out );
			return;
		}

		// Check if method is an upload-type plugin
		$status = $this->checkServiceStatus();
		if ( $status && isset( $status['plugins'][$method] ) ) {
			$pluginInfo = $status['plugins'][$method];
			if ( $pluginInfo['type'] === 'upload' ) {
				$this->displayFileUploadForm( $out, $method );
				return;
			}
		}

		// Fallback for unknown methods
		switch ( $method ) {
			default:
				$out->addHTML( '<div class="errorbox">Unknown method: ' . htmlspecialchars( $method ) . '</div>' );
				break;
		}
	}

	/**
	 * Display URL form (original form)
	 * @param OutputPage $out
	 * @param string $elnUrl
	 */
	private function displayUrlForm( $out, $elnUrl = '' ) {
		$request = $this->getRequest();

		// Handle form submission
		if ( $request->wasPosted() ) {
			if ( !$this->getUser()->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
				$out->addHTML( '<div class="errorbox">Invalid form submission. Please try again.</div>' );
			} else {
				$urlValue = $request->getVal( 'eln-url', '' );
				$result = $this->processForm( [ 'eln-url' => $urlValue ] );
				if ( $result !== true && is_string( $result ) ) {
					$out->addHTML( '<div class="errorbox">' . htmlspecialchars( $result ) . '</div>' );
				} elseif ( $result === true ) {
					// Redirect happened in processForm
					return;
				}
				// Keep the entered value
				$elnUrl = $urlValue;
			}
		}

		// Display styled form
		$token = $this->getUser()->getEditToken();

		$html = '<div class="elnsmwadapterui-form-container">';
		$html .= '<div class="elnsmwadapterui-section">';
		$html .= '<h2 class="elnsmwadapterui-section-title">' .
			$this->msg( 'elnsmwadapterui-form-legend' )->escaped() . '</h2>';

		$html .= '<div class="elnsmwadapterui-form-content">';
		$html .= '<p class="elnsmwadapterui-form-description">' .
			$this->msg( 'elnsmwadapterui-form-url-help' )->escaped() . '</p>';

		$html .= Html::openElement( 'form', [
			'method' => 'post',
			'action' => $this->getPageTitle()->getLocalURL( [ 'method' => 'url' ] )
		] );

		$html .= Html::element( 'input', [
			'type' => 'hidden',
			'name' => 'wpEditToken',
			'value' => $token
		] );

		$html .= '<div class="elnsmwadapterui-form-field">';
		$html .= '<label class="elnsmwadapterui-form-label">' .
			$this->msg( 'elnsmwadapterui-form-url-label' )->escaped() . '</label>';
		$html .= Html::element( 'input', [
			'type' => 'url',
			'name' => 'eln-url',
			'class' => 'elnsmwadapterui-form-input',
			'placeholder' => 'https://elab.tu-clausthal.de/experiments.php?mode=view&id=0000',
			'required' => 'required',
			'value' => $elnUrl
		] );
		$html .= '</div>';

		$html .= '<div class="elnsmwadapterui-form-actions">';
		$html .= Html::element( 'input', [
			'type' => 'submit',
			'value' => $this->msg( 'elnsmwadapterui-form-submit' )->text(),
			'class' => 'mw-ui-button mw-ui-progressive elnsmwadapterui-submit-button'
		] );
		$html .= '</div>';

		$html .= Html::closeElement( 'form' );
		// Close form-content
		$html .= '</div>';
		// Close section
		$html .= '</div>';
		// Close form-container
		$html .= '</div>';

		$out->addHTML( $html );

		// Display any messages
		$this->displayMessages( $out );

		// Add back button
		$this->addBackToSelectionButton( $out );
	}

	/**
	 * Display file upload form
	 * @param OutputPage $out
	 * @param string $method
	 */
	private function displayFileUploadForm( $out, $method ) {
		$request = $this->getRequest();

		// Handle form submission first
		if ( $request->wasPosted() && $request->getVal( 'wpmethod' ) === $method ) {
			if ( !$this->getUser()->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
				$out->addHTML( '<div class="errorbox">Invalid form submission. Please try again.</div>' );
				return;
			}

			$result = $this->processFileUploadForm( [ 'method' => $method ] );
			if ( $result !== true && is_string( $result ) ) {
				$out->addHTML( '<div class="errorbox">' . htmlspecialchars( $result ) . '</div>' );
			} elseif ( $result === true ) {
				// Redirect happened in processFileUploadForm
				return;
			}
		}

		// Fetch plugin info to get dynamic fields
		$pluginInfo = $this->getPluginInfo( $method );

		// Display styled file upload form
		$token = $this->getUser()->getEditToken();

		$html = '<div class="elnsmwadapterui-form-container">';
		$html .= '<div class="elnsmwadapterui-section">';
		$html .= '<h2 class="elnsmwadapterui-section-title">' .
			$this->msg( 'elnsmwadapterui-form-upload-legend' )->escaped() . '</h2>';

		$html .= '<div class="elnsmwadapterui-form-content">';

		// Show plugin information
		$html .= '<div class="elnsmwadapterui-plugin-info">';
		$html .= '<h3>Import method: ' . htmlspecialchars( $method ) . '</h3>';
		$html .= '</div>';

		$html .= '<p class="elnsmwadapterui-form-description">' .
			$this->msg( 'elnsmwadapterui-form-file-help' )->escaped() . '</p>';

		$html .= Html::openElement( 'form', [
			'method' => 'post',
			'enctype' => 'multipart/form-data',
			'action' => $this->getPageTitle()->getLocalURL( [ 'method' => $method ] )
		] );

		$html .= Html::element( 'input', [
			'type' => 'hidden',
			'name' => 'wpEditToken',
			'value' => $token
		] );

		$html .= Html::element( 'input', [
			'type' => 'hidden',
			'name' => 'wpmethod',
			'value' => $method
		] );

		// File upload field
		$html .= '<div class="elnsmwadapterui-form-field">';
		$html .= '<label class="elnsmwadapterui-form-label">' .
			$this->msg( 'elnsmwadapterui-form-file-label' )->escaped() . '</label>';

		$html .= '<div class="elnsmwadapterui-file-upload">';
		$html .= Html::element( 'input', [
			'type' => 'file',
			'name' => 'upload-file',
			'class' => 'elnsmwadapterui-file-input',
			'required' => 'required',
			'onchange' => 'updateFileName(this)'
		] );
		$html .= '<div class="elnsmwadapterui-file-drop-zone" ' .
			'onclick="document.querySelector(\'.elnsmwadapterui-file-input\').click();">';
		$html .= '<div class="elnsmwadapterui-file-icon">📄</div>';
		$html .= '<div class="elnsmwadapterui-file-text">' .
			$this->msg( 'elnsmwadapterui-file-drop-text' )->escaped() . '</div>';
		$html .= '</div>';
		$html .= '<div class="elnsmwadapterui-selected-file" style="display: none;">';
		$html .= '<span class="elnsmwadapterui-file-name"></span>';
		$html .= '<button type="button" class="elnsmwadapterui-remove-file" onclick="clearFileName()">×</button>';
		$html .= '</div>';
		$html .= '</div>';

		// Close form-field
		$html .= '</div>';

		// Render dynamic fields from plugin info
		if ( $pluginInfo && isset( $pluginInfo['fields'] ) && is_array( $pluginInfo['fields'] ) ) {
			foreach ( $pluginInfo['fields'] as $field ) {
				$html .= $this->renderDynamicField( $field );
			}
		}

		$html .= '<div class="elnsmwadapterui-form-actions">';
		$html .= Html::element( 'input', [
			'type' => 'submit',
			'value' => $this->msg( 'elnsmwadapterui-form-upload' )->text(),
			'class' => 'mw-ui-button mw-ui-progressive elnsmwadapterui-submit-button'
		] );
		$html .= '</div>';

		$html .= Html::closeElement( 'form' );
		// Close form-content
		$html .= '</div>';
		// Close section
		$html .= '</div>';
		// Close form-container
		$html .= '</div>';

		$out->addHTML( $html );

		// Add JavaScript for file name display and drag-and-drop
		$out->addInlineScript( "
            function updateFileName(input) {
                var fileName = input.files[0] ? input.files[0].name : '';
                var fileNameSpan = document.querySelector('.elnsmwadapterui-file-name');
                var selectedFileDiv = document.querySelector('.elnsmwadapterui-selected-file');
                var dropZone = document.querySelector('.elnsmwadapterui-file-drop-zone');

                if (fileName) {
                    fileNameSpan.textContent = fileName;
                    selectedFileDiv.style.display = 'block';
                    dropZone.style.display = 'none';
                } else {
                    selectedFileDiv.style.display = 'none';
                    dropZone.style.display = 'block';
                }
            }

            function clearFileName() {
                var fileInput = document.querySelector('.elnsmwadapterui-file-input');
                var selectedFileDiv = document.querySelector('.elnsmwadapterui-selected-file');
                var dropZone = document.querySelector('.elnsmwadapterui-file-drop-zone');

                fileInput.value = '';
                selectedFileDiv.style.display = 'none';
                dropZone.style.display = 'block';
            }

            // Add drag-and-drop functionality
            (function() {
                var dropZone = document.querySelector('.elnsmwadapterui-file-drop-zone');
                var fileInput = document.querySelector('.elnsmwadapterui-file-input');

                if (dropZone && fileInput) {
                    // Prevent default drag behaviors
                    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(eventName) {
                        dropZone.addEventListener(eventName, function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                        }, false);
                    });

                    // Highlight drop zone when dragging over
                    ['dragenter', 'dragover'].forEach(function(eventName) {
                        dropZone.addEventListener(eventName, function() {
                            dropZone.style.backgroundColor = '#f0f8ff';
                            dropZone.style.borderColor = '#4a90e2';
                        }, false);
                    });

                    ['dragleave', 'drop'].forEach(function(eventName) {
                        dropZone.addEventListener(eventName, function() {
                            dropZone.style.backgroundColor = '';
                            dropZone.style.borderColor = '';
                        }, false);
                    });

                    // Handle dropped files
                    dropZone.addEventListener('drop', function(e) {
                        var dt = e.dataTransfer;
                        var files = dt.files;

                        if (files.length > 0) {
                            fileInput.files = files;
                            updateFileName(fileInput);
                        }
                    }, false);
                }
            })();
        " );

		// Display any messages
		$this->displayMessages( $out );

		// Add back button
		$this->addBackToSelectionButton( $out );
	}

	/**
	 * Add back to selection button
	 * @param OutputPage $out
	 */
	private function addBackToSelectionButton( $out ) {
		$html = '<div class="elnsmwadapterui-back-selection">';
		$html .= Html::element( 'a', [
			'href' => $this->getPageTitle()->getLocalURL(),
			'class' => 'mw-ui-button'
		], $this->msg( 'elnsmwadapterui-back-to-selection' )->text() );
		$html .= '</div>';
		$out->addHTML( $html );
	}

	/**
	 * Render a dynamic form field based on field metadata
	 * @param array $field Field metadata from plugin info
	 * @return string HTML for the field
	 */
	private function renderDynamicField( $field ) {
		$html = '<div class="elnsmwadapterui-form-field">';
		$html .= '<label class="elnsmwadapterui-form-label">';
		$html .= htmlspecialchars( $field['label'] );
		if ( isset( $field['required'] ) && $field['required'] ) {
			$html .= ' <span style="color: red;">*</span>';
		}
		$html .= '</label>';

		$fieldName = 'field_' . htmlspecialchars( $field['name'] );
		$isRequired = isset( $field['required'] ) && $field['required'];

		switch ( $field['type'] ) {
			case 'select':
				$html .= Html::openElement( 'select', [
					'name' => $fieldName,
					'class' => 'elnsmwadapterui-form-select',
					'required' => $isRequired ? 'required' : null
				] );

				// Add empty option that's selected by default to force user selection
				$html .= Html::element( 'option', [
					'value' => '',
					'selected' => 'selected',
					'disabled' => 'disabled'
				], '-- Please select --' );

				if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
					foreach ( $field['options'] as $option ) {
						$html .= Html::element( 'option', [
							'value' => htmlspecialchars( $option )
						], htmlspecialchars( $option ) );
					}
				}
				$html .= Html::closeElement( 'select' );
				break;

			case 'text':
			default:
				$html .= Html::element( 'input', [
					'type' => 'text',
					'name' => $fieldName,
					'class' => 'elnsmwadapterui-form-input',
					'required' => $isRequired ? 'required' : null
				] );
				break;
		}

		$html .= '</div>';
		return $html;
	}

	/**
	 * Process URL form submission callback
	 * @param array $data
	 * @return bool|string
	 */
	public function processForm( $data ) {
		$elnUrl = isset( $data['eln-url'] ) ? $data['eln-url'] : '';

		if ( !$this->isValidUrl( $elnUrl ) ) {
			return 'Please provide a valid URL.';
		}

		$result = $this->adaptProtocols( $elnUrl );
		if ( $result ) {
			// Check if this is an async job
			if ( isset( $result->is_async_job ) && $result->is_async_job === true ) {
				// Redirect to processing page with job_id
				$this->getOutput()->redirect(
					$this->getPageTitle()->getLocalURL( [ 'action' => 'processing', 'job_id' => $result->job_id ] )
				);
				return true;
			}

			// Synchronous result - redirect to show results page
			$this->getOutput()->redirect(
				$this->getPageTitle()->getLocalURL( [
					'action' => 'results',
					'data' => base64_encode( json_encode( $result ) )
				] )
			);
			return true;
		}

		return 'Failed to process the request.';
	}

	/**
	 * Process file upload form submission
	 * @param array $data
	 * @return bool|string
	 */
	public function processFileUploadForm( $data ) {
		$method = isset( $data['method'] ) ? $data['method'] : '';

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
		$result = $this->adaptProtocols( $uploadPath, $method, $dynamicFields );
		if ( $result ) {
			// Check if this is an async job
			if ( isset( $result->is_async_job ) && $result->is_async_job === true ) {
				// Redirect to processing page with job_id
				$this->getOutput()->redirect(
					$this->getPageTitle()->getLocalURL( [ 'action' => 'processing', 'job_id' => $result->job_id ] )
				);
				return true;
			}

			// Synchronous result - redirect to show results page
			$this->getOutput()->redirect(
				$this->getPageTitle()->getLocalURL( [
					'action' => 'results',
					'data' => base64_encode( json_encode( $result ) )
				] )
			);
			return true;
		}

		return 'Failed to process the uploaded file.';
	}

	/**
	 * Get upload directory path
	 * @return string|false
	 */
	private function getUploadDirectory() {
		// Get upload path from service status
		$status = $this->checkServiceStatus();
		if ( $status && isset( $status['upload_path'] ) ) {
			return $status['upload_path'];
		}

		$this->logger->error( 'Upload path not available from service' );
		return false;
	}

	/**
	 * Display results page
	 * @param OutputPage $out
	 * @param WebRequest $request
	 */
	private function displayResultsPage( $out, $request ) {
		// Check if we have job_id (new method) or data (old method for backwards compat)
		$jobId = $request->getVal( 'job_id', '' );
		$data = $request->getVal( 'data', '' );

		$result = null;

		if ( !empty( $jobId ) ) {
			// Fetch result from backend via job_id
			$serviceUrl = $this->config->get( 'ELNSMWAdapterUIServiceURL' );
			$jobUrl = rtrim( $serviceUrl, '/' ) . '/job/' . urlencode( $jobId );

			$ch = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $jobUrl );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 10 );

			$response = curl_exec( $ch );
			$httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			curl_close( $ch );

			if ( $httpCode === 200 && $response ) {
				$jobData = json_decode( $response );
				if ( $jobData && isset( $jobData->result ) ) {
					$result = $jobData->result;
				}
			}
		} elseif ( !empty( $data ) ) {
			// Old method: decode from URL parameter
			$result = json_decode( base64_decode( $data ) );
		}

		if ( !$result ) {
			$out->addHTML( '<div class="errorbox">No results data found.</div>' );
			return;
		}

		$this->displayResults( $out, $result );
	}

	/**
	 * Display processing page with JavaScript polling
	 * @param OutputPage $out
	 * @param WebRequest $request
	 */
	private function displayProcessingPage( $out, $request ) {
		$jobId = $request->getVal( 'job_id', '' );
		if ( empty( $jobId ) ) {
			$out->addHTML( '<div class="errorbox">No job ID provided.</div>' );
			return;
		}

		$out->setPageTitle( 'Processing Import' );

		// Use public job status URL that browser can access (via nginx proxy)
		// Construct from $wgServer (https://service.tib.eu) + /sfb1368/eln-smw-adapter/job/
		$server = $this->getConfig()->get( 'Server' );
		$jobStatusUrl = rtrim( $server, '/' ) . '/sfb1368/eln-smw-adapter/job/' . urlencode( $jobId );
		$resultsUrl = $this->getPageTitle()->getLocalURL( [ 'action' => 'results', 'job_id' => '__JOBID__' ] );

		$html = '<div class="elnsmwadapterui-processing">';
		$html .= '<div class="elnsmwadapterui-section">';
		$html .= '<h2 class="elnsmwadapterui-section-title">Processing Your Import</h2>';
		$html .= '<div class="elnsmwadapterui-processing-content">';
		$html .= '<div class="elnsmwadapterui-spinner"></div>';
		$html .= '<p id="processing-status">Please wait while your import is being processed...</p>';
		$html .= '<p id="processing-error" style="display:none; color: red;"></p>';
		$html .= '</div>';
		$html .= '</div>';
		$html .= '</div>';

		$out->addHTML( $html );

		// Add inline JavaScript for polling
		$out->addInlineScript( "
(function() {
    var jobStatusUrl = " . json_encode( $jobStatusUrl ) . ";
    var resultsBaseUrl = " . json_encode( $resultsUrl ) . ";
    var pollInterval = 1000; // Poll every second
    var maxAttempts = 180; // Max 180 seconds
    var attempts = 0;

    function pollJobStatus() {
        attempts++;

        if (attempts > maxAttempts) {
            document.getElementById('processing-status').style.display = 'none';
            document.getElementById('processing-error').textContent =
                'Request timed out after 3 minutes. The import may still be processing. Please check back later.';
            document.getElementById('processing-error').style.display = 'block';
            return;
        }

        fetch(jobStatusUrl)
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data) {
                if (data.status === 'completed') {
                    // Success - redirect to results page with job_id
                    window.location.href = resultsBaseUrl.replace('__JOBID__', data.id);
                } else if (data.status === 'failed') {
                    // Failed - show error
                    document.getElementById('processing-status').style.display = 'none';
                    document.getElementById('processing-error').textContent =
                        'Import failed: ' + (data.error || 'Unknown error');
                    document.getElementById('processing-error').style.display = 'block';
                } else {
                    // Still processing - poll again
                    setTimeout(pollJobStatus, pollInterval);
                }
            })
            .catch(function(error) {
                // Network error - retry
                console.error('Polling error:', error);
                setTimeout(pollJobStatus, pollInterval);
            });
    }

    // Start polling
    pollJobStatus();
})();
        " );

		// Add CSS for spinner
		$out->addInlineStyle( "
.elnsmwadapterui-processing {
    text-align: center;
    padding: 40px 20px;
}
.elnsmwadapterui-processing-content {
    padding: 20px;
}
.elnsmwadapterui-spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
#processing-status {
    font-size: 16px;
    color: #555;
    margin: 10px 0;
}
        " );
	}

	/**
	 * Display results after processing
	 * @param OutputPage $out
	 * @param stdClass|null $result
	 */
	private function displayResults( $out, $result ) {
		if ( !$result ) {
			$out->addHTML( '<div class="errorbox">' .
				$this->msg( 'elnsmwadapterui-error-service-offline' )->escaped() .
				'</div>' );
			return;
		}

		// Page header
		$out->setPageTitle( $this->msg( 'elnsmwadapterui-results-title' ) );

		$html = '<div class="elnsmwadapterui-results">';

		// Imported Protocols Section
		$html .= '<div class="elnsmwadapterui-section">';
		$html .= '<h2 class="elnsmwadapterui-section-title">' .
			$this->msg( 'elnsmwadapterui-results-protocols' )->escaped() . '</h2>';

		if ( isset( $result->smw_pages ) && !empty( $result->smw_pages ) ) {
			$wikiUrl = $this->config->get( 'ELNSMWAdapterUIWikiURL' );
			$html .= '<div class="elnsmwadapterui-protocols-list">';

			$protocolCount = 0;
			foreach ( $result->smw_pages as $pageId => $pageData ) {
				if ( strpos( $pageId, 'P' ) === 0 ) {
					$url = $wikiUrl . '/' . urlencode( $pageId );
					$html .= '<div class="elnsmwadapterui-protocol-item">';
					$html .= Html::element( 'a', [
						'href' => $url,
						'target' => '_blank',
						'rel' => 'noopener noreferrer',
						'class' => 'elnsmwadapterui-protocol-link'
					], $pageId );
					$html .= '</div>';
					$protocolCount++;
				}
			}

			if ( $protocolCount === 0 ) {
				$html .= '<div class="elnsmwadapterui-no-protocols">' .
					$this->msg( 'elnsmwadapterui-warning-no-protocols' )->escaped() . '</div>';
			} else {
				$html .= '<div class="elnsmwadapterui-protocols-summary">' .
					$this->msg( 'elnsmwadapterui-protocols-count', $protocolCount )->escaped() . '</div>';
			}

			$html .= '</div>';
		} else {
			$html .= '<div class="elnsmwadapterui-no-protocols">' .
				$this->msg( 'elnsmwadapterui-warning-no-protocols' )->escaped() . '</div>';
		}

		// Close protocols section
		$html .= '</div>';

		// Import Log Section
		$html .= '<div class="elnsmwadapterui-section">';
		$html .= '<h2 class="elnsmwadapterui-section-title">' .
			$this->msg( 'elnsmwadapterui-results-log' )->escaped() . '</h2>';

		if ( isset( $result->messages ) && !empty( $result->messages ) ) {
			$html .= '<div class="elnsmwadapterui-log-messages">';

			foreach ( $result->messages as $message ) {
				$cssClass = $this->getMessageCssClass( $message->type );
				$html .= '<div class="elnsmwadapterui-log-message elnsmwadapterui-log-' . $cssClass . '">';
				$html .= '<span class="elnsmwadapterui-log-type">[' . strtoupper( $message->type ) . ']</span> ';
				$html .= '<span class="elnsmwadapterui-log-text">' . htmlspecialchars( $message->text ) . '</span>';
				$html .= '</div>';
			}

			$html .= '</div>';
		} else {
			$html .= '<div class="elnsmwadapterui-no-messages">No log messages available.</div>';
		}

		// Close log section
		$html .= '</div>';

		// Action buttons
		$html .= '<div class="elnsmwadapterui-actions">';
		$html .= Html::element( 'a', [
			'href' => $this->getPageTitle()->getLocalURL(),
			'class' => 'mw-ui-button mw-ui-progressive'
		], $this->msg( 'elnsmwadapterui-back-button' )->text() );
		$html .= '</div>';

		// Close main results div
		$html .= '</div>';

		$out->addHTML( $html );
	}

	/**
	 * Get CSS class for message type
	 * @param string $type
	 * @return string
	 */
	private function getMessageCssClass( $type ) {
		switch ( $type ) {
			case 'error':
				return 'error';
			case 'warning':
				return 'warning';
			default:
				return 'notice';
		}
	}

	/**
	 * Process protocols from ELN URL or file path
	 * @param string $elnUrlOrPath
	 * @param string $method
	 * @param array $dynamicFields
	 * @return stdClass|null
	 */
	private function adaptProtocols( $elnUrlOrPath, $method = 'url', $dynamicFields = [] ) {
		if ( $method === 'url' ) {
			// Handle URL-based processing (original logic)
			$parsedUrl = parse_url( $elnUrlOrPath );

			if ( !isset( $parsedUrl['host'] ) ) {
				$this->addMessage( 'error', 'elnsmwadapterui-error-invalid-url' );
				return null;
			}

			switch ( $parsedUrl['host'] ) {
				case 'elab.tu-clausthal.de':
					return $this->processELabFTWUrl( $parsedUrl, $dynamicFields );
				default:
					$this->addMessage( 'error', 'elnsmwadapterui-error-unsupported-eln', [ $parsedUrl['host'] ] );
					return null;
			}
		} else {
			// Handle file-based processing
			return $this->processUploadedFile( $elnUrlOrPath, $method, $dynamicFields );
		}
	}

	/**
	 * Process uploaded file
	 * @param string $filePath
	 * @param string $method
	 * @param array $dynamicFields
	 * @return stdClass|null
	 */
	private function processUploadedFile( $filePath, $method, $dynamicFields = [] ) {
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
	 * @return stdClass|null
	 */
	private function processELabFTWUrl( $parsedUrl, $dynamicFields = [] ) {
		if ( !isset( $parsedUrl['query'] ) ) {
			$this->addMessage( 'error', 'elnsmwadapterui-error-missing-query' );
			return null;
		}

		parse_str( $parsedUrl['query'], $query );

		if ( !isset( $query['id'] ) ) {
			$this->addMessage( 'error', 'elnsmwadapterui-error-missing-id' );
			return null;
		}

		return $this->callAdapterService( 'eLabFTW', $query['id'], $dynamicFields );
	}

	/**
	 * Call the adapter service with proper error handling
	 * @param string $eln
	 * @param string $id
	 * @param array $additionalData Additional data including user, dynamic fields, etc.
	 * @return stdClass|null
	 */
	private function callAdapterService( $eln, $id, $additionalData = [] ) {
		$serviceUrl = $this->config->get( 'ELNSMWAdapterUIServiceURL' );
		$url = rtrim( $serviceUrl, '/' ) . '/adapt-async';

		// Get current user - prefer real name, fallback to username
		$currentUser = $this->getUser();
		$userName = $currentUser->getRealName();
		if ( empty( $userName ) ) {
			$userName = $currentUser->getName();
		}

		// Build request payload
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

		$ch = curl_init();
		curl_setopt_array( $ch, [
			CURLOPT_URL => $url,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => json_encode( $payload ),
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json',
				'User-Agent: MediaWiki-ELNSMWAdapterUI/0.2.0'
			],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 120,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_SSL_VERIFYPEER => true,
		] );

		$response = curl_exec( $ch );
		$httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$curlError = curl_error( $ch );
		curl_close( $ch );

		if ( $response === false || !empty( $curlError ) ) {
			$this->logger->error( 'cURL error when calling adapter service', [
				'error' => $curlError,
				'url' => $url
			] );
			$this->addMessage( 'error', 'elnsmwadapterui-error-service-offline' );
			return null;
		}

		if ( $httpCode !== 200 ) {
			$this->logger->warning( 'HTTP error from adapter service', [
				'http_code' => $httpCode,
				'response' => $response
			] );
			$this->addMessage( 'error', 'elnsmwadapterui-error-service-error', [ $httpCode ] );
			return null;
		}

		$jobResponse = json_decode( $response );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->logger->error( 'Invalid JSON response from adapter service', [
				'response' => $response,
				'json_error' => json_last_error_msg()
			] );
			$this->addMessage( 'error', 'elnsmwadapterui-error-invalid-response' );
			return null;
		}

		$this->logger->info( 'Got job response', [ 'job_response' => $response ] );

		// Get job ID from response
		if ( !isset( $jobResponse->job_id ) ) {
			$this->logger->error( 'No job_id in response', [ 'response' => $response ] );
			$this->addMessage( 'error', 'elnsmwadapterui-error-invalid-response' );
			return null;
		}

		$jobId = $jobResponse->job_id;
		$this->logger->info( 'Returning job_id to client', [ 'job_id' => $jobId ] );

		// Return job_id wrapped in object to distinguish from regular result
		return (object)[
			'job_id' => $jobId,
			'is_async_job' => true
		];
	}

	/**
	 * Check the status of the adapter service
	 * @return array|null Status information or null if unreachable
	 */
	private function checkServiceStatus() {
		$serviceUrl = $this->config->get( 'ELNSMWAdapterUIServiceURL' );
		$statusUrl = rtrim( $serviceUrl, '/' ) . '/status';

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $statusUrl );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 5 );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 3 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ] );

		$response = curl_exec( $ch );
		$httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		if ( $httpCode === 200 && $response ) {
			return json_decode( $response, true );
		}

		return null;
	}

	/**
	 * Get plugin info including dynamic form fields
	 * @param string $pluginName
	 * @return array|null Plugin info or null if unreachable
	 */
	private function getPluginInfo( $pluginName ) {
		$serviceUrl = $this->config->get( 'ELNSMWAdapterUIServiceURL' );
		$pluginInfoUrl = rtrim( $serviceUrl, '/' ) . '/plugin-info/' . urlencode( $pluginName );

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $pluginInfoUrl );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 5 );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 3 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ] );

		$response = curl_exec( $ch );
		$httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		if ( $httpCode === 200 && $response ) {
			return json_decode( $response, true );
		}

		$this->logger->warning( 'Failed to get plugin info', [
			'plugin' => $pluginName,
			'http_code' => $httpCode
		] );

		return null;
	}

	/**
	 * Add a message to be displayed to the user
	 * @param string $type
	 * @param string $messageKey
	 * @param array $params
	 */
	private function addMessage( $type, $messageKey, $params = [] ) {
		$this->messages[] = [
			'type' => $type,
			'message' => $this->msg( $messageKey, $params )->text()
		];
	}

	/**
	 * Display accumulated messages
	 * @param OutputPage $out
	 */
	private function displayMessages( $out ): void {
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
