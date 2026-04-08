<?php
defined( 'ABSPATH' ) || exit;

class AIG_Generator {

    const TYPES = [ 'post_content', 'seo_title', 'meta_description', 'excerpt' ];

    private static array $provider_instances = [];

    /**
     * Resolve a provider instance by slug.
     *
     * @throws InvalidArgumentException on unknown slug
     */
    public static function get_provider( string $slug ): AIG_Provider {
        if ( ! isset( self::$provider_instances[ $slug ] ) ) {
            switch ( $slug ) {
                case 'claude':
                    self::$provider_instances['claude'] = new AIG_Provider_Claude();
                    break;
                case 'openai':
                    self::$provider_instances['openai'] = new AIG_Provider_OpenAI();
                    break;
                case 'ollama':
                    self::$provider_instances['ollama'] = new AIG_Provider_Ollama();
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

        $provider_slug = $provider ?: AIG_Settings::get( 'default_provider', 'claude' );
        $instance      = self::get_provider( $provider_slug );

        $overrides           = self::normalize_overrides( $context );
        $max_output_tokens  = $overrides['max_output_tokens']
            ?? AIG_Settings::get( 'max_output_tokens', AIG_Settings::get( 'max_tokens', 15000 ) );
        $max_thinking_tokens = $overrides['max_thinking_tokens']
            ?? AIG_Settings::get( 'max_thinking_tokens', 15000 );
        $temp               = $overrides['temperature']
            ?? AIG_Settings::get( 'temperature', 0.7 );

        // Shorter outputs need fewer tokens
        if ( in_array( $type, [ 'seo_title', 'meta_description', 'excerpt' ], true ) ) {
            $max_output_tokens = min( $max_output_tokens, 300 );
            $temp              = max( 0.3, $temp - 0.2 );
        }

        $prompt_context = $context;
        $prompt_context['effective_max_output_tokens']   = $max_output_tokens;
        $prompt_context['effective_max_thinking_tokens'] = $max_thinking_tokens;
        $prompt = self::build_prompt( $type, $prompt_context );

        $instance->set_model_override( $overrides['model'] ?? '' );
        $instance->set_generation_id( $overrides['generation_id'] ?? '' );

        try {
            return $instance->generate( $prompt, $max_output_tokens, $temp, $max_thinking_tokens );
        } finally {
            $instance->set_model_override( '' );
            $instance->set_generation_id( '' );
        }
    }

    /**
     * Stream generated content through the provider.
     *
     * @param callable(string):void $emit
     * @return array<string,mixed>
     */
    public static function stream_generate( string $type, array $context, string $provider, callable $emit, ?callable $emit_usage_estimate = null ): array {
        if ( ! in_array( $type, self::TYPES, true ) ) {
            throw new InvalidArgumentException( "Unknown generation type: $type" );
        }

        $provider_slug        = $provider ?: AIG_Settings::get( 'default_provider', 'claude' );
        $instance             = self::get_provider( $provider_slug );
        $overrides            = self::normalize_overrides( $context );
        $max_output_tokens    = $overrides['max_output_tokens']
            ?? AIG_Settings::get( 'max_output_tokens', AIG_Settings::get( 'max_tokens', 15000 ) );
        $max_thinking_tokens  = $overrides['max_thinking_tokens']
            ?? AIG_Settings::get( 'max_thinking_tokens', 15000 );
        $temp                 = $overrides['temperature']
            ?? AIG_Settings::get( 'temperature', 0.7 );

        if ( in_array( $type, [ 'seo_title', 'meta_description', 'excerpt' ], true ) ) {
            $max_output_tokens = min( $max_output_tokens, 300 );
            $temp              = max( 0.3, $temp - 0.2 );
        }

        $prompt_context = $context;
        $prompt_context['effective_max_output_tokens']   = $max_output_tokens;
        $prompt_context['effective_max_thinking_tokens'] = $max_thinking_tokens;
        $prompt = self::build_prompt( $type, $prompt_context );

        $instance->set_model_override( $overrides['model'] ?? '' );
        $instance->set_generation_id( $overrides['generation_id'] ?? '' );

        try {
            $resolved_model   = self::resolve_usage_estimate_model( $provider_slug, $overrides );
            $estimated_usage  = null;
            $streamed_output  = '';
            $last_output_tokens = -1;

            if ( null !== $emit_usage_estimate ) {
                $estimated_usage = AIG_Token_Usage_Estimator::begin_estimate( $provider_slug, $resolved_model, $prompt );

                if ( ! empty( $estimated_usage ) ) {
                    $emit_usage_estimate( $estimated_usage );
                    $last_output_tokens = (int) ( $estimated_usage['output_tokens'] ?? 0 );
                }
            }

            $emit_and_estimate = static function ( string $chunk ) use ( $emit, $emit_usage_estimate, &$estimated_usage, &$streamed_output, &$last_output_tokens ): void {
                if ( '' === $chunk ) {
                    return;
                }

                $emit( $chunk );

                if ( null === $emit_usage_estimate || empty( $estimated_usage ) ) {
                    return;
                }

                $streamed_output .= $chunk;
                $estimated_usage  = AIG_Token_Usage_Estimator::update_estimate( $estimated_usage, $streamed_output );
                $output_tokens    = (int) ( $estimated_usage['output_tokens'] ?? 0 );

                if ( $output_tokens !== $last_output_tokens ) {
                    $last_output_tokens = $output_tokens;
                    $emit_usage_estimate( $estimated_usage );
                }
            };

            return self::stream_generate_with_continuations(
                $instance,
                $type,
                $context,
                $prompt,
                $max_output_tokens,
                $temp,
                $max_thinking_tokens,
                $emit_and_estimate
            );
        } finally {
            $instance->set_model_override( '' );
            $instance->set_generation_id( '' );
        }
    }

    /**
     * Attempt to stop an active generation for the selected/default provider.
     */
    public static function stop_generation( string $provider = '', string $generation_id = '' ): bool {
        $provider_slug = $provider ?: AIG_Settings::get( 'default_provider', 'claude' );
        $instance      = self::get_provider( $provider_slug );
        $instance->set_generation_id( $generation_id );

        try {
            return $instance->cancel_generation( $generation_id );
        } finally {
            $instance->set_generation_id( '' );
        }
    }

    // -------------------------------------------------------------------------
    // Prompt builders
    // -------------------------------------------------------------------------

    private static function build_prompt( string $type, array $context ): string {
        $title           = sanitize_text_field( $context['title'] ?? '' );
        $keywords        = sanitize_text_field( $context['keywords'] ?? '' );
        $tone            = sanitize_text_field( $context['tone'] ?? 'professional' );
        $existing        = wp_strip_all_tags( $context['existing_content'] ?? '' );
        $post_type       = sanitize_text_field( $context['post_type'] ?? 'post' );
        $language        = sanitize_text_field( $context['language'] ?? 'English' );
        $structure       = sanitize_text_field( $context['structure'] ?? '' );
        $target_length   = absint( $context['target_length'] ?? 0 );
        $max_output_tokens = absint( $context['effective_max_output_tokens'] ?? 0 );
        $max_thinking_tokens = absint( $context['effective_max_thinking_tokens'] ?? 0 );
        $existing_snip   = $existing ? mb_substr( $existing, 0, 1000 ) : '';
        $prompt_override = isset( $context['prompt_override'] ) ? (string) $context['prompt_override'] : '';
        $prompt_template = '' !== trim( $prompt_override )
            ? self::normalize_prompt_template( $prompt_override )
            : AIG_Settings::get_prompt_template( $type );

        if ( '' === $structure && 'post_content' === $type ) {
            $structure = 'Full Draft';
        }

        if ( $target_length <= 0 && 'post_content' === $type ) {
            $target_length = 900;
        }

        $structure_line = '' !== $structure ? "Requested format: {$structure}." : '';
        $target_length_line = $target_length > 0 ? "Target length: about {$target_length} words." : '';

        $prompt = strtr(
            $prompt_template,
            [
                '{title}'                  => $title,
                '{tone}'                   => $tone,
                '{keywords}'               => $keywords,
                '{keywords_line}'          => $keywords ? "Focus keywords: {$keywords}." : '',
                '{post_type}'              => $post_type,
                '{language}'               => $language,
                '{structure}'              => $structure,
                '{structure_line}'         => $structure_line,
                '{target_length}'          => $target_length ? (string) $target_length : '',
                '{target_length_line}'     => $target_length_line,
                '{existing_content}'       => $existing_snip,
                '{existing_content_block}' => $existing_snip
                    ? "Existing content for reference:\n---\n{$existing_snip}\n---"
                    : '',
            ]
        );

        $prompt = preg_replace( "/[ \t]+\n/", "\n", $prompt );
        $prompt = preg_replace( "/\n{3,}/", "\n\n", $prompt );

        if ( 'post_content' === $type ) {
            $prompt .= self::build_post_content_budget_guidance(
                $target_length,
                $max_output_tokens,
                $max_thinking_tokens
            );
        }

        return trim( $prompt );
    }

    private static function build_post_content_budget_guidance( int $target_length, int $max_output_tokens, int $max_thinking_tokens ): string {
        $lines = [
            '',
            'Generation budget rules:',
            '- Treat Max Output Tokens and Max Thinking Tokens as hard caps. Never rely on exceeding them.',
            '- Use as much of the available thinking budget as useful to plan, reason, and improve coverage before finalising the article.',
            '- Use as much of the available output budget as useful to deliver the strongest, richest, most complete blog post possible.',
            '- Prioritise quality, depth, structure, specificity, and reader value over brevity.',
        ];

        if ( $target_length > 0 ) {
            $lines[] = "- Aim for {$target_length} words and stay as close as possible, normally within about plus or minus 100 words.";
            $lines[] = '- If you cannot hit the target exactly, prefer being slightly under or over only when that produces a materially better article.';
        }

        if ( $max_output_tokens > 0 ) {
            $lines[] = "- Max Output Tokens hard cap: {$max_output_tokens}. Use the budget efficiently and avoid leaving obvious value on the table.";
        }

        if ( $max_thinking_tokens > 0 ) {
            $lines[] = "- Max Thinking Tokens hard cap: {$max_thinking_tokens}. Spend the reasoning budget aggressively when it improves the final article.";
        }

        return "\n\n" . implode( "\n", $lines );
    }

    private static function stream_generate_with_continuations(
        AIG_Provider $instance,
        string $type,
        array $context,
        string $prompt,
        int $max_output_tokens,
        float $temperature,
        int $max_thinking_tokens,
        callable $emit
    ): array {
        $usage            = [];
        $aggregate_usage  = [];
        $full_output      = '';
        $remaining_output = max( 0, $max_output_tokens );
        $remaining_think  = max( 0, $max_thinking_tokens );
        $target_length    = absint( $context['target_length'] ?? 0 );
        $continuations    = 0;

        while ( true ) {
            $segment_output = '';
            $usage = $instance->stream_generate(
                $prompt,
                max( 1, $remaining_output ),
                $temperature,
                $remaining_think,
                static function ( string $chunk ) use ( $emit, &$segment_output, &$full_output ): void {
                    if ( '' === $chunk ) {
                        return;
                    }

                    $segment_output .= $chunk;
                    $full_output    .= $chunk;
                    $emit( $chunk );
                }
            );

            $aggregate_usage = self::merge_usage( $aggregate_usage, $usage );
            $remaining_output = self::remaining_budget( $max_output_tokens, $aggregate_usage['output_tokens'] ?? 0 );
            $remaining_think  = self::remaining_budget( $max_thinking_tokens, $aggregate_usage['thinking_tokens'] ?? 0 );

            if ( ! self::should_continue_post_content(
                $type,
                $target_length,
                $full_output,
                $remaining_output,
                $remaining_think,
                $aggregate_usage,
                $continuations
            ) ) {
                break;
            }

            $continuations++;
            $prompt = self::build_continuation_prompt(
                $context,
                $full_output,
                $target_length,
                $remaining_output
            );
        }

        return $aggregate_usage;
    }

    private static function should_continue_post_content(
        string $type,
        int $target_length,
        string $full_output,
        int $remaining_output,
        int $remaining_think,
        array $aggregate_usage,
        int $continuations
    ): bool {
        if ( 'post_content' !== $type || $target_length <= 0 ) {
            return false;
        }

        if ( $continuations >= 2 ) {
            return false;
        }

        $provider = (string) ( $aggregate_usage['provider'] ?? '' );
        if ( ! in_array( $provider, [ 'openai', 'ollama' ], true ) ) {
            return false;
        }

        $current_words = self::count_words( $full_output );
        $missing_words = $target_length - $current_words;

        if ( $missing_words <= 100 ) {
            return false;
        }

        if ( $remaining_output < 400 ) {
            return false;
        }

        if ( 'openai' === $provider && $remaining_think < 0 ) {
            return false;
        }

        return true;
    }

    private static function build_continuation_prompt( array $context, string $full_output, int $target_length, int $remaining_output ): string {
        $title          = sanitize_text_field( $context['title'] ?? '' );
        $tone           = sanitize_text_field( $context['tone'] ?? 'professional' );
        $language       = sanitize_text_field( $context['language'] ?? 'English' );
        $structure      = sanitize_text_field( $context['structure'] ?? 'Full Draft' );
        $current_words  = self::count_words( $full_output );
        $missing_words  = max( 0, $target_length - $current_words );
        $tail_excerpt   = trim( mb_substr( wp_strip_all_tags( $full_output ), -4000 ) );
        $html_excerpt   = trim( mb_substr( $full_output, -8000 ) );

        return trim(
            "Continue the same WordPress post seamlessly in {$language}.\n\n" .
            "Title: {$title}\n" .
            "Tone: {$tone}\n" .
            "Requested format: {$structure}.\n" .
            "Current draft length: about {$current_words} words.\n" .
            "Target length: about {$target_length} words total.\n" .
            "Missing length to add: about {$missing_words} words.\n" .
            "Remaining output token budget hard cap for this continuation: {$remaining_output}.\n\n" .
            "Continuation requirements:\n" .
            "- Continue exactly where the current draft stops.\n" .
            "- Do not restart the article, repeat earlier sections, or add a second introduction.\n" .
            "- Preserve the same structure, voice, and HTML style.\n" .
            "- Add the most valuable missing sections, depth, examples, and details needed to reach the target length as closely as possible.\n" .
            "- Finish with a strong conclusion only if the current draft has not already concluded.\n" .
            "- Return only the next HTML/content that should be appended after the current draft.\n\n" .
            "Current draft ending context (plain text):\n---\n{$tail_excerpt}\n---\n\n" .
            "Current draft tail (raw HTML/content to continue from):\n---\n{$html_excerpt}\n---"
        );
    }

    private static function merge_usage( array $aggregate, array $usage ): array {
        if ( empty( $aggregate ) ) {
            return $usage;
        }

        foreach ( [ 'provider', 'model', 'currency' ] as $key ) {
            if ( empty( $aggregate[ $key ] ) && ! empty( $usage[ $key ] ) ) {
                $aggregate[ $key ] = $usage[ $key ];
            }
        }

        foreach ( [ 'input_tokens', 'thinking_tokens', 'output_tokens', 'total_tokens', 'cost_usd' ] as $key ) {
            $aggregate[ $key ] = self::sum_usage_metric( $aggregate[ $key ] ?? null, $usage[ $key ] ?? null );
        }

        return $aggregate;
    }

    private static function sum_usage_metric( $left, $right ) {
        if ( null === $left && null === $right ) {
            return null;
        }

        return (float) ( $left ?? 0 ) + (float) ( $right ?? 0 );
    }

    private static function remaining_budget( int $cap, $used ): int {
        if ( $cap <= 0 ) {
            return 0;
        }

        return max( 0, $cap - (int) round( (float) $used ) );
    }

    private static function count_words( string $text ): int {
        $plain = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $text ) ) );

