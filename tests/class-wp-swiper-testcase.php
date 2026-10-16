<?php
/**
 * Helpers shared by the tests: media library items (no files: their
 * metadata only), sliders, and the markup and config a slider renders.
 */

abstract class WP_Swiper_TestCase extends WP_UnitTestCase {

	/**
	 * An image in the media library, without a file (the plugin only reads
	 * its URL and size).
	 *
	 * @param int $width  Width.
	 * @param int $height Height.
	 * @return int Attachment ID.
	 */
	protected function image( $width = 2000, $height = 1000 ) {
		$name = 'image-' . wp_generate_password( 6, false ) . '.jpg';
		$id   = self::factory()->attachment->create_object(
			array(
				'file'           => $name,
				'post_mime_type' => 'image/jpeg',
				'post_title'     => $name,
			)
		);
		wp_update_attachment_metadata(
			$id,
			array(
				'width'  => $width,
				'height' => $height,
				'file'   => $name,
				'sizes'  => array(),
			)
		);
		return $id;
	}

	/**
	 * A video in the media library, without a file.
	 *
	 * @return int Attachment ID.
	 */
	protected function video() {
		$name = 'clip-' . wp_generate_password( 6, false ) . '.mp4';
		$id   = self::factory()->attachment->create_object(
			array(
				'file'           => $name,
				'post_mime_type' => 'video/mp4',
				'post_title'     => $name,
			)
		);
		wp_update_attachment_metadata(
			$id,
			array(
				'width'  => 1920,
				'height' => 1080,
			)
		);
		return $id;
	}

	/**
	 * Slides of `count` new images.
	 *
	 * @param int   $count Number of slides.
	 * @param array $extra Values every slide gets (caption, link...).
	 * @return array
	 */
	protected function slides( $count = 3, $extra = array() ) {
		$slides = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$slides[] = $extra + array(
				'id'      => $this->image(),
				'caption' => '',
				'link'    => '',
				'new_tab' => false,
				'poster'  => 0,
			);
		}
		return $slides;
	}

	/**
	 * A published slider.
	 *
	 * @param array $slides   Slides.
	 * @param array $settings Saved settings (over the defaults when read).
	 * @param array $post     Post fields.
	 * @return int Slider ID.
	 */
	protected function slider( $slides = null, $settings = array(), $post = array() ) {
		$id = self::factory()->post->create(
			$post + array(
				'post_type'   => WP_Swiper_Settings::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Test slider',
			)
		);
		update_post_meta( $id, WP_Swiper_Settings::META_SLIDES, null === $slides ? $this->slides() : $slides );
		update_post_meta( $id, WP_Swiper_Settings::META_SETTINGS, $settings );
		return $id;
	}

	/**
	 * Settings as the plugin uses them: the defaults with these over them.
	 *
	 * @param array $values Values.
	 * @return array
	 */
	protected function settings( $values = array() ) {
		return array_merge( WP_Swiper_Settings::defaults(), $values );
	}

	/**
	 * A slider's markup from slides and settings (as the preview draws it).
	 *
	 * @param array $values Settings over the defaults.
	 * @param array $slides Slides (3 images when null).
	 * @param string $class Extra classes.
	 * @return string
	 */
	protected function render( $values = array(), $slides = null, $class = '' ) {
		return WP_Swiper_Render::render_slider( 1, null === $slides ? $this->slides() : $slides, $this->settings( $values ), $class );
	}

	/**
	 * The Swiper config (data-wp-swiper) of some markup.
	 *
	 * @param string $html Markup.
	 * @param string $attr The attribute (data-wp-swiper-collage for a collage).
	 * @return array
	 */
	protected function config( $html, $attr = 'data-wp-swiper' ) {
		$this->assertSame( 1, preg_match( '/' . preg_quote( $attr, '/' ) . '="([^"]*)"/', $html, $match ), "No $attr in the markup." );
		return json_decode( html_entity_decode( $match[1], ENT_QUOTES ), true );
	}

	/**
	 * The classes of the slider's outer block.
	 *
	 * @param string $html Markup.
	 * @return string[]
	 */
	protected function root_classes( $html ) {
		$this->assertSame( 1, preg_match( '/<div[^>]* class="(wp-swiper [^"]*)"/', $html, $match ), 'No outer block in the markup.' );
		return explode( ' ', $match[1] );
	}

	/**
	 * The inline style (custom properties) of the slider's outer block.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	protected function root_style( $html ) {
		preg_match( '/<div[^>]* class="wp-swiper [^"]*" style="([^"]*)"/', $html, $match );
		return isset( $match[1] ) ? html_entity_decode( $match[1] ) : '';
	}
}
