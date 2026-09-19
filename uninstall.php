<?php
/**
 * Simple Series by KWWD - uninstall handler.
 *
 * By default nothing is removed when the plugin is deleted.
 * Data is only wiped when the user enabled
 * "Remove all plugin data when the plugin is deleted" in
 * Settings > General Settings before uninstalling.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$general_options = get_option('kwwd_series_general_settings', array());
$remove_data = isset($general_options['remove_data_on_uninstall']) ? $general_options['remove_data_on_uninstall'] : '0';

if ($remove_data !== '1') {
    return;
}

$remove_images = isset($general_options['remove_series_images']) ? $general_options['remove_series_images'] : '0';

$options_to_delete = array(
    'kwwd_series_default_settings',
    'kwwd_series_page_settings',
    'kwwd_series_archive_settings',
    'kwwd_series_archive_order',
    'kwwd_series_slug_notice',
    'kwwd_series_version',
    'kwwd_series_general_settings',
);
foreach ($options_to_delete as $option_name) {
    delete_option($option_name);
}

delete_site_option('external_updates-kwwd-simple-series-for-wp');

$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_kwwd_series_%' OR meta_key LIKE '_kwwd_post_series_%'");

$series_ids = get_posts(array(
    'post_type' => 'kwwd_series',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'fields' => 'ids',
    'suppress_filters' => true,
));

if (!empty($series_ids)) {
    require_once ABSPATH . 'wp-admin/includes/post.php';

    $series_thumbnail_ids = array();
    if ($remove_images === '1') {
        foreach ($series_ids as $series_id) {
            $thumb_id = get_post_thumbnail_id($series_id);
            if ($thumb_id) {
                $series_thumbnail_ids[] = (int) $thumb_id;
            }
        }
        $series_thumbnail_ids = array_unique($series_thumbnail_ids);
    }

    foreach ($series_ids as $series_id) {
        wp_delete_post($series_id, true);
    }

    if ($remove_images === '1') {
        foreach ($series_thumbnail_ids as $attachment_id) {
            $references = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d",
                $attachment_id
            ));
            if ($references === 0) {
                wp_delete_attachment($attachment_id, true);
            }
        }
    }
}