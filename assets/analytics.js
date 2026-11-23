/* 4WP Social analytics panel: period switch, chart tooltip, top items of a day, table sort. */
( function () {
	'use strict';

	var cfg = window.forwpSocialAnalytics || { i18n: {} };
	var t = function ( key, fallback ) {
		return ( cfg.i18n && cfg.i18n[ key ] ) || fallback;
	};

	// Period: presets submit at once, "custom" shows the dates.
	document.querySelectorAll( '.forwp-social-an-range' ).forEach( function ( form ) {
		var select = form.querySelector( 'select[name="range"]' );
		var custom = form.querySelector( '.forwp-social-an-range__custom' );
		if ( ! select ) {
			return;
		}
		select.addEventListener( 'change', function () {
			if ( 'custom' === select.value ) {
				custom.hidden = false;
				return;
			}
			custom.hidden = true;
			custom.querySelectorAll( 'input' ).forEach( function ( input ) {
				input.disabled = true;
			} );
			form.submit();
		} );
	} );

	document.querySelectorAll( '.forwp-social-an-panel' ).forEach( function ( panel ) {
		var source = panel.getAttribute( 'data-source' );
		var wrap = panel.querySelector( '.forwp-social-an__chart-wrap' );
		var tip = panel.querySelector( '.forwp-social-an__tip' );
		var top = panel.querySelector( '.forwp-social-an__top' );

		if ( wrap && tip ) {
			wrap.addEventListener( 'mousemove', function ( e ) {
				var hit = e.target.closest( '.forwp-social-an__hit' );
				if ( ! hit ) {
					tip.hidden = true;
					return;
				}
				tip.textContent = hit.getAttribute( 'data-tip' );
				tip.hidden = false;
				var box = wrap.getBoundingClientRect();
				var x = e.clientX - box.left + 12;
				if ( x + tip.offsetWidth > box.width ) {
					x = e.clientX - box.left - tip.offsetWidth - 12;
				}
				tip.style.left = Math.max( 0, x ) + 'px';
				tip.style.top = Math.max( 0, e.clientY - box.top - tip.offsetHeight - 8 ) + 'px';
				panel.querySelectorAll( '.forwp-social-an__bar.is-hover' ).forEach( function ( bar ) {
					bar.classList.remove( 'is-hover' );
				} );
				var bar = panel.querySelector( '.forwp-social-an__bar[data-day="' + hit.getAttribute( 'data-day' ) + '"]' );
				if ( bar ) {
					bar.classList.add( 'is-hover' );
				}
			} );
			wrap.addEventListener( 'mouseleave', function () {
				tip.hidden = true;
				panel.querySelectorAll( '.forwp-social-an__bar.is-hover' ).forEach( function ( bar ) {
					bar.classList.remove( 'is-hover' );
				} );
			} );

			// Click a day: its top 5 items.
			wrap.addEventListener( 'click', function ( e ) {
				var hit = e.target.closest( '.forwp-social-an__hit' );
				if ( ! hit || ! top || ! window.wp || ! window.wp.apiFetch ) {
					return;
				}
				var day = hit.getAttribute( 'data-day' );
				panel.querySelectorAll( '.forwp-social-an__bar.is-active' ).forEach( function ( bar ) {
					bar.classList.remove( 'is-active' );
				} );
				var bar = panel.querySelector( '.forwp-social-an__bar[data-day="' + day + '"]' );
				if ( bar ) {
					bar.classList.add( 'is-active' );
				}
				top.hidden = false;
				top.textContent = '';
				renderDay( top, hit );
				var list = document.createElement( 'div' );
				list.className = 'forwp-social-an__top-list';
				list.textContent = t( 'loading', 'Loading…' );
				top.appendChild( list );
				window.wp
					.apiFetch( {
						path: '/forwp-social/v1/analytics/items?source=' + encodeURIComponent( source ) + '&start=' + day + '&end=' + day + '&limit=5',
					} )
					.then( function ( res ) {
						renderTop( list, day, ( res && res.items ) || [] );
					} )
					.catch( function ( err ) {
						list.textContent = ( err && err.message ) || t( 'error', 'Request failed.' );
					} );
			} );
		}

		var table = panel.querySelector( 'table[data-sortable]' );
		if ( table ) {
			sortable( table );
		}
	} );

	// Stats of the chosen day, as tiles.
	function renderDay( box, hit ) {
		var stats = [];
		try {
			stats = JSON.parse( hit.getAttribute( 'data-stats' ) || '[]' );
		} catch ( e ) {
			stats = [];
		}
		var title = document.createElement( 'h4' );
		title.textContent = hit.getAttribute( 'data-date' ) || hit.getAttribute( 'data-day' );
		box.appendChild( title );
		var tiles = document.createElement( 'div' );
		tiles.className = 'forwp-social-an__tiles forwp-social-an__tiles--day';
		stats.forEach( function ( pair ) {
			var tile = document.createElement( 'div' );
			tile.className = 'forwp-social-an__tile';
			var value = document.createElement( 'strong' );
			value.textContent = pair[ 1 ];
			var label = document.createElement( 'span' );
			label.textContent = pair[ 0 ];
			tile.appendChild( value );
			tile.appendChild( label );
			tiles.appendChild( tile );
		} );
		box.appendChild( tiles );
	}

	function renderTop( box, day, items ) {
		box.textContent = '';
		var title = document.createElement( 'h4' );
		title.textContent = t( 'top', 'Top 5' ) + ' · ' + day;
		box.appendChild( title );
		if ( ! items.length ) {
			var none = document.createElement( 'p' );
			none.className = 'forwp-social-muted';
			none.textContent = t( 'empty', 'No views this day.' );
			box.appendChild( none );
			return;
		}
		var max = Math.max.apply(
			null,
			items.map( function ( item ) {
				return Number( item.views ) || 0;
			} ).concat( [ 1 ] )
		);
		var list = document.createElement( 'ol' );
		items.forEach( function ( item ) {
			var li = document.createElement( 'li' );
			var a = document.createElement( 'a' );
			a.href = item.url || '#';
			a.target = '_blank';
			a.rel = 'noopener noreferrer';
			a.textContent = item.title || item.id;
			var meter = document.createElement( 'span' );
			meter.className = 'forwp-social-an__meter';
			meter.style.width = Math.round( ( 100 * ( Number( item.views ) || 0 ) ) / max ) + '%';
			var num = document.createElement( 'strong' );
			num.textContent = ( Number( item.views ) || 0 ).toLocaleString();
			li.appendChild( a );
			li.appendChild( meter );
			li.appendChild( num );
			list.appendChild( li );
		} );
		box.appendChild( list );
	}

	function sortable( table ) {
		var heads = table.querySelectorAll( 'thead th[data-sort]' );
		var body = table.querySelector( 'tbody.forwp-social-an__rows' );
		heads.forEach( function ( th, index ) {
			var button = th.querySelector( 'button' );
			if ( ! button || ! body ) {
				return;
			}
			button.addEventListener( 'click', function () {
				var desc = 'descending' !== th.getAttribute( 'aria-sort' );
				var numeric = 'num' === th.getAttribute( 'data-sort' );
				// Text columns start ascending.
				if ( ! numeric && ! th.hasAttribute( 'aria-sort' ) ) {
					desc = false;
				}
				heads.forEach( function ( other ) {
					other.removeAttribute( 'aria-sort' );
					other.classList.remove( 'is-sorted' );
				} );
				th.setAttribute( 'aria-sort', desc ? 'descending' : 'ascending' );
				th.classList.add( 'is-sorted' );

				var rows = Array.prototype.slice.call( body.rows );
				rows.sort( function ( a, b ) {
					var va = a.cells[ index ].getAttribute( 'data-v' ) || '';
					var vb = b.cells[ index ].getAttribute( 'data-v' ) || '';
					var cmp = numeric ? ( parseFloat( va ) || 0 ) - ( parseFloat( vb ) || 0 ) : va.localeCompare( vb );
					return desc ? -cmp : cmp;
				} );
				rows.forEach( function ( row ) {
					body.appendChild( row );
				} );
			} );
		} );
	}
} )();
