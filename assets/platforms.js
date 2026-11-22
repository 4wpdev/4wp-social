/* global jQuery, wp, forwpSocialPlatforms */
( function ( $ ) {
	'use strict';

	var cfg = window.forwpSocialPlatforms;
	var i18n = cfg.i18n;
	var data = cfg.data;
	var $list = $( '#forwp-social-platform-list' );
	var $new = $( '#forwp-social-platform-new' );
	var $msg = $( '#forwp-social-platform-msg' );

	function request( path, method, body ) {
		$msg.text( '' );
		return wp.apiFetch( { path: '/forwp-social/v1/' + path, method: method, data: body } )
			.then( function ( res ) {
				data = res;
				render();
				return res;
			} )
			.catch( function ( err ) {
				$msg.text( ( err && err.message ) || i18n.failed );
				throw err;
			} );
	}

	function connector( slug ) {
		return data.connectors.filter( function ( c ) {
			return c.slug === slug;
		} )[ 0 ] || null;
	}

	function owner( slug, exceptKey ) {
		return data.platforms.filter( function ( p ) {
			return p.connector === slug && p.key !== exceptKey;
		} )[ 0 ] || null;
	}

	function sourceSelect( row ) {
		var $select = $( '<select name="connector"></select>' );
		$select.append( $( '<option value=""></option>' ).text( i18n.manual ) );
		data.connectors.forEach( function ( c ) {
			var taken = owner( c.slug, row.key );
			var text = c.label + ( taken ? ' (' + i18n.boundTo + ' ' + taken.label + ')' : '' );
			$select.append( $( '<option></option>' ).val( c.slug ).text( text ).prop( 'disabled', !! taken ) );
		} );
		$select.val( row.connector || '' );
		return $select;
	}

	function iconField( row ) {
		var $input = $( '<input type="hidden" name="icon" />' ).val( row.icon || '' );
		var $preview = $( '<span class="forwp-social-platform-icon-preview"></span>' );
		var $remove = $( '<button type="button" class="button-link"></button>' ).text( i18n.useDefault );

		function show() {
			var url = $input.val();
			if ( url ) {
				$preview.empty().append( $( '<img class="forwp-social-icon" width="16" height="16" alt="" />' ).attr( 'src', url ) );
			} else {
				$preview.html( row.icon_html || '' );
			}
			$remove.toggle( !! url );
		}

		var $choose = $( '<button type="button" class="button button-small"></button>' ).text( i18n.chooseIcon ).on( 'click', function () {
			var frame = wp.media( { title: i18n.chooseIcon, multiple: false, library: { type: 'image' } } );
			frame.on( 'select', function () {
				$input.val( frame.state().get( 'selection' ).first().toJSON().url );
				show();
			} );
			frame.open();
		} );

		$remove.on( 'click', function () {
			$input.val( '' );
			show();
		} );
		show();

		return $( '<div class="forwp-social-platform-field"></div>' ).append(
			$( '<span></span>' ).text( i18n.icon ),
			$( '<div class="forwp-social-platform-icon-row"></div>' ).append( $preview, $choose, $remove, $input )
		);
	}

	function postType( key ) {
		return ( data.post_types || [] ).filter( function ( t ) {
			return t.key === key;
		} )[ 0 ] || null;
	}

	function typesField( row ) {
		var chosen = row.types || [];
		var $box = $( '<div class="forwp-social-platform-types"></div>' );
		( data.post_types || [] ).forEach( function ( t ) {
			$box.append(
				$( '<label></label>' ).append(
					$( '<input type="checkbox" name="types" />' ).val( t.key ).prop( 'checked', chosen.indexOf( t.key ) > -1 ),
					' ',
					$( '<span class="dashicons" aria-hidden="true"></span>' ).addClass( t.icon ),
					' ',
					document.createTextNode( t.label ),
					' ',
					$( '<code></code>' ).text( t.key )
				)
			);
		} );
		return $( '<div class="forwp-social-platform-field forwp-social-platform-field--wide"></div>' ).append( $( '<span></span>' ).text( i18n.types ), $box );
	}

	function typeIcons( row ) {
		var $wrap = $( '<span class="forwp-social-platform__types"></span>' );
		var types = row.types || [];
		if ( ! types.length ) {
			return $wrap.append( $( '<span class="forwp-social-muted"></span>' ).text( i18n.noTypes ) );
		}
		types.forEach( function ( key ) {
			var t = postType( key );
			$wrap.append( $( '<span class="dashicons" role="img"></span>' ).addClass( t ? t.icon : 'dashicons-editor-help' ).attr( { title: t ? t.label + ' (' + key + ')' : key, 'aria-label': t ? t.label : key } ) );
		} );
		return $wrap;
	}

	function field( label, $input ) {
		return $( '<label class="forwp-social-platform-field"></label>' ).append( $( '<span></span>' ).text( label ), $input );
	}

	function form( row, isNew, onCancel ) {
		var $form = $( '<form class="forwp-social-platform-form"></form>' );
		var $grid = $( '<div class="forwp-social-platform-grid"></div>' );
		$grid.append( field( i18n.name, $( '<input type="text" name="label" />' ).val( row.label || '' ) ) );
		$grid.append( field( i18n.key, $( '<input type="text" required placeholder="example.com" />' ).attr( 'name', isNew ? 'key' : 'new_key' ).val( row.key || '' ) ) );
		$grid.append( field( i18n.source, sourceSelect( row ) ) );
		$grid.append( field( i18n.openUrl, $( '<input type="url" name="url" />' ).val( row.url || '' ) ) );
		$grid.append( field( i18n.format, $( '<input type="text" name="format" />' ).val( row.format || '' ) ) );
		$grid.append( iconField( row ) );
		$grid.append( typesField( row ) );

		$form.append(
			$grid,
			$( '<p class="forwp-social-platform-buttons"></p>' ).append(
				$( '<button type="submit" class="button button-primary"></button>' ).text( i18n.save ),
				' ',
				$( '<button type="button" class="button"></button>' ).text( i18n.cancel ).on( 'click', onCancel )
			)
		);

		$form.on( 'submit', function ( e ) {
			e.preventDefault();
			var body = { types: [] };
			$form.serializeArray().forEach( function ( item ) {
				if ( 'types' === item.name ) {
					body.types.push( item.value );
					return;
				}
				body[ item.name ] = item.value;
			} );
			if ( isNew ) {
				body.enabled = true;
				request( 'platforms', 'POST', body ).then( function () {
					$new.empty();
				} ).catch( function () {} );
			} else {
				request( 'platforms/' + row.key, 'POST', body ).catch( function () {} );
			}
		} );

		return $form;
	}

	function item( row ) {
		var $li = $( '<li class="forwp-social-platform"></li>' ).attr( 'data-key', row.key ).toggleClass( 'is-off', ! row.enabled );
		var $head = $( '<div class="forwp-social-platform__row"></div>' );
		var bound = row.connector ? connector( row.connector ) : null;

		var $source = $( '<span class="forwp-social-badge"></span>' )
			.addClass( row.connector ? 'forwp-social-badge--info' : '' )
			.text( row.connector ? ( bound ? bound.label : row.connector ) : i18n.manual );

		var $state = $( '<span class="forwp-social-platform__state"></span>' );
		if ( bound && ! bound.ready ) {
			$state.append(
				$( '<span class="forwp-social-muted"></span>' ).text( i18n.notConnected + ' · ' ),
				$( '<a></a>' ).attr( 'href', data.accounts_url + '#forwp-social-' + row.connector.replace( /\./g, '-' ) ).text( i18n.connect )
			);
		}

		var $toggle = $( '<label class="forwp-social-platform__toggle"></label>' ).append(
			$( '<input type="checkbox" />' ).prop( 'checked', !! row.enabled ).on( 'change', function () {
				request( 'platforms/' + row.key, 'POST', { enabled: this.checked } ).catch( function () {
					render();
				} );
			} ),
			' ',
			document.createTextNode( i18n.on )
		);

		var $edit = $( '<button type="button" class="button-link"></button>' ).text( i18n.edit ).on( 'click', function () {
			var $open = $li.children( '.forwp-social-platform-form' );
			if ( $open.length ) {
				$open.remove();
				return;
			}
			$li.append( form( row, false, function () {
				$li.children( '.forwp-social-platform-form' ).remove();
			} ) );
		} );

		var $delete = $( '<button type="button" class="button-link button-link-delete"></button>' ).text( i18n.delete ).on( 'click', function () {
			// eslint-disable-next-line no-alert
			if ( window.confirm( i18n.confirmDelete ) ) {
				request( 'platforms/' + row.key, 'DELETE' ).catch( function () {} );
			}
		} );

		$head.append(
			$( '<span class="forwp-social-platform__drag dashicons dashicons-menu" aria-hidden="true"></span>' ).attr( 'title', i18n.drag ),
			$( '<span class="forwp-social-platform__icon"></span>' ).html( row.icon_html || '' ),
			$( '<span class="forwp-social-platform__name"></span>' ).append(
				$( '<strong></strong>' ).text( row.label ),
				' ',
				$( '<code></code>' ).text( row.key )
			),
			typeIcons( row ),
			$source,
			$state,
			$toggle,
			$edit,
			$delete
		);

		return $li.append( $head );
	}

	function render() {
		$list.empty();
		if ( ! data.platforms.length ) {
			$list.append( $( '<li class="forwp-social-empty"></li>' ).text( i18n.empty ) );
			return;
		}
		data.platforms.forEach( function ( row ) {
			$list.append( item( row ) );
		} );
	}

	$list.sortable( {
		handle: '.forwp-social-platform__drag',
		items: '> .forwp-social-platform',
		axis: 'y',
		update: function () {
			var keys = $list.children( '.forwp-social-platform' ).map( function () {
				return $( this ).attr( 'data-key' );
			} ).get();
			request( 'platforms-order', 'POST', { keys: keys } ).catch( function () {
				render();
			} );
		},
	} );

	$( '#forwp-social-platform-add' ).on( 'click', function () {
		if ( $new.children().length ) {
			return;
		}
		$new.append( form( { key: '', connector: '' }, true, function () {
			$new.empty();
		} ) );
		$new.find( 'input[name=label]' ).trigger( 'focus' );
	} );

	render();
} )( jQuery );
