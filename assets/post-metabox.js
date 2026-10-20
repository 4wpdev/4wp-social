/* global wp, jQuery, forwpSocialMetabox */
( function ( $ ) {
	'use strict';

	var i18n = ( window.forwpSocialMetabox || {} ).i18n || {};

	$( document ).on( 'click', '.forwp-social-pick', function ( e ) {
		e.preventDefault();
		var $row = $( this ).closest( '.forwp-social-file' );
		var library = $( this ).data( 'library' );
		var frame = wp.media( {
			title: i18n.choose,
			multiple: false,
			library: library ? { type: library } : {},
		} );
		frame.on( 'select', function () {
			var file = frame.state().get( 'selection' ).first().toJSON();
			$row.find( 'input[type=hidden]' ).val( file.id );
			$row.find( '.forwp-social-file-name' ).text( file.filename || file.title );
		} );
		frame.open();
	} );

	$( document ).on( 'click', '.forwp-social-clear', function ( e ) {
		e.preventDefault();
		var $row = $( this ).closest( '.forwp-social-file' );
		$row.find( 'input[type=hidden]' ).val( '0' );
		$row.find( '.forwp-social-file-name' ).text( '—' );
	} );

	// Link to a video already on YouTube: search by title, pick, or type the ID.
	function postId( $el ) {
		return parseInt( $el.closest( '.forwp-social-box' ).data( 'post-id' ), 10 );
	}

	function linkVideo( $wrap, video ) {
		var $msg = $wrap.find( '.forwp-social-link-msg' ).text( '…' );
		wp.apiFetch( {
			path: '/forwp-social/v1/link',
			method: 'POST',
			data: { post_id: postId( $wrap ), video: video },
		} ).then( function ( row ) {
			$wrap.html( $( '<a target="_blank" rel="noopener"/>' ).attr( 'href', row.url ).text( i18n.linked + ' ' + row.id ) );
			$wrap.closest( 'li' ).find( 'input[type=checkbox]' ).prop( 'checked', false );
		} ).catch( function ( err ) {
			$msg.text( ( err && err.message ) || i18n.failed );
		} );
	}

	$( document ).on( 'click', '.forwp-social-find', function ( e ) {
		e.preventDefault();
		var $wrap = $( this ).closest( '.forwp-social-link' );
		var $list = $wrap.find( '.forwp-social-found' ).text( '…' );
		wp.apiFetch( { path: '/forwp-social/v1/link/search?post_id=' + postId( $wrap ) } ).then( function ( rows ) {
			$list.empty();
			if ( ! rows.length ) {
				$list.text( i18n.notFound );
				return;
			}
			rows.forEach( function ( row ) {
				$( '<button type="button" class="button-link forwp-social-pick-video"/>' )
					.attr( 'data-id', row.id )
					.text( row.title + ' · ' + ( row.published_at || '' ).slice( 0, 10 ) )
					.appendTo( $( '<div/>' ).appendTo( $list ) );
			} );
		} ).catch( function ( err ) {
			$list.text( ( err && err.message ) || i18n.failed );
		} );
	} );

	$( document ).on( 'click', '.forwp-social-pick-video', function ( e ) {
		e.preventDefault();
		linkVideo( $( this ).closest( '.forwp-social-link' ), $( this ).data( 'id' ) );
	} );

	$( document ).on( 'click', '.forwp-social-link-save', function ( e ) {
		e.preventDefault();
		var $wrap = $( this ).closest( '.forwp-social-link' );
		linkVideo( $wrap, $wrap.find( '.forwp-social-link-input' ).val() );
	} );

	$( document ).on( 'click', '.forwp-social-unlink', function ( e ) {
		e.preventDefault();
		var $wrap = $( this ).closest( '.forwp-social-link' );
		wp.apiFetch( {
			path: '/forwp-social/v1/link?post_id=' + postId( $wrap ),
			method: 'DELETE',
		} ).then( function () {
			$wrap.text( i18n.unlinked );
		} ).catch( function ( err ) {
			$wrap.find( '.forwp-social-link-msg' ).text( ( err && err.message ) || i18n.failed );
		} );
	} );

	$( document ).on( 'change', 'input[name=forwp_social_source]', function () {
		var $box = $( this ).closest( '.forwp-social-box' );
		$box.find( '.forwp-social-manual' ).prop( 'hidden', 'package' === this.value );
	} );

	$( document ).on( 'click', '.forwp-social-publish', function ( e ) {
		e.preventDefault();
		var $box = $( this ).closest( '.forwp-social-box' );
		var $out = $box.find( '.forwp-social-result' ).empty();
		var platforms = $box.find( 'input[name="forwp_social_platforms[]"]:checked' ).map( function () {
			return this.value;
		} ).get();

		if ( ! platforms.length ) {
			$out.text( i18n.pick );
			return;
		}

		var options = {};
		$box.find( '[data-field]' ).each( function () {
			options[ $( this ).data( 'field' ) ] = $( this ).val();
		} );

		var $button = $( this ).prop( 'disabled', true ).text( i18n.working );

		wp.apiFetch( {
			path: '/forwp-social/v1/jobs',
			method: 'POST',
			data: {
				post_id: parseInt( $box.data( 'post-id' ), 10 ),
				platforms: platforms,
				source: $box.find( 'input[name=forwp_social_source]:checked' ).val() || 'post',
				options: options,
				force: $box.find( '.forwp-social-force' ).is( ':checked' ),
			},
		} ).then( function ( report ) {
			var lines = Object.keys( report ).map( function ( key ) {
				var row = report[ key ];
				return key + ': ' + ( row.job_id ? i18n.queued : i18n.skipped + ' (' + row.skipped + ')' );
			} );
			$out.html( lines.map( function ( line ) {
				return $( '<div/>' ).text( line ).prop( 'outerHTML' );
			} ).join( '' ) );
			Object.keys( report ).forEach( function ( key ) {
				if ( report[ key ].job_id ) {
					$box.find( '.forwp-social-status[data-platform="' + key + '"]' ).attr( 'data-status', 'queued' ).text( 'queued' );
				}
			} );
			syncButton( $box );
			watchJobs( $box );
		} ).catch( function ( err ) {
			$out.text( ( err && err.message ) || i18n.failed );
			$button.prop( 'disabled', false ).text( $button.data( 'label' ) );
		} );
	} );

	// Button mirrors the jobs: busy while queued/processing, done after publish, retry after a failure.
	function syncButton( $box ) {
		var $button = $box.find( '.forwp-social-publish' );
		if ( ! $button.data( 'label' ) ) {
			$button.data( 'label', $button.text() );
		}
		var states = $box.find( '.forwp-social-status[data-status]' ).map( function () {
			return $( this ).attr( 'data-status' );
		} ).get().filter( Boolean );
		var force = $box.find( '.forwp-social-force' ).is( ':checked' );
		var busy = states.indexOf( 'queued' ) !== -1 || states.indexOf( 'processing' ) !== -1;

		$button.removeClass( 'is-done is-failed' );
		if ( busy ) {
			$button.prop( 'disabled', true ).text( i18n.queuedBtn );
		} else if ( states.indexOf( 'failed' ) !== -1 ) {
			$button.prop( 'disabled', false ).addClass( 'is-failed' ).text( i18n.retryBtn );
		} else if ( allCheckedPublished( $box ) && ! force ) {
			$button.prop( 'disabled', true ).addClass( 'is-done' ).text( i18n.doneBtn );
		} else {
			$button.prop( 'disabled', false ).text( $button.data( 'label' ) );
		}
		return busy;
	}

	function allCheckedPublished( $box ) {
		var $checked = $box.find( 'input[name="forwp_social_platforms[]"]:checked' );
		if ( ! $checked.length ) {
			return false;
		}
		return $checked.toArray().every( function ( input ) {
			return 'published' === $box.find( '.forwp-social-status[data-platform="' + input.value + '"]' ).attr( 'data-status' );
		} );
	}

	function watchJobs( $box ) {
		if ( $box.data( 'watching' ) ) {
			return;
		}
		$box.data( 'watching', true );
		var tick = function () {
			wp.apiFetch( { path: '/forwp-social/v1/jobs?post_id=' + parseInt( $box.data( 'post-id' ), 10 ) } ).then( function ( rows ) {
				var seen = {};
				( rows || [] ).forEach( function ( row ) {
					if ( seen[ row.platform ] ) {
						return; // Newest first: keep the latest job per platform.
					}
					seen[ row.platform ] = true;
					var $status = $box.find( '.forwp-social-status[data-platform="' + row.platform + '"]' );
					if ( ! $status.length ) {
						return;
					}
					$status.attr( 'data-status', row.status );
					if ( 'published' === row.status && row.remote_url ) {
						$status.empty().append( $( '<a target="_blank" rel="noopener"/>' ).attr( 'href', row.remote_url ).text( i18n.posted ) );
					} else {
						$status.text( row.status ).attr( 'title', row.message || '' );
					}
				} );
				if ( syncButton( $box ) ) {
					setTimeout( tick, 15000 );
				} else {
					$box.data( 'watching', false );
				}
			} ).catch( function () {
				setTimeout( tick, 30000 );
			} );
		};
		setTimeout( tick, 15000 );
	}

	$( document ).on( 'change', '.forwp-social-force, input[name="forwp_social_platforms[]"]', function () {
		syncButton( $( this ).closest( '.forwp-social-box' ) );
	} );

	$( function () {
		$( '.forwp-social-box' ).each( function () {
			if ( syncButton( $( this ) ) ) {
				watchJobs( $( this ) );
			}
		} );
	} );
} )( jQuery );
