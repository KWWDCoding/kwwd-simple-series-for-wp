<?php
/**
 * KWWD Article Series - Admin Series List Page
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Article Series', 'kwwd-simple-series'); ?>
        <a href="<?php echo admin_url('post-new.php?post_type=kwwd_series'); ?>" class="page-title-action">
            <?php esc_html_e('Add New Series', 'kwwd-simple-series'); ?>
        </a>
    </h1>
    
    <?php if (!empty($series_list)) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Series Title', 'kwwd-simple-series'); ?></th>
                    <th><?php esc_html_e('Posts/Pages', 'kwwd-simple-series'); ?></th>
                    <th><?php esc_html_e('Date', 'kwwd-simple-series'); ?></th>
                    <th><?php esc_html_e('Actions', 'kwwd-simple-series'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($series_list as $series) : 
                    $post_ids = get_post_meta($series->ID, '_kwwd_series_posts', true);
                    $count = is_array($post_ids) ? count($post_ids) : 0;
                ?>
                    <tr>
                        <td class="row-title">
                            <a href="<?php echo get_edit_post_link($series->ID); ?>">
                                <?php echo esc_html($series->post_title); ?>
                            </a>
                        </td>
                        <td>
                            <span class="post-count"><?php echo esc_html($count); ?></span>
                        </td>
                        <td>
                            <?php echo esc_html(get_the_date('', $series)); ?>
                        </td>
                        <td>
                            <a href="<?php echo get_edit_post_link($series->ID); ?>" class="button button-small">
                                <?php esc_html_e('Edit', 'kwwd-simple-series'); ?>
                            </a>
                            <?php 
                            if ($count > 0) {
                                $first_post_id = is_array($post_ids) ? reset($post_ids) : 0;
                                if ($first_post_id) {
                                    echo ' <a href="' . esc_url(get_permalink($first_post_id)) . '" class="button button-small" target="_blank">';
                                    esc_html_e('View First Post', 'kwwd-simple-series');
                                    echo '</a>';
                                }
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <div class="kwwd-series-empty">
            <span class="dashicons dashicons-book"></span>
            <h2><?php esc_html_e('No Series Yet', 'kwwd-simple-series'); ?></h2>
            <p><?php esc_html_e('Create your first article series to group related posts and pages together.', 'kwwd-simple-series'); ?></p>
            <a href="<?php echo admin_url('post-new.php?post_type=kwwd_series'); ?>" class="button button-primary">
                <?php esc_html_e('Create Your First Series', 'kwwd-simple-series'); ?>
            </a>
        </div>
    <?php endif; ?>
    
    <div class="kwwd-series-help" style="margin-top: 30px; padding: 20px; background: #f6f7f7; border-radius: 4px;">
        <h3><?php esc_html_e('Quick Guide', 'kwwd-simple-series'); ?></h3>
        <ol>
            <li><?php esc_html_e('Click "Add New Series" to create a new series with a title.', 'kwwd-simple-series'); ?></li>
            <li><?php esc_html_e('Add posts/pages to the series using the drag-and-drop interface in the Series editor.', 'kwwd-simple-series'); ?></li>
            <li><?php esc_html_e('Drag posts to reorder them within the series.', 'kwwd-simple-series'); ?></li>
            <li><?php esc_html_e('Customize the appearance (colors, font size, layout) in the "Series Display Settings" box.', 'kwwd-simple-series'); ?></li>
            <li><?php esc_html_e('Each post in the series will automatically display the series information below the content.', 'kwwd-simple-series'); ?></li>
        </ol>
        <h4><?php esc_html_e('Shortcode Usage', 'kwwd-simple-series'); ?></h4>
        <p><code>[simple_series id="123"]</code> - <?php esc_html_e('Display a series anywhere using this shortcode (replace 123 with your Series ID).', 'kwwd-simple-series'); ?></p>
    </div>
</div>

<style>
.kwwd-series-empty {
    text-align: center;
    padding: 60px 20px;
}
.kwwd-series-empty .dashicons {
    font-size: 64px;
    width: 64px;
    height: 64px;
    color: #c2c5ca;
}
.kwwd-series-help {
    max-width: 800px;
}
.kwwd-series-help ol {
    line-height: 1.8;
}
.kwwd-series-help code {
    background: #fff;
    padding: 2px 8px;
    border-radius: 3px;
    border: 1px solid #ddd;
}
</style>