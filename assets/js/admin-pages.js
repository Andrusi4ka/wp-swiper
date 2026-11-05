/**
 * WP Swiper slider edit screen: the settings pages and their menu, the
 * card of what the effect can do, the settings' text editors, the Caption
 * and Description pages' boxes, the per-device values and icons, and the
 * device the whole editor shows. A part of admin.js, run by it (see there).
 */
( function ( $ ) {
	'use strict';

	var parts = ( window.wpSwiperEditor = window.wpSwiperEditor || { parts: {} } ).parts;

	/*
	 * The settings pages, their menu and the effect's card; the text editors;
	 * per-device values; the device shown.
	 */
	parts.pages = function ( shared ) {
		// From the parts before this one (shared).
		var beforeSend = shared.beforeSend;
		var $modal = shared.$modal;
		var showFontDevice = shared.showFontDevice;
		var slidesWith = shared.slidesWith;
		var startEditor = shared.startEditor;
		var syncFields = shared.syncFields;
		// For the parts after it.
		shared.showPage = showPage;
		shared.startSettingsEditors = startSettingsEditors;
		shared.saveSettingsEditors = saveSettingsEditors;
		shared.syncCaptions = syncCaptions;
		shared.syncDescriptions = syncDescriptions;
		shared.syncResponsive = syncResponsive;
		shared.showFieldDevice = showFieldDevice;
		shared.syncTiles = syncTiles;
		shared.setDevice = setDevice;
		shared.shareDevice = shareDevice;

		// The settings menu: one page in view beside it, the one chosen last kept
		// for the next visit (this browser only).
		var PAGE_KEY = 'wpSwiperPage';
		shared.PAGE_KEY = PAGE_KEY;
		var $menu = $( '.wp-swiper-menu__item' );
		shared.$menu = $menu;

		function showPage( page ) {
			if ( ! page ) {
				return;
			}
			$menu.each( function () {
				$( this ).attr( 'aria-current', $( this ).data( 'page' ) === page ? 'true' : 'false' );
			} );
			$( '.wp-swiper-page' ).each( function () {
				this.hidden = $( this ).data( 'page' ) !== page;
			} );
		}

		$menu.on( 'click', function () {
			var page = $( this ).data( 'page' );
			showPage( page );
			try {
				window.localStorage.setItem( PAGE_KEY, page );
			} catch ( e ) {}
		} );

		try {
			if ( $menu.filter( '[data-page="' + window.localStorage.getItem( PAGE_KEY ) + '"]' ).length ) {
				showPage( window.localStorage.getItem( PAGE_KEY ) );
			}
		} catch ( e ) {}

		/*
		 * Under the menu: what the chosen effect can do (WP_Swiper_Admin::
		 * effect_notes()), its list shown on a new Effect; open or folded as
		 * it was left (for this browser).
		 */
		var NOTES_KEY = 'wpSwiperNotes';
		var $notes = $( '.wp-swiper-notes' );
		var showNotes = function () {
			var effect = $( '#wp-swiper-effect' ).val();
			$notes.find( '[data-effect]' ).each( function () {
				this.hidden = $( this ).data( 'effect' ) !== effect;
			} );
		};
		$( '#wp-swiper-effect' ).on( 'change', showNotes );
		showNotes();
		try {
			if ( window.localStorage.getItem( NOTES_KEY ) === 'closed' ) {
				$notes.prop( 'open', false );
			}
		} catch ( e ) {}
		$notes.on( 'toggle', function () {
			try {
				window.localStorage.setItem( NOTES_KEY, this.open ? 'open' : 'closed' );
			} catch ( e ) {}
		} );

		/*
		 * A setting edited in WordPress's editor (type "editor": Art's text in the
		 * opening): started the first time it is in view (an editor started out of
		 * view is drawn wrong), as a slide description's (links, lists...). Its
		 * text goes into its textarea before the form is read.
		 */
		function startSettingsEditors() {
			$( '.wp-swiper-settings-editor' ).each( function () {
				if ( this.wpSwiperStarted || ! $( this ).is( ':visible' ) ) {
					return;
				}
				this.wpSwiperStarted = true;
				startEditor( this.id, {
					toolbar1: 'formatselect,bold,italic,underline,forecolor,alignleft,aligncenter,alignright,bullist,numlist,link,unlink,removeformat,undo,redo',
					toolbar2: '',
					block_formats: 'Paragraph=p;Heading 1=h1;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6',
					height: 160,
					// A change marks the form changed, as any other setting.
					setup: function ( instance ) {
						instance.on( 'change keyup', function () {
							instance.save();
							$( '#' + instance.id ).trigger( 'change' );
						} );
					}
				}, 'strong,em,link,ul,ol,li' );
			} );
		}

		function saveSettingsEditors() {
			if ( window.tinymce ) {
				$( '.wp-swiper-settings-editor' ).each( function () {
					var instance = window.tinymce.get( this.id );
					if ( instance ) {
						instance.save();
					}
				} );
			}
		}
		beforeSend( saveSettingsEditors );

		$( '#wp-swiper-settings' ).on( 'change input', 'select, input', syncFields );
		syncFields();
		startSettingsEditors();
		$( '#wp-swiper-settings' ).on( 'change', 'select, input', startSettingsEditors );
		$menu.on( 'click', startSettingsEditors );

		// Caption settings only apply once a slide has a caption, and while the
		// slider shows captions (not as a background, syncFields): the whole box
		// is greyed otherwise, with the note that says which (a disabled fieldset
		// is not sent, so it is enabled again for the submit and saving keeps its
		// values). The slide's window runs it again on Apply, as does a change of
		// the slides. syncFields has run before this point: captionsShown is
		// declared without a value (one would undo that run's).
		var $captionBox = $( 'fieldset[data-section="caption"]' );
		shared.$captionBox = $captionBox;
		var captionsLocked = false;
		// shared.captionsShown: no value until one is set.

		function syncCaptions() {
			var why = ! slidesWith( 'caption' ) ? 'empty' : ( shared.captionsShown === false ? 'unavailable' : '' );
			captionsLocked = !! why;
			$captionBox.prop( 'disabled', captionsLocked ).find( '.wp-swiper-field' ).toggleClass( 'is-disabled', captionsLocked );
			$captionBox.find( '.wp-swiper-section-note' ).each( function () {
				this.hidden = $( this ).data( 'note' ) !== why;
			} );
		}

		beforeSend( function () {
			if ( captionsLocked ) {
				$captionBox.prop( 'disabled', false );
				setTimeout( function () {
					$captionBox.prop( 'disabled', captionsLocked );
				} );
			}
		} );
		syncCaptions();

		/*
		 * Description settings: greyed like Caption's (see above) while no slide
		 * has a description, or while the slider shows none (syncFields), with
		 * the note that says which. syncFields has run it before this point, so
		 * the state is declared without a value (one would undo that run's).
		 */
		var descriptionsLocked;

		function syncDescriptions( why ) {
			var $box = $( 'fieldset[data-section="description"]' );
			descriptionsLocked = !! why;
			$box.prop( 'disabled', descriptionsLocked ).find( '.wp-swiper-field' ).toggleClass( 'is-disabled', descriptionsLocked );
			$box.find( '.wp-swiper-section-note' ).each( function () {
				this.hidden = $( this ).data( 'note' ) !== why;
			} );
		}

		beforeSend( function () {
			if ( descriptionsLocked ) {
				var $box = $( 'fieldset[data-section="description"]' ).prop( 'disabled', false );
				setTimeout( function () {
					$box.prop( 'disabled', descriptionsLocked );
				} );
			}
		} );

		// Responsive fields: desktop / tablet / mobile switcher.
		function syncResponsive( $field ) {
			var $inputs = $field.find( '.wp-swiper-devices__input' );
			var inherited = '';
			// Devices with a value of their own (a field may keep more than one
			// value per device: the collage Order its order and mosaic sizes).
			var owns = {};

			// Empty tablet/mobile inputs show the value they inherit.
			$inputs.each( function () {
				var $input = $( this );
				var device = $input.data( 'device' );
				// Art's SVG shape: a box per device, its value in the input inside.
				var value = $input.is( '.wp-swiper-shape-device' ) ? $input.find( 'input[type="hidden"]' ).val() : $input.val();
				var own = value !== '';

				if ( device !== 'desktop' ) {
					// Numbers only: a select's empty choice just says "Inherit".
					if ( ! $input.is( 'select, .wp-swiper-shape-device' ) ) {
						$input.attr( 'placeholder', inherited );
					}
					owns[ device ] = owns[ device ] || own;
				}
				if ( own ) {
					inherited = value;
				} else if ( device === 'desktop' ) {
					// An empty desktop value says what it means ("As the slides").
					inherited = $input.attr( 'placeholder' ) || '';
				}
			} );
			// A dot on the icon: this device has a value of its own.
			$field.find( '.wp-swiper-devices__btn' ).each( function () {
				var device = $( this ).data( 'device' );
				if ( device !== 'desktop' ) {
					$( this ).toggleClass( 'has-value', !! owns[ device ] );
				}
			} );
		}

		$( '.wp-swiper-field--responsive' ).each( function () {
			syncResponsive( $( this ) );
		} );

		/*
		 * Which device's value a field with device icons shows: its icon marked,
		 * its value (and its unit, where it has one of its own) in view, its
		 * value focused if asked. A field without icons (sizes side by side,
		 * all in view) is left as it is.
		 */
		function showFieldDevice( $field, device, focus ) {
			var $btn = $field.find( '.wp-swiper-devices__btn[data-device="' + device + '"]' );
			if ( ! $btn.length ) {
				return;
			}
			$field.find( '.wp-swiper-devices__btn' ).removeClass( 'is-active' ).attr( 'aria-pressed', 'false' );
			$btn.addClass( 'is-active' ).attr( 'aria-pressed', 'true' );
			var $value = $field.find( '.wp-swiper-devices__input' ).prop( 'hidden', true )
				.filter( '[data-device="' + device + '"]' ).prop( 'hidden', false );
			if ( focus ) {
				$value.first().trigger( 'focus' );
			}
			// A Style shown as icons (tiles): the choice of that device marked.
			if ( $field.hasClass( 'wp-swiper-field--tiles' ) && typeof syncTiles === 'function' ) {
				syncTiles( $field );
			}
		}

		$( '#wp-swiper-settings' )
			// A field's own device icons: which device's value it shows.
			// Every field and the preview follow (shareDevice()); then its value takes the focus.
			.on( 'click', '.wp-swiper-devices__btn', function () {
				var device = $( this ).data( 'device' );
				shareDevice( device );
				showFieldDevice( $( this ).closest( '.wp-swiper-field--responsive' ), device, true );
			} )
			// A size per device side by side: its tablet or mobile value edited, the preview that device.
			.on( 'focusin', '.wp-swiper-inline-devices .wp-swiper-devices__input', function () {
				shareDevice( $( this ).data( 'device' ) );
			} )
			.on( 'input change', '.wp-swiper-devices__input', function () {
				syncResponsive( $( this ).closest( '.wp-swiper-field--responsive' ) );
			} )
			// A size per device side by side: the button shows (or hides) tablet and mobile.
			.on( 'click', '.wp-swiper-inline-devices__toggle', function () {
				var $box = $( this ).closest( '.wp-swiper-inline-devices' );
				var open = ! $box.hasClass( 'is-open' );
				$box.toggleClass( 'is-open', open );
				$( this ).attr( 'aria-expanded', open ? 'true' : 'false' );
				if ( open ) {
					$box.find( '.wp-swiper-devices__input[data-device="tablet"]' ).trigger( 'focus' );
				}
			} );

		/*
		 * A select shown as icons (a field's "tiles": the Collage's and Art's
		 * Style, Text mask's Strips go): the choice of the device shown is marked; on tablet and mobile
		 * the Inherit tile too, and while it inherits, the larger screen's choice
		 * (dashed). A click puts the tile's value into that device's select,
		 * hidden; a field with one value for every screen (Art's) has one select.
		 */
		function tilesSelect( $field, device ) {
			var $one = $field.find( 'select.wp-swiper-tiles__select' );
			return $one.length ? $one : $field.find( '.wp-swiper-devices__input[data-device="' + device + '"]' );
		}

		function syncTiles( $field ) {
			var device = $field.find( '.wp-swiper-devices__btn.is-active' ).data( 'device' ) || 'desktop';
			var value = $field.find( 'select.wp-swiper-tiles__select' ).val() || '';
			var inherited = '';
			$field.find( '.wp-swiper-devices__input' ).each( function () {
				if ( $( this ).data( 'device' ) === device ) {
					value = $( this ).val() || '';
					return false;
				}
				inherited = $( this ).val() || inherited;
			} );
			$field.find( '.wp-swiper-tiles__tile' ).each( function () {
				var tile = $( this ).attr( 'data-value' );
				var chosen = tile === value;
				$( this ).toggleClass( 'is-selected', chosen ).attr( 'aria-checked', chosen ? 'true' : 'false' )
					.toggleClass( 'is-inherited', value === '' && tile !== '' && tile === inherited );
			} );
			$field.find( '.wp-swiper-tiles__tile--inherit' ).prop( 'hidden', device === 'desktop' );
		}

		$( '.wp-swiper-field--tiles' ).each( function () {
			syncTiles( $( this ) );
		} );


		$( '#wp-swiper-settings' )
			// Not a Style to come (greyed, no value).
			.on( 'click', '.wp-swiper-tiles__tile:not(.wp-swiper-tiles__tile--soon)', function () {
				var $field = $( this ).closest( '.wp-swiper-field' );
				var device = $field.find( '.wp-swiper-devices__btn.is-active' ).data( 'device' ) || 'desktop';
				tilesSelect( $field, device ).val( $( this ).attr( 'data-value' ) ).trigger( 'change' );
			} )
			// After the field's own handlers (bound above): its device, its value.
			.on( 'click', '.wp-swiper-field--tiles .wp-swiper-devices__btn', function () {
				syncTiles( $( this ).closest( '.wp-swiper-field' ) );
			} )
			.on( 'change', '.wp-swiper-field--tiles .wp-swiper-devices__input, .wp-swiper-field--tiles select.wp-swiper-tiles__select', function () {
				syncTiles( $( this ).closest( '.wp-swiper-field' ) );
			} );

		/*
		 * The preview's device: the frame takes its size. Kept for the next visit
		 * (this browser only).
		 */
		var DEVICE_KEY = 'wpSwiperDevice';
		shared.DEVICE_KEY = DEVICE_KEY;
		var DEVICES = [ 'desktop', 'tablet', 'mobile' ];
		shared.DEVICES = DEVICES;
		shared.currentDevice = 'desktop';
		// Another device: its values in the fields, and (Collage) the fields of its Style.
		var deviceListeners = [ syncFields ];
		shared.deviceListeners = deviceListeners;
		try {
			if ( DEVICES.indexOf( window.localStorage.getItem( DEVICE_KEY ) ) !== -1 ) {
				shared.currentDevice = window.localStorage.getItem( DEVICE_KEY );
			}
		} catch ( e ) {}

		function setDevice( device ) {
			shared.currentDevice = device;
			$( '.wp-swiper-device-switch .wp-swiper-segmented__btn' ).each( function () {
				$( this ).attr( 'aria-pressed', $( this ).data( 'device' ) === device ? 'true' : 'false' );
			} );
			deviceListeners.forEach( function ( fn ) {
				fn( device );
			} );
		}

		/*
		 * One device for the whole editor: a device chosen anywhere (a field's
		 * icons, a size side by side, a font size in Slide info, the preview's
		 * Desktop / Tablet / Mobile) is the preview's (its frame only: the
		 * values as Update last drew them) and every field shows its value
		 * there. Kept for the next visit (this browser only).
		 */
		function shareDevice( device ) {
			if ( DEVICES.indexOf( device ) === -1 || device === shared.currentDevice ) {
				return;
			}
			setDevice( device );
			try {
				window.localStorage.setItem( DEVICE_KEY, shared.currentDevice );
			} catch ( e ) {}
		}

		deviceListeners.push( function ( device ) {
			$( '#wp-swiper-settings .wp-swiper-field--responsive' ).each( function () {
				showFieldDevice( $( this ), device, false );
			} );
			$modal.find( '.wp-swiper-modal__size' ).each( function () {
				showFontDevice( $( this ), device );
			} );
		} );

		$( '#wp-swiper-settings' ).on( 'click', '.wp-swiper-device-switch .wp-swiper-segmented__btn', function () {
			shareDevice( $( this ).data( 'device' ) );
		} );
		setDevice( shared.currentDevice );
	};
}( jQuery ) );
