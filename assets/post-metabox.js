/* global wp, jQuery, forwpSocialMetabox */
( function ( $ ) {
	'use strict';

	var i18n = ( window.forwpSocialMetabox || {} ).i18n || {};
	var tt = ( window.forwpSocialMetabox || {} ).tiktok || {};
	var TIKTOK = 'tiktok.com';

	// TikTok panel (Content Sharing Guidelines): shown while TikTok is ticked.
	function tiktokChecked( $box ) {
		return $box.find( 'input[name="forwp_social_platforms[]"][value="' + TIKTOK + '"]' ).is( ':checked' );
	}

	function tiktokField( $box, name ) {
		return $box.find( '.forwp-social-tiktok [data-tiktok="' + name + '"]' );
	}

	function tiktokSource( $box ) {
		return $box.data( 'source' ) || $box.find( 'input[name=forwp_social_source]:checked' ).val() || 'post';
	}

	function tiktokLoad( $box ) {
		var $panel = $box.find( '.forwp-social-tiktok' );
		var $msg = $panel.find( '.forwp-social-tiktok__msg' ).text( tt.loading );
		$panel.data( 'creator', null );
		wp.apiFetch( {
			path: '/forwp-social/v1/tiktok/creator?post_id=' + postId( $panel ) +
				'&source=' + encodeURIComponent( tiktokSource( $box ) ) +
				'&video_id=' + ( parseInt( $box.find( '[data-field="video_id"]' ).val(), 10 ) || 0 ),
		} ).then( function ( info ) {
			$panel.data( 'creator', info );
			$msg.text( '' );
			var name = info.nickname || info.username;
			$panel.find( '.forwp-social-tiktok__name' ).text( info.username ? name + ' (@' + info.username + ')' : name );
			if ( info.avatar ) {
				$panel.find( '.forwp-social-tiktok__avatar' ).attr( 'src', info.avatar ).prop( 'hidden', false );
			}

			var $select = tiktokField( $box, 'privacy_level' );
			$select.find( 'option:not([value=""])' ).remove();
			( info.privacy_options || [] ).forEach( function ( level ) {
				$select.append( $( '<option/>' ).val( level ).text( ( tt.privacy || {} )[ level ] || level ) );
			} );
			$select.val( '' );

			[ 'comment', 'duet', 'stitch' ].forEach( function ( key ) {
				var off = !! info[ key + '_disabled' ];
				tiktokField( $box, 'allow_' + key ).prop( 'checked', false ).prop( 'disabled', off )
					.closest( 'label' ).toggleClass( 'is-disabled', off ).attr( 'title', off ? tt.disabledHint : '' );
			} );

			var $title = tiktokField( $box, 'title' );
			if ( ! $title.val() ) {
				$title.val( info.caption || '' );
			}

			var $video = $panel.find( '.forwp-social-tiktok__preview' );
			if ( info.video_url ) {
				$video.attr( 'src', info.video_url ).prop( 'hidden', false );
			} else {
				$video.prop( 'hidden', true ).removeAttr( 'src' );
				$msg.text( tt.noVideo );
			}
			if ( info.max_duration && info.duration > info.max_duration ) {
				$msg.text( String( tt.tooLong ).replace( '%d', info.max_duration ) );
			}
			tiktokSync( $box );
		} ).catch( function ( err ) {
			$msg.text( ( err && err.message ) || i18n.failed );
		} );
	}

	function tiktokChoice( $box ) {
		var choice = { consent: 1 };
		[ 'title', 'privacy_level' ].forEach( function ( name ) {
			choice[ name ] = tiktokField( $box, name ).val();
		} );
		[ 'allow_comment', 'allow_duet', 'allow_stitch', 'commercial', 'brand_organic', 'brand_content' ].forEach( function ( name ) {
			choice[ name ] = tiktokField( $box, name ).is( ':checked' ) ? 1 : 0;
		} );
		return choice;
	}

	function tiktokProblem( $box ) {
		var info = $box.find( '.forwp-social-tiktok' ).data( 'creator' );
		var choice = tiktokChoice( $box );
		if ( ! info ) {
			return tt.loading;
		}
		if ( info.max_duration && info.duration > info.max_duration ) {
			return String( tt.tooLong ).replace( '%d', info.max_duration );
		}
		if ( ! choice.privacy_level ) {
			return tt.needPrivacy;
		}
		if ( choice.commercial && ! choice.brand_organic && ! choice.brand_content ) {
			return tt.needBrand;
		}
		if ( choice.commercial && choice.brand_content && 'SELF_ONLY' === choice.privacy_level ) {
			return tt.privateBrand;
		}
		return '';
	}

	function tiktokLink( text, url ) {
		return $( '<a/>' ).attr( { href: url, target: '_blank', rel: 'noopener' } ).text( text ).prop( 'outerHTML' );
	}

	// Disclosure, branded-content privacy rule, label note, consent text, publish button.
	function tiktokSync( $box ) {
		var $panel = $box.find( '.forwp-social-tiktok' );
		var choice = tiktokChoice( $box );
		var branded = !! ( choice.commercial && choice.brand_content );

		$panel.find( '.forwp-social-tiktok__brand' ).prop( 'hidden', ! choice.commercial );

		var $private = tiktokField( $box, 'privacy_level' ).find( 'option[value="SELF_ONLY"]' );
		$private.prop( 'disabled', branded ).attr( 'title', branded ? tt.privateBrand : '' );
		if ( branded && 'SELF_ONLY' === choice.privacy_level ) {
			tiktokField( $box, 'privacy_level' ).val( '' );
		}

		var label = '';
		if ( branded ) {
			label = tt.labelPaid;
		} else if ( choice.commercial && choice.brand_organic ) {
			label = tt.labelOrganic;
		}
		$panel.find( '.forwp-social-tiktok__label' ).text( label );

		var music = tiktokLink( tt.music, tt.musicUrl );
		var consent = branded ?
			String( tt.consentBrand ).replace( '%1$s', tiktokLink( tt.bcPolicy, tt.bcPolicyUrl ) ).replace( '%2$s', music ) :
			String( tt.consent ).replace( '%s', music );
		$panel.find( '.forwp-social-tiktok__consent' ).html( consent );

		if ( tiktokChecked( $box ) ) {
			$box.find( '.forwp-social-publish' ).prop( 'disabled', !! tiktokProblem( $box ) );
		}
	}

	$( document ).on( 'change', 'input[name="forwp_social_platforms[]"][value="' + TIKTOK + '"], input[name=forwp_social_source]', function () {
		var $box = $( this ).closest( '.forwp-social-box' );
		var on = tiktokChecked( $box );
		$box.find( '.forwp-social-tiktok' ).prop( 'hidden', ! on );
		if ( on ) {
			tiktokLoad( $box );
		} else {
			$box.find( '.forwp-social-publish' ).prop( 'disabled', false );
		}
	} );

	$( document ).on( 'change input', '.forwp-social-tiktok [data-tiktok]', function () {
		tiktokSync( $( this ).closest( '.forwp-social-box' ) );
	} );

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
			path: '/forwp-social/v1/link?post_id=' + postId( $wrap ) + '&platform=' + encodeURIComponent( String( $wrap.data( 'platform' ) ) ),
			method: 'DELETE',
		} ).then( function () {
			$wrap.text( i18n.unlinked );
		} ).catch( function ( err ) {
			$wrap.find( '.forwp-social-link-msg' ).text( ( err && err.message ) || i18n.failed );
		} );
	} );

	// Own date per platform: sent with "Publish to selected"; a queued job moves at once.
	function schedule( $box ) {
		var out = {};
		$box.find( '.forwp-social-when' ).each( function () {
			if ( this.value ) {
				out[ String( $( this ).data( 'platform' ) ) ] = this.value;
			}
		} );
		return out;
	}

	function dateMsg( $el, text ) {
		$el.closest( '.forwp-social-box, .forwp-social-manual-box' ).find( '.forwp-social-result, .forwp-social-manual-msg' ).first().text( text );
	}

	$( document ).on( 'change', '.forwp-social-when', function () {
		var $input = $( this );
		var jobId = parseInt( $input.attr( 'data-job-id' ), 10 );
		if ( ! jobId || ! this.value ) {
			return;
		}
		wp.apiFetch( {
			path: '/forwp-social/v1/jobs/' + jobId + '/schedule',
			method: 'POST',
			data: { run_at: this.value },
		} ).then( function () {
			dateMsg( $input, i18n.dateSaved );
		} ).catch( function ( err ) {
			dateMsg( $input, ( err && err.message ) || i18n.failed );
		} );
	} );

	$( document ).on( 'change', '.forwp-social-plan-when', function () {
		var $input = $( this );
		var $box = $input.closest( '.forwp-social-manual-box' );
		wp.apiFetch( {
			path: '/forwp-social/v1/plan',
			method: 'POST',
			data: {
				post_id: parseInt( $box.data( 'post-id' ), 10 ),
				platform: String( $input.closest( '[data-platform]' ).data( 'platform' ) ),
				run_at: this.value,
			},
		} ).then( function () {
			dateMsg( $input, i18n.dateSaved );
		} ).catch( function ( err ) {
			dateMsg( $input, ( err && err.message ) || i18n.failed );
		} );
	} );

	// Already on another platform: the post link marks it published (and drops a queued job).
	$( document ).on( 'click', '.forwp-social-link-url-save', function ( e ) {
		e.preventDefault();
		var $wrap = $( this ).closest( '.forwp-social-link' );
		var $msg = $wrap.find( '.forwp-social-link-msg' ).text( '…' );
		wp.apiFetch( {
			path: '/forwp-social/v1/crosspost/published',
			method: 'POST',
			data: {
				post_id: postId( $wrap ),
				platform: String( $wrap.data( 'platform' ) ),
				url: $wrap.find( '.forwp-social-link-url' ).val(),
				via: 'manual',
			},
		} ).then( function ( res ) {
			var $li = $wrap.closest( 'li' );
			$li.find( 'input[type=checkbox]' ).prop( 'checked', false );
			$li.find( '.forwp-social-when' ).remove();
			$li.find( '.forwp-social-status' ).attr( 'data-status', 'published' ).empty()
				.append( $( '<a target="_blank" rel="noopener"/>' ).attr( 'href', res.url ).text( i18n.markedDone ) );
			$wrap.html( $( '<a target="_blank" rel="noopener"/>' ).attr( 'href', res.url ).text( i18n.linked + ' ' + res.url ) );
		} ).catch( function ( err ) {
			$msg.text( ( err && err.message ) || i18n.failed );
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

		if ( tiktokChecked( $box ) ) {
			var problem = tiktokProblem( $box );
			if ( problem ) {
				$out.text( problem );
				return;
			}
			options.tiktok = tiktokChoice( $box );
		}

		var $button = $( this ).prop( 'disabled', true ).text( i18n.working );

		wp.apiFetch( {
			path: '/forwp-social/v1/jobs',
			method: 'POST',
			data: {
				post_id: parseInt( $box.data( 'post-id' ), 10 ),
				platforms: platforms,
				source: $box.data( 'source' ) || $box.find( 'input[name=forwp_social_source]:checked' ).val() || 'post',
				options: options,
				force: $box.find( '.forwp-social-force' ).is( ':checked' ),
				schedule: schedule( $box ),
			},
		} ).then( function ( report ) {
			var reasons = i18n.reasons || {};
			var lines = Object.keys( report ).map( function ( key ) {
				var row = report[ key ];
				return key + ': ' + ( row.job_id ? i18n.queued : i18n.skipped + ': ' + ( reasons[ row.skipped ] || row.skipped ) );
			} );
			$out.html( lines.map( function ( line ) {
				return $( '<div/>' ).text( line ).prop( 'outerHTML' );
			} ).join( '' ) );
			Object.keys( report ).forEach( function ( key ) {
				var $status = $box.find( '.forwp-social-status[data-platform="' + key + '"]' );
				if ( report[ key ].job_id ) {
					$status.attr( 'data-status', 'queued' ).text( 'queued' ).attr( 'title', '' );
				} else if ( 'already_posted' !== report[ key ].skipped && 'published' !== $status.attr( 'data-status' ) ) {
					// Next to the platform too, not only in the result lines.
					$status.attr( 'data-status', 'skipped' ).text( i18n.skippedShort ).attr( 'title', reasons[ report[ key ].skipped ] || report[ key ].skipped );
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

	$( document ).on( 'click', '.forwp-social-mark', function ( e ) {
		e.preventDefault();
		var $btn = $( this );
		var $row = $btn.closest( '[data-platform]' );
		var $box = $btn.closest( '.forwp-social-manual-box' );
		var $msg = $box.find( '.forwp-social-manual-msg' );
		$btn.prop( 'disabled', true );
		wp.apiFetch( {
			path: '/forwp-social/v1/crosspost/published',
			method: 'POST',
			data: {
				post_id: parseInt( $box.data( 'post-id' ), 10 ),
				platform: String( $row.data( 'platform' ) ),
				url: $row.find( '.forwp-social-mark-url' ).val(),
				via: 'manual',
			},
		} ).then( function ( res ) {
			var $link = $( '<a target="_blank" rel="noopener"></a>' ).attr( 'href', res.url ).text( i18n.markedDone );
			$row.find( '.forwp-social-status' ).attr( 'data-status', 'published' ).empty().append( $link );
			$row.find( '.forwp-social-mark-form' ).remove();
			$msg.text( res.sync_error || '' );
		} ).catch( function ( err ) {
			$btn.prop( 'disabled', false );
			$msg.text( ( err && err.message ) || i18n.failed );
		} );
	} );

	$( document ).on( 'click', '.forwp-social-notify', function ( e ) {
		e.preventDefault();
		var $btn = $( this );
		var $box = $btn.closest( '.forwp-social-manual-box' );
		var $msg = $box.find( '.forwp-social-manual-msg' );
		$btn.prop( 'disabled', true );
		wp.apiFetch( {
			path: '/forwp-social/v1/crosspost/notify',
			method: 'POST',
			data: { post_id: parseInt( $box.data( 'post-id' ), 10 ) },
		} ).then( function () {
			$btn.prop( 'disabled', false );
			$msg.text( i18n.notifySent );
		} ).catch( function ( err ) {
			$btn.prop( 'disabled', false );
			$msg.text( ( err && err.message ) || i18n.failed );
		} );
	} );

	$( function () {
		$( '.forwp-social-box' ).each( function () {
			if ( syncButton( $( this ) ) ) {
				watchJobs( $( this ) );
			}
		} );
	} );
} )( jQuery );
