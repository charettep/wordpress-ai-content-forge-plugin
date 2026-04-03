<?php
defined( 'ABSPATH' ) || exit;

class ACF_Provider_OpenAI extends ACF_Provider {

    const CHAT_API_URL = 'https://api.openai.com/v1/chat/completions';
    const RESPONSES_API_URL = 'https://api.openai.com/v1/responses';
    const MODELS_API_URL = 'https://api.openai.com/v1/models';

    public function id(): string    { return 'openai'; }
    public function label(): string { return 'OpenAI'; }

    public function is_configured( array $config = [] ): bool {
        return '' !== trim( (string) $this->resolve_setting( 'openai_api_key', '', $config ) );
    }

    public function discover_models( array $config = [] ): array {
        $api_key = trim( (string) $this->resolve_setting( 'openai_api_key', '', $config ) );

        if ( '' === $api_key ) {
            throw new RuntimeException( 'OpenAI API key is not set.' );
        }

        $data = $this->http_get(
            self::MODELS_API_URL,
            [
                'Authorization' => 'Bearer ' . $api_key,
            ]
        );

        $models = array_filter(
            array_map(
                static function ( array $item ): ?array {
                    $id = (string) ( $item['id'] ?? '' );

                    if ( '' === $id || ! self::is_supported_text_model( $id ) ) {
                        return null;
                    }

                    return [
                        'id'    => $id,
                        'label' => $id,
                    ];
                },
                $data['data'] ?? []
            )
        );

        usort(
            $models,
            static function ( array $a, array $b ): int {
                return strcasecmp( $a['id'], $b['id'] );
            }
        );

        if ( empty( $models ) ) {
            throw new RuntimeException( 'No supported text-generation models were returned for this API key.' );
        }

        return array_values( $models );
    }

