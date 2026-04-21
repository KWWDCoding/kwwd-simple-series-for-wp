<?php
/**
 * Plugin Name: Simple Series by KWWD
 * Plugin URI: https://kwwdcoding.github.io/kwwd-simple-series.html
 * Description: Create and manage series which allows you to collate posts and pages together to enable users to view all related posts
 * Version: 1.4.1
 * Author:      KWWD
 * License:     GPL3
 * Licence URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Update URI: https://raw.githubusercontent.com/KWWDCoding/kwwd-simple-series-for-wp/main/assets/';
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


define('KWWD_SERIES_VERSION', '1.4.1');
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('save_post', array($this, 'save_series_meta'), 10, 2);
        add_action('save_post', array($this, 'save_post_series_assignment'), 11, 2);
        add_action('add_meta_boxes', array($this, 'add_series_meta_boxes'));
        add_action('add_meta_boxes', array($this, 'add_post_series_meta_box'));
        add_action('wp_ajax_kwwd_series_update_post_order', array($this, 'ajax_update_post_order'));
        add_action('the_content', array($this, 'display_series_on_content'));
        add_action('wp_footer', array($this, 'print_collapsible_script'), 99);
        add_shortcode('simple_series', array($this, 'shortcode_series'));
    }

    public function register_series_post_type() {
        register_post_type($this->series_cpt, array(
            'label' => __('Series', 'kwwd-simple-series'),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'menu_icon' => 'dashicons-book',
            'supports' => array('title', 'custom-fields'),
            'capability_type' => 'post',
            'capabilities' => array('edit_posts' => 'edit_posts', 'publish_posts' => 'publish_posts'),
            'map_meta_cap' => true,
            'has_archive' => false,
            'rewrite' => false,
        ));
    }

    public function add_admin_menu() {
        add_menu_page(__('Series', 'kwwd-simple-series'), __('Series', 'kwwd-simple-series'), 'edit_posts', 'kwwd-simple-series', array($this, 'render_series_admin_page'), 'dashicons-book', 50);
        add_submenu_page('kwwd-simple-series', __('All Series', 'kwwd-simple-series'), __('All Series', 'kwwd-simple-series'), 'edit_posts', 'kwwd-simple-series', array($this, 'render_series_admin_page'));
        add_submenu_page('kwwd-simple-series', __('Add New', 'kwwd-simple-series'), __('Add New', 'kwwd-simple-series'), 'edit_posts', 'post-new.php?post_type=' . $this->series_cpt);
        add_submenu_page('kwwd-simple-series', __('Settings', 'kwwd-simple-series'), __('Settings', 'kwwd-simple-series'), 'manage_options', 'kwwd-simple-series-settings', array($this, 'render_settings_page'));
    }

    public function render_settings_page() {
        $options = get_option('kwwd_series_default_settings', array(
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

        if (isset($_POST['save_defaults']) && check_admin_referer('kwwd_series_save_settings')) {
            $options = array(
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
            echo '<div class="notice notice-success"><p>Default settings saved.</p></div>';
        }

        echo '<div class="wrap"><h1>' . esc_html__('Series Default Settings', 'kwwd-simple-series') . '</h1>';
        echo '<form method="post">' . wp_nonce_field('kwwd_series_save_settings', '_wpnonce', true, false) . '<table class="form-table">';
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
        echo '</table><p><input type="submit" name="save_defaults" class="button button-primary" value="' . esc_attr__('Save Default Settings', 'kwwd-simple-series') . '" /></p></form></div>';
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
        if ($screen && $screen->post_type === $this->series_cpt) {
            wp_enqueue_script('jquery');
            wp_enqueue_script('jquery-ui-sortable');
            wp_register_script('kwwd-series-admin', KWWD_SERIES_ASSETS_URL . '/js/admin-series.js', array('jquery', 'jquery-ui-sortable'), KWWD_SERIES_VERSION, true);
            wp_localize_script('kwwd-series-admin', 'kwwdSeriesAdmin', array('ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('kwwd_series_update_order'), 'strings' => array('saved' => __('Order saved!', 'kwwd-simple-series'))));
            wp_enqueue_script('kwwd-series-admin');
        }
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
        
        echo '<p><label><input type="checkbox" name="kwwd_series_override" value="1" ' . checked($override, '1', false) . ' /> <strong>' . esc_html__('Override defaults', 'kwwd-simple-series') . '</strong></label></p>';
        echo '<div id="kwwd-series-override-settings" style="display:' . ($override === '1' ? 'block' : 'none') . '">';
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
            echo '<p style="color:#646970;">' . esc_html__('No series available.', 'kwwd-simple-series') . '</p>';
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

    public function display_series_on_content($content) {
        if (!is_singular(array('post', 'page'))) return $content;

        $post_id = get_queried_object_id();
        if (!$post_id) return $content;

        $options = get_option('kwwd_series_settings', array());
        $display_position = isset($options['display_position']) ? $options['display_position'] : 'after_content';
        if ($display_position === 'none') return $content;

        $series_ids = get_post_meta($post_id, '_kwwd_post_series_ids', true);
        if (!is_array($series_ids) || empty($series_ids)) {
            $series_ids = $this->find_series_for_post($post_id);
        }
        if (empty($series_ids)) return $content;

        $series_html = '';
        foreach ($series_ids as $sid) {
            $series_html .= $this->get_series_html($sid);
        }

        if ($display_position === 'before_content') {
            return $series_html . $content;
        } elseif ($display_position === 'after_content') {
            return $content . $series_html;
        }
        return $content;
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

        $series_post_ids = get_post_meta($series_id, $this->series_meta_key, true);
        if (!is_array($series_post_ids) || empty($series_post_ids)) return '';

        $ordered_ids = get_post_meta($series_id, $this->series_order_meta_key, true);
        if (!is_array($ordered_ids)) $ordered_ids = array();

        usort($series_post_ids, function($a, $b) use ($ordered_ids) {
            $order_a = isset($ordered_ids[$a]) ? $ordered_ids[$a] : 999;
            $order_b = isset($ordered_ids[$b]) ? $ordered_ids[$b] : 999;
            return $order_a - $order_b;
        });

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
            $html .= '<style>.kwwd-series-container{background:var(--kwwd-bg);color:var(--kwwd-color);font-size:var(--kwwd-font-size);padding:var(--kwwd-pad);margin:20px 0;border-radius:var(--kwwd-radius);border:var(--kwwd-border)}.kwwd-series-title{margin:0 0 10px;font-size:1.3em;font-weight:bold}.kwwd-series-description{margin:0;padding:0;font-size:0.95em;line-height:1.5}.kwwd-series-list{list-style:var(--kwwd-list);margin:0;padding-left:20px}.kwwd-series-list li{padding:5px 0}.kwwd-series-item{color:inherit;text-decoration:none}.kwwd-series-item:hover{text-decoration:underline}.kwwd-series-item.kwwd-series-current{font-weight:bold}.kwwd-series-toggle-link{cursor:pointer;color:inherit;text-decoration:none;display:inline-flex;align-items:center;gap:8px;font-size:inherit;font-weight:inherit;outline:none}.kwwd-series-toggle-link:hover{text-decoration:none}.kwwd-series-toggle-arrow{font-size:0.7em;margin-left:5px}.kwwd-series-hidden{display:none}</style>';
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

        $html .= '</ul></div>';
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