/**
 * WP Swiper slider edit screen.
 */
( function ( $ ) {
	'use strict';

	var i18n = window.wpSwiperAdmin || {};
	// Effects that show exactly one slide (WP_Swiper_Settings::SINGLE_SLIDE_EFFECTS).
	var SINGLE_SLIDE_EFFECTS = i18n.singleSlide || [];

	$( function () {
		var $list = $( '#wp-swiper-slide-list' );
		var $empty = $( '.wp-swiper-empty' );
		var $hint = $( '.wp-swiper-hint' );
		var template = $( '#tmpl-wp-swiper-slide' ).html() || '';
		var frame;

		/*
		 * What must happen before the form's values are read: locked fields put
		 * their own values back and are enabled, the Layout values of every
		 * effect are written out. Run on submit, and before the preview reads
		 * the form (each step locks again on its own right after).
		 */
		var preparers = [];

		function beforeSend( fn ) {
			preparers.push( fn );
		}

		function prepareForm() {
			preparers.forEach( function ( fn ) {
				fn();
			} );
		}

		$( '#post' ).on( 'submit', prepareForm );

		// After slides are added or removed.
		function toggleEmpty() {
			var hasSlides = $list.children().length > 0;
			$empty.prop( 'hidden', hasSlides );
			syncCaptions();
			$hint.prop( 'hidden', ! hasSlides );
			syncLightbox();
			// The preview no longer shows these slides.
			$( '.wp-swiper-preview__update' ).addClass( 'is-stale' );
		}

		function escapeAttr( value ) {
			return String( value ).replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' ).replace( /</g, '&lt;' );
		}

		// Slides: add from media library.
		$( '#wp-swiper-add' ).on( 'click', function ( e ) {
			e.preventDefault();

			if ( ! frame ) {
				frame = wp.media( {
					title: i18n.frameTitle,
					button: { text: i18n.frameButton },
					library: { type: 'image' },
					multiple: 'add'
				} );

				frame.on( 'select', function () {
					frame.state().get( 'selection' ).each( function ( attachment ) {
						var data = attachment.toJSON();
						var sizes = data.sizes || {};
						var thumb = ( sizes.medium || sizes.thumbnail || data ).url;

						$list.append(
							template
								.replace( /\{\{id\}\}/g, escapeAttr( data.id ) )
								.replace( /\{\{thumb\}\}/g, escapeAttr( thumb ) )
						);
					} );

					// Start with an empty selection next time.
					frame.state().get( 'selection' ).reset();
					toggleEmpty();
				} );
			}

			frame.open();
		} );

		// Slides: remove and reorder.
		$list.on( 'click', '.wp-swiper-slide__remove', function () {
			$( this ).closest( '.wp-swiper-slide' ).remove();
			toggleEmpty();
		} );

		$list.sortable( {
			items: '> .wp-swiper-slide',
			placeholder: 'wp-swiper-slide wp-swiper-slide--placeholder',
			cancel: 'input, button, label',
			tolerance: 'pointer',
			forcePlaceholderSize: true
		} );

		// "Open in a new tab": mirror the (unnamed) checkbox into its hidden input.
		$list.on( 'change', '.wp-swiper-slide__new-tab input', function () {
			$( this ).closest( '.wp-swiper-slide' ).find( 'input[name="wp_swiper_slides[new_tab][]"]' ).val( this.checked ? '1' : '0' );
		} );

		// Sortable treats items as a row only when they float or are inline;
		// CSS grid items are neither, so it would only react to vertical moves.
		$list.sortable( 'instance' )._isFloating = function () {
			return true;
		};

		// Settings: hide fields that do not apply.
		function field( name ) {
			return $( '.wp-swiper-field[data-field="' + name + '"]' );
		}

		/**
		 * A checkbox that some other setting can make unavailable: disabled (not
		 * hidden) and shown as `shown` (unchecked unless given) while locked,
		 * with the visitor's choice remembered and restored once it is unlocked.
		 * A disabled box is not sent, so before the form is: enabled again with
		 * the visitor's choice, so saving keeps it. Returns lock( bool ).
		 */
		function lockable( name, shown ) {
			var $box = $( '#wp-swiper-' + name );
			var wanted = $box.is( ':checked' );
			var locked = false;

			// Bound on the checkbox itself so it runs before the delegated syncFields,
			// and on "input" too: a click fires "input" first, and syncFields (which
			// also listens to it) would otherwise restore the old state.
			$box.on( 'input change', function () {
				if ( ! locked ) {
					wanted = this.checked;
				}
			} );

			beforeSend( function () {
				if ( locked ) {
					$box.prop( { disabled: false, checked: wanted } );
					// Locked again should the submit be cancelled.
					setTimeout( function () {
						if ( locked ) {
							$box.prop( { disabled: true, checked: !! shown } );
						}
					} );
				}
			} );

			return function ( lock ) {
				locked = lock;
				$box.prop( { disabled: lock, checked: lock ? !! shown : wanted } );
				field( name ).toggleClass( 'is-disabled', lock );
			};
		}

		/**
		 * A number or select field some other setting fixes at `value`: shown
		 * greyed with that value and not editable (number inputs read-only,
		 * selects disabled), the visitor's own values kept and restored once it
		 * is unlocked. They are also put back, and the selects enabled, before
		 * the form is sent, so saving keeps them. Without a `value` the field
		 * keeps showing its own (greyed: not used). Returns lock( bool ).
		 */
		function lockableValue( name, value ) {
			var $field = field( name );
			var $inputs = $field.find( 'input[type="number"], select' );
			var locked = false;

			function restore() {
				$inputs.each( function () {
					$( this ).val( $( this ).data( 'wanted' ) );
				} );
			}

			// Responsive fields: desktop shows the value; tablet and mobile inherit it.
			function showFixed() {
				if ( undefined === value ) {
					return;
				}
				$inputs.each( function () {
					var device = $( this ).data( 'device' );
					$( this ).val( ! device || device === 'desktop' ? value : '' );
				} );
			}

			function setEditable( editable ) {
				$inputs.filter( 'input' ).prop( 'readOnly', ! editable );
				$inputs.filter( 'select' ).prop( 'disabled', ! editable );
			}

			beforeSend( function () {
				if ( locked ) {
					restore();
					setEditable( true );
					// Locked again should the submit be cancelled.
					setTimeout( function () {
						if ( locked ) {
							showFixed();
							setEditable( false );
						}
					} );
				}
			} );

			return function ( lock ) {
				if ( lock === locked ) {
					return;
				}
				locked = lock;
				if ( lock ) {
					$inputs.each( function () {
						$( this ).data( 'wanted', $( this ).val() );
					} );
					showFixed();
				} else {
					restore();
				}
				setEditable( ! lock );
				$field.toggleClass( 'is-disabled', lock );
				if ( $field.hasClass( 'wp-swiper-field--responsive' ) ) {
					syncResponsive( $field );
				}
			};
		}

		// Fade only slides horizontally (render.php does the same).
		var lockDirection = lockableValue( 'direction', 'horizontal' );
		// "Width from image": the slide has the image's proportions, nothing to crop or fit.
		var lockFit = lockableValue( 'image_fit', 'cover' );
		var lockCentered = lockable( 'centered_slides' );
		var lockCubeFaces = lockable( 'cube_slide_shadows' );
		var lockShadows = lockable( 'effect_shadows' );
		// Cards: no rotation at all without "Rotate cards".
		var lockStackRotation = lockableValue( 'cards_rotate' );
		// Thumbnails: per view sizes nothing where "Thumbnail height" is set on every device.
		var lockThumbsPerView = lockableValue( 'thumbs_per_view' );

		// "Thumbnail height" on every device (empty tablet/mobile inherit; 0 = none).
		function thumbsHeightEverywhere() {
			var all = true;
			var inherited = '';
			field( 'thumbs_height' ).find( '.wp-swiper-devices__input' ).each( function () {
				var value = $( this ).val() === '' ? inherited : $( this ).val();
				inherited = value;
				all = all && Number( value ) > 0;
			} );
			return all;
		}

		// Largest Slides per view across devices (empty tablet/mobile inherit).
		function maxPerView() {
			var max = 0;
			var inherited = 1;
			field( 'slides_per_view' ).find( '.wp-swiper-devices__input' ).each( function () {
				var value = $( this ).val() === '' ? inherited : Number( $( this ).val() );
				inherited = value;
				max = Math.max( max, value );
			} );
			return max;
		}

		// Continuous scroll runs on its own (render.php ignores these for it).
		var lockMarquee = [
			lockableValue( 'speed' ),
			lockableValue( 'autoplay_delay' ),
			lockable( 'loop', true ), // It always loops.
			lockable( 'autoplay' ),
			lockable( 'navigation' ),
			lockableValue( 'pagination', 'none' ),
			lockable( 'keyboard' )
		];

		/*
		 * Each effect keeps its own Layout values (WP_Swiper_Settings::layouts()):
		 * on an effect change the fields' values are put aside for the old effect
		 * and the new one's are filled in; all of them are sent back on save.
		 */
		var $layouts = $( '#wp-swiper-layouts' );
		var layouts = {};
		try {
			layouts = JSON.parse( $layouts.val() ) || {};
		} catch ( e ) {}
		var layoutEffect = $( '#wp-swiper-effect' ).val();

		function layoutInputs() {
			return $( 'fieldset[data-section="layout"]' ).find( 'input[name^="wp_swiper_settings["], select[name^="wp_swiper_settings["]' );
		}

		// "wp_swiper_settings[height_tablet]" -> "height_tablet".
		function storageKey( input ) {
			return input.name.slice( 'wp_swiper_settings['.length, -1 );
		}

		function readLayout() {
			var values = {};
			layoutInputs().each( function () {
				values[ storageKey( this ) ] = this.type === 'checkbox' ? this.checked : $( this ).val();
			} );
			return values;
		}

		function writeLayout( values ) {
			layoutInputs().each( function () {
				var key = storageKey( this );
				if ( ! ( key in values ) ) {
					return;
				}
				if ( this.type === 'checkbox' ) {
					this.checked = !! values[ key ];
					// A lockable box (unlocked for the swap) takes this as the
					// choice it remembers; triggerHandler: not syncFields yet.
					$( this ).triggerHandler( 'input' );
				} else {
					$( this ).val( null === values[ key ] ? '' : String( values[ key ] ) );
				}
			} );
			$( '.wp-swiper-field--responsive' ).each( function () {
				syncResponsive( $( this ) );
			} );
		}

		// Bound on the select itself: runs before syncFields, which then shows,
		// hides and locks the fields for the new effect.
		$( '#wp-swiper-effect' ).on( 'change', function () {
			var effect = $( this ).val();
			if ( effect === layoutEffect ) {
				return;
			}
			// The own values, not the fixed ones locked Layout fields show.
			lockFit( false );
			lockCentered( false );
			lockCubeFaces( false );
			lockShadows( false );
			lockStackRotation( false );
			lockThumbsPerView( false );
			layouts[ layoutEffect ] = readLayout();
			if ( layouts[ effect ] ) {
				writeLayout( layouts[ effect ] );
			}
			layoutEffect = effect;
		} );

		// After the locked fields have put their own values back (their submit
		// handlers were bound first).
		beforeSend( function () {
			layouts[ layoutEffect ] = readLayout();
			$layouts.val( JSON.stringify( layouts ) );
		} );

		// Thumbnails: the strip switches the slides, so of Controls the pagination
		// and the keyboard are not used (the arrows sit on the strip; speed, loop
		// and autoplay apply). Those are greyed (disabled controls are not sent,
		// so they are enabled again for the submit and saving keeps their values).
		// Their own class, .is-unused: .is-disabled belongs to the locks above,
		// which run first (syncFields) and may enable a control this then disables again.
		var $controlsBox = $( '#wp-swiper-page-controls' );
		var $controlsUnused = $controlsBox.find( [ 'pagination', 'pagination_position', 'pagination_position_vertical', 'pagination_placement', 'pagination_offset', 'keyboard' ].map( function ( name ) {
			return '.wp-swiper-field[data-field="' + name + '"]';
		} ).join( ', ' ) );
		var controlsLocked = false;

		function syncControls( effect ) {
			controlsLocked = effect === 'thumbs';
			$controlsUnused.toggleClass( 'is-unused', controlsLocked );
			if ( controlsLocked ) {
				$controlsUnused.find( 'input, select' ).prop( 'disabled', true );
			} else {
				// Not those a lock keeps disabled.
				$controlsUnused.not( '.is-disabled' ).find( 'input, select' ).prop( 'disabled', false );
			}
			$controlsBox.find( '.wp-swiper-section-note' ).prop( 'hidden', ! controlsLocked );
		}

		// Bound after the locks' own submit handlers, which put their values back.
		beforeSend( function () {
			if ( controlsLocked ) {
				$controlsUnused.find( 'input, select' ).prop( 'disabled', false );
				setTimeout( function () {
					$controlsUnused.find( 'input, select' ).prop( 'disabled', controlsLocked );
				} );
			}
		} );

		function syncFields() {
			var effect = $( '#wp-swiper-effect' ).val();
			var single = SINGLE_SLIDE_EFFECTS.indexOf( effect ) !== -1;

			// Slide effect modes (Layout) that make other settings unused: locked
			// first, so the values read below are the ones that apply.
			var slide = effect === 'slide';
			var autoWidth = slide && $( '#wp-swiper-auto_width' ).is( ':checked' );
			var marquee = slide && $( '#wp-swiper-marquee' ).is( ':checked' );
			lockMarquee.forEach( function ( lock ) {
				lock( marquee );
			} );
			lockFit( autoWidth );
			// After the locks on the Controls fields (see syncControls).
			syncControls( effect );

			var autoplay = $( '#wp-swiper-autoplay' ).is( ':checked' );

			// Before reading Direction: locked, it shows the fixed value.
			// Fading slides: the Fade effect, or Thumbnails with the Fade transition.
			lockDirection( effect === 'fade' || ( effect === 'thumbs' && $( '#wp-swiper-thumbs_transition' ).val() === 'fade' ) );
			var vertical = $( '#wp-swiper-direction' ).val() === 'vertical';
			var cover = $( '#wp-swiper-image_fit' ).val() === 'cover';

			// Height says what it measures: the slides, or the whole vertical slider.
			if ( i18n.heightLabels ) {
				field( 'height' ).find( '.wp-swiper-field__head label' ).text(
					vertical ? i18n.heightLabels.vertical : ( effect === 'thumbs' ? i18n.heightLabels.thumbs : i18n.heightLabels.horizontal )
				);
			}
			lockThumbsPerView( effect === 'thumbs' && thumbsHeightEverywhere() );

			// Layout shows the chosen effect's fields only.
			$( '.wp-swiper-field[data-effects]' ).each( function () {
				$( this ).toggle( $( this ).data( 'effects' ).split( ' ' ).indexOf( effect ) !== -1 );
			} );

			// Slide effect options (data-effects above) that depend on each other.
			// Wider active slide: it always crops, so only with Image fit "Cover";
			// not with slides sized by their images or a moving strip. Its size once on.
			var canExpand = slide && cover && ! autoWidth && ! marquee;
			var expand = canExpand && $( '#wp-swiper-expand_active' ).is( ':checked' );
			field( 'expand_active' ).toggle( canExpand );
			field( 'active_width' ).toggle( expand );
			// Centring one slide that fills the slider changes nothing (the wider
			// active slide and slides sized by their images do use it).
			lockCentered( slide && ! autoWidth && ! expand && maxPerView() <= 1 );
			// "Width from image" replaces the count, and effects that always show one
			// slide have none (render.php ignores it there); it is also the visible
			// slides count of the wider active slide layout. Groups only where steps are slides.
			field( 'slides_per_view' ).toggle( ! autoWidth && ! single );
			field( 'slides_per_group' ).toggle( slide && ! marquee && ! expand );
			field( 'marquee_speed' ).toggle( marquee );

			// Effect shadows (Layout, data-effects): not with "Contain", where they
			// would shade the empty band around the image too.
			lockShadows( ! cover );
			lockStackRotation( effect === 'cards' && ! $( '#wp-swiper-cards_tilt' ).is( ':checked' ) );
			// Creative: the neighbour settings belong to the Carousel style.
			var carousel = effect === 'creative' && $( '#wp-swiper-creative_style' ).val() === 'carousel';
			[ 'creative_neighbours', 'creative_offset', 'creative_scale', 'creative_opacity' ].forEach( function ( name ) {
				field( name ).toggle( carousel );
			} );
			// Thumbnails: the numbers of "Custom" proportions, while any device has it.
			var customRatio = effect === 'thumbs' && field( 'thumbs_ratio' ).find( 'select' ).filter( function () {
				return $( this ).val() === 'custom';
			} ).length > 0;
			field( 'thumbs_ratio_custom' ).toggle( customRatio );
			// Cube: the shadow's size and offset with the shadow; face shading
			// not with "Contain" (it would shade the empty band too).
			var cubeShadow = $( '#wp-swiper-cube_shadow' ).is( ':checked' );
			field( 'cube_shadow_offset' ).toggle( effect === 'cube' && cubeShadow );
			field( 'cube_shadow_scale' ).toggle( effect === 'cube' && cubeShadow );
			field( 'cube_shadow_opacity' ).toggle( effect === 'cube' && cubeShadow );
			lockCubeFaces( effect === 'cube' && ! cover );
			// The arrows' color and height, once they are on.
			field( 'navigation_color' ).toggle( $( '#wp-swiper-navigation' ).is( ':checked' ) );
			field( 'navigation_size' ).toggle( $( '#wp-swiper-navigation' ).is( ':checked' ) );
			// Image border: its width and color once it is on.
			var border = $( '#wp-swiper-image_border' ).is( ':checked' );
			field( 'image_border_width' ).toggle( border );
			field( 'image_border_color' ).toggle( border );
			// Continuous scroll: the delay shown greyed (locked above), the pause
			// applies (frontend.js stops the strip where it is).
			field( 'autoplay_delay' ).toggle( autoplay || marquee );
			field( 'pause_on_hover' ).toggle( autoplay || marquee );
			// Height "Auto" (the parent block's) needs no numbers.
			field( 'height' ).toggleClass( 'is-auto', $( '#wp-swiper-height_unit' ).val() === 'auto' );
			// Fields with their own help text for "Contain".
			$( '.wp-swiper-help-contain' ).each( function () {
				$( this ).prop( 'hidden', cover ).siblings( '.wp-swiper-help' ).prop( 'hidden', ! cover );
			} );
			// Alignment of a whole ("Contain") image, across the slider axis.
			field( 'image_align' ).toggle( ! cover && ! vertical );
			field( 'image_align_vertical' ).toggle( ! cover && vertical );
			// Caption background options, only with a background.
			var captionBg = $( '#wp-swiper-caption_bg' ).is( ':checked' );
			[ 'caption_bg_style', 'caption_bg_color', 'caption_bg_opacity' ].forEach( function ( name ) {
				field( name ).toggle( captionBg );
			} );
			var lightbox = $( '#wp-swiper-lightbox' ).is( ':checked' );
			field( 'lightbox_radius' ).toggle( lightbox );
			field( 'lightbox_thumbs' ).toggle( lightbox && effect === 'thumbs' );
			field( 'lightbox_thumbs_radius' ).toggle( lightbox && effect === 'thumbs' && $( '#wp-swiper-lightbox_thumbs' ).is( ':checked' ) );

			// Pagination layout: one position field per direction.
			var paginated = $( '#wp-swiper-pagination' ).val() !== 'none';
			field( 'pagination_position' ).toggle( paginated && ! vertical );
			field( 'pagination_position_vertical' ).toggle( paginated && vertical );
			field( 'pagination_placement' ).toggle( paginated );
			field( 'pagination_offset' ).toggle( paginated );

			// A row of fields goes too once all of them are hidden (its spacing would stay).
			$( '.wp-swiper-field-group' ).each( function () {
				$( this ).toggle( $( this ).children( '.wp-swiper-field' ).filter( function () {
					return this.style.display !== 'none';
				} ).length > 0 );
			} );
			// So does the menu item of a page with nothing left in it.
			$( '.wp-swiper-page' ).each( function () {
				var empty = $( this ).find( '.wp-swiper-field' ).filter( function () {
					return this.style.display !== 'none';
				} ).length === 0;
				$( '.wp-swiper-menu__item[data-page="' + $( this ).data( 'page' ) + '"]' ).toggle( ! empty );
			} );
			// The page in view lost its item (Thumbnails, another effect chosen):
			// Layout, the nearest one, instead.
			if ( $menu ) {
				var $shown = $menu.filter( function () {
					return this.style.display !== 'none';
				} );
				if ( ! $shown.filter( '[aria-current="true"]' ).length ) {
					showPage( ( $shown.filter( '[data-page="layout"]' ).length ? $shown.filter( '[data-page="layout"]' ) : $shown.first() ).data( 'page' ) );
				}
			}

			// Last: the locks above may have changed the values and states shown.
			syncWidgets();
		}

		/*
		 * Buttons over a select (Direction) and sliders beside a number (speed,
		 * delay). The select and the number stay the fields: the locks set them
		 * (without events), so these follow them after every sync.
		 */
		function syncWidgets() {
			$( '.wp-swiper-field--segmented .wp-swiper-segmented' ).each( function () {
				var $select = $( this ).siblings( 'select' );
				$( this ).find( '.wp-swiper-segmented__btn' ).each( function () {
					$( this )
						.attr( 'aria-pressed', $( this ).data( 'value' ) === $select.val() ? 'true' : 'false' )
						.prop( 'disabled', $select.prop( 'disabled' ) );
				} );
			} );
			$( '.wp-swiper-range' ).each( function () {
				var $number = $( this ).siblings( 'input[type="number"]' );
				$( this )
					.val( $number.val() )
					.prop( 'disabled', $number.prop( 'readOnly' ) || $number.prop( 'disabled' ) );
			} );
		}

		$( '#wp-swiper-settings' )
			.on( 'click', '.wp-swiper-field--segmented .wp-swiper-segmented__btn', function () {
				var $select = $( this ).closest( '.wp-swiper-field' ).find( 'select' );
				if ( ! $select.prop( 'disabled' ) && $select.val() !== $( this ).data( 'value' ) ) {
					// syncFields (bound to "change") updates the buttons.
					$select.val( $( this ).data( 'value' ) ).trigger( 'change' );
				}
			} )
			.on( 'input', '.wp-swiper-range', function () {
				$( this ).siblings( 'input[type="number"]' ).val( this.value );
			} );

		// The settings menu: one page in view beside it, the one chosen last kept
		// for the next visit (this browser only).
		var PAGE_KEY = 'wpSwiperPage';
		var $menu = $( '.wp-swiper-menu__item' );

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

		$( '#wp-swiper-settings' ).on( 'change input', 'select, input', syncFields );
		syncFields();

		// A click opens either links or the lightbox: unavailable while any slide has a link.
		var lockLightbox = lockable( 'lightbox' );

		function syncLightbox() {
			lockLightbox( $list.find( 'input[type="url"]' ).filter( function () {
				return $.trim( this.value ) !== '';
			} ).length > 0 );
			syncFields();
		}

		$list.on( 'input change', 'input[type="url"]', syncLightbox );
		syncLightbox();

		// Caption settings only apply once a slide has a caption: the whole box is
		// greyed until then (a disabled fieldset is not sent, so it is enabled
		// again for the submit and saving keeps its values).
		var $captionBox = $( 'fieldset[data-section="caption"]' );
		var captionsLocked = false;

		function syncCaptions() {
			captionsLocked = $list.find( 'input[name="wp_swiper_slides[caption][]"]' ).filter( function () {
				return $.trim( this.value ) !== '';
			} ).length === 0;
			$captionBox.prop( 'disabled', captionsLocked ).find( '.wp-swiper-field' ).toggleClass( 'is-disabled', captionsLocked );
			$captionBox.find( '.wp-swiper-section-note' ).prop( 'hidden', ! captionsLocked );
		}

		$list.on( 'input change', 'input[name="wp_swiper_slides[caption][]"]', syncCaptions );
		beforeSend( function () {
			if ( captionsLocked ) {
				$captionBox.prop( 'disabled', false );
				setTimeout( function () {
					$captionBox.prop( 'disabled', captionsLocked );
				} );
			}
		} );
		syncCaptions();

		// Responsive fields: desktop / tablet / mobile switcher.
		function syncResponsive( $field ) {
			var $inputs = $field.find( '.wp-swiper-devices__input' );
			var inherited = '';

			// Empty tablet/mobile inputs show the value they inherit.
			$inputs.each( function () {
				var $input = $( this );
				var device = $input.data( 'device' );
				var own = $input.val() !== '';

				if ( device !== 'desktop' ) {
					// Numbers only: a select's empty choice just says "Inherit".
					if ( ! $input.is( 'select' ) ) {
						$input.attr( 'placeholder', inherited );
					}
					// A dot on the icon: this device has a value of its own.
					$field.find( '.wp-swiper-devices__btn[data-device="' + device + '"]' ).toggleClass( 'has-value', own );
				}
				if ( own ) {
					inherited = $input.val();
				} else if ( device === 'desktop' ) {
					// An empty desktop value says what it means ("As the slides").
					inherited = $input.attr( 'placeholder' ) || '';
				}
			} );
		}

		$( '.wp-swiper-field--responsive' ).each( function () {
			syncResponsive( $( this ) );
		} );

		$( '#wp-swiper-settings' )
			// A field's own device icons: which device's value it shows.
			.on( 'click', '.wp-swiper-devices__btn', function () {
				var $btn = $( this );
				var $field = $btn.closest( '.wp-swiper-field--responsive' );
				var device = $btn.data( 'device' );

				$field.find( '.wp-swiper-devices__btn' ).removeClass( 'is-active' ).attr( 'aria-pressed', 'false' );
				$btn.addClass( 'is-active' ).attr( 'aria-pressed', 'true' );
				$field.find( '.wp-swiper-devices__input' ).prop( 'hidden', true )
					.filter( '[data-device="' + device + '"]' ).prop( 'hidden', false ).trigger( 'focus' );
			} )
			.on( 'input change', '.wp-swiper-devices__input', function () {
				syncResponsive( $( this ).closest( '.wp-swiper-field--responsive' ) );
			} );

		/*
		 * The preview's device: the frame takes its size. Kept for the next visit
		 * (this browser only).
		 */
		var DEVICE_KEY = 'wpSwiperDevice';
		var DEVICES = [ 'desktop', 'tablet', 'mobile' ];
		var currentDevice = 'desktop';
		var deviceListeners = [];
		try {
			if ( DEVICES.indexOf( window.localStorage.getItem( DEVICE_KEY ) ) !== -1 ) {
				currentDevice = window.localStorage.getItem( DEVICE_KEY );
			}
		} catch ( e ) {}

		function setDevice( device ) {
			currentDevice = device;
			$( '.wp-swiper-device-switch .wp-swiper-segmented__btn' ).each( function () {
				$( this ).attr( 'aria-pressed', $( this ).data( 'device' ) === device ? 'true' : 'false' );
			} );
			deviceListeners.forEach( function ( fn ) {
				fn( device );
			} );
		}

		$( '#wp-swiper-settings' ).on( 'click', '.wp-swiper-device-switch .wp-swiper-segmented__btn', function () {
			setDevice( $( this ).data( 'device' ) );
			try {
				window.localStorage.setItem( DEVICE_KEY, currentDevice );
			} catch ( e ) {}
		} );
		setDevice( currentDevice );

		/*
		 * Preview (WP_Swiper_Preview): the form's slides and settings, saved or
		 * not, posted into the frame when the screen opens and on "Update" only
		 * (not on every change). The frame is drawn at the device's size and
		 * scaled down to the column, so the slider shows that device's settings.
		 */
		var $preview = $( '.wp-swiper-preview' );
		if ( $preview.length ) {
			var $stage = $preview.find( '.wp-swiper-preview__stage' );
			var $update = $preview.find( '.wp-swiper-preview__update' );
			var previewFrame = $preview.find( '.wp-swiper-preview__frame' )[ 0 ];
			var sizes = $preview.data( 'sizes' );

			// At the size of the device being edited (setDevice()).
			var fitPreview = function () {
				var size = sizes[ currentDevice ];
				var width = $stage.width();
				// The whole frame in view: no taller than the window leaves room for.
				var height = Math.max( 300, window.innerHeight - 180 );
				var scale = Math.min( 1, width / size[ 0 ], height / size[ 1 ] );

				previewFrame.style.width = size[ 0 ] + 'px';
				previewFrame.style.height = size[ 1 ] + 'px';
				previewFrame.style.transform = 'scale(' + scale + ')';
				previewFrame.style.left = Math.max( 0, ( width - size[ 0 ] * scale ) / 2 ) + 'px';
				$stage.css( 'height', Math.ceil( size[ 1 ] * scale ) + 'px' );
			};

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
			};

			$update.on( 'click', updatePreview );

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

		// Shortcode: click to copy.
		$( document ).on( 'click', '.wp-swiper-shortcode', function () {
			var input = this;
			input.select();

			if ( navigator.clipboard && input.value !== i18n.copied ) {
				navigator.clipboard.writeText( input.value ).then( function () {
					var original = input.value;
					input.value = i18n.copied;
					setTimeout( function () {
						input.value = original;
					}, 900 );
				} );
			}
		} );
	} );
}( jQuery ) );
