<?php
defined( 'ABSPATH' ) || exit;

class ACF_Provider_Claude extends ACF_Provider {

    const API_URL = 'https://api.anthropic.com/v1/messages';
    const API_VERSION = '2023-06-01';

    public function id(): string    { return 'claude'; }
    public function label(): string { return 'Anthropic Claude'; }

    public function is_configured(): bool {
        return ! empty( ACF_Settings::get( 'claude_api_key' ) );
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
