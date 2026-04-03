<?php
defined( 'ABSPATH' ) || exit;

class ACF_Provider_Claude extends ACF_Provider {

    const API_URL = 'https://api.anthropic.com/v1/messages';
    const MODELS_API_URL = 'https://api.anthropic.com/v1/models';
    const API_VERSION = '2023-06-01';

    public function id(): string    { return 'claude'; }
    public function label(): string { return 'Anthropic Claude'; }

    public function is_configured( array $config = [] ): bool {
        return '' !== trim( (string) $this->resolve_setting( 'claude_api_key', '', $config ) );
    }

    public function discover_models( array $config = [] ): array {
        $api_key = trim( (string) $this->resolve_setting( 'claude_api_key', '', $config ) );

        if ( '' === $api_key ) {
            throw new RuntimeException( 'Claude API key is not set.' );
        }

        $data = $this->http_get(
            self::MODELS_API_URL,
            [
                'x-api-key'         => $api_key,
                'anthropic-version' => self::API_VERSION,
            ]
        );

        $models = array_filter(
            array_map(
                static function ( array $item ): ?array {
                    $id = (string) ( $item['id'] ?? '' );

                    if ( '' === $id ) {
                        return null;
                    }

                    return [
                        'id'    => $id,
                        'label' => (string) ( $item['display_name'] ?? $id ),
                    ];
                },
                $data['data'] ?? []
            )
        );

        if ( empty( $models ) ) {
            throw new RuntimeException( 'No Claude models were returned for this API key.' );
        }

        return array_values( $models );
    }

    public function generate( string $prompt, int $max_tokens, float $temperature ): string {
        if ( ! $this->is_configured() ) {
            throw new RuntimeException( 'Claude API key is not set.' );
        }

        $data = $this->http_post(
            self::API_URL,
            [
                'model'       => ACF_Settings::get( 'claude_model', 'claude-sonnet-4-20250514' ),
                'max_tokens'  => $max_tokens,
                'temperature' => $temperature,
                'messages'    => [
                    [ 'role' => 'user', 'content' => $prompt ],
                ],
            ],
            [
                'x-api-key'         => ACF_Settings::get( 'claude_api_key' ),
                'anthropic-version' => self::API_VERSION,
                'content-type'      => 'application/json',
            ]
        );

        return $data['content'][0]['text'] ?? '';
    }
}
