/**
 * WP Swiper front end: turns every [data-wp-swiper] element into a Swiper.
 */
( function () {
	'use strict';

	var BREAKPOINT_TABLET = 768;
	var BREAKPOINT_DESKTOP = 1025;

	/**
	 * Swiper options of the slider's effect. `config.shadows`: the depth shading
	 * Swiper draws on slides, off unless "Effect shadows"; the effect's own
	 * settings (Layout): `config.cube` { shadow, offset, scale, slideShadows },
	 * `config.coverflow` { rotate, depth, stretch, scale }, `config.cards`
	 * { offset, rotate, tilt }.
	 */
	function effectOptions( config ) {
		var shadows = !! config.shadows;
		var coverflow = config.coverflow;
		var cards = config.cards;
		var cube = config.cube || { shadow: false, offset: 20, scale: 0.94, slideShadows: false };

		switch ( config.effect ) {
			case 'fade':
				return { fadeEffect: { crossFade: true } };
			case 'cube':
				return { cubeEffect: { shadow: !! cube.shadow, slideShadows: !! cube.slideShadows, shadowOffset: cube.offset, shadowScale: cube.scale } };
			case 'coverflow':
				coverflow = coverflow || { rotate: 50, depth: 100, stretch: 0, scale: 1 };
				return { centeredSlides: true, coverflowEffect: { rotate: coverflow.rotate, stretch: coverflow.stretch, depth: coverflow.depth, scale: coverflow.scale, modifier: 1, slideShadows: shadows } };
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

	/* ------------------------------------------------------------------ *
	 * Lightbox: full-size images over the page, itself a Swiper (with zoom)
	 * ------------------------------------------------------------------ */

	var i18n = window.wpSwiperI18n || { dialog: 'Image viewer', close: 'Close', prev: 'Previous image', next: 'Next image' };

	function escapeHtml( text ) {
		var div = document.createElement( 'div' );
		div.textContent = text;
		return div.innerHTML;
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
	 * Collage effect
	 *
	 * Not a slider: the photos sit together in places of a template, of a
	 * grid, or arranged by their proportions (Justified rows, Masonry). The
	 * order is the one set for the device (dragged in the preview, or
	 * shuffled), else the saved shuffle (the same number, the same order),
	 * so the arrangement does not change from visit to visit. With more
	 * photos than places, every so often a place at random fades to a photo
	 * not shown yet; the places keep their size, the photo is cropped to fit.
	 * ------------------------------------------------------------------ */

	// The templates: their places as cuts, the same as a Random mosaic's
	// (parseMosaic()), the big one first: "V" side by side, "H" one over the
	// other, the share of the first part. Laid out as a mosaic is, so the
	// lines between the places resize them in the preview (collage_cuts).
	var COLLAGE_TEMPLATES = {
		// A big photo on the left (2/3), two on the right.
		'feature-left': 'V0.6667[0,H0.5[1,2]]',
		// Two on the left, a big one in the middle (half), two on the right.
		'feature-center': 'V0.25[H0.5[1,2],V0.6667[0,H0.5[3,4]]]',
		// A wide one (2/3) and one on top, three under them.
		mosaic: 'H0.5[V0.6667[0,1],V0.3333[2,V0.5[3,4]]]',
		// A wide one on top (3/5 of the height), three under it.
		banner: 'H0.6[0,V0.3333[1,V0.5[2,3]]]'
	};

	// A small seeded random generator (mulberry32): the same seed, the same numbers.
	function seededRandom( seed ) {
		var state = seed >>> 0;
		return function () {
			state = ( state + 0x6d2b79f5 ) >>> 0;
			var t = state;
			t = Math.imul( t ^ ( t >>> 15 ), t | 1 );
			t ^= t + Math.imul( t ^ ( t >>> 7 ), t | 61 );
			return ( ( t ^ ( t >>> 14 ) ) >>> 0 ) / 4294967296;
		};
	}

	// 0 .. count - 1 shuffled by the seed.
	function seededOrder( count, seed ) {
		var random = seededRandom( seed );
		var order = [];
		for ( var i = 0; i < count; i++ ) {
			order.push( i );
		}
		for ( var j = count - 1; j > 0; j-- ) {
			var k = Math.floor( random() * ( j + 1 ) );
			var swap = order[ j ];
			order[ j ] = order[ k ];
			order[ k ] = swap;
		}
		return order;
	}

	// Random mosaic: how many mosaics are tried for one shuffle (the best kept).
	var MOSAIC_TRIES = 60;

	/**
	 * Random mosaic: the block cut in two, each part again, until there is a
	 * place for every photo; flush on all four sides. A cut mostly runs
	 * across the part's longer side, at a random place among its photos and
	 * where they would fit side by side (or stacked) at their own
	 * proportions, give or take. Of MOSAIC_TRIES such mosaics (all from the
	 * shuffle, so the same each time) the one that crops its photos least
	 * is kept, with no sliver and no place far smaller than the others. The
	 * cuts are kept as shares, so a resize keeps the mosaic.
	 *
	 * @param {number[]} ratios Width / height of each place's photo, in order.
	 * @param {number}   aspect Width / height of the block.
	 * @param {Function} random Seeded random numbers.
	 * @return {Object} { leaf: index } or { vertical, share, a, b }.
	 */
	function mosaicTree( ratios, aspect, random ) {
		var count = ratios.length;

		function cut( list, shape ) {
			if ( list.length === 1 ) {
				return { leaf: list[ 0 ] };
			}
			var split = 1 + Math.floor( random() * ( list.length - 1 ) );
			var a = list.slice( 0, split );
			var b = list.slice( split );
			var vertical = shape >= 1 ? random() < 0.8 : random() < 0.2;
			// Side by side the photos share the width (their widths at one
			// height add up), stacked the height (their heights at one width).
			var weight = function ( group ) {
				return group.reduce( function ( sum, index ) {
					return sum + ( vertical ? ratios[ index ] : 1 / ratios[ index ] );
				}, 0 );
			};
			var share = weight( a ) / ( weight( a ) + weight( b ) ) * ( 0.85 + random() * 0.3 );
			share = Math.max( 0.2, Math.min( 0.8, share ) );
			return {
				vertical: vertical,
				share: share,
				a: cut( a, vertical ? shape * share : shape / share ),
				b: cut( b, vertical ? shape * ( 1 - share ) : shape / ( 1 - share ) )
			};
		}

		// What a mosaic costs: each photo's crop (log of how far its place's
		// shape is from its own), slivers (over 3:1) and tiny places dearer.
		function cost( node, shape, area ) {
			if ( undefined !== node.leaf ) {
				var crop = Math.abs( Math.log( shape / ratios[ node.leaf ] ) );
				var sliver = Math.max( 0, Math.abs( Math.log( shape ) ) - Math.log( 3 ) );
				var tiny = area < 0.35 / count ? 1 : 0;
				return crop + 3 * sliver + tiny;
			}
			var s = node.share;
			return node.vertical
				? cost( node.a, shape * s, area * s ) + cost( node.b, shape * ( 1 - s ), area * ( 1 - s ) )
				: cost( node.a, shape / s, area * s ) + cost( node.b, shape / ( 1 - s ), area * ( 1 - s ) );
		}

		var all = ratios.map( function ( r, index ) {
			return index;
		} );
		var best = null;
		var bestCost = Infinity;
		for ( var i = 0; i < MOSAIC_TRIES; i++ ) {
			var tree = cut( all, aspect );
			var price = cost( tree, aspect, 1 );
			if ( price < bestCost ) {
				best = tree;
				bestCost = price;
			}
		}
		return best;
	}

	/**
	 * Random mosaic cuts as stored (WP_Swiper_Settings::sanitize_mosaic()):
	 * "V0.6[0,H0.5[1,2]]" to a tree as mosaicTree() makes, and back.
	 *
	 * @param {string} text The cuts.
	 * @return {Object|null} The tree; null when it is not one.
	 */
	function parseMosaic( text ) {
		var at = 0;
		text = String( text || '' );
		function node() {
			var leaf = /^\d+/.exec( text.slice( at ) );
			if ( leaf ) {
				at += leaf[ 0 ].length;
				return { leaf: Number( leaf[ 0 ] ) };
			}
			var cut = /^([VH])(\d*\.?\d+)\[/.exec( text.slice( at ) );
			if ( ! cut ) {
				return null;
			}
			at += cut[ 0 ].length;
			var a = node();
			if ( ! a || text.charAt( at ) !== ',' ) {
				return null;
			}
			at++;
			var b = node();
			if ( ! b || text.charAt( at ) !== ']' ) {
				return null;
			}
			at++;
			return { vertical: cut[ 1 ] === 'V', share: Number( cut[ 2 ] ), a: a, b: b };
		}
		var tree = text ? node() : null;
		return tree && at === text.length ? tree : null;
	}

	function serializeMosaic( node ) {
		if ( undefined !== node.leaf ) {
			return String( node.leaf );
		}
		return ( node.vertical ? 'V' : 'H' ) + Math.round( node.share * 10000 ) / 10000 + '[' + serializeMosaic( node.a ) + ',' + serializeMosaic( node.b ) + ']';
	}

	// The places of a mosaic, each once: its leaves, sorted.
	function mosaicLeaves( node, list ) {
		list = list || [];
		if ( undefined !== node.leaf ) {
			list.push( node.leaf );
		} else {
			mosaicLeaves( node.a, list );
			mosaicLeaves( node.b, list );
		}
		return list;
	}

	/*
	 * Scattered: a photo in the middle and four arms of photos around it,
	 * turning like a pinwheel. Each arm keeps to its own quarter: above the
	 * middle photo from its left edge on (T), right of it from its top edge
	 * down (R), below it from its right edge back (B), left of it from its
	 * bottom edge up (L). An arm's first photo touches the middle one, a gap
	 * between them; each next one touches the one before, either along the
	 * arm ("a": right, down, left, up) or outwards ("o": up, right, down,
	 * left), lined up with it on the side towards the middle. So photos never
	 * overlap and neighbours are always a gap apart, whatever their sizes,
	 * while the outline stays uneven, its corners empty.
	 *
	 * The layout: { middle: { w, h }, arms: [ T, R, B, L ] }, each arm a list
	 * of { w, h, step } (step: "a" or "o"; none for the first), sizes in no
	 * unit: the whole is scaled to its block. The places in order: the
	 * middle one, then T's, R's, B's and L's.
	 */
	var SCATTER_ARMS = 'TRBL';
	// Scattered: how many layouts are tried for one shuffle (the one filling its block most kept).
	var SCATTER_TRIES = 80;

	// The layout's places in order (the objects themselves: resizing one resizes the layout).
	function scatterPlaces( layout ) {
		var list = [ layout.middle ];
		layout.arms.forEach( function ( arm ) {
			list = list.concat( arm );
		} );
		return list;
	}

	/**
	 * The places' rectangles at a scale (px per unit), a gap apart, the
	 * middle photo's top left corner at 0, 0. Each with its arm (-1: the
	 * middle) and the edges it is resized by: those not lined up with its
	 * neighbour (the others stay where they are as it changes size).
	 *
	 * @param {Object} layout See above.
	 * @param {number} scale  px per unit.
	 * @param {number} gap    px.
	 * @return {Object[]} { x, y, w, h, arm, edges: [ horizontal edge, vertical edge ] }
	 */
	function scatterRects( layout, scale, gap ) {
		// The edges each arm grows from (the middle: right and down, as it is placed from its top left).
		var EDGES = [ [ 'right', 'top' ], [ 'right', 'bottom' ], [ 'left', 'bottom' ], [ 'left', 'top' ] ];
		var mid = { x: 0, y: 0, w: layout.middle.w * scale, h: layout.middle.h * scale, arm: -1, edges: [ 'right', 'bottom' ] };
		var rects = [ mid ];
		layout.arms.forEach( function ( arm, a ) {
			var prev = null;
			arm.forEach( function ( place ) {
				var w = place.w * scale;
				var h = place.h * scale;
				var along = place.step !== 'o';
				var x;
				var y;
				if ( ! prev ) {
					// Next to the middle photo, lined up with its edge where the arm starts.
					x = [ mid.x, mid.x + mid.w + gap, mid.x + mid.w - w, mid.x - gap - w ][ a ];
					y = [ mid.y - gap - h, mid.y, mid.y + mid.h + gap, mid.y + mid.h - h ][ a ];
				} else if ( a === 0 ) {
					x = along ? prev.x + prev.w + gap : prev.x;
					y = along ? prev.y + prev.h - h : prev.y - gap - h;
				} else if ( a === 1 ) {
					x = along ? prev.x : prev.x + prev.w + gap;
					y = along ? prev.y + prev.h + gap : prev.y;
				} else if ( a === 2 ) {
					x = along ? prev.x - gap - w : prev.x + prev.w - w;
					y = along ? prev.y : prev.y + prev.h + gap;
				} else {
					x = along ? prev.x + prev.w - w : prev.x - gap - w;
					y = along ? prev.y - gap - h : prev.y + prev.h - h;
				}
				prev = { x: x, y: y, w: w, h: h, arm: a, edges: EDGES[ a ] };
				rects.push( prev );
			} );
		} );
		return rects;
	}

	// The rectangles' bounds: { left, top, width, height }.
	function scatterBox( rects ) {
		var left = Infinity;
		var top = Infinity;
		var right = -Infinity;
		var bottom = -Infinity;
		rects.forEach( function ( r ) {
			left = Math.min( left, r.x );
			top = Math.min( top, r.y );
			right = Math.max( right, r.x + r.w );
			bottom = Math.max( bottom, r.y + r.h );
		} );
		return { left: left, top: top, width: right - left, height: bottom - top };
	}

	/**
	 * The largest scale at which the layout fits the block, the gaps kept
	 * as they are (they do not scale).
	 *
	 * @param {Object} layout See above.
	 * @param {number} width  The block's, px.
	 * @param {number} height The block's, px.
	 * @param {number} gap    px.
	 * @return {number} px per unit.
	 */
	function scatterFit( layout, width, height, gap ) {
		var low = 0;
		var high = Math.max( width, height, 1 );
		for ( var i = 0; i < 32; i++ ) {
			var scale = ( low + high ) / 2;
			var box = scatterBox( scatterRects( layout, scale, gap ) );
			if ( box.width <= width && box.height <= height ) {
				low = scale;
			} else {
				high = scale;
			}
		}
		return Math.max( low, 0.0001 );
	}

	/**
	 * A layout for these photos: the first in the middle (larger), the others
	 * dealt out to the arms, each place shaped after its photo, give or take,
	 * and a little larger or smaller than the others. Of SCATTER_TRIES such
	 * layouts (all from the shuffle, so the same each time) the one whose
	 * photos cover most of the block is kept.
	 *
	 * @param {number[]} ratios Width / height of each place's photo, in order.
	 * @param {number}   width  The block's, px.
	 * @param {number}   height The block's, px.
	 * @param {number}   gap    px.
	 * @param {Function} random Seeded random numbers.
	 * @return {Object} The layout.
	 */
	function scatterLayout( ratios, width, height, gap, random ) {
		function shape( ratio, area ) {
			var r = Math.max( 0.4, Math.min( 2.5, ( ratio || 1 ) * ( 0.9 + random() * 0.2 ) ) );
			return { w: Math.sqrt( area * r ), h: Math.sqrt( area / r ) };
		}

		var others = ratios.length - 1;
		var best = null;
		var bestScore = -Infinity;
		for ( var t = 0; t < SCATTER_TRIES; t++ ) {
			// How many photos each arm gets: as many each (fewer than four: one
			// each to some), the rest to arms at random; now and then one moved
			// to another arm, so the arms are a little uneven.
			var counts = [ 0, 0, 0, 0 ];
			var arms = seededOrder( 4, Math.floor( random() * 4294967296 ) );
			for ( var i = 0; i < others; i++ ) {
				counts[ arms[ i % 4 ] ]++;
			}
			if ( others > 4 && random() < 0.4 ) {
				var from = arms[ Math.floor( random() * 4 ) ];
				if ( counts[ from ] > 1 ) {
					counts[ from ]--;
					counts[ arms[ Math.floor( random() * 4 ) ] ]++;
				}
			}
			var layout = { middle: shape( ratios[ 0 ], 1.5 + random() * 0.6 ), arms: [ [], [], [], [] ] };
			var index = 1;
			counts.forEach( function ( count, a ) {
				for ( var k = 0; k < count; k++ ) {
					var place = shape( ratios[ index++ ], 0.6 + random() * 0.65 );
					if ( k ) {
						place.step = random() < 0.55 ? 'a' : 'o';
					}
					layout.arms[ a ].push( place );
				}
			} );

			// How good it is: the share of the block its photos cover, less how
			// far the middle photo is from the block's middle.
			var scale = scatterFit( layout, width, height, gap );
			var rects = scatterRects( layout, scale, gap );
			var box = scatterBox( rects );
			var cover = rects.reduce( function ( sum, r ) {
				return sum + r.w * r.h;
			}, 0 ) / ( width * height );
			var offX = Math.abs( rects[ 0 ].x + rects[ 0 ].w / 2 - box.left - box.width / 2 ) / width;
			var offY = Math.abs( rects[ 0 ].y + rects[ 0 ].h / 2 - box.top - box.height / 2 ) / height;
			var score = cover - 0.8 * ( offX + offY );
			if ( score > bestScore ) {
				best = layout;
				bestScore = score;
			}
		}
		return best;
	}

	/**
	 * A Scattered layout as stored (WP_Swiper_Settings::sanitize_scatter()):
	 * "1.2x1.6;T0.9x0.6;R1x0.7,a0.8x0.8;L0.85x0.5", and back.
	 *
	 * @param {string} text The layout.
	 * @return {Object|null} The layout; null when it is not one.
	 */
	function parseScatter( text ) {
		var size = function ( part ) {
			var match = /^(\d*\.?\d+)x(\d*\.?\d+)$/.exec( part );
			return match && Number( match[ 1 ] ) > 0 && Number( match[ 2 ] ) > 0 ? { w: Number( match[ 1 ] ), h: Number( match[ 2 ] ) } : null;
		};
		var groups = String( text || '' ).split( ';' );
		var middle = size( groups.shift() );
		if ( ! middle ) {
			return null;
		}
		var layout = { middle: middle, arms: [ [], [], [], [] ] };
		var last = -1;
		for ( var g = 0; g < groups.length; g++ ) {
			var a = SCATTER_ARMS.indexOf( groups[ g ].charAt( 0 ) );
			if ( a <= last ) {
				return null;
			}
			last = a;
			var steps = groups[ g ].slice( 1 ).split( ',' );
			for ( var k = 0; k < steps.length; k++ ) {
				var step = k ? steps[ k ].charAt( 0 ) : '';
				var place = size( k ? steps[ k ].slice( 1 ) : steps[ k ] );
				if ( ! place || ( k && step !== 'a' && step !== 'o' ) ) {
					return null;
				}
				if ( k ) {
					place.step = step;
				}
				layout.arms[ a ].push( place );
			}
		}
		return layout;
	}

	function serializeScatter( layout ) {
		var number = function ( value ) {
			return Math.round( Math.max( 0.05, Math.min( 20, value ) ) * 10000 ) / 10000;
		};
		var size = function ( place ) {
			return number( place.w ) + 'x' + number( place.h );
		};
		var parts = [ size( layout.middle ) ];
		layout.arms.forEach( function ( arm, a ) {
			if ( arm.length ) {
				parts.push( SCATTER_ARMS.charAt( a ) + arm.map( function ( place, k ) {
					return ( k ? place.step : '' ) + size( place );
				} ).join( ',' ) );
			}
		} );
		return parts.join( ';' );
	}

	/**
	 * @param {Element} el     The .wp-swiper-collage block.
	 * @param {Object}  config { layout, count, columns, rowHeight, gap: per
	 *                         device; seed; swap: false or { delay, pause };
	 *                         fill; lightbox: false or { radius } }.
	 */
	function collage( el, config ) {
		var grid = el.querySelector( '.wp-swiper-collage__grid' );
		var pool = el.querySelector( 'template.wp-swiper-collage__pool' );
		var slides = pool ? [].slice.call( pool.content.children ) : [];
		var order = [];
		var cells = [];
		var queue = [];
		var tree = null;
		var current = null;
		var lastSwapped = -1;
		var timer = null;
		var paused = false;
		var inView = true;
		var calm = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		var root = el.parentNode;
		// Height "Auto" in a column (on the devices that have it, fills()): the
		// collage fills the column's height, like a slider (fillHeight()); not a
		// background (it fills its block anyway).
		var stretchy = !! config.fill && ! root.classList.contains( 'wp-swiper--background' );
		var stretched = false;
		// Random mosaic and the templates: their cuts as last laid out (place()),
		// for the preview.
		var mosaicLines = [];
		// Scattered: its layout (scatterLayout()), whether it is the one set in
		// the preview, the block's proportions it was worked out for, and its
		// places as last laid out (place(): the scale, where the middle photo's
		// corner is, the rectangles in grid px). Held at a scale and spot while
		// a place is resized in the preview (frozen).
		var scatter = null;
		var scatterSet = false;
		var scatterAspect = 0;
		var scatterView = null;
		var frozen = null;
		// Called once the places are laid out (the preview draws the lines then).
		var placed = [];

		if ( ! slides.length ) {
			return;
		}

		/**
		 * Height "Auto": as tall as the free height of the column (the templates,
		 * Grid and Random mosaic). Justified rows and Masonry stay as tall as
		 * their photos make them. Too little free height (stacked columns on
		 * phones): the fixed proportions (.wp-swiper-collage__grid--ratio).
		 *
		 * @return {boolean} The height changed.
		 */
		function stretch() {
			var before = root.offsetHeight;
			var layout = config.layout[ device() ] || 'grid';
			if ( stretchy && fills( config ) && layout !== 'justified' && layout !== 'masonry' ) {
				fillHeight( root, false, 0 );
				stretched = !! root.style.height;
			} else {
				root.style.height = '';
				stretched = false;
			}
			el.classList.toggle( 'wp-swiper-collage--stretch', stretched );
			grid.classList.toggle( 'wp-swiper-collage__grid--stretch', stretched );
			return Math.abs( root.offsetHeight - before ) > 1;
		}

		// Random mosaic: its cuts for the block's proportions, from the shuffle
		// too (and the number of places).
		function cut() {
			// The cuts set in the preview for this device, while they have a
			// place for every place shown (each once); else worked out.
			var stored = config.mosaic && config.mosaic[ current ] ? parseMosaic( config.mosaic[ current ] ) : null;
			if ( stored ) {
				var leaves = mosaicLeaves( stored ).sort( function ( x, y ) {
					return x - y;
				} );
				if ( leaves.length === cells.length && leaves.every( function ( leaf, i ) {
					return leaf === i;
				} ) ) {
					tree = stored;
					return;
				}
			}
			var aspect = grid.clientWidth / ( grid.clientHeight || 1 ) || 1.5;
			tree = mosaicTree( cells.map( function ( cell ) {
				return cell.wpRatio;
			} ), aspect, seededRandom( ( config.seed || 1 ) * 7919 + cells.length ) );
		}

		// A template's cuts: the ones set in the preview for this device, while
		// they are this template's and have its places; else its own.
		function templateTree( layout ) {
			var own = parseMosaic( COLLAGE_TEMPLATES[ layout ] );
			var stored = config.cuts && config.cuts[ current ];
			var prefix = layout + ':';
			var set = stored && stored.indexOf( prefix ) === 0 ? parseMosaic( stored.slice( prefix.length ) ) : null;
			if ( set && mosaicLeaves( set ).sort().join( ',' ) === mosaicLeaves( own ).sort().join( ',' ) ) {
				return set;
			}
			return own;
		}

		// Scattered: the layout set in the preview for this device, while it
		// has a place for every place shown; else worked out for the block's
		// proportions (from the shuffle too, and the number of places).
		function scatterCut() {
			var stored = config.cuts && config.cuts[ current ];
			var set = stored && stored.indexOf( 'scatter:' ) === 0 ? parseScatter( stored.slice( 8 ) ) : null;
			var width = grid.clientWidth || 1;
			var height = grid.clientHeight || 1;
			scatterAspect = width / height;
			scatterSet = !! set && scatterPlaces( set ).length === cells.length;
			if ( scatterSet ) {
				scatter = set;
				return;
			}
			scatter = scatterLayout( cells.map( function ( cell ) {
				return cell.wpRatio;
			} ), width, height, Number( config.gap[ current ] ) || 0, seededRandom( ( config.seed || 1 ) * 7919 + cells.length * 31 + 7 ) );
		}

		function ratioOf( slide ) {
			return Number( slide.style.getPropertyValue( '--wp-swiper-img-ratio' ) ) || 1;
		}

		/**
		 * The photos in their order on a device: the order set there (slide
		 * indexes, the first ones in the first places), then the photos not in
		 * it in shuffle order; without one, the shuffle.
		 *
		 * @param {string} name Device.
		 * @return {number[]}
		 */
		function orderFor( name ) {
			var shuffled = seededOrder( slides.length, config.seed || 1 );
			var set = config.order && config.order[ name ];
			if ( ! set || ! set.length ) {
				return shuffled;
			}
			var seen = {};
			var out = [];
			set.concat( shuffled ).forEach( function ( real ) {
				real = Number( real );
				if ( real >= 0 && real < slides.length && ! seen[ real ] ) {
					seen[ real ] = true;
					out.push( real );
				}
			} );
			return out;
		}

		// A copy of slide `real` to put in a place.
		function copy( real ) {
			var node = slides[ real ].cloneNode( true );
			node.classList.add( 'wp-swiper-collage__item' );
			return node;
		}

		// The image file the browser picks: the width it is drawn at (cropped to the place).
		function sizeImages( cell ) {
			var w = cell.clientWidth;
			var h = cell.clientHeight;
			[].forEach.call( cell.querySelectorAll( 'img.wp-swiper__image' ), function ( img ) {
				var ratio = img.getAttribute( 'width' ) / img.getAttribute( 'height' ) || 1;
				var drawn = Math.ceil( Math.max( w, h * ratio ) );
				if ( drawn > 0 ) {
					img.setAttribute( 'sizes', drawn + 'px' );
				}
			} );
		}

		/**
		 * A background (frontend.js background()) fills its block: Justified
		 * rows and Masonry, as tall as their photos make them, take its height
		 * instead. Each line of places (a row; a column of Masonry) keeps its
		 * widths, and its heights are scaled to fill the block, the gaps kept:
		 * the places keep their order and look, their photos are cropped to
		 * them as always.
		 *
		 * @param {Element[][]} lines  The places of each line, from the top or left.
		 * @param {number}      height The block's height.
		 * @param {number}      gap    The gap.
		 * @param {boolean}     across The lines run across (rows): scaled together.
		 */
		function fillLines( lines, height, gap, across ) {
			var size = function ( cell ) {
				return parseFloat( cell.style.height ) || 0;
			};
			// Rows share one scale (a row is as tall as its places); a column has its own.
			var groups = across ? [ lines ] : lines.map( function ( line ) {
				return [ line ];
			} );
			groups.forEach( function ( group ) {
				var spans = group.map( function ( line ) {
					return across ? Math.max.apply( Math, line.map( size ) ) : line.reduce( function ( sum, cell ) {
						return sum + size( cell );
					}, 0 );
				} );
				var steps = across ? group.length - 1 : group[ 0 ].length - 1;
				var total = spans.reduce( function ( sum, span ) {
					return sum + span;
				}, 0 );
				var scale = total > 0 ? Math.max( 0, height - gap * steps ) / total : 1;
				var top = 0;
				group.forEach( function ( line, i ) {
					line.forEach( function ( cell ) {
						var h = size( cell ) * scale;
						cell.style.top = top + 'px';
						cell.style.height = h + 'px';
						if ( ! across ) {
							top += h + gap;
						}
					} );
					if ( across ) {
						top += spans[ i ] * scale + gap;
					}
				} );
			} );
		}

		// Justified rows and Masonry: each place absolutely positioned, the
		// block as tall as they make it (a background: as its block, see
		// fillLines()); again on every resize.
		function place() {
			var layout = config.layout[ current ];
			var gap = Number( config.gap[ current ] ) || 0;
			var width = grid.clientWidth;
			if ( ! width ) {
				return;
			}
			var fill = el.parentNode.classList.contains( 'wp-swiper--background' );
			// The places of each row (Justified) or column (Masonry), for fillLines().
			var lines = [];
			var set = function ( cell, left, top, w, h ) {
				cell.style.left = left + 'px';
				cell.style.top = top + 'px';
				cell.style.width = w + 'px';
				cell.style.height = h + 'px';
			};
			var setHeight = function ( height ) {
				grid.style.height = fill ? '' : Math.max( 0, height ) + 'px';
			};

			if ( layout === 'scatter' ) {
				// The block keeps its Height; the layout as large as fits it, in its
				// middle (held where it is while a place is resized in the preview).
				mosaicLines = [];
				if ( scatter ) {
					var gridHeight = grid.clientHeight;
					var scale = frozen ? frozen.scale : scatterFit( scatter, width, gridHeight, gap );
					var rects = scatterRects( scatter, scale, gap );
					var box = scatterBox( rects );
					var originX = frozen ? frozen.x : ( width - box.width ) / 2 - box.left;
					var originY = frozen ? frozen.y : ( gridHeight - box.height ) / 2 - box.top;
					rects.forEach( function ( r, i ) {
						if ( cells[ i ] ) {
							set( cells[ i ], originX + r.x, originY + r.y, r.w, r.h );
						}
					} );
					scatterView = { scale: scale, x: originX, y: originY, rects: rects };
				}
			} else if ( layout === 'random' || COLLAGE_TEMPLATES[ layout ] ) {
				// The block keeps its Height; the cuts are laid over it, gaps between.
				// Each cut kept with its part and the line between its two (mosaicLines).
				mosaicLines = [];
				var apply = function ( node, left, top, w, h ) {
					if ( undefined !== node.leaf ) {
						set( cells[ node.leaf ], left, top, w, h );
						return;
					}
					if ( node.vertical ) {
						var first = Math.max( 0, ( w - gap ) * node.share );
						mosaicLines.push( { node: node, vertical: true, left: left, top: top, width: w, height: h, at: left + first, gap: gap } );
						apply( node.a, left, top, first, h );
						apply( node.b, left + first + gap, top, Math.max( 0, w - first - gap ), h );
					} else {
						var upper = Math.max( 0, ( h - gap ) * node.share );
						mosaicLines.push( { node: node, vertical: false, left: left, top: top, width: w, height: h, at: top + upper, gap: gap } );
						apply( node.a, left, top, w, upper );
						apply( node.b, left, top + upper + gap, w, Math.max( 0, h - upper - gap ) );
					}
				};
				if ( tree ) {
					apply( tree, 0, 0, width, grid.clientHeight );
				}
			} else if ( layout === 'masonry' ) {
				var columns = Math.max( 1, Number( config.columns[ current ] ) || 1 );
				var columnWidth = ( width - gap * ( columns - 1 ) ) / columns;
				var heights = [];
				for ( var c = 0; c < columns; c++ ) {
					heights.push( 0 );
					lines.push( [] );
				}
				cells.forEach( function ( cell ) {
					var shortest = heights.indexOf( Math.min.apply( Math, heights ) );
					var h = columnWidth / cell.wpRatio;
					set( cell, shortest * ( columnWidth + gap ), heights[ shortest ], columnWidth, h );
					heights[ shortest ] += h + gap;
					lines[ shortest ].push( cell );
				} );
				setHeight( Math.max.apply( Math, heights ) - gap );
			} else if ( config.rowExact ) {
				// Justified, "Exact row height": every row the row height, each photo
				// at its own proportions; a row ends where the next photo would not
				// fit (one wider than the block on its own is fitted to the width).
				var rowHeight = Math.max( 20, Number( config.rowHeight[ current ] ) || 200 );
				var x = 0;
				var y = 0;
				var line = [];
				cells.forEach( function ( cell ) {
					var w = cell.wpRatio * rowHeight;
					var h = rowHeight;
					if ( w > width ) {
						h = width / cell.wpRatio;
						w = width;
					}
					if ( x > 0 && x + w > width + 0.5 ) {
						x = 0;
						y += rowHeight + gap;
						lines.push( line );
						line = [];
					}
					set( cell, x, y, w, h );
					line.push( cell );
					x += w + gap;
				} );
				lines.push( line );
				setHeight( y + rowHeight );
			} else {
				// Justified: fill a row up to about the row height, then fit it to the width exactly.
				var target = Math.max( 20, Number( config.rowHeight[ current ] ) || 200 );
				var top = 0;
				var row = [];
				var sum = 0;
				var flush = function ( h ) {
					var left = 0;
					row.forEach( function ( cell ) {
						var w = cell.wpRatio * h;
						set( cell, left, top, w, h );
						left += w + gap;
					} );
					top += h + gap;
					lines.push( row );
					row = [];
					sum = 0;
				};
				cells.forEach( function ( cell ) {
					row.push( cell );
					sum += cell.wpRatio;
					if ( sum * target + gap * ( row.length - 1 ) >= width ) {
						flush( ( width - gap * ( row.length - 1 ) ) / sum );
					}
				} );
				// The last row is not stretched: at most the row height.
				if ( row.length ) {
					flush( Math.min( target, ( width - gap * ( row.length - 1 ) ) / sum ) );
				}
				setHeight( top - gap );
			}
			// Random mosaic is cut to the block's height already.
			if ( fill && lines.length ) {
				fillLines( lines, grid.clientHeight, gap, layout !== 'masonry' );
			}
			cells.forEach( sizeImages );
			placed.forEach( function ( callback ) {
				callback();
			} );
		}

		// The places for this device's layout, the photos in shuffle order.
		function build() {
			current = device();
			order = orderFor( current );
			var layout = config.layout[ current ] || 'grid';
			var template = COLLAGE_TEMPLATES[ layout ] ? templateTree( layout ) : null;
			// Laid out by cuts (Random mosaic, the templates), or Scattered: in the block's Height.
			var cuts = layout === 'random' || layout === 'scatter' || !! template;
			// Places positioned by the script.
			var free = cuts || layout === 'justified' || layout === 'masonry';
			var gap = Number( config.gap[ current ] ) || 0;
			// A template has its places (photos repeat when there are fewer); the
			// others show as many as set, at most the photos there are.
			var count = template ? mosaicLeaves( template ).length : Math.min( slides.length, Math.max( 1, Number( config.count[ current ] ) || 1 ) );

			grid.innerHTML = '';
			grid.className = 'wp-swiper-collage__grid wp-swiper-collage__grid--' + layout + ( free ? ' wp-swiper-collage__grid--free' : '' ) + ( cuts ? ' wp-swiper-collage__grid--cuts' : '' ) + ( fills( config ) ? ' wp-swiper-collage__grid--ratio' : '' ) + ( stretched ? ' wp-swiper-collage__grid--stretch' : '' );
			grid.style.cssText = '';
			cells = [];
			for ( var i = 0; i < count; i++ ) {
				var real = order[ i % slides.length ];
				var cell = document.createElement( 'div' );
				cell.className = 'wp-swiper-collage__cell';
				// A place's proportions stay those of the photo it got first.
				cell.wpRatio = ratioOf( slides[ real ] );
				cell.wpReal = real;
				cell.appendChild( copy( real ) );
				grid.appendChild( cell );
				cells.push( cell );
			}
			// The photos not in a place wait their turn, in shuffle order.
			queue = order.slice( count );

			tree = template;
			scatter = null;
			scatterView = null;
			frozen = null;
			if ( layout === 'random' ) {
				cut();
			} else if ( layout === 'scatter' ) {
				scatterCut();
			}

			if ( free ) {
				place();
			} else {
				grid.style.gap = gap + 'px';
				var columns = Math.max( 1, Math.min( count, Number( config.columns[ current ] ) || 1 ) );
				grid.style.gridTemplateColumns = 'repeat(' + columns + ', 1fr)';
				grid.style.gridTemplateRows = 'repeat(' + Math.ceil( count / columns ) + ', 1fr)';
				cells.forEach( sizeImages );
			}
			posterFallbacks( grid );
		}

		// One place, at random (not the one before), fades to the next photo waiting.
		function swap() {
			if ( ! queue.length || ! cells.length ) {
				return;
			}
			var index;
			do {
				index = Math.floor( Math.random() * cells.length );
			} while ( cells.length > 1 && index === lastSwapped );
			lastSwapped = index;

			var cell = cells[ index ];
			var real = queue.shift();
			queue.push( cell.wpReal );
			cell.wpReal = real;

			var fresh = copy( real );
			fresh.classList.add( 'is-entering' );
			cell.appendChild( fresh );
			sizeImages( cell );
			posterFallbacks( cell );

			// Faded in once its image is there (decoded), so it does not pop in
			// half-drawn; at the latest after the delay between swaps (a slow file).
			var img = fresh.querySelector( 'img' );
			var decoded = img && img.decode ? img.decode().catch( function () {} ) : Promise.resolve();
			var late = new Promise( function ( resolve ) {
				setTimeout( resolve, Math.max( 500, Number( config.swap.delay ) || 3000 ) );
			} );
			Promise.race( [ decoded, late ] ).then( function () {
				requestAnimationFrame( function () {
					fresh.classList.remove( 'is-entering' );
				} );
				// Then the photos under it go: all of them, should swaps have piled up.
				var speed = parseFloat( getComputedStyle( fresh ).transitionDuration ) * 1000 || 0;
				setTimeout( function () {
					while ( fresh.parentNode === cell && cell.firstElementChild !== fresh ) {
						cell.removeChild( cell.firstElementChild );
					}
				}, speed + 100 );
			} );
		}

		function schedule() {
			clearTimeout( timer );
			if ( ! config.swap || calm || ! queue.length ) {
				return;
			}
			timer = setTimeout( function () {
				// Not while paused, off screen or in a hidden tab: try again later.
				if ( ! paused && inView && ! document.hidden ) {
					swap();
				}
				schedule();
			}, Math.max( 500, Number( config.swap.delay ) || 3000 ) );
		}

		if ( config.swap && config.swap.pause ) {
			el.addEventListener( 'mouseenter', function () {
				paused = true;
			} );
			el.addEventListener( 'mouseleave', function () {
				paused = false;
			} );
		}
		if ( 'IntersectionObserver' in window ) {
			new IntersectionObserver( function ( entries ) {
				inView = entries[ 0 ].isIntersecting;
			} ).observe( el );
		}

		// Lightbox: a click on a place opens its photo, the others in slide order beside it.
		if ( config.lightbox ) {
			el.addEventListener( 'click', function ( e ) {
				var cell = e.target.closest( '.wp-swiper-collage__cell' );
				if ( ! cell || e.defaultPrevented || e.target.closest( '.wp-swiper__link' ) ) {
					return;
				}
				var item = cell.lastElementChild;
				if ( ! item || ! item.matches( LIGHTBOX_SHOWN ) ) {
					return;
				}
				e.preventDefault();
				var items = [];
				slides.forEach( function ( slide, real ) {
					if ( slide.matches( LIGHTBOX_SHOWN ) ) {
						items.push( lightboxItem( slide, real, '' ) );
					}
				} );
				var start = 0;
				items.forEach( function ( entry, i ) {
					if ( entry.real === cell.wpReal ) {
						start = i;
					}
				} );
				paused = true;
				openLightbox( items, start, function () {
					paused = false;
				}, { radius: config.lightbox.radius, thumbRadius: false, thumbs: false } );
			} );
		}

		// The height first: Random mosaic cuts for the block's proportions.
		stretch();
		build();
		schedule();
		requestAnimationFrame( function () {
			el.parentNode.classList.add( 'wp-swiper--ready' );
		} );

		// A background's block may change height with no resize of the window
		// (its content's fonts and images load): the places positioned by the
		// script follow it. Placing them never changes it (fillLines()).
		if ( 'ResizeObserver' in window && el.parentNode.classList.contains( 'wp-swiper--background' ) ) {
			var blockHeight = el.clientHeight;
			new ResizeObserver( function () {
				if ( Math.abs( el.clientHeight - blockHeight ) > 1 ) {
					blockHeight = el.clientHeight;
					if ( grid.classList.contains( 'wp-swiper-collage__grid--free' ) ) {
						place();
					}
				}
			} ).observe( el );
		}

		// Another device: its own layout, built again (the same shuffle); the
		// same device: the arranged layouts re-measured (Random mosaic cut
		// again when the column's height changed).
		var queued = false;
		function refit() {
			if ( queued ) {
				return;
			}
			queued = true;
			requestAnimationFrame( function () {
				queued = false;
				var resized = stretch();
				if ( device() !== current ) {
					build();
					schedule();
				} else if ( grid.classList.contains( 'wp-swiper-collage__grid--free' ) ) {
					// A template keeps its shares whatever the block's proportions.
					if ( resized && tree && config.layout[ current ] === 'random' ) {
						cut();
					}
					// Scattered, unless set in the preview: worked out again once the
					// block's proportions are a tenth off those it was made for.
					if ( scatter && ! scatterSet && ! frozen && grid.clientHeight && Math.abs( grid.clientWidth / grid.clientHeight / scatterAspect - 1 ) > 0.1 ) {
						scatterCut();
					}
					place();
				} else {
					cells.forEach( sizeImages );
				}
			} );
		}
		window.addEventListener( 'resize', refit );
		if ( stretchy ) {
			window.addEventListener( 'load', refit );
			watchNeighbours( root, refit );
		}

		return {
			rebuild: build,
			// For the preview (preview-collage.js): the photos as placed now on this
			// device (the places in turn, then those waiting), a new order for it,
			// each slide's photo ID, and the photo swapping held while dragging.
			places: function () {
				return { device: current, count: cells.length, order: cells.map( function ( cell ) {
					return cell.wpReal;
				} ).concat( queue ) };
			},
			setOrder: function ( list ) {
				config.order = config.order || {};
				config.order[ current ] = list;
				build();
				schedule();
			},
			ids: config.ids || [],
			hold: function ( on ) {
				paused = !! on;
			},
			// Random mosaic and the templates: their cuts' lines as laid out now
			// (grid px), a cut's new share laid out at once, the cuts as stored,
			// and new ones (or none: worked out again, the template's own) for
			// this device; which template is shown ('' : none, Random mosaic).
			mosaic: {
				lines: function () {
					return tree ? mosaicLines.slice() : [];
				},
				template: function () {
					return COLLAGE_TEMPLATES[ config.layout[ current ] ] ? config.layout[ current ] : '';
				},
				setShare: function ( node, share ) {
					node.share = share;
					place();
				},
				serialize: function () {
					return tree ? serializeMosaic( tree ) : '';
				},
				set: function ( text ) {
					var layout = config.layout[ current ];
					if ( COLLAGE_TEMPLATES[ layout ] ) {
						config.cuts = config.cuts || {};
						config.cuts[ current ] = text ? layout + ':' + text : '';
						tree = templateTree( layout );
					} else {
						config.mosaic = config.mosaic || {};
						config.mosaic[ current ] = text || '';
						cut();
					}
					place();
				}
			},
			// Scattered: whether it is shown; its places as laid out now (grid px,
			// each with the edges it is resized by); held at their scale and spot
			// (on) or fitted to the block again (off); a place's new size in grid
			// px, laid out at once; two places' sizes swapped (their photos are
			// swapped: each place keeps the shape of its photo); the layout as
			// stored, and a new one (or none: worked out again) for this device.
			scatter: {
				active: function () {
					return !! scatter && config.layout[ current ] === 'scatter';
				},
				places: function () {
					if ( ! scatterView ) {
						return [];
					}
					return scatterView.rects.map( function ( r, index ) {
						return { index: index, left: scatterView.x + r.x, top: scatterView.y + r.y, width: r.w, height: r.h, edges: r.edges.slice() };
					} );
				},
				freeze: function ( on ) {
					frozen = on && scatterView ? { scale: scatterView.scale, x: scatterView.x, y: scatterView.y } : null;
					if ( ! on ) {
						place();
					}
				},
				resize: function ( index, width, height ) {
					var spot = scatter && scatterPlaces( scatter )[ index ];
					var scale = scatterView ? scatterView.scale : 0;
					if ( ! spot || ! scale ) {
						return;
					}
					spot.w = Math.max( 0.05, Math.min( 20, width / scale ) );
					spot.h = Math.max( 0.05, Math.min( 20, height / scale ) );
					place();
				},
				swap: function ( from, to ) {
					var list = scatter ? scatterPlaces( scatter ) : [];
					if ( list[ from ] && list[ to ] ) {
						var w = list[ from ].w;
						var h = list[ from ].h;
						list[ from ].w = list[ to ].w;
						list[ from ].h = list[ to ].h;
						list[ to ].w = w;
						list[ to ].h = h;
					}
				},
				serialize: function () {
					return scatter ? serializeScatter( scatter ) : '';
				},
				set: function ( text ) {
					config.cuts = config.cuts || {};
					config.cuts[ current ] = text ? 'scatter:' + text : '';
					frozen = null;
					scatterCut();
					place();
				}
			},
			onPlace: function ( callback ) {
				placed.push( callback );
			}
		};
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
		// Collages first: they do not need Swiper.
		( root || document ).querySelectorAll( '[data-wp-swiper-collage]' ).forEach( function ( el ) {
			if ( el.wpSwiperCollage ) {
				return;
			}
			background( el.parentNode );
			try {
				el.wpSwiperCollage = collage( el, JSON.parse( el.getAttribute( 'data-wp-swiper-collage' ) ) ) || true;
			} catch ( e ) {}
		} );

		if ( typeof window.Swiper === 'undefined' ) {
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
		} );
	}

	window.wpSwiperInit = init;
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
