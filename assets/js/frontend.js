/**
 * WP Swiper front end, on every page with a slider, Collage or Art: the
 * lightbox, the video players, the heights, Use as background and init().
 * The sliders themselves are slider.js (where one is shown), the Collage
 * collage.js, Art art.js.
 */
( function () {
	'use strict';

	var BREAKPOINT_TABLET = 768;
	var BREAKPOINT_DESKTOP = 1025;

	// Same breakpoints as the CSS and Swiper's own: mobile < 768 <= tablet < 1025 <= desktop.
	// Asked through the CSS's own media queries, so both always agree (a
	// width read too early, before the viewport settles, could not).
	function device() {
		if ( window.matchMedia( '(max-width: ' + ( BREAKPOINT_TABLET - 1 ) + 'px)' ).matches ) {
			return 'mobile';
		}
		return window.matchMedia( '(max-width: ' + ( BREAKPOINT_DESKTOP - 1 ) + 'px)' ).matches ? 'tablet' : 'desktop';
	}

	// Height "Auto" on the device in view (config.fill: false, or per device;
	// true from an older page cache: every device).
	function fills( config ) {
		return config.fill === true || !! ( config.fill && config.fill[ device() ] );
	}

	var THUMB_ACTIVE_CLASS = 'wp-swiper-thumb-active';

	/**
	 * Swiper options shared by the strips of thumbnails (the slider's and the
	 * lightbox's): as many in view, with the gaps, as set per device.
	 */
	function stripOptions( settings ) {
		var options = {
			slidesPerView: settings.perView.mobile,
			spaceBetween: settings.gap.mobile,
			breakpoints: {}
		};
		options.breakpoints[ BREAKPOINT_TABLET ] = { slidesPerView: settings.perView.tablet, spaceBetween: settings.gap.tablet };
		options.breakpoints[ BREAKPOINT_DESKTOP ] = { slidesPerView: settings.perView.desktop, spaceBetween: settings.gap.desktop };
		return options;
	}

	/**
	 * Mark thumbnail `index` of a strip as the current one and keep it in view:
	 * in the middle where the strip can scroll that far.
	 */
	function stripShow( swiper, items, index ) {
		[].forEach.call( items, function ( item, i ) {
			item.classList.toggle( THUMB_ACTIVE_CLASS, i === index );
			if ( i === index ) {
				item.setAttribute( 'aria-current', 'true' );
			} else {
				item.removeAttribute( 'aria-current' );
			}
		} );
		// Thumbnails of a set height ('auto'): as many as are in view now.
		var perView = swiper.params.slidesPerView === 'auto' ? swiper.slidesPerViewDynamic() : Number( swiper.params.slidesPerView ) || 1;
		swiper.slideTo( Math.max( 0, index - Math.floor( ( perView - 1 ) / 2 ) ) );
	}

	/* ------------------------------------------------------------------ *
	 * Lightbox: full-size images over the page, itself a Swiper (with zoom)
	 * ------------------------------------------------------------------ */

	var i18n = window.wpSwiperI18n || { dialog: 'Image viewer', close: 'Close', prev: 'Previous image', next: 'Next image' };

	// Text for HTML, in an attribute's quotes too (an image's alt text, a
	// video's title may have quotes: they must not end the attribute).
	function escapeHtml( text ) {
		return String( null === text || undefined === text ? '' : text )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}

	/**
	 * A lightbox slide's media: an image to zoom, or a video ("Videos in the
	 * lightbox": a file, or a YouTube / Vimeo facade) in a box of its proportions.
	 */
	function lightboxMedia( item ) {
		if ( item.type === 'image' ) {
			return '<div class="swiper-zoom-container"><img data-src="' + escapeHtml( item.src ) + '" alt="' + escapeHtml( item.alt ) + '"></div>';
		}
		var inner = item.type === 'video'
			? '<video class="wp-swiper-lightbox__video" data-src="' + escapeHtml( item.src ) + '"' + ( item.poster ? ' poster="' + escapeHtml( item.poster ) + '"' : '' ) + ' controls playsinline preload="none"></video>'
			: '<div class="wp-swiper__embed wp-swiper-lightbox__embed">' +
				( item.poster ? '<img class="wp-swiper__embed-poster" src="' + escapeHtml( item.poster ) + '" alt="">' : '' ) +
				'<button type="button" class="wp-swiper__play" aria-label="' + escapeHtml( item.embed.title || '' ) + '"></button>' +
			  '</div>';
		return '<div class="wp-swiper-lightbox__media" style="--wp-swiper-lightbox-ratio:' + ( Number( item.ratio ) || 16 / 9 ) + '">' + inner + '</div>';
	}

	/**
	 * @param {Array}    items   [ { type: 'image'|'video'|'embed', src, embed, poster, ratio, alt, caption, description, thumb } ]
	 *                           (caption, description: HTML, or none)
	 * @param {number}   index   Item to open.
	 * @param {Function} onClose Called with the index shown last.
	 * @param {Object}   options { radius, thumbs, thumbRadius } from the slider
	 *                           settings. radius, thumbRadius: the image's and
	 *                           the thumbnails' corners per device, or false;
	 *                           thumbs: false or the slider's strip settings
	 *                           { perView, gap, ratio } for a strip under the image;
	 *                           text: false (descriptions over the image) or
	 *                           { width, max, align } to have them under it.
	 */
	function openLightbox( items, index, onClose, options ) {
		withSwiper( function () {
			showLightbox( items, index, onClose, options );
		} );
	}

	/*
	 * Swiper where it is not loaded yet: a Collage or Art loads only this
	 * file (render.php needs_swiper()), its lightbox loads Swiper's files the
	 * first time it opens (wpSwiperI18n.swiper), then goes on.
	 */
	var swiperWaiting = null;
	function withSwiper( done ) {
		if ( window.Swiper ) {
			done();
			return;
		}
		if ( ! i18n.swiper ) {
			return;
		}
		if ( swiperWaiting ) {
			swiperWaiting.push( done );
			return;
		}
		swiperWaiting = [ done ];
		var pending = 1;
		var ready = function () {
			pending--;
			if ( ! pending && window.Swiper ) {
				var waiting = swiperWaiting;
				swiperWaiting = null;
				waiting.forEach( function ( fn ) {
					fn();
				} );
			}
		};
		// Its stylesheet, unless the page has it already (WordPress's id for it).
		if ( ! document.getElementById( 'swiper-css' ) ) {
			pending++;
			var link = document.createElement( 'link' );
			link.rel = 'stylesheet';
			link.id = 'swiper-css';
			link.href = i18n.swiper.css;
			link.onload = link.onerror = ready;
			document.head.appendChild( link );
		}
		var script = document.createElement( 'script' );
		script.src = i18n.swiper.js;
		script.onload = ready;
		script.onerror = function () {
			swiperWaiting = null;
		};
		document.head.appendChild( script );
	}

	function showLightbox( items, index, onClose, options ) {
		var opener = document.activeElement;
		var root = document.createElement( 'div' );
		var strip = options.thumbs && items.length > 1 ? options.thumbs : null;

		root.className = 'wp-swiper-lightbox' + ( strip ? ' wp-swiper-lightbox--thumbs' : '' );
		// The image's corners: the Slide border radius of this device, if any.
		function setRadius() {
			root.style.setProperty( '--wp-swiper-lightbox-radius', ( options.radius ? Number( options.radius[ device() ] ) || 0 : 0 ) + 'px' );
		}
		setRadius();
		window.addEventListener( 'resize', setRadius );
		root.setAttribute( 'role', 'dialog' );
		root.setAttribute( 'aria-modal', 'true' );
		root.setAttribute( 'aria-label', i18n.dialog );
		root.innerHTML =
			'<div class="wp-swiper-lightbox__bar">' +
				'<span class="wp-swiper-lightbox__counter"></span>' +
				'<button type="button" class="wp-swiper-lightbox__close" aria-label="' + escapeHtml( i18n.close ) + '"></button>' +
			'</div>' +
			'<div class="swiper wp-swiper-lightbox__swiper"><div class="swiper-wrapper">' +
				items.map( function ( item ) {
					// The caption's HTML, as the slider shows it (sanitized by render.php).
					var caption = item.caption ? '<div class="wp-swiper-lightbox__caption">' + item.caption + '</div>' : '';
					// With a description (HTML of the slider's own, sanitized by
					// render.php): the caption over it, both in one panel at the bottom
					// that scrolls when long; its links and text are not for swiping.
					// "Under the image" (options.text): the panel below it instead, sized
					// by fitLightboxText().
					var under = item.description && options.text;
					var text = item.description
						? '<div class="wp-swiper-lightbox__info' + ( under ? ' wp-swiper-lightbox__info--under' : '' ) + ' swiper-no-swiping">' + caption + '<div class="wp-swiper-lightbox__description">' + item.description + '</div></div>'
						: caption;
					return '<div class="swiper-slide' + ( under ? ' wp-swiper-lightbox__slide--text' : '' ) + '">' + lightboxMedia( item ) + text + '</div>';
				} ).join( '' ) +
			'</div></div>' +
			( strip
				? '<div class="swiper wp-swiper-lightbox__thumbs"><div class="swiper-wrapper">' +
					items.map( function ( item ) {
						return '<div class="swiper-slide"><img src="' + escapeHtml( item.thumb || item.src ) + '" alt=""></div>';
					} ).join( '' ) +
				  '</div></div>'
				: '' ) +
			( items.length > 1
				? '<button type="button" class="wp-swiper-lightbox__prev" aria-label="' + escapeHtml( i18n.prev ) + '"></button>' +
				  '<button type="button" class="wp-swiper-lightbox__next" aria-label="' + escapeHtml( i18n.next ) + '"></button>'
				: '' );

		document.body.appendChild( root );
		document.documentElement.classList.add( 'wp-swiper-lightbox-open' );
		// The slider's own videos stop meanwhile (videos()).
		document.dispatchEvent( new CustomEvent( 'wpswiperlightbox', { detail: { open: true } } ) );

		var counter = root.querySelector( '.wp-swiper-lightbox__counter' );
		var slides = root.querySelectorAll( '.wp-swiper-lightbox__swiper .swiper-slide' );

		// Its videos: with sound and controls, played while their slide is shown.
		var lightboxSettings = { muted: false, loop: false, controls: true };
		var players = items.map( function ( item, i ) {
			if ( item.type === 'video' ) {
				return filePlayer( slides[ i ].querySelector( 'video' ), lightboxSettings );
			}
			return item.type === 'embed' ? embedPlayer( slides[ i ].querySelector( '.wp-swiper__embed' ), item.embed, lightboxSettings ) : null;
		} );

		function playIn( i ) {
			players.forEach( function ( video, n ) {
				if ( video && n !== i && video.playing() ) {
					video.pause();
				}
			} );
			if ( players[ i ] && ! players[ i ].playing() ) {
				players[ i ].play();
			}
		}

		// Load the shown image and its neighbours only.
		function load( i ) {
			[ i - 1, i, i + 1 ].forEach( function ( n ) {
				var img = slides[ ( n + items.length ) % items.length ].querySelector( 'img[data-src]' );
				if ( img ) {
					img.src = img.getAttribute( 'data-src' );
					img.removeAttribute( 'data-src' );
				}
			} );
		}

		var viewer = new window.Swiper( root.querySelector( '.swiper' ), {
			initialSlide: index,
			loop: items.length > 2,
			speed: 400,
			spaceBetween: 40,
			zoom: { maxRatio: 3 },
			keyboard: { enabled: true },
			navigation: items.length > 1 ? {
				prevEl: root.querySelector( '.wp-swiper-lightbox__prev' ),
				nextEl: root.querySelector( '.wp-swiper-lightbox__next' )
			} : false,
			on: {
				init: function ( s ) {
					load( s.realIndex );
					counter.textContent = ( s.realIndex + 1 ) + ' / ' + items.length;
					playIn( s.realIndex );
				},
				realIndexChange: function ( s ) {
					load( s.realIndex );
					counter.textContent = ( s.realIndex + 1 ) + ' / ' + items.length;
					playIn( s.realIndex );
				}
			}
		} );

		var thumbs = strip ? lightboxThumbs( root, viewer, strip, options.thumbRadius ) : null;
		var closed = false;
		posterFallbacks( root );

		// Descriptions under the images: sized now, as each image loads, and with the window.
		function fitText() {
			fitLightboxText( root, options.text );
		}
		if ( options.text ) {
			fitText();
			root.addEventListener( 'load', fitText, true );
			window.addEventListener( 'resize', fitText );
		}

		function close() {
			// A second click during the fade-out must not close (and destroy) twice.
			if ( closed ) {
				return;
			}
			closed = true;

			var last = viewer.realIndex;
			players.forEach( function ( video ) {
				if ( video ) {
					video.pause();
				}
			} );
			document.dispatchEvent( new CustomEvent( 'wpswiperlightbox', { detail: { open: false } } ) );
			if ( thumbs ) {
				thumbs.destroy();
			}
			document.removeEventListener( 'keydown', onKey );
			window.removeEventListener( 'resize', setRadius );
			window.removeEventListener( 'resize', fitText );
			root.classList.remove( 'is-open' );
			document.documentElement.classList.remove( 'wp-swiper-lightbox-open' );

			setTimeout( function () {
				viewer.destroy( true, true );
				root.remove();
			}, 250 );

			if ( opener && opener.focus ) {
				opener.focus( { preventScroll: true } );
			}
			onClose( last );
		}

		function onKey( e ) {
			if ( e.key === 'Escape' ) {
				close();
			} else if ( e.key === 'Tab' ) {
				// Keep focus inside the dialog (arrows are hidden on phones).
				var focusable = [].filter.call( root.querySelectorAll( 'button' ), function ( button ) {
					return button.offsetParent !== null;
				} );
				var first = focusable[ 0 ], last = focusable[ focusable.length - 1 ];
				if ( e.shiftKey && document.activeElement === first ) {
					e.preventDefault();
					last.focus();
				} else if ( ! e.shiftKey && document.activeElement === last ) {
					e.preventDefault();
					first.focus();
				}
			}
		}

		root.querySelector( '.wp-swiper-lightbox__close' ).addEventListener( 'click', close );
		document.addEventListener( 'keydown', onKey );

		// A YouTube / Vimeo video's play button (should its autoplay be refused).
		root.addEventListener( 'click', function ( e ) {
			var box = e.target.closest( '.wp-swiper-lightbox__embed' );
			var i = box ? [].indexOf.call( slides, box.closest( '.swiper-slide' ) ) : -1;
			if ( i >= 0 && players[ i ] ) {
				players[ i ].play();
			}
		} );

		// A click on the backdrop (not the image, video, caption or buttons) closes.
		root.addEventListener( 'click', function ( e ) {
			if ( viewer.allowClick && ! e.target.closest( 'img, button, .wp-swiper-lightbox__media, .wp-swiper-lightbox__caption, .wp-swiper-lightbox__info, .wp-swiper-lightbox__thumbs' ) ) {
				close();
			}
		} );

		requestAnimationFrame( function () {
			root.classList.add( 'is-open' );
			root.querySelector( '.wp-swiper-lightbox__close' ).focus( { preventScroll: true } );
		} );
	}

	// "Under the image": the text's narrowest width where the window allows (px).
	var LIGHTBOX_TEXT_MIN = 500;

	/**
	 * Descriptions under the lightbox images (options.text { width, max,
	 * align }): the text as wide as its image, within "Text width" (% of the
	 * window, this device's) and its maximum, not narrower than
	 * LIGHTBOX_TEXT_MIN (CSS); the image as tall as the slide leaves room for
	 * once the text is under it (CSS caps the text at 40%: longer scrolls).
	 * Each depends on the other, so twice. Layout sizes (offset*): a zoomed
	 * image keeps its own.
	 *
	 * @param {Element} root The lightbox.
	 * @param {Object}  text options.text.
	 */
	function fitLightboxText( root, text ) {
		root.style.setProperty( '--wp-swiper-lightbox-text-width', ( Number( text.width[ device() ] ) || 100 ) + '%' );
		root.style.setProperty( '--wp-swiper-lightbox-text-max', ( Number( text.max ) || 800 ) + 'px' );
		root.style.setProperty( '--wp-swiper-lightbox-text-min', LIGHTBOX_TEXT_MIN + 'px' );
		root.style.setProperty( '--wp-swiper-lightbox-text-align', text.align || 'left' );

		root.querySelectorAll( '.wp-swiper-lightbox__slide--text' ).forEach( function ( slide ) {
			var info = slide.querySelector( '.wp-swiper-lightbox__info' );
			var media = slide.querySelector( '.swiper-zoom-container img, .wp-swiper-lightbox__media' );
			var box = getComputedStyle( slide );
			var gap = parseFloat( getComputedStyle( info ).marginTop ) || 0;

			for ( var pass = 0; pass < 2; pass++ ) {
				var room = slide.clientHeight - parseFloat( box.paddingTop ) - parseFloat( box.paddingBottom ) - gap - info.offsetHeight;
				slide.style.setProperty( '--wp-swiper-lightbox-room', Math.max( 0, Math.floor( room ) ) + 'px' );
				// An image not loaded yet has no width: the text's own limits meanwhile.
				if ( media && media.offsetWidth ) {
					slide.style.setProperty( '--wp-swiper-lightbox-media-width', media.offsetWidth + 'px' );
				}
			}
		} );
	}

	// Lightbox thumbnails are at most this tall: px, and share of the window's height.
	var LIGHTBOX_THUMB_HEIGHT = 90;
	var LIGHTBOX_THUMB_SHARE = 0.14;

	/**
	 * The strip of thumbnails under the lightbox image: as many in view, with
	 * the same gaps and proportions, as the slider's own strip. That many
	 * across the whole window would be far too tall, so the strip is only as
	 * wide as thumbnails of the height above make it (CSS keeps it inside
	 * the window). Returns { destroy }.
	 *
	 * @param {Element} root     The lightbox.
	 * @param {Object}  viewer   The lightbox's Swiper.
	 * @param {Object}  settings The slider's strip settings { perView, gap, ratio }.
	 * @param {Object}  radius   The thumbnails' corners per device, or false.
	 */
	function lightboxThumbs( root, viewer, settings, radius ) {
		var el = root.querySelector( '.wp-swiper-lightbox__thumbs' );
		var items = el.querySelectorAll( '.swiper-slide' );

		function size() {
			var current = device();
			// The proportions and the corners follow the device too.
			root.style.setProperty( '--wp-swiper-lightbox-thumb-ratio', settings.ratio[ current ] );
			root.style.setProperty( '--wp-swiper-lightbox-thumb-radius', ( radius ? Number( radius[ current ] ) || 0 : 0 ) + 'px' );
			var perView = Number( settings.perView[ current ] ) || 1;
			var gap = Number( settings.gap[ current ] ) || 0;
			var height = Math.min( LIGHTBOX_THUMB_HEIGHT, window.innerHeight * LIGHTBOX_THUMB_SHARE );
			el.style.width = Math.round( perView * height * settings.ratio[ current ] + gap * ( perView - 1 ) ) + 'px';
		}
		size();
		window.addEventListener( 'resize', size );

		var options = stripOptions( settings );
		options.centerInsufficientSlides = true;

		var swiper = new window.Swiper( el, options );

		el.addEventListener( 'click', function ( e ) {
			var item = e.target.closest( '.swiper-slide' );
			// Not at the end of a drag of the strip.
			if ( ! item || ! swiper.allowClick ) {
				return;
			}
			var index = [].indexOf.call( items, item );
			if ( viewer.params.loop ) {
				viewer.slideToLoop( index );
			} else {
				viewer.slideTo( index );
			}
		} );

		var update = function () {
			stripShow( swiper, items, viewer.realIndex );
		};
		viewer.on( 'realIndexChange', update );
		update();

		return {
			destroy: function () {
				window.removeEventListener( 'resize', size );
				// With the lightbox's own Swiper, once it has faded out.
				viewer.on( 'beforeDestroy', function () {
					swiper.destroy( true, false );
				} );
			}
		};
	}

	/**
	 * Open the lightbox from a click on a slide image.
	 */
	// Slides the lightbox shows: images, and with "Videos in the lightbox" the
	// video slides (render.php gives them what the lightbox shows).
	var LIGHTBOX_SHOWN = '[data-wp-swiper-full], [data-wp-swiper-video], [data-wp-swiper-embed]';

	/**
	 * What the lightbox shows for a slide (see openLightbox()'s items).
	 *
	 * @param {Element} node  The slide.
	 * @param {number}  real  Its index.
	 * @param {string}  thumb A small file for the lightbox's strip, or ''.
	 */
	function lightboxItem( node, real, thumb ) {
		var img = node.querySelector( 'img.wp-swiper__image' );
		// Its HTML (render.php keeps the caption's own bold, italic and line breaks only).
		var caption = node.querySelector( '.wp-swiper__caption-text' );
		var type = node.hasAttribute( 'data-wp-swiper-full' ) ? 'image' : ( node.hasAttribute( 'data-wp-swiper-video' ) ? 'video' : 'embed' );
		var poster = node.getAttribute( 'data-wp-swiper-poster' ) || '';
		return {
			real: real,
			type: type,
			src: type === 'image' ? node.getAttribute( 'data-wp-swiper-full' ) : ( node.getAttribute( 'data-wp-swiper-video' ) || '' ),
			embed: type === 'embed' ? JSON.parse( node.getAttribute( 'data-wp-swiper-embed' ) ) : null,
			poster: poster,
			ratio: Number( node.getAttribute( 'data-wp-swiper-ratio' ) ) || 16 / 9,
			alt: img ? img.alt : '',
			caption: caption ? caption.innerHTML.trim() : '',
			thumb: thumb || poster
		};
	}

	/* ------------------------------------------------------------------ *
	 * Video players
	 *
	 * A file's <video>, or a YouTube / Vimeo video put into its "facade"
	 * (render.php: the poster and a play button) the first time it plays.
	 * Both have one interface for the slider and the lightbox: load( preload ),
	 * play(), pause(), playing(), once() (no loop), onEnded( fn ), onError( fn ).
	 * YouTube and Vimeo are driven by messages to their player: no API script
	 * of theirs loads.
	 * ------------------------------------------------------------------ */

	var EMBED_ORIGINS = {
		youtube: 'https://www.youtube-nocookie.com',
		vimeo: 'https://player.vimeo.com'
	};

	/**
	 * @param {Element} video    The <video> (its file waits in data-src).
	 * @param {Object}  settings { muted, loop, controls }.
	 */
	function filePlayer( video, settings ) {
		// As properties too: the attributes alone do not mute every browser.
		video.muted = settings.muted;
		video.loop = settings.loop;
		video.controls = settings.controls;

		function load( preload ) {
			if ( ! video.getAttribute( 'src' ) && video.getAttribute( 'data-src' ) ) {
				video.preload = preload;
				video.setAttribute( 'src', video.getAttribute( 'data-src' ) );
			}
		}

		return {
			el: video,
			load: load,
			play: function () {
				load( 'auto' );
				if ( video.ended ) {
					video.currentTime = 0;
				}
				var started = video.play();
				if ( started && started.catch ) {
					started.catch( function () {
						// Browsers start a video by itself only without sound: then muted
						// (with the controls the visitor can turn it on).
						if ( ! video.muted ) {
							video.muted = true;
							video.play().catch( function () {} );
						}
					} );
				}
			},
			pause: function () {
				if ( ! video.paused ) {
					video.pause();
				}
			},
			playing: function () {
				return ! video.paused;
			},
			once: function () {
				video.loop = false;
			},
			onEnded: function ( fn ) {
				video.addEventListener( 'ended', fn );
			},
			onError: function ( fn ) {
				video.addEventListener( 'error', fn );
			}
		};
	}

	/**
	 * @param {Element} box      The facade (its poster, its play button).
	 * @param {Object}  data     { provider: 'youtube'|'vimeo', id, hash, title }.
	 * @param {Object}  settings { muted, loop, controls }: fixed once the player
	 *                           is there (they are its address).
	 */
	function embedPlayer( box, data, settings ) {
		var youtube = data.provider === 'youtube';
		var origin = EMBED_ORIGINS[ data.provider ];
		var iframe = null;
		// Whether it should play: the player may start (autoplay) after it was left.
		var wanted = false;
		var ended = [];

		function address() {
			var params;
			if ( youtube ) {
				params = [ 'autoplay=1', 'mute=' + ( settings.muted ? 1 : 0 ), 'controls=' + ( settings.controls ? 1 : 0 ), 'playsinline=1', 'rel=0', 'modestbranding=1', 'iv_load_policy=3', 'enablejsapi=1' ];
				if ( settings.loop ) {
					params.push( 'loop=1', 'playlist=' + data.id );
				}
				if ( ! settings.controls ) {
					params.push( 'disablekb=1' );
				}
				if ( /^https?:/.test( window.location.origin ) ) {
					params.push( 'origin=' + encodeURIComponent( window.location.origin ) );
				}
				return origin + '/embed/' + encodeURIComponent( data.id ) + '?' + params.join( '&' );
			}
			params = [ 'autoplay=1', 'muted=' + ( settings.muted ? 1 : 0 ), 'loop=' + ( settings.loop ? 1 : 0 ), 'controls=' + ( settings.controls ? 1 : 0 ), 'playsinline=1', 'dnt=1', 'autopause=0', 'title=0', 'byline=0', 'portrait=0' ];
			if ( data.hash ) {
				params.unshift( 'h=' + encodeURIComponent( data.hash ) );
			}
			return origin + '/video/' + encodeURIComponent( data.id ) + '?' + params.join( '&' );
		}

		function send( message ) {
			if ( iframe && iframe.contentWindow ) {
				iframe.contentWindow.postMessage( JSON.stringify( message ), origin );
			}
		}

		function command( play ) {
			if ( youtube ) {
				send( { event: 'command', func: play ? 'playVideo' : 'pauseVideo', args: [] } );
			} else {
				send( { method: play ? 'play' : 'pause' } );
			}
		}

		// Ask the player to tell its state (YouTube: its "listening"; Vimeo: events).
		function listen() {
			if ( youtube ) {
				send( { event: 'listening', id: data.id, channel: 'widget' } );
			} else {
				[ 'play', 'pause', 'ended' ].forEach( function ( name ) {
					send( { method: 'addEventListener', value: name } );
				} );
			}
		}

		function started() {
			box.classList.add( 'is-playing' );
			// Started (autoplay) after it was left: stop it again.
			if ( ! wanted ) {
				command( false );
			}
		}

		function finished() {
			ended.forEach( function ( fn ) {
				fn();
			} );
		}

		window.addEventListener( 'message', function ( e ) {
			if ( ! iframe || e.source !== iframe.contentWindow ) {
				return;
			}
			var message;
			try {
				message = typeof e.data === 'string' ? JSON.parse( e.data ) : e.data;
			} catch ( error ) {
				return;
			}
			if ( ! message ) {
				return;
			}
			if ( youtube ) {
				// 1 playing, 0 ended (infoDelivery, or onStateChange).
				var info = message.info;
				var state = info && typeof info === 'object' ? info.playerState : ( message.event === 'onStateChange' ? info : undefined );
				if ( state === 1 ) {
					started();
				} else if ( state === 0 ) {
					finished();
				}
			} else if ( message.event === 'ready' ) {
				listen();
			} else if ( message.event === 'play' || message.event === 'playing' ) {
				started();
			} else if ( message.event === 'ended' || message.event === 'finish' ) {
				finished();
			}
		} );

		function create() {
			iframe = document.createElement( 'iframe' );
			iframe.className = 'wp-swiper__embed-frame';
			iframe.src = address();
			iframe.title = data.title || '';
			iframe.setAttribute( 'allow', 'autoplay; encrypted-media; picture-in-picture; fullscreen' );
			iframe.setAttribute( 'allowfullscreen', '' );
			iframe.addEventListener( 'load', function () {
				// YouTube may not listen yet right at load: asked again shortly after.
				listen();
				setTimeout( listen, 500 );
				setTimeout( listen, 1500 );
			} );
			box.appendChild( iframe );
			box.classList.add( 'has-player' );
		}

		return {
			el: box,
			load: function () {},
			play: function () {
				wanted = true;
				if ( iframe ) {
					command( true );
				} else {
					create();
				}
			},
			pause: function () {
				wanted = false;
				command( false );
			},
			playing: function () {
				return wanted;
			},
			once: function () {},
			onEnded: function ( fn ) {
				ended.push( fn );
			},
			onError: function () {}
		};
	}

	/**
	 * YouTube posters: its largest thumbnail, which not every video has (YouTube
	 * then serves a small grey one): the smaller one, always there, instead.
	 */
	function posterFallbacks( root ) {
		[].forEach.call( root.querySelectorAll( '.wp-swiper__embed-poster[data-fallback]' ), function ( img ) {
			var check = function () {
				if ( img.naturalWidth && img.naturalWidth <= 120 ) {
					img.src = img.getAttribute( 'data-fallback' );
					img.removeAttribute( 'data-fallback' );
				}
			};
			img.addEventListener( 'error', function () {
				if ( img.hasAttribute( 'data-fallback' ) ) {
					img.src = img.getAttribute( 'data-fallback' );
					img.removeAttribute( 'data-fallback' );
				}
			} );
			img.addEventListener( 'load', check );
			if ( img.complete ) {
				check();
			}
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Height "Auto": the slider fills its parent block's height
	 *
	 * height: 100% cannot reach through the page builder's wrappers (column >
	 * inner > wrapper > element > wrapper), so the free height is measured.
	 * With the slider collapsed, the column keeps the height its row gives it
	 * (the neighbour column, a full-height row...). The slider is then made
	 * as tall as the whole column, and whatever the column grew beyond its
	 * collapsed height (its padding, other content) is taken back off. Sizes
	 * only, no positions: content centred in the column ("middle" placement)
	 * moves as the slider is collapsed.
	 * ------------------------------------------------------------------ */

	// Below this much free height (e.g. stacked columns on phones) a horizontal
	// slider sizes itself from the first image's proportions; one that cannot
	// (vertical, or slides of different widths) takes FILL_FIXED.
	var FILL_MIN = 120;
	var FILL_FIXED = 500;

	function fillBox( root ) {
		return root.closest( '.wpb_column, .vc_column_container' ) || root.parentElement;
	}

	/**
	 * @param {Element} root       The .wp-swiper block.
	 * @param {boolean} needsFixed The slides cannot take the image proportions.
	 * @param {number}  cap        Most height the slides can use (0: no cap).
	 */
	function fillHeight( root, needsFixed, cap ) {
		var box = fillBox( root );

		root.style.height = '0px';
		var collapsed = box.getBoundingClientRect().height;

		root.style.height = collapsed + 'px';
		var free = Math.floor( collapsed - ( box.getBoundingClientRect().height - collapsed ) );
		if ( free >= FILL_MIN ) {
			root.style.height = free + 'px';
		} else {
			root.style.height = needsFixed ? FILL_FIXED + 'px' : '';
		}

		// The slides fill the block but for an outside pagination: keep that.
		if ( cap && root.style.height ) {
			var height = root.getBoundingClientRect().height;
			var extra = height - root.querySelector( '.swiper' ).getBoundingClientRect().height;
			if ( cap + extra < height ) {
				root.style.height = ( cap + extra ) + 'px';
			}
		}
		return root.offsetHeight;
	}

	/**
	 * Neighbour columns change height as their images load or text wraps.
	 * Watch their content, not the row: once the slider is the tallest thing
	 * in the row, the row keeps its height and a shrinking neighbour would go
	 * unnoticed. Collapsing and restoring the slider in one go leaves the
	 * observed sizes unchanged, so this does not feed itself.
	 *
	 * @param {Element}  root     The .wp-swiper block.
	 * @param {Function} callback Called on a change.
	 */
	function watchNeighbours( root, callback ) {
		if ( ! ( 'ResizeObserver' in window ) ) {
			return;
		}
		var box = fillBox( root );
		var row = box.parentElement || box;
		var observer = new ResizeObserver( callback );

		observer.observe( row );
		[].forEach.call( row.querySelectorAll( '.wpb_wrapper, .vc_column-inner > *' ), function ( node ) {
			if ( ! node.contains( root ) && ! root.contains( node ) ) {
				observer.observe( node );
			}
		} );
		// Outside the page builder: the other content next to the slider.
		[].forEach.call( box.children, function ( node ) {
			if ( ! node.contains( root ) ) {
				observer.observe( node );
			}
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Background ("Use as background")
	 *
	 * The slider fills the nearest block around it with the class it was
	 * given (a row, a column), behind that block's content: it is moved to
	 * be the block's first child, so that no wrapper of the page builder in
	 * between (positioned, clipping) decides its size. Before Swiper or the
	 * collage measure it. Without such a block it stays a slider as usual,
	 * and those who can edit it see why (render.php's notice).
	 * ------------------------------------------------------------------ */

	function background( wrapper ) {
		if ( ! wrapper.classList.contains( 'wp-swiper--background' ) || wrapper.wpSwiperBackground ) {
			return;
		}
		wrapper.wpSwiperBackground = true;

		var classes = ( wrapper.getAttribute( 'data-wp-swiper-bg' ) || '' ).split( /\s+/ ).filter( Boolean );
		var block = null;
		if ( classes.length && wrapper.parentElement ) {
			try {
				block = wrapper.parentElement.closest( classes.map( function ( name ) {
					return '.' + ( window.CSS && CSS.escape ? CSS.escape( name ) : name );
				} ).join( '' ) );
			} catch ( e ) {}
		}

		if ( ! block ) {
			wrapper.classList.remove( 'wp-swiper--background' );
			var notice = wrapper.nextElementSibling;
			if ( notice && notice.hasAttribute( 'data-wp-swiper-bg-missing' ) ) {
				notice.hidden = false;
			}
			return;
		}

		block.classList.add( 'wp-swiper-bg-host' );
		// Its own position (absolute, sticky...) is kept: any but static holds the slider.
		if ( getComputedStyle( block ).position === 'static' ) {
			block.classList.add( 'wp-swiper-bg-host--static' );
		}
		// "Minimum block height", on the devices it is set for.
		[ '', '-tablet', '-mobile' ].forEach( function ( suffix ) {
			var name = '--wp-swiper-bg-min-height' + suffix;
			var value = wrapper.style.getPropertyValue( name );
			if ( value ) {
				block.style.setProperty( name, value );
				block.classList.add( 'wp-swiper-bg-host--min' );
			}
		} );
		block.insertBefore( wrapper, block.firstChild );
	}

	function init( root ) {
		// Collages first: they do not need Swiper. Laid out by collage.js (loaded
		// after this file, only where a collage is shown); not before it is there.
		( root || document ).querySelectorAll( '[data-wp-swiper-collage]' ).forEach( function ( el ) {
			if ( el.wpSwiperCollage || typeof window.wpSwiperCollage !== 'function' ) {
				return;
			}
			background( el.parentNode );
			try {
				el.wpSwiperCollage = window.wpSwiperCollage( el, JSON.parse( el.getAttribute( 'data-wp-swiper-collage' ) ) ) || true;
			} catch ( e ) {}
		} );

		// Sliders: made Swipers by slider.js (loaded after Swiper and this
		// file, only where a slider is shown); not before both are there.
		if ( typeof window.Swiper === 'undefined' || typeof window.wpSwiperSlider !== 'function' ) {
			return;
		}

		( root || document ).querySelectorAll( '[data-wp-swiper]' ).forEach( function ( el ) {
			if ( el.swiper ) {
				return;
			}

			var config;
			try {
				config = JSON.parse( el.getAttribute( 'data-wp-swiper' ) );
			} catch ( e ) {
				return;
			}

			window.wpSwiperSlider( el, config );
		} );
	}

	window.wpSwiperInit = init;
	// For slider.js and collage.js, the sliders' and the Collage effect's own
	// files: what they share with this one.
	window.wpSwiperShared = {
		BREAKPOINT_TABLET: BREAKPOINT_TABLET,
		BREAKPOINT_DESKTOP: BREAKPOINT_DESKTOP,
		device: device,
		fills: fills,
		fillHeight: fillHeight,
		watchNeighbours: watchNeighbours,
		background: background,
		stripOptions: stripOptions,
		stripShow: stripShow,
		lightboxItem: lightboxItem,
		openLightbox: openLightbox,
		filePlayer: filePlayer,
		embedPlayer: embedPlayer,
		posterFallbacks: posterFallbacks,
		LIGHTBOX_SHOWN: LIGHTBOX_SHOWN
	};
	// The lightbox for the Art effect (art.js), which is no slider of its own.
	window.wpSwiperLightbox = openLightbox;

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init();
		} );
	} else {
		init();
	}

	// WPBakery front-end editor re-renders elements without a page reload.
	if ( window.jQuery ) {
		window.jQuery( window ).on( 'vc_reload', function () {
			init();
		} );
	}
}() );
