/**
 * WP Swiper slider edit screen: every color field (Pickr) and the
 * gradient's editor. A part of admin.js, run by it (see there).
 */
( function ( $ ) {
	'use strict';

	var parts = ( window.wpSwiperEditor = window.wpSwiperEditor || { parts: {} } ).parts;

	/*
	 * The color fields (Pickr) and the gradient's editor.
	 */
	parts.colors = function ( shared ) {
		// From the parts before this one (shared).
		var i18n = shared.i18n;
		var $modal = shared.$modal;
		var showFontDevice = shared.showFontDevice;
		var syncFontInherits = shared.syncFontInherits;
		// For the parts after it.
		shared.openPickr = openPickr;
		shared.closeColorPickers = closeColorPickers;
		shared.showColor = showColor;
		shared.readColor = readColor;
		shared.syncColorPicks = syncColorPicks;

		/*
		 * Every color field (color_pick() in class-wp-swiper-admin.php): a
		 * swatch that opens Pickr (the color, hue, opacity where the field has
		 * it, the palette: the theme's colors, black, white and greys; HEX /
		 * RGB / HSL; Cancel: back to the color it had when opened; Default).
		 * Each field (.wp-swiper-color-pick) keeps what is chosen as its data:
		 * hex and alpha (0–100). A setting's field writes it into its hidden
		 * input too: "#rrggbb", or "rgba(r,g,b,a)" below full opacity.
		 *
		 * A slide's own text color, for its caption and its description
		 * (.wp-swiper-modal__color): Default (the Caption page's color; the
		 * Description page's or the page's text) or Custom, which shows the
		 * field; its Default goes back to that page's color.
		 */
		var COLOR_PROPS = [ 'caption_color', 'description_color' ];
		shared.COLOR_PROPS = COLOR_PROPS;
		var COLOR_PICK = '.wp-swiper-color-pick';
		// The eyedropper's icon: WP Banner's pipette, in the button's color
		// (the viewBox cropped to the drawing).
		var EYEDROPPER = '<svg viewBox="314 314 380 380" aria-hidden="true" focusable="false" fill="currentColor">' +
			'<rect x="480.78" y="406.64" width="177.31" height="63.87" rx="31.93" ry="31.93" transform="translate(476.9 -274.19) rotate(45)"/>' +
			'<path fill="none" stroke="currentColor" stroke-linecap="round" stroke-miterlimit="10" stroke-width="20" d="M621.5,373.23c7.35-7.35,19.25-7.35,26.6,0"/>' +
			'<path d="M653.93,354.08c13.82,13.82,13.82,36.32,0,50.15l-194.31,194.31c-8.98,8.98-22.19,12.47-34.48,9.11l-11.21-3.06-57.7,57.7c-2.72,2.72-7.13,2.72-9.84,0-3.08-3.08-3.08-8.1,0-11.17l57.04-57.04-3.06-11.21c-3.37-12.29.13-25.5,9.11-34.48l194.31-194.31c13.82-13.82,36.32-13.82,50.15,0M668.07,339.94h0c-21.66-21.66-56.77-21.66-78.43,0l-194.31,194.31c-14.6,14.6-19.35,35.31-14.26,53.9l-48.82,48.82c-10.9,10.9-10.9,28.56,0,39.46h0c10.53,10.53,27.6,10.53,38.13,0l49.48-49.48c18.59,5.08,39.3.34,53.9-14.26l194.31-194.31c21.66-21.66,21.66-56.77,0-78.43h0Z"/>' +
			'</svg>';
		var colorI18n = $.extend( {}, i18n.colorPicker || {} );
		// The palette's colors and names (TinyMCE's map: "RRGGBB", name, …).
		var colorSwatches = [];
		var colorNames = [];
		( $.isArray( i18n.textColors ) ? i18n.textColors : [] ).forEach( function ( item, i, map ) {
			if ( i % 2 === 0 && /^[0-9a-f]{6}$/i.test( item ) ) {
				colorSwatches.push( '#' + item.toLowerCase() );
				colorNames.push( String( map[ i + 1 ] || '' ) );
			}
		} );

		function colorControl( prop ) {
			return $( '.wp-swiper-modal__color[data-prop="' + prop + '"]' );
		}

		// Where Custom starts: the Caption / Description page's color.
		function defaultColor( prop ) {
			var value = String( $( '[name="wp_swiper_settings[' + prop + ']"]' ).val() || '' ).toLowerCase();
			return /^#[0-9a-f]{6}$/.test( value ) ? value : ( prop === 'caption_color' ? '#ffffff' : '#333333' );
		}

		// "#rrggbb" / "rgba(r,g,b,a)" as { hex, alpha }; null for anything else.
		function parseColor( value ) {
			value = String( value || '' ).replace( /\s+/g, '' ).toLowerCase();
			var m = /^rgba\((\d{1,3}),(\d{1,3}),(\d{1,3}),(\d*\.?\d+)\)$/.exec( value );
			if ( m ) {
				return {
					hex: '#' + [ m[ 1 ], m[ 2 ], m[ 3 ] ].map( function ( channel ) {
						return ( '0' + Math.min( 255, parseInt( channel, 10 ) ).toString( 16 ) ).slice( -2 );
					} ).join( '' ),
					alpha: Math.round( Math.min( 1, parseFloat( m[ 4 ] ) ) * 100 )
				};
			}
			if ( /^#[0-9a-f]{3}$/.test( value ) ) {
				value = '#' + value[ 1 ] + value[ 1 ] + value[ 2 ] + value[ 2 ] + value[ 3 ] + value[ 3 ];
			}
			return /^#[0-9a-f]{6}$/.test( value ) ? { hex: value, alpha: 100 } : null;
		}

		function colorValue( hex, alpha ) {
			if ( alpha >= 100 ) {
				return hex;
			}
			return 'rgba(' + [ 1, 3, 5 ].map( function ( i ) {
				return parseInt( hex.substr( i, 2 ), 16 );
			} ).join( ',' ) + ',' + ( alpha / 100 ) + ')';
		}

		// What Default goes back to.
		function pickDefault( $pick ) {
			var $row = $pick.closest( '.wp-swiper-modal__color' );
			if ( $row.length ) {
				return { hex: defaultColor( $row.attr( 'data-prop' ) ), alpha: 100 };
			}
			return parseColor( $pick.attr( 'data-default' ) ) || { hex: '#000000', alpha: 100 };
		}

		// The swatch says what is chosen (over its checkerboard, admin.css).
		function paintColor( $pick ) {
			$pick.find( '.wp-swiper-color-pick__chip' ).css( 'box-shadow', 'inset 0 0 0 60px ' + colorValue( $pick.data( 'hex' ), $pick.data( 'alpha' ) ) );
		}

		// Into Pickr (no event of its own), in the format it shows.
		function pickrShow( $pick ) {
			var pickr = $pick.data( 'pickr' );
			if ( ! pickr || ! $pick.data( 'pickrReady' ) ) {
				return;
			}
			var shown = pickr.getColorRepresentation() || 'HEXA';
			// Its format buttons say "change" too: not a new color.
			$pick.data( 'pickrSync', true );
			pickr.setColor( colorValue( $pick.data( 'hex' ), $pick.data( 'alpha' ) ), true );
			pickr.setColorRepresentation( shown );
			$pick.data( 'pickrSync', false );
			// What Cancel goes back to.
			pickr.applyColor( true );
		}

		function setColor( $pick, hex, alpha ) {
			$pick.data( { hex: hex, alpha: alpha } );
			paintColor( $pick );
		}

		// A setting's field: what is chosen into its input (the preview and
		// the "unsaved" warning take it as any other change).
		function colorPicked( $pick ) {
			var $input = $pick.find( '.wp-swiper-color-pick__value' );
			var value = colorValue( $pick.data( 'hex' ), $pick.data( 'alpha' ) );
			if ( $input.length && $input.val() !== value ) {
				$input.val( value ).trigger( 'change' );
			}
			// A swatch without an input of its own (a gradient's point) says so.
			$pick.trigger( 'wpSwiperColor' );
		}

		// From Pickr: the color it shows, as the field keeps it. Only a new
		// one counts (Pickr says "change" for a format button too).
		function fromPickr( $pick, color ) {
			if ( ! color || $pick.data( 'pickrSync' ) ) {
				return;
			}
			var rgba = color.toRGBA();
			var hex = '#' + [ 0, 1, 2 ].map( function ( i ) {
				return ( '0' + Math.max( 0, Math.min( 255, Math.round( rgba[ i ] ) ) ).toString( 16 ) ).slice( -2 );
			} ).join( '' );
			var alpha = $pick.attr( 'data-alpha' ) === '1' ? Math.max( 0, Math.min( 100, Math.round( rgba[ 3 ] * 100 ) ) ) : 100;
			if ( hex === $pick.data( 'hex' ) && alpha === $pick.data( 'alpha' ) ) {
				return;
			}
			setColor( $pick, hex, alpha );
			colorPicked( $pick );
		}

		function openPickr() {
			var open = null;
			$( COLOR_PICK ).each( function () {
				var pickr = $( this ).data( 'pickr' );
				if ( pickr && pickr.isOpen() ) {
					open = pickr;
				}
			} );
			return open;
		}

		function closeColorPickers() {
			$( COLOR_PICK ).each( function () {
				var pickr = $( this ).data( 'pickr' );
				if ( pickr ) {
					pickr.hide();
				}
			} );
		}

		// Pickr the first time a field opens (one app each, in the body).
		function makePickr( $pick ) {
			var button = $pick.find( '.wp-swiper-color-pick__swatch' )[ 0 ];
			var alpha = $pick.attr( 'data-alpha' ) === '1';
			var pickr = window.Pickr.create( {
				el: button,
				useAsButton: true,
				theme: 'classic',
				appClass: 'wp-swiper-pcr',
				position: 'bottom-start',
				default: colorValue( $pick.data( 'hex' ), $pick.data( 'alpha' ) ),
				defaultRepresentation: 'HEXA',
				lockOpacity: ! alpha,
				swatches: colorSwatches,
				i18n: $.extend( {}, colorI18n, { 'btn:toggle': button.getAttribute( 'aria-label' ) } ),
				components: {
					preview: false,
					opacity: alpha,
					hue: true,
					interaction: {
						hex: true,
						rgba: true,
						hsla: true,
						input: true,
						cancel: true
					}
				}
			} );
			$pick.data( 'pickr', pickr );
			pickr.on( 'init', function ( instance ) {
				var $app = $( instance.getRoot().app );
				// The palette's names on hover.
				$app.find( '.pcr-swatches > button' ).each( function ( i ) {
					if ( colorNames[ i ] ) {
						this.setAttribute( 'title', colorNames[ i ] );
						this.setAttribute( 'aria-label', colorNames[ i ] );
					}
				} );
				// Default, after Cancel: red, as Pickr's own Clear (classic theme).
				$( '<input type="button" class="pcr-clear wp-swiper-pcr__default">' )
					.val( colorI18n[ 'default' ] || 'Default' )
					.attr( 'aria-label', colorI18n.defaultLabel || '' )
					.appendTo( $app.find( '.pcr-interaction' ) )
					.on( 'click', function () {
						var color = pickDefault( $pick );
						setColor( $pick, color.hex, color.alpha );
						colorPicked( $pick );
						pickrShow( $pick );
					} );
				// The eyedropper (as WP Banner's): beside the color's value, where
				// the browser has one (Chrome, Edge, Opera; not Firefox or Safari):
				// a color from anywhere on the screen, e.g. a photo in the preview;
				// the field's opacity kept; Escape: no change.
				if ( window.EyeDropper ) {
					var label = colorI18n.eyedropper || 'Pick a color from the screen';
					var $result = $app.find( '.pcr-result' );
					var $drop = $( '<button type="button" class="wp-swiper-pcr__dropper"></button>' )
						.attr( { title: label, 'aria-label': label } )
						.html( EYEDROPPER )
						.on( 'click', function () {
							new window.EyeDropper().open().then( function ( picked ) {
								var value = String( picked && picked.sRGBHex || '' );
								var m = /^rgba?\((\d+),\s*(\d+),\s*(\d+)/.exec( value );
								var color = m ? { hex: '#' + [ m[ 1 ], m[ 2 ], m[ 3 ] ].map( function ( channel ) {
									return ( '0' + Math.min( 255, Number( channel ) ).toString( 16 ) ).slice( -2 );
								} ).join( '' ) } : parseColor( value );
								if ( ! color ) {
									return;
								}
								setColor( $pick, color.hex, $pick.attr( 'data-alpha' ) === '1' ? $pick.data( 'alpha' ) : 100 );
								colorPicked( $pick );
								pickrShow( $pick );
							} ).catch( function () {
								// Escape, or the page left: nothing changes.
							} );
						} );
					$result.after( $drop, '<span class="wp-swiper-pcr__break"></span>' );
				}
				$pick.data( 'pickrReady', true );
				instance.show();
			} );
			pickr.on( 'show', function () {
				pickrShow( $pick );
				button.setAttribute( 'aria-expanded', 'true' );
			} );
			pickr.on( 'hide', function () {
				button.setAttribute( 'aria-expanded', 'false' );
			} );
			pickr.on( 'change', function ( color ) {
				fromPickr( $pick, color );
			} );
			pickr.on( 'cancel', function ( instance ) {
				fromPickr( $pick, instance.getColor() );
				instance.hide();
			} );
		}

		function showColor( prop, value ) {
			var $control = colorControl( prop );
			var color = parseColor( value );
			$control.find( '.wp-swiper-modal__color-mode' ).val( color ? 'custom' : '' );
			$control.find( COLOR_PICK ).prop( 'hidden', ! color );
			color = color || { hex: defaultColor( prop ), alpha: 100 };
			setColor( $control.find( COLOR_PICK ), color.hex, color.alpha );
			closeColorPickers();
		}

		// What the row says, as stored ("" for Default); render.php checks it again.
		function readColor( prop ) {
			var $control = colorControl( prop );
			var $pick = $control.find( COLOR_PICK );
			if ( $control.find( '.wp-swiper-modal__color-mode' ).val() !== 'custom' ) {
				return '';
			}
			return colorValue( $pick.data( 'hex' ), $pick.data( 'alpha' ) );
		}

		$modal.on( 'change', '.wp-swiper-modal__color-mode', function () {
			$( this ).closest( '.wp-swiper-modal__color' ).find( COLOR_PICK ).prop( 'hidden', $( this ).val() !== 'custom' );
			closeColorPickers();
		} );
		// Pickr made on the first click; it opens and closes the picker after
		// that (its own click on the swatch).
		$( document ).on( 'click', '.wp-swiper-color-pick__swatch', function () {
			var $pick = $( this ).closest( COLOR_PICK );
			if ( ! $pick.data( 'pickr' ) && window.Pickr ) {
				makePickr( $pick );
			}
		} );
		// The fields show their saved color.
		$( COLOR_PICK ).each( function () {
			var $pick = $( this );
			var color = parseColor( $pick.find( '.wp-swiper-color-pick__value' ).val() ) || pickDefault( $pick );
			$pick.find( '.wp-swiper-color-pick__swatch' ).attr( 'aria-expanded', 'false' );
			setColor( $pick, color.hex, color.alpha );
		} );

		// The settings' swatches show their inputs' colors again, after these
		// were set without the picker (another effect's Layout, Undo / Redo).
		function syncColorPicks() {
			$( '#wp-swiper-settings ' + COLOR_PICK ).each( function () {
				var $pick = $( this );
				var $input = $pick.find( '.wp-swiper-color-pick__value' );
				if ( $input.length ) {
					var color = parseColor( $input.val() ) || pickDefault( $pick );
					setColor( $pick, color.hex, color.alpha );
				}
			} );
			syncGradients();
		}

		/*
		 * The photos' Gradient (gradient_field() in class-wp-swiper-admin.php),
		 * edited as WoodMart's: its type and where it starts (or its middle);
		 * the gradient as a bar, its colors' points under it: a click on the
		 * bar adds one (the color there), a point is dragged along it (or moved
		 * with the arrow keys; Delete removes it), the chosen one's color
		 * (Pickr, with opacity) and Delete under them. Kept in the
		 * hidden input as "type;where;color@position;…", in order of the
		 * positions (WP_Swiper_Art::parse_gradient() checks it again).
		 */
		var GRADIENT = '.wp-swiper-gradient';
		var GRADIENT_STOPS = 10;

		function parseGradient( value ) {
			var parts = String( value || '' ).split( ';' );
			if ( parts.length < 4 ) {
				return null;
			}
			var type = parts.shift();
			var where = parts.shift();
			var at = where.split( ' ' );
			if ( type === 'linear' ? [ 'left', 'right', 'top', 'bottom' ].indexOf( where ) === -1 : (
				[ 'circle', 'ellipse' ].indexOf( type ) === -1 || at.length !== 2 ||
				[ 'left', 'center', 'right' ].indexOf( at[ 0 ] ) === -1 || [ 'top', 'center', 'bottom' ].indexOf( at[ 1 ] ) === -1
			) ) {
				return null;
			}
			var stops = [];
			parts.forEach( function ( part ) {
				var point = part.split( '@' );
				var color = point.length === 2 ? parseColor( point[ 0 ] ) : null;
				var position = point.length === 2 ? parseFloat( point[ 1 ] ) : NaN;
				if ( color && ! isNaN( position ) ) {
					stops.push( { color: colorValue( color.hex, color.alpha ), at: Math.max( 0, Math.min( 100, position ) ) } );
				}
			} );
			return stops.length < 2 ? null : { type: type, where: where, stops: stops.slice( 0, GRADIENT_STOPS ) };
		}

		// Its points in order of their positions (those at one place as they are).
		function gradientSorted( gradient ) {
			return gradient.stops.map( function ( stop, i ) {
				return { stop: stop, i: i };
			} ).sort( function ( a, b ) {
				return a.stop.at - b.stop.at || a.i - b.i;
			} ).map( function ( item ) {
				return item.stop;
			} );
		}

		function gradientPosition( at ) {
			return Math.round( at * 10 ) / 10;
		}

		// The bar: the colors left to right, whatever the type.
		function gradientBar( gradient ) {
			return 'linear-gradient(to right, ' + gradientSorted( gradient ).map( function ( stop ) {
				return stop.color + ' ' + gradientPosition( stop.at ) + '%';
			} ).join( ', ' ) + ')';
		}

		// The color the gradient has at a position (a new point's).
		function gradientColorAt( gradient, at ) {
			var stops = gradientSorted( gradient );
			var before = stops[ 0 ];
			var after = stops[ stops.length - 1 ];
			stops.forEach( function ( stop ) {
				if ( stop.at <= at ) {
					before = stop;
				}
			} );
			for ( var i = stops.length - 1; i >= 0; i-- ) {
				if ( stops[ i ].at >= at ) {
					after = stops[ i ];
				}
			}
			var a = parseColor( before.color );
			var b = parseColor( after.color );
			var share = after.at > before.at ? ( at - before.at ) / ( after.at - before.at ) : 0;
			var mix = function ( x, y ) {
				return Math.round( x + ( y - x ) * share );
			};
			var hex = '#' + [ 1, 3, 5 ].map( function ( i ) {
				return ( '0' + mix( parseInt( a.hex.substr( i, 2 ), 16 ), parseInt( b.hex.substr( i, 2 ), 16 ) ).toString( 16 ) ).slice( -2 );
			} ).join( '' );
			return colorValue( hex, mix( a.alpha, b.alpha ) );
		}

		// The gradient, the bar and its points as they are now (no new ones).
		function drawGradientBar( $gradient ) {
			var gradient = $gradient.data( 'gradient' );
			$gradient.find( '.wp-swiper-gradient__fill' ).css( 'background-image', gradientBar( gradient ) );
			$gradient.find( '.wp-swiper-gradient__handle' ).each( function () {
				var stop = $( this ).data( 'stop' );
				$( this ).css( 'left', stop.at + '%' )
					.attr( 'aria-label', stop.color + ', ' + gradientPosition( stop.at ) + '%' )
					.find( '.wp-swiper-gradient__chip' ).css( 'background-color', stop.color );
			} );
		}

		// Everything: the selects, a point for each color, the chosen one's.
		function drawGradient( $gradient ) {
			var gradient = $gradient.data( 'gradient' );
			var chosen = $gradient.data( 'chosen' );
			var linear = gradient.type === 'linear';
			$gradient.find( '.wp-swiper-gradient__type' ).val( gradient.type );
			$gradient.find( '.wp-swiper-gradient__direction' ).prop( 'hidden', ! linear ).val( linear ? gradient.where : 'left' );
			$gradient.find( '.wp-swiper-gradient__x' ).prop( 'hidden', linear ).val( linear ? 'center' : gradient.where.split( ' ' )[ 0 ] );
			$gradient.find( '.wp-swiper-gradient__y' ).prop( 'hidden', linear ).val( linear ? 'center' : gradient.where.split( ' ' )[ 1 ] );
			var $stops = $gradient.find( '.wp-swiper-gradient__stops' ).empty();
			gradient.stops.forEach( function ( stop ) {
				$( '<button type="button" class="wp-swiper-gradient__handle" role="option"><span class="wp-swiper-gradient__chip"></span></button>' )
					.data( 'stop', stop )
					.attr( 'aria-selected', stop === chosen ? 'true' : 'false' )
					.toggleClass( 'is-chosen', stop === chosen )
					.appendTo( $stops );
			} );
			var color = parseColor( chosen.color );
			setColor( $gradient.find( COLOR_PICK ), color.hex, color.alpha );
			$gradient.find( '.wp-swiper-gradient__delete' ).prop( 'disabled', gradient.stops.length <= 2 );
			drawGradientBar( $gradient );
		}

		// Into the input, in order (the preview and Undo take it as any change).
		function saveGradient( $gradient ) {
			var gradient = $gradient.data( 'gradient' );
			gradient.stops = gradientSorted( gradient );
			var value = gradient.type + ';' + gradient.where + ';' + gradient.stops.map( function ( stop ) {
				return stop.color + '@' + gradientPosition( stop.at );
			} ).join( ';' );
			var $input = $gradient.find( '.wp-swiper-gradient__value' );
			if ( $input.val() !== value ) {
				$input.val( value ).trigger( 'change' );
			}
		}

		// From the input (on load, after Undo / Redo or another effect's Layout).
		function loadGradient( $gradient ) {
			var gradient = parseGradient( $gradient.find( '.wp-swiper-gradient__value' ).val() ) || parseGradient( $gradient.attr( 'data-default' ) );
			if ( ! gradient ) {
				return;
			}
			var before = $gradient.data( 'gradient' );
			var index = before ? Math.max( 0, before.stops.indexOf( $gradient.data( 'chosen' ) ) ) : 0;
			$gradient.data( { gradient: gradient, chosen: gradient.stops[ Math.min( index, gradient.stops.length - 1 ) ] } );
			drawGradient( $gradient );
		}

		function syncGradients() {
			$( '#wp-swiper-settings ' + GRADIENT ).each( function () {
				loadGradient( $( this ) );
			} );
		}

		function chooseStop( $gradient, stop ) {
			if ( $gradient.data( 'chosen' ) !== stop ) {
				closeColorPickers();
			}
			$gradient.data( 'chosen', stop );
			drawGradient( $gradient );
		}

		function gradientHandle( $gradient ) {
			var chosen = $gradient.data( 'chosen' );
			return $gradient.find( '.wp-swiper-gradient__handle' ).filter( function () {
				return $( this ).data( 'stop' ) === chosen;
			} );
		}

		function deleteStop( $gradient ) {
			var gradient = $gradient.data( 'gradient' );
			if ( gradient.stops.length <= 2 ) {
				return;
			}
			gradient.stops.splice( gradient.stops.indexOf( $gradient.data( 'chosen' ) ), 1 );
			chooseStop( $gradient, gradientSorted( gradient )[ 0 ] );
			saveGradient( $gradient );
		}

		// Where along the bar a pointer is, 0–100.
		function gradientAt( $gradient, clientX ) {
			var rect = $gradient.find( '.wp-swiper-gradient__bar' )[ 0 ].getBoundingClientRect();
			return rect.width ? Math.max( 0, Math.min( 100, Math.round( ( clientX - rect.left ) / rect.width * 100 ) ) ) : 0;
		}

		$( '#wp-swiper-settings' )
			.on( 'change', GRADIENT + ' select', function () {
				var $gradient = $( this ).closest( GRADIENT );
				var gradient = $gradient.data( 'gradient' );
				var type = $gradient.find( '.wp-swiper-gradient__type' ).val();
				if ( type === 'linear' ) {
					gradient.where = gradient.type === 'linear' ? $gradient.find( '.wp-swiper-gradient__direction' ).val() : 'left';
				} else {
					gradient.where = gradient.type === 'linear' ? 'center center' : $gradient.find( '.wp-swiper-gradient__x' ).val() + ' ' + $gradient.find( '.wp-swiper-gradient__y' ).val();
				}
				gradient.type = type;
				drawGradient( $gradient );
				saveGradient( $gradient );
			} )
			// A new color where the bar is clicked, the gradient's color there.
			.on( 'click', GRADIENT + ' .wp-swiper-gradient__bar', function ( e ) {
				var $gradient = $( this ).closest( GRADIENT );
				var gradient = $gradient.data( 'gradient' );
				if ( gradient.stops.length >= GRADIENT_STOPS ) {
					return;
				}
				var at = gradientAt( $gradient, e.clientX );
				var stop = { color: gradientColorAt( gradient, at ), at: at };
				gradient.stops.push( stop );
				chooseStop( $gradient, stop );
				saveGradient( $gradient );
			} )
			// A point: chosen, and dragged along the bar while held.
			.on( 'pointerdown', GRADIENT + ' .wp-swiper-gradient__handle', function ( e ) {
				var $gradient = $( this ).closest( GRADIENT );
				var stop = $( this ).data( 'stop' );
				chooseStop( $gradient, stop );
				var handle = gradientHandle( $gradient )[ 0 ];
				handle.focus();
				if ( e.button !== 0 ) {
					return;
				}
				e.preventDefault();
				var moved = false;
				var move = function ( event ) {
					var at = gradientAt( $gradient, event.clientX );
					if ( at !== stop.at ) {
						stop.at = at;
						moved = true;
						drawGradientBar( $gradient );
					}
				};
				var end = function () {
					$( document ).off( 'pointermove', move ).off( 'pointerup pointercancel', end );
					if ( moved ) {
						saveGradient( $gradient );
						drawGradient( $gradient );
						gradientHandle( $gradient ).trigger( 'focus' );
					}
				};
				$( document ).on( 'pointermove', move ).on( 'pointerup pointercancel', end );
			} )
			.on( 'keydown', GRADIENT + ' .wp-swiper-gradient__handle', function ( e ) {
				var $gradient = $( this ).closest( GRADIENT );
				var stop = $( this ).data( 'stop' );
				var step = { ArrowLeft: -1, ArrowDown: -1, ArrowRight: 1, ArrowUp: 1 }[ e.key ];
				if ( step ) {
					e.preventDefault();
					stop.at = Math.max( 0, Math.min( 100, Math.round( stop.at ) + step * ( e.shiftKey ? 10 : 1 ) ) );
					saveGradient( $gradient );
					chooseStop( $gradient, stop );
					gradientHandle( $gradient ).trigger( 'focus' );
				} else if ( e.key === 'Delete' || e.key === 'Backspace' ) {
					e.preventDefault();
					deleteStop( $gradient );
					gradientHandle( $gradient ).trigger( 'focus' );
				}
			} )
			.on( 'click', GRADIENT + ' .wp-swiper-gradient__delete', function () {
				deleteStop( $( this ).closest( GRADIENT ) );
			} )
			.on( 'wpSwiperColor', GRADIENT + ' ' + COLOR_PICK, function () {
				var $pick = $( this );
				var $gradient = $pick.closest( GRADIENT );
				$gradient.data( 'chosen' ).color = colorValue( $pick.data( 'hex' ), $pick.data( 'alpha' ) );
				drawGradientBar( $gradient );
				saveGradient( $gradient );
			} );
		syncGradients();

		// A size's device icons: which device's value the field shows; then the
		// other fields and the preview follow (shareDevice()).
		$modal.on( 'click', '.wp-swiper-modal__size .wp-swiper-devices__btn', function () {
			var $control = $( this ).closest( '.wp-swiper-modal__size' );
			shared.shareDevice( $( this ).data( 'device' ) );
			showFontDevice( $control, $( this ).data( 'device' ) );
			$control.find( '.wp-swiper-modal__size-input:not([hidden])' ).trigger( 'focus' );
		} );
		$modal.on( 'input change', '.wp-swiper-modal__size-input', function () {
			syncFontInherits( $( this ).closest( '.wp-swiper-modal__size' ) );
		} );
	};
}( jQuery ) );
