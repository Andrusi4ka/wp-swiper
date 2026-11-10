/**
 * WP Swiper slider edit screen: the preview's frame (its device and size,
 * turned, a screen size of one's own, Update, Developer mode, dark screen).
 * A part of admin.js, run by it right before admin-preview.js, which takes
 * from it the frame, Update and the fitting (shared).
 */
( function ( $ ) {
	'use strict';

	var parts = ( window.wpSwiperEditor = window.wpSwiperEditor || { parts: {} } ).parts;

	/*
	 * Preview (WP_Swiper_Preview): the form's slides and settings, saved or
	 * not, posted into the frame when the screen opens, on "Update", and at
	 * once for another Effect or Style (autoUpdate; not on every change).
	 * The frame is drawn at the device's size and
	 * scaled down to the column, so the slider shows that device's settings.
	 */
	parts.frame = function ( shared ) {
		// From the parts before this one (shared).
		var i18n = shared.i18n;
		var $list = shared.$list;
		var prepareForm = shared.prepareForm;

		var $preview = $( '.wp-swiper-preview' );
		if ( ! $preview.length ) {
			return;
		}
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

		/*
		 * A screen size of one's own per device (the two fields in the bar,
		 * as WP Banner's): what is typed applies with Update, as the other
		 * changes; empty, the device's own size (its placeholder). Kept as
		 * typed, [ width or null, height or null ] of the device held upright,
		 * so a tablet or phone turned shows the two swapped and turned back
		 * as typed. Shown in a plain frame (the drawings have their own
		 * proportions). Kept per device for the next visit (this browser
		 * only, not with the slider).
		 */
		var SCREEN_KEY = 'wpSwiperScreens';
		var screenLimits = $preview.data( 'screen-limits' ) || [ [ 240, 3840 ], [ 240, 2160 ] ];
		var screens = {};
		try {
			screens = JSON.parse( window.localStorage.getItem( SCREEN_KEY ) ) || {};
		} catch ( e ) {}
		var draftScreens = $.extend( true, {}, screens );
		var $screenWidth = $preview.find( '.wp-swiper-preview__size-w' );
		var $screenHeight = $preview.find( '.wp-swiper-preview__size-h' );
		// The device's own size, or its size of one's own (applied), upright.
		var ownScreen = function () {
			var own = screens[ shared.currentDevice ];
			var usual = sizes[ shared.currentDevice ];
			return own && ( own[ 0 ] || own[ 1 ] ) ? [ own[ 0 ] || usual[ 0 ], own[ 1 ] || usual[ 1 ] ] : null;
		};
		// The fields: the device's size of one's own as typed, its own size
		// as the placeholder; turned, the two swapped.
		var syncScreenFields = function () {
			var own = draftScreens[ shared.currentDevice ] || [ null, null ];
			var usual = sizes[ shared.currentDevice ];
			var order = isTurned() ? [ 1, 0 ] : [ 0, 1 ];
			[ $screenWidth, $screenHeight ].forEach( function ( $input, i ) {
				var side = order[ i ];
				$input.attr( {
					placeholder: usual[ side ],
					min: screenLimits[ side ][ 0 ],
					max: screenLimits[ side ][ 1 ]
				} ).val( own[ side ] ? own[ side ] : '' );
			} );
		};
		var readScreen = function ( $input, limits ) {
			var value = parseFloat( $input.val() );
			return $input.val() === '' || ! isFinite( value ) ? null : Math.round( Math.min( limits[ 1 ], Math.max( limits[ 0 ], value ) ) );
		};
		var screenInput = function () {
			var turn = isTurned();
			var width = readScreen( turn ? $screenHeight : $screenWidth, screenLimits[ 0 ] );
			var height = readScreen( turn ? $screenWidth : $screenHeight, screenLimits[ 1 ] );
			if ( width === null && height === null ) {
				delete draftScreens[ shared.currentDevice ];
			} else {
				draftScreens[ shared.currentDevice ] = [ width, height ];
			}
			$update.addClass( 'is-stale' );
		};
		$screenWidth.add( $screenHeight ).on( 'input', screenInput ).on( 'change', function () {
			screenInput();
			// Within the limits as they will apply.
			syncScreenFields();
		} ).on( 'keydown', function ( e ) {
			// Enter: Update (not the slider's form sent).
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				screenInput();
				syncScreenFields();
				$update.trigger( 'click' );
			}
		} );
		// Update: the sizes typed apply (and are kept for the next visit).
		var applyScreens = function () {
			screens = $.extend( true, {}, draftScreens );
			try {
				window.localStorage.setItem( SCREEN_KEY, JSON.stringify( screens ) );
			} catch ( e ) {}
			fitPreview();
		};
		var $screenNote = $preview.find( '.wp-swiper-preview__size-note' );
		// Which device's settings a screen this wide takes (WP_Swiper_Preview::device_at()).
		var deviceAt = function ( width ) {
			return width <= 767 ? 'mobile' : ( width <= 1024 ? 'tablet' : 'desktop' );
		};

		var fitPreview = function () {
			var turn = isTurned();
			var own = ownScreen();
			var base = own || sizes[ shared.currentDevice ];
			var size = turn ? [ base[ 1 ], base[ 0 ] ] : base;
			var device = drawings[ shared.currentDevice ];
			var image = $drawings.filter( '[data-device="' + shared.currentDevice + '"]' )[ 0 ];
			// A size of one's own: a plain frame, no drawing.
			var inDevice = ! own && !! ( device && image );
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
			// A size of one's own has a note of its own (below).
			$preview.find( '.wp-swiper-preview__turned' ).each( function () {
				this.hidden = ! turn || !! own || $( this ).data( 'device' ) !== shared.currentDevice;
			} );
			// A size of one's own whose width takes another device's settings.
			var takes = own ? deviceAt( size[ 0 ] ) : shared.currentDevice;
			var names = i18n.deviceNames || {};
			$screenNote.prop( 'hidden', takes === shared.currentDevice || ! i18n.screenNote );
			if ( takes !== shared.currentDevice && i18n.screenNote ) {
				$screenNote.find( '.wp-swiper-preview__size-text' ).text( i18n.screenNote.replace( '%1$s', size[ 0 ] ).replace( '%2$s', names[ takes ] || takes ) );
			}
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
			// The size fields swapped with it.
			syncScreenFields();
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
			// The screen sizes typed apply now (the frame takes its size).
			applyScreens();
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

		// For admin-preview.js (run next).
		shared.previewFrame = previewFrame;
		shared.$update = $update;
		shared.$stage = $stage;
		shared.fitPreview = fitPreview;
		shared.syncScreenFields = syncScreenFields;
		shared.updatePreview = updatePreview;
		shared.applyDev = applyDev;
	};
}( jQuery ) );
