<?php
defined( 'ABSPATH' ) || exit;

class ACF_Provider_OpenAI extends ACF_Provider {

    const API_URL = 'https://api.openai.com/v1/chat/completions';

    public function id(): string    { return 'openai'; }
    public function label(): string { return 'OpenAI'; }

    public function is_configured(): bool {
        return ! empty( ACF_Settings::get( 'openai_api_key' ) );
    }

    public function generate( string $prompt, int $max_tokens, float $temperature ): string {
        if ( ! $this->is_configured() ) {
            throw new RuntimeException( 'OpenAI API key is not set.' );
        }

        $data = $this->http_post(
            self::API_URL,
            [
                'model'       => ACF_Settings::get( 'openai_model', 'gpt-4o' ),
                'max_tokens'  => $max_tokens,
                'temperature' => $temperature,
                'messages'    => [
                    [ 'role' => 'user', 'content' => $prompt ],
                ],
            ],
            [
                'Authorization' => 'Bearer ' . ACF_Settings::get( 'openai_api_key' ),
                'Content-Type'  => 'application/json',
            ]
        );

        return $data['choices'][0]['message']['content'] ?? '';
    }
}
