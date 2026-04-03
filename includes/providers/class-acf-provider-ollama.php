<?php
defined( 'ABSPATH' ) || exit;

class ACF_Provider_Ollama extends ACF_Provider {

    public function id(): string    { return 'ollama'; }
    public function label(): string { return 'Ollama (Local)'; }

    public function is_configured( array $config = [] ): bool {
        return '' !== trim( (string) $this->resolve_setting( 'ollama_url', '', $config ) );
    }

    public function generate( string $prompt, int $max_tokens, float $temperature ): string {
        if ( ! $this->is_configured() ) {
            throw new RuntimeException( 'Ollama URL is not configured.' );
        }

        $base_url = rtrim( ACF_Settings::get( 'ollama_url' ), '/' );
        $url      = $base_url . '/api/chat';

        $data = $this->http_post(
            $url,
            [
                'model'  => ACF_Settings::get( 'ollama_model', 'llama3' ),
                'stream' => false,
                'options' => [
                    'temperature' => $temperature,
                    'num_predict' => $max_tokens,
                ],
                'messages' => [
                    [ 'role' => 'user', 'content' => $prompt ],
                ],
            ],
            [
                'Content-Type' => 'application/json',
            ]
        );

        return $data['message']['content'] ?? '';
    }
}
