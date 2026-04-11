<?php
defined( 'ABSPATH' ) || exit;

class AIG_Updater {

    private const GITHUB_OWNER = 'charettep';
    private const GITHUB_REPO  = 'wordpress-ai-genie-plugin';
    private const PLUGIN_SLUG  = 'ai-genie';
    private const CACHE_KEY    = 'aig_github_latest_release';
    private const CACHE_TTL    = HOUR_IN_SECONDS;
    private const ERROR_TTL    = 15 * MINUTE_IN_SECONDS;

    public static function init(): void {
        add_filter( 'site_transient_update_plugins', [ self::class, 'inject_update_data' ] );
        add_filter( 'pre_set_site_transient_update_plugins', [ self::class, 'inject_update_data' ] );
        add_filter( 'plugins_api', [ self::class, 'filter_plugin_info' ], 10, 3 );
        add_filter( 'update_plugins_github.com', [ self::class, 'handle_update_check' ], 10, 4 );

        // Manual "Check for updates" link on the plugins list page.
        add_filter( 'plugin_action_links_' . plugin_basename( AIG_PLUGIN_FILE ), [ self::class, 'add_action_links' ] );
        add_action( 'admin_action_aig-check-updates', [ self::class, 'handle_manual_check' ] );
    }

    /**
     * Append a "Check for updates" link to the plugin's action links row.
     *
     * @param string[] $links
     * @return string[]
     */
    public static function add_action_links( array $links ): array {
        $url     = wp_nonce_url( admin_url( 'plugins.php?action=aig-check-updates' ), 'aig-check-updates' );
        $links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for updates', 'ai-genie' ) . '</a>';
        return $links;
    }

    /**
     * Handle the manual update-check request triggered by the action link.
     * Clears both our release cache and WordPress's own update transient,
     * forces a fresh wp_update_plugins() call, then redirects back to
     * the plugins list so the UI reflects the latest state immediately.
     */
    public static function handle_manual_check(): void {
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_die( esc_html__( 'You do not have permission to update plugins.', 'ai-genie' ) );
        }

        check_admin_referer( 'aig-check-updates' );

        delete_site_transient( self::CACHE_KEY );
        delete_site_transient( self::CACHE_KEY . '_list' );
        delete_site_transient( 'update_plugins' );
        wp_update_plugins();

