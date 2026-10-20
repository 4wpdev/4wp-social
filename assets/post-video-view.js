/**
 * Post video cards: tap plays, tap again pauses; only one card plays at a time.
 */
( function () {
	'use strict';

	var cards = document.querySelectorAll( '.wp-block-forwp-social-post-video.is-card' );
	if ( ! cards.length ) {
		return;
	}

	function setState( card, playing ) {
		card.classList.toggle( 'is-playing', playing );
		var button = card.querySelector( '.forwp-post-video__toggle' );
		if ( button ) {
			button.setAttribute( 'aria-pressed', playing ? 'true' : 'false' );
		}
	}

	Array.prototype.forEach.call( cards, function ( card ) {
		var video = card.querySelector( 'video' );
		var button = card.querySelector( '.forwp-post-video__toggle' );
		if ( ! video || ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			if ( video.paused ) {
				Array.prototype.forEach.call( document.querySelectorAll( '.wp-block-forwp-social-post-video.is-card video' ), function ( other ) {
					if ( other !== video && ! other.paused ) {
						other.pause();
					}
				} );
				video.play();
			} else {
				video.pause();
			}
		} );

		video.addEventListener( 'play', function () {
			setState( card, true );
		} );
		video.addEventListener( 'pause', function () {
			setState( card, false );
		} );
	} );
} )();
