/**
 * WP Swiper slider edit screen: the slides (added from the media library
 * or a video link, removed, reordered, cloned), the Video page's posters,
 * an SVG shape's file. A part of admin.js, run by it (see there).
 */
( function ( $ ) {
	'use strict';

	var parts = ( window.wpSwiperEditor = window.wpSwiperEditor || { parts: {} } ).parts;

	/*
	 * The slides: added from the media library or a video link, removed,
	 * reordered, cloned; the Video page's posters; an SVG shape's file.
	 */
	parts.slides = function ( shared ) {
		// From the parts before this one (shared).
		var i18n = shared.i18n;
		var $empty = shared.$empty;
		var $hint = shared.$hint;
		var $list = shared.$list;
		var template = shared.template;
		// For the parts after it.
		shared.mediaRow = mediaRow;
		shared.syncPosters = syncPosters;
		shared.syncPicker = syncPicker;

		// After slides are added or removed.
		function toggleEmpty() {
			var hasSlides = $list.children().length > 0;
			$empty.prop( 'hidden', hasSlides );
			shared.syncCaptions();
			$hint.prop( 'hidden', ! hasSlides );
			// The Video page, the lightbox lock (links) and what else follows the slides.
			shared.syncFields();
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

			if ( ! shared.frame ) {
				shared.frame = wp.media( {
					title: i18n.frameTitle,
					button: { text: i18n.frameButton },
					library: { type: [ 'image', 'video' ] },
					multiple: 'add'
				} );

				shared.frame.on( 'select', function () {
					shared.frame.state().get( 'selection' ).each( function ( attachment ) {
						mediaRow( attachment.toJSON() ).appendTo( $list );
					} );

					// Start with an empty selection next time.
					shared.frame.state().get( 'selection' ).reset();
					toggleEmpty();
				} );
			}

			shared.frame.open();
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
	};
}( jQuery ) );
