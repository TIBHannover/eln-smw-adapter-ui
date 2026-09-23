( function () {
	'use strict';

	function updateFileName( input ) {
		const fileName = input.files[ 0 ] ? input.files[ 0 ].name : '';
		const fileNameSpan = document.querySelector( '.elnsmwadapterui-file-name' );
		const selectedFileDiv = document.querySelector( '.elnsmwadapterui-selected-file' );
		const dropZone = document.querySelector( '.elnsmwadapterui-file-drop-zone' );

		if ( fileName ) {
			fileNameSpan.textContent = fileName;
			selectedFileDiv.style.display = 'block';
			dropZone.style.display = 'none';
		} else {
			selectedFileDiv.style.display = 'none';
			dropZone.style.display = 'block';
		}
	}

	function clearFileName() {
		const fileInput = document.querySelector( '.elnsmwadapterui-file-input' );
		const selectedFileDiv = document.querySelector( '.elnsmwadapterui-selected-file' );
		const dropZone = document.querySelector( '.elnsmwadapterui-file-drop-zone' );

		fileInput.value = '';
		selectedFileDiv.style.display = 'none';
		dropZone.style.display = 'block';
	}

	function initDragAndDrop() {
		const dropZone = document.querySelector( '.elnsmwadapterui-file-drop-zone' );
		const fileInput = document.querySelector( '.elnsmwadapterui-file-input' );

		if ( !dropZone || !fileInput ) {
			return;
		}

		[ 'dragenter', 'dragover', 'dragleave', 'drop' ].forEach( ( eventName ) => {
			dropZone.addEventListener( eventName, ( e ) => {
				e.preventDefault();
				e.stopPropagation();
			}, false );
		} );

		[ 'dragenter', 'dragover' ].forEach( ( eventName ) => {
			dropZone.addEventListener( eventName, () => {
				dropZone.style.backgroundColor = '#f0f8ff';
				dropZone.style.borderColor = '#4a90e2';
			}, false );
		} );

		[ 'dragleave', 'drop' ].forEach( ( eventName ) => {
			dropZone.addEventListener( eventName, () => {
				dropZone.style.backgroundColor = '';
				dropZone.style.borderColor = '';
			}, false );
		} );

		dropZone.addEventListener( 'drop', ( e ) => {
			const files = e.dataTransfer.files;

			if ( files.length > 0 ) {
				fileInput.files = files;
				updateFileName( fileInput );
			}
		}, false );
	}

	document.addEventListener( 'change', ( e ) => {
		if ( e.target.classList.contains( 'elnsmwadapterui-file-input' ) ) {
			updateFileName( e.target );
		}
	} );

	document.addEventListener( 'click', ( e ) => {
		if ( e.target.closest( '.elnsmwadapterui-file-drop-zone' ) ) {
			document.querySelector( '.elnsmwadapterui-file-input' ).click();
		} else if ( e.target.classList.contains( 'elnsmwadapterui-remove-file' ) ) {
			clearFileName();
		}
	} );

	initDragAndDrop();
}() );
