/**
 * AI Content Forge — Gutenberg Sidebar Plugin
 *
 * Build: cd gutenberg && npm install && npm run build
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import {
    Button, SelectControl, TextControl, TextareaControl,
    Notice, Spinner, Panel, PanelBody, PanelRow,
} from '@wordpress/components';
import { useState, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const { restNamespace, settings, typeLabels } = window.acfGutenberg;

// ── Tone options ──────────────────────────────────────────────────────────────
const TONE_OPTIONS = [
    { value: 'professional', label: 'Professional' },
    { value: 'conversational', label: 'Conversational' },
    { value: 'authoritative', label: 'Authoritative' },
    { value: 'friendly', label: 'Friendly' },
    { value: 'humorous', label: 'Humorous' },
    { value: 'persuasive', label: 'Persuasive' },
];

const LANG_OPTIONS = [
    { value: 'English', label: 'English' },
    { value: 'French', label: 'French (Français)' },
    { value: 'Spanish', label: 'Spanish (Español)' },
    { value: 'German', label: 'German (Deutsch)' },
    { value: 'Portuguese', label: 'Portuguese' },
    { value: 'Italian', label: 'Italian (Italiano)' },
];

const PROVIDER_OPTIONS = [
    { value: '',       label: `Default (${settings.default_provider})` },
    { value: 'claude', label: '🟠 Anthropic Claude' },
    { value: 'openai', label: '🟢 OpenAI' },
    { value: 'ollama', label: '🔵 Ollama (Local)' },
];

const TYPE_OPTIONS = Object.entries( typeLabels ).map( ( [ value, label ] ) => ( { value, label } ) );

// ── Helper: apply generated content to post ───────────────────────────────────
function useApplyResult() {
    const { editPost } = useDispatch( 'core/editor' );
    const { resetBlocks } = useDispatch( 'core/block-editor' );
    const { createBlock } = window.wp.blocks;

    return useCallback( ( type, content ) => {
        switch ( type ) {
            case 'post_content': {
                // Insert as raw HTML block
                const block = createBlock( 'core/html', { content } );
                resetBlocks( [ block ] );
                break;
            }
            case 'seo_title':
                editPost( { title: content } );
                break;
            case 'excerpt':
                editPost( { excerpt: content } );
                break;
            case 'meta_description':
                // Store in post meta — requires REST support enabled for the meta key
                editPost( { meta: { _acf_meta_description: content } } );
                break;
            default:
                break;
        }
    }, [ editPost, resetBlocks ] );
}

// ── Main sidebar component ────────────────────────────────────────────────────
function AcfSidebar() {
    const [type,     setType]     = useState( 'post_content' );
    const [provider, setProvider] = useState( '' );
    const [keywords, setKeywords] = useState( '' );
    const [tone,     setTone]     = useState( 'professional' );
    const [language, setLanguage] = useState( 'English' );
    const [result,   setResult]   = useState( '' );
    const [loading,  setLoading]  = useState( false );
    const [error,    setError]    = useState( '' );
    const [copied,   setCopied]   = useState( false );

    const { postTitle, postType, postContent } = useSelect( ( select ) => {
        const editor = select( 'core/editor' );
        return {
            postTitle:   editor.getEditedPostAttribute( 'title' ) || '',
            postType:    editor.getCurrentPostType() || 'post',
            postContent: editor.getEditedPostAttribute( 'content' ) || '',
        };
    }, [] );

    const applyResult = useApplyResult();

    const generate = async () => {
        setLoading( true );
        setError( '' );
        setResult( '' );
        setCopied( false );

        try {
            const res = await apiFetch( {
                path:   `/${restNamespace}/generate`,
                method: 'POST',
                data: {
                    type,
                    provider,
                    title:            postTitle,
                    keywords,
                    tone,
                    language,
                    post_type:        postType,
                    existing_content: postContent,
                },
            } );

            if ( res.success ) {
                setResult( res.result );
            } else {
                setError( res.message || __( 'Unknown error', 'ai-content-forge' ) );
            }
        } catch ( e ) {
            setError( e?.message || __( 'Request failed', 'ai-content-forge' ) );
        } finally {
            setLoading( false );
        }
    };

    const copyToClipboard = () => {
        navigator.clipboard.writeText( result ).then( () => {
            setCopied( true );
            setTimeout( () => setCopied( false ), 2000 );
        } );
    };

    return (
        <Panel>
            {/* ── Generate ───────────────────────────── */}
            <PanelBody title={ __( 'Generate', 'ai-content-forge' ) } initialOpen={ true }>

                <PanelRow>
                    <SelectControl
                        label={ __( 'Content Type', 'ai-content-forge' ) }
                        value={ type }
                        options={ TYPE_OPTIONS }
                        onChange={ setType }
                    />
                </PanelRow>

                <PanelRow>
                    <SelectControl
                        label={ __( 'AI Provider', 'ai-content-forge' ) }
                        value={ provider }
                        options={ PROVIDER_OPTIONS }
                        onChange={ setProvider }
                    />
                </PanelRow>

                <PanelRow>
                    <TextControl
                        label={ __( 'Keywords / Topic hints', 'ai-content-forge' ) }
                        value={ keywords }
                        onChange={ setKeywords }
                        placeholder="e.g. WordPress, AI, automation"
                    />
                </PanelRow>

                <PanelRow>
                    <SelectControl
                        label={ __( 'Tone', 'ai-content-forge' ) }
                        value={ tone }
                        options={ TONE_OPTIONS }
                        onChange={ setTone }
                    />
                </PanelRow>

                <PanelRow>
                    <SelectControl
                        label={ __( 'Language', 'ai-content-forge' ) }
                        value={ language }
                        options={ LANG_OPTIONS }
                        onChange={ setLanguage }
                    />
                </PanelRow>

                <PanelRow>
                    <Button
                        variant="primary"
                        onClick={ generate }
                        disabled={ loading }
                        style={ { width: '100%', justifyContent: 'center' } }
                    >
                        { loading
                            ? <><Spinner /> { __( 'Generating…', 'ai-content-forge' ) }</>
                            : __( '⚡ Generate', 'ai-content-forge' )
                        }
                    </Button>
                </PanelRow>

                { error && (
                    <Notice status="error" isDismissible={ false }>
                        { error }
                    </Notice>
                ) }
            </PanelBody>

            {/* ── Result ─────────────────────────────── */}
            { result && (
                <PanelBody title={ __( 'Result', 'ai-content-forge' ) } initialOpen={ true }>
                    <PanelRow>
                        <TextareaControl
                            value={ result }
                            onChange={ setResult }
                            rows={ 10 }
                            style={ { fontFamily: 'monospace', fontSize: '12px' } }
                        />
                    </PanelRow>
                    <PanelRow>
                        <div style={ { display: 'flex', gap: '8px', width: '100%' } }>
                            <Button
                                variant="secondary"
                                onClick={ copyToClipboard }
                                style={ { flex: 1 } }
                            >
                                { copied ? __( '✓ Copied!', 'ai-content-forge' ) : __( 'Copy', 'ai-content-forge' ) }
                            </Button>
                            <Button
                                variant="primary"
                                onClick={ () => applyResult( type, result ) }
                                style={ { flex: 1 } }
                            >
                                { __( 'Apply to Post', 'ai-content-forge' ) }
                            </Button>
                        </div>
                    </PanelRow>
                </PanelBody>
            ) }
        </Panel>
    );
}

// ── Register sidebar ──────────────────────────────────────────────────────────
registerPlugin( 'ai-content-forge', {
    render: () => (
        <>
            <PluginSidebarMoreMenuItem target="ai-content-forge-sidebar">
                { __( 'AI Content Forge', 'ai-content-forge' ) }
            </PluginSidebarMoreMenuItem>
            <PluginSidebar
                name="ai-content-forge-sidebar"
                title={ __( 'AI Content Forge', 'ai-content-forge' ) }
                icon="superhero-alt"
            >
                <AcfSidebar />
            </PluginSidebar>
        </>
    ),
} );
