<?php
defined( 'ABSPATH' ) || exit;

abstract class ACF_Provider {

    /**
     * @param string $prompt     Full prompt string
     * @param int    $max_tokens
     * @param float  $temperature
     * @return string            Generated text
     * @throws RuntimeException  on API error
     */
    abstract public function generate( string $prompt, int $max_tokens, float $temperature ): string;

    /**
     * Provider identifier slug (claude / openai / ollama).
     */
    abstract public function id(): string;

    /**
     * Human-readable label.
     */
    abstract public function label(): string;

    /**
     * Whether the provider appears correctly configured.
     */
    abstract public function is_configured(): bool;

    /**
     * Shared wp_remote_post helper with error normalisation.
     */
    protected function http_post( string $url, array $body, array $headers ): array {
        $response = wp_remote_post( $url, [
            'headers'     => $headers,
            'body'        => wp_json_encode( $body ),
            'timeout'     => 60,
            'data_format' => 'body',
        ] );

        if ( is_wp_error( $response ) ) {
            throw new RuntimeException( 'HTTP error: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );
        $data = json_decode( $raw, true );

        if ( $code >= 400 ) {
            $msg = $data['error']['message'] ?? $data['error'] ?? "HTTP $code";
            throw new RuntimeException( $msg );
        }

        return $data;
    }
}
