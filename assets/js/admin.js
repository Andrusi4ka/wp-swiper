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
	// Effects with a main image that can zoom (WP_Swiper_Settings::ZOOM_EFFECTS).
	var ZOOM_EFFECTS = i18n.zoomEffects || [];

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
						var data = attachment.toJSON();
						var video = data.type === 'video';
						var sizes = data.sizes || {};
						// A video has no picture of its own until a poster is chosen.
						var thumb = video ? '' : ( sizes.medium || sizes.thumbnail || data ).url;

						// The larger image for the Image position window.
						var full = video ? '' : ( sizes.large || data ).url;
						var $item = $( slideRow( data.id, thumb, data.title || data.filename || '', '', i18n.videoBadge, full ) ).appendTo( $list );
						// Without a poster a video has no thumbnail to go back to.
						$item.attr( 'data-default-thumb', '' );
						if ( video ) {
							$item.addClass( 'wp-swiper-slide--video' ).find( '.wp-swiper-slide__thumb img' ).prop( 'hidden', true );
						}
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
			$slide.find( '.wp-swiper-slide__edit' ).text( marks.caption || marks.description || marks.link ? i18n.infoEdit : i18n.infoAdd );
		}

		function editor() {
			return window.wp && window.wp.editor && window.wp.editor.initialize ? window.wp.editor : null;
		}

		// The "Text color" button's palette (class-wp-swiper-palette.php): the
		// theme's colors, the user's (WP Swiper > Settings), black, white and
		// greys, as many rows as they take; "Custom..." under them for any other.
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

		// A caption's aligned lines are kept as spans shown as blocks
		// (WP_Swiper_Settings::sanitize_caption()); the editor aligns blocks, so
		// they go back to the blocks it made, and its buttons show their alignment.
		function captionForEditor( html ) {
			// Parsed inert (a template): nothing in it loads or runs.
			var box = document.createElement( 'template' );
			box.innerHTML = String( html || '' );
			[].slice.call( box.content.children ).forEach( function ( node ) {
				var align = node.tagName === 'SPAN' && node.style.display === 'block' ? node.style.textAlign : '';
				if ( [ 'left', 'center', 'right' ].indexOf( align ) === -1 ) {
					return;
				}
				var line = document.createElement( 'div' );
				line.style.textAlign = align;
				while ( node.firstChild ) {
					line.appendChild( node.firstChild );
				}
				node.parentNode.replaceChild( line, node );
			} );
			return box.innerHTML;
		}

		function openSlide( $slide ) {
			$editing = $slide;
			$modal.find( '.wp-swiper-modal__slide' ).text( '— ' + ( i18n.slideNumber || '%d' ).replace( '%d', $slide.index() + 1 ) );
			// Which picture it is about: the slide's own thumbnail, as in the list.
			var $img = $slide.find( '.wp-swiper-slide__thumb img' );
			var $thumb = $modal.find( '.wp-swiper-modal__thumb' ).empty();
			if ( ! $img.prop( 'hidden' ) && $img.attr( 'src' ) ) {
				$( '<img alt="">' ).attr( 'src', $img.attr( 'src' ) ).appendTo( $thumb );
			} else {
				$thumb.append( '<span class="dashicons dashicons-video-alt3"></span>' );
			}
			$( '#' + CAPTION_EDITOR ).val( captionForEditor( slideInput( $slide, 'caption' ).val() ) );
			$( '#' + DESCRIPTION_EDITOR ).val( slideInput( $slide, 'description' ).val() );
			// A video slide has no link (a click on it is the player's).
			$modal.find( '.wp-swiper-modal__link' ).prop( 'hidden', $slide.hasClass( 'wp-swiper-slide--video' ) );
			$( '#wp-swiper-link-input' ).val( slideInput( $slide, 'link' ).val() );
			$( '#wp-swiper-new-tab-input' ).prop( 'checked', slideInput( $slide, 'new_tab' ).val() === '1' );
			$modal.prop( 'hidden', false );
			$( document.body ).addClass( 'wp-swiper-modal-open' );

			// The caption: inline styles (text color too) and each line's alignment only, Enter a line break (render.php keeps
			// what caption_tags() allows); the first field, so it takes the focus.
			startEditor( CAPTION_EDITOR, {
				toolbar1: 'bold,italic,underline,forecolor,alignleft,aligncenter,alignright,removeformat,undo,redo',
				toolbar2: '',
				forced_root_block: false,
				height: 70,
				init_instance_callback: function ( instance ) {
					instance.focus();
				}
			}, 'strong,em' );
			// The description: paragraph or heading (aligned), the inline styles (text color too), links and lists.
			startEditor( DESCRIPTION_EDITOR, {
				toolbar1: 'formatselect,bold,italic,underline,forecolor,alignleft,aligncenter,alignright,bullist,numlist,link,unlink,removeformat,undo,redo',
				toolbar2: '',
				block_formats: 'Paragraph=p;Heading 1=h1;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;Heading 6=h6',
				height: 220
			}, 'strong,em,link,ul,ol,li' );
			if ( ! window.tinymce ) {
				$( '#' + CAPTION_EDITOR ).trigger( 'focus' );
			}
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
				if ( ! $editing.hasClass( 'wp-swiper-slide--video' ) ) {
					slideInput( $editing, 'link' ).val( $.trim( $( '#wp-swiper-link-input' ).val() ) );
					slideInput( $editing, 'new_tab' ).val( $( '#wp-swiper-new-tab-input' ).is( ':checked' ) ? '1' : '0' );
				}
				syncMarks( $editing );
				$( '.wp-swiper-preview__update' ).addClass( 'is-stale' );
				// The Caption and Description pages and the lightbox lock (links) follow the slides.
				syncCaptions();
				syncFields();
			}
			if ( editor() ) {
				editor().remove( CAPTION_EDITOR );
				editor().remove( DESCRIPTION_EDITOR );
			}
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
			if ( e.key === 'Escape' && $editing && ! $( '#wp-link-wrap' ).is( ':visible' ) ) {
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
			// An image setting's thumbnail (the one picked stays in its data-url).
			$( '.wp-swiper-field--image input[type="hidden"]' ).each( function () {
				syncPicker( $( this ) );
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
			lockDirection( effect === 'fade' || collage || ( effect === 'thumbs' && $( '#wp-swiper-thumbs_transition' ).val() === 'fade' ) );
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

			// Background: its settings once it is on (data-effects above: the effects that can be one).
			[ 'background_class', 'background_overlay_opacity', 'background_overlay_color', 'background_min_height' ].forEach( function ( name ) {
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
			// Height "Auto" (the parent block's) needs no numbers.
			field( 'height' ).toggleClass( 'is-auto', $( '#wp-swiper-height_unit' ).val() === 'auto' );
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
			[ 'caption_bg_style', 'caption_bg_color', 'caption_bg_opacity' ].forEach( function ( name ) {
				field( name ).toggle( captionBg );
			} );
			var lightbox = $( '#wp-swiper-lightbox' ).is( ':checked' );
			field( 'lightbox_radius' ).toggle( lightbox );
			field( 'lightbox_thumbs' ).toggle( lightbox && effect === 'thumbs' );
			field( 'lightbox_thumbs_radius' ).toggle( lightbox && effect === 'thumbs' && $( '#wp-swiper-lightbox_thumbs' ).is( ':checked' ) );

			// Descriptions: under a horizontal slider with a current slide, not as a
			// background (WP_Swiper_Settings::can_describe()); the page is greyed
			// otherwise, and while no slide has one.
			// Art: always under it (Direction does not apply).
			var describable = effect === 'art' || ( ! vertical && ! background && ! marquee && ! collage );
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
			// Font size: its Size only when Custom (Default: the plugin's 16px).
			field( 'caption_font_size' ).toggle( $( '#wp-swiper-caption_font' ).val() === 'custom' );
			field( 'description_font_size' ).toggle( $( '#wp-swiper-description_font' ).val() === 'custom' );

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
			$( '.wp-swiper-preview__hint[data-effect="mosaic"]' ).prop( 'hidden', ! collageUses( [ 'random' ] ) );
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
			field( 'collage_count' ).toggle( collageUses( [ 'grid', 'justified', 'masonry', 'random' ] ) );
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
			var ART_SHARED = [ 'effect', 'css_class', 'css_id', 'lightbox', 'lightbox_radius', 'description_lightbox', 'description_lightbox_place', 'description_lightbox_width', 'description_lightbox_max' ];
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
					var styles = $field.data( 'styles' );
					$field.toggle( ! styles || String( styles ).split( ' ' ).indexOf( artStyle ) !== -1 );
				} );
				// Image border: its width and color only while it is on (as a slider's).
				if ( ! $( '#wp-swiper-art_border' ).is( ':checked' ) ) {
					field( 'art_border_width' ).hide();
					field( 'art_border_color' ).hide();
				}
				// Photo swapping's own settings only while it is on (as the Collage's).
				if ( ! $( '#wp-swiper-art_swap' ).is( ':checked' ) ) {
					[ 'art_swap_delay', 'art_swap_speed', 'art_pause' ].forEach( function ( name ) {
						field( name ).hide();
					} );
				}
				// Size "Auto" on a device (its unit, else the larger screen's): its
				// number does not apply there (greyed, kept).
				var sizeUnit = 'px';
				[ 'desktop', 'tablet', 'mobile' ].forEach( function ( device ) {
					var suffix = device === 'desktop' ? '' : '_' + device;
					sizeUnit = $( '#wp-swiper-art_size_unit' + suffix ).val() || sizeUnit;
					$( '#wp-swiper-art_size' + suffix ).prop( 'readOnly', sizeUnit === 'auto' ).toggleClass( 'is-unused', sizeUnit === 'auto' );
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

		$( '#wp-swiper-settings' ).on( 'change input', 'select, input', syncFields );
		syncFields();

		// Caption settings only apply once a slide has a caption: the whole box is
		// greyed until then (a disabled fieldset is not sent, so it is enabled
		// again for the submit and saving keeps its values). The slide's window
		// runs it again on Apply, as does a change of the slides.
		var $captionBox = $( 'fieldset[data-section="caption"]' );
		var captionsLocked = false;

		function syncCaptions() {
			captionsLocked = ! slidesWith( 'caption' );
			$captionBox.prop( 'disabled', captionsLocked ).find( '.wp-swiper-field' ).toggleClass( 'is-disabled', captionsLocked );
			$captionBox.find( '.wp-swiper-section-note' ).prop( 'hidden', ! captionsLocked );
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
				var own = $input.val() !== '';

				if ( device !== 'desktop' ) {
					// Numbers only: a select's empty choice just says "Inherit".
					if ( ! $input.is( 'select' ) ) {
						$input.attr( 'placeholder', inherited );
					}
					owns[ device ] = owns[ device ] || own;
				}
				if ( own ) {
					inherited = $input.val();
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

			// Developer mode: the boxes and empty spaces drawn over the preview
			// (preview-dev.js in the frame), again after every update; kept for
			// the next visit (this browser only).
			var DEV_KEY = 'wpSwiperDev';
			var $dev = $preview.find( '.wp-swiper-preview__dev' );
			var devOn = false;
			try {
				devOn = window.localStorage.getItem( DEV_KEY ) === '1';
			} catch ( e ) {}
			// It draws a slider's slides only: nothing to show for the effects
			// that are no slider (Collage, Art). Greyed there, with a note why;
			// the choice is kept for the others.
			var devTitle = $dev.attr( 'title' );
			var devAvailable = function () {
				return [ 'collage', 'art' ].indexOf( $( '#wp-swiper-effect' ).val() ) === -1;
			};
			var applyDev = function () {
				var available = devAvailable();
				$dev.toggleClass( 'is-unavailable', ! available )
					.attr( 'aria-disabled', available ? null : 'true' )
					.attr( 'title', available ? devTitle : ( i18n.devUnavailable || devTitle ) )
					.attr( 'aria-pressed', devOn && available ? 'true' : 'false' );
				var win = previewFrame.contentWindow;
				try {
					if ( win && win.wpSwiperDev ) {
						win.wpSwiperDev( devOn && available );
					}
				} catch ( e ) {}
			};
			$( '#wp-swiper-effect' ).on( 'change', applyDev );
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
				var own = input( 'collage_order', device ).val() !== '' || input( 'collage_mosaic', device ).val() !== '';
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
				setOrder( orderDevice(), shuffled );
				updatePreview();
			} );

			// Reset: the order and the mosaic sizes both, and that they were set by hand.
			$order.on( 'click', '.wp-swiper-order-reset', function () {
				setOrder( orderDevice(), [] );
				setMosaic( orderDevice(), '' );
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
				if ( data.wpSwiper === 'collage-order' && Array.isArray( data.ids ) ) {
					setOrder( data.device, data.ids.map( Number ).filter( function ( id ) {
						return id > 0;
					} ) );
					setByHand( data.device, true );
				} else if ( data.wpSwiper === 'collage-mosaic' && typeof data.mosaic === 'string' && /^[VH0-9.,[\]]{1,4000}$/.test( data.mosaic ) ) {
					// Checked again when saved (WP_Swiper_Settings::sanitize_mosaic()).
					setMosaic( data.device, data.mosaic );
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
