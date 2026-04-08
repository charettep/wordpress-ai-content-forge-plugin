<?php
defined( 'ABSPATH' ) || exit;

class ACF_Gutenberg {

    const META_DESCRIPTION_KEY = '_acf_meta_description';

    public static function init(): void {
        add_action( 'init', [ self::class, 'register_editor_meta' ], 20 );
        add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue_assets' ] );
    }

    public static function register_editor_meta(): void {
        $post_types = get_post_types( [ 'show_in_rest' => true ], 'names' );

        foreach ( $post_types as $post_type ) {
            if ( ! post_type_supports( $post_type, 'editor' ) || ! post_type_supports( $post_type, 'custom-fields' ) ) {
                continue;
            }

            register_post_meta(
                $post_type,
                self::META_DESCRIPTION_KEY,
                [
                    'type'              => 'string',
                    'single'            => true,
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                    'show_in_rest'      => true,
                    'auth_callback'     => [ self::class, 'can_edit_meta' ],
                ]
            );
        }
    }

    public static function can_edit_meta( $_allowed, string $_meta_key, int $post_id ): bool {
        return current_user_can( 'edit_post', $post_id );
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
            'restUrl'       => rest_url( ACF_Rest_API::REST_NAMESPACE ),
            'nonce'         => wp_create_nonce( 'wp_rest' ),
            'settings'      => ACF_Settings::for_js(),
            'promptTemplates' => array_map(
                static fn( string $type ): string => ACF_Settings::get_prompt_template( $type ),
                array_combine( ACF_Generator::TYPES, ACF_Generator::TYPES )
            ),
            'metaKeys'      => [
                'metaDescription' => self::META_DESCRIPTION_KEY,
            ],
            'types'         => ACF_Generator::TYPES,
            'typeLabels'    => [
                'post_content'     => __( 'Post Content',      'ai-content-forge' ),
                'seo_title'        => __( 'SEO Title',         'ai-content-forge' ),
                'meta_description' => __( 'Meta Description',  'ai-content-forge' ),
                'excerpt'          => __( 'Excerpt',           'ai-content-forge' ),
            ],
            'assetUrls'     => [
                'pluginIcon'    => ACF_PLUGIN_URL . 'images/plugin-icon.png',
                'providerIcons' => [
                    'claude' => ACF_PLUGIN_URL . 'images/claude-ai-icon.png',
                    'openai' => ACF_PLUGIN_URL . 'images/openai-icon.png',
                    'ollama' => ACF_PLUGIN_URL . 'images/ollama-icon.png',
                ],
            ],
        ] );
    }
}
