/* global wp */
/* Calendar page: the day popup, grouped by platform, with day navigation. */
( function () {
	'use strict';

	var dialog = document.getElementById( 'forwp-social-day' );
	if ( ! dialog ) {
		return;
	}

	var cfg = JSON.parse( dialog.getAttribute( 'data-config' ) );
	var months = {};
	var current = '';
	var title = dialog.querySelector( '.forwp-social-day__title' );
	var stats = dialog.querySelector( '.forwp-social-day__stats' );
	var body = dialog.querySelector( '.forwp-social-day__body' );
	months[ cfg.month ] = Promise.resolve( { events: cfg.events, site: cfg.site } );

	function el( tag, className, text ) {
		var node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( undefined !== text ) {
			node.textContent = text;
		}
		return node;
	}

	function parse( date ) {
		var p = date.split( '-' );
		return new Date( +p[ 0 ], +p[ 1 ] - 1, +p[ 2 ] );
	}

	function format( d ) {
		var pad = function ( n ) {
			return ( n < 10 ? '0' : '' ) + n;
		};
		return d.getFullYear() + '-' + pad( d.getMonth() + 1 ) + '-' + pad( d.getDate() );
	}

	function eventsOf( month ) {
		if ( ! months[ month ] ) {
			months[ month ] = wp.apiFetch( { path: '/forwp-social/v1/calendar?month=' + month } ).then( function ( res ) {
				return { events: res.events, site: res.site };
			} ).catch( function ( err ) {
				delete months[ month ];
				throw err;
			} );
		}
		return months[ month ];
	}

	function renderStats( events ) {
		var counts = {};
		events.forEach( function ( event ) {
			var group = cfg.groups[ event.status ];
			counts[ group ] = ( counts[ group ] || 0 ) + 1;
		} );
		stats.textContent = '';
		Object.keys( cfg.stats ).forEach( function ( group ) {
			if ( ! counts[ group ] ) {
				return;
			}
			var item = el( 'span', 'forwp-social-day__stat' );
			item.innerHTML = cfg.stats[ group ].marker; // Built on the server from constants.
			item.appendChild( document.createTextNode( cfg.stats[ group ].label + ': ' ) );
			item.appendChild( el( 'strong', '', String( counts[ group ] ) ) );
			stats.appendChild( item );
		} );
		stats.hidden = ! stats.childNodes.length;
	}

	function statusCell( marker, label ) {
		var cell = el( 'span', 'forwp-social-day__status' );
		cell.innerHTML = marker; // Built on the server from constants.
		cell.appendChild( el( 'span', '', label ) );
		return cell;
	}

	function openLink( url, label ) {
		var cell = el( 'span', 'forwp-social-day__open' );
		if ( /^https?:\/\//i.test( url || '' ) ) {
			var link = el( 'a', 'dashicons dashicons-external' );
			link.href = url;
			link.target = '_blank';
			link.rel = 'noopener';
			link.title = label;
			link.setAttribute( 'aria-label', label );
			cell.appendChild( link );
		}
		return cell;
	}

	function renderItem( event ) {
		var status = cfg.statuses[ event.status ] || { label: event.status, marker: '' };
		var item = el( 'li', 'forwp-social-day__item' );
		item.appendChild( el( 'span', 'forwp-social-day__time', event.time ) );

		var post = el( 'a', 'forwp-social-day__post', event.title );
		post.href = event.edit_url;
		item.appendChild( post );

		item.appendChild( statusCell( status.marker, status.label ) );
		item.appendChild( openLink( event.url, cfg.i18n.open ) );
		return item;
	}

	function renderSiteItem( item ) {
		var row = el( 'li', 'forwp-social-day__item' );
		row.appendChild( el( 'span', 'forwp-social-day__time', item.time ) );

		var post = el( 'a', 'forwp-social-day__post', item.title );
		post.href = item.edit_url;
		row.appendChild( post );

		var action = cfg.actions[ item.action ];
		row.appendChild( statusCell( action.marker, action.label ) );
		row.appendChild( openLink( item.url, cfg.i18n.view ) );
		return row;
	}

	// Server order: Page, Post, Video, Case, then the other types.
	function renderSite( items ) {
		var list = null;
		var type = '';
		items.forEach( function ( item ) {
			if ( item.post_type !== type ) {
				type = item.post_type;
				var head = el( 'h4', 'forwp-social-day__platform' );
				head.appendChild( el( 'span', 'dashicons ' + item.icon ) );
				head.appendChild( el( 'span', '', item.type_label ) );
				body.appendChild( head );
				list = el( 'ul', 'forwp-social-day__list' );
				body.appendChild( list );
			}
			list.appendChild( renderSiteItem( item ) );
		} );
	}

	function renderBody( events, site ) {
		body.textContent = '';
		if ( ! events.length && ! site.length ) {
			body.appendChild( el( 'p', 'forwp-social-day__empty', cfg.i18n.empty ) );
			return;
		}

		if ( site.length ) {
			body.appendChild( el( 'h3', 'forwp-social-day__section', cfg.i18n.siteTitle ) );
			renderSite( site );
		}
		if ( events.length ) {
			body.appendChild( el( 'h3', 'forwp-social-day__section', cfg.i18n.shared ) );
		}

		var groups = {};
		events.forEach( function ( event ) {
			( groups[ event.platform ] = groups[ event.platform ] || [] ).push( event );
		} );

		// Platforms in the order of the Platforms page; unknown keys after them.
		Object.keys( cfg.platforms ).concat( Object.keys( groups ).filter( function ( key ) {
			return ! cfg.platforms[ key ];
		} ) ).forEach( function ( key ) {
			if ( ! groups[ key ] ) {
				return;
			}
			var platform = cfg.platforms[ key ] || { label: key, icon: '' };
			var head = el( 'h4', 'forwp-social-day__platform' );
			head.innerHTML = platform.icon; // Built and escaped on the server.
			head.appendChild( el( 'span', '', platform.label ) );
			body.appendChild( head );

			var list = el( 'ul', 'forwp-social-day__list' );
			groups[ key ].sort( function ( a, b ) {
				return a.time < b.time ? -1 : ( a.time > b.time ? 1 : 0 );
			} ).forEach( function ( event ) {
				list.appendChild( renderItem( event ) );
			} );
			body.appendChild( list );
		} );
	}

	function show( date ) {
		current = date;
		title.textContent = parse( date ).toLocaleDateString( document.documentElement.lang || undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' } );
		stats.textContent = '';
		stats.hidden = true;
		body.textContent = '';
		body.appendChild( el( 'p', 'forwp-social-day__empty', cfg.i18n.loading ) );
		if ( ! dialog.open ) {
			dialog.showModal();
			dialog.focus();
		}

		eventsOf( date.slice( 0, 7 ) ).then( function ( data ) {
			if ( current !== date ) {
				return;
			}
			var onDay = function ( item ) {
				return item.date === date;
			};
			var day = data.events.filter( onDay );
			renderStats( day );
			renderBody( day, data.site.filter( onDay ) );
		} ).catch( function () {
			if ( current === date ) {
				body.textContent = '';
				body.appendChild( el( 'p', 'forwp-social-day__empty', cfg.i18n.failed ) );
			}
		} );
	}

	function step( days ) {
		var d = parse( current );
		d.setDate( d.getDate() + days );
		show( format( d ) );
	}

	dialog.querySelector( '.forwp-social-day__prev' ).addEventListener( 'click', function () {
		step( -1 );
	} );
	dialog.querySelector( '.forwp-social-day__next' ).addEventListener( 'click', function () {
		step( 1 );
	} );
	dialog.querySelector( '.forwp-social-day__close' ).addEventListener( 'click', function () {
		dialog.close();
	} );

	dialog.addEventListener( 'click', function ( event ) {
		// A click on the backdrop lands on the dialog itself.
		if ( event.target === dialog ) {
			dialog.close();
		}
	} );
	dialog.addEventListener( 'keydown', function ( event ) {
		if ( 'ArrowLeft' === event.key || 'ArrowRight' === event.key ) {
			event.preventDefault();
			step( 'ArrowLeft' === event.key ? -1 : 1 );
		}
	} );

	document.querySelector( '.forwp-social-month' ).addEventListener( 'click', function ( event ) {
		var zoom = event.target.closest( '.forwp-social-month__zoom' );
		if ( zoom ) {
			show( zoom.getAttribute( 'data-date' ) );
		}
	} );
} )();
