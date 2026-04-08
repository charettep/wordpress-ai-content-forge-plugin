<?php
defined( 'ABSPATH' ) || exit;

class AIG_Gutenberg {

    const META_DESCRIPTION_KEY = '_aig_meta_description';

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
        $script_path     = AIG_PLUGIN_DIR . $script_relative;
        $asset_path      = AIG_PLUGIN_DIR . $asset_relative;

        if ( ! file_exists( $script_path ) || ! file_exists( $asset_path ) ) {
            return;
        }

        $asset = require $asset_path;

        wp_enqueue_script(
            'aig-gutenberg',
            AIG_PLUGIN_URL . $script_relative,
            $asset['dependencies'] ?? [],
            $asset['version'] ?? AIG_VERSION,
            true
        );

        foreach ( [ 'gutenberg/build/style-index.css', 'gutenberg/build/index.css', 'gutenberg/build/style.css' ] as $style_relative ) {
            if ( ! file_exists( AIG_PLUGIN_DIR . $style_relative ) ) {
                continue;
            }

            wp_enqueue_style(
                'aig-gutenberg',
                AIG_PLUGIN_URL . $style_relative,
                [ 'wp-components' ],
                $asset['version'] ?? AIG_VERSION
            );
            break;
        }

        wp_localize_script( 'aig-gutenberg', 'aigGutenberg', [
            'restNamespace' => AIG_Rest_API::REST_NAMESPACE,
            'restUrl'       => rest_url( AIG_Rest_API::REST_NAMESPACE ),
            'nonce'         => wp_create_nonce( 'wp_rest' ),
            'settings'      => AIG_Settings::for_js(),
            'promptTemplates' => array_map(
                static fn( string $type ): string => AIG_Settings::get_prompt_template( $type ),
                array_combine( AIG_Generator::TYPES, AIG_Generator::TYPES )
            ),
            'metaKeys'      => [
                'metaDescription' => self::META_DESCRIPTION_KEY,
            ],
            'types'         => AIG_Generator::TYPES,
            'typeLabels'    => [
                'post_content'     => __( 'Post Content',      'ai-genie' ),
                'seo_title'        => __( 'SEO Title',         'ai-genie' ),
                'meta_description' => __( 'Meta Description',  'ai-genie' ),
                'excerpt'          => __( 'Excerpt',           'ai-genie' ),
            ],
            'assetUrls'     => [
                'pluginIcon'    => AIG_PLUGIN_URL . 'images/plugin-icon.png',
                'providerIcons' => [
                    'claude' => AIG_PLUGIN_URL . 'images/claude-ai-icon.png',
                    'openai' => AIG_PLUGIN_URL . 'images/openai-icon.png',
                    'ollama' => AIG_PLUGIN_URL . 'images/ollama-icon.png',
                ],
            ],
        ] );
    }
}
