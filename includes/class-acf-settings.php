<?php
defined( 'ABSPATH' ) || exit;

class ACF_Settings {

    const OPTION_KEY = 'acf_settings';

    const PROVIDERS = [ 'claude', 'openai', 'ollama' ];

    const DEFAULTS = [
        'default_provider'  => 'claude',
        'claude_api_key'    => '',
        'claude_model'      => '',
        'openai_api_key'    => '',
        'openai_model'      => '',
        'ollama_url'        => 'http://localhost:11434',
        'ollama_model'      => '',
        'max_tokens'        => 1500,
        'temperature'       => 0.7,
    ];

    private static array $cache = [];

    public static function init(): void {
        register_setting( 'acf_settings_group', self::OPTION_KEY, [
            'sanitize_callback' => [ self::class, 'sanitize' ],
        ] );
    }

    public static function get( string $key, $fallback = null ) {
        if ( empty( self::$cache ) ) {
            self::$cache = wp_parse_args(
                get_option( self::OPTION_KEY, [] ),
                self::DEFAULTS
            );
        }
        return self::$cache[ $key ] ?? $fallback;
    }

    public static function all(): array {
        if ( empty( self::$cache ) ) {
            self::$cache = wp_parse_args(
                get_option( self::OPTION_KEY, [] ),
                self::DEFAULTS
            );
        }
        return self::$cache;
    }

    public static function sanitize( array $input ): array {
        $clean = self::DEFAULTS;

        if ( isset( $input['default_provider'] ) && in_array( $input['default_provider'], self::PROVIDERS, true ) ) {
            $clean['default_provider'] = $input['default_provider'];
        }
        $clean['claude_api_key']  = sanitize_text_field( $input['claude_api_key'] ?? '' );
        $clean['claude_model']    = '' === $clean['claude_api_key']
            ? ''
            : sanitize_text_field( $input['claude_model'] ?? '' );
        $clean['openai_api_key']  = sanitize_text_field( $input['openai_api_key'] ?? '' );
        $clean['openai_model']    = '' === $clean['openai_api_key']
            ? ''
            : sanitize_text_field( $input['openai_model'] ?? '' );
        $clean['ollama_url']      = esc_url_raw( $input['ollama_url'] ?? 'http://localhost:11434' );
        $clean['ollama_model']    = '' === $clean['ollama_url']
            ? ''
            : sanitize_text_field( $input['ollama_model'] ?? '' );
        $clean['max_tokens']      = absint( $input['max_tokens'] ?? 1500 );
        $clean['temperature']     = min( 2.0, max( 0.0, (float) ( $input['temperature'] ?? 0.7 ) ) );

        self::$cache = [];  // bust cache on save
        return $clean;
    }

    /**
     * Return only non-sensitive settings for JS (no API keys).
     */
    public static function for_js(): array {
        $s = self::all();
        return [
            'default_provider' => $s['default_provider'],
            'claude_model'     => $s['claude_model'],
            'openai_model'     => $s['openai_model'],
            'ollama_url'       => $s['ollama_url'],
            'ollama_model'     => $s['ollama_model'],
            'providers'        => self::PROVIDERS,
        ];
    }
}
