jQuery(document).ready(function($){

    // Predefined icons accordion
    $('.lumn-utilities-admin-accordion-header').click(function() {
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

    // Developers page (admin/dev-notes-page.php) - Edit/Cancel toggles the
    // profile card and rules panel between their read view and their form,
    // without a page reload. Nothing here changes what gets submitted or
    // saved - it only shows/hides markup that's already in the page.
    $(document).on('click', '.lumn-ut-dn-edit-toggle', function () {
        $(this).closest('.lumn-ut-dn-card').addClass('lumn-ut-dn-editing');
    });
    $(document).on('click', '.lumn-ut-dn-edit-cancel', function () {
        var $card = $(this).closest('.lumn-ut-dn-card');
        $card.removeClass('lumn-ut-dn-editing');
        $card.find('form.lumn-ut-dn-edit')[0].reset();
    });

    // Generic show/hide for the dependency and known-issue add/edit forms,
    // keyed by data-lumn-ut-dn-target pointing at the target's id.
    $(document).on('click', '.lumn-ut-dn-toggle-target', function () {
        var targetId = $(this).data('lumn-ut-dn-target');
        if (targetId) {
            $('#' + targetId).toggleClass('lumn-ut-dn-visible');
        }
    });

    // Manual "Refresh now" - fires the same detection routine the daily
    // cron event runs, via an authenticated REST call, then reloads the
    // page to show the new results. Never runs on page load.
    $(document).on('click', '.lumn-ut-dn-refresh-btn', function () {
        if (typeof lumnUtDevNotes === 'undefined') {
            return;
        }
        var $btn = $(this);
        var originalText = $btn.text();
        $btn.prop('disabled', true).text(lumnUtDevNotes.refreshingText);

        $.ajax({
            url: lumnUtDevNotes.restUrl,
            method: 'POST',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', lumnUtDevNotes.nonce);
            }
        }).done(function () {
            window.location.reload();
        }).fail(function () {
            window.alert(lumnUtDevNotes.refreshErrorText);
            $btn.prop('disabled', false).text(originalText || lumnUtDevNotes.refreshText);
        });
    });
});