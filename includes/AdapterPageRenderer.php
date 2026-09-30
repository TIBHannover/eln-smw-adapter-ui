<?php

declare( strict_types=1 );

namespace ELNSMWAdapterUI;

use Html;
use MessageLocalizer;
use OOUI\ButtonWidget;
use OOUI\ProgressBarWidget;
use ReflectionClass;
use stdClass;

/**
 * Renders the non-form HTML of the special page (service status, processing, results).
 * The input forms themselves are built with HTMLForm in the special page.
 */
class AdapterPageRenderer {

	public function __construct(
		private MessageLocalizer $messages,
		private string $templateDir = __DIR__ . '/../templates'
	) {
	}

	/**
	 * @param array<string, mixed>|null $status Service status, or null if the service is unreachable
	 */
	public function statusBox( ?array $status ): string {
		if ( !$status ) {
			return Html::errorBox( $this->messages->msg( 'elnsmwadapterui-status-disconnected' )->escaped() );
		}

		$lines = [ $this->status( 'connected', (string)( $status['version'] ?? '?' ) ) ];
		if ( isset( $status['smw_connection'] ) ) {
			$lines[] = $this->status( 'smw-connection', (string)$status['smw_connection'] );
		}
		if ( isset( $status['enabled_plugins'] ) && is_array( $status['enabled_plugins'] ) ) {
			$lines[] = $this->status( 'plugins', implode( ', ', $status['enabled_plugins'] ) );
		}

		return Html::successBox( implode( '<br>', $lines ) );
	}

	public function processing(): string {
		return $this->renderTemplate( 'processing', [
			'progressBar' => (string)new ProgressBarWidget( [ 'progress' => false ] ),
			'statusText' => $this->messages->msg( 'elnsmwadapterui-processing-status' )->text(),
		] );
	}

	public function results( stdClass $result, string $wikiUrl, string $backUrl ): string {
		$protocols = [];
		foreach ( $result->smw_pages ?? [] as $pageId => $pageData ) {
			if ( strpos( (string)$pageId, 'P' ) === 0 ) {
				$protocols[] = [
					'url' => $wikiUrl . '/' . urlencode( (string)$pageId ),
					'pageId' => (string)$pageId,
				];
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
			'hasProtocols' => $protocols !== [],
			'protocols' => $protocols,
			'summary' => $this->messages->msg( 'elnsmwadapterui-protocols-count', count( $protocols ) )->text(),
			'noProtocolsText' => $this->messages->msg( 'elnsmwadapterui-warning-no-protocols' )->text(),
			'logTitle' => $this->messages->msg( 'elnsmwadapterui-results-log' )->text(),
			'hasLogMessages' => $logMessages !== [],
			'logMessages' => $logMessages,
			'noLogText' => $this->messages->msg( 'elnsmwadapterui-results-no-log' )->text(),
			'backButton' => $this->button( $backUrl, 'elnsmwadapterui-back-button', [ 'progressive', 'primary' ] ),
		] );
	}

	/**
	 * @param string $text Plain text, will be escaped
	 */
	public function errorBox( string $text ): string {
		return Html::errorBox( htmlspecialchars( $text ) );
	}

	public function backToSelectionButton( string $selectionUrl ): string {
		return $this->button( $selectionUrl, 'elnsmwadapterui-back-to-selection' );
	}

	private function status( string $key, string $value ): string {
		return $this->messages->msg( 'elnsmwadapterui-status-' . $key, $value )->escaped();
	}

	/**
	 * @param string $url
	 * @param string $labelKey
	 * @param string[] $flags OOUI button flags
	 */
	private function button( string $url, string $labelKey, array $flags = [] ): string {
		return (string)new ButtonWidget( [
			'href' => $url,
			'label' => $this->messages->msg( $labelKey )->text(),
			'flags' => $flags,
		] );
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
