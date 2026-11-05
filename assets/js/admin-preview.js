/**
 * WP Swiper slider edit screen: the preview (its devices, turned, dark
 * screen, Developer mode, updates), Undo / Redo, and what is done in it
 * (the Collage's order and sizes, Art's order, middle and cells). A
 * part of admin.js, run by it (see there).
 */
( function ( $ ) {
	'use strict';

	var parts = ( window.wpSwiperEditor = window.wpSwiperEditor || { parts: {} } ).parts;

	/*
	 * The preview, Undo / Redo and what is done in the preview.
	 */
	parts.preview = function ( shared ) {
		// From the parts before this one (shared).
		var i18n = shared.i18n;
		var TEMPLATES = shared.TEMPLATES;
		var $list = shared.$list;
		var prepareForm = shared.prepareForm;
		var syncPicker = shared.syncPicker;
		var $modal = shared.$modal;
		var closeColorPickers = shared.closeColorPickers;
		var openPickr = shared.openPickr;
		var syncColorPicks = shared.syncColorPicks;
		var $layouts = shared.$layouts;
		var field = shared.field;
		var lockStates = shared.lockStates;
		var syncFields = shared.syncFields;
		var $menu = shared.$menu;
		var DEVICE_KEY = shared.DEVICE_KEY;
		var deviceListeners = shared.deviceListeners;
		var DEVICES = shared.DEVICES;
		var PAGE_KEY = shared.PAGE_KEY;
		var saveSettingsEditors = shared.saveSettingsEditors;
		var setDevice = shared.setDevice;
		var showFieldDevice = shared.showFieldDevice;
		var showPage = shared.showPage;
		var startSettingsEditors = shared.startSettingsEditors;
		var syncResponsive = shared.syncResponsive;
		var syncTiles = shared.syncTiles;

		/*
		 * Preview (WP_Swiper_Preview): the form's slides and settings, saved or
		 * not, posted into the frame when the screen opens, on "Update", and at
		 * once for another Effect or Style (autoUpdate; not on every change).
		 * The frame is drawn at the device's size and
		 * scaled down to the column, so the slider shows that device's settings.
		 */
		var $preview = $( '.wp-swiper-preview' );
		if ( $preview.length ) {
			var $stage = $preview.find( '.wp-swiper-preview__stage' );
			var $update = $preview.find( '.wp-swiper-preview__update' );
			var previewFrame = $preview.find( '.wp-swiper-preview__frame' )[ 0 ];
			var sizes = $preview.data( 'sizes' );
			var drawings = $preview.data( 'devices' ) || {};
			var $drawings = $preview.find( '.wp-swiper-preview__device' );

			/*
			 * At the size of the device being edited (setDevice()). Tablet and
			 * Mobile: in the device drawn for it (WP_Swiper_Preview::DEVICES), the
			 * frame in its screen with its rounded corners; the drawing over it
			 * (a camera or bar over the page, as on a real one), letting the
			 * clicks through. Drawing and frame scale as one.
			 *
			 * Turned sideways (Tablet, Mobile; the button by the devices): the
			 * frame's sides swapped, the drawing a quarter turn clockwise round
			 * it. The slider then takes the settings of the device its width
			 * falls in, as on the site (the note under the bar says which). Kept
			 * per device for the next visit (this browser only).
			 */
			var TURN_KEY = 'wpSwiperTurned';
			var turned = {};
			try {
				turned = JSON.parse( window.localStorage.getItem( TURN_KEY ) ) || {};
			} catch ( e ) {}
			var $turn = $preview.find( '.wp-swiper-preview__rotate' );
			var canTurn = function () {
				return shared.currentDevice !== 'desktop' && !! drawings[ shared.currentDevice ];
			};
			var isTurned = function () {
				return canTurn() && !! turned[ shared.currentDevice ];
			};

			var fitPreview = function () {
				var turn = isTurned();
				var size = turn ? [ sizes[ shared.currentDevice ][ 1 ], sizes[ shared.currentDevice ][ 0 ] ] : sizes[ shared.currentDevice ];
				var device = drawings[ shared.currentDevice ];
				var image = $drawings.filter( '[data-device="' + shared.currentDevice + '"]' )[ 0 ];
				var inDevice = !! ( device && image );
				// px of the frame per unit of the drawing (its screen as wide as the
				// upright frame); the whole device's size, and the screen's place in it.
				var unit = inDevice ? sizes[ shared.currentDevice ][ 0 ] / device.screen[ 2 ] : 1;
				var upright = inDevice ? [ device.width * unit, device.height * unit ] : size;
				var outer = turn ? [ upright[ 1 ], upright[ 0 ] ] : upright;
				var offset = [ 0, 0 ];
				if ( inDevice ) {
					// A quarter turn clockwise takes a point ( x, y ) of the drawing to ( height - y, x ).
					offset = turn ?
						[ ( device.height - device.screen[ 1 ] - device.screen[ 3 ] ) * unit, device.screen[ 0 ] * unit ] :
						[ device.screen[ 0 ] * unit, device.screen[ 1 ] * unit ];
				}
				var width = $stage.width();
				// The whole frame in view: no taller than the window leaves room for.
				var height = Math.max( 300, window.innerHeight - 180 );
				var scale = Math.min( 1, width / outer[ 0 ], height / outer[ 1 ] );
				var left = Math.max( 0, ( width - outer[ 0 ] * scale ) / 2 );

				previewFrame.style.width = size[ 0 ] + 'px';
				previewFrame.style.height = size[ 1 ] + 'px';
				previewFrame.style.transform = 'scale(' + scale + ')';
				previewFrame.style.left = ( left + offset[ 0 ] * scale ) + 'px';
				previewFrame.style.top = ( offset[ 1 ] * scale ) + 'px';
				previewFrame.style.borderRadius = inDevice ? ( device.radius * unit ) + 'px' : '';
				$preview.toggleClass( 'wp-swiper-preview--device', inDevice );
				$drawings.each( function () {
					this.hidden = this !== image || ! inDevice;
				} );
				if ( inDevice ) {
					// Turned: drawn upright, then turned round its top left corner,
					// which goes to the right of its box.
					image.style.left = ( left + ( turn ? outer[ 0 ] * scale : 0 ) ) + 'px';
					image.style.width = ( upright[ 0 ] * scale ) + 'px';
					image.style.height = ( upright[ 1 ] * scale ) + 'px';
					image.style.transform = turn ? 'rotate(90deg)' : '';
				}
				$stage.css( 'height', Math.ceil( outer[ 1 ] * scale ) + 'px' );
				// The button (Tablet, Mobile) and the note of the device turned.
				$turn.prop( 'disabled', ! canTurn() ).attr( 'aria-pressed', turn ? 'true' : 'false' );
				$preview.find( '.wp-swiper-preview__turned' ).each( function () {
					this.hidden = ! turn || $( this ).data( 'device' ) !== shared.currentDevice;
				} );
			};

			/*
			 * The turn itself, seen: the device as it was (its box on screen)
			 * turned a quarter round into its new place and size (the stage
			 * drawn turned back, then let go). Not with reduced motion.
			 */
			var shownBox = function () {
				var image = $drawings.filter( function () {
					return ! this.hidden;
				} )[ 0 ];
				return ( image || previewFrame ).getBoundingClientRect();
			};
			$turn.on( 'click', function () {
				if ( ! canTurn() ) {
					return;
				}
				var from = shownBox();
				turned[ shared.currentDevice ] = ! turned[ shared.currentDevice ];
				try {
					window.localStorage.setItem( TURN_KEY, JSON.stringify( turned ) );
				} catch ( e ) {}
				var stage = $stage[ 0 ];
				stage.style.transition = 'none';
				stage.style.transform = '';
				fitPreview();
				if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
					return;
				}
				var to = shownBox();
				var origin = stage.getBoundingClientRect();
				// Round the new box's middle: back the other way, its sides as they were, where it was.
				var middle = [ to.left + to.width / 2 - origin.left, to.top + to.height / 2 - origin.top ];
				var shift = [ from.left + from.width / 2 - ( to.left + to.width / 2 ), from.top + from.height / 2 - ( to.top + to.height / 2 ) ];
				var size = to.width ? from.height / to.width : 1;
				$stage.addClass( 'is-turning' );
				stage.style.transformOrigin = middle[ 0 ] + 'px ' + middle[ 1 ] + 'px';
				stage.style.transform = 'translate(' + shift[ 0 ] + 'px, ' + shift[ 1 ] + 'px) rotate(' + ( turned[ shared.currentDevice ] ? -90 : 90 ) + 'deg) scale(' + size + ')';
				// Drawn so, then let go.
				void stage.offsetWidth;
				stage.style.transition = 'transform 0.5s ease';
				stage.style.transform = '';
				var done = function () {
					$stage.removeClass( 'is-turning' );
					stage.style.transition = '';
					stage.style.transformOrigin = '';
				};
				$stage.one( 'transitionend', done );
				setTimeout( done, 700 );
			} );

			var updatePreview = function () {
				prepareForm();
				var $form = $( '<form method="post" hidden></form>' ).attr( {
					action: $preview.data( 'url' ),
					target: previewFrame.name
				} );
				var add = function ( name, value ) {
					$( '<input type="hidden">' ).attr( 'name', name ).val( value ).appendTo( $form );
				};
				add( 'action', $preview.data( 'action' ) );
				add( 'post_id', $preview.data( 'post' ) );
				add( '_wpnonce', $preview.data( 'nonce' ) );
				// Read now: the locked fields lock again right after (prepareForm).
				$( '#post' ).serializeArray().forEach( function ( item ) {
					if ( /^wp_swiper_(settings|slides)\[/.test( item.name ) ) {
						add( item.name, item.value );
					}
				} );
				$form.appendTo( document.body );
				$form[ 0 ].submit();
				$form.remove();
				$update.removeClass( 'is-stale' );
				markShown();
			};

			// The Styles (fields shown as icons) the preview shows now.
			var markShown = function () {
				$( '#wp-swiper-settings .wp-swiper-field--tiles select' ).each( function () {
					$( this ).data( 'shown', $( this ).val() );
				} );
			};

			$update.on( 'click', updatePreview );

			// Another Effect, or a Style picked from its icons (the Collage's,
			// Art's, Text mask's Strips go): the preview follows at once (each
			// new post into the frame replaces the one before). Only the choices
			// made here: Undo / Redo set the values without "change" (the
			// preview stays, Update says it is out of date); the other settings
			// wait for Update.
			var autoUpdate = function () {
				updatePreview();
			};
			$( '#wp-swiper-effect' ).on( 'change', autoUpdate );
			$( '#wp-swiper-settings' ).on( 'change', '.wp-swiper-field--tiles select', function () {
				// The Style the preview shows clicked again: nothing new to show.
				if ( $( this ).data( 'shown' ) !== $( this ).val() ) {
					autoUpdate();
				}
			} );
			markShown();

			// Developer mode: the boxes and empty spaces drawn over the preview
			// (preview-dev.js in the frame), again after every update; kept for
			// the next visit (this browser only).
			var DEV_KEY = 'wpSwiperDev';
			var $dev = $preview.find( '.wp-swiper-preview__dev' );
			var devOn = false;
			try {
				devOn = window.localStorage.getItem( DEV_KEY ) === '1';
			} catch ( e ) {}
			// It draws a slider's slides, a collage's places and an SVG shape's
			// windows (preview-dev.js): hidden where there are none to draw (Text
			// mask, Art's other Styles; no slides yet), its place kept (the bar
			// stays as it is); the choice is kept for when it is back.
			var devAvailable = function () {
				var effect = $( '#wp-swiper-effect' ).val();
				var art = effect === 'art' && $( '#wp-swiper-art_style' ).val() === 'shape';
				return ( art || [ 'art', 'text-mask' ].indexOf( effect ) === -1 ) && $list.children().length > 0;
			};
			var applyDev = function () {
				var available = devAvailable();
				$dev.prop( 'hidden', ! available ).attr( 'aria-pressed', devOn && available ? 'true' : 'false' );
				var win = previewFrame.contentWindow;
				try {
					if ( win && win.wpSwiperDev ) {
						win.wpSwiperDev( devOn && available );
					}
				} catch ( e ) {}
			};
			$( '#wp-swiper-effect, #wp-swiper-art_style' ).on( 'change', applyDev );
			// A slide added or removed (toggleEmpty()).
			$list.on( 'wpswiperslides', applyDev );
			$dev.on( 'click', function () {
				if ( ! devAvailable() ) {
					return;
				}
				devOn = ! devOn;
				try {
					window.localStorage.setItem( DEV_KEY, devOn ? '1' : '0' );
				} catch ( e ) {}
				applyDev();
			} );
			$( previewFrame ).on( 'load', applyDev );
			applyDev();

			// Dark screen: the preview page's background almost black (its own
			// CSS, class-wp-swiper-preview.php, which reads the same key before
			// it is drawn), at once, without an update; kept for the next visit.
			var DARK_KEY = 'wpSwiperDark';
			var $screen = $preview.find( '.wp-swiper-preview__screen' );
			var darkOn = false;
			try {
				darkOn = window.localStorage.getItem( DARK_KEY ) === '1';
			} catch ( e ) {}
			var applyDark = function () {
				$screen.attr( 'aria-pressed', darkOn ? 'true' : 'false' );
				$preview.toggleClass( 'wp-swiper-preview--dark', darkOn );
				try {
					var doc = previewFrame.contentDocument;
					if ( doc && doc.documentElement ) {
						doc.documentElement.classList.toggle( 'wp-swiper-preview-dark', darkOn );
					}
				} catch ( e ) {}
			};
			$screen.on( 'click', function () {
				darkOn = ! darkOn;
				try {
					window.localStorage.setItem( DARK_KEY, darkOn ? '1' : '0' );
				} catch ( e ) {}
				applyDark();
			} );
			$( previewFrame ).on( 'load', applyDark );
			applyDark();

			/*
			 * Collage order (the Order field): the photos' IDs per device, the first
			 * ones in the first places. For the device the field shows: Shuffle sets
			 * one at random, Reset clears it (back to the larger screen's; desktop:
			 * the shuffle number's), and a photo dragged onto another place in the
			 * preview (preview-collage.js) sends the order it made. Shown right away.
			 */
			var $order = field( 'collage_order' );
			var orderDevice = function () {
				return $order.find( '.wp-swiper-devices__btn.is-active' ).data( 'device' ) || shared.currentDevice;
			};
			// The field keeps three values per device: the order, the mosaic sizes
			// and whether either was set by hand in the preview ("1").
			var input = function ( name, device ) {
				return $( '#wp-swiper-' + name + ( device === 'desktop' ? '' : '_' + device ) );
			};
			var $shuffle = $order.find( '.wp-swiper-shuffle' );
			var $reset = $order.find( '.wp-swiper-order-reset' );

			/*
			 * For the device the field shows: Reset only while it has an order or
			 * sizes of its own (there is something to go back from); Shuffle not
			 * while they were set by hand (it would undo that without a word).
			 */
			var syncOrderButtons = function () {
				var device = orderDevice();
				var own = input( 'collage_order', device ).val() !== '' || input( 'collage_mosaic', device ).val() !== '' || input( 'collage_cuts', device ).val() !== '';
				var byHand = own && input( 'collage_manual', device ).val() === '1';
				$shuffle.prop( 'disabled', byHand ).attr( 'title', byHand ? i18n.shuffleLocked || '' : '' );
				$reset.prop( 'disabled', ! own ).attr( 'title', own ? '' : i18n.resetNothing || '' );
			};
			var setOrder = function ( device, ids ) {
				input( 'collage_order', device ).val( ids.join( ',' ) );
				syncResponsive( $order );
				syncOrderButtons();
			};
			var setMosaic = function ( device, text ) {
				input( 'collage_mosaic', device ).val( text );
				syncResponsive( $order );
				syncOrderButtons();
			};
			// A template's sizes: "feature-left:V0.7[...]" (WP_Swiper_Settings::sanitize_template_cuts()).
			var setCuts = function ( device, text ) {
				input( 'collage_cuts', device ).val( text );
				syncResponsive( $order );
				syncOrderButtons();
			};
			var setByHand = function ( device, on ) {
				input( 'collage_manual', device ).val( on ? '1' : '' );
				syncResponsive( $order );
				syncOrderButtons();
			};

			// Another device shown in the field (its own icons): its buttons, once
			// the field has switched. The field follows the preview's device, so
			// the buttons are those of the collage in view.
			var showOrderDevice = function ( device ) {
				$order.find( '.wp-swiper-devices__btn[data-device="' + device + '"]' ).trigger( 'click' );
			};
			$order.on( 'click', '.wp-swiper-devices__btn', function () {
				setTimeout( syncOrderButtons );
			} );
			deviceListeners.push( showOrderDevice );
			showOrderDevice( shared.currentDevice );
			syncOrderButtons();

			/*
			 * Undo / redo (the buttons left of the preview's devices; Ctrl+Z and
			 * Ctrl+Shift+Z or Ctrl+Y, in the preview too, but not while typing in
			 * a box: the browser undoes the typing there). Every setting of the
			 * slider and, for Collage and Art, what was done by hand in the
			 * preview (photos swapped or moved, sizes dragged, the Order field's
			 * Shuffle and Reset); not the slides (their list, captions, links).
			 * One history for the page, up to 50 steps, until the page is left.
			 *
			 * A step keeps the settings as they were before it and where it was
			 * made: its page, and its device for a device's own value or an edit
			 * in the preview. Going back or forth shows them first, puts the
			 * values in (the locks' states with them) and lights up the fields
			 * that changed. The preview is not drawn again (Update says it is
			 * out of date, as after any change); an edit made in it is taken
			 * back there at once.
			 * Changes of one field close together (typing, dragging) are one step.
			 */
			var HISTORY_MAX = 50;
			// Taken by the preview without drawing it again (wpSwiperRestoreCollage / -Art).
			var PREVIEW_FIELDS = [ 'collage_order', 'collage_mosaic', 'collage_cuts', 'collage_manual', 'collage_count', 'art_order', 'art_ring_center', 'art_cells' ];
			var $history = $preview.find( '.wp-swiper-preview__history' );
			var $undo = $history.find( '.wp-swiper-preview__undo' );
			var $redo = $history.find( '.wp-swiper-preview__redo' );
			var undoSteps = [];
			var redoSteps = [];
			// The settings as they are: read again shortly after each change.
			var baseline = null;
			var baselineTimer = null;
			// The step still taking changes (its key), until a pause.
			var openKey = null;
			var openTimer = null;
			var redoKept = [];
			var restoring = false;
			var markTimer = null;

			var settingInputs = function () {
				return $( '#wp-swiper-settings' ).find( 'input[name^="wp_swiper_settings["], select[name^="wp_swiper_settings["], textarea[name^="wp_swiper_settings["]' ).not( $layouts );
			};
			// Each setting's input with its key: its name, and which one of that name.
			var eachSetting = function ( fn ) {
				var seen = {};
				settingInputs().each( function () {
					seen[ this.name ] = ( seen[ this.name ] || 0 ) + 1;
					fn( this, this.name + '#' + seen[ this.name ] );
				} );
			};
			var isBox = function ( el ) {
				return el.type === 'checkbox' || el.type === 'radio';
			};

			var snapshot = function () {
				saveSettingsEditors();
				var values = {};
				eachSetting( function ( el, key ) {
					var value = $( el ).val();
					values[ key ] = isBox( el ) ? el.checked : ( null === value ? '' : String( value ) );
					// An image setting's thumbnail.
					if ( el.hasAttribute( 'data-url' ) ) {
						values[ key + '@url' ] = el.getAttribute( 'data-url' );
					}
				} );
				// The other effects' Layout values (the chosen one's are the fields').
				var others = $.extend( {}, shared.layouts );
				delete others[ shared.layoutEffect ];
				return {
					values: values,
					locks: lockStates.map( function ( lock ) {
						return lock.get();
					} ),
					layouts: JSON.stringify( others ),
					effect: shared.layoutEffect,
					// Art's SVG shape boxes: which file they say is in.
					shapes: $( '#wp-swiper-settings .wp-swiper-shape' ).map( function () {
						return this.innerHTML;
					} ).get()
				};
			};
			var sameState = function ( a, b ) {
				return JSON.stringify( [ a.values, a.locks, a.layouts, a.effect ] ) === JSON.stringify( [ b.values, b.locks, b.layouts, b.effect ] );
			};

			// The values of `state` into the fields, and all that shows them.
			var restoreState = function ( state ) {
				closeColorPickers();
				eachSetting( function ( el, key ) {
					if ( ! ( key in state.values ) ) {
						return;
					}
					if ( isBox( el ) ) {
						el.checked = !! state.values[ key ];
					} else {
						$( el ).val( state.values[ key ] );
						var editor = el.tagName === 'TEXTAREA' && window.tinymce ? window.tinymce.get( el.id ) : null;
						if ( editor ) {
							editor.setContent( el.value );
						}
					}
					if ( ( key + '@url' ) in state.values ) {
						el.setAttribute( 'data-url', state.values[ key + '@url' ] );
					}
				} );
				state.locks.forEach( function ( lock, i ) {
					if ( lockStates[ i ] ) {
						lockStates[ i ].set( lock );
					}
				} );
				shared.layouts = JSON.parse( state.layouts );
				shared.layoutEffect = state.effect;
				$( '#wp-swiper-settings .wp-swiper-shape' ).each( function ( i ) {
					if ( undefined !== state.shapes[ i ] ) {
						this.innerHTML = state.shapes[ i ];
					}
				} );
				$( '.wp-swiper-field--responsive' ).each( function () {
					syncResponsive( $( this ) );
				} );
				$( '.wp-swiper-field--image input[type="hidden"]' ).each( function () {
					syncPicker( $( this ) );
				} );
				$( '.wp-swiper-field--tiles' ).each( function () {
					syncTiles( $( this ) );
				} );
				syncColorPicks();
				syncFields();
				syncOrderButtons();
			};

			var syncHistory = function () {
				$undo.prop( 'disabled', ! undoSteps.length );
				$redo.prop( 'disabled', ! redoSteps.length );
			};

			var currentPage = function () {
				return $menu.filter( '[aria-current="true"]' ).data( 'page' ) || '';
			};

			var readBaseline = function () {
				clearTimeout( baselineTimer );
				baselineTimer = null;
				baseline = snapshot();
			};

			// The open step takes no more changes; if nothing changed after all, it goes.
			var closeStep = function () {
				clearTimeout( openTimer );
				openTimer = null;
				if ( null === openKey ) {
					return;
				}
				openKey = null;
				if ( baselineTimer ) {
					readBaseline();
				}
				var last = undoSteps[ undoSteps.length - 1 ];
				if ( last && sameState( last.state, baseline ) ) {
					undoSteps.pop();
					redoSteps = redoKept;
				}
				redoKept = [];
				syncHistory();
			};

			/*
			 * A change, made already. `at`: { key (the field and its device), page,
			 * device (a device's own value, an edit in the preview; '' for all) }.
			 * The first change of a field starts a step with the settings read
			 * before it; more of the same field, or any while the settings were
			 * not read again yet (one action changing two), go into that step.
			 */
			var noteChange = function ( at ) {
				if ( restoring || null === baseline ) {
					return;
				}
				if ( ! baselineTimer && at.key !== openKey ) {
					closeStep();
					undoSteps.push( { state: baseline, at: at } );
					if ( undoSteps.length > HISTORY_MAX ) {
						undoSteps.shift();
					}
					redoKept = redoSteps;
					redoSteps = [];
					openKey = at.key;
				}
				clearTimeout( baselineTimer );
				baselineTimer = setTimeout( readBaseline, 150 );
				clearTimeout( openTimer );
				openTimer = setTimeout( closeStep, 800 );
				syncHistory();
			};

			// An edit in the preview (and the Order field's buttons), on `device`.
			var record = function ( device, page ) {
				noteChange( { key: 'preview:' + device, page: page || currentPage(), device: device } );
			};

			$( '#wp-swiper-settings' ).on( 'input change', 'input, select, textarea', function () {
				if ( this.type === 'file' || $( this ).closest( '.wp-swiper-preview' ).length ) {
					return;
				}
				var device = $( this ).closest( '[data-device]' ).data( 'device' ) || '';
				noteChange( {
					key: ( $( this ).closest( '.wp-swiper-field' ).data( 'field' ) || this.name || this.id ) + ':' + device,
					page: $( this ).closest( '.wp-swiper-page' ).data( 'page' ) || currentPage(),
					device: device
				} );
			} );

			// The fields whose values went back or forth: shown, lit for a moment, in view.
			var markChanged = function ( before, after ) {
				var $fields = $();
				eachSetting( function ( el, key ) {
					if ( before.values[ key ] === after.values[ key ] && before.values[ key + '@url' ] === after.values[ key + '@url' ] ) {
						return;
					}
					var $field = $( el ).closest( '.wp-swiper-field' );
					var device = $( el ).closest( '[data-device]' ).data( 'device' );
					if ( device ) {
						showFieldDevice( $field, device );
						// Sizes side by side: tablet and mobile shown.
						if ( device !== 'desktop' ) {
							$( el ).closest( '.wp-swiper-inline-devices' ).addClass( 'is-open' )
								.find( '.wp-swiper-inline-devices__toggle' ).attr( 'aria-expanded', 'true' );
						}
					}
					$fields = $fields.add( $field );
				} );
				syncOrderButtons();
				$fields = $fields.filter( ':visible' );
				clearTimeout( markTimer );
				$( '.wp-swiper-field.is-restored' ).removeClass( 'is-restored' );
				if ( ! $fields.length ) {
					return;
				}
				// Reflow: the light starts again on a field lit already.
				void $fields[ 0 ].offsetWidth;
				$fields.addClass( 'is-restored' );
				markTimer = setTimeout( function () {
					$fields.removeClass( 'is-restored' );
				}, 1600 );
				var box = $fields[ 0 ].getBoundingClientRect();
				if ( box.top < 40 || box.bottom > window.innerHeight ) {
					$fields[ 0 ].scrollIntoView( { block: 'center', behavior: 'smooth' } );
				}
			};

			// Only values the preview takes at once changed (an edit made in it).
			var previewOnly = function ( before, after ) {
				if ( before.layouts !== after.layouts || before.effect !== after.effect ) {
					return false;
				}
				return Object.keys( after.values ).every( function ( key ) {
					if ( before.values[ key ] === after.values[ key ] ) {
						return true;
					}
					var name = /^wp_swiper_settings\[(.+)\]#\d+$/.exec( key );
					return !! name && PREVIEW_FIELDS.indexOf( name[ 1 ].replace( /_(tablet|mobile)$/, '' ) ) !== -1;
				} );
			};

			// A device's value as the slider takes it: its own, else the larger screen's.
			var resolved = function ( name, device ) {
				for ( var i = DEVICES.indexOf( device ); i >= 0; i-- ) {
					var value = input( name, DEVICES[ i ] ).val() || '';
					if ( value !== '' ) {
						return value;
					}
				}
				return '';
			};
			var idsOf = function ( text ) {
				return text.split( ',' ).map( Number ).filter( function ( id ) {
					return id > 0;
				} );
			};
			// The preview takes the device's values at once; else Update says it is out of date.
			var restorePreview = function ( device, cellsChanged ) {
				var win = previewFrame.contentWindow;
				var center = parseFloat( resolved( 'art_ring_center', device ) );
				var done = false;
				try {
					if ( win && win.wpSwiperRestoreCollage ) {
						done = win.wpSwiperRestoreCollage( {
							device: device,
							order: idsOf( resolved( 'collage_order', device ) ),
							mosaic: resolved( 'collage_mosaic', device ),
							cuts: resolved( 'collage_cuts', device ),
							count: Number( resolved( 'collage_count', device ) ) || 0,
							// Content in a place: where it is (moved with the photos there).
							contentPlace: Number( resolved( 'collage_content_place', device ) ) || 0
						} ) || done;
					}
					if ( win && win.wpSwiperRestoreArt ) {
						done = win.wpSwiperRestoreArt( {
							device: device,
							order: idsOf( resolved( 'art_order', device ) ),
							center: isFinite( center ) ? center : null,
							// SVG shape: the window with Content in a window.
							contentWindow: Number( resolved( 'art_content_window', device ) ) || 0,
							// Grid shapes: its layout (one for every device), only where
							// it changed (none kept, the default one: the preview cannot).
							cells: cellsChanged ? String( $( '#wp-swiper-art_cells' ).val() || '' ) : undefined
						} ) || done;
					}
				} catch ( e ) {}
				if ( ! done ) {
					$update.addClass( 'is-stale' );
				}
			};

			var stepHistory = function ( back ) {
				closeStep();
				if ( baselineTimer ) {
					readBaseline();
				}
				var from = back ? undoSteps : redoSteps;
				if ( ! from.length || null === baseline ) {
					return;
				}
				var step = from.pop();
				var before = baseline;
				( back ? redoSteps : undoSteps ).push( { state: before, at: step.at } );

				restoring = true;
				// Where it was made: its device first (the preview's size, the fields' values)…
				if ( step.at.device && step.at.device !== shared.currentDevice ) {
					setDevice( step.at.device );
					try {
						window.localStorage.setItem( DEVICE_KEY, shared.currentDevice );
					} catch ( e ) {}
				}
				restoreState( step.state );
				// …its page once the values say which pages there are.
				var $item = $menu.filter( '[data-page="' + step.at.page + '"]' ).filter( function () {
					return this.style.display !== 'none';
				} );
				if ( $item.length ) {
					showPage( step.at.page );
					try {
						window.localStorage.setItem( PAGE_KEY, step.at.page );
					} catch ( e ) {}
				}
				startSettingsEditors();
				// What follows the effect without its "change" event (put back quietly).
				applyDev();
				baseline = snapshot();
				restoring = false;

				markChanged( before, baseline );
				if ( previewOnly( before, baseline ) ) {
					restorePreview( step.at.device || shared.currentDevice, before.values[ 'wp_swiper_settings[art_cells]#1' ] !== baseline.values[ 'wp_swiper_settings[art_cells]#1' ] );
				} else {
					$update.addClass( 'is-stale' );
				}
				syncHistory();
			};

			$undo.on( 'click', function () {
				stepHistory( true );
			} );
			$redo.on( 'click', function () {
				stepHistory( false );
			} );

			// The keys (by the key's place: any keyboard layout), on the page and in the preview.
			var historyKey = function ( e ) {
				if ( ! ( e.ctrlKey || e.metaKey ) || e.altKey || ( e.code !== 'KeyZ' && e.code !== 'KeyY' ) ) {
					return;
				}
				var target = e.target || {};
				// Typing in a box: the browser's own undo.
				if ( target.isContentEditable || /^(TEXTAREA|SELECT)$/.test( target.tagName ) ||
					( target.tagName === 'INPUT' && ! /^(checkbox|radio|button|submit|range|file)$/.test( target.type ) ) ) {
					return;
				}
				// A window over the page (Slide info, the media library, a color picker).
				if ( $modal.is( ':visible' ) || $( '.media-modal:visible' ).length || openPickr() ) {
					return;
				}
				e.preventDefault();
				stepHistory( e.code === 'KeyZ' && ! e.shiftKey );
			};
			document.addEventListener( 'keydown', historyKey );

			$( previewFrame ).on( 'load', function () {
				try {
					previewFrame.contentWindow.document.addEventListener( 'keydown', historyKey );
				} catch ( e ) {}
			} );

			syncHistory();
			readBaseline();
			// Again once the rest of the page has set its fields.
			setTimeout( readBaseline );

			$order.on( 'click', '.wp-swiper-shuffle', function () {
				var $input = input( 'collage_order', orderDevice() );
				var ids = $list.find( 'input[name="wp_swiper_slides[id][]"]' ).map( function () {
					return Number( this.value ) || 0;
				} ).get().filter( function ( id ) {
					return id > 0;
				} );
				var shuffled;
				var tries = 0;
				do {
					shuffled = ids.slice();
					for ( var i = shuffled.length - 1; i > 0; i-- ) {
						var j = Math.floor( Math.random() * ( i + 1 ) );
						var swap = shuffled[ i ];
						shuffled[ i ] = shuffled[ j ];
						shuffled[ j ] = swap;
					}
				} while ( shuffled.length > 1 && shuffled.join( ',' ) === $input.val() && ++tries < 10 );
				record( orderDevice(), $order.closest( '.wp-swiper-page' ).data( 'page' ) );
				setOrder( orderDevice(), shuffled );
				updatePreview();
			} );

			// Reset: the order and the mosaic or template sizes, and that they were set by hand.
			$order.on( 'click', '.wp-swiper-order-reset', function () {
				record( orderDevice(), $order.closest( '.wp-swiper-page' ).data( 'page' ) );
				setOrder( orderDevice(), [] );
				setMosaic( orderDevice(), '' );
				setCuts( orderDevice(), '' );
				setByHand( orderDevice(), false );
				updatePreview();
			} );

			// From the preview's frame only: the order a photo dragged there made,
			// or the mosaic a line dragged there made, for the device it shows. The
			// frame shows it already: no update needed.
			window.addEventListener( 'message', function ( e ) {
				var data = e.data || {};
				if ( e.source !== previewFrame.contentWindow || DEVICES.indexOf( data.device ) === -1 ) {
					return;
				}
				if ( /^collage-(order|mosaic|scatter|free|count)$/.test( data.wpSwiper ) ) {
					record( data.device );
				}
				// Scattered: a place taken out there, one photo fewer on screen on that device.
				if ( data.wpSwiper === 'collage-count' && data.count >= 1 && data.count <= 30 ) {
					input( 'collage_count', data.device ).val( Math.round( data.count ) );
					syncResponsive( field( 'collage_count' ) );
				}
				// Content in a place dragged onto a photo there: its new place.
				if ( data.wpSwiper === 'collage-content' && data.place >= 1 && data.place <= 30 ) {
					input( 'collage_content_place', data.device ).val( Math.round( data.place ) );
					syncResponsive( field( 'collage_content_place' ) );
				}
				if ( data.wpSwiper === 'collage-order' && Array.isArray( data.ids ) ) {
					setOrder( data.device, data.ids.map( Number ).filter( function ( id ) {
						return id > 0;
					} ) );
					setByHand( data.device, true );
				} else if ( data.wpSwiper === 'collage-mosaic' && typeof data.mosaic === 'string' && /^[VH0-9.,[\]]{1,4000}$/.test( data.mosaic ) ) {
					// Checked again when saved (WP_Swiper_Settings::sanitize_mosaic(),
					// sanitize_template_cuts()): a template's sizes apart from Random mosaic's.
					if ( TEMPLATES.indexOf( data.template ) !== -1 ) {
						setCuts( data.device, data.template + ':' + data.mosaic );
					} else {
						setMosaic( data.device, data.mosaic );
					}
					setByHand( data.device, true );
				} else if ( data.wpSwiper === 'collage-scatter' && typeof data.layout === 'string' && /^[0-9.x;,TRBLao]{1,4000}$/.test( data.layout ) ) {
					// Scattered's layout, a photo's edge dragged or two photos swapped
					// there; checked again when saved (WP_Swiper_Settings::sanitize_scatter()).
					setCuts( data.device, 'scatter:' + data.layout );
					setByHand( data.device, true );
				} else if ( data.wpSwiper === 'collage-free' && typeof data.layout === 'string' && /^[0-9.,;-]{1,4000}$/.test( data.layout ) ) {
					// Freeform's layout, a photo moved or resized there; checked again
					// when saved (WP_Swiper_Settings::sanitize_freeform()).
					setCuts( data.device, 'freeform:' + data.layout );
					setByHand( data.device, true );
				}
			} );

			// Art (apart from the collage above): the order a photo dragged onto
			// another piece in the preview made, for the device it shows, kept in
			// the hidden art_order values (class-wp-swiper-art.php). No update needed.
			// And the Ring's middle circle size its edge dragged there made (art_ring_center).
			window.addEventListener( 'message', function ( e ) {
				var data = e.data || {};
				if ( e.source !== previewFrame.contentWindow || DEVICES.indexOf( data.device ) === -1 ) {
					return;
				}
				var suffix = data.device === 'desktop' ? '' : '_' + data.device;
				if ( data.wpSwiper === 'art-order' || data.wpSwiper === 'art-center' || data.wpSwiper === 'art-cells' ) {
					record( data.device );
				}
				// Grid shapes: the layout drawn there (one for every device; checked again when saved).
				if ( data.wpSwiper === 'art-cells' && typeof data.cells === 'string' && data.cells.length <= 2000 ) {
					$( '#wp-swiper-art_cells' ).val( data.cells );
					return;
				}
				if ( data.wpSwiper === 'art-order' && Array.isArray( data.ids ) ) {
					$( '#wp-swiper-art_order' + suffix ).val( data.ids.map( Number ).filter( function ( id ) {
						return id > 0;
					} ).join( ',' ) );
				} else if ( data.wpSwiper === 'art-content' && data.window >= 1 && data.window <= 100 ) {
					// SVG shape, Content in a window dragged onto another window (with
					// the order just before it: one step back with Undo).
					input( 'art_content_window', data.device ).val( Math.round( data.window ) );
					syncResponsive( field( 'art_content_window' ) );
				} else if ( data.wpSwiper === 'art-center' && isFinite( data.center ) ) {
					// Checked again when saved (20–75).
					$( '#wp-swiper-art_ring_center' + suffix ).val( Math.round( Math.max( 20, Math.min( 75, Number( data.center ) ) ) * 10 ) / 10 );
				}
			} );

			// Changes since the last update: the button says so.
			$( '#wp-swiper-slides, #wp-swiper-settings' ).on( 'input change', 'input, select', function () {
				$update.addClass( 'is-stale' );
			} );
			$list.on( 'sortupdate', function () {
				$update.addClass( 'is-stale' );
			} );

			// Another device: the frame takes its size; the slider follows by itself.
			deviceListeners.push( fitPreview );

			$( window ).on( 'resize', fitPreview );
			if ( 'ResizeObserver' in window ) {
				new ResizeObserver( fitPreview ).observe( $stage[ 0 ] );
			}
			fitPreview();
			updatePreview();
		}
	};
}( jQuery ) );
