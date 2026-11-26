/**
 * Post video cards: tap plays, tap again pauses; only one card plays at a time.
 */
( function () {
	'use strict';

	// Lightbox cards: muted preview on hover (pointer devices only); the click opens the lightbox.
	var canHover = window.matchMedia && window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches;
	Array.prototype.forEach.call( document.querySelectorAll( '[data-forwp-post-video-preview]' ), function ( figure ) {
		var preview = figure.querySelector( 'video' );
		if ( ! preview || ! canHover ) {
			return;
		}
		figure.addEventListener( 'mouseenter', function () {
			preview.muted = true;
			var played = preview.play();
			if ( played && played.catch ) {
				played.catch( function () {} );
			}
			figure.classList.add( 'is-previewing' );
		} );
		figure.addEventListener( 'mouseleave', function () {
			preview.pause();
			figure.classList.remove( 'is-previewing' );
		} );
		// Opening the lightbox stops the preview.
		figure.addEventListener( 'click', function () {
			preview.pause();
		} );
	} );

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