        if ( '' === $plain ) {
            return 0;
        }

        return count( preg_split( '/\s+/', $plain ) );
    }

    private static function normalize_prompt_template( string $template ): string {
        $template = wp_check_invalid_utf8( $template );
        $template = preg_replace( "/\r\n?/", "\n", $template );

        return trim( $template );
    }

    private static function normalize_overrides( array $context ): array {
        $overrides = [];

        if ( array_key_exists( 'model', $context ) ) {
            $model = sanitize_text_field( (string) $context['model'] );
            if ( '' !== $model ) {
                $overrides['model'] = $model;
            }
        }

        if ( array_key_exists( 'generation_id', $context ) ) {
            $generation_id = sanitize_text_field( (string) $context['generation_id'] );
            if ( '' !== $generation_id ) {
                $overrides['generation_id'] = $generation_id;
            }
        }

        if ( array_key_exists( 'max_output_tokens', $context ) ) {
            $max_output_tokens = absint( $context['max_output_tokens'] );
            if ( $max_output_tokens > 0 ) {
                $overrides['max_output_tokens'] = $max_output_tokens;
            }
        }

        if ( array_key_exists( 'max_thinking_tokens', $context ) ) {
            $overrides['max_thinking_tokens'] = absint( $context['max_thinking_tokens'] );
        }

        if ( array_key_exists( 'temperature', $context ) && is_numeric( $context['temperature'] ) ) {
            $temp = (float) $context['temperature'];
            $overrides['temperature'] = min( 2.0, max( 0.0, $temp ) );
        }

        return $overrides;
    }

    private static function resolve_usage_estimate_model( string $provider_slug, array $overrides ): string {
        if ( ! empty( $overrides['model'] ) ) {
            return (string) $overrides['model'];
        }

        switch ( $provider_slug ) {
            case 'claude':
                return trim( (string) AIG_Settings::get( 'claude_model', '' ) );
            case 'openai':
                return trim( (string) AIG_Settings::get( 'openai_model', '' ) );
            case 'ollama':
                return trim( (string) AIG_Settings::get( 'ollama_model', '' ) );
            default:
                return '';
        }
    }
}
