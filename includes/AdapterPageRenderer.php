<?php

declare( strict_types=1 );

namespace ELNSMWAdapterUI;

use Html;
use MessageLocalizer;
use ReflectionClass;
use stdClass;

/**
 * Renders the HTML of the individual special page states (forms, processing, results).
 */
class AdapterPageRenderer {

	public function __construct(
		private MessageLocalizer $messages,
		private string $templateDir = __DIR__ . '/../templates'
	) {
	}

	/**
	 * @param array<string, mixed>|null $status Service status, or null if the service is unreachable
	 * @param string $actionUrl
	 */
	public function selectionForm( ?array $status, string $actionUrl ): string {
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

		return $this->renderTemplate( 'selection-form', [
			'connected' => (bool)$status,
			'version' => (string)( $status['version'] ?? 'unknown' ),
			'hasSmwConnection' => $status && isset( $status['smw_connection'] ),
			'smwConnection' => (string)( $status['smw_connection'] ?? '' ),
			'hasEnabledPlugins' => $hasEnabledPlugins,
			'enabledPlugins' => $hasEnabledPlugins ? implode( ', ', $status['enabled_plugins'] ) : '',
			'legend' => $this->messages->msg( 'elnsmwadapterui-form-select-legend' )->text(),
			'help' => $this->messages->msg( 'elnsmwadapterui-form-eln-type-help' )->text(),
			'action' => $actionUrl,
			'label' => $this->messages->msg( 'elnsmwadapterui-form-eln-type-label' )->text(),
			'options' => $options,
			'submitLabel' => $this->messages->msg( 'elnsmwadapterui-form-continue' )->text(),
		] );
	}

	public function urlForm( string $actionUrl, string $token, string $elnUrl ): string {
		return $this->renderTemplate( 'url-form', [
			'legend' => $this->messages->msg( 'elnsmwadapterui-form-legend' )->text(),
			'help' => $this->messages->msg( 'elnsmwadapterui-form-url-help' )->text(),
			'action' => $actionUrl,
			'token' => $token,
			'label' => $this->messages->msg( 'elnsmwadapterui-form-url-label' )->text(),
			'placeholder' => 'https://elab.tu-clausthal.de/experiments.php?mode=view&id=0000',
			'elnUrl' => $elnUrl,
			'submitLabel' => $this->messages->msg( 'elnsmwadapterui-form-submit' )->text(),
		] );
	}

	/**
	 * @param string $method
	 * @param string $actionUrl
	 * @param string $token
	 * @param array<int, array{name: string, label: string, type: string, required?: bool, options?: string[]}> $fields
	 *   Field metadata as reported by the adapter service
	 */
	public function uploadForm( string $method, string $actionUrl, string $token, array $fields ): string {
		return $this->renderTemplate( 'upload-form', [
			'legend' => $this->messages->msg( 'elnsmwadapterui-form-upload-legend' )->text(),
			'method' => $method,
			'help' => $this->messages->msg( 'elnsmwadapterui-form-file-help' )->text(),
			'action' => $actionUrl,
			'token' => $token,
			'label' => $this->messages->msg( 'elnsmwadapterui-form-file-label' )->text(),
			'dropText' => $this->messages->msg( 'elnsmwadapterui-file-drop-text' )->text(),
			'fields' => array_map( [ $this, 'getDynamicFieldData' ], $fields ),
			'submitLabel' => $this->messages->msg( 'elnsmwadapterui-form-upload' )->text(),
		] );
	}

	public function processing(): string {
		return $this->renderTemplate( 'processing', [] );
	}

	public function results( stdClass $result, string $wikiUrl, string $backUrl ): string {
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

		return $this->renderTemplate( 'results', [
			'protocolsTitle' => $this->messages->msg( 'elnsmwadapterui-results-protocols' )->text(),
			'hasPages' => $hasPages,
			'hasProtocols' => $protocols !== [],
			'protocols' => $protocols,
			'summary' => $this->messages->msg( 'elnsmwadapterui-protocols-count', count( $protocols ) )->text(),
			'noProtocolsText' => $this->messages->msg( 'elnsmwadapterui-warning-no-protocols' )->text(),
			'logTitle' => $this->messages->msg( 'elnsmwadapterui-results-log' )->text(),
			'hasLogMessages' => $logMessages !== [],
			'logMessages' => $logMessages,
			'backUrl' => $backUrl,
			'backText' => $this->messages->msg( 'elnsmwadapterui-back-button' )->text(),
		] );
	}

	/**
	 * @param string $text Plain text, will be escaped
	 */
	public function errorBox( string $text ): string {
		return Html::element( 'div', [ 'class' => 'errorbox' ], $text );
	}

	public function backToSelectionButton( string $selectionUrl ): string {
		return Html::rawElement(
			'div',
			[ 'class' => 'elnsmwadapterui-back-selection' ],
			Html::element( 'a', [
				'href' => $selectionUrl,
				'class' => 'mw-ui-button'
			], $this->messages->msg( 'elnsmwadapterui-back-to-selection' )->text() )
		);
	}

	/**
	 * @param array<int, array{type: string, message: string}> $messages Already localized messages
	 */
	public function messageBoxes( array $messages ): string {
		$html = '';
		foreach ( $messages as $message ) {
			$cssClass = $this->getMessageCssClass( $message['type'] );
			$html .= Html::element( 'div', [
				'class' => "mw-message-box mw-message-box-{$cssClass}"
			], $message['message'] );
		}
		return $html;
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

	private function getMessageCssClass( string $type ): string {
		return match ( $type ) {
			'error' => 'error',
			'warning' => 'warning',
			default => 'notice',
		};
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
		$parser = ( new ReflectionClass( $class ) )->newInstance( $this->templateDir );
		return $parser->processTemplate( $name, $data );
	}
}
