jQuery(document).ready(function($){

    // Predefined icons accordion
    $('.lumn-utilites-admin-accordion-header').click(function() {
        $(this).parent().toggleClass('active');
    });

    // Copy-to-clipboard controls (Tracking Debugger's Event Catalog / GTM
    // Guide tabs - lumn_ut_render_copy_button() in
    // admin/tracking-debugger-page.php). Falls back to a manual-select
    // prompt on browsers/contexts without the Clipboard API (e.g. no
    // secure context).
    $(document).on('click', '.lumn-ut-copy-btn', function () {
        var $btn = $(this);
        var value = $btn.data('copy-value');
        var originalText = $btn.text();

        function showCopied() {
            $btn.text('Copied!');
            setTimeout(function () { $btn.text(originalText); }, 1500);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(String(value)).then(showCopied, function () {
                window.prompt('Copy this value:', value);
            });
        } else {
            window.prompt('Copy this value:', value);
        }
    });
});