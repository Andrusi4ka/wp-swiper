/**
 * WP Swiper, Art's Grid shapes (art.js's part, loaded after it where a
 * slider has that Style, and in the preview): the shapes joined from the
 * grid's cells drawn at the stage's size, their corners rounded out and in.
 * art.js's helpers from window.wpSwiperArtKit; the Style registered there.
 */
( function () {
	'use strict';

	var kit = window.wpSwiperArtKit;
	if ( ! kit ) {
		return;
	}
	var forDevice = kit.forDevice;
	var place = kit.place;
	var captionAt = kit.captionAt;
	var outline = kit.outline;
	var tidyOutline = kit.tidyOutline;

	/*
	 * Grid shapes: a grid of Columns x Rows cells (config.grid, one layout for
	 * every device), each cell in a shape (its number) or empty (-1). Drawn
	 * anew at the stage's size, so it stretches to any width and height. A
	 * shape is its cells and the Gap between them where they touch (between
	 * four cells only where all four are its), cut from the stage as one
	 * outline per piece; a shape round a hole gets the hole too. Its corners
	 * are real arcs: outward ones at the shape's radius (Radius, or its own:
	 * a share of half a cell), inward ones at the radius of the shape across
	 * the corner plus the Gap, so the gap between them stays even.
	 */

	// A layout as stored (WP_Swiper_Art::parse_cells()), read; null for anything else.
	function parseCells( text ) {
		var m = /^(\d{1,2})x(\d{1,2}):(-?\d{1,3}(?:,-?\d{1,3})*)(?:;((?:\d{1,3}=\d{1,3})(?:,\d{1,3}=\d{1,3})*)?(?:;((?:\d{1,2}\.\d{1,2}=\d{1,3})(?:,\d{1,2}\.\d{1,2}=\d{1,3})*))?)?$/.exec( String( text || '' ).replace( /\s+/g, '' ) );
		if ( ! m ) {
			return null;
		}
		var columns = Number( m[ 1 ] );
		var rows = Number( m[ 2 ] );
		var cells = m[ 3 ].split( ',' ).map( Number );
		if ( columns < 1 || rows < 1 || columns > 12 || rows > 12 || cells.length !== columns * rows ) {
			return null;
		}
		var radii = {};
		( m[ 4 ] || '' ).split( ',' ).forEach( function ( pair ) {
			var parts = pair.split( '=' );
			if ( parts.length === 2 ) {
				radii[ Number( parts[ 0 ] ) ] = Math.min( 100, Number( parts[ 1 ] ) );
			}
		} );
		// Single outward corners' own radii, by their place on the grid of cells and gaps ("x.y").
		var corners = {};
		( m[ 5 ] || '' ).split( ',' ).forEach( function ( pair ) {
			var parts = pair.split( '=' );
			if ( parts.length === 2 ) {
				var at = parts[ 0 ].split( '.' ).map( Number );
				if ( at[ 0 ] <= 2 * columns - 1 && at[ 1 ] <= 2 * rows - 1 ) {
					corners[ at[ 0 ] + '.' + at[ 1 ] ] = Math.min( 100, Number( parts[ 1 ] ) );
				}
			}
		} );
		return { columns: columns, rows: rows, cells: cells, radii: radii, corners: corners };
	}

	// The shapes numbered from 0 in the order they first come row by row (WP_Swiper_Art::number_cells()).
	function numberCells( grid ) {
		var map = {};
		var count = 0;
		var cells = grid.cells.map( function ( cell ) {
			if ( cell < 0 ) {
				return -1;
			}
			if ( ! ( cell in map ) ) {
				map[ cell ] = count++;
			}
			return map[ cell ];
		} );
		var radii = {};
		Object.keys( grid.radii || {} ).forEach( function ( shape ) {
			if ( shape in map ) {
				radii[ map[ shape ] ] = grid.radii[ shape ];
			}
		} );
		var corners = {};
		Object.keys( grid.corners || {} ).forEach( function ( at ) {
			corners[ at ] = grid.corners[ at ];
		} );
		return { columns: grid.columns, rows: grid.rows, cells: cells, radii: radii, corners: corners, count: count };
	}

	// A layout as stored.
	function cellsText( grid ) {
		var radii = Object.keys( grid.radii || {} ).map( Number ).sort( function ( a, b ) {
			return a - b;
		} ).map( function ( shape ) {
			return shape + '=' + Math.round( grid.radii[ shape ] );
		} );
		var corners = Object.keys( grid.corners || {} ).sort( function ( a, b ) {
			var p = a.split( '.' ).map( Number );
			var q = b.split( '.' ).map( Number );
			return p[ 0 ] - q[ 0 ] || p[ 1 ] - q[ 1 ];
		} ).map( function ( at ) {
			return at + '=' + Math.round( grid.corners[ at ] );
		} );
		var text = grid.columns + 'x' + grid.rows + ':' + grid.cells.join( ',' );
		if ( corners.length ) {
			return text + ';' + radii.join( ',' ) + ';' + corners.join( ',' );
		}
		return text + ( radii.length ? ';' + radii.join( ',' ) : '' );
	}

	// Each shape's cells that touch side by side as one shape: a shape an
	// edit cut in two becomes two (the first part keeps its own radius).
	function splitCells( grid ) {
		var cells = grid.cells.slice();
		var out = cells.map( function () {
			return -1;
		} );
		var radii = {};
		var given = {};
		var next = 0;
		for ( var start = 0; start < cells.length; start++ ) {
			if ( cells[ start ] < 0 || out[ start ] >= 0 ) {
				continue;
			}
			var shape = cells[ start ];
			var stack = [ start ];
			var own = next++;
			// Its radius to the first part only.
			if ( grid.radii && shape in grid.radii && ! given[ shape ] ) {
				radii[ own ] = grid.radii[ shape ];
				given[ shape ] = true;
			}
			out[ start ] = own;
			while ( stack.length ) {
				var at = stack.pop();
				var x = at % grid.columns;
				var y = Math.floor( at / grid.columns );
				[ [ x - 1, y ], [ x + 1, y ], [ x, y - 1 ], [ x, y + 1 ] ].forEach( function ( p ) {
					var k = p[ 1 ] * grid.columns + p[ 0 ];
					if ( p[ 0 ] >= 0 && p[ 0 ] < grid.columns && p[ 1 ] >= 0 && p[ 1 ] < grid.rows && out[ k ] < 0 && cells[ k ] === shape ) {
						out[ k ] = own;
						stack.push( k );
					}
				} );
			}
		}
		return numberCells( { columns: grid.columns, rows: grid.rows, cells: out, radii: radii, corners: grid.corners || {} } );
	}

	// For the preview (preview-art.js): read, write, number and split layouts.
	window.wpSwiperArtCells = { parse: parseCells, text: cellsText, number: numberCells, split: splitCells };

	/*
	 * A shape's outlines in the grid's own units (cells at even, gaps at odd
	 * indexes): its border, interior on the right (clockwise on screen, a
	 * hole the other way), as lists of corners. `filled(i, j)` says whether a
	 * unit is the shape's.
	 */
	function unitLoops( filled, i0, j0, i1, j1 ) {
		var edges = {};
		var key = function ( x, y ) {
			return x + ',' + y;
		};
		var add = function ( x0, y0, x1, y1 ) {
			var k = key( x0, y0 );
			( edges[ k ] = edges[ k ] || [] ).push( { x0: x0, y0: y0, x1: x1, y1: y1, used: false } );
		};
		for ( var j = j0; j <= j1; j++ ) {
			for ( var i = i0; i <= i1; i++ ) {
				if ( ! filled( i, j ) ) {
					continue;
				}
				if ( ! filled( i, j - 1 ) ) {
					add( i, j, i + 1, j );
				}
				if ( ! filled( i + 1, j ) ) {
					add( i + 1, j, i + 1, j + 1 );
				}
				if ( ! filled( i, j + 1 ) ) {
					add( i + 1, j + 1, i, j + 1 );
				}
				if ( ! filled( i - 1, j ) ) {
					add( i, j + 1, i, j );
				}
			}
		}
		var loops = [];
		Object.keys( edges ).forEach( function ( k ) {
			edges[ k ].forEach( function ( first ) {
				if ( first.used ) {
					return;
				}
				var loop = [];
				var edge = first;
				while ( edge && ! edge.used ) {
					edge.used = true;
					loop.push( { x: edge.x0, y: edge.y0 } );
					var dx = edge.x1 - edge.x0;
					var dy = edge.y1 - edge.y0;
					var options = ( edges[ key( edge.x1, edge.y1 ) ] || [] ).filter( function ( e ) {
						return ! e.used;
					} );
					// Where two corners touch: the right turn, round its own cell.
					options.sort( function ( a, b ) {
						var turn = function ( e ) {
							var cross = dx * ( e.y1 - e.y0 ) - dy * ( e.x1 - e.x0 );
							return cross > 0 ? 0 : ( cross === 0 ? 1 : 2 );
						};
						return turn( a ) - turn( b );
					} );
					edge = options[ 0 ];
				}
				if ( loop.length > 2 ) {
					loops.push( loop );
				}
			} );
		} );
		return loops;
	}

	function gridShapes( block, stage, config ) {
		var width = stage.clientWidth;
		var height = stage.clientHeight;
		var grid = config.grid;
		if ( ! width || ! height || ! grid ) {
			return;
		}
		var pieces = stage.querySelectorAll( '.wp-swiper-art__piece' );
		var columns = grid.columns;
		var rows = grid.rows;
		var gap = Number( forDevice( config.gap ) ) || 0;
		var round = Number( forDevice( config.radius ) ) || 0;
		var cellW = ( width - ( columns - 1 ) * gap ) / columns;
		var cellH = ( height - ( rows - 1 ) * gap ) / rows;
		// No room for the Gap: none.
		if ( cellW < 4 || cellH < 4 ) {
			gap = 0;
			cellW = width / columns;
			cellH = height / rows;
		}
		var half = Math.min( cellW, cellH ) / 2;
		var count = 0;
		grid.cells.forEach( function ( cell ) {
			count = Math.max( count, cell + 1 );
		} );

		// Where each unit starts (cells and gaps in turn), across and down.
		var bx = [ 0 ];
		var by = [ 0 ];
		for ( var u = 0; u < 2 * columns - 1; u++ ) {
			bx.push( bx[ u ] + ( u % 2 ? gap : cellW ) );
		}
		for ( var v = 0; v < 2 * rows - 1; v++ ) {
			by.push( by[ v ] + ( v % 2 ? gap : cellH ) );
		}
		var cellAt = function ( x, y ) {
			return x < 0 || y < 0 || x >= columns || y >= rows ? -1 : grid.cells[ y * columns + x ];
		};
		// A shape's radius: its own (a share of half a cell), else Radius.
		var radiusOf = function ( shape ) {
			var own = grid.radii && grid.radii[ shape ];
			if ( typeof own === 'number' && isFinite( own ) ) {
				return half * Math.max( 0, Math.min( 100, own ) ) / 100;
			}
			return Math.min( round, half );
		};
		// An outward corner's radius: its own (a share of half a cell), else its shape's.
		var cornerRadius = function ( shape, ux, uy ) {
			var own = grid.corners && grid.corners[ ux + '.' + uy ];
			if ( typeof own === 'number' && isFinite( own ) ) {
				return half * Math.max( 0, Math.min( 100, own ) ) / 100;
			}
			return radiusOf( shape );
		};
		// The shape of the cell at a point (-1: none, or a gap).
		var shapeAtPoint = function ( px, py ) {
			var x = Math.floor( px / ( cellW + gap ) );
			var y = Math.floor( py / ( cellH + gap ) );
			if ( px - x * ( cellW + gap ) > cellW || py - y * ( cellH + gap ) > cellH ) {
				return -1;
			}
			return cellAt( x, y );
		};

		// For the preview (preview-art.js): the grid as drawn, and each shape's outline.
		block.wpSwiperArtGrid = { columns: columns, rows: rows, cellW: cellW, cellH: cellH, gap: gap, width: width, height: height };
		block.wpSwiperArtOutlines = [];
		var windows = [];

		for ( var shape = 0; shape < count; shape++ ) {
			var piece = pieces[ shape ];
			if ( ! piece ) {
				break;
			}
			// Its cells' extent.
			var x0 = columns;
			var y0 = rows;
			var x1 = -1;
			var y1 = -1;
			var sumX = 0;
			var sumY = 0;
			var cellsIn = 0;
			for ( var k = 0; k < grid.cells.length; k++ ) {
				if ( grid.cells[ k ] === shape ) {
					var cx = k % columns;
					var cy = Math.floor( k / columns );
					x0 = Math.min( x0, cx );
					y0 = Math.min( y0, cy );
					x1 = Math.max( x1, cx );
					y1 = Math.max( y1, cy );
					sumX += cx;
					sumY += cy;
					cellsIn++;
				}
			}
			if ( ! cellsIn ) {
				piece.hidden = true;
				continue;
			}
			var filled = ( function ( own ) {
				return function ( i, j ) {
					if ( i < 0 || j < 0 || i > 2 * columns - 2 || j > 2 * rows - 2 ) {
						return false;
					}
					var x = i >> 1;
					var y = j >> 1;
					var across = i % 2;
					var down = j % 2;
					if ( cellAt( x, y ) !== own ) {
						return false;
					}
					if ( across && cellAt( x + 1, y ) !== own ) {
						return false;
					}
					if ( down && cellAt( x, y + 1 ) !== own ) {
						return false;
					}
					return ! ( across && down && cellAt( x + 1, y + 1 ) !== own );
				};
			}( shape ) );
			var loops = unitLoops( filled, 2 * x0, 2 * y0, 2 * x1, 2 * y1 ).map( function ( loop ) {
				// In px, without points on a straight line or on top of each other
				// (a gap of 0 is a unit of no size).
				return tidyOutline( loop.map( function ( p ) {
					// Its place on the grid too (a corner's own radius is kept by it).
					return { x: bx[ p.x ], y: by[ p.y ], ux: p.x, uy: p.y };
				} ) );
			} ).filter( function ( loop ) {
				return loop.length > 2;
			} );
			var box = { left: bx[ 2 * x0 ], top: by[ 2 * y0 ] };
			box.width = bx[ 2 * x1 ] + cellW - box.left;
			box.height = by[ 2 * y1 ] + cellH - box.top;
			var own = radiusOf( shape );
			// Its outward corners, for the preview: where each is and its place on the grid.
			var handles = [];

			var d = [];
			loops.forEach( function ( loop ) {
				var n = loop.length;
				var corners = loop.map( function ( p, i ) {
					var prev = loop[ ( i - 1 + n ) % n ];
					var next = loop[ ( i + 1 ) % n ];
					var lenIn = Math.hypot( p.x - prev.x, p.y - prev.y ) || 1;
					var lenOut = Math.hypot( next.x - p.x, next.y - p.y ) || 1;
					var din = { x: ( p.x - prev.x ) / lenIn, y: ( p.y - prev.y ) / lenIn };
					var dout = { x: ( next.x - p.x ) / lenOut, y: ( next.y - p.y ) / lenOut };
					var outward = din.x * dout.y - din.y * dout.x > 0;
					// Inwards, along the corner's diagonal.
					var dx = Math.round( dout.x - din.x );
					var dy = Math.round( dout.y - din.y );
					var r;
					if ( outward ) {
						r = cornerRadius( shape, p.ux, p.uy );
					} else {
						// The shape across the corner: its outward corner there (one
						// place on along the diagonal), a Gap away.
						var reach = gap + half / 2;
						var across = shapeAtPoint( p.x + dx * reach, p.y + dy * reach );
						var facing = across >= 0 ? cornerRadius( across, p.ux + dx, p.uy + dy ) : own;
						// No radius there (Rounded corners off): a sharp corner too.
						r = facing > 0 ? facing + gap : 0;
					}
					r = Math.max( 0, Math.min( r, lenIn / 2, lenOut / 2 ) );
					if ( outward ) {
						// Its handle on the curve, a little in from the corner (the
						// preview drags it inwards, dx / dy, from its radius r).
						var inset = Math.max( 7, r * ( 1 - Math.SQRT1_2 ) );
						handles.push( { key: p.ux + '.' + p.uy, x: p.x + dx * inset, y: p.y + dy * inset, dx: dx, dy: dy, r: r, half: half } );
					}
					return { p: p, din: din, dout: dout, r: r, sweep: outward ? 1 : 0 };
				} );
				var rel = function ( x, y ) {
					return ( Math.round( ( x - box.left ) * 100 ) / 100 ) + ' ' + ( Math.round( ( y - box.top ) * 100 ) / 100 );
				};
				var from = function ( c ) {
					return rel( c.p.x - c.din.x * c.r, c.p.y - c.din.y * c.r );
				};
				var to = function ( c ) {
					return rel( c.p.x + c.dout.x * c.r, c.p.y + c.dout.y * c.r );
				};
				d.push( 'M', to( corners[ 0 ] ) );
				for ( var c = 1; c <= n; c++ ) {
					var corner = corners[ c % n ];
					d.push( 'L', from( corner ) );
					if ( corner.r > 0.01 ) {
						var r = Math.round( corner.r * 100 ) / 100;
						d.push( 'A', r, r, 0, 0, corner.sweep, to( corner ) );
					}
				}
				d.push( 'Z' );
			} );
			d = d.join( ' ' );

			piece.hidden = false;
			place( piece, box );
			piece.style.clipPath = 'path("' + d + '")';
			piece.classList.remove( 'wp-swiper-art__piece--circle' );
			if ( config.one ) {
				piece.style.setProperty( '--wp-swiper-art-ox', box.left + 'px' );
				piece.style.setProperty( '--wp-swiper-art-oy', box.top + 'px' );
				// The caption in the photo, as large as the stage: its middle (art.css).
				captionAt( piece, { x: width / 2, y: height / 2, width: width } );
			} else {
				// Its caption in the cell nearest its middle, as wide as its run of cells in that row.
				var mx = sumX / cellsIn;
				var my = sumY / cellsIn;
				var best = -1;
				var bestDistance = Infinity;
				for ( var m = 0; m < grid.cells.length; m++ ) {
					if ( grid.cells[ m ] === shape ) {
						var distance = Math.hypot( m % columns - mx, Math.floor( m / columns ) - my );
						if ( distance < bestDistance ) {
							bestDistance = distance;
							best = m;
						}
					}
				}
				var bxCell = best % columns;
				var byCell = Math.floor( best / columns );
				var left = bxCell;
				var right = bxCell;
				while ( cellAt( left - 1, byCell ) === shape ) {
					left--;
				}
				while ( cellAt( right + 1, byCell ) === shape ) {
					right++;
				}
				captionAt( piece, {
					x: ( bx[ 2 * left ] + bx[ 2 * right ] + cellW ) / 2 - box.left,
					y: by[ 2 * byCell ] + cellH / 2 - box.top,
					width: ( right - left + 1 ) * ( cellW + gap ) - gap
				} );
			}
			outline( block, piece, d );
			block.wpSwiperArtOutlines[ shape ] = { box: box, d: d, corners: handles };
			windows.push( { box: [ box.left / width, box.top / height, box.width / width, box.height / height ] } );
		}
		for ( var extra = count; extra < pieces.length; extra++ ) {
			pieces[ extra ].hidden = true;
		}
		if ( config.one ) {
			block.style.setProperty( '--wp-swiper-art-sw', width + 'px' );
			block.style.setProperty( '--wp-swiper-art-sh', height + 'px' );
		}
		// For Appearance (wave()): where each window is, and the stage's proportions.
		config.windows = windows;
		config.windowsRatio = width / height;
		block.wpSwiperArtShown = Math.min( count, pieces.length );
	}

	kit.styles[ 'grid-shapes' ] = gridShapes;
	// Blocks of this Style waiting for it (art.js init()).
	kit.init();
}() );
