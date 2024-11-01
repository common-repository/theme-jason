<?php

namespace ThemeJason\Classes\Front;

class Front {

	function __construct() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueues the Google fonts based on user and theme styles.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {

		if ( class_exists( '\WP_Theme_JSON_Resolver' ) ) {
			$data = \WP_Theme_JSON_Resolver::get_merged_data();
		} else {
			$data = \WP_Theme_JSON_Resolver_Gutenberg::get_merged_data();
		}

		$data = $data->get_raw_data();
		if (
			! empty( $data['settings'] ) &&
			! empty( $data['settings']['typography'] ) &&
			! empty( $data['settings']['typography']['fontFamilies'] ) &&
			! empty( $data['settings']['typography']['fontFamilies']['user'] ) &&
			is_array( $data['settings']['typography']['fontFamilies']['user'] )
		) {

			$fonts = $data['settings']['typography']['fontFamilies'];

			$theme_fonts = ! empty( $fonts['theme'] ) ? $fonts['theme'] : array();
			$user_fonts  = ! empty( $fonts['user'] ) ? $fonts['user'] : array();

			$all_fonts    = array_merge( $theme_fonts, $user_fonts );
			$google_fonts = array_column( $all_fonts, 'google' );
			$google_fonts = array_unique( $google_fonts );

			$fonts = implode( '&', $google_fonts );

			// It's needed to use null as the version to prevent PHP from removing multiple the multiple font parameter.
			wp_enqueue_style( 'theme-jason-fonts', sprintf( 'https://fonts.googleapis.com/css?%s', esc_attr( $fonts ) ), false, null ); // phpcs:ignore WordPress.WP
		}

	}

}
