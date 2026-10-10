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

		// After slides are added or removed.
		function toggleEmpty() {
			var hasSlides = $list.children().length > 0;
			$empty.prop( 'hidden', hasSlides );
			syncCaptions();
			$hint.prop( 'hidden', ! hasSlides );
			syncLightbox();
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

			$( '#post' ).on( 'submit', function () {
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

			$( '#post' ).on( 'submit', function () {
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

		// Single-slide effects show one slide; Fade also only horizontally (render.php does the same).
		var lockPerView = lockableValue( 'slides_per_view', 1 );
		var lockDirection = lockableValue( 'direction', 'horizontal' );
		// "Width from image": the slide has the image's proportions, nothing to crop or fit.
		var lockFit = lockableValue( 'image_fit', 'cover' );
		var lockCentered = lockable( 'centered_slides' );
		var lockCubeFaces = lockable( 'cube_slide_shadows' );
		var lockShadows = lockable( 'effect_shadows' );
		// Cards: no rotation at all without "Rotate cards".
		var lockStackRotation = lockableValue( 'cards_rotate' );

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
			lockPerView( false );
			lockFit( false );
			lockCentered( false );
			lockCubeFaces( false );
			lockShadows( false );
			lockStackRotation( false );
			layouts[ layoutEffect ] = readLayout();
			if ( layouts[ effect ] ) {
				writeLayout( layouts[ effect ] );
			}
			layoutEffect = effect;
		} );

		// After the locked fields have put their own values back (their submit
		// handlers were bound first).
		$( '#post' ).on( 'submit', function () {
			layouts[ layoutEffect ] = readLayout();
			$layouts.val( JSON.stringify( layouts ) );
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

			var autoplay = $( '#wp-swiper-autoplay' ).is( ':checked' );

			// Before reading Direction: locked, it shows the fixed value.
			lockDirection( effect === 'fade' );
			var vertical = $( '#wp-swiper-direction' ).val() === 'vertical';
			var cover = $( '#wp-swiper-image_fit' ).val() === 'cover';

			// Layout shows the chosen effect's fields only, and says which effect.
			$( '.wp-swiper-layout-effect' ).text( $( '#wp-swiper-effect option:selected' ).text() );
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
			// "Width from image" replaces the count; groups only where steps are slides.
			field( 'slides_per_view' ).toggle( ! autoWidth );
			field( 'slides_per_group' ).toggle( slide && ! marquee && ! expand );
			field( 'marquee_speed' ).toggle( marquee );

			// Also the visible slides count of the wider active slide layout.
			// Effects that show one slide: shown as information only, fixed at 1.
			lockPerView( single );
			// Effect shadows (Layout, data-effects): not with "Contain", where they
			// would shade the empty band around the image too.
			lockShadows( ! cover );
			lockStackRotation( effect === 'cards' && ! $( '#wp-swiper-cards_tilt' ).is( ':checked' ) );
			// Creative: the neighbour settings belong to the Carousel style.
			var carousel = effect === 'creative' && $( '#wp-swiper-creative_style' ).val() === 'carousel';
			[ 'creative_neighbours', 'creative_offset', 'creative_scale', 'creative_opacity' ].forEach( function ( name ) {
				field( name ).toggle( carousel );
			} );
			// Cube: the shadow's size and offset with the shadow; face shading
			// not with "Contain" (it would shade the empty band too).
			var cubeShadow = $( '#wp-swiper-cube_shadow' ).is( ':checked' );
			field( 'cube_shadow_offset' ).toggle( effect === 'cube' && cubeShadow );
			field( 'cube_shadow_scale' ).toggle( effect === 'cube' && cubeShadow );
			field( 'cube_shadow_opacity' ).toggle( effect === 'cube' && cubeShadow );
			lockCubeFaces( effect === 'cube' && ! cover );
			field( 'navigation_color' ).toggle( $( '#wp-swiper-navigation' ).is( ':checked' ) );
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
			field( 'lightbox_radius' ).toggle( $( '#wp-swiper-lightbox' ).is( ':checked' ) );

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
		}

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
		$( '#post' ).on( 'submit', function () {
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
					$input.attr( 'placeholder', inherited );
					$field.find( '.wp-swiper-devices__btn[data-device="' + device + '"]' ).toggleClass( 'has-value', own );
				}
				if ( own ) {
					inherited = $input.val();
				}
			} );
		}

		$( '.wp-swiper-field--responsive' ).each( function () {
			syncResponsive( $( this ) );
		} );

		$( '#wp-swiper-settings' )
			.on( 'click', '.wp-swiper-devices__btn', function () {
				var $btn = $( this );
				var $field = $btn.closest( '.wp-swiper-field--responsive' );
				var device = $btn.data( 'device' );

				$field.find( '.wp-swiper-devices__btn' ).removeClass( 'is-active' ).attr( 'aria-pressed', 'false' );
				$btn.addClass( 'is-active' ).attr( 'aria-pressed', 'true' );
				$field.find( '.wp-swiper-devices__input' ).prop( 'hidden', true )
					.filter( '[data-device="' + device + '"]' ).prop( 'hidden', false ).trigger( 'focus' );
			} )
			.on( 'input', '.wp-swiper-devices__input', function () {
				syncResponsive( $( this ).closest( '.wp-swiper-field--responsive' ) );
			} );

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
