<?php
/**
 * Plugin Name: Simple Series by KWWD
 * Plugin URI: https://kwwdcoding.github.io/kwwd-simple-series.html
 * Description: Create and manage series which allows you to collate posts and pages together to enable users to view all related posts
 * Version: 1.5.4
 * Author:      KWWD
 * Author URI: https://www.kwwd.co.uk
 * License:     GPL3
 * Licence URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Update URI: https://github.com/KWWDCoding/kwwd-simple-series-for-wp
 */

if (!defined('ABSPATH')) {
    exit;
}

/**************************************************************
 * UPDATE CHECKER (GITHUB Method)
 *************************************************************/
// Use the RAW content URL from GitHub
$githubAssets = 'https://raw.githubusercontent.com/KWWDCoding/kwwd-simple-series-for-wp/main/assets/';

require_once plugin_dir_path(__FILE__) . 'includes/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/KWWDCoding/kwwd-simple-series-for-wp/',
    __FILE__,
    'kwwd-simple-series-for-wp'
);
// Since you're using GitHub's "Releases" feature to host the ZIPs:
$myUpdateChecker->getVcsApi()->enableReleaseAssets();

/** PLUGIN ICONS ***/
$myUpdateChecker->addResultFilter(function($info) use ($githubAssets) {
    if ($info) {
        $info->icons = array(
            '1x'      => $githubAssets . 'icon-128x128.png',
            '2x'      => $githubAssets . 'icon-256x256.png', // Optional
            'default' => $githubAssets . 'icon-128x128.png',
        );
    }
    return $info;
});
/***************** END PLUGIN UPDATE **************************/


/*************************************************************
 * Plugin Links
 ************************************************************/
add_filter( 'plugin_row_meta', 'kwwd_series_custom_meta_links', 10, 2 );

function kwwd_series_custom_meta_links( $links, $file ) {
    if ( $file !== plugin_basename( __FILE__ ) ) {
        return $links;
    }

    $settings_url = admin_url( 'admin.php?page=kwwd-simple-series-settings' );
    $links['settings'] = '<a href="' . esc_url( $settings_url ) . '">Settings</a>';
    $links['changelog'] = '<a href="https://github.com/KWWDCoding/kwwd-simple-series-for-wp/releases" target="_blank">Changelog</a>';
    $links['support'] = '<a href="https://support.kwwd.co.uk/" target="_blank">Support</a>';
    return $links;
}


define('KWWD_SERIES_VERSION', '1.5.4');
define('KWWD_SERIES_PATH', plugin_dir_path(__FILE__));
define('KWWD_SERIES_URL', plugin_dir_url(__FILE__));
define('KWWD_SERIES_ASSETS_URL', KWWD_SERIES_URL . 'assets');

final class KWWD_Series_Plugin {

