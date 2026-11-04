<?php
/**
 * Grid shapes: its layout as stored (art_cells,
 * "4x4:0,0,1,2,…;1=100;;6.2=0") read, numbered, written back and
 * sanitized, and the default one. Used by WP_Swiper_Art, so `self::` is
 * that class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Swiper_Art_Cells {

	/**
	 * Grid shapes: a layout as stored, read. "4x4:0,0,1,2,…;1=100,7=50;6.2=0":
	 * columns x rows, then the shape of each cell row by row (its number,
	 * -1 for an empty cell), then the shapes' own corner radius (% of the
	 * largest, half a cell's smaller side), each "shape=percent", then single
	 * outward corners' own, each "x.y=percent": the corner's place on the grid
	 * of cells and gaps (cells at even, gaps at odd lines: x from 0 to
	 * 2 × columns - 1). Null for anything else.
	 *
	 * @param string $value Stored value.
	 * @return array|null [ 'columns' => int, 'rows' => int, 'cells' => int[], 'radii' => [ shape => int ], 'corners' => [ "x.y" => int ] ]
	 */
	public static function parse_cells( $value ) {
		$value = preg_replace( '/\s+/', '', (string) $value );
		if ( strlen( $value ) > 4000 || ! preg_match( '/^(\d{1,2})x(\d{1,2}):(-?\d{1,3}(?:,-?\d{1,3})*)(?:;((?:\d{1,3}=\d{1,3})(?:,\d{1,3}=\d{1,3})*)?(?:;((?:\d{1,2}\.\d{1,2}=\d{1,3})(?:,\d{1,2}\.\d{1,2}=\d{1,3})*))?)?$/', $value, $m ) ) {
			return null;
		}
		$columns = (int) $m[1];
		$rows    = (int) $m[2];
		$cells   = array_map( 'intval', explode( ',', $m[3] ) );
		$max     = self::CELLS_MAX * self::CELLS_MAX;
		if ( $columns < 1 || $rows < 1 || $columns > self::CELLS_MAX || $rows > self::CELLS_MAX || count( $cells ) !== $columns * $rows ) {
			return null;
		}
		foreach ( $cells as $cell ) {
			if ( $cell < -1 || $cell >= $max ) {
				return null;
			}
		}
		$radii = array();
		if ( ! empty( $m[4] ) ) {
			foreach ( explode( ',', $m[4] ) as $pair ) {
				list( $shape, $percent ) = array_map( 'intval', explode( '=', $pair ) );
				if ( $shape < $max ) {
					$radii[ $shape ] = min( 100, $percent );
				}
			}
		}
		return array(
			'columns' => $columns,
			'rows'    => $rows,
			'cells'   => $cells,
			'radii'   => $radii,
			'corners' => self::corners_within( empty( $m[5] ) ? array() : self::read_corners( $m[5] ), $columns, $rows ),
		);
	}

	/**
	 * Grid shapes: single corners' own radii as stored ("6.2=0,3.4=100").
	 *
	 * @param string $text Stored.
	 * @return array "x.y" => percent
	 */
	private static function read_corners( $text ) {
		$corners = array();
		foreach ( explode( ',', $text ) as $pair ) {
			list( $at, $percent ) = explode( '=', $pair );
			list( $x, $y )        = array_map( 'intval', explode( '.', $at ) );
			$corners[ $x . '.' . $y ] = min( 100, (int) $percent );
		}
		return $corners;
	}

	/**
	 * Grid shapes: the corners' own radii of a grid of that size (the others
	 * left out), at most one per corner place.
	 *
	 * @param array $corners "x.y" => percent.
	 * @param int   $columns Columns.
	 * @param int   $rows    Rows.
	 * @return array "x.y" => percent
	 */
	private static function corners_within( $corners, $columns, $rows ) {
		$kept = array();
		foreach ( $corners as $at => $percent ) {
			list( $x, $y ) = array_map( 'intval', explode( '.', $at ) );
			if ( $x <= 2 * $columns - 1 && $y <= 2 * $rows - 1 ) {
				$kept[ $x . '.' . $y ] = $percent;
			}
		}
		return $kept;
	}

	/**
	 * Grid shapes: a layout with its shapes numbered from 0 in the order they
	 * first come row by row (empty cells -1), the radii of shapes left out dropped.
	 *
	 * @param array $grid As parse_cells() gives it (any shape numbers).
	 * @return array The same, numbered, with 'count' (how many shapes).
	 */
	public static function number_cells( $grid ) {
		$map   = array();
		$cells = array();
		foreach ( $grid['cells'] as $cell ) {
			if ( $cell < 0 ) {
				$cells[] = -1;
				continue;
			}
			if ( ! isset( $map[ $cell ] ) ) {
				$map[ $cell ] = count( $map );
			}
			$cells[] = $map[ $cell ];
		}
		$radii = array();
		foreach ( $grid['radii'] as $shape => $percent ) {
			if ( isset( $map[ $shape ] ) ) {
				$radii[ $map[ $shape ] ] = $percent;
			}
		}
		ksort( $radii );
		// The corners' own radii are by place on the grid, whatever the shapes' numbers.
		$corners = isset( $grid['corners'] ) ? $grid['corners'] : array();
		ksort( $corners, SORT_NATURAL );
		return array(
			'columns' => $grid['columns'],
			'rows'    => $grid['rows'],
			'cells'   => $cells,
			'radii'   => $radii,
			'corners' => $corners,
			'count'   => count( $map ),
		);
	}

	/**
	 * Grid shapes: a layout as stored (parse_cells()'s format).
	 *
	 * @param array $grid A layout.
	 * @return string
	 */
	public static function cells_text( $grid ) {
		$radii = array();
		foreach ( $grid['radii'] as $shape => $percent ) {
			$radii[] = (int) $shape . '=' . (int) $percent;
		}
		$corners = array();
		foreach ( isset( $grid['corners'] ) ? $grid['corners'] : array() as $at => $percent ) {
			$corners[] = $at . '=' . (int) $percent;
		}
		$text = (int) $grid['columns'] . 'x' . (int) $grid['rows'] . ':' . implode( ',', array_map( 'intval', $grid['cells'] ) );
		if ( $corners ) {
			return $text . ';' . implode( ',', $radii ) . ';' . implode( ',', $corners );
		}
		return $text . ( $radii ? ';' . implode( ',', $radii ) : '' );
	}

	/**
	 * Grid shapes: the layout kept for the setting, numbered (number_cells());
	 * '' for none or anything else (the default layout then).
	 *
	 * @param mixed $value Raw input.
	 * @return string
	 */
	public static function sanitize_cells( $value ) {
		$grid = self::parse_cells( (string) $value );
		return $grid ? self::cells_text( self::number_cells( $grid ) ) : '';
	}

	/**
	 * Grid shapes: the layout before any is drawn. On 4 x 4 a few shapes of
	 * different sizes, the cells of the others each a shape of its own.
	 *
	 * @param int $columns Columns.
	 * @param int $rows    Rows.
	 * @return array A layout (parse_cells()).
	 */
	public static function default_cells( $columns, $rows ) {
		if ( 4 === $columns && 4 === $rows ) {
			return self::parse_cells( '4x4:0,0,1,2,3,4,4,2,3,4,4,5,6,6,7,5;1=100,7=100' );
		}
		return array(
			'columns' => $columns,
			'rows'    => $rows,
			'cells'   => range( 0, $columns * $rows - 1 ),
			'radii'   => array(),
			'corners' => array(),
		);
	}

	/**
	 * Grid shapes: the layout as drawn, at its Columns and Rows (one for every
	 * device): the saved one (else the default one) where it reaches, each
	 * cell beyond it a shape of its own; numbered (number_cells()).
	 *
	 * @param array $settings Slider settings.
	 * @return array [ 'columns', 'rows', 'cells', 'radii', 'count' ]
	 */
	public static function cells( $settings ) {
		$columns = max( 2, min( self::CELLS_MAX, (int) ( isset( $settings['art_cells_columns'] ) ? $settings['art_cells_columns'] : 4 ) ) );
		$rows    = max( 2, min( self::CELLS_MAX, (int) ( isset( $settings['art_cells_rows'] ) ? $settings['art_cells_rows'] : 4 ) ) );
		$saved   = self::parse_cells( isset( $settings['art_cells'] ) ? $settings['art_cells'] : '' );
		if ( ! $saved ) {
			$saved = self::default_cells( $columns, $rows );
		}
		$cells = array();
		// Shape numbers for the new cells, past any saved one.
		$next = self::CELLS_MAX * self::CELLS_MAX;
		for ( $y = 0; $y < $rows; $y++ ) {
			for ( $x = 0; $x < $columns; $x++ ) {
				$cells[] = $x < $saved['columns'] && $y < $saved['rows'] ? $saved['cells'][ $y * $saved['columns'] + $x ] : $next++;
			}
		}
		return self::number_cells(
			array(
				'columns' => $columns,
				'rows'    => $rows,
				'cells'   => $cells,
				'radii'   => $saved['radii'],
				// Those the grid still reaches (art.js uses those on an outward corner).
				'corners' => self::corners_within( $saved['corners'], $columns, $rows ),
			)
		);
	}
}
