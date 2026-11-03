/**
 * WP Swiper, a font name's field (WP_Swiper_Admin, type "font": Text mask's
 * Font name): the name typed, or picked from a list under the field of the
 * fonts the site names (WP_Swiper_Fonts::suggestions(), data-fonts), in
 * their groups, each name in its own font (where this page has it). Typing
 * keeps those whose name has what is typed; the arrows move through them,
 * Enter picks one, Escape closes. A pick is a change as any (input and
 * change events: the preview's Update, Undo, the "unsaved" warning).
 */
( function () {
	'use strict';

	function setup( box ) {
		var input = box.querySelector( '.wp-swiper-font__input' );
		var list = box.querySelector( '.wp-swiper-font__list' );
		var toggle = box.querySelector( '.wp-swiper-font__toggle' );
		var fonts = [];
		try {
			fonts = JSON.parse( box.getAttribute( 'data-fonts' ) || '[]' );
		} catch ( e ) {}
		if ( ! input || ! list || ! Array.isArray( fonts ) || ! fonts.length ) {
			if ( toggle ) {
				toggle.hidden = true;
			}
			return;
		}
		var options = [];
		var active = -1;

		// A name as CSS: quoted, the page's plain font after it.
		function family( name ) {
			return /[,"']/.test( name ) ? name : '"' + name + '", sans-serif';
		}

		// The list for what is typed (`all`: every font, as on opening).
		function draw( all ) {
			var typed = all ? '' : input.value.trim().toLowerCase();
			list.textContent = '';
			options = [];
			active = -1;
			var group = null;
			fonts.forEach( function ( font ) {
				if ( typed && font.value.toLowerCase().indexOf( typed ) === -1 ) {
					return;
				}
				if ( font.group !== group ) {
					group = font.group;
					var head = document.createElement( 'li' );
					head.className = 'wp-swiper-font__group';
					head.setAttribute( 'role', 'presentation' );
					head.textContent = group;
					list.appendChild( head );
				}
				var item = document.createElement( 'li' );
				item.className = 'wp-swiper-font__option';
				item.id = list.id + '-' + options.length;
				item.setAttribute( 'role', 'option' );
				item.setAttribute( 'aria-selected', font.value === input.value ? 'true' : 'false' );
				item.wpFont = font.value;
				var name = document.createElement( 'span' );
				name.className = 'wp-swiper-font__name';
				name.style.fontFamily = family( font.value );
				name.textContent = font.value;
				item.appendChild( name );
				if ( font.label ) {
					var where = document.createElement( 'span' );
					where.className = 'wp-swiper-font__where';
					where.textContent = font.label;
					item.appendChild( where );
				}
				list.appendChild( item );
				options.push( item );
			} );
			return options.length;
		}

		function open( all ) {
			if ( ! draw( all ) ) {
				close();
				return;
			}
			list.hidden = false;
			input.setAttribute( 'aria-expanded', 'true' );
			box.classList.add( 'is-open' );
			// The chosen one in view.
			options.forEach( function ( item, i ) {
				if ( item.getAttribute( 'aria-selected' ) === 'true' ) {
					mark( i );
				}
			} );
		}

		function close() {
			list.hidden = true;
			input.setAttribute( 'aria-expanded', 'false' );
			input.removeAttribute( 'aria-activedescendant' );
			box.classList.remove( 'is-open' );
			active = -1;
		}

		function mark( i ) {
			options.forEach( function ( item, k ) {
				item.classList.toggle( 'is-active', k === i );
			} );
			active = i;
			if ( options[ i ] ) {
				input.setAttribute( 'aria-activedescendant', options[ i ].id );
				options[ i ].scrollIntoView( { block: 'nearest' } );
			}
		}

		function pick( item ) {
			if ( input.value !== item.wpFont ) {
				input.value = item.wpFont;
				input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
			close();
		}

		input.addEventListener( 'focus', function () {
			open( true );
		} );
		input.addEventListener( 'click', function () {
			if ( list.hidden ) {
				open( true );
			}
		} );
		input.addEventListener( 'input', function ( e ) {
			// Our own (a pick): the list stays closed.
			if ( e.isTrusted ) {
				open( false );
			}
		} );
		input.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
				e.preventDefault();
				if ( list.hidden ) {
					open( true );
					return;
				}
				var step = e.key === 'ArrowDown' ? 1 : -1;
				mark( active < 0 ? ( step > 0 ? 0 : options.length - 1 ) : ( active + step + options.length ) % options.length );
			} else if ( e.key === 'Enter' && ! list.hidden && options[ active ] ) {
				e.preventDefault();
				pick( options[ active ] );
			} else if ( e.key === 'Escape' && ! list.hidden ) {
				e.preventDefault();
				e.stopPropagation();
				close();
			} else if ( e.key === 'Tab' ) {
				close();
			}
		} );
		// A click on a name: picked (mousedown, before the field loses focus).
		list.addEventListener( 'mousedown', function ( e ) {
			e.preventDefault();
			var item = e.target.closest( '.wp-swiper-font__option' );
			if ( item ) {
				pick( item );
			}
		} );
		list.addEventListener( 'mousemove', function ( e ) {
			var item = e.target.closest( '.wp-swiper-font__option' );
			if ( item && options.indexOf( item ) !== active ) {
				options.forEach( function ( other ) {
					other.classList.toggle( 'is-active', other === item );
				} );
				active = options.indexOf( item );
			}
		} );
		toggle.addEventListener( 'mousedown', function ( e ) {
			e.preventDefault();
			if ( list.hidden ) {
				input.focus();
				open( true );
			} else {
				close();
			}
		} );
		input.addEventListener( 'blur', close );
	}

	function init() {
		[].forEach.call( document.querySelectorAll( '.wp-swiper-font' ), setup );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
