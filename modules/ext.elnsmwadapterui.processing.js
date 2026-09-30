( function () {
	'use strict';

	const jobStatusUrl = mw.config.get( 'elnsmwadapteruiJobStatusUrl' );
	const resultsBaseUrl = mw.config.get( 'elnsmwadapteruiResultsUrl' );
	const pollInterval = 1000; // Poll every second
	const maxAttempts = 180; // Max 180 seconds
	let attempts = 0;

	function showError( text ) {
		const box = document.createElement( 'div' );
		box.className = 'mw-message-box mw-message-box-error';
		box.textContent = text;
		document.querySelector( '.elnsmwadapterui-processing__status' ).hidden = true;
		document.querySelector( '.elnsmwadapterui-processing__error' ).appendChild( box );
	}

	function pollJobStatus() {
		attempts++;

		if ( attempts > maxAttempts ) {
			showError( mw.msg( 'elnsmwadapterui-processing-timeout' ) );
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
					showError( data.error ?
						mw.msg( 'elnsmwadapterui-processing-failed', data.error ) :
						mw.msg( 'elnsmwadapterui-processing-failed-unknown' ) );
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
