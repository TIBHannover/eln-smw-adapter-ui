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
        parent::__construct('ELNSMWAdapterUI', 'elnsmwadapterui-use');
        $this->logger = LoggerFactory::getInstance('ELNSMWAdapterUI');
    }

    /**
     * Initialize the special page with required services
     * @param string|null $par
     */
    public function execute($par) {
        $this->config = $this->getConfig();
        $this->setHeaders();
        $this->checkPermissions();
        
        $out = $this->getOutput();
        $request = $this->getRequest();

        // Add CSS for styling
        $out->addModuleStyles('ext.elnsmwadapterui.styles');

        // Check if we should display results or form
        $action = $request->getVal('action', '');
        $method = $request->getVal('method', '');
        
        if ($action === 'results') {
            $this->displayResultsPage($out, $request);
        } elseif (!empty($method)) {
            // Skip dropdown if method is specified
            $this->displayMethodForm($out, $method);
        } else {
            $this->displaySelectionForm($out, $request);
        }
    }

    /**
     * Validate CSRF token for form submissions
     * @param WebRequest $request
     * @return bool
     */
    private function validateCSRFToken(WebRequest $request) {
        $token = $request->getVal('wpEditToken');
        return $this->getUser()->matchEditToken($token);
    }

    /**
     * Handle form submission and process the ELN URL
     * @param WebRequest $request
     * @param OutputPage $out
     */
    private function handleFormSubmission(WebRequest $request, $out) {
        // Try different field names in case HTMLForm uses a different one
        $elnUrl = $request->getVal('wpeln-url', '');
        if (empty($elnUrl)) {
            $elnUrl = $request->getVal('eln-url', '');
        }
        
        // Debug logging
        $this->logger->debug('Form submission received', [
            'wpeln-url' => $request->getVal('wpeln-url', 'not set'),
            'eln-url' => $request->getVal('eln-url', 'not set'),
            'all_values' => $request->getValues()
        ]);
        
        if (!$this->isValidUrl($elnUrl)) {
            $this->addMessage('error', 'elnsmwadapterui-error-invalid-url');
            $this->displayForm($out, $elnUrl);
            return;
        }

        $result = $this->adaptProtocols($elnUrl);
        $this->displayResults($out, $result);
    }

    /**
     * Validate the provided URL
     * @param string $url
     * @return bool
     */
    private function isValidUrl($url) {
        if (empty($url)) {
            $this->logger->debug('URL validation failed: empty URL');
            return false;
        }

        $parsedUrl = parse_url($url);
        $this->logger->debug('URL validation', [
            'url' => $url,
            'parsed' => $parsedUrl
        ]);
        
        $isValid = isset($parsedUrl['scheme'], $parsedUrl['host']);
        $this->logger->debug('URL validation result', ['is_valid' => $isValid]);
        
        return $isValid;
    }

    /**
     * Display ELN selection form (step 1)
     * @param OutputPage $out
     * @param WebRequest $request
     */
    private function displaySelectionForm($out, $request) {
        // Check if form was submitted - HTMLForm prefixes with 'wp'
        $elnType = $request->getVal('wpeln-type', '');
        if (empty($elnType)) {
            $elnType = $request->getVal('eln-type', '');
        }
        
        if (!empty($elnType)) {
            // Redirect to method-specific form
            $out->redirect($this->getPageTitle()->getLocalURL(['method' => $elnType]));
            return;
        }

        // Check service status and display
        $status = $this->checkServiceStatus();
        
        // Display compact dropdown selection
        $html = '<div class="elnsmwadapterui-form-container">';
        
        // Service status section
        $html .= '<div class="elnsmwadapterui-status-section">';
        $html .= '<h3>Service Status</h3>';
        if ($status) {
            $html .= '<div class="elnsmwadapterui-status-connected">';
            $html .= '<span style="color: green;">●</span> Service is running (v' . htmlspecialchars($status['version'] ?? 'unknown') . ')';
            
            if (isset($status['smw_connection'])) {
                $html .= '<br><small>SMW Connection: ' . htmlspecialchars($status['smw_connection']) . '</small>';
            }
            if (isset($status['enabled_plugins']) && is_array($status['enabled_plugins'])) {
                $html .= '<br><small>Available plugins: ' . htmlspecialchars(implode(', ', $status['enabled_plugins'])) . '</small>';
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
            $this->msg('elnsmwadapterui-form-select-legend')->escaped() . '</h2>';
        
        $html .= '<div class="elnsmwadapterui-form-content">';
        $html .= '<p class="elnsmwadapterui-form-description">' . 
            $this->msg('elnsmwadapterui-form-eln-type-help')->escaped() . '</p>';
        
        $html .= Html::openElement('form', [
            'method' => 'get',
            'action' => $this->getPageTitle()->getLocalURL()
        ]);
        
        $html .= '<div class="elnsmwadapterui-form-field">';
        $html .= '<label class="elnsmwadapterui-form-label">' . 
            $this->msg('elnsmwadapterui-form-eln-type-label')->escaped() . '</label>';
        $html .= Html::openElement('select', [
            'name' => 'eln-type',
            'class' => 'elnsmwadapterui-form-select',
            'required' => 'required'
        ]);
        // Get plugins dynamically from service status
        $status = $this->checkServiceStatus();
        $hasUrlPlugins = false;
        $uploadPlugins = [];
        
        if ($status && isset($status['plugins'])) {
            foreach ($status['plugins'] as $pluginName => $pluginInfo) {
                if ($pluginInfo['type'] === 'url') {
                    $hasUrlPlugins = true;
                } elseif ($pluginInfo['type'] === 'upload') {
                    $uploadPlugins[] = $pluginName;
                }
            }
        }
        
        // Add URL option if there are URL-based plugins (preselected)
        if ($hasUrlPlugins) {
            $html .= Html::element('option', ['value' => 'url', 'selected' => 'selected'], 'URL (default)');
        }
        
        // Add upload options for each upload plugin
        foreach ($uploadPlugins as $pluginName) {
            $optionText = 'Upload file (' . htmlspecialchars($pluginName) . ')';
            $html .= Html::element('option', ['value' => $pluginName], $optionText);
        }
        $html .= Html::closeElement('select');
        $html .= '</div>';
        
        $html .= '<div class="elnsmwadapterui-form-actions">';
        $html .= Html::element('input', [
            'type' => 'submit',
            'value' => $this->msg('elnsmwadapterui-form-continue')->text(),
            'class' => 'mw-ui-button mw-ui-progressive elnsmwadapterui-submit-button'
        ]);
        $html .= '</div>';
        
        $html .= Html::closeElement('form');
        $html .= '</div>'; // Close form-content
        $html .= '</div>'; // Close section
        $html .= '</div>'; // Close form-container
        
        $out->addHTML($html);

        // Display any messages
        $this->displayMessages($out);
    }


    /**
     * Display method-specific form (step 2)
     * @param OutputPage $out
     * @param string $method
     */
    private function displayMethodForm($out, $method) {
        if ($method === 'url') {
            $this->displayUrlForm($out);
            return;
        }
        
        // Check if method is an upload-type plugin
        $status = $this->checkServiceStatus();
        if ($status && isset($status['plugins'][$method])) {
            $pluginInfo = $status['plugins'][$method];
            if ($pluginInfo['type'] === 'upload') {
                $this->displayFileUploadForm($out, $method);
                return;
            }
        }
        
        // Fallback for unknown methods
        switch ($method) {
            default:
                $out->addHTML('<div class="errorbox">Unknown method: ' . htmlspecialchars($method) . '</div>');
                break;
        }
    }

    /**
     * Display URL form (original form)
     * @param OutputPage $out
     * @param string $elnUrl
     */
    private function displayUrlForm($out, $elnUrl = '') {
        $request = $this->getRequest();
        
        // Handle form submission
        if ($request->wasPosted()) {
            if (!$this->getUser()->matchEditToken($request->getVal('wpEditToken'))) {
                $out->addHTML('<div class="errorbox">Invalid form submission. Please try again.</div>');
            } else {
                $urlValue = $request->getVal('eln-url', '');
                $result = $this->processForm(['eln-url' => $urlValue]);
                if ($result !== true && is_string($result)) {
                    $out->addHTML('<div class="errorbox">' . htmlspecialchars($result) . '</div>');
                } elseif ($result === true) {
                    return; // Redirect happened in processForm
                }
                $elnUrl = $urlValue; // Keep the entered value
            }
        }

        // Display styled form
        $token = $this->getUser()->getEditToken();
        
        $html = '<div class="elnsmwadapterui-form-container">';
        $html .= '<div class="elnsmwadapterui-section">';
        $html .= '<h2 class="elnsmwadapterui-section-title">' . 
            $this->msg('elnsmwadapterui-form-legend')->escaped() . '</h2>';
        
        $html .= '<div class="elnsmwadapterui-form-content">';
        $html .= '<p class="elnsmwadapterui-form-description">' . 
            $this->msg('elnsmwadapterui-form-url-help')->escaped() . '</p>';
        
        $html .= Html::openElement('form', [
            'method' => 'post',
            'action' => $this->getPageTitle()->getLocalURL(['method' => 'url'])
        ]);
        
        $html .= Html::element('input', [
            'type' => 'hidden',
            'name' => 'wpEditToken',
            'value' => $token
        ]);
        
        $html .= '<div class="elnsmwadapterui-form-field">';
        $html .= '<label class="elnsmwadapterui-form-label">' . 
            $this->msg('elnsmwadapterui-form-url-label')->escaped() . '</label>';
        $html .= Html::element('input', [
            'type' => 'url',
            'name' => 'eln-url',
            'class' => 'elnsmwadapterui-form-input',
            'placeholder' => 'https://elab.tu-clausthal.de/experiments.php?mode=view&id=0000',
            'required' => 'required',
            'value' => $elnUrl
        ]);
        $html .= '</div>';
        
        $html .= '<div class="elnsmwadapterui-form-actions">';
        $html .= Html::element('input', [
            'type' => 'submit',
            'value' => $this->msg('elnsmwadapterui-form-submit')->text(),
            'class' => 'mw-ui-button mw-ui-progressive elnsmwadapterui-submit-button'
        ]);
        $html .= '</div>';
        
        $html .= Html::closeElement('form');
        $html .= '</div>'; // Close form-content
        $html .= '</div>'; // Close section
        $html .= '</div>'; // Close form-container
        
        $out->addHTML($html);

        // Display any messages
        $this->displayMessages($out);
        
        // Add back button
        $this->addBackToSelectionButton($out);
    }

    /**
     * Display file upload form
     * @param OutputPage $out
     * @param string $method
     */
    private function displayFileUploadForm($out, $method) {
        $request = $this->getRequest();
        
        // Handle form submission first
        if ($request->wasPosted() && $request->getVal('wpmethod') === $method) {
            if (!$this->getUser()->matchEditToken($request->getVal('wpEditToken'))) {
                $out->addHTML('<div class="errorbox">Invalid form submission. Please try again.</div>');
                return;
            }
            
            $result = $this->processFileUploadForm(['method' => $method]);
            if ($result !== true && is_string($result)) {
                $out->addHTML('<div class="errorbox">' . htmlspecialchars($result) . '</div>');
            } elseif ($result === true) {
                return; // Redirect happened in processFileUploadForm
            }
        }

        // Display styled file upload form
        $token = $this->getUser()->getEditToken();
        
        $html = '<div class="elnsmwadapterui-form-container">';
        $html .= '<div class="elnsmwadapterui-section">';
        $html .= '<h2 class="elnsmwadapterui-section-title">' . 
            $this->msg('elnsmwadapterui-form-upload-legend')->escaped() . '</h2>';
        
        $html .= '<div class="elnsmwadapterui-form-content">';
        
        // Show plugin information
        $html .= '<div class="elnsmwadapterui-plugin-info">';
        $html .= '<h3>Import method: ' . htmlspecialchars($method) . '</h3>';
        $html .= '</div>';
        
        $html .= '<p class="elnsmwadapterui-form-description">' . 
            $this->msg('elnsmwadapterui-form-file-help')->escaped() . '</p>';
        
        $html .= Html::openElement('form', [
            'method' => 'post',
            'enctype' => 'multipart/form-data',
            'action' => $this->getPageTitle()->getLocalURL(['method' => $method])
        ]);
        
        $html .= Html::element('input', [
            'type' => 'hidden',
            'name' => 'wpEditToken',
            'value' => $token
        ]);
        
        $html .= Html::element('input', [
            'type' => 'hidden',
            'name' => 'wpmethod',
            'value' => $method
        ]);
        
        // File upload field
        $html .= '<div class="elnsmwadapterui-form-field">';
        $html .= '<label class="elnsmwadapterui-form-label">' . 
            $this->msg('elnsmwadapterui-form-file-label')->escaped() . '</label>';
        
        $html .= '<div class="elnsmwadapterui-file-upload">';
        $html .= Html::element('input', [
            'type' => 'file',
            'name' => 'upload-file',
            'class' => 'elnsmwadapterui-file-input',
            'required' => 'required',
            'onchange' => 'updateFileName(this)'
        ]);
        $html .= '<div class="elnsmwadapterui-file-drop-zone" onclick="document.querySelector(\'.elnsmwadapterui-file-input\').click();">';
        $html .= '<div class="elnsmwadapterui-file-icon">📄</div>';
        $html .= '<div class="elnsmwadapterui-file-text">' . 
            $this->msg('elnsmwadapterui-file-drop-text')->escaped() . '</div>';
        $html .= '</div>';
        $html .= '<div class="elnsmwadapterui-selected-file" style="display: none;">';
        $html .= '<span class="elnsmwadapterui-file-name"></span>';
        $html .= '<button type="button" class="elnsmwadapterui-remove-file" onclick="clearFileName()">×</button>';
        $html .= '</div>';
        $html .= '</div>';
        
        $html .= '</div>'; // Close form-field
        
        $html .= '<div class="elnsmwadapterui-form-actions">';
        $html .= Html::element('input', [
            'type' => 'submit',
            'value' => $this->msg('elnsmwadapterui-form-upload')->text(),
            'class' => 'mw-ui-button mw-ui-progressive elnsmwadapterui-submit-button'
        ]);
        $html .= '</div>';
        
        $html .= Html::closeElement('form');
        $html .= '</div>'; // Close form-content
        $html .= '</div>'; // Close section
        $html .= '</div>'; // Close form-container
        
        $out->addHTML($html);

        // Add JavaScript for file name display
        $out->addInlineScript("
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
        ");

        // Display any messages
        $this->displayMessages($out);
        
        // Add back button
        $this->addBackToSelectionButton($out);
    }


    /**
     * Add back to selection button
     * @param OutputPage $out
     */
    private function addBackToSelectionButton($out) {
        $html = '<div class="elnsmwadapterui-back-selection">';
        $html .= Html::element('a', [
            'href' => $this->getPageTitle()->getLocalURL(),
            'class' => 'mw-ui-button'
        ], $this->msg('elnsmwadapterui-back-to-selection')->text());
        $html .= '</div>';
        $out->addHTML($html);
    }
    
    /**
     * Process URL form submission callback
     * @param array $data
     * @return bool|string
     */
    public function processForm($data) {
        $elnUrl = isset($data['eln-url']) ? $data['eln-url'] : '';
        
        if (!$this->isValidUrl($elnUrl)) {
            return 'Please provide a valid URL.';
        }

        $result = $this->adaptProtocols($elnUrl);
        if ($result) {
            // Instead of showing results here, redirect to show results page
            $this->getOutput()->redirect(
                $this->getPageTitle()->getLocalURL(['action' => 'results', 'data' => base64_encode(json_encode($result))])
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
    public function processFileUploadForm($data) {
        $method = isset($data['method']) ? $data['method'] : '';
        
        // Handle file upload
        $request = $this->getRequest();
        $uploadedFile = $request->getUpload('upload-file');
        
        if (!$uploadedFile || !$uploadedFile->exists()) {
            return 'Please select a file to upload.';
        }

        // Get file info
        $fileName = $uploadedFile->getName();
        $fileSize = $uploadedFile->getSize();

        // Create upload directory if it doesn't exist
        $uploadDir = $this->getUploadDirectory();
        if (!$uploadDir) {
            return 'Upload directory is not configured.';
        }

        // Generate unique filename using GUID preserving original extension
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
        $guid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        $uploadPath = $uploadDir . '/' . $guid . ($fileExtension ? '.' . $fileExtension : '');
        
        // Move uploaded file from temp location
        $tempPath = $uploadedFile->getTempName();
        if (!$tempPath || !move_uploaded_file($tempPath, $uploadPath)) {
            return 'Failed to save uploaded file.';
        }

        $this->logger->info('File uploaded successfully', [
            'original_name' => $fileName,
            'upload_path' => $uploadPath,
            'method' => $method,
            'size' => $fileSize
        ]);

        // Process with adapter service using file path as ID
        $result = $this->adaptProtocols($uploadPath, $method);
        if ($result) {
            $this->getOutput()->redirect(
                $this->getPageTitle()->getLocalURL(['action' => 'results', 'data' => base64_encode(json_encode($result))])
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
        if ($status && isset($status['upload_path'])) {
            return $status['upload_path'];
        }
        
        $this->logger->error('Upload path not available from service');
        return false;
    }

    /**
     * Display results page
     * @param OutputPage $out
     * @param WebRequest $request
     */
    private function displayResultsPage($out, $request) {
        $data = $request->getVal('data', '');
        if (empty($data)) {
            $out->addHTML('<div class="errorbox">No results data found.</div>');
            return;
        }
        
        $result = json_decode(base64_decode($data));
        if (!$result) {
            $out->addHTML('<div class="errorbox">Invalid results data.</div>');
            return;
        }
        
        $this->displayResults($out, $result);
    }

    /**
     * Display results after processing  
     * @param OutputPage $out
     * @param object|null $result
     */
    private function displayResults($out, $result) {
        if (!$result) {
            $out->addHTML('<div class="errorbox">' . 
                $this->msg('elnsmwadapterui-error-service-offline')->escaped() . 
                '</div>');
            return;
        }

        // Page header
        $out->setPageTitle($this->msg('elnsmwadapterui-results-title'));
        
        $html = '<div class="elnsmwadapterui-results">';
        
        // Imported Protocols Section
        $html .= '<div class="elnsmwadapterui-section">';
        $html .= '<h2 class="elnsmwadapterui-section-title">' . $this->msg('elnsmwadapterui-results-protocols')->escaped() . '</h2>';
        
        if (isset($result->smw_pages) && !empty($result->smw_pages)) {
            $wikiUrl = $this->config->get('ELNSMWAdapterUIWikiURL');
            $html .= '<div class="elnsmwadapterui-protocols-list">';
            
            $protocolCount = 0;
            foreach ($result->smw_pages as $pageId => $pageData) {
                if (strpos($pageId, 'P') === 0) {
                    $url = $wikiUrl . '/' . urlencode($pageId);
                    $html .= '<div class="elnsmwadapterui-protocol-item">';
                    $html .= Html::element('a', [
                        'href' => $url,
                        'target' => '_blank',
                        'rel' => 'noopener noreferrer',
                        'class' => 'elnsmwadapterui-protocol-link'
                    ], $pageId);
                    $html .= '</div>';
                    $protocolCount++;
                }
            }
            
            if ($protocolCount === 0) {
                $html .= '<div class="elnsmwadapterui-no-protocols">' . 
                    $this->msg('elnsmwadapterui-warning-no-protocols')->escaped() . '</div>';
            } else {
                $html .= '<div class="elnsmwadapterui-protocols-summary">' . 
                    $this->msg('elnsmwadapterui-protocols-count', $protocolCount)->escaped() . '</div>';
            }
            
            $html .= '</div>';
        } else {
            $html .= '<div class="elnsmwadapterui-no-protocols">' . 
                $this->msg('elnsmwadapterui-warning-no-protocols')->escaped() . '</div>';
        }
        
        $html .= '</div>'; // Close protocols section
        
        // Import Log Section
        $html .= '<div class="elnsmwadapterui-section">';
        $html .= '<h2 class="elnsmwadapterui-section-title">' . $this->msg('elnsmwadapterui-results-log')->escaped() . '</h2>';
        
        if (isset($result->messages) && !empty($result->messages)) {
            $html .= '<div class="elnsmwadapterui-log-messages">';
            
            foreach ($result->messages as $message) {
                $cssClass = $this->getMessageCssClass($message->type);
                $html .= '<div class="elnsmwadapterui-log-message elnsmwadapterui-log-' . $cssClass . '">';
                $html .= '<span class="elnsmwadapterui-log-type">[' . strtoupper($message->type) . ']</span> ';
                $html .= '<span class="elnsmwadapterui-log-text">' . htmlspecialchars($message->text) . '</span>';
                $html .= '</div>';
            }
            
            $html .= '</div>';
        } else {
            $html .= '<div class="elnsmwadapterui-no-messages">No log messages available.</div>';
        }
        
        $html .= '</div>'; // Close log section
        
        // Action buttons
        $html .= '<div class="elnsmwadapterui-actions">';
        $html .= Html::element('a', [
            'href' => $this->getPageTitle()->getLocalURL(),
            'class' => 'mw-ui-button mw-ui-progressive'
        ], $this->msg('elnsmwadapterui-back-button')->text());
        $html .= '</div>';
        
        $html .= '</div>'; // Close main results div
        
        $out->addHTML($html);
    }

    /**
     * Get CSS class for message type
     * @param string $type
     * @return string
     */
    private function getMessageCssClass($type) {
        switch ($type) {
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
     * @return object|null
     */
    private function adaptProtocols($elnUrlOrPath, $method = 'url') {
        if ($method === 'url') {
            // Handle URL-based processing (original logic)
            $parsedUrl = parse_url($elnUrlOrPath);
            
            if (!isset($parsedUrl['host'])) {
                $this->addMessage('error', 'elnsmwadapterui-error-invalid-url');
                return null;
            }

            switch ($parsedUrl['host']) {
                case 'elab.tu-clausthal.de':
                    return $this->processELabFTWUrl($parsedUrl);
                default:
                    $this->addMessage('error', 'elnsmwadapterui-error-unsupported-eln', [$parsedUrl['host']]);
                    return null;
            }
        } else {
            // Handle file-based processing
            return $this->processUploadedFile($elnUrlOrPath, $method);
        }
    }

    /**
     * Process uploaded file
     * @param string $filePath
     * @param string $method
     * @return object|null
     */
    private function processUploadedFile($filePath, $method) {
        if (!file_exists($filePath)) {
            $this->addMessage('error', 'elnsmwadapterui-error-file-not-found');
            return null;
        }

        // Use method name directly as plugin name
        // For file uploads, pass just the filename, not the full path
        $fileName = basename($filePath);
        return $this->callAdapterService($method, $fileName);
    }

    /**
     * Process eLabFTW URL and extract experiment ID
     * @param array $parsedUrl
     * @return object|null
     */
    private function processELabFTWUrl($parsedUrl) {
        if (!isset($parsedUrl['query'])) {
            $this->addMessage('error', 'elnsmwadapterui-error-missing-query');
            return null;
        }

        parse_str($parsedUrl['query'], $query);
        
        if (!isset($query['id'])) {
            $this->addMessage('error', 'elnsmwadapterui-error-missing-id');
            return null;
        }

        return $this->callAdapterService('eLabFTW', $query['id']);
    }

    /**
     * Call the adapter service with proper error handling
     * @param string $eln
     * @param string $id
     * @return object|null
     */
    private function callAdapterService($eln, $id) {
        $serviceUrl = $this->config->get('ELNSMWAdapterUIServiceURL');
        $url = rtrim($serviceUrl, '/') . '/adapt';
        
        $data = [
            'eln' => $eln,
            'id' => $id
        ];

        $this->logger->info('Calling adapter service', [
            'url' => $url,
            'eln' => $eln,
            'id' => $id
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: MediaWiki-ELNSMWAdapterUI/0.2.0'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($curlError)) {
            $this->logger->error('cURL error when calling adapter service', [
                'error' => $curlError,
                'url' => $url
            ]);
            $this->addMessage('error', 'elnsmwadapterui-error-service-offline');
            return null;
        }

        if ($httpCode !== 200) {
            $this->logger->warning('HTTP error from adapter service', [
                'http_code' => $httpCode,
                'response' => $response
            ]);
            $this->addMessage('error', 'elnsmwadapterui-error-service-error', [$httpCode]);
            return null;
        }

        $result = json_decode($response);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Invalid JSON response from adapter service', [
                'response' => $response,
                'json_error' => json_last_error_msg()
            ]);
            $this->addMessage('error', 'elnsmwadapterui-error-invalid-response');
            return null;
        }

        return $result;
    }

    /**
     * Check the status of the adapter service
     * @return array|null Status information or null if unreachable
     */
    private function checkServiceStatus() {
        $serviceUrl = $this->config->get('ELNSMWAdapterUIServiceURL');
        $statusUrl = rtrim($serviceUrl, '/') . '/status';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $statusUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            return json_decode($response, true);
        }
        
        return null;
    }

    /**
     * Add a message to be displayed to the user
     * @param string $type
     * @param string $messageKey
     * @param array $params
     */
    private function addMessage($type, $messageKey, $params = []) {
        $this->messages[] = [
            'type' => $type,
            'message' => $this->msg($messageKey, $params)->text()
        ];
    }

    /**
     * Display accumulated messages
     */
    private function displayMessages($out): void {
        foreach ($this->messages as $message) {
            $cssClass = $this->getMessageCssClass($message['type']);
            $out->addHTML(Html::element('div', [
                'class' => "mw-message-box mw-message-box-{$cssClass}"
            ], $message['message']));
        }
    }

    /**
     * @return string
     */
    public function getGroupName() {
        return 'other';
    }

    public function getDescription() {
        return $this->msg('elnsmwadapterui-special-page-title');
    }
}