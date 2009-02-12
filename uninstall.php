<?php

    global $wpdb;
    delete_option('micropoll_options');
    $wpdb->query(sprintf("ALTER TABLE %s DROP `%s`", $wpdb->posts, $wpdb->escape('micropoll_inactive')));
