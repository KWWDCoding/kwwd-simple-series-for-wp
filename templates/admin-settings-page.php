<?php
/**
 * KWWD Article Series - Admin Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$options = get_option('kwwd_series_settings', array());
?>

<div class="wrap">
    <h1><?php esc_html_e('Series Settings', 'kwwd-simple-series'); ?></h1>
    
    <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Settings saved successfully.', 'kwwd-simple-series'); ?></p>
        </div>
    <?php endif; ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('kwwd_series_settings_save'); ?>
        <input type="hidden" name="kwwd_series_settings_save" value="1" />
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Default Display Position', 'kwwd-simple-series'); ?></label>
                    </th>
                    <td>
                        <select name="kwwd_series_settings[display_position]" id="kwwd_series_display_position">
                            <option value="after_content" <?php selected($options['display_position'], 'after_content'); ?>><?php esc_html_e('After Content', 'kwwd-simple-series'); ?></option>
                            <option value="before_content" <?php selected($options['display_position'], 'before_content'); ?>><?php esc_html_e('Before Content', 'kwwd-simple-series'); ?></option>
                            <option value="both" <?php selected($options['display_position'], 'both'); ?>><?php esc_html_e('Both', 'kwwd-simple-series'); ?></option>
                            <option value="none" <?php selected($options['display_position'], 'none'); ?>><?php esc_html_e('None (use shortcode)', 'kwwd-simple-series'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Where to display the series on posts and pages.', 'kwwd-simple-series'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Default Background Color', 'kwwd-simple-series'); ?></label>
                    </th>
                    <td>
                        <input type="color" name="kwwd_series_settings[default_bg_color]" value="<?php echo esc_attr($options['default_bg_color'] ?? '#f5f5f5'); ?>" />
                        <p class="description"><?php esc_html_e('Default background color for series containers.', 'kwwd-simple-series'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Default Font Size', 'kwwd-simple-series'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="kwwd_series_settings[default_font_size]" value="<?php echo esc_attr($options['default_font_size'] ?? '16'); ?>" min="10" max="32" style="width: 80px" />
                        <span>px</span>
                        <p class="description"><?php esc_html_e('Default font size for series text.', 'kwwd-simple-series'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e('Display Options', 'kwwd-simple-series'); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php esc_html_e('Display Options', 'kwwd-simple-series'); ?></legend>
                            
                            <label>
                                <input type="checkbox" name="kwwd_series_settings[show_post_count]" value="1" <?php checked($options['show_post_count'] ?? '1', '1'); ?> />
                                <?php esc_html_e('Show post count', 'kwwd-simple-series'); ?>
                            </label>
                            <br />
                            
                            <label>
                                <input type="checkbox" name="kwwd_series_settings[link_posts]" value="1" <?php checked($options['link_posts'] ?? '1', '1'); ?> />
                                <?php esc_html_e('Link series items to posts/pages', 'kwwd-simple-series'); ?>
                            </label>
                            <br />
                            
                            <label>
                                <input type="checkbox" name="kwwd_series_settings[open_new_tab]" value="1" <?php checked($options['open_new_tab'] ?? '1', '1'); ?> />
                                <?php esc_html_e('Open links in new tab', 'kwwd-simple-series'); ?>
                            </label>
                            <br />
                            
                            <label>
                                <input type="checkbox" name="kwwd_series_settings[show_thumbnails]" value="1" <?php checked($options['show_thumbnails'] ?? '0', '1'); ?> />
                                <?php esc_html_e('Show post thumbnails in series list', 'kwwd-simple-series'); ?>
                            </label>
                            <br />
                            
                            <label>
                                <input type="checkbox" name="kwwd_series_settings[allow_gutenberg]" value="1" <?php checked($options['allow_gutenberg'] ?? '1', '1'); ?> />
                                <?php esc_html_e('Allow selecting series in Gutenberg editor', 'kwwd-simple-series'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Save Changes', 'kwwd-simple-series'); ?>" />
        </p>
    </form>
    
    <div class="kwwd-series-info" style="margin-top: 40px; padding: 20px; background: #f6f7f7; border-radius: 4px;">
        <h3><?php esc_html_e('How It Works', 'kwwd-simple-series'); ?></h3>
        <ol>
            <li><?php esc_html_e('Create a new Series from the Series menu in the admin sidebar.', 'kwwd-simple-series'); ?></li>
            <li><?php esc_html_e('Add posts/pages to your series in the Series editor.', 'kwwd-simple-series'); ?></li>
            <li><?php esc_html_e('Each post in the series will automatically display the series listing.', 'kwwd-simple-series'); ?></li>
            <li><?php esc_html_e('Use the shortcode [simple_series id="X"] to display a series anywhere.', 'kwwd-simple-series'); ?></li>
        </ol>
        
        <h3><?php esc_html_e('Assigning a Series to a Post', 'kwwd-simple-series'); ?></h3>
        <p><?php esc_html_e('To assign a series to a post or page, edit the post and look for the "Series" meta box in the sidebar, or use the Gutenberg block if enabled in settings.', 'kwwd-simple-series'); ?></p>
        
        <h3><?php esc_html_e('Per-Series Customization', 'kwwd-simple-series'); ?></h3>
        <p><?php esc_html_e('Each series can have its own background color, font size, layout, and other display settings. Edit a series to customize its appearance.', 'kwwd-simple-series'); ?></p>
    </div>
</div>

<style>
.kwwd-series-info {
    max-width: 800px;
}
.kwwd-series-info ol {
    line-height: 1.8;
}
.kwwd-series-info h3 {
    margin-top: 20px;
}
.kwwd-series-info h3:first-child {
    margin-top: 0;
}
</style>