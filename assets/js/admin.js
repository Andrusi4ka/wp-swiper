/**
 * WP Swiper slider edit screen.
 */
( function ( $ ) {
	'use strict';

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

	$( function () {
		var $list = $( '#wp-swiper-slide-list' );
		var $empty = $( '.wp-swiper-empty' );
		var $hint = $( '.wp-swiper-hint' );
		var template = $( '#tmpl-wp-swiper-slide' ).html() || '';
		var frame;

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

		// After slides are added or removed.
		function toggleEmpty() {
			var hasSlides = $list.children().length > 0;
			$empty.prop( 'hidden', hasSlides );
			syncCaptions();
			$hint.prop( 'hidden', ! hasSlides );
			// The Video page, the lightbox lock (links) and what else follows the slides.
			syncFields();
			// The preview no longer shows these slides.
			$( '.wp-swiper-preview__update' ).addClass( 'is-stale' );
			// Nor the Video page its list of posters.
			syncPosters();
			// The preview's Developer mode (none without slides).
			$list.trigger( 'wpswiperslides' );
		}

		function escapeAttr( value ) {
			return String( value ).replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' ).replace( /</g, '&lt;' );
		}

		// A new slide's row from the template.
		function slideRow( id, thumb, title, embed, badge, full ) {
			return template
				.replace( /\{\{id\}\}/g, escapeAttr( id ) )
				.replace( /\{\{thumb\}\}/g, escapeAttr( thumb ) )
				.replace( /\{\{full\}\}/g, escapeAttr( full || '' ) )
				.replace( /\{\{title\}\}/g, escapeAttr( title ) )
				.replace( /\{\{embed\}\}/g, escapeAttr( embed ) )
				.replace( /\{\{badge\}\}/g, escapeAttr( badge ) );
		}

		/**
		 * A YouTube or Vimeo link as its provider and id (as
		 * WP_Swiper_Settings::parse_embed() reads it), or null.
		 */
		function parseEmbed( url ) {
			var match = /^https?:\/\/(?:www\.|m\.)?(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/)|youtube-nocookie\.com\/embed\/|youtu\.be\/)([\w-]{11})/i.exec( url );
			if ( match ) {
				return { provider: 'youtube', id: match[ 1 ] };
			}
			match = /^https?:\/\/(?:www\.)?(?:player\.)?vimeo\.com\/(?:.*?\/)?(\d{6,})/i.exec( url );
			return match ? { provider: 'vimeo', id: match[ 1 ] } : null;
		}

		// Slides: a YouTube / Vimeo video from its link. Its thumbnail: YouTube's
		// at once; Vimeo's once the slider is saved (it comes from Vimeo).
		var $embedUrl = $( '#wp-swiper-embed-url' );
		var $embedError = $( '.wp-swiper-embed-error' );

		function addEmbed() {
			var url = $.trim( $embedUrl.val() );
			var embed = parseEmbed( url );

			$embedError.prop( 'hidden', !! embed || url === '' );
			if ( ! embed ) {
				return;
			}
			// YouTube's 16:9 thumbnail (its larger ones have black bands in the picture).
			var thumb = embed.provider === 'youtube' ? 'https://i.ytimg.com/vi/' + embed.id + '/mqdefault.jpg' : '';
			var $item = $( slideRow( 0, thumb, url, url, embed.provider === 'youtube' ? 'YouTube' : 'Vimeo' ) ).appendTo( $list );

			$item.addClass( 'wp-swiper-slide--video wp-swiper-slide--embed' ).attr( 'data-default-thumb', thumb );
			if ( ! thumb ) {
				$item.find( '.wp-swiper-slide__thumb img' ).prop( 'hidden', true );
			}
			$embedUrl.val( '' );
			toggleEmpty();
		}

		$( '#wp-swiper-embed-add' ).on( 'click', addEmbed );
		$embedUrl.on( 'keydown', function ( e ) {
			// Enter adds it (not the form's submit).
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				addEmbed();
			}
		} ).on( 'input', function () {
			$embedError.prop( 'hidden', true );
		} );

		// A new slide's row for a media library image or video (its JSON).
		function mediaRow( data ) {
			var video = data.type === 'video';
			var sizes = data.sizes || {};
			// A video has no picture of its own until a poster is chosen.
			var thumb = video ? '' : ( sizes.medium || sizes.thumbnail || data ).url;
			// The larger image for the Image position window.
			var full = video ? '' : ( sizes.large || data ).url;
			var $item = $( slideRow( data.id, thumb, data.title || data.filename || '', '', i18n.videoBadge, full ) );
			// Without a poster a video has no thumbnail to go back to.
			$item.attr( 'data-default-thumb', '' );
			if ( video ) {
				$item.addClass( 'wp-swiper-slide--video' ).find( '.wp-swiper-slide__thumb img' ).prop( 'hidden', true );
			}
			return $item;
		}

		// Slides: add from media library.
		$( '#wp-swiper-add' ).on( 'click', function ( e ) {
			e.preventDefault();

			if ( ! frame ) {
				frame = wp.media( {
					title: i18n.frameTitle,
					button: { text: i18n.frameButton },
					library: { type: [ 'image', 'video' ] },
					multiple: 'add'
				} );

				frame.on( 'select', function () {
					frame.state().get( 'selection' ).each( function ( attachment ) {
						mediaRow( attachment.toJSON() ).appendTo( $list );
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

		// Slides: clone. The copy (media, caption, description, fonts, colors,
		// link, position, poster) right after it, the others one place on.
		$list.on( 'click', '.wp-swiper-slide__clone', function () {
			var $slide = $( this ).closest( '.wp-swiper-slide' );
			var $copy = $slide.clone();
			// Values as they are now (a clone keeps a textarea's first one only).
			var fields = $slide.find( 'input, textarea' );
			$copy.find( 'input, textarea' ).each( function ( i ) {
				this.value = fields[ i ].value;
			} );
			$slide.after( $copy );
			toggleEmpty();
			$copy.find( '.wp-swiper-slide__clone' ).trigger( 'focus' );
		} );

		$list.sortable( {
			items: '> .wp-swiper-slide',
			placeholder: 'wp-swiper-slide wp-swiper-slide--placeholder',
			cancel: 'input, button, label',
			tolerance: 'pointer',
			forcePlaceholderSize: true
		} );

		/*
		 * Video slides' posters, chosen on the Video page: one row per video slide
		 * of the Slides box, in its order. A poster is kept in its slide's hidden
		 * input and shown at once as the thumbnail both here and in the slide.
		 */
		var $posters = $( '.wp-swiper-posters__list' );
		var posterFrame;
		var $posterSlide;

		function syncPosters() {
			$posters.empty();
			$list.children( '.wp-swiper-slide' ).each( function ( index ) {
				var $slide = $( this );
				if ( ! $slide.hasClass( 'wp-swiper-slide--video' ) ) {
					return;
				}
				var $img = $slide.find( '.wp-swiper-slide__thumb img' );
				var own = $slide.find( 'input[name="wp_swiper_slides[poster][]"]' ).val() !== '';
				var $row = $( '<li class="wp-swiper-posters__item"></li>' ).data( 'slide', $slide );
				var $thumb = $( '<span class="wp-swiper-posters__thumb"></span>' ).appendTo( $row );

				if ( ! $img.prop( 'hidden' ) && $img.attr( 'src' ) ) {
					$( '<img alt="">' ).attr( 'src', $img.attr( 'src' ) ).appendTo( $thumb );
				} else {
					$thumb.append( '<span class="dashicons dashicons-video-alt3" aria-hidden="true"></span>' );
				}
				$( '<span class="wp-swiper-posters__name"></span>' )
					.append( $( '<strong></strong>' ).text( ( i18n.slideNumber || '%d' ).replace( '%d', index + 1 ) ) )
					.append( $( '<span></span>' ).text( $slide.data( 'title' ) || '' ) )
					.appendTo( $row );
				var $actions = $( '<span class="wp-swiper-posters__actions"></span>' ).appendTo( $row );
				$( '<button type="button" class="button button-small wp-swiper-posters__set"></button>' )
					.text( own ? i18n.posterChange : i18n.posterChoose )
					.appendTo( $actions );
				if ( own ) {
					$( '<button type="button" class="button-link wp-swiper-posters__remove"></button>' ).text( i18n.posterRemove ).appendTo( $actions );
				}
				$posters.append( $row );
			} );
		}

		function setPoster( $slide, id, url ) {
			// Without a poster: the video's own thumbnail again, if it has one.
			url = url || $slide.attr( 'data-default-thumb' ) || '';
			$slide.find( 'input[name="wp_swiper_slides[poster][]"]' ).val( id );
			$slide.find( '.wp-swiper-slide__thumb img' ).attr( 'src', url ).prop( 'hidden', ! url );
			$( '.wp-swiper-preview__update' ).addClass( 'is-stale' );
			syncPosters();
		}

		$posters.on( 'click', '.wp-swiper-posters__set', function () {
			$posterSlide = $( this ).closest( '.wp-swiper-posters__item' ).data( 'slide' );
			if ( ! posterFrame ) {
				posterFrame = wp.media( {
					title: i18n.posterTitle,
					button: { text: i18n.posterButton },
					library: { type: 'image' },
					multiple: false
				} );
				posterFrame.on( 'select', function () {
					var data = posterFrame.state().get( 'selection' ).first().toJSON();
					var sizes = data.sizes || {};
					setPoster( $posterSlide, data.id, ( sizes.medium || sizes.thumbnail || data ).url );
				} );
			}
			posterFrame.open();
		} );

		$posters.on( 'click', '.wp-swiper-posters__remove', function () {
			setPoster( $( this ).closest( '.wp-swiper-posters__item' ).data( 'slide' ), '', '' );
		} );

		// The slides' order changes the numbers.
		$list.on( 'sortupdate', syncPosters );
		syncPosters();

		/*
		 * Image settings (the continuous scroll's separator): the hidden input
		 * keeps the image's ID, its data-url the thumbnail shown; the buttons
		 * pick one from the media library or clear it. "change" on the input
		 * lets syncFields and the preview know.
		 */
		var pickerFrame;
		var $pickerInput;

		function syncPicker( $input ) {
			var url = Number( $input.val() ) > 0 ? $input.attr( 'data-url' ) : '';
			var $picker = $input.siblings( '.wp-swiper-picker' );
			$picker.find( '.wp-swiper-picker__thumb' ).empty().append( url ? $( '<img alt="">' ).attr( 'src', url ) : null );
			$picker.find( '.wp-swiper-picker__choose' ).text( url ? i18n.posterChange : i18n.posterChoose );
			$picker.find( '.wp-swiper-picker__remove' ).prop( 'hidden', ! url );
		}

		$( '#wp-swiper-settings' ).on( 'click', '.wp-swiper-picker__choose', function () {
			$pickerInput = $( this ).closest( '.wp-swiper-field' ).find( 'input[type="hidden"]' );
			if ( ! pickerFrame ) {
				pickerFrame = wp.media( {
					title: i18n.imageTitle,
					button: { text: i18n.imageButton },
					library: { type: 'image' },
					multiple: false
				} );
				pickerFrame.on( 'select', function () {
					var data = pickerFrame.state().get( 'selection' ).first().toJSON();
					var sizes = data.sizes || {};
					$pickerInput.attr( 'data-url', ( sizes.thumbnail || sizes.medium || data ).url ).val( data.id );
					syncPicker( $pickerInput );
					$pickerInput.trigger( 'change' );
				} );
			}
			pickerFrame.open();
		} );

		$( '#wp-swiper-settings' ).on( 'click', '.wp-swiper-picker__remove', function () {
			var $input = $( this ).closest( '.wp-swiper-field' ).find( 'input[type="hidden"]' );
			$input.val( '' );
			syncPicker( $input );
			$input.trigger( 'change' );
		} );

		/*
		 * Art's SVG shape (a field of type svg_shape): the file chosen is read
		 * here, in the browser, and goes into the field as { name, svg }; the
		 * server keeps only its shapes' geometry (WP_Swiper_Svg_Shape) when the
		 * preview updates (at once, below) or the slider is saved. The file is
		 * not uploaded to the media library.
		 */
		var SHAPE_MAX = 1048576;
		function shapeState( $box, text, loaded ) {
			$box.find( '.wp-swiper-shape__summary' ).text( text );
			$box.find( '.wp-swiper-shape__thumb' ).prop( 'hidden', true ).empty();
			$box.find( '.wp-swiper-shape__remove' ).prop( 'hidden', ! loaded );
			$box.find( '.wp-swiper-shape__choose' ).contents().filter( function () {
				return this.nodeType === 3 && $.trim( this.nodeValue ) !== '';
			} ).first().replaceWith( document.createTextNode( ( loaded ? i18n.shapeReplace : i18n.shapeUpload ) + ' ' ) );
		}
		function shapeChanged( $input ) {
			$input.trigger( 'change' );
			$( '.wp-swiper-preview__update' ).trigger( 'click' );
		}
		$( '#wp-swiper-settings' ).on( 'change', '.wp-swiper-shape input[type="file"]', function () {
			var file = this.files && this.files[ 0 ];
			var $box = $( this ).closest( '.wp-swiper-shape' );
			var $input = $box.prevAll( 'input[type="hidden"]' ).first();
			// The same file chosen again is a change too.
			this.value = '';
			if ( ! file ) {
				return;
			}
			if ( file.size > SHAPE_MAX ) {
				$box.find( '.wp-swiper-shape__summary' ).text( i18n.shapeTooBig );
				return;
			}
			var reader = new FileReader();
			reader.onload = function () {
				var text = String( reader.result || '' );
				if ( ! /<svg[\s>]/i.test( text ) ) {
					$box.find( '.wp-swiper-shape__summary' ).text( i18n.shapeNotSvg );
					return;
				}
				$input.val( JSON.stringify( { name: file.name, svg: text } ) );
				shapeState( $box, ( i18n.shapeRead || '%s' ).replace( '%s', file.name ), true );
				shapeChanged( $input );
			};
			reader.readAsText( file );
		} );
		$( '#wp-swiper-settings' ).on( 'click', '.wp-swiper-shape__remove', function () {
			var $box = $( this ).closest( '.wp-swiper-shape' );
			var $input = $box.prevAll( 'input[type="hidden"]' ).first();
			$input.val( '' );
			// Tablet and mobile: back to the larger screen's.
			var device = $box.closest( '.wp-swiper-shape-device' ).data( 'device' );
			shapeState( $box, device && device !== 'desktop' ? i18n.shapeInherit : i18n.shapeNone, false );
			shapeChanged( $input );
		} );

		/*
		 * A slide's caption, description and link: kept in its hidden fields,
		 * edited in one window (WordPress's editor, wp.editor, for the two
		 * texts), whose editors start when it opens and are removed when it
		 * closes, so the slides stay plain fields that can be dragged. "Apply"
		 * puts the values into the slide; saving the slider saves them.
		 */
		var CAPTION_EDITOR = 'wp-swiper-caption-editor';
		var DESCRIPTION_EDITOR = 'wp-swiper-description-editor';
		// Out of the form (its fields are not sent) and of the box, which may be folded.
		var $modal = $( '#wp-swiper-slide-modal' ).appendTo( document.body );
		var $editing = null;

		function slideInput( $slide, name ) {
			return $slide.find( '[name="wp_swiper_slides[' + name + '][]"]' );
		}

		/*
		 * A slide's own font size and weight, for its caption and its description.
		 * A size "20,16," — desktop, tablet, mobile, one field with device icons
		 * that switch it (as a responsive setting); an empty one takes the larger
		 * screen's, before the first one the slider's (a caption: the Caption
		 * page's Font size) or the plugin's 16px. A weight "700", one
		 * for every screen, picked from a list (.wp-swiper-modal__weight); none:
		 * the tag's or the page's. Set in the Slide info window under each editor,
		 * each in its row (.wp-swiper-modal__size[data-prop]).
		 */
		var FONT_PROPS = [ 'caption_size', 'caption_weight', 'description_size', 'description_weight' ];
		var FONT_DEVICES = [ 'desktop', 'tablet', 'mobile' ];

		// A slider saved with one size or weight for all (until 2.8): into the
		// slides without their own, so saving keeps it (render.php uses it until then).
		// Not the captions' size: it is the Caption page's Font size now (WP_Swiper_Settings::get()).
		FONT_PROPS.forEach( function ( prop ) {
			var legacy = $list.attr( 'data-legacy-' + prop.replace( '_', '-' ) ) || '';
			if ( legacy ) {
				$list.children( '.wp-swiper-slide' ).each( function () {
					var $input = slideInput( $( this ), prop );
					if ( ! $input.val() ) {
						$input.val( legacy );
					}
				} );
			}
		} );

		// The same for a caption saved in the slider's old tag (p, h1–h6; "div"
		// needs none): its lines into that tag, as the editor shows them.
		if ( /^(p|h[1-6])$/.test( $list.attr( 'data-legacy-caption-tag' ) || '' ) ) {
			$list.children( '.wp-swiper-slide' ).each( function () {
				var $caption = slideInput( $( this ), 'caption' );
				$caption.val( captionForEditor( $caption.val() ) );
			} );
		}

		function fontControl( prop ) {
			return $( '.wp-swiper-modal__size[data-prop="' + prop + '"]' );
		}

		function fontField( $control, device ) {
			return $control.find( '[data-device="' + device + '"]' );
		}

		/*
		 * The size a slide without its own gets, per device: a caption the
		 * Caption page's Font size (caption_size: empty tablet / mobile take the
		 * larger screen's, empty desktop 16px), a description 16px.
		 */
		function baseFontSizes( prop ) {
			var size = '16';
			return FONT_DEVICES.map( function ( device ) {
				if ( prop === 'caption_size' ) {
					var key = 'caption_size' + ( device === 'desktop' ? '' : '_' + device );
					size = $.trim( $( '[name="wp_swiper_settings[' + key + ']"]' ).val() || '' ) || size;
				}
				return size;
			} );
		}

		// Empty sizes show what they take as their placeholder: the slide's own
		// for a larger screen, else the slider's (baseFontSizes()); a dot on the
		// icon of one with its own (as on a responsive setting).
		function syncFontInherits( $control ) {
			var base = baseFontSizes( $control.data( 'prop' ) );
			var own = '';
			FONT_DEVICES.forEach( function ( device, i ) {
				var $field = fontField( $control, device ).filter( 'input' );
				$field.attr( 'placeholder', own || base[ i ] );
				if ( device !== 'desktop' ) {
					$control.find( '.wp-swiper-devices__btn[data-device="' + device + '"]' ).toggleClass( 'has-value', $field.val() !== '' );
				}
				own = $field.val() || own;
			} );
		}

		// Which device's size the row shows.
		function showFontDevice( $control, device ) {
			$control.find( '.wp-swiper-devices__btn' ).each( function () {
				var on = $( this ).data( 'device' ) === device;
				$( this ).toggleClass( 'is-active', on ).attr( 'aria-pressed', on ? 'true' : 'false' );
			} );
			$control.find( '.wp-swiper-modal__size-input' ).each( function () {
				this.hidden = $( this ).data( 'device' ) !== device;
			} );
		}

		function showFont( prop, value ) {
			var $control = fontControl( prop );
			var values = String( value || '' ).split( ',' );
			var $weight = $control.find( '.wp-swiper-modal__weight' );
			if ( $weight.length ) {
				// An old per-device weight ("700,,400"): its desktop one.
				$weight.val( values[ 0 ] );
				if ( $weight.val() === null ) {
					$weight.val( '' );
				}
				return;
			}
			FONT_DEVICES.forEach( function ( device, i ) {
				fontField( $control, device ).filter( 'input' ).val( values[ i ] || '' );
			} );
			showFontDevice( $control, 'desktop' );
			syncFontInherits( $control );
		}

		// What the row says, as stored ("" for Default); render.php checks it again.
		function readFont( prop ) {
			var $control = fontControl( prop );
			var $weight = $control.find( '.wp-swiper-modal__weight' );
			if ( $weight.length ) {
				return $weight.val() || '';
			}
			var values = FONT_DEVICES.map( function ( device ) {
				var value = parseInt( fontField( $control, device ).filter( 'input' ).val(), 10 );
				return isNaN( value ) ? '' : String( Math.max( 8, Math.min( 200, value ) ) );
			} );
			return values.join( '' ) ? values.join( ',' ) : '';
		}

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
		var COLOR_PICK = '.wp-swiper-color-pick';
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
		}

		// A size's device icons: which device's value the field shows.
		$modal.on( 'click', '.wp-swiper-modal__size .wp-swiper-devices__btn', function () {
			var $control = $( this ).closest( '.wp-swiper-modal__size' );
			showFontDevice( $control, $( this ).data( 'device' ) );
			$control.find( '.wp-swiper-modal__size-input:not([hidden])' ).trigger( 'focus' );
		} );
		$modal.on( 'input change', '.wp-swiper-modal__size-input', function () {
			syncFontInherits( $( this ).closest( '.wp-swiper-modal__size' ) );
		} );

		// Some text, not just tags or the editor's empty paragraph ($.trim takes
		// its no-break space too). DOMParser: the HTML is read, nothing of it
		// loads or runs.
		function hasText( html ) {
			var text = new window.DOMParser().parseFromString( html || '', 'text/html' ).body.textContent || '';
			return $.trim( text ) !== '';
		}

		// Any slide with this text (caption, description).
		function slidesWith( name ) {
			return $list.find( 'textarea[name="wp_swiper_slides[' + name + '][]"]' ).filter( function () {
				return hasText( this.value );
			} ).length > 0;
		}

		function hasDescriptions() {
			return slidesWith( 'description' );
		}

		// The marks under the button: what the slide has; the button says Add or Edit.
		function syncMarks( $slide ) {
			var marks = {
				caption: hasText( slideInput( $slide, 'caption' ).val() ),
				description: hasText( slideInput( $slide, 'description' ).val() ),
				link: ! $slide.hasClass( 'wp-swiper-slide--video' ) && $.trim( slideInput( $slide, 'link' ).val() ) !== ''
			};
			$slide.find( '.wp-swiper-slide__mark' ).each( function () {
				this.hidden = ! marks[ $( this ).data( 'mark' ) ];
			} );
		}

		function editor() {
			return window.wp && window.wp.editor && window.wp.editor.initialize ? window.wp.editor : null;
		}

		// The "Text color" button's palette (class-wp-swiper-palette.php): the
		// theme's colors, black, white and greys, as many rows as they take;
		// "Custom..." under them for any other.
		function textColors() {
			var map = $.isArray( i18n.textColors ) ? i18n.textColors : [];
			if ( ! map.length ) {
				return {};
			}
			var cols = 8;
			return {
				forecolor_map: map,
				forecolor_cols: cols,
				// One more place: TinyMCE's "No color".
				forecolor_rows: Math.ceil( ( map.length / 2 + 1 ) / cols )
			};
		}

		// One of the window's editors; without the visual one (the user's
		// profile turns it off) the code one only.
		function startEditor( id, tinymce, buttons ) {
			if ( editor() ) {
				editor().initialize( id, {
					tinymce: window.tinymce ? $.extend( textColors(), tinymce ) : false,
					quicktags: { buttons: buttons },
					mediaButtons: false
				} );
			}
		}

		function editorValue( id ) {
			return editor() ? editor().getContent( id ) : $( '#' + id ).val();
		}

		/*
		 * A caption's lines are paragraphs or headings (the editor's Format). One
		 * saved before (until 2.8) has none: its text goes into the slider's old
		 * tag (the Caption page's Tag; a paragraph for "div"), each aligned line
		 * (a span shown as a block, WP_Swiper_Settings::sanitize_caption()) into
		 * one of its own, so the Format and alignment buttons show them.
		 */
		function captionForEditor( html ) {
			html = String( html || '' );
			if ( /<(?:p|h[1-6])\b/i.test( html ) || $.trim( html ) === '' ) {
				return html;
			}
			var tag = $list.attr( 'data-legacy-caption-tag' ) || '';
			tag = /^(p|h[1-6])$/.test( tag ) ? tag : 'p';
			// Parsed inert (a template): nothing in it loads or runs.
			var box = document.createElement( 'template' );
			box.innerHTML = html;
			var out = document.createElement( 'div' );
			var line = null;
			[].slice.call( box.content.childNodes ).forEach( function ( node ) {
				var align = node.nodeType === 1 && node.tagName === 'SPAN' && node.style.display === 'block' ? node.style.textAlign : '';
				if ( [ 'left', 'center', 'right' ].indexOf( align ) !== -1 ) {
					// The break before it only ended the line above.
					if ( line && line.lastChild && line.lastChild.nodeName === 'BR' ) {
						line.removeChild( line.lastChild );
					}
					var own = document.createElement( tag );
					own.style.textAlign = align;
					while ( node.firstChild ) {
						own.appendChild( node.firstChild );
					}
					out.appendChild( own );
					line = null;
					return;
				}
				// A break right after an aligned line was only its end.
				if ( ! line && node.nodeType === 1 && node.tagName === 'BR' ) {
					return;
				}
				line = line || out.appendChild( document.createElement( tag ) );
				line.appendChild( node );
			} );
			return out.innerHTML;
		}

		function openSlide( $slide ) {
			$editing = $slide;
			$modal.find( '.wp-swiper-modal__slide' ).text( '— ' + ( i18n.slideNumber || '%d' ).replace( '%d', $slide.index() + 1 ) );
			// Which picture it is about: the slide's own thumbnail, as in the list.
			$replacement = null;
			showModalThumb( $slide );
			$( '#' + CAPTION_EDITOR ).val( captionForEditor( slideInput( $slide, 'caption' ).val() ) );
			$( '#' + DESCRIPTION_EDITOR ).val( slideInput( $slide, 'description' ).val() );
			FONT_PROPS.forEach( function ( prop ) {
				showFont( prop, slideInput( $slide, prop ).val() );
			} );
			COLOR_PROPS.forEach( function ( prop ) {
				showColor( prop, slideInput( $slide, prop ).val() );
			} );
			$( '#wp-swiper-link-input' ).val( slideInput( $slide, 'link' ).val() );
			$( '#wp-swiper-new-tab-input' ).prop( 'checked', slideInput( $slide, 'new_tab' ).val() === '1' );
			// A video slide has no link (a click on it is the player's).
			allowLink( ! $slide.hasClass( 'wp-swiper-slide--video' ) );
			showTab( 'caption' );
			$modal.prop( 'hidden', false );
			$( document.body ).addClass( 'wp-swiper-modal-open' );

			// The caption: paragraph or heading (aligned), the inline styles (text color too); no links (render.php keeps
			// what caption_tags() allows). The first field, so it takes the focus.
			startEditor( CAPTION_EDITOR, {
				toolbar1: 'formatselect,bold,italic,underline,forecolor,alignleft,aligncenter,alignright,removeformat,undo,redo',
				toolbar2: '',
				block_formats: 'Paragraph=p;Heading 1=h1;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6',
				height: 90,
				init_instance_callback: function ( instance ) {
					instance.focus();
				}
			}, 'strong,em' );
			if ( ! window.tinymce ) {
				$( '#' + CAPTION_EDITOR ).trigger( 'focus' );
			}
		}

		/*
		 * The window's tabs (Caption, Description, Link): one part in view,
		 * Caption on opening. The description's editor starts the first time
		 * its tab is shown (an editor started out of view is drawn wrong). A
		 * video slide has no Link tab. A dot on a tab: that part has something
		 * in it (checked on opening and on each switch, the link as it is typed).
		 */
		var $modalTabs = $modal.find( '.wp-swiper-modal__tab' );
		var descriptionStarted = false;

		function markTabs() {
			var filled = {
				caption: hasText( editorValue( CAPTION_EDITOR ) ),
				description: hasText( editorValue( DESCRIPTION_EDITOR ) ),
				link: $.trim( $( '#wp-swiper-link-input' ).val() ) !== ''
			};
			$modalTabs.each( function () {
				$( this ).toggleClass( 'has-value', !! filled[ $( this ).data( 'tab' ) ] );
			} );
		}

		function showTab( tab ) {
			$modalTabs.each( function () {
				var on = $( this ).data( 'tab' ) === tab;
				$( this ).attr( { 'aria-selected': on ? 'true' : 'false', tabindex: on ? null : '-1' } );
			} );
			$modal.find( '.wp-swiper-modal__section[role="tabpanel"]' ).each( function () {
				this.hidden = $( this ).data( 'tab' ) !== tab;
			} );
			closeColorPickers();
			// The description: paragraph or heading (aligned), the inline styles (text color too), links and lists.
			if ( tab === 'description' && $editing && ! descriptionStarted ) {
				descriptionStarted = true;
				startEditor( DESCRIPTION_EDITOR, {
					toolbar1: 'formatselect,bold,italic,underline,forecolor,alignleft,aligncenter,alignright,bullist,numlist,link,unlink,removeformat,undo,redo',
					toolbar2: '',
					block_formats: 'Paragraph=p;Heading 1=h1;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6',
					height: 220
				}, 'strong,em,link,ul,ol,li' );
			}
			markTabs();
		}

		// The Link tab, or none (a video slide): Caption instead if it was in view.
		function allowLink( on ) {
			var $tab = $modalTabs.filter( '[data-tab="link"]' ).prop( 'hidden', ! on );
			if ( ! on && $tab.attr( 'aria-selected' ) === 'true' ) {
				showTab( 'caption' );
			}
		}

		$modal.on( 'click', '.wp-swiper-modal__tab', function () {
			showTab( $( this ).data( 'tab' ) );
		} );
		// The arrows, Home and End move along the tabs shown (as tabs do).
		$modal.on( 'keydown', '.wp-swiper-modal__tab', function ( e ) {
			var steps = { ArrowRight: 1, ArrowLeft: -1 };
			if ( ! ( e.key in steps ) && e.key !== 'Home' && e.key !== 'End' ) {
				return;
			}
			var $shown = $modalTabs.filter( function () {
				return ! this.hidden;
			} );
			var count = $shown.length;
			var at = $shown.index( this );
			var to = e.key === 'Home' ? 0 : ( e.key === 'End' ? count - 1 : ( at + steps[ e.key ] + count ) % count );
			e.preventDefault();
			$shown.eq( to ).trigger( 'focus' );
			showTab( $shown.eq( to ).data( 'tab' ) );
		} );
		$( '#wp-swiper-link-input' ).on( 'input', markTabs );

		/*
		 * The window's Replace photo button (under its title) picks another image
		 * or video from the media library for the slide. Its thumbnail at once,
		 * applied with the rest: a new row for it (as when added) takes the
		 * slide's place with its caption, description, fonts, colors and link;
		 * its own image position and poster are the new media's (none yet). No
		 * way to leave the slide without one: the old media until another is chosen.
		 */
		var $replacement = null;
		var replaceFrame;

		// A row's picture in the window: its image or poster, else a video icon.
		function showModalThumb( $row ) {
			var $img = $row.find( '.wp-swiper-slide__thumb img' );
			var $media = $modal.find( '.wp-swiper-modal__thumb-media' ).empty();
			if ( ! $img.prop( 'hidden' ) && $img.attr( 'src' ) ) {
				$( '<img alt="">' ).attr( 'src', $img.attr( 'src' ) ).appendTo( $media );
			} else {
				$media.append( '<span class="dashicons dashicons-video-alt3"></span>' );
			}
		}

		$modal.on( 'click', '.wp-swiper-modal__replace', function () {
			if ( ! window.wp || ! wp.media ) {
				return;
			}
			if ( ! replaceFrame ) {
				replaceFrame = wp.media( {
					title: i18n.replaceTitle,
					button: { text: i18n.replaceButton },
					library: { type: [ 'image', 'video' ] },
					multiple: false
				} );
				replaceFrame.on( 'select', function () {
					var data = replaceFrame.state().get( 'selection' ).first().toJSON();
					replaceFrame.state().get( 'selection' ).reset();
					if ( ! $editing ) {
						return;
					}
					$replacement = mediaRow( data );
					showModalThumb( $replacement );
					// A video slide has no link (a click on it is the player's).
					allowLink( ! $replacement.hasClass( 'wp-swiper-slide--video' ) );
				} );
			}
			replaceFrame.open();
		} );

		// The slide's values into the new row; the collage and Art orders (photo
		// ids) keep the new one where the old one was.
		function replaceSlide( $old, $row ) {
			var skip = [ 'id', 'embed', 'poster', 'position' ];
			$old.find( '[name^="wp_swiper_slides["]' ).each( function () {
				var prop = this.name.replace( /^wp_swiper_slides\[([^\]]+)\]\[\]$/, '$1' );
				if ( skip.indexOf( prop ) === -1 ) {
					slideInput( $row, prop ).val( $( this ).val() );
				}
			} );
			var from = String( slideInput( $old, 'id' ).val() || '' );
			var to = String( slideInput( $row, 'id' ).val() || '' );
			if ( from !== '0' && from !== '' ) {
				$( '[name^="wp_swiper_settings[collage_order"], [name^="wp_swiper_settings[art_order"]' ).each( function () {
					var ids = String( $( this ).val() || '' ).split( ',' );
					if ( ids.indexOf( from ) !== -1 ) {
						$( this ).val( ids.map( function ( id ) {
							return id === from ? to : id;
						} ).filter( function ( id, i, all ) {
							return all.indexOf( id ) === i;
						} ).join( ',' ) );
					}
				} );
			}
			$old.replaceWith( $row );
			return $row;
		}

		function closeSlide( apply ) {
			if ( ! $editing ) {
				return;
			}
			if ( apply ) {
				var caption = editorValue( CAPTION_EDITOR );
				var description = editorValue( DESCRIPTION_EDITOR );
				slideInput( $editing, 'caption' ).val( hasText( caption ) ? $.trim( caption ) : '' );
				slideInput( $editing, 'description' ).val( hasText( description ) ? description : '' );
				FONT_PROPS.forEach( function ( prop ) {
					slideInput( $editing, prop ).val( readFont( prop ) );
				} );
				COLOR_PROPS.forEach( function ( prop ) {
					slideInput( $editing, prop ).val( readColor( prop ) );
				} );
				if ( ! $editing.hasClass( 'wp-swiper-slide--video' ) ) {
					slideInput( $editing, 'link' ).val( $.trim( $( '#wp-swiper-link-input' ).val() ) );
					slideInput( $editing, 'new_tab' ).val( $( '#wp-swiper-new-tab-input' ).is( ':checked' ) ? '1' : '0' );
				}
				if ( $replacement ) {
					$editing = replaceSlide( $editing, $replacement );
					// The Video page's posters too (it may be a video now, or no longer).
					syncPosters();
				}
				syncMarks( $editing );
				$( '.wp-swiper-preview__update' ).addClass( 'is-stale' );
				// The Caption and Description pages and the lightbox lock (links) follow the slides.
				syncCaptions();
				syncFields();
			}
			$replacement = null;
			if ( editor() ) {
				editor().remove( CAPTION_EDITOR );
				if ( descriptionStarted ) {
					editor().remove( DESCRIPTION_EDITOR );
				}
			}
			descriptionStarted = false;
			closeColorPickers();
			$modal.prop( 'hidden', true );
			$( document.body ).removeClass( 'wp-swiper-modal-open' );
			var $button = $editing.find( '.wp-swiper-slide__edit' );
			$editing = null;
			$button.trigger( 'focus' );
		}

		$list.on( 'click', '.wp-swiper-slide__edit', function () {
			openSlide( $( this ).closest( '.wp-swiper-slide' ) );
		} );
		$modal.on( 'click', '.wp-swiper-modal__apply', function () {
			closeSlide( true );
		} );
		$modal.on( 'click', '.wp-swiper-modal__cancel, .wp-swiper-modal__close', function () {
			closeSlide( false );
		} );
		// Enter in the link field applies, as in a form.
		$( '#wp-swiper-link-input' ).on( 'keydown', function ( e ) {
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				closeSlide( true );
			}
		} );
		$( document ).on( 'keydown', function ( e ) {
			// Not while the editor's own link window is open (Escape closes that).
			// Nor while the media library is open over it (for a replacement).
			if ( e.key === 'Escape' && $editing && ! $( '#wp-link-wrap' ).is( ':visible' ) && ! $( '.media-modal' ).is( ':visible' ) ) {
				// An open color picker first.
				if ( openPickr() ) {
					closeColorPickers();
					return;
				}
				closeSlide( false );
			}
		} );

		/*
		 * An image's own position ("Cover"): the point of it kept in view, on
		 * every device, over the slider's Image position. Kept in the slide's
		 * hidden input as "x y" (percent; empty: none), set in its window by a
		 * click, a drag or the arrow keys on the image; the frames beside it show
		 * the crops of a wide, a square and a tall slide. The slide's thumbnail
		 * follows it. Images only: the button shows while the slider crops
		 * (syncFields: .wp-swiper-slides--positions).
		 */
		var $focusModal = $( '#wp-swiper-position-modal' ).appendTo( document.body );
		var $focusStage = $focusModal.find( '.wp-swiper-focus__stage' );
		var $focusImage = $focusModal.find( '.wp-swiper-focus__image' );
		var $focusMark = $focusModal.find( '.wp-swiper-focus__point' );
		var $focusFrames = $focusModal.find( '.wp-swiper-focus__frame img' );
		var $focusValue = $focusModal.find( '.wp-swiper-focus__value' );
		var $focusReset = $focusModal.find( '.wp-swiper-focus__reset' );
		var $focusSlide = null;
		var focusPoint = null;

		// "x y" as [ x, y ] (WP_Swiper_Settings::sanitize_position() reads it so), or null.
		function parsePoint( value ) {
			var match = /^(\d+(?:\.\d+)?) (\d+(?:\.\d+)?)$/.exec( $.trim( value || '' ) );
			return match ? [ Math.min( 100, Number( match[ 1 ] ) ), Math.min( 100, Number( match[ 2 ] ) ) ] : null;
		}

		function roundPercent( value ) {
			return Math.round( Math.min( 100, Math.max( 0, value ) ) * 10 ) / 10;
		}

		// The point on the image, the crops, the text; none: the middle, dimmed.
		function showFocus() {
			var point = focusPoint || [ 50, 50 ];
			var css = point[ 0 ] + '% ' + point[ 1 ] + '%';
			$focusMark.css( { left: point[ 0 ] + '%', top: point[ 1 ] + '%' } ).toggleClass( 'is-default', ! focusPoint );
			$focusFrames.css( 'object-position', css );
			var text = focusPoint ?
				( i18n.positionPoint || '%1$s%% %2$s%%' ).replace( '%1$s', point[ 0 ] ).replace( '%2$s', point[ 1 ] ).replace( /%%/g, '%' ) :
				( i18n.positionNone || '' );
			$focusValue.text( text );
			$focusStage.attr( { 'aria-valuenow': point[ 0 ], 'aria-valuetext': text } );
			$focusReset.prop( 'disabled', ! focusPoint );
		}

		// The point where the image was clicked or dragged to.
		function focusAt( e ) {
			var rect = $focusImage[ 0 ].getBoundingClientRect();
			if ( ! rect.width || ! rect.height ) {
				return;
			}
			focusPoint = [
				roundPercent( ( e.clientX - rect.left ) / rect.width * 100 ),
				roundPercent( ( e.clientY - rect.top ) / rect.height * 100 )
			];
			showFocus();
		}

		function openFocus( $slide ) {
			$focusSlide = $slide;
			focusPoint = parsePoint( slideInput( $slide, 'position' ).val() );
			$focusModal.find( '.wp-swiper-modal__slide' ).text( '— ' + ( i18n.slideNumber || '%d' ).replace( '%d', $slide.index() + 1 ) );
			// The larger image (data-full), else the thumbnail.
			var src = $slide.attr( 'data-full' ) || $slide.find( '.wp-swiper-slide__thumb img' ).attr( 'src' ) || '';
			$focusImage.attr( 'src', src );
			$focusFrames.attr( 'src', src );
			showFocus();
			$focusModal.prop( 'hidden', false );
			$( document.body ).addClass( 'wp-swiper-modal-open' );
			$focusStage.trigger( 'focus' );
		}

		function closeFocus( apply ) {
			if ( ! $focusSlide ) {
				return;
			}
			if ( apply ) {
				var value = focusPoint ? focusPoint[ 0 ] + ' ' + focusPoint[ 1 ] : '';
				slideInput( $focusSlide, 'position' ).val( value );
				$focusSlide.css( '--wp-swiper-thumb-position', focusPoint ? focusPoint[ 0 ] + '% ' + focusPoint[ 1 ] + '%' : '' );
				$focusSlide.find( '.wp-swiper-slide__position' ).toggleClass( 'is-set', !! focusPoint );
				$focusSlide.find( '.wp-swiper-slide__mark[data-mark="position"]' ).prop( 'hidden', ! focusPoint );
				$( '.wp-swiper-preview__update' ).addClass( 'is-stale' );
			}
			$focusModal.prop( 'hidden', true );
			$( document.body ).removeClass( 'wp-swiper-modal-open' );
			var $button = $focusSlide.find( '.wp-swiper-slide__position' );
			$focusSlide = null;
			$button.trigger( 'focus' );
		}

		$list.on( 'click', '.wp-swiper-slide__position', function () {
			openFocus( $( this ).closest( '.wp-swiper-slide' ) );
		} );
		$focusModal.on( 'click', '.wp-swiper-modal__apply', function () {
			closeFocus( true );
		} );
		$focusModal.on( 'click', '.wp-swiper-modal__cancel, .wp-swiper-modal__close', function () {
			closeFocus( false );
		} );
		$focusReset.on( 'click', function () {
			focusPoint = null;
			showFocus();
		} );

		// A click sets the point; dragging moves it.
		$focusStage.on( 'pointerdown', function ( e ) {
			if ( e.originalEvent.button !== 0 ) {
				return;
			}
			e.preventDefault();
			this.focus();
			if ( this.setPointerCapture ) {
				this.setPointerCapture( e.originalEvent.pointerId );
			}
			$focusStage.addClass( 'is-dragging' );
			focusAt( e.originalEvent );
		} ).on( 'pointermove', function ( e ) {
			if ( $focusStage.hasClass( 'is-dragging' ) ) {
				focusAt( e.originalEvent );
			}
		} ).on( 'pointerup pointercancel', function () {
			$focusStage.removeClass( 'is-dragging' );
		} ).on( 'keydown', function ( e ) {
			// Arrow keys: 1% (Shift: 10%); Enter applies.
			var steps = { ArrowLeft: [ -1, 0 ], ArrowRight: [ 1, 0 ], ArrowUp: [ 0, -1 ], ArrowDown: [ 0, 1 ] };
			if ( e.key === 'Enter' ) {
				e.preventDefault();
				closeFocus( true );
				return;
			}
			if ( ! steps[ e.key ] ) {
				return;
			}
			e.preventDefault();
			var by = e.shiftKey ? 10 : 1;
			var point = focusPoint || [ 50, 50 ];
			focusPoint = [ roundPercent( point[ 0 ] + steps[ e.key ][ 0 ] * by ), roundPercent( point[ 1 ] + steps[ e.key ][ 1 ] * by ) ];
			showFocus();
		} );

		$( document ).on( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && $focusSlide ) {
				closeFocus( false );
			}
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

		// Every lock below, its state as Undo / Redo keeps it with the values
		// (get(): the state; set( state ): back to it, the fields' values aside).
		var lockStates = [];

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
		var layouts = {};
		try {
			layouts = JSON.parse( $layouts.val() ) || {};
		} catch ( e ) {}
		var layoutEffect = $( '#wp-swiper-effect' ).val();

		function layoutInputs() {
			return $( 'fieldset[data-section="layout"]' ).find( 'input[name^="wp_swiper_settings["], select[name^="wp_swiper_settings["], textarea[name^="wp_swiper_settings["]' );
		}

		// "wp_swiper_settings[height_tablet]" -> "height_tablet".
		function storageKey( input ) {
			return input.name.slice( 'wp_swiper_settings['.length, -1 );
		}

		function readLayout() {
			var values = {};
			saveSettingsEditors();
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
				syncResponsive( $( this ) );
			} );
			// An image setting's thumbnail (the one picked stays in its data-url).
			$( '.wp-swiper-field--image input[type="hidden"]' ).each( function () {
				syncPicker( $( this ) );
			} );
			// The fields shown as icons (a Style) mark the value they got.
			$( '.wp-swiper-field--tiles' ).each( function () {
				syncTiles( $( this ) );
			} );
			// The color swatches show it too.
			closeColorPickers();
			syncColorPicks();
		}

		// Bound on the select itself: runs before syncFields, which then shows,
		// hides and locks the fields for the new effect.
		$( '#wp-swiper-effect' ).on( 'change', function () {
			var effect = $( this ).val();
			if ( effect === layoutEffect ) {
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
			var fixedDirection = effect === 'fade' || collage || effect === 'art' || ( effect === 'thumbs' && $( '#wp-swiper-thumbs_transition' ).val() === 'fade' );
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
			[ 'background_class', 'background_overlay_color' ].forEach( function ( name ) {
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
			// checks the same), and Fade's one photo: not a background; Creative:
			// the carousel; Slide: more than one slide in view, of their own
			// widths or a continuous scroll. Its settings once on; Art has no
			// spread (drop-shadow).
			var canShadow = SHADOW_EFFECTS.indexOf( effect ) !== -1 && ! background
				&& ( effect !== 'creative' || carousel )
				&& ( ! slide || marquee || autoWidth || expand || maxPerView() > 1 );
			var shadowOn = canShadow && $( '#wp-swiper-image_shadow' ).is( ':checked' );
			field( 'image_shadow' ).toggle( canShadow );
			[ 'image_shadow_x', 'image_shadow_y', 'image_shadow_blur', 'image_shadow_color' ].forEach( function ( name ) {
				field( name ).toggle( shadowOn );
			} );
			field( 'image_shadow_spread' ).toggle( shadowOn && effect !== 'art' );
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
			[ 'caption_bg_style', 'caption_bg_color' ].forEach( function ( name ) {
				field( name ).toggle( captionBg );
			} );
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
			var describable = effect === 'art' || ( ! vertical && ! background && ! marquee && ! collage );
			// Captions: none as a background either (WP_Swiper_Settings::can_caption());
			// the Caption page is greyed then, as while no slide has one.
			captionsShown = ! background;
			if ( $captionBox ) {
				syncCaptions();
			}
			var described = hasDescriptions();
			syncDescriptions( ! described ? 'empty' : ( describable ? '' : 'unavailable' ) );
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
				return device !== ( currentDevice || 'desktop' );
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
			var swapping = collage && $( '#wp-swiper-collage_swap' ).is( ':checked' );
			[ 'collage_swap_delay', 'collage_swap_speed', 'collage_pause' ].forEach( function ( name ) {
				field( name ).toggle( swapping );
			} );
			// A class of its own, over the toggles above (which keep their say for the
			// other effects): of the Controls page a Collage uses Photo swapping only.
			$( '#wp-swiper-page-controls .wp-swiper-field' ).not( '[data-effects~="collage"]' ).toggleClass( 'is-not-applicable', collage );

			// Art: the preview says its photos swap by dragging.
			$( '.wp-swiper-preview__hint[data-effect="art"]' ).prop( 'hidden', effect !== 'art' );
			// Art (class-wp-swiper-art.php): its own fields only, those of its Style
			// (data-styles); of the rest the Effect, the slider's class and id, the
			// lightbox's (images only, no strip), and the Caption and Description
			// pages (all as the toggles above left them; vertical captions
			// excepted). Last, over the toggles above: none of the others applies to it.
			var ART_SHARED = [ 'effect', 'css_class', 'css_id', 'lightbox', 'lightbox_border_rounded', 'lightbox_radius', 'description_lightbox', 'description_lightbox_place', 'description_lightbox_width', 'description_lightbox_max' ];
			if ( effect === 'art' ) {
				var artStyle = $( '#wp-swiper-art_style' ).val();
				$( '.wp-swiper-field' ).each( function () {
					var $field = $( this );
					if ( String( $field.data( 'effects' ) || '' ).split( ' ' ).indexOf( 'art' ) === -1 ) {
						var name = $field.data( 'field' );
						var text = $field.closest( 'fieldset[data-section="caption"], fieldset[data-section="description"]' ).length > 0;
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
				// Image border: its width and color only while it is on (as a slider's).
				if ( ! $( '#wp-swiper-art_border' ).is( ':checked' ) ) {
					field( 'art_border_width' ).hide();
					field( 'art_border_color' ).hide();
				}
				// Ring + text: the middle's own settings only while Content in the middle is on.
				if ( ! $( '#wp-swiper-art_center' ).is( ':checked' ) ) {
					[ 'art_center_image', 'art_center_image_size', 'art_center_text', 'art_center_font_size', 'art_center_padding', 'art_center_bg' ].forEach( function ( name ) {
						field( name ).hide();
					} );
				}
				// Photo swapping's own settings only while it is on (as the Collage's).
				if ( ! $( '#wp-swiper-art_swap' ).is( ':checked' ) ) {
					[ 'art_swap_target', 'art_swap_delay', 'art_swap_speed', 'art_pause' ].forEach( function ( name ) {
						field( name ).hide();
					} );
				}
				// SVG shape: the order windows show a new photo in, only where they all change at once.
				if ( [ 'one', 'advance' ].indexOf( $( '#wp-swiper-art_shape_target' ).val() ) === -1 ) {
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
			}

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
		var captionsLocked = false;
		var captionsShown;

		function syncCaptions() {
			var why = ! slidesWith( 'caption' ) ? 'empty' : ( captionsShown === false ? 'unavailable' : '' );
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

		$( '#wp-swiper-settings' )
			// A field's own device icons: which device's value it shows.
			.on( 'click', '.wp-swiper-devices__btn', function () {
				var $btn = $( this );
				var $field = $btn.closest( '.wp-swiper-field--responsive' );
				var device = $btn.data( 'device' );

				$field.find( '.wp-swiper-devices__btn' ).removeClass( 'is-active' ).attr( 'aria-pressed', 'false' );
				$btn.addClass( 'is-active' ).attr( 'aria-pressed', 'true' );
				$field.find( '.wp-swiper-devices__input' ).prop( 'hidden', true )
					// Its value (and its unit, where it has one of its own); the value takes the focus.
					.filter( '[data-device="' + device + '"]' ).prop( 'hidden', false ).first().trigger( 'focus' );
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
		 * Style): the choice of the device shown is marked; on tablet and mobile
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
		var DEVICES = [ 'desktop', 'tablet', 'mobile' ];
		var currentDevice = 'desktop';
		// Another device: its values in the fields, and (Collage) the fields of its Style.
		var deviceListeners = [ syncFields ];
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
				return currentDevice !== 'desktop' && !! drawings[ currentDevice ];
			};
			var isTurned = function () {
				return canTurn() && !! turned[ currentDevice ];
			};

			var fitPreview = function () {
				var turn = isTurned();
				var size = turn ? [ sizes[ currentDevice ][ 1 ], sizes[ currentDevice ][ 0 ] ] : sizes[ currentDevice ];
				var device = drawings[ currentDevice ];
				var image = $drawings.filter( '[data-device="' + currentDevice + '"]' )[ 0 ];
				var inDevice = !! ( device && image );
				// px of the frame per unit of the drawing (its screen as wide as the
				// upright frame); the whole device's size, and the screen's place in it.
				var unit = inDevice ? sizes[ currentDevice ][ 0 ] / device.screen[ 2 ] : 1;
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
					this.hidden = ! turn || $( this ).data( 'device' ) !== currentDevice;
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
				turned[ currentDevice ] = ! turned[ currentDevice ];
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
				stage.style.transform = 'translate(' + shift[ 0 ] + 'px, ' + shift[ 1 ] + 'px) rotate(' + ( turned[ currentDevice ] ? -90 : 90 ) + 'deg) scale(' + size + ')';
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
			};

			$update.on( 'click', updatePreview );

			// Developer mode: the boxes and empty spaces drawn over the preview
			// (preview-dev.js in the frame), again after every update; kept for
			// the next visit (this browser only).
			var DEV_KEY = 'wpSwiperDev';
			var $dev = $preview.find( '.wp-swiper-preview__dev' );
			var devOn = false;
			try {
				devOn = window.localStorage.getItem( DEV_KEY ) === '1';
			} catch ( e ) {}
			// It draws a slider's slides only: hidden where there are none to
			// draw (Collage, Art; a slider with no slides yet), its place kept (the
			// bar stays as it is); the choice is kept for when it is back.
			var devAvailable = function () {
				return [ 'collage', 'art' ].indexOf( $( '#wp-swiper-effect' ).val() ) === -1 && $list.children().length > 0;
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
			$( '#wp-swiper-effect' ).on( 'change', applyDev );
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

			/*
			 * Collage order (the Order field): the photos' IDs per device, the first
			 * ones in the first places. For the device the field shows: Shuffle sets
			 * one at random, Reset clears it (back to the larger screen's; desktop:
			 * the shuffle number's), and a photo dragged onto another place in the
			 * preview (preview-collage.js) sends the order it made. Shown right away.
			 */
			var $order = field( 'collage_order' );
			var orderDevice = function () {
				return $order.find( '.wp-swiper-devices__btn.is-active' ).data( 'device' ) || currentDevice;
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
			showOrderDevice( currentDevice );
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
			var PREVIEW_FIELDS = [ 'collage_order', 'collage_mosaic', 'collage_cuts', 'collage_manual', 'collage_count', 'art_order', 'art_ring_center' ];
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
				var others = $.extend( {}, layouts );
				delete others[ layoutEffect ];
				return {
					values: values,
					locks: lockStates.map( function ( lock ) {
						return lock.get();
					} ),
					layouts: JSON.stringify( others ),
					effect: layoutEffect,
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
				layouts = JSON.parse( state.layouts );
				layoutEffect = state.effect;
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

			// A responsive field shows `device`'s value (as its icons do, without the focus).
			var showFieldDevice = function ( $field, device ) {
				if ( ! $field.find( '.wp-swiper-devices__btn[data-device="' + device + '"]' ).length ) {
					return;
				}
				$field.find( '.wp-swiper-devices__btn' ).each( function () {
					var on = $( this ).data( 'device' ) === device;
					$( this ).toggleClass( 'is-active', on ).attr( 'aria-pressed', on ? 'true' : 'false' );
				} );
				$field.find( '.wp-swiper-devices__input' ).each( function () {
					this.hidden = $( this ).data( 'device' ) !== device;
				} );
				if ( $field.is( '.wp-swiper-field--tiles' ) ) {
					syncTiles( $field );
				}
			};

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
			var restorePreview = function ( device ) {
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
							count: Number( resolved( 'collage_count', device ) ) || 0
						} ) || done;
					}
					if ( win && win.wpSwiperRestoreArt ) {
						done = win.wpSwiperRestoreArt( {
							device: device,
							order: idsOf( resolved( 'art_order', device ) ),
							center: isFinite( center ) ? center : null
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
				if ( step.at.device && step.at.device !== currentDevice ) {
					setDevice( step.at.device );
					try {
						window.localStorage.setItem( DEVICE_KEY, currentDevice );
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
					restorePreview( step.at.device || currentDevice );
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
				if ( data.wpSwiper === 'art-order' || data.wpSwiper === 'art-center' ) {
					record( data.device );
				}
				if ( data.wpSwiper === 'art-order' && Array.isArray( data.ids ) ) {
					$( '#wp-swiper-art_order' + suffix ).val( data.ids.map( Number ).filter( function ( id ) {
						return id > 0;
					} ).join( ',' ) );
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
	} );
}( jQuery ) );