        wp_safe_redirect( admin_url( 'plugins.php' ) );
        exit;
    }

    /**
     * Handle the WordPress 5.8+ Update URI-based update check fired during wp_update_plugins().
     * This is the authoritative injection point for auto-updates.
     *
     * @param array|false      $update  The update data (false = no update found yet).
     * @param array            $plugin_data Plugin header data.
     * @param string           $plugin_file Plugin file path relative to plugins dir.
     * @param string[]         $locales Requested locales.
     * @return array|false
     */
    public static function handle_update_check( $update, array $plugin_data, string $plugin_file, array $locales ) {
        if ( plugin_basename( AIG_PLUGIN_FILE ) !== $plugin_file ) {
            return $update;
        }

        $release = self::get_latest_release();

        if ( ! $release || empty( $release['version'] ) ) {
            return $update;
        }

        if ( ! version_compare( $release['version'], AIG_VERSION, '>' ) ) {
            return $update;
        }

        return [
            'id'            => 'https://github.com/' . self::GITHUB_OWNER . '/' . self::GITHUB_REPO,
            'slug'          => self::PLUGIN_SLUG,
            'plugin'        => $plugin_file,
            'new_version'   => $release['version'],
            'url'           => 'https://github.com/' . self::GITHUB_OWNER . '/' . self::GITHUB_REPO,
            'package'       => $release['package_url'],
            'requires'      => '6.4',
            'requires_php'  => '8.1',
            'autoupdate'    => true,
        ];
    }

    /**
     * Inject GitHub release metadata into the native WordPress update response.
     *
     * @param mixed $transient
     * @return mixed
     */
    public static function inject_update_data( $transient ) {
        if ( ! is_object( $transient ) ) {
            return $transient;
        }

        $plugin_file = plugin_basename( AIG_PLUGIN_FILE );
        $release     = self::get_latest_release();

        if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
            $transient->response = [];
        }

        if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
            $transient->no_update = [];
        }

        if ( ! $release || empty( $release['version'] ) ) {
            return $transient;
        }

        $update = (object) [
            'id'           => 'https://github.com/' . self::GITHUB_OWNER . '/' . self::GITHUB_REPO,
            'slug'         => self::PLUGIN_SLUG,
            'plugin'       => $plugin_file,
            'new_version'  => $release['version'],
            'url'          => 'https://github.com/' . self::GITHUB_OWNER . '/' . self::GITHUB_REPO,
            'package'      => $release['package_url'],
            'requires'     => '6.4',
            'requires_php' => '8.1',
            'autoupdate'   => true,
        ];

        unset( $transient->response[ $plugin_file ], $transient->no_update[ $plugin_file ] );

        if ( version_compare( $release['version'], AIG_VERSION, '>' ) ) {
            $transient->response[ $plugin_file ] = $update;
        } else {
            $transient->no_update[ $plugin_file ] = $update;
        }

        return $transient;
    }

    /**
     * Supply the details modal data for the plugin information screen.
     *
     * @param mixed  $result
     * @param string $action
     * @param object $args
     * @return mixed
     */
    public static function filter_plugin_info( $result, string $action, $args ) {
        if ( 'plugin_information' !== $action || ! is_object( $args ) || ( $args->slug ?? '' ) !== self::PLUGIN_SLUG ) {
            return $result;
        }

        $release = self::get_latest_release();

        if ( ! $release ) {
            return $result;
        }

        $gh_raw  = 'https://raw.githubusercontent.com/' . self::GITHUB_OWNER . '/' . self::GITHUB_REPO . '/main/images/';
        $gh_repo = 'https://github.com/' . self::GITHUB_OWNER . '/' . self::GITHUB_REPO;

        $description = '
            <p>AI Genie is a WordPress plugin that generates editorial content using Anthropic Claude, OpenAI, or a self-hosted Ollama instance — directly from wp-admin, with no external dashboards required beyond your chosen AI provider.</p>

            <h3>Features</h3>
            <ul>
                <li><strong>Multi-provider support</strong> — Switch between Anthropic Claude, OpenAI, and self-hosted Ollama. Change providers per-generation from the Gutenberg sidebar.</li>
                <li><strong>Deep Research</strong> — Run long-form OpenAI research jobs using <code>o4-mini-deep-research</code> and <code>o3-deep-research</code> models, with a dedicated wp-admin workspace for managing runs, sources, and outputs.</li>
                <li><strong>Gutenberg sidebar</strong> — Generate post body HTML, SEO title, meta description, and excerpt without leaving the block editor. Output streams in real time via Server-Sent Events.</li>
                <li><strong>Context scope control</strong> — Generate from the full post, selected blocks, or a custom-pasted excerpt.</li>
                <li><strong>Advanced per-run overrides</strong> — Override model, max output tokens, thinking tokens, and temperature from the Gutenberg sidebar Advanced panel.</li>
                <li><strong>Budget-aware continuation</strong> — Automatically continues generation when a provider stops early, spending only the remaining output budget so configured token limits act as hard caps.</li>
                <li><strong>Self-hosted Ollama</strong> — Point the plugin at any local or remote Ollama instance. No API key required. A Cloudflare Worker proxy script is included for browser-based WordPress runtimes.</li>
                <li><strong>Auto-updates</strong> — Backed by GitHub Releases. WordPress detects and installs new versions through its native plugin update pipeline — no update server required.</li>
            </ul>

            <h3>Providers</h3>
            <ul>
                <li><strong>Anthropic Claude</strong> — Claude 3.5, 3.7, and current models via the Messages API.</li>
                <li><strong>OpenAI</strong> — GPT-4o, o3, o4-mini, and deep-research models via Chat Completions and Responses APIs.</li>
                <li><strong>Ollama</strong> — Any locally or remotely hosted model. Cloudflare Worker proxy support included for browser-based environments.</li>
            </ul>

            <h3>Requirements</h3>
            <ul>
                <li>WordPress 6.4 or higher</li>
                <li>PHP 8.1 or higher</li>
                <li>An API key for Anthropic Claude or OpenAI, or a running Ollama instance</li>
            </ul>
        ';

        $installation = '
            <h3>From WordPress admin</h3>
            <ol>
                <li>Download the latest <code>ai-genie-vX.X.X.zip</code> from <a href="' . esc_url( $gh_repo ) . '/releases">GitHub Releases</a>.</li>
                <li>Go to <strong>Plugins &rarr; Add Plugin &rarr; Upload Plugin</strong>.</li>
                <li>Upload the zip, click <strong>Install Now</strong>, then <strong>Activate</strong>.</li>
                <li>Open <strong>AI Genie</strong> in the sidebar and enter your provider API key under Settings.</li>
            </ol>

            <h3>Self-hosted Ollama</h3>
            <ol>
                <li>Install and start <a href="https://ollama.com">Ollama</a> on your machine or server.</li>
                <li>In AI Genie Settings, set <strong>Provider</strong> to Ollama and enter your Ollama host URL (e.g. <code>http://localhost:11434</code>).</li>
                <li>Click <strong>Load Models</strong> to verify the connection.</li>
            </ol>

            <h3>Auto-updates</h3>
            <p>Enable <strong>Automatic Updates</strong> from the Plugins list. AI Genie checks GitHub Releases every hour and installs new versions through the native WordPress update pipeline automatically.</p>
        ';

        $changelog = self::build_changelog_html();

        return (object) [
            'name'           => 'AI Genie',
            'slug'           => self::PLUGIN_SLUG,
            'version'        => $release['version'],
            'author'         => '<a href="https://github.com/' . self::GITHUB_OWNER . '">' . esc_html( self::GITHUB_OWNER ) . '</a>',
            'author_profile' => 'https://github.com/' . self::GITHUB_OWNER,
            'requires'       => '6.4',
            'requires_php'   => '8.1',
            'tested'         => '6.8',
            'last_updated'   => $release['published_at'] ?? '',
            'download_link'  => $release['package_url'],
            'homepage'       => $gh_repo,
            'tags'           => [
                'ai'                 => 'AI',
                'content-generation' => 'Content Generation',
                'openai'             => 'OpenAI',
                'claude'             => 'Claude',
                'ollama'             => 'Ollama',
                'seo'                => 'SEO',
                'deep-research'      => 'Deep Research',
                'gutenberg'          => 'Gutenberg',
            ],
            'icons'          => [
                '1x' => $gh_raw . 'plugin-icon.png',
                '2x' => $gh_raw . 'plugin-icon.png',
            ],
            'banners'        => [
                'low'  => $gh_raw . 'banner-772x250.png',
                'high' => $gh_raw . 'banner-1544x500.png',
            ],
            'sections'       => [
                'description'  => $description,
                'installation' => $installation,
                'changelog'    => $changelog,
            ],
        ];
    }

    /**
     * Build a multi-version changelog HTML block from the GitHub releases list.
     */
    private static function build_changelog_html(): string {
        $releases = self::get_all_releases();

        if ( empty( $releases ) ) {
            return '<p>See <a href="https://github.com/' . self::GITHUB_OWNER . '/' . self::GITHUB_REPO . '/releases">GitHub Releases</a> for the full changelog.</p>';
        }

        $html = '';
        foreach ( $releases as $r ) {
            $date  = ! empty( $r['published_at'] ) ? date_i18n( get_option( 'date_format' ), strtotime( $r['published_at'] ) ) : '';
            $html .= '<h3>' . esc_html( $r['version'] ) . '</h3>';
            if ( $date ) {
                $html .= '<p><em>Released ' . esc_html( $date ) . '</em></p>';
            }
            if ( ! empty( $r['body'] ) ) {
                $html .= self::markdown_to_html( $r['body'] );
            }
        }

        return $html;
    }

    /**
     * Fetch the last 15 releases from GitHub for the changelog.
     * Cached separately from the latest-release check.
     *
     * @return array<int,array<string,string>>
     */
    private static function get_all_releases(): array {
        $cache_key = self::CACHE_KEY . '_list';
        $cached    = get_site_transient( $cache_key );

        if ( is_array( $cached ) ) {
            return $cached;
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' . self::GITHUB_OWNER . '/' . self::GITHUB_REPO . '/releases?per_page=15',
            [
                'timeout' => 15,
                'headers' => [
                    'Accept'               => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                    'User-Agent'           => 'AI Genie WordPress updater',
                ],
            ]
        );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return [];
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $data ) ) {
            return [];
        }

        $releases = [];
        foreach ( $data as $item ) {
            $version = self::normalize_version( $item['tag_name'] ?? '' );
            if ( '' === $version ) {
                continue;
            }
            $releases[] = [
                'version'      => $version,
                'published_at' => $item['published_at'] ?? '',
                'body'         => $item['body'] ?? '',
            ];
        }

        set_site_transient( $cache_key, $releases, self::CACHE_TTL );

        return $releases;
    }

    /**
     * Convert a GitHub-flavoured Markdown string to safe HTML.
     * Handles headings, bullet lists, bold, and inline code.
     */
    private static function markdown_to_html( string $markdown ): string {
        $lines   = explode( "\n", $markdown );
        $output  = [];
        $in_list = false;

        foreach ( $lines as $line ) {
            $line = rtrim( $line );

            if ( preg_match( '/^### (.+)$/', $line, $m ) ) {
                if ( $in_list ) { $output[] = '</ul>'; $in_list = false; }
                $output[] = '<h4>' . self::inline_md( $m[1] ) . '</h4>';
            } elseif ( preg_match( '/^## (.+)$/', $line, $m ) ) {
                if ( $in_list ) { $output[] = '</ul>'; $in_list = false; }
                $output[] = '<h3>' . self::inline_md( $m[1] ) . '</h3>';
            } elseif ( preg_match( '/^# (.+)$/', $line, $m ) ) {
                if ( $in_list ) { $output[] = '</ul>'; $in_list = false; }
                $output[] = '<h2>' . self::inline_md( $m[1] ) . '</h2>';
            } elseif ( preg_match( '/^[-*+] (.+)$/', $line, $m ) ) {
                if ( ! $in_list ) { $output[] = '<ul>'; $in_list = true; }
                $output[] = '<li>' . self::inline_md( $m[1] ) . '</li>';
            } elseif ( '' === $line ) {
                if ( $in_list ) { $output[] = '</ul>'; $in_list = false; }
                $output[] = '';
            } else {
                if ( $in_list ) { $output[] = '</ul>'; $in_list = false; }
                $output[] = self::inline_md( $line );
            }
        }

        if ( $in_list ) {
            $output[] = '</ul>';
        }

        return wp_kses_post( wpautop( implode( "\n", $output ) ) );
    }

    /**
     * Apply inline Markdown formatting (bold, inline code) to a plain text string.
     * HTML-escapes the input first so the result is safe to embed in HTML.
     */
    private static function inline_md( string $text ): string {
        $text = esc_html( $text );
        $text = (string) preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
        $text = (string) preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text );
        return $text;
    }

    /**
     * Return the latest GitHub release details in a cached array format.
     *
     * @return array<string,string>|null
     */
    private static function get_latest_release(): ?array {
        $cached = get_site_transient( self::CACHE_KEY );

        if ( is_array( $cached ) ) {
            if ( ! empty( $cached['version'] ) && ! empty( $cached['package_url'] ) ) {
                return $cached;
            }

            if ( array_key_exists( 'version', $cached ) ) {
                return null;
            }
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' . self::GITHUB_OWNER . '/' . self::GITHUB_REPO . '/releases/latest',
            [
                'timeout' => 15,
                'headers' => [
                    'Accept'               => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                    'User-Agent'           => 'AI Genie WordPress updater',
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            self::store_cache_failure();
            return null;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );

        if ( 200 !== $status || '' === $body ) {
            self::store_cache_failure();
            return null;
        }

        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            self::store_cache_failure();
            return null;
        }

        $version = self::normalize_version( $data['tag_name'] ?? '' );
        $asset   = self::find_release_asset( $data['assets'] ?? [], $version );

        if ( '' === $version || empty( $asset['browser_download_url'] ) ) {
            self::store_cache_failure();
            return null;
        }

        $release = [
            'version'       => $version,
            'package_url'   => $asset['browser_download_url'],
            'published_at'  => $data['published_at'] ?? '',
            'body_html'     => self::markdown_to_html( $data['body'] ?? '' ),
        ];

        set_site_transient( self::CACHE_KEY, $release, self::CACHE_TTL );

        return $release;
    }

    /**
     * Store a short-lived negative cache so a temporary API failure does not hammer GitHub.
     */
    private static function store_cache_failure(): void {
        set_site_transient( self::CACHE_KEY, [ 'version' => '', 'package_url' => '', 'failed' => true ], self::ERROR_TTL );
    }

    private static function normalize_version( string $tag ): string {
        $tag = trim( $tag );

        if ( '' === $tag ) {
            return '';
        }

        return preg_replace( '/^v/i', '', $tag ) ?: '';
    }

    /**
     * @param array<int,array<string,mixed>> $assets
     * @return array<string,mixed>
     */
    private static function find_release_asset( array $assets, string $version ): array {
        $expected_name = 'ai-genie-v' . $version . '.zip';

        foreach ( $assets as $asset ) {
            if ( ( $asset['name'] ?? '' ) === $expected_name ) {
                return $asset;
            }
        }

        return [];
    }
}
