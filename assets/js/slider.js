/**
 * WP Swiper sliders: every [data-wp-swiper] element made a Swiper, with its
 * effect, layout, controls, video slides and lightbox trigger. Loaded only
 * where a slider is shown (WP_Swiper_Render::enqueue()), after Swiper and
 * frontend.js, which calls window.wpSwiperSlider() for each slider (init()).
 * What it shares with frontend.js (the lightbox, the players, the heights)
 * comes from window.wpSwiperShared.
 */
( function () {
	'use strict';

	var shared = window.wpSwiperShared;
	if ( ! shared ) {
		return;
	}
	var BREAKPOINT_TABLET = shared.BREAKPOINT_TABLET;
	var BREAKPOINT_DESKTOP = shared.BREAKPOINT_DESKTOP;
	var device = shared.device;
	var fills = shared.fills;
	var stripOptions = shared.stripOptions;
	var stripShow = shared.stripShow;
	var openLightbox = shared.openLightbox;
	var LIGHTBOX_SHOWN = shared.LIGHTBOX_SHOWN;
	var lightboxItem = shared.lightboxItem;
	var filePlayer = shared.filePlayer;
	var embedPlayer = shared.embedPlayer;
	var posterFallbacks = shared.posterFallbacks;
	var fillHeight = shared.fillHeight;
	var watchNeighbours = shared.watchNeighbours;
	var background = shared.background;

	/**
	 * Swiper options of the slider's effect. `config.shadows`: the depth shading
	 * Swiper draws on slides, off unless "Effect shadows"; the effect's own
	 * settings (Layout): `config.cube` { shadow, offset, scale, slideShadows },
	 * `config.coverflow` { rotate, depth, stretch, scale } (see coverflowAt()),
	 * `config.cards` { offset, rotate, tilt }.
	 */
	function effectOptions( config ) {
		var shadows = !! config.shadows;
		var cards = config.cards;
		var cube = config.cube || { shadow: false, offset: 20, scale: 0.94, slideShadows: false };

		switch ( config.effect ) {
			case 'fade':
				return { fadeEffect: { crossFade: true } };
			case 'cube':
				return { cubeEffect: { shadow: !! cube.shadow, slideShadows: !! cube.slideShadows, shadowOffset: cube.offset, shadowScale: cube.scale } };
			case 'coverflow':
				// The phone's here; the larger screens' in their breakpoints (coverflowAt()).
				return { centeredSlides: true, coverflowEffect: coverflowAt( config, 'mobile' ) };
			case 'flip':
				return { flipEffect: { slideShadows: shadows, limitRotation: true } };
			case 'cards':
				cards = cards || { offset: 8, rotate: 2, tilt: true };
				return { cardsEffect: { slideShadows: shadows, rotate: !! cards.tilt, perSlideOffset: cards.offset, perSlideRotate: cards.rotate } };
			case 'creative':
				return { creativeEffect: creativePresets( config.creative || { style: 'push' }, shadows, config.direction === 'vertical' ) };
		}
		return {};
	}

	/**
	 * Swiper's coverflowEffect on a device: depth and stretch are per device
	 * ({ mobile, tablet, desktop }, a plain number in markup saved before:
	 * the same everywhere), rotate and scale one for all.
	 */
	function coverflowAt( config, device ) {
		var coverflow = config.coverflow || { rotate: 50, depth: 100, stretch: 0, scale: 1 };
		var at = function ( value ) {
			return 'object' === typeof value && null !== value ? value[ device ] : value;
		};
		return { rotate: coverflow.rotate, stretch: at( coverflow.stretch ), depth: at( coverflow.depth ), scale: coverflow.scale, modifier: 1, slideShadows: !! config.shadows };
	}

	/**
	 * Creative effect styles (Swiper's own demos), written for a horizontal
	 * slider. A vertical one gets them turned a quarter: moves along X go
	 * along Y, turns about Y (left/right) about X (up/down), and an edge on
	 * the left/right becomes the top/bottom one, so the slides move the way
	 * the slider is swiped.
	 */
	function creativePresets( creative, shadows, vertical ) {
		var style = creative.style;
		var carousel = creative.carousel;
		var preset;

		if ( style === 'carousel' && carousel ) {
			// Slides span the slider, the image takes the `width` share of it
			// (CSS): a neighbour step is `offset` image widths, in % of the slide.
			var step = ( carousel.offset * carousel.width * 100 ).toFixed( 3 ) + '%';
			var side = function ( sign ) {
				return { shadow: shadows, translate: [ sign + step, 0, 0 ], scale: carousel.scale, opacity: carousel.opacity };
			};
			preset = { limitProgress: carousel.neighbours, shadowPerProgress: true, prev: side( '-' ), next: side( '' ) };
			if ( vertical ) {
				preset.prev.translate = [ 0, preset.prev.translate[ 0 ], 0 ];
				preset.next.translate = [ 0, preset.next.translate[ 0 ], 0 ];
			}
			return preset;
		}

		var presets = {
			push: { prev: { shadow: shadows, translate: [ '-20%', 0, -1 ] }, next: { translate: [ '100%', 0, 0 ] } },
			zoom: { prev: { shadow: shadows, translate: [ 0, 0, -400 ] }, next: { translate: [ '100%', 0, 0 ] } },
			apart: { prev: { shadow: shadows, translate: [ '-120%', 0, -500 ] }, next: { shadow: shadows, translate: [ '120%', 0, -500 ] } },
			flip: { prev: { shadow: shadows, translate: [ 0, 0, -800 ], rotate: [ 180, 0, 0 ] }, next: { shadow: shadows, translate: [ 0, 0, -800 ], rotate: [ -180, 0, 0 ] } },
			rotate: { prev: { shadow: shadows, translate: [ '-125%', 0, -800 ], rotate: [ 0, 0, -90 ] }, next: { shadow: shadows, translate: [ '125%', 0, -800 ], rotate: [ 0, 0, 90 ] } },
			door: { prev: { shadow: shadows, origin: 'left center', translate: [ '-5%', 0, -200 ], rotate: [ 0, 100, 0 ] }, next: { origin: 'right center', translate: [ '5%', 0, -200 ], rotate: [ 0, -100, 0 ] } }
		};
		preset = presets[ style ] || presets.push;

		if ( vertical ) {
			[ 'prev', 'next' ].forEach( function ( side ) {
				var p = preset[ side ];
				p.translate = [ p.translate[ 1 ], p.translate[ 0 ], p.translate[ 2 ] ];
				if ( p.rotate ) {
					// rotateY(+) swings the right edge back; rotateX(-) the bottom one.
					p.rotate = [ -p.rotate[ 1 ], -p.rotate[ 0 ], p.rotate[ 2 ] ];
				}
				if ( p.origin ) {
					p.origin = { 'left center': 'center top', 'right center': 'center bottom' }[ p.origin ];
				}
			} );
		}
		return preset;
	}

	// The Creative "Carousel" settings of a slider, or null.
	function carouselOf( config ) {
		return config.creative && config.creative.style === 'carousel' && config.creative.carousel ? config.creative.carousel : null;
	}

	/**
	 * Hide the slides more than `limit` steps from the current one (CSS
	 * .wp-swiper-slide-beyond: faded out with the slide's own transition).
	 */
	function hideBeyond( swiper, limit ) {
		var update = function () {
			swiper.slides.forEach( function ( slide ) {
				slide.classList.toggle( 'wp-swiper-slide-beyond', Math.abs( slide.progress ) > limit + 0.5 );
			} );
		};
		swiper.on( 'setTranslate', update );
		update();
	}

	// Cards: how many cards of the stack show on each side. Further ones only
	// spread it wider (past the block on a phone) without looking any fuller.
	var CARDS_IN_STACK = 3;

	/**
	 * Cards: every move (arrows, pagination, keyboard, autoplay, the end of a
	 * drag) drawn frame by frame, like a drag. Swiper otherwise sets the end
	 * state at once and lets CSS move the cards there: the card coming up is
	 * put on top (z-index, which does not animate) at the very start, before
	 * it has moved. Drawn this way it takes the top halfway, as when dragged.
	 * The slider's state (index, pagination, events) changes at the start as
	 * usual; only the drawing follows over Transition speed.
	 */
	function cardsTween( swiper ) {
		var slideTo = swiper.slideTo;
		var emit = swiper.emit;
		var frame = null;
		// "...TransitionEnd" events of the instant move, held back until the
		// drawing ends: the autoplay times its delay from them.
		var ended = [];

		function flush() {
			var events = ended;
			ended = [];
			if ( ! events.length ) {
				return;
			}
			// Swiper's autoplay pauses at each move until the strip's CSS
			// transition ends ("transitionend" on the wrapper): there is none here.
			if ( swiper.wrapperEl ) {
				swiper.wrapperEl.dispatchEvent( new Event( 'transitionend' ) );
			}
			events.forEach( function ( args ) {
				emit.apply( swiper, args );
			} );
		}

		function stop() {
			if ( frame ) {
				cancelAnimationFrame( frame );
				frame = null;
			}
			flush();
		}

		// A drag takes over from wherever the cards are.
		swiper.on( 'touchStart destroy', stop );

		swiper.slideTo = function ( index, speed, runCallbacks, internal, initial ) {
			if ( undefined === speed ) {
				speed = swiper.params.speed;
			}
			stop();
			if ( ! speed || swiper.destroyed ) {
				return slideTo.call( swiper, index, speed, runCallbacks, internal, initial );
			}

			var from = swiper.translate;
			swiper.emit = function ( name ) {
				if ( /TransitionEnd$|^transitionEnd$/.test( name ) ) {
					ended.push( arguments );
					return swiper;
				}
				return emit.apply( swiper, arguments );
			};
			var result = slideTo.call( swiper, index, 0, runCallbacks, internal, initial );
			swiper.emit = emit;
			var to = swiper.translate;
			if ( from === to ) {
				flush();
				return result;
			}

			var start = null;
			var step = function ( now ) {
				if ( null === start ) {
					start = now;
				}
				var t = Math.min( 1, ( now - start ) / speed );
				// Ease in and out, as the cards' own transition did.
				var eased = t < 0.5 ? 4 * t * t * t : 1 - Math.pow( -2 * t + 2, 3 ) / 2;
				swiper.setTranslate( from + ( to - from ) * eased );
				frame = t < 1 ? requestAnimationFrame( step ) : null;
				if ( ! frame ) {
					flush();
				}
			};
			swiper.setTranslate( from );
			frame = requestAnimationFrame( step );
			return result;
		};
	}

	/**
	 * Creative "Carousel": slides past the last neighbour hidden, and a click
	 * on a neighbour brings it to the middle instead of opening its link or
	 * the lightbox (captured, so before them).
	 */
	function carouselSetup( swiper, el ) {
		// Swiper parks every slide past the last neighbour on that neighbour's
		// spot, underneath it: with "Contain" images of other proportions peek out.
		hideBeyond( swiper, swiper.params.creativeEffect.limitProgress );

		el.addEventListener( 'click', function ( e ) {
			var slide = e.target.closest( '.swiper-slide' );
			if ( ! swiper.allowClick || ! slide || slide === swiper.slides[ swiper.activeIndex ] ) {
				return;
			}
			e.preventDefault();
			e.stopPropagation();
			swiper.slideTo( swiper.slides.indexOf( slide ) );
		}, true );
	}

	var CURRENT_CLASS = 'wp-swiper-slide-current';

	/**
	 * The slide in view: the one the wider active slide layout marks as its
	 * current slide, else Swiper's active one. Videos and the zoom follow it.
	 */
	function shownSlide( swiper, el ) {
		return el.querySelector( '.swiper-slide.' + CURRENT_CLASS ) || swiper.slides[ swiper.activeIndex ];
	}

	function realIndexOf( slide ) {
		return Number( slide.getAttribute( 'data-wp-swiper-index' ) );
	}

	/**
	 * Of the Swiper loop indexes that show real slide `real` (the markup may
	 * repeat the set), the one nearest to `from`, going round the loop.
	 */
	function nearestCopy( real, from, config ) {
		var count = config.slideCount;
		var total = count * config.copies;
		var base = from - ( from % count ) + real;

		return [ base - count, base, base + count ].map( function ( index ) {
			return ( index + total ) % total;
		} ).sort( function ( a, b ) {
			var da = Math.abs( a - from ), db = Math.abs( b - from );
			return Math.min( da, total - da ) - Math.min( db, total - db );
		} )[ 0 ];
	}

	/**
	 * In loop mode, go to loop index `target` (0 .. total - 1) the short way
	 * round, one slide at a time: Swiper's slideToLoop() stops short on long
	 * jumps when there are few slides to spare. Every step but the last is
	 * instant; `instant` makes the last one instant too.
	 */
	function loopTo( swiper, target, total, instant ) {
		var diff = ( ( target - swiper.realIndex ) % total + total ) % total;
		var forward = diff <= total / 2;
		var steps = forward ? diff : total - diff;

		for ( var i = 0; i < steps; i++ ) {
			var speed = instant || i < steps - 1 ? 0 : undefined;
			if ( forward ) {
				swiper.slideNext( speed );
			} else {
				swiper.slidePrev( speed );
			}
		}
	}

	/* ------------------------------------------------------------------ *
	 * "Wider active slide" layout
	 *
	 * `visible` slides fill the slider: the current one is `active` % wide,
	 * the others share the rest equally. Swiper only scrolls the strip: its
	 * active index is the first visible slide, and Swiper sizes every slide
	 * as narrow. We track which visible slide is current (`offset`) and CSS
	 * widens it. Next/prev move the current slide across the visible ones;
	 * only at the edge does the strip scroll by one slide.
	 *
	 * Swiper's translate stays exact because every slide before the first
	 * visible one is narrow. The current slide's extra width is added after
	 * the last slide (slidesOffsetAfter) so the strip can reach the end.
	 * ------------------------------------------------------------------ */

	function expandOptions( options, config, el ) {
		var expand = config.expand;

		// Sizes in px along the slider axis (width, or height when vertical)
		// for a container `width` px long on that axis.
		function layout( width ) {
			var current = device();
			var gap = config.spaceBetween[ current ];
			var active = width * expand.active[ current ] / 100;
			var others = expand.visible[ current ] - 1;
			var narrow = Math.max( 1, ( width - active - others * gap ) / others );

			return {
				active: active,
				narrow: narrow,
				// slidesPerView that makes Swiper size every slide `narrow` px.
				perView: ( width + gap ) / ( narrow + gap ),
				gap: gap
			};
		}

		options.centeredSlides = false;
		options.breakpoints = {};
		var vertical = options.direction === 'vertical';
		var start = layout( ( vertical ? el.clientHeight : el.clientWidth ) || window.innerWidth );
		options.slidesPerView = start.perView;
		options.spaceBetween = start.gap;

		options.slidesOffsetAfter = function () {
			// Called by Swiper at the start of every layout pass, after the
			// container was measured and before slidesPerView and spaceBetween are
			// read, so the narrow width and the gap follow the device on resize.
			var sizes = layout( this.size ); // Swiper's size along its axis.
			this.params.slidesPerView = sizes.perView;
			this.params.spaceBetween = sizes.gap;
			return Math.max( 0, sizes.active - sizes.narrow );
		};

		// Driven by the controller below instead of Swiper.
		delete options.navigation;
		delete options.pagination;
		delete options.autoplay;
		delete options.keyboard;
	}

	/*
	 * With "Center active slide" the current slide sits in the middle of the
	 * visible ones and the strip scrolls on every step instead. With loop it
	 * is always centred; without, it moves towards an edge near the first and
	 * last slides, where there is nothing to fill the other side.
	 */
	function expandController( swiper, el, config ) {
		var listeners = [];
		var offset = 0;
		var lastReal = null;
		var centered = !! config.expand.centered;
		var total = config.slideCount * config.copies;

		function visible() {
			return config.expand.visible[ device() ];
		}

		// Middle position (an even count has no exact middle: one more slide after it).
		function middle() {
			return Math.floor( ( visible() - 1 ) / 2 );
		}

		function alwaysCentered() {
			return centered && swiper.params.loop;
		}

		function slideAt( position ) {
			return swiper.slides[ swiper.activeIndex + position ];
		}

		// Scroll the strip so real slide `real` is current at visible position
		// `at` (or as close to it as the first/last slides allow without loop).
		function scrollTo( real, at ) {
			if ( swiper.params.loop ) {
				// Loop indexes: the current slide is `at` after the first visible one.
				var target = nearestCopy( real, ( swiper.realIndex + at ) % total, config );
				offset = at;
				loopTo( swiper, ( target - at + total ) % total, total );
			} else {
				var first = Math.max( 0, Math.min( real - at, swiper.slides.length - visible() ) );
				offset = real - first;
				swiper.slideTo( first );
			}
			render();
		}

		function render() {
			if ( alwaysCentered() ) {
				offset = middle();
			}
			// The window may have shrunk (resize) or reached the end of the slides.
			offset = Math.max( 0, Math.min( offset, visible() - 1, swiper.slides.length - 1 - swiper.activeIndex ) );

			var current = slideAt( offset );
			swiper.slides.forEach( function ( slide ) {
				slide.classList.toggle( CURRENT_CLASS, slide === current );
			} );

			var real = current ? realIndexOf( current ) : 0;
			if ( real !== lastReal ) {
				lastReal = real;
				listeners.forEach( function ( fn ) {
					fn( real );
				} );
			}
		}

		var api = {
			current: function () {
				return lastReal;
			},

			isFirst: function () {
				return ! swiper.params.loop && swiper.activeIndex === 0 && offset === 0;
			},

			isLast: function () {
				return ! swiper.params.loop && swiper.activeIndex + offset >= swiper.slides.length - 1;
			},

			next: function () {
				if ( centered ) {
					if ( alwaysCentered() ) {
						swiper.slideNext();
					} else if ( offset < middle() && slideAt( offset + 1 ) ) {
						offset++; // Near the first slide: move towards the middle.
						render();
					} else if ( ! swiper.isEnd ) {
						swiper.slideNext();
					} else if ( offset < visible() - 1 && slideAt( offset + 1 ) ) {
						offset++; // Near the last slide: move past the middle.
						render();
					}
					return;
				}
				if ( offset < visible() - 1 && slideAt( offset + 1 ) ) {
					offset++;
					render();
				} else if ( swiper.params.loop || ! swiper.isEnd ) {
					swiper.slideNext();
				}
			},

			prev: function () {
				if ( centered ) {
					if ( alwaysCentered() ) {
						swiper.slidePrev();
					} else if ( offset > middle() ) {
						offset--;
						render();
					} else if ( ! swiper.isBeginning ) {
						swiper.slidePrev();
					} else if ( offset > 0 ) {
						offset--;
						render();
					}
					return;
				}
				if ( offset > 0 ) {
					offset--;
					render();
				} else if ( swiper.params.loop || ! swiper.isBeginning ) {
					swiper.slidePrev();
				}
			},

			// Make real slide `real` current: in place if visible, else scroll to it
			// (centred: always scroll it to the middle).
			goTo: function ( real ) {
				if ( centered ) {
					scrollTo( real, middle() );
					return;
				}
				for ( var i = 0; i < visible(); i++ ) {
					var slide = slideAt( i );
					if ( slide && realIndexOf( slide ) === real ) {
						offset = i;
						render();
						return;
					}
				}
				scrollTo( real, 0 );
			},

			// Click on a visible slide.
			select: function ( slide ) {
				if ( centered ) {
					api.goTo( realIndexOf( slide ) );
					return true;
				}
				var position = swiper.slides.indexOf( slide ) - swiper.activeIndex;
				if ( position >= 0 && position < visible() && position !== offset ) {
					offset = position;
					render();
					return true;
				}
				return false;
			},

			onChange: function ( fn ) {
				listeners.push( fn );
			}
		};

		swiper.on( 'activeIndexChange loopFix resize', render );

		// Centred with loop: start with the first slide in the middle.
		if ( alwaysCentered() ) {
			loopTo( swiper, ( total - middle() ) % total, total, true );
		}
		render();

		el.addEventListener( 'click', function ( e ) {
			if ( ! swiper.allowClick ) {
				return; // End of a drag, not a click.
			}
			var slide = e.target.closest( '.swiper-slide' );
			// A click on a narrow slide makes it current instead of following its link.
			if ( slide && ! slide.classList.contains( CURRENT_CLASS ) && api.select( slide ) ) {
				e.preventDefault();
			}
		} );

		return api;
	}

	/* ------------------------------------------------------------------ *
	 * Controls driven by a controller: everything in the expand layout; for
	 * other sliders only the pagination over repeated slides and the lightbox
	 * (Swiper drives their arrows, keyboard and autoplay itself).
	 * ------------------------------------------------------------------ */

	// The part of expandController's interface the pagination and the
	// lightbox use, backed by Swiper itself.
	function swiperController( swiper, config ) {
		var count = config.slideCount;

		return {
			current: function () {
				return swiper.realIndex % count;
			},
			goTo: function ( real ) {
				if ( swiper.params.loop ) {
					loopTo( swiper, nearestCopy( real, swiper.realIndex, config ), config.slideCount * config.copies );
				} else {
					swiper.slideTo( real );
				}
			},
			onChange: function ( fn ) {
				swiper.on( 'realIndexChange', function () {
					fn( swiper.realIndex % count );
				} );
			}
		};
	}

	/**
	 * Pagination over real slides (Swiper's own would count repeated copies
	 * and knows nothing about the expand layout's current slide).
	 */
	function pagination( pager, controller, config, swiper ) {
		var count = config.slideCount;
		var type = config.pagination;

		// Slides per group: one bullet (fraction step) per group of real slides,
		// as Swiper's own pagination does; the group size follows the device.
		function group() {
			return config.group ? Math.max( 1, Number( config.group[ device() ] ) || 1 ) : 1;
		}

		function steps() {
			return Math.ceil( count / group() );
		}

		pager.classList.add( 'swiper-pagination-' + type, 'swiper-pagination-' + ( swiper.isHorizontal() ? 'horizontal' : 'vertical' ) );

		function build() {
			pager.innerHTML = '';
			if ( type === 'bullets' ) {
				for ( var i = 0; i < steps(); i++ ) {
					var bullet = document.createElement( 'span' );
					bullet.className = 'swiper-pagination-bullet';
					bullet.setAttribute( 'role', 'button' );
					bullet.setAttribute( 'tabindex', '0' );
					bullet.setAttribute( 'aria-label', String( i + 1 ) );
					// The first real slide of the group.
					bullet.setAttribute( 'data-index', i * group() );
					pager.appendChild( bullet );
				}
			} else if ( type === 'fraction' ) {
				pager.innerHTML = '<span class="swiper-pagination-current"></span> / <span class="swiper-pagination-total">' + steps() + '</span>';
			}
		}

		build();

		if ( type === 'bullets' ) {
			pager.classList.add( 'swiper-pagination-clickable' );

			var go = function ( e ) {
				var target = e.target.closest( '.swiper-pagination-bullet' );
				if ( ! target || ( e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ' ) ) {
					return;
				}
				e.preventDefault();
				controller.goTo( Number( target.getAttribute( 'data-index' ) ) );
			};
			pager.addEventListener( 'click', go );
			pager.addEventListener( 'keydown', go );
		}

		var update = function ( index ) {
			var step = Math.floor( index / group() );
			if ( type === 'bullets' ) {
				[].forEach.call( pager.children, function ( bullet, i ) {
					bullet.classList.toggle( 'swiper-pagination-bullet-active', i === step );
				} );
			} else if ( type === 'fraction' ) {
				pager.firstChild.textContent = step + 1;
			}
		};

		controller.onChange( update );
		update( controller.current() );

		// Another device may have another group size.
		if ( config.group ) {
			swiper.on( 'breakpoint', function () {
				build();
				update( controller.current() );
			} );
		}
	}

	/**
	 * The slides' descriptions (render.php descriptions()): the block right
	 * after the slider's, or null.
	 *
	 * @param {Element} el The .swiper element.
	 */
	function descriptionsBox( el ) {
		var box = el.parentNode.nextElementSibling;
		return box && box.classList.contains( 'wp-swiper-descriptions' ) ? box : null;
	}

	/**
	 * The current slide's description shown; a slide without one shows none.
	 * On a change the one going and the one coming play the Text transition
	 * (frontend.css, .is-leaving / .is-entering) the way the slider went:
	 * forward or back (--wp-swiper-description-dir), with a loop the shorter
	 * way round. "None", or no motion wanted: they just swap.
	 */
	function descriptions( box, controller, config ) {
		var items = [].slice.call( box.querySelectorAll( '.wp-swiper-descriptions__item' ) );
		var count = config.slideCount;
		var still = box.classList.contains( 'wp-swiper-descriptions--none' ) || ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
		var current = controller.current();

		function forward( from, to ) {
			if ( config.loop ) {
				return ( to - from + count ) % count <= count / 2;
			}
			return to > from;
		}

		function settle( e ) {
			// Its own animation, not one of the text inside.
			if ( e.target === this ) {
				this.classList.remove( 'is-entering', 'is-leaving' );
			}
		}

		items.forEach( function ( item ) {
			item.addEventListener( 'animationend', settle );
		} );

		function show( index ) {
			if ( index === current ) {
				return;
			}
			box.style.setProperty( '--wp-swiper-description-dir', forward( current, index ) ? '1' : '-1' );
			current = index;
			items.forEach( function ( item ) {
				var coming = Number( item.getAttribute( 'data-wp-swiper-index' ) ) === index;
				var going = ! coming && item.classList.contains( 'is-active' );

				// A change during the last one's: from where things are now.
				item.classList.remove( 'is-entering', 'is-leaving' );
				item.classList.toggle( 'is-active', coming );
				if ( still || ! ( coming || going ) ) {
					return;
				}
				// Read layout so the animation starts over even with the same class.
				void item.offsetWidth;
				item.classList.add( coming ? 'is-entering' : 'is-leaving' );
			} );
		}

		controller.onChange( show );
		items.forEach( function ( item ) {
			item.classList.toggle( 'is-active', Number( item.getAttribute( 'data-wp-swiper-index' ) ) === current );
		} );
	}

	/**
	 * "Thumbnail height" on this device, px (frame included), or 0: sized by
	 * Thumbnails per view instead.
	 */
	function thumbHeight( settings, current ) {
		return settings.height ? Number( settings.height[ current ] ) || 0 : 0;
	}

	/**
	 * Thumbnails effect: the strip under (or above) the slider, one thumbnail
	 * per real slide. Tied to the slider through its controller, not Swiper's
	 * own thumbs module, which would count the slides loop mode repeats.
	 */
	function thumbs( strip, controller, config ) {
		var settings = config.thumbs;
		var items = strip.querySelectorAll( '.swiper-slide' );

		// Beside the slider on that device: a column (sized by thumbsBeside()).
		// Swiper turns the strip itself when a breakpoint changes the direction.
		var direction = function ( current ) {
			return settings.side[ current ] ? 'vertical' : 'horizontal';
		};

		var options = stripOptions( settings );
		options.direction = direction( 'mobile' );
		options.breakpoints[ BREAKPOINT_TABLET ].direction = direction( 'tablet' );
		options.breakpoints[ BREAKPOINT_DESKTOP ].direction = direction( 'desktop' );
		// Too few to fill the strip: in its middle, or at its start.
		options.centerInsufficientSlides = !! settings.center;

		// A set "Thumbnail height" on a device: thumbnails that tall, as many as
		// fit (Swiper's 'auto': each as wide as sizeItems() makes it).
		if ( thumbHeight( settings, 'mobile' ) ) {
			options.slidesPerView = 'auto';
		}
		if ( thumbHeight( settings, 'tablet' ) ) {
			options.breakpoints[ BREAKPOINT_TABLET ].slidesPerView = 'auto';
		}
		if ( thumbHeight( settings, 'desktop' ) ) {
			options.breakpoints[ BREAKPOINT_DESKTOP ].slidesPerView = 'auto';
		}

		// Below or above the slider: as wide as the proportions make a thumbnail
		// of that height. Beside it: that tall (the image fills the thumbnail
		// there, see the CSS) and as wide as the column (thumbsBeside()). Set as
		// the strip's CSS variables (.wp-swiper__thumbs--sized): in 'auto' mode
		// Swiper clears the slides' own inline sizes before it measures them.
		var sizeItems = function () {
			var current = device();
			var height = thumbHeight( settings, current );
			var side = settings.side[ current ];
			var frame = 2 * settings.frame;

			strip.classList.toggle( 'wp-swiper__thumbs--sized', height > 0 );
			strip.style.setProperty( '--wp-swiper-thumb-w', height && ! side ? Math.round( ( height - frame ) * settings.ratio[ current ] + frame ) + 'px' : '100%' );
			strip.style.setProperty( '--wp-swiper-thumb-h', height && side ? height + 'px' : 'auto' );
		};
		sizeItems();

		var swiper = new window.Swiper( strip, options );

		// Another device may have another height, or none (Swiper sizes them).
		// Also now: Swiper took this device's settings while it started.
		var resize = function () {
			sizeItems();
			swiper.update();
		};
		swiper.on( 'breakpoint resize', resize );
		resize();

		var go = function ( e ) {
			var item = e.target.closest( '.swiper-slide' );
			// Not at the end of a drag of the strip.
			if ( ! item || ( e.type === 'click' && ! swiper.allowClick ) || ( e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ' ) ) {
				return;
			}
			e.preventDefault();
			controller.goTo( realIndexOf( item ) );
		};
		strip.addEventListener( 'click', go );
		strip.addEventListener( 'keydown', go );

		// Arrows on the strip: to the previous / next slide (the strip follows);
		// round the loop, or stopping at the first and last slides without one.
		var prev = strip.querySelector( '.swiper-button-prev' );
		var next = strip.querySelector( '.swiper-button-next' );
		var step = function ( by ) {
			var target = controller.current() + by;
			if ( config.loop ) {
				target = ( target + items.length ) % items.length;
			} else if ( target < 0 || target >= items.length ) {
				return;
			}
			controller.goTo( target );
		};
		if ( prev && next ) {
			prev.addEventListener( 'click', function () {
				step( -1 );
			} );
			next.addEventListener( 'click', function () {
				step( 1 );
			} );
		}

		var update = function ( index ) {
			if ( prev && next ) {
				prev.classList.toggle( 'swiper-button-disabled', ! config.loop && index <= 0 );
				next.classList.toggle( 'swiper-button-disabled', ! config.loop && index >= items.length - 1 );
			}
			stripShow( swiper, items, index );
		};

		controller.onChange( update );
		update( controller.current() );
	}

	/**
	 * Thumbnails beside the slider: the column is as tall as the slider (CSS)
	 * and "Thumbnails per view" of them fill it, which sets their height (unless
	 * "Thumbnail height" sets it: then the rest scroll); the
	 * column is then as wide as their proportions make them. Where the slider's
	 * height follows from its width (no set Height), the two depend on each
	 * other, so it is worked out in a few rounds. Before Swiper starts (it
	 * measures the slider) and whenever the slider is resized. On a device
	 * where the strip is above or below the slider it spans it (CSS): no width.
	 * Returns the update.
	 */
	function thumbsBeside( root, el, config ) {
		var settings = config.thumbs;
		var strip = root.querySelector( '.wp-swiper__thumbs' );

		function fit() {
			var current = device();
			if ( ! settings.side[ current ] ) {
				strip.style.width = '';
				return;
			}
			for ( var round = 0; round < 5; round++ ) {
				var perView = Number( settings.perView[ current ] ) || 1;
				var gap = Number( settings.gap[ current ] ) || 0;
				var frame = 2 * settings.frame;
				// A set "Thumbnail height", or that many filling the slider's height.
				var thumb = thumbHeight( settings, current ) || ( el.offsetHeight - gap * ( perView - 1 ) ) / perView;
				var width = Math.max( 0, Math.round( ( thumb - frame ) * settings.ratio[ current ] + frame ) );

				if ( Math.abs( width - strip.offsetWidth ) <= 1 ) {
					break;
				}
				strip.style.width = width + 'px';
			}
		}

		fit();
		window.addEventListener( 'resize', fit );
		return fit;
	}

	function navigation( el, controller ) {
		var prev = el.querySelector( '.swiper-button-prev' );
		var next = el.querySelector( '.swiper-button-next' );

		prev.addEventListener( 'click', controller.prev );
		next.addEventListener( 'click', controller.next );

		var update = function () {
			prev.classList.toggle( 'swiper-button-disabled', controller.isFirst() );
			next.classList.toggle( 'swiper-button-disabled', controller.isLast() );
		};
		controller.onChange( update );
		update();
	}

	function keyboard( el, controller, vertical ) {
		var inView = false;

		if ( 'IntersectionObserver' in window ) {
			new IntersectionObserver( function ( entries ) {
				inView = entries[ 0 ].isIntersecting;
			} ).observe( el );
		}

		document.addEventListener( 'keydown', function ( e ) {
			var tag = document.activeElement && document.activeElement.tagName;
			var lightboxOpen = document.documentElement.classList.contains( 'wp-swiper-lightbox-open' );
			if ( ! inView || lightboxOpen || /^(INPUT|TEXTAREA|SELECT)$/.test( tag ) ) {
				return;
			}
			if ( e.key === ( vertical ? 'ArrowDown' : 'ArrowRight' ) ) {
				e.preventDefault(); // Do not scroll the page as well.
				controller.next();
			} else if ( e.key === ( vertical ? 'ArrowUp' : 'ArrowLeft' ) ) {
				e.preventDefault();
				controller.prev();
			}
		} );
	}

	function autoplay( el, controller, settings ) {
		var timer = null;
		var paused = false;
		var stopped = false;

		var schedule = function () {
			clearTimeout( timer );
			if ( paused || stopped ) {
				return;
			}
			// isLast() is only ever true without loop: stop there, as Swiper's own autoplay does.
			if ( controller.isLast() ) {
				return;
			}
			timer = setTimeout( controller.next, settings.delay );
		};

		// Any change (also a user click) restarts the countdown.
		controller.onChange( schedule );

		if ( settings.pauseOnMouseEnter ) {
			el.addEventListener( 'mouseenter', function () {
				paused = true;
				clearTimeout( timer );
			} );
			el.addEventListener( 'mouseleave', function () {
				paused = false;
				schedule();
			} );
		}

		schedule();

		return {
			stop: function () {
				stopped = true;
				clearTimeout( timer );
			},
			start: function () {
				stopped = false;
				schedule();
			}
		};
	}

	/**
	 * The slide a click inside the slider was meant for. Usually the one under
	 * the pointer; but once a cube has turned, browsers stop hit-testing its
	 * active face and the click lands on the slider itself, so there (the face
	 * fills the slider at rest) it is the active slide. Not for its arrows or pagination.
	 */
	function clickedSlide( e, swiper, config ) {
		var slide = e.target.closest( '.swiper-slide' );
		if ( ! slide && 'cube' === config.effect && ! e.target.closest( 'button, .swiper-pagination' ) ) {
			slide = swiper.slides[ swiper.activeIndex ] || null;
		}
		return slide;
	}

	/**
	 * Cube: follow the active slide's link when the click could not reach it
	 * (see clickedSlide).
	 */
	function cubeLinks( swiper, el, config ) {
		el.addEventListener( 'click', function ( e ) {
			if ( ! swiper.allowClick || e.defaultPrevented || e.target.closest( '.swiper-slide, button, .swiper-pagination' ) ) {
				return;
			}
			var slide = clickedSlide( e, swiper, config );
			var link = slide && slide.querySelector( '.wp-swiper__link' );
			if ( link ) {
				window.open( link.href, link.target || '_self', link.target === '_blank' ? 'noopener' : '' );
			}
		} );
	}

	function lightboxTrigger( swiper, el, config, controller, player ) {
		el.addEventListener( 'click', function ( e ) {
			// Drags, slide links, and clicks the expand layout used to pick a slide
			// do not open the lightbox.
			if ( ! swiper.allowClick || e.defaultPrevented || e.target.closest( '.wp-swiper__link' ) ) {
				return;
			}

			// A click on a video's own controls is the player's.
			if ( e.target.tagName === 'VIDEO' && e.target.controls ) {
				return;
			}

			var SHOWN = LIGHTBOX_SHOWN;
			var slide = clickedSlide( e, swiper, config );
			if ( ! slide || ! slide.matches( SHOWN ) ) {
				return;
			}
			e.preventDefault();

			// Thumbnails in the lightbox: the small files of the slider's own strip.
			var withThumbs = config.lightbox.thumbs && config.thumbs;
			var small = withThumbs ? el.parentNode.querySelectorAll( '.wp-swiper__thumbs .wp-swiper__thumb-image' ) : [];

			// "Descriptions in the lightbox": each slide's, from the block under the slider.
			var texts = {};
			var box = config.lightbox.descriptions ? descriptionsBox( el ) : null;
			if ( box ) {
				box.querySelectorAll( '.wp-swiper-descriptions__item' ).forEach( function ( item ) {
					texts[ item.getAttribute( 'data-wp-swiper-index' ) ] = item.innerHTML;
				} );
			}

			// One item per real slide it shows, whatever copies the markup holds, in
			// slide order; `real`: its slide's index.
			var items = [];
			var seen = {};
			el.querySelectorAll( '.swiper-slide' ).forEach( function ( node ) {
				var real = realIndexOf( node );
				if ( seen[ real ] || ! node.matches( SHOWN ) ) {
					return;
				}
				seen[ real ] = true;
				var thumb = small[ real ] && small[ real ].tagName === 'IMG' ? small[ real ].currentSrc || small[ real ].src : '';
				var item = lightboxItem( node, real, thumb );
				item.description = texts[ real ] || '';
				items.push( item );
			} );
			items.sort( function ( a, b ) {
				return a.real - b.real;
			} );
			var start = 0;
			items.forEach( function ( item, i ) {
				if ( item.real === realIndexOf( slide ) ) {
					start = i;
				}
			} );

			// The slider stays still and deaf to arrow keys while the lightbox is open.
			var keys = swiper.keyboard && swiper.keyboard.enabled;
			if ( keys ) {
				swiper.keyboard.disable();
			}
			if ( player ) {
				player.stop();
			}

			openLightbox( items, start, function ( shown ) {
				var last = items[ shown ].real;
				if ( keys ) {
					swiper.keyboard.enable();
				}
				// Leave the slider on the slide the visitor looked at last.
				if ( last !== controller.current() ) {
					controller.goTo( last );
				}
				if ( player ) {
					player.start();
				}
			}, { radius: config.lightbox.radius, thumbRadius: config.lightbox.thumbRadius, thumbs: withThumbs ? config.thumbs : false, text: config.lightbox.text || false } );
		} );
	}

	/* ------------------------------------------------------------------ *
	 * Video slides
	 *
	 * Only the video of the slide in view plays (the current one of the
	 * wider active slide layout), and only while the slider is on screen and
	 * no lightbox is open. A file loads when its slide comes up (render.php
	 * leaves its address in data-src), the next slide's ahead as "Preload"
	 * says; a YouTube / Vimeo player is put in when its slide first plays.
	 * The copies loop mode repeats load nothing until they come up themselves.
	 * ------------------------------------------------------------------ */

	/**
	 * @param {Object}   swiper The slider.
	 * @param {Element}  el     The .swiper element.
	 * @param {Object}   config Slider config (config.video: the settings).
	 * @param {Object}   player The slider's autoplay ({ stop, start }) or null.
	 * @param {Function} next   Goes to the next slide.
	 */
	function videos( swiper, el, config, player, next ) {
		var settings = config.video;
		// A video the slider waits for plays once: a YouTube / Vimeo player is
		// made so from the start (its loop is part of its address).
		var embedSettings = { muted: settings.muted, loop: settings.loop && ! settings.wait, controls: settings.controls };
		var players = [].map.call( el.querySelectorAll( 'video.wp-swiper__video, .wp-swiper__embed' ), function ( node ) {
			return node.tagName === 'VIDEO'
				? filePlayer( node, settings )
				: embedPlayer( node, JSON.parse( node.getAttribute( 'data-wp-swiper-embed-player' ) || '{}' ), embedSettings );
		} );
		var inView = true;
		var lightboxOpen = false;
		var current = null;
		// The video the slider's autoplay is held for ("Next slide when the video ends").
		var waiting = null;

		function playerOf( slide ) {
			for ( var i = 0; slide && i < players.length; i++ ) {
				if ( slide.contains( players[ i ].el ) ) {
					return players[ i ];
				}
			}
			return null;
		}

		// The slider's autoplay again, once it no longer waits for a video.
		function release() {
			if ( waiting ) {
				waiting = null;
				if ( player ) {
					player.start();
				}
			}
		}

		function sync() {
			var slide = shownSlide( swiper, el );
			var video = playerOf( slide );

			players.forEach( function ( other ) {
				if ( other !== video && other.playing() && ( settings.pause || ! inView || lightboxOpen ) ) {
					other.pause();
				}
			} );
			// Moved on (a click, the arrows) before the video ended.
			if ( waiting && waiting !== video ) {
				release();
			}
			current = video;

			// The next slide's video, ahead of time (a file only).
			if ( settings.preload !== 'none' && slide ) {
				var following = playerOf( slide.nextElementSibling );
				if ( following ) {
					following.load( settings.preload );
				}
			}

			if ( ! video ) {
				return;
			}
			if ( ! settings.autoplay ) {
				// The visitor starts it (controls, or the play button): its first frame meanwhile.
				video.load( 'metadata' );
				return;
			}
			if ( ! inView || lightboxOpen ) {
				return;
			}
			if ( settings.wait && player && waiting !== video ) {
				// Hold the slider until the video ends; it plays once, from the start.
				waiting = video;
				player.stop();
				video.once();
			}
			if ( ! video.playing() ) {
				video.play();
			}
		}

		players.forEach( function ( video ) {
			video.onEnded( function () {
				if ( video === waiting ) {
					waiting = null;
					if ( player ) {
						player.start();
					}
					next();
				}
			} );
			// A file that cannot play must not hold the slider.
			video.onError( function () {
				if ( video === waiting ) {
					release();
				}
			} );
		} );

		// A YouTube / Vimeo video's play button: it plays there (unless a click
		// opens the lightbox instead, "Videos in the lightbox").
		if ( ! ( config.lightbox && config.lightbox.videos ) ) {
			el.addEventListener( 'click', function ( e ) {
				var box = e.target.closest( '.wp-swiper__embed' );
				var video = box && swiper.allowClick ? playerOf( box ) : null;
				if ( video && ! video.playing() ) {
					e.preventDefault();
					video.play();
				}
			} );
		}

		// Nothing plays behind an open lightbox.
		document.addEventListener( 'wpswiperlightbox', function ( e ) {
			lightboxOpen = !! ( e.detail && e.detail.open );
			if ( lightboxOpen && current && current.playing() ) {
				current.pause();
			} else if ( ! lightboxOpen ) {
				sync();
			}
		} );

		// Off screen: nothing plays.
		if ( 'IntersectionObserver' in window ) {
			new IntersectionObserver( function ( entries ) {
				inView = entries[ 0 ].isIntersecting;
				if ( inView ) {
					sync();
				} else if ( current && current.playing() ) {
					current.pause();
				}
			}, { threshold: 0.25 } ).observe( el );
		}

		posterFallbacks( el );
		swiper.on( 'activeIndexChange transitionEnd', sync );
		// The wider active slide layout moves its current slide without Swiper.
		if ( config.expand ) {
			new MutationObserver( sync ).observe( el.querySelector( '.swiper-wrapper' ), { subtree: true, attributes: true, attributeFilter: [ 'class' ] } );
		}
		sync();
	}

	/* ------------------------------------------------------------------ */

	function buildOptions( el, config ) {
		var single = config.single; // Effect shows exactly one slide (set in PHP).
		// "Width from image": Swiper measures each slide (its CSS width, see
		// .wp-swiper--auto-width) instead of dividing the slider.
		var perView = config.autoWidth ? { mobile: 'auto', tablet: 'auto', desktop: 'auto' } : config.slidesPerView;

		var options = {
			// Thumbnails: sliding or fading ("Transition"); the strip is a Swiper of its own (thumbs()).
			effect: config.thumbs ? ( config.thumbs.fade ? 'fade' : 'slide' ) : config.effect,
			direction: config.direction,
			speed: config.speed,
			loop: config.loop,
			grabCursor: true,
			centeredSlides: config.centered,
			spaceBetween: single ? 0 : config.spaceBetween.mobile,
			slidesPerView: single ? 1 : perView.mobile,
			breakpoints: {},
			watchSlidesProgress: true
		};

		if ( ! single ) {
			options.breakpoints[ BREAKPOINT_TABLET ] = { slidesPerView: perView.tablet, spaceBetween: config.spaceBetween.tablet };
			options.breakpoints[ BREAKPOINT_DESKTOP ] = { slidesPerView: perView.desktop, spaceBetween: config.spaceBetween.desktop };
		}

		// Slides per group (Slide effect).
		if ( config.group ) {
			options.slidesPerGroup = config.group.mobile;
			options.breakpoints[ BREAKPOINT_TABLET ].slidesPerGroup = config.group.tablet;
			options.breakpoints[ BREAKPOINT_DESKTOP ].slidesPerGroup = config.group.desktop;
		}

		Object.assign( options, effectOptions( config ) );
		if ( 'coverflow' === options.effect && ! single ) {
			options.breakpoints[ BREAKPOINT_TABLET ].coverflowEffect = coverflowAt( config, 'tablet' );
			options.breakpoints[ BREAKPOINT_DESKTOP ].coverflowEffect = coverflowAt( config, 'desktop' );
		}
		if ( config.thumbs && config.thumbs.fade ) {
			options.fadeEffect = { crossFade: true };
		}

		// Creative "Carousel": loop mode keeps the neighbours in view on both
		// sides; centred, so it does from the start too (else Swiper only adds
		// the spare slides after the first one until the first step back).
		if ( carouselOf( config ) ) {
			options.loopAdditionalSlides = carouselOf( config ).neighbours;
			options.centeredSlides = true;
		}

		// Once a cube or flip has turned, browsers stop hit-testing its active face:
		// presses land on the slider itself, which Swiper ignores by default (it
		// listens to the slides' wrapper), so dragging would stop working.
		if ( 'cube' === config.effect || 'flip' === config.effect ) {
			options.touchEventsTarget = 'container';
		}

		if ( config.autoplay ) {
			options.autoplay = {
				delay: config.autoplay.delay,
				pauseOnMouseEnter: config.autoplay.pauseOnMouseEnter,
				disableOnInteraction: false,
				// Without loop, stop at the end instead of jumping back to the first slide.
				stopOnLastSlide: ! config.loop
			};
		}

		// Continuous scroll: autoplay with no pause between steps, each step at
		// an even speed (marquee() sets it per slide) and linear (CSS). Nothing
		// may stop it: no dragging, no pause on hover (it would halt mid-step).
		// Left to right (vertical: top to bottom): the autoplay goes back.
		if ( config.marquee ) {
			options.autoplay = { delay: 0, disableOnInteraction: false, pauseOnMouseEnter: false, reverseDirection: !! config.marquee.reverse };
			options.allowTouchMove = false;
		}

		if ( config.navigation ) {
			options.navigation = {
				prevEl: el.querySelector( '.swiper-button-prev' ),
				nextEl: el.querySelector( '.swiper-button-next' )
			};
		}

		if ( config.pagination && config.copies === 1 ) {
			options.pagination = {
				el: el.parentNode.querySelector( '.swiper-pagination' ),
				type: config.pagination,
				clickable: true
			};
		}

		if ( config.keyboard ) {
			options.keyboard = { enabled: true, onlyInViewport: true };
		}

		if ( config.expand ) {
			expandOptions( options, config, el );
		}

		return options;
	}

	/**
	 * Continuous scroll: a step moves the strip by the current slide (plus the
	 * gap), so its duration is that distance at the set speed, whatever the
	 * slide's width ("Width from image" slides differ). Going back (Left to
	 * right), by the slide before it, the one coming in (before the first: the
	 * last, the loop goes round).
	 */
	function marquee( swiper, config ) {
		function setSpeed() {
			var sizes = swiper.slidesSizesGrid;
			var index = config.marquee.reverse ? ( swiper.activeIndex - 1 + sizes.length ) % ( sizes.length || 1 ) : swiper.activeIndex;
			var size = sizes[ index ] || swiper.size;
			var gap = Number( swiper.params.spaceBetween ) || 0;
			swiper.params.speed = Math.round( ( size + gap ) / config.marquee.speed * 1000 );
		}
		setSpeed();
		// Also once the slides have their final sizes (they may still change
		// after init, e.g. once the slider gets its height).
		swiper.on( 'activeIndexChange resize slidesUpdated', setSpeed );

		if ( config.marquee.pause ) {
			marqueePause( swiper, config );
		}
	}

	/**
	 * Pause on hover for the continuous scroll. Swiper's own would only stop
	 * at the end of a step; this freezes the strip where it is and, on leave,
	 * finishes the step at the same speed before the autoplay carries on.
	 */
	function marqueePause( swiper, config ) {
		var paused = false;

		// Where the strip is drawn right now (mid-transition).
		function drawnTranslate() {
			var matrix = new DOMMatrix( getComputedStyle( swiper.wrapperEl ).transform );
			return swiper.isHorizontal() ? matrix.m41 : matrix.m42;
		}

		swiper.el.addEventListener( 'mouseenter', function () {
			if ( paused ) {
				return;
			}
			paused = true;
			swiper.autoplay.stop();
			var at = drawnTranslate();
			swiper.setTransition( 0 );
			swiper.setTranslate( at );
		} );

		swiper.el.addEventListener( 'mouseleave', function () {
			if ( ! paused ) {
				return;
			}
			paused = false;
			// The rest of the step that was interrupted: to the current slide.
			var left = Math.abs( -swiper.snapGrid[ swiper.snapIndex ] - swiper.translate );
			var duration = Math.round( left / config.marquee.speed * 1000 );
			if ( duration < 1 ) {
				swiper.autoplay.start();
				return;
			}
			swiper.once( 'transitionEnd', function () {
				if ( ! paused ) {
					swiper.autoplay.start();
				}
			} );
			swiper.slideTo( swiper.activeIndex, duration );
		} );
	}

	/**
	 * Image proportions: on the devices that have them every image is as tall
	 * as the slider (Height) and as wide as its proportions make it, and
	 * Slides per view of them share the row like flex items. Space between:
	 * the outer ones at the edges, Swiper's gap is the free width shared out.
	 * Space around: no gap, each slide an equal share with its image in the
	 * middle (.wp-swiper--around). The set gap is the least space between the
	 * images; where they do not fit with it they get smaller, and so does the
	 * slider's height. Again whenever the slider resizes or changes device.
	 */
	function distribute( swiper, el, config ) {
		var root = el.parentNode;
		var last = '';

		function apply() {
			var current = device();
			var ratio = config.distribute.ratio[ current ];
			var gap = Number( config.spaceBetween[ current ] ) || 0;
			var around = false;
			var imageWidth = 0;

			el.style.height = '';
			if ( ratio && el.clientWidth && el.clientHeight ) {
				// The width Swiper divides (its own measure), so the slides come out
				// exactly as wide as the images.
				var width = swiper.width || el.clientWidth;
				var height = el.clientHeight;
				var perView = Math.max( 1, Number( config.slidesPerView[ current ] ) || 1 );
				around = 'around' === config.distribute.mode || perView <= 1;
				imageWidth = height * ratio;
				if ( around ) {
					imageWidth = Math.min( imageWidth, width / perView - gap );
					gap = 0;
				} else {
					imageWidth = Math.min( imageWidth, ( width - gap * ( perView - 1 ) ) / perView );
					gap = ( width - perView * imageWidth ) / ( perView - 1 );
				}
				imageWidth = Math.max( 1, imageWidth );
				if ( imageWidth / ratio < height - 0.5 ) {
					el.style.height = ( imageWidth / ratio ) + 'px';
				}
			}

			root.classList.toggle( 'wp-swiper--around', around );
			root.style.setProperty( '--wp-swiper-image-width', imageWidth ? imageWidth + 'px' : '' );
			// Space around: the share's free width either side of the image's
			// frame ("Contain" fits the image in the frame, not the whole share).
			root.style.setProperty( '--wp-swiper-around-pad', around ? Math.max( 0, ( ( swiper.width || el.clientWidth ) / Math.max( 1, Number( config.slidesPerView[ current ] ) || 1 ) - imageWidth ) / 2 ) + 'px' : '' );
			// The separator stands in the middle of the space Swiper leaves.
			root.style.setProperty( '--wp-swiper-gap-live', ratio ? gap + 'px' : '' );

			var key = [ current, gap, imageWidth, el.style.height ].join( '|' );
			if ( key === last ) {
				return;
			}
			last = key;
			swiper.params.spaceBetween = gap;
			swiper.update();
		}

		apply();
		// Right after Swiper's own resize (its handler runs first), not a frame
		// later: the slides would show at the old space for that frame.
		swiper.on( 'resize breakpoint', apply );
	}

	/**
	 * Wire up the controls Swiper does not handle itself for this slider.
	 */
	function setupControls( swiper, el, config ) {
		var controller;
		// Swiper's own autoplay, unless the expand layout replaces it below.
		var player = swiper.autoplay && ( config.autoplay || config.marquee ) && ! config.expand ? swiper.autoplay : null;

		if ( config.marquee ) {
			marquee( swiper, config );
		}
		if ( carouselOf( config ) ) {
			carouselSetup( swiper, el );
		}
		if ( 'cards' === config.effect ) {
			hideBeyond( swiper, CARDS_IN_STACK );
			cardsTween( swiper );
		}

		if ( config.expand ) {
			controller = expandController( swiper, el, config );

			if ( config.navigation ) {
				navigation( el, controller );
			}
			if ( config.pagination ) {
				pagination( el.parentNode.querySelector( '.swiper-pagination' ), controller, config, swiper );
			}
			if ( config.keyboard ) {
				keyboard( el, controller, swiper.isVertical() );
			}
			if ( config.autoplay ) {
				player = autoplay( el, controller, config.autoplay );
			}
		} else {
			controller = swiperController( swiper, config );

			if ( config.pagination && config.copies > 1 ) {
				pagination( el.parentNode.querySelector( '.swiper-pagination' ), controller, config, swiper );
			}
			if ( config.thumbs ) {
				thumbs( el.parentNode.querySelector( '.wp-swiper__thumbs' ), controller, config );
			}
		}

		var described = config.descriptions ? descriptionsBox( el ) : null;
		if ( described ) {
			descriptions( described, controller, config );
		}

		if ( config.lightbox ) {
			lightboxTrigger( swiper, el, config, controller, player );
		} else if ( 'cube' === config.effect ) {
			cubeLinks( swiper, el, config );
		}

		if ( config.video ) {
			videos( swiper, el, config, player, config.expand ? controller.next : function () {
				swiper.slideNext();
			} );
		}
	}

	/* ------------------------------------------------------------------ *
	 * Image file size (sizes attribute)
	 *
	 * WordPress tells the browser an image is as wide as its slide, but with
	 * Image fit "Cover" the image is cropped and drawn wider than a tall, narrow
	 * slide: the browser would pick a file too small and the image would look
	 * blurry. Give each image the width it is really drawn at (in the wider
	 * active slide layout: at the active size, which any slide can take).
	 * ------------------------------------------------------------------ */

	function imageSizes( swiper, config ) {
		var cover = ! swiper.el.parentNode.classList.contains( 'wp-swiper--fit-contain' );
		var active = config.expand ? swiper.size * config.expand.active[ device() ] / 100 : 0;
		var carousel = carouselOf( config );

		swiper.slides.forEach( function ( slide, index ) {
			// The slide's own image, not the continuous scroll's separator.
			var img = slide.querySelector( '.wp-swiper__media img' );
			if ( ! img ) {
				return;
			}
			// Along the slider axis, Swiper's own slide size: the rendered one may be
			// mid-transition. Across it, the layout size (offset* ignore effect
			// transforms such as cards or coverflow).
			var main = swiper.slidesSizesGrid[ index ];
			var width = swiper.isHorizontal() && main ? main : slide.offsetWidth;
			var height = ! swiper.isHorizontal() && main ? main : slide.offsetHeight;
			if ( active ) {
				if ( swiper.isHorizontal() ) {
					width = Math.max( width, active );
				} else {
					height = Math.max( height, active );
				}
			}
			// Creative "Carousel": the image takes its share of the slide.
			if ( carousel ) {
				if ( swiper.isHorizontal() ) {
					width *= carousel.width;
				} else {
					height *= carousel.width;
				}
			}
			var ratio = img.getAttribute( 'width' ) / img.getAttribute( 'height' ) || 1;
			var drawn = cover ? Math.max( width, height * ratio ) : Math.min( width, height * ratio );
			var sizes = Math.ceil( drawn ) + 'px';

			if ( drawn > 0 && img.getAttribute( 'sizes' ) !== sizes ) {
				img.setAttribute( 'sizes', sizes );
			}
		} );
	}

	/**
	 * Image fit "Contain": the most height the slides can use, i.e. that of the
	 * tallest image as wide as its slide (vertical sliders: as wide as the
	 * slider, times the slides in view). Any more would only add empty space
	 * above and below the images, so a taller Height is capped to it. It
	 * depends on the widths only, so capping never changes it. 0: no cap.
	 *
	 * @param {Element} el     The .swiper element.
	 * @param {Object}  config Slider config.
	 * @return {number} px
	 */
	function containCap( el, config ) {
		// "Width from image" slides are as wide as their images: never a gap to cap.
		if ( ! el.parentNode.classList.contains( 'wp-swiper--fit-contain' ) || config.autoWidth || ! el.clientWidth ) {
			return 0;
		}
		var current = device();
		var perView = config.single ? 1 : Number( config.slidesPerView[ current ] );
		var gap = config.single ? 0 : Number( config.spaceBetween[ current ] );

		// The tallest image is the one with the smallest width / height.
		var ratio = Infinity;
		var widest = 0;
		el.querySelectorAll( '.wp-swiper__image' ).forEach( function ( img ) {
			var r = img.getAttribute( 'width' ) / img.getAttribute( 'height' );
			if ( r > 0 ) {
				ratio = Math.min( ratio, r );
				widest = Math.max( widest, r );
			}
		} );
		if ( ratio === Infinity ) {
			return 0;
		}

		// Creative "Carousel": all images share the height at which the widest
		// fits the middle share of the slider (CSS); a vertical slider's
		// images share a width instead, nothing to cap.
		var carousel = carouselOf( config );
		if ( carousel ) {
			return config.direction === 'vertical' ? 0 : Math.floor( carousel.width * el.clientWidth / widest );
		}

		if ( config.direction === 'vertical' ) {
			return Math.floor( perView * el.clientWidth / ratio + gap * ( perView - 1 ) );
		}
		return Math.floor( ( el.clientWidth - gap * ( perView - 1 ) ) / perView / ratio );
	}

	/**
	 * A set Height ("px", "vh", "%"), capped for "Contain" (see containCap).
	 * Before Swiper starts (vertical sliders measure their height) and on
	 * resize: Swiper notices the new size itself. Returns the update, for
	 * Swiper's own resize too.
	 */
	function capHeight( el, config ) {
		function apply() {
			// Height "Auto" on this device: capped by fillHeight() instead.
			var cap = fills( config ) ? 0 : containCap( el, config );
			el.style.maxHeight = cap ? cap + 'px' : '';
		}
		apply();
		window.addEventListener( 'resize', apply );
		return apply;
	}

	/**
	 * Height "Auto" where the device in view has it (the block marked
	 * .wp-swiper--fill and sized to its parent); elsewhere its own Height
	 * (the CSS's), the size set before taken back.
	 *
	 * @param {Element} root       The .wp-swiper block.
	 * @param {Element} el         The .swiper element.
	 * @param {Object}  config     Slider config.
	 * @param {boolean} needsFixed The slides cannot take the image proportions.
	 * @return {number} The block's height.
	 */
	function fitHeight( root, el, config, needsFixed ) {
		var on = fills( config );
		root.classList.toggle( 'wp-swiper--fill', on );
		if ( ! on ) {
			root.style.height = '';
			return root.offsetHeight;
		}
		return fillHeight( root, needsFixed, containCap( el, config ) );
	}

	function fillParent( root, swiper, needsFixed, config ) {
		var last = fitHeight( root, swiper.el, config, needsFixed );
		var queued = false;

		function refit() {
			if ( queued ) {
				return;
			}
			queued = true;
			requestAnimationFrame( function () {
				queued = false;
				var size = fitHeight( root, swiper.el, config, needsFixed );
				if ( Math.abs( size - last ) > 1 ) {
					last = size;
					swiper.update();
				}
			} );
		}

		window.addEventListener( 'resize', refit );
		window.addEventListener( 'load', refit );
		watchNeighbours( root, refit );
	}

	/* ------------------------------------------------------------------ *
	 * "Zoom the main image"
	 *
	 * The image of the slide in view zooms from one scale to the other
	 * (config.zoom) towards the point chosen (CSS: transform-origin), over
	 * the time it is shown. The slide left eases back to the waiting scale
	 * from wherever it got to, while the slider moves on (no jump on a fade
	 * or beside the wider active slide). Videos do not zoom.
	 * ------------------------------------------------------------------ */

	function zoom( swiper, el, config ) {
		var settings = config.zoom;
		var shown = null;

		if ( ! Element.prototype.animate || ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) ) {
			return;
		}

		function images( slide ) {
			return slide ? [].slice.call( slide.querySelectorAll( 'img.wp-swiper__image' ) ) : [];
		}

		function scale( value ) {
			return 'scale(' + value + ')';
		}

		function release( slide ) {
			images( slide ).forEach( function ( img ) {
				// Where it got to, read before the zoom is stopped.
				var now = getComputedStyle( img ).transform;
				if ( img.wpSwiperZoom ) {
					img.wpSwiperZoom.cancel();
				}
				img.wpSwiperZoom = now && now !== 'none'
					? img.animate( [ { transform: now }, { transform: scale( settings.from ) } ], { duration: Math.max( 200, Number( config.speed ) || 600 ), easing: 'ease-out' } )
					: null;
			} );
		}

		function start() {
			var slide = shownSlide( swiper, el );
			if ( ! slide || slide === shown ) {
				return;
			}
			if ( shown ) {
				release( shown );
			}
			shown = slide;
			images( slide ).forEach( function ( img ) {
				if ( img.wpSwiperZoom ) {
					img.wpSwiperZoom.cancel();
				}
				// Kept at the end (fill) while its slide stays in view.
				img.wpSwiperZoom = img.animate( [ { transform: scale( settings.from ) }, { transform: scale( settings.to ) } ], { duration: Number( settings.duration ) || 6000, easing: 'linear', fill: 'forwards' } );
			} );
		}

		swiper.on( 'activeIndexChange transitionStart', start );
		// The wider active slide layout moves its current slide without Swiper.
		if ( config.expand ) {
			new MutationObserver( start ).observe( el.querySelector( '.swiper-wrapper' ), { subtree: true, attributes: true, attributeFilter: [ 'class' ] } );
		}
		start();
	}

	/* ------------------------------------------------------------------ *
	 * Image shadow, Slide: the slides out of view hidden
	 *
	 * The slider shows past its edges for the shadow (frontend.css:
	 * overflow-clip-margin, or in Safari its padding), so a slide a gap away
	 * from those in view would show there. A slide wholly
	 * outside the slides' area gets OUTSIDE_CLASS (frontend.css fades it
	 * out); checked frame by frame while anything in the slider moves (the
	 * strip, the wider active slide's widths, the marquee), so a slide
	 * coming in shows as it crosses the edge.
	 * ------------------------------------------------------------------ */

	var OUTSIDE_CLASS = 'wp-swiper-slide--outside';

	function hideOutside( swiper ) {
		var el = swiper.el;
		var until = 0;
		var ticking = false;

		function check() {
			var box = el.getBoundingClientRect();
			var style = getComputedStyle( el );
			var horizontal = swiper.isHorizontal();
			var start = horizontal ? box.left + parseFloat( style.paddingLeft ) : box.top + parseFloat( style.paddingTop );
			var end = horizontal ? box.right - parseFloat( style.paddingRight ) : box.bottom - parseFloat( style.paddingBottom );
			swiper.slides.forEach( function ( slide ) {
				var rect = slide.getBoundingClientRect();
				var from = horizontal ? rect.left : rect.top;
				var to = horizontal ? rect.right : rect.bottom;
				slide.classList.toggle( OUTSIDE_CLASS, to <= start + 0.5 || from >= end - 0.5 );
			} );
		}

		function tick() {
			check();
			if ( performance.now() < until ) {
				requestAnimationFrame( tick );
			} else {
				ticking = false;
			}
		}

		// Checked every frame for this long (ms), and once more after.
		function watch( ms ) {
			until = Math.max( until, performance.now() + ms );
			if ( ! ticking ) {
				ticking = true;
				requestAnimationFrame( tick );
			}
		}

		// A CSS transition in the slider (the strip's move, a slide's width):
		// as long as it may take. Not the fading itself.
		function moving( e ) {
			if ( 'opacity' !== e.propertyName ) {
				watch( ( Number( swiper.params.speed ) || 600 ) + 100 );
			}
		}
		el.addEventListener( 'transitionrun', moving );
		el.addEventListener( 'transitionend', function ( e ) {
			if ( 'opacity' !== e.propertyName ) {
				watch( 50 );
			}
		} );
		// Dragged (no transition), resized, rebuilt: where they are now.
		swiper.on( 'setTranslate resize update slidesUpdated', check );
		swiper.on( 'transitionStart', function () {
			watch( ( Number( swiper.params.speed ) || 600 ) + 100 );
		} );
		check();
	}

	/* ------------------------------------------------------------------ *
	 * One slider (frontend.js init(): not made a Swiper yet, its config read)
	 * ------------------------------------------------------------------ */

	function slider( el, config ) {
		// Sized before Swiper measures the slider (vertical sliders need it).
		// The wider active slide layout's slides differ in width, so they
		// cannot all take the image proportions either; "Width from image"
		// slides take their width from a set height.
		var wrapper = el.parentNode;
		background( wrapper );
		var needsFixed = config.direction === 'vertical' || !! config.expand || !! config.autoWidth || !! carouselOf( config );
		// Height "Auto" on the device in view (fitHeight()); a set Height
		// capped for "Contain" on the others (capHeight(): not where "Auto").
		if ( config.fill ) {
			fitHeight( wrapper, el, config, needsFixed );
		}
		var recap = capHeight( el, config );
		// Thumbnails beside the slider (on any device) take their share of its width first.
		var side = config.thumbs && config.thumbs.side;
		var refitThumbs = side && ( side.desktop || side.tablet || side.mobile ) ? thumbsBeside( wrapper, el, config ) : null;

		var options = buildOptions( el, config );
		options.on = {
			init: function ( swiper ) {
				setupControls( swiper, el, config );
				// Image shadow on a strip of slides: its room shows no neighbour.
				if ( 'slide' === config.effect && wrapper.classList.contains( 'wp-swiper--shadow' ) ) {
					hideOutside( swiper );
				}
				if ( config.zoom ) {
					zoom( swiper, el, config );
				}
				if ( config.fill ) {
					fillParent( wrapper, swiper, needsFixed, config );
				}
				// Image proportions: after the height is known (Height "Auto" above).
				if ( config.distribute ) {
					distribute( swiper, el, config );
				}
				// After the slider has its final size; again whenever it changes.
				imageSizes( swiper, config );

				// Size transitions (wider active slide) only from the next frame:
				// otherwise slides would visibly shrink from full width on load.
				requestAnimationFrame( function () {
					wrapper.classList.add( 'wp-swiper--ready' );
				} );

				// Cube/flip: the faces next to the active one are only needed while
				// turning; at rest CSS hides them (see .wp-swiper--turning).
				if ( 'cube' === config.effect || 'flip' === config.effect ) {
					swiper.on( 'sliderFirstMove transitionStart', function () {
						wrapper.classList.add( 'wp-swiper--turning' );
					} );
					swiper.on( 'transitionEnd', function () {
						wrapper.classList.remove( 'wp-swiper--turning' );
					} );
				}
				swiper.on( 'resize update', function () {
					imageSizes( swiper, config );
				} );
				// The cap again whenever Swiper resizes or changes device (a
				// new cap only resizes it once more, to the same result).
				if ( recap ) {
					recap();
					swiper.on( 'resize breakpoint', recap );
				}
				// The column again whenever the slider's height may have changed
				// (the same height gives the same width: it settles at once).
				if ( refitThumbs ) {
					refitThumbs();
					swiper.on( 'resize breakpoint update', refitThumbs );
				}
			}
		};

		new window.Swiper( el, options );
	}

	window.wpSwiperSlider = slider;
	// frontend.js already ran (this file came later, say added by a page
	// builder): the sliders now. Those made already are left as they are.
	if ( document.readyState !== 'loading' && window.wpSwiperInit ) {
		window.wpSwiperInit();
	}
}() );
