<?php
defined( 'ABSPATH' ) || exit;

class ACF_Rest_API {

    const REST_NAMESPACE = 'ai-content-forge/v1';

    public static function init(): void {
        add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
    }

    public static function register_routes(): void {
        // Generate content
        register_rest_route( self::REST_NAMESPACE, '/generate', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ self::class, 'handle_generate' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'type'     => [
                    'required'          => true,
                    'validate_callback' => fn( $v ) => in_array( $v, ACF_Generator::TYPES, true ),
                ],
                'provider' => [
                    'default'           => '',
                    'validate_callback' => fn( $v ) => $v === '' || in_array( $v, ACF_Settings::PROVIDERS, true ),
                ],
                'title'             => [ 'default' => '' ],
                'keywords'          => [ 'default' => '' ],
                'tone'              => [ 'default' => 'professional' ],
                'existing_content'  => [ 'default' => '' ],
                'post_type'         => [ 'default' => 'post' ],
                'language'          => [ 'default' => 'English' ],
            ],
        ] );

        // Test provider connection
        register_rest_route( self::REST_NAMESPACE, '/test-provider', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ self::class, 'handle_test_provider' ],
            'permission_callback' => [ self::class, 'check_permission' ],
            'args'                => [
                'provider' => [
                    'required'          => true,
                    'validate_callback' => fn( $v ) => in_array( $v, ACF_Settings::PROVIDERS, true ),
                ],
            ],
        ] );

        // Sync provider connection state + model list from unsaved admin form inputs
        register_rest_route( self::REST_NAMESPACE, '/sync-provider', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ self::class, 'handle_sync_provider' ],
            'permission_callback' => [ self::class, 'check_manage_options_permission' ],
            'args'                => [
                'provider' => [
                    'required'          => true,
                    'validate_callback' => fn( $v ) => in_array( $v, [ 'claude', 'openai' ], true ),
                ],
                'api_key' => [
                    'required' => true,
                ],
                'current_model' => [
                    'default' => '',
                ],
            ],
        ] );

        // Get available providers
        register_rest_route( self::REST_NAMESPACE, '/providers', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ self::class, 'handle_providers' ],
            'permission_callback' => [ self::class, 'check_permission' ],
        ] );
    }

    public static function check_permission(): bool {
        return current_user_can( 'edit_posts' );
    }

    public static function check_manage_options_permission(): bool {
        return current_user_can( 'manage_options' );
    }

    public static function handle_generate( WP_REST_Request $request ): WP_REST_Response {
        try {
            $context = [
                'title'            => $request->get_param( 'title' ),
                'keywords'         => $request->get_param( 'keywords' ),
                'tone'             => $request->get_param( 'tone' ),
                'existing_content' => $request->get_param( 'existing_content' ),
                'post_type'        => $request->get_param( 'post_type' ),
                'language'         => $request->get_param( 'language' ),
            ];

            $result = ACF_Generator::generate(
                $request->get_param( 'type' ),
                $context,
                $request->get_param( 'provider' )
            );

            return new WP_REST_Response( [ 'success' => true, 'result' => $result ], 200 );

        } catch ( \Throwable $e ) {
            return new WP_REST_Response(
                [ 'success' => false, 'message' => $e->getMessage() ],
                500
            );
        }
    }

    public static function handle_test_provider( WP_REST_Request $request ): WP_REST_Response {
        $slug = $request->get_param( 'provider' );
        try {
            $provider = ACF_Generator::get_provider( $slug );
            if ( ! $provider->is_configured() ) {
                return new WP_REST_Response(
                    [ 'success' => false, 'message' => 'Provider not configured — check API key / URL.' ],
                    400
                );
            }
            try {
                $provider->discover_models();
            } catch ( RuntimeException $e ) {
                $provider->generate( 'Reply with exactly: OK', 10, 0.0 );
            }

            return new WP_REST_Response( [ 'success' => true, 'message' => 'Connection successful.' ], 200 );
        } catch ( \Throwable $e ) {
            return new WP_REST_Response( [ 'success' => false, 'message' => $e->getMessage() ], 500 );
        }
    }

    public static function handle_sync_provider( WP_REST_Request $request ): WP_REST_Response {
        $slug          = (string) $request->get_param( 'provider' );
        $api_key       = trim( (string) $request->get_param( 'api_key' ) );
        $current_model = sanitize_text_field( (string) $request->get_param( 'current_model' ) );

        if ( '' === $api_key ) {
            return new WP_REST_Response(
                [ 'success' => false, 'message' => 'API key is required.' ],
                400
            );
        }

        try {
            $provider = ACF_Generator::get_provider( $slug );
            $models   = $provider->discover_models(
                [
                    $slug . '_api_key' => $api_key,
                ]
            );

            $model_ids = array_column( $models, 'id' );
            $selected  = in_array( $current_model, $model_ids, true )
                ? $current_model
                : ( $model_ids[0] ?? '' );

            return new WP_REST_Response(
                [
                    'success'        => true,
                    'connected'      => true,
                    'message'        => 'Connected',
                    'models'         => $models,
                    'selected_model' => $selected,
                ],
                200
            );
        } catch ( \Throwable $e ) {
            return new WP_REST_Response(
                [ 'success' => false, 'connected' => false, 'message' => $e->getMessage() ],
                500
            );
        }
    }

    public static function handle_providers(): WP_REST_Response {
        $list = [];
        foreach ( ACF_Settings::PROVIDERS as $slug ) {
            $p      = ACF_Generator::get_provider( $slug );
            $list[] = [
                'id'            => $slug,
                'label'         => $p->label(),
                'is_configured' => $p->is_configured(),
                'is_default'    => ( $slug === ACF_Settings::get( 'default_provider' ) ),
            ];
        }
        return new WP_REST_Response( $list, 200 );
    }
}
