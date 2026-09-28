( function () {
	'use strict';

	function updateFileName( input ) {
		const fileName = input.files[ 0 ] ? input.files[ 0 ].name : '';
		const fileNameSpan = document.querySelector( '.elnsmwadapterui-file-upload__name' );
		const selectedFileDiv = document.querySelector( '.elnsmwadapterui-file-upload__selected' );
		const dropZone = document.querySelector( '.elnsmwadapterui-file-upload__drop-zone' );

		if ( fileName ) {
			fileNameSpan.textContent = fileName;
			selectedFileDiv.classList.add( 'elnsmwadapterui-file-upload__selected--visible' );
			dropZone.classList.add( 'elnsmwadapterui-file-upload__drop-zone--hidden' );
		} else {
			selectedFileDiv.classList.remove( 'elnsmwadapterui-file-upload__selected--visible' );
			dropZone.classList.remove( 'elnsmwadapterui-file-upload__drop-zone--hidden' );
		}
	}

	function clearFileName() {
		const fileInput = document.querySelector( '.elnsmwadapterui-file-upload__input' );
		const selectedFileDiv = document.querySelector( '.elnsmwadapterui-file-upload__selected' );
		const dropZone = document.querySelector( '.elnsmwadapterui-file-upload__drop-zone' );

		fileInput.value = '';
		selectedFileDiv.classList.remove( 'elnsmwadapterui-file-upload__selected--visible' );
		dropZone.classList.remove( 'elnsmwadapterui-file-upload__drop-zone--hidden' );
	}

	function initDragAndDrop() {
		const dropZone = document.querySelector( '.elnsmwadapterui-file-upload__drop-zone' );
		const fileInput = document.querySelector( '.elnsmwadapterui-file-upload__input' );

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
				dropZone.classList.add( 'elnsmwadapterui-file-upload__drop-zone--dragover' );
			}, false );
		} );

		[ 'dragleave', 'drop' ].forEach( ( eventName ) => {
			dropZone.addEventListener( eventName, () => {
				dropZone.classList.remove( 'elnsmwadapterui-file-upload__drop-zone--dragover' );
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
		if ( e.target.classList.contains( 'elnsmwadapterui-file-upload__input' ) ) {
			updateFileName( e.target );
		}
	} );

	document.addEventListener( 'click', ( e ) => {
		if ( e.target.closest( '.elnsmwadapterui-file-upload__drop-zone' ) ) {
			document.querySelector( '.elnsmwadapterui-file-upload__input' ).click();
		} else if ( e.target.classList.contains( 'elnsmwadapterui-file-upload__remove' ) ) {
			clearFileName();
		}
	} );

	initDragAndDrop();
}() );
