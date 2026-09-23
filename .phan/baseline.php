<?php
/**
 * This is an automatically generated baseline for Phan issues.
 * When Phan is invoked with --load-baseline=path/to/baseline.php,
 * The pre-existing issues listed in this file won't be emitted.
 *
 * This file can be updated by invoking Phan with --save-baseline=path/to/baseline.php
 * (can be combined with --load-baseline)
 */
return [
	// # Issue statistics:
	// PhanPluginDuplicateAdjacentStatement : 10+ occurrences
	// MediaWikiNoEmptyIfDefined : 8 occurrences
	// SecurityCheck-DoubleEscaped : 4 occurrences
	// PhanNoopSwitchCases : 1 occurrence
	// SecurityCheck-XSS : 1 occurrence

	'file_suppressions' => [
		'includes/SpecialELNSMWAdapterUI.php' => [
			'MediaWikiNoEmptyIfDefined' => ['\\ELNSMWAdapterUI\\SpecialELNSMWAdapterUI::callAdapterService', '\\ELNSMWAdapterUI\\SpecialELNSMWAdapterUI::displayProcessingPage', '\\ELNSMWAdapterUI\\SpecialELNSMWAdapterUI::displayResultsPage', '\\ELNSMWAdapterUI\\SpecialELNSMWAdapterUI::displaySelectionForm', '\\ELNSMWAdapterUI\\SpecialELNSMWAdapterUI::execute', '\\ELNSMWAdapterUI\\SpecialELNSMWAdapterUI::isValidUrl'],
			'PhanNoopSwitchCases' => ['\\ELNSMWAdapterUI\\SpecialELNSMWAdapterUI::displayMethodForm'],
			'PhanPluginDuplicateAdjacentStatement' => ['\\ELNSMWAdapterUI\\SpecialELNSMWAdapterUI::displayFileUploadForm', '\\ELNSMWAdapterUI\\SpecialELNSMWAdapterUI::displayProcessingPage', '\\ELNSMWAdapterUI\\SpecialELNSMWAdapterUI::displayResults', '\\ELNSMWAdapterUI\\SpecialELNSMWAdapterUI::displaySelectionForm', '\\ELNSMWAdapterUI\\SpecialELNSMWAdapterUI::displayUrlForm'],
			'SecurityCheck-DoubleEscaped' => ['\\ELNSMWAdapterUI\\SpecialELNSMWAdapterUI::displaySelectionForm', '\\ELNSMWAdapterUI\\SpecialELNSMWAdapterUI::renderDynamicField'],
			'SecurityCheck-XSS' => ['\\ELNSMWAdapterUI\\SpecialELNSMWAdapterUI::displayProcessingPage']
		],
	],
	// 'directory_suppressions' => ['src/directory_name' => ['PhanIssueName1', 'PhanIssueName2']] can be manually added if needed.
	// (directory_suppressions will currently be ignored by subsequent calls to --save-baseline, but may be preserved in future Phan releases)
];
