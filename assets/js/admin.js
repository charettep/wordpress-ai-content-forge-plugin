/* AI Content Forge — Admin JS */
/* global acfAdmin, jQuery */
jQuery( function ( $ ) {
    const { restUrl, nonce, i18n } = acfAdmin;

    // ── Provider card selection highlight ────────────────────────────────────
    $( '.acf-provider-card input[type="radio"]' ).on( 'change', function () {
        $( '.acf-provider-card' ).removeClass( 'selected' );
        $( this ).closest( '.acf-provider-card' ).addClass( 'selected' );
    } );

    // ── Test connection ───────────────────────────────────────────────────────
    $( '.acf-test-btn' ).on( 'click', function () {
        const $btn    = $( this );
        const slug    = $btn.data( 'provider' );
        const $result = $( '#test-' + slug );

        $btn.prop( 'disabled', true ).text( i18n.testing );
        $result.text( '' ).removeClass( 'success error' );

        $.ajax( {
            url:         restUrl + '/test-provider',
            method:      'POST',
            contentType: 'application/json',
            beforeSend:  function ( xhr ) { xhr.setRequestHeader( 'X-WP-Nonce', nonce ); },
            data:        JSON.stringify( { provider: slug } ),
        } )
        .done( function ( res ) {
            $result.text( i18n.success ).addClass( 'success' );
        } )
        .fail( function ( xhr ) {
            const msg = xhr.responseJSON?.message || i18n.fail;
            $result.text( i18n.fail + ': ' + msg ).addClass( 'error' );
        } )
        .always( function () {
            $btn.prop( 'disabled', false ).text( 'Test Connection' );
        } );
    } );
} );
