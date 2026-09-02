<?php
namespace Lumn\Utilities;

/**
 * Centralized defaults for the Developers page's Dependencies table, keyed
 * by plugin slug exactly as WordPress's get_plugins() keys it
 * ('folder-name/main-file.php'). This plugin is deployed identically to
 * every site via git, so editing this list and pushing to `test`/`main` is
 * how you change a default across every site at once - e.g. "we own
 * Formidable Pro on every site" instead of setting licence ownership and
 * notes individually on 150+ sites.
 *
 * To find the exact slug for a plugin: open its row on the Developers page
 * (LUMN Utilities > Developers > Dependencies) on any site that has it
 * installed - the slug is printed under the plugin name with a Copy button.
 *
 * A site can still override any individual field for itself (Dependencies
 * table > Edit on that plugin's row). Leaving a field on that form blank
 * keeps it tracking the default below; typing something in overrides it
 * for that site only, and keeps tracking that override even if the
 * default here later changes - see
 * lumn_ut_dev_notes_get_plugin_dependencies() in register/dev-notes.php
 * for exactly how the two are merged.
 *
 * 'licence_ownership' is one of 'ours', 'client', or 'none'; omit the key
 * entirely (or a whole slug's entry) for anything not covered here - it
 * falls back to 'none' with no default expiry/notes, same as before this
 * list existed.
 */
function lumn_ut_dev_notes_dependency_defaults() {
    return array(
        // Formidable Pro requires the free Formidable plugin to also be
        // installed, so both show up as separate rows in get_plugins() -
        // 'formidable-pro/formidable-pro.php' is a best guess at Pro's
        // exact main file; confirm it (and the free plugin's slug below)
        // against the Copy button on a real site's Dependencies row before
        // relying on either - a wrong slug here just means that row keeps
        // showing "None" instead of silently applying to the wrong plugin.
        'formidable-pro/formidable-pro.php' => array(
            'licence_ownership' => 'ours',
            'licence_expiry' => '2027-02-03',
            'notes' => __("LUMN primarily uses Formidable Pro for it's conditional logic features.", 'lumn-utilities'),
        ),
        'formidable/formidable.php' => array(
            'licence_ownership' => 'none',
            'notes' => __('Free version, required by Formidable Pro to be installed alongside it.', 'lumn-utilities'),
        ),
    );
}
