<?php
/**
 * The slides and their media: the saved slides, images and videos (own
 * files and YouTube / Vimeo embeds), their sizes, thumbnails and posters.
 * Used by WP_Swiper_Settings, so `self::` is that class.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait WP_Swiper_Settings_Media {

	/**
	 * Saved slides: list of [ 'id' => int, 'caption' => string, 'link' => string,
	 * 'new_tab' => bool, 'poster' => int, 'embed' => string, 'description' =>
	 * string ] (slides saved before "new tab", video slides or descriptions
	 * existed have no new_tab / poster / embed / description key). A
	 * description: HTML (see description_tags()); a caption too (see
	 * caption_tags()), plain text when saved before it had an editor. "id": an image, or a video (its "poster": the image shown
	 * before it plays; 0 = none); or "embed": a YouTube / Vimeo link (id 0).
	 *
	 * @param int $post_id Slider ID.
	 * @return array
	 */
	public static function get_slides( $post_id ) {
		$slides = get_post_meta( $post_id, self::META_SLIDES, true );
		return is_array( $slides ) ? $slides : array();
	}

	/**
	 * Whether a media library item can be a slide: an image or a video.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function is_slide_media( $attachment_id ) {
		return wp_attachment_is_image( $attachment_id ) || self::is_video( $attachment_id );
	}

	/**
	 * Whether a saved slide can be shown: a YouTube / Vimeo link it can read,
	 * or an image or video still in the media library.
	 *
	 * @param array $slide Slide data.
	 * @return bool
	 */
	public static function is_slide( $slide ) {
		if ( ! empty( $slide['embed'] ) ) {
			return (bool) self::parse_embed( $slide['embed'] );
		}
		return ! empty( $slide['id'] ) && self::is_slide_media( $slide['id'] );
	}

	/**
	 * A YouTube or Vimeo link as its provider and video id ("hash": an
	 * unlisted Vimeo video's key; "vertical": a YouTube Short).
	 *
	 * @param string $url Video page or player address.
	 * @return array|false [ 'provider', 'id', 'hash', 'vertical' ]
	 */
	public static function parse_embed( $url ) {
		$url = trim( (string) $url );
		if ( preg_match( '~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtube-nocookie\.com/embed/|youtu\.be/)([\w-]{11})~i', $url, $match ) ) {
			return array(
				'provider' => 'youtube',
				'id'       => $match[1],
				'hash'     => '',
				'vertical' => false !== stripos( $url, '/shorts/' ),
			);
		}
		if ( preg_match( '~^https?://(?:www\.)?(?:player\.)?vimeo\.com/(?:.*?/)?(\d{6,})(?:/([\da-f]{6,}))?~i', $url, $match ) ) {
			$hash = isset( $match[2] ) ? $match[2] : '';
			if ( ! $hash && preg_match( '~[?&]h=([\da-f]+)~i', $url, $key ) ) {
				$hash = $key[1];
			}
			return array(
				'provider' => 'vimeo',
				'id'       => $match[1],
				'hash'     => $hash,
				'vertical' => false,
			);
		}
		return false;
	}

	/**
	 * A YouTube / Vimeo video's size, thumbnail and title from the service
	 * (oEmbed), kept for a week (an hour when the service did not answer).
	 *
	 * @param string $url Video address.
	 * @return array [ 'width', 'height', 'thumb', 'title' ], empty if unknown.
	 */
	public static function embed_meta( $url ) {
		$key  = 'wp_swiper_oembed_' . md5( $url );
		$meta = get_transient( $key );
		if ( false === $meta ) {
			$data = _wp_oembed_get_object()->get_data( $url );
			$meta = $data ? array(
				'width'  => isset( $data->width ) ? (int) $data->width : 0,
				'height' => isset( $data->height ) ? (int) $data->height : 0,
				'thumb'  => isset( $data->thumbnail_url ) ? esc_url_raw( $data->thumbnail_url ) : '',
				'title'  => isset( $data->title ) ? sanitize_text_field( $data->title ) : '',
			) : array();
			set_transient( $key, $meta, $data ? WEEK_IN_SECONDS : HOUR_IN_SECONDS );
		}
		return is_array( $meta ) ? $meta : array();
	}

	/**
	 * Width and height of a slide: its image's or video's, a YouTube / Vimeo
	 * video's from the service (16:9 when unknown, 9:16 for a YouTube Short).
	 *
	 * @param array $slide Slide data.
	 * @return array|false [ width, height ]
	 */
	public static function slide_size( $slide ) {
		if ( empty( $slide['embed'] ) ) {
			return self::media_size( $slide['id'] );
		}
		$embed = self::parse_embed( $slide['embed'] );
		$meta  = self::embed_meta( $slide['embed'] );
		if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) && ! ( $embed && $embed['vertical'] ) ) {
			return array( $meta['width'], $meta['height'] );
		}
		return $embed && $embed['vertical'] ? array( 9, 16 ) : array( 16, 9 );
	}

	/**
	 * A YouTube / Vimeo video's own thumbnail: YouTube's largest (the page
	 * falls back to `fallback` where it has none), Vimeo's from the service;
	 * `small` for thumbnails (YouTube's 16:9 one: its larger ones that are
	 * always there have black bands in the picture).
	 *
	 * @param string $url Video address.
	 * @return array [ 'src', 'fallback', 'small' ], empty strings if none.
	 */
	public static function embed_thumb( $url ) {
		$embed = self::parse_embed( $url );
		if ( $embed && 'youtube' === $embed['provider'] ) {
			return array(
				'src'      => 'https://i.ytimg.com/vi/' . $embed['id'] . '/maxresdefault.jpg',
				'fallback' => 'https://i.ytimg.com/vi/' . $embed['id'] . '/hqdefault.jpg',
				'small'    => 'https://i.ytimg.com/vi/' . $embed['id'] . '/mqdefault.jpg',
			);
		}
		$meta  = $embed ? self::embed_meta( $url ) : array();
		$thumb = empty( $meta['thumb'] ) ? '' : $meta['thumb'];
		return array(
			'src'      => $thumb,
			'fallback' => '',
			'small'    => $thumb,
		);
	}

	/**
	 * Whether a slide's media is a video.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public static function is_video( $attachment_id ) {
		return (bool) wp_attachment_is( 'video', $attachment_id );
	}

	/**
	 * Width and height of a slide's media: an image's full size, a video's
	 * frame (from its metadata).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|false [ width, height ], both above 0.
	 */
	public static function media_size( $attachment_id ) {
		if ( self::is_video( $attachment_id ) ) {
			$meta = wp_get_attachment_metadata( $attachment_id );
			$size = array( isset( $meta['width'] ) ? (int) $meta['width'] : 0, isset( $meta['height'] ) ? (int) $meta['height'] : 0 );
		} else {
			$src  = wp_get_attachment_image_src( $attachment_id, 'full' );
			$size = $src ? array( (int) $src[1], (int) $src[2] ) : array( 0, 0 );
		}
		return $size[0] > 0 && $size[1] > 0 ? $size : false;
	}

	/**
	 * The image that stands for a video slide (before it plays, in the strip
	 * of thumbnails): its poster, else the video's own featured image.
	 *
	 * @param array $slide Slide data.
	 * @return int Attachment ID, 0 if none.
	 */
	public static function poster( $slide ) {
		if ( ! empty( $slide['poster'] ) && wp_attachment_is_image( $slide['poster'] ) ) {
			return (int) $slide['poster'];
		}
		// A YouTube / Vimeo video has its own thumbnail instead (embed_thumb()).
		if ( ! empty( $slide['embed'] ) || empty( $slide['id'] ) ) {
			return 0;
		}
		$featured = (int) get_post_thumbnail_id( $slide['id'] );
		return $featured && wp_attachment_is_image( $featured ) ? $featured : 0;
	}
}
