/**
 * WP Swiper slider edit screen: the Slide info window (a slide's caption,
 * description and link, their fonts and colors) and an image's own
 * position. Parts of admin.js, run by it (see there).
 */
( function ( $ ) {
	'use strict';

	var parts = ( window.wpSwiperEditor = window.wpSwiperEditor || { parts: {} } ).parts;

	/*
	 * The Slide info window's fields and each text's fonts (sizes per
	 * device, weights).
	 */
	parts.slideFonts = function ( shared ) {
		// From the parts before this one (shared).
		var $list = shared.$list;
		// For the parts after it.
		shared.slideInput = slideInput;
		shared.captionForEditor = captionForEditor;
		shared.syncFontInherits = syncFontInherits;
		shared.showFontDevice = showFontDevice;
		shared.showFont = showFont;
		shared.readFont = readFont;

		/*
		 * A slide's caption, description and link: kept in its hidden fields,
		 * edited in one window (WordPress's editor, wp.editor, for the two
		 * texts), whose editors start when it opens and are removed when it
		 * closes, so the slides stay plain fields that can be dragged. "Apply"
		 * puts the values into the slide; saving the slider saves them.
		 */
		var CAPTION_EDITOR = 'wp-swiper-caption-editor';
		shared.CAPTION_EDITOR = CAPTION_EDITOR;
		var DESCRIPTION_EDITOR = 'wp-swiper-description-editor';
		shared.DESCRIPTION_EDITOR = DESCRIPTION_EDITOR;
		// Out of the form (its fields are not sent) and of the box, which may be folded.
		var $modal = $( '#wp-swiper-slide-modal' ).appendTo( document.body );
		shared.$modal = $modal;
		shared.$editing = null;

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
		shared.FONT_PROPS = FONT_PROPS;
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
			// The editor's device (shareDevice()), as every field shows.
			showFontDevice( $control, shared.DEVICES.indexOf( shared.currentDevice ) !== -1 ? shared.currentDevice : 'desktop' );
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
	};

	/*
	 * The Slide info window: its tabs, editors, the slide it edits.
	 */
	parts.slideInfo = function ( shared ) {
		// From the parts before this one (shared).
		var i18n = shared.i18n;
		var $list = shared.$list;
		var mediaRow = shared.mediaRow;
		var syncPosters = shared.syncPosters;
		var $modal = shared.$modal;
		var CAPTION_EDITOR = shared.CAPTION_EDITOR;
		var captionForEditor = shared.captionForEditor;
		var DESCRIPTION_EDITOR = shared.DESCRIPTION_EDITOR;
		var FONT_PROPS = shared.FONT_PROPS;
		var readFont = shared.readFont;
		var showFont = shared.showFont;
		var slideInput = shared.slideInput;
		var closeColorPickers = shared.closeColorPickers;
		var COLOR_PROPS = shared.COLOR_PROPS;
		var openPickr = shared.openPickr;
		var readColor = shared.readColor;
		var showColor = shared.showColor;
		// For the parts after it.
		shared.slidesWith = slidesWith;
		shared.hasDescriptions = hasDescriptions;
		shared.startEditor = startEditor;

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

		function openSlide( $slide ) {
			shared.$editing = $slide;
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
			if ( tab === 'description' && shared.$editing && ! descriptionStarted ) {
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
					if ( ! shared.$editing ) {
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
			if ( ! shared.$editing ) {
				return;
			}
			if ( apply ) {
				var caption = editorValue( CAPTION_EDITOR );
				var description = editorValue( DESCRIPTION_EDITOR );
				slideInput( shared.$editing, 'caption' ).val( hasText( caption ) ? $.trim( caption ) : '' );
				slideInput( shared.$editing, 'description' ).val( hasText( description ) ? description : '' );
				FONT_PROPS.forEach( function ( prop ) {
					slideInput( shared.$editing, prop ).val( readFont( prop ) );
				} );
				COLOR_PROPS.forEach( function ( prop ) {
					slideInput( shared.$editing, prop ).val( readColor( prop ) );
				} );
				if ( ! shared.$editing.hasClass( 'wp-swiper-slide--video' ) ) {
					slideInput( shared.$editing, 'link' ).val( $.trim( $( '#wp-swiper-link-input' ).val() ) );
					slideInput( shared.$editing, 'new_tab' ).val( $( '#wp-swiper-new-tab-input' ).is( ':checked' ) ? '1' : '0' );
				}
				if ( $replacement ) {
					shared.$editing = replaceSlide( shared.$editing, $replacement );
					// The Video page's posters too (it may be a video now, or no longer).
					syncPosters();
				}
				syncMarks( shared.$editing );
				$( '.wp-swiper-preview__update' ).addClass( 'is-stale' );
				// The Caption and Description pages and the lightbox lock (links) follow the slides.
				shared.syncCaptions();
				shared.syncFields();
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
			var $button = shared.$editing.find( '.wp-swiper-slide__edit' );
			shared.$editing = null;
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
			if ( e.key === 'Escape' && shared.$editing && ! $( '#wp-link-wrap' ).is( ':visible' ) && ! $( '.media-modal' ).is( ':visible' ) ) {
				// An open color picker first.
				if ( openPickr() ) {
					closeColorPickers();
					return;
				}
				closeSlide( false );
			}
		} );
	};

	/*
	 * An image's own position (Cover): its window.
	 */
	parts.focus = function ( shared ) {
		// From the parts before this one (shared).
		var i18n = shared.i18n;
		var $list = shared.$list;
		var slideInput = shared.slideInput;

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
	};
}( jQuery ) );
