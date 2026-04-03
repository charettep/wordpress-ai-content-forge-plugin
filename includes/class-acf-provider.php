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
    abstract public function is_configured( array $config = [] ): bool;

    /**
     * Return provider-exposed model options for the supplied runtime config.
     *
     * @return array<int,array{id:string,label:string}>
     */
    public function discover_models( array $config = [] ): array {
        throw new RuntimeException( 'Model discovery is not supported for this provider.' );
    }

    protected function resolve_setting( string $key, $fallback = null, array $config = [] ) {
        return $config[ $key ] ?? ACF_Settings::get( $key, $fallback );
    }

    /**
     * Shared wp_remote_get helper with error normalisation.
     */
    protected function http_get( string $url, array $headers ): array {
        $response = wp_remote_get( $url, [
            'headers' => $headers,
            'timeout' => 180,
        ] );

        return $this->normalize_response( $response );
    }

    /**
     * Shared wp_remote_post helper with error normalisation.
     */
    protected function http_post( string $url, array $body, array $headers ): array {
        $response = wp_remote_post( $url, [
            'headers'     => $headers,
            'body'        => wp_json_encode( $body ),
            'timeout'     => 180,
            'data_format' => 'body',
        ] );

        return $this->normalize_response( $response );
    }

    /**
     * Normalize remote responses and bubble up provider error messages.
     */
    protected function normalize_response( $response ): array {
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
