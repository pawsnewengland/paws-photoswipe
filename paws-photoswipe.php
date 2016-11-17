<?php

/**
 * Plugin Name: PAWS PhotoSwipe
 * Plugin URI: https://github.com/cferdinandi/paws-photoswipe
 * GitHub Plugin URI: https://github.com/cferdinandi/paws-photoswipe
 * Description: A WordPress plugin for <a href="https://github.com/dimsemenov/PhotoSwipe">PhotoSwipe image galleries</a>.
 * Version: 1.0.0
 * Author: Chris Ferdinandi
 * Author URI: http://gomakethings.com
 * License: All rights reserved
 */

	// Get settings
	require_once(  plugin_dir_path( __FILE__ ) . 'includes/options.php' );

	/**
	 * Override default [gallery] shortcode
	 * @link http://robido.com/wordpress/wordpress-gallery-filter-to-modify-the-html-output-of-the-default-gallery-shortcode-and-style/address
	 * @param  String $output Default [gallery] output
	 * @param  Array  $attr   Settings and options
	 * @return String         New markup
	 */
	function paws_photoswipe_gallery( $output, $attr ) {

		// Initialize
		global $post;

		// Get user options
		$options = paws_photoswipe_get_theme_options();

		// Get user group and security
		$current_user = wp_get_current_user();
		$group = current_user_can( 'edit_themes' ) ? false : get_user_meta( $current_user->ID, 'paws_user_group', true );
		$has_security = function_exists( 'paws_restrict_photo_access_add_fields' );

		// Gallery instance counter
		static $instance = 0;
		$instance++;

		// Validate the author's orderby attribute
		if ( isset( $attr['orderby'] ) ) {
			$attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
			if ( ! $attr['orderby'] ) unset( $attr['orderby'] );
		}

		// Get attributes from shortcode
		extract( shortcode_atts( array(
			'order'      => 'ASC',
			'orderby'    => 'menu_order ID',
			'id'         => $post->ID,
			'include'    => '',
			'exclude'    => ''
		), $attr ) );

		// Initialize
		$id = intval( $id );
		$attachments = array();
		if ( $order == 'RAND' ) $orderby = 'none';

		if ( ! empty( $include ) ) {

			// Include attribute is present
			$include = preg_replace( '/[^0-9,]+/', '', $include );
			$_attachments = get_posts(array(
				'include' => $include,
				'post_status' => 'inherit',
				'post_type' => 'attachment',
				'post_mime_type' => 'image',
				'order' => $order,
				'orderby' => $orderby
			));

			// Setup attachments array
			foreach ( $_attachments as $key => $val ) {
				$attachments[ $val->ID ] = $_attachments[ $key ];
			}

		} else if ( ! empty( $exclude ) ) {

			// Exclude attribute is present
			$exclude = preg_replace( '/[^0-9,]+/', '', $exclude );

			// Setup attachments array
			$attachments = get_children(array(
				'post_parent' => $id,
				'exclude' => $exclude,
				'post_status' => 'inherit',
				'post_type' => 'attachment',
				'post_mime_type' => 'image',
				'order' => $order,
				'orderby' => $orderby
			));

		} else {

			// Setup attachments array
			$attachments = get_children(array(
				'post_parent' => $id,
				'post_status' => 'inherit',
				'post_type' => 'attachment',
				'post_mime_type' => 'image',
				'order' => $order,
				'orderby' => $orderby
			));

		}

		if ( empty( $attachments ) ) return '';

		// Filter gallery differently for feeds
		if ( is_feed() ) {
			$output = "\n";
			foreach ( $attachments as $att_id => $attachment ) $output .= wp_get_attachment_link( $att_id, 'medium', true ) . "\n";
			return $output;
		}

		// Generate gallery
		$count = count( $attachments );
		$gallery = '<div data-masonry data-photoswipe data-pswp-uid="' . $instance . '" ' . stripslashes( $options['wrapper_atts'] ) . ' data-photoswipe-count="' . $count . '">';
		foreach ( $attachments as $id => $attachment ) {

			// Image data
			$img_full = wp_get_attachment_image_src( $id, 'full' );
			$img_medium = wp_get_attachment_image_src( $id, 'medium' );
			$img = wp_get_attachment_image( $id, 'medium', false, array( 'class' => stripslashes( $options['img_atts'] ) ) );
			$caption = $attachment->post_excerpt;
			$figure = empty( $caption ) ? '' : '<figure ' . stripslashes( $options['caption_atts'] ) . '>' . $caption . '</figure>';
			$access = get_post_meta( $attachment->ID, 'paws_media_user_groups', true );

			// Restrict photo visibility by group
			if ( $has_security && is_array( $access ) && !empty( $access ) && !empty( $group ) ) {
				if ( !array_key_exists( $group, $access ) ) continue; // If the access group has no setting
				if ( $access[$group] !== 'on' ) continue; // If it does but the setting isn't "on"
			}

			$gallery .=
				'<a data-masonry-content data-size="' . $img_full[1] . 'x' . $img_full[2] . '" data-med="' . $img_medium[0] . '" data-med-size="' . $img_medium[1] . 'x' . $img_medium[2] . '" href="' . $img_full[0] . '" ' . stripslashes( $options['link_atts'] ) . '>' .
					( in_array( $count, array( 1, 2 ) ) ? '<img src="' . $img_full[0] . '" class="' . $options['img_atts'] . '">' : $img ) .
					$figure .
				'</a>';

		}
		$gallery .= '</div>';


		$ps_framework =
			'<div class="pswp" tabindex="-1" role="dialog" aria-hidden="true">' .
				'<div class="pswp__bg"></div>' .
				'<div class="pswp__scroll-wrap">' .
					'<div class="pswp__container">' .
						'<div class="pswp__item"></div>' .
						'<div class="pswp__item"></div>' .
						'<div class="pswp__item"></div>' .
					'</div>' .
					'<div class="pswp__ui pswp__ui--hidden">' .
						'<div class="pswp__top-bar">' .
							'<div class="pswp__counter"></div>' .
							'<button class="pswp__button pswp__button--close" title="Close (Esc)"></button>' .
							'<button class="pswp__button pswp__button--share" title="Share"></button>' .
							'<button class="pswp__button pswp__button--fs" title="Toggle fullscreen"></button>' .
							'<button class="pswp__button pswp__button--zoom" title="Zoom in/out"></button>' .
							'<div class="pswp__preloader">' .
								'<div class="pswp__preloader__icn">' .
								'<div class="pswp__preloader__cut">' .
									'<div class="pswp__preloader__donut"></div>' .
								'</div>' .
								'</div>' .
							'</div>' .
						'</div>' .
						'<div class="pswp__share-modal pswp__share-modal--hidden pswp__single-tap">' .
							'<div class="pswp__share-tooltip"></div>' .
						'</div>' .
						'<button class="pswp__button pswp__button--arrow--left" title="Previous (arrow left)"></button>' .
						'<button class="pswp__button pswp__button--arrow--right" title="Next (arrow right)"></button>' .
						'<div class="pswp__caption">' .
							'<div class="pswp__caption__center"></div>' .
						'</div>' .
					'</div>' .
				'</div>' .
			'</div>';

		return $gallery . $ps_framework;

	}
	add_filter( 'post_gallery', 'paws_photoswipe_gallery', 10, 2 );



	/**
	 * Load scripts and styles async if [gallery] shortcode is used
	 */
	function paws_photoswipe_styles_and_scripts() {
		global $post;
		if ( !is_a( $post, 'WP_Post' ) || !has_shortcode( $post->post_content, 'gallery') ) return;
		?>
			<script>
				;(function (window, document, undefined) {
					'use strict';
					<?php echo file_get_contents( plugin_dir_url( __FILE__ ) . 'dist/js/detects.min.4.1.0.js' ); ?>
					loadCSS( '<?php echo plugin_dir_url( __FILE__ ); ?>dist/css/photoswipe.min.4.1.0.css' );
					loadJS( '<?php echo plugin_dir_url( __FILE__ ); ?>dist/js/photoswipe.min.4.1.0.js' );
				})(window, document);
			</script>
		<?php
	}
	add_action( 'wp_footer', 'paws_photoswipe_styles_and_scripts' );