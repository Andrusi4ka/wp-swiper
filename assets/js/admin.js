/**
 * WP Swiper slider edit screen: its core (the fields, their locks, each
 * effect's Layout values) and the runner of its parts.
 *
 * Its parts are in files of their own, each a function of `shared` in
 * window.wpSwiperEditor.parts: admin-slides.js (the slides),
 * admin-slide-info.js (the Slide info window, an image's position),
 * admin-colors.js (the color fields and the gradient), admin-pages.js (the
 * pages and menu, per-device values, the device shown), admin-preview.js
 * (the preview, Undo / Redo, what is done in it). They run here once the
 * page is there, in the order they had when they were one function, so
 * each finds what the ones before it set up.
 * What one takes from another is on `shared`: a function or value set
 * before it is read once at its start (var x = shared.x), one set after
 * it or one that changes (currentDevice, layouts…) is read as shared.x
 * each time. A new name another part needs: shared.x = x where it is set.
 */
( function ( $ ) {
	'use strict';

	var parts = ( window.wpSwiperEditor = window.wpSwiperEditor || { parts: {} } ).parts;

	var i18n = window.wpSwiperAdmin || {};
	// Effects that show exactly one slide (WP_Swiper_Settings::SINGLE_SLIDE_EFFECTS).
	var SINGLE_SLIDE_EFFECTS = i18n.singleSlide || [];
	// Effects that can be their block's background (WP_Swiper_Settings::BACKGROUND_EFFECTS).
	var BACKGROUND_EFFECTS = i18n.backgroundEffects || [];
	// The collage's template Styles (resized by the lines between their photos).
	var TEMPLATES = i18n.collageTemplates || [];
	// Effects with a main image that can zoom (WP_Swiper_Settings::ZOOM_EFFECTS).
	var ZOOM_EFFECTS = i18n.zoomEffects || [];
	// Effects whose photos can have a shadow (Image shadow).
	var SHADOW_EFFECTS = i18n.shadowEffects || [];
	// Of those, the ones that keep it as a background (Collage, Art).
	var SHADOW_BACKGROUND_EFFECTS = i18n.shadowBackgroundEffects || [];

	/*
	 * The slides' list and the form: what every part needs first.
	 */
	parts.head = function ( shared ) {
		// For the parts after it.
		shared.beforeSend = beforeSend;
		shared.prepareForm = prepareForm;

		var $list = $( '#wp-swiper-slide-list' );
		shared.$list = $list;
		var $empty = $( '.wp-swiper-empty' );
		shared.$empty = $empty;
		var $hint = $( '.wp-swiper-hint' );
		shared.$hint = $hint;
		var template = $( '#tmpl-wp-swiper-slide' ).html() || '';
		shared.template = template;
		// shared.frame: no value until one is set.

		/*
		 * A slider saved with the old Description Alignment (center or right,
		 * WP_Swiper_Settings::legacy_description_align()): each description's
		 * paragraphs, headings and list items without an alignment of their own
		 * take it, so that it stays once the slider is saved (each one now has
		 * its own, the editor's buttons). Text outside any of them (typed in
		 * the code view) becomes paragraphs first, as it is shown.
		 */
		function alignDescription( html, align ) {
			var box = document.createElement( 'template' );
			box.innerHTML = String( html || '' );
			[].slice.call( box.content.childNodes ).forEach( function ( node ) {
				if ( node.nodeType !== 3 || ! node.textContent.trim() ) {
					return;
				}
				node.textContent.trim().split( /\n\s*\n/ ).forEach( function ( text ) {
					var p = document.createElement( 'p' );
					text.split( '\n' ).forEach( function ( line, i ) {
						if ( i ) {
							p.appendChild( document.createElement( 'br' ) );
						}
						p.appendChild( document.createTextNode( line ) );
					} );
					node.parentNode.insertBefore( p, node );
				} );
				node.parentNode.removeChild( node );
			} );
			var blocks = [];
			[].forEach.call( box.content.children, function ( node ) {
				if ( /^(P|H[1-6])$/.test( node.tagName ) ) {
					blocks.push( node );
				} else if ( /^(UL|OL)$/.test( node.tagName ) ) {
					[].push.apply( blocks, [].filter.call( node.children, function ( item ) {
						return item.tagName === 'LI';
					} ) );
				}
			} );
			blocks.forEach( function ( node ) {
				if ( ! node.style.textAlign ) {
					node.style.textAlign = align;
				}
			} );
			return box.innerHTML;
		}
		if ( [ 'center', 'right' ].indexOf( i18n.legacyDescriptionAlign ) !== -1 ) {
			$list.find( '[name="wp_swiper_slides[description][]"]' ).each( function () {
				if ( this.value.trim() ) {
					this.value = alignDescription( this.value, i18n.legacyDescriptionAlign );
				}
			} );
		}

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
	};

	/*
	 * The settings: fields, their locks, each effect's Layout values.
	 */
	parts.fields = function ( shared ) {
		// From the parts before this one (shared).
		var $list = shared.$list;
		var beforeSend = shared.beforeSend;
		var syncPicker = shared.syncPicker;
		var closeColorPickers = shared.closeColorPickers;
		var syncColorPicks = shared.syncColorPicks;
		var hasDescriptions = shared.hasDescriptions;
		// For the parts after it.
		shared.field = field;
		shared.syncFields = syncFields;

		// Sortable treats items as a row only when they float or are inline;
		// CSS grid items are neither, so it would only react to vertical moves.
		$list.sortable( 'instance' )._isFloating = function () {
			return true;
		};

		// Settings: hide fields that do not apply.
		function field( name ) {
			return $( '.wp-swiper-field[data-field="' + name + '"]' );
		}

		// Every lock below, its state as Undo / Redo keeps it with the values
		// (get(): the state; set( state ): back to it, the fields' values aside).
		var lockStates = [];
		shared.lockStates = lockStates;

		/**
		 * A checkbox that some other setting can make unavailable: disabled (not
		 * hidden) and shown as `shown` (unchecked unless given) while locked,
		 * with the visitor's choice remembered and restored once it is unlocked.
		 * A disabled box is not sent, so before the form is: enabled again with
		 * the visitor's choice, so saving keeps it. Returns lock( bool, shown ):
		 * a lock may show another state than `shown` (two settings that lock the
		 * same box, each its own way).
		 */
		function lockable( name, shown ) {
			var $box = $( '#wp-swiper-' + name );
			var wanted = $box.is( ':checked' );
			var locked = false;
			var showing = !! shown;

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
							$box.prop( { disabled: true, checked: showing } );
						}
					} );
				}
			} );

			lockStates.push( {
				get: function () {
					return [ locked, wanted, showing ];
				},
				set: function ( state ) {
					locked = state[ 0 ];
					wanted = state[ 1 ];
					showing = state[ 2 ];
					$box.prop( 'disabled', locked );
					field( name ).toggleClass( 'is-disabled', locked );
				}
			} );

			return function ( lock, shownNow ) {
				locked = lock;
				showing = undefined === shownNow ? !! shown : !! shownNow;
				$box.prop( { disabled: lock, checked: lock ? showing : wanted } );
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

			lockStates.push( {
				get: function () {
					// A plain array: jQuery's map() would drop the nulls.
					var wanted = [];
					$inputs.each( function () {
						var value = $( this ).data( 'wanted' );
						wanted.push( undefined === value || null === value ? null : String( value ) );
					} );
					return [ locked, wanted ];
				},
				set: function ( state ) {
					locked = state[ 0 ];
					$inputs.each( function ( i ) {
						$( this ).data( 'wanted', null === state[ 1 ][ i ] ? undefined : state[ 1 ][ i ] );
					} );
					setEditable( ! locked );
					$field.toggleClass( 'is-disabled', locked );
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
					shared.syncResponsive( $field );
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
		// Video: the controls show (on) without autoplay, or nobody could start it;
		// waiting for the end needs the slider's autoplay and the video's own;
		// a video that is waited for plays once (no loop).
		var lockVideoControls = lockable( 'video_controls', true );
		var lockVideoWait = lockable( 'video_wait' );
		var lockVideoLoop = lockable( 'video_loop' );
		// Background: its videos play by themselves, muted (nobody can reach controls under the content).
		var lockVideoAutoplay = lockable( 'video_autoplay', true );
		var lockVideoMuted = lockable( 'video_muted', true );
		// A click opens either links or the lightbox: unavailable while any slide
		// has a link, or behind a block's content (Background).
		var lockLightbox = lockable( 'lightbox' );
		// Background: as tall as its block (the values kept, greyed); no hover reaches it.
		var lockHeight = lockableValue( 'height' );
		var lockPause = lockable( 'pause_on_hover' );
		var lockCollagePause = lockable( 'collage_pause' );
		// Zoom: the Slide effect has a main image only with one slide in view or the wider active slide.
		var lockZoom = lockable( 'zoom' );
		var VIDEO_FIELDS = [ 'video_posters', 'video_autoplay', 'video_muted', 'video_loop', 'video_controls', 'video_pause', 'video_wait', 'video_preload' ];

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

		// "Image proportions" of each device (desktop, tablet, mobile), an empty one inherited.
		function slideRatios() {
			var values = [];
			var inherited = 'off';
			field( 'slide_ratio' ).find( '.wp-swiper-devices__input' ).each( function () {
				inherited = $( this ).val() || inherited;
				values.push( inherited );
			} );
			return values;
		}

		// Continuous scroll runs on its own (render.php ignores these for it); so
		// does a background, with the slider's autoplay (render.php forces it).
		var lockSpeed = lockableValue( 'speed' );
		var lockDelay = lockableValue( 'autoplay_delay' );
		var lockLoop = lockable( 'loop', true ); // Both always loop.
		var lockAutoplay = lockable( 'autoplay' );
		var lockNavigation = lockable( 'navigation' );
		var lockPagination = lockableValue( 'pagination', 'none' );
		var lockKeyboard = lockable( 'keyboard' );

		// Any slide with a link (a video slide's is not used).
		function hasLinks() {
			return $list.find( '.wp-swiper-slide:not(.wp-swiper-slide--video) input[name="wp_swiper_slides[link][]"]' ).filter( function () {
				return $.trim( this.value ) !== '';
			} ).length > 0;
		}

		/*
		 * Each effect keeps its own Layout values (WP_Swiper_Settings::layouts()):
		 * on an effect change the fields' values are put aside for the old effect
		 * and the new one's are filled in; all of them are sent back on save.
		 */
		var $layouts = $( '#wp-swiper-layouts' );
		shared.$layouts = $layouts;
		shared.layouts = {};
		try {
			shared.layouts = JSON.parse( $layouts.val() ) || {};
		} catch ( e ) {}
		shared.layoutEffect = $( '#wp-swiper-effect' ).val();

		function layoutInputs() {
			return $( 'fieldset[data-section="layout"]' ).find( 'input[name^="wp_swiper_settings["], select[name^="wp_swiper_settings["], textarea[name^="wp_swiper_settings["]' );
		}

		// "wp_swiper_settings[height_tablet]" -> "height_tablet".
		function storageKey( input ) {
			return input.name.slice( 'wp_swiper_settings['.length, -1 );
		}

		function readLayout() {
			var values = {};
			shared.saveSettingsEditors();
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
					// A settings editor already started shows it too.
					var instance = this.tagName === 'TEXTAREA' && window.tinymce ? window.tinymce.get( this.id ) : null;
					if ( instance ) {
						instance.setContent( $( this ).val() );
					}
				}
			} );
			$( '.wp-swiper-field--responsive' ).each( function () {
				shared.syncResponsive( $( this ) );
			} );
			// An image setting's thumbnail (the one picked stays in its data-url).
			$( '.wp-swiper-field--image input[type="hidden"]' ).each( function () {
				syncPicker( $( this ) );
			} );
			// The fields shown as icons (a Style) mark the value they got.
			$( '.wp-swiper-field--tiles' ).each( function () {
				shared.syncTiles( $( this ) );
			} );
			// The color swatches show it too.
			closeColorPickers();
			syncColorPicks();
		}

		// Bound on the select itself: runs before syncFields, which then shows,
		// hides and locks the fields for the new effect.
		$( '#wp-swiper-effect' ).on( 'change', function () {
			var effect = $( this ).val();
			if ( effect === shared.layoutEffect ) {
				return;
			}
			// The own values, not the fixed ones locked Layout fields show.
			lockDirection( false );
			lockFit( false );
			lockCentered( false );
			lockCubeFaces( false );
			lockShadows( false );
			lockStackRotation( false );
			lockThumbsPerView( false );
			lockHeight( false );
			lockCollagePause( false );
			lockZoom( false );
			shared.layouts[ shared.layoutEffect ] = readLayout();
			if ( shared.layouts[ effect ] ) {
				writeLayout( shared.layouts[ effect ] );
			}
			shared.layoutEffect = effect;
		} );

		// After the locked fields have put their own values back (their submit
		// handlers were bound first).
		beforeSend( function () {
			shared.layouts[ shared.layoutEffect ] = readLayout();
			$layouts.val( JSON.stringify( shared.layouts ) );
		} );

		// Thumbnails: the strip switches the slides, so of Controls the pagination
		// and the keyboard are not used (the arrows sit on the strip; speed, loop
		// and autoplay apply). Those are greyed (disabled controls are not sent,
		// so they are enabled again for the submit and saving keeps their values).
		// Their own class, .is-unused: .is-disabled belongs to the locks above,
		// which run first (syncFields) and may enable a control this then disables again.
		var $controlsBox = $( '#wp-swiper-page-controls' );
		var $controlsUnused = $controlsBox.find( [ 'pagination', 'pagination_position', 'pagination_position_vertical', 'pagination_placement', 'pagination_offset', 'pagination_bullet_width', 'pagination_bullet_height', 'pagination_bullet_radius', 'pagination_bullet_gap', 'pagination_bullet_active', 'pagination_fraction_size', 'pagination_colors', 'pagination_color', 'pagination_inactive_color', 'pagination_inactive_opacity', 'keyboard' ].map( function ( name ) {
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
			// The fields Art hid (below) back first: another effect has its say on them.
			$( '.wp-swiper-field.is-art-hidden' ).removeClass( 'is-art-hidden' ).show();
			var single = SINGLE_SLIDE_EFFECTS.indexOf( effect ) !== -1;

			// Slide effect modes (Layout) that make other settings unused: locked
			// first, so the values read below are the ones that apply.
			var slide = effect === 'slide';
			var autoWidth = slide && $( '#wp-swiper-auto_width' ).is( ':checked' );
			var marquee = slide && $( '#wp-swiper-marquee' ).is( ':checked' );
			// Collage: not a slider; its places crop their photos (render.php does the same).
			var collage = effect === 'collage';
			// The block's background: behind its content, it plays on its own.
			var background = BACKGROUND_EFFECTS.indexOf( effect ) !== -1 && $( '#wp-swiper-background' ).is( ':checked' );
			var still = marquee || background;
			lockSpeed( marquee );
			lockDelay( marquee );
			lockLoop( still );
			// The marquee has its own (off); a background the slider's (on).
			lockAutoplay( still, background && ! marquee );
			lockNavigation( still );
			lockPagination( still );
			lockKeyboard( still );
			lockPause( background );
			lockCollagePause( background );
			lockHeight( background );
			lockLightbox( hasLinks() || background );
			// A background crops its images to fill the block.
			lockFit( autoWidth || collage || background );
			// After the locks on the Controls fields (see syncControls).
			syncControls( effect );

			var autoplay = $( '#wp-swiper-autoplay' ).is( ':checked' );

			// Before reading Direction: locked, it shows the fixed value.
			// Fading slides: the Fade effect, or Thumbnails with the Fade transition.
			var fixedDirection = effect === 'fade' || collage || effect === 'art' || effect === 'text-mask' || ( effect === 'thumbs' && $( '#wp-swiper-thumbs_transition' ).val() === 'fade' );
			lockDirection( fixedDirection );
			var vertical = $( '#wp-swiper-direction' ).val() === 'vertical';
			var cover = $( '#wp-swiper-image_fit' ).val() === 'cover';

			// Height says what it measures: the slides, or the whole vertical slider.
			if ( i18n.heightLabels ) {
				field( 'height' ).find( '.wp-swiper-field__head label' ).text(
					collage ? i18n.heightLabels.collage : ( vertical ? i18n.heightLabels.vertical : ( effect === 'thumbs' ? i18n.heightLabels.thumbs : i18n.heightLabels.horizontal ) )
				);
			}
			lockThumbsPerView( effect === 'thumbs' && thumbsHeightEverywhere() );

			// Layout shows the chosen effect's fields only.
			$( '.wp-swiper-field[data-effects]' ).each( function () {
				$( this ).toggle( $( this ).data( 'effects' ).split( ' ' ).indexOf( effect ) !== -1 );
			} );
			// Direction: not where the slides only fade (Thumbnails with the Fade
			// transition; its other effects are left out above).
			if ( fixedDirection ) {
				field( 'direction' ).hide();
			}

			// Background: its settings once it is on (data-effects above: the effects that can be one).
			[ 'background_class', 'art_bg_size', 'art_bg_position', 'background_overlay_color' ].forEach( function ( name ) {
				field( name ).toggle( background );
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
			field( 'marquee_direction' ).toggle( marquee );
			// Its choices named for the Direction: a vertical strip moves up or down.
			if ( i18n.marqueeDirections ) {
				var directionNames = i18n.marqueeDirections[ vertical ? 'vertical' : 'horizontal' ];
				$( '#wp-swiper-marquee_direction option' ).each( function () {
					if ( directionNames[ this.value ] ) {
						this.textContent = directionNames[ this.value ];
					}
				} );
			}
			// Its separator: the image to pick, the size and the dot's color once chosen.
			var separator = marquee ? $( '#wp-swiper-marquee_separator' ).val() : 'none';
			field( 'marquee_separator' ).toggle( marquee );
			field( 'marquee_separator_image' ).toggle( separator === 'image' );
			field( 'marquee_separator_size' ).toggle( separator !== 'none' );
			field( 'marquee_separator_color' ).toggle( separator === 'dot' );

			// Zoom the main image (data-effects above: the effects that have one).
			// Slide: one slide in view on every device, or the wider active slide
			// (render.php checks the same); otherwise greyed. Its settings once on.
			var zoomEffect = ZOOM_EFFECTS.indexOf( effect ) !== -1;
			var canZoom = zoomEffect && ( ! slide || ( ! marquee && ! autoWidth && ( expand || maxPerView() <= 1 ) ) );
			lockZoom( zoomEffect && ! canZoom );
			var zooming = canZoom && $( '#wp-swiper-zoom' ).is( ':checked' );
			[ 'zoom_direction', 'zoom_amount', 'zoom_origin', 'zoom_duration' ].forEach( function ( name ) {
				field( name ).toggle( zooming );
			} );

			// Effect shadows (Layout, data-effects): not with "Contain", where they
			// would shade the empty band around the image too.
			lockShadows( ! cover );
			// Their color, while they are on.
			field( 'effect_shadow_color' ).toggle( [ 'coverflow', 'cards', 'creative' ].indexOf( effect ) >= 0 && cover && $( '#wp-swiper-effect_shadows' ).is( ':checked' ) );
			// A color's Default of the effect's own (Coverflow's shadow): data-effect-default.
			$( '.wp-swiper-color-pick[data-effect-default]' ).each( function () {
				var own = {};
				try {
					own = JSON.parse( $( this ).attr( 'data-effect-default' ) ) || {};
				} catch ( e ) {}
				$( this ).attr( 'data-default', own[ effect ] || $( this ).attr( 'data-base-default' ) );
			} );
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
			field( 'cube_shadow_color' ).toggle( effect === 'cube' && cubeShadow );
			lockCubeFaces( effect === 'cube' && ! cover );
			// The arrows' color and height, once they are on.
			field( 'navigation_color' ).toggle( $( '#wp-swiper-navigation' ).is( ':checked' ) );
			field( 'navigation_size' ).toggle( $( '#wp-swiper-navigation' ).is( ':checked' ) );
			// Rounded corners: the radius once it is on (as Image border).
			field( 'border_radius' ).toggle( $( '#wp-swiper-border_rounded' ).is( ':checked' ) );
			// Gradient: its colors once it is on.
			field( 'image_gradient' ).toggle( $( '#wp-swiper-image_gradient_on' ).is( ':checked' ) );
			// Image border: its width and color once it is on.
			var border = $( '#wp-swiper-image_border' ).is( ':checked' );
			field( 'image_border_width' ).toggle( border );
			field( 'image_border_color' ).toggle( border );
			// Thumbnails (their effect only): the same, their radius and their frame once on.
			field( 'thumbs_radius' ).toggle( effect === 'thumbs' && $( '#wp-swiper-thumbs_rounded' ).is( ':checked' ) );
			var thumbsFrame = effect === 'thumbs' && $( '#wp-swiper-thumbs_border' ).is( ':checked' );
			field( 'thumbs_border_width' ).toggle( thumbsFrame );
			field( 'thumbs_border_color' ).toggle( thumbsFrame );
			field( 'thumbs_active_color' ).toggle( thumbsFrame );
			// Image shadow: where the photos stand apart (WP_Swiper_Settings::shadow()
			// checks the same), and Fade's one photo: not a background (but Collage and Art,
			// whose photos stand apart there too); Creative:
			// the carousel; Slide: more than one slide in view, of their own
			// widths or a continuous scroll. Its settings once on; Art has no
			// spread (drop-shadow).
			var canShadow = SHADOW_EFFECTS.indexOf( effect ) !== -1 && ( ! background || SHADOW_BACKGROUND_EFFECTS.indexOf( effect ) !== -1 )
				&& ( effect !== 'creative' || carousel )
				&& ( ! slide || marquee || autoWidth || expand || maxPerView() > 1 );
			var shadowOn = canShadow && $( '#wp-swiper-image_shadow' ).is( ':checked' );
			field( 'image_shadow' ).toggle( canShadow );
			[ 'image_shadow_x', 'image_shadow_y', 'image_shadow_blur', 'image_shadow_color' ].forEach( function ( name ) {
				field( name ).toggle( shadowOn );
			} );
			field( 'image_shadow_spread' ).toggle( shadowOn && effect !== 'art' && effect !== 'text-mask' );
			// Continuous scroll: the delay shown greyed (locked above), the pause
			// applies (frontend.js stops the strip where it is).
			field( 'autoplay_delay' ).toggle( autoplay || marquee );
			field( 'pause_on_hover' ).toggle( autoplay || marquee );

			// Video: its page (menu item) only while some slide is a video.
			var hasVideo = $list.find( '.wp-swiper-slide--video' ).length > 0;
			VIDEO_FIELDS.forEach( function ( name ) {
				field( name ).toggle( hasVideo );
			} );
			// Lightbox: video slides in it, while there are some.
			field( 'lightbox_videos' ).toggle( hasVideo && $( '#wp-swiper-lightbox' ).is( ':checked' ) );
			lockVideoAutoplay( background );
			lockVideoMuted( background );
			var videoAutoplay = $( '#wp-swiper-video_autoplay' ).is( ':checked' );
			// Shown on without autoplay; a background has none (off).
			lockVideoControls( ! videoAutoplay || background, ! background );
			lockVideoWait( ! autoplay || marquee || ! videoAutoplay );
			lockVideoLoop( ! $( '#wp-swiper-video_wait' ).prop( 'disabled' ) && $( '#wp-swiper-video_wait' ).is( ':checked' ) );
			// Height "Auto" on a device (its unit, else the larger screen's): its
			// number does not apply there (greyed, kept), as Art's Size. Still
			// read-only everywhere while a background locks it (lockHeight).
			var heightUnit = 'px';
			[ 'desktop', 'tablet', 'mobile' ].forEach( function ( device ) {
				var suffix = device === 'desktop' ? '' : '_' + device;
				heightUnit = $( '#wp-swiper-height_unit' + suffix ).val() || heightUnit;
				$( '#wp-swiper-height' + suffix ).prop( 'readOnly', heightUnit === 'auto' || background ).toggleClass( 'is-unused', heightUnit === 'auto' && ! background );
			} );
			// Fields with their own help text for "Contain".
			$( '.wp-swiper-help-contain' ).each( function () {
				$( this ).prop( 'hidden', cover ).siblings( '.wp-swiper-help' ).prop( 'hidden', ! cover );
			} );
			// Alignment of a whole ("Contain") image, across the slider axis.
			field( 'image_align' ).toggle( ! cover && ! vertical );
			field( 'image_align_vertical' ).toggle( ! cover && vertical );
			// The part kept of a cropped ("Cover") image; a background and a collage
			// always crop (Image fit locked above); slides sized by their images do not.
			field( 'image_position' ).toggle( cover && ! autoWidth );
			// Each image's own position too (render.php uses it on the same terms).
			$list.toggleClass( 'wp-swiper-slides--positions', cover && ! autoWidth );
			// Caption background options, only with a background.
			var captionBg = $( '#wp-swiper-caption_bg' ).is( ':checked' );
			field( 'caption_bg_style' ).toggle( captionBg );
			// One color (fading or solid), or a Custom gradient's own colors.
			var captionCustom = $( '#wp-swiper-caption_bg_style' ).val() === 'custom';
			field( 'caption_bg_color' ).toggle( captionBg && ! captionCustom );
			field( 'caption_bg_gradient' ).toggle( captionBg && captionCustom );
			var lightbox = $( '#wp-swiper-lightbox' ).is( ':checked' );
			// Rounded corners of the image: the radius once on (as the slider's).
			field( 'lightbox_border_rounded' ).toggle( lightbox );
			field( 'lightbox_radius' ).toggle( lightbox && $( '#wp-swiper-lightbox_border_rounded' ).is( ':checked' ) );
			field( 'lightbox_thumbs' ).toggle( lightbox && effect === 'thumbs' );
			field( 'lightbox_thumbs_radius' ).toggle( lightbox && effect === 'thumbs' && $( '#wp-swiper-lightbox_thumbs' ).is( ':checked' ) );

			// Descriptions: under a horizontal slider with a current slide, not as a
			// background (WP_Swiper_Settings::can_describe()); the page is greyed
			// otherwise, and while no slide has one.
			// Art: always under it (Direction does not apply).
			var describable = effect === 'art' || effect === 'text-mask' || ( ! vertical && ! background && ! marquee && ! collage );
			// Captions: none as a background either (WP_Swiper_Settings::can_caption());
			// the Caption page is greyed then, as while no slide has one.
			shared.captionsShown = ! background;
			if ( shared.$captionBox ) {
				shared.syncCaptions();
			}
			var described = hasDescriptions();
			shared.syncDescriptions( ! described ? 'empty' : ( describable ? '' : 'unavailable' ) );
			field( 'description_lightbox' ).toggle( lightbox && describable && described );
			// Where they go, and (under the image) how wide the text is.
			var lightboxText = lightbox && describable && described && $( '#wp-swiper-description_lightbox' ).is( ':checked' );
			field( 'description_lightbox_place' ).toggle( lightboxText );
			var textUnder = lightboxText && $( '#wp-swiper-description_lightbox_place' ).val() === 'under';
			field( 'description_lightbox_width' ).toggle( textUnder );
			field( 'description_lightbox_max' ).toggle( textUnder );
			field( 'description_color' ).toggle( $( '#wp-swiper-description_own_color' ).is( ':checked' ) );

			// Pagination layout: one position field per direction.
			var paginated = $( '#wp-swiper-pagination' ).val() !== 'none';
			field( 'pagination_position' ).toggle( paginated && ! vertical );
			field( 'pagination_position_vertical' ).toggle( paginated && vertical );
			field( 'pagination_placement' ).toggle( paginated );
			field( 'pagination_offset' ).toggle( paginated );
			// Its look: the bullets' or the fraction's, and its own colours once on
			// (the fraction has one colour: the current one's).
			var bullets = paginated && $( '#wp-swiper-pagination' ).val() === 'bullets';
			[ 'pagination_bullet_width', 'pagination_bullet_height', 'pagination_bullet_radius', 'pagination_bullet_gap', 'pagination_bullet_active' ].forEach( function ( name ) {
				field( name ).toggle( bullets );
			} );
			field( 'pagination_fraction_size' ).toggle( paginated && ! bullets );
			field( 'pagination_colors' ).toggle( paginated );
			var ownColors = paginated && $( '#wp-swiper-pagination_colors' ).is( ':checked' );
			field( 'pagination_color' ).toggle( ownColors );
			field( 'pagination_inactive_color' ).toggle( ownColors && bullets );
			field( 'pagination_inactive_opacity' ).toggle( ownColors && bullets );

			// Collage: the fields of the Style of the device being edited (the
			// device switch; an empty tablet / mobile Style inherits), so a
			// template's page has none of the grid's. The swapping's once it is on.
			// Controls are a slider's: none applies, so the page goes (its menu item below).
			var collageStyle = '';
			field( 'collage_layout' ).find( '.wp-swiper-devices__input' ).each( function () {
				var device = $( this ).data( 'device' );
				collageStyle = $( this ).val() || collageStyle;
				return device !== ( shared.currentDevice || 'desktop' );
			} );
			var collageUses = function ( names ) {
				return collage && names.indexOf( collageStyle ) !== -1;
			};
			// Which photo is in which place: every style (its own per device).
			field( 'collage_order' ).toggle( collage );
			// Lines between the photos resize them: Random mosaic and the templates.
			$( '.wp-swiper-preview__hint[data-effect="mosaic"]' ).prop( 'hidden', ! collageUses( [ 'random' ].concat( TEMPLATES ) ) );
			// Scattered: the photos' outer edges.
			$( '.wp-swiper-preview__hint[data-effect="scatter"]' ).prop( 'hidden', ! collageUses( [ 'scatter' ] ) );
			// Freeform: photos moved and resized by hand.
			$( '.wp-swiper-preview__hint[data-effect="freeform"]' ).prop( 'hidden', ! collageUses( [ 'freeform' ] ) );
			// The preview says its photos swap by dragging.
			$( '.wp-swiper-preview__hint[data-effect="collage"]' ).prop( 'hidden', ! collage );
			// The collage's Height: not for the styles as tall as their photos make them.
			field( 'height' ).toggle( ! collage || ! collageUses( [ 'justified', 'masonry' ] ) );
			// Image proportions (Slide effect, data-effects): a horizontal row of slides
			// of one width, not as a background (WP_Swiper_Settings::slide_ratios()).
			// Their custom numbers and Distribution once a device has them.
			var canRatio = slide && ! vertical && ! background && ! autoWidth && ! expand;
			var ratioOn = ( canRatio ? slideRatios() : [] ).filter( function ( value ) {
				return value !== 'off';
			} );
			if ( ! canRatio ) {
				field( 'slide_ratio' ).hide();
			}
			field( 'slide_ratio_custom' ).toggle( ratioOn.indexOf( 'custom' ) !== -1 );
			field( 'slide_distribution' ).toggle( ratioOn.length > 0 );
			// Its places always crop their photos (Cover): no Image fit.
			field( 'image_fit' ).toggle( ! collage );
			field( 'collage_count' ).toggle( collageUses( [ 'grid', 'justified', 'masonry', 'random', 'scatter', 'freeform' ] ) );
			field( 'collage_columns' ).toggle( collageUses( [ 'grid', 'masonry' ] ) );
			field( 'collage_row_height' ).toggle( collageUses( [ 'justified' ] ) );
			field( 'collage_row_exact' ).toggle( collageUses( [ 'justified' ] ) );
			// Rotation: the Styles laid out in the Height (collage.js orient());
			// Fill or Fit once this device's angle (its own or inherited) is not
			// 0, not for Scattered and Freeform (always as large as fits).
			var turnable = collageUses( [ 'grid', 'random', 'scatter', 'freeform' ].concat( TEMPLATES ) );
			var turnFits = collageUses( [ 'grid', 'random' ].concat( TEMPLATES ) );
			var collageAngle = '';
			field( 'collage_rotate' ).find( '.wp-swiper-devices__input' ).each( function () {
				collageAngle = $( this ).val() !== '' ? $( this ).val() : collageAngle;
				return $( this ).data( 'device' ) !== ( shared.currentDevice || 'desktop' );
			} );
			field( 'collage_rotate' ).toggle( turnable );
			field( 'collage_rotate_fit' ).toggle( turnFits && Number( collageAngle ) !== 0 );
			// Content in a place: not Justified rows or Masonry, not a background
			// (render.php collage_content()); its settings while it is on.
			var contentable = collageUses( [ 'grid', 'random', 'scatter', 'freeform' ].concat( TEMPLATES ) ) && ! $( '#wp-swiper-background' ).is( ':checked' );
			var contentOn = contentable && $( '#wp-swiper-collage_content' ).is( ':checked' );
			$( '.wp-swiper-block[data-block="collage_content"]' ).toggle( contentable );
			field( 'collage_content' ).toggle( contentable );
			[ 'collage_content_place', 'collage_content_image', 'collage_content_image_size', 'collage_content_text', 'collage_content_font_size', 'collage_content_padding', 'collage_content_valign', 'collage_content_bg' ].forEach( function ( name ) {
				field( name ).toggle( contentOn );
			} );
			// The preview says the content is dragged as a photo is.
			$( '.wp-swiper-preview__hint[data-effect="collage-content"]' ).prop( 'hidden', ! contentOn );
			var swapping = collage && $( '#wp-swiper-collage_swap' ).is( ':checked' );
			[ 'collage_swap_delay', 'collage_swap_speed', 'collage_pause' ].forEach( function ( name ) {
				field( name ).toggle( swapping );
			} );
			// A class of its own, over the toggles above (which keep their say for the
			// other effects): of the Controls page a Collage uses Photo swapping only.
			$( '#wp-swiper-page-controls .wp-swiper-field' ).not( '[data-effects~="collage"]' ).toggleClass( 'is-not-applicable', collage );

			// Art: the preview says its photos swap by dragging.
			$( '.wp-swiper-preview__hint[data-effect="art"]' ).prop( 'hidden', effect !== 'art' && ! ( effect === 'text-mask' && $( '#wp-swiper-text_mask_photos' ).val() === 'each' ) );
			// Grid shapes: its shapes are drawn there (preview-art.js).
			$( '.wp-swiper-preview__hint[data-effect="art-cells"]' ).prop( 'hidden', effect !== 'art' || $( '#wp-swiper-art_style' ).val() !== 'grid-shapes' );
			// Art (class-wp-swiper-art.php): its own fields only, those of its Style
			// (data-styles); of the rest the Effect, the slider's class and id, the
			// lightbox's (images only, no strip), and the Caption and Description
			// pages (all as the toggles above left them; vertical captions
			// excepted). Last, over the toggles above: none of the others applies to it.
			var ART_SHARED = [ 'effect', 'css_class', 'css_id', 'lightbox', 'lightbox_border_rounded', 'lightbox_radius', 'description_lightbox', 'description_lightbox_place', 'description_lightbox_width', 'description_lightbox_max' ];
			// Text mask is drawn by Art too, as its Style "text" (WP_Swiper_Art::style_of()):
			// the same here, its own fields (data-effects) and no captions (its
			// letters would cut them up), the descriptions kept.
			var textMask = effect === 'text-mask';
			if ( effect === 'art' || textMask ) {
				var artStyle = textMask ? 'text' : $( '#wp-swiper-art_style' ).val();
				var textSections = textMask ? 'fieldset[data-section="description"]' : 'fieldset[data-section="caption"], fieldset[data-section="description"]';
				$( '.wp-swiper-field' ).each( function () {
					var $field = $( this );
					if ( String( $field.data( 'effects' ) || '' ).split( ' ' ).indexOf( effect ) === -1 ) {
						var name = $field.data( 'field' );
						var text = $field.closest( textSections ).length > 0;
						if ( ( ! text || name === 'caption_orientation' ) && ART_SHARED.indexOf( name ) === -1 ) {
							$field.addClass( 'is-art-hidden' ).hide();
						}
						return;
					}
					// Not of this Style: hidden. The others stay as the toggles above
					// left them (data-effects showed them; Image shadow's own fields,
					// for one, only while it is on).
					var styles = $field.data( 'styles' );
					if ( styles && String( styles ).split( ' ' ).indexOf( artStyle ) === -1 ) {
						$field.hide();
					}
				} );
				// Rounded corners: the radius only while it is on (as a slider's).
				if ( ! $( '#wp-swiper-art_rounded' ).is( ':checked' ) ) {
					field( 'art_radius' ).hide();
				}
				// Text mask's strips too (and which end).
				if ( ! $( '#wp-swiper-text_mask_rounded' ).is( ':checked' ) ) {
					field( 'text_mask_radius' ).hide();
					field( 'text_mask_corners' ).hide();
				}
				// Image border: its width and color only while it is on (as a slider's).
				if ( ! $( '#wp-swiper-art_border' ).is( ':checked' ) ) {
					field( 'art_border_width' ).hide();
					field( 'art_border_color' ).hide();
				}
				// Content in a window: not as a background (render
				// leaves it out there); its own settings only while it is on.
				if ( background ) {
					field( 'art_content' ).hide();
				}
				if ( background || ! $( '#wp-swiper-art_content' ).is( ':checked' ) ) {
					[ 'art_content_window', 'art_content_image', 'art_content_image_size', 'art_content_text', 'art_content_font_size', 'art_content_padding', 'art_content_valign', 'art_content_bg' ].forEach( function ( name ) {
						field( name ).hide();
					} );
				}
				// Photo swapping's own settings only while it is on (as the Collage's).
				if ( ! $( '#wp-swiper-art_swap' ).is( ':checked' ) ) {
					[ 'art_swap_target', 'art_swap_delay', 'art_swap_speed', 'art_pause' ].forEach( function ( name ) {
						field( name ).hide();
					} );
				}
				// Text mask: the font's name only with Font "By name".
				if ( textMask && $( '#wp-swiper-text_mask_font' ).val() !== 'name' ) {
					field( 'text_mask_font_name' ).hide();
				}
				// Text mask, strips right or left: the word fills the Height, its
				// letters as large as that leaves (art.js textLayout()): no Letter size.
				if ( textMask && [ 'right', 'left' ].indexOf( $( '#wp-swiper-text_mask_direction' ).val() ) !== -1 ) {
					field( 'text_mask_size' ).hide();
				}
				// Text mask: What changes only with a photo in each letter (its "One
				// photo through all windows" is Photos' to say: not offered there),
				// and only while photos swap; with one photo, as that choice.
				var eachLetter = textMask && $( '#wp-swiper-text_mask_photos' ).val() === 'each';
				var $target = $( '#wp-swiper-art_shape_target' );
				$target.find( 'option[value="one"]' ).prop( { hidden: textMask, disabled: textMask } );
				if ( textMask && ( ! eachLetter || ! $( '#wp-swiper-art_swap' ).is( ':checked' ) ) ) {
					field( 'art_shape_target' ).hide();
				}
				var target = textMask ? ( eachLetter ? ( $target.val() === 'one' ? 'any' : $target.val() ) : 'one' ) : $target.val();
				// SVG shape: the order windows show a new photo in, only where they all
				// change at once.
				if ( [ 'one', 'advance' ].indexOf( target ) === -1 ) {
					field( 'art_shape_appear' ).hide();
				}
				// Opening: its size and content (image and text, either or both) only
				// while it is on (as Content in the middle).
				if ( ! $( '#wp-swiper-art_opening' ).is( ':checked' ) ) {
					[ 'art_ring_opening', 'art_opening_image', 'art_opening_image_size', 'art_opening_text', 'art_opening_font_size', 'art_opening_gap' ].forEach( function ( name ) {
						field( name ).hide();
					} );
				}
				// Height "Auto" on a device (its unit, else the larger screen's): its
				// number does not apply there (greyed, kept), as a slider's.
				var heightUnit = 'px';
				[ 'desktop', 'tablet', 'mobile' ].forEach( function ( device ) {
					var suffix = device === 'desktop' ? '' : '_' + device;
					heightUnit = $( '#wp-swiper-art_height_unit' + suffix ).val() || heightUnit;
					$( '#wp-swiper-art_height' + suffix ).prop( 'readOnly', heightUnit === 'auto' ).toggleClass( 'is-unused', heightUnit === 'auto' );
				} );
				// Text mask, Letter size Auto on a device: its % does not apply there (greyed, kept).
				var sizeUnit = 'auto';
				[ 'desktop', 'tablet', 'mobile' ].forEach( function ( device ) {
					var suffix = device === 'desktop' ? '' : '_' + device;
					sizeUnit = $( '#wp-swiper-text_mask_size_unit' + suffix ).val() || sizeUnit;
					$( '#wp-swiper-text_mask_size' + suffix ).prop( 'readOnly', sizeUnit === 'auto' ).toggleClass( 'is-unused', sizeUnit === 'auto' );
				} );
			}

			// A block titled otherwise for some effects (render_blocks(): Art's for Text mask).
			$( '.wp-swiper-subheading[data-titles]' ).each( function () {
				var titles = $( this ).data( 'titles' ) || {};
				$( this ).text( titles[ effect ] || $( this ).attr( 'data-title' ) );
			} );

			// Shown: not hidden by a toggle, nor as not applicable (above).
			var shown = function () {
				return this.style.display !== 'none' && ! $( this ).hasClass( 'is-not-applicable' );
			};
			// A row of fields goes too once all of them are hidden (its spacing would stay).
			$( '.wp-swiper-field-group' ).each( function () {
				$( this ).toggle( $( this ).children( '.wp-swiper-field' ).filter( shown ).length > 0 );
			} );
			// A block (its title) goes with its last field; the first one left on
			// a page has no line over it (CSS).
			$( '.wp-swiper-block' ).each( function () {
				$( this ).toggle( $( this ).find( '.wp-swiper-field' ).filter( shown ).length > 0 );
			} );
			$( '.wp-swiper-page' ).each( function () {
				$( this ).find( '.wp-swiper-block' ).removeClass( 'is-first' ).filter( function () {
					return this.style.display !== 'none';
				} ).first().addClass( 'is-first' );
			} );
			// So does the menu item of a page with nothing left in it.
			$( '.wp-swiper-page' ).each( function () {
				var empty = $( this ).find( '.wp-swiper-field' ).filter( shown ).length === 0;
				$( '.wp-swiper-menu__item[data-page="' + $( this ).data( 'page' ) + '"]' ).toggle( ! empty );
			} );
			// The page in view lost its item (Thumbnails, another effect chosen):
			// Layout, the nearest one, instead.
			if ( shared.$menu ) {
				var $shown = shared.$menu.filter( function () {
					return this.style.display !== 'none';
				} );
				if ( ! $shown.filter( '[aria-current="true"]' ).length ) {
					shared.showPage( ( $shown.filter( '[data-page="layout"]' ).length ? $shown.filter( '[data-page="layout"]' ) : $shown.first() ).data( 'page' ) );
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
	};

	/*
	 * The shortcode copied; leaving with changes not saved.
	 */
	parts.tail = function ( shared ) {
		// From the parts before this one (shared).
		var prepareForm = shared.prepareForm;

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

		/*
		 * Unsaved changes: leaving the page (closing it, reloading, a link) asks
		 * first. WordPress's own check only knows the title and the content. What
		 * would be saved (prepareForm(), as for the preview: the locked fields and
		 * the Layout values of every effect as they are sent) is compared with
		 * what it was when the page opened or was last saved.
		 */
		var $post = $( '#post' );
		if ( $post.length && $( '#wp-swiper-settings' ).length ) {
			var formState = function () {
				prepareForm();
				return $post.serializeArray().filter( function ( item ) {
					return item.name === 'post_title' || /^wp_swiper_(settings|slides)\[/.test( item.name );
				} ).map( function ( item ) {
					return item.name + '=' + item.value;
				} ).join( '&' );
			};
			var savedState = formState();
			var saving = false;

			$post.on( 'submit', function () {
				saving = true;
				// The submit stopped (a check of WordPress's own): asked again on leaving.
				setTimeout( function () {
					saving = false;
				}, 3000 );
			} );

			$( window ).on( 'beforeunload.wp-swiper', function ( e ) {
				if ( saving || formState() === savedState ) {
					return;
				}
				// Browsers show their own text; this one is for the older ones.
				e.preventDefault();
				e.originalEvent.returnValue = i18n.unsaved || '';
				return i18n.unsaved || '';
			} );
		}
	};

	// The parts in their order, once the page is there; what they share.
	$( function () {
		var shared = {
			i18n: i18n,
			SINGLE_SLIDE_EFFECTS: SINGLE_SLIDE_EFFECTS,
			BACKGROUND_EFFECTS: BACKGROUND_EFFECTS,
			TEMPLATES: TEMPLATES,
			ZOOM_EFFECTS: ZOOM_EFFECTS,
			SHADOW_EFFECTS: SHADOW_EFFECTS,
			SHADOW_BACKGROUND_EFFECTS: SHADOW_BACKGROUND_EFFECTS
		};
		[ 'head', 'slides', 'slideFonts', 'colors', 'slideInfo', 'focus', 'fields', 'pages', 'preview', 'tail' ].forEach( function ( name ) {
			parts[ name ]( shared );
		} );
	} );
}( jQuery ) );
