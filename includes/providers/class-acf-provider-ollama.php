<?php
defined( 'ABSPATH' ) || exit;

class ACF_Provider_Ollama extends ACF_Provider {

    const DEFAULT_BASE_URL = 'http://localhost:11434';
    const DOCKER_HOST_ALIAS = 'host.docker.internal';
    const DOCKER_PROXY_PORT = 11435;

    public function id(): string    { return 'ollama'; }
    public function label(): string { return 'Ollama (Local)'; }

    public function is_configured( array $config = [] ): bool {
        return '' !== trim( (string) $this->resolve_setting( 'ollama_url', '', $config ) );
    }

    public function discover_models( array $config = [] ): array {
        $data = $this->request( 'GET', '/api/tags', [], $config );

        $models = array_filter(
            array_map(
                static function ( array $item ): ?array {
                    $id = trim( (string) ( $item['model'] ?? $item['name'] ?? '' ) );

                    if ( '' === $id ) {
                        return null;
                    }

                    return [
                        'id'    => $id,
                        'label' => (string) ( $item['name'] ?? $id ),
                    ];
                },
                $data['models'] ?? []
            )
        );

        usort(
            $models,
            static function ( array $a, array $b ): int {
                return strcasecmp( $a['id'], $b['id'] );
            }
        );

        if ( empty( $models ) ) {
            throw new RuntimeException( 'No Ollama models were returned by this server.' );
        }

        return array_values( $models );
    }

    public function generate( string $prompt, int $max_tokens, float $temperature ): string {
        if ( ! $this->is_configured() ) {
            throw new RuntimeException( 'Ollama URL is not configured.' );
        }

        $data = $this->request(
            'POST',
            '/api/chat',
            [
                'model'   => $this->resolve_model(),
                'stream'  => false,
                'options' => [
                    'temperature' => $temperature,
                    'num_predict' => $max_tokens,
                ],
                'messages' => [
                    [ 'role' => 'user', 'content' => $prompt ],
                ],
            ]
        );

        return $data['message']['content'] ?? '';
    }

    private function resolve_model(): string {
        $model = trim( (string) ACF_Settings::get( 'ollama_model', '' ) );

        if ( '' !== $model ) {
            return $model;
        }

        $models = $this->discover_models();
        $model  = (string) ( $models[0]['id'] ?? '' );

        if ( '' === $model ) {
            throw new RuntimeException( 'No Ollama model is selected.' );
        }

        return $model;
    }

    private function request( string $method, string $path, array $body = [], array $config = [] ): array {
        $base_urls = $this->get_runtime_base_urls( $config );

        if ( empty( $base_urls ) ) {
            throw new RuntimeException( 'Ollama URL is not configured.' );
        }

        $last_error = null;

        foreach ( $base_urls as $base_url ) {
            $url = $base_url . $path;

            try {
                if ( 'GET' === $method ) {
                    return $this->http_get( $url, [] );
                }

                return $this->http_post(
                    $url,
                    $body,
                    [
                        'Content-Type' => 'application/json',
                    ]
                );
            } catch ( RuntimeException $e ) {
                $last_error = $e;
            }
        }

        if ( null !== $last_error ) {
            throw $last_error;
        }

        throw new RuntimeException( 'Unable to reach the Ollama server.' );
    }

    private function get_runtime_base_urls( array $config = [] ): array {
        $base_url = $this->normalize_base_url( (string) $this->resolve_setting( 'ollama_url', self::DEFAULT_BASE_URL, $config ) );

        if ( '' === $base_url ) {
            return [];
        }

        $candidates = [ $base_url ];

        if ( ! $this->should_try_docker_local_fallbacks( $base_url ) ) {
            return array_values( array_unique( $candidates ) );
        }

        $parts = wp_parse_url( $base_url );

        if ( ! is_array( $parts ) ) {
            return array_values( array_unique( $candidates ) );
        }

        $port  = isset( $parts['port'] ) ? (int) $parts['port'] : 11434;
        $proxy = (int) ( getenv( 'ACF_OLLAMA_DOCKER_PROXY_PORT' ) ?: getenv( 'OLLAMA_PROXY_PORT' ) ?: self::DOCKER_PROXY_PORT );

        $candidates[] = $this->build_url_from_parts( $parts, self::DOCKER_HOST_ALIAS, $port );

        if ( $proxy > 0 && $proxy !== $port ) {
            $candidates[] = $this->build_url_from_parts( $parts, self::DOCKER_HOST_ALIAS, $proxy );
        }

        return array_values( array_unique( array_filter( $candidates ) ) );
    }

    private function should_try_docker_local_fallbacks( string $base_url ): bool {
        return $this->is_running_in_docker() && $this->is_loopback_host( $base_url );
    }

    private function is_running_in_docker(): bool {
        return file_exists( '/.dockerenv' );
    }

    private function is_loopback_host( string $base_url ): bool {
        $host = strtolower( (string) wp_parse_url( $base_url, PHP_URL_HOST ) );

        return in_array( $host, [ 'localhost', '127.0.0.1', '::1', '[::1]' ], true );
    }

    private function build_url_from_parts( array $parts, string $host, int $port ): string {
        $scheme = (string) ( $parts['scheme'] ?? 'http' );
        $path   = isset( $parts['path'] ) ? rtrim( (string) $parts['path'], '/' ) : '';

        return sprintf( '%s://%s:%d%s', $scheme, $host, $port, $path );
    }

    private function normalize_base_url( string $base_url ): string {
        return rtrim( trim( $base_url ), '/' );
    }
}