    private static $instance = null;
    private $series_cpt = 'kwwd_series';
    private $series_meta_key = '_kwwd_series_posts';
    private $series_order_meta_key = '_kwwd_series_post_orders';
    private static $output_styles = array();

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('init', array($this, 'register_series_post_type'), 0);
        add_action('init', array($this, 'maybe_flush_rewrites'), 5);
        add_filter('post_updated_messages', array($this, 'series_updated_messages'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_notices', array($this, 'admin_slug_notices'));
        add_action('save_post', array($this, 'save_series_meta'), 10, 2);
        add_action('save_post', array($this, 'save_post_series_assignment'), 11, 2);
        add_action('add_meta_boxes', array($this, 'add_series_meta_boxes'));
        add_action('add_meta_boxes', array($this, 'add_post_series_meta_box'));
        add_action('wp_ajax_kwwd_series_update_post_order', array($this, 'ajax_update_post_order'));
        add_action('wp_ajax_kwwd_series_create', array($this, 'ajax_create_series'));
        add_action('the_content', array($this, 'display_series_on_content'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_filter('template_include', array($this, 'series_template_include'));
        add_action('wp_footer', array($this, 'print_collapsible_script'), 99);
        add_shortcode('simple_series', array($this, 'shortcode_series'));
    }

    public static function activate() {
        self::get_instance()->ensure_slug_free(true);
        flush_rewrite_rules();
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public function maybe_flush_rewrites() {
        if (get_option('kwwd_series_version') !== KWWD_SERIES_VERSION) {
            update_option('kwwd_series_version', KWWD_SERIES_VERSION);
            flush_rewrite_rules();
        }
    }

    public function get_series_slug() {
        $settings = get_option('kwwd_series_page_settings', array());
        $slug = isset($settings['series_page_slug']) ? sanitize_title($settings['series_page_slug']) : '';
        return ($slug !== '') ? $slug : 'series';
    }

    public function register_series_post_type() {
        register_post_type($this->series_cpt, array(
            'label' => __('Series', 'kwwd-simple-series'),
            'labels' => array(
                'name' => __('Series', 'kwwd-simple-series'),
                'singular_name' => __('Series', 'kwwd-simple-series'),
                'add_new' => __('Add New', 'kwwd-simple-series'),
                'add_new_item' => __('Add New Series', 'kwwd-simple-series'),
                'edit_item' => __('Edit Series', 'kwwd-simple-series'),
                'new_item' => __('New Series', 'kwwd-simple-series'),
                'view_item' => __('View Series', 'kwwd-simple-series'),
                'search_items' => __('Search Series', 'kwwd-simple-series'),
                'not_found' => __('No series found.', 'kwwd-simple-series'),
                'not_found_in_trash' => __('No series found in Trash.', 'kwwd-simple-series'),
                'all_items' => __('All Series', 'kwwd-simple-series'),
                'menu_name' => __('Series', 'kwwd-simple-series'),
                'item_published' => __('Series published.', 'kwwd-simple-series'),
                'item_published_privately' => __('Series published privately.', 'kwwd-simple-series'),
                'item_reverted_to_draft' => __('Series reverted to draft.', 'kwwd-simple-series'),
                'item_scheduled' => __('Series scheduled.', 'kwwd-simple-series'),
                'item_updated' => __('Series updated.', 'kwwd-simple-series'),
            ),
            'public' => false,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'menu_icon' => 'dashicons-book',
            'supports' => array('title', 'custom-fields', 'thumbnail'),
            'capability_type' => 'post',
            'capabilities' => array('edit_posts' => 'edit_posts', 'publish_posts' => 'publish_posts'),
            'map_meta_cap' => true,
            'has_archive' => true,
            'exclude_from_search' => true,
            'rewrite' => array('slug' => $this->get_series_slug(), 'with_front' => false),
        ));
    }

    public function ensure_slug_free($notify = false) {
        $current = $this->get_series_slug();
        $conflicts = $this->check_series_slug_conflicts($current);
        if (empty($conflicts)) {
            return false;
        }
        $fallback = $this->get_free_slug($current);
        $settings = wp_parse_args(get_option('kwwd_series_page_settings', array()), array(
            'series_page_slug' => 'series',
            'series_page_show_image' => '1',
            'series_page_layout' => 'list',
            'series_page_show_post_image' => '1',
            'series_page_show_description' => '1',
            'series_page_fallback_first_post_image' => '1'
        ));
        $settings['series_page_slug'] = $fallback;
        update_option('kwwd_series_page_settings', $settings);
        if ($notify) {
            update_option('kwwd_series_slug_notice', array('from' => $current, 'to' => $fallback, 'conflicts' => $conflicts));
        }
        return $fallback;
    }

    public function get_free_slug($base) {
        $base = sanitize_title($base);
        if ($base === '') $base = 'series';
        $candidate = $base;
        $i = 2;
        while (!empty($this->check_series_slug_conflicts($candidate))) {
            $candidate = $base . '-' . $i;
            $i++;
        }
        return $candidate;
    }

    public function check_series_slug_conflicts($slug) {
        $conflicts = array();
        $slug = sanitize_title($slug);
        if ($slug === '') return $conflicts;

        // Published posts and pages whose URL path equals or falls under the slug base.
        $items = get_posts(array(
            'post_type' => array('post', 'page'),
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'orderby' => 'ID',
            'order' => 'ASC',
        ));
        foreach ($items as $item) {
            $url = get_permalink($item->ID);
            if (!$url) continue;
            $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
            if ($path === '') continue;
            if ($path === $slug || strpos($path, $slug . '/') === 0) {
                $conflicts[] = array('type' => $item->post_type, 'id' => $item->ID, 'title' => $item->post_title, 'url' => $url);
            }
        }

        // Taxonomies whose rewrite base equals the slug.
        $taxonomies = get_taxonomies(array('public' => true), 'objects');
        foreach ($taxonomies as $tax) {
            $rewrite = $tax->rewrite;
            $base = (is_array($rewrite) && isset($rewrite['slug'])) ? trim($rewrite['slug'], '/') : '';
            if ($base !== '' && $base === $slug) {
                $conflicts[] = array('type' => 'taxonomy', 'id' => 0, 'title' => $tax->label, 'url' => '');
            }
        }

        return $conflicts;
    }

    public function admin_slug_notices() {
        if (!current_user_can('manage_options')) return;
        $notice = get_option('kwwd_series_slug_notice', false);
        if (!is_array($notice) || empty($notice)) return;
        echo '<div class="notice notice-warning is-dismissible"><p>' . sprintf(
            esc_html__('Simple Series: The URL slug "%1$s" is already in use by other content, so series pages are now using "%2$s". You can change this any time in Series → Settings → Series Page URL Slug.', 'kwwd-simple-series'),
            esc_html($notice['from']),
            esc_html($notice['to'])
        ) . '</p></div>';
    }

    public function series_updated_messages($messages) {
        global $post;

        $messages[$this->series_cpt] = array(
            0 => '',
            1 => __('Series updated.', 'kwwd-simple-series'),
            2 => __('Custom field updated.', 'kwwd-simple-series'),
            3 => __('Custom field deleted.', 'kwwd-simple-series'),
            4 => __('Series updated.', 'kwwd-simple-series'),
            5 => isset($_GET['revision']) ? sprintf(__('Series restored to revision from %s.', 'kwwd-simple-series'), wp_post_revision_title((int) $_GET['revision'], false)) : false,
            6 => __('Series published.', 'kwwd-simple-series'),
            7 => __('Series saved.', 'kwwd-simple-series'),
            8 => $post ? sprintf(__('Series submitted.', 'kwwd-simple-series'), '<a href="' . esc_url(get_permalink($post->ID)) . '">' . esc_html($post->post_title) . '</a>') : __('Series submitted.', 'kwwd-simple-series'),
            9 => isset($_GET['date']) ? sprintf(__('Series scheduled for: %s.', 'kwwd-simple-series'), '<strong>' . date_i18n('M j, Y @ G:i', strtotime($_GET['date'])) . '</strong>') : '',
            10 => __('Series draft updated.', 'kwwd-simple-series'),
        );

        return $messages;
    }

    public function add_admin_menu() {
        add_menu_page(__('Series', 'kwwd-simple-series'), __('Series', 'kwwd-simple-series'), 'edit_posts', 'kwwd-simple-series', array($this, 'render_series_admin_page'), 'dashicons-book', 50);
        add_submenu_page('kwwd-simple-series', __('All Series', 'kwwd-simple-series'), __('All Series', 'kwwd-simple-series'), 'edit_posts', 'kwwd-simple-series', array($this, 'render_series_admin_page'));
        add_submenu_page('kwwd-simple-series', __('Add New', 'kwwd-simple-series'), __('Add New', 'kwwd-simple-series'), 'edit_posts', 'post-new.php?post_type=' . $this->series_cpt);
        add_submenu_page('kwwd-simple-series', __('Settings', 'kwwd-simple-series'), __('Settings', 'kwwd-simple-series'), 'manage_options', 'kwwd-simple-series-settings', array($this, 'render_settings_page'));
    }

    public function render_settings_page() {
        $options = get_option('kwwd_series_default_settings', array(
            'display_position' => 'before_content',
            'series_page_link' => '1',
            'archive_page_link' => '1',
            'bg_color' => '#f5f5f5',
            'bg_opacity' => '0.9',
            'font_size' => '16',
            'text_color' => '#333333',
            'border_color' => '#dddddd',
            'border_width' => '1',
            'border_style' => 'solid',
            'border_radius' => '8',
            'list_style' => 'decimal',
            'padding' => '20',
            'collapsed' => '0'
        ));

        $page_options = get_option('kwwd_series_page_settings', array(
            'series_page_slug' => 'series',
            'series_page_show_image' => '1',
            'series_page_layout' => 'list',
            'series_page_show_post_image' => '1',
            'series_page_show_description' => '1',
            'series_page_fallback_first_post_image' => '1'
        ));

        $archive_options = get_option('kwwd_series_archive_settings', array(
            'archive_show_image' => '1',
            'archive_fallback_first_post_image' => '1',
            'archive_show_count' => '1',
            'archive_show_description' => '1',
            'archive_link_mode' => 'page',
            'archive_layout' => 'grid'
        ));

        $current_slug = isset($page_options['series_page_slug']) ? $page_options['series_page_slug'] : 'series';

        if (isset($_POST['save_defaults']) && check_admin_referer('kwwd_series_save_settings')) {
            $slug_ok = true;
            $old_slug = $current_slug;
            $new_slug = isset($_POST['series_page_slug']) ? sanitize_title(wp_unslash($_POST['series_page_slug'])) : '';
            if ($new_slug === '') $new_slug = 'series';

            if (!empty($this->check_series_slug_conflicts($new_slug))) {
                $suggestion = $this->get_free_slug($new_slug);
                echo '<div class="notice notice-error"><p>' . sprintf(
                    esc_html__('The URL slug "%1$s" is already in use by other content. The previous slug "%2$s" has been kept. Try a free slug such as "%3$s".', 'kwwd-simple-series'),
                    esc_html($new_slug),
                    esc_html($old_slug),
                    esc_html($suggestion)
                ) . '</p></div>';
                $new_slug = $old_slug;
                $slug_ok = false;
            } else {
                delete_option('kwwd_series_slug_notice');
            }

            $options = array(
                'display_position' => sanitize_text_field($_POST['display_position']),
                'series_page_link' => isset($_POST['series_page_link']) ? '1' : '0',
                'archive_page_link' => isset($_POST['archive_page_link']) ? '1' : '0',
                'bg_color' => sanitize_hex_color($_POST['bg_color']),
                'bg_opacity' => sanitize_text_field($_POST['bg_opacity']),
                'font_size' => intval($_POST['font_size']),
                'text_color' => sanitize_hex_color($_POST['text_color']),
                'border_color' => sanitize_hex_color($_POST['border_color']),
                'border_width' => intval($_POST['border_width']),
                'border_style' => sanitize_text_field($_POST['border_style']),
                'border_radius' => intval($_POST['border_radius']),
                'list_style' => sanitize_text_field($_POST['list_style']),
                'padding' => intval($_POST['padding']),
                'collapsed' => isset($_POST['collapsed']) ? '1' : '0'
            );
            update_option('kwwd_series_default_settings', $options);

            $layout = isset($_POST['series_page_layout']) ? $_POST['series_page_layout'] : 'list';
            $page_options = array(
                'series_page_slug' => $new_slug,
                'series_page_show_image' => isset($_POST['series_page_show_image']) ? '1' : '0',
                'series_page_layout' => in_array($layout, array('grid', 'list'), true) ? $layout : 'list',
                'series_page_show_post_image' => isset($_POST['series_page_show_post_image']) ? '1' : '0',
                'series_page_show_description' => isset($_POST['series_page_show_description']) ? '1' : '0',
                'series_page_fallback_first_post_image' => isset($_POST['series_page_fallback_first_post_image']) ? '1' : '0'
            );
            update_option('kwwd_series_page_settings', $page_options);

            $archive_link_mode = isset($_POST['archive_link_mode']) ? $_POST['archive_link_mode'] : 'page';
            $archive_layout = isset($_POST['archive_layout']) ? $_POST['archive_layout'] : 'grid';
            $archive_options = array(
                'archive_show_image' => isset($_POST['archive_show_image']) ? '1' : '0',
                'archive_fallback_first_post_image' => isset($_POST['archive_fallback_first_post_image']) ? '1' : '0',
                'archive_show_count' => isset($_POST['archive_show_count']) ? '1' : '0',
                'archive_show_description' => isset($_POST['archive_show_description']) ? '1' : '0',
                'archive_link_mode' => in_array($archive_link_mode, array('page', 'expand'), true) ? $archive_link_mode : 'page',
                'archive_layout' => in_array($archive_layout, array('grid', 'list'), true) ? $archive_layout : 'grid'
            );
            update_option('kwwd_series_archive_settings', $archive_options);

            if ($new_slug !== $old_slug) {
                flush_rewrite_rules();
            }

            if ($slug_ok) {
                echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'kwwd-simple-series') . '</p></div>';
            }

            $current_slug = isset($page_options['series_page_slug']) ? $page_options['series_page_slug'] : 'series';
        }

        $current_conflicts = $this->check_series_slug_conflicts($current_slug);
        if (!empty($current_conflicts)) {
            echo '<div class="notice notice-warning"><p>' . sprintf(
                esc_html__('The series URL slug "%1$s" currently conflicts with other content. Series URLs will fall back to "%2$s". Choose a different slug below to resolve this.', 'kwwd-simple-series'),
                esc_html($current_slug),
                esc_html($this->get_free_slug($current_slug))
            ) . '</p></div>';
        }

        echo '<div class="wrap"><h1>' . esc_html__('Series Settings', 'kwwd-simple-series') . '</h1>';
        echo '<form method="post">' . wp_nonce_field('kwwd_series_save_settings', '_wpnonce', true, false);
        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="#kwwd-tab-default" class="nav-tab nav-tab-active" data-tab="kwwd-tab-default">' . esc_html__('Default Display Settings', 'kwwd-simple-series') . '</a>';
        echo '<a href="#kwwd-tab-series-page" class="nav-tab" data-tab="kwwd-tab-series-page">' . esc_html__('Series Page Settings', 'kwwd-simple-series') . '</a>';
        echo '<a href="#kwwd-tab-series-archive" class="nav-tab" data-tab="kwwd-tab-series-archive">' . esc_html__('Series Archive Settings', 'kwwd-simple-series') . '</a>';
        echo '</h2>';

        echo '<div id="kwwd-tab-default" class="kwwd-settings-tab"><table class="form-table">';
        echo '<tr><th>' . esc_html__('Display Position', 'kwwd-simple-series') . '</th><td><select name="display_position">';
        echo '<option value="before_content" ' . selected($options['display_position'], 'before_content', false) . '>' . esc_html__('Above Post Content', 'kwwd-simple-series') . '</option>';
        echo '<option value="after_content" ' . selected($options['display_position'], 'after_content', false) . '>' . esc_html__('Below Post Content', 'kwwd-simple-series') . '</option>';
        echo '<option value="both" ' . selected($options['display_position'], 'both', false) . '>' . esc_html__('Both Above and Below', 'kwwd-simple-series') . '</option>';
        echo '<option value="none" ' . selected($options['display_position'], 'none', false) . '>' . esc_html__('None (use shortcode only)', 'kwwd-simple-series') . '</option>';
        echo '</select><p class="description" style="margin-top:4px;">' . esc_html__('Where to display the series on posts and pages.', 'kwwd-simple-series') . '</p></td></tr>';
        echo '<tr><th>' . esc_html__('Series Page Link', 'kwwd-simple-series') . '</th><td><label><input type="checkbox" name="series_page_link" value="1" ' . checked($options['series_page_link'], '1', false) . ' /> ' . esc_html__('Display a link to the series page on posts', 'kwwd-simple-series') . '</label><p class="description" style="margin-top:4px;">' . esc_html__('Adds a link to the dedicated series page at the bottom of the series container.', 'kwwd-simple-series') . '</p></td></tr>';
        echo '<tr><th>' . esc_html__('All Series Link', 'kwwd-simple-series') . '</th><td><label><input type="checkbox" name="archive_page_link" value="1" ' . checked($options['archive_page_link'], '1', false) . ' /> ' . esc_html__('Display a link to the series archive page on posts', 'kwwd-simple-series') . '</label><p class="description" style="margin-top:4px;">' . esc_html__('Adds a "View All Series" link at the bottom of the series container.', 'kwwd-simple-series') . '</p></td></tr>';
        echo '<tr><th>' . esc_html__('Background Color', 'kwwd-simple-series') . '</th><td><input type="color" name="bg_color" value="' . esc_attr($options['bg_color']) . '" /></td></tr>';
        echo '<tr><th>' . esc_html__('Background Opacity', 'kwwd-simple-series') . '</th><td><select name="bg_opacity">';
        for($i = 0; $i <= 1; $i += 0.05) {
            $val = number_format($i, 2);
            echo '<option value="' . esc_attr($val) . '" ' . selected($options['bg_opacity'], $val, false) . '>' . esc_html($val) . '</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th>' . esc_html__('Font Size (px)', 'kwwd-simple-series') . '</th><td><input type="number" name="font_size" value="' . esc_attr($options['font_size']) . '" min="10" max="32" style="width:60px" /></td></tr>';
        echo '<tr><th>' . esc_html__('Text Color', 'kwwd-simple-series') . '</th><td><input type="color" name="text_color" value="' . esc_attr($options['text_color']) . '" /></td></tr>';
        echo '<tr><th>' . esc_html__('Border Color', 'kwwd-simple-series') . '</th><td><input type="color" name="border_color" value="' . esc_attr($options['border_color']) . '" /></td></tr>';
        echo '<tr><th>' . esc_html__('Border Width (px)', 'kwwd-simple-series') . '</th><td><input type="number" name="border_width" value="' . esc_attr($options['border_width']) . '" min="0" max="10" style="width:60px" /></td></tr>';
        echo '<tr><th>' . esc_html__('Border Style', 'kwwd-simple-series') . '</th><td><select name="border_style">';
        echo '<option value="solid" ' . selected($options['border_style'], 'solid', false) . '>Solid</option>';
        echo '<option value="dotted" ' . selected($options['border_style'], 'dotted', false) . '>Dotted</option>';
        echo '<option value="dashed" ' . selected($options['border_style'], 'dashed', false) . '>Dashed</option>';
        echo '<option value="none" ' . selected($options['border_style'], 'none', false) . '>None</option>';
        echo '</select></td></tr>';
        echo '<tr><th>' . esc_html__('Border Radius (px)', 'kwwd-simple-series') . '</th><td><select name="border_radius">';
        for($i = 0; $i <= 10; $i++) {
            echo '<option value="' . esc_attr($i) . '" ' . selected($options['border_radius'], strval($i), false) . '>' . esc_html($i) . 'px</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th>' . esc_html__('List Style', 'kwwd-simple-series') . '</th><td><select name="list_style">';
        echo '<option value="none" ' . selected($options['list_style'], 'none', false) . '>None</option>';
        echo '<option value="decimal" ' . selected($options['list_style'], 'decimal', false) . '>1. 2. 3.</option>';
        echo '<option value="decimal-leading-zero" ' . selected($options['list_style'], 'decimal-leading-zero', false) . '>01. 02. 03.</option>';
        echo '<option value="lower-alpha" ' . selected($options['list_style'], 'lower-alpha', false) . '>a. b. c.</option>';
        echo '<option value="upper-alpha" ' . selected($options['list_style'], 'upper-alpha', false) . '>A. B. C.</option>';
        echo '<option value="lower-roman" ' . selected($options['list_style'], 'lower-roman', false) . '>i. ii. iii.</option>';
        echo '<option value="upper-roman" ' . selected($options['list_style'], 'upper-roman', false) . '>I. II. III.</option>';
        echo '<option value="lower-greek" ' . selected($options['list_style'], 'lower-greek', false) . '>alpha, beta, gamma</option>';
        echo '<option value="disc" ' . selected($options['list_style'], 'disc', false) . '>Disc (bullet)</option>';
        echo '<option value="circle" ' . selected($options['list_style'], 'circle', false) . '>Circle (bullet)</option>';
        echo '<option value="square" ' . selected($options['list_style'], 'square', false) . '>Square (bullet)</option>';
        echo '</select></td></tr>';
        echo '<tr><th>' . esc_html__('Padding (px)', 'kwwd-simple-series') . '</th><td><select name="padding">';
        for($i = 0; $i <= 40; $i += 5) {
            echo '<option value="' . esc_attr($i) . '" ' . selected($options['padding'], strval($i), false) . '>' . esc_html($i) . 'px</option>';
        }
        echo '</select></td></tr>';
        echo '<tr><th>' . esc_html__('Start Collapsed', 'kwwd-simple-series') . '</th><td><label><input type="checkbox" name="collapsed" value="1" ' . checked($options['collapsed'], '1', false) . ' /> ' . esc_html__('Series starts collapsed by default', 'kwwd-simple-series') . '</label></td></tr>';
        echo '</table></div>';

        $slug_field_note = sprintf(
            esc_html__('Series pages appear at %1$s and the series index at %2$s.', 'kwwd-simple-series'),
            '<code>' . esc_html(home_url('/' . $current_slug . '/{series-slug}/')) . '</code>',
            '<code>' . esc_html(home_url('/' . $current_slug . '/')) . '</code>'
        );
        if (!empty($current_conflicts)) {
            $slug_field_note .= ' ' . sprintf(esc_html__('This slug is currently in use - try "%s".', 'kwwd-simple-series'), esc_html($this->get_free_slug($current_slug)));
        }

        echo '<div id="kwwd-tab-series-page" class="kwwd-settings-tab" style="display:none;"><table class="form-table">';
        echo '<tr><th>' . esc_html__('Show Series Featured Image', 'kwwd-simple-series') . '</th><td><label><input type="checkbox" name="series_page_show_image" value="1" ' . checked($page_options['series_page_show_image'], '1', false) . ' /> ' . esc_html__('Display the series featured image at the top of the series page', 'kwwd-simple-series') . '</label><p class="description" style="margin-top:4px;"><label><input type="checkbox" name="series_page_fallback_first_post_image" value="1" ' . checked($page_options['series_page_fallback_first_post_image'], '1', false) . ' /> ' . esc_html__('Fallback to the featured image of the first post in the series that has one', 'kwwd-simple-series') . '</label></p></td></tr>';
        echo '<tr><th>' . esc_html__('Display Series Posts As', 'kwwd-simple-series') . '</th><td><select name="series_page_layout">';
        echo '<option value="list" ' . selected($page_options['series_page_layout'], 'list', false) . '>' . esc_html__('List', 'kwwd-simple-series') . '</option>';
        echo '<option value="grid" ' . selected($page_options['series_page_layout'], 'grid', false) . '>' . esc_html__('Grid', 'kwwd-simple-series') . '</option>';
        echo '</select></td></tr>';
        echo '<tr><th>' . esc_html__('Display Post Featured Image', 'kwwd-simple-series') . '</th><td><label><input type="checkbox" name="series_page_show_post_image" value="1" ' . checked($page_options['series_page_show_post_image'], '1', false) . ' /> ' . esc_html__('Show each post\'s featured image in the series list', 'kwwd-simple-series') . '</label></td></tr>';
        echo '<tr><th>' . esc_html__('Display Series Description', 'kwwd-simple-series') . '</th><td><label><input type="checkbox" name="series_page_show_description" value="1" ' . checked($page_options['series_page_show_description'], '1', false) . ' /> ' . esc_html__('Show the series description on the series page', 'kwwd-simple-series') . '</label></td></tr>';
        echo '</table></div>';

        echo '<div id="kwwd-tab-series-archive" class="kwwd-settings-tab" style="display:none;"><table class="form-table">';
        echo '<tr><th>' . esc_html__('Series Page URL Slug', 'kwwd-simple-series') . '</th><td><input type="text" name="series_page_slug" value="' . esc_attr($current_slug) . '" style="width:200px;" /><p class="description" style="margin-top:4px;">' . $slug_field_note . '</p></td></tr>';
        echo '<tr><th>' . esc_html__('Show Series Featured Image', 'kwwd-simple-series') . '</th><td><label><input type="checkbox" name="archive_show_image" value="1" ' . checked($archive_options['archive_show_image'], '1', false) . ' /> ' . esc_html__('Display the series featured image on archive cards', 'kwwd-simple-series') . '</label><p class="description" style="margin-top:4px;"><label><input type="checkbox" name="archive_fallback_first_post_image" value="1" ' . checked($archive_options['archive_fallback_first_post_image'], '1', false) . ' /> ' . esc_html__('Fallback to the featured image of the first post in the series that has one', 'kwwd-simple-series') . '</label></p></td></tr>';
        echo '<tr><th>' . esc_html__('Show Post Count', 'kwwd-simple-series') . '</th><td><label><input type="checkbox" name="archive_show_count" value="1" ' . checked($archive_options['archive_show_count'], '1', false) . ' /> ' . esc_html__('Display the number of posts on each card', 'kwwd-simple-series') . '</label></td></tr>';
        echo '<tr><th>' . esc_html__('Show Series Description', 'kwwd-simple-series') . '</th><td><label><input type="checkbox" name="archive_show_description" value="1" ' . checked($archive_options['archive_show_description'], '1', false) . ' /> ' . esc_html__('Display a description excerpt on each card', 'kwwd-simple-series') . '</label></td></tr>';
        echo '<tr><th>' . esc_html__('Display Archive As', 'kwwd-simple-series') . '</th><td><select name="archive_layout">';
        echo '<option value="grid" ' . selected($archive_options['archive_layout'], 'grid', false) . '>' . esc_html__('Grid', 'kwwd-simple-series') . '</option>';
        echo '<option value="list" ' . selected($archive_options['archive_layout'], 'list', false) . '>' . esc_html__('List', 'kwwd-simple-series') . '</option>';
        echo '</select></td></tr>';
        echo '<tr><th>' . esc_html__('Card Link Behavior', 'kwwd-simple-series') . '</th><td><select name="archive_link_mode">';
        echo '<option value="page" ' . selected($archive_options['archive_link_mode'], 'page', false) . '>' . esc_html__('Link to series page', 'kwwd-simple-series') . '</option>';
        echo '<option value="expand" ' . selected($archive_options['archive_link_mode'], 'expand', false) . '>' . esc_html__('Expandable list (posts shown inline)', 'kwwd-simple-series') . '</option>';
        echo '</select><p class="description" style="margin-top:4px;">' . esc_html__('When linking to series pages, an "All Series" back link appears on each series page.', 'kwwd-simple-series') . '</p></td></tr>';
        echo '</table></div>';

        echo '<p><input type="submit" name="save_defaults" class="button button-primary" value="' . esc_attr__('Save Settings', 'kwwd-simple-series') . '" /></p></form></div>';
        echo '<script>jQuery(document).ready(function($){function kwwdShowTab(t){$(".nav-tab-wrapper .nav-tab").removeClass("nav-tab-active");$(\'.nav-tab-wrapper .nav-tab[data-tab="\'+t+\'"]\').addClass("nav-tab-active");$(".kwwd-settings-tab").hide();$("#"+t).show();}var saved=sessionStorage.getItem("kwwd_series_settings_tab");if(saved){kwwdShowTab(saved);}$(".nav-tab-wrapper .nav-tab").on("click",function(e){e.preventDefault();var t=$(this).data("tab");kwwdShowTab(t);sessionStorage.setItem("kwwd_series_settings_tab",t);});});</script>';
    }

    public function render_series_admin_page() {
        $series_list = get_posts(array('post_type' => $this->series_cpt, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        echo '<div class="wrap"><h1>' . esc_html__('Article Series', 'kwwd-simple-series') . ' <a href="' . admin_url('post-new.php?post_type=' . $this->series_cpt) . '" class="button button-primary">' . esc_html__('Add New Series', 'kwwd-simple-series') . '</a></h1>';
        if (empty($series_list)) {
            echo '<p>' . esc_html__('No series yet.', 'kwwd-simple-series') . '</p>';
        } else {
            echo '<table class="widefat"><thead><tr><th>' . esc_html__('Title', 'kwwd-simple-series') . '</th><th>' . esc_html__('Posts', 'kwwd-simple-series') . '</th><th>' . esc_html__('Shortcode', 'kwwd-simple-series') . '</th><th>' . esc_html__('Date', 'kwwd-simple-series') . '</th><th>' . esc_html__('Actions', 'kwwd-simple-series') . '</th></tr></thead><tbody>';
            foreach ($series_list as $series) {
                $post_ids = get_post_meta($series->ID, $this->series_meta_key, true);
                $count = is_array($post_ids) ? count($post_ids) : 0;
                $shortcode = '[simple_series id=' . $series->ID . ']';
                $edit_link = get_edit_post_link($series->ID);
                $delete_url = get_delete_post_link($series->ID, '', 'false');
                echo '<tr>';
                echo '<td><a href="' . esc_url($edit_link) . '"><strong>' . esc_html($series->post_title) . '</strong></a></td>';
                echo '<td><span class="post-count-badge" style="display:inline-block;padding:2px 8px;background:' . ($count > 0 ? '#4ab866' : '#ccc') . ';color:#fff;border-radius:10px;font-size:12px;font-weight:bold;">' . esc_html($count) . ' post' . ($count !== 1 ? 's' : '') . '</span></td>';
                echo '<td><code style="cursor:pointer" onclick="navigator.clipboard.writeText(this.innerText);jQuery(this).text(\'Copied!\').css(\'background\',\'#4ab866\').css(\'color\',\'#fff\');setTimeout(()=>jQuery(this).text(\'' . esc_js($shortcode) . '\').css(\'background\',\'#f5f5f5\').css(\'color\',\'#333\'),2000);">' . esc_html($shortcode) . '</code></td>';
                echo '<td>' . get_the_date('', $series) . '</td>';
                echo '<td>';
                echo '<a href="' . esc_url($edit_link) . '" class="button button-small">' . esc_html__('Edit', 'kwwd-simple-series') . '</a> ';
                $view_url = get_permalink($series->ID);
                if ($view_url) {
                    echo '<a href="' . esc_url($view_url) . '" class="button button-small" target="_blank">' . esc_html__('View', 'kwwd-simple-series') . '</a> ';
                }
                echo '<a href="' . esc_url($delete_url) . '" class="button button-small button-danger" onclick="return confirm(\'' . esc_js(__('Are you sure you want to delete this series?', 'kwwd-simple-series')) . '\');">' . esc_html__('Delete', 'kwwd-simple-series') . '</a>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }

    public function enqueue_admin_scripts($hook) {
        $screen = get_current_screen();
        if (!$screen) return;

        $is_series_screen = ($screen->post_type === $this->series_cpt);
        $is_post_screen = ($screen->base === 'post' && in_array($screen->post_type, array('post', 'page'), true));
        if (!$is_series_screen && !$is_post_screen) return;

        wp_enqueue_style('kwwd-series-admin', KWWD_SERIES_ASSETS_URL . '/css/admin-series.css', array(), $this->asset_version('assets/css/admin-series.css'));

        if ($is_series_screen) {
            wp_enqueue_script('jquery-ui-sortable');
        }
        wp_enqueue_script('jquery');
        wp_register_script('kwwd-series-admin', KWWD_SERIES_ASSETS_URL . '/js/admin-series.js', array('jquery', 'jquery-ui-sortable'), $this->asset_version('assets/js/admin-series.js'), true);
        wp_localize_script('kwwd-series-admin', 'kwwdSeriesAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kwwd_series_update_order'),
            'create_nonce' => wp_create_nonce('kwwd_series_create'),
            'strings' => array(
                'saved' => __('Order saved!', 'kwwd-simple-series'),
                'add_series' => __('Add New Series', 'kwwd-simple-series'),
                'add_series_success' => __('Series created and assigned to this post.', 'kwwd-simple-series'),
                'add_series_error' => __('Could not create the series.', 'kwwd-simple-series'),
                'duplicate_exists' => __('A series with this name already exists.', 'kwwd-simple-series'),
                'duplicate_add_link' => __('Click here to add this post to the existing series', 'kwwd-simple-series'),
                'duplicate_added' => __('Added to existing series.', 'kwwd-simple-series'),
            ),
        ));
        wp_enqueue_script('kwwd-series-admin');
    }

    private function asset_version($relative_path) {
        $file = KWWD_SERIES_PATH . ltrim($relative_path, '/');
        $mtime = @filemtime($file);
        return $mtime ? $mtime : KWWD_SERIES_VERSION;
    }

    public function enqueue_frontend_scripts() {
        if (is_singular($this->series_cpt) || is_post_type_archive($this->series_cpt)) {
            wp_enqueue_style('kwwd-series-frontend', KWWD_SERIES_ASSETS_URL . '/css/frontend.css', array(), $this->asset_version('assets/css/frontend.css'));
            wp_enqueue_script('kwwd-series-frontend', KWWD_SERIES_ASSETS_URL . '/js/frontend.js', array(), $this->asset_version('assets/js/frontend.js'), true);
        }
    }

    public function series_template_include($template) {
        if (is_singular($this->series_cpt)) {
            $file = KWWD_SERIES_PATH . 'templates/single-series.php';
            return (file_exists($file)) ? $file : $template;
        }
        if (is_post_type_archive($this->series_cpt)) {
            $file = KWWD_SERIES_PATH . 'templates/archive-kwwd_series.php';
            return (file_exists($file)) ? $file : $template;
        }
        return $template;
    }

    public function add_series_meta_boxes() {
        add_meta_box('kwwd_series_description_box', __('Series Description', 'kwwd-simple-series'), array($this, 'render_series_description_box'), $this->series_cpt, 'normal', 'high');
        add_meta_box('kwwd_series_posts_box', __('Posts in This Series (Drag to Order)', 'kwwd-simple-series'), array($this, 'render_series_posts_box'), $this->series_cpt, 'normal', 'high');
        add_meta_box('kwwd_series_shortcode_box', __('Shortcode', 'kwwd-simple-series'), array($this, 'render_shortcode_box'), $this->series_cpt, 'side', 'default');
        add_meta_box('kwwd_series_settings_box', __('Display Settings', 'kwwd-simple-series'), array($this, 'render_series_settings_box'), $this->series_cpt, 'side', 'default');
    }

    public function render_series_description_box($post) {
        $description = get_post_meta($post->ID, '_kwwd_series_description', true);
        echo '<p><strong>' . esc_html__('Description', 'kwwd-simple-series') . '</strong></p>';
        echo '<p style="margin-top:5px;color:#646970;font-size:12px;">' . esc_html__('This displays below the series title on the frontend.', 'kwwd-simple-series') . '</p>';
        echo '<textarea name="kwwd_series_description" rows="4" style="width:100%;">' . esc_textarea($description) . '</textarea>';
    }

    public function render_shortcode_box($post) {
        $shortcode = '[simple_series id=' . $post->ID . ']';
        echo '<p style="margin-bottom:10px;">' . esc_html__('Use this shortcode to display the series anywhere on your site:', 'kwwd-simple-series') . '</p>';
        echo '<code style="display:block;padding:10px;background:#f5f5f5;text-align:center;font-size:16px;cursor:pointer;word-break:break-all;" onclick="navigator.clipboard.writeText(this.innerText);this.text=\'' . esc_js(__('Copied!', 'kwwd-simple-series')) . '\';this.style.background=\'#4ab866\';this.style.color=\'#fff\';var t=this;setTimeout(function(){t.text=\'' . esc_js($shortcode) . '\';t.style.background=\'#f5f5f5\';t.style.color=\'#333\';},2000);">' . esc_html($shortcode) . '</code>';
    }

    public function render_series_posts_box($post) {
        $series_post_ids = get_post_meta($post->ID, $this->series_meta_key, true);
        if (!is_array($series_post_ids)) $series_post_ids = array();
        $ordered_ids = get_post_meta($post->ID, $this->series_order_meta_key, true);
        if (!is_array($ordered_ids)) $ordered_ids = array();
        usort($series_post_ids, function($a, $b) use ($ordered_ids) {
            $order_a = isset($ordered_ids[$a]) ? $ordered_ids[$a] : 999;
            $order_b = isset($ordered_ids[$b]) ? $ordered_ids[$b] : 999;
            return $order_a - $order_b;
        });
        
        // Get all posts and pages for adding
        $posts = get_posts(array('post_type' => array('post', 'page'), 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        
        echo '<div style="margin-bottom:20px;padding:15px;background:#f6f7f7;border-radius:4px;">';
        echo '<strong>' . esc_html__('Add Post or Page', 'kwwd-simple-series') . '</strong><br>';
        echo '<input type="text" id="kwwd-post-search" placeholder="Type to search posts..." style="margin-top:5px;width:100%;max-width:300px;" /> ';
        echo '<div id="kwwd-search-results" style="margin-top:5px;max-height:200px;overflow-y:auto;border:1px solid #ccc;background:#fff;display:none;"></div>';
        echo '<script>jQuery(document).ready(function($){var allPosts=[';
        $first = true;
        foreach ($posts as $p) {
            $series_ids = get_post_meta($p->ID, '_kwwd_post_series_ids', true);
            if (!is_array($series_ids)) $series_ids = array();
            $in_series = in_array($post->ID, $series_ids) ? '1' : '0';
            if (!$first) echo ',';
            echo '{id:' . $p->ID . ',title:"' . esc_js($p->post_title) . '",type:"' . $p->post_type . '",inSeries:' . $in_series . '}';
            $first = false;
        }
        echo '];var $search=$("#kwwd-post-search");var $results=$("#kwwd-search-results");$search.on("input",function(){var q=$(this).val().toLowerCase();$results.empty();if(q.length<2){$results.hide();return}var matches=allPosts.filter(function(p){return p.title.toLowerCase().indexOf(q)>-1});if(matches.length===0){$results.append("<p style=\'padding:10px;color:#646970;\'>No posts found</p>")}else{matches.forEach(function(p){var btn=p.inSeries?"":"<button type=\'button\' class=\'button button-small kwwd-add-single\' data-id=\'"+p.id+"\' data-title=\'"+p.title.replace(/"/g,"&quot;")+"\' style=\'margin-left:10px;\'>Add</button>";var txt=p.inSeries?" - already in this series":"";$results.append("<p style=\'margin:5px;padding:5px;border-bottom:1px solid #eee;\'>"+p.title+" ("+p.type+")"+txt+btn+"</p>")})}$results.show()});$(document).on("click",".kwwd-add-single",function(){var pid=$(this).data("id");var ptitle=$(this).data("title");var li=$("<li>").addClass("kwwd-series-post-item").attr("data-id",pid);li.append($("<span>").addClass("dashicons dashicons-menu")).append(" ").append($("<a>").attr("href","' . admin_url('post.php?post=') . '"+pid+"&action=edit").attr("target","_blank").text(ptitle));li.append($("<input>").attr("type","hidden").attr("name","kwwd_series_posts[]").val(pid));$(".kwwd-series-posts-list").append(li);$(this).prop("disabled",true).text("Added").closest("p").append(" - already in this series")})});</script>';
        echo '<p style="margin-top:10px;margin-bottom:0;font-size:12px;color:#646970;">' . esc_html__('Posts can belong to multiple series.', 'kwwd-simple-series') . '</p>';
        echo '</div>';
        
        echo '<h4>' . esc_html__('Posts in This Series', 'kwwd-simple-series') . '</h4>';
        echo '<ul class="kwwd-series-posts-list" data-series-id="' . esc_attr($post->ID) . '">';
        foreach ($series_post_ids as $pid) {
            $p = get_post($pid);
            if ($p) {
                echo '<li class="kwwd-series-post-item" data-id="' . esc_attr($pid) . '">';
                echo '<span class="dashicons dashicons-menu"></span> ';
                echo '<a href="' . get_edit_post_link($pid) . '" target="_blank">' . esc_html($p->post_title) . '</a>';
                echo ' <a href="#" class="kwwd-remove-post" data-id="' . esc_attr($pid) . '" style="color:#dc3232;margin-left:5px;text-decoration:none;" title="Remove from series">[x]</a>';
                echo '<input type="hidden" name="kwwd_series_posts[]" value="' . esc_attr($pid) . '" />';
                echo '</li>';
            }
        }
        echo '</ul>';
        echo '<script>jQuery(document).ready(function($){$(document).on("click",".kwwd-remove-post",function(e){e.preventDefault();$(this).closest("li").remove()})});</script>';
        if (empty($series_post_ids)) {
            echo '<p class="description" style="padding:15px;background:#f6f7f7;border-radius:4px;">' . esc_html__('No posts in this series yet.', 'kwwd-simple-series') . '</p>';
        }
    }

    public function render_series_settings_box($post) {
        $defaults = get_option('kwwd_series_default_settings', array());
        $override = get_post_meta($post->ID, '_kwwd_series_override', true) ?: '0';
        
        if ($override === '1') {
            $bg_color = get_post_meta($post->ID, '_kwwd_series_bg_color', true) ?: ($defaults['bg_color'] ?? '#f5f5f5');
            $bg_opacity = get_post_meta($post->ID, '_kwwd_series_bg_opacity', true) ?: ($defaults['bg_opacity'] ?? '0.9');
            $font_size = get_post_meta($post->ID, '_kwwd_series_font_size', true) ?: ($defaults['font_size'] ?? '16');
            $text_color = get_post_meta($post->ID, '_kwwd_series_text_color', true) ?: ($defaults['text_color'] ?? '#333333');
            $border_color = get_post_meta($post->ID, '_kwwd_series_border_color', true) ?: ($defaults['border_color'] ?? '#dddddd');
            $border_width = get_post_meta($post->ID, '_kwwd_series_border_width', true) ?: ($defaults['border_width'] ?? '1');
            $border_style = get_post_meta($post->ID, '_kwwd_series_border_style', true) ?: ($defaults['border_style'] ?? 'solid');
            $border_radius = get_post_meta($post->ID, '_kwwd_series_border_radius', true) ?: ($defaults['border_radius'] ?? '8');
            $list_style = get_post_meta($post->ID, '_kwwd_series_list_style', true) ?: ($defaults['list_style'] ?? 'decimal');
            $padding = get_post_meta($post->ID, '_kwwd_series_padding', true) ?: ($defaults['padding'] ?? '20');
            $default_collapsed = $defaults['collapsed'] ?? '0';
        } else {
            $bg_color = $defaults['bg_color'] ?? '#f5f5f5';
            $bg_opacity = $defaults['bg_opacity'] ?? '0.9';
            $font_size = $defaults['font_size'] ?? '16';
            $text_color = $defaults['text_color'] ?? '#333333';
            $border_color = $defaults['border_color'] ?? '#dddddd';
            $border_width = $defaults['border_width'] ?? '1';
            $border_style = $defaults['border_style'] ?? 'solid';
            $border_radius = $defaults['border_radius'] ?? '8';
            $list_style = $defaults['list_style'] ?? 'decimal';
            $padding = $defaults['padding'] ?? '20';
            $default_collapsed = $defaults['collapsed'] ?? '0';
        }
        $collapsed = get_post_meta($post->ID, '_kwwd_series_collapsed', true);
        if ($collapsed === '') {
            $collapsed = $default_collapsed;
        }
        
        $display_position = get_post_meta($post->ID, '_kwwd_series_display_position', true);

        echo '<p><label><input type="checkbox" name="kwwd_series_override" value="1" ' . checked($override, '1', false) . ' /> <strong>' . esc_html__('Override defaults', 'kwwd-simple-series') . '</strong></label></p>';
        echo '<div id="kwwd-series-override-settings" style="display:' . ($override === '1' ? 'block' : 'none') . '">';
        echo '<p><label>' . esc_html__('Display Position', 'kwwd-simple-series') . '</label><br>';
        echo '<select name="kwwd_series_display_position">';
        echo '<option value="" ' . selected($display_position, '', false) . '>' . esc_html__('Default', 'kwwd-simple-series') . '</option>';
        echo '<option value="before_content" ' . selected($display_position, 'before_content', false) . '>' . esc_html__('Above Post Content', 'kwwd-simple-series') . '</option>';
        echo '<option value="after_content" ' . selected($display_position, 'after_content', false) . '>' . esc_html__('Below Post Content', 'kwwd-simple-series') . '</option>';
        echo '<option value="both" ' . selected($display_position, 'both', false) . '>' . esc_html__('Both Above and Below', 'kwwd-simple-series') . '</option>';
        echo '<option value="none" ' . selected($display_position, 'none', false) . '>' . esc_html__('None (use shortcode only)', 'kwwd-simple-series') . '</option>';
        echo '</select></p>';
        echo '<p><label>' . esc_html__('Background Color', 'kwwd-simple-series') . '</label><br>';
        echo '<input type="color" name="kwwd_series_bg_color" value="' . esc_attr($bg_color) . '" /></p>';
        echo '<p><label>' . esc_html__('Background Opacity', 'kwwd-simple-series') . '</label><br>';
        echo '<select name="kwwd_series_bg_opacity">';
        for($i = 0; $i <= 1; $i += 0.05) {
            $val = number_format($i, 2);
            echo '<option value="' . esc_attr($val) . '" ' . selected($bg_opacity, $val, false) . '>' . esc_html($val) . '</option>';
        }
        echo '</select></p>';
        echo '<p><label>' . esc_html__('Font Size (px)', 'kwwd-simple-series') . '</label><br>';
        echo '<input type="number" name="kwwd_series_font_size" value="' . esc_attr($font_size) . '" min="10" max="32" style="width:60px" /></p>';
        echo '<p><label>' . esc_html__('Text Color', 'kwwd-simple-series') . '</label><br>';
        echo '<input type="color" name="kwwd_series_text_color" value="' . esc_attr($text_color) . '" /></p>';
        echo '<p><label>' . esc_html__('Border Color', 'kwwd-simple-series') . '</label><br>';
        echo '<input type="color" name="kwwd_series_border_color" value="' . esc_attr($border_color) . '" /></p>';
        echo '<p><label>' . esc_html__('Border Width (px)', 'kwwd-simple-series') . '</label><br>';
        echo '<input type="number" name="kwwd_series_border_width" value="' . esc_attr($border_width) . '" min="0" max="10" style="width:60px" /></p>';
        echo '<p><label>' . esc_html__('Border Style', 'kwwd-simple-series') . '</label><br>';
        echo '<select name="kwwd_series_border_style">';
        echo '<option value="solid" ' . selected($border_style, 'solid', false) . '>Solid</option>';
        echo '<option value="dotted" ' . selected($border_style, 'dotted', false) . '>Dotted</option>';
        echo '<option value="dashed" ' . selected($border_style, 'dashed', false) . '>Dashed</option>';
        echo '<option value="none" ' . selected($border_style, 'none', false) . '>None</option>';
        echo '</select></p>';
        echo '<p><label>' . esc_html__('Border Radius (px)', 'kwwd-simple-series') . '</label><br>';
        echo '<select name="kwwd_series_border_radius">';
        for($i = 0; $i <= 10; $i++) {
            echo '<option value="' . esc_attr($i) . '" ' . selected($border_radius, strval($i), false) . '>' . esc_html($i) . 'px</option>';
        }
        echo '</select></p>';
        echo '<p><label>' . esc_html__('List Style', 'kwwd-simple-series') . '</label><br>';
        echo '<select name="kwwd_series_list_style">';
        echo '<option value="none" ' . selected($list_style, 'none', false) . '>None</option>';
        echo '<option value="decimal" ' . selected($list_style, 'decimal', false) . '>1. 2. 3.</option>';
        echo '<option value="decimal-leading-zero" ' . selected($list_style, 'decimal-leading-zero', false) . '>01. 02. 03.</option>';
        echo '<option value="lower-alpha" ' . selected($list_style, 'lower-alpha', false) . '>a. b. c.</option>';
        echo '<option value="upper-alpha" ' . selected($list_style, 'upper-alpha', false) . '>A. B. C.</option>';
        echo '<option value="lower-roman" ' . selected($list_style, 'lower-roman', false) . '>i. ii. iii.</option>';
        echo '<option value="upper-roman" ' . selected($list_style, 'upper-roman', false) . '>I. II. III.</option>';
        echo '<option value="lower-greek" ' . selected($list_style, 'lower-greek', false) . '>alpha, beta, gamma</option>';
        echo '<option value="disc" ' . selected($list_style, 'disc', false) . '>Disc (bullet)</option>';
        echo '<option value="circle" ' . selected($list_style, 'circle', false) . '>Circle (bullet)</option>';
        echo '<option value="square" ' . selected($list_style, 'square', false) . '>Square (bullet)</option>';
        echo '</select></p>';
        echo '<p><label>' . esc_html__('Padding (px)', 'kwwd-simple-series') . '</label><br>';
        echo '<select name="kwwd_series_padding">';
        for($i = 0; $i <= 40; $i += 5) {
            echo '<option value="' . esc_attr($i) . '" ' . selected($padding, strval($i), false) . '>' . esc_html($i) . 'px</option>';
        }
        echo '</select></p>';
        echo '<p><label><input type="checkbox" name="kwwd_series_collapsed" value="1" ' . checked($collapsed, '1', true) . ' /> ' . esc_html__('Start collapsed', 'kwwd-simple-series') . '</label></p>';
        echo '</div>';
        echo '<script>jQuery(document).ready(function(){jQuery("input[name=kwwd_series_override]").change(function(){jQuery("#kwwd-series-override-settings").slideToggle(this.checked);});});</script>';
    }

    public function add_post_series_meta_box() {
        add_meta_box('kwwd_post_series_box', __('Series', 'kwwd-simple-series'), array($this, 'render_post_series_box'), array('post', 'page'), 'side', 'default');
    }

    public function render_post_series_box($post) {
        $series_ids = get_post_meta($post->ID, '_kwwd_post_series_ids', true);
        if (!is_array($series_ids)) $series_ids = array();
        $series_list = get_posts(array('post_type' => $this->series_cpt, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        wp_nonce_field('kwwd_post_series_nonce', 'kwwd_post_series_nonce');
        echo '<p><strong>' . esc_html__('Assign to Series', 'kwwd-simple-series') . '</strong></p>';
        if (empty($series_list)) {
            echo '<p id="kwwd-series-empty" style="color:#646970;">' . esc_html__('No series available.', 'kwwd-simple-series') . '</p>';
        } else {
            echo '<div class="kwwd-series-checkboxes" style="max-height:200px;overflow-y:auto;">';
            foreach ($series_list as $series) {
                echo '<p><label>';
                echo '<input type="checkbox" name="kwwd_post_series_ids[]" value="' . esc_attr($series->ID) . '"';
                echo checked(in_array($series->ID, $series_ids), true, false) . ' /> ';
                echo esc_html($series->post_title) . '</label></p>';
            }
            echo '</div>';
        }
        echo '<div class="kwwd-series-add-box">';
        echo '<p style="margin-bottom:4px;"><strong>' . esc_html__('Add New Series', 'kwwd-simple-series') . '</strong></p>';
        echo '<input type="text" id="kwwd-new-series-title" placeholder="' . esc_attr__('New series name', 'kwwd-simple-series') . '" style="width:100%;" />';
        echo '<p style="margin-top:6px;"><button type="button" class="button" id="kwwd-add-series">' . esc_html__('Add New Series', 'kwwd-simple-series') . '</button></p>';
        echo '<p id="kwwd-series-add-msg" class="description" style="margin:6px 0 0;display:none;"></p>';
        echo '</div>';
        $display_position = get_post_meta($post->ID, '_kwwd_series_display_position', true);
        echo '<p style="margin-bottom:4px;"><strong>' . esc_html__('Series Display Position', 'kwwd-simple-series') . '</strong></p>';
        echo '<p style="margin-top:4px;"><select name="kwwd_post_series_display_position" style="width:100%;">';
        echo '<option value="" ' . selected($display_position, '', false) . '>' . esc_html__('Default', 'kwwd-simple-series') . '</option>';
        echo '<option value="before_content" ' . selected($display_position, 'before_content', false) . '>' . esc_html__('Above Post Content', 'kwwd-simple-series') . '</option>';
        echo '<option value="after_content" ' . selected($display_position, 'after_content', false) . '>' . esc_html__('Below Post Content', 'kwwd-simple-series') . '</option>';
        echo '<option value="both" ' . selected($display_position, 'both', false) . '>' . esc_html__('Both Above and Below', 'kwwd-simple-series') . '</option>';
        echo '<option value="none" ' . selected($display_position, 'none', false) . '>' . esc_html__('None (use shortcode only)', 'kwwd-simple-series') . '</option>';
        echo '</select></p>';
        $show_page_link = get_post_meta($post->ID, '_kwwd_series_show_page_link', true);
        echo '<p style="margin-bottom:4px;"><strong>' . esc_html__('Series Page Link', 'kwwd-simple-series') . '</strong></p>';
        echo '<p style="margin-top:4px;"><select name="kwwd_post_series_show_page_link" style="width:100%;">';
        echo '<option value="" ' . selected($show_page_link, '', false) . '>' . esc_html__('Default', 'kwwd-simple-series') . '</option>';
        echo '<option value="1" ' . selected($show_page_link, '1', false) . '>' . esc_html__('Show', 'kwwd-simple-series') . '</option>';
        echo '<option value="0" ' . selected($show_page_link, '0', false) . '>' . esc_html__('Hide', 'kwwd-simple-series') . '</option>';
        echo '</select></p>';
    }

    public function save_series_meta($post_id, $post) {
        if ($post->post_type !== $this->series_cpt) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $old_post_ids = get_post_meta($post_id, $this->series_meta_key, true);
        if (!is_array($old_post_ids)) $old_post_ids = array();

        if (isset($_POST['kwwd_series_posts'])) {
            $post_ids = array_map('intval', $_POST['kwwd_series_posts']);
            $post_ids = array_unique(array_filter($post_ids));
            update_post_meta($post_id, $this->series_meta_key, array_values($post_ids));
        } else {
            $post_ids = array();
            delete_post_meta($post_id, $this->series_meta_key);
        }

        // Update each post's series list - posts can belong to multiple series
        $removed_ids = array_diff($old_post_ids, $post_ids);
        foreach ($removed_ids as $old_id) {
            $post_series = get_post_meta($old_id, '_kwwd_post_series_ids', true);
            if (!is_array($post_series)) $post_series = array();
            $post_series = array_diff($post_series, array($post_id));
            if (empty($post_series)) {
                delete_post_meta($old_id, '_kwwd_post_series_ids');
            } else {
                update_post_meta($old_id, '_kwwd_post_series_ids', array_values($post_series));
            }
        }
        foreach ($post_ids as $p_id) {
            $post_series = get_post_meta($p_id, '_kwwd_post_series_ids', true);
            if (!is_array($post_series)) $post_series = array();
            if (!in_array($post_id, $post_series)) {
                $post_series[] = $post_id;
                update_post_meta($p_id, '_kwwd_post_series_ids', array_values($post_series));
            }
        }

        // Save override setting
        if (isset($_POST['kwwd_series_override'])) {
            update_post_meta($post_id, '_kwwd_series_override', '1');
        } else {
            delete_post_meta($post_id, '_kwwd_series_override');
        }

        // Save description
        if (isset($_POST['kwwd_series_description'])) {
            update_post_meta($post_id, '_kwwd_series_description', sanitize_textarea_field($_POST['kwwd_series_description']));
        }

        $meta_fields = array('kwwd_series_bg_color', 'kwwd_series_bg_opacity', 'kwwd_series_font_size', 'kwwd_series_text_color', 'kwwd_series_border_color', 'kwwd_series_border_width', 'kwwd_series_border_style', 'kwwd_series_border_radius', 'kwwd_series_list_style', 'kwwd_series_padding');
        foreach ($meta_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Handle collapsed - only save if override is enabled
        if (isset($_POST['kwwd_series_override'])) {
            $collapsed = isset($_POST['kwwd_series_collapsed']) ? '1' : '0';
            update_post_meta($post_id, '_kwwd_series_collapsed', $collapsed);
        } else {
            delete_post_meta($post_id, '_kwwd_series_collapsed');
        }

        // Handle display position - only save if override is enabled
        if (isset($_POST['kwwd_series_override'])) {
            $display_position = isset($_POST['kwwd_series_display_position']) ? sanitize_text_field($_POST['kwwd_series_display_position']) : '';
            if ($display_position === '') {
                delete_post_meta($post_id, '_kwwd_series_display_position');
            } else {
                update_post_meta($post_id, '_kwwd_series_display_position', $display_position);
            }
        } else {
            delete_post_meta($post_id, '_kwwd_series_display_position');
        }
    }

    public function save_post_series_assignment($post_id, $post) {
        if (!isset($_POST['kwwd_post_series_nonce']) || !wp_verify_nonce($_POST['kwwd_post_series_nonce'], 'kwwd_post_series_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $old_series_ids = get_post_meta($post_id, '_kwwd_post_series_ids', true);
        if (!is_array($old_series_ids)) $old_series_ids = array();
        $new_series_ids = isset($_POST['kwwd_post_series_ids']) ? array_map('intval', $_POST['kwwd_post_series_ids']) : array();

        // Remove from old series no longer selected
        foreach ($old_series_ids as $old_sid) {
            if (!in_array($old_sid, $new_series_ids)) {
                $series_posts = get_post_meta($old_sid, $this->series_meta_key, true);
                if (is_array($series_posts) && in_array($post_id, $series_posts)) {
                    $series_posts = array_diff($series_posts, array($post_id));
                    if (empty($series_posts)) {
                        delete_post_meta($old_sid, $this->series_meta_key);
                    } else {
                        update_post_meta($old_sid, $this->series_meta_key, array_values($series_posts));
                    }
                }
            }
        }

        // Add to new series
        foreach ($new_series_ids as $new_sid) {
            $series_posts = get_post_meta($new_sid, $this->series_meta_key, true);
            if (!is_array($series_posts)) $series_posts = array();
            if (!in_array($post_id, $series_posts)) {
                $series_posts[] = $post_id;
                update_post_meta($new_sid, $this->series_meta_key, array_values($series_posts));
            }
        }

        if (empty($new_series_ids)) {
            delete_post_meta($post_id, '_kwwd_post_series_ids');
        } else {
            update_post_meta($post_id, '_kwwd_post_series_ids', array_values($new_series_ids));
        }

        $display_position = isset($_POST['kwwd_post_series_display_position']) ? sanitize_text_field($_POST['kwwd_post_series_display_position']) : '';
        if ($display_position === '') {
            delete_post_meta($post_id, '_kwwd_series_display_position');
        } else {
            update_post_meta($post_id, '_kwwd_series_display_position', $display_position);
        }

        $show_page_link = isset($_POST['kwwd_post_series_show_page_link']) ? sanitize_text_field($_POST['kwwd_post_series_show_page_link']) : '';
        if ($show_page_link === '') {
            delete_post_meta($post_id, '_kwwd_series_show_page_link');
        } else {
            update_post_meta($post_id, '_kwwd_series_show_page_link', $show_page_link);
        }
    }

    public function ajax_update_post_order() {
        check_ajax_referer('kwwd_series_update_order', 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error('Unauthorized');

        $series_id = isset($_POST['series_id']) ? intval($_POST['series_id']) : 0;
        $post_orders = isset($_POST['post_orders']) ? $_POST['post_orders'] : array();
        if (!$series_id) wp_send_json_error('Invalid series ID');

        $order_array = array();
        foreach ($post_orders as $index => $post_id) {
            $order_array[intval($post_id)] = intval($index);
        }
        update_post_meta($series_id, $this->series_order_meta_key, $order_array);
        wp_send_json_success('Order saved');
    }

    public function ajax_create_series() {
        check_ajax_referer('kwwd_series_create', 'nonce');
        if (!current_user_can('publish_posts')) wp_send_json_error(__('You are not allowed to create series.', 'kwwd-simple-series'));

        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        if ($title === '') {
            wp_send_json_error(__('Please enter a series name.', 'kwwd-simple-series'));
        }

        // Block duplicates - case-insensitive title match against existing series
        $existing_series = get_posts(array('post_type' => $this->series_cpt, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids'));
        foreach ($existing_series as $existing_id) {
            $existing_title = get_the_title($existing_id);
            if (strcasecmp($title, $existing_title) === 0) {
                wp_send_json_error(array(
                    'code' => 'duplicate',
                    'message' => __('A series with this name already exists.', 'kwwd-simple-series'),
                    'id' => intval($existing_id),
                    'title' => $existing_title,
                ));
            }
        }

        $new_id = wp_insert_post(array(
            'post_type' => $this->series_cpt,
            'post_status' => 'publish',
            'post_title' => $title,
        ));

        if (is_wp_error($new_id) || !$new_id) {
            wp_send_json_error(__('Could not create the series.', 'kwwd-simple-series'));
        }

        wp_send_json_success(array('id' => intval($new_id), 'title' => $title));
    }

    public function display_series_on_content($content) {
        if (!is_singular(array('post', 'page'))) return $content;

        $post_id = get_queried_object_id();
        if (!$post_id) return $content;

        $series_ids = get_post_meta($post_id, '_kwwd_post_series_ids', true);
        if (!is_array($series_ids) || empty($series_ids)) {
            $series_ids = $this->find_series_for_post($post_id);
        }
        if (empty($series_ids)) return $content;

        $defaults = get_option('kwwd_series_default_settings', array());
        $global_position = isset($defaults['display_position']) ? $defaults['display_position'] : 'before_content';
        $post_override = get_post_meta($post_id, '_kwwd_series_display_position', true);

        $before_html = '';
        $after_html = '';

        foreach ($series_ids as $sid) {
            $position = $post_override;
            if ($position === '') {
                $series_override = get_post_meta($sid, '_kwwd_series_display_position', true);
                $position = ($series_override !== '') ? $series_override : $global_position;
            }

            if ($position === 'none') continue;

            $series_html = $this->get_series_html($sid);
            if ($position === 'before_content' || $position === 'both') {
                $before_html .= $series_html;
            }
            if ($position === 'after_content' || $position === 'both') {
                $after_html .= $series_html;
            }
        }

        return $before_html . $content . $after_html;
    }

    private function find_series_for_post($post_id) {
        $series_ids = array();
        $series_list = get_posts(array('post_type' => $this->series_cpt, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids'));
        foreach ($series_list as $series_id) {
            $series_post_ids = get_post_meta($series_id, $this->series_meta_key, true);
            if (is_array($series_post_ids) && in_array($post_id, $series_post_ids)) {
                $series_ids[] = $series_id;
            }
        }
        return $series_ids;
    }

    public function get_series_html($series_id) {
        $series_post = get_post($series_id);
        if (!$series_post) return '';

        $series_post_ids = $this->get_ordered_series_post_ids($series_id);
        if (empty($series_post_ids)) return '';

        $override = get_post_meta($series_id, '_kwwd_series_override', true);
        $defaults = get_option('kwwd_series_default_settings', array());
        
        if ($override === '1') {
            $bg_color = get_post_meta($series_id, '_kwwd_series_bg_color', true) ?: '#f5f5f5';
            $font_size = get_post_meta($series_id, '_kwwd_series_font_size', true) ?: '16';
            $text_color = get_post_meta($series_id, '_kwwd_series_text_color', true) ?: '#333333';
            $bg_opacity = get_post_meta($series_id, '_kwwd_series_bg_opacity', true) ?: '0.9';
            $border_color = get_post_meta($series_id, '_kwwd_series_border_color', true) ?: '#dddddd';
            $border_width = get_post_meta($series_id, '_kwwd_series_border_width', true) ?: '1';
            $border_style = get_post_meta($series_id, '_kwwd_series_border_style', true) ?: 'solid';
            $border_radius = get_post_meta($series_id, '_kwwd_series_border_radius', true) ?: '8';
            $list_style = get_post_meta($series_id, '_kwwd_series_list_style', true) ?: 'decimal';
            $padding = get_post_meta($series_id, '_kwwd_series_padding', true) ?: '20';
        } else {
            $bg_color = $defaults['bg_color'] ?? '#f5f5f5';
            $font_size = $defaults['font_size'] ?? '16';
            $text_color = $defaults['text_color'] ?? '#333333';
            $bg_opacity = $defaults['bg_opacity'] ?? '0.9';
            $border_color = $defaults['border_color'] ?? '#dddddd';
            $border_width = $defaults['border_width'] ?? '1';
            $border_style = $defaults['border_style'] ?? 'solid';
            $border_radius = $defaults['border_radius'] ?? '8';
            $list_style = $defaults['list_style'] ?? 'decimal';
            $padding = $defaults['padding'] ?? '20';
        }
        
        // Collapsed setting - only use series meta if override is enabled
        $collapsed_meta = get_post_meta($series_id, '_kwwd_series_collapsed', true);
        $default_collapsed = $defaults['collapsed'] ?? '0';
        if ($override === '1' && $collapsed_meta !== '') {
            $collapsed = $collapsed_meta;
        } else {
            $collapsed = $default_collapsed;
        }
        $is_collapsed = ($collapsed === '1');

        $rgb = $this->hex_to_rgb($bg_color);
        $rgba = "rgba({$rgb['r']}, {$rgb['g']}, {$rgb['b']}, {$bg_opacity})";
        $border = $border_width . 'px ' . $border_style . ' ' . $border_color;

        $html = '<div class="kwwd-series-container' . ($is_collapsed ? ' kwwd-series-collapsed' : '') . '" id="kwwd-series-' . esc_attr($series_id) . '">';
        
        if (!isset(self::$output_styles['common'])) {
            $html .= '<style>.kwwd-series-container{background:var(--kwwd-bg);color:var(--kwwd-color);font-size:var(--kwwd-font-size);padding:var(--kwwd-pad);margin:20px 0;border-radius:var(--kwwd-radius);border:var(--kwwd-border)}.kwwd-series-title{margin:0 0 10px;font-size:1.3em;font-weight:bold}.kwwd-series-description{margin:0;padding:0;font-size:0.95em;line-height:1.5}.kwwd-series-list{list-style:var(--kwwd-list);margin:0;padding-left:20px}.kwwd-series-list li{padding:5px 0}.kwwd-series-item{color:inherit;text-decoration:none}.kwwd-series-item:hover{text-decoration:underline}.kwwd-series-item.kwwd-series-current{font-weight:bold}.kwwd-series-toggle-link{cursor:pointer;color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-size:inherit;font-weight:inherit;outline:none}.kwwd-series-toggle-link:hover{text-decoration:none}.kwwd-series-toggle-arrow{font-size:0.7em;margin-left:5px}.kwwd-series-hidden{display:none}.kwwd-series-view-link-wrap{margin-top:10px;text-align:right}.kwwd-series-view-link{color:inherit;font-weight:bold;text-decoration:none;font-size:0.9em}.kwwd-series-view-link:hover{text-decoration:underline}</style>';
            self::$output_styles['common'] = true;
        }
        
        $html .= '<style>#kwwd-series-' . esc_attr($series_id) . '{--kwwd-bg:' . esc_attr($rgba) . ';--kwwd-color:' . esc_attr($text_color) . ';--kwwd-font-size:' . esc_attr($font_size) . 'px;--kwwd-pad:' . esc_attr($padding) . 'px;--kwwd-radius:' . esc_attr($border_radius) . 'px;--kwwd-border:' . esc_attr($border) . ';--kwwd-list:' . esc_attr($list_style) . '}</style>';

        if ($is_collapsed) {
            $html .= '<div class="kwwd-series-title">';
            $html .= '<a href="#" class="kwwd-series-toggle-link" data-target="kwwd-series-list-' . esc_attr($series_id) . '">';
            $html .= esc_html($series_post->post_title);
            $html .= ' <span class="kwwd-series-toggle-arrow">&#9660;</span></a>';
            $html .= '</div>';
            $html .= '<ul class="kwwd-series-list kwwd-series-hidden" id="kwwd-series-list-' . esc_attr($series_id) . '">';
        } else {
            $html .= '<div class="kwwd-series-title">' . esc_html($series_post->post_title) . '</div>';
            $html .= '<ul class="kwwd-series-list">';
        }

        // Series description
        $description = get_post_meta($series_id, '_kwwd_series_description', true);
        if ($description) {
            $html .= '<div class="kwwd-series-description">' . wpautop(esc_html($description)) . '</div>';
        }

        global $post;
        $current_post_id = $post->ID;

        foreach ($series_post_ids as $index => $post_id) {
            $series_post = get_post($post_id);
            if (!$series_post) continue;

            $is_current = ($post_id === $current_post_id);
            $title = $series_post->post_title;
            $link = get_permalink($post_id);

            $html .= '<li>';
            if ($link) {
                $html .= '<a href="' . esc_url($link) . '" class="kwwd-series-item' . ($is_current ? ' kwwd-series-current' : '') . '">' . esc_html($title) . '</a>';
            } else {
                $html .= '<span class="kwwd-series-item' . ($is_current ? ' kwwd-series-current' : '') . '">' . esc_html($title) . '</span>';
            }
            $html .= '</li>';
        }

        $html .= '</ul>';

        // Series page link
        $global_link = $defaults['series_page_link'] ?? '1';
        $post_link_override = get_post_meta($current_post_id, '_kwwd_series_show_page_link', true);
        $show_page_link = ($post_link_override === '') ? $global_link : $post_link_override;
        $show_archive_link = $defaults['archive_page_link'] ?? '1';

        if ($show_page_link === '1' || $show_archive_link === '1') {
            $html .= '<div class="kwwd-series-view-link-wrap">';
            if ($show_page_link === '1') {
                $series_page_url = get_permalink($series_id);
                if ($series_page_url) {
                    $html .= '<a class="kwwd-series-view-link" href="' . esc_url($series_page_url) . '">' . esc_html__('View Series Page', 'kwwd-simple-series') . '</a>';
                }
            }
            if ($show_archive_link === '1') {
                $archive_url = get_post_type_archive_link($this->series_cpt);
                if ($archive_url) {
                    if ($show_page_link === '1' && $series_page_url) {
                        $html .= '<span class="kwwd-series-view-sep">&nbsp;&middot;&nbsp;</span>';
                    }
                    $html .= '<a class="kwwd-series-view-link" href="' . esc_url($archive_url) . '">' . esc_html__('View All Series', 'kwwd-simple-series') . '</a>';
                }
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    private function get_ordered_series_post_ids($series_id) {
        $ids = get_post_meta($series_id, $this->series_meta_key, true);
        if (!is_array($ids)) $ids = array();
        $ordered = get_post_meta($series_id, $this->series_order_meta_key, true);
        if (!is_array($ordered)) $ordered = array();
        usort($ids, function($a, $b) use ($ordered) {
            $order_a = isset($ordered[$a]) ? $ordered[$a] : 999;
            $order_b = isset($ordered[$b]) ? $ordered[$b] : 999;
            return $order_a - $order_b;
        });
        return $ids;
    }

    private function get_series_image_html($series_id, $size, $allow_fallback) {
        if (has_post_thumbnail($series_id)) {
            return '<div class="kwwd-series-image">' . get_the_post_thumbnail($series_id, $size) . '</div>';
        }
        if (!$allow_fallback) return '';
        foreach ($this->get_ordered_series_post_ids($series_id) as $pid) {
            if (has_post_thumbnail($pid)) {
                return '<div class="kwwd-series-image">' . get_the_post_thumbnail($pid, $size) . '</div>';
            }
        }
        return '';
    }

    public function get_series_page_html($series_id) {
        $series_post = get_post($series_id);
        if (!$series_post) return '';

        $page_options = get_option('kwwd_series_page_settings', array());
        $archive_options = get_option('kwwd_series_archive_settings', array());
        $html = '';

        $link_mode = isset($archive_options['archive_link_mode']) ? $archive_options['archive_link_mode'] : 'page';
        $archive_url = get_post_type_archive_link($this->series_cpt);
        if ($link_mode === 'page' && $archive_url) {
            $html .= '<p class="kwwd-series-archive-link"><a href="' . esc_url($archive_url) . '">&larr; ' . esc_html__('All Series', 'kwwd-simple-series') . '</a></p>';
        }

        $show_image = isset($page_options['series_page_show_image']) ? $page_options['series_page_show_image'] : '1';
        if ($show_image === '1') {
            $fallback = isset($page_options['series_page_fallback_first_post_image']) ? $page_options['series_page_fallback_first_post_image'] : '1';
            $html .= $this->get_series_image_html($series_id, 'large', ($fallback === '1'));
        }

        $html .= '<h1 class="kwwd-series-page-title">' . esc_html($series_post->post_title) . '</h1>';

        $show_desc = isset($page_options['series_page_show_description']) ? $page_options['series_page_show_description'] : '1';
        if ($show_desc === '1') {
            $description = get_post_meta($series_id, '_kwwd_series_description', true);
            if ($description) {
                $html .= '<div class="kwwd-series-page-description">' . wpautop(esc_html($description)) . '</div>';
            }
        }

        $post_ids = $this->get_ordered_series_post_ids($series_id);
        $layout = isset($page_options['series_page_layout']) ? $page_options['series_page_layout'] : 'list';
        $show_post_image = isset($page_options['series_page_show_post_image']) ? $page_options['series_page_show_post_image'] : '1';

        if (empty($post_ids)) {
            $html .= '<p class="kwwd-series-page-empty">' . esc_html__('No posts in this series yet.', 'kwwd-simple-series') . '</p>';
        } elseif ($layout === 'grid') {
            $html .= '<div class="kwwd-series-page-grid">';
            foreach ($post_ids as $pid) {
                $p = get_post($pid);
                if (!$p) continue;
                $card = '<a class="kwwd-series-grid-card" href="' . esc_url(get_permalink($pid)) . '">';
                if ($show_post_image === '1' && has_post_thumbnail($pid)) {
                    $card .= get_the_post_thumbnail($pid, 'medium_large');
                }
                $card .= '<span class="kwwd-series-grid-title">' . esc_html($p->post_title) . '</span>';
                $html .= $card . '</a>';
            }
            $html .= '</div>';
        } else {
            $html .= '<ol class="kwwd-series-page-list">';
            foreach ($post_ids as $pid) {
                $p = get_post($pid);
                if (!$p) continue;
                $html .= '<li>';
                if ($show_post_image === '1' && has_post_thumbnail($pid)) {
                    $html .= '<span class="kwwd-series-list-thumb">' . get_the_post_thumbnail($pid, 'thumbnail') . '</span>';
                }
                $html .= '<a href="' . esc_url(get_permalink($pid)) . '">' . esc_html($p->post_title) . '</a>';
                $html .= '</li>';
            }
            $html .= '</ol>';
        }

        return $html;
    }

    public function get_series_archive_html() {
        $archive_options = get_option('kwwd_series_archive_settings', array());
        $show_image = isset($archive_options['archive_show_image']) ? $archive_options['archive_show_image'] : '1';
        $fallback = isset($archive_options['archive_fallback_first_post_image']) ? $archive_options['archive_fallback_first_post_image'] : '1';
        $show_count = isset($archive_options['archive_show_count']) ? $archive_options['archive_show_count'] : '1';
        $show_desc = isset($archive_options['archive_show_description']) ? $archive_options['archive_show_description'] : '1';
        $link_mode = isset($archive_options['archive_link_mode']) ? $archive_options['archive_link_mode'] : 'page';
        $layout = isset($archive_options['archive_layout']) ? $archive_options['archive_layout'] : 'grid';

        $series_list = get_posts(array('post_type' => $this->series_cpt, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC'));
        $items = array();
        foreach ($series_list as $series) {
            $count = 0;
            foreach ($this->get_ordered_series_post_ids($series->ID) as $pid) {
                if (get_post_status($pid) === 'publish') $count++;
            }
            if ($count > 0) {
                $items[] = array('series' => $series, 'count' => $count);
            }
        }

        if (empty($items)) {
            return '<p class="kwwd-series-archive-empty">' . esc_html__('No series yet.', 'kwwd-simple-series') . '</p>';
        }

        if ($layout === 'list') {
            $html = '<div class="kwwd-series-archive-list">';
            foreach ($items as $item) {
                $series = $item['series'];
                $page_url = get_permalink($series->ID);
                $card = '<article class="kwwd-series-list-card">';
                if ($show_image === '1') {
                    $card .= '<div class="kwwd-series-list-card-image">' . $this->get_series_image_html($series->ID, 'medium', ($fallback === '1')) . '</div>';
                }
                $card .= '<div class="kwwd-series-list-card-body">';
                $card .= '<h2 class="kwwd-series-card-title">' . esc_html($series->post_title) . '</h2>';
                if ($show_desc === '1') {
                    $desc = get_post_meta($series->ID, '_kwwd_series_description', true);
                    if ($desc) {
                        $card .= '<div class="kwwd-series-card-desc">' . esc_html(wp_trim_words($desc, 20, '...')) . '</div>';
                    }
                }
                $card .= '<div class="kwwd-series-list-card-footer">';
                if ($show_count === '1') {
                    $card .= '<span class="kwwd-series-card-count">' . sprintf(esc_html(_n('%s post', '%s posts', $item['count'], 'kwwd-simple-series')), $item['count']) . '</span>';
                }
                if ($link_mode === 'expand') {
                    $card .= '<span class="kwwd-series-card-expand" data-target="kwwd-archive-list-' . esc_attr($series->ID) . '">' . esc_html__('View posts', 'kwwd-simple-series') . '</span>';
                } else {
                    $card .= '<a class="kwwd-series-card-link" href="' . esc_url($page_url) . '">' . esc_html__('View Series Page', 'kwwd-simple-series') . '</a>';
                }
                $card .= '</div>';
                if ($link_mode === 'expand') {
                    $card .= $this->get_archive_post_list($series->ID);
                }
                $card .= '</div>';
                $html .= $card . '</article>';
            }
            $html .= '</div>';
            return $html;
        }

        $html = '<div class="kwwd-series-archive-grid">';
        foreach ($items as $item) {
            $series = $item['series'];
            $page_url = get_permalink($series->ID);
            $card = '<article class="kwwd-series-card">';
            if ($show_image === '1') {
                $card .= $this->get_series_image_html($series->ID, 'medium', ($fallback === '1'));
            }
            $card .= '<h2 class="kwwd-series-card-title">' . esc_html($series->post_title) . '</h2>';
            if ($show_count === '1') {
                $card .= '<span class="kwwd-series-card-count">' . sprintf(esc_html(_n('%s post', '%s posts', $item['count'], 'kwwd-simple-series')), $item['count']) . '</span>';
            }
            if ($show_desc === '1') {
                $desc = get_post_meta($series->ID, '_kwwd_series_description', true);
                if ($desc) {
                    $card .= '<div class="kwwd-series-card-desc">' . esc_html(wp_trim_words($desc, 20, '...')) . '</div>';
                }
            }
            if ($link_mode === 'expand') {
                $card .= '<span class="kwwd-series-card-expand" data-target="kwwd-archive-list-' . esc_attr($series->ID) . '">' . esc_html__('View posts', 'kwwd-simple-series') . '</span>';
                $card .= $this->get_archive_post_list($series->ID);
            } else {
                $card .= '<a class="kwwd-series-card-link" href="' . esc_url($page_url) . '">' . esc_html__('View Series Page', 'kwwd-simple-series') . '</a>';
            }
            $html .= $card . '</article>';
        }
        $html .= '</div>';
        return $html;
    }

    private function get_archive_post_list($series_id) {
        $html = '<ul class="kwwd-series-archive-posts" id="kwwd-archive-list-' . esc_attr($series_id) . '" style="display:none;">';
        foreach ($this->get_ordered_series_post_ids($series_id) as $pid) {
            $p = get_post($pid);
            if (!$p || get_post_status($pid) !== 'publish') continue;
            $html .= '<li><a href="' . esc_url(get_permalink($pid)) . '">' . esc_html($p->post_title) . '</a></li>';
        }
        $html .= '</ul>';
        return $html;
    }

    public function print_collapsible_script() {
        if (!is_singular(array('post', 'page'))) return;
        ?>
        <script>
        (function(){
            var links = document.querySelectorAll('.kwwd-series-toggle-link');
            links.forEach(function(link){
                link.addEventListener('click', function(e){
                    e.preventDefault();
                    var target = document.getElementById(link.getAttribute('data-target'));
                    var arrow = link.querySelector('.kwwd-series-toggle-arrow');
                    if(target) {
                        target.classList.toggle('kwwd-series-hidden');
                    }
                    if(arrow) {
                        arrow.innerHTML = target.classList.contains('kwwd-series-hidden') ? '&#9660;' : '&#9650;';
                    }
                });
            });
        })();
        </script>
        <?php
    }

    public function shortcode_series($atts) {
        $atts = shortcode_atts(array('id' => 0), $atts);
        $series_id = intval($atts['id']);
        if (!$series_id) return '';
        return $this->get_series_html($series_id);
    }

    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $int = hexdec($hex);
        return array('r' => ($int >> 16) & 255, 'g' => ($int >> 8) & 255, 'b' => $int & 255);
    }
}

function KWWD_Series() {
    return KWWD_Series_Plugin::get_instance();
}

KWWD_Series();

register_activation_hook(__FILE__, array('KWWD_Series_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('KWWD_Series_Plugin', 'deactivate'));