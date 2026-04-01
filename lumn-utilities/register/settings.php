<?php

add_action('admin_menu', function () {

    add_menu_page(
        'Manage Patterns',
        'Patterns',
        'edit_theme_options',
        'edit.php?post_type=wp_block',
        '',
        'dashicons-layout',
        61
    );

});
