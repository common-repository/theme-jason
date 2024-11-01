<?php

namespace ThemeJason\Classes\Admin;

class Admin {

	/**
	 * Holds block metadata extracted from block.json
	 * to be shared among all instances so we don't
	 * process it twice.
	 *
	 * @var array
	 */
	private static $blocks_metadata = null;


	function __construct() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_theme_jason_import_styles', array( $this, 'import_styles' ) );
		add_action( 'wp_ajax_theme_jason_export_styles', array( $this, 'export_styles' ) );
	}

	/**
	 * Validates the user permissions and runs the AJAX 'theme_jason_import_styles' to import the Global Styles.
	 *
	 * Only users with 'edit_theme_options' permission can import Global Styles.
	 *
	 * @return void
	 */
	public function import_styles() {

		if ( empty( $_POST['content'] ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_theme_options' ) || empty( check_ajax_referer( 'theme_json_import_styles' ) ) ) {
			return;
		}

		$content = json_decode( wp_kses_post( wp_unslash( $_POST['content'] ) ), true );

		if ( ! empty( $content['config'] ) ) {
			$content = $content['config'];
		}

		if ( empty( $content ) ) {
			return;
		}

		if ( class_exists( '\WP_Theme_JSON' ) ) {
			$schema = new \WP_Theme_JSON( $content, 'user' );
		} else {
			$schema = new \WP_Theme_JSON_Gutenberg( $content, 'user' );
		}

		// Gets the provided version or the latest schema if null.
		$version = empty( $content['version'] ) && is_numeric( $content['version'] ) ? intval( $content['version'] ) : $schema;

		$to_save = array(
			'isGlobalStylesUserThemeJSON' => true,
			'version'                     => $version,
		);

		$config        = array();
		$allowed_items = array( 'settings', 'styles' );

		foreach ( $allowed_items as $key => $value ) {
			if ( ! empty( $content[ $value ] ) ) {
				$config[ $value ] = $content[ $value ];
			}
		}

		// Creates a \WP_Theme_JSON_Gutenberg class to use its constants.
		if ( class_exists( '\WP_Theme_JSON' ) ) {
			$theme_gutenberg = new \WP_Theme_JSON( $content, 'user' );
		} else {
			$theme_gutenberg = new \WP_Theme_JSON_Gutenberg( $content, 'user' );
		}

		$valid_block_names   = array_keys( $this->get_blocks_metadata( $theme_gutenberg::ELEMENTS ) );
		$valid_element_names = array_keys( $theme_gutenberg::ELEMENTS );
		$config              = $this->sanitize_gutenberg_schema( $config, $valid_block_names, $valid_element_names, $theme_gutenberg::VALID_TOP_LEVEL_KEYS, $theme_gutenberg::VALID_STYLES, $theme_gutenberg::VALID_SETTINGS );

		$to_save = array_merge( $to_save, $config );

		$name = 'wp-global-styles-' . urlencode( wp_get_theme()->get_stylesheet() ); // Imports to the current theme.

		$saved_styles = get_posts(
			array(
				'numberposts' => 1,
				'post_type'   => 'wp_global_styles',
				'post_name'   => $name,
				'post_status' => 'publish',
			)
		);

		if ( ! empty( $saved_styles ) ) {
			$result = wp_update_post(
				array(
					'ID'           => $saved_styles[0]->ID,
					'post_content' => wp_json_encode( $to_save ),
					'post_author'  => get_current_user_id(),
					'post_type'    => 'wp_global_styles',
					'post_name'    => $name,

				)
			);
		} else {
			$result = wp_insert_post(
				array(
					'post_content' => wp_json_encode( $to_save ),
					'post_status'  => 'publish',
					'post_title'   => __( 'Custom Styles', 'default' ),
					'post_type'    => 'wp_global_styles',
					'post_name'    => $name,
					'tax_input'    => array(
						'wp_theme' => array( wp_get_theme()->get_stylesheet() ),
					),
				),
				true
			);
		}

		wp_cache_flush();

		wp_send_json( array( 'success' => ! empty( $result ) ), ! empty( $result ) ? 200 : 400 );

		wp_die();
	}

	/**
	 * Validates the user permissions and runs the AJAX 'theme_jason_export_styles' to export the Global Styles.
	 *
	 * Only users with 'edit_theme_options' permission can export Global Styles.
	 *
	 * @return void
	 */
	public function export_styles() {

		if ( ! current_user_can( 'edit_theme_options' ) || empty( check_ajax_referer( 'theme_json_export_styles' ) ) ) {
			return;
		}

		$name = 'wp-global-styles-' . urlencode( wp_get_theme()->get_stylesheet() );

		$styles = get_posts(
			array(
				'numberposts' => 1,
				'post_status' => 'publish',
				'post_type'   => 'wp_global_styles',
				'post_name'   => $name,
			)
		);

		if ( ! empty( $styles ) ) {
			$content = json_decode( $styles[0]->post_content );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$content = false;
			}
			wp_send_json(
				array(
					'content' => array(
						'name'   => sanitize_key( $styles[0]->post_name ),
						'config' => $content,
					),
				),
				200
			);
		}
		wp_die();
	}

	public function enqueue_scripts() {

		wp_enqueue_style( 'theme-jason-admin-css', THEME_JASON_DIRECTORY_URL . 'assets/admin/css/main.css', array(), THEME_JASON_PLUGIN_VERSION, 'all' );

		wp_enqueue_script(
			'theme-jason-admin-js',
			THEME_JASON_DIRECTORY_URL . 'assets/admin/js/main.js',
			array( 'wp-blocks', 'wp-element', 'wp-hooks', 'wp-components', 'wp-i18n', 'wp-edit-post', 'wp-compose' ),
			THEME_JASON_PLUGIN_VERSION,
			true
		);

		$script_params = array(
			'file_name'    => sprintf( '%s-%s-global-styles.json', current_time( 'Y-m-d-h-i-s' ), sanitize_key( get_bloginfo( 'name' ) ) ),
			'ajax'         => array(
				'url'          => admin_url( 'admin-ajax.php' ),
				'import_nonce' => wp_create_nonce( 'theme_json_import_styles' ),
				'export_nonce' => wp_create_nonce( 'theme_json_export_styles' ),
			),
			'localization' => array(
				'import_styles' => __( 'Import styles', 'theme-jason' ),
				'export_styles' => __( 'Export styles', 'theme-jason' ),
				'refresh'       => __( 'Refresh', 'theme-jason' ),
				'success'       => __( 'Style Activated', 'theme-jason' ),
				'error'         => __( 'An error occurred.', 'theme-jason' ),
			),
		);

		wp_localize_script( 'theme-jason-admin-js', 'scriptParams', $script_params );
	}

	/**
	 * Sanitizes the input according to the schemas. (Duplicated from \WP_Theme_JSON_Gutenberg::sanitize)
	 *
	 * @param array $input Structure to sanitize.
	 * @param array $valid_block_names List of valid block names.
	 * @param array $valid_element_names List of valid element names.
	 *
	 * @return array The sanitized output.
	 */
	private function sanitize_gutenberg_schema( $input, $valid_block_names, $valid_element_names, $valid_top_level_keys, $valid_styles, $valid_settings ) {
		$output = array();

		if ( ! is_array( $input ) ) {
			return $output;
		}

		$output = array_intersect_key( $input, array_flip( $valid_top_level_keys ) );

		// Build the schema based on valid block & element names.
		$schema                 = array();
		$schema_styles_elements = array();
		foreach ( $valid_element_names as $element ) {
			$schema_styles_elements[ $element ] = $valid_styles;
		}
		$schema_styles_blocks   = array();
		$schema_settings_blocks = array();
		foreach ( $valid_block_names as $block ) {
			$schema_settings_blocks[ $block ]           = $valid_settings;
			$schema_styles_blocks[ $block ]             = $valid_styles;
			$schema_styles_blocks[ $block ]['elements'] = $schema_styles_elements;
		}
		$schema['styles']             = $valid_styles;
		$schema['styles']['blocks']   = $schema_styles_blocks;
		$schema['styles']['elements'] = $schema_styles_elements;
		$schema['settings']           = $valid_settings;
		$schema['settings']['blocks'] = $schema_settings_blocks;

		// Remove anything that's not present in the schema.
		foreach ( array( 'styles', 'settings' ) as $subtree ) {
			if ( ! isset( $input[ $subtree ] ) ) {
				continue;
			}

			if ( ! is_array( $input[ $subtree ] ) ) {
				unset( $output[ $subtree ] );
				continue;
			}

			$result = self::remove_keys_not_in_schema( $input[ $subtree ], $schema[ $subtree ] );

			if ( empty( $result ) ) {
				unset( $output[ $subtree ] );
			} else {
				$output[ $subtree ] = $result;
			}
		}

		return $output;
	}

	/**
	 * Given a tree, removes the keys that are not present in the schema. (Duplicated from \WP_Theme_JSON_Gutenberg::remove_keys_not_in_schema)
	 *
	 * It is recursive and modifies the input in-place.
	 *
	 * @param array $tree Input to process.
	 * @param array $schema Schema to adhere to.
	 *
	 * @return array Returns the modified $tree.
	 */
	private function remove_keys_not_in_schema( $tree, $schema ) {
		$tree = array_intersect_key( $tree, $schema );

		foreach ( $schema as $key => $data ) {
			if ( ! isset( $tree[ $key ] ) ) {
				continue;
			}

			if ( is_array( $schema[ $key ] ) && is_array( $tree[ $key ] ) ) {
				$tree[ $key ] = $this->remove_keys_not_in_schema( $tree[ $key ], $schema[ $key ] );

				if ( empty( $tree[ $key ] ) ) {
					unset( $tree[ $key ] );
				}
			} elseif ( is_array( $schema[ $key ] ) && ! is_array( $tree[ $key ] ) ) {
				unset( $tree[ $key ] );
			}
		}

		return $tree;
	}

	/**
	 * Returns the metadata for each block. (Duplicated from \WP_Theme_JSON_Gutenberg::get_blocks_metadata)
	 *
	 * Example:
	 *
	 * {
	 *   'core/paragraph': {
	 *     'selector': 'p'
	 *   },
	 *   'core/heading': {
	 *     'selector': 'h1'
	 *   },
	 *   'core/group': {
	 *     'selector': '.wp-block-group'
	 *   },
	 *   'core/cover': {
	 *     'selector': '.wp-block-cover',
	 *     'duotone': '> .wp-block-cover__image-background, > .wp-block-cover__video-background'
	 *   }
	 * }
	 *
	 * @return array Block metadata.
	 */
	private function get_blocks_metadata( $elements ) {
		if ( null !== self::$blocks_metadata ) {
			return self::$blocks_metadata;
		}

		self::$blocks_metadata = array();

		$registry = \WP_Block_Type_Registry::get_instance();
		$blocks   = $registry->get_all_registered();
		foreach ( $blocks as $block_name => $block_type ) {
			if (
				isset( $block_type->supports['__experimentalSelector'] ) &&
				is_string( $block_type->supports['__experimentalSelector'] )
			) {
				self::$blocks_metadata[ $block_name ]['selector'] = $block_type->supports['__experimentalSelector'];
			} else {
				self::$blocks_metadata[ $block_name ]['selector'] = '.wp-block-' . str_replace( '/', '-', str_replace( 'core/', '', $block_name ) );
			}

			if (
				isset( $block_type->supports['color']['__experimentalDuotone'] ) &&
				is_string( $block_type->supports['color']['__experimentalDuotone'] )
			) {
				self::$blocks_metadata[ $block_name ]['duotone'] = $block_type->supports['color']['__experimentalDuotone'];
			}

			// Assign defaults, then overwrite those that the block sets by itself.
			// If the block selector is compounded, will append the element to each
			// individual block selector.
			$block_selectors = explode( ',', self::$blocks_metadata[ $block_name ]['selector'] );
			foreach ( $elements as $el_name => $el_selector ) {
				$element_selector = array();
				foreach ( $block_selectors as $selector ) {
					$element_selector[] = $selector . ' ' . $el_selector;
				}
				self::$blocks_metadata[ $block_name ]['elements'][ $el_name ] = implode( ',', $element_selector );
			}
		}

		return self::$blocks_metadata;
	}

}
