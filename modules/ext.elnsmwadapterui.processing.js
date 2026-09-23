( function () {
	'use strict';

	const jobStatusUrl = mw.config.get( 'elnsmwadapteruiJobStatusUrl' );
	const resultsBaseUrl = mw.config.get( 'elnsmwadapteruiResultsUrl' );
	const pollInterval = 1000; // Poll every second
	const maxAttempts = 180; // Max 180 seconds
	let attempts = 0;

	function pollJobStatus() {
		attempts++;

		if ( attempts > maxAttempts ) {
			document.querySelector( '.elnsmwadapterui-processing-status' ).style.display = 'none';
			document.getElementById( 'processing-error' ).textContent =
				'Request timed out after 3 minutes. The import may still be processing. Please check back later.';
			document.getElementById( 'processing-error' ).style.display = 'block';
			return;
		}

		fetch( jobStatusUrl )
			.then( ( response ) => {
				if ( !response.ok ) {
					throw new Error( 'Network response was not ok' );
				}
				return response.json();
			} )
			.then( ( data ) => {
				if ( data.status === 'completed' ) {
					// Success - redirect to results page with job_id
					window.location.href = resultsBaseUrl.replace( '__JOBID__', data.id );
				} else if ( data.status === 'failed' ) {
					// Failed - show error
					document.querySelector( '.elnsmwadapterui-processing-status' ).style.display = 'none';
					document.getElementById( 'processing-error' ).textContent =
						'Import failed: ' + ( data.error || 'Unknown error' );
					document.getElementById( 'processing-error' ).style.display = 'block';
				} else {
					// Still processing - poll again
					setTimeout( pollJobStatus, pollInterval );
				}
			} )
			.catch( ( error ) => {
				// Network error - retry
				mw.log.error( 'Polling error:', error );
				setTimeout( pollJobStatus, pollInterval );
			} );
	}

	pollJobStatus();
}() );
