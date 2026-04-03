<?php
defined( 'ABSPATH' ) || exit;

class ACF_Generator {

    const TYPES = [ 'post_content', 'seo_title', 'meta_description', 'excerpt' ];

    private static array $provider_instances = [];

    /**
     * Resolve a provider instance by slug.
     *
     * @throws InvalidArgumentException on unknown slug
     */
    public static function get_provider( string $slug ): ACF_Provider {
        if ( ! isset( self::$provider_instances[ $slug ] ) ) {
            switch ( $slug ) {
                case 'claude':
                    self::$provider_instances['claude'] = new ACF_Provider_Claude();
                    break;
                case 'openai':
                    self::$provider_instances['openai'] = new ACF_Provider_OpenAI();
                    break;
                case 'ollama':
                    self::$provider_instances['ollama'] = new ACF_Provider_Ollama();
                    break;
                default:
                    throw new InvalidArgumentException( "Unknown provider: $slug" );
            }
        }
        return self::$provider_instances[ $slug ];
    }

    /**
     * Generate content.
     *
     * @param string $type         One of self::TYPES
     * @param array  $context      [ title, keywords, tone, existing_content, post_type ]
     * @param string $provider     Provider slug or '' to use global default
     * @return string
     * @throws RuntimeException|InvalidArgumentException
     */
    public static function generate( string $type, array $context, string $provider = '' ): string {
        if ( ! in_array( $type, self::TYPES, true ) ) {
            throw new InvalidArgumentException( "Unknown generation type: $type" );
        }

        $provider_slug = $provider ?: ACF_Settings::get( 'default_provider', 'claude' );
        $instance      = self::get_provider( $provider_slug );

        $prompt     = self::build_prompt( $type, $context );
        $max_tokens = ACF_Settings::get( 'max_tokens', 1500 );
        $temp       = ACF_Settings::get( 'temperature', 0.7 );

        // Shorter outputs need fewer tokens
        if ( in_array( $type, [ 'seo_title', 'meta_description', 'excerpt' ], true ) ) {
            $max_tokens = min( $max_tokens, 300 );
            $temp       = max( 0.3, $temp - 0.2 );
        }

        return $instance->generate( $prompt, $max_tokens, $temp );
    }

    // -------------------------------------------------------------------------
    // Prompt builders
    // -------------------------------------------------------------------------

    private static function build_prompt( string $type, array $context ): string {
        $title    = sanitize_text_field( $context['title'] ?? '' );
        $keywords = sanitize_text_field( $context['keywords'] ?? '' );
        $tone     = sanitize_text_field( $context['tone'] ?? 'professional' );
        $existing = wp_strip_all_tags( $context['existing_content'] ?? '' );
        $pt       = sanitize_text_field( $context['post_type'] ?? 'post' );
        $lang     = sanitize_text_field( $context['language'] ?? 'English' );

        $kw_line  = $keywords ? "Focus keywords: $keywords." : '';
        $ex_line  = $existing ? "Existing content for reference:\n---\n" . mb_substr( $existing, 0, 1000 ) . "\n---" : '';

        switch ( $type ) {
            case 'post_content':
                return <<<PROMPT
You are an expert content writer. Write a complete, well-structured WordPress $pt in $lang.

Title: $title
Tone: $tone
$kw_line
$ex_line

Requirements:
- Use proper heading hierarchy (H2, H3)
- Include an engaging introduction and a clear conclusion
- Target roughly 600–900 words and keep each section concise
- Output clean HTML suitable for the WordPress block editor (use <h2>, <h3>, <p>, <ul>/<ol>)
- Do NOT include the post title as an H1 — WordPress outputs that separately
- Do NOT wrap the output in code fences
PROMPT;

            case 'seo_title':
                return <<<PROMPT
You are an SEO specialist. Write an optimised SEO title tag for a WordPress $pt.

Post title: $title
Tone: $tone
$kw_line

Requirements:
- 50–60 characters maximum
- Include the primary keyword naturally
- Be compelling and click-worthy
- Output only the title text, no quotes, no explanation
PROMPT;

            case 'meta_description':
                return <<<PROMPT
You are an SEO specialist. Write a meta description for a WordPress $pt.

Post title: $title
Tone: $tone
$kw_line
$ex_line

Requirements:
- 150–160 characters maximum
- Include the primary keyword naturally
- Include a subtle call to action
- Output only the description text, no quotes, no explanation
PROMPT;

            case 'excerpt':
                return <<<PROMPT
You are a content editor. Write a short excerpt for a WordPress $pt.

Post title: $title
Tone: $tone
$ex_line

Requirements:
- 40–55 words
- Engaging, teases the content without giving everything away
- Plain text only — no HTML, no quotes around the output, no explanation
PROMPT;
        }

        return ''; // unreachable but satisfies static analysis
    }
}