    public function generate( string $prompt, int $max_tokens, float $temperature ): string {
        if ( ! $this->is_configured() ) {
            throw new RuntimeException( 'OpenAI API key is not set.' );
        }

        $model   = $this->resolve_model();
        $api_key = (string) ACF_Settings::get( 'openai_api_key' );

        if ( self::should_use_responses_api( $model ) ) {
            $body = [
                'model'             => $model,
                'input'             => $prompt,
                'max_output_tokens' => $max_tokens,
            ];

            if ( self::supports_temperature( $model ) ) {
                $body['temperature'] = $temperature;
            }

            $reasoning = self::build_reasoning_config( $model );
            if ( ! empty( $reasoning ) ) {
                $body['reasoning'] = $reasoning;
            }

            $headers = [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ];

            return $this->generate_responses_text( $body, $headers, $model );
        }

        $body = [
            'model'       => $model,
            'messages'    => [
                [ 'role' => 'user', 'content' => $prompt ],
            ],
        ];

        if ( self::supports_temperature( $model ) ) {
            $body['temperature'] = $temperature;
        }

        if ( self::should_use_max_completion_tokens( $model ) ) {
            $body['max_completion_tokens'] = $max_tokens;
        } else {
            $body['max_tokens'] = $max_tokens;
        }

        $data = $this->post_with_parameter_fallback(
            self::CHAT_API_URL,
            $body,
            [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'temperature'
        );

        return $data['choices'][0]['message']['content'] ?? '';
    }

    private function extract_responses_text( array $data ): string {
        if ( ! empty( $data['output_text'] ) ) {
            return trim( (string) $data['output_text'] );
        }

        $chunks = [];

        foreach ( $data['output'] ?? [] as $output ) {
            foreach ( $output['content'] ?? [] as $content ) {
                if ( 'output_text' === ( $content['type'] ?? '' ) && ! empty( $content['text'] ) ) {
                    $chunks[] = $content['text'];
                }
            }
        }

        return trim( implode( "\n\n", $chunks ) );
    }

    private function generate_responses_text( array $body, array $headers, string $model ): string {
        $data = $this->post_with_parameter_fallback(
            self::RESPONSES_API_URL,
            $body,
            $headers,
            'temperature'
        );

        $text = $this->extract_responses_text( $data );

        if ( '' !== $text ) {
            return $text;
        }

        if ( self::should_retry_empty_responses_output( $data, $model ) ) {
            $retry_body = $body;
            $retry_body['max_output_tokens'] = max(
                (int) ( $body['max_output_tokens'] ?? 0 ),
                self::minimum_responses_retry_budget( $model )
            );

            if ( $retry_body['max_output_tokens'] !== (int) ( $body['max_output_tokens'] ?? 0 ) ) {
                $retry_data = $this->post_with_parameter_fallback(
                    self::RESPONSES_API_URL,
                    $retry_body,
                    $headers,
                    'temperature'
                );

                $retry_text = $this->extract_responses_text( $retry_data );

                if ( '' !== $retry_text ) {
                    return $retry_text;
                }

                $data = $retry_data;
            }
        }

        throw new RuntimeException( self::build_empty_responses_error( $data ) );
    }

    private static function build_reasoning_config( string $model ): array {
        if ( self::is_gpt5_pro( $model ) ) {
            return [ 'effort' => 'high' ];
        }

        if ( self::is_gpt5_family( $model ) ) {
            return [ 'effort' => 'low' ];
        }

        return [];
    }

    private static function should_use_responses_api( string $model ): bool {
        return preg_match( '/^(gpt-5|gpt-4\.1|gpt-4o|o1|o3|o4|chatgpt-4o)/i', $model ) === 1;
    }

    private static function should_use_max_completion_tokens( string $model ): bool {
        return preg_match( '/^(gpt-5|o1|o3|o4)/i', $model ) === 1;
    }

    private static function supports_temperature( string $model ): bool {
        return preg_match( '/^(gpt-5|o1|o3|o4)/i', $model ) !== 1;
    }

    private static function is_gpt5_family( string $model ): bool {
        return preg_match( '/^gpt-5(?!-pro)/i', $model ) === 1;
    }

    private static function is_gpt5_pro( string $model ): bool {
        return preg_match( '/^gpt-5(?:\.\d+)?-pro/i', $model ) === 1;
    }

    private static function is_supported_text_model( string $model ): bool {
        if ( preg_match( '/(audio|image|tts|transcribe|embedding|search|moderation|realtime)/i', $model ) ) {
            return false;
        }

        return preg_match( '/^(gpt-|o1|o3|o4|chatgpt-)/i', $model ) === 1;
    }

    private function resolve_model(): string {
        $model = trim( (string) ACF_Settings::get( 'openai_model', '' ) );

        if ( '' !== $model ) {
            return $model;
        }

        $models = $this->discover_models();
        $model  = (string) ( $models[0]['id'] ?? '' );

        if ( '' === $model ) {
            throw new RuntimeException( 'No OpenAI model is selected.' );
        }

        return $model;
    }

    private static function should_retry_empty_responses_output( array $data, string $model ): bool {
        return self::minimum_responses_retry_budget( $model ) > 0
            && 'incomplete' === (string) ( $data['status'] ?? '' )
            && 'max_output_tokens' === (string) ( $data['incomplete_details']['reason'] ?? '' );
    }

    private static function minimum_responses_retry_budget( string $model ): int {
        if ( self::is_gpt5_family( $model ) || self::is_gpt5_pro( $model ) ) {
            return 2048;
        }

        return 0;
    }

    private static function build_empty_responses_error( array $data ): string {
        if ( 'incomplete' === (string) ( $data['status'] ?? '' ) && 'max_output_tokens' === (string) ( $data['incomplete_details']['reason'] ?? '' ) ) {
            return 'OpenAI did not return visible text before hitting max_output_tokens. Increase Max Tokens and try again.';
        }

        return 'OpenAI returned an empty response.';
    }

    private function post_with_parameter_fallback( string $url, array $body, array $headers, string $parameter ): array {
        try {
            return $this->http_post( $url, $body, $headers );
        } catch ( RuntimeException $e ) {
            if ( ! isset( $body[ $parameter ] ) ) {
                throw $e;
            }

            if ( ! self::is_unsupported_parameter_error( $e->getMessage(), $parameter ) ) {
                throw $e;
            }

            unset( $body[ $parameter ] );

            return $this->http_post( $url, $body, $headers );
        }
    }

    private static function is_unsupported_parameter_error( string $message, string $parameter ): bool {
        return preg_match( "/unsupported parameter:\\s*'?" . preg_quote( $parameter, '/' ) . "'?/i", $message ) === 1;
    }
}
