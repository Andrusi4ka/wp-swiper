<?php
/**
 * Video slides: the poster, an embedded player's data and markup, a video
 * file's. Used by WP_Swiper_Render, so `self::` is that class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Swiper_Render_Media {

	/**
	 * A video slide's poster address: the one chosen, else (a YouTube / Vimeo
	 * video) its own thumbnail.
	 *
	 * @param array $slide Slide data.
	 * @return string '' if none.
	 */
	private static function poster_url( $slide ) {
		$poster = WP_Swiper_Settings::poster( $slide );
		if ( $poster ) {
			return (string) wp_get_attachment_image_url( $poster, 'full' );
		}
		if ( $slide['embed_info'] ) {
			$thumb = WP_Swiper_Settings::embed_thumb( $slide['embed'] );
			return $thumb['src'];
		}
		return '';
	}

	/**
	 * A YouTube / Vimeo video for frontend.js: provider, id, Vimeo's key, title.
	 *
	 * @param array $slide Slide data.
	 * @return array
	 */
	private static function embed_data( $slide ) {
		$meta = WP_Swiper_Settings::embed_meta( $slide['embed'] );
		return array(
			'provider' => $slide['embed_info']['provider'],
			'id'       => $slide['embed_info']['id'],
			'hash'     => $slide['embed_info']['hash'],
			'title'    => empty( $meta['title'] ) ? '' : $meta['title'],
		);
	}

	/**
	 * A YouTube / Vimeo slide: a "facade", its poster (and a play button)
	 * until frontend.js puts the service's player in it, which is when the
	 * slide comes up (Play automatically) or is clicked. Nothing of the
	 * service loads with the page but the poster image.
	 *
	 * @param array $slide Slide data.
	 * @return string
	 */
	private static function embed( $slide ) {
		$poster   = WP_Swiper_Settings::poster( $slide );
		$thumb    = WP_Swiper_Settings::embed_thumb( $slide['embed'] );
		$src      = $poster ? wp_get_attachment_image_url( $poster, 'full' ) : $thumb['src'];
		$fallback = $poster ? '' : $thumb['fallback'];
		$data     = self::embed_data( $slide );

		return sprintf(
			'<div class="wp-swiper__image wp-swiper__embed" data-wp-swiper-embed-player="%1$s">%2$s<button type="button" class="wp-swiper__play" aria-label="%3$s"></button></div>',
			esc_attr( wp_json_encode( $data ) ),
			$src ? sprintf(
				'<img class="wp-swiper__embed-poster" src="%s" alt="" loading="lazy" decoding="async"%s>',
				esc_url( $src ),
				$fallback ? ' data-fallback="' . esc_url( $fallback ) . '"' : ''
			) : '',
			/* translators: %s: video title. */
			esc_attr( $data['title'] ? sprintf( __( 'Play video: %s', 'wp-swiper' ), $data['title'] ) : __( 'Play video', 'wp-swiper' ) )
		);
	}

	/**
	 * A video slide's player. Its file is not loaded with the page: the
	 * address waits in data-src until frontend.js plays the slide (or loads
	 * the next one ahead), so the copies loop mode repeats load nothing.
	 * Muted in the markup when set: browsers only autoplay muted videos.
	 *
	 * @param array       $slide    Slide data.
	 * @param array|false $size     The video's width and height.
	 * @param array       $settings Slider settings.
	 * @return string
	 */
	private static function video( $slide, $size, $settings ) {
		$poster = WP_Swiper_Settings::poster( $slide );
		$poster = $poster ? wp_get_attachment_image_url( $poster, 'full' ) : '';

		return sprintf(
			'<video class="wp-swiper__image wp-swiper__video" data-src="%1$s"%2$s%3$s preload="none" playsinline%4$s%5$s%6$s></video>',
			esc_url( wp_get_attachment_url( $slide['id'] ) ),
			$size ? sprintf( ' width="%d" height="%d"', $size[0], $size[1] ) : '',
			$poster ? ' poster="' . esc_url( $poster ) . '"' : '',
			$settings['video_muted'] ? ' muted' : '',
			$settings['video_loop'] ? ' loop' : '',
			$settings['video_controls'] || ! $settings['video_autoplay'] ? ' controls' : ''
		);
	}
}
