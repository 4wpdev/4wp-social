/* 4WP Social admin: module cards and copy buttons. */
( function () {
	'use strict';

	function syncCard( card, input ) {
		card.classList.toggle( 'is-enabled', !! input.checked );
		card.setAttribute( 'aria-pressed', input.checked ? 'true' : 'false' );
		var on = card.querySelector( '.forwp-social-badge--on' );
		var off = card.querySelector( '.forwp-social-badge--off' );
		if ( on ) {
			on.hidden = ! input.checked;
		}
		if ( off ) {
			off.hidden = !! input.checked;
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var copy = event.target.closest( '.forwp-social-copy-btn' );
		if ( copy ) {
			event.preventDefault();
			var field = document.getElementById( copy.getAttribute( 'data-target' ) );
			if ( ! field ) {
				return;
			}
			field.select();
			var done = function () {
				var label = copy.textContent;
				copy.classList.add( 'is-copied' );
				copy.textContent = copy.getAttribute( 'data-copied' ) || label;
				setTimeout( function () {
					copy.classList.remove( 'is-copied' );
					copy.textContent = label;
				}, 1600 );
			};
			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( field.value ).then( done );
			} else {
				document.execCommand( 'copy' );
				done();
			}
			return;
		}

		var card = event.target.closest( '.forwp-social-module-card' );
		if ( ! card ) {
			return;
		}
		var input = card.querySelector( '.forwp-social-module-card__check' );
		if ( ! input ) {
			return;
		}
		if ( event.target !== input ) {
			input.checked = ! input.checked;
		}
		syncCard( card, input );
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Enter' !== event.key && ' ' !== event.key ) {
			return;
		}
		var card = event.target.closest( '.forwp-social-module-card' );
		if ( card ) {
			event.preventDefault();
			card.click();
		}
	} );
} )();
