<?php
/**
 * Fires only on a real uninstall (delete via wp-admin, never on plain
 * deactivate) - see register/dev-notes.php's "Remove the cap on uninstall,
 * not on deactivation" requirement for the Developers tab.
 *
 * Deliberately narrow: this only removes the lumn_manage_dev_notes
 * capability grant. It does not delete the site profile, dev-note posts
 * (rules/dependencies/issues/log), or any other plugin data - none of
 * that was asked for, and silently deleting a client's recorded context
 * on uninstall would be a surprising, hard-to-reverse side effect.
 */

namespace Lumn\Utilities;

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$role = get_role('company_super_admin');
if ($role && $role->has_cap('lumn_manage_dev_notes')) {
    $role->remove_cap('lumn_manage_dev_notes');
}

// Reset the versioned-grant marker so a future reinstall re-runs the
// idempotent grant in register/dev-notes.php from scratch, rather than
// believing the capability is already up to date.
delete_option('lumn_ut_caps_version');
