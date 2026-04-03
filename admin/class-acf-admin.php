<?php
defined( 'ABSPATH' ) || exit;

class ACF_Admin {

    public static function init(): void {
        add_action( 'admin_menu',    [ self::class, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
    }

    public static function register_menu(): void {
        add_options_page(
            __( 'AI Content Forge', 'ai-content-forge' ),
            __( 'AI Content Forge', 'ai-content-forge' ),
            'manage_options',
            'ai-content-forge',
            [ self::class, 'render_page' ]
        );
    }

    public static function enqueue_assets( string $hook ): void {
        if ( $hook !== 'settings_page_ai-content-forge' ) {
            return;
        }
        wp_enqueue_style(
            'acf-admin',
            ACF_PLUGIN_URL . 'assets/css/admin.css',
            [],
            ACF_VERSION
        );
        wp_enqueue_script(
            'acf-admin',
            ACF_PLUGIN_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            ACF_VERSION,
            true
        );
        wp_localize_script( 'acf-admin', 'acfAdmin', [
            'restUrl' => rest_url( ACF_Rest_API::REST_NAMESPACE ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'settings' => ACF_Settings::for_js(),
            'i18n' => [
                'testing'   => __( 'Testing…', 'ai-content-forge' ),
                'success'   => __( '✓ Connected', 'ai-content-forge' ),
                'fail'      => __( '✗ Failed', 'ai-content-forge' ),
                'generating'=> __( 'Generating…', 'ai-content-forge' ),
            ],
        ] );
    }

    public static function render_page(): void {
        $settings = ACF_Settings::all();
        ?>
        <div class="wrap acf-settings-wrap">
            <h1 class="acf-page-title">
                <span class="acf-logo">⚡</span>
                <?php esc_html_e( 'AI Content Forge', 'ai-content-forge' ); ?>
            </h1>

            <?php settings_errors(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'acf_settings_group' );
                $opt = ACF_Settings::OPTION_KEY;
                ?>

                <!-- ── Provider Default ───────────────────────────────── -->
                <div class="acf-card">
                    <h2><?php esc_html_e( 'Default Provider', 'ai-content-forge' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Used when no per-use override is selected.', 'ai-content-forge' ); ?></p>
                    <div class="acf-provider-cards">
                        <?php foreach ( ACF_Settings::PROVIDERS as $slug ) :
                            $checked = checked( $settings['default_provider'], $slug, false );
                            $labels  = [ 'claude' => 'Anthropic Claude', 'openai' => 'OpenAI', 'ollama' => 'Ollama (Local)' ];
                            $icons   = [ 'claude' => '🟠', 'openai' => '🟢', 'ollama' => '🔵' ];
                        ?>
                        <label class="acf-provider-card <?php echo $settings['default_provider'] === $slug ? 'selected' : ''; ?>">
                            <input type="radio" name="<?php echo esc_attr( $opt ); ?>[default_provider]"
                                   value="<?php echo esc_attr( $slug ); ?>" <?php echo $checked; ?>>
                            <span class="acf-provider-icon"><?php echo $icons[ $slug ]; ?></span>
                            <span class="acf-provider-name"><?php echo esc_html( $labels[ $slug ] ); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ── Claude ─────────────────────────────────────────── -->
                <div class="acf-card acf-provider-section" id="section-claude">
                    <h2>🟠 <?php esc_html_e( 'Anthropic Claude', 'ai-content-forge' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th><?php esc_html_e( 'API Key', 'ai-content-forge' ); ?></th>
                            <td>
                                <input type="password" class="regular-text"
                                       name="<?php echo esc_attr( $opt ); ?>[claude_api_key]"
                                       value="<?php echo esc_attr( $settings['claude_api_key'] ); ?>" autocomplete="off">
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Model', 'ai-content-forge' ); ?></th>
                            <td>
                                <input type="text" class="regular-text"
                                       name="<?php echo esc_attr( $opt ); ?>[claude_model]"
                                       value="<?php echo esc_attr( $settings['claude_model'] ); ?>">
                                <p class="description">e.g. <code>claude-sonnet-4-20250514</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th></th>
                            <td>
                                <button type="button" class="button acf-test-btn" data-provider="claude">
                                    <?php esc_html_e( 'Test Connection', 'ai-content-forge' ); ?>
                                </button>
                                <span class="acf-test-result" id="test-claude"></span>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ── OpenAI ─────────────────────────────────────────── -->
                <div class="acf-card acf-provider-section" id="section-openai">
                    <h2>🟢 <?php esc_html_e( 'OpenAI', 'ai-content-forge' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th><?php esc_html_e( 'API Key', 'ai-content-forge' ); ?></th>
                            <td>
                                <input type="password" class="regular-text"
                                       name="<?php echo esc_attr( $opt ); ?>[openai_api_key]"
                                       value="<?php echo esc_attr( $settings['openai_api_key'] ); ?>" autocomplete="off">
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Model', 'ai-content-forge' ); ?></th>
                            <td>
                                <input type="text" class="regular-text"
                                       name="<?php echo esc_attr( $opt ); ?>[openai_model]"
                                       value="<?php echo esc_attr( $settings['openai_model'] ); ?>">
                                <p class="description">e.g. <code>gpt-4o</code>, <code>gpt-4o-mini</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th></th>
                            <td>
                                <button type="button" class="button acf-test-btn" data-provider="openai">
                                    <?php esc_html_e( 'Test Connection', 'ai-content-forge' ); ?>
                                </button>
                                <span class="acf-test-result" id="test-openai"></span>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ── Ollama ─────────────────────────────────────────── -->
                <div class="acf-card acf-provider-section" id="section-ollama">
                    <h2>🔵 <?php esc_html_e( 'Ollama (Local LLM)', 'ai-content-forge' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th><?php esc_html_e( 'Base URL', 'ai-content-forge' ); ?></th>
                            <td>
                                <input type="url" class="regular-text"
                                       name="<?php echo esc_attr( $opt ); ?>[ollama_url]"
                                       value="<?php echo esc_attr( $settings['ollama_url'] ); ?>">
                                <p class="description">Default: <code>http://localhost:11434</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Model', 'ai-content-forge' ); ?></th>
                            <td>
                                <input type="text" class="regular-text"
                                       name="<?php echo esc_attr( $opt ); ?>[ollama_model]"
                                       value="<?php echo esc_attr( $settings['ollama_model'] ); ?>">
                                <p class="description">e.g. <code>llama3</code>, <code>mistral</code>, <code>gemma3</code></p>
                            </td>
                        </tr>
                        <tr>
                            <th></th>
                            <td>
                                <button type="button" class="button acf-test-btn" data-provider="ollama">
                                    <?php esc_html_e( 'Test Connection', 'ai-content-forge' ); ?>
                                </button>
                                <span class="acf-test-result" id="test-ollama"></span>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- ── Generation defaults ────────────────────────────── -->
                <div class="acf-card">
                    <h2><?php esc_html_e( 'Generation Defaults', 'ai-content-forge' ); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th><?php esc_html_e( 'Max Tokens', 'ai-content-forge' ); ?></th>
                            <td>
                                <input type="number" min="100" max="4000" step="50"
                                       name="<?php echo esc_attr( $opt ); ?>[max_tokens]"
                                       value="<?php echo esc_attr( $settings['max_tokens'] ); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Temperature', 'ai-content-forge' ); ?></th>
                            <td>
                                <input type="number" min="0" max="2" step="0.1"
                                       name="<?php echo esc_attr( $opt ); ?>[temperature]"
                                       value="<?php echo esc_attr( $settings['temperature'] ); ?>">
                                <p class="description"><?php esc_html_e( '0 = deterministic, 1 = creative, 2 = chaotic', 'ai-content-forge' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button( __( 'Save Settings', 'ai-content-forge' ) ); ?>
            </form>
        </div>
        <?php
    }
}
