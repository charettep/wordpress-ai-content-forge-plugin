<?php
defined( 'ABSPATH' ) || exit;

class ACF_Gutenberg {

    public static function init(): void {
        add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue_assets' ] );
    }

    public static function enqueue_assets(): void {
        $script_relative = 'gutenberg/build/index.js';
        $asset_relative  = 'gutenberg/build/index.asset.php';
        $script_path     = ACF_PLUGIN_DIR . $script_relative;
        $asset_path      = ACF_PLUGIN_DIR . $asset_relative;

        if ( ! file_exists( $script_path ) || ! file_exists( $asset_path ) ) {
            return;
        }

        $asset = require $asset_path;

        wp_enqueue_script(
            'acf-gutenberg',
            ACF_PLUGIN_URL . $script_relative,
            $asset['dependencies'] ?? [],
            $asset['version'] ?? ACF_VERSION,
            true
        );

        foreach ( [ 'gutenberg/build/style-index.css', 'gutenberg/build/index.css', 'gutenberg/build/style.css' ] as $style_relative ) {
            if ( ! file_exists( ACF_PLUGIN_DIR . $style_relative ) ) {
                continue;
            }

            wp_enqueue_style(
                'acf-gutenberg',
                ACF_PLUGIN_URL . $style_relative,
                [ 'wp-components' ],
                $asset['version'] ?? ACF_VERSION
            );
            break;
        }

        wp_localize_script( 'acf-gutenberg', 'acfGutenberg', [
            'restNamespace' => ACF_Rest_API::REST_NAMESPACE,
            'nonce'         => wp_create_nonce( 'wp_rest' ),
            'settings'      => ACF_Settings::for_js(),
            'types'         => ACF_Generator::TYPES,
            'typeLabels'    => [
                'post_content'     => __( 'Post Content',      'ai-content-forge' ),
                'seo_title'        => __( 'SEO Title',         'ai-content-forge' ),
                'meta_description' => __( 'Meta Description',  'ai-content-forge' ),
                'excerpt'          => __( 'Excerpt',           'ai-content-forge' ),
            ],
        ] );
    }
}
